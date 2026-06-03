<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      backups.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;


// Load functions
require_once 'main.functions.php';
require_once __DIR__ . '/backup.functions.php';
$session = SessionManager::getSession();


// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
// Detect if this is a restore continuation call.
// During a database restore, tables used for session/access checks can temporarily disappear.
// We allow continuation calls when they present a valid restore token stored in PHP session.
$earlyType = (string) $request->request->get('type', '');
if ($earlyType === '') {
    $earlyType = (string) $request->query->get('type', '');
}
$restoreToken = (string) $request->request->get('restore_token', '');
$restoreTokenSession = (string) ($session->get('restore-token') ?? '');
$isRestoreContinuation = (
    $earlyType === 'onthefly_restore'
    && $restoreToken !== ''
    && $restoreTokenSession !== ''
    && hash_equals($restoreTokenSession, $restoreToken)
);

$lang = new Language($session->get('user-language') ?? 'english');

// Load config (for restore continuations, prefer the snapshot stored in session)
$configManager = new ConfigManager();
$SETTINGS = [];
if ($isRestoreContinuation === true) {
    $tmpSettings = $session->get('restore-settings');
    if (is_array($tmpSettings) && !empty($tmpSettings)) {
        $SETTINGS = $tmpSettings;
    }
}
if (empty($SETTINGS)) {
    $SETTINGS = $configManager->getAllSettings();
}

// Do checks (skip for restore continuation calls, as the DB can be temporarily inconsistent)
if ($isRestoreContinuation === false) {
    // Do checks
    // Instantiate the class with posted data
    $checkUserAccess = new PerformChecks(
        dataSanitizer(
            [
                'type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
            ],
            [
                'type' => 'trim|escape',
            ],
        ),
        [
            'user_id' => returnIfSet($session->get('user-id'), null),
            'user_key' => returnIfSet($session->get('key'), null),
        ]
    );
    // Handle the case
    echo $checkUserAccess->caseHandler();
    if (
        $checkUserAccess->userAccessPage('backups') === false ||
        $checkUserAccess->checkSession() === false
    ) {
        // Not allowed page
        $session->set('system-error_code', ERR_NOT_ALLOWED);
        include TEAMPASS_ROOT . '/public/error.php';
        exit;
    }
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //


// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (empty($post_type) === true) {
    $post_type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (empty($post_key) === true) {
    $post_key = filter_input(INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}
$post_data = filter_input(
    INPUT_POST,
    'data',
    FILTER_SANITIZE_FULL_SPECIAL_CHARS,
    FILTER_FLAG_NO_ENCODE_QUOTES
);

// manage action required
    if (null !== $post_type) {
    /**
     * Read a setting from teampass_misc (type='settings', intitule=key).
     */
    function tpGetSettingsValue(string $key, string $default = ''): string
    {
        $val = DB::queryFirstField(
            'SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type=%s AND intitule=%s LIMIT 1',
            'settings',
            $key
        );

        return ($val === null || $val === false || $val === '') ? $default : (string) $val;
    }

    /**
     * Upsert a setting into teampass_misc (type='settings', intitule=key).
     */
    function tpUpsertSettingsValue(string $key, string $value): void
    {
        $exists = DB::queryFirstField(
            'SELECT 1 FROM ' . prefixTable('misc') . ' WHERE type=%s AND intitule=%s LIMIT 1',
            'settings',
            $key
        );

        if ((int)$exists === 1) {
            DB::update(
                prefixTable('misc'),
                ['valeur' => $value],
                'type=%s AND intitule=%s',
                'settings',
                $key
            );
        } else {
            DB::insert(
                prefixTable('misc'),
                ['type' => 'settings', 'intitule' => $key, 'valeur' => $value]
            );
        }
    }

    /**
     * Get TeamPass timezone name from teampass_misc (type='admin', intitule='timezone').
     */
    function tpGetAdminTimezoneName(): string
    {
        $tz = DB::queryFirstField(
            'SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type=%s AND intitule=%s LIMIT 1',
            'admin',
            'timezone'
        );

        return (is_string($tz) && $tz !== '') ? $tz : 'UTC';
    }

    function tpFormatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        if ($i === 0) {
            return sprintf('%d %s', (int) $bytes, $units[$i]);
        }

        return sprintf('%.1f %s', $bytes, $units[$i]);
    }

    function tpExternalizedDestinationErrorMessage(Language $lang, string $reason): string
    {
        $map = [
            'EMPTY' => 'bck_externalized_destination_empty',
            'TOO_LONG' => 'bck_externalized_destination_too_long',
            'NOT_ABSOLUTE' => 'bck_externalized_destination_not_absolute',
            'NOT_FOUND' => 'bck_externalized_destination_not_found',
            'NOT_WRITABLE' => 'bck_externalized_destination_not_writable',
            'INSIDE_TEAMPASS' => 'bck_externalized_destination_inside_teampass',
            'UNSUPPORTED_TYPE' => 'bck_externalized_destination_type_unsupported',
        ];

        return (string) $lang->get($map[$reason] ?? 'bck_externalized_destination_invalid');
    }

    function tpRecoveryForgetSessionPackage($session): void
    {
        $current = $session->get('recovery-package-download');
        if (is_array($current) === true && !empty($current['path']) && is_scalar($current['path'])) {
            tpRecoverySafeDeletePackage((string) $current['path']);
        }

        if (method_exists($session, 'remove') === true) {
            $session->remove('recovery-package-download');
        } else {
            $session->set('recovery-package-download', null);
        }
    }

    /**
     * Returns the default directory for scheduled backups (storage/backups).
     */
    function tpGetScheduledBackupDefaultDir(): string
    {
        return defined('TEAMPASS_STORAGE') ? TEAMPASS_STORAGE . '/backups' : __DIR__ . '/../../storage/backups';
    }

    /**
     * Returns the default directory for on-the-fly backups (storage/onthefly).
     * Note: an equivalent helper exists in downloadFile.php and utilities.queries.php
     * because each is a standalone script with its own include chain.
     */
    function tpGetOntheflyBackupDefaultDir(): string
    {
        return defined('TEAMPASS_STORAGE') ? TEAMPASS_STORAGE . '/onthefly' : __DIR__ . '/../../storage/onthefly';
    }

    /**
     * Returns the resolved real path for the on-the-fly backup directory,
     * optionally creating it if it does not exist.
     */
    function tpGetOntheflyBackupDir(bool $create = false): string
    {
        $dir = tpGetOntheflyBackupDefaultDir();
        if ($create === true && !is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        $dirReal = realpath($dir);

        return $dirReal !== false ? $dirReal : rtrim($dir, '/\\');
    }

    /**
     * Normalizes a path string for cross-platform comparison:
     * converts backslashes to forward slashes, strips trailing slashes,
     * and lowercases on Windows.
     */
    function tpNormalizePathForCompare(string $path): string
    {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');

        return DIRECTORY_SEPARATOR === '\\' ? strtolower($normalized) : $normalized;
    }

    /**
     * Returns true if $path is equal to or a direct child of $baseDir,
     * after resolving $baseDir with realpath() to prevent symlink bypass.
     */
    function tpIsResolvedPathInsideDirectory(string $path, string $baseDir): bool
    {
        $baseReal = realpath($baseDir);
        if ($path === '' || $baseReal === false) {
            return false;
        }

        $pathNormalized = tpNormalizePathForCompare($path);
        $baseNormalized = tpNormalizePathForCompare($baseReal);

        return $pathNormalized === $baseNormalized || str_starts_with($pathNormalized, $baseNormalized . '/');
    }

    /**
     * Resolves the real path that would result from creating $path,
     * by walking up to the deepest existing ancestor and appending
     * the missing components. Uses realpath() on the existing portion
     * to neutralize symlinks and ".." segments.
     * Returns '' if no existing ancestor can be found.
     */
    function tpResolveDirectoryCreationPath(string $path): string
    {
        $path = rtrim($path, '/\\');
        if ($path === '') {
            return '';
        }

        $missingParts = [];
        $currentPath = $path;

        while (!is_dir($currentPath)) {
            $parentPath = dirname($currentPath);
            if ($parentPath === $currentPath || $parentPath === '.') {
                return '';
            }

            $missingParts[] = basename($currentPath);
            $currentPath = $parentPath;
        }

        $currentRealPath = realpath($currentPath);
        if ($currentRealPath === false) {
            return '';
        }

        while ($missingParts !== []) {
            $currentRealPath .= DIRECTORY_SEPARATOR . array_pop($missingParts);
        }

        return $currentRealPath;
    }

    /**
     * Resolves and validates the scheduled backup output directory.
     * Falls back to the default storage/backups path when $requestedDir is empty.
     * Creates missing allowed base directories before validation.
     * Returns ['success' => true, 'path' => <real path>] on success,
     * or ['success' => false, 'path' => ''] when the path falls outside all allowed bases.
     *
     * @param string[] $allowedBaseDirs
     * @return array{success: bool, path: string}
     */
    function tpResolveScheduledBackupOutputDir(string $requestedDir, array $allowedBaseDirs): array
    {
        $outputDir = trim($requestedDir);
        if ($outputDir === '') {
            $outputDir = tpGetScheduledBackupDefaultDir();
        }

        if ($outputDir === '') {
            return ['success' => false, 'path' => ''];
        }

        foreach ($allowedBaseDirs as $allowedBaseDir) {
            if (trim($allowedBaseDir) === '') {
                continue;
            }

            if (!is_dir($allowedBaseDir)) {
                $mkdirBaseResult = @mkdir($allowedBaseDir, 0750, true);
                if ($mkdirBaseResult === false && !is_dir($allowedBaseDir)) {
                    continue;
                }
            }
        }

        $resolvedOutputDir = tpResolveDirectoryCreationPath($outputDir);
        if ($resolvedOutputDir === '') {
            return ['success' => false, 'path' => ''];
        }

        $isAllowed = false;
        foreach ($allowedBaseDirs as $allowedBaseDir) {
            if (tpIsResolvedPathInsideDirectory($resolvedOutputDir, $allowedBaseDir) === true) {
                $isAllowed = true;
                break;
            }
        }

        if ($isAllowed === false) {
            return ['success' => false, 'path' => ''];
        }

        if (!is_dir($outputDir)) {
            $mkdirResult = @mkdir($outputDir, 0750, true);
            if ($mkdirResult === false && !is_dir($outputDir)) {
                return ['success' => false, 'path' => ''];
            }
        }

        $dirReal = realpath($outputDir);
        if ($dirReal === false || !is_dir($dirReal)) {
            return ['success' => false, 'path' => ''];
        }

        return ['success' => true, 'path' => $dirReal];
    }

// ---------------------------------------------------------------------
// Restore compatibility helpers
// ---------------------------------------------------------------------
// Compatibility is based on schema level (UPGRADE_MIN_DATE).
// UI must NOT display schema_level; only TeamPass files version.

function tpExpectedTpFilesVersion(): string
{
    if (function_exists('tpGetTpFilesVersion')) {
        $v = (string) tpGetTpFilesVersion();
        if ($v !== '') return $v;
    }
    if (defined('TP_VERSION') && defined('TP_VERSION_MINOR')) {
        return (string) TP_VERSION . '.' . (string) TP_VERSION_MINOR;
    }
    return '';
}

function tpCurrentSchemaLevel(): string
{
    if (function_exists('tpGetSchemaLevel')) {
        return (string) tpGetSchemaLevel();
    }
    if (defined('UPGRADE_MIN_DATE')) {
        $v = (string) UPGRADE_MIN_DATE;
        if (preg_match('/^\d+$/', $v) === 1) return $v;
    }
    return '';
}

/**
 * Check restore compatibility (schema level).
 * - For server backups: reads schema_level from .meta.json when present, otherwise from "-sl<schema>" in filename.
 * - For uploaded restore file: reads schema from "-sl<schema>" preserved in filename stored in teampass_misc.
 *
 * @return array{is_compatible: bool, reason: string, backup_tp_files_version: string|null, expected_tp_files_version: string, mode: string, warnings: array<mixed>, backup_schema_level: string, expected_schema_level: string, resolved_path: string}
 */
function tpCheckRestoreCompatibility(array $SETTINGS, string $serverScope = '', string $serverFile = '', int $operationId = 0): array
{
    $expectedVersion = tpExpectedTpFilesVersion();
    $expectedSchema  = tpCurrentSchemaLevel();

    $backupVersion = null;
    $backupSchema  = '';
    $warnings = [];
    $mode = ($operationId > 0) ? 'upload_schema_only' : 'server_strict';

    // Resolve target file path
    $targetPath = '';
    if ($operationId > 0) {
        // Uploaded restore file (temp_file in misc)
        $data = DB::queryFirstRow(
            'SELECT valeur FROM ' . prefixTable('misc') . ' WHERE increment_id = %i LIMIT 1',
            $operationId
        );
        $val = isset($data['valeur']) ? (string) $data['valeur'] : '';
        if ($val === '') {
            return [
                'is_compatible' => false,
                'reason' => 'MISSING_UPLOAD_ENTRY',
                'mode' => $mode,
                'warnings' => $warnings,
                'backup_schema_level' => '',
                'expected_schema_level' => $expectedSchema,
                'backup_tp_files_version' => null,
                'expected_tp_files_version' => $expectedVersion,
                'resolved_path' => '',
            ];
        }

        $bn = basename($val);
        $baseDir = rtrim((string) ($SETTINGS['path_to_files_folder'] ?? (defined('TEAMPASS_STORAGE') ? TEAMPASS_STORAGE . '/files' : __DIR__ . '/../../storage/files')), '/');
        $targetPath = $baseDir . '/' . $bn;

        if (function_exists('tpParseSchemaLevelFromBackupFilename')) {
            $backupSchema = (string) tpParseSchemaLevelFromBackupFilename($bn);
        }

        // Upload restore has no meta => version cannot be verified by design
        $warnings[] = 'VERSION_NOT_VERIFIED';
    } elseif ($serverFile !== '') {
        $bn = basename(str_replace('\\', '/', $serverFile));
        $extension = strtolower((string) pathinfo($bn, PATHINFO_EXTENSION));
        if ($bn === '' || tpBackupFileExtensionIsSupported($extension) === false) {
            return [
                'is_compatible' => false,
                'reason' => 'INVALID_FILENAME',
                'mode' => $mode,
                'warnings' => $warnings,
                'backup_schema_level' => '',
                'expected_schema_level' => $expectedSchema,
                'backup_tp_files_version' => null,
                'expected_tp_files_version' => $expectedVersion,
                'resolved_path' => '',
            ];
        }

        $baseDir = rtrim(tpGetOntheflyBackupDir(false), '/');
        if ($serverScope === 'scheduled') {
            $dir = (string) tpGetSettingsValue('bck_scheduled_output_dir', tpGetScheduledBackupDefaultDir());
            $resolvedDir = tpBackupResolveScheduledOutputDir($dir, $SETTINGS);
            if ($resolvedDir['success'] === false) {
                return [
                    'is_compatible' => false,
                    'reason' => 'INVALID_BACKUP_DIRECTORY',
                    'mode' => $mode,
                    'warnings' => $warnings,
                    'backup_schema_level' => '',
                    'expected_schema_level' => $expectedSchema,
                    'backup_tp_files_version' => null,
                    'expected_tp_files_version' => $expectedVersion,
                    'resolved_path' => '',
                ];
            }
            $baseDir = rtrim($resolvedDir['path'], '/\\');
        } elseif ($serverScope === 'externalized') {
            if (strpos($bn, 'externalized-') !== 0) {
                return [
                    'is_compatible' => false,
                    'reason' => 'INVALID_FILENAME',
                    'mode' => $mode,
                    'warnings' => $warnings,
                    'backup_schema_level' => '',
                    'expected_schema_level' => $expectedSchema,
                    'backup_tp_files_version' => null,
                    'expected_tp_files_version' => $expectedVersion,
                    'resolved_path' => '',
                ];
            }

            $destinationType = tpBackupResolveExternalizedDestinationType((string) tpGetSettingsValue('bck_externalized_destination_type', 'local_directory'));
            $dir = (string) tpGetSettingsValue('bck_externalized_target_dir', '');
            $resolvedDir = tpBackupResolveExternalizedDestination($destinationType, $dir, $SETTINGS);
            if ($resolvedDir['success'] === false) {
                return [
                    'is_compatible' => false,
                    'reason' => 'INVALID_BACKUP_DIRECTORY',
                    'mode' => $mode,
                    'warnings' => $warnings,
                    'backup_schema_level' => '',
                    'expected_schema_level' => $expectedSchema,
                    'backup_tp_files_version' => null,
                    'expected_tp_files_version' => $expectedVersion,
                    'resolved_path' => '',
                ];
            }

            $baseDir = rtrim((string) $resolvedDir['path'], '/\\');
        }
        $targetPath = $baseDir . '/' . $bn;
        $meta = function_exists('tpReadBackupMetadata') ? tpReadBackupMetadata($targetPath) : [];
        if (is_array($meta['warnings'] ?? null)) {
            $warnings = array_merge($warnings, array_map('strval', $meta['warnings']));
        }
        if (is_array($meta['documents'] ?? null) && is_array($meta['documents']['warnings'] ?? null)) {
            $warnings = array_merge($warnings, array_map('strval', $meta['documents']['warnings']));
        }
        $warnings = array_values(array_unique(array_filter($warnings)));

        $isPackage = tpBackupIsPackageFilename($bn);
        if ($isPackage === true) {
            $backupType = is_scalar($meta['backup_type'] ?? null) ? (string) $meta['backup_type'] : '';
            if (in_array($backupType, ['database_only', 'db_only', 'db_documents', 'database_documents'], true) === false) {
                return [
                    'is_compatible' => false,
                    'reason' => 'PACKAGE_TYPE_UNSUPPORTED',
                    'mode' => $mode,
                    'warnings' => $warnings,
                    'backup_schema_level' => '',
                    'expected_schema_level' => $expectedSchema,
                    'backup_tp_files_version' => null,
                    'expected_tp_files_version' => $expectedVersion,
                    'resolved_path' => $targetPath,
                ];
            }
        }

        if (function_exists('tpGetBackupTpFilesVersionFromMeta')) {
            $v = (string) tpGetBackupTpFilesVersionFromMeta($targetPath);
            if ($v !== '') {
                $backupVersion = $v;
            }
        }
        if (function_exists('tpGetBackupSchemaLevelFromMetaOrFilename')) {
            $backupSchema = (string) tpGetBackupSchemaLevelFromMetaOrFilename($targetPath);
        }
    } else {
        return [
            'is_compatible' => false,
            'reason' => 'NO_TARGET',
            'mode' => $mode,
            'warnings' => $warnings,
            'backup_schema_level' => '',
            'expected_schema_level' => $expectedSchema,
            'backup_tp_files_version' => null,
            'expected_tp_files_version' => $expectedVersion,
            'resolved_path' => '',
        ];
    }

    // File existence check (best-effort, for clearer errors)
    if (file_exists($targetPath) === false) {
        return [
            'is_compatible' => false,
            'reason' => 'FILE_NOT_FOUND',
            'mode' => $mode,
            'warnings' => $warnings,
            'backup_schema_level' => $backupSchema,
            'expected_schema_level' => $expectedSchema,
            'backup_tp_files_version' => $backupVersion,
            'expected_tp_files_version' => $expectedVersion,
            'resolved_path' => $targetPath,
        ];
    }

    // Schema is mandatory in all modes
    if ($backupSchema === '' || $expectedSchema === '') {
        return [
            'is_compatible' => false,
            'reason' => 'MISSING_SCHEMA',
            'mode' => $mode,
            'warnings' => $warnings,
            'backup_schema_level' => $backupSchema,
            'expected_schema_level' => $expectedSchema,
            'backup_tp_files_version' => $backupVersion,
            'expected_tp_files_version' => $expectedVersion,
            'resolved_path' => $targetPath,
        ];
    }

    if ((string) $backupSchema !== (string) $expectedSchema) {
        return [
            'is_compatible' => false,
            'reason' => 'SCHEMA_MISMATCH',
            'mode' => $mode,
            'warnings' => $warnings,
            'backup_schema_level' => $backupSchema,
            'expected_schema_level' => $expectedSchema,
            'backup_tp_files_version' => $backupVersion,
            'expected_tp_files_version' => $expectedVersion,
            'resolved_path' => $targetPath,
        ];
    }

    // Version check is strict for server-side backups only
    if ($mode === 'server_strict') {
        if ($backupVersion === null) {
            return [
                'is_compatible' => false,
                'reason' => 'MISSING_VERSION_METADATA',
                'mode' => $mode,
                'warnings' => $warnings,
                'backup_schema_level' => $backupSchema,
                'expected_schema_level' => $expectedSchema,
                'backup_tp_files_version' => $backupVersion,
                'expected_tp_files_version' => $expectedVersion,
                'resolved_path' => $targetPath,
            ];
        }

        if ((string) $backupVersion !== (string) $expectedVersion) {
            return [
                'is_compatible' => false,
                'reason' => 'VERSION_MISMATCH',
                'mode' => $mode,
                'warnings' => $warnings,
                'backup_schema_level' => $backupSchema,
                'expected_schema_level' => $expectedSchema,
                'backup_tp_files_version' => $backupVersion,
                'expected_tp_files_version' => $expectedVersion,
                'resolved_path' => $targetPath,
            ];
        }
    }

    return [
        'is_compatible' => true,
        'reason' => '',
        'mode' => $mode,
        'warnings' => $warnings,
        'backup_schema_level' => $backupSchema,
        'expected_schema_level' => $expectedSchema,
        'backup_tp_files_version' => $backupVersion,
        'expected_tp_files_version' => $expectedVersion,
        'resolved_path' => $targetPath,
    ];
}
    switch ($post_type) {
        
        case 'scheduled_download_backup':
            // Download a scheduled backup file (encrypted) from the server
            if ($post_key !== $session->get('key') || (int) $session->get('user-admin') !== 1) {
                header('HTTP/1.1 403 Forbidden');
                exit;
            }

            $get_key_tmp = filter_input(INPUT_GET, 'key_tmp', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if (empty($get_key_tmp) === true || $get_key_tmp !== (string) $session->get('user-key_tmp')) {
                header('HTTP/1.1 403 Forbidden');
                exit;
            }

            $get_file = filter_input(INPUT_GET, 'file', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $get_file = basename((string) $get_file);

            // Safety check: only known backup files allowed
            $extension = (string) pathinfo($get_file, PATHINFO_EXTENSION);
            if (tpBackupFileExtensionIsSupported($extension) === false) {
                header('HTTP/1.1 400 Bad Request');
                exit;
            }

            // Safety check: only scheduled backup files allowed
            if ($get_file === '' || strpos($get_file, 'scheduled-') !== 0) {
                header('HTTP/1.1 400 Bad Request');
                exit;
            }

            $dir = (string) tpGetSettingsValue('bck_scheduled_output_dir', tpGetScheduledBackupDefaultDir());
            $resolvedDir = tpBackupResolveScheduledOutputDir($dir, $SETTINGS);
            if ($resolvedDir['success'] === false) {
                header('HTTP/1.1 404 Not Found');
                exit;
            }
            $dir = $resolvedDir['path'];
            $fp = rtrim($dir, '/') . '/' . $get_file;

            $dirReal = realpath($dir);
            $fpReal = realpath($fp);

            if ($dirReal === false || $fpReal === false || strpos($fpReal, $dirReal . DIRECTORY_SEPARATOR) !== 0 || is_file($fpReal) === false) {
                header('HTTP/1.1 404 Not Found');
                exit;
            }

            /**
             * Stream file with proper error handling
             * Removes output buffers, sets execution time limit, and validates file
             *
             * @param string $fpReal Real file path
             * @return int File size in bytes, 0 if file doesn't exist
             */
            // Set unlimited execution time if function is available and not disabled
            if (function_exists('set_time_limit') && !ini_get('safe_mode')) {
                set_time_limit(0);
            }

            // Get file size with proper validation
            $size = 0;
            if (file_exists($fpReal) && is_readable($fpReal)) {
                $size = (int) filesize($fpReal);
            }

            // Clear all output buffers
            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
            }

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $get_file . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: private, must-revalidate');
            header('Pragma: public');
            if ($size > 0) {
                header('Content-Length: ' . $size);
            }

            readfile($fpReal);
            exit;

        case 'externalized_download_backup':
            // Download an externalized backup file (encrypted) from the configured destination.
            if ($post_key !== $session->get('key') || (int) $session->get('user-admin') !== 1) {
                header('HTTP/1.1 403 Forbidden');
                exit;
            }

            $get_key_tmp = filter_input(INPUT_GET, 'key_tmp', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if (empty($get_key_tmp) === true || $get_key_tmp !== (string) $session->get('user-key_tmp')) {
                header('HTTP/1.1 403 Forbidden');
                exit;
            }

            $get_file = filter_input(INPUT_GET, 'file', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $get_file = basename(str_replace('\\', '/', (string) $get_file));

            $extension = (string) pathinfo($get_file, PATHINFO_EXTENSION);
            if ($get_file === '' || strpos($get_file, 'externalized-') !== 0 || tpBackupFileExtensionIsSupported($extension) === false) {
                header('HTTP/1.1 400 Bad Request');
                exit;
            }

            $destinationType = tpBackupResolveExternalizedDestinationType((string) tpGetSettingsValue('bck_externalized_destination_type', 'local_directory'));
            $dir = (string) tpGetSettingsValue('bck_externalized_target_dir', '');
            $resolvedDir = tpBackupResolveExternalizedDestination($destinationType, $dir, $SETTINGS);
            if ($resolvedDir['success'] === false) {
                header('HTTP/1.1 404 Not Found');
                exit;
            }
            $dir = $resolvedDir['path'];
            $fp = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $get_file;

            $dirReal = realpath($dir);
            $fpReal = realpath($fp);

            if ($dirReal === false || $fpReal === false || tpBackupIsResolvedPathInsideDirectory($fpReal, $dirReal) === false || is_file($fpReal) === false) {
                header('HTTP/1.1 404 Not Found');
                exit;
            }

            if (function_exists('set_time_limit') && !ini_get('safe_mode')) {
                set_time_limit(0);
            }

            $size = 0;
            if (file_exists($fpReal) && is_readable($fpReal)) {
                $size = (int) filesize($fpReal);
            }

            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
            }

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $get_file . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: private, must-revalidate');
            header('Pragma: public');
            if ($size > 0) {
                header('Content-Length: ' . $size);
            }

            readfile($fpReal);
            exit;

        case 'recovery_package_download':
            // Download a one-time manual recovery package, then remove it from temp storage.
            if ($post_key !== $session->get('key') || (int) $session->get('user-admin') !== 1) {
                header('HTTP/1.1 403 Forbidden');
                exit;
            }

            $get_key_tmp = filter_input(INPUT_GET, 'key_tmp', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if (empty($get_key_tmp) === true || $get_key_tmp !== (string) $session->get('user-key_tmp')) {
                header('HTTP/1.1 403 Forbidden');
                exit;
            }

            $get_token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $download = $session->get('recovery-package-download');
            if (is_array($download) === false || empty($download['token']) || empty($download['path']) || empty($download['filename'])) {
                header('HTTP/1.1 404 Not Found');
                exit;
            }

            if ($get_token === null || hash_equals((string) $download['token'], (string) $get_token) === false) {
                header('HTTP/1.1 403 Forbidden');
                exit;
            }

            if ((int) ($download['expires_at'] ?? 0) < time()) {
                tpRecoveryForgetSessionPackage($session);
                header('HTTP/1.1 410 Gone');
                exit;
            }

            $fpReal = realpath((string) $download['path']);
            if ($fpReal === false || tpRecoveryPackagePathIsSafe($fpReal) === false) {
                tpRecoveryForgetSessionPackage($session);
                header('HTTP/1.1 404 Not Found');
                exit;
            }

            $filename = basename((string) $download['filename']);
            if (
                $filename === ''
                || strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) !== tpRecoveryGetPackageExtension()
                || str_starts_with($filename, 'recovery-') === false
            ) {
                tpRecoveryForgetSessionPackage($session);
                header('HTTP/1.1 400 Bad Request');
                exit;
            }

            if (function_exists('set_time_limit') && !ini_get('safe_mode')) {
                set_time_limit(0);
            }

            $size = is_readable($fpReal) ? (int) filesize($fpReal) : 0;

            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
            }

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: private, must-revalidate');
            header('Pragma: public');
            if ($size > 0) {
                header('Content-Length: ' . $size);
            }

            if (function_exists('ignore_user_abort')) {
                ignore_user_abort(true);
            }

            readfile($fpReal);
            tpRecoveryForgetSessionPackage($session);
            exit;

        //CASE adding a new function
        case 'onthefly_backup':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-admin') === 0) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }
        
            // Decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );
        
            // Prepare variables
            $encryptionKey = filter_var($dataReceived['encryptionKey'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $includeDocuments = (int)($dataReceived['include_documents'] ?? 0) === 1;
            $backupFormat = tpBackupNormalizeRequestedFormat((string) ($dataReceived['backup_format'] ?? $dataReceived['format'] ?? 'sql'));
            if ($includeDocuments === true) {
                $backupFormat = tpBackupGetPackageExtension();
            }

            // Optional comment (stored in <backup>.meta.json)
            $comment = isset($dataReceived['comment']) ? (string) $dataReceived['comment'] : '';
            $comment = str_replace("\0", '', $comment);
            $comment = trim($comment);
            $commentLen = function_exists('mb_strlen') ? (int) mb_strlen($comment) : (int) strlen($comment);
            if ($commentLen > 2000) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('bck_onthefly_comment_too_long'),
                    ),
                    'encode'
                );
                break;
            }

            if ($backupFormat === tpBackupGetPackageExtension()) {
                $backupResult = tpCreateBackupPackage(
                    $SETTINGS,
                    $encryptionKey,
                    [
                        'output_dir' => tpGetOntheflyBackupDir(true),
                        'source' => 'onthefly',
                        'comment' => $comment,
                        'include_documents' => $includeDocuments,
                    ]
                );
            } else {
                $backupResult = tpCreateDatabaseBackup(
                    $SETTINGS,
                    $encryptionKey,
                    ['output_dir' => tpGetOntheflyBackupDir(true)]
                );
            }

            if ($backupResult['success'] !== true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $backupResult['message'],
                    ),
                    'encode'
                );
                break;
            }

            $filename = $backupResult['filename'];
            // Write metadata sidecar (<backup>.meta.json) for fast listings / migration safety
            try {
                if ($backupFormat !== tpBackupGetPackageExtension() && function_exists('tpWriteBackupMetadata') && !empty($backupResult['filepath'])) {
                    $extraMeta = ['source' => 'onthefly'];
                    if ($comment !== '') {
                        $extraMeta['comment'] = $comment;
                    }
                    tpWriteBackupMetadata((string)$backupResult['filepath'], '', '', $extraMeta);
                }
            } catch (Throwable $ignored) {
                // best effort
            }
        
            // Generate 2d key
            $session->set('user-key_tmp', GenerateCryptKey(16, false, true, true, false, true));
        
            // Update LOG
            logEvents(
                $SETTINGS,
                'admin_action',
                'dataBase backup',
                (string) $session->get('user-id'),
                $session->get('user-login'),
                $filename
            );
        
            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                    'download' => 'sources/downloadFile.php?name=' . urlencode($filename) .
                        '&action=backup&file=' . urlencode($filename) . '&type=' . tpBackupGetDownloadTypeForFilename($filename) . '&key=' . $session->get('key') . '&key_tmp=' .
                        $session->get('user-key_tmp') . '&pathIsOnthefly=1',
                    'backup_format' => $backupFormat,
                ),
                'encode'
            );
            break;
        /* ============================================================
         * Scheduled backups (UI)
         * ============================================================ */

        case 'scheduled_get_settings':
            if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
                echo prepareExchangedData(['error' => true, 'message' => 'Not allowed'], 'encode');
                break;
            }

            $scheduledOutputDir = (string) tpGetSettingsValue('bck_scheduled_output_dir', '');
            $resolvedScheduledOutputDir = tpBackupResolveScheduledOutputDir($scheduledOutputDir, $SETTINGS);
            if ($resolvedScheduledOutputDir['success'] === true && $scheduledOutputDir !== $resolvedScheduledOutputDir['path']) {
                $scheduledOutputDir = $resolvedScheduledOutputDir['path'];
                tpUpsertSettingsValue('bck_scheduled_output_dir', $scheduledOutputDir);
            }

            echo prepareExchangedData([
                'error' => false,
                'settings' => [
                    'enabled' => (int) tpGetSettingsValue('bck_scheduled_enabled', '0'),
                    'frequency' => (string) tpGetSettingsValue('bck_scheduled_frequency', 'daily'),
                    'time' => (string) tpGetSettingsValue('bck_scheduled_time', '02:00'),
                    'dow' => (int) tpGetSettingsValue('bck_scheduled_dow', '1'),
                    'dom' => (int) tpGetSettingsValue('bck_scheduled_dom', '1'),
                    'output_dir' => $scheduledOutputDir,
                    'retention_days' => (int) tpGetSettingsValue('bck_scheduled_retention_days', '30'),
                    'backup_format' => tpBackupNormalizeRequestedFormat((string) tpGetSettingsValue('bck_scheduled_format', 'sql')),
                    'include_documents' => (int) tpGetSettingsValue('bck_scheduled_include_documents', '0'),

                    'next_run_at' => (int) tpGetSettingsValue('bck_scheduled_next_run_at', '0'),
                    'last_run_at' => (int) tpGetSettingsValue('bck_scheduled_last_run_at', '0'),
                    'last_status' => (string) tpGetSettingsValue('bck_scheduled_last_status', ''),
                    'last_message' => (string) tpGetSettingsValue('bck_scheduled_last_message', ''),
                    'last_completed_at' => (int) tpGetSettingsValue('bck_scheduled_last_completed_at', '0'),
                    'last_purge_at' => (int) tpGetSettingsValue('bck_scheduled_last_purge_at', '0'),
                    'last_purge_deleted' => (int) tpGetSettingsValue('bck_scheduled_last_purge_deleted', '0'),

                    'email_report_enabled' => (int) tpGetSettingsValue('bck_scheduled_email_report_enabled', '0'),
                    'email_report_only_failures' => (int) tpGetSettingsValue('bck_scheduled_email_report_only_failures', '0'),

                    'timezone' => tpGetAdminTimezoneName(),
                ],
            ], 'encode');
            break;

        case 'disk_usage':
            // Provide disk usage information for the storage containing the <files> directory
            if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
                echo prepareExchangedData(['error' => true, 'message' => 'Not allowed'], 'encode');
                break;
            }

            $baseFilesDir = (string)(string) ($SETTINGS['path_to_files_folder'] ?? (defined('TEAMPASS_STORAGE') ? TEAMPASS_STORAGE . '/files' : __DIR__ . '/../../storage/files'));
            $dirReal = realpath($baseFilesDir);

            if ($dirReal === false) {
                echo prepareExchangedData(['error' => true, 'message' => 'Invalid path'], 'encode');
                break;
            }

            $total = @disk_total_space($dirReal);
            $free = @disk_free_space($dirReal);

            if ($total === false || $free === false || (float)$total <= 0) {
                echo prepareExchangedData(['error' => true, 'message' => 'Unable to read disk usage'], 'encode');
                break;
            }

            $used = max(0.0, (float)$total - (float)$free);
            $pct = round(($used / (float)$total) * 100, 1);

            $label = tpFormatBytes($used) . ' / ' . tpFormatBytes((float)$total);
            $tooltip = sprintf(
                $lang->get('bck_storage_usage_tooltip'),
                tpFormatBytes($used),
                tpFormatBytes((float)$total),
                (string)$pct,
                tpFormatBytes((float)$free),
                $dirReal
            );

            echo prepareExchangedData(
                [
                    'error' => false,
                    'used_percent' => $pct,
                    'label' => $label,
                    'tooltip' => $tooltip,
                    'path' => $dirReal,
                ],
                'encode'
            );
            break;

        case 'externalized_get_settings':
            if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
                echo prepareExchangedData(['error' => true, 'message' => 'Not allowed'], 'encode');
                break;
            }

            echo prepareExchangedData(
                [
                    'error' => false,
                    'settings' => [
                        'enabled' => (int) tpGetSettingsValue('bck_externalized_enabled', '0'),
                        'destination_type' => tpBackupResolveExternalizedDestinationType((string) tpGetSettingsValue('bck_externalized_destination_type', 'local_directory')),
                        'target_dir' => (string) tpGetSettingsValue('bck_externalized_target_dir', ''),
                        'backup_format' => tpBackupNormalizeRequestedFormat((string) tpGetSettingsValue('bck_externalized_format', tpBackupGetPackageExtension())),
                        'include_documents' => (int) tpGetSettingsValue('bck_externalized_include_documents', '0'),
                        'retention_days' => (int) tpGetSettingsValue('bck_externalized_retention_days', '30'),
                        'retention_count' => (int) tpGetSettingsValue('bck_externalized_retention_count', '10'),
                        'retry_attempts' => (int) tpGetSettingsValue('bck_externalized_retry_attempts', '3'),
                        'retry_delay_seconds' => (int) tpGetSettingsValue('bck_externalized_retry_delay_seconds', '5'),
                        'last_test_at' => (int) tpGetSettingsValue('bck_externalized_last_test_at', '0'),
                        'last_run_at' => (int) tpGetSettingsValue('bck_externalized_last_run_at', '0'),
                        'last_completed_at' => (int) tpGetSettingsValue('bck_externalized_last_completed_at', '0'),
                        'last_status' => (string) tpGetSettingsValue('bck_externalized_last_status', ''),
                        'last_message' => (string) tpGetSettingsValue('bck_externalized_last_message', ''),
                        'last_file' => (string) tpGetSettingsValue('bck_externalized_last_file', ''),
                        'last_size_bytes' => (int) tpGetSettingsValue('bck_externalized_last_size_bytes', '0'),
                        'last_purge_at' => (int) tpGetSettingsValue('bck_externalized_last_purge_at', '0'),
                        'last_purge_deleted' => (int) tpGetSettingsValue('bck_externalized_last_purge_deleted', '0'),
                    ],
                ],
                'encode'
            );
            break;

        case 'externalized_save_settings':
            if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
                echo prepareExchangedData(['error' => true, 'message' => 'Not allowed'], 'encode');
                break;
            }

            $dataReceived = prepareExchangedData($post_data, 'decode');
            if (!is_array($dataReceived)) {
                $dataReceived = [];
            }

            $enabled = ((int)($dataReceived['enabled'] ?? 0) === 1) ? 1 : 0;
            $destinationType = tpBackupNormalizeExternalizedDestinationType((string)($dataReceived['destination_type'] ?? 'local_directory'));
            if (tpBackupExternalizedDestinationTypeIsSupported($destinationType) === false) {
                echo prepareExchangedData(
                    [
                        'error' => true,
                        'message' => tpExternalizedDestinationErrorMessage($lang, 'UNSUPPORTED_TYPE'),
                    ],
                    'encode'
                );
                break;
            }
            $targetDir = trim(str_replace("\0", '', (string)($dataReceived['target_dir'] ?? '')));
            if (strlen($targetDir) > 1024) {
                echo prepareExchangedData(
                    [
                        'error' => true,
                        'message' => tpExternalizedDestinationErrorMessage($lang, 'TOO_LONG'),
                    ],
                    'encode'
                );
                break;
            }
            $includeDocuments = ((int)($dataReceived['include_documents'] ?? 0) === 1) ? 1 : 0;
            $backupFormat = tpBackupNormalizeRequestedFormat((string)($dataReceived['backup_format'] ?? tpBackupGetPackageExtension()));
            if ($includeDocuments === 1) {
                $backupFormat = tpBackupGetPackageExtension();
            }
            $retentionDays = (int)($dataReceived['retention_days'] ?? 30);
            if ($retentionDays < 1) {
                $retentionDays = 1;
            }
            if ($retentionDays > 3650) {
                $retentionDays = 3650;
            }
            $retentionCount = (int)($dataReceived['retention_count'] ?? 10);
            if ($retentionCount < 1) {
                $retentionCount = 1;
            }
            if ($retentionCount > 999) {
                $retentionCount = 999;
            }
            $retryAttempts = (int)($dataReceived['retry_attempts'] ?? 3);
            if ($retryAttempts < 1) {
                $retryAttempts = 1;
            }
            if ($retryAttempts > 5) {
                $retryAttempts = 5;
            }
            $retryDelaySeconds = (int)($dataReceived['retry_delay_seconds'] ?? 5);
            if ($retryDelaySeconds < 0) {
                $retryDelaySeconds = 0;
            }
            if ($retryDelaySeconds > 60) {
                $retryDelaySeconds = 60;
            }

            if ($enabled === 1) {
                $resolvedTarget = tpBackupResolveExternalizedDestination($destinationType, $targetDir, $SETTINGS);
                if ($resolvedTarget['success'] === false) {
                    echo prepareExchangedData(
                        [
                            'error' => true,
                            'message' => tpExternalizedDestinationErrorMessage($lang, (string) $resolvedTarget['reason']),
                        ],
                        'encode'
                    );
                    break;
                }
                $targetDir = (string) $resolvedTarget['path'];
            }

            tpUpsertSettingsValue('bck_externalized_enabled', (string)$enabled);
            tpUpsertSettingsValue('bck_externalized_destination_type', $destinationType);
            tpUpsertSettingsValue('bck_externalized_target_dir', $targetDir);
            tpUpsertSettingsValue('bck_externalized_format', $backupFormat);
            tpUpsertSettingsValue('bck_externalized_include_documents', (string)$includeDocuments);
            tpUpsertSettingsValue('bck_externalized_retention_days', (string)$retentionDays);
            tpUpsertSettingsValue('bck_externalized_retention_count', (string)$retentionCount);
            tpUpsertSettingsValue('bck_externalized_retry_attempts', (string)$retryAttempts);
            tpUpsertSettingsValue('bck_externalized_retry_delay_seconds', (string)$retryDelaySeconds);

            echo prepareExchangedData(
                [
                    'error' => false,
                    'message' => $lang->get('bck_externalized_settings_saved'),
                    'settings' => [
                        'enabled' => $enabled,
                        'destination_type' => $destinationType,
                        'target_dir' => $targetDir,
                        'backup_format' => $backupFormat,
                        'include_documents' => $includeDocuments,
                        'retention_days' => $retentionDays,
                        'retention_count' => $retentionCount,
                        'retry_attempts' => $retryAttempts,
                        'retry_delay_seconds' => $retryDelaySeconds,
                    ],
                ],
                'encode'
            );
            break;

        case 'externalized_test_destination':
            if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
                echo prepareExchangedData(['error' => true, 'message' => 'Not allowed'], 'encode');
                break;
            }

            $dataReceived = prepareExchangedData($post_data, 'decode');
            if (!is_array($dataReceived)) {
                $dataReceived = [];
            }

            $destinationType = tpBackupResolveExternalizedDestinationType((string)($dataReceived['destination_type'] ?? tpGetSettingsValue('bck_externalized_destination_type', 'local_directory')));
            $targetDir = trim(str_replace("\0", '', (string)($dataReceived['target_dir'] ?? tpGetSettingsValue('bck_externalized_target_dir', ''))));
            $resolvedTarget = tpBackupResolveExternalizedDestination($destinationType, $targetDir, $SETTINGS);
            $now = time();

            if ($resolvedTarget['success'] === false) {
                $message = tpExternalizedDestinationErrorMessage($lang, (string) $resolvedTarget['reason']);
                tpUpsertSettingsValue('bck_externalized_last_test_at', (string)$now);
                tpUpsertSettingsValue('bck_externalized_last_status', 'failed');
                tpUpsertSettingsValue('bck_externalized_last_message', $message);

                echo prepareExchangedData(
                    [
                        'error' => true,
                        'message' => $message,
                        'last_test_at' => $now,
                        'last_status' => 'failed',
                    ],
                    'encode'
                );
                break;
            }

            $message = (string) $lang->get('bck_externalized_test_ok');
            tpUpsertSettingsValue('bck_externalized_last_test_at', (string)$now);
            tpUpsertSettingsValue('bck_externalized_last_status', 'ok');
            tpUpsertSettingsValue('bck_externalized_last_message', $message);

            echo prepareExchangedData(
                [
                    'error' => false,
                    'message' => $message,
                    'path' => (string) $resolvedTarget['path'],
                    'last_test_at' => $now,
                    'last_status' => 'ok',
                ],
                'encode'
            );
            break;

        case 'externalized_run_now':
            if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
                echo prepareExchangedData(['error' => true, 'message' => 'Not allowed'], 'encode');
                break;
            }

            if ((int) tpGetSettingsValue('bck_externalized_enabled', '0') !== 1) {
                echo prepareExchangedData(['error' => true, 'message' => $lang->get('bck_externalized_disabled')], 'encode');
                break;
            }

            $destinationType = tpBackupResolveExternalizedDestinationType((string) tpGetSettingsValue('bck_externalized_destination_type', 'local_directory'));
            $targetDir = (string) tpGetSettingsValue('bck_externalized_target_dir', '');
            $resolvedTarget = tpBackupResolveExternalizedDestination($destinationType, $targetDir, $SETTINGS);
            if ($resolvedTarget['success'] === false) {
                echo prepareExchangedData(
                    [
                        'error' => true,
                        'message' => tpExternalizedDestinationErrorMessage($lang, (string) $resolvedTarget['reason']),
                    ],
                    'encode'
                );
                break;
            }
            $targetDir = (string) $resolvedTarget['path'];
            tpUpsertSettingsValue('bck_externalized_target_dir', $targetDir);

            $resolvedBackupScriptPasskey = tpResolveBackupScriptPasskey($SETTINGS, true);
            $instanceKey = !empty($resolvedBackupScriptPasskey['success'])
                ? (string) $resolvedBackupScriptPasskey['clear_key']
                : '';
            if ($instanceKey === '') {
                echo prepareExchangedData(['error' => true, 'message' => $lang->get('bck_externalized_missing_instance_key')], 'encode');
                break;
            }

            $pending = (int)DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable('background_tasks') . '
                 WHERE process_type=%s AND is_in_progress IN (0,1)
                   AND (finished_at IS NULL OR finished_at = "" OR finished_at = 0)',
                'externalized_backup'
            );
            if ($pending > 0) {
                echo prepareExchangedData(['error' => true, 'message' => $lang->get('bck_externalized_task_already_pending')], 'encode');
                break;
            }

            $now = time();
            $backupFormat = tpBackupNormalizeRequestedFormat((string) tpGetSettingsValue('bck_externalized_format', tpBackupGetPackageExtension()));
            $includeDocuments = ((int) tpGetSettingsValue('bck_externalized_include_documents', '0') === 1) ? 1 : 0;
            if ($includeDocuments === 1) {
                $backupFormat = tpBackupGetPackageExtension();
            }
            $retryAttempts = max(1, min(5, (int) tpGetSettingsValue('bck_externalized_retry_attempts', '3')));
            $retryDelaySeconds = max(0, min(60, (int) tpGetSettingsValue('bck_externalized_retry_delay_seconds', '5')));
            DB::insert(
                prefixTable('background_tasks'),
                [
                    'created_at' => (string)$now,
                    'process_type' => 'externalized_backup',
                    'arguments' => json_encode(
                        [
                            'output_dir' => $targetDir,
                            'destination_type' => $destinationType,
                            'source' => 'externalized',
                            'initiator_user_id' => (int) $session->get('user-id'),
                            'backup_format' => $backupFormat,
                            'include_documents' => $includeDocuments,
                            'retry_attempts' => $retryAttempts,
                            'retry_delay_seconds' => $retryDelaySeconds,
                        ],
                        JSON_UNESCAPED_SLASHES
                    ),
                    'is_in_progress' => 0,
                    'status' => 'new',
                ]
            );

            tpUpsertSettingsValue('bck_externalized_last_run_at', (string)$now);
            tpUpsertSettingsValue('bck_externalized_last_status', 'queued');
            tpUpsertSettingsValue('bck_externalized_last_message', (string) $lang->get('bck_externalized_task_queued'));

            if (function_exists('triggerBackgroundHandler')) {
                triggerBackgroundHandler();
            }

            echo prepareExchangedData(
                [
                    'error' => false,
                    'message' => $lang->get('bck_externalized_task_queued'),
                    'last_run_at' => $now,
                ],
                'encode'
            );
            break;

        case 'externalized_list_backups':
            if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
                echo prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
                break;
            }

            $destinationType = tpBackupResolveExternalizedDestinationType((string) tpGetSettingsValue('bck_externalized_destination_type', 'local_directory'));
            $dir = (string) tpGetSettingsValue('bck_externalized_target_dir', '');
            $resolvedListDir = tpBackupResolveExternalizedDestination($destinationType, $dir, $SETTINGS);
            if ($resolvedListDir['success'] === false) {
                echo prepareExchangedData(
                    [
                        'error' => true,
                        'message' => tpExternalizedDestinationErrorMessage($lang, (string) $resolvedListDir['reason']),
                    ],
                    'encode'
                );
                break;
            }
            $dir = (string) $resolvedListDir['path'];

            $keyTmp = (string) $session->get('user-key_tmp');
            if ($keyTmp === '') {
                $keyTmp = GenerateCryptKey(16, false, true, true, false, true);
                $session->set('user-key_tmp', $keyTmp);
            }

            $files = [];
            $globDir = rtrim(str_replace('\\', '/', $dir), '/');
            $paths = array_merge(
                glob($globDir . '/externalized-*.sql') ?: [],
                glob($globDir . '/externalized-*.' . tpBackupGetPackageExtension()) ?: []
            );

            foreach ($paths as $fp) {
                $bn = basename($fp);
                $extension = (string) pathinfo($bn, PATHINFO_EXTENSION);
                if ($bn === '' || strpos($bn, 'externalized-') !== 0 || tpBackupFileExtensionIsSupported($extension) === false) {
                    continue;
                }

                $fpReal = realpath($fp);
                if ($fpReal === false || tpBackupIsResolvedPathInsideDirectory($fpReal, $dir) === false || is_file($fpReal) === false) {
                    continue;
                }

                $meta = function_exists('tpReadBackupMetadata') ? tpReadBackupMetadata($fpReal) : [];
                $isPackage = tpBackupIsPackageFilename($bn);
                $backupType = is_scalar($meta['backup_type'] ?? null) ? (string) $meta['backup_type'] : ($isPackage ? '' : 'database_only');
                $restoreSupported = $isPackage === false || in_array($backupType, ['database_only', 'db_only', 'db_documents', 'database_documents'], true) === true;
                $files[] = [
                    'name' => $bn,
                    'size_bytes' => (int)@filesize($fpReal),
                    'mtime' => (int)@filemtime($fpReal),
                    'tp_files_version' => (function_exists('tpGetBackupTpFilesVersionFromMeta') ? ((($v = (string)tpGetBackupTpFilesVersionFromMeta($fpReal)) !== '') ? $v : null) : null),
                    'backup_format' => $isPackage ? tpBackupGetPackageFormatId() : 'sql',
                    'backup_format_version' => $isPackage ? (int) ($meta['backup_format_version'] ?? tpBackupGetPackageFormatVersion()) : null,
                    'backup_type' => $backupType !== '' ? $backupType : 'unknown',
                    'restore_supported' => $restoreSupported,
                    'download' => 'sources/backups.queries.php?type=externalized_download_backup&file=' . urlencode($bn)
                        . '&key=' . urlencode((string) $session->get('key'))
                        . '&key_tmp=' . urlencode($keyTmp),
                ];
            }

            usort($files, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);

            echo prepareExchangedData(['error' => false, 'dir' => $dir, 'files' => $files], 'encode');
            break;

        case 'externalized_delete_backup':
            if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
                echo prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
                break;
            }

            $dataReceived = prepareExchangedData($post_data, 'decode');
            if (!is_array($dataReceived)) {
                $dataReceived = [];
            }

            $file = basename(str_replace('\\', '/', (string)($dataReceived['file'] ?? '')));
            $extension = (string) pathinfo($file, PATHINFO_EXTENSION);
            if ($file === '' || strpos($file, 'externalized-') !== 0 || tpBackupFileExtensionIsSupported($extension) === false) {
                echo prepareExchangedData(['error' => true, 'message' => 'Invalid filename'], 'encode');
                break;
            }

            $destinationType = tpBackupResolveExternalizedDestinationType((string) tpGetSettingsValue('bck_externalized_destination_type', 'local_directory'));
            $dir = (string) tpGetSettingsValue('bck_externalized_target_dir', '');
            $resolvedDeleteDir = tpBackupResolveExternalizedDestination($destinationType, $dir, $SETTINGS);
            if ($resolvedDeleteDir['success'] === false) {
                echo prepareExchangedData(
                    [
                        'error' => true,
                        'message' => tpExternalizedDestinationErrorMessage($lang, (string) $resolvedDeleteDir['reason']),
                    ],
                    'encode'
                );
                break;
            }

            $dir = (string) $resolvedDeleteDir['path'];
            $fp = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $file;
            $fpReal = realpath($fp);
            if ($fpReal === false || tpBackupIsResolvedPathInsideDirectory($fpReal, $dir) === false || is_file($fpReal) === false) {
                echo prepareExchangedData(['error' => false], 'encode');
                break;
            }

            if (is_writable($fpReal) === false) {
                $errorMessage = "File is not writable, cannot delete: " . $fpReal;
                if (WIP === true) {
                    error_log("TeamPass - " . $errorMessage);
                }
                echo prepareExchangedData(['error' => true, 'message' => $errorMessage], 'encode');
                break;
            }

            if (unlink($fpReal) === false) {
                $errorMessage = "Failed to delete file: " . $fpReal;
                if (WIP === true) {
                    error_log("TeamPass - " . $errorMessage);
                }
                echo prepareExchangedData(['error' => true, 'message' => $errorMessage], 'encode');
                break;
            }

            $metaPath = tpGetBackupMetadataPath($fpReal);
            if (file_exists($metaPath) === true) {
                @unlink($metaPath);
            }

            echo prepareExchangedData(['error' => false], 'encode');
            break;

        case 'recovery_package_create':
            if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
                echo prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
                break;
            }

            $dataReceived = prepareExchangedData($post_data, 'decode');
            if (!is_array($dataReceived)) {
                $dataReceived = [];
            }

            $passphrase = str_replace("\0", '', (string) ($dataReceived['passphrase'] ?? ''));
            $passphraseLength = function_exists('mb_strlen') ? (int) mb_strlen($passphrase) : (int) strlen($passphrase);
            if ($passphraseLength < 12) {
                echo prepareExchangedData(
                    [
                        'error' => true,
                        'message' => $lang->get('bck_recovery_package_passphrase_too_short'),
                    ],
                    'encode'
                );
                break;
            }

            tpRecoveryForgetSessionPackage($session);
            $package = tpCreateRecoveryPackage($SETTINGS, $passphrase, ['output_dir' => (string) sys_get_temp_dir()]);
            if (empty($package['success']) || empty($package['filepath']) || empty($package['filename'])) {
                $details = !empty($package['message']) ? ' ' . tpSafeUtf8String((string) $package['message']) : '';
                echo prepareExchangedData(
                    [
                        'error' => true,
                        'message' => $lang->get('bck_recovery_package_create_failed') . $details,
                    ],
                    'encode'
                );
                break;
            }

            $keyTmp = (string) $session->get('user-key_tmp');
            if ($keyTmp === '') {
                $keyTmp = GenerateCryptKey(16, false, true, true, false, true);
                $session->set('user-key_tmp', $keyTmp);
            }

            $downloadToken = GenerateCryptKey(32, false, true, true, false, true);
            $expiresAt = time() + 600;
            $session->set(
                'recovery-package-download',
                [
                    'token' => $downloadToken,
                    'path' => (string) $package['filepath'],
                    'filename' => (string) $package['filename'],
                    'expires_at' => $expiresAt,
                    'size_bytes' => (int) ($package['size_bytes'] ?? 0),
                ]
            );

            echo prepareExchangedData(
                [
                    'error' => false,
                    'message' => $lang->get('bck_recovery_package_created'),
                    'filename' => (string) $package['filename'],
                    'size_bytes' => (int) ($package['size_bytes'] ?? 0),
                    'expires_at' => $expiresAt,
                    'warnings' => is_array($package['warnings'] ?? null) ? $package['warnings'] : [],
                    'download' => 'sources/backups.queries.php?type=recovery_package_download'
                        . '&token=' . urlencode($downloadToken)
                        . '&key=' . urlencode((string) $session->get('key'))
                        . '&key_tmp=' . urlencode($keyTmp),
                ],
                'encode'
            );
            break;

        
        case 'copy_instance_key':
            // Return decrypted instance key (admin only)
            if ($post_key !== $session->get('key') || (int) $session->get('user-admin') !== 1) {
                echo prepareExchangedData(
                    array('error' => true, 'message' => $lang->get('error_not_allowed_to')),
                    'encode'
                );
                break;
            }

            $resolvedBackupScriptPasskey = tpResolveBackupScriptPasskey($SETTINGS, true);
            $instanceKey = !empty($resolvedBackupScriptPasskey['success'])
                ? (string) $resolvedBackupScriptPasskey['clear_key']
                : '';
            if ($instanceKey === '') {
                echo prepareExchangedData(
                    array('error' => true, 'message' => $lang->get('bck_instance_key_not_set')),
                    'encode'
                );
                break;
            }

            echo prepareExchangedData(
                array('error' => false, 'instanceKey' => $instanceKey),
                'encode'
            );
            break;

        case 'scheduled_save_settings':
            if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
                echo prepareExchangedData(['error' => true, 'message' => 'Not allowed'], 'encode');
                break;
            }

            $dataReceived = prepareExchangedData($post_data, 'decode');
            if (!is_array($dataReceived)) $dataReceived = [];

            $enabled = (int)($dataReceived['enabled'] ?? 0);
            $enabled = ($enabled === 1) ? 1 : 0;


            $emailReportEnabled = (int)($dataReceived['email_report_enabled'] ?? 0);
            $emailReportEnabled = ($emailReportEnabled === 1) ? 1 : 0;

            $emailReportOnlyFailures = (int)($dataReceived['email_report_only_failures'] ?? 0);
            $emailReportOnlyFailures = ($emailReportOnlyFailures === 1) ? 1 : 0;

            if ($emailReportEnabled === 0) {
                $emailReportOnlyFailures = 0;
            }

            $frequency = (string)($dataReceived['frequency'] ?? 'daily');
            if (!in_array($frequency, ['daily', 'weekly', 'monthly'], true)) {
                $frequency = 'daily';
            }

            $timeStr = (string)($dataReceived['time'] ?? '02:00');
            if (!preg_match('/^\d{2}:\d{2}$/', $timeStr)) {
                $timeStr = '02:00';
            } else {
                [$hh, $mm] = array_map('intval', explode(':', $timeStr));
                if ($hh < 0 || $hh > 23 || $mm < 0 || $mm > 59) {
                    $timeStr = '02:00';
                }
            }

            $dow = (int)($dataReceived['dow'] ?? 1);
            if ($dow < 1 || $dow > 7) $dow = 1;

            $dom = (int)($dataReceived['dom'] ?? 1);
            if ($dom < 1) $dom = 1;
            if ($dom > 31) $dom = 31;

            $retentionDays = (int)($dataReceived['retention_days'] ?? 30);
            if ($retentionDays < 1) $retentionDays = 1;
            if ($retentionDays > 3650) $retentionDays = 3650;
            $includeDocuments = ((int)($dataReceived['include_documents'] ?? 0) === 1) ? 1 : 0;
            $backupFormat = tpBackupNormalizeRequestedFormat((string)($dataReceived['backup_format'] ?? tpGetSettingsValue('bck_scheduled_format', 'sql')));
            if ($includeDocuments === 1) {
                $backupFormat = tpBackupGetPackageExtension();
            }

            // Output dir: default to storage/backups; legacy subfolders of files/ remain allowed.
            $resolveOutputDir = tpBackupResolveScheduledOutputDir((string) ($dataReceived['output_dir'] ?? ''), $SETTINGS);

            if ($resolveOutputDir['success'] === false) {
                echo prepareExchangedData(['error' => true, 'message' => $lang->get('bck_scheduled_output_dir_invalid')], 'encode');
                break;
            }
            $dirReal = $resolveOutputDir['path'];

            tpUpsertSettingsValue('bck_scheduled_enabled', (string)$enabled);
            tpUpsertSettingsValue('bck_scheduled_frequency', $frequency);
            tpUpsertSettingsValue('bck_scheduled_time', $timeStr);
            tpUpsertSettingsValue('bck_scheduled_dow', (string)$dow);
            tpUpsertSettingsValue('bck_scheduled_dom', (string)$dom);
            tpUpsertSettingsValue('bck_scheduled_output_dir', $dirReal);
            tpUpsertSettingsValue('bck_scheduled_retention_days', (string)$retentionDays);
            tpUpsertSettingsValue('bck_scheduled_format', $backupFormat);
            tpUpsertSettingsValue('bck_scheduled_include_documents', (string)$includeDocuments);
            tpUpsertSettingsValue('bck_scheduled_email_report_enabled', (string)$emailReportEnabled);
            tpUpsertSettingsValue('bck_scheduled_email_report_only_failures', (string)$emailReportOnlyFailures);


            // Force re-init of next_run_at so handler recomputes cleanly
            tpUpsertSettingsValue('bck_scheduled_next_run_at', '0');

            echo prepareExchangedData(['error' => false, 'message' => 'Saved'], 'encode');
            break;

        case 'scheduled_list_backups':
            if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
                echo prepareExchangedData(['error' => true, 'message' => 'Not allowed'], 'encode');
                break;
            }

            $dir = (string)tpGetSettingsValue('bck_scheduled_output_dir', tpGetScheduledBackupDefaultDir());

            // Validate the retrieved path before creating it (defensive check against direct DB edits).
            $resolvedListDir = tpBackupResolveScheduledOutputDir($dir, $SETTINGS);
            if ($resolvedListDir['success'] === false) {
                echo prepareExchangedData(['error' => true, 'message' => $lang->get('bck_scheduled_output_dir_invalid')], 'encode');
                break;
            }
            $dir = $resolvedListDir['path'];

            // Ensure we have a temporary key for downloadFile.php
            $keyTmp = (string) $session->get('user-key_tmp');
            if ($keyTmp === '') {
                $keyTmp = GenerateCryptKey(16, false, true, true, false, true);
                $session->set('user-key_tmp', $keyTmp);
            }


            $files = [];
            $paths = array_merge(
                glob(rtrim($dir, '/') . '/scheduled-*.sql') ?: [],
                glob(rtrim($dir, '/') . '/scheduled-*.' . tpBackupGetPackageExtension()) ?: []
            );
            foreach ($paths as $fp) {
                $bn = basename($fp);
                $meta = function_exists('tpReadBackupMetadata') ? tpReadBackupMetadata($fp) : [];
                $isPackage = tpBackupIsPackageFilename($bn);
                $backupType = is_scalar($meta['backup_type'] ?? null) ? (string) $meta['backup_type'] : ($isPackage ? '' : 'database_only');
                $restoreSupported = $isPackage === false || in_array($backupType, ['database_only', 'db_only', 'db_documents', 'database_documents'], true) === true;
                $files[] = [
                    'name' => $bn,
                    'size_bytes' => (int)@filesize($fp),
                    'mtime' => (int)@filemtime($fp),
                    'tp_files_version' => (function_exists('tpGetBackupTpFilesVersionFromMeta') ? ((($v = (string)tpGetBackupTpFilesVersionFromMeta($fp)) !== '') ? $v : null) : null),
                    'backup_format' => $isPackage ? tpBackupGetPackageFormatId() : 'sql',
                    'backup_format_version' => $isPackage ? (int) ($meta['backup_format_version'] ?? tpBackupGetPackageFormatVersion()) : null,
                    'backup_type' => $backupType !== '' ? $backupType : 'unknown',
                    'restore_supported' => $restoreSupported,
                    'download' => 'sources/backups.queries.php?type=scheduled_download_backup&file=' . urlencode($bn)
                        . '&key=' . urlencode((string) $session->get('key'))
                        . '&key_tmp=' . urlencode($keyTmp),
                ];
            }

            usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

            echo prepareExchangedData(['error' => false, 'files' => $files], 'encode');
            break;

        case 'scheduled_delete_backup':
            if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
                echo prepareExchangedData(['error' => true, 'message' => 'Not allowed'], 'encode');
                break;
            }

            $dataReceived = prepareExchangedData($post_data, 'decode');
            if (!is_array($dataReceived)) $dataReceived = [];

            $file = (string)($dataReceived['file'] ?? '');
            $file = basename($file);

            $extension = (string) pathinfo($file, PATHINFO_EXTENSION);
            if ($file === '' || strpos($file, 'scheduled-') !== 0 || tpBackupFileExtensionIsSupported($extension) === false) {
                echo prepareExchangedData(['error' => true, 'message' => 'Invalid filename'], 'encode');
                break;
            }

            $dir = (string)tpGetSettingsValue('bck_scheduled_output_dir', tpGetScheduledBackupDefaultDir());
            $resolvedDeleteDir = tpBackupResolveScheduledOutputDir($dir, $SETTINGS);
            if ($resolvedDeleteDir['success'] === false) {
                echo prepareExchangedData(['error' => true, 'message' => $lang->get('bck_scheduled_output_dir_invalid')], 'encode');
                break;
            }
            $dir = $resolvedDeleteDir['path'];
            $fp = rtrim($dir, '/') . '/' . $file;

            /**
             * Delete a file and its associated metadata.
             * * @param string $fp Full path to the file.
             * @return void
             */
            // Check if file exists and is valid
            if (file_exists($fp) === false || is_file($fp) === false) {
                echo prepareExchangedData(['error' => false], 'encode');
                break;
            }

            // Check permissions
            if (is_writable($fp) === false) {
                $errorMessage = "File is not writable, cannot delete: " . $fp;
                if (WIP === true) error_log("TeamPass - " . $errorMessage);
                echo prepareExchangedData(['error' => true, 'message' => $errorMessage], 'encode');
                break;
            }

            // Attempt deletion
            if (unlink($fp) === false) {
                $errorMessage = "Failed to delete file: " . $fp;
                if (WIP === true) error_log("TeamPass - " . $errorMessage);
                echo prepareExchangedData(['error' => true, 'message' => $errorMessage], 'encode');
                break;
            }

            // Cleanup metadata (silent fail for sidecar is acceptable)
            if (file_exists($fp . '.meta.json')) {
                @unlink($fp . '.meta.json');
            }

            echo prepareExchangedData(['error' => false], 'encode');
            break;

        case 'check_connected_users':
            if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
                echo prepareExchangedData(['error' => true, 'message' => 'Not allowed'], 'encode');
                break;
            }

            $excludeUserId = (int) filter_input(INPUT_POST, 'exclude_user_id', FILTER_SANITIZE_NUMBER_INT);
            if ($excludeUserId === 0 && null !== $session->get('user-id')) {
                $excludeUserId = (int) $session->get('user-id');
            }

            $connectedCount = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable('users') . ' WHERE session_end >= %i AND id != %i',
                time(),
                $excludeUserId
            );

            echo prepareExchangedData(['error' => false, 'connected_count' => $connectedCount], 'encode');
            break;

        case 'scheduled_run_now':
            if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
                echo prepareExchangedData(['error' => true, 'message' => 'Not allowed'], 'encode');
                break;
            }

            $now = time();
            $dir = (string)tpGetSettingsValue('bck_scheduled_output_dir', tpGetScheduledBackupDefaultDir());
            $resolvedRunDir = tpBackupResolveScheduledOutputDir($dir, $SETTINGS);
            if ($resolvedRunDir['success'] === false) {
                echo prepareExchangedData(['error' => true, 'message' => $lang->get('bck_scheduled_output_dir_invalid')], 'encode');
                break;
            }
            $dir = $resolvedRunDir['path'];
            tpUpsertSettingsValue('bck_scheduled_output_dir', $dir);
            $backupFormat = tpBackupNormalizeRequestedFormat((string) tpGetSettingsValue('bck_scheduled_format', 'sql'));
            $includeDocuments = ((int) tpGetSettingsValue('bck_scheduled_include_documents', '0') === 1) ? 1 : 0;
            if ($includeDocuments === 1) {
                $backupFormat = tpBackupGetPackageExtension();
            }

            // avoid duplicates
            $pending = (int)DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable('background_tasks') . '
                 WHERE process_type=%s AND is_in_progress IN (0,1)
                   AND (finished_at IS NULL OR finished_at = "" OR finished_at = 0)',
                'database_backup'
            );
            if ($pending > 0) {
                echo prepareExchangedData(['error' => true, 'message' => 'A backup task is already pending/running'], 'encode');
                break;
            }

            DB::insert(
                prefixTable('background_tasks'),
                [
                    'created_at' => (string)$now,
                    'process_type' => 'database_backup',
                    'arguments' => json_encode(['output_dir' => $dir, 'source' => 'scheduler', 'initiator_user_id' => (int) $session->get('user-id'), 'backup_format' => $backupFormat, 'include_documents' => $includeDocuments], JSON_UNESCAPED_SLASHES),
                    'is_in_progress' => 0,
                    'status' => 'new',
                ]
            );

            tpUpsertSettingsValue('bck_scheduled_last_run_at', (string)$now);
            tpUpsertSettingsValue('bck_scheduled_last_status', 'queued');
            tpUpsertSettingsValue('bck_scheduled_last_message', 'Task enqueued by UI');

            echo prepareExchangedData(['error' => false], 'encode');
            break;

        case 'onthefly_delete_backup':
            // Delete an on-the-fly backup file stored in storage/onthefly.
            if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => 'Not allowed',
                    ),
                    'encode'
                );
                break;
            }

            $dataReceived = prepareExchangedData($post_data, 'decode');
            $fileToDelete = isset($dataReceived['file']) === true ? (string)$dataReceived['file'] : '';
            $bn = basename($fileToDelete);

            // Safety checks
            $extension = (string) pathinfo($bn, PATHINFO_EXTENSION);
            if ($bn === '' || tpBackupFileExtensionIsSupported($extension) === false) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => 'Invalid file name',
                    ),
                    'encode'
                );
                break;
            }

            // Never allow deleting scheduled backups or temp files
            if (strpos($bn, 'scheduled-') === 0 || strpos($bn, 'defuse_temp_') === 0 || strpos($bn, 'defuse_temp_restore_') === 0) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => 'Not allowed on this file',
                    ),
                    'encode'
                );
                break;
            }

            $dir = rtrim(tpGetOntheflyBackupDir(false), '/');
            $fullPath = $dir . '/' . $bn;

            if (file_exists($fullPath) === false) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => 'File not found',
                    ),
                    'encode'
                );
                break;
            }

            // Delete
            $ok = @unlink($fullPath);

            // Also remove metadata sidecar if present
            @unlink($fullPath . '.meta.json');

            if ($ok !== true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => 'Unable to delete file',
                    ),
                    'encode'
                );
                break;
            }

            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => 'Deleted',
                    'file' => $bn,
                ),
                'encode'
            );
            break;

        case 'onthefly_update_comment':
            // Update comment for an on-the-fly backup (stored in <backup>.meta.json)
            if ($post_key !== $session->get('key') || (int) $session->get('user-admin') !== 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            $dataReceived = prepareExchangedData($post_data, 'decode');
            $file = isset($dataReceived['file']) ? (string) $dataReceived['file'] : '';
            $comment = isset($dataReceived['comment']) ? (string) $dataReceived['comment'] : '';

            $bn = basename($file);

            // Safety checks
            $extension = (string) pathinfo($bn, PATHINFO_EXTENSION);
            if ($bn === '' || tpBackupFileExtensionIsSupported($extension) === false) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('bck_onthefly_invalid_filename'),
                    ),
                    'encode'
                );
                break;
            }

            // Never allow touching scheduled backups or temp files
            if (strpos($bn, 'scheduled-') === 0 || strpos($bn, 'defuse_temp_') === 0 || strpos($bn, 'defuse_temp_restore_') === 0) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            $comment = str_replace("\0", '', $comment);
            $comment = trim($comment);
            $commentLen = function_exists('mb_strlen') ? (int) mb_strlen($comment) : (int) strlen($comment);
            if ($commentLen > 2000) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('bck_onthefly_comment_too_long'),
                    ),
                    'encode'
                );
                break;
            }

            $dir = rtrim(tpGetOntheflyBackupDir(false), '/');
            $fullPath = $dir . '/' . $bn;

            if (is_file($fullPath) === false) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('bck_onthefly_file_not_found'),
                    ),
                    'encode'
                );
                break;
            }

            try {
                if (function_exists('tpUpsertBackupMetadata') === true) {
                    tpUpsertBackupMetadata($fullPath, ['comment' => $comment]);
                } else {
                    // Fallback: rewrite minimal metadata
                    if (function_exists('tpWriteBackupMetadata') === true) {
                        $extraMeta = ['source' => 'onthefly', 'comment' => $comment];
                        tpWriteBackupMetadata($fullPath, '', '', $extraMeta);
                    }
                }
            } catch (Throwable $e) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('bck_onthefly_comment_save_failed'),
                    ),
                    'encode'
                );
                break;
            }

            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => $lang->get('bck_onthefly_comment_saved'),
                ),
                'encode'
            );
            break;




        case 'onthefly_check_upload_filename':
            // Pre-check before uploading a restore SQL file.
            // We refuse uploading a file if its ORIGINAL name already exists in storage/onthefly,
            // to avoid duplicates and accidental metadata removal edge cases.
            if ($post_key !== $session->get('key') || (int) $session->get('user-admin') !== 1) {
                echo prepareExchangedData(
                    [
                        'error' => true,
                        'message' => 'Not allowed',
                    ],
                    'encode'
                );
                break;
            }

            $dataReceived = prepareExchangedData($post_data, 'decode');
            if (!is_array($dataReceived)) {
                $dataReceived = [];
            }

            $filename = (string) ($dataReceived['filename'] ?? '');
            $filename = basename($filename);

            // Safety: only .sql allowed here (UI already filters, but keep server strict)
            if ($filename === '' || strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) !== 'sql') {
                echo prepareExchangedData(
                    [
                        'error' => true,
                        'message' => 'Invalid filename',
                    ],
                    'encode'
                );
                break;
            }

            $dir = rtrim(tpGetOntheflyBackupDir(false), '/');
            $fullPath = $dir . '/' . $filename;

            $exists = is_file($fullPath);

            echo prepareExchangedData(
                [
                    'error' => false,
                    'exists' => $exists,
                    'filename' => $filename,
                    'message' => $exists ? str_replace('{FILENAME}', $filename, (string) $lang->get('bck_upload_error_file_already_exists')) : '',
                ],
                'encode'
            );
            break;

        case 'bck_meta_orphans_status':
            // Return the count of orphan *.meta.json files in:
            // - storage/onthefly (on-the-fly)
            // - storage/backups (scheduled)
            if ($post_key !== $session->get('key') || (int) $session->get('user-admin') !== 1) {
                echo prepareExchangedData(
                    [
                        'error' => true,
                        'message' => 'Not allowed',
                    ],
                    'encode'
                );
                break;
            }

            $filesDir = rtrim(tpGetOntheflyBackupDir(false), '/');
            $scheduledDir = defined('TEAMPASS_STORAGE') ? TEAMPASS_STORAGE . '/backups' : __DIR__ . '/../../storage/backups';

            $orphFiles = function_exists('tpListOrphanBackupMetaFiles') ? tpListOrphanBackupMetaFiles($filesDir) : [];
            $orphSched = function_exists('tpListOrphanBackupMetaFiles') ? tpListOrphanBackupMetaFiles($scheduledDir) : [];

            echo prepareExchangedData(
                [
                    'error' => false,
                    'files' => count($orphFiles),
                    'scheduled' => count($orphSched),
                    'total' => count($orphFiles) + count($orphSched),
                ],
                'encode'
            );
            break;

        case 'bck_meta_orphans_purge':
            // Purge orphan *.meta.json files in storage/onthefly and storage/backups.
            if ($post_key !== $session->get('key') || (int) $session->get('user-admin') !== 1) {
                echo prepareExchangedData(
                    [
                        'error' => true,
                        'message' => 'Not allowed',
                    ],
                    'encode'
                );
                break;
            }

            $filesDir = rtrim(tpGetOntheflyBackupDir(false), '/');
            $scheduledDir = defined('TEAMPASS_STORAGE') ? TEAMPASS_STORAGE . '/backups' : __DIR__ . '/../../storage/backups';

            $orphFiles = function_exists('tpListOrphanBackupMetaFiles') ? tpListOrphanBackupMetaFiles($filesDir) : [];
            $orphSched = function_exists('tpListOrphanBackupMetaFiles') ? tpListOrphanBackupMetaFiles($scheduledDir) : [];

            $all = array_merge($orphFiles, $orphSched);
            $res = function_exists('tpPurgeOrphanBackupMetaFiles') ? tpPurgeOrphanBackupMetaFiles($all) : ['deleted' => 0, 'failed' => []];

            echo prepareExchangedData(
                [
                    'error' => false,
                    'deleted' => (int) $res['deleted'],
                    'failed' => $res['failed'],
                ],
                'encode'
            );
            break;

        case 'onthefly_list_backups':
            // List on-the-fly backup files stored in storage/onthefly.
            if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => 'Not allowed',
                    ),
                    'encode'
                );
                break;
            }

            $dir = rtrim(tpGetOntheflyBackupDir(false), '/');

            $files = array();
            $paths = array_merge(
                glob($dir . '/*.sql') ?: array(),
                glob($dir . '/*.' . tpBackupGetPackageExtension()) ?: array()
            );

            // Ensure we have a temporary key for downloadFile.php
            $keyTmp = (string) $session->get('user-key_tmp');
            if ($keyTmp === '') {
                $keyTmp = GenerateCryptKey(16, false, true, true, false, true);
                $session->set('user-key_tmp', $keyTmp);
            }

            foreach ($paths as $fp) {
                $bn = basename($fp);

                // Skip scheduled backups and temporary files
                if (strpos($bn, 'scheduled-') === 0) {
                    continue;
                }
                if (strpos($bn, 'defuse_temp_') === 0 || strpos($bn, 'defuse_temp_restore_') === 0) {
                    continue;
                }

                $meta = function_exists('tpReadBackupMetadata') ? tpReadBackupMetadata($fp) : array();
                $isPackage = tpBackupIsPackageFilename($bn);
                $backupType = is_scalar($meta['backup_type'] ?? null) ? (string) $meta['backup_type'] : ($isPackage ? '' : 'database_only');
                $restoreSupported = $isPackage === false || in_array($backupType, ['database_only', 'db_only', 'db_documents', 'database_documents'], true) === true;

                $files[] = array(
                    'name' => $bn,
                    'size_bytes' => (int)@filesize($fp),
                    'mtime' => (int)@filemtime($fp),
                    'tp_files_version' => (function_exists('tpGetBackupTpFilesVersionFromMeta') ? ((($v = (string)tpGetBackupTpFilesVersionFromMeta($fp)) !== '') ? $v : null) : null),
                    'comment' => (function_exists('tpGetBackupCommentFromMeta') ? ((($c = (string)tpGetBackupCommentFromMeta($fp)) !== '') ? $c : null) : null),
                    'backup_format' => $isPackage ? tpBackupGetPackageFormatId() : 'sql',
                    'backup_format_version' => $isPackage ? (int) ($meta['backup_format_version'] ?? tpBackupGetPackageFormatVersion()) : null,
                    'backup_type' => $backupType !== '' ? $backupType : 'unknown',
                    'restore_supported' => $restoreSupported,
                    'download' => 'sources/downloadFile.php?name=' . urlencode($bn) .
                        '&action=backup&file=' . urlencode($bn) .
                        '&type=' . tpBackupGetDownloadTypeForFilename($bn) . '&key=' . $session->get('key') .
                        '&key_tmp=' . $session->get('user-key_tmp') .
                        '&pathIsOnthefly=1',
                );
            }

            usort($files, static function ($a, $b) {
                return $b['mtime'] <=> $a['mtime'];
            });

            echo prepareExchangedData(
                array(
                    'error' => false,
                    'dir' => $dir,
                    'files' => $files,
                ),
                'encode'
            );
            break;
        case 'preflight_restore_compatibility':
            if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
                echo prepareExchangedData(['error' => true, 'message' => 'Not allowed'], 'encode');
                break;
            }

            $dataReceived = prepareExchangedData($post_data, 'decode');
            if (!is_array($dataReceived)) {
                $dataReceived = [];
            }

            $serverScope = (string) ($dataReceived['serverScope'] ?? '');
            $serverFile  = (string) ($dataReceived['serverFile']  ?? '');
            $operationId = (int)    ($dataReceived['operation_id'] ?? 0);

            $chk = tpCheckRestoreCompatibility($SETTINGS, $serverScope, $serverFile, $operationId);

            echo prepareExchangedData(
                [
                    'error' => false,
                    'is_compatible' => (bool) $chk['is_compatible'],
                    'reason' => (string) $chk['reason'],
                    'mode' => (string) $chk['mode'],
                    'warnings' => $chk['warnings'],
                    'backup_schema_level' => (string) $chk['backup_schema_level'],
                    'expected_schema_level' => (string) $chk['expected_schema_level'],
                    'backup_tp_files_version' => $chk['backup_tp_files_version'],
                    'expected_tp_files_version' => (string) $chk['expected_tp_files_version'],
                ],
                'encode'
            );
            break;

                case 'prepare_restore_cli':
                    // Check KEY + Admin only
                    if ($post_key !== $session->get('key') || (int) $session->get('user-admin') !== 1) {
                        echo prepareExchangedData(
                            [
                                'error' => true,
                                'message' => $lang->get('error_not_allowed_to'),
                            ],
                            'encode'
                        );
                        break;
                    }

                    $dataReceived = prepareExchangedData($post_data, 'decode');
                    if (!is_array($dataReceived)) {
                        $dataReceived = [];
                    }

                    $serverScope = (string) ($dataReceived['serverScope'] ?? '');
                    $serverFile  = (string) ($dataReceived['serverFile']  ?? '');
                    $operationId = (int)    ($dataReceived['operation_id'] ?? 0);

                    $encryptionKey = (string) ($dataReceived['encryptionKey'] ?? '');
                    $overrideKey   = (string) ($dataReceived['overrideKey'] ?? '');

                    // Compatibility check
                    $chk = tpCheckRestoreCompatibility($SETTINGS, $serverScope, $serverFile, $operationId);
                    if (!empty($chk['is_compatible']) === false) {
                        $compatMessage = $lang->get('bck_restore_incompatible_version_body');
                        if ((string) $chk['reason'] === 'PACKAGE_DOCUMENTS_RESTORE_NOT_SUPPORTED') {
                            $compatMessage = $lang->get('bck_restore_tpbackup_documents_not_supported');
                        } elseif ((string) $chk['reason'] === 'PACKAGE_TYPE_UNSUPPORTED') {
                            $compatMessage = $lang->get('bck_restore_tpbackup_invalid');
                        }

                        echo prepareExchangedData(
                            [
                                'error' => true,
                                'error_code' => 'INCOMPATIBLE_BACKUP',
                                'reason' => (string) $chk['reason'],
                                'mode' => (string) $chk['mode'],
                                'warnings' => $chk['warnings'],
                                'backup_schema_level' => (string) $chk['backup_schema_level'],
                                'expected_schema_level' => (string) $chk['expected_schema_level'],
                                'backup_tp_files_version' => $chk['backup_tp_files_version'],
                                'expected_tp_files_version' => (string) $chk['expected_tp_files_version'],
                                'message' => $compatMessage,
                            ],
                            'encode'
                        );
                        break;
                    }

                    $resolvedPath = (string) $chk['resolved_path'];
                    if ($resolvedPath === '' || file_exists($resolvedPath) === false) {
                        echo prepareExchangedData(
                            [
                                'error' => true,
                                'error_code' => 'FILE_NOT_FOUND',
                                'message' => 'Restore file not found on server.',
                            ],
                            'encode'
                        );
                        break;
                    }

                    // Validate encryption key by attempting to decrypt to a temporary file
                    $keysToTry = [];

                    // Build candidate keys depending on restore source
                    // - scheduled/externalized: uses the instance key candidates + optional override key
                    // - on-the-fly: uses the key provided by the UI
                    // - upload (serverScope empty): can be either, so also try archived instance key candidates
                    if (in_array($serverScope, ['scheduled', 'externalized'], true) === true) {
                        if ($overrideKey !== '') {
                            $keysToTry[] = $overrideKey;
                        }

                        $keysToTry = array_merge(
                            $keysToTry,
                            tpGetBackupScriptPasskeyCandidates($SETTINGS, false)
                        );
                    } else {
                        if ($encryptionKey !== '') {
                            $keysToTry[] = $encryptionKey;
                        }
                        if ($overrideKey !== '') {
                            $keysToTry[] = $overrideKey;
                        }

                        if ($serverScope === '') {
                            $keysToTry = array_merge(
                                $keysToTry,
                                tpGetBackupScriptPasskeyCandidates($SETTINGS, false)
                            );
                        }
                    }

                    $keysToTry = array_values(array_unique(array_filter($keysToTry)));

                    if (empty($keysToTry)) {
                        echo prepareExchangedData(
                            [
                                'error' => true,
                                'error_code' => 'MISSING_ENCRYPTION_KEY',
                                'message' => 'Missing encryption key.',
                            ],
                            'encode'
                        );
                        break;
                    }

                    // IMPORTANT: do NOT use tempnam() here. It creates the file and can break Defuse file decrypt.
                    $tmpRand = '';
                    try {
                        $tmpRand = bin2hex(random_bytes(4));
                    } catch (Throwable $ignored) {
                        $tmpRand = uniqid('', true);
                    }

                    $tmpDecryptedSql = function_exists('tpBackupRestoreTempPath')
                        ? tpBackupRestoreTempPath('sql')
                        : (
                            rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
                            . 'defuse_temp_restore_' . (int) ($session->get('user-id') ?? 0) . '_' . time() . '_' . $tmpRand . '.sql'
                        );

                    if ($tmpDecryptedSql === '') {
                        echo prepareExchangedData(
                            [
                                'error' => true,
                                'error_code' => 'TEMP_FILE_FAILED',
                                'message' => $lang->get('server_answer_error'),
                            ],
                            'encode'
                        );
                        break;
                    }

                    if (tpBackupIsPackageFilename($resolvedPath) === true) {
                        $packageRet = tpBackupPreparePackageSqlForRestore($resolvedPath, $tmpDecryptedSql, $keysToTry, $SETTINGS, true, true);
                        @unlink($tmpDecryptedSql);

                        if (empty($packageRet['success'])) {
                            if (!empty($packageRet['decrypted_package_path']) && is_string($packageRet['decrypted_package_path'])) {
                                @unlink($packageRet['decrypted_package_path']);
                            }
                            $packageErrorCode = (string) ($packageRet['error_code'] ?? 'TPBACKUP_INVALID');
                            $message = $lang->get('bck_restore_tpbackup_invalid');
                            if ($packageErrorCode === 'DECRYPT_FAILED') {
                                $message = (string) ($packageRet['message'] ?? $lang->get('bck_restore_key_invalid'));
                            } elseif ($packageErrorCode === 'PACKAGE_DOCUMENTS_RESTORE_NOT_SUPPORTED') {
                                $message = $lang->get('bck_restore_tpbackup_documents_not_supported');
                            } elseif ($packageErrorCode === 'ZIP_EXTENSION_MISSING') {
                                $message = $lang->get('bck_restore_tpbackup_zip_missing');
                            }

                            echo prepareExchangedData(
                                [
                                    'error' => true,
                                    'error_code' => $packageErrorCode === 'DECRYPT_FAILED' ? 'DECRYPT_FAILED' : 'TPBACKUP_INVALID',
                                    'package_error_code' => $packageErrorCode,
                                    'message' => $message,
                                ],
                                'encode'
                            );
                            break;
                        }

                        $tmpPackageZipForDryRun = (string) ($packageRet['decrypted_package_path'] ?? '');
                        $packageManifestForDryRun = is_array($packageRet['manifest'] ?? null) ? $packageRet['manifest'] : [];
                        if ($tmpPackageZipForDryRun !== '') {
                            $documents = is_array($packageManifestForDryRun['documents'] ?? null) ? $packageManifestForDryRun['documents'] : [];
                            if (($documents['included'] ?? false) === true) {
                                $docDryRun = tpBackupRestorePackageDocumentsFromZip($tmpPackageZipForDryRun, $packageManifestForDryRun, $SETTINGS, true, true);
                                if (empty($docDryRun['success'])) {
                                    @unlink($tmpPackageZipForDryRun);
                                    echo prepareExchangedData(
                                        [
                                            'error' => true,
                                            'error_code' => 'TPBACKUP_INVALID',
                                            'package_error_code' => (string) ($docDryRun['error_code'] ?? 'DOCUMENT_RESTORE_FAILED'),
                                            'message' => $lang->get('bck_restore_tpbackup_documents_preflight_failed'),
                                        ],
                                        'encode'
                                    );
                                    break;
                                }
                            }
                            @unlink($tmpPackageZipForDryRun);
                        }
                    } else {
                        // Try to decrypt with the available keys
                        $decRet = tpDefuseDecryptWithCandidates($resolvedPath, $tmpDecryptedSql, $keysToTry, $SETTINGS);
                        @unlink($tmpDecryptedSql);

                        if (empty($decRet['success'])) {
                            echo prepareExchangedData(
                                [
                                    'error' => true,
                                    'error_code' => 'DECRYPT_FAILED',
                                    'message' => (string) $decRet['message'],
                                ],
                                'encode'
                            );
                            break;
                        }
                    }

// Create authorization token in DB (teampass_misc)
                    $compat = [
                        'mode' => (string) $chk['mode'],
                        'warnings' => $chk['warnings'],
                        'backup_schema_level' => (string) $chk['backup_schema_level'],
                        'expected_schema_level' => (string) $chk['expected_schema_level'],
                        'backup_tp_files_version' => $chk['backup_tp_files_version'],
                        'expected_tp_files_version' => (string) $chk['expected_tp_files_version'],
                    ];

                    $ttl = 3600;

                    $create = tpRestoreAuthorizationCreate(
                        (int) ($session->get('user-id') ?? 0),
                        (string) ($session->get('user-login') ?? ''),
                        $resolvedPath,
                        $serverScope,
                        $serverFile,
                        $operationId,
                        $encryptionKey,
                        $overrideKey,
                        $compat,
                        $ttl,
                        $SETTINGS
                    );

                    if (empty($create['success'])) {
                        echo prepareExchangedData(
                            [
                                'error' => true,
                                'error_code' => 'TOKEN_CREATE_FAILED',
                                'message' => (string) ($create['message'] ?? 'Unable to create authorization token.'),
                            ],
                            'encode'
                        );
                        break;
                    }

                    $token = (string) ($create['token'] ?? '');
                    $expiresAt = (int) ($create['expires_at'] ?? 0);

                    $tpRoot = TEAMPASS_ROOT;

					// Build a command adapted to the OS and (when possible) running as the same OS user as the web server.
					// This avoids common permission issues on files/ and keeps behavior consistent.
					$osFamily = defined('PHP_OS_FAMILY') ? (string) PHP_OS_FAMILY : (string) PHP_OS;
					$isWindows = (stripos($osFamily, 'Windows') !== false);
					$webUser = '';
					if (!$isWindows && function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
						$pw = @posix_getpwuid(@posix_geteuid());
						if (is_array($pw) && !empty($pw['name'])) {
							$webUser = (string) $pw['name'];
						}
					}

					$scriptRel = $isWindows ? 'app\\scripts\\restore.php' : 'app/scripts/restore.php';
					$filePathForCmd = $isWindows ? str_replace('/', '\\', $resolvedPath) : $resolvedPath;
					$rootForCmd = $isWindows ? str_replace('/', '\\', rtrim($tpRoot, '/\\')) : rtrim($tpRoot, '/');

					$sudoPrefix = '';
					if (!$isWindows && $webUser !== '') {
						$sudoPrefix = 'sudo -u ' . escapeshellarg($webUser) . ' ';
					}

					$cmdNoCd = $sudoPrefix . 'php ' . $scriptRel
						. ' --file ' . escapeshellarg($filePathForCmd)
						. ' --auth-token ' . escapeshellarg($token);
					$cmdNoCdForce = $cmdNoCd . ' --force-disconnect';

					if ($isWindows) {
						$cmd = 'cd /d ' . escapeshellarg($rootForCmd) . ' && ' . $cmdNoCd;
						$cmdForce = 'cd /d ' . escapeshellarg($rootForCmd) . ' && ' . $cmdNoCdForce;
					} else {
						$cmd = 'cd ' . escapeshellarg($rootForCmd) . ' && ' . $cmdNoCd;
						$cmdForce = 'cd ' . escapeshellarg($rootForCmd) . ' && ' . $cmdNoCdForce;
					}

					// Provide multiple variants to cover the most common OS families and privilege models.
					$variants = [];
					$variants[] = [
						'label' => $isWindows ? 'Windows (CMD/Powershell)' : 'Recommended',
						'command' => $cmd,
					];
					$variants[] = [
						'label' => $isWindows ? 'Force disconnect (--force-disconnect)' : 'Force disconnect (--force-disconnect)',
						'command' => $cmdForce,
					];
					if (!$isWindows && $webUser !== '') {
						$cmdNoSudo = 'php ' . $scriptRel
							. ' --file ' . escapeshellarg($resolvedPath)
							. ' --auth-token ' . escapeshellarg($token);
						$cmdNoSudoCd = 'cd ' . escapeshellarg($rootForCmd) . ' && ' . $cmdNoSudo;
						$variants[] = [
							'label' => 'Alternative (no sudo) - run as ' . $webUser,
							'command' => $cmdNoSudoCd,
						];
					}

                    echo prepareExchangedData(
                        [
                            'error' => false,
                            'token_expires_at' => $expiresAt,
                            'ttl' => $ttl,
                            'mode' => (string) $chk['mode'],
                            'warnings' => $chk['warnings'],
                            'command' => $cmd,
                            'command_force_disconnect' => $cmdForce,
                            'command_no_cd' => $cmdNoCd,
							'command_variants' => $variants,
							'os_family' => $osFamily,
							'web_user' => $webUser,
                            'resolved_path' => $resolvedPath,
                        ],
                        'encode'
                    );
                    break;

case 'onthefly_restore':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-admin') === 0) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }
            
                                    // Web restore is disabled: the UI only prepares a CLI command.
                        echo prepareExchangedData(
                            array(
                                'error' => true,
                                'error_code' => 'RESTORE_CLI_ONLY',
                                'message' => $lang->get('bck_restore_cli_only'),
                            ),
                            'encode'
                        );
                        break;
    }
}
