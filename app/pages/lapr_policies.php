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
 * @file      lapr_policies.php
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('lapr_policies') === false) {
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
                <h1 class="m-0 text-dark"><i class="fas fa-scroll mr-2"></i><?php echo $lang->get('lapr_policies'); ?></h1>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo $lang->get('lapr_policies'); ?></h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-sm btn-primary" id="lapr-add-policy-btn">
                                <i class="fas fa-plus mr-1"></i><?php echo $lang->get('lapr_add_policy'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <table id="lapr-policies-table" class="table table-hover table-striped" style="width:100%">
                            <thead>
                                <tr>
                                    <th><?php echo $lang->get('lapr_endpoint_label'); ?></th>
                                    <th><?php echo $lang->get('lapr_frequency_days'); ?></th>
                                    <th><?php echo $lang->get('lapr_password_length'); ?></th>
                                    <th><?php echo $lang->get('lapr_capabilities'); ?></th>
                                    <th><?php echo $lang->get('lapr_rotate_on_enroll'); ?></th>
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

<!-- Policy modal -->
<div class="modal fade" id="modal_lapr_policy" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="lapr-policy-modal-title"><?php echo $lang->get('lapr_add_policy'); ?></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="lapr-policy-id" value="0">
                <div class="form-group">
                    <label><?php echo $lang->get('lapr_endpoint_label'); ?></label>
                    <input type="text" class="form-control" id="lapr-policy-label" maxlength="255">
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label><?php echo $lang->get('lapr_frequency_days'); ?></label>
                        <input type="number" class="form-control" id="lapr-policy-frequency" value="30" min="1" max="3650">
                    </div>
                    <div class="form-group col-md-6">
                        <label><?php echo $lang->get('lapr_password_length'); ?></label>
                        <input type="number" class="form-control" id="lapr-policy-length" value="24" min="8" max="128">
                    </div>
                </div>
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="lapr-policy-upper" checked>
                        <label class="custom-control-label" for="lapr-policy-upper"><?php echo $lang->get('lapr_use_uppercase'); ?></label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="lapr-policy-lower" checked>
                        <label class="custom-control-label" for="lapr-policy-lower"><?php echo $lang->get('lapr_use_lowercase'); ?></label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="lapr-policy-digits" checked>
                        <label class="custom-control-label" for="lapr-policy-digits"><?php echo $lang->get('lapr_use_digits'); ?></label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="lapr-policy-symbols" checked>
                        <label class="custom-control-label" for="lapr-policy-symbols"><?php echo $lang->get('lapr_use_symbols'); ?></label>
                    </div>
                </div>
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="lapr-policy-onenroll">
                        <label class="custom-control-label" for="lapr-policy-onenroll"><?php echo $lang->get('lapr_rotate_on_enroll'); ?></label>
                    </div>
                </div>
                <div class="form-group">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="lapr-policy-preview-btn"><i class="fas fa-eye mr-1"></i><?php echo $lang->get('lapr_preview_password'); ?></button>
                    <code id="lapr-policy-preview" class="ml-2"></code>
                </div>
                <small class="form-text text-muted"><?php echo $lang->get('lapr_policy_bounds'); ?></small>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $lang->get('cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="lapr-policy-save-btn"><i class="fas fa-save mr-1"></i><?php echo $lang->get('save'); ?></button>
            </div>
        </div>
    </div>
</div>
