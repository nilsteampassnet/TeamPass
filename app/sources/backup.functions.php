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
 * @file      backup.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Teampass - Backup helper functions
 * This file provides reusable functions for database backup creation
 * (manual UI + scheduled/background tasks).
 */

function tpBackupGetScheduledDefaultDir(): string
{
    return defined('TEAMPASS_STORAGE') ? TEAMPASS_STORAGE . '/backups' : __DIR__ . '/../../storage/backups';
}

function tpBackupGetConfiguredFilesDir(array $SETTINGS): string
{
    return (string) ($SETTINGS['path_to_files_folder'] ?? (defined('TEAMPASS_STORAGE') ? TEAMPASS_STORAGE . '/files' : __DIR__ . '/../../storage/files'));
}

function tpBackupNormalizePathForCompare(string $path): string
{
    $normalized = rtrim(str_replace('\\', '/', $path), '/');

    return DIRECTORY_SEPARATOR === '\\' ? strtolower($normalized) : $normalized;
}

function tpBackupIsResolvedPathInsideDirectory(string $path, string $baseDir): bool
{
    $baseReal = realpath($baseDir);
    if ($path === '' || $baseReal === false) {
        return false;
    }

    $pathNormalized = tpBackupNormalizePathForCompare($path);
    $baseNormalized = tpBackupNormalizePathForCompare($baseReal);

    return $pathNormalized === $baseNormalized || str_starts_with($pathNormalized, $baseNormalized . '/');
}

function tpBackupMapLegacyScheduledOutputDir(string $requestedDir, array $SETTINGS = []): string
{
    $requestedDir = trim($requestedDir);
    if ($requestedDir === '') {
        return '';
    }

    $root = (string) ($SETTINGS['cpassman_dir'] ?? '');
    if ($root === '' && defined('TEAMPASS_ROOT')) {
        $root = (string) TEAMPASS_ROOT;
    }
    if ($root === '') {
        $root = (string) realpath(__DIR__ . '/../..');
    }
    if ($root === '') {
        return $requestedDir;
    }

    $requestedNormalized = tpBackupNormalizePathForCompare($requestedDir);
    $legacyBases = [
        rtrim($root, '/\\') . '/files',
        rtrim($root, '/\\') . '/backups',
    ];

    foreach ($legacyBases as $legacyBase) {
        $legacyNormalized = tpBackupNormalizePathForCompare($legacyBase);
        if ($requestedNormalized === $legacyNormalized || str_starts_with($requestedNormalized, $legacyNormalized . '/')) {
            return tpBackupGetScheduledDefaultDir();
        }
    }

    return $requestedDir;
}

function tpBackupResolveDirectoryCreationPath(string $path): string
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
 * Resolve and validate a scheduled backup output directory.
 *
 * Allowed bases are the documented storage/backups directory and the configured
 * files folder kept for legacy compatibility.
 *
 * @param array<int, string>|null $allowedBaseDirs
 * @return array{success: bool, path: string}
 */
function tpBackupResolveScheduledOutputDir(string $requestedDir, array $SETTINGS = [], ?array $allowedBaseDirs = null): array
{
    $outputDir = trim($requestedDir);
    if ($outputDir === '') {
        $outputDir = tpBackupGetScheduledDefaultDir();
    } else {
        $outputDir = tpBackupMapLegacyScheduledOutputDir($outputDir, $SETTINGS);
    }

    if ($allowedBaseDirs === null) {
        $allowedBaseDirs = [
            tpBackupGetScheduledDefaultDir(),
            tpBackupGetConfiguredFilesDir($SETTINGS),
        ];
    }

    foreach ($allowedBaseDirs as $allowedBaseDir) {
        if (trim((string) $allowedBaseDir) === '') {
            continue;
        }

        if (!is_dir($allowedBaseDir)) {
            $mkdirBaseResult = @mkdir($allowedBaseDir, 0750, true);
            if ($mkdirBaseResult === false && !is_dir($allowedBaseDir)) {
                continue;
            }
        }
    }

    $resolvedOutputDir = tpBackupResolveDirectoryCreationPath($outputDir);
    if ($resolvedOutputDir === '') {
        return ['success' => false, 'path' => ''];
    }

    $isAllowed = false;
    foreach ($allowedBaseDirs as $allowedBaseDir) {
        if (tpBackupIsResolvedPathInsideDirectory($resolvedOutputDir, (string) $allowedBaseDir) === true) {
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

/**
 * Create a Teampass database backup file (optionally encrypted) in the requested output directory.
 *
 * @param array  $SETTINGS      Teampass settings array (must include path_to_files_folder)
 * @param string $encryptionKey Encryption key (Defuse). If empty => no encryption
 * @param array  $options       Optional:
 *                              - output_dir (string) default: $SETTINGS['path_to_files_folder']
 *                              - filename_prefix (string) default: '' (ex: 'scheduled-')
 *                              - chunk_rows (int) default: 1000
 *                              - flush_every_inserts (int) default: 200
 *                              - include_tables (array<string>) default: [] (empty => all)
 *                              - exclude_tables (array<string>) default: [] (empty => none)
 * @return array
 * @psalm-return array{success: bool, filename: string, filepath: string, encrypted: bool, size_bytes: int, message: string}
 */
function tpCreateDatabaseBackup(array $SETTINGS, string $encryptionKey = '', array $options = []): array
{
        // Enable maintenance mode for the whole backup operation, then restore previous value at the end.
        // This is best-effort: a failure to toggle maintenance must not break the backup itself.
        /** @scrutinizer ignore-unused */
        $__tpMaintenanceGuard = new class() {
            private $prev = null;
            private $changed = false;

            public function __construct()
            {
                try {
                    $row = DB::queryFirstRow(
                        'SELECT valeur FROM ' . prefixTable('misc') . ' WHERE intitule=%s AND type=%s',
                        'maintenance_mode',
                        'admin'
                    );

                    if (is_array($row) && array_key_exists('valeur', $row)) {
                        $this->prev = (string) $row['valeur'];
                    }

                    // Only toggle if it was not already enabled
                    if ($this->prev !== '1') {
                        DB::update(
                            prefixTable('misc'),
                            array(
                                'valeur' => '1',
                                'updated_at' => time(),
                            ),
                            'intitule = %s AND type= %s',
                            'maintenance_mode',
                            'admin'
                        );
                        $this->changed = true;
                    }
                } catch (Throwable $ignored) {
                    // ignore
                }
            }

            public function __destruct()
            {
                if ($this->changed !== true) {
                    return;
                }

                try {
                    DB::update(
                        prefixTable('misc'),
                        array(
                            'valeur' => (string) ($this->prev ?? '0'),
                            'updated_at' => time(),
                        ),
                        'intitule = %s AND type= %s',
                        'maintenance_mode',
                        'admin'
                    );
                } catch (Throwable $ignored) {
                    // ignore
                }
            }
        };

        $outputDir = $options['output_dir'] ?? ($SETTINGS['path_to_files_folder'] ?? '');
        $prefix = (string)($options['filename_prefix'] ?? '');
        $chunkRows = (int)($options['chunk_rows'] ?? 1000);
        $flushEvery = (int)($options['flush_every_inserts'] ?? 200);
        $includeTables = $options['include_tables'] ?? [];
        $excludeTables = $options['exclude_tables'] ?? [];

        if ($outputDir === '' || !is_dir($outputDir) || !is_writable($outputDir)) {
            return [
                'success' => false,
                'filename' => '',
                'filepath' => '',
                'encrypted' => false,
                'size_bytes' => 0,
                'message' => 'Backup folder is not writable or not found: ' . $outputDir,
            ];
        }

        // Generate filename
        $token = GenerateCryptKey(20, false, true, true, false, true);

        // Schema level token in filename (used for compatibility checks during migrations)
        $schemaLevel = tpGetSchemaLevel();
        $schemaSuffix = ($schemaLevel !== '') ? ('-sl' . $schemaLevel) : '';
        $filename = $prefix . time() . '-' . $token . $schemaSuffix . '.sql';
        $filepath = rtrim($outputDir, '/') . '/' . $filename;

        $handle = @fopen($filepath, 'w+');
        if ($handle === false) {
            return [
                'success' => false,
                'filename' => $filename,
                'filepath' => $filepath,
                'encrypted' => false,
                'size_bytes' => 0,
                'message' => 'Could not create backup file: ' . $filepath,
            ];
        }

        $insertCount = 0;

        try {
            // Get all tables
            $tables = [];
            $result = DB::query('SHOW TABLES');
            foreach ($result as $row) {
                // SHOW TABLES returns key like 'Tables_in_<DB_NAME>'
                foreach ($row as $v) {
                    $tables[] = (string) $v;
                    break;
                }
            }

            // Filter tables if requested
            if (!empty($includeTables) && is_array($includeTables)) {
                $tables = array_values(array_intersect($tables, $includeTables));
            }
            if (!empty($excludeTables) && is_array($excludeTables)) {
                $tables = array_values(array_diff($tables, $excludeTables));
            }

            foreach ($tables as $tableName) {
                // Safety: only allow typical MySQL table identifiers
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
                    continue;
                }

                // Write drop and creation
                fwrite($handle, 'DROP TABLE IF EXISTS `' . $tableName . "`;\n");

                $row2 = DB::queryFirstRow('SHOW CREATE TABLE `' . $tableName . '`');
                if (!is_array($row2) || empty($row2['Create Table'])) {
                    // Skip table if structure cannot be fetched
                    fwrite($handle, "\n");
                    continue;
                }

                fwrite($handle, $row2['Create Table'] . ";\n\n");

                // Process table data in chunks to reduce memory usage
                $offset = 0;
                while (true) {
                    $rows = DB::query(
                        'SELECT * FROM `' . $tableName . '` LIMIT %i OFFSET %i',
                        $chunkRows,
                        $offset
                    );

                    if (empty($rows)) {
                        break;
                    }

                    foreach ($rows as $record) {
                        $values = [];
                        foreach ($record as $value) {
                            if ($value === null) {
                                $values[] = 'NULL';
                                continue;
                            }

                            // Force scalar/string
                            if (is_bool($value)) {
                                $value = $value ? '1' : '0';
                            } elseif (is_numeric($value)) {
                                // keep numeric as string but quoted (safe & consistent)
                                $value = (string) $value;
                            } else {
                                $value = (string) $value;
                            }

                            // Escape and keep newlines
                            $value = addslashes(preg_replace("/\n/", '\\n', $value));
                            $values[] = '"' . $value . '"';
                        }

                        $insertQuery = 'INSERT INTO `' . $tableName . '` VALUES(' . implode(',', $values) . ");\n";
                        fwrite($handle, $insertQuery);

                        $insertCount++;
                        if ($flushEvery > 0 && ($insertCount % $flushEvery) === 0) {
                            fflush($handle);
                        }
                    }

                    $offset += $chunkRows;
                    fflush($handle);
                }

                fwrite($handle, "\n\n");
                fflush($handle);
            }
        } catch (Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            $errorMessage = 'Backup failed: ' . $e->getMessage();

            // Suppression sécurisée sans @
            if (file_exists($filepath)) {
                $deleted = unlink($filepath);
                if ($deleted === false) {
                    $errorMessage .= ' (Note: Temporary backup file could not be deleted from disk)';
                }
            }

            return [
                'success' => false,
                'filename' => $filename,
                'filepath' => $filepath,
                'encrypted' => false,
                'size_bytes' => 0,
                'message' => $errorMessage,
            ];
        }

        fclose($handle);

        // Encrypt the file if key provided
        $encrypted = false;
        if ($encryptionKey !== '') {
            $tmpPath = rtrim($outputDir, '/') . '/defuse_temp_' . $filename;

            if (!function_exists('prepareFileWithDefuse')) {
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                return [
                    'success' => false,
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'encrypted' => false,
                    'size_bytes' => 0,
                    'message' => 'Missing prepareFileWithDefuse() dependency (main.functions.php not loaded?)',
                ];
            }

            $ret = prepareFileWithDefuse('encrypt', $filepath, $tmpPath, $encryptionKey);

            if ($ret !== true) {
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                if (file_exists($tmpPath)) {
                    unlink($tmpPath);
                }
                return [
                    'success' => false,
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'encrypted' => false,
                    'size_bytes' => 0,
                    'message' => 'Encryption failed: ' . (is_string($ret) ? $ret : 'unknown error'),
                ];
            }

            // Replace original with encrypted version
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            
            // On vérifie le succès de rename() sans @
            if (is_file($tmpPath) && !rename($tmpPath, $filepath)) {
                if (file_exists($tmpPath)) {
                    unlink($tmpPath);
                }
                return [
                    'success' => false,
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'encrypted' => false,
                    'size_bytes' => 0,
                    'message' => 'Encryption succeeded but could not finalize file (rename failed)',
                ];
            }

            $encrypted = true;
        }

        // Gestion de filesize sans @
        $size = 0;
        if (is_file($filepath)) {
            $size = filesize($filepath);
            if ($size === false) {
                $size = 0;
            }
        }

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'encrypted' => $encrypted,
            'size_bytes' => (int) $size,
            'message' => '',
        ];
}


// -----------------------------------------------------------------------------
// Unified .tpbackup package helpers
// -----------------------------------------------------------------------------

/**
 * Return the internal format identifier for .tpbackup packages.
 *
 * @return string
 */
function tpBackupGetPackageFormatId(): string
{
    return 'teampass.tpbackup';
}

/**
 * Return the current .tpbackup package format version.
 *
 * @return int
 */
function tpBackupGetPackageFormatVersion(): int
{
    return 1;
}

/**
 * Return the file extension used for .tpbackup packages (without the dot).
 *
 * @return string
 */
function tpBackupGetPackageExtension(): string
{
    return 'tpbackup';
}

/**
 * Tell whether a filename refers to a .tpbackup package (case-insensitive).
 *
 * @return bool
 */
function tpBackupIsPackageFilename(string $filename): bool
{
    return strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) === tpBackupGetPackageExtension();
}

/**
 * Normalize a requested backup format to 'sql' or 'tpbackup' (defaults to 'sql').
 *
 * @return string
 */
function tpBackupNormalizeRequestedFormat(string $format): string
{
    $format = strtolower(trim($format));
    if ($format === '.tpbackup' || $format === tpBackupGetPackageExtension()) {
        return tpBackupGetPackageExtension();
    }

    return 'sql';
}

/**
 * Tell whether the given file extension is a supported backup format.
 *
 * @return bool
 */
function tpBackupFileExtensionIsSupported(string $extension): bool
{
    return in_array(strtolower($extension), ['sql', tpBackupGetPackageExtension()], true);
}

/**
 * Return the download type ('sql' or 'tpbackup') matching a backup filename.
 *
 * @return string
 */
function tpBackupGetDownloadTypeForFilename(string $filename): string
{
    return tpBackupIsPackageFilename($filename) ? tpBackupGetPackageExtension() : 'sql';
}

/**
 * Return the internal format identifier for .tprecovery packages.
 *
 * @return string
 */
function tpRecoveryGetPackageFormatId(): string
{
    return 'teampass.recovery';
}

/**
 * Return the current .tprecovery package format version.
 *
 * @return int
 */
function tpRecoveryGetPackageFormatVersion(): int
{
    return 1;
}

/**
 * Return the file extension used for .tprecovery packages (without the dot).
 *
 * @return string
 */
function tpRecoveryGetPackageExtension(): string
{
    return 'tprecovery';
}

/**
 * Build a unique .tprecovery package filename using the given prefix.
 *
 * @return string
 */
function tpRecoveryBuildPackageFilename(string $prefix = 'recovery-'): string
{
    if (function_exists('GenerateCryptKey')) {
        $token = GenerateCryptKey(20, false, true, true, false, true);
    } else {
        try {
            $token = bin2hex(random_bytes(10));
        } catch (Throwable $ignored) {
            $token = str_replace('.', '', uniqid('', true));
        }
    }

    return $prefix . time() . '-' . $token . '.' . tpRecoveryGetPackageExtension();
}

/**
 * Return the absolute path to the TeamPass settings.php file.
 *
 * @return string
 */
function tpRecoveryGetSettingsFilePath(): string
{
    if (defined('TEAMPASS_APP')) {
        return (string) TEAMPASS_APP . '/config/settings.php';
    }

    return realpath(__DIR__ . '/../config/settings.php') ?: (__DIR__ . '/../config/settings.php');
}

/**
 * Return the absolute path to the SECUREFILE secret file.
 *
 * @return string
 */
function tpRecoveryGetSecureFilePath(): string
{
    if (defined('TEAMPASS_SECRETS') === false || defined('SECUREFILE') === false) {
        return '';
    }

    return rtrim((string) TEAMPASS_SECRETS, '/\\') . DIRECTORY_SEPARATOR . (string) SECUREFILE;
}

/**
 * @return array<string, mixed>
 */
function tpRecoveryCaptureConfigConstants(): array
{
    $constants = [];
    foreach ([
        'DB_HOST',
        'DB_USER',
        'DB_PASSWD',
        'DB_NAME',
        'DB_PREFIX',
        'DB_PORT',
        'DB_ENCODING',
        'DB_SSL',
        'DB_CONNECT_OPTIONS',
        'SECUREFILE',
        'IKEY',
        'SKEY',
        'HOST',
        'TEAMPASS_ROOT',
        'TEAMPASS_APP',
        'TEAMPASS_STORAGE',
        'TEAMPASS_SECRETS',
    ] as $constantName) {
        if (defined($constantName) === true) {
            $constants[$constantName] = constant($constantName);
        }
    }

    return $constants;
}

/**
 * @return array<string, mixed>
 */
function tpRecoveryBuildSettingsSummary(array $SETTINGS): array
{
    $summary = [];
    foreach ([
        'cpassman_dir',
        'cpassman_url',
        'path_to_files_folder',
        'path_to_upload_folder',
        'timezone',
        'teampass_version',
        'bck_externalized_destination_type',
        'bck_externalized_enabled',
        'bck_externalized_target_dir',
        'bck_externalized_format',
        'bck_externalized_include_documents',
        'bck_externalized_retry_attempts',
        'bck_externalized_retry_delay_seconds',
        'bck_externalized_sftp_host',
        'bck_externalized_sftp_port',
        'bck_externalized_sftp_username',
        'bck_externalized_sftp_auth_type',
        'bck_externalized_webdav_url',
        'bck_externalized_webdav_username',
        'bck_externalized_s3_endpoint',
        'bck_externalized_s3_region',
        'bck_externalized_s3_bucket',
        'bck_externalized_s3_access_key',
        'bck_externalized_s3_path_style',
        'bck_scheduled_output_dir',
        'bck_scheduled_format',
        'bck_scheduled_include_documents',
    ] as $settingName) {
        if (array_key_exists($settingName, $SETTINGS) === true) {
            $summary[$settingName] = $SETTINGS[$settingName];
        }
    }

    return $summary;
}

/**
 * Tell whether a recovery package path points to a safe, expected temporary file.
 *
 * @return bool
 */
function tpRecoveryPackagePathIsSafe(string $path): bool
{
    $real = realpath($path);
    $tmpReal = realpath((string) sys_get_temp_dir());
    if ($real === false || $tmpReal === false || is_file($real) === false) {
        return false;
    }

    $basename = basename($real);
    if (
        str_starts_with($basename, 'recovery-') === false
        || strtolower((string) pathinfo($basename, PATHINFO_EXTENSION)) !== tpRecoveryGetPackageExtension()
    ) {
        return false;
    }

    return tpBackupIsResolvedPathInsideDirectory($real, $tmpReal);
}

/**
 * Delete a recovery package file after validating that its path is safe.
 *
 * @return bool
 */
function tpRecoverySafeDeletePackage(string $path): bool
{
    if (tpRecoveryPackagePathIsSafe($path) === false) {
        return false;
    }

    return @unlink((string) realpath($path));
}

/**
 * Create a manual encrypted recovery package.
 *
 * This package is intentionally separate from .tpbackup backup lists and must
 * only be downloaded manually by an administrator.
 *
 * @return array{success: bool, filename: string, filepath: string, encrypted: bool, size_bytes: int, message: string, recovery_format?: string, recovery_format_version?: int, warnings?: array<int,string>}
 */
function tpCreateRecoveryPackage(array &$SETTINGS, string $passphrase, array $options = []): array
{
    $outputDir = (string) ($options['output_dir'] ?? (string) sys_get_temp_dir());
    $passphrase = (string) $passphrase;

    if ($passphrase === '') {
        return [
            'success' => false,
            'filename' => '',
            'filepath' => '',
            'encrypted' => false,
            'size_bytes' => 0,
            'message' => 'Missing recovery package passphrase.',
        ];
    }

    if (class_exists('ZipArchive') === false) {
        return [
            'success' => false,
            'filename' => '',
            'filepath' => '',
            'encrypted' => false,
            'size_bytes' => 0,
            'message' => 'ZipArchive PHP extension is required to create recovery packages.',
        ];
    }

    if (function_exists('prepareFileWithDefuse') === false) {
        return [
            'success' => false,
            'filename' => '',
            'filepath' => '',
            'encrypted' => false,
            'size_bytes' => 0,
            'message' => 'Missing prepareFileWithDefuse() dependency (main.functions.php not loaded?)',
        ];
    }

    if ($outputDir === '' || is_dir($outputDir) === false || is_writable($outputDir) === false) {
        return [
            'success' => false,
            'filename' => '',
            'filepath' => '',
            'encrypted' => false,
            'size_bytes' => 0,
            'message' => 'Recovery package temporary directory is not writable.',
        ];
    }

    $secureFilePath = tpRecoveryGetSecureFilePath();
    if ($secureFilePath === '' || is_readable($secureFilePath) === false) {
        return [
            'success' => false,
            'filename' => '',
            'filepath' => '',
            'encrypted' => false,
            'size_bytes' => 0,
            'message' => 'Secure file is not readable.',
        ];
    }

    $settingsFilePath = tpRecoveryGetSettingsFilePath();
    if (is_readable($settingsFilePath) === false) {
        return [
            'success' => false,
            'filename' => '',
            'filepath' => '',
            'encrypted' => false,
            'size_bytes' => 0,
            'message' => 'TeamPass settings.php is not readable.',
        ];
    }

    $workDir = tpBackupCreateTemporaryDirectory($outputDir);
    if ($workDir === '') {
        return [
            'success' => false,
            'filename' => '',
            'filepath' => '',
            'encrypted' => false,
            'size_bytes' => 0,
            'message' => 'Could not create temporary recovery package directory.',
        ];
    }

    try {
        $warnings = [];
        $resolvedBackupScriptPasskey = tpResolveBackupScriptPasskey($SETTINGS, true);
        $backupInstanceKey = !empty($resolvedBackupScriptPasskey['success'])
            ? (string) $resolvedBackupScriptPasskey['clear_key']
            : '';
        if ($backupInstanceKey === '') {
            $warnings[] = 'BACKUP_INSTANCE_KEY_MISSING';
        }

        $secureFileNameRaw = defined('SECUREFILE') ? (string) SECUREFILE : basename($secureFilePath);
        $secureFileName = tpBackupSafeStorageBasename(basename(str_replace('\\', '/', $secureFileNameRaw)));
        if ($secureFileName === '') {
            $secureFileName = 'securefile.key';
        }
        $manifest = [
            'recovery_format' => tpRecoveryGetPackageFormatId(),
            'recovery_format_version' => tpRecoveryGetPackageFormatVersion(),
            'created_at' => gmdate('c'),
            'teampass' => [
                'files_version' => (($v = tpGetTpFilesVersion()) !== '') ? $v : null,
                'schema_level' => (($sl = tpGetSchemaLevel()) !== '') ? $sl : null,
            ],
            'encryption' => [
                'engine' => 'defuse',
                'scope' => 'package',
                'password_protected' => true,
            ],
            'contents' => [
                'config/settings.php',
                'config/constants.json',
                'config/admin_settings_summary.json',
                'secrets/' . $secureFileName,
            ],
            'warnings' => $warnings,
        ];
        if ($backupInstanceKey !== '') {
            $manifest['contents'][] = 'backup/instance_key.txt';
        }

        $manifestJson = tpBackupCanonicalJson($manifest);
        $constantsJson = tpBackupCanonicalJson(tpRecoveryCaptureConfigConstants());
        $settingsSummaryJson = tpBackupCanonicalJson(tpRecoveryBuildSettingsSummary($SETTINGS));
        if ($manifestJson === '' || $constantsJson === '' || $settingsSummaryJson === '') {
            return [
                'success' => false,
                'filename' => '',
                'filepath' => '',
                'encrypted' => false,
                'size_bytes' => 0,
                'message' => 'Unable to encode recovery package metadata.',
            ];
        }

        $zipPath = $workDir . DIRECTORY_SEPARATOR . 'recovery.zip';
        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($openResult !== true) {
            return [
                'success' => false,
                'filename' => '',
                'filepath' => '',
                'encrypted' => false,
                'size_bytes' => 0,
                'message' => 'Unable to create recovery package archive.',
            ];
        }

        $readme = "Teampass Recovery Package\n"
            . "Created at: " . (string) $manifest['created_at'] . "\n\n"
            . "This encrypted package contains sensitive recovery material for this TeamPass instance.\n"
            . "Keep it offline and protected. It is not created or externalized automatically.\n";

        $zipOk = $zip->addFromString('manifest.json', $manifestJson)
            && $zip->addFromString('README.txt', $readme)
            && $zip->addFile($settingsFilePath, 'config/settings.php')
            && $zip->addFromString('config/constants.json', $constantsJson)
            && $zip->addFromString('config/admin_settings_summary.json', $settingsSummaryJson)
            && $zip->addFile($secureFilePath, 'secrets/' . $secureFileName);

        if ($zipOk === true && $backupInstanceKey !== '') {
            $zipOk = $zip->addFromString('backup/instance_key.txt', $backupInstanceKey . "\n");
        }

        $closed = $zip->close();
        if ($zipOk === false || $closed === false || is_file($zipPath) === false) {
            return [
                'success' => false,
                'filename' => '',
                'filepath' => '',
                'encrypted' => false,
                'size_bytes' => 0,
                'message' => 'Unable to finalize recovery package archive.',
            ];
        }

        $filename = tpRecoveryBuildPackageFilename();
        $filepath = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        $encryptedTmpPath = $workDir . DIRECTORY_SEPARATOR . 'recovery.encrypted';

        $encryptResult = tpPrepareFileWithDefuseNormalized('encrypt', $zipPath, $encryptedTmpPath, $passphrase);
        if ($encryptResult['success'] !== true || is_file($encryptedTmpPath) === false) {
            return [
                'success' => false,
                'filename' => $filename,
                'filepath' => $filepath,
                'encrypted' => false,
                'size_bytes' => 0,
                'message' => 'Encryption failed: ' . (string) $encryptResult['message'],
            ];
        }

        if (@rename($encryptedTmpPath, $filepath) === false) {
            return [
                'success' => false,
                'filename' => $filename,
                'filepath' => $filepath,
                'encrypted' => false,
                'size_bytes' => 0,
                'message' => 'Encryption succeeded but could not finalize recovery package file.',
            ];
        }

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'encrypted' => true,
            'size_bytes' => is_file($filepath) ? (int) filesize($filepath) : 0,
            'message' => '',
            'recovery_format' => tpRecoveryGetPackageFormatId(),
            'recovery_format_version' => tpRecoveryGetPackageFormatVersion(),
            'warnings' => $warnings,
        ];
    } finally {
        tpBackupRemoveTemporaryDirectory($workDir);
    }
}

/**
 * Tell whether a path looks absolute on Unix or Windows (including UNC shares).
 *
 * @return bool
 */
function tpBackupPathLooksAbsolute(string $path): bool
{
    $normalized = str_replace('\\', '/', trim($path));
    if ($normalized === '') {
        return false;
    }

    return str_starts_with($normalized, '/')
        || str_starts_with($normalized, '//')
        || preg_match('/^[A-Za-z]:\//', $normalized) === 1;
}

/**
 * @return array<int, string>
 */
function tpBackupGetSupportedExternalizedDestinationTypes(): array
{
    return ['local_directory', 'sftp', 'webdav', 's3'];
}

/**
 * Normalize an externalized destination type to a known value.
 *
 * @return string
 */
function tpBackupNormalizeExternalizedDestinationType(string $destinationType): string
{
    return strtolower(trim(str_replace("\0", '', $destinationType)));
}

/**
 * Tell whether the externalized destination type is supported.
 *
 * @return bool
 */
function tpBackupExternalizedDestinationTypeIsSupported(string $destinationType): bool
{
    return in_array(
        tpBackupNormalizeExternalizedDestinationType($destinationType),
        tpBackupGetSupportedExternalizedDestinationTypes(),
        true
    );
}

/**
 * Resolve an externalized destination type, falling back to a default when unknown.
 *
 * @return string
 */
function tpBackupResolveExternalizedDestinationType(string $destinationType): string
{
    $destinationType = tpBackupNormalizeExternalizedDestinationType($destinationType);
    if (tpBackupExternalizedDestinationTypeIsSupported($destinationType) === true) {
        return $destinationType;
    }

    return 'local_directory';
}

/**
 * Tell whether the destination type supports file operations (list/download/delete).
 *
 * @return bool
 */
function tpBackupExternalizedDestinationTypeSupportsFileOperations(string $destinationType): bool
{
    $destinationType = tpBackupNormalizeExternalizedDestinationType($destinationType);

    return in_array($destinationType, ['local_directory', 'sftp', 'webdav', 's3'], true) === true
        && tpBackupExternalizedDestinationTypeIsSupported($destinationType) === true;
}

/**
 * Tell whether the destination type is a remote transport (SFTP, WebDAV or S3).
 *
 * @return bool
 */
function tpBackupExternalizedDestinationTypeIsRemote(string $destinationType): bool
{
    return in_array(tpBackupNormalizeExternalizedDestinationType($destinationType), ['sftp', 'webdav', 's3'], true) === true;
}

/**
 * Resolve and validate an externalized destination.
 *
 * Remote destination types intentionally flow through this single switch when
 * they are added, so callers do not need to know transport-specific details.
 *
 * @return array{success: bool, path: string, reason: string, destination_type: string}
 */
function tpBackupResolveExternalizedDestination(string $destinationType, string $targetDir, array $SETTINGS = []): array
{
    $destinationType = tpBackupNormalizeExternalizedDestinationType($destinationType);
    if (tpBackupExternalizedDestinationTypeIsSupported($destinationType) === false) {
        return ['success' => false, 'path' => '', 'reason' => 'UNSUPPORTED_TYPE', 'destination_type' => $destinationType];
    }

    if ($destinationType === 'local_directory') {
        $resolved = tpBackupResolveExternalizedLocalDestinationDir($targetDir, $SETTINGS);
        $resolved['destination_type'] = $destinationType;

        return $resolved;
    }

    return ['success' => false, 'path' => '', 'reason' => 'UNSUPPORTED_OPERATION', 'destination_type' => $destinationType];
}

/**
 * Validate an externalized local destination directory.
 *
 * This intentionally targets only an existing local or mounted directory. Remote
 * protocols will get their own validators when their clients are implemented.
 *
 * @return array{success: bool, path: string, reason: string}
 */
function tpBackupResolveExternalizedLocalDestinationDir(string $requestedDir, array $SETTINGS = []): array
{
    $requestedDir = trim(str_replace("\0", '', $requestedDir));
    if ($requestedDir === '') {
        return ['success' => false, 'path' => '', 'reason' => 'EMPTY'];
    }

    if (strlen($requestedDir) > 1024) {
        return ['success' => false, 'path' => '', 'reason' => 'TOO_LONG'];
    }

    if (tpBackupPathLooksAbsolute($requestedDir) === false) {
        return ['success' => false, 'path' => '', 'reason' => 'NOT_ABSOLUTE'];
    }

    if (is_dir($requestedDir) === false) {
        return ['success' => false, 'path' => '', 'reason' => 'NOT_FOUND'];
    }

    $real = realpath($requestedDir);
    if ($real === false || is_dir($real) === false) {
        return ['success' => false, 'path' => '', 'reason' => 'NOT_FOUND'];
    }

    if (is_writable($real) === false) {
        return ['success' => false, 'path' => $real, 'reason' => 'NOT_WRITABLE'];
    }

    $blockedBases = [];
    if (defined('TEAMPASS_ROOT')) {
        $blockedBases[] = (string) TEAMPASS_ROOT;
    }
    if (defined('TEAMPASS_STORAGE')) {
        $blockedBases[] = (string) TEAMPASS_STORAGE;
    }
    foreach (['path_to_files_folder', 'path_to_upload_folder'] as $settingKey) {
        if (!empty($SETTINGS[$settingKey]) && is_scalar($SETTINGS[$settingKey])) {
            $blockedBases[] = (string) $SETTINGS[$settingKey];
        }
    }

    foreach (array_unique(array_filter($blockedBases)) as $blockedBase) {
        if (is_dir($blockedBase) === false) {
            continue;
        }
        if (tpBackupIsResolvedPathInsideDirectory($real, $blockedBase) === true) {
            return ['success' => false, 'path' => $real, 'reason' => 'INSIDE_TEAMPASS'];
        }
    }

    return ['success' => true, 'path' => $real, 'reason' => ''];
}

/**
 * Normalize an SFTP remote path; return '' when it is not absolute or escapes upward.
 *
 * @return string
 */
function tpBackupNormalizeExternalizedSftpRemotePath(string $remotePath): string
{
    $remotePath = trim(str_replace("\0", '', str_replace('\\', '/', $remotePath)));
    if ($remotePath === '') {
        return '';
    }

    $remotePath = preg_replace('#/+#', '/', $remotePath) ?? $remotePath;
    if (str_starts_with($remotePath, '/') === false) {
        return '';
    }

    $parts = [];
    foreach (explode('/', $remotePath) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..' || str_contains($part, ':')) {
            return '';
        }
        $parts[] = $part;
    }

    return '/' . implode('/', $parts);
}

/**
 * @return array{success: bool, reason: string, path: string}
 */
function tpBackupValidateExternalizedSftpConfig(array $config): array
{
    $host = trim(str_replace("\0", '', (string)($config['host'] ?? '')));
    if ($host === '') {
        return ['success' => false, 'reason' => 'SFTP_MISSING_HOST', 'path' => ''];
    }

    $port = (int)($config['port'] ?? 22);
    if ($port < 1 || $port > 65535) {
        return ['success' => false, 'reason' => 'SFTP_INVALID_PORT', 'path' => ''];
    }

    $username = trim(str_replace("\0", '', (string)($config['username'] ?? '')));
    if ($username === '') {
        return ['success' => false, 'reason' => 'SFTP_MISSING_USERNAME', 'path' => ''];
    }

    $remotePath = tpBackupNormalizeExternalizedSftpRemotePath((string)($config['remote_path'] ?? ''));
    if ($remotePath === '') {
        return ['success' => false, 'reason' => 'SFTP_REMOTE_PATH_INVALID', 'path' => ''];
    }

    $authType = ((string)($config['auth_type'] ?? 'password') === 'private_key') ? 'private_key' : 'password';
    if ($authType === 'private_key') {
        $privateKeyAvailable = trim((string)($config['private_key'] ?? '')) !== '' || !empty($config['private_key_available']);
        if ($privateKeyAvailable === false) {
            return ['success' => false, 'reason' => 'SFTP_MISSING_PRIVATE_KEY', 'path' => $remotePath];
        }
    } else {
        $passwordAvailable = (string)($config['password'] ?? '') !== '' || !empty($config['password_available']);
        if ($passwordAvailable === false) {
            return ['success' => false, 'reason' => 'SFTP_MISSING_PASSWORD', 'path' => $remotePath];
        }
    }

    return ['success' => true, 'reason' => '', 'path' => $remotePath];
}

/**
 * Test an externalized destination without exposing secrets to callers.
 *
 * @return array{success: bool, path: string, reason: string, destination_type: string}
 */
function tpBackupTestExternalizedDestination(string $destinationType, string $targetDir, array $SETTINGS = [], array $sftpConfig = []): array
{
    $destinationType = tpBackupNormalizeExternalizedDestinationType($destinationType);
    if (tpBackupExternalizedDestinationTypeIsSupported($destinationType) === false) {
        return ['success' => false, 'path' => '', 'reason' => 'UNSUPPORTED_TYPE', 'destination_type' => $destinationType];
    }

    if ($destinationType === 'local_directory') {
        $resolved = tpBackupResolveExternalizedLocalDestinationDir($targetDir, $SETTINGS);
        $resolved['destination_type'] = $destinationType;

        return $resolved;
    }

    if ($destinationType === 'sftp') {
        $sftpConfig['remote_path'] = $targetDir;

        return tpBackupTestExternalizedSftpDestination($sftpConfig);
    }

    if ($destinationType === 'webdav') {
        $sftpConfig['remote_path'] = $targetDir;

        return tpBackupTestExternalizedWebdavDestination($sftpConfig);
    }

    if ($destinationType === 's3') {
        $sftpConfig['prefix'] = $targetDir;

        return tpBackupTestExternalizedS3Destination($sftpConfig);
    }

    return ['success' => false, 'path' => '', 'reason' => 'UNSUPPORTED_TYPE', 'destination_type' => $destinationType];
}

/**
 * @return array{success: bool, path: string, reason: string, destination_type: string}
 */
function tpBackupTestExternalizedSftpDestination(array $config): array
{
    $connection = tpBackupOpenExternalizedSftpConnection($config);
    if ($connection['success'] === false) {
        return [
            'success' => false,
            'path' => (string) $connection['path'],
            'reason' => (string) $connection['reason'],
            'destination_type' => 'sftp',
        ];
    }

    $remotePath = (string) $connection['path'];
    $sftp = $connection['client'];
    try {
        $testPath = rtrim($remotePath, '/') . '/.teampass-sftp-test-' . bin2hex(random_bytes(6)) . '.tmp';
        $written = $sftp->put($testPath, 'teampass', \phpseclib3\Net\SFTP::SOURCE_STRING);
        if ($written !== true) {
            return ['success' => false, 'path' => $remotePath, 'reason' => 'SFTP_NOT_WRITABLE', 'destination_type' => 'sftp'];
        }

        $sftp->delete($testPath);

        return ['success' => true, 'path' => $remotePath, 'reason' => '', 'destination_type' => 'sftp'];
    } catch (Throwable) {
        return ['success' => false, 'path' => $remotePath, 'reason' => 'SFTP_CONNECTION_FAILED', 'destination_type' => 'sftp'];
    }
}

/**
 * @return array{success: bool, path: string, reason: string, destination_type: string, client?: object}
 */
function tpBackupOpenExternalizedSftpConnection(array $config): array
{
    $validated = tpBackupValidateExternalizedSftpConfig($config);
    if ($validated['success'] === false) {
        return ['success' => false, 'path' => (string) $validated['path'], 'reason' => (string) $validated['reason'], 'destination_type' => 'sftp'];
    }

    if (class_exists('\\phpseclib3\\Net\\SFTP') === false) {
        return ['success' => false, 'path' => (string) $validated['path'], 'reason' => 'SFTP_LIBRARY_MISSING', 'destination_type' => 'sftp'];
    }

    $host = trim(str_replace("\0", '', (string)($config['host'] ?? '')));
    $port = (int)($config['port'] ?? 22);
    $username = trim(str_replace("\0", '', (string)($config['username'] ?? '')));
    $authType = ((string)($config['auth_type'] ?? 'password') === 'private_key') ? 'private_key' : 'password';
    $remotePath = (string) $validated['path'];

    try {
        $sftp = new \phpseclib3\Net\SFTP($host, $port, 15);
        if ($authType === 'private_key') {
            if (class_exists('\\phpseclib3\\Crypt\\PublicKeyLoader') === false) {
                return ['success' => false, 'path' => $remotePath, 'reason' => 'SFTP_LIBRARY_MISSING', 'destination_type' => 'sftp'];
            }

            $passphrase = (string)($config['private_key_passphrase'] ?? '');
            $privateKey = \phpseclib3\Crypt\PublicKeyLoader::loadPrivateKey(
                (string)($config['private_key'] ?? ''),
                $passphrase !== '' ? $passphrase : false
            );
            $loggedIn = $sftp->login($username, $privateKey);
        } else {
            $loggedIn = $sftp->login($username, (string)($config['password'] ?? ''));
        }

        if ($loggedIn !== true) {
            return ['success' => false, 'path' => $remotePath, 'reason' => 'SFTP_AUTH_FAILED', 'destination_type' => 'sftp'];
        }

        if ($sftp->is_dir($remotePath) !== true) {
            return ['success' => false, 'path' => $remotePath, 'reason' => 'SFTP_PATH_NOT_FOUND', 'destination_type' => 'sftp'];
        }

        return ['success' => true, 'path' => $remotePath, 'reason' => '', 'destination_type' => 'sftp', 'client' => $sftp];
    } catch (Throwable) {
        return ['success' => false, 'path' => $remotePath, 'reason' => 'SFTP_CONNECTION_FAILED', 'destination_type' => 'sftp'];
    }
}

/**
 * Build the full SFTP remote path for a backup file inside the remote directory.
 *
 * @return string
 */
function tpBackupExternalizedSftpRemoteFilePath(string $remoteDir, string $filename): string
{
    $filename = basename(str_replace('\\', '/', $filename));
    if (tpBackupExternalizedBackupFilenameIsAllowed($filename) === false) {
        return '';
    }

    $remoteDir = tpBackupNormalizeExternalizedSftpRemotePath($remoteDir);
    if ($remoteDir === '') {
        return '';
    }

    return rtrim($remoteDir, '/') . '/' . $filename;
}

/**
 * @return array<string, mixed>
 */
function tpBackupReadExternalizedSftpMetadata($sftp, string $remoteFilePath): array
{
    try {
        $payload = $sftp->get($remoteFilePath . '.meta.json');
        if (is_string($payload) === false || $payload === '') {
            return [];
        }

        $data = json_decode($payload, true);
        return is_array($data) === true ? $data : [];
    } catch (Throwable) {
        return [];
    }
}

/**
 * Normalize a WebDAV remote path; return '' when it is invalid.
 *
 * @return string
 */
function tpBackupNormalizeExternalizedWebdavRemotePath(string $remotePath): string
{
    $remotePath = trim(str_replace("\0", '', str_replace('\\', '/', $remotePath)));
    if ($remotePath === '') {
        return '';
    }

    $remotePath = preg_replace('#/+#', '/', $remotePath) ?? $remotePath;
    if (str_starts_with($remotePath, '/') === false) {
        return '';
    }

    $parts = [];
    foreach (explode('/', $remotePath) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..' || str_contains($part, '?') || str_contains($part, '#')) {
            return '';
        }
        $parts[] = $part;
    }

    return '/' . implode('/', $parts);
}

/**
 * @return array{success: bool, reason: string, path: string}
 */
function tpBackupValidateExternalizedWebdavConfig(array $config): array
{
    $baseUrl = trim(str_replace("\0", '', (string) ($config['url'] ?? '')));
    if ($baseUrl === '') {
        return ['success' => false, 'reason' => 'WEBDAV_MISSING_URL', 'path' => ''];
    }

    if (strlen($baseUrl) > 2048 || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
        return ['success' => false, 'reason' => 'WEBDAV_URL_INVALID', 'path' => ''];
    }

    $parts = parse_url($baseUrl);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (($scheme !== 'http' && $scheme !== 'https') || empty($parts['host'])) {
        return ['success' => false, 'reason' => 'WEBDAV_URL_INVALID', 'path' => ''];
    }

    $username = trim(str_replace("\0", '', (string) ($config['username'] ?? '')));
    if ($username === '') {
        return ['success' => false, 'reason' => 'WEBDAV_MISSING_USERNAME', 'path' => ''];
    }

    $passwordAvailable = (string) ($config['password'] ?? '') !== '' || !empty($config['password_available']);
    if ($passwordAvailable === false) {
        return ['success' => false, 'reason' => 'WEBDAV_MISSING_PASSWORD', 'path' => ''];
    }

    $remotePath = tpBackupNormalizeExternalizedWebdavRemotePath((string) ($config['remote_path'] ?? ''));
    if ($remotePath === '') {
        return ['success' => false, 'reason' => 'WEBDAV_REMOTE_PATH_INVALID', 'path' => ''];
    }

    return ['success' => true, 'reason' => '', 'path' => $remotePath];
}

/**
 * @return array{success: bool, path: string, reason: string, destination_type: string, client?: object, config?: array<string, mixed>}
 */
function tpBackupOpenExternalizedWebdavConnection(array $config): array
{
    $validated = tpBackupValidateExternalizedWebdavConfig($config);
    if ($validated['success'] === false) {
        return ['success' => false, 'path' => (string) $validated['path'], 'reason' => (string) $validated['reason'], 'destination_type' => 'webdav'];
    }

    if (class_exists('\\GuzzleHttp\\Client') === false) {
        return ['success' => false, 'path' => (string) $validated['path'], 'reason' => 'WEBDAV_LIBRARY_MISSING', 'destination_type' => 'webdav'];
    }

    try {
        $client = new \GuzzleHttp\Client([
            'timeout' => 30,
            'connect_timeout' => 15,
            'http_errors' => false,
        ]);
    } catch (Throwable) {
        return ['success' => false, 'path' => (string) $validated['path'], 'reason' => 'WEBDAV_CONNECTION_FAILED', 'destination_type' => 'webdav'];
    }

    $cleanConfig = [
        'url' => rtrim(trim((string) ($config['url'] ?? '')), '/'),
        'username' => trim(str_replace("\0", '', (string) ($config['username'] ?? ''))),
        'password' => (string) ($config['password'] ?? ''),
        'remote_path' => (string) $validated['path'],
    ];

    return [
        'success' => true,
        'path' => (string) $validated['path'],
        'reason' => '',
        'destination_type' => 'webdav',
        'client' => $client,
        'config' => $cleanConfig,
    ];
}

/**
 * Build the full WebDAV remote path for a backup file inside the remote directory.
 *
 * @return string
 */
function tpBackupExternalizedWebdavRemoteFilePath(string $remoteDir, string $filename): string
{
    $filename = basename(str_replace('\\', '/', $filename));
    if (tpBackupExternalizedBackupFilenameIsAllowed($filename) === false) {
        return '';
    }

    $remoteDir = tpBackupNormalizeExternalizedWebdavRemotePath($remoteDir);
    if ($remoteDir === '') {
        return '';
    }

    return rtrim($remoteDir, '/') . '/' . $filename;
}

/**
 * Build an absolute WebDAV URL from the base URL and a remote path.
 *
 * @return string
 */
function tpBackupBuildExternalizedWebdavUrl(string $baseUrl, string $remotePath): string
{
    $baseUrl = rtrim(trim($baseUrl), '/');
    $remotePath = tpBackupNormalizeExternalizedWebdavRemotePath($remotePath);
    if ($baseUrl === '' || $remotePath === '') {
        return '';
    }

    $segments = array_filter(explode('/', trim($remotePath, '/')), static function (string $part): bool {
        return $part !== '';
    });

    if (empty($segments) === true) {
        return $baseUrl . '/';
    }

    return $baseUrl . '/' . implode('/', array_map('rawurlencode', $segments));
}

/**
 * Send an authenticated WebDAV request through the given HTTP client.
 *
 * @param object $client HTTP client exposing a request() method
 * @param array<string, mixed> $config
 * @param array<string, mixed> $options
 * @return mixed HTTP response object
 */
function tpBackupExternalizedWebdavRequest($client, array $config, string $method, string $remotePath, array $options = [])
{
    $url = tpBackupBuildExternalizedWebdavUrl((string) ($config['url'] ?? ''), $remotePath);
    if ($url === '') {
        throw new RuntimeException('Invalid WebDAV URL.');
    }

    $requestOptions = array_replace_recursive([
        'http_errors' => false,
        'timeout' => 30,
        'connect_timeout' => 15,
        'headers' => [
            'User-Agent' => 'TeamPass-Backup-WebDAV',
        ],
    ], $options);

    $username = (string) ($config['username'] ?? '');
    if ($username !== '') {
        $requestOptions['auth'] = [$username, (string) ($config['password'] ?? '')];
    }

    return $client->request($method, $url, $requestOptions);
}

/**
 * Map a WebDAV HTTP status code to a TeamPass error reason code.
 *
 * @return string
 */
function tpBackupExternalizedWebdavReasonFromStatus(int $status, string $fallback): string
{
    if ($status === 401 || $status === 403) {
        return 'WEBDAV_AUTH_FAILED';
    }

    if ($status === 404 || $status === 409) {
        return 'WEBDAV_PATH_NOT_FOUND';
    }

    return $fallback;
}

/**
 * @return array<int, array{name: string, path: string, size_bytes: int, mtime: int, is_dir: bool}>
 */
function tpBackupParseExternalizedWebdavPropfind(string $payload): array
{
    if (trim($payload) === '') {
        return [];
    }

    $previous = libxml_use_internal_errors(true);
    try {
        $xml = simplexml_load_string($payload, 'SimpleXMLElement', LIBXML_NONET);
        if ($xml === false) {
            return [];
        }

        $xml->registerXPathNamespace('d', 'DAV:');
        $responses = $xml->xpath('//d:response') ?: [];
        $items = [];

        foreach ($responses as $response) {
            $response->registerXPathNamespace('d', 'DAV:');
            $hrefNodes = $response->xpath('./d:href') ?: [];
            if (empty($hrefNodes) === true) {
                continue;
            }

            $href = (string) $hrefNodes[0];
            $path = parse_url($href, PHP_URL_PATH);
            $decodedPath = rawurldecode((string) ($path !== null && $path !== false ? $path : $href));
            $name = basename(str_replace('\\', '/', $decodedPath));
            if ($name === '' || $name === '.' || $name === '..') {
                continue;
            }

            $propNodes = $response->xpath('./d:propstat/d:prop') ?: [];
            $sizeBytes = 0;
            $mtime = 0;
            $isDir = false;
            foreach ($propNodes as $prop) {
                $dav = $prop->children('DAV:');
                if (isset($dav->getcontentlength)) {
                    $sizeBytes = (int) $dav->getcontentlength;
                }
                if (isset($dav->getlastmodified)) {
                    $parsedMtime = strtotime((string) $dav->getlastmodified);
                    $mtime = $parsedMtime !== false ? (int) $parsedMtime : $mtime;
                }
                if (isset($dav->resourcetype)) {
                    $collections = $dav->resourcetype->children('DAV:');
                    if (isset($collections->collection)) {
                        $isDir = true;
                    }
                }
            }

            $items[] = [
                'name' => $name,
                'path' => $decodedPath,
                'size_bytes' => $sizeBytes,
                'mtime' => $mtime,
                'is_dir' => $isDir,
            ];
        }

        return $items;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

/**
 * @return array<int, array{name: string, path: string, size_bytes: int, mtime: int, is_dir: bool}>
 */
function tpBackupExternalizedWebdavPropfind($client, array $config, string $remotePath, int $depth): array
{
    $response = tpBackupExternalizedWebdavRequest(
        $client,
        $config,
        'PROPFIND',
        $remotePath,
        [
            'headers' => [
                'Depth' => (string) $depth,
                'Content-Type' => 'application/xml; charset=utf-8',
            ],
            'body' => '<?xml version="1.0" encoding="utf-8" ?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/><d:getcontentlength/><d:getlastmodified/></d:prop></d:propfind>',
        ]
    );

    $status = (int) $response->getStatusCode();
    if ($status !== 207 && $status !== 200) {
        throw new RuntimeException(tpBackupExternalizedWebdavReasonFromStatus($status, 'WEBDAV_CONNECTION_FAILED'));
    }

    return tpBackupParseExternalizedWebdavPropfind((string) $response->getBody());
}

/**
 * @return array{name: string, path: string, size_bytes: int, mtime: int, is_dir: bool}|null
 */
function tpBackupExternalizedWebdavStat($client, array $config, string $remoteFilePath): ?array
{
    $items = tpBackupExternalizedWebdavPropfind($client, $config, $remoteFilePath, 0);
    $filename = basename(str_replace('\\', '/', $remoteFilePath));
    foreach ($items as $item) {
        if ((string) $item['name'] === $filename) {
            return $item;
        }
    }

    return null;
}

/**
 * @return array<string, mixed>
 */
function tpBackupReadExternalizedWebdavMetadata($client, array $config, string $remoteFilePath): array
{
    try {
        $response = tpBackupExternalizedWebdavRequest($client, $config, 'GET', $remoteFilePath . '.meta.json');
        $status = (int) $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return [];
        }

        $payload = (string) $response->getBody();
        if (trim($payload) === '') {
            return [];
        }

        $data = json_decode($payload, true);
        return is_array($data) === true ? $data : [];
    } catch (Throwable) {
        return [];
    }
}

/**
 * @return array{success: bool, path: string, reason: string, destination_type: string}
 */
function tpBackupTestExternalizedWebdavDestination(array $config): array
{
    $connection = tpBackupOpenExternalizedWebdavConnection($config);
    if ($connection['success'] === false) {
        return [
            'success' => false,
            'path' => (string) $connection['path'],
            'reason' => (string) $connection['reason'],
            'destination_type' => 'webdav',
        ];
    }

    $remotePath = (string) $connection['path'];
    $client = $connection['client'];
    $webdavConfig = (array) $connection['config'];
    try {
        $items = tpBackupExternalizedWebdavPropfind($client, $webdavConfig, $remotePath, 0);
        $directoryFound = false;
        foreach ($items as $item) {
            if (!empty($item['is_dir'])) {
                $directoryFound = true;
                break;
            }
        }
        if ($directoryFound === false) {
            return ['success' => false, 'path' => $remotePath, 'reason' => 'WEBDAV_PATH_NOT_FOUND', 'destination_type' => 'webdav'];
        }

        $testFile = rtrim($remotePath, '/') . '/.teampass-webdav-test-' . bin2hex(random_bytes(6)) . '.tmp';
        $put = tpBackupExternalizedWebdavRequest($client, $webdavConfig, 'PUT', $testFile, ['body' => 'teampass']);
        $putStatus = (int) $put->getStatusCode();
        if ($putStatus < 200 || $putStatus >= 300) {
            return ['success' => false, 'path' => $remotePath, 'reason' => tpBackupExternalizedWebdavReasonFromStatus($putStatus, 'WEBDAV_NOT_WRITABLE'), 'destination_type' => 'webdav'];
        }

        tpBackupExternalizedWebdavRequest($client, $webdavConfig, 'DELETE', $testFile);

        return ['success' => true, 'path' => $remotePath, 'reason' => '', 'destination_type' => 'webdav'];
    } catch (Throwable $e) {
        $reason = in_array($e->getMessage(), ['WEBDAV_AUTH_FAILED', 'WEBDAV_PATH_NOT_FOUND', 'WEBDAV_CONNECTION_FAILED'], true) === true
            ? $e->getMessage()
            : 'WEBDAV_CONNECTION_FAILED';

        return ['success' => false, 'path' => $remotePath, 'reason' => $reason, 'destination_type' => 'webdav'];
    }
}

/**
 * Normalize an S3 object prefix (trim slashes, reject path traversal).
 *
 * @return string
 */
function tpBackupNormalizeExternalizedS3Prefix(string $prefix): string
{
    $prefix = trim(str_replace("\0", '', str_replace('\\', '/', $prefix)));
    if ($prefix === '') {
        return '';
    }

    $prefix = preg_replace('#/+#', '/', $prefix) ?? $prefix;
    $prefix = trim($prefix, '/');
    if ($prefix === '') {
        return '';
    }

    $parts = [];
    foreach (explode('/', $prefix) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..' || str_contains($part, '?') || str_contains($part, '#')) {
            return '';
        }
        $parts[] = $part;
    }

    return implode('/', $parts);
}

/**
 * @return array{success: bool, reason: string, path: string}
 */
function tpBackupValidateExternalizedS3Config(array $config): array
{
    $region = strtolower(trim(str_replace("\0", '', (string) ($config['region'] ?? 'us-east-1'))));
    if ($region === '') {
        $region = 'us-east-1';
    }
    if (preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/', $region) !== 1) {
        return ['success' => false, 'reason' => 'S3_REGION_INVALID', 'path' => ''];
    }

    $endpoint = trim(str_replace("\0", '', (string) ($config['endpoint'] ?? '')));
    if ($endpoint !== '') {
        if (strlen($endpoint) > 2048 || filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            return ['success' => false, 'reason' => 'S3_ENDPOINT_INVALID', 'path' => ''];
        }

        $parts = parse_url($endpoint);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (($scheme !== 'http' && $scheme !== 'https') || empty($parts['host'])) {
            return ['success' => false, 'reason' => 'S3_ENDPOINT_INVALID', 'path' => ''];
        }
    }

    $bucket = trim(str_replace("\0", '', (string) ($config['bucket'] ?? '')));
    if ($bucket === '') {
        return ['success' => false, 'reason' => 'S3_MISSING_BUCKET', 'path' => ''];
    }
    if (strlen($bucket) > 255 || str_contains($bucket, '/') || str_contains($bucket, '\\')) {
        return ['success' => false, 'reason' => 'S3_BUCKET_INVALID', 'path' => ''];
    }

    $accessKey = trim(str_replace("\0", '', (string) ($config['access_key'] ?? '')));
    if ($accessKey === '') {
        return ['success' => false, 'reason' => 'S3_MISSING_ACCESS_KEY', 'path' => ''];
    }

    $secretAvailable = (string) ($config['secret_key'] ?? '') !== '' || !empty($config['secret_key_available']);
    if ($secretAvailable === false) {
        return ['success' => false, 'reason' => 'S3_MISSING_SECRET_KEY', 'path' => ''];
    }

    $prefix = tpBackupNormalizeExternalizedS3Prefix((string) ($config['prefix'] ?? $config['remote_path'] ?? ''));
    if ((string) ($config['prefix'] ?? $config['remote_path'] ?? '') !== '' && $prefix === '') {
        return ['success' => false, 'reason' => 'S3_PREFIX_INVALID', 'path' => ''];
    }

    return ['success' => true, 'reason' => '', 'path' => $prefix];
}

/**
 * Return the default AWS S3 endpoint for the given region.
 *
 * @return string
 */
function tpBackupExternalizedS3DefaultEndpoint(string $region): string
{
    return $region === 'us-east-1'
        ? 'https://s3.amazonaws.com'
        : 'https://s3.' . $region . '.amazonaws.com';
}

/**
 * @return array{success: bool, path: string, reason: string, destination_type: string, config?: array<string, mixed>}
 */
function tpBackupOpenExternalizedS3Connection(array $config): array
{
    $validated = tpBackupValidateExternalizedS3Config($config);
    if ($validated['success'] === false) {
        return ['success' => false, 'path' => (string) $validated['path'], 'reason' => (string) $validated['reason'], 'destination_type' => 's3'];
    }

    if (function_exists('curl_init') === false) {
        return ['success' => false, 'path' => (string) $validated['path'], 'reason' => 'S3_CURL_MISSING', 'destination_type' => 's3'];
    }

    $region = strtolower(trim((string) ($config['region'] ?? 'us-east-1')));
    if ($region === '') {
        $region = 'us-east-1';
    }

    $endpoint = trim((string) ($config['endpoint'] ?? ''));
    if ($endpoint === '') {
        $endpoint = tpBackupExternalizedS3DefaultEndpoint($region);
    }

    $cleanConfig = [
        'endpoint' => rtrim($endpoint, '/'),
        'region' => $region,
        'bucket' => trim(str_replace("\0", '', (string) ($config['bucket'] ?? ''))),
        'access_key' => trim(str_replace("\0", '', (string) ($config['access_key'] ?? ''))),
        'secret_key' => (string) ($config['secret_key'] ?? ''),
        'path_style' => ((int) ($config['path_style'] ?? 1) === 1),
        'prefix' => (string) $validated['path'],
    ];

    return [
        'success' => true,
        'path' => (string) $validated['path'],
        'reason' => '',
        'destination_type' => 's3',
        'config' => $cleanConfig,
    ];
}

/**
 * Build the S3 object key for a backup file under the given prefix.
 *
 * @return string
 */
function tpBackupExternalizedS3ObjectKey(string $prefix, string $filename): string
{
    $filename = basename(str_replace('\\', '/', $filename));
    if (tpBackupExternalizedBackupFilenameIsAllowed($filename) === false) {
        return '';
    }

    $prefix = tpBackupNormalizeExternalizedS3Prefix($prefix);
    return $prefix === '' ? $filename : $prefix . '/' . $filename;
}

/**
 * URL-encode an S3 path segment by segment for canonical requests.
 *
 * @return string
 */
function tpBackupExternalizedS3EncodePath(string $path): string
{
    $path = trim(str_replace('\\', '/', $path), '/');
    if ($path === '') {
        return '';
    }

    return implode('/', array_map('rawurlencode', explode('/', $path)));
}

/**
 * Build the canonical query string used for AWS Signature V4.
 *
 * @param array<string, string|null> $query
 * @return string
 */
function tpBackupExternalizedS3CanonicalQuery(array $query): string
{
    ksort($query);
    $parts = [];
    foreach ($query as $key => $value) {
        if ($value === null) {
            continue;
        }
        $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
    }

    return implode('&', $parts);
}

/**
 * @return array{url: string, host: string, canonical_uri: string, canonical_query: string}
 */
function tpBackupExternalizedS3BuildRequestTarget(array $config, string $key, array $query): array
{
    $endpoint = rtrim((string) ($config['endpoint'] ?? ''), '/');
    $parts = parse_url($endpoint);
    $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
    $host = (string) ($parts['host'] ?? '');
    $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
    $basePath = trim((string) ($parts['path'] ?? ''), '/');
    $bucket = (string) ($config['bucket'] ?? '');
    $pathStyle = !empty($config['path_style']);
    $encodedKey = tpBackupExternalizedS3EncodePath($key);

    if ($pathStyle === true) {
        $canonicalUri = '/' . ($basePath !== '' ? $basePath . '/' : '') . rawurlencode($bucket);
        $canonicalUri .= $encodedKey !== '' ? '/' . $encodedKey : '/';
        $requestHost = $host . $port;
    } else {
        $canonicalUri = '/' . ($basePath !== '' ? $basePath . '/' : '');
        $canonicalUri .= $encodedKey !== '' ? $encodedKey : '';
        $requestHost = $bucket . '.' . $host . $port;
    }

    $canonicalUri = preg_replace('#/+#', '/', $canonicalUri) ?? $canonicalUri;

    $canonicalQuery = tpBackupExternalizedS3CanonicalQuery($query);
    $url = $scheme . '://' . $requestHost . $canonicalUri;
    if ($canonicalQuery !== '') {
        $url .= '?' . $canonicalQuery;
    }

    return [
        'url' => $url,
        'host' => $requestHost,
        'canonical_uri' => $canonicalUri,
        'canonical_query' => $canonicalQuery,
    ];
}

/**
 * Derive the AWS Signature V4 signing key for the date, region and S3 service.
 *
 * @return string
 */
function tpBackupExternalizedS3SigningKey(string $secretKey, string $dateStamp, string $region): string
{
    $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', 's3', $kRegion, true);

    return hash_hmac('sha256', 'aws4_request', $kService, true);
}

/**
 * @return array{status: int, body: string, error: string, headers: array<string, string>}
 */
function tpBackupExternalizedS3Request(array $config, string $method, string $key = '', array $options = []): array
{
    $method = strtoupper($method);
    $query = is_array($options['query'] ?? null) ? (array) $options['query'] : [];
    $target = tpBackupExternalizedS3BuildRequestTarget($config, $key, $query);
    $dateStamp = gmdate('Ymd');
    $amzDate = gmdate('Ymd\THis\Z');
    $sourceFile = isset($options['source_file']) && is_scalar($options['source_file']) ? (string) $options['source_file'] : '';
    $body = array_key_exists('body', $options) ? (string) $options['body'] : '';

    if ($sourceFile !== '') {
        $payloadHash = is_file($sourceFile) ? (string) hash_file('sha256', $sourceFile) : '';
        if ($payloadHash === '') {
            return ['status' => 0, 'body' => '', 'error' => 'source_file_unavailable', 'headers' => []];
        }
    } elseif (array_key_exists('body', $options) === true) {
        $payloadHash = hash('sha256', $body);
    } else {
        $payloadHash = hash('sha256', '');
    }

    $headersToSign = [
        'host' => $target['host'],
        'x-amz-content-sha256' => $payloadHash,
        'x-amz-date' => $amzDate,
    ];
    ksort($headersToSign);

    $canonicalHeaders = '';
    foreach ($headersToSign as $headerName => $headerValue) {
        $canonicalHeaders .= strtolower($headerName) . ':' . trim((string) preg_replace('/\s+/', ' ', (string) $headerValue)) . "\n";
    }
    $signedHeaders = implode(';', array_keys($headersToSign));

    $canonicalRequest = implode("\n", [
        $method,
        $target['canonical_uri'],
        $target['canonical_query'],
        $canonicalHeaders,
        $signedHeaders,
        $payloadHash,
    ]);

    $credentialScope = $dateStamp . '/' . (string) $config['region'] . '/s3/aws4_request';
    $stringToSign = implode("\n", [
        'AWS4-HMAC-SHA256',
        $amzDate,
        $credentialScope,
        hash('sha256', $canonicalRequest),
    ]);
    $signature = hash_hmac(
        'sha256',
        $stringToSign,
        tpBackupExternalizedS3SigningKey((string) $config['secret_key'], $dateStamp, (string) $config['region'])
    );
    $authorization = 'AWS4-HMAC-SHA256 Credential=' . (string) $config['access_key'] . '/' . $credentialScope
        . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

    $curlHeaders = [
        'Host: ' . $target['host'],
        'x-amz-content-sha256: ' . $payloadHash,
        'x-amz-date: ' . $amzDate,
        'Authorization: ' . $authorization,
    ];
    if (isset($options['content_type']) && is_scalar($options['content_type'])) {
        $curlHeaders[] = 'Content-Type: ' . (string) $options['content_type'];
    }

    $responseHeaders = [];
    $ch = curl_init($target['url']);
    if ($ch === false) {
        return ['status' => 0, 'body' => '', 'error' => 'curl_init_failed', 'headers' => []];
    }

    $fileHandle = null;
    $sinkHandle = null;
    try {
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_FAILONERROR => false,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$responseHeaders): int {
                $length = strlen($headerLine);
                $parts = explode(':', $headerLine, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $length;
            },
        ]);

        if ($method === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        } elseif ($sourceFile !== '') {
            $fileHandle = fopen($sourceFile, 'rb');
            if ($fileHandle === false) {
                return ['status' => 0, 'body' => '', 'error' => 'source_file_unavailable', 'headers' => []];
            }
            curl_setopt($ch, CURLOPT_UPLOAD, true);
            curl_setopt($ch, CURLOPT_INFILE, $fileHandle);
            curl_setopt($ch, CURLOPT_INFILESIZE, (int) (@filesize($sourceFile) ?: 0));
        } elseif (array_key_exists('body', $options) === true) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $sink = isset($options['sink']) && is_scalar($options['sink']) ? (string) $options['sink'] : '';
        if ($sink !== '') {
            $sinkHandle = fopen($sink, 'wb');
            if ($sinkHandle === false) {
                return ['status' => 0, 'body' => '', 'error' => 'sink_unavailable', 'headers' => []];
            }
            curl_setopt($ch, CURLOPT_FILE, $sinkHandle);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        }

        $bodyResponse = curl_exec($ch);
        $error = $bodyResponse === false ? curl_error($ch) : '';
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        return [
            'status' => $status,
            'body' => is_string($bodyResponse) ? $bodyResponse : '',
            'error' => $error,
            'headers' => $responseHeaders,
        ];
    } finally {
        if (is_resource($fileHandle) === true) {
            fclose($fileHandle);
        }
        if (is_resource($sinkHandle) === true) {
            fclose($sinkHandle);
        }
        curl_close($ch);
    }
}

/**
 * Map an S3 HTTP status code to a TeamPass error reason code.
 *
 * @return string
 */
function tpBackupExternalizedS3ReasonFromStatus(int $status, string $fallback): string
{
    if ($status === 401 || $status === 403) {
        return 'S3_AUTH_FAILED';
    }

    if ($status === 404) {
        return 'S3_NOT_FOUND';
    }

    return $fallback;
}

/**
 * @return array{objects: array<int, array{key: string, size_bytes: int, mtime: int}>, truncated: bool, next_token: string}
 */
function tpBackupParseExternalizedS3ListObjects(string $payload): array
{
    $result = ['objects' => [], 'truncated' => false, 'next_token' => ''];
    if (trim($payload) === '') {
        return $result;
    }

    $previous = libxml_use_internal_errors(true);
    try {
        $xml = simplexml_load_string($payload, 'SimpleXMLElement', LIBXML_NONET);
        if ($xml === false) {
            return $result;
        }

        foreach ($xml->Contents as $content) {
            $key = (string) $content->Key;
            if ($key === '') {
                continue;
            }
            $mtime = strtotime((string) $content->LastModified);
            $result['objects'][] = [
                'key' => $key,
                'size_bytes' => (int) $content->Size,
                'mtime' => $mtime !== false ? (int) $mtime : 0,
            ];
        }

        $result['truncated'] = strtolower((string) $xml->IsTruncated) === 'true';
        $result['next_token'] = (string) $xml->NextContinuationToken;

        return $result;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

/**
 * @return array<int, array{key: string, size_bytes: int, mtime: int}>
 */
function tpBackupExternalizedS3ListObjects(array $config, string $prefix): array
{
    $objects = [];
    $token = '';
    for ($page = 0; $page < 100; $page++) {
        $query = [
            'list-type' => '2',
            'max-keys' => '1000',
        ];
        if ($prefix !== '') {
            $query['prefix'] = $prefix;
        }
        if ($token !== '') {
            $query['continuation-token'] = $token;
        }

        $response = tpBackupExternalizedS3Request($config, 'GET', '', ['query' => $query]);
        $status = (int) $response['status'];
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(tpBackupExternalizedS3ReasonFromStatus($status, 'S3_LIST_FAILED'));
        }

        $parsed = tpBackupParseExternalizedS3ListObjects((string) $response['body']);
        foreach ($parsed['objects'] as $object) {
            $objects[] = $object;
        }

        if ($parsed['truncated'] !== true || (string) $parsed['next_token'] === '') {
            break;
        }
        $token = (string) $parsed['next_token'];
    }

    return $objects;
}

/**
 * @return array{key: string, size_bytes: int, mtime: int}|null
 */
function tpBackupExternalizedS3Stat(array $config, string $key): ?array
{
    $objects = tpBackupExternalizedS3ListObjects($config, $key);
    foreach ($objects as $object) {
        if ((string) $object['key'] === $key) {
            return $object;
        }
    }

    return null;
}

/**
 * @return array<string, mixed>
 */
function tpBackupReadExternalizedS3Metadata(array $config, string $key): array
{
    try {
        $response = tpBackupExternalizedS3Request($config, 'GET', $key . '.meta.json');
        $status = (int) $response['status'];
        if ($status < 200 || $status >= 300) {
            return [];
        }

        $data = json_decode((string) $response['body'], true);
        return is_array($data) === true ? $data : [];
    } catch (Throwable) {
        return [];
    }
}

/**
 * @return array{success: bool, path: string, reason: string, destination_type: string}
 */
function tpBackupTestExternalizedS3Destination(array $config): array
{
    $connection = tpBackupOpenExternalizedS3Connection($config);
    if ($connection['success'] === false) {
        return [
            'success' => false,
            'path' => (string) $connection['path'],
            'reason' => (string) $connection['reason'],
            'destination_type' => 's3',
        ];
    }

    $s3Config = (array) $connection['config'];
    $prefix = (string) $connection['path'];
    try {
        tpBackupExternalizedS3ListObjects($s3Config, $prefix);
        $testName = '.teampass-s3-test-' . bin2hex(random_bytes(6)) . '.tmp';
        $testKey = $prefix === '' ? $testName : $prefix . '/' . $testName;
        $put = tpBackupExternalizedS3Request($s3Config, 'PUT', $testKey, ['body' => 'teampass', 'content_type' => 'application/octet-stream']);
        $putStatus = (int) $put['status'];
        if ($putStatus < 200 || $putStatus >= 300) {
            return ['success' => false, 'path' => $prefix, 'reason' => tpBackupExternalizedS3ReasonFromStatus($putStatus, 'S3_NOT_WRITABLE'), 'destination_type' => 's3'];
        }

        tpBackupExternalizedS3Request($s3Config, 'DELETE', $testKey);

        return ['success' => true, 'path' => $prefix, 'reason' => '', 'destination_type' => 's3'];
    } catch (Throwable $e) {
        $reason = in_array($e->getMessage(), ['S3_AUTH_FAILED', 'S3_NOT_FOUND', 'S3_LIST_FAILED'], true) === true
            ? $e->getMessage()
            : 'S3_CONNECTION_FAILED';

        return ['success' => false, 'path' => $prefix, 'reason' => $reason, 'destination_type' => 's3'];
    }
}

/**
 * Upload a locally generated backup and its optional sidecar to the destination.
 *
 * @return array{success: bool, reason: string, destination_type: string, path: string, filename: string, size_bytes: int}
 */
function tpBackupUploadExternalizedBackup(string $destinationType, string $targetDir, string $localFilePath, array $SETTINGS = [], array $sftpConfig = []): array
{
    $destinationType = tpBackupNormalizeExternalizedDestinationType($destinationType);
    $filename = basename(str_replace('\\', '/', $localFilePath));
    if (tpBackupExternalizedBackupFilenameIsAllowed($filename) === false || is_file($localFilePath) === false) {
        return ['success' => false, 'reason' => 'INVALID_FILENAME', 'destination_type' => $destinationType, 'path' => '', 'filename' => $filename, 'size_bytes' => 0];
    }

    if ($destinationType === 'local_directory') {
        $resolved = tpBackupResolveExternalizedBackupFile($destinationType, $targetDir, $filename, $SETTINGS);
        if ($resolved['success'] === false) {
            return ['success' => false, 'reason' => (string) $resolved['reason'], 'destination_type' => $destinationType, 'path' => '', 'filename' => $filename, 'size_bytes' => 0];
        }

        return ['success' => true, 'reason' => '', 'destination_type' => $destinationType, 'path' => (string) $resolved['path'], 'filename' => $filename, 'size_bytes' => (int) $resolved['size_bytes']];
    }

    if ($destinationType === 'webdav') {
        $sftpConfig['remote_path'] = $targetDir;
        $connection = tpBackupOpenExternalizedWebdavConnection($sftpConfig);
        if ($connection['success'] === false) {
            return ['success' => false, 'reason' => (string) $connection['reason'], 'destination_type' => 'webdav', 'path' => (string) $connection['path'], 'filename' => $filename, 'size_bytes' => 0];
        }

        $remoteFilePath = tpBackupExternalizedWebdavRemoteFilePath((string) $connection['path'], $filename);
        if ($remoteFilePath === '') {
            return ['success' => false, 'reason' => 'INVALID_FILENAME', 'destination_type' => 'webdav', 'path' => '', 'filename' => $filename, 'size_bytes' => 0];
        }

        try {
            $client = $connection['client'];
            $webdavConfig = (array) $connection['config'];
            $resource = fopen($localFilePath, 'rb');
            if ($resource === false) {
                return ['success' => false, 'reason' => 'WEBDAV_UPLOAD_FAILED', 'destination_type' => 'webdav', 'path' => $remoteFilePath, 'filename' => $filename, 'size_bytes' => 0];
            }

            try {
                $uploaded = tpBackupExternalizedWebdavRequest($client, $webdavConfig, 'PUT', $remoteFilePath, ['body' => $resource]);
            } finally {
                fclose($resource);
            }

            $uploadStatus = (int) $uploaded->getStatusCode();
            if ($uploadStatus < 200 || $uploadStatus >= 300) {
                return ['success' => false, 'reason' => tpBackupExternalizedWebdavReasonFromStatus($uploadStatus, 'WEBDAV_UPLOAD_FAILED'), 'destination_type' => 'webdav', 'path' => $remoteFilePath, 'filename' => $filename, 'size_bytes' => 0];
            }

            $metadataPath = tpGetBackupMetadataPath($localFilePath);
            if (is_file($metadataPath) === true) {
                $metaResource = fopen($metadataPath, 'rb');
                if ($metaResource !== false) {
                    try {
                        $metaUploaded = tpBackupExternalizedWebdavRequest($client, $webdavConfig, 'PUT', $remoteFilePath . '.meta.json', ['body' => $metaResource]);
                    } finally {
                        fclose($metaResource);
                    }
                    $metaStatus = (int) $metaUploaded->getStatusCode();
                    if ($metaStatus < 200 || $metaStatus >= 300) {
                        return ['success' => false, 'reason' => tpBackupExternalizedWebdavReasonFromStatus($metaStatus, 'WEBDAV_UPLOAD_FAILED'), 'destination_type' => 'webdav', 'path' => $remoteFilePath . '.meta.json', 'filename' => $filename, 'size_bytes' => 0];
                    }
                }
            }

            return ['success' => true, 'reason' => '', 'destination_type' => 'webdav', 'path' => $remoteFilePath, 'filename' => $filename, 'size_bytes' => (int) (@filesize($localFilePath) ?: 0)];
        } catch (Throwable) {
            return ['success' => false, 'reason' => 'WEBDAV_UPLOAD_FAILED', 'destination_type' => 'webdav', 'path' => $remoteFilePath, 'filename' => $filename, 'size_bytes' => 0];
        }
    }

    if ($destinationType === 's3') {
        $sftpConfig['prefix'] = $targetDir;
        $connection = tpBackupOpenExternalizedS3Connection($sftpConfig);
        if ($connection['success'] === false) {
            return ['success' => false, 'reason' => (string) $connection['reason'], 'destination_type' => 's3', 'path' => (string) $connection['path'], 'filename' => $filename, 'size_bytes' => 0];
        }

        $s3Config = (array) $connection['config'];
        $objectKey = tpBackupExternalizedS3ObjectKey((string) $connection['path'], $filename);
        if ($objectKey === '') {
            return ['success' => false, 'reason' => 'INVALID_FILENAME', 'destination_type' => 's3', 'path' => '', 'filename' => $filename, 'size_bytes' => 0];
        }

        try {
            $uploaded = tpBackupExternalizedS3Request($s3Config, 'PUT', $objectKey, ['source_file' => $localFilePath, 'content_type' => 'application/octet-stream']);
            $uploadStatus = (int) $uploaded['status'];
            if ($uploadStatus < 200 || $uploadStatus >= 300) {
                return ['success' => false, 'reason' => tpBackupExternalizedS3ReasonFromStatus($uploadStatus, 'S3_UPLOAD_FAILED'), 'destination_type' => 's3', 'path' => $objectKey, 'filename' => $filename, 'size_bytes' => 0];
            }

            $metadataPath = tpGetBackupMetadataPath($localFilePath);
            if (is_file($metadataPath) === true) {
                $metaUploaded = tpBackupExternalizedS3Request($s3Config, 'PUT', $objectKey . '.meta.json', ['source_file' => $metadataPath, 'content_type' => 'application/json']);
                $metaStatus = (int) $metaUploaded['status'];
                if ($metaStatus < 200 || $metaStatus >= 300) {
                    return ['success' => false, 'reason' => tpBackupExternalizedS3ReasonFromStatus($metaStatus, 'S3_UPLOAD_FAILED'), 'destination_type' => 's3', 'path' => $objectKey . '.meta.json', 'filename' => $filename, 'size_bytes' => 0];
                }
            }

            return ['success' => true, 'reason' => '', 'destination_type' => 's3', 'path' => $objectKey, 'filename' => $filename, 'size_bytes' => (int) (@filesize($localFilePath) ?: 0)];
        } catch (Throwable) {
            return ['success' => false, 'reason' => 'S3_UPLOAD_FAILED', 'destination_type' => 's3', 'path' => $objectKey, 'filename' => $filename, 'size_bytes' => 0];
        }
    }

    if ($destinationType !== 'sftp') {
        return ['success' => false, 'reason' => 'UNSUPPORTED_TYPE', 'destination_type' => $destinationType, 'path' => '', 'filename' => $filename, 'size_bytes' => 0];
    }

    $sftpConfig['remote_path'] = $targetDir;
    $connection = tpBackupOpenExternalizedSftpConnection($sftpConfig);
    if ($connection['success'] === false) {
        return ['success' => false, 'reason' => (string) $connection['reason'], 'destination_type' => 'sftp', 'path' => (string) $connection['path'], 'filename' => $filename, 'size_bytes' => 0];
    }

    $remoteFilePath = tpBackupExternalizedSftpRemoteFilePath((string) $connection['path'], $filename);
    if ($remoteFilePath === '') {
        return ['success' => false, 'reason' => 'INVALID_FILENAME', 'destination_type' => 'sftp', 'path' => '', 'filename' => $filename, 'size_bytes' => 0];
    }

    try {
        $sftp = $connection['client'];
        $uploaded = $sftp->put($remoteFilePath, $localFilePath, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE);
        if ($uploaded !== true) {
            return ['success' => false, 'reason' => 'SFTP_UPLOAD_FAILED', 'destination_type' => 'sftp', 'path' => $remoteFilePath, 'filename' => $filename, 'size_bytes' => 0];
        }

        $metadataPath = tpGetBackupMetadataPath($localFilePath);
        if (is_file($metadataPath) === true) {
            $metaUploaded = $sftp->put($remoteFilePath . '.meta.json', $metadataPath, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE);
            if ($metaUploaded !== true) {
                return ['success' => false, 'reason' => 'SFTP_UPLOAD_FAILED', 'destination_type' => 'sftp', 'path' => $remoteFilePath . '.meta.json', 'filename' => $filename, 'size_bytes' => 0];
            }
        }

        return ['success' => true, 'reason' => '', 'destination_type' => 'sftp', 'path' => $remoteFilePath, 'filename' => $filename, 'size_bytes' => (int) (@filesize($localFilePath) ?: 0)];
    } catch (Throwable) {
        return ['success' => false, 'reason' => 'SFTP_UPLOAD_FAILED', 'destination_type' => 'sftp', 'path' => $remoteFilePath, 'filename' => $filename, 'size_bytes' => 0];
    }
}

/**
 * Tell whether a filename is a TeamPass externalized backup file.
 * Used as a guard so retention and delete only ever touch TeamPass backups.
 *
 * @return bool
 */
function tpBackupExternalizedBackupFilenameIsAllowed(string $filename): bool
{
    $filename = trim(str_replace("\0", '', str_replace('\\', '/', $filename)));
    if ($filename === '' || basename($filename) !== $filename) {
        return false;
    }

    $extension = (string) pathinfo($filename, PATHINFO_EXTENSION);

    return strpos($filename, 'externalized-') === 0
        && tpBackupFileExtensionIsSupported($extension) === true;
}

/**
 * Resolve one backup file from the configured externalized destination.
 *
 * @return array{success: bool, reason: string, destination_type: string, root_path: string, path: string, filename: string, size_bytes: int, mtime: int, metadata?: array<string, mixed>, remote?: bool}
 */
function tpBackupResolveExternalizedBackupFile(string $destinationType, string $targetDir, string $filename, array $SETTINGS = [], array $sftpConfig = []): array
{
    $filename = trim(str_replace("\0", '', str_replace('\\', '/', $filename)));
    $destinationType = tpBackupNormalizeExternalizedDestinationType($destinationType);

    if (tpBackupExternalizedBackupFilenameIsAllowed($filename) === false) {
        return [
            'success' => false,
            'reason' => 'INVALID_FILENAME',
            'destination_type' => $destinationType,
            'root_path' => '',
            'path' => '',
            'filename' => $filename,
            'size_bytes' => 0,
            'mtime' => 0,
        ];
    }

    if ($destinationType === 'sftp') {
        $sftpConfig['remote_path'] = $targetDir;
        $connection = tpBackupOpenExternalizedSftpConnection($sftpConfig);
        if ($connection['success'] === false) {
            return [
                'success' => false,
                'reason' => (string) $connection['reason'],
                'destination_type' => 'sftp',
                'root_path' => (string) $connection['path'],
                'path' => '',
                'filename' => $filename,
                'size_bytes' => 0,
                'mtime' => 0,
            ];
        }

        $rootPath = (string) $connection['path'];
        $remoteFilePath = tpBackupExternalizedSftpRemoteFilePath($rootPath, $filename);
        if ($remoteFilePath === '') {
            return [
                'success' => false,
                'reason' => 'INVALID_FILENAME',
                'destination_type' => 'sftp',
                'root_path' => $rootPath,
                'path' => '',
                'filename' => $filename,
                'size_bytes' => 0,
                'mtime' => 0,
            ];
        }

        try {
            $sftp = $connection['client'];
            $stat = $sftp->stat($remoteFilePath);
            $directoryType = defined('NET_SFTP_TYPE_DIRECTORY') ? constant('NET_SFTP_TYPE_DIRECTORY') : 2;
            if (is_array($stat) === false || (isset($stat['type']) && (int) $stat['type'] === (int) $directoryType)) {
                return [
                    'success' => false,
                    'reason' => 'NOT_FOUND',
                    'destination_type' => 'sftp',
                    'root_path' => $rootPath,
                    'path' => '',
                    'filename' => $filename,
                    'size_bytes' => 0,
                    'mtime' => 0,
                ];
            }

            return [
                'success' => true,
                'reason' => '',
                'destination_type' => 'sftp',
                'root_path' => $rootPath,
                'path' => $remoteFilePath,
                'filename' => $filename,
                'size_bytes' => (int) ($stat['size'] ?? 0),
                'mtime' => (int) ($stat['mtime'] ?? 0),
                'metadata' => tpBackupReadExternalizedSftpMetadata($sftp, $remoteFilePath),
                'remote' => true,
            ];
        } catch (Throwable) {
            return [
                'success' => false,
                'reason' => 'SFTP_CONNECTION_FAILED',
                'destination_type' => 'sftp',
                'root_path' => $rootPath,
                'path' => '',
                'filename' => $filename,
                'size_bytes' => 0,
                'mtime' => 0,
            ];
        }
    }

    if ($destinationType === 'webdav') {
        $sftpConfig['remote_path'] = $targetDir;
        $connection = tpBackupOpenExternalizedWebdavConnection($sftpConfig);
        if ($connection['success'] === false) {
            return [
                'success' => false,
                'reason' => (string) $connection['reason'],
                'destination_type' => 'webdav',
                'root_path' => (string) $connection['path'],
                'path' => '',
                'filename' => $filename,
                'size_bytes' => 0,
                'mtime' => 0,
            ];
        }

        $rootPath = (string) $connection['path'];
        $remoteFilePath = tpBackupExternalizedWebdavRemoteFilePath($rootPath, $filename);
        if ($remoteFilePath === '') {
            return [
                'success' => false,
                'reason' => 'INVALID_FILENAME',
                'destination_type' => 'webdav',
                'root_path' => $rootPath,
                'path' => '',
                'filename' => $filename,
                'size_bytes' => 0,
                'mtime' => 0,
            ];
        }

        try {
            $client = $connection['client'];
            $webdavConfig = (array) $connection['config'];
            $stat = tpBackupExternalizedWebdavStat($client, $webdavConfig, $remoteFilePath);
            if ($stat === null || !empty($stat['is_dir'])) {
                return [
                    'success' => false,
                    'reason' => 'NOT_FOUND',
                    'destination_type' => 'webdav',
                    'root_path' => $rootPath,
                    'path' => '',
                    'filename' => $filename,
                    'size_bytes' => 0,
                    'mtime' => 0,
                ];
            }

            return [
                'success' => true,
                'reason' => '',
                'destination_type' => 'webdav',
                'root_path' => $rootPath,
                'path' => $remoteFilePath,
                'filename' => $filename,
                'size_bytes' => (int) $stat['size_bytes'],
                'mtime' => (int) $stat['mtime'],
                'metadata' => tpBackupReadExternalizedWebdavMetadata($client, $webdavConfig, $remoteFilePath),
                'remote' => true,
            ];
        } catch (Throwable $e) {
            $reason = in_array($e->getMessage(), ['WEBDAV_AUTH_FAILED', 'WEBDAV_PATH_NOT_FOUND', 'WEBDAV_CONNECTION_FAILED'], true) === true
                ? $e->getMessage()
                : 'WEBDAV_CONNECTION_FAILED';

            return [
                'success' => false,
                'reason' => $reason,
                'destination_type' => 'webdav',
                'root_path' => $rootPath,
                'path' => '',
                'filename' => $filename,
                'size_bytes' => 0,
                'mtime' => 0,
            ];
        }
    }

    if ($destinationType === 's3') {
        $sftpConfig['prefix'] = $targetDir;
        $connection = tpBackupOpenExternalizedS3Connection($sftpConfig);
        if ($connection['success'] === false) {
            return [
                'success' => false,
                'reason' => (string) $connection['reason'],
                'destination_type' => 's3',
                'root_path' => (string) $connection['path'],
                'path' => '',
                'filename' => $filename,
                'size_bytes' => 0,
                'mtime' => 0,
            ];
        }

        $prefix = (string) $connection['path'];
        $objectKey = tpBackupExternalizedS3ObjectKey($prefix, $filename);
        if ($objectKey === '') {
            return [
                'success' => false,
                'reason' => 'INVALID_FILENAME',
                'destination_type' => 's3',
                'root_path' => $prefix,
                'path' => '',
                'filename' => $filename,
                'size_bytes' => 0,
                'mtime' => 0,
            ];
        }

        try {
            $s3Config = (array) $connection['config'];
            $stat = tpBackupExternalizedS3Stat($s3Config, $objectKey);
            if ($stat === null) {
                return [
                    'success' => false,
                    'reason' => 'NOT_FOUND',
                    'destination_type' => 's3',
                    'root_path' => $prefix,
                    'path' => '',
                    'filename' => $filename,
                    'size_bytes' => 0,
                    'mtime' => 0,
                ];
            }

            return [
                'success' => true,
                'reason' => '',
                'destination_type' => 's3',
                'root_path' => $prefix,
                'path' => $objectKey,
                'filename' => $filename,
                'size_bytes' => (int) $stat['size_bytes'],
                'mtime' => (int) $stat['mtime'],
                'metadata' => tpBackupReadExternalizedS3Metadata($s3Config, $objectKey),
                'remote' => true,
            ];
        } catch (Throwable $e) {
            $reason = in_array($e->getMessage(), ['S3_AUTH_FAILED', 'S3_NOT_FOUND', 'S3_LIST_FAILED'], true) === true
                ? $e->getMessage()
                : 'S3_CONNECTION_FAILED';

            return [
                'success' => false,
                'reason' => $reason,
                'destination_type' => 's3',
                'root_path' => $prefix,
                'path' => '',
                'filename' => $filename,
                'size_bytes' => 0,
                'mtime' => 0,
            ];
        }
    }

    $resolved = tpBackupResolveExternalizedDestination($destinationType, $targetDir, $SETTINGS);
    if ($resolved['success'] === false) {
        return [
            'success' => false,
            'reason' => (string) $resolved['reason'],
            'destination_type' => (string) $resolved['destination_type'],
            'root_path' => '',
            'path' => '',
            'filename' => $filename,
            'size_bytes' => 0,
            'mtime' => 0,
        ];
    }

    $rootPath = (string) $resolved['path'];
    $filePath = rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . $filename;
    $fileReal = realpath($filePath);
    if (
        $fileReal === false
        || tpBackupIsResolvedPathInsideDirectory($fileReal, $rootPath) === false
        || is_file($fileReal) === false
    ) {
        return [
            'success' => false,
            'reason' => 'NOT_FOUND',
            'destination_type' => (string) $resolved['destination_type'],
            'root_path' => $rootPath,
            'path' => '',
            'filename' => $filename,
            'size_bytes' => 0,
            'mtime' => 0,
        ];
    }

    return [
        'success' => true,
        'reason' => '',
        'destination_type' => (string) $resolved['destination_type'],
        'root_path' => $rootPath,
        'path' => $fileReal,
        'filename' => $filename,
        'size_bytes' => (int) (@filesize($fileReal) ?: 0),
        'mtime' => (int) (@filemtime($fileReal) ?: 0),
    ];
}

/**
 * List externalized backup files through the destination abstraction.
 *
 * @return array{success: bool, reason: string, destination_type: string, path: string, files: array<int, array{name: string, path: string, size_bytes: int, mtime: int, metadata?: array<string, mixed>, remote?: bool}>}
 */
function tpBackupListExternalizedBackups(string $destinationType, string $targetDir, array $SETTINGS = [], array $sftpConfig = []): array
{
    $destinationType = tpBackupNormalizeExternalizedDestinationType($destinationType);

    if ($destinationType === 's3') {
        $sftpConfig['prefix'] = $targetDir;
        $connection = tpBackupOpenExternalizedS3Connection($sftpConfig);
        if ($connection['success'] === false) {
            return [
                'success' => false,
                'reason' => (string) $connection['reason'],
                'destination_type' => 's3',
                'path' => (string) $connection['path'],
                'files' => [],
            ];
        }

        $prefix = (string) $connection['path'];
        try {
            $s3Config = (array) $connection['config'];
            $listPrefix = $prefix === '' ? 'externalized-' : $prefix . '/externalized-';
            $objects = tpBackupExternalizedS3ListObjects($s3Config, $listPrefix);
            $files = [];
            foreach ($objects as $object) {
                $objectKey = (string) $object['key'];
                $filename = basename(str_replace('\\', '/', $objectKey));
                if (tpBackupExternalizedBackupFilenameIsAllowed($filename) === false) {
                    continue;
                }
                if (str_ends_with($objectKey, '.meta.json') === true) {
                    continue;
                }

                $files[] = [
                    'name' => $filename,
                    'path' => $objectKey,
                    'size_bytes' => (int) $object['size_bytes'],
                    'mtime' => (int) $object['mtime'],
                    'metadata' => tpBackupReadExternalizedS3Metadata($s3Config, $objectKey),
                    'remote' => true,
                ];
            }

            usort($files, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);

            return [
                'success' => true,
                'reason' => '',
                'destination_type' => 's3',
                'path' => $prefix,
                'files' => $files,
            ];
        } catch (Throwable $e) {
            $reason = in_array($e->getMessage(), ['S3_AUTH_FAILED', 'S3_NOT_FOUND', 'S3_LIST_FAILED'], true) === true
                ? $e->getMessage()
                : 'S3_CONNECTION_FAILED';

            return [
                'success' => false,
                'reason' => $reason,
                'destination_type' => 's3',
                'path' => $prefix,
                'files' => [],
            ];
        }
    }

    if ($destinationType === 'sftp') {
        $sftpConfig['remote_path'] = $targetDir;
        $connection = tpBackupOpenExternalizedSftpConnection($sftpConfig);
        if ($connection['success'] === false) {
            return [
                'success' => false,
                'reason' => (string) $connection['reason'],
                'destination_type' => 'sftp',
                'path' => (string) $connection['path'],
                'files' => [],
            ];
        }

        $rootPath = (string) $connection['path'];
        try {
            $sftp = $connection['client'];
            $rawList = $sftp->rawlist($rootPath);
            if (is_array($rawList) === false) {
                return [
                    'success' => false,
                    'reason' => 'SFTP_PATH_NOT_FOUND',
                    'destination_type' => 'sftp',
                    'path' => $rootPath,
                    'files' => [],
                ];
            }

            $directoryType = defined('NET_SFTP_TYPE_DIRECTORY') ? constant('NET_SFTP_TYPE_DIRECTORY') : 2;
            $files = [];
            foreach ($rawList as $entryName => $entry) {
                $filename = is_array($entry) && isset($entry['filename']) && is_scalar($entry['filename'])
                    ? (string) $entry['filename']
                    : (string) $entryName;
                $filename = basename(str_replace('\\', '/', $filename));
                if ($filename === '.' || $filename === '..' || tpBackupExternalizedBackupFilenameIsAllowed($filename) === false) {
                    continue;
                }
                if (is_array($entry) && isset($entry['type']) && (int) $entry['type'] === (int) $directoryType) {
                    continue;
                }

                $remoteFilePath = tpBackupExternalizedSftpRemoteFilePath($rootPath, $filename);
                if ($remoteFilePath === '') {
                    continue;
                }

                $files[] = [
                    'name' => $filename,
                    'path' => $remoteFilePath,
                    'size_bytes' => is_array($entry) ? (int) ($entry['size'] ?? 0) : 0,
                    'mtime' => is_array($entry) ? (int) ($entry['mtime'] ?? 0) : 0,
                    'metadata' => tpBackupReadExternalizedSftpMetadata($sftp, $remoteFilePath),
                    'remote' => true,
                ];
            }

            usort($files, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);

            return [
                'success' => true,
                'reason' => '',
                'destination_type' => 'sftp',
                'path' => $rootPath,
                'files' => $files,
            ];
        } catch (Throwable) {
            return [
                'success' => false,
                'reason' => 'SFTP_CONNECTION_FAILED',
                'destination_type' => 'sftp',
                'path' => $rootPath,
                'files' => [],
            ];
        }
    }

    if ($destinationType === 'webdav') {
        $sftpConfig['remote_path'] = $targetDir;
        $connection = tpBackupOpenExternalizedWebdavConnection($sftpConfig);
        if ($connection['success'] === false) {
            return [
                'success' => false,
                'reason' => (string) $connection['reason'],
                'destination_type' => 'webdav',
                'path' => (string) $connection['path'],
                'files' => [],
            ];
        }

        $rootPath = (string) $connection['path'];
        try {
            $client = $connection['client'];
            $webdavConfig = (array) $connection['config'];
            $rawList = tpBackupExternalizedWebdavPropfind($client, $webdavConfig, $rootPath, 1);
            $files = [];
            foreach ($rawList as $entry) {
                $filename = basename(str_replace('\\', '/', (string) $entry['name']));
                if ($filename === '' || !empty($entry['is_dir']) || tpBackupExternalizedBackupFilenameIsAllowed($filename) === false) {
                    continue;
                }

                $remoteFilePath = tpBackupExternalizedWebdavRemoteFilePath($rootPath, $filename);
                if ($remoteFilePath === '') {
                    continue;
                }

                $files[] = [
                    'name' => $filename,
                    'path' => $remoteFilePath,
                    'size_bytes' => (int) $entry['size_bytes'],
                    'mtime' => (int) $entry['mtime'],
                    'metadata' => tpBackupReadExternalizedWebdavMetadata($client, $webdavConfig, $remoteFilePath),
                    'remote' => true,
                ];
            }

            usort($files, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);

            return [
                'success' => true,
                'reason' => '',
                'destination_type' => 'webdav',
                'path' => $rootPath,
                'files' => $files,
            ];
        } catch (Throwable $e) {
            $reason = in_array($e->getMessage(), ['WEBDAV_AUTH_FAILED', 'WEBDAV_PATH_NOT_FOUND', 'WEBDAV_CONNECTION_FAILED'], true) === true
                ? $e->getMessage()
                : 'WEBDAV_CONNECTION_FAILED';

            return [
                'success' => false,
                'reason' => $reason,
                'destination_type' => 'webdav',
                'path' => $rootPath,
                'files' => [],
            ];
        }
    }

    $resolved = tpBackupResolveExternalizedDestination($destinationType, $targetDir, $SETTINGS);
    if ($resolved['success'] === false) {
        return [
            'success' => false,
            'reason' => (string) $resolved['reason'],
            'destination_type' => (string) $resolved['destination_type'],
            'path' => '',
            'files' => [],
        ];
    }

    $rootPath = (string) $resolved['path'];
    $globDir = rtrim(str_replace('\\', '/', $rootPath), '/');
    $paths = array_values(array_unique(array_merge(
        glob($globDir . '/externalized-*.sql') ?: [],
        glob($globDir . '/externalized-*.' . tpBackupGetPackageExtension()) ?: []
    )));

    $files = [];
    foreach ($paths as $filePath) {
        $filename = basename((string) $filePath);
        if (tpBackupExternalizedBackupFilenameIsAllowed($filename) === false) {
            continue;
        }

        $fileReal = realpath((string) $filePath);
        if (
            $fileReal === false
            || tpBackupIsResolvedPathInsideDirectory($fileReal, $rootPath) === false
            || is_file($fileReal) === false
        ) {
            continue;
        }

        $files[] = [
            'name' => $filename,
            'path' => $fileReal,
            'size_bytes' => (int) (@filesize($fileReal) ?: 0),
            'mtime' => (int) (@filemtime($fileReal) ?: 0),
        ];
    }

    usort($files, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);

    return [
        'success' => true,
        'reason' => '',
        'destination_type' => (string) $resolved['destination_type'],
        'path' => $rootPath,
        'files' => $files,
    ];
}

/**
 * Delete an externalized backup file and its metadata sidecar when present.
 *
 * @return array{success: bool, reason: string, deleted: bool, path: string}
 */
function tpBackupDeleteExternalizedBackup(string $destinationType, string $targetDir, string $filename, array $SETTINGS = [], array $sftpConfig = []): array
{
    $filename = trim(str_replace("\0", '', str_replace('\\', '/', $filename)));
    if (tpBackupExternalizedBackupFilenameIsAllowed($filename) === false) {
        return ['success' => false, 'reason' => 'INVALID_FILENAME', 'deleted' => false, 'path' => ''];
    }

    $destinationType = tpBackupNormalizeExternalizedDestinationType($destinationType);
    if ($destinationType === 'sftp') {
        $sftpConfig['remote_path'] = $targetDir;
        $connection = tpBackupOpenExternalizedSftpConnection($sftpConfig);
        if ($connection['success'] === false) {
            return ['success' => false, 'reason' => (string) $connection['reason'], 'deleted' => false, 'path' => (string) $connection['path']];
        }

        $remoteFilePath = tpBackupExternalizedSftpRemoteFilePath((string) $connection['path'], $filename);
        if ($remoteFilePath === '') {
            return ['success' => false, 'reason' => 'INVALID_FILENAME', 'deleted' => false, 'path' => ''];
        }

        try {
            $sftp = $connection['client'];
            $stat = $sftp->stat($remoteFilePath);
            $directoryType = defined('NET_SFTP_TYPE_DIRECTORY') ? constant('NET_SFTP_TYPE_DIRECTORY') : 2;
            if (is_array($stat) === false || (isset($stat['type']) && (int) $stat['type'] === (int) $directoryType)) {
                return ['success' => true, 'reason' => '', 'deleted' => false, 'path' => ''];
            }

            if ($sftp->delete($remoteFilePath, false) !== true) {
                return ['success' => false, 'reason' => 'SFTP_DELETE_FAILED', 'deleted' => false, 'path' => $remoteFilePath];
            }
            $sftp->delete($remoteFilePath . '.meta.json', false);

            return ['success' => true, 'reason' => '', 'deleted' => true, 'path' => $remoteFilePath];
        } catch (Throwable) {
            return ['success' => false, 'reason' => 'SFTP_DELETE_FAILED', 'deleted' => false, 'path' => $remoteFilePath];
        }
    }

    if ($destinationType === 'webdav') {
        $sftpConfig['remote_path'] = $targetDir;
        $connection = tpBackupOpenExternalizedWebdavConnection($sftpConfig);
        if ($connection['success'] === false) {
            return ['success' => false, 'reason' => (string) $connection['reason'], 'deleted' => false, 'path' => (string) $connection['path']];
        }

        $remoteFilePath = tpBackupExternalizedWebdavRemoteFilePath((string) $connection['path'], $filename);
        if ($remoteFilePath === '') {
            return ['success' => false, 'reason' => 'INVALID_FILENAME', 'deleted' => false, 'path' => ''];
        }

        try {
            $client = $connection['client'];
            $webdavConfig = (array) $connection['config'];
            $stat = tpBackupExternalizedWebdavStat($client, $webdavConfig, $remoteFilePath);
            if ($stat === null || !empty($stat['is_dir'])) {
                return ['success' => true, 'reason' => '', 'deleted' => false, 'path' => ''];
            }

            $deleted = tpBackupExternalizedWebdavRequest($client, $webdavConfig, 'DELETE', $remoteFilePath);
            $deleteStatus = (int) $deleted->getStatusCode();
            if ($deleteStatus !== 404 && ($deleteStatus < 200 || $deleteStatus >= 300)) {
                return ['success' => false, 'reason' => tpBackupExternalizedWebdavReasonFromStatus($deleteStatus, 'WEBDAV_DELETE_FAILED'), 'deleted' => false, 'path' => $remoteFilePath];
            }

            try {
                tpBackupExternalizedWebdavRequest($client, $webdavConfig, 'DELETE', $remoteFilePath . '.meta.json');
            } catch (Throwable) {
                // Sidecar cleanup is best effort; the backup file itself was deleted.
            }

            return ['success' => true, 'reason' => '', 'deleted' => true, 'path' => $remoteFilePath];
        } catch (Throwable) {
            return ['success' => false, 'reason' => 'WEBDAV_DELETE_FAILED', 'deleted' => false, 'path' => $remoteFilePath];
        }
    }

    if ($destinationType === 's3') {
        $sftpConfig['prefix'] = $targetDir;
        $connection = tpBackupOpenExternalizedS3Connection($sftpConfig);
        if ($connection['success'] === false) {
            return ['success' => false, 'reason' => (string) $connection['reason'], 'deleted' => false, 'path' => (string) $connection['path']];
        }

        $objectKey = tpBackupExternalizedS3ObjectKey((string) $connection['path'], $filename);
        if ($objectKey === '') {
            return ['success' => false, 'reason' => 'INVALID_FILENAME', 'deleted' => false, 'path' => ''];
        }

        try {
            $s3Config = (array) $connection['config'];
            $stat = tpBackupExternalizedS3Stat($s3Config, $objectKey);
            if ($stat === null) {
                return ['success' => true, 'reason' => '', 'deleted' => false, 'path' => ''];
            }

            $deleted = tpBackupExternalizedS3Request($s3Config, 'DELETE', $objectKey);
            $deleteStatus = (int) $deleted['status'];
            if ($deleteStatus !== 404 && ($deleteStatus < 200 || $deleteStatus >= 300)) {
                return ['success' => false, 'reason' => tpBackupExternalizedS3ReasonFromStatus($deleteStatus, 'S3_DELETE_FAILED'), 'deleted' => false, 'path' => $objectKey];
            }

            try {
                tpBackupExternalizedS3Request($s3Config, 'DELETE', $objectKey . '.meta.json');
            } catch (Throwable) {
                // Sidecar cleanup is best effort; the backup object itself was deleted.
            }

            return ['success' => true, 'reason' => '', 'deleted' => true, 'path' => $objectKey];
        } catch (Throwable) {
            return ['success' => false, 'reason' => 'S3_DELETE_FAILED', 'deleted' => false, 'path' => $objectKey];
        }
    }

    $resolved = tpBackupResolveExternalizedDestination($destinationType, $targetDir, $SETTINGS);
    if ($resolved['success'] === false) {
        return ['success' => false, 'reason' => (string) $resolved['reason'], 'deleted' => false, 'path' => ''];
    }

    $rootPath = (string) $resolved['path'];
    $filePath = rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . $filename;
    $fileReal = realpath($filePath);
    if (
        $fileReal === false
        || tpBackupIsResolvedPathInsideDirectory($fileReal, $rootPath) === false
        || is_file($fileReal) === false
    ) {
        return ['success' => true, 'reason' => '', 'deleted' => false, 'path' => ''];
    }

    if (is_writable($fileReal) === false) {
        return ['success' => false, 'reason' => 'FILE_NOT_WRITABLE', 'deleted' => false, 'path' => $fileReal];
    }

    if (@unlink($fileReal) === false) {
        return ['success' => false, 'reason' => 'DELETE_FAILED', 'deleted' => false, 'path' => $fileReal];
    }

    $metaPath = tpGetBackupMetadataPath($fileReal);
    if (file_exists($metaPath) === true) {
        @unlink($metaPath);
    }

    return ['success' => true, 'reason' => '', 'deleted' => true, 'path' => $fileReal];
}

/**
 * Download an externalized backup file to a local path when the destination is remote.
 *
 * Local destinations return the resolved file path directly and ignore $localTargetPath.
 *
 * @return array{success: bool, reason: string, destination_type: string, path: string, filename: string, size_bytes: int}
 */
function tpBackupDownloadExternalizedBackup(string $destinationType, string $targetDir, string $filename, string $localTargetPath, array $SETTINGS = [], array $sftpConfig = []): array
{
    $filename = trim(str_replace("\0", '', str_replace('\\', '/', $filename)));
    $destinationType = tpBackupNormalizeExternalizedDestinationType($destinationType);
    if (tpBackupExternalizedBackupFilenameIsAllowed($filename) === false) {
        return ['success' => false, 'reason' => 'INVALID_FILENAME', 'destination_type' => $destinationType, 'path' => '', 'filename' => $filename, 'size_bytes' => 0];
    }

    if ($destinationType === 'local_directory') {
        $resolved = tpBackupResolveExternalizedBackupFile($destinationType, $targetDir, $filename, $SETTINGS);
        if ($resolved['success'] === false) {
            return ['success' => false, 'reason' => (string) $resolved['reason'], 'destination_type' => $destinationType, 'path' => '', 'filename' => $filename, 'size_bytes' => 0];
        }

        return ['success' => true, 'reason' => '', 'destination_type' => $destinationType, 'path' => (string) $resolved['path'], 'filename' => $filename, 'size_bytes' => (int) $resolved['size_bytes']];
    }

    if ($destinationType === 'webdav') {
        $targetParent = dirname($localTargetPath);
        if ($localTargetPath === '' || is_dir($targetParent) === false || is_writable($targetParent) === false) {
            return ['success' => false, 'reason' => 'WEBDAV_DOWNLOAD_FAILED', 'destination_type' => 'webdav', 'path' => '', 'filename' => $filename, 'size_bytes' => 0];
        }

        $sftpConfig['remote_path'] = $targetDir;
        $connection = tpBackupOpenExternalizedWebdavConnection($sftpConfig);
        if ($connection['success'] === false) {
            return ['success' => false, 'reason' => (string) $connection['reason'], 'destination_type' => 'webdav', 'path' => (string) $connection['path'], 'filename' => $filename, 'size_bytes' => 0];
        }

        $remoteFilePath = tpBackupExternalizedWebdavRemoteFilePath((string) $connection['path'], $filename);
        if ($remoteFilePath === '') {
            return ['success' => false, 'reason' => 'INVALID_FILENAME', 'destination_type' => 'webdav', 'path' => '', 'filename' => $filename, 'size_bytes' => 0];
        }

        try {
            $downloaded = tpBackupExternalizedWebdavRequest(
                $connection['client'],
                (array) $connection['config'],
                'GET',
                $remoteFilePath,
                ['sink' => $localTargetPath]
            );
            $status = (int) $downloaded->getStatusCode();
            if ($status < 200 || $status >= 300 || is_file($localTargetPath) === false) {
                return ['success' => false, 'reason' => tpBackupExternalizedWebdavReasonFromStatus($status, 'WEBDAV_DOWNLOAD_FAILED'), 'destination_type' => 'webdav', 'path' => $remoteFilePath, 'filename' => $filename, 'size_bytes' => 0];
            }

            return ['success' => true, 'reason' => '', 'destination_type' => 'webdav', 'path' => $localTargetPath, 'filename' => $filename, 'size_bytes' => (int) (@filesize($localTargetPath) ?: 0)];
        } catch (Throwable) {
            return ['success' => false, 'reason' => 'WEBDAV_DOWNLOAD_FAILED', 'destination_type' => 'webdav', 'path' => $remoteFilePath, 'filename' => $filename, 'size_bytes' => 0];
        }
    }

    if ($destinationType === 's3') {
        $targetParent = dirname($localTargetPath);
        if ($localTargetPath === '' || is_dir($targetParent) === false || is_writable($targetParent) === false) {
            return ['success' => false, 'reason' => 'S3_DOWNLOAD_FAILED', 'destination_type' => 's3', 'path' => '', 'filename' => $filename, 'size_bytes' => 0];
        }

        $sftpConfig['prefix'] = $targetDir;
        $connection = tpBackupOpenExternalizedS3Connection($sftpConfig);
        if ($connection['success'] === false) {
            return ['success' => false, 'reason' => (string) $connection['reason'], 'destination_type' => 's3', 'path' => (string) $connection['path'], 'filename' => $filename, 'size_bytes' => 0];
        }

        $objectKey = tpBackupExternalizedS3ObjectKey((string) $connection['path'], $filename);
        if ($objectKey === '') {
            return ['success' => false, 'reason' => 'INVALID_FILENAME', 'destination_type' => 's3', 'path' => '', 'filename' => $filename, 'size_bytes' => 0];
        }

        try {
            $downloaded = tpBackupExternalizedS3Request((array) $connection['config'], 'GET', $objectKey, ['sink' => $localTargetPath]);
            $status = (int) $downloaded['status'];
            if ($status < 200 || $status >= 300 || is_file($localTargetPath) === false) {
                return ['success' => false, 'reason' => tpBackupExternalizedS3ReasonFromStatus($status, 'S3_DOWNLOAD_FAILED'), 'destination_type' => 's3', 'path' => $objectKey, 'filename' => $filename, 'size_bytes' => 0];
            }

            return ['success' => true, 'reason' => '', 'destination_type' => 's3', 'path' => $localTargetPath, 'filename' => $filename, 'size_bytes' => (int) (@filesize($localTargetPath) ?: 0)];
        } catch (Throwable) {
            return ['success' => false, 'reason' => 'S3_DOWNLOAD_FAILED', 'destination_type' => 's3', 'path' => $objectKey, 'filename' => $filename, 'size_bytes' => 0];
        }
    }

    if ($destinationType !== 'sftp') {
        return ['success' => false, 'reason' => 'UNSUPPORTED_TYPE', 'destination_type' => $destinationType, 'path' => '', 'filename' => $filename, 'size_bytes' => 0];
    }

    $targetParent = dirname($localTargetPath);
    if ($localTargetPath === '' || is_dir($targetParent) === false || is_writable($targetParent) === false) {
        return ['success' => false, 'reason' => 'SFTP_DOWNLOAD_FAILED', 'destination_type' => 'sftp', 'path' => '', 'filename' => $filename, 'size_bytes' => 0];
    }

    $sftpConfig['remote_path'] = $targetDir;
    $connection = tpBackupOpenExternalizedSftpConnection($sftpConfig);
    if ($connection['success'] === false) {
        return ['success' => false, 'reason' => (string) $connection['reason'], 'destination_type' => 'sftp', 'path' => (string) $connection['path'], 'filename' => $filename, 'size_bytes' => 0];
    }

    $remoteFilePath = tpBackupExternalizedSftpRemoteFilePath((string) $connection['path'], $filename);
    if ($remoteFilePath === '') {
        return ['success' => false, 'reason' => 'INVALID_FILENAME', 'destination_type' => 'sftp', 'path' => '', 'filename' => $filename, 'size_bytes' => 0];
    }

    try {
        $sftp = $connection['client'];
        if ($sftp->get($remoteFilePath, $localTargetPath) !== true) {
            return ['success' => false, 'reason' => 'SFTP_DOWNLOAD_FAILED', 'destination_type' => 'sftp', 'path' => $remoteFilePath, 'filename' => $filename, 'size_bytes' => 0];
        }

        return ['success' => true, 'reason' => '', 'destination_type' => 'sftp', 'path' => $localTargetPath, 'filename' => $filename, 'size_bytes' => (int) (@filesize($localTargetPath) ?: 0)];
    } catch (Throwable) {
        return ['success' => false, 'reason' => 'SFTP_DOWNLOAD_FAILED', 'destination_type' => 'sftp', 'path' => $remoteFilePath, 'filename' => $filename, 'size_bytes' => 0];
    }
}

/**
 * Stage a backup from an externalized destination so the existing CLI restore
 * flow can operate on a local file path.
 *
 * Local destinations simply resolve the existing file and require no cleanup.
 * Remote destinations create a temporary directory and may copy sidecar metadata.
 *
 * @return array{success: bool, reason: string, destination_type: string, path: string, filename: string, cleanup_required: bool, cleanup_dir: string, source_path: string, size_bytes: int}
 */
function tpBackupStageExternalizedBackupForRestore(string $destinationType, string $targetDir, string $filename, array $SETTINGS = [], array $sftpConfig = []): array
{
    $filename = trim(str_replace("\0", '', str_replace('\\', '/', $filename)));
    $destinationType = tpBackupNormalizeExternalizedDestinationType($destinationType);
    if (tpBackupExternalizedBackupFilenameIsAllowed($filename) === false) {
        return ['success' => false, 'reason' => 'INVALID_FILENAME', 'destination_type' => $destinationType, 'path' => '', 'filename' => $filename, 'cleanup_required' => false, 'cleanup_dir' => '', 'source_path' => '', 'size_bytes' => 0];
    }

    if ($destinationType === 'local_directory') {
        $resolved = tpBackupResolveExternalizedBackupFile($destinationType, $targetDir, $filename, $SETTINGS);
        if ($resolved['success'] === false) {
            return ['success' => false, 'reason' => (string) $resolved['reason'], 'destination_type' => $destinationType, 'path' => '', 'filename' => $filename, 'cleanup_required' => false, 'cleanup_dir' => '', 'source_path' => '', 'size_bytes' => 0];
        }

        return ['success' => true, 'reason' => '', 'destination_type' => $destinationType, 'path' => (string) $resolved['path'], 'filename' => $filename, 'cleanup_required' => false, 'cleanup_dir' => '', 'source_path' => (string) $resolved['path'], 'size_bytes' => (int) $resolved['size_bytes']];
    }

    if ($destinationType === 'webdav') {
        $cleanupDir = tpBackupCreateTemporaryDirectory((string) sys_get_temp_dir());
        if ($cleanupDir === '') {
            return ['success' => false, 'reason' => 'WEBDAV_DOWNLOAD_FAILED', 'destination_type' => 'webdav', 'path' => '', 'filename' => $filename, 'cleanup_required' => false, 'cleanup_dir' => '', 'source_path' => '', 'size_bytes' => 0];
        }

        $localPath = $cleanupDir . DIRECTORY_SEPARATOR . $filename;
        $sftpConfig['remote_path'] = $targetDir;
        $connection = tpBackupOpenExternalizedWebdavConnection($sftpConfig);
        if ($connection['success'] === false) {
            tpBackupRemoveTemporaryDirectory($cleanupDir);
            return ['success' => false, 'reason' => (string) $connection['reason'], 'destination_type' => 'webdav', 'path' => '', 'filename' => $filename, 'cleanup_required' => false, 'cleanup_dir' => '', 'source_path' => (string) $connection['path'], 'size_bytes' => 0];
        }

        $remoteFilePath = tpBackupExternalizedWebdavRemoteFilePath((string) $connection['path'], $filename);
        if ($remoteFilePath === '') {
            tpBackupRemoveTemporaryDirectory($cleanupDir);
            return ['success' => false, 'reason' => 'INVALID_FILENAME', 'destination_type' => 'webdav', 'path' => '', 'filename' => $filename, 'cleanup_required' => false, 'cleanup_dir' => '', 'source_path' => '', 'size_bytes' => 0];
        }

        try {
            $client = $connection['client'];
            $webdavConfig = (array) $connection['config'];
            $downloaded = tpBackupExternalizedWebdavRequest($client, $webdavConfig, 'GET', $remoteFilePath, ['sink' => $localPath]);
            $status = (int) $downloaded->getStatusCode();
            if ($status < 200 || $status >= 300 || is_file($localPath) === false) {
                tpBackupRemoveTemporaryDirectory($cleanupDir);
                return ['success' => false, 'reason' => tpBackupExternalizedWebdavReasonFromStatus($status, 'WEBDAV_DOWNLOAD_FAILED'), 'destination_type' => 'webdav', 'path' => '', 'filename' => $filename, 'cleanup_required' => false, 'cleanup_dir' => '', 'source_path' => $remoteFilePath, 'size_bytes' => 0];
            }

            $metadataResponse = tpBackupExternalizedWebdavRequest($client, $webdavConfig, 'GET', $remoteFilePath . '.meta.json');
            $metadataStatus = (int) $metadataResponse->getStatusCode();
            if ($metadataStatus >= 200 && $metadataStatus < 300) {
                $metadataPayload = (string) $metadataResponse->getBody();
                if (trim($metadataPayload) !== '') {
                    @file_put_contents(tpGetBackupMetadataPath($localPath), $metadataPayload, LOCK_EX);
                }
            }

            return ['success' => true, 'reason' => '', 'destination_type' => 'webdav', 'path' => $localPath, 'filename' => $filename, 'cleanup_required' => true, 'cleanup_dir' => $cleanupDir, 'source_path' => $remoteFilePath, 'size_bytes' => (int) (@filesize($localPath) ?: 0)];
        } catch (Throwable) {
            tpBackupRemoveTemporaryDirectory($cleanupDir);
            return ['success' => false, 'reason' => 'WEBDAV_DOWNLOAD_FAILED', 'destination_type' => 'webdav', 'path' => '', 'filename' => $filename, 'cleanup_required' => false, 'cleanup_dir' => '', 'source_path' => $remoteFilePath, 'size_bytes' => 0];
        }
    }

    if ($destinationType === 's3') {
        $cleanupDir = tpBackupCreateTemporaryDirectory((string) sys_get_temp_dir());
        if ($cleanupDir === '') {
            return ['success' => false, 'reason' => 'S3_DOWNLOAD_FAILED', 'destination_type' => 's3', 'path' => '', 'filename' => $filename, 'cleanup_required' => false, 'cleanup_dir' => '', 'source_path' => '', 'size_bytes' => 0];
        }

        $localPath = $cleanupDir . DIRECTORY_SEPARATOR . $filename;
        $sftpConfig['prefix'] = $targetDir;
        $connection = tpBackupOpenExternalizedS3Connection($sftpConfig);
        if ($connection['success'] === false) {
            tpBackupRemoveTemporaryDirectory($cleanupDir);
            return ['success' => false, 'reason' => (string) $connection['reason'], 'destination_type' => 's3', 'path' => '', 'filename' => $filename, 'cleanup_required' => false, 'cleanup_dir' => '', 'source_path' => (string) $connection['path'], 'size_bytes' => 0];
        }

        $objectKey = tpBackupExternalizedS3ObjectKey((string) $connection['path'], $filename);
        if ($objectKey === '') {
            tpBackupRemoveTemporaryDirectory($cleanupDir);
            return ['success' => false, 'reason' => 'INVALID_FILENAME', 'destination_type' => 's3', 'path' => '', 'filename' => $filename, 'cleanup_required' => false, 'cleanup_dir' => '', 'source_path' => '', 'size_bytes' => 0];
        }

        try {
            $s3Config = (array) $connection['config'];
            $downloaded = tpBackupExternalizedS3Request($s3Config, 'GET', $objectKey, ['sink' => $localPath]);
            $status = (int) $downloaded['status'];
            if ($status < 200 || $status >= 300 || is_file($localPath) === false) {
                tpBackupRemoveTemporaryDirectory($cleanupDir);
                return ['success' => false, 'reason' => tpBackupExternalizedS3ReasonFromStatus($status, 'S3_DOWNLOAD_FAILED'), 'destination_type' => 's3', 'path' => '', 'filename' => $filename, 'cleanup_required' => false, 'cleanup_dir' => '', 'source_path' => $objectKey, 'size_bytes' => 0];
            }

            $metadataResponse = tpBackupExternalizedS3Request($s3Config, 'GET', $objectKey . '.meta.json');
            $metadataStatus = (int) $metadataResponse['status'];
            if ($metadataStatus >= 200 && $metadataStatus < 300) {
                $metadataPayload = (string) $metadataResponse['body'];
                if (trim($metadataPayload) !== '') {
                    @file_put_contents(tpGetBackupMetadataPath($localPath), $metadataPayload, LOCK_EX);
                }
            }

            return ['success' => true, 'reason' => '', 'destination_type' => 's3', 'path' => $localPath, 'filename' => $filename, 'cleanup_required' => true, 'cleanup_dir' => $cleanupDir, 'source_path' => $objectKey, 'size_bytes' => (int) (@filesize($localPath) ?: 0)];
        } catch (Throwable) {
            tpBackupRemoveTemporaryDirectory($cleanupDir);
            return ['success' => false, 'reason' => 'S3_DOWNLOAD_FAILED', 'destination_type' => 's3', 'path' => '', 'filename' => $filename, 'cleanup_required' => false, 'cleanup_dir' => '', 'source_path' => $objectKey, 'size_bytes' => 0];
        }
    }

    if ($destinationType !== 'sftp') {
        return ['success' => false, 'reason' => 'UNSUPPORTED_TYPE', 'destination_type' => $destinationType, 'path' => '', 'filename' => $filename, 'cleanup_required' => false, 'cleanup_dir' => '', 'source_path' => '', 'size_bytes' => 0];
    }

    $cleanupDir = tpBackupCreateTemporaryDirectory((string) sys_get_temp_dir());
    if ($cleanupDir === '') {
        return ['success' => false, 'reason' => 'SFTP_DOWNLOAD_FAILED', 'destination_type' => 'sftp', 'path' => '', 'filename' => $filename, 'cleanup_required' => false, 'cleanup_dir' => '', 'source_path' => '', 'size_bytes' => 0];
    }

    $localPath = $cleanupDir . DIRECTORY_SEPARATOR . $filename;
    $sftpConfig['remote_path'] = $targetDir;
    $connection = tpBackupOpenExternalizedSftpConnection($sftpConfig);
    if ($connection['success'] === false) {
        tpBackupRemoveTemporaryDirectory($cleanupDir);
        return ['success' => false, 'reason' => (string) $connection['reason'], 'destination_type' => 'sftp', 'path' => '', 'filename' => $filename, 'cleanup_required' => false, 'cleanup_dir' => '', 'source_path' => (string) $connection['path'], 'size_bytes' => 0];
    }

    $remoteFilePath = tpBackupExternalizedSftpRemoteFilePath((string) $connection['path'], $filename);
    if ($remoteFilePath === '') {
        tpBackupRemoveTemporaryDirectory($cleanupDir);
        return ['success' => false, 'reason' => 'INVALID_FILENAME', 'destination_type' => 'sftp', 'path' => '', 'filename' => $filename, 'cleanup_required' => false, 'cleanup_dir' => '', 'source_path' => '', 'size_bytes' => 0];
    }

    try {
        $sftp = $connection['client'];
        if ($sftp->get($remoteFilePath, $localPath) !== true || is_file($localPath) === false) {
            tpBackupRemoveTemporaryDirectory($cleanupDir);
            return ['success' => false, 'reason' => 'SFTP_DOWNLOAD_FAILED', 'destination_type' => 'sftp', 'path' => '', 'filename' => $filename, 'cleanup_required' => false, 'cleanup_dir' => '', 'source_path' => $remoteFilePath, 'size_bytes' => 0];
        }

        $metadataPayload = $sftp->get($remoteFilePath . '.meta.json');
        if (is_string($metadataPayload) === true && trim($metadataPayload) !== '') {
            @file_put_contents(tpGetBackupMetadataPath($localPath), $metadataPayload, LOCK_EX);
        }

        return ['success' => true, 'reason' => '', 'destination_type' => 'sftp', 'path' => $localPath, 'filename' => $filename, 'cleanup_required' => true, 'cleanup_dir' => $cleanupDir, 'source_path' => $remoteFilePath, 'size_bytes' => (int) (@filesize($localPath) ?: 0)];
    } catch (Throwable) {
        tpBackupRemoveTemporaryDirectory($cleanupDir);
        return ['success' => false, 'reason' => 'SFTP_DOWNLOAD_FAILED', 'destination_type' => 'sftp', 'path' => '', 'filename' => $filename, 'cleanup_required' => false, 'cleanup_dir' => '', 'source_path' => $remoteFilePath, 'size_bytes' => 0];
    }
}

/**
 * Remove the temporary staging directory created when preparing a remote restore.
 *
 * @param array<string, mixed> $stage
 * @return bool
 */
function tpBackupCleanupExternalizedRestoreStage(array $stage): bool
{
    if (empty($stage['cleanup_required']) || empty($stage['cleanup_dir']) || is_scalar($stage['cleanup_dir']) === false) {
        return true;
    }

    return tpBackupRemoveTemporaryDirectory((string) $stage['cleanup_dir']);
}

/**
 * Apply externalized retention through the destination abstraction.
 *
 * @return array{success: bool, reason: string, deleted: int}
 */
function tpBackupPurgeExternalizedBackups(string $destinationType, string $targetDir, int $retentionDays, int $retentionCount, array $SETTINGS = [], array $sftpConfig = []): array
{
    $listed = tpBackupListExternalizedBackups($destinationType, $targetDir, $SETTINGS, $sftpConfig);
    if ($listed['success'] === false) {
        return ['success' => false, 'reason' => (string) $listed['reason'], 'deleted' => 0];
    }

    $deleted = 0;
    $remaining = $listed['files'];
    if ($retentionDays > 0) {
        $cutoff = time() - ($retentionDays * 86400);
        foreach ($remaining as $idx => $entry) {
            if ((int) $entry['mtime'] >= $cutoff) {
                continue;
            }

            $delete = tpBackupDeleteExternalizedBackup($destinationType, (string) $listed['path'], (string) $entry['name'], $SETTINGS, $sftpConfig);
            if ($delete['success'] === true && $delete['deleted'] === true) {
                $deleted++;
            }
            unset($remaining[$idx]);
        }
    }

    $remaining = array_values($remaining);
    usort($remaining, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    if ($retentionCount > 0 && count($remaining) > $retentionCount) {
        foreach (array_slice($remaining, $retentionCount) as $entry) {
            $delete = tpBackupDeleteExternalizedBackup($destinationType, (string) $listed['path'], (string) $entry['name'], $SETTINGS, $sftpConfig);
            if ($delete['success'] === true && $delete['deleted'] === true) {
                $deleted++;
            }
        }
    }

    return ['success' => true, 'reason' => '', 'deleted' => $deleted];
}

/**
 * Normalize a package ZIP entry path (forward slashes, no traversal).
 *
 * @return string
 */
function tpBackupNormalizePackageEntryPath(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) === 1) {
        return '';
    }

    $path = preg_replace('#/+#', '/', $path) ?? $path;
    $parts = [];
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..' || str_contains($part, "\0") || str_contains($part, ':')) {
            return '';
        }
        $parts[] = $part;
    }

    return implode('/', $parts);
}

/**
 * Recursively normalize a value into a JSON-safe structure.
 *
 * @param mixed $value
 * @return mixed
 */
function tpBackupNormalizeJsonValue(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    if (array_is_list($value) === false) {
        ksort($value);
    }

    foreach ($value as $k => $v) {
        $value[$k] = tpBackupNormalizeJsonValue($v);
    }

    return $value;
}

/**
 * Encode a payload as canonical (key-sorted, escaped) JSON for checksums.
 *
 * @param array<mixed> $payload
 * @return string
 */
function tpBackupCanonicalJson(array $payload): string
{
    $json = json_encode(
        tpBackupNormalizeJsonValue($payload),
        JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );

    return is_string($json) ? $json : '';
}

/**
 * Return the SHA-256 hash of a file, or '' when it cannot be read.
 *
 * @return string
 */
function tpBackupFileSha256(string $path): string
{
    if (is_file($path) === false) {
        return '';
    }

    $hash = hash_file('sha256', $path);
    return is_string($hash) ? $hash : '';
}

/**
 * Create a unique temporary backup directory under the base dir; return its path or ''.
 *
 * @return string
 */
function tpBackupCreateTemporaryDirectory(string $baseDir): string
{
    if ($baseDir === '' || is_dir($baseDir) === false || is_writable($baseDir) === false) {
        return '';
    }

    for ($i = 0; $i < 10; $i++) {
        try {
            $token = bin2hex(random_bytes(8));
        } catch (Throwable $ignored) {
            $token = str_replace('.', '', uniqid('', true));
        }

        $dir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . 'tpbackup_tmp_' . $token;
        if (is_dir($dir)) {
            continue;
        }

        if (@mkdir($dir, 0700, true) === true) {
            $real = realpath($dir);
            return is_string($real) ? $real : $dir;
        }
    }

    return '';
}

/**
 * Recursively remove a TeamPass temporary backup directory.
 *
 * @return bool
 */
function tpBackupRemoveTemporaryDirectory(string $path): bool
{
    $real = realpath($path);
    if ($real === false || is_dir($real) === false) {
        return true;
    }

    $isInsidePackageTempDir = false;
    $cursor = $real;
    while (dirname($cursor) !== $cursor) {
        if (str_starts_with(basename($cursor), 'tpbackup_tmp_') === true) {
            $isInsidePackageTempDir = true;
            break;
        }
        $cursor = dirname($cursor);
    }

    if ($isInsidePackageTempDir === false) {
        return false;
    }

    $entries = scandir($real);
    if ($entries === false) {
        return false;
    }

    $ok = true;
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $child = $real . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child) === true && is_link($child) === false) {
            $ok = tpBackupRemoveTemporaryDirectory($child) && $ok;
        } elseif (is_file($child) === true || is_link($child) === true) {
            $ok = @unlink($child) && $ok;
        }
    }

    return @rmdir($real) && $ok;
}

/**
 * Purge stale Teampass backup temporary directories.
 *
 * This is intentionally limited to directories created by
 * tpBackupCreateTemporaryDirectory() and never follows arbitrary names.
 *
 * @return array{scanned: int, deleted: int, failed: int, skipped_recent: int}
 */
function tpBackupPurgeOldTemporaryDirectories(string $baseDir, int $olderThanSeconds = 86400): array
{
    $stats = [
        'scanned' => 0,
        'deleted' => 0,
        'failed' => 0,
        'skipped_recent' => 0,
    ];

    $baseReal = realpath($baseDir);
    if ($baseReal === false || is_dir($baseReal) === false) {
        return $stats;
    }

    $olderThanSeconds = max(3600, $olderThanSeconds);
    $entries = scandir($baseReal);
    if ($entries === false) {
        return $stats;
    }

    $cutoff = time() - $olderThanSeconds;
    foreach ($entries as $entry) {
        if (str_starts_with($entry, 'tpbackup_tmp_') === false) {
            continue;
        }

        $candidate = $baseReal . DIRECTORY_SEPARATOR . $entry;
        $candidateReal = realpath($candidate);
        if (
            $candidateReal === false
            || is_dir($candidateReal) === false
            || tpBackupIsResolvedPathInsideDirectory($candidateReal, $baseReal) === false
        ) {
            continue;
        }

        $stats['scanned']++;
        $mtime = @filemtime($candidateReal);
        $ctime = @filectime($candidateReal);
        $lastChanged = max((int) ($mtime ?: 0), (int) ($ctime ?: 0));
        if ($lastChanged > $cutoff) {
            $stats['skipped_recent']++;
            continue;
        }

        if (tpBackupRemoveTemporaryDirectory($candidateReal) === true) {
            $stats['deleted']++;
        } else {
            $stats['failed']++;
        }
    }

    return $stats;
}

/**
 * Build a unique .tpbackup package filename using the given prefix.
 *
 * @return string
 */
function tpBackupBuildPackageFilename(string $prefix = ''): string
{
    if (function_exists('GenerateCryptKey')) {
        $token = GenerateCryptKey(20, false, true, true, false, true);
    } else {
        try {
            $token = bin2hex(random_bytes(10));
        } catch (Throwable $ignored) {
            $token = str_replace('.', '', uniqid('', true));
        }
    }

    $schemaLevel = tpGetSchemaLevel();
    $schemaSuffix = ($schemaLevel !== '') ? ('-sl' . $schemaLevel) : '';

    return $prefix . time() . '-' . $token . $schemaSuffix . '.' . tpBackupGetPackageExtension();
}

/**
 * @return array<string, array{referenced_count:int, included_count:int, missing_count:int, total_bytes:int}>
 */
function tpBackupGetDocumentReferenceSummary(): array
{
    $summary = [
        'item_attachments' => [
            'referenced_count' => 0,
            'included_count' => 0,
            'missing_count' => 0,
            'total_bytes' => 0,
        ],
        'kb_attachments' => [
            'referenced_count' => 0,
            'included_count' => 0,
            'missing_count' => 0,
            'total_bytes' => 0,
        ],
        'avatars' => [
            'referenced_count' => 0,
            'included_count' => 0,
            'missing_count' => 0,
            'total_bytes' => 0,
        ],
    ];

    if (class_exists('DB') === false || function_exists('prefixTable') === false) {
        return $summary;
    }

    try {
        $summary['item_attachments']['referenced_count'] = (int) DB::queryFirstField(
            'SELECT COUNT(*) FROM ' . prefixTable('files')
        );
    } catch (Throwable $ignored) {
        // best effort
    }

    try {
        $summary['kb_attachments']['referenced_count'] = (int) DB::queryFirstField(
            'SELECT COUNT(*) FROM ' . prefixTable('misc') . ' WHERE type = %s',
            'kb_attachment'
        );
    } catch (Throwable $ignored) {
        // best effort
    }

    try {
        $summary['avatars']['referenced_count'] = (int) DB::queryFirstField(
            'SELECT COUNT(*) FROM ' . prefixTable('users') . '
             WHERE (avatar IS NOT NULL AND avatar != "")
                OR (avatar_thumb IS NOT NULL AND avatar_thumb != "")'
        );
    } catch (Throwable $ignored) {
        // best effort
    }

    return $summary;
}

/**
 * Count the document references declared in a package document summary.
 *
 * @param array<string, mixed> $documents
 * @return int
 */
function tpBackupDocumentReferenceCount(array $documents): int
{
    $count = 0;
    foreach ($documents as $entry) {
        if (is_array($entry) === false) {
            continue;
        }
        $count += (int) ($entry['referenced_count'] ?? ($entry['count'] ?? 0));
    }

    return $count;
}

/**
 * Build an empty document summary structure for a package manifest.
 *
 * @return array<string, mixed>
 */
function tpBackupBuildEmptyDocumentSummary(bool $included = false, string $mode = 'not_requested'): array
{
    $set = [
        'count' => 0,
        'included_count' => 0,
        'missing_count' => 0,
        'total_bytes' => 0,
    ];

    return [
        'included' => $included,
        'mode' => $mode,
        'item_attachments' => $set,
        'kb_attachments' => $set,
        'avatars' => $set,
        'total_bytes' => 0,
        'manifest_checksum' => null,
        'warnings' => [],
    ];
}

/**
 * Return a safe storage basename (allow-listed characters) or '' when invalid.
 *
 * @return string
 */
function tpBackupSafeStorageBasename(string $filename): string
{
    $filename = trim(str_replace("\0", '', $filename));
    if ($filename === '') {
        return '';
    }

    $normalized = str_replace('\\', '/', $filename);
    if (str_contains($normalized, '/') === true) {
        return '';
    }

    $basename = basename($normalized);
    if ($basename === '.' || $basename === '..') {
        return '';
    }

    return preg_match('/^[A-Za-z0-9._-]+$/', $basename) === 1 ? $basename : '';
}

/**
 * Resolve a filename inside a base directory; return '' on traversal or when outside.
 *
 * @return string
 */
function tpBackupResolveFileInBase(string $baseDir, string $filename): string
{
    $basename = tpBackupSafeStorageBasename($filename);
    if ($basename === '' || is_dir($baseDir) === false) {
        return '';
    }

    $baseReal = realpath($baseDir);
    if ($baseReal === false) {
        return '';
    }

    $candidate = $baseReal . DIRECTORY_SEPARATOR . $basename;
    $real = realpath($candidate);
    if ($real === false || is_file($real) === false || is_readable($real) === false) {
        return '';
    }

    return tpBackupIsResolvedPathInsideDirectory($real, $baseReal) === true ? $real : '';
}

/**
 * Add a single document entry to the in-progress backup document catalog.
 *
 * @param array<string, mixed> $catalog
 * @param array<string, mixed> $manifestData
 * @return void
 */
function tpBackupAddDocumentEntryToCatalog(
    array &$catalog,
    string $setName,
    string $kind,
    string $sourcePath,
    string $packagePath,
    array $manifestData
): void {
    $packagePath = tpBackupNormalizePackageEntryPath($packagePath);
    if ($sourcePath === '' || $packagePath === '' || is_file($sourcePath) === false) {
        $catalog['summary'][$setName]['missing_count']++;
        return;
    }

    $size = (int) (@filesize($sourcePath) ?: 0);
    $sha256 = tpBackupFileSha256($sourcePath);

    $catalog['summary'][$setName]['included_count']++;
    if (isset($catalog['zip_entries'][$packagePath]) === false) {
        $catalog['zip_entries'][$packagePath] = $sourcePath;
        $catalog['summary'][$setName]['total_bytes'] += $size;
        $catalog['summary']['total_bytes'] += $size;
    }

    $catalog['manifest_entries'][] = array_merge(
        [
            'kind' => $kind,
            'package_path' => $packagePath,
            'size_bytes' => $size,
            'sha256' => $sha256,
        ],
        $manifestData
    );
}

/**
 * Build the catalog of documents (item attachments, KB files, avatars) to add to a package.
 *
 * @param array<string, mixed> $SETTINGS
 * @return array<string, mixed>
 */
function tpBackupBuildDocumentCatalog(array $SETTINGS, bool $includeDocuments): array
{
    $catalog = [
        'summary' => tpBackupBuildEmptyDocumentSummary($includeDocuments, $includeDocuments ? 'included' : 'not_requested'),
        'zip_entries' => [],
        'manifest_entries' => [],
    ];

    if (class_exists('DB') === false || function_exists('prefixTable') === false) {
        return $catalog;
    }

    $uploadDir = (string) ($SETTINGS['path_to_upload_folder'] ?? '');
    $filesDir = (string) ($SETTINGS['path_to_files_folder'] ?? '');
    $kbDir = rtrim($filesDir, '/\\') . DIRECTORY_SEPARATOR . 'kb_attachments';
    $avatarDir = defined('TEAMPASS_ROOT')
        ? ((string) TEAMPASS_ROOT . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'avatars')
        : '';

    try {
        $rows = DB::query(
            'SELECT id, id_item, file, size, extension, type, status, confirmed
             FROM ' . prefixTable('files') . '
             ORDER BY id ASC'
        );
        foreach ($rows as $row) {
            $catalog['summary']['item_attachments']['count']++;
            if ($includeDocuments === false) {
                continue;
            }

            $dbFileValue = (string) ($row['file'] ?? '');
            $candidates = [];
            if ($dbFileValue !== '' && defined('TP_FILE_PREFIX')) {
                $candidates[] = (string) TP_FILE_PREFIX . $dbFileValue;
                $decoded = base64_decode($dbFileValue, true);
                if (is_string($decoded) && $decoded !== '' && $decoded !== $dbFileValue) {
                    $candidates[] = (string) TP_FILE_PREFIX . $decoded;
                }
            }

            $sourcePath = '';
            $storedName = '';
            foreach (array_values(array_unique($candidates)) as $candidate) {
                $sourcePath = tpBackupResolveFileInBase($uploadDir, $candidate);
                if ($sourcePath !== '') {
                    $storedName = basename($sourcePath);
                    break;
                }
            }

            if ($sourcePath === '') {
                $catalog['summary']['item_attachments']['missing_count']++;
                continue;
            }

            tpBackupAddDocumentEntryToCatalog(
                $catalog,
                'item_attachments',
                'item_attachment',
                $sourcePath,
                'documents/item_attachments/' . $storedName,
                [
                    'file_id' => (int) ($row['id'] ?? 0),
                    'item_id' => (int) ($row['id_item'] ?? 0),
                    'stored_name' => $storedName,
                    'db_file' => $dbFileValue,
                    'extension' => (string) ($row['extension'] ?? ''),
                    'status' => (string) ($row['status'] ?? ''),
                ]
            );
        }
    } catch (Throwable $ignored) {
        $catalog['summary']['warnings'][] = 'ITEM_ATTACHMENTS_SCAN_FAILED';
    }

    try {
        $rows = DB::query(
            'SELECT increment_id, intitule, valeur
             FROM ' . prefixTable('misc') . '
             WHERE type = %s
             ORDER BY increment_id ASC',
            'kb_attachment'
        );
        foreach ($rows as $row) {
            $catalog['summary']['kb_attachments']['count']++;
            $payload = json_decode((string) ($row['valeur'] ?? ''), true);
            $payload = is_array($payload) ? $payload : [];

            if ($includeDocuments === false) {
                continue;
            }

            $storedName = tpBackupSafeStorageBasename((string) ($payload['stored_name'] ?? ''));
            $sourcePath = tpBackupResolveFileInBase($kbDir, $storedName);
            if ($sourcePath === '') {
                $catalog['summary']['kb_attachments']['missing_count']++;
                continue;
            }

            tpBackupAddDocumentEntryToCatalog(
                $catalog,
                'kb_attachments',
                'kb_attachment',
                $sourcePath,
                'documents/kb_attachments/' . $storedName,
                [
                    'misc_id' => (int) ($row['increment_id'] ?? 0),
                    'intitule' => (string) ($row['intitule'] ?? ''),
                    'kb_id' => (int) ($payload['kb_id'] ?? 0),
                    'attachment_id' => (string) ($payload['attachment_id'] ?? ''),
                    'stored_name' => $storedName,
                ]
            );
        }
    } catch (Throwable $ignored) {
        $catalog['summary']['warnings'][] = 'KB_ATTACHMENTS_SCAN_FAILED';
    }

    try {
        $rows = DB::query(
            'SELECT id, avatar, avatar_thumb
             FROM ' . prefixTable('users') . '
             WHERE (avatar IS NOT NULL AND avatar != "")
                OR (avatar_thumb IS NOT NULL AND avatar_thumb != "")
             ORDER BY id ASC'
        );
        foreach ($rows as $row) {
            foreach (['avatar' => 'avatar', 'avatar_thumb' => 'avatar_thumb'] as $column => $role) {
                $storedName = tpBackupSafeStorageBasename((string) ($row[$column] ?? ''));
                if ($storedName === '') {
                    continue;
                }

                $catalog['summary']['avatars']['count']++;
                if ($includeDocuments === false) {
                    continue;
                }

                $sourcePath = tpBackupResolveFileInBase($avatarDir, $storedName);
                if ($sourcePath === '') {
                    $catalog['summary']['avatars']['missing_count']++;
                    continue;
                }

                tpBackupAddDocumentEntryToCatalog(
                    $catalog,
                    'avatars',
                    'user_avatar',
                    $sourcePath,
                    'documents/user_avatars/' . $storedName,
                    [
                        'user_id' => (int) ($row['id'] ?? 0),
                        'role' => $role,
                        'stored_name' => $storedName,
                    ]
                );
            }
        }
    } catch (Throwable $ignored) {
        $catalog['summary']['warnings'][] = 'AVATARS_SCAN_FAILED';
    }

    $manifestEntriesJson = tpBackupCanonicalJson(['entries' => $catalog['manifest_entries']]);
    $catalog['summary']['manifest_checksum'] = $manifestEntriesJson !== ''
        ? hash('sha256', $manifestEntriesJson)
        : null;

    if ($includeDocuments === false && tpBackupDocumentReferenceCount([
        'item_attachments' => $catalog['summary']['item_attachments'],
        'kb_attachments' => $catalog['summary']['kb_attachments'],
        'avatars' => $catalog['summary']['avatars'],
    ]) > 0) {
        $catalog['summary']['warnings'][] = 'DOCUMENTS_NOT_INCLUDED';
    }

    if ($includeDocuments === true) {
        foreach (['item_attachments', 'kb_attachments', 'avatars'] as $setName) {
            if ((int) $catalog['summary'][$setName]['missing_count'] > 0) {
                $catalog['summary']['warnings'][] = 'DOCUMENTS_PARTIALLY_INCLUDED';
                break;
            }
        }
    }

    $catalog['summary']['warnings'] = array_values(array_unique($catalog['summary']['warnings']));

    return $catalog;
}

/**
 * Build the public, non-sensitive .meta.json sidecar data for a package.
 *
 * @param array<string, mixed> $manifest
 * @return array<string, mixed>
 */
function tpBackupBuildPackagePublicMetadata(array $manifest, string $packagePath, string $manifestJson, string $comment = ''): array
{
    $teampass = is_array($manifest['teampass'] ?? null) ? $manifest['teampass'] : [];
    $documents = is_array($manifest['documents'] ?? null) ? $manifest['documents'] : [];

    $metadata = [
        'backup_format' => tpBackupGetPackageFormatId(),
        'backup_format_version' => tpBackupGetPackageFormatVersion(),
        'tp_files_version' => $teampass['files_version'] ?? null,
        'teampass_version' => $teampass['files_version'] ?? null,
        'schema_level' => $teampass['schema_level'] ?? null,
        'created_at' => $manifest['created_at'] ?? gmdate('c'),
        'backup_type' => $manifest['backup_type'] ?? 'database_only',
        'source' => $manifest['source'] ?? '',
        'encrypted' => true,
        'encryption' => [
            'engine' => 'defuse',
            'scope' => 'package',
        ],
        'package' => [
            'archive' => 'zip',
            'size_bytes' => is_file($packagePath) ? (int) filesize($packagePath) : 0,
            'sha256' => tpBackupFileSha256($packagePath),
        ],
        'manifest_checksum' => hash('sha256', $manifestJson),
        'documents' => $documents,
        'warnings' => is_array($manifest['warnings'] ?? null) ? $manifest['warnings'] : [],
    ];

    if ($comment !== '') {
        $metadata['comment'] = $comment;
    }

    return $metadata;
}

/**
 * Create a unified encrypted .tpbackup package.
 *
 * The package can contain the database only or the database plus confirmed
 * persistent document streams. The final .tpbackup file is encrypted.
 *
 * @return array{success: bool, filename: string, filepath: string, encrypted: bool, size_bytes: int, message: string, meta_path?: string, backup_format?: string, backup_format_version?: int}
 */
function tpCreateBackupPackage(array $SETTINGS, string $encryptionKey, array $options = []): array
{
    $outputDir = (string) ($options['output_dir'] ?? ($SETTINGS['path_to_files_folder'] ?? ''));
    $prefix = (string) ($options['filename_prefix'] ?? '');
    $source = trim((string) ($options['source'] ?? 'manual'));
    $comment = trim(str_replace("\0", '', (string) ($options['comment'] ?? '')));
    $includeDocuments = (bool) ($options['include_documents'] ?? false);

    if ($encryptionKey === '') {
        return [
            'success' => false,
            'filename' => '',
            'filepath' => '',
            'encrypted' => false,
            'size_bytes' => 0,
            'message' => 'Missing encryption key for .tpbackup package.',
        ];
    }

    if (class_exists('ZipArchive') === false) {
        return [
            'success' => false,
            'filename' => '',
            'filepath' => '',
            'encrypted' => false,
            'size_bytes' => 0,
            'message' => 'ZipArchive PHP extension is required to create .tpbackup packages.',
        ];
    }

    if (function_exists('prepareFileWithDefuse') === false) {
        return [
            'success' => false,
            'filename' => '',
            'filepath' => '',
            'encrypted' => false,
            'size_bytes' => 0,
            'message' => 'Missing prepareFileWithDefuse() dependency (main.functions.php not loaded?)',
        ];
    }

    if ($outputDir === '' || is_dir($outputDir) === false || is_writable($outputDir) === false) {
        return [
            'success' => false,
            'filename' => '',
            'filepath' => '',
            'encrypted' => false,
            'size_bytes' => 0,
            'message' => 'Backup folder is not writable or not found: ' . $outputDir,
        ];
    }

    $workDir = tpBackupCreateTemporaryDirectory($outputDir);
    if ($workDir === '') {
        return [
            'success' => false,
            'filename' => '',
            'filepath' => '',
            'encrypted' => false,
            'size_bytes' => 0,
            'message' => 'Could not create temporary backup package directory.',
        ];
    }

    try {
        $dbOptions = [
            'output_dir' => $workDir,
            'filename_prefix' => 'database-',
        ];
        foreach (['chunk_rows', 'flush_every_inserts', 'include_tables', 'exclude_tables'] as $optName) {
            if (array_key_exists($optName, $options)) {
                $dbOptions[$optName] = $options[$optName];
            }
        }

        $dbBackup = tpCreateDatabaseBackup($SETTINGS, '', $dbOptions);
        if ($dbBackup['success'] !== true || (string) $dbBackup['filepath'] === '') {
            return [
                'success' => false,
                'filename' => '',
                'filepath' => '',
                'encrypted' => false,
                'size_bytes' => 0,
                'message' => (string) $dbBackup['message'],
            ];
        }

        $databaseDir = $workDir . DIRECTORY_SEPARATOR . 'database';
        if (@mkdir($databaseDir, 0700, true) === false && is_dir($databaseDir) === false) {
            return [
                'success' => false,
                'filename' => '',
                'filepath' => '',
                'encrypted' => false,
                'size_bytes' => 0,
                'message' => 'Could not create package database directory.',
            ];
        }

        $dumpPath = $databaseDir . DIRECTORY_SEPARATOR . 'dump.sql';
        if (@rename((string) $dbBackup['filepath'], $dumpPath) === false) {
            return [
                'success' => false,
                'filename' => '',
                'filepath' => '',
                'encrypted' => false,
                'size_bytes' => 0,
                'message' => 'Could not move SQL dump into package payload.',
            ];
        }

        $documentCatalog = tpBackupBuildDocumentCatalog($SETTINGS, $includeDocuments);
        $documentSummary = is_array($documentCatalog['summary'] ?? null)
            ? $documentCatalog['summary']
            : tpBackupBuildEmptyDocumentSummary($includeDocuments, $includeDocuments ? 'included' : 'not_requested');
        $warnings = is_array($documentSummary['warnings'] ?? null) ? $documentSummary['warnings'] : [];

        $manifest = [
            'backup_format' => tpBackupGetPackageFormatId(),
            'backup_format_version' => tpBackupGetPackageFormatVersion(),
            'created_at' => gmdate('c'),
            'source' => ($source !== '') ? $source : 'manual',
            'backup_type' => $includeDocuments ? 'db_documents' : 'database_only',
            'teampass' => [
                'files_version' => (($v = tpGetTpFilesVersion()) !== '') ? $v : null,
                'schema_level' => (($sl = tpGetSchemaLevel()) !== '') ? $sl : null,
            ],
            'encryption' => [
                'engine' => 'defuse',
                'scope' => 'package',
            ],
            'database' => [
                'path' => 'database/dump.sql',
                'size_bytes' => is_file($dumpPath) ? (int) filesize($dumpPath) : 0,
                'sha256' => tpBackupFileSha256($dumpPath),
            ],
            'documents' => $documentSummary,
            'document_entries' => is_array($documentCatalog['manifest_entries'] ?? null) ? $documentCatalog['manifest_entries'] : [],
            'warnings' => $warnings,
        ];
        if ($comment !== '') {
            $manifest['comment'] = $comment;
        }

        $manifestJson = tpBackupCanonicalJson($manifest);
        if ($manifestJson === '') {
            return [
                'success' => false,
                'filename' => '',
                'filepath' => '',
                'encrypted' => false,
                'size_bytes' => 0,
                'message' => 'Unable to encode package manifest as JSON.',
            ];
        }

        $zipPath = $workDir . DIRECTORY_SEPARATOR . 'payload.zip';
        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($openResult !== true) {
            return [
                'success' => false,
                'filename' => '',
                'filepath' => '',
                'encrypted' => false,
                'size_bytes' => 0,
                'message' => 'Unable to create package archive.',
            ];
        }

        $zipOk = $zip->addFromString('manifest.json', $manifestJson)
            && $zip->addFile($dumpPath, 'database/dump.sql');
        if ($zipOk === true && $includeDocuments === true && is_array($documentCatalog['zip_entries'] ?? null)) {
            foreach ($documentCatalog['zip_entries'] as $entryPath => $sourcePath) {
                $entryPath = tpBackupNormalizePackageEntryPath((string) $entryPath);
                if ($entryPath === '' || is_file((string) $sourcePath) === false) {
                    $zipOk = false;
                    break;
                }
                if ($zip->addFile((string) $sourcePath, $entryPath) === false) {
                    $zipOk = false;
                    break;
                }
            }
        }
        $closed = $zip->close();
        if ($zipOk === false || $closed === false || is_file($zipPath) === false) {
            return [
                'success' => false,
                'filename' => '',
                'filepath' => '',
                'encrypted' => false,
                'size_bytes' => 0,
                'message' => 'Unable to finalize package archive.',
            ];
        }

        $filename = tpBackupBuildPackageFilename($prefix);
        $filepath = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        $encryptedTmpPath = $workDir . DIRECTORY_SEPARATOR . 'payload.encrypted';

        $encryptResult = tpPrepareFileWithDefuseNormalized('encrypt', $zipPath, $encryptedTmpPath, $encryptionKey);
        if ($encryptResult['success'] !== true || is_file($encryptedTmpPath) === false) {
            return [
                'success' => false,
                'filename' => $filename,
                'filepath' => $filepath,
                'encrypted' => false,
                'size_bytes' => 0,
                'message' => 'Encryption failed: ' . (string) $encryptResult['message'],
            ];
        }

        if (@rename($encryptedTmpPath, $filepath) === false) {
            return [
                'success' => false,
                'filename' => $filename,
                'filepath' => $filepath,
                'encrypted' => false,
                'size_bytes' => 0,
                'message' => 'Encryption succeeded but could not finalize package file.',
            ];
        }

        $metadata = tpBackupBuildPackagePublicMetadata($manifest, $filepath, $manifestJson, $comment);
        $metaResult = tpWriteBackupMetadata($filepath, '', '', $metadata);
        if ($metaResult['success'] !== true) {
            @unlink($filepath);
            return [
                'success' => false,
                'filename' => $filename,
                'filepath' => $filepath,
                'encrypted' => false,
                'size_bytes' => 0,
                'message' => (string) $metaResult['message'],
            ];
        }

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'encrypted' => true,
            'size_bytes' => is_file($filepath) ? (int) filesize($filepath) : 0,
            'message' => '',
            'meta_path' => (string) $metaResult['meta_path'],
            'backup_format' => tpBackupGetPackageFormatId(),
            'backup_format_version' => tpBackupGetPackageFormatVersion(),
        ];
    } finally {
        tpBackupRemoveTemporaryDirectory($workDir);
    }
}


/**
 * Check whether the provided value matches the clear backup passkey format.
 */
function tpBackupScriptPasskeyIsClear(string $value, int $maxLength = 0): bool
{
    $lengths = [40];
    if ($maxLength > 0 && $maxLength !== 40) {
        $lengths[] = $maxLength;
    }
    foreach ($lengths as $len) {
        if (preg_match('/^[A-Za-z0-9]{' . $len . '}$/', $value) === 1) {
            return true;
        }
    }
    return false;
}

/**
 * Generate a clear backup script passkey.
 */
function tpGenerateBackupScriptPasskey(): string
{
    return GenerateCryptKey(40, false, true, true, false, true);
}

function tpGetBackupScriptPasskeyArchiveSettingName(): string
{
    return 'bck_script_passkey_restore_candidates';
}

/**
 * @param array<int|string, mixed> $candidates
 * @return array<int, string>
 */
function tpNormalizeBackupScriptPasskeyCandidates(array $candidates): array
{
        $normalized = [];
        foreach ($candidates as $candidate) {
            if (!is_scalar($candidate)) {
                continue;
            }

            $value = trim((string) $candidate);
            if ($value === '') {
                continue;
            }

            if (!in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return $normalized;
}

/**
 * Load archived restore candidates for backward compatibility with historical backups.
 *
 * @return array<int, string>
 */
function tpLoadBackupScriptPasskeyRestoreCandidates(array &$SETTINGS): array
{
        $settingName = tpGetBackupScriptPasskeyArchiveSettingName();
        $storedPayload = isset($SETTINGS[$settingName]) ? (string) $SETTINGS[$settingName] : '';

        if ($storedPayload === '') {
            $row = DB::queryFirstRow(
                'SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type=%s AND intitule=%s LIMIT 1',
                'admin',
                $settingName
            );
            $storedPayload = isset($row['valeur']) ? (string) $row['valeur'] : '';
            if ($storedPayload !== '') {
                $SETTINGS[$settingName] = $storedPayload;
            }
        }

        if ($storedPayload === '') {
            return [];
        }

        $decodedPayload = '';
        try {
            $tmp = cryption($storedPayload, '', 'decrypt', $SETTINGS);
            $decodedPayload = isset($tmp['string']) ? (string) $tmp['string'] : '';
        } catch (Throwable $e) {
            $decodedPayload = '';
        }

        if ($decodedPayload === '') {
            $decodedPayload = $storedPayload;
        }

        $data = json_decode($decodedPayload, true);
        if (is_array($data)) {
            return tpNormalizeBackupScriptPasskeyCandidates($data);
        }

        return tpNormalizeBackupScriptPasskeyCandidates([$decodedPayload]);
}

/**
 * Persist archived restore candidates as an encrypted JSON payload.
 */
function tpStoreBackupScriptPasskeyRestoreCandidates(array $candidates, array &$SETTINGS): bool
{
        $settingName = tpGetBackupScriptPasskeyArchiveSettingName();
        $normalized = tpNormalizeBackupScriptPasskeyCandidates($candidates);

        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        $enc = cryption($json, '', 'encrypt', $SETTINGS);
        $storedPayload = isset($enc['string']) ? (string) $enc['string'] : '';
        if ($storedPayload === '') {
            return false;
        }

        $row = DB::queryFirstRow(
            'SELECT increment_id FROM ' . prefixTable('misc') . ' WHERE type=%s AND intitule=%s LIMIT 1',
            'admin',
            $settingName
        );

        try {
            if (!empty($row['increment_id'])) {
                DB::update(
                    prefixTable('misc'),
                    [
                        'valeur' => $storedPayload,
                        'updated_at' => time(),
                        'is_encrypted' => 1,
                    ],
                    'increment_id=%i',
                    (int) $row['increment_id']
                );
            } else {
                DB::insert(
                    prefixTable('misc'),
                    [
                        'type' => 'admin',
                        'intitule' => $settingName,
                        'valeur' => $storedPayload,
                        'created_at' => time(),
                        'is_encrypted' => 1,
                    ]
                );
            }
        } catch (Throwable $e) {
            try {
                if (!empty($row['increment_id'])) {
                    DB::update(
                        prefixTable('misc'),
                        [
                            'valeur' => $storedPayload,
                            'updated_at' => time(),
                        ],
                        'increment_id=%i',
                        (int) $row['increment_id']
                    );
                } else {
                    DB::insert(
                        prefixTable('misc'),
                        [
                            'type' => 'admin',
                            'intitule' => $settingName,
                            'valeur' => $storedPayload,
                            'created_at' => time(),
                        ]
                    );
                }
            } catch (Throwable $e2) {
                return false;
            }
        }

        $SETTINGS[$settingName] = $storedPayload;

        if (class_exists('\TeampassClasses\ConfigManager\ConfigManager')) {
            \TeampassClasses\ConfigManager\ConfigManager::invalidateCache();
        }

        return true;
}

/**
 * Store a restore candidate if it is not already archived.
 */
function tpArchiveBackupScriptPasskeyCandidate(string $candidate, array &$SETTINGS): bool
{
        $candidate = trim($candidate);
        if ($candidate === '') {
            return false;
        }

        $candidates = tpLoadBackupScriptPasskeyRestoreCandidates($SETTINGS);
        if (in_array($candidate, $candidates, true)) {
            return true;
        }

        $candidates[] = $candidate;
        return tpStoreBackupScriptPasskeyRestoreCandidates($candidates, $SETTINGS);
}

/**
 * Archive the current backup passkey state so historical scheduled backups remain restorable.
 */
function tpArchiveCurrentBackupScriptPasskeyState(array &$SETTINGS): void
{
        $storedValue = isset($SETTINGS['bck_script_passkey']) ? (string) $SETTINGS['bck_script_passkey'] : '';
        if ($storedValue !== '') {
            tpArchiveBackupScriptPasskeyCandidate($storedValue, $SETTINGS);
        }

        $resolved = tpResolveBackupScriptPasskey($SETTINGS, false);
        if (!empty($resolved['success']) && !empty($resolved['clear_key'])) {
            tpArchiveBackupScriptPasskeyCandidate((string) $resolved['clear_key'], $SETTINGS);
        }
}

/**
 * Store the backup script passkey in teampass_misc as an encrypted admin setting.
 *
 * @return array{success: bool, clear_key: string, encrypted_key: string, message_code: string}
 */
function tpStoreBackupScriptPasskey(string $clearKey, array &$SETTINGS, bool $archiveCurrent = true): array
{
        if (!tpBackupScriptPasskeyIsClear($clearKey)) {
            return [
                'success' => false,
                'clear_key' => '',
                'encrypted_key' => '',
                'message_code' => 'invalid_format',
            ];
        }

        $enc = cryption($clearKey, '', 'encrypt', $SETTINGS);
        $encryptedKey = isset($enc['string']) ? (string) $enc['string'] : '';
        if ($encryptedKey === '') {
            return [
                'success' => false,
                'clear_key' => '',
                'encrypted_key' => '',
                'message_code' => 'encrypt_failed',
            ];
        }

        if ($archiveCurrent === true) {
            $currentStoredValue = isset($SETTINGS['bck_script_passkey']) ? (string) $SETTINGS['bck_script_passkey'] : '';
            if ($currentStoredValue !== '' && $currentStoredValue !== $encryptedKey) {
                tpArchiveBackupScriptPasskeyCandidate($currentStoredValue, $SETTINGS);
            }

            $currentResolved = tpResolveBackupScriptPasskey($SETTINGS, false);
            if (!empty($currentResolved['success']) && !empty($currentResolved['clear_key'])) {
                $currentClearKey = (string) $currentResolved['clear_key'];
                if ($currentClearKey !== $clearKey) {
                    tpArchiveBackupScriptPasskeyCandidate($currentClearKey, $SETTINGS);
                }
            }
        }

        $row = DB::queryFirstRow(
            'SELECT increment_id FROM ' . prefixTable('misc') . ' WHERE type=%s AND intitule=%s LIMIT 1',
            'admin',
            'bck_script_passkey'
        );

        try {
            if (!empty($row['increment_id'])) {
                DB::update(
                    prefixTable('misc'),
                    [
                        'valeur' => $encryptedKey,
                        'updated_at' => time(),
                        'is_encrypted' => 1,
                    ],
                    'increment_id=%i',
                    (int) $row['increment_id']
                );
            } else {
                DB::insert(
                    prefixTable('misc'),
                    [
                        'type' => 'admin',
                        'intitule' => 'bck_script_passkey',
                        'valeur' => $encryptedKey,
                        'created_at' => time(),
                        'is_encrypted' => 1,
                    ]
                );
            }
        } catch (Throwable $e) {
            try {
                if (!empty($row['increment_id'])) {
                    DB::update(
                        prefixTable('misc'),
                        [
                            'valeur' => $encryptedKey,
                            'updated_at' => time(),
                        ],
                        'increment_id=%i',
                        (int) $row['increment_id']
                    );
                } else {
                    DB::insert(
                        prefixTable('misc'),
                        [
                            'type' => 'admin',
                            'intitule' => 'bck_script_passkey',
                            'valeur' => $encryptedKey,
                            'created_at' => time(),
                        ]
                    );
                }
            } catch (Throwable $e2) {
                return [
                    'success' => false,
                    'clear_key' => '',
                    'encrypted_key' => '',
                    'message_code' => 'db_write_failed',
                ];
            }
        }

        $SETTINGS['bck_script_passkey'] = $encryptedKey;

        if (class_exists('\TeampassClasses\ConfigManager\ConfigManager')) {
            \TeampassClasses\ConfigManager\ConfigManager::invalidateCache();
        }

        return [
            'success' => true,
            'clear_key' => $clearKey,
            'encrypted_key' => $encryptedKey,
            'message_code' => '',
        ];
}

/**
 * Resolve the backup script passkey to a clear key.
 *
 * Supported cases:
 * - encrypted value stored in misc/config (expected format)
 * - legacy clear-text 40 chars value
 * - empty value (optionally self-healed)
 *
 * Corrupted non-empty values are never overwritten automatically.
 *
 * @return array{success: bool, clear_key: string, stored_value: string, source: string, message_code: string}
 */
function tpResolveBackupScriptPasskey(array &$SETTINGS, bool $autoHeal = false): array
{
        $storedValue = isset($SETTINGS['bck_script_passkey']) ? (string) $SETTINGS['bck_script_passkey'] : '';

        if ($storedValue !== '') {
            if (tpBackupScriptPasskeyIsClear($storedValue)) {
                if ($autoHeal === true) {
                    tpArchiveBackupScriptPasskeyCandidate($storedValue, $SETTINGS);

                    $stored = tpStoreBackupScriptPasskey($storedValue, $SETTINGS, false);
                    if (!empty($stored['success'])) {
                        tpArchiveBackupScriptPasskeyCandidate((string) $stored['encrypted_key'], $SETTINGS);
                        return [
                            'success' => true,
                            'clear_key' => $storedValue,
                            'stored_value' => (string) $stored['encrypted_key'],
                            'source' => 'legacy_clear_migrated',
                            'message_code' => '',
                        ];
                    }
                }

                return [
                    'success' => true,
                    'clear_key' => $storedValue,
                    'stored_value' => $storedValue,
                    'source' => 'legacy_clear',
                    'message_code' => '',
                ];
            }

            try {
                $tmp = cryption($storedValue, '', 'decrypt', $SETTINGS);
                $decryptedValue = isset($tmp['string']) ? (string) $tmp['string'] : '';
                if (tpBackupScriptPasskeyIsClear($decryptedValue, strlen($decryptedValue))) {
                    if ($autoHeal === true) {
                        tpArchiveBackupScriptPasskeyCandidate($storedValue, $SETTINGS);
                        tpArchiveBackupScriptPasskeyCandidate($decryptedValue, $SETTINGS);
                    }

                    return [
                        'success' => true,
                        'clear_key' => $decryptedValue,
                        'stored_value' => $storedValue,
                        'source' => 'encrypted',
                        'message_code' => '',
                    ];
                }
            } catch (Throwable $e) {
                // Continue below for invalid non-empty values.
            }

            return [
                'success' => false,
                'clear_key' => '',
                'stored_value' => $storedValue,
                'source' => 'invalid',
                'message_code' => 'invalid_format',
            ];
        }

        if ($autoHeal === true) {
            $generatedKey = tpGenerateBackupScriptPasskey();
            $stored = tpStoreBackupScriptPasskey($generatedKey, $SETTINGS, false);
            if (!empty($stored['success'])) {
                return [
                    'success' => true,
                    'clear_key' => $generatedKey,
                    'stored_value' => (string) $stored['encrypted_key'],
                    'source' => 'generated',
                    'message_code' => '',
                ];
            }
        }

        return [
            'success' => false,
            'clear_key' => '',
            'stored_value' => '',
            'source' => 'empty',
            'message_code' => 'not_set',
        ];
}

/**
 * Return candidate backup script passkeys for backward-compatible restore operations.
 *
 * @return array<int, string>
 */
function tpGetBackupScriptPasskeyCandidates(array &$SETTINGS, bool $autoHeal = false): array
{
        $keys = [];

        $resolved = tpResolveBackupScriptPasskey($SETTINGS, $autoHeal);
        if (!empty($resolved['success']) && !empty($resolved['clear_key'])) {
            $keys[] = (string) $resolved['clear_key'];
        }

        $storedValue = (string) $resolved['stored_value'];
        if ($storedValue === '') {
            $storedValue = isset($SETTINGS['bck_script_passkey']) ? (string) $SETTINGS['bck_script_passkey'] : '';
        }
        if ($storedValue !== '') {
            $keys[] = $storedValue;
        }

        $keys = array_merge($keys, tpLoadBackupScriptPasskeyRestoreCandidates($SETTINGS));

        return tpNormalizeBackupScriptPasskeyCandidates($keys);
}



// -----------------------------------------------------------------------------
// Helpers for restore logic (used by backups.queries.php)
// -----------------------------------------------------------------------------

/**
 * Ensure the returned string is valid UTF-8 and JSON-safe.
 *
 * Some crypto libraries can return messages containing non-UTF8 bytes.
 * Those would break json_encode() / prepareExchangedData().
 */
function tpSafeUtf8String(mixed $value): string
{
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value) === false) {
            $value = print_r($value, true);
        }

        $str = (string) $value;

        if (mb_check_encoding($str, 'UTF-8') === false) {
            // ASCII safe fallback
            return '[hex]' . bin2hex($str);
        }

        // Strip ASCII control chars
        $str = preg_replace("/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/", '', $str) ?? $str;
        return $str;
}

/**
 * Wrapper around prepareFileWithDefuse() that normalizes return values across TeamPass versions.
 *
 * @return array{success: bool, message: string}
 */
function tpPrepareFileWithDefuseNormalized(
    string $mode,
    string $sourceFile,
    string $destFile,
    string $encryptionKey
): array {
        try {
            $ret = prepareFileWithDefuse($mode, $sourceFile, $destFile, $encryptionKey);

            if ($ret === true) {
                return ['success' => true, 'message' => ''];
            }

            if (is_string($ret)) {
                return ['success' => false, 'message' => tpSafeUtf8String($ret)];
            }

            return ['success' => false, 'message' => 'Unknown error'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => tpSafeUtf8String($e->getMessage())];
        }
}

/**
 * Try to decrypt a file using Defuse with multiple candidate keys.
 *
 * @param array<int,string> $candidateKeys
 * @return array{success: bool, message: string, key_used?: string}
 */
function tpDefuseDecryptWithCandidates(
    string $encryptedFile,
    string $decryptedFile,
    array $candidateKeys,
    array $SETTINGS = []
): array {
        $lastMsg = '';
        foreach ($candidateKeys as $k) {
            $k = (string)$k;
            if ($k === '') {
                continue;
            }

            // Ensure we start from a clean slate.
            if (is_file($decryptedFile) && !unlink($decryptedFile)) {
                // Nothing to do, try next key
            }

            $r = tpPrepareFileWithDefuseNormalized('decrypt', $encryptedFile, $decryptedFile, $k);
            if (!empty($r['success'])) {
                return ['success' => true, 'message' => '', 'key_used' => $k];
            }
            $lastMsg = tpSafeUtf8String((string)$r['message']);
        }

        return ['success' => false, 'message' => ($lastMsg !== '' ? $lastMsg : 'Unable to decrypt')];
}

/**
 * Return a unique temporary file path for restore operations; '' on failure.
 *
 * @return string
 */
function tpBackupRestoreTempPath(string $extension): string
{
        $extension = trim($extension, ". \t\n\r\0\x0B");
        if ($extension === '') {
            $extension = 'tmp';
        }

        for ($i = 0; $i < 10; $i++) {
            try {
                $token = bin2hex(random_bytes(8));
            } catch (Throwable $ignored) {
                $token = str_replace('.', '', uniqid('', true));
            }

            $path = rtrim((string) sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
                . 'tpbackup_restore_' . getmypid() . '_' . time() . '_' . $token . '.' . $extension;
            if (file_exists($path) === false) {
                return $path;
            }
        }

        return '';
}

/**
 * Return the SHA-256 hash of a ZIP entry's contents.
 *
 * @param \ZipArchive $zip
 * @return string
 */
function tpBackupZipEntrySha256($zip, string $entryPath): string
{
        $entryPath = tpBackupNormalizePackageEntryPath($entryPath);
        if ($entryPath === '') {
            return '';
        }

        $stream = $zip->getStream($entryPath);
        if (is_resource($stream) === false) {
            return '';
        }

        $ctx = hash_init('sha256');
        while (feof($stream) === false) {
            $chunk = fread($stream, 1048576);
            if ($chunk === false) {
                fclose($stream);
                return '';
            }
            if ($chunk !== '') {
                hash_update($ctx, $chunk);
            }
        }
        fclose($stream);

        return hash_final($ctx);
}

/**
 * Extract a single ZIP entry to a destination file.
 *
 * @param \ZipArchive $zip
 * @return array<string, mixed>
 */
function tpBackupExtractZipEntryToFile($zip, string $entryPath, string $destFile): array
{
        $entryPath = tpBackupNormalizePackageEntryPath($entryPath);
        if ($entryPath === '' || $destFile === '') {
            return ['success' => false, 'message' => 'invalid_path'];
        }

        $stream = $zip->getStream($entryPath);
        if (is_resource($stream) === false) {
            return ['success' => false, 'message' => 'entry_not_found'];
        }

        $out = @fopen($destFile, 'wb');
        if (is_resource($out) === false) {
            fclose($stream);
            return ['success' => false, 'message' => 'destination_not_writable'];
        }

        $ok = true;
        while (feof($stream) === false) {
            $chunk = fread($stream, 1048576);
            if ($chunk === false) {
                $ok = false;
                break;
            }
            if ($chunk !== '' && fwrite($out, $chunk) === false) {
                $ok = false;
                break;
            }
        }

        fclose($stream);
        fclose($out);

        if ($ok === false) {
            @unlink($destFile);
            return ['success' => false, 'message' => 'copy_failed'];
        }

        return ['success' => true, 'message' => ''];
}

/**
 * Validate a decrypted .tpbackup ZIP and extract its SQL dump.
 * Checks the manifest, format version, backup type and database/document checksums.
 *
 * @return array<string, mixed>
 */
function tpBackupValidatePackageZipAndExtractSql(
    string $zipPath,
    string $sqlDestFile,
    bool $allowDocumentsRestore = false
): array {
        if (class_exists('ZipArchive') === false) {
            return ['success' => false, 'error_code' => 'ZIP_EXTENSION_MISSING', 'message' => 'ZipArchive is not available.'];
        }

        if (is_file($zipPath) === false || is_readable($zipPath) === false) {
            return ['success' => false, 'error_code' => 'PACKAGE_FILE_NOT_FOUND', 'message' => 'Package file not found.'];
        }

        $zip = new ZipArchive();
        $open = $zip->open($zipPath);
        if ($open !== true) {
            return ['success' => false, 'error_code' => 'PACKAGE_OPEN_FAILED', 'message' => 'Unable to open package archive.'];
        }

        try {
            $manifestJson = $zip->getFromName('manifest.json');
            if (is_string($manifestJson) === false || trim($manifestJson) === '') {
                return ['success' => false, 'error_code' => 'MANIFEST_MISSING', 'message' => 'Package manifest is missing.'];
            }

            $manifest = json_decode($manifestJson, true);
            if (is_array($manifest) === false) {
                return ['success' => false, 'error_code' => 'MANIFEST_INVALID', 'message' => 'Package manifest is invalid.'];
            }

            $format = is_scalar($manifest['backup_format'] ?? null) ? (string) $manifest['backup_format'] : '';
            $formatVersion = (int) ($manifest['backup_format_version'] ?? 0);
            if ($format !== tpBackupGetPackageFormatId() || $formatVersion < 1 || $formatVersion > tpBackupGetPackageFormatVersion()) {
                return ['success' => false, 'error_code' => 'PACKAGE_FORMAT_UNSUPPORTED', 'message' => 'Unsupported package format.'];
            }

            $backupType = is_scalar($manifest['backup_type'] ?? null) ? (string) $manifest['backup_type'] : '';
            if ($backupType === '') {
                $backupType = 'database_only';
            }

            if (in_array($backupType, ['db_documents', 'database_documents'], true) === true && $allowDocumentsRestore === false) {
                return [
                    'success' => false,
                    'error_code' => 'PACKAGE_DOCUMENTS_RESTORE_NOT_SUPPORTED',
                    'message' => 'Document restore from .tpbackup is not available yet.',
                    'manifest' => $manifest,
                ];
            }

            if (in_array($backupType, ['database_only', 'db_only', 'db_documents', 'database_documents'], true) === false) {
                return ['success' => false, 'error_code' => 'PACKAGE_TYPE_UNSUPPORTED', 'message' => 'Unsupported package backup type.'];
            }

            $database = is_array($manifest['database'] ?? null) ? $manifest['database'] : [];
            $dbPath = tpBackupNormalizePackageEntryPath((string) ($database['path'] ?? ''));
            if ($dbPath !== 'database/dump.sql') {
                return ['success' => false, 'error_code' => 'DATABASE_ENTRY_INVALID', 'message' => 'Database entry is invalid.'];
            }

            if ($zip->locateName($dbPath) === false) {
                return ['success' => false, 'error_code' => 'DATABASE_ENTRY_MISSING', 'message' => 'Database dump is missing from package.'];
            }

            $extract = tpBackupExtractZipEntryToFile($zip, $dbPath, $sqlDestFile);
            if (empty($extract['success'])) {
                return [
                    'success' => false,
                    'error_code' => 'DATABASE_EXTRACT_FAILED',
                    'message' => (string) ($extract['message'] ?? 'Unable to extract database dump.'),
                ];
            }

            $expectedDbHash = is_scalar($database['sha256'] ?? null) ? (string) $database['sha256'] : '';
            if ($expectedDbHash !== '' && hash_equals($expectedDbHash, tpBackupFileSha256($sqlDestFile)) === false) {
                @unlink($sqlDestFile);
                return ['success' => false, 'error_code' => 'DATABASE_CHECKSUM_INVALID', 'message' => 'Database checksum is invalid.'];
            }

            $documents = is_array($manifest['documents'] ?? null) ? $manifest['documents'] : [];
            $documentEntries = is_array($manifest['document_entries'] ?? null) ? $manifest['document_entries'] : [];
            $documentSummaryHash = is_scalar($documents['manifest_checksum'] ?? null) ? (string) $documents['manifest_checksum'] : '';
            if ($documentSummaryHash !== '') {
                $entriesJson = tpBackupCanonicalJson(['entries' => $documentEntries]);
                if ($entriesJson === '' || hash_equals($documentSummaryHash, hash('sha256', $entriesJson)) === false) {
                    @unlink($sqlDestFile);
                    return ['success' => false, 'error_code' => 'DOCUMENT_MANIFEST_CHECKSUM_INVALID', 'message' => 'Document manifest checksum is invalid.'];
                }
            }

            if ($allowDocumentsRestore === true) {
                foreach ($documentEntries as $entry) {
                    if (is_array($entry) === false) {
                        @unlink($sqlDestFile);
                        return ['success' => false, 'error_code' => 'DOCUMENT_ENTRY_INVALID', 'message' => 'Document entry is invalid.'];
                    }
                    $entryPath = tpBackupNormalizePackageEntryPath((string) ($entry['package_path'] ?? ''));
                    if ($entryPath === '' || str_starts_with($entryPath, 'documents/') === false || $zip->locateName($entryPath) === false) {
                        @unlink($sqlDestFile);
                        return ['success' => false, 'error_code' => 'DOCUMENT_ENTRY_MISSING', 'message' => 'A document entry is missing from package.'];
                    }
                    $expectedHash = is_scalar($entry['sha256'] ?? null) ? (string) $entry['sha256'] : '';
                    if ($expectedHash !== '' && hash_equals($expectedHash, tpBackupZipEntrySha256($zip, $entryPath)) === false) {
                        @unlink($sqlDestFile);
                        return ['success' => false, 'error_code' => 'DOCUMENT_CHECKSUM_INVALID', 'message' => 'A document checksum is invalid.'];
                    }
                }
            }

            return [
                'success' => true,
                'error_code' => '',
                'message' => '',
                'manifest' => $manifest,
                'manifest_checksum' => hash('sha256', $manifestJson),
                'warnings' => is_array($manifest['warnings'] ?? null) ? $manifest['warnings'] : [],
            ];
        } finally {
            $zip->close();
        }
}

/**
 * Decrypt a .tpbackup package and prepare its SQL dump for restore.
 *
 * @param array<int, string> $candidateKeys
 * @param array<string, mixed> $SETTINGS
 * @return array<string, mixed>
 */
function tpBackupPreparePackageSqlForRestore(
    string $packagePath,
    string $sqlDestFile,
    array $candidateKeys,
    array $SETTINGS = [],
    bool $allowDocumentsRestore = false,
    bool $keepDecryptedPackage = false
): array {
        $tmpZip = tpBackupRestoreTempPath('zip');
        if ($tmpZip === '') {
            return ['success' => false, 'error_code' => 'TEMP_FILE_FAILED', 'message' => 'Unable to create temporary package path.'];
        }
        $shouldKeepTmpZip = false;

        $dec = tpDefuseDecryptWithCandidates($packagePath, $tmpZip, $candidateKeys, $SETTINGS);
        if (empty($dec['success'])) {
            @unlink($tmpZip);
            return [
                'success' => false,
                'error_code' => 'DECRYPT_FAILED',
                'message' => (string) $dec['message'],
            ];
        }

        try {
            $validated = tpBackupValidatePackageZipAndExtractSql($tmpZip, $sqlDestFile, $allowDocumentsRestore);
            if (empty($validated['success'])) {
                return $validated;
            }

            $validated['key_used'] = (string) ($dec['key_used'] ?? '');
            if ($keepDecryptedPackage === true) {
                $shouldKeepTmpZip = true;
                $validated['decrypted_package_path'] = $tmpZip;
            }
            return $validated;
        } finally {
            if ($shouldKeepTmpZip === false) {
                @unlink($tmpZip);
            }
        }
}

/**
 * Resolve the on-disk target path for a package document entry during restore.
 *
 * @param array<string, mixed> $entry
 * @param array<string, mixed> $SETTINGS
 * @return array<string, mixed>
 */
function tpBackupResolveDocumentRestoreTarget(array $entry, array $SETTINGS = [], bool $createMissingDirs = true): array
{
        $kind = is_scalar($entry['kind'] ?? null) ? (string) $entry['kind'] : '';
        $packagePath = tpBackupNormalizePackageEntryPath((string) ($entry['package_path'] ?? ''));
        $storedName = tpBackupSafeStorageBasename((string) ($entry['stored_name'] ?? ''));
        if ($storedName === '' && $packagePath !== '') {
            $storedName = tpBackupSafeStorageBasename(basename($packagePath));
        }
        if ($packagePath === '' || $storedName === '') {
            return ['success' => false, 'error_code' => 'DOCUMENT_TARGET_INVALID', 'target_path' => ''];
        }

        $targetDir = '';
        if ($kind === 'item_attachment' && str_starts_with($packagePath, 'documents/item_attachments/') === true) {
            $targetDir = (string) ($SETTINGS['path_to_upload_folder'] ?? '');
        } elseif ($kind === 'kb_attachment' && str_starts_with($packagePath, 'documents/kb_attachments/') === true) {
            $filesDir = (string) ($SETTINGS['path_to_files_folder'] ?? '');
            $targetDir = rtrim($filesDir, '/\\') . DIRECTORY_SEPARATOR . 'kb_attachments';
        } elseif ($kind === 'user_avatar' && str_starts_with($packagePath, 'documents/user_avatars/') === true) {
            $targetDir = defined('TEAMPASS_ROOT')
                ? ((string) TEAMPASS_ROOT . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'avatars')
                : '';
        } else {
            return ['success' => false, 'error_code' => 'DOCUMENT_KIND_UNSUPPORTED', 'target_path' => ''];
        }

        if ($targetDir === '') {
            return ['success' => false, 'error_code' => 'DOCUMENT_TARGET_DIR_MISSING', 'target_path' => ''];
        }

        if (is_dir($targetDir) === false) {
            if ($createMissingDirs === false) {
                $parentReal = realpath(dirname($targetDir));
                if ($parentReal === false || is_dir($parentReal) === false || is_writable($parentReal) === false) {
                    return ['success' => false, 'error_code' => 'DOCUMENT_TARGET_DIR_MISSING', 'target_path' => ''];
                }

                $plannedDir = $parentReal . DIRECTORY_SEPARATOR . basename($targetDir);
                $targetPath = $plannedDir . DIRECTORY_SEPARATOR . $storedName;

                return [
                    'success' => true,
                    'error_code' => '',
                    'target_path' => $targetPath,
                    'target_dir' => $plannedDir,
                    'package_path' => $packagePath,
                ];
            }
            if (@mkdir($targetDir, 0700, true) === false && is_dir($targetDir) === false) {
                return ['success' => false, 'error_code' => 'DOCUMENT_TARGET_DIR_MISSING', 'target_path' => ''];
            }
        }

        $dirReal = realpath($targetDir);
        if ($dirReal === false || is_dir($dirReal) === false || is_writable($dirReal) === false) {
            return ['success' => false, 'error_code' => 'DOCUMENT_TARGET_DIR_NOT_WRITABLE', 'target_path' => ''];
        }

        $targetPath = $dirReal . DIRECTORY_SEPARATOR . $storedName;
        if (tpBackupIsResolvedPathInsideDirectory($targetPath, $dirReal) === false) {
            return ['success' => false, 'error_code' => 'DOCUMENT_TARGET_INVALID', 'target_path' => ''];
        }

        return [
            'success' => true,
            'error_code' => '',
            'target_path' => $targetPath,
            'target_dir' => $dirReal,
            'package_path' => $packagePath,
        ];
}

/**
 * Restore the document files contained in a .tpbackup package.
 *
 * @param array<string, mixed> $manifest
 * @param array<string, mixed> $SETTINGS
 * @return array<string, mixed>
 */
function tpBackupRestorePackageDocumentsFromZip(
    string $zipPath,
    array $manifest,
    array $SETTINGS = [],
    bool $allowExistingIdentical = true,
    bool $dryRun = false
): array {
        if (class_exists('ZipArchive') === false) {
            return ['success' => false, 'error_code' => 'ZIP_EXTENSION_MISSING', 'message' => 'ZipArchive is not available.'];
        }

        if (is_file($zipPath) === false || is_readable($zipPath) === false) {
            return ['success' => false, 'error_code' => 'PACKAGE_FILE_NOT_FOUND', 'message' => 'Package file not found.'];
        }

        $entries = is_array($manifest['document_entries'] ?? null) ? $manifest['document_entries'] : [];
        if (empty($entries) === true) {
            return [
                'success' => true,
                'error_code' => '',
                'message' => '',
                'restored_count' => 0,
                'skipped_existing_count' => 0,
                'failed' => [],
            ];
        }

        $zip = new ZipArchive();
        $open = $zip->open($zipPath);
        if ($open !== true) {
            return ['success' => false, 'error_code' => 'PACKAGE_OPEN_FAILED', 'message' => 'Unable to open package archive.'];
        }

        $restored = 0;
        $skipped = 0;
        $failed = [];

        try {
            foreach ($entries as $entry) {
                if (is_array($entry) === false) {
                    $failed[] = ['package_path' => '', 'error_code' => 'DOCUMENT_ENTRY_INVALID'];
                    continue;
                }

                $target = tpBackupResolveDocumentRestoreTarget($entry, $SETTINGS, $dryRun === false);
                if (empty($target['success'])) {
                    $failed[] = [
                        'package_path' => (string) ($entry['package_path'] ?? ''),
                        'error_code' => (string) ($target['error_code'] ?? 'DOCUMENT_TARGET_INVALID'),
                    ];
                    continue;
                }

                $packagePath = (string) $target['package_path'];
                $targetPath = (string) $target['target_path'];
                $targetDir = (string) $target['target_dir'];
                $expectedHash = is_scalar($entry['sha256'] ?? null) ? (string) $entry['sha256'] : '';

                if ($zip->locateName($packagePath) === false) {
                    $failed[] = ['package_path' => $packagePath, 'error_code' => 'DOCUMENT_ENTRY_MISSING'];
                    continue;
                }

                if (is_file($targetPath) === true) {
                    if (
                        $allowExistingIdentical === true
                        && $expectedHash !== ''
                        && hash_equals($expectedHash, tpBackupFileSha256($targetPath)) === true
                    ) {
                        $skipped++;
                        continue;
                    }

                    $failed[] = ['package_path' => $packagePath, 'error_code' => 'DOCUMENT_TARGET_EXISTS'];
                    continue;
                }

                if ($dryRun === true) {
                    if ($expectedHash !== '' && hash_equals($expectedHash, tpBackupZipEntrySha256($zip, $packagePath)) === false) {
                        $failed[] = ['package_path' => $packagePath, 'error_code' => 'DOCUMENT_CHECKSUM_INVALID'];
                        continue;
                    }
                    $restored++;
                    continue;
                }

                $tmpTarget = '';
                try {
                    $tmpTarget = $targetDir . DIRECTORY_SEPARATOR . '.tpbackup_restore_' . bin2hex(random_bytes(8)) . '.tmp';
                } catch (Throwable $ignored) {
                    $tmpTarget = $targetDir . DIRECTORY_SEPARATOR . '.tpbackup_restore_' . str_replace('.', '', uniqid('', true)) . '.tmp';
                }

                $extract = tpBackupExtractZipEntryToFile($zip, $packagePath, $tmpTarget);
                if (empty($extract['success'])) {
                    @unlink($tmpTarget);
                    $failed[] = [
                        'package_path' => $packagePath,
                        'error_code' => (string) ($extract['message'] ?? 'DOCUMENT_EXTRACT_FAILED'),
                    ];
                    continue;
                }

                if ($expectedHash !== '' && hash_equals($expectedHash, tpBackupFileSha256($tmpTarget)) === false) {
                    @unlink($tmpTarget);
                    $failed[] = ['package_path' => $packagePath, 'error_code' => 'DOCUMENT_CHECKSUM_INVALID'];
                    continue;
                }

                if (@rename($tmpTarget, $targetPath) === false) {
                    @unlink($tmpTarget);
                    $failed[] = ['package_path' => $packagePath, 'error_code' => 'DOCUMENT_RESTORE_FAILED'];
                    continue;
                }

                $restored++;
            }
        } finally {
            $zip->close();
        }

        return [
            'success' => empty($failed),
            'error_code' => empty($failed) ? '' : 'DOCUMENT_RESTORE_FAILED',
            'message' => empty($failed) ? '' : 'One or more documents could not be restored.',
            'restored_count' => $restored,
            'skipped_existing_count' => $skipped,
            'failed' => $failed,
        ];
}

// -----------------------------------------------------------------------------
// Backup metadata helpers (.meta.json sidecar) and schema token parsing
// -----------------------------------------------------------------------------
// NOTE: schema_level is stored for internal checks only. UI must never display schema_level.

function tpGetTpFilesVersion(): string
{
    if (defined('TP_VERSION') === false || defined('TP_VERSION_MINOR') === false) {
        return '';
    }

    return (string) TP_VERSION . '.' . (string) TP_VERSION_MINOR;
}

function tpGetSchemaLevel(): string
{
    if (defined('UPGRADE_MIN_DATE')) {
        $v = (string) UPGRADE_MIN_DATE;
        if (preg_match('/^\d+$/', $v) === 1) {
            return $v;
        }
    }
    return '';
}

function tpGetBackupMetadataPath(string $backupFilePath): string
{
    return $backupFilePath . '.meta.json';
}

/**
 * Write backup metadata sidecar file (<backup>.meta.json).
 *
 * @return array{success: bool, message: string, meta_path: string}
 */
function tpWriteBackupMetadata(string $backupFilePath, string $tpFilesVersion = '', string $schemaLevel = '', array $extra = []): array
{
        $metaPath = tpGetBackupMetadataPath($backupFilePath);

        if ($tpFilesVersion === '') {
            $tpFilesVersion = tpGetTpFilesVersion();
        }
        if ($schemaLevel === '') {
            $schemaLevel = tpGetSchemaLevel();
        }

        $payload = array_merge(
            [
                'tp_files_version' => $tpFilesVersion !== '' ? $tpFilesVersion : null,
                'schema_level' => $schemaLevel !== '' ? $schemaLevel : null,
                'created_at' => gmdate('c'),
            ],
            $extra
        );

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            return ['success' => false, 'message' => 'Unable to encode metadata as JSON', 'meta_path' => $metaPath];
        }

        $ok = @file_put_contents($metaPath, $json, LOCK_EX);
        if ($ok === false) {
            return ['success' => false, 'message' => 'Unable to write metadata file', 'meta_path' => $metaPath];
        }

        return ['success' => true, 'message' => '', 'meta_path' => $metaPath];
}


/**
 * Upsert backup metadata sidecar file (<backup>.meta.json) without dropping existing fields.
 *
 * @param array<string, mixed> $updates
 * @return array{success: bool, message: string, meta_path: string}
 */
function tpUpsertBackupMetadata(string $backupFilePath, array $updates): array
{
        $metaPath = tpGetBackupMetadataPath($backupFilePath);

        $current = [];
        if (is_file($metaPath)) {
            $current = tpReadBackupMetadata($backupFilePath);
        } else {
            // Ensure a minimal base payload for newly created meta file
            $current = [
                'tp_files_version' => ($v = tpGetTpFilesVersion()) !== '' ? $v : null,
                'schema_level' => ($sl = tpGetSchemaLevel()) !== '' ? $sl : null,
                'created_at' => gmdate('c'),
            ];
        }

        // Merge updates (explicit null allowed)
        foreach ($updates as $k => $v) {
            $current[$k] = $v;
        }

        $json = json_encode($current, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            return ['success' => false, 'message' => 'Unable to encode metadata as JSON', 'meta_path' => $metaPath];
        }

        $ok = @file_put_contents($metaPath, $json);
        if ($ok === false) {
            return ['success' => false, 'message' => 'Unable to write metadata file', 'meta_path' => $metaPath];
        }

        return ['success' => true, 'message' => 'OK', 'meta_path' => $metaPath];
}

function tpReadBackupMetadata(string $backupFilePath): array
{
        $metaPath = tpGetBackupMetadataPath($backupFilePath);
        if (!is_file($metaPath)) {
            return [];
        }
        $raw = @file_get_contents($metaPath);
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
}

function tpParseSchemaLevelFromBackupFilename(string $filename): string
{
    $bn = basename($filename);
    if (preg_match('/-sl(\d+)(?:\D|$)/', $bn, $m) === 1) {
        return (string) $m[1];
    }
    return '';
}

function tpGetBackupSchemaLevelFromMetaOrFilename(string $backupFilePath): string
{
    $meta = tpReadBackupMetadata($backupFilePath);
    if (!empty($meta['schema_level']) && is_scalar($meta['schema_level'])) {
        $v = (string) $meta['schema_level'];
        if (preg_match('/^\d+$/', $v) === 1) {
            return $v;
        }
    }
    $v = tpParseSchemaLevelFromBackupFilename($backupFilePath);
    return ($v !== '' && preg_match('/^\d+$/', $v) === 1) ? $v : '';
}

function tpGetBackupTpFilesVersionFromMeta(string $backupFilePath): string
{
    $meta = tpReadBackupMetadata($backupFilePath);
    if (!empty($meta['tp_files_version']) && is_scalar($meta['tp_files_version'])) {
        return (string) $meta['tp_files_version'];
    }
    return '';
}

/**
 * Read optional user comment from <backup>.meta.json.
 */
function tpGetBackupCommentFromMeta(string $backupFilePath): string
{
    $meta = tpReadBackupMetadata($backupFilePath);
    if (!empty($meta['comment']) && is_scalar($meta['comment'])) {
        return (string) $meta['comment'];
    }
    return '';
}

/**
 * -----------------------------------------------------------------------------
 * Orphan backup metadata (.meta.json) helpers
 * -----------------------------------------------------------------------------
 * A metadata file is considered orphan when <backup>.meta.json exists but the
 * corresponding <backup> file does not exist anymore.
 *
 * We use this to detect rare edge cases (crash / partial operations) and allow
 * admins to purge those orphan metadata files safely.
 */

function tpListOrphanBackupMetaFiles(string $dir): array
{
    $dir = rtrim($dir, '/\\');
    if ($dir === '' || is_dir($dir) === false) {
        return [];
    }

    $dirReal = realpath($dir);
    if ($dirReal === false || is_dir($dirReal) === false) {
        return [];
    }

    $paths = glob($dirReal . '/*.meta.json');
    if ($paths === false) {
        $paths = [];
    }

    $orphans = [];
    foreach ($paths as $metaPath) {
        if ($metaPath === '') {
            continue;
        }
        $basePath = substr($metaPath, 0, -strlen('.meta.json'));
        if ($basePath === '' || is_file($basePath) === true) {
            continue;
        }
        $orphans[] = $metaPath;
    }

    return $orphans;
}

/**
 * @param array $metaPaths list of full paths to *.meta.json files
 * @return array{deleted:int, failed:array}
 */
function tpPurgeOrphanBackupMetaFiles(array $metaPaths): array
{
    $deleted = 0;
    $failed = [];

    foreach ($metaPaths as $p) {
        if (!is_string($p) || $p === '') {
            continue;
        }
        if (is_file($p) === false) {
            continue;
        }
        $ok = @unlink($p);
        if ($ok === true) {
            $deleted++;
        } else {
            $failed[] = $p;
        }
    }

    return [
        'deleted' => $deleted,
        'failed' => $failed,
    ];
}


/**
 * -----------------------------------------------------------------------------
 * CLI Restore Authorization (one-shot token stored in teampass_misc)
 * -----------------------------------------------------------------------------
 * type    : restore_authorization_cli
 * intitule: sha256(token)
 * valeur  : JSON payload (status, initiator, file info, encrypted secrets)
 *
 * TTL fixed at 3600s (1 hour) for now.
 */

function tpRestoreAuthorizationCreate(
        int $userId,
        string $userLogin,
        string $filePath,
        string $serverScope,
        string $serverFile,
        int $operationId,
        string $encryptionKey,
        string $overrideKey,
        array $compat,
        int $ttl,
        array $SETTINGS,
        array $extraPayload = []
    ): array {
        $ttl = (int) $ttl;
        if ($ttl <= 0) {
            $ttl = 3600;
        }

        $now = time();

        try {
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $tokenHash = hash('sha256', $token);

        $real = realpath($filePath);
        $resolvedPath = ($real !== false) ? $real : $filePath;

        $payload = [
            'status' => 'pending',
            'created_at' => $now,
            'expires_at' => $now + $ttl,
            'ttl' => $ttl,
            'initiator' => [
                'id' => $userId,
                'login' => $userLogin,
            ],
            'file' => [
                'path' => $resolvedPath,
                'basename' => basename($resolvedPath),
                'size' => is_file($resolvedPath) ? filesize($resolvedPath) : null,
                'mtime' => is_file($resolvedPath) ? filemtime($resolvedPath) : null,
            ],
            'source' => [
                'serverScope' => $serverScope,
                'serverFile' => $serverFile,
                'operation_id' => $operationId,
            ],
            'compat' => $compat,
            'secrets' => [],
        ];

        if (is_array($extraPayload['restore_stage'] ?? null)) {
            $payload['restore_stage'] = $extraPayload['restore_stage'];
        }

        if ($encryptionKey !== '') {
            $enc = cryption($encryptionKey, '', 'encrypt', $SETTINGS);
            $payload['secrets']['encryptionKey'] = isset($enc['string']) ? (string) $enc['string'] : '';
        }

        if ($overrideKey !== '') {
            $enc = cryption($overrideKey, '', 'encrypt', $SETTINGS);
            $payload['secrets']['overrideKey'] = isset($enc['string']) ? (string) $enc['string'] : '';
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return ['success' => false, 'message' => 'json_encode_failed'];
        }

        // Insert token entry
        try {
            DB::insert(
                prefixTable('misc'),
                [
                    'type' => 'restore_authorization_cli',
                    'intitule' => $tokenHash,
                    'valeur' => $json,
                    'updated_at' => $now,
                ]
            );
            $id = (int) DB::insertId();
        } catch (Throwable $e) {
            // Fallback if updated_at column does not exist
            try {
                DB::insert(
                    prefixTable('misc'),
                    [
                        'type' => 'restore_authorization_cli',
                        'intitule' => $tokenHash,
                        'valeur' => $json,
                    ]
                );
                $id = (int) DB::insertId();
            } catch (Throwable $e2) {
                return ['success' => false, 'message' => $e2->getMessage()];
            }
        }

        return [
            'success' => true,
            'id' => $id,
            'token' => $token,
            'token_hash' => $tokenHash,
            'expires_at' => (int) $payload['expires_at'],
            'ttl' => $ttl,
        ];
}

function tpRestoreAuthorizationFetchByHash(string $tokenHash): array
{
        $row = DB::queryFirstRow(
            'SELECT increment_id, valeur FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %s LIMIT 1',
            'restore_authorization_cli',
            $tokenHash
        );

        if (empty($row)) {
            return ['success' => false, 'message' => 'not_found'];
        }

        $payload = json_decode((string) ($row['valeur'] ?? ''), true);
        if (!is_array($payload)) {
            return ['success' => false, 'message' => 'invalid_payload'];
        }

        return [
            'success' => true,
            'id' => (int) ($row['increment_id'] ?? 0),
            'payload' => $payload,
        ];
}

function tpRestoreAuthorizationUpdatePayload(int $id, array $payload): bool
{
        $payload['updated_at'] = time();
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        try {
            DB::update(
                prefixTable('misc'),
                [
                    'valeur' => $json,
                    'updated_at' => time(),
                ],
                'increment_id=%i AND type=%s',
                $id,
                'restore_authorization_cli'
            );
            return true;
        } catch (Throwable $e) {
            // Fallback if updated_at column does not exist
            try {
                DB::update(
                    prefixTable('misc'),
                    [
                        'valeur' => $json,
                    ],
                    'increment_id=%i AND type=%s',
                    $id,
                    'restore_authorization_cli'
                );
                return true;
            } catch (Throwable $e2) {
                return false;
            }
        }
}
