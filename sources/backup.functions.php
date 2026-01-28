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

if (!function_exists('tpCreateDatabaseBackup')) {
    /**
     * Create a Teampass database backup file (optionally encrypted) in files folder.
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
        // Ensure required dependencies are loaded
        $mainFunctionsPath = __DIR__ . '/main.functions.php';
        if ((!function_exists('GenerateCryptKey') || !function_exists('prefixTable')) && is_file($mainFunctionsPath)) {
            require_once $mainFunctionsPath;
        }
        if (function_exists('loadClasses') && !class_exists('DB')) {
            loadClasses('DB');
        }

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
        $token = function_exists('GenerateCryptKey')
            ? GenerateCryptKey(20, false, true, true, false, true)
            : bin2hex(random_bytes(10));

        // Schema level token in filename (used for compatibility checks during migrations)
$schemaLevel = '';
if (defined('UPGRADE_MIN_DATE')) {
    $schemaLevel = (string) UPGRADE_MIN_DATE;
}
if ($schemaLevel !== '' && preg_match('/^\d+$/', $schemaLevel) !== 1) {
    $schemaLevel = '';
}
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
}


// -----------------------------------------------------------------------------
// Helpers for restore logic (used by backups.queries.php)
// -----------------------------------------------------------------------------

if (function_exists('tpSafeUtf8String') === false) {
    /**
     * Ensure the returned string is valid UTF-8 and JSON-safe.
     *
     * Some crypto libraries can return messages containing non-UTF8 bytes.
     * Those would break json_encode() / prepareExchangedData().
     */
    function tpSafeUtf8String($value): string
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

        $isUtf8 = false;
        if (function_exists('mb_check_encoding')) {
            $isUtf8 = mb_check_encoding($str, 'UTF-8');
        } else {
            $isUtf8 = (@preg_match('//u', $str) === 1);
        }

        if ($isUtf8 === false) {
            // ASCII safe fallback
            return '[hex]' . bin2hex($str);
        }

        // Strip ASCII control chars
        $str = preg_replace("/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/", '', $str) ?? $str;
        return $str;
    }
}
if (function_exists('tpPrepareFileWithDefuseNormalized') === false) {
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
        if (function_exists('prepareFileWithDefuse') === false) {
            return ['success' => false, 'message' => 'prepareFileWithDefuse() is not available'];
        }

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
}

if (function_exists('tpDefuseDecryptWithCandidates') === false) {
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
            $lastMsg = tpSafeUtf8String((string)($r['message'] ?? ''));
        }

        return ['success' => false, 'message' => ($lastMsg !== '' ? $lastMsg : 'Unable to decrypt')];
    }
}

// -----------------------------------------------------------------------------
// Backup metadata helpers (.meta.json sidecar) and schema token parsing
// -----------------------------------------------------------------------------
// NOTE: schema_level is stored for internal checks only. UI must never display schema_level.

if (function_exists('tpGetTpFilesVersion') === false) {
    function tpGetTpFilesVersion(): string
    {
        if (defined('TP_VERSION') && defined('TP_VERSION_MINOR')) {
            return (string) TP_VERSION . '.' . (string) TP_VERSION_MINOR;
        }
        return '';
    }
}

if (function_exists('tpGetSchemaLevel') === false) {
    function tpGetSchemaLevel(): string
    {
        if (defined('UPGRADE_MIN_DATE')) {
            $v = (string) UPGRADE_MIN_DATE;
            if ($v !== '' && preg_match('/^\d+$/', $v) === 1) {
                return $v;
            }
        }
        return '';
    }
}

if (function_exists('tpGetBackupMetadataPath') === false) {
    function tpGetBackupMetadataPath(string $backupFilePath): string
    {
        return $backupFilePath . '.meta.json';
    }
}

if (function_exists('tpWriteBackupMetadata') === false) {
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
}

if (function_exists('tpReadBackupMetadata') === false) {
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
}

if (function_exists('tpParseSchemaLevelFromBackupFilename') === false) {
    function tpParseSchemaLevelFromBackupFilename(string $filename): string
    {
        $bn = basename($filename);
        if (preg_match('/-sl(\d+)(?:\D|$)/', $bn, $m) === 1) {
            return (string) $m[1];
        }
        return '';
    }
}

if (function_exists('tpGetBackupSchemaLevelFromMetaOrFilename') === false) {
    function tpGetBackupSchemaLevelFromMetaOrFilename(string $backupFilePath): string
    {
        $meta = tpReadBackupMetadata($backupFilePath);
        if (!empty($meta['schema_level']) && is_scalar($meta['schema_level'])) {
            $v = (string) $meta['schema_level'];
            if ($v !== '' && preg_match('/^\d+$/', $v) === 1) {
                return $v;
            }
        }
        $v = tpParseSchemaLevelFromBackupFilename($backupFilePath);
        return ($v !== '' && preg_match('/^\d+$/', $v) === 1) ? $v : '';
    }
}

if (function_exists('tpGetBackupTpFilesVersionFromMeta') === false) {
    function tpGetBackupTpFilesVersionFromMeta(string $backupFilePath): string
    {
        $meta = tpReadBackupMetadata($backupFilePath);
        if (!empty($meta['tp_files_version']) && is_scalar($meta['tp_files_version'])) {
            return (string) $meta['tp_files_version'];
        }
        return '';
    }
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

if (function_exists('tpListOrphanBackupMetaFiles') === false) {
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
            if (!is_string($metaPath) || $metaPath === '') {
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
}

if (function_exists('tpPurgeOrphanBackupMetaFiles') === false) {
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

if (function_exists('tpRestoreAuthorizationCreate') === false) {
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
}

if (function_exists('tpRestoreAuthorizationFetchByHash') === false) {
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
}

if (function_exists('tpRestoreAuthorizationUpdatePayload') === false) {
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
}

