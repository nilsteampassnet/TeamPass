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
 * @file      lapr_accounts.php
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('lapr_accounts') === false) {
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
                <h1 class="m-0 text-dark"><i class="fas fa-user-gear mr-2"></i><?php echo $lang->get('lapr_accounts'); ?></h1>
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
                        <h3 class="card-title"><?php echo $lang->get('lapr_accounts'); ?></h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-sm btn-info mr-1" id="lapr-discover-btn">
                                <i class="fas fa-magnifying-glass mr-1"></i><?php echo $lang->get('lapr_discover'); ?>
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" id="lapr-add-account-btn">
                                <i class="fas fa-plus mr-1"></i><?php echo $lang->get('lapr_add_account'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <table id="lapr-accounts-table" class="table table-hover table-striped" style="width:100%">
                            <thead>
                                <tr>
                                    <th><?php echo $lang->get('lapr_username'); ?></th>
                                    <th><?php echo $lang->get('lapr_endpoint'); ?></th>
                                    <th><?php echo $lang->get('lapr_policy'); ?></th>
                                    <th><?php echo $lang->get('lapr_last_rotation'); ?></th>
                                    <th><?php echo $lang->get('lapr_next_rotation'); ?></th>
                                    <th><?php echo $lang->get('lapr_status'); ?></th>
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

<!-- Add managed account modal -->
<div class="modal fade" id="modal_lapr_account" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?php echo $lang->get('lapr_add_account'); ?></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info hidden" id="lapr-acc-discovered-context"></div>
                <div class="alert alert-warning hidden" id="lapr-acc-no-matching-item"></div>
                <div class="form-group">
                    <label><?php echo $lang->get('lapr_endpoint'); ?></label>
                    <select class="form-control" id="lapr-acc-endpoint"></select>
                </div>
                <div class="form-group">
                    <label><?php echo $lang->get('lapr_select_item'); ?></label>
                    <select class="form-control" id="lapr-acc-item"></select>
                </div>
                <div class="form-group">
                    <label><?php echo $lang->get('lapr_policy'); ?></label>
                    <select class="form-control" id="lapr-acc-policy">
                        <option value="0"><?php echo $lang->get('lapr_no_policy'); ?></option>
                    </select>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $lang->get('cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="lapr-acc-save-btn"><i class="fas fa-save mr-1"></i><?php echo $lang->get('save'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Change policy modal -->
<div class="modal fade" id="modal_lapr_account_policy" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?php echo $lang->get('lapr_policy'); ?></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="lapr-editpolicy-account-id">
                <div class="form-group">
                    <label><?php echo $lang->get('lapr_policy'); ?></label>
                    <select class="form-control" id="lapr-editpolicy-policy">
                        <option value="0"><?php echo $lang->get('lapr_no_policy'); ?></option>
                    </select>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $lang->get('cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="lapr-editpolicy-save-btn"><i class="fas fa-save mr-1"></i><?php echo $lang->get('save'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Rotation history modal -->
<div class="modal fade" id="modal_lapr_account_history" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?php echo $lang->get('lapr_history_title'); ?> — <span id="lapr_history_title"></span></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th><?php echo $lang->get('date'); ?></th>
                            <th><?php echo $lang->get('lapr_event'); ?></th>
                            <th><?php echo $lang->get('lapr_trigger'); ?></th>
                            <th><?php echo $lang->get('lapr_result'); ?></th>
                            <th><?php echo $lang->get('lapr_by'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="lapr_history_tbody"></tbody>
                </table>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted" id="lapr_history_range"></small>
                    <div>
                        <button type="button" class="btn btn-sm btn-default" id="lapr_history_prev" disabled>&lsaquo; <?php echo $lang->get('onboarding_btn_prev'); ?></button>
                        <span class="mx-2" id="lapr_history_page"></span>
                        <button type="button" class="btn btn-sm btn-default" id="lapr_history_next" disabled><?php echo $lang->get('onboarding_btn_next'); ?> &rsaquo;</button>
                    </div>
                </div>
                <small class="text-muted" id="lapr_history_retention_note"></small>
            </div>
        </div>
    </div>
</div>

<!-- Discover accounts modal -->
<div class="modal fade" id="modal_lapr_discover" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?php echo $lang->get('lapr_discovered_accounts'); ?></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label><?php echo $lang->get('lapr_endpoint'); ?></label>
                    <div class="input-group">
                        <select class="form-control" id="lapr-discover-endpoint"></select>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-info" id="lapr-discover-start-btn"><?php echo $lang->get('lapr_discover'); ?></button>
                        </div>
                    </div>
                </div>
                <div id="lapr-discover-result"></div>
            </div>
        </div>
    </div>
</div>
