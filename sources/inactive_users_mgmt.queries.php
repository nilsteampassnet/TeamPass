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
 * @file      inactive_users_mgmt.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => htmlspecialchars((string) $request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('options') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

/**
 * Get a setting from teampass_misc (type='settings').
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

    if ((int) $exists === 1) {
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

/**
 * Build a small HTML summary from last_details json.
 */
function tpBuildInactiveUsersLastSummaryHtml(array $details, Language $lang): string
{
    if (empty($details)) {
        return '-';
    }

    $parts = [];

    if (isset($details['checked'])) {
        $parts[] = $lang->get('inactive_users_mgmt_summary_checked') . ': ' . (int)$details['checked'];
    }
    if (isset($details['warned'])) {
        $parts[] = $lang->get('inactive_users_mgmt_summary_warned') . ': ' . (int)$details['warned'];
    }
    if (isset($details['warned_no_email'])) {
        $parts[] = $lang->get('inactive_users_mgmt_badge_no_email') . ': ' . (int)$details['warned_no_email'];
    }
    if (isset($details['action_disable'])) {
        $parts[] = $lang->get('inactive_users_mgmt_action_disable') . ': ' . (int)$details['action_disable'];
    }
    if (isset($details['action_soft_delete'])) {
        $parts[] = $lang->get('inactive_users_mgmt_action_soft_delete') . ': ' . (int)$details['action_soft_delete'];
    }
    if (isset($details['action_hard_delete'])) {
        $parts[] = $lang->get('inactive_users_mgmt_action_hard_delete') . ': ' . (int)$details['action_hard_delete'];
    }
    if (isset($details['errors']) && (int)$details['errors'] > 0) {
        $parts[] = $lang->get('inactive_users_mgmt_summary_errors') . ': ' . (int)$details['errors'];
    }

    return implode(' &nbsp;|&nbsp; ', array_map('htmlspecialchars', $parts));
}

// Prepare post variables
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);

// Decode data
$dataReceived = [];
if (!empty($post_data)) {
    $dataReceived = prepareExchangedData($post_data, 'decode');
}
if (!is_array($dataReceived)) {
    $dataReceived = [];
}

switch ((string) $post_type) {
    case 'inactive_users_mgmt_get_settings':
        if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
            echo prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }

        $enabled = (int)tpGetSettingsValue('inactive_users_mgmt_enabled', '0');
        $inactivityDays = (int)tpGetSettingsValue('inactive_users_mgmt_inactivity_days', '90');
        $graceDays = (int)tpGetSettingsValue('inactive_users_mgmt_grace_days', '7');
        $action = (string)tpGetSettingsValue('inactive_users_mgmt_action', 'disable');
        $time = (string)tpGetSettingsValue('inactive_users_mgmt_time', '02:00');

        $nextRunAt = (int)tpGetSettingsValue('inactive_users_mgmt_next_run_at', '0');
        $lastRunAt = (int)tpGetSettingsValue('inactive_users_mgmt_last_run_at', '0');
        $lastStatus = (string)tpGetSettingsValue('inactive_users_mgmt_last_status', '');
        $lastMessageKey = (string)tpGetSettingsValue('inactive_users_mgmt_last_message', '');
        $lastDetailsRaw = (string)tpGetSettingsValue('inactive_users_mgmt_last_details', '');

        $details = [];
        if ($lastDetailsRaw !== '') {
            $tmp = json_decode($lastDetailsRaw, true);
            if (is_array($tmp)) $details = $tmp;
        }

        $tz = tpGetAdminTimezoneName();

        echo prepareExchangedData(
            [
                'error' => false,
                'result' => [
                    'tz' => $tz,
                    'settings' => [
                        'enabled' => $enabled === 1 ? 1 : 0,
                        'inactivity_days' => $inactivityDays > 0 ? $inactivityDays : 90,
                        'grace_days' => $graceDays >= 0 ? $graceDays : 7,
                        'action' => in_array($action, ['disable','soft_delete','hard_delete'], true) ? $action : 'disable',
                        'time' => preg_match('/^\d{2}:\d{2}$/', $time) ? $time : '02:00',
                    ],
                    'status' => [
                        'next_run_at' => $nextRunAt,
                        'last_run_at' => $lastRunAt,
                        'last_status' => $lastStatus !== '' ? $lastStatus : '-',
                        'last_message_key' => $lastMessageKey,
                        'last_message' => $lastMessageKey !== '' ? $lang->get($lastMessageKey) : '-',
                        'last_summary_html' => tpBuildInactiveUsersLastSummaryHtml($details, $lang),
                    ],
                ],
            ],
            'encode'
        );
        break;

    case 'inactive_users_mgmt_save_settings':
        if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
            echo prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }

        $enabled = (int)($dataReceived['enabled'] ?? 0);
        $enabled = ($enabled === 1) ? 1 : 0;

        $inactivityDays = (int)($dataReceived['inactivity_days'] ?? 90);
        if ($inactivityDays < 1) $inactivityDays = 1;

        $graceDays = (int)($dataReceived['grace_days'] ?? 7);
        if ($graceDays < 0) $graceDays = 0;

        $action = (string)($dataReceived['action'] ?? 'disable');
        if (!in_array($action, ['disable','soft_delete','hard_delete'], true)) {
            $action = 'disable';
        }

        $time = (string)($dataReceived['time'] ?? '02:00');
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time = '02:00';
        }

        tpUpsertSettingsValue('inactive_users_mgmt_enabled', (string)$enabled);
        tpUpsertSettingsValue('inactive_users_mgmt_inactivity_days', (string)$inactivityDays);
        tpUpsertSettingsValue('inactive_users_mgmt_grace_days', (string)$graceDays);
        tpUpsertSettingsValue('inactive_users_mgmt_action', $action);
        tpUpsertSettingsValue('inactive_users_mgmt_time', $time);

        // Force recompute by handler
        tpUpsertSettingsValue('inactive_users_mgmt_next_run_at', '0');

        echo prepareExchangedData(['error' => false], 'encode');
        break;

    case 'inactive_users_mgmt_run_now':
        if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
            echo prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }

        $pending = (int)DB::queryFirstField(
            'SELECT COUNT(*) FROM ' . prefixTable('background_tasks') . '
             WHERE process_type=%s AND is_in_progress IN (0,1)
               AND (finished_at IS NULL OR finished_at = "" OR finished_at = 0)',
            'inactive_users_housekeeping'
        );
        if ($pending > 0) {
            echo prepareExchangedData(['error' => true, 'message' => $lang->get('inactive_users_mgmt_msg_task_already_pending')], 'encode');
            break;
        }

        $now = time();
        DB::insert(
            prefixTable('background_tasks'),
            [
                'created_at' => (string)$now,
                'process_type' => 'inactive_users_housekeeping',
                'arguments' => json_encode(['source' => 'ui', 'initiator_user_id' => (int)$session->get('user-id')], JSON_UNESCAPED_SLASHES),
                'is_in_progress' => 0,
                'status' => 'new',
            ]
        );

        tpUpsertSettingsValue('inactive_users_mgmt_last_run_at', (string)$now);
        tpUpsertSettingsValue('inactive_users_mgmt_last_status', 'queued');
        tpUpsertSettingsValue('inactive_users_mgmt_last_message', 'inactive_users_mgmt_msg_task_enqueued');

        echo prepareExchangedData(['error' => false], 'encode');
        break;

    case 'inactive_users_mgmt_get_status':
        if ($post_key !== $session->get('key') || (int)$session->get('user-admin') !== 1) {
            echo prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }

        $enabled = (int)tpGetSettingsValue('inactive_users_mgmt_enabled', '0');
        $inactivityDays = (int)tpGetSettingsValue('inactive_users_mgmt_inactivity_days', '90');
        $graceDays = (int)tpGetSettingsValue('inactive_users_mgmt_grace_days', '7');
        $action = (string)tpGetSettingsValue('inactive_users_mgmt_action', 'disable');
        if (!in_array($action, ['disable','soft_delete','hard_delete'], true)) {
            $action = 'disable';
        }

        $actionLabel = $lang->get('inactive_users_mgmt_action_' . $action);

        $banner = $enabled === 1
            ? sprintf($lang->get('inactive_users_mgmt_banner_enabled'), (string)$inactivityDays, (string)$graceDays, $actionLabel)
            : $lang->get('inactive_users_mgmt_banner_disabled');

        $banner .= ' <a href="index.php?page=options" class="alert-link">' . $lang->get('inactive_users_mgmt_go_to_options') . '</a>';

        echo prepareExchangedData(
            [
                'error' => false,
                'result' => [
                    'enabled' => $enabled === 1 ? 1 : 0,
                    'banner_type' => $enabled === 1 ? 'info' : 'warning',
                    'banner_html' => $banner,
                ],
            ],
            'encode'
        );
        break;

    default:
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('server_answer_error')], 'encode');
        break;
}
