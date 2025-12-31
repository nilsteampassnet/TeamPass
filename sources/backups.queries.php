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
 * @copyright 2009-2025 Teampass.net
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

            if ($get_file === '' || strpos($get_file, 'scheduled-') !== 0 || strtolower(pathinfo($get_file, PATHINFO_EXTENSION)) !== 'sql') {
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

            // Stream file
            @set_time_limit(0);
            $size = (int) @filesize($fpReal);
            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) {
                    @ob_end_clean();
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

            if (is_file($fp)) {
                @unlink($fp);
            }

            echo prepareExchangedData(['error' => false], 'encode');
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
                    'arguments' => json_encode(['output_dir' => $dir, 'source' => 'scheduler'], JSON_UNESCAPED_SLASHES),
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
                if ($tmp !== '' && file_exists($tmp) === true && strpos(basename($tmp), 'defuse_temp_restore_') === 0) {
                    @unlink($tmp);
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
                    fileDelete($serverPath);

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
