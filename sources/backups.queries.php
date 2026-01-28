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
        include $SETTINGS['cpassman_dir'] . '/error.php';
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
        if ($v !== '' && preg_match('/^\d+$/', $v) === 1) return $v;
    }
    return '';
}

/**
 * Check restore compatibility (schema level).
 * - For server backups: reads schema_level from .meta.json when present, otherwise from "-sl<schema>" in filename.
 * - For uploaded restore file: reads schema from "-sl<schema>" preserved in filename stored in teampass_misc.
 *
 * @return array{is_compatible: bool, reason: string, backup_tp_files_version: ?string, expected_tp_files_version: string}
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
        $baseDir = rtrim((string) ($SETTINGS['path_to_files_folder'] ?? (__DIR__ . '/../files')), '/');
        $targetPath = $baseDir . '/' . $bn;

        if (function_exists('tpParseSchemaLevelFromBackupFilename')) {
            $backupSchema = (string) tpParseSchemaLevelFromBackupFilename($bn);
        }

        // Upload restore has no meta => version cannot be verified by design
        $warnings[] = 'VERSION_NOT_VERIFIED';
    } elseif ($serverFile !== '') {
        $bn = basename($serverFile);
        if ($bn === '' || strtolower(pathinfo($bn, PATHINFO_EXTENSION)) !== 'sql') {
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

        $baseDir = rtrim((string) ($SETTINGS['path_to_files_folder'] ?? (__DIR__ . '/../files')), '/');
        if ($serverScope === 'scheduled') {
            $baseFilesDir = (string) ($SETTINGS['path_to_files_folder'] ?? (__DIR__ . '/../files'));
            $dir = (string) tpGetSettingsValue('bck_scheduled_output_dir', rtrim($baseFilesDir, '/') . '/backups');
            $baseDir = rtrim($dir, '/');
        }
        $targetPath = $baseDir . '/' . $bn;

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
    if ($targetPath !== '' && file_exists($targetPath) === false) {
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
        if ($expectedVersion === '' || $backupVersion === null || (string) $backupVersion === '') {
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

            // Safety check: only .sql files allowed
            $extension = (string) pathinfo($get_file, PATHINFO_EXTENSION);
            if (strtolower($extension) !== 'sql') {
                header('HTTP/1.1 400 Bad Request');
                exit;
            }

            // Safety check: only scheduled-*.sql files allowed
            if ($get_file === '' || strpos($get_file, 'scheduled-') !== 0) {
                header('HTTP/1.1 400 Bad Request');
                exit;
            }

            $baseFilesDir = (string) ($SETTINGS['path_to_files_folder'] ?? (__DIR__ . '/../files'));
            $dir = (string) tpGetSettingsValue('bck_scheduled_output_dir', rtrim($baseFilesDir, '/') . '/backups');
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
        
            require_once __DIR__ . '/backup.functions.php';

            $backupResult = tpCreateDatabaseBackup($SETTINGS, $encryptionKey);

            if (($backupResult['success'] ?? false) !== true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $backupResult['message'] ?? 'Backup failed',
                    ),
                    'encode'
                );
                break;
            }

            $filename = $backupResult['filename'];
// Write metadata sidecar (<backup>.meta.json) for fast listings / migration safety
try {
    if (function_exists('tpWriteBackupMetadata') && !empty($backupResult['filepath'])) {
        tpWriteBackupMetadata((string)$backupResult['filepath'], '', '', ['source' => 'onthefly']);
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
                        '&action=backup&file=' . $filename . '&type=sql&key=' . $session->get('key') . '&key_tmp=' .
                        $session->get('user-key_tmp') . '&pathIsFiles=1',
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

            echo prepareExchangedData([
                'error' => false,
                'settings' => [
                    'enabled' => (int) tpGetSettingsValue('bck_scheduled_enabled', '0'),
                    'frequency' => (string) tpGetSettingsValue('bck_scheduled_frequency', 'daily'),
                    'time' => (string) tpGetSettingsValue('bck_scheduled_time', '02:00'),
                    'dow' => (int) tpGetSettingsValue('bck_scheduled_dow', '1'),
                    'dom' => (int) tpGetSettingsValue('bck_scheduled_dom', '1'),
                    'output_dir' => (string) tpGetSettingsValue('bck_scheduled_output_dir', ''),
                    'retention_days' => (int) tpGetSettingsValue('bck_scheduled_retention_days', '30'),

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

            $baseFilesDir = (string)($SETTINGS['path_to_files_folder'] ?? (__DIR__ . '/../files'));
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

        
        case 'copy_instance_key':
            // Return decrypted instance key (admin only)
            if ($post_key !== $session->get('key') || (int) $session->get('user-admin') !== 1) {
                echo prepareExchangedData(
                    array('error' => true, 'message' => $lang->get('error_not_allowed_to')),
                    'encode'
                );
                break;
            }

            if (empty($SETTINGS['bck_script_passkey'] ?? '') === true) {
                echo prepareExchangedData(
                    array('error' => true, 'message' => $lang->get('bck_instance_key_not_set')),
                    'encode'
                );
                break;
            }

            $tmp = cryption($SETTINGS['bck_script_passkey'], '', 'decrypt', $SETTINGS);
            $instanceKey = isset($tmp['string']) ? (string) $tmp['string'] : '';
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

            // Output dir: default to <files>/backups
            $baseFilesDir = (string)($SETTINGS['path_to_files_folder'] ?? (__DIR__ . '/../files'));
            $defaultDir = rtrim($baseFilesDir, '/') . '/backups';

            $outputDir = trim((string)($dataReceived['output_dir'] ?? ''));
            if ($outputDir === '') $outputDir = $defaultDir;

            // Safety: prevent path traversal / outside files folder
            @mkdir($outputDir, 0770, true);
            $baseReal = realpath($baseFilesDir) ?: $baseFilesDir;
            $dirReal = realpath($outputDir);

            if ($dirReal === false || strpos($dirReal, $baseReal) !== 0) {
                echo prepareExchangedData(['error' => true, 'message' => 'Invalid output directory'], 'encode');
                break;
            }

            tpUpsertSettingsValue('bck_scheduled_enabled', (string)$enabled);
            tpUpsertSettingsValue('bck_scheduled_frequency', $frequency);
            tpUpsertSettingsValue('bck_scheduled_time', $timeStr);
            tpUpsertSettingsValue('bck_scheduled_dow', (string)$dow);
            tpUpsertSettingsValue('bck_scheduled_dom', (string)$dom);
            tpUpsertSettingsValue('bck_scheduled_output_dir', $dirReal);
            tpUpsertSettingsValue('bck_scheduled_retention_days', (string)$retentionDays);
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

            $baseFilesDir = (string)($SETTINGS['path_to_files_folder'] ?? (__DIR__ . '/../files'));
            $dir = (string)tpGetSettingsValue('bck_scheduled_output_dir', rtrim($baseFilesDir, '/') . '/backups');
            @mkdir($dir, 0770, true);
            // Build a relative path from files/ root (output_dir can be a subfolder)
            $filesRoot = realpath($baseFilesDir);
            $dirReal = realpath($dir);
            $relDir = '';
            if ($filesRoot !== false && $dirReal !== false && strpos($dirReal, $filesRoot) === 0) {
                $relDir = trim(str_replace($filesRoot, '', $dirReal), DIRECTORY_SEPARATOR);
                $relDir = str_replace(DIRECTORY_SEPARATOR, '/', $relDir);
            }

            // Ensure we have a temporary key for downloadFile.php
            $keyTmp = (string) $session->get('user-key_tmp');
            if ($keyTmp === '') {
                $keyTmp = GenerateCryptKey(16, false, true, true, false, true);
                $session->set('user-key_tmp', $keyTmp);
            }


            $files = [];
            foreach (glob(rtrim($dir, '/') . '/scheduled-*.sql') ?: [] as $fp) {
                $bn = basename($fp);
                $files[] = [
                    'name' => $bn,
                    'size_bytes' => (int)@filesize($fp),
                    'mtime' => (int)@filemtime($fp),
                    'tp_files_version' => (function_exists('tpGetBackupTpFilesVersionFromMeta') ? ((($v = (string)tpGetBackupTpFilesVersionFromMeta($fp)) !== '') ? $v : null) : null),
                    'download' => 'sources/backups.queries.php?type=scheduled_download_backup&file=' . urlencode($bn)
                        . '&key=' . urlencode((string) $session->get('key'))
                        . '&key_tmp=' . urlencode($keyTmp),
                ];
            }

            usort($files, fn($a, $b) => ($b['mtime'] ?? 0) <=> ($a['mtime'] ?? 0));

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

            if ($file === '' || strpos($file, 'scheduled-') !== 0 || !str_ends_with($file, '.sql')) {
                echo prepareExchangedData(['error' => true, 'message' => 'Invalid filename'], 'encode');
                break;
            }

            $baseFilesDir = (string)($SETTINGS['path_to_files_folder'] ?? (__DIR__ . '/../files'));
            $dir = (string)tpGetSettingsValue('bck_scheduled_output_dir', rtrim($baseFilesDir, '/') . '/backups');
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
            $baseFilesDir = (string)($SETTINGS['path_to_files_folder'] ?? (__DIR__ . '/../files'));
            $dir = (string)tpGetSettingsValue('bck_scheduled_output_dir', rtrim($baseFilesDir, '/') . '/backups');
            @mkdir($dir, 0770, true);

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
                    'arguments' => json_encode(['output_dir' => $dir, 'source' => 'scheduler', 'initiator_user_id' => (int) $session->get('user-id')], JSON_UNESCAPED_SLASHES),
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
            // Delete an on-the-fly backup file stored in <files> directory
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
            if ($bn === '' || strtolower(pathinfo($bn, PATHINFO_EXTENSION)) !== 'sql') {
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

            $baseFilesDir = (string)($SETTINGS['path_to_files_folder'] ?? (__DIR__ . '/../files'));
            $dir = rtrim($baseFilesDir, '/');
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


        case 'onthefly_check_upload_filename':
            // Pre-check before uploading a restore SQL file to <files>.
            // We refuse uploading a file if its ORIGINAL name already exists in <files>,
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

            $baseFilesDir = (string) ($SETTINGS['path_to_files_folder'] ?? (__DIR__ . '/../files'));
            $dir = rtrim($baseFilesDir, '/');
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
            // - <files> (on-the-fly)
            // - <files>/backups (scheduled)
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

            $baseFilesDir = (string) ($SETTINGS['path_to_files_folder'] ?? (__DIR__ . '/../files'));
            $filesDir = rtrim($baseFilesDir, '/');
            $scheduledDir = $filesDir . '/backups';

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
            // Purge orphan *.meta.json files in <files> and <files>/backups (scheduled)
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

            $baseFilesDir = (string) ($SETTINGS['path_to_files_folder'] ?? (__DIR__ . '/../files'));
            $filesDir = rtrim($baseFilesDir, '/');
            $scheduledDir = $filesDir . '/backups';

            $orphFiles = function_exists('tpListOrphanBackupMetaFiles') ? tpListOrphanBackupMetaFiles($filesDir) : [];
            $orphSched = function_exists('tpListOrphanBackupMetaFiles') ? tpListOrphanBackupMetaFiles($scheduledDir) : [];

            $all = array_merge($orphFiles, $orphSched);
            $res = function_exists('tpPurgeOrphanBackupMetaFiles') ? tpPurgeOrphanBackupMetaFiles($all) : ['deleted' => 0, 'failed' => []];

            echo prepareExchangedData(
                [
                    'error' => false,
                    'deleted' => (int) ($res['deleted'] ?? 0),
                    'failed' => $res['failed'] ?? [],
                ],
                'encode'
            );
            break;

        case 'onthefly_list_backups':
            // List on-the-fly backup files stored directly in <files> directory (not in /backups for scheduled)
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

            $baseFilesDir = (string)($SETTINGS['path_to_files_folder'] ?? (__DIR__ . '/../files'));
            $dir = rtrim($baseFilesDir, '/');

            $files = array();
            $paths = glob($dir . '/*.sql');
            if ($paths === false) {
                $paths = array();
            }

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

                $files[] = array(
                    'name' => $bn,
                    'size_bytes' => (int)@filesize($fp),
                    'mtime' => (int)@filemtime($fp),
                    'tp_files_version' => (function_exists('tpGetBackupTpFilesVersionFromMeta') ? ((($v = (string)tpGetBackupTpFilesVersionFromMeta($fp)) !== '') ? $v : null) : null),
                    'download' => 'sources/downloadFile.php?name=' . urlencode($bn) .
                        '&action=backup&file=' . urlencode($bn) .
                        '&type=sql&key=' . $session->get('key') .
                        '&key_tmp=' . $session->get('user-key_tmp') .
                        '&pathIsFiles=1',
                );
            }

            usort($files, static function ($a, $b) {
                return ($b['mtime'] ?? 0) <=> ($a['mtime'] ?? 0);
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
                    'is_compatible' => (bool) ($chk['is_compatible'] ?? false),
                    'reason' => (string) ($chk['reason'] ?? ''),
                    'mode' => (string) ($chk['mode'] ?? ''),
                    'warnings' => $chk['warnings'] ?? [],
                    'backup_schema_level' => (string) ($chk['backup_schema_level'] ?? ''),
                    'expected_schema_level' => (string) ($chk['expected_schema_level'] ?? ''),
                    'backup_tp_files_version' => $chk['backup_tp_files_version'] ?? null,
                    'expected_tp_files_version' => (string) ($chk['expected_tp_files_version'] ?? ''),
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
                        echo prepareExchangedData(
                            [
                                'error' => true,
                                'error_code' => 'INCOMPATIBLE_BACKUP',
                                'reason' => (string) ($chk['reason'] ?? ''),
                                'mode' => (string) ($chk['mode'] ?? ''),
                                'warnings' => $chk['warnings'] ?? [],
                                'backup_schema_level' => (string) ($chk['backup_schema_level'] ?? ''),
                                'expected_schema_level' => (string) ($chk['expected_schema_level'] ?? ''),
                                'backup_tp_files_version' => $chk['backup_tp_files_version'] ?? null,
                                'expected_tp_files_version' => (string) ($chk['expected_tp_files_version'] ?? ''),
                                'message' => $lang->get('bck_restore_incompatible_version_body'),
                            ],
                            'encode'
                        );
                        break;
                    }

                    $resolvedPath = (string) ($chk['resolved_path'] ?? '');
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
                    // - scheduled: uses the instance key (stored in bck_script_passkey) + optional override key
                    // - on-the-fly: uses the key provided by the UI
                    // - upload (serverScope empty): can be either, so also try instance key candidates
                    if ($serverScope === 'scheduled') {
                        if ($overrideKey !== '') {
                            $keysToTry[] = $overrideKey;
                        }

                        if (!empty($SETTINGS['bck_script_passkey'] ?? '')) {
                            $rawInstanceKey = (string) $SETTINGS['bck_script_passkey'];
                            $tmp = cryption($rawInstanceKey, '', 'decrypt', $SETTINGS);
                            $decInstanceKey = isset($tmp['string']) ? (string) $tmp['string'] : '';

                            if ($decInstanceKey !== '') {
                                $keysToTry[] = $decInstanceKey;
                            }
                            // Some environments store bck_script_passkey already decrypted (or in another format)
                            if ($rawInstanceKey !== '' && $rawInstanceKey !== $decInstanceKey) {
                                $keysToTry[] = $rawInstanceKey;
                            }
                        }
                    } else {
                        if ($encryptionKey !== '') {
                            $keysToTry[] = $encryptionKey;
                        }
                        if ($overrideKey !== '') {
                            $keysToTry[] = $overrideKey;
                        }

                        // For uploaded restores (serverScope is empty), also try the instance key candidates.
                        // This allows restoring scheduled backups uploaded manually (they are encrypted using bck_script_passkey).
                        if ($serverScope === '' && !empty($SETTINGS['bck_script_passkey'] ?? '')) {
                            $rawInstanceKey = (string) $SETTINGS['bck_script_passkey'];
                            $tmp = cryption($rawInstanceKey, '', 'decrypt', $SETTINGS);
                            $decInstanceKey = isset($tmp['string']) ? (string) $tmp['string'] : '';

                            if ($decInstanceKey !== '') {
                                $keysToTry[] = $decInstanceKey;
                            }
                            if ($rawInstanceKey !== '' && $rawInstanceKey !== $decInstanceKey) {
                                $keysToTry[] = $rawInstanceKey;
                            }
                        }
                    }

                    $keysToTry = array_values(array_unique(array_filter($keysToTry, function ($v) {
                        return $v !== '';
                    })));

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

                    $tmpDecryptedSql = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
                        . 'defuse_temp_restore_' . (int) ($session->get('user-id') ?? 0) . '_' . time() . '_' . $tmpRand . '.sql';

                    // Try to decrypt with the available keys
                    $decRet = tpDefuseDecryptWithCandidates($resolvedPath, $tmpDecryptedSql, $keysToTry, $SETTINGS);
                    @unlink($tmpDecryptedSql);

                    if (empty($decRet['success'])) {
                        echo prepareExchangedData(
                            [
                                'error' => true,
                                'error_code' => 'DECRYPT_FAILED',
                                'message' => (string) ($decRet['message'] ?? 'Unable to decrypt backup with provided key(s).'),
                            ],
                            'encode'
                        );
                        break;
                    }

// Create authorization token in DB (teampass_misc)
                    $compat = [
                        'mode' => (string) ($chk['mode'] ?? ''),
                        'warnings' => $chk['warnings'] ?? [],
                        'backup_schema_level' => (string) ($chk['backup_schema_level'] ?? ''),
                        'expected_schema_level' => (string) ($chk['expected_schema_level'] ?? ''),
                        'backup_tp_files_version' => $chk['backup_tp_files_version'] ?? null,
                        'expected_tp_files_version' => (string) ($chk['expected_tp_files_version'] ?? ''),
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

                    $tpRoot = (string) ($SETTINGS['cpassman_dir'] ?? '');

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

					$scriptRel = $isWindows ? 'scripts\\restore.php' : 'scripts/restore.php';
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

					$cmd = $cmdNoCd;
					$cmdForce = $cmdNoCdForce;
					if ($tpRoot !== '') {
						if ($isWindows) {
							$cmd = 'cd /d ' . escapeshellarg($rootForCmd) . ' && ' . $cmdNoCd;
							$cmdForce = 'cd /d ' . escapeshellarg($rootForCmd) . ' && ' . $cmdNoCdForce;
						} else {
							$cmd = 'cd ' . escapeshellarg($rootForCmd) . ' && ' . $cmdNoCd;
							$cmdForce = 'cd ' . escapeshellarg($rootForCmd) . ' && ' . $cmdNoCdForce;
						}
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
						$cmdNoSudo = 'php scripts/restore.php'
							. ' --file ' . escapeshellarg($resolvedPath)
							. ' --auth-token ' . escapeshellarg($token);
						$cmdNoSudoCd = ($tpRoot !== '')
							? ('cd ' . escapeshellarg($rootForCmd) . ' && ' . $cmdNoSudo)
							: $cmdNoSudo;
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
                            'mode' => (string) ($chk['mode'] ?? ''),
                            'warnings' => $chk['warnings'] ?? [],
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

// Compatibility check (schema-level) BEFORE maintenance/lock and any destructive action
            $dataEarly = prepareExchangedData($post_data, 'decode');
            if (!is_array($dataEarly)) $dataEarly = [];

            $earlyOffset = (int) ($dataEarly['offset'] ?? 0);
            $earlyClear = (string) ($dataEarly['clearFilename'] ?? '');
            $earlyServerScope = (string) ($dataEarly['serverScope'] ?? '');
            $earlyServerFile  = (string) ($dataEarly['serverFile']  ?? '');
            $earlyBackupFile  = (string) ($dataEarly['backupFile']  ?? '');
            $earlyOperationId = (int) ($dataEarly['operation_id'] ?? 0);

            // If restore is starting (first chunk), enforce schema compatibility.
            if ($earlyOffset === 0 && $earlyClear === '') {
                // Operation id can be passed either as operation_id or as legacy backupFile numeric id
                if ($earlyOperationId === 0 && $earlyBackupFile !== '' && ctype_digit($earlyBackupFile)) {
                    $earlyOperationId = (int) $earlyBackupFile;
                }

                $chk = tpCheckRestoreCompatibility($SETTINGS, $earlyServerScope, $earlyServerFile, $earlyOperationId);
                if (($chk['is_compatible'] ?? false) !== true) {
                    echo prepareExchangedData(
                        [
                            'error' => true,
                            'error_code' => 'INCOMPATIBLE_BACKUP_SCHEMA',
                            'reason' => (string) ($chk['reason'] ?? ''),
                            'backup_tp_files_version' => $chk['backup_tp_files_version'] ?? null,
                            'expected_tp_files_version' => (string) ($chk['expected_tp_files_version'] ?? ''),
                            'message' => $lang->get('bck_restore_incompatible_version_body'),
                        ],
                        'encode'
                    );
                    break;
                }
            }
            // Put TeamPass in maintenance mode for the whole restore workflow.
            // Intentionally NOT disabled at the end: admin must validate the instance after restore.
            try {
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
            } catch (Throwable $ignored) {
                // Best effort
            }

            // Decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );
        
            // Prepare variables (safe defaults for both upload-restore and serverFile-restore)
            $post_encryptionKey = filter_var(($dataReceived['encryptionKey'] ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            // Optional override key (mainly for scheduled restores in case of migration)
            // This MUST NOT be auto-filled from the on-the-fly key; it is only used when explicitly provided.
            $post_overrideKey = filter_var(($dataReceived['overrideKey'] ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $post_backupFile = filter_var(($dataReceived['backupFile'] ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_clearFilename = filter_var(($dataReceived['clearFilename'] ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $post_serverScope = filter_var(($dataReceived['serverScope'] ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_serverFile  = filter_var(($dataReceived['serverFile']  ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            // Scheduled backups must always be decrypted with the instance key (server-side).
            // Ignore any key coming from the UI to avoid mismatches.
            if ($post_serverScope === 'scheduled') {
                $post_encryptionKey = '';
            }
            // Ensure all strings we send back through prepareExchangedData() are JSON-safe.
            // This avoids PHP "malformed UTF-8" warnings when restore errors contain binary/latin1 bytes.
            $tpSafeJsonString = static function ($value): string {
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

                // If the string isn't valid UTF-8, return a hex dump instead (ASCII-only, safe for JSON).
                $isUtf8 = false;
                if (function_exists('mb_check_encoding')) {
                    $isUtf8 = mb_check_encoding($str, 'UTF-8');
                } else {
                    $isUtf8 = (@preg_match('//u', $str) === 1);
                }
                if ($isUtf8 === false) {
                    return '[hex]' . bin2hex($str);
                }

                // Strip ASCII control chars that could pollute JSON.
                $str = preg_replace("/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/", '', $str) ?? $str;
                return $str;
            };


            // Chunked upload fields (can be absent when restoring from an existing server file)
            $post_offset = (int) ($dataReceived['offset'] ?? 0);
            $post_totalSize = (int) ($dataReceived['totalSize'] ?? 0);

            // Restore session + concurrency lock management.
            // - We keep a token in session to allow chunked restore even while DB is being replaced.
            // - We also block starting a second restore in the same session (double click / 2 tabs).
            $clearRestoreState = static function ($session): void {
                $tmp = (string) ($session->get('restore-temp-file') ?? '');
                if ($tmp !== '' && file_exists($tmp) === true && strpos(basename($tmp), 'defuse_temp_restore_') === 0 && is_file($tmp)) {
                    if (is_writable($tmp)) {
                        if (unlink($tmp) === false && WIP === true) {
                            error_log("TeamPass: Failed to delete file: {$tmp}");
                        }
                    } else if (WIP === true) {
                        error_log("TeamPass: File is not writable, cannot delete: {$tmp}");
                    }
                }
                $session->set('restore-temp-file', '');
                $session->set('restore-token', '');
                $session->set('restore-settings', []);
                $session->set('restore-context', []);
                $session->set('restore-in-progress', false);
                $session->set('restore-in-progress-ts', 0);
                $session->set('restore-start-ts', 0);
            };

            $nowTs = time();
            $inProgress = (bool) ($session->get('restore-in-progress') ?? false);
            $lastTs = (int) ($session->get('restore-in-progress-ts') ?? 0);

            // Auto-release stale lock (e.g. client crashed / browser closed)
            if ($inProgress === true && $lastTs > 0 && ($nowTs - $lastTs) > 3600) {
                $clearRestoreState($session);
                $inProgress = false;
                $lastTs = 0;
            }

            $sessionRestoreToken = (string) ($session->get('restore-token') ?? '');
            $isStartRestore = (empty($post_clearFilename) === true && (int) $post_offset === 0);

            if ($isStartRestore === true) {
                if ($inProgress === true && $sessionRestoreToken !== '') {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => 'A restore is already in progress in this session. Please wait for it to complete (or logout/login to reset).',
                        ),
                        'encode'
                    );
                    break;
                }

                $sessionRestoreToken = bin2hex(random_bytes(16));
                $session->set('restore-token', $sessionRestoreToken);
                $session->set('restore-settings', $SETTINGS);
                $session->set('restore-in-progress', true);
                $session->set('restore-in-progress-ts', $nowTs);
                $session->set('restore-start-ts', $nowTs);
                $session->set('restore-context', []);
            } else {
                // Restore continuation must provide the correct token
                if ($restoreToken === '' || $sessionRestoreToken === '' || !hash_equals($sessionRestoreToken, $restoreToken)) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => 'Restore session expired. Please restart the restore process.',
                        ),
                        'encode'
                    );
                    break;
                }

                // Update activity timestamp (keeps the lock alive)
                $session->set('restore-in-progress', true);
                $session->set('restore-in-progress-ts', $nowTs);
                if ((int) ($session->get('restore-start-ts') ?? 0) === 0) {
                    $session->set('restore-start-ts', $nowTs);
                }
            }

            $batchSize = 500;
            $errors = array(); // Collect potential errors
        
            // Check if the offset is greater than the total size
            if ($post_offset > 0 && $post_totalSize > 0 && $post_offset >= $post_totalSize) {
                // Defensive: if client asks to continue beyond end, consider restore finished and release lock.
                if (is_string($post_clearFilename) && $post_clearFilename !== '' && file_exists($post_clearFilename) === true
                    && strpos(basename($post_clearFilename), 'defuse_temp_restore_') === 0) {
                    @unlink($post_clearFilename);
                }
                $clearRestoreState($session);

                echo prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => 'operation_finished',
                        'finished' => true,
                        'restore_token' => $sessionRestoreToken,
                    ),
                    'encode'
                );
                break;
            }
            
            // Log debug information if in development mode
            if (defined('WIP') && WIP === true) {
                error_log('DEBUG: Offset -> '.$post_offset.'/'.$post_totalSize.' | File -> '.$post_clearFilename);
            }
        
            include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
        
            include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

            /*
             * Restore workflow
             * - 1st call (clearFilename empty): locate encrypted backup, decrypt to a temp file, return its path in clearFilename
             * - next calls: reuse clearFilename as the decrypted file path and continue reading from offset
             */

            if (empty($post_clearFilename) === true) {
                // Default behavior: uploaded on-the-fly backup file (stored in teampass_misc) is removed after decrypt/restore.
                // New behavior: user can select an existing encrypted backup file already present on server (scheduled or on-the-fly stored file).
                $deleteEncryptedAfterDecrypt = true;

                $bn = '';
                $serverPath = '';
                $legacyOperationId = null;

                // NEW: restore from an existing server file (scheduled or on-the-fly stored file)
                if (!empty($post_serverFile)) {
                    $deleteEncryptedAfterDecrypt = false;

                    $bn = basename((string) $post_serverFile);

                    // Safety: allow only *.sql name
                    if ($bn === '' || strtolower(pathinfo($bn, PATHINFO_EXTENSION)) !== 'sql') {
                        $clearRestoreState($session);
                        echo prepareExchangedData(
                            array('error' => true, 'message' => 'Invalid serverFile'),
                            'encode'
                        );
                        break;
                    }

                    $baseDir = rtrim((string) $SETTINGS['path_to_files_folder'], '/');

                    // Scheduled backups are stored in configured output directory
                    if ($post_serverScope === 'scheduled') {
                        $baseFilesDir = (string) ($SETTINGS['path_to_files_folder'] ?? (__DIR__ . '/../files'));
                        $dir = (string) tpGetSettingsValue('bck_scheduled_output_dir', rtrim($baseFilesDir, '/') . '/backups');
                        $baseDir = rtrim($dir, '/');
                    }

                    $serverPath = $baseDir . '/' . $bn;

                    if (file_exists($serverPath) === false) {
                        try {
                            logEvents(
                                $SETTINGS,
                                'admin_action',
                                'dataBase restore failed (file not found)',
                                (string) $session->get('user-id'),
                                $session->get('user-login')
                            );
                        } catch (Throwable $ignored) {
                            // ignore logging errors
                        }
                        $clearRestoreState($session);
                        echo prepareExchangedData(
                            array('error' => true, 'message' => 'Backup file not found on server'),
                            'encode'
                        );
                        break;
                    }
                
                    // Log restore start once (best effort)
                    if ($isStartRestore === true) {
                        $sizeBytes = (int) @filesize($serverPath);
                        $session->set(
                            'restore-context',
                            array(
                                'scope' => $post_serverScope,
                                'backup' => $bn,
                                'size_bytes' => $sizeBytes,
                            )
                        );

                        try {
                            $msg = 'dataBase restore started (scope=' . $post_serverScope . ', file=' . $bn . ')';
                            logEvents(
                                $SETTINGS,
                                'admin_action',
                                $msg,
                                (string) $session->get('user-id'),
                                $session->get('user-login')
                            );
                        } catch (Throwable $ignored) {
                            // ignore logging errors during restore
                        }
                    }

                } else {
                    // LEGACY: restore from uploaded on-the-fly backup identified by its misc.increment_id
                    if (empty($post_backupFile) === true || ctype_digit((string) $post_backupFile) === false) {
                        echo prepareExchangedData(
                            array('error' => true, 'message' => 'No backup selected'),
                            'encode'
                        );
                        break;
                    }

                    $legacyOperationId = (int) $post_backupFile;

                    // Find filename from DB (misc)
                    $data = DB::queryFirstRow(
                        'SELECT valeur FROM ' . prefixTable('misc') . ' WHERE increment_id = %i LIMIT 1',
                        $legacyOperationId
                    );

                    if (empty($data['valeur'])) {
                        try {
                            logEvents(
                                $SETTINGS,
                                'admin_action',
                                'dataBase restore failed (missing misc entry)',
                                (string) $session->get('user-id'),
                                $session->get('user-login')
                            );
                        } catch (Throwable $ignored) {
                            // ignore logging errors
                        }
                        $clearRestoreState($session);
                        echo prepareExchangedData(
                            array('error' => true, 'message' => 'Backup file not found in database'),
                            'encode'
                        );
                        break;
                    }

                    $bn = safeString($data['valeur']);
                    $serverPath = rtrim((string) $SETTINGS['path_to_files_folder'], '/') . '/' . $bn;

                    if (file_exists($serverPath) === false) {
                        try {
                            logEvents(
                                $SETTINGS,
                                'admin_action',
                                'dataBase restore failed (file not found)',
                                (string) $session->get('user-id'),
                                $session->get('user-login')
                            );
                        } catch (Throwable $ignored) {
                            // ignore logging errors
                        }
                        $clearRestoreState($session);
                        echo prepareExchangedData(
                            array('error' => true, 'message' => 'Backup file not found on server'),
                            'encode'
                        );
                        break;
                    }
                }

                // Common checks
                if ($post_serverScope !== 'scheduled' && empty($post_encryptionKey) === true) {
                    echo prepareExchangedData(
                        array('error' => true, 'message' => 'Missing encryption key'),
                        'encode'
                    );
                    break;
                }

                // Decrypt to a dedicated temp file (unique)
                $tmpDecrypted = rtrim((string) $SETTINGS['path_to_files_folder'], '/')
                    . '/defuse_temp_restore_' . (int) $session->get('user-id') . '_' . time() . '_' . $bn;

                // Build the list of keys we can try to decrypt with.
                // - on-the-fly: uses the key provided by the UI
                // - scheduled: uses the instance key (stored in bck_script_passkey)
                $keysToTry = [];
                if ($post_serverScope === 'scheduled') {
                    // Allow an explicit override key (migration use-case)
                    if (!empty($post_overrideKey)) {
                        $keysToTry[] = (string) $post_overrideKey;
                    }
                    if (!empty($SETTINGS['bck_script_passkey'] ?? '')) {
                        $rawInstanceKey = (string) $SETTINGS['bck_script_passkey'];
                        $tmp = cryption($rawInstanceKey, '', 'decrypt', $SETTINGS);
                        $decInstanceKey = isset($tmp['string']) ? (string) $tmp['string'] : '';

                        if ($decInstanceKey !== '') {
                            $keysToTry[] = $decInstanceKey;
                        }
                        if ($rawInstanceKey !== '' && $rawInstanceKey !== $decInstanceKey) {
                            $keysToTry[] = $rawInstanceKey;
                        }
                    }
                } else {
                    if ($post_encryptionKey !== '') {
                        $keysToTry[] = (string) $post_encryptionKey;
                    }
                

                    // For uploaded restores (serverScope is empty), also try the instance key candidates.
                    // This allows restoring scheduled backups uploaded manually (they are encrypted using bck_script_passkey).
                    if ($post_serverScope === '' && !empty($SETTINGS['bck_script_passkey'] ?? '')) {
                        $rawInstanceKey = (string) $SETTINGS['bck_script_passkey'];
                        $tmp = cryption($rawInstanceKey, '', 'decrypt', $SETTINGS);
                        $decInstanceKey = isset($tmp['string']) ? (string) $tmp['string'] : '';

                        if ($decInstanceKey !== '') {
                            $keysToTry[] = $decInstanceKey;
                        }
                        if ($rawInstanceKey !== '' && $rawInstanceKey !== $decInstanceKey) {
                            $keysToTry[] = $rawInstanceKey;
                        }
                    }
}

                // Ensure we have at least one key
                $keysToTry = array_values(array_unique(array_filter($keysToTry, static fn ($v) => $v !== '')));
                if (empty($keysToTry)) {
                    echo prepareExchangedData(
                        array('error' => true, 'message' => 'Missing encryption key'),
                        'encode'
                    );
                    break;
                }

                // Try to decrypt with the available keys (some environments store bck_script_passkey encrypted)
                $decRet = tpDefuseDecryptWithCandidates($serverPath, $tmpDecrypted, $keysToTry, $SETTINGS);
                if (!empty($decRet['success']) === false) {
                    @unlink($tmpDecrypted);
                    try {
                        logEvents(
                            $SETTINGS,
                            'admin_action',
                            'dataBase restore failed (decrypt error)',
                            (string) $session->get('user-id'),
                            $session->get('user-login')
                        );
                    } catch (Throwable $ignored) {
                        // ignore logging errors
                    }
                    $clearRestoreState($session);
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'error_code' => 'DECRYPT_FAILED',
                            'message' => 'Unable to decrypt backup: ' . $tpSafeJsonString((string) ($decRet['message'] ?? 'unknown error')),
                        ),
                        'encode'
                    );
                    break;
                }

                if (!is_file($tmpDecrypted) || (int) @filesize($tmpDecrypted) === 0) {
                    @unlink($tmpDecrypted);
                    $clearRestoreState($session);
                    echo prepareExchangedData(
                        array('error' => true, 'message' => 'Decrypted backup is empty or unreadable'),
                        'encode'
                    );
                    break;
                }

                // Remove original encrypted file ONLY for legacy uploaded one-shot restore
                if ($deleteEncryptedAfterDecrypt === true) {
                    fileDelete($serverPath, $SETTINGS);

                    // Delete operation record
                    if ($legacyOperationId !== null) {
                        DB::delete(
                            prefixTable('misc'),
                            'increment_id = %i',
                            $legacyOperationId
                        );
                    }
                }
                // From now, restore uses the decrypted temp file
                $post_backupFile = $tmpDecrypted;
                $session->set('restore-temp-file', $tmpDecrypted);
                $post_clearFilename = $tmpDecrypted;
            } else {
                $post_backupFile = $post_clearFilename;
                $session->set('restore-temp-file', $post_clearFilename);
            }

            // Read sql file
            $handle = fopen($post_backupFile, 'r');
        
            if ($handle === false) {
                if (is_string($post_backupFile) && $post_backupFile !== '' && file_exists($post_backupFile) === true
                    && strpos(basename($post_backupFile), 'defuse_temp_restore_') === 0) {
                    @unlink($post_backupFile);
                }
                $clearRestoreState($session);
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => 'Unable to open backup file.',
                        'finished' => false,
                        'restore_token' => $sessionRestoreToken,
                    ),
                    'encode'
                );
                break;
            }
        
                        // Get total file size
            if ((int) $post_totalSize === 0) {
                $post_totalSize = filesize($post_backupFile);
            }

            // Validate chunk parameters
            if ((int) $post_totalSize <= 0) {
                // Abort: we cannot safely run a chunked restore without a reliable size
                $clearRestoreState($session);
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => 'Invalid backup file size (0).',
                        'finished' => true,
                    ),
                    'encode'
                );
                break;
            }

            if ($post_offset < 0) {
                $post_offset = 0;
            }

            if ($post_offset > $post_totalSize) {
                // Abort: invalid offset (prevents instant "success" due to EOF)
                if (is_string($post_backupFile) && $post_backupFile !== '' && file_exists($post_backupFile) === true) {
                    // If it is a temporary decrypted file, cleanup
                    if (strpos(basename($post_backupFile), 'defuse_temp_restore_') === 0) {
                        @unlink($post_backupFile);
                    }
                }
                $clearRestoreState($session);
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => 'Invalid restore offset.',
                        'finished' => true,
                    ),
                    'encode'
                );
                break;
            }

            // Move the file pointer to the current offset
            fseek($handle, $post_offset);
            $query = '';
            $executedQueries = 0;
            $inMultiLineComment = false;
            
            try {
                // Start transaction to ensure database consistency
                DB::startTransaction();
                DB::query("SET FOREIGN_KEY_CHECKS = 0");
                DB::query("SET UNIQUE_CHECKS = 0");
                
                while (!feof($handle) && $executedQueries < $batchSize) {
                    $line = fgets($handle);
                    
                    // Check if not false
                    if ($line !== false) {
                        $trimmedLine = trim($line);
                        
                        // Skip empty lines or comments
                        if (empty($trimmedLine) || 
                            (strpos($trimmedLine, '--') === 0) || 
                            (strpos($trimmedLine, '#') === 0)) {
                            continue;
                        }
                        
                        // Handle multi-line comments
                        if (strpos($trimmedLine, '/*') === 0 && strpos($trimmedLine, '*/') === false) {
                            $inMultiLineComment = true;
                            continue;
                        }
                        
                        if ($inMultiLineComment) {
                            if (strpos($trimmedLine, '*/') !== false) {
                                $inMultiLineComment = false;
                            }
                            continue;
                        }
                        
                        // Add line to current query
                        $query .= $line;
                        
                        // Execute if this is the end of a statement
                        if (substr($trimmedLine, -1) === ';') {
                            try {
                                DB::query($query);
                                $executedQueries++;
                            } catch (Exception $e) {
                                $snippet = substr($query, 0, 120);
                                $snippet = $tpSafeJsonString($snippet);
                                $errors[] = 'Error executing query: ' . $tpSafeJsonString($e->getMessage()) . ' - Query: ' . $snippet . '...';
                            }
                            $query = '';
                        }
                    }
                }

                // Set default settings back
                DB::query("SET FOREIGN_KEY_CHECKS = 1");
                DB::query("SET UNIQUE_CHECKS = 1");
                
                // Commit the transaction if no errors
                if (empty($errors)) {
                    DB::commit();
                } else {
                    DB::rollback();
                }
            } catch (Exception $e) {
                try {
                    DB::query("SET FOREIGN_KEY_CHECKS = 1");
                    DB::query("SET UNIQUE_CHECKS = 1");
                } catch (Exception $ignored) {
                    // Ignore further exceptions
                }
                // Rollback transaction on any exception
                DB::rollback();
                $errors[] = 'Transaction failed: ' . $tpSafeJsonString($e->getMessage());
            }
        
            // Calculate the new offset
            $newOffset = ftell($handle);
        
            // Check if the end of the file has been reached
            $isEndOfFile = feof($handle);
            fclose($handle);
        
            // Handle errors if any
            if (!empty($errors)) {
                // Abort restore: cleanup temp file and release session lock
                if (is_string($post_backupFile) && $post_backupFile !== '' && file_exists($post_backupFile) === true
                    && strpos(basename($post_backupFile), 'defuse_temp_restore_') === 0) {
                    @unlink($post_backupFile);
                }

                $tokenForResponse = $sessionRestoreToken;

                // Best-effort log
                try {
                    $ctx = $session->get('restore-context');
                    $scope = is_array($ctx) ? (string) ($ctx['scope'] ?? '') : '';
                    logEvents(
                        $SETTINGS,
                        'admin_action',
                        'dataBase restore failed' . ($scope !== '' ? ' (scope=' . $scope . ')' : ''),
                        (string) $session->get('user-id'),
                        $session->get('user-login')
                    );
                } catch (Throwable $ignored) {
                    // ignore logging errors during restore
                }

                $clearRestoreState($session);

                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => 'Errors occurred during import: ' . implode('; ', ($post_serverScope === 'scheduled' ? array_map($tpSafeJsonString, $errors) : $errors)),
                        'newOffset' => $newOffset,
                        'totalSize' => $post_totalSize,
                        'clearFilename' => $post_backupFile,
                        'finished' => true,
                        'restore_token' => $tokenForResponse,
                    ),
                    'encode'
                );
                break;
            }

            // Determine if restore is complete
            $finished = ($isEndOfFile === true) || ($post_totalSize > 0 && $newOffset >= $post_totalSize);

            // Respond with the new offset
            echo prepareExchangedData(
                array(
                    'error' => false,
                    'newOffset' => $newOffset,
                    'totalSize' => $post_totalSize,
                    'clearFilename' => $post_backupFile,
                    'finished' => $finished,
                    'restore_token' => $sessionRestoreToken,
                ),
                'encode'
            );

            // Check if the end of the file has been reached to delete the file
            if ($finished) {
                if (defined('WIP') && WIP === true) {
                    error_log('DEBUG: End of file reached. Deleting file '.$post_backupFile);
                }

                if (is_string($post_backupFile) && $post_backupFile !== '' && file_exists($post_backupFile) === true) {
                    @unlink($post_backupFile);
                }

                // Ensure maintenance mode stays enabled after restore (dump may have restored it to 0).
                try {
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
                } catch (Throwable $ignored) {
                    // Best effort
                }

                // Cleanup: after a DB restore, the SQL dump may re-import a running database_backup task
                // (is_in_progress=1) that becomes a "ghost" in Task Manager.
                try {
                    DB::delete(
                        prefixTable('background_tasks'),
                        'process_type=%s AND is_in_progress=%i',
                        'database_backup',
                        1
                    );
                } catch (Throwable $ignored) {
                    // Best effort: ignore if table does not exist yet / partial restore / schema mismatch
                }

                // Finalize: clear lock/session state and log duration (best effort)
                $ctx = $session->get('restore-context');
                $scope = is_array($ctx) ? (string) ($ctx['scope'] ?? '') : '';
                $fileLabel = is_array($ctx) ? (string) ($ctx['backup'] ?? '') : '';
                $startTs = (int) ($session->get('restore-start-ts') ?? 0);
                $duration = ($startTs > 0) ? (time() - $startTs) : 0;

                $clearRestoreState($session);

                try {
                    $msg = 'dataBase restore completed';
                    if ($scope !== '' || $fileLabel !== '' || $duration > 0) {
                        $parts = array();
                        if ($scope !== '') {
                            $parts[] = 'scope=' . $scope;
                        }
                        if ($fileLabel !== '') {
                            $parts[] = 'file=' . $fileLabel;
                        }
                        if ($duration > 0) {
                            $parts[] = 'duration=' . $duration . 's';
                        }
                        $msg .= ' (' . implode(', ', $parts) . ')';
                    }

                    logEvents(
                        $SETTINGS,
                        'admin_action',
                        $msg,
                        (string) $session->get('user-id'),
                        $session->get('user-login')
                    );
                } catch (Throwable $ignored) {
                    // ignore logging errors during restore
                }
            }
            break;
    }
}
