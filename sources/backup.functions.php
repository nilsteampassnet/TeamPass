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
 * @file      backups.functions.php
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
     *
     * @return array{
     *   success: bool,
     *   filename: string,
     *   filepath: string,
     *   encrypted: bool,
     *   size_bytes: int,
     *   message: string
     * }
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

        $filename = $prefix . time() . '-' . $token . '.sql';
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
            fclose($handle);
            @unlink($filepath);

            return [
                'success' => false,
                'filename' => $filename,
                'filepath' => $filepath,
                'encrypted' => false,
                'size_bytes' => 0,
                'message' => 'Backup failed: ' . $e->getMessage(),
            ];
        }

        fclose($handle);

        // Encrypt the file if key provided
        $encrypted = false;
        if ($encryptionKey !== '') {
            $tmpPath = rtrim($outputDir, '/') . '/defuse_temp_' . $filename;

            if (!function_exists('prepareFileWithDefuse')) {
                @unlink($filepath);
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

            // prepareFileWithDefuse usually returns true on success, otherwise message/false
            if ($ret !== true) {
                @unlink($filepath);
                @unlink($tmpPath);
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
            @unlink($filepath);
            if (!@rename($tmpPath, $filepath)) {
                @unlink($tmpPath);
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

        $size = (int) (@filesize($filepath) ?: 0);

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'encrypted' => $encrypted,
            'size_bytes' => $size,
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
        string $encryptionKey,
        array $SETTINGS = []
    ): array {
        if (function_exists('prepareFileWithDefuse') === false) {
            return ['success' => false, 'message' => 'prepareFileWithDefuse() is not available'];
        }

        try {
            $ret = prepareFileWithDefuse($mode, $sourceFile, $destFile, $encryptionKey);

            if ($ret === true) {
                return ['success' => true, 'message' => ''];
            }

            if (is_array($ret)) {
                $hasError = !empty($ret['error']);
                $msg = tpSafeUtf8String((string)($ret['message'] ?? $ret['details'] ?? $ret['error'] ?? ''));
                return ['success' => !$hasError, 'message' => $msg];
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
            if (is_file($decryptedFile)) {
                @unlink($decryptedFile);
            }

            $r = tpPrepareFileWithDefuseNormalized('decrypt', $encryptedFile, $decryptedFile, $k, $SETTINGS);
            if (!empty($r['success'])) {
                return ['success' => true, 'message' => '', 'key_used' => $k];
            }
            $lastMsg = tpSafeUtf8String((string)($r['message'] ?? ''));
        }

        return ['success' => false, 'message' => ($lastMsg !== '' ? $lastMsg : 'Unable to decrypt')];
    }
}
