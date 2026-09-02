<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * File integrity scan logic shared by the web health page, background worker,
 * command-line diagnostics, and unit tests.
 *
 * This module is deliberately DB-free. It never deletes, moves, or repairs an
 * installation. Its only write operation is persisting the latest JSON report
 * under storage/logs when explicitly requested by a caller.
 */

require_once __DIR__ . '/file_permissions.functions.php';
require_once __DIR__ . '/file_scope.functions.php';

/**
 * Return the paths that must never participate in a code integrity scan.
 *
 * @return array{prefixes: array<int,string>, exact: array<int,string>}
 */
function tpFileIntegrityExcludedPaths(): array
{
    return [
        'prefixes' => [
            'storage/',
            'secrets/',
            'files/',
            'upload/',
            'backups/',
            'app/includes/libraries/csrfp/log/',
            'app/websocket/logs/',
            'app/config/settings.php.',
            'app/includes/libraries/csrfp/libs/csrfp.config.php.',
        ],
        'exact' => [
            'files_reference.txt',
            'app/files_reference.txt',
            'app/config/settings.php',
            'app/config/version-commit.php',
            'app/includes/libraries/csrfp/libs/csrfp.config.php',
        ],
    ];
}

/**
 * Return legacy 3.1.x code directories that may remain beside app/ and public/.
 *
 * Runtime data directories from the former layout are intentionally absent:
 * files/, upload/, and backups/ may still contain instance data and must never
 * be proposed for generic cleanup.
 *
 * @return array<int,string>
 */
function tpFileIntegrityLegacyDirectories(): array
{
    return [
        'api',
        'includes',
        'install',
        'licences',
        'pages',
        'plugins',
        'readmeFiles',
        'scripts',
        'sources',
        'vendor',
        'websocket',
    ];
}

/**
 * Return legacy root files from the pre-3.2 layout.
 *
 * @return array<int,string>
 */
function tpFileIntegrityLegacyRootFiles(): array
{
    return [
        'changelog.txt',
        'error.php',
        'favicon.ico',
        'index.php',
        'manifest.json',
        'readme.md',
        'self-unlock.php',
    ];
}

/**
 * Normalize and validate a path relative to the TeamPass root.
 */
function tpFileIntegrityNormalizePath(string $path): ?string
{
    $path = str_replace('\\', '/', trim($path));
    while (str_starts_with($path, './')) {
        $path = substr($path, 2);
    }

    if (
        $path === ''
        || str_contains($path, "\0")
        || str_starts_with($path, '/')
        || preg_match('/^[A-Za-z]:\//', $path) === 1
    ) {
        return null;
    }

    $segments = explode('/', $path);
    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return null;
        }
    }

    return implode('/', $segments);
}

/**
 * Tell whether a relative path is outside runtime integrity scope.
 *
 * This includes instance-owned data, self-referential generated files and the
 * explicit repository/development artifact policy shared with permission scans.
 */
function tpFileIntegrityIsExcluded(string $path): bool
{
    $path = str_replace('\\', '/', $path);
    if (tpFileScopeIsRepositoryArtifact($path)) {
        return true;
    }
    $rules = tpFileIntegrityExcludedPaths();

    if (in_array(rtrim($path, '/'), $rules['exact'], true)) {
        return true;
    }

    $pathWithSlash = rtrim($path, '/') . '/';
    foreach ($rules['prefixes'] as $prefix) {
        if (str_starts_with($pathWithSlash, $prefix)) {
            return true;
        }
    }

    return false;
}

/**
 * Parse a TeamPass reference manifest.
 *
 * Both historical MD5 entries and future SHA-256 entries are accepted. Invalid
 * lines are reported and never silently converted into reference paths.
 *
 * @return array{files: array<string,array{hash:string,algorithm:string}>, warnings: array<int,array{path:string,message:string}>, reference_hash:string}
 */
function tpFileIntegrityParseReference(string $referenceFile): array
{
    if (is_file($referenceFile) === false || is_readable($referenceFile) === false) {
        throw new RuntimeException('The file integrity reference manifest is missing or unreadable.');
    }

    $contents = file_get_contents($referenceFile);
    if ($contents === false) {
        throw new RuntimeException('The file integrity reference manifest could not be read.');
    }
    $lines = preg_split('/\R/', $contents) ?: [];

    $files = [];
    $warnings = [];
    foreach ($lines as $index => $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (preg_match('/^(.+)\s+([0-9a-fA-F]{32}|[0-9a-fA-F]{64})$/', $line, $matches) !== 1) {
            $warnings[] = [
                'path' => $referenceFile . ':' . strval($index + 1),
                'message' => 'Invalid reference manifest line.',
            ];
            continue;
        }

        $path = tpFileIntegrityNormalizePath($matches[1]);
        if ($path === null) {
            $warnings[] = [
                'path' => $referenceFile . ':' . strval($index + 1),
                'message' => 'Unsafe reference manifest path.',
            ];
            continue;
        }

        $hash = strtolower($matches[2]);
        if (isset($files[$path])) {
            $warnings[] = [
                'path' => $referenceFile . ':' . strval($index + 1),
                'message' => 'Duplicate reference manifest path.',
            ];
        }
        $files[$path] = [
            'hash' => $hash,
            'algorithm' => strlen($hash) === 64 ? 'sha256' : 'md5',
        ];
    }

    if ($files === []) {
        throw new RuntimeException('The file integrity reference manifest contains no usable entry.');
    }

    return [
        'files' => $files,
        'warnings' => $warnings,
        'reference_hash' => hash('sha256', $contents),
    ];
}

/**
 * Build the development dependency path catalogue from composer.lock.
 *
 * @return array{prefixes: array<int,string>, binaries: array<string,bool>}
 */
function tpFileIntegrityDevelopmentPaths(string $root): array
{
    $lockPath = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'composer.lock';
    if (is_file($lockPath) === false || is_readable($lockPath) === false) {
        return ['prefixes' => [], 'binaries' => []];
    }

    $decoded = json_decode((string) file_get_contents($lockPath), true);
    if (is_array($decoded) === false || is_array($decoded['packages-dev'] ?? null) === false) {
        return ['prefixes' => [], 'binaries' => []];
    }

    $prefixes = [];
    $binaries = [];
    foreach ($decoded['packages-dev'] as $package) {
        if (is_array($package) === false) {
            continue;
        }

        $name = (string) ($package['name'] ?? '');
        if (preg_match('#^[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*$#i', $name) === 1) {
            $prefixes[] = 'app/vendor/' . $name . '/';
        }

        $packageBinaries = $package['bin'] ?? [];
        if (is_string($packageBinaries)) {
            $packageBinaries = [$packageBinaries];
        }
        if (is_array($packageBinaries) === false) {
            continue;
        }
        foreach ($packageBinaries as $binary) {
            $baseName = basename(str_replace('\\', '/', (string) $binary));
            if ($baseName !== '' && $baseName !== '.' && $baseName !== '..') {
                $binaries['app/vendor/bin/' . $baseName] = true;
                $binaries['app/vendor/bin/' . $baseName . '.bat'] = true;
            }
        }
    }

    sort($prefixes);
    return ['prefixes' => array_values(array_unique($prefixes)), 'binaries' => $binaries];
}

/**
 * Return true when a non-reference path belongs to a Composer development package.
 *
 * @param array{prefixes: array<int,string>, binaries: array<string,bool>} $developmentPaths
 */
function tpFileIntegrityIsDevelopmentPath(string $path, array $developmentPaths): bool
{
    if (isset($developmentPaths['binaries'][$path])) {
        return true;
    }
    foreach ($developmentPaths['prefixes'] as $prefix) {
        if (str_starts_with($path, $prefix)) {
            return true;
        }
    }

    return false;
}

/**
 * Return the legacy root name for a path, or null for a current path.
 */
function tpFileIntegrityLegacyRoot(string $path): ?string
{
    $firstSegment = explode('/', $path, 2)[0];
    if (in_array($firstSegment, tpFileIntegrityLegacyDirectories(), true)) {
        return $firstSegment;
    }
    if (str_starts_with($firstSegment, '_includes.bak.')) {
        return $firstSegment;
    }
    if (in_array($path, tpFileIntegrityLegacyRootFiles(), true)) {
        return $path;
    }

    return null;
}

/**
 * Tell whether an unknown path can be executed or interpreted in a web context.
 */
function tpFileIntegrityIsExecutablePath(string $path): bool
{
    $baseName = strtolower(basename(str_replace('\\', '/', $path)));
    if (
        in_array($baseName, ['.htaccess', '.user.ini', '.env'], true)
        || str_starts_with($baseName, '.env.')
    ) {
        return true;
    }
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($extension, ['php', 'phtml', 'phar', 'inc', 'cgi', 'pl', 'py', 'sh'], true);
}

/**
 * Tell whether a path is ordinary user content in the writable public avatar directory.
 */
function tpFileIntegrityIsAllowedRuntimeAsset(string $path, bool $isLink): bool
{
    return str_starts_with($path, 'public/assets/avatars/')
        && $isLink === false
        && tpFileIntegrityIsExecutablePath($path) === false;
}

/**
 * Ensure a resolved symbolic-link target remains inside the TeamPass root.
 */
function tpFileIntegrityIsInsideRoot(string $root, string $resolvedPath): bool
{
    $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/') . '/';
    $normalizedPath = str_replace('\\', '/', $resolvedPath);
    if (DIRECTORY_SEPARATOR === '\\') {
        $normalizedRoot = strtolower($normalizedRoot);
        $normalizedPath = strtolower($normalizedPath);
    }

    return str_starts_with($normalizedPath, $normalizedRoot);
}

/**
 * Collect files and symbolic links below a scan root.
 *
 * @param array<int,array{path:string,message:string}> $warnings
 * @return array<int,array{path:string,absolute:string,link:bool}>
 */
function tpFileIntegrityCollectTree(string $absoluteRoot, string $relativeRoot, array &$warnings): array
{
    if (is_dir($absoluteRoot) === false) {
        return [];
    }
    if (is_readable($absoluteRoot) === false) {
        $warnings[] = ['path' => $relativeRoot, 'message' => 'Directory is not readable.'];
        return [];
    }

    $entries = [];
    try {
        $directory = new RecursiveDirectoryIterator($absoluteRoot, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator(
            $directory,
            static function (SplFileInfo $current) use ($absoluteRoot, $relativeRoot, &$warnings): bool {
                $suffix = str_replace('\\', '/', substr($current->getPathname(), strlen($absoluteRoot)));
                $path = trim($relativeRoot . '/' . ltrim($suffix, '/'), '/');
                if (tpFileIntegrityIsExcluded($path)) {
                    return false;
                }
                if ($current->isLink()) {
                    return true;
                }
                if ($current->isDir() && $current->isReadable() === false) {
                    $warnings[] = ['path' => $path, 'message' => 'Directory is not readable.'];
                    return false;
                }
                return true;
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
            if ($entry->isLink() === false && $entry->isFile() === false) {
                continue;
            }
            $suffix = str_replace('\\', '/', substr($entry->getPathname(), strlen($absoluteRoot)));
            $path = tpFileIntegrityNormalizePath(trim($relativeRoot . '/' . ltrim($suffix, '/'), '/'));
            if ($path === null || tpFileIntegrityIsExcluded($path)) {
                continue;
            }
            $entries[] = [
                'path' => $path,
                'absolute' => $entry->getPathname(),
                'link' => $entry->isLink(),
            ];
        }
    } catch (UnexpectedValueException $exception) {
        $warnings[] = ['path' => $relativeRoot, 'message' => $exception->getMessage()];
    }

    return $entries;
}

/**
 * Return a report shell with stable keys for the API and CLI.
 *
 * @return array<string,mixed>
 */
function tpFileIntegrityDefaultReport(): array
{
    return [
        'schema_version' => 1,
        'scan_id' => '',
        'has_result' => false,
        'running' => false,
        'stale' => false,
        'reference_missing' => false,
        'reference_unreadable' => false,
        'report_invalid' => false,
        'status' => 'not_run',
        'started_at' => 0,
        'completed_at' => 0,
        'duration_ms' => 0,
        'reference_file' => 'app/files_reference.txt',
        'reference_hash' => '',
        'counts' => [
            'reference_entries' => 0,
            'checked' => 0,
            'ok' => 0,
            'modified' => 0,
            'missing' => 0,
            'unknown' => 0,
            'legacy' => 0,
            'development' => 0,
            'warnings' => 0,
            'permissions_checked' => 0,
            'permission_issues' => 0,
            'permission_errors' => 0,
            'permission_warnings' => 0,
            'excluded_reference' => 0,
            'critical' => 0,
            'total_issues' => 0,
        ],
        'issues' => [
            'modified' => [],
            'missing' => [],
            'unknown' => [],
            'legacy' => [],
            'development' => [],
            'warnings' => [],
            'permissions' => [],
        ],
        'permissions' => tpFilePermissionsDefaultReport(),
    ];
}

/**
 * Return the report file used by the health page and dashboard.
 */
function tpFileIntegrityReportPath(string $root): string
{
    return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR
        . 'logs' . DIRECTORY_SEPARATOR . 'file-integrity-report.json';
}

/**
 * Return the advisory scan lock path.
 */
function tpFileIntegrityLockPath(string $root): string
{
    return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR
        . 'logs' . DIRECTORY_SEPARATOR . 'file-integrity-scan.lock';
}

/**
 * Return the short-lived lock that serializes web task creation.
 */
function tpFileIntegrityEnqueueLockPath(string $root): string
{
    return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR
        . 'logs' . DIRECTORY_SEPARATOR . 'file-integrity-enqueue.lock';
}

/**
 * Tell whether another process currently owns the scan lock.
 */
function tpFileIntegrityIsRunning(string $root): bool
{
    $lockPath = tpFileIntegrityLockPath($root);
    if (is_file($lockPath) === false) {
        return false;
    }

    $handle = @fopen($lockPath, 'c+');
    if ($handle === false) {
        return false;
    }
    $available = @flock($handle, LOCK_EX | LOCK_NB);
    if ($available) {
        @flock($handle, LOCK_UN);
    }
    fclose($handle);

    return $available === false;
}

/**
 * Perform a complete, read-only integrity scan.
 *
 * @return array<string,mixed>
 */
function tpFileIntegrityScan(
    string $root,
    string $referenceFile,
    bool $useLock = true,
    bool $scanPermissions = true,
    bool $deepPermissionScan = false
): array
{
    $rootReal = realpath($root);
    if ($rootReal === false || is_dir($rootReal) === false) {
        throw new RuntimeException('The TeamPass root directory is invalid.');
    }

    $lockHandle = null;
    if ($useLock) {
        $lockPath = tpFileIntegrityLockPath($rootReal);
        if (is_dir(dirname($lockPath)) === false || is_writable(dirname($lockPath)) === false) {
            throw new RuntimeException('The file integrity lock directory is not writable.');
        }
        $lockHandle = fopen($lockPath, 'c+');
        if ($lockHandle === false || flock($lockHandle, LOCK_EX | LOCK_NB) === false) {
            if (is_resource($lockHandle)) {
                fclose($lockHandle);
            }
            throw new RuntimeException('A file integrity scan is already running.');
        }
        ftruncate($lockHandle, 0);
        fwrite($lockHandle, strval(getmypid()) . ' ' . strval(time()));
        fflush($lockHandle);
    }

    $startedAt = time();
    $startedMicrotime = microtime(true);
    try {
        $reference = tpFileIntegrityParseReference($referenceFile);
        $referenceFiles = $reference['files'];
        $warnings = $reference['warnings'];
        $developmentPaths = tpFileIntegrityDevelopmentPaths($rootReal);
        $report = tpFileIntegrityDefaultReport();
        $report['scan_id'] = gmdate('YmdHis', $startedAt) . '-' . bin2hex(random_bytes(4));
        $report['has_result'] = true;
        $report['started_at'] = $startedAt;
        $report['reference_hash'] = $reference['reference_hash'];
        $report['counts']['reference_entries'] = count($referenceFiles);
        $publicInstallerPresent = is_dir($rootReal . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'install');

        foreach ($referenceFiles as $relativePath => $referenceData) {
            if (
                tpFileIntegrityIsExcluded($relativePath)
                || ($publicInstallerPresent === false && str_starts_with($relativePath, 'public/install/'))
            ) {
                $report['counts']['excluded_reference']++;
                continue;
            }

            $absolutePath = $rootReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $hashPath = $absolutePath;
            if (is_link($absolutePath)) {
                $resolvedTarget = realpath($absolutePath);
                if (
                    $resolvedTarget === false
                    || is_file($resolvedTarget) === false
                    || tpFileIntegrityIsInsideRoot($rootReal, $resolvedTarget) === false
                ) {
                    $report['issues']['modified'][] = [
                        'path' => $relativePath,
                        'expected' => $referenceData['hash'],
                        'actual' => 'unsafe-symbolic-link',
                    ];
                    continue;
                }
                $hashPath = $resolvedTarget;
            } elseif (is_file($absolutePath) === false) {
                $report['issues']['missing'][] = ['path' => $relativePath];
                continue;
            }
            if (is_readable($hashPath) === false) {
                $warnings[] = ['path' => $relativePath, 'message' => 'File is not readable.'];
                continue;
            }

            $actualHash = hash_file($referenceData['algorithm'], $hashPath);
            if ($actualHash === false) {
                $warnings[] = ['path' => $relativePath, 'message' => 'File hash could not be calculated.'];
                continue;
            }

            $report['counts']['checked']++;
            if (hash_equals($referenceData['hash'], strtolower($actualHash))) {
                $report['counts']['ok']++;
            } else {
                $report['issues']['modified'][] = [
                    'path' => $relativePath,
                    'expected' => $referenceData['hash'],
                    'actual' => strtolower($actualHash),
                ];
            }
        }

        $seenPaths = [];
        foreach (tpFileIntegrityCollectTree($rootReal, '', $warnings) as $entry) {
            $relativePath = $entry['path'];
            if (isset($seenPaths[$relativePath])) {
                continue;
            }
            $seenPaths[$relativePath] = true;
            if (isset($referenceFiles[$relativePath]) || tpFileIntegrityIsExcluded($relativePath)) {
                continue;
            }
            if (tpFileIntegrityIsAllowedRuntimeAsset($relativePath, $entry['link'])) {
                continue;
            }

            $legacyRoot = tpFileIntegrityLegacyRoot($relativePath);
            if ($legacyRoot !== null) {
                $report['issues']['legacy'][] = ['path' => $relativePath, 'root' => $legacyRoot];
            } elseif (tpFileIntegrityIsDevelopmentPath($relativePath, $developmentPaths)) {
                $report['issues']['development'][] = ['path' => $relativePath];
            } else {
                $report['issues']['unknown'][] = [
                    'path' => $relativePath,
                    'critical' => $entry['link'] || tpFileIntegrityIsExecutablePath($relativePath),
                    'link' => $entry['link'],
                ];
            }
        }

        foreach (tpFileIntegrityLegacyRootFiles() as $legacyFile) {
            $absolutePath = $rootReal . DIRECTORY_SEPARATOR . $legacyFile;
            if (is_file($absolutePath) && isset($referenceFiles[$legacyFile]) === false) {
                $report['issues']['legacy'][] = ['path' => $legacyFile, 'root' => $legacyFile];
            }
        }

        $report['issues']['warnings'] = $warnings;
        foreach (['modified', 'missing', 'unknown', 'legacy', 'development', 'warnings'] as $category) {
            usort(
                $report['issues'][$category],
                static function (array $left, array $right): int {
                    return strcmp((string) ($left['path'] ?? ''), (string) ($right['path'] ?? ''));
                }
            );
            $report['counts'][$category] = count($report['issues'][$category]);
        }

        if ($scanPermissions) {
            $permissionReport = tpFilePermissionsScan($rootReal, null, null, $deepPermissionScan);
            $report['issues']['permissions'] = is_array($permissionReport['issues'] ?? null)
                ? $permissionReport['issues']
                : [];
            // Keep the large paginated issue collection in one place only.
            // The nested permission report carries summary/remediation metadata.
            $permissionReport['issues'] = [];
            $report['permissions'] = $permissionReport;
            $permissionCounts = is_array($permissionReport['counts'] ?? null)
                ? $permissionReport['counts']
                : [];
            $report['counts']['permissions_checked'] = (int) ($permissionCounts['checked'] ?? 0);
            $report['counts']['permission_issues'] = (int) ($permissionCounts['issues'] ?? 0);
            $report['counts']['permission_errors'] = (int) ($permissionCounts['errors'] ?? 0);
            $report['counts']['permission_warnings'] = (int) ($permissionCounts['warnings'] ?? 0);
        }

        $criticalUnknown = 0;
        foreach ($report['issues']['unknown'] as $unknown) {
            if ((bool) ($unknown['critical'] ?? false)) {
                $criticalUnknown++;
            }
        }
        $report['counts']['critical'] = $report['counts']['modified']
            + $report['counts']['missing']
            + $criticalUnknown
            + $report['counts']['permission_errors'];
        $report['counts']['total_issues'] = $report['counts']['modified']
            + $report['counts']['missing']
            + $report['counts']['unknown']
            + $report['counts']['legacy']
            + $report['counts']['development']
            + $report['counts']['warnings']
            + $report['counts']['permission_issues'];

        if ($report['counts']['critical'] > 0) {
            $report['status'] = 'danger';
        } elseif ($report['counts']['total_issues'] > 0 || $report['counts']['warnings'] > 0) {
            $report['status'] = 'warning';
        } else {
            $report['status'] = 'success';
        }

        $report['completed_at'] = time();
        $report['duration_ms'] = (int) round((microtime(true) - $startedMicrotime) * 1000);
        return $report;
    } finally {
        if (is_resource($lockHandle)) {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }
}

/**
 * Return the lightweight summary file used by Dashboard and Health polling.
 */
function tpFileIntegritySummaryPath(string $root): string
{
    return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR
        . 'logs' . DIRECTORY_SEPARATOR . 'file-integrity-summary.json';
}

/**
 * Persist one JSON payload using an atomic replace when supported.
 *
 * @param array<string,mixed> $payload
 */
function tpFileIntegrityWriteJsonFile(string $path, array $payload): void
{
    $directory = dirname($path);
    if (is_dir($directory) === false || is_writable($directory) === false) {
        throw new RuntimeException('The file integrity report directory is not writable.');
    }

    $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($encoded === false) {
        throw new RuntimeException('The file integrity report could not be encoded.');
    }

    $temporaryPath = $path . '.tmp.' . strval(getmypid()) . '.' . bin2hex(random_bytes(4));
    if (file_put_contents($temporaryPath, $encoded, LOCK_EX) === false) {
        throw new RuntimeException('The temporary file integrity report could not be written.');
    }
    @chmod($temporaryPath, 0640);

    if (@rename($temporaryPath, $path) === false) {
        if (file_put_contents($path, $encoded, LOCK_EX) === false) {
            @unlink($temporaryPath);
            throw new RuntimeException('The file integrity report could not be written.');
        }
        @unlink($temporaryPath);
    }
    @chmod($path, 0640);
}

/**
 * Persist the detailed report first and publish its small matching summary last.
 *
 * Publishing in this order ensures that readers never associate a new summary
 * with an older detailed report. Consumers of details also validate scan_id.
 *
 * @param array<string,mixed> $report
 */
function tpFileIntegritySaveReport(string $root, array $report): void
{
    if ((string) ($report['scan_id'] ?? '') === '') {
        throw new RuntimeException('The file integrity report has no scan identifier.');
    }

    tpFileIntegrityWriteJsonFile(tpFileIntegrityReportPath($root), $report);
    tpFileIntegrityWriteJsonFile(tpFileIntegritySummaryPath($root), tpFileIntegritySummary($report));
}

/**
 * Apply current manifest and running-state checks to a persisted payload.
 *
 * @param array<string,mixed> $payload
 *
 * @return array<string,mixed>
 */
function tpFileIntegrityApplyRuntimeState(string $root, array $payload): array
{
    $currentReferencePath = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'app'
        . DIRECTORY_SEPARATOR . 'files_reference.txt';
    $payload['running'] = false;
    $payload['stale'] = false;
    $payload['reference_missing'] = false;
    $payload['reference_unreadable'] = false;
    $payload['status'] = (string) ($payload['scan_status'] ?? $payload['status'] ?? 'not_run');

    if ((bool) ($payload['report_invalid'] ?? false)) {
        $payload['status'] = 'error';
    } elseif (is_file($currentReferencePath) === false) {
        $payload['reference_missing'] = true;
        $payload['stale'] = (bool) ($payload['has_result'] ?? false);
        $payload['status'] = 'error';
    } elseif (is_readable($currentReferencePath) === false) {
        $payload['reference_unreadable'] = true;
        $payload['stale'] = (bool) ($payload['has_result'] ?? false);
        $payload['status'] = 'error';
    } elseif ((bool) ($payload['has_result'] ?? false)) {
        $currentReferenceHash = @hash_file('sha256', $currentReferencePath);
        if ($currentReferenceHash === false) {
            $payload['reference_unreadable'] = true;
            $payload['stale'] = true;
            $payload['status'] = 'error';
        } elseif (hash_equals((string) ($payload['reference_hash'] ?? ''), $currentReferenceHash) === false) {
            $payload['stale'] = true;
            $payload['status'] = 'stale';
        }
    }

    $payload['running'] = tpFileIntegrityIsRunning($root);
    if ($payload['running']) {
        $payload['status'] = 'running';
    }

    return $payload;
}

/**
 * Load only the lightweight summary used by overview and polling endpoints.
 *
 * @return array<string,mixed>
 */
function tpFileIntegrityLoadSummary(string $root): array
{
    $default = tpFileIntegritySummary(tpFileIntegrityDefaultReport());
    $path = tpFileIntegritySummaryPath($root);
    if (is_file($path) === false) {
        return tpFileIntegrityApplyRuntimeState($root, $default);
    }

    $contents = false;
    $handle = @fopen($path, 'rb');
    if (is_resource($handle)) {
        if (@flock($handle, LOCK_SH)) {
            $contents = stream_get_contents($handle);
            flock($handle, LOCK_UN);
        }
        fclose($handle);
    }

    $decoded = is_string($contents) ? json_decode($contents, true) : null;
    if (
        is_array($decoded) === false
        || (int) ($decoded['schema_version'] ?? 0) !== 1
        || isset($decoded['issues'])
    ) {
        $default['status'] = 'error';
        $default['scan_status'] = 'error';
        $default['report_invalid'] = true;
        return tpFileIntegrityApplyRuntimeState($root, $default);
    }

    return tpFileIntegrityApplyRuntimeState($root, array_replace_recursive($default, $decoded));
}

/**
 * Load the latest persisted report, returning a stable not-run state on absence.
 *
 * @return array<string,mixed>
 */
function tpFileIntegrityLoadReport(string $root, ?string $expectedScanId = null): array
{
    $default = tpFileIntegrityDefaultReport();
    $path = tpFileIntegrityReportPath($root);
    if (is_file($path) === false) {
        return tpFileIntegrityApplyRuntimeState($root, $default);
    }

    $contents = false;
    $handle = @fopen($path, 'rb');
    if (is_resource($handle)) {
        if (@flock($handle, LOCK_SH)) {
            $contents = stream_get_contents($handle);
            flock($handle, LOCK_UN);
        }
        fclose($handle);
    }

    $decoded = is_string($contents) ? json_decode($contents, true) : null;
    if (is_array($decoded) === false || (int) ($decoded['schema_version'] ?? 0) !== 1) {
        $default['status'] = 'error';
        $default['report_invalid'] = true;
        $default['issues']['warnings'][] = [
            'path' => $path,
            'message' => is_readable($path)
                ? 'The saved file integrity report is invalid.'
                : 'The saved file integrity report is not readable.',
        ];
        $default['counts']['warnings'] = 1;
        return tpFileIntegrityApplyRuntimeState($root, $default);
    }

    $report = array_replace_recursive($default, $decoded);
    if (
        $expectedScanId !== null
        && ($expectedScanId === '' || hash_equals($expectedScanId, (string) ($report['scan_id'] ?? '')) === false)
    ) {
        $default['status'] = 'error';
        $default['report_invalid'] = true;
        $default['issues']['warnings'][] = [
            'path' => $path,
            'message' => 'The detailed report does not match the current summary.',
        ];
        $default['counts']['warnings'] = 1;
        return tpFileIntegrityApplyRuntimeState($root, $default);
    }

    return tpFileIntegrityApplyRuntimeState($root, $report);
}

/**
 * Reduce a full report to the fields safe and useful for overview endpoints.
 *
 * @return array<string,mixed>
 */
function tpFileIntegritySummary(array $report): array
{
    $counts = is_array($report['counts'] ?? null) ? $report['counts'] : tpFileIntegrityDefaultReport()['counts'];
    return [
        'schema_version' => 1,
        'has_result' => (bool) ($report['has_result'] ?? false),
        'running' => (bool) ($report['running'] ?? false),
        'stale' => (bool) ($report['stale'] ?? false),
        'reference_missing' => (bool) ($report['reference_missing'] ?? false),
        'reference_unreadable' => (bool) ($report['reference_unreadable'] ?? false),
        'report_invalid' => (bool) ($report['report_invalid'] ?? false),
        'status' => (string) ($report['status'] ?? 'not_run'),
        'scan_status' => (string) ($report['scan_status'] ?? $report['status'] ?? 'not_run'),
        'scan_id' => (string) ($report['scan_id'] ?? ''),
        'completed_at' => (int) ($report['completed_at'] ?? 0),
        'duration_ms' => (int) ($report['duration_ms'] ?? 0),
        'reference_hash' => (string) ($report['reference_hash'] ?? ''),
        'counts' => $counts,
        'permissions' => tpFilePermissionsSummary(
            is_array($report['permissions'] ?? null) ? $report['permissions'] : []
        ),
    ];
}

/**
 * Return a safely paginated issue category from a report.
 *
 * @return array{category:string,offset:int,limit:int,total:int,items:array<int,mixed>}
 */
function tpFileIntegrityIssuePage(array $report, string $category, int $offset, int $limit): array
{
    $allowed = ['modified', 'missing', 'unknown', 'legacy', 'development', 'warnings', 'permissions'];
    if ($category === 'all') {
        $items = [];
        foreach ($allowed as $issueCategory) {
            $categoryItems = is_array($report['issues'][$issueCategory] ?? null)
                ? array_values($report['issues'][$issueCategory])
                : [];
            foreach ($categoryItems as $item) {
                if (is_array($item)) {
                    $item['category'] = $issueCategory;
                    $items[] = $item;
                }
            }
        }
    } elseif (in_array($category, $allowed, true) === false) {
        $category = 'modified';
        $items = is_array($report['issues'][$category] ?? null)
            ? array_values($report['issues'][$category])
            : [];
    } else {
        $items = is_array($report['issues'][$category] ?? null)
            ? array_values($report['issues'][$category])
            : [];
    }
    $offset = max(0, $offset);
    $limit = max(1, min(200, $limit));

    return [
        'category' => $category,
        'offset' => $offset,
        'limit' => $limit,
        'total' => count($items),
        'items' => array_slice($items, $offset, $limit),
    ];
}

/**
 * Build SSH cleanup guidance for an administrator.
 *
 * The returned commands form a quarantine or dependency-cleanup plan. They are
 * never executed by TeamPass and deliberately omit former runtime directories.
 *
 * @return array<int,string>
 */
function tpFileIntegrityCleanupPlan(string $root, array $report): array
{
    $rootReal = realpath($root);
    if ($rootReal === false) {
        return [];
    }

    $legacyRoots = [];
    foreach ((array) ($report['issues']['legacy'] ?? []) as $issue) {
        $legacyRoot = (string) ($issue['root'] ?? '');
        if ($legacyRoot !== '') {
            $legacyRoots[$legacyRoot] = true;
        }
    }

    $scanId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($report['scan_id'] ?? '')) ?: gmdate('YmdHis');
    $quarantine = dirname($rootReal) . DIRECTORY_SEPARATOR . 'teampass-quarantine-' . $scanId;
    $commands = [
        '# Review every path before running this plan as the deployment owner.',
    ];

    $referenceRoots = [];
    try {
        $referencePath = $rootReal . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'files_reference.txt';
        $reference = tpFileIntegrityParseReference($referencePath);
        if (hash_equals((string) ($report['reference_hash'] ?? ''), $reference['reference_hash']) === false) {
            $commands[] = '# The saved report belongs to a different reference manifest.';
            $commands[] = '# Run a new integrity scan before preparing cleanup commands.';
            return $commands;
        }
        foreach (array_keys($reference['files']) as $referenceEntryPath) {
            $referenceRoots[explode('/', $referenceEntryPath, 2)[0]] = true;
        }
    } catch (RuntimeException $exception) {
        $commands[] = '# Reference manifest unavailable: no whole-directory move is proposed.';
        return $commands;
    }

    $quarantinePrepared = false;
    foreach (array_keys($legacyRoots) as $legacyRoot) {
        $normalized = tpFileIntegrityNormalizePath($legacyRoot);
        if ($normalized === null || tpFileIntegrityLegacyRoot($normalized) === null) {
            continue;
        }
        $topLevel = explode('/', $normalized, 2)[0];
        if (isset($referenceRoots[$topLevel])) {
            $commands[] = '# Review legacy entries under ' . escapeshellarg($topLevel)
                . ' individually: this directory also contains current reference files.';
            continue;
        }
        $source = $rootReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        if (file_exists($source) === false) {
            continue;
        }
        if ($quarantinePrepared === false) {
            $commands[] = 'sudo install -d -m 0700 -- ' . escapeshellarg($quarantine);
            $quarantinePrepared = true;
        }
        $destination = $quarantine . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        $commands[] = 'sudo mv -- ' . escapeshellarg($source) . ' ' . escapeshellarg($destination);
    }

    if ((int) ($report['counts']['development'] ?? 0) > 0) {
        $commands[] = '# Remove Composer development packages from the production tree:';
        $commands[] = 'sudo php ' . escapeshellarg(
            $rootReal . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'scripts'
            . DIRECTORY_SEPARATOR . 'cleanup_dev_dependencies.php'
        );
    }

    return $commands;
}

/**
 * Return only executable shell lines for display in the localized web UI.
 * Human-readable comments remain available in the CLI cleanup plan.
 *
 * @return array<int,string>
 */
function tpFileIntegrityCleanupCommands(string $root, array $report): array
{
    return array_values(array_filter(
        tpFileIntegrityCleanupPlan($root, $report),
        static function (string $line): bool {
            return $line !== '' && str_starts_with($line, '#') === false;
        }
    ));
}
