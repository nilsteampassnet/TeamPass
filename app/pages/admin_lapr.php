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
 * @file      admin_lapr.php
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
require_once __DIR__ . '/../sources/main.functions.php';

// init
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
loadClasses('DB');
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks — admin only page
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        ['type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8')],
        ['type' => 'trim|escape'],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false
    || $checkUserAccess->userAccessPage('admin_lapr') === false
    || (int) $session->get('user-admin') !== 1
) {
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}

date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

/**
 * Render a boolean toggle wired to the generic save_option_change handler.
 *
 * @param string $field    Setting key (teampass_misc intitule)
 * @param array  $SETTINGS Settings array
 * @param int    $default  Value used before the setting is seeded
 *
 * @return string
 */
function laprAdminToggle(string $field, array $SETTINGS, int $default = 0): string
{
    $on = (int) ($SETTINGS[$field] ?? $default) === 1;
    return "<div class='toggle toggle-modern' id='" . $field . "' data-toggle-on='" . ($on ? 'true' : 'false') . "'></div>"
        . "<input type='hidden' id='" . $field . "_input' value='" . ($on ? '1' : '0') . "' />";
}
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-shield-halved mr-2"></i><?php echo $lang->get('lapr_admin'); ?></h1>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="card card-primary card-outline card-tabs">
            <div class="card-header p-0 pt-1 border-bottom-0">
                <ul class="nav nav-tabs" id="lapr-admin-tabs" role="tablist">
                    <li class="nav-item"><a class="nav-link active" data-toggle="pill" href="#tab-lapr-general"><?php echo $lang->get('lapr_admin_general'); ?></a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#tab-lapr-security"><?php echo $lang->get('lapr_admin_security'); ?></a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#tab-lapr-scheduler"><?php echo $lang->get('lapr_admin_scheduler'); ?></a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#tab-lapr-permissions"><?php echo $lang->get('lapr_admin_permissions'); ?></a></li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="lapr-admin-tabs-content">

                    <!-- GENERAL -->
                    <div class="tab-pane fade show active" id="tab-lapr-general">
                        <div class="form-group row">
                            <div class="col-7"><?php echo $lang->get('lapr_enable_setting'); ?>
                                <small class="form-text text-muted"><?php echo $lang->get('lapr_enable_setting_tip'); ?></small>
                            </div>
                            <div class="col-5"><?php echo laprAdminToggle('lapr_enabled', $SETTINGS); ?></div>
                        </div>
                        <div class="form-group row">
                            <div class="col-7"><?php echo $lang->get('lapr_ssh_connect_timeout_setting'); ?></div>
                            <div class="col-5"><input type="number" min="1" max="120" class="form-control form-control-sm" id="lapr_ssh_connect_timeout" value="<?php echo (int) ($SETTINGS['lapr_ssh_connect_timeout'] ?? 10); ?>"></div>
                        </div>
                        <div class="form-group row">
                            <div class="col-7"><?php echo $lang->get('lapr_audit_retention_setting'); ?>
                                <small class="form-text text-muted"><?php echo $lang->get('lapr_audit_retention_tip'); ?></small>
                            </div>
                            <div class="col-5"><input type="number" min="0" max="36500" class="form-control form-control-sm" id="lapr_audit_retention_days" value="<?php echo (int) ($SETTINGS['lapr_audit_retention_days'] ?? 365); ?>"></div>
                        </div>
                    </div>

                    <!-- SECURITY -->
                    <div class="tab-pane fade" id="tab-lapr-security">
                        <div class="alert alert-warning"><i class="fas fa-triangle-exclamation mr-1"></i><?php echo $lang->get('lapr_ssh_credential_folder_warning'); ?></div>
                        <div class="form-group row">
                            <div class="col-7"><?php echo $lang->get('lapr_allowlist_enabled_setting'); ?>
                                <small class="form-text text-muted"><?php echo $lang->get('lapr_allowlist_enabled_tip'); ?></small>
                            </div>
                            <div class="col-5"><?php echo laprAdminToggle('lapr_allowlist_enabled', $SETTINGS); ?></div>
                        </div>
                        <div class="form-group row">
                            <div class="col-7"><?php echo $lang->get('lapr_allowlist_setting'); ?>
                                <small class="form-text text-muted"><?php echo $lang->get('lapr_allowlist_tip'); ?></small>
                            </div>
                            <div class="col-5"><textarea class="form-control form-control-sm" id="lapr_allowlist" rows="3"><?php echo htmlspecialchars((string) ($SETTINGS['lapr_allowlist'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea></div>
                        </div>
                        <div class="form-group row">
                            <div class="col-7"><?php echo $lang->get('lapr_allow_self_management_setting'); ?>
                                <small class="form-text text-danger"><?php echo $lang->get('lapr_allow_self_management_tip'); ?></small>
                            </div>
                            <div class="col-5"><?php echo laprAdminToggle('lapr_allow_self_management', $SETTINGS); ?></div>
                        </div>
                        <div class="form-group row">
                            <div class="col-7"><?php echo $lang->get('lapr_rate_limit_max_attempts_setting'); ?></div>
                            <div class="col-5"><input type="number" min="1" class="form-control form-control-sm" id="lapr_rate_limit_max_attempts" value="<?php echo (int) ($SETTINGS['lapr_rate_limit_max_attempts'] ?? 5); ?>"></div>
                        </div>
                        <div class="form-group row">
                            <div class="col-7"><?php echo $lang->get('lapr_rate_limit_window_setting'); ?></div>
                            <div class="col-5"><input type="number" min="1" class="form-control form-control-sm" id="lapr_rate_limit_window_seconds" value="<?php echo (int) ($SETTINGS['lapr_rate_limit_window_seconds'] ?? 60); ?>"></div>
                        </div>
                        <div class="form-group row">
                            <div class="col-7"><?php echo $lang->get('lapr_rate_limit_block_setting'); ?></div>
                            <div class="col-5"><input type="number" min="1" class="form-control form-control-sm" id="lapr_rate_limit_block_seconds" value="<?php echo (int) ($SETTINGS['lapr_rate_limit_block_seconds'] ?? 300); ?>"></div>
                        </div>
                        <div class="form-group row">
                            <div class="col-7"><?php echo $lang->get('lapr_alert_email_enabled_setting'); ?></div>
                            <div class="col-5"><?php echo laprAdminToggle('lapr_alert_email_enabled', $SETTINGS); ?></div>
                        </div>
                        <div class="form-group row">
                            <div class="col-7"><?php echo $lang->get('lapr_alert_email_recipient_setting'); ?></div>
                            <div class="col-5"><input type="email" class="form-control form-control-sm" id="lapr_alert_email_recipient" value="<?php echo htmlspecialchars((string) ($SETTINGS['lapr_alert_email_recipient'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        </div>
                    </div>

                    <!-- SCHEDULER -->
                    <div class="tab-pane fade" id="tab-lapr-scheduler">
                        <div class="form-group row">
                            <div class="col-7"><?php echo $lang->get('lapr_scheduler_enabled_setting'); ?></div>
                            <div class="col-5"><?php echo laprAdminToggle('lapr_scheduler_enabled', $SETTINGS); ?></div>
                        </div>
                        <div class="form-group row">
                            <div class="col-7"><?php echo $lang->get('lapr_scheduler_interval_setting'); ?></div>
                            <div class="col-5"><input type="number" min="1" class="form-control form-control-sm" id="lapr_scheduler_interval_minutes" value="<?php echo (int) ($SETTINGS['lapr_scheduler_interval_minutes'] ?? 5); ?>"></div>
                        </div>
                        <div class="form-group row">
                            <div class="col-7"><?php echo $lang->get('lapr_endpoint_checks_enabled_setting'); ?></div>
                            <div class="col-5"><?php echo laprAdminToggle('lapr_endpoint_checks_enabled', $SETTINGS); ?></div>
                        </div>
                        <div class="form-group row">
                            <div class="col-7"><?php echo $lang->get('lapr_endpoint_check_interval_setting'); ?></div>
                            <div class="col-5"><input type="number" min="5" class="form-control form-control-sm" id="lapr_endpoint_check_interval_minutes" value="<?php echo (int) ($SETTINGS['lapr_endpoint_check_interval_minutes'] ?? 1440); ?>"></div>
                        </div>
                        <div class="form-group row">
                            <div class="col-7"><?php echo $lang->get('lapr_max_retries_setting'); ?></div>
                            <div class="col-5"><input type="number" min="0" class="form-control form-control-sm" id="lapr_max_retries" value="<?php echo (int) ($SETTINGS['lapr_max_retries'] ?? 3); ?>"></div>
                        </div>
                        <div class="form-group row">
                            <div class="col-7"><?php echo $lang->get('lapr_retry_delay_setting'); ?></div>
                            <div class="col-5"><input type="number" min="1" class="form-control form-control-sm" id="lapr_retry_delay_minutes" value="<?php echo (int) ($SETTINGS['lapr_retry_delay_minutes'] ?? 60); ?>"></div>
                        </div>
                    </div>

                    <!-- PERMISSIONS -->
                    <div class="tab-pane fade" id="tab-lapr-permissions">
                        <p class="text-muted"><?php echo $lang->get('lapr_permissions_intro'); ?></p>
                        <div class="row mb-3">
                            <div class="col-12 col-lg-6">
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text">
                                            <i class="fas fa-search"></i>
                                        </div>
                                    </div>
                                    <input type="search" class="form-control" placeholder="<?php echo $lang->get('find'); ?>" aria-label="<?php echo $lang->get('find'); ?>" id="lapr-permissions-search">
                                </div>
                            </div>
                        </div>
                        <table class="table table-hover table-sm" id="lapr-permissions-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th><?php echo $lang->get('login'); ?></th>
                                    <th><?php echo $lang->get('name'); ?></th>
                                    <th><?php echo $lang->get('lapr_can_manage'); ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                        <div class="mt-2 hidden" id="lapr-permissions-search-no-results">
                            <i class="fas fa-info mr-2 text-warning"></i><?php echo $lang->get('no_item_to_display'); ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
