<?php

declare(strict_types=1);

require_once __DIR__ . '/file_scope.functions.php';

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * POSIX permission diagnostics shared by the file-integrity health scan and
 * command-line tooling.
 *
 * This module is DB-free and read-only. Remediation commands are returned as
 * plain text for an administrator; TeamPass never executes them.
 */

/**
 * Return a stable permission report shell.
 *
 * @return array<string,mixed>
 */
function tpFilePermissionsDefaultReport(): array
{
    return [
        'has_result' => false,
        'scan_supported' => false,
        'remediation_supported' => false,
        'status' => 'not_run',
        'platform' => [
            'os_family' => '',
            'distribution_id' => '',
            'distribution_name' => '',
            'distribution_version' => '',
            'family' => 'unsupported',
        ],
        'identity' => [
            'web_user' => '',
            'web_group' => '',
            'source' => 'unknown',
            'uid' => null,
            'gids' => [],
        ],
        'counts' => [
            'checked' => 0,
            'issues' => 0,
            'errors' => 0,
            'warnings' => 0,
            'protected_writable' => 0,
            'runtime_not_writable' => 0,
            'insecure_modes' => 0,
            'missing_required' => 0,
        ],
        'issues' => [],
    ];
}

/**
 * Parse the simple KEY=VALUE format used by /etc/os-release.
 *
 * @return array<string,string>
 */
function tpFilePermissionsParseOsRelease(string $contents): array
{
    $metadata = [];
    foreach (preg_split('/\R/', $contents) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', $line, $match) !== 1) {
            continue;
        }
        $value = trim($match[2]);
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
            $value = substr($value, 1, -1);
        }
        $metadata[$match[1]] = stripcslashes($value);
    }

    return $metadata;
}

/**
 * Detect a supported Linux distribution family.
 *
 * Only Debian/Ubuntu and the common RHEL derivatives receive remediation
 * commands. Other operating systems remain explicitly unsupported.
 *
 * @return array<string,mixed>
 */
function tpFilePermissionsDetectPlatform(?string $osReleaseContents = null, ?string $osFamily = null): array
{
    $detectedOsFamily = $osFamily ?? PHP_OS_FAMILY;
    $platform = [
        'os_family' => $detectedOsFamily,
        'distribution_id' => '',
        'distribution_name' => '',
        'distribution_version' => '',
        'family' => 'unsupported',
        'scan_supported' => $detectedOsFamily === 'Linux',
        'remediation_supported' => false,
    ];
    if ($detectedOsFamily !== 'Linux') {
        return $platform;
    }

    if ($osReleaseContents === null) {
        $read = @file_get_contents('/etc/os-release');
        $osReleaseContents = is_string($read) ? $read : '';
    }
    $metadata = tpFilePermissionsParseOsRelease($osReleaseContents);
    $id = strtolower(trim((string) ($metadata['ID'] ?? '')));
    $debianIds = ['debian', 'ubuntu'];
    $rhelIds = ['rhel', 'rocky', 'almalinux', 'centos'];
    if (in_array($id, $debianIds, true)) {
        $platform['family'] = 'debian';
        $platform['remediation_supported'] = true;
    } elseif (in_array($id, $rhelIds, true)) {
        $platform['family'] = 'rhel';
        $platform['remediation_supported'] = true;
    }

    $platform['distribution_id'] = $id;
    $platform['distribution_name'] = trim((string) ($metadata['NAME'] ?? $id));
    $platform['distribution_version'] = trim((string) ($metadata['VERSION_ID'] ?? ''));

    return $platform;
}

/**
 * Resolve the web identity whose effective access should be audited.
 *
 * A non-root scan uses its current process identity, which is how the Health
 * background task runs. A root CLI scan falls back to the distribution's
 * conventional PHP/web account so root's access does not create false alarms.
 *
 * @param array<string,mixed> $platform
 *
 * @return array<string,mixed>
 */
function tpFilePermissionsResolveIdentity(array $platform): array
{
    $defaultUser = ($platform['family'] ?? '') === 'rhel' ? 'apache' : 'www-data';
    $selectedUser = $defaultUser;
    $source = 'distribution_default';
    $currentUid = null;
    $currentUser = '';

    if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
        $currentUid = @posix_geteuid();
        $currentInfo = is_int($currentUid) ? @posix_getpwuid($currentUid) : false;
        if (is_array($currentInfo) && is_string($currentInfo['name'] ?? null)) {
            $currentUser = $currentInfo['name'];
        }
        if (is_int($currentUid) && $currentUid !== 0 && $currentUser !== '') {
            $selectedUser = $currentUser;
            $source = 'current_process';
        }
    }

    $uid = null;
    $primaryGid = null;
    $groupName = $selectedUser;
    if (function_exists('posix_getpwnam')) {
        $selectedInfo = @posix_getpwnam($selectedUser);
        if (is_array($selectedInfo)) {
            $uid = is_int($selectedInfo['uid'] ?? null) ? $selectedInfo['uid'] : null;
            $primaryGid = is_int($selectedInfo['gid'] ?? null) ? $selectedInfo['gid'] : null;
        }
    }
    if ($uid === null && $source === 'current_process' && is_int($currentUid)) {
        $uid = $currentUid;
    }
    if ($primaryGid === null && $source === 'current_process' && function_exists('posix_getegid')) {
        $detectedGid = @posix_getegid();
        $primaryGid = is_int($detectedGid) ? $detectedGid : null;
    }

    $gids = $primaryGid === null ? [] : [$primaryGid];
    if ($uid !== null && function_exists('posix_getgrouplist')) {
        $detectedGroups = @posix_getgrouplist($selectedUser, $primaryGid ?? 0);
        if (is_array($detectedGroups)) {
            $gids = array_values(array_unique(array_map('intval', $detectedGroups)));
        }
    } elseif ($source === 'current_process' && function_exists('posix_getgroups')) {
        $detectedGroups = @posix_getgroups();
        if (is_array($detectedGroups)) {
            $gids = array_values(array_unique(array_map('intval', $detectedGroups)));
        }
    }
    if ($primaryGid !== null && function_exists('posix_getgrgid')) {
        $groupInfo = @posix_getgrgid($primaryGid);
        if (is_array($groupInfo) && is_string($groupInfo['name'] ?? null) && $groupInfo['name'] !== '') {
            $groupName = $groupInfo['name'];
        }
    }

    return [
        'web_user' => $selectedUser,
        'web_group' => $groupName,
        'source' => $source,
        'uid' => $uid,
        'gids' => $gids,
    ];
}

/**
 * Return the runtime paths which share the installer/upgrade permission model.
 *
 * @return array<int,array{path:string,required:bool}>
 */
function tpFilePermissionsRuntimeRules(): array
{
    return [
        ['path' => 'storage', 'required' => true],
        ['path' => 'storage/logs', 'required' => true],
        ['path' => 'storage/files', 'required' => true],
        ['path' => 'storage/upload', 'required' => false],
        ['path' => 'storage/backups', 'required' => false],
        ['path' => 'public/assets/avatars', 'required' => false],
        ['path' => 'app/includes/libraries/csrfp/log', 'required' => true],
        ['path' => 'app/websocket/logs', 'required' => false],
    ];
}

/**
 * Tell whether a relative path is a rule root or one of its descendants.
 */
function tpFilePermissionsPathIsWithin(string $relativePath, string $ruleRoot): bool
{
    return $relativePath === $ruleRoot || str_starts_with($relativePath, $ruleRoot . '/');
}

/**
 * Classify a TeamPass path for its normal runtime permission posture.
 */
function tpFilePermissionsClassifyPath(string $relativePath): string
{
    foreach (tpFilePermissionsRuntimeRules() as $rule) {
        if (tpFilePermissionsPathIsWithin($relativePath, $rule['path'])) {
            return 'runtime';
        }
    }
    if (tpFilePermissionsPathIsWithin($relativePath, 'secrets')) {
        return 'secret';
    }
    foreach (['files', 'upload', 'backups'] as $legacyDataRoot) {
        if (tpFilePermissionsPathIsWithin($relativePath, $legacyDataRoot)) {
            return 'instance_data';
        }
    }

    return 'protected';
}

/**
 * Test an effective POSIX permission for the selected web identity.
 *
 * @param array<string,mixed> $identity
 */
function tpFilePermissionsIdentityHasAccess(string $path, array $identity, int $requiredBits): bool
{
    if (($identity['source'] ?? '') === 'current_process') {
        $readable = ($requiredBits & 4) === 0 || is_readable($path);
        $writable = ($requiredBits & 2) === 0 || is_writable($path);
        $executable = ($requiredBits & 1) === 0 || is_executable($path);

        return $readable && $writable && $executable;
    }

    $stat = @stat($path);
    $uid = $identity['uid'] ?? null;
    $gids = is_array($identity['gids'] ?? null) ? $identity['gids'] : [];
    if (is_array($stat) && is_int($uid)) {
        $mode = (int) $stat['mode'] & 0777;
        if ((int) $stat['uid'] === $uid) {
            $available = ($mode >> 6) & 7;
        } elseif (in_array((int) $stat['gid'], array_map('intval', $gids), true)) {
            $available = ($mode >> 3) & 7;
        } else {
            $available = $mode & 7;
        }

        return ($available & $requiredBits) === $requiredBits;
    }

    $readable = ($requiredBits & 4) === 0 || is_readable($path);
    $writable = ($requiredBits & 2) === 0 || is_writable($path);
    $executable = ($requiredBits & 1) === 0 || is_executable($path);

    return $readable && $writable && $executable;
}

/**
 * Append one normalized permission issue and update counters.
 *
 * @param array<string,mixed> $report
 */
function tpFilePermissionsAddIssue(
    array &$report,
    string $path,
    string $reason,
    string $severity,
    string $actualMode,
    string $expected
): void {
    $report['issues'][] = [
        'path' => $path,
        'reason' => $reason,
        'severity' => $severity,
        'actual_mode' => $actualMode,
        'expected' => $expected,
    ];
    $report['counts']['issues']++;
    $report['counts'][$severity === 'error' ? 'errors' : 'warnings']++;
    if ($reason === 'protected_writable') {
        $report['counts']['protected_writable']++;
    }
    if ($reason === 'runtime_not_writable') {
        $report['counts']['runtime_not_writable']++;
    }
    if (in_array($reason, ['world_writable', 'group_writable', 'runtime_world_accessible', 'secret_world_accessible', 'secret_group_writable', 'instance_data_world_accessible'], true)) {
        $report['counts']['insecure_modes']++;
    }
    if ($reason === 'required_path_missing') {
        $report['counts']['missing_required']++;
    }
}

/**
 * Inspect every runtime-relevant TeamPass file and directory against the
 * permission policy. Repository and development-only artifacts are neutral.
 *
 * Symbolic-link modes are deliberately ignored: POSIX reports them as 0777 and
 * enforces permissions on their targets, which are scanned separately.
 *
 * @param array<string,mixed>|null $platformOverride Test-only platform metadata
 * @param array<string,mixed>|null $identityOverride Test-only web identity
 *
 * @return array<string,mixed>
 */
function tpFilePermissionsScan(
    string $root,
    ?array $platformOverride = null,
    ?array $identityOverride = null
): array {
    $report = tpFilePermissionsDefaultReport();
    $rootReal = realpath($root);
    if ($rootReal === false || is_dir($rootReal) === false) {
        throw new RuntimeException('The TeamPass root directory is invalid for the permission scan.');
    }

    $platform = $platformOverride ?? tpFilePermissionsDetectPlatform();
    $identity = $identityOverride ?? tpFilePermissionsResolveIdentity($platform);
    $report['platform'] = $platform;
    $report['identity'] = $identity;
    $report['scan_supported'] = (bool) ($platform['scan_supported'] ?? false);
    $report['remediation_supported'] = (bool) ($platform['remediation_supported'] ?? false);
    if ($report['scan_supported'] === false) {
        $report['status'] = 'unsupported';
        return $report;
    }

    $report['has_result'] = true;
    foreach (tpFilePermissionsRuntimeRules() as $rule) {
        $absolute = $rootReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rule['path']);
        if ($rule['required'] && is_dir($absolute) === false) {
            tpFilePermissionsAddIssue(
                $report,
                $rule['path'],
                'required_path_missing',
                'error',
                '-',
                'runtime-directory'
            );
        }
    }

    $inspect = static function (string $absolutePath, string $relativePath) use (&$report, $identity): void {
        if (is_link($absolutePath)) {
            return;
        }
        $modeValue = @fileperms($absolutePath);
        if ($modeValue === false) {
            tpFilePermissionsAddIssue($report, $relativePath, 'inspection_failed', 'error', '-', 'inspectable');
            return;
        }
        $mode = $modeValue & 0777;
        $actualMode = sprintf('%04o', $mode);
        $isDirectory = is_dir($absolutePath);
        $classification = tpFilePermissionsClassifyPath($relativePath);
        $readBits = $isDirectory ? 5 : 4;
        $writeBits = $isDirectory ? 3 : 2;
        $readable = tpFilePermissionsIdentityHasAccess($absolutePath, $identity, $readBits);
        $writable = tpFilePermissionsIdentityHasAccess($absolutePath, $identity, $writeBits);
        $worldWritable = ($mode & 0002) !== 0;

        $report['counts']['checked']++;
        if ($worldWritable) {
            tpFilePermissionsAddIssue($report, $relativePath, 'world_writable', 'error', $actualMode, 'no-world-write');
        }

        if ($classification === 'runtime') {
            if ($readable === false) {
                tpFilePermissionsAddIssue($report, $relativePath, 'runtime_not_readable', 'error', $actualMode, $isDirectory ? '0750' : '0640');
            }
            if ($writable === false) {
                tpFilePermissionsAddIssue($report, $relativePath, 'runtime_not_writable', 'error', $actualMode, $isDirectory ? '0750' : '0640');
            }
            if (($mode & 0007) !== 0 && $worldWritable === false) {
                tpFilePermissionsAddIssue($report, $relativePath, 'runtime_world_accessible', 'warning', $actualMode, $isDirectory ? '0750' : '0640');
            }
            return;
        }

        if ($classification === 'secret') {
            if ($readable === false) {
                tpFilePermissionsAddIssue($report, $relativePath, 'secret_not_readable', 'error', $actualMode, $isDirectory ? '0750' : '0640');
            }
            if (($mode & 0007) !== 0 && $worldWritable === false) {
                tpFilePermissionsAddIssue($report, $relativePath, 'secret_world_accessible', 'error', $actualMode, $isDirectory ? '0750' : '0640');
            }
            if (($mode & 0020) !== 0) {
                tpFilePermissionsAddIssue($report, $relativePath, 'secret_group_writable', 'warning', $actualMode, $isDirectory ? '0750' : '0640');
            }
            if ($writable && $worldWritable === false) {
                tpFilePermissionsAddIssue($report, $relativePath, 'secret_writable', 'warning', $actualMode, 'read-only-for-web');
            }
            return;
        }

        if ($classification === 'instance_data') {
            if (($mode & 0007) !== 0 && $worldWritable === false) {
                tpFilePermissionsAddIssue($report, $relativePath, 'instance_data_world_accessible', 'warning', $actualMode, $isDirectory ? '0750' : '0640');
            }
            return;
        }

        if ($readable === false) {
            tpFilePermissionsAddIssue($report, $relativePath, 'protected_not_readable', 'error', $actualMode, 'read-only-for-web');
        }
        if ($writable && $worldWritable === false) {
            tpFilePermissionsAddIssue($report, $relativePath, 'protected_writable', 'warning', $actualMode, 'read-only-for-web');
        } elseif (($mode & 0020) !== 0 && $worldWritable === false) {
            tpFilePermissionsAddIssue($report, $relativePath, 'group_writable', 'warning', $actualMode, 'no-group-write');
        }
    };

    $inspect($rootReal, '.');
    try {
        $directory = new RecursiveDirectoryIterator($rootReal, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator(
            $directory,
            static function (SplFileInfo $current) use ($rootReal): bool {
                $relative = str_replace('\\', '/', substr($current->getPathname(), strlen($rootReal) + 1));
                return tpFileScopeIsRepositoryArtifact($relative) === false;
            }
        );
        $iterator = new RecursiveIteratorIterator(
            $filter,
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($iterator as $entry) {
            if ($entry instanceof SplFileInfo === false) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($entry->getPathname(), strlen($rootReal) + 1));
            $inspect($entry->getPathname(), $relative);
        }
    } catch (UnexpectedValueException $exception) {
        tpFilePermissionsAddIssue($report, '.', 'inspection_failed', 'error', '-', 'inspectable');
    }

    usort(
        $report['issues'],
        static function (array $left, array $right): int {
            $pathComparison = strcmp((string) ($left['path'] ?? ''), (string) ($right['path'] ?? ''));
            return $pathComparison !== 0
                ? $pathComparison
                : strcmp((string) ($left['reason'] ?? ''), (string) ($right['reason'] ?? ''));
        }
    );
    $report['status'] = $report['counts']['errors'] > 0
        ? 'danger'
        : ($report['counts']['warnings'] > 0 ? 'warning' : 'success');

    return $report;
}

/**
 * Reduce a permission report for dashboard and Health overview responses.
 *
 * @param array<string,mixed> $report
 *
 * @return array<string,mixed>
 */
function tpFilePermissionsSummary(array $report): array
{
    $default = tpFilePermissionsDefaultReport();

    return [
        'has_result' => (bool) ($report['has_result'] ?? false),
        'scan_supported' => (bool) ($report['scan_supported'] ?? false),
        'remediation_supported' => (bool) ($report['remediation_supported'] ?? false),
        'status' => (string) ($report['status'] ?? 'not_run'),
        'platform' => is_array($report['platform'] ?? null) ? $report['platform'] : $default['platform'],
        'identity' => is_array($report['identity'] ?? null) ? $report['identity'] : $default['identity'],
        'counts' => is_array($report['counts'] ?? null) ? $report['counts'] : $default['counts'],
    ];
}

/**
 * Return safe top-level paths covered by the protected-code remediation.
 *
 * @return array<int,string>
 */
function tpFilePermissionsProtectedTopLevelPaths(string $root): array
{
    $paths = [];
    $excluded = ['storage', 'secrets', 'files', 'upload', 'backups'];
    try {
        foreach (new DirectoryIterator($root) as $entry) {
            if (
                $entry->isDot()
                || in_array($entry->getFilename(), $excluded, true)
                || tpFileScopeIsRepositoryArtifact($entry->getFilename())
                || $entry->isLink()
            ) {
                continue;
            }
            $paths[] = $entry->getPathname();
        }
    } catch (UnexpectedValueException $exception) {
        foreach (['app', 'public', 'composer.json', 'composer.lock'] as $knownPath) {
            $absolute = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . $knownPath;
            if (file_exists($absolute) && is_link($absolute) === false) {
                $paths[] = $absolute;
            }
        }
    }
    sort($paths, SORT_STRING);

    return $paths;
}

/**
 * Remove child paths already covered by an earlier recursive parent path.
 *
 * @param array<int,string> $paths
 *
 * @return array<int,string>
 */
function tpFilePermissionsReduceNestedPaths(array $paths): array
{
    usort(
        $paths,
        static function (string $left, string $right): int {
            $lengthComparison = strlen($left) <=> strlen($right);
            return $lengthComparison !== 0 ? $lengthComparison : strcmp($left, $right);
        }
    );
    $reduced = [];
    foreach ($paths as $path) {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');
        $covered = false;
        foreach ($reduced as $parent) {
            $normalizedParent = rtrim(str_replace('\\', '/', $parent), '/');
            if ($normalized === $normalizedParent || str_starts_with($normalized, $normalizedParent . '/')) {
                $covered = true;
                break;
            }
        }
        if ($covered === false) {
            $reduced[] = $path;
        }
    }

    return $reduced;
}

/**
 * Preserve an existing non-web deployment owner, otherwise fall back to root.
 */
function tpFilePermissionsResolveCodeOwner(string $root, string $webUser): string
{
    $stat = @stat($root);
    if (is_array($stat) && function_exists('posix_getpwuid')) {
        $owner = @posix_getpwuid((int) $stat['uid']);
        $ownerName = is_array($owner) && is_string($owner['name'] ?? null) ? $owner['name'] : '';
        if (
            $ownerName !== ''
            && $ownerName !== $webUser
            && preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $ownerName) === 1
        ) {
            return $ownerName;
        }
    }

    return 'root';
}

/**
 * Build sudo-based runtime permission remediation for supported distributions.
 *
 * Commands implement the hardened normal-runtime model: code keeps an existing
 * non-web deployment owner (or falls back to root) and stays read-only for PHP,
 * while only explicit runtime paths are writable by the detected web account.
 * Upgrade-only paths must be unlocked separately before using the web upgrader.
 *
 * @param array<string,mixed> $permissionReport
 *
 * @return array<int,string>
 */
function tpFilePermissionsRemediationCommands(string $root, array $permissionReport): array
{
    if (
        (bool) ($permissionReport['remediation_supported'] ?? false) === false
        || (int) ($permissionReport['counts']['issues'] ?? 0) === 0
    ) {
        return [];
    }
    $rootReal = realpath($root);
    if ($rootReal === false) {
        return [];
    }

    $identity = is_array($permissionReport['identity'] ?? null) ? $permissionReport['identity'] : [];
    $webUser = (string) ($identity['web_user'] ?? '');
    $webGroup = (string) ($identity['web_group'] ?? '');
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $webUser) !== 1 || preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $webGroup) !== 1) {
        return [];
    }
    $codeOwner = tpFilePermissionsResolveCodeOwner($rootReal, $webUser);

    $protected = tpFilePermissionsProtectedTopLevelPaths($rootReal);
    $protectedArguments = implode(' ', array_map('escapeshellarg', $protected));
    $runtimePaths = [];
    $commands = [];
    foreach (tpFilePermissionsRuntimeRules() as $rule) {
        $absolute = $rootReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rule['path']);
        if ($rule['required']) {
            $commands[] = 'sudo install -d -o ' . escapeshellarg($webUser) . ' -g ' . escapeshellarg($webGroup)
                . ' -m 0750 -- ' . escapeshellarg($absolute);
        }
        if (is_dir($absolute) || $rule['required']) {
            $runtimePaths[] = $absolute;
        }
    }
    $runtimePaths = tpFilePermissionsReduceNestedPaths(array_values(array_unique($runtimePaths)));
    $runtimeArguments = implode(' ', array_map('escapeshellarg', $runtimePaths));

    array_unshift(
        $commands,
        'sudo chown ' . escapeshellarg($codeOwner . ':' . $webGroup) . ' -- ' . escapeshellarg($rootReal),
        'sudo chmod 0750 -- ' . escapeshellarg($rootReal)
    );
    if ($protectedArguments !== '') {
        $commands[] = 'sudo chown -R ' . escapeshellarg($codeOwner . ':' . $webGroup) . ' -- ' . $protectedArguments;
        $commands[] = 'sudo find ' . $protectedArguments . ' -xdev -type d -exec chmod 0750 {} +';
        $commands[] = 'sudo find ' . $protectedArguments . ' -xdev -type f -exec chmod 0640 {} +';
    }
    if ($runtimeArguments !== '') {
        $commands[] = 'sudo chown -R ' . escapeshellarg($webUser . ':' . $webGroup) . ' -- ' . $runtimeArguments;
        $commands[] = 'sudo find ' . $runtimeArguments . ' -xdev -type d -exec chmod 0750 {} +';
        $commands[] = 'sudo find ' . $runtimeArguments . ' -xdev -type f -exec chmod 0640 {} +';
    }

    $secretsPath = $rootReal . DIRECTORY_SEPARATOR . 'secrets';
    if (is_dir($secretsPath)) {
        $commands[] = 'sudo chown -R ' . escapeshellarg('root:' . $webGroup) . ' -- ' . escapeshellarg($secretsPath);
        $commands[] = 'sudo find ' . escapeshellarg($secretsPath) . ' -type d -exec chmod 0750 {} +';
        $commands[] = 'sudo find ' . escapeshellarg($secretsPath) . ' -type f -exec chmod 0640 {} +';
    }

    $legacyDataPaths = [];
    foreach (['files', 'upload', 'backups'] as $legacyRoot) {
        $legacyPath = $rootReal . DIRECTORY_SEPARATOR . $legacyRoot;
        if (is_dir($legacyPath)) {
            $legacyDataPaths[] = $legacyPath;
        }
    }
    if ($legacyDataPaths !== []) {
        $legacyArguments = implode(' ', array_map('escapeshellarg', $legacyDataPaths));
        $commands[] = 'sudo chown -R ' . escapeshellarg('root:' . $webGroup) . ' -- ' . $legacyArguments;
        $commands[] = 'sudo find ' . $legacyArguments . ' -xdev -type d -exec chmod 0750 {} +';
        $commands[] = 'sudo find ' . $legacyArguments . ' -xdev -type f -exec chmod 0640 {} +';
    }

    $platform = is_array($permissionReport['platform'] ?? null) ? $permissionReport['platform'] : [];
    if (($platform['family'] ?? '') === 'rhel' && $runtimePaths !== []) {
        foreach ($runtimePaths as $runtimePath) {
            $contextPattern = preg_quote(str_replace('\\', '/', $runtimePath), '#') . '(/.*)?';
            $quotedPattern = escapeshellarg($contextPattern);
            $commands[] = 'if command -v semanage >/dev/null 2>&1; then sudo semanage fcontext -a -t httpd_sys_rw_content_t '
                . $quotedPattern . ' 2>/dev/null || sudo semanage fcontext -m -t httpd_sys_rw_content_t ' . $quotedPattern . '; fi';
        }
        $commands[] = 'sudo restorecon -RF -- ' . $runtimeArguments;
    }

    return $commands;
}
