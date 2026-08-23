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
 * @file      lapr_endpoints.php
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
require_once __DIR__ . '/../sources/lapr.functions.php';

// init
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
loadClasses('DB');
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('lapr_endpoints') === false) {
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}

// LAPR module gate
if (laprCheckPermission($session, $SETTINGS) === false) {
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}

date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-server mr-2"></i><?php echo $lang->get('lapr_endpoints'); ?></h1>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="alert alert-warning">
            <i class="fas fa-triangle-exclamation mr-1"></i><?php echo $lang->get('lapr_ssh_credential_folder_warning'); ?>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo $lang->get('lapr_endpoints'); ?></h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-sm btn-primary" id="lapr-add-endpoint-btn">
                                <i class="fas fa-plus mr-1"></i><?php echo $lang->get('lapr_add_endpoint'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <table id="lapr-endpoints-table" class="table table-hover table-striped" style="width:100%">
                            <thead>
                                <tr>
                                    <th><?php echo $lang->get('lapr_endpoint_label'); ?></th>
                                    <th><?php echo $lang->get('lapr_hostname'); ?></th>
                                    <th><?php echo $lang->get('lapr_ssh_username'); ?></th>
                                    <th><?php echo $lang->get('lapr_os_info'); ?></th>
                                    <th><?php echo $lang->get('lapr_status'); ?></th>
                                    <th><?php echo $lang->get('lapr_last_check'); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enroll endpoint modal -->
<div class="modal fade" id="modal_lapr_endpoint" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?php echo $lang->get('lapr_add_endpoint'); ?></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo htmlspecialchars($lang->get('close'), ENT_QUOTES, 'UTF-8'); ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label><?php echo $lang->get('lapr_endpoint_label'); ?></label>
                    <input type="text" class="form-control" id="lapr-ep-label" maxlength="255">
                </div>
                <div class="form-row">
                    <div class="form-group col-md-8">
                        <label><?php echo $lang->get('lapr_hostname'); ?></label>
                        <input type="text" class="form-control" id="lapr-ep-hostname" maxlength="255">
                    </div>
                    <div class="form-group col-md-4">
                        <label><?php echo $lang->get('lapr_port'); ?></label>
                        <input type="number" class="form-control" id="lapr-ep-port" value="22" min="1" max="65535">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label><?php echo $lang->get('lapr_ssh_username'); ?></label>
                        <input type="text" class="form-control" id="lapr-ep-username" maxlength="100">
                    </div>
                    <div class="form-group col-md-6">
                        <label><?php echo $lang->get('lapr_ssh_auth_method'); ?></label>
                        <select class="form-control" id="lapr-ep-auth-method">
                            <option value="password"><?php echo $lang->get('lapr_ssh_password'); ?></option>
                            <option value="key"><?php echo $lang->get('lapr_ssh_private_key'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label><?php echo $lang->get('lapr_select_item'); ?></label>
                    <select class="form-control" id="lapr-ep-credential-item"></select>
                    <small class="form-text text-muted"><?php echo $lang->get('lapr_credential_stored_note'); ?></small>
                </div>
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="lapr-ep-skip-hostkey">
                        <label class="custom-control-label" for="lapr-ep-skip-hostkey"><?php echo $lang->get('lapr_skip_hostkey_verification'); ?></label>
                    </div>
                    <small class="form-text text-danger" id="lapr-ep-skip-hostkey-warning" style="display:none;"><?php echo $lang->get('lapr_skip_hostkey_warning'); ?></small>
                </div>

                <div class="alert alert-danger" id="lapr-ep-self-management-warning" style="display:none;">
                    <div><i class="fas fa-triangle-exclamation mr-1"></i><?php echo $lang->get('lapr_self_management_warning'); ?></div>
                    <div class="custom-control custom-checkbox mt-2">
                        <input type="checkbox" class="custom-control-input" id="lapr-ep-self-management-ack">
                        <label class="custom-control-label" for="lapr-ep-self-management-ack"><?php echo $lang->get('lapr_self_management_ack'); ?></label>
                    </div>
                </div>

                <div id="lapr-ep-test-result" style="display:none;" class="mt-3"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $lang->get('cancel'); ?></button>
                <div>
                    <button type="button" class="btn btn-info" id="lapr-ep-test-btn"><i class="fas fa-plug mr-1"></i><?php echo $lang->get('lapr_test_connection'); ?></button>
                    <button type="button" class="btn btn-primary" id="lapr-ep-save-btn" disabled><i class="fas fa-save mr-1"></i><?php echo $lang->get('save'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
