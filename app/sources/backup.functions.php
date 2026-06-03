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

function tpBackupGetPackageFormatId(): string
{
    return 'teampass.tpbackup';
}

function tpBackupGetPackageFormatVersion(): int
{
    return 1;
}

function tpBackupGetPackageExtension(): string
{
    return 'tpbackup';
}

function tpBackupIsPackageFilename(string $filename): bool
{
    return strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) === tpBackupGetPackageExtension();
}

function tpBackupNormalizeRequestedFormat(string $format): string
{
    $format = strtolower(trim($format));
    if ($format === '.tpbackup' || $format === tpBackupGetPackageExtension()) {
        return tpBackupGetPackageExtension();
    }

    return 'sql';
}

function tpBackupFileExtensionIsSupported(string $extension): bool
{
    return in_array(strtolower($extension), ['sql', tpBackupGetPackageExtension()], true);
}

function tpBackupGetDownloadTypeForFilename(string $filename): string
{
    return tpBackupIsPackageFilename($filename) ? tpBackupGetPackageExtension() : 'sql';
}

function tpRecoveryGetPackageFormatId(): string
{
    return 'teampass.recovery';
}

function tpRecoveryGetPackageFormatVersion(): int
{
    return 1;
}

function tpRecoveryGetPackageExtension(): string
{
    return 'tprecovery';
}

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

function tpRecoveryGetSettingsFilePath(): string
{
    if (defined('TEAMPASS_APP')) {
        return (string) TEAMPASS_APP . '/config/settings.php';
    }

    return realpath(__DIR__ . '/../config/settings.php') ?: (__DIR__ . '/../config/settings.php');
}

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
                'message' => 'Encryption failed: ' . (string) ($encryptResult['message'] ?? 'unknown error'),
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
    return ['local_directory'];
}

function tpBackupNormalizeExternalizedDestinationType(string $destinationType): string
{
    return strtolower(trim(str_replace("\0", '', $destinationType)));
}

function tpBackupExternalizedDestinationTypeIsSupported(string $destinationType): bool
{
    return in_array(
        tpBackupNormalizeExternalizedDestinationType($destinationType),
        tpBackupGetSupportedExternalizedDestinationTypes(),
        true
    );
}

function tpBackupResolveExternalizedDestinationType(string $destinationType): string
{
    $destinationType = tpBackupNormalizeExternalizedDestinationType($destinationType);
    if (tpBackupExternalizedDestinationTypeIsSupported($destinationType) === true) {
        return $destinationType;
    }

    return 'local_directory';
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

    return ['success' => false, 'path' => '', 'reason' => 'UNSUPPORTED_TYPE', 'destination_type' => $destinationType];
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

function tpBackupCanonicalJson(array $payload): string
{
    $json = json_encode(
        tpBackupNormalizeJsonValue($payload),
        JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );

    return is_string($json) ? $json : '';
}

function tpBackupFileSha256(string $path): string
{
    if (is_file($path) === false) {
        return '';
    }

    $hash = hash_file('sha256', $path);
    return is_string($hash) ? $hash : '';
}

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

function tpBackupRemoveTemporaryDirectory(string $path): bool
{
    $real = realpath($path);
    if ($real === false || is_dir($real) === false) {
        return true;
    }

    $isInsidePackageTempDir = false;
    $cursor = $real;
    while ($cursor !== '' && dirname($cursor) !== $cursor) {
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
                'message' => (string) ($dbBackup['message'] ?? 'Database backup creation failed.'),
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
                'message' => 'Encryption failed: ' . (string) ($encryptResult['message'] ?? 'unknown error'),
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
                'message' => (string) ($dec['message'] ?? 'Unable to decrypt package.'),
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
        array $SETTINGS
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
