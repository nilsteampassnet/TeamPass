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
 * Collection and rendering of the admin dashboard notices.
 *
 * Two families feed the same list:
 *   - maintenance notices: a pending operation the administrator must complete
 *     (migrations, mandatory tasks manager, leftover configuration file);
 *   - getting-started recommendations: security options a fresh installation has
 *     not set up yet. They are dismissible, maintenance notices are not.
 *
 * Every decision about ordering, severity and layout lives in the DB-free module
 * admin_notices_logic.php; this file only reads the database and the settings.
 *
 * @file      admin_notices.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\Language\Language;

require_once __DIR__ . '/admin_notices_logic.php';

/**
 * TeamPass system accounts, excluded from every user-based maintenance check.
 */
const ADMIN_NOTICES_SYSTEM_LOGINS = ['API', 'TP', 'OTV'];

/**
 * Normalises a notice so the renderer can rely on every key being present.
 *
 * @param array $notice Partial notice definition.
 *
 * @return array Complete notice.
 */
function adminNoticeBuild(array $notice): array
{
    return array_merge(
        [
            'id' => '',
            'severity' => 'info',
            'icon' => 'fa-solid fa-circle-info',
            'title' => '',
            'badge' => '',
            'description' => '',
            'action' => '',
            'extra' => '',
            'dismissible' => false,
        ],
        $notice
    );
}

/**
 * Builds a link used as the action of a notice.
 *
 * @param string $url   Target URL.
 * @param string $label Link label.
 *
 * @return string HTML link.
 */
function adminNoticeActionLink(string $url, string $label): string
{
    return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary btn-sm ml-2">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        . ' <i class="fas fa-caret-right ml-1"></i></a>';
}

/**
 * Collects the pending maintenance operations of the instance.
 *
 * @param array    $SETTINGS TeamPass settings.
 * @param Language $lang     Language object.
 *
 * @return array List of notices.
 */
function adminNoticesCollectMaintenance(array $SETTINGS, Language $lang): array
{
    $notices = [];

    // Tasks manager has been mandatory since 3.0.0.23.
    if (isset($SETTINGS['enable_tasks_manager']) === true && (int) $SETTINGS['enable_tasks_manager'] === 0) {
        $notices[] = adminNoticeBuild([
            'id' => 'tasks_manager',
            'severity' => 'danger',
            'icon' => 'fa-solid fa-triangle-exclamation',
            'title' => $lang->get('admin_notice_tasks_manager'),
            'description' => $lang->get('admin_notice_tasks_manager_desc')
                . ' <a href="https://documentation.teampass.net/#/manage/tasks" target="_blank">'
                . '<i class="fa-solid fa-book mr-1"></i>' . $lang->get('learn_more') . '</a>',
            'action' => adminNoticeActionLink('index.php?page=tasks#settings', $lang->get('open')),
        ]);
    }

    // Has the transparent recovery migration been done?
    DB::query(
        'SELECT id FROM ' . prefixTable('users') . '
        WHERE (user_derivation_seed IS NULL
        OR private_key_backup IS NULL)
        AND disabled = 0
        AND deleted_at IS NULL
        AND login NOT IN %ls',
        ADMIN_NOTICES_SYSTEM_LOGINS
    );
    if (DB::count() !== 0) {
        $notices[] = adminNoticeBuild([
            'id' => 'transparent_recovery',
            'severity' => 'warning',
            'icon' => 'fa-solid fa-chart-bar',
            'title' => $lang->get('perform_transparent_recovery_check'),
            'action' => '<button type="button" class="btn btn-primary btn-sm ml-2" id="check-transparent-recovery-btn" '
                . 'onclick="performTransparentRecoveryCheck()"><i class="fas fa-caret-right"></i></button>',
        ]);
    }

    // Has the personal items migration been done for users?
    $stats = DB::query(
        'SELECT
            COUNT(*) as total_users,
            SUM(CASE WHEN personal_items_migrated = 1 THEN 1 ELSE 0 END) as migrated_users,
            SUM(CASE WHEN personal_items_migrated = 0 THEN 1 ELSE 0 END) as pending_users
        FROM ' . prefixTable('users') . '
        WHERE disabled = 0 AND deleted_at IS NULL AND login NOT IN %ls',
        ADMIN_NOTICES_SYSTEM_LOGINS
    );
    $totalUsers = (int) $stats[0]['total_users'];
    $progressPercent = $totalUsers > 0
        ? ((int) $stats[0]['migrated_users'] / $totalUsers) * 100
        : 0;
    if ($progressPercent !== 100) {
        $notices[] = adminNoticeBuild([
            'id' => 'personal_items_migration',
            'severity' => 'warning',
            'icon' => 'fa-solid fa-chart-bar',
            'title' => $lang->get('get_personal_items_migration_status'),
            'action' => '<button type="button" class="btn btn-primary btn-sm ml-2" id="personal-items-migration-btn" '
                . 'onclick="performPersonalItemsMigrationCheck()"><i class="fas fa-caret-right"></i></button>',
        ]);
    }

    // Status on users sharekeys migration to phpseclib v3.
    if (isset($SETTINGS['phpseclibv3_native']) === true && (int) $SETTINGS['phpseclibv3_native'] === 0) {
        $excludedIds = [9999991, 9999997, 9999998, 9999999];
        $userResults = DB::query(
            'SELECT login
            FROM ' . prefixTable('users') . '
            WHERE phpseclibv3_migration_completed = 0 AND deleted_at IS NULL AND id NOT IN %ls',
            $excludedIds
        );
        if (DB::count() > 0) {
            $remainingUsers = DB::count();
            $loginsArray = array_column($userResults, 'login');
            sort($loginsArray, SORT_NATURAL | SORT_FLAG_CASE);
            $loginsBadges = '';
            foreach ($loginsArray as $login) {
                $loginsBadges .= '<span class="badge badge-light border text-dark mr-1 mb-1 p-2">'
                    . htmlspecialchars($login, ENT_QUOTES, 'UTF-8') . '</span>';
            }

            $notices[] = adminNoticeBuild([
                'id' => 'sharekeys_migration',
                'severity' => 'warning',
                'icon' => 'fa-solid fa-hand',
                'title' => $lang->get('sharekeys_encryption_migration_required'),
                'badge' => '<span class="badge badge-warning ml-1">' . $remainingUsers . ' '
                    . $lang->get('sharekeys_remaining_users') . '</span>',
                'action' => '<i class="fa-solid fa-info-circle text-primary open-info pointer" '
                    . 'data-target="#info-migration-sharekeys" data-size="lg" data-title="'
                    . htmlspecialchars($lang->get('sharekeys_migration_modal_title'), ENT_QUOTES, 'UTF-8') . '"></i>',
                'extra' => '<div id="info-migration-sharekeys" class="d-none">'
                    . '<div class="alert alert-warning mb-3" role="alert">'
                    . '<i class="fa-solid fa-triangle-exclamation mr-2"></i>'
                    . '<strong>' . sprintf($lang->get('sharekeys_migration_notice_count'), $remainingUsers) . '</strong><br>'
                    . $lang->get('sharekeys_migration_notice_text')
                    . '</div>'
                    . '<div class="mb-3">'
                    . '<div class="small text-muted text-uppercase font-weight-bold mb-2">'
                    . $lang->get('sharekeys_migration_next_step_title') . '</div>'
                    . '<ul class="pl-3 mb-0">'
                    . '<li>' . $lang->get('sharekeys_migration_next_step_1') . '</li>'
                    . '<li>' . $lang->get('sharekeys_migration_next_step_2') . '</li>'
                    . '</ul>'
                    . '</div>'
                    . '<div class="small text-muted text-uppercase font-weight-bold mb-2">'
                    . sprintf($lang->get('sharekeys_migration_remaining_users_title'), $remainingUsers) . '</div>'
                    . '<div class="border rounded bg-light p-2" style="max-height:260px; overflow-y:auto;">'
                    . $loginsBadges
                    . '</div>'
                    . '</div>',
            ]);
        }
    }

    // Check if tp.config.php file is still present.
    if (file_exists(__DIR__ . '/../config/tp.config.php') === true) {
        $notices[] = adminNoticeBuild([
            'id' => 'tp_config_file',
            'severity' => 'warning',
            'icon' => 'fa-solid fa-circle-exclamation',
            'title' => $lang->get('admin_notice_tp_config'),
            'description' => $lang->get('admin_notice_tp_config_desc'),
        ]);
    }

    return $notices;
}

/**
 * Collects the getting-started recommendations.
 *
 * They are all dismissible: an administrator running a deliberately local-only or
 * API-less instance must be able to clear them for good.
 *
 * @param array    $SETTINGS TeamPass settings.
 * @param Language $lang     Language object.
 *
 * @return array List of notices.
 */
function adminNoticesCollectChecklist(array $SETTINGS, Language $lang): array
{
    $notices = [];

    // No scheduled backup means no recovery path after an incident.
    // Backup settings use their own misc namespace and are not loaded by ConfigManager.
    $scheduledBackupEnabled = DB::queryFirstField(
        'SELECT valeur FROM ' . prefixTable('misc') . '
        WHERE type = %s AND intitule = %s
        LIMIT 1',
        'settings',
        'bck_scheduled_enabled'
    );
    if ((int) $scheduledBackupEnabled !== 1) {
        $notices[] = adminNoticeBuild([
            'id' => 'checklist_backup',
            'severity' => 'info',
            'icon' => 'fa-solid fa-database',
            'title' => $lang->get('admin_checklist_backup'),
            'description' => $lang->get('admin_checklist_backup_desc'),
            'action' => adminNoticeActionLink('index.php?page=backups', $lang->get('open')),
            'dismissible' => true,
        ]);
    }

    // Not a single MFA method enabled.
    $mfaEnabled = (int) ($SETTINGS['google_authentication'] ?? 0) === 1
        || (int) ($SETTINGS['yubico_authentication'] ?? 0) === 1
        || (int) ($SETTINGS['duo'] ?? 0) === 1
        || (int) ($SETTINGS['agses_authentication_enabled'] ?? 0) === 1;
    if ($mfaEnabled === false) {
        $notices[] = adminNoticeBuild([
            'id' => 'checklist_mfa',
            'severity' => 'info',
            'icon' => 'fa-solid fa-mobile-screen-button',
            'title' => $lang->get('admin_checklist_mfa'),
            'description' => $lang->get('admin_checklist_mfa_desc'),
            'action' => adminNoticeActionLink('index.php?page=2fa', $lang->get('open')),
            'dismissible' => true,
        ]);
    }

    // Every account is local: no directory, no SSO.
    if ((int) ($SETTINGS['ldap_mode'] ?? 0) !== 1 && (int) ($SETTINGS['oauth2_enabled'] ?? 0) !== 1) {
        $notices[] = adminNoticeBuild([
            'id' => 'checklist_auth_source',
            'severity' => 'info',
            'icon' => 'fa-solid fa-address-book',
            'title' => $lang->get('admin_checklist_auth_source'),
            'description' => $lang->get('admin_checklist_auth_source_desc'),
            'action' => adminNoticeActionLink('index.php?page=ldap', $lang->get('open')),
            'dismissible' => true,
        ]);
    }

    // API credentials travelling over plain HTTP.
    if ((int) ($SETTINGS['api'] ?? 0) === 1 && (int) ($SETTINGS['api_require_https'] ?? 0) !== 1) {
        $notices[] = adminNoticeBuild([
            'id' => 'checklist_api_https',
            'severity' => 'info',
            'icon' => 'fa-solid fa-lock',
            'title' => $lang->get('admin_checklist_api_https'),
            'description' => $lang->get('admin_checklist_api_https_desc'),
            'action' => adminNoticeActionLink('index.php?page=api', $lang->get('open')),
            'dismissible' => true,
        ]);
    }

    // Nothing can be shared yet.
    $sharedFolders = (int) DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('nested_tree') . '
        WHERE personal_folder = %i',
        0
    );
    if ($sharedFolders === 0) {
        $notices[] = adminNoticeBuild([
            'id' => 'checklist_shared_folder',
            'severity' => 'info',
            'icon' => 'fa-solid fa-folder-plus',
            'title' => $lang->get('admin_checklist_shared_folder'),
            'description' => $lang->get('admin_checklist_shared_folder_desc'),
            'action' => adminNoticeActionLink('index.php?page=folders', $lang->get('open')),
            'dismissible' => true,
        ]);
    }

    return $notices;
}

/**
 * Collects every notice to display, ordered by severity.
 *
 * @param array    $SETTINGS TeamPass settings.
 * @param Language $lang     Language object.
 *
 * @return array Ordered list of notices.
 */
function adminNoticesCollect(array $SETTINGS, Language $lang): array
{
    return adminNoticesSort(
        array_merge(
            adminNoticesCollectMaintenance($SETTINGS, $lang),
            adminNoticesCollectChecklist($SETTINGS, $lang)
        )
    );
}

/**
 * Renders one notice as a list item.
 *
 * @param array    $notice Notice to render.
 * @param Language $lang   Language object.
 *
 * @return string HTML list item.
 */
function adminNoticeRender(array $notice, Language $lang): string
{
    $dismissible = ($notice['dismissible'] ?? false) === true;

    $dismissButton = $dismissible === true
        ? '<button type="button" class="btn btn-xs btn-link text-muted admin-notice-dismiss ml-1" title="'
            . htmlspecialchars($lang->get('admin_notice_dismiss'), ENT_QUOTES, 'UTF-8')
            . '"><i class="fas fa-times"></i></button>'
        : '';

    $html = '<li class="list-group-item admin-notice-item" data-notice-id="'
        . htmlspecialchars((string) $notice['id'], ENT_QUOTES, 'UTF-8') . '"'
        . ($dismissible === true ? ' data-notice-dismissible="1"' : '') . '>';

    $html .= '<div class="d-flex justify-content-between align-items-center">';
    $html .= '<span><i class="' . htmlspecialchars((string) $notice['icon'], ENT_QUOTES, 'UTF-8') . ' '
        . adminNoticeIconClass((string) $notice['severity']) . ' mr-1"></i> '
        . $notice['title'] . $notice['badge'] . '</span>';
    $html .= '<span class="text-nowrap ml-2">' . $notice['action'] . $dismissButton . '</span>';
    $html .= '</div>';

    if ((string) $notice['description'] !== '') {
        $html .= '<div class="small text-muted mt-1">' . $notice['description'] . '</div>';
    }

    $html .= $notice['extra'];
    $html .= '</li>';

    return $html;
}

/**
 * Renders the whole notices list.
 *
 * @param array    $notices Ordered notices.
 * @param Language $lang    Language object.
 *
 * @return string HTML list items.
 */
function adminNoticesRender(array $notices, Language $lang): string
{
    $html = '';
    foreach ($notices as $notice) {
        $html .= adminNoticeRender($notice, $lang);
    }

    return $html;
}
