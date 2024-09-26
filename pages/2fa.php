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
 * @file      2fa.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

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
            'type' => $request->request->get('type', '') !== '' ? htmlspecialchars($request->request->get('type')) : '',
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('mfa') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //
 

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">
                    <i class="fas fa-qrcode mr-2"></i><?php echo $lang->get('mfa'); ?>
                </h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class='card-title'><?php echo $lang->get('mfa_configuration'); ?></h3>
                    </div>

                    <div class="card-body">

                        <div class="row mb-4">
                            <div class="col-9">
                                <?php echo $lang->get('2factors_expected_for_admin'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('2factors_expected_for_admin_tip'); ?>
                                </small>
                            </div>
                            <div class="col-3">
                                <div class="toggle toggle-modern" id="admin_2fa_required" data-toggle-on="<?php echo isset($SETTINGS['admin_2fa_required']) && (int) $SETTINGS['admin_2fa_required'] === 1 ? 'true' : 'false'; ?>"></div><input type="hidden" id="admin_2fa_required_input" value="<?php echo isset($SETTINGS['admin_2fa_required']) && (int) $SETTINGS['admin_2fa_required'] === 1 ? '1' : '0'; ?>">
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-6">
                                <?php echo $lang->get('mfa_for_roles'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('mfa_for_roles_tip'); ?>
                                </small>
                            </div>
                            <div class='col-6'>
                                <select class='form-control form-control-sm select2 disabled' id='mfa_for_roles' onchange='' multiple="multiple" style="width:100%;">
                                    <?php
                                    // Get selected groups
                                    $arrRolesMFA = json_decode($SETTINGS['mfa_for_roles'], true);
                                    if ($arrRolesMFA === 0 || empty($arrRolesMFA) === true) {
                                        $arrRolesMFA = [];
                                    }
                                    // Get full list
                                    $roles = performDBQuery(
                                        $SETTINGS,
                                        'id, title',
                                        'roles_title'
                                    );
                                    foreach ($roles as $role) {
                                        echo '
                                    <option value="' . $role['id'] . '"', in_array($role['id'], $arrRolesMFA) === true ? ' selected' : '', '>' . addslashes($role['title']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <ul class="nav nav-tabs mb-4">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#google" aria-controls="google" aria-selected="true"><?php echo $lang->get('google_2fa'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#duo" role="tab" aria-controls="duo" aria-selected="false"><?php echo $lang->get('duo_security'); ?></a>
                            </li>
                            <!--
                                <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#yubico" role="tab" aria-controls="yubico" aria-selected="false"><?php echo $lang->get('yubico'); ?></a>
                            </li>
                                -->
                        </ul>
                        <div class="tab-content">

                            <div class="tab-pane fade show active" id="google" role="tabpanel" aria-labelledby="google-tab">
                                <div class="row mb-2">
                                    <div class="col-9">
                                        <?php echo $lang->get('admin_2factors_authentication_setting'); ?>
                                        <small class='form-text text-muted'>
                                            <?php echo $lang->get('admin_2factors_authentication_setting_tip'); ?>
                                        </small>
                                    </div>
                                    <div class="col-3">
                                        <div class="toggle toggle-modern" id="google_authentication" data-toggle-on="<?php echo isset($SETTINGS['google_authentication']) && (int) $SETTINGS['google_authentication'] === 1 ? 'true' : 'false'; ?>"></div><input type="hidden" id="google_authentication_input" value="<?php echo isset($SETTINGS['google_authentication']) && (int) $SETTINGS['google_authentication'] === 1 ? '1' : '0'; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-9">
                                        <?php echo $lang->get('admin_ga_website_name'); ?>
                                        <small class='form-text text-muted'>
                                            <?php echo $lang->get('admin_ga_website_name_tip'); ?>
                                        </small>
                                    </div>
                                    <div class="col-3">
                                        <input type="text" class="form-control form-control-sm purify" data-field="label" id="ga_website_name" value="<?php echo isset($SETTINGS['ga_website_name']) === true ? $SETTINGS['ga_website_name'] : ''; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-9">
                                        <?php echo $lang->get('ga_reset_by_user'); ?>
                                        <small class='form-text text-muted'>
                                            <?php echo $lang->get('ga_reset_by_user_tip'); ?>
                                        </small>
                                    </div>
                                    <div class="col-3">
                                        <div class="toggle toggle-modern" id="ga_reset_by_user" data-toggle-on="<?php echo isset($SETTINGS['ga_reset_by_user']) && (int) $SETTINGS['ga_reset_by_user'] === 1 ? 'true' : 'false'; ?>"></div><input type="hidden" id="ga_reset_by_user_input" value="<?php echo isset($SETTINGS['ga_reset_by_user']) && (int) $SETTINGS['ga_reset_by_user'] === 1 ? '1' : '0'; ?>">
                                    </div>
                                </div>

                            </div>

                            <div class="tab-pane" id="duo" role="tabpanel" aria-labelledby="duo-tab">
                                <div class="row mb-2">
                                    <div class="col-9">
                                        <?php echo $lang->get('settings_duo'); ?>
                                        <small id="passwordHelpBlock" class="form-text text-muted">
                                            <?php echo $lang->get('settings_duo_tip'); ?>
                                        </small>
                                        <div>
                                            <small><a href="<?php echo DUO_ADMIN_URL_INFO; ?>" target="_blank"><?php echo $lang->get('more_information'); ?></a></small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="toggle toggle-modern" id="duo" data-toggle-on="<?php echo isset($SETTINGS['duo']) && (int) $SETTINGS['duo'] === 1 ? 'true' : 'false'; ?>"></div><input type="hidden" id="duo_input" value="<?php echo isset($SETTINGS['duo']) && (int) $SETTINGS['duo'] === 1 ? '1' : '0'; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-9">
                                        <?php echo $lang->get('admin_duo_intro'); ?>
                                        <small id="passwordHelpBlock" class="form-text text-muted">
                                            <?php echo $lang->get('settings_duo_explanation'); ?>
                                        </small>
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-5">
                                        <?php echo $lang->get('admin_duo_ikey'); ?>
                                    </div>
                                    <div class="col-7">
                                        <input type="text" class="form-control form-control-sm purify" data-field="label" id="duo_ikey" value="<?php echo isset($SETTINGS['duo_ikey']) === true ? $SETTINGS['duo_ikey'] : ''; ?>">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-5">
                                        <?php echo $lang->get('admin_duo_skey'); ?>
                                    </div>
                                    <div class="col-7">
                                        <input type="password" class="form-control form-control-sm purify" data-field="label" id="duo_skey" value="<?php echo isset($SETTINGS['duo_skey']) === true ? $SETTINGS['duo_skey'] : ''; ?>">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-5">
                                        <?php echo $lang->get('admin_duo_host'); ?>
                                    </div>
                                    <div class="col-7">
                                        <input type="text" class="form-control form-control-sm purify" data-field="label" id="duo_host" value="<?php echo isset($SETTINGS['duo_host']) === true ? $SETTINGS['duo_host'] : ''; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <button class="btn btn-primary" id="button-duo-config-check">
                                        <?php echo $lang->get('duo-run-config-check'); ?>
                                    </button>
                                </div>
                            </div>

                            <!--
                            <div class="tab-pane" id="yubico" role="tabpanel" aria-labelledby="yubico-tab">
                                <div class="row mb-2">
                                    <div class="col-9">
                                        <?php echo $lang->get('admin_yubico_authentication_setting'); ?>
                                        <small id="passwordHelpBlock" class="form-text text-muted">
                                            <?php echo $lang->get('yubico_authentication_tip'); ?>
                                        </small>
                                    </div>
                                    <div class="col-3">
                                        <div class="toggle toggle-modern" id="yubico_authentication" data-toggle-on="<?php echo isset($SETTINGS['yubico_authentication']) && (int) $SETTINGS['yubico_authentication'] === 1 ? 'true' : 'false'; ?>"></div><input type="hidden" id="yubico_authentication_input" value="<?php echo isset($SETTINGS['yubico_authentication']) && (int) $SETTINGS['yubico_authentication'] === 1 ? '1' : '0'; ?>">
                                    </div>
                                </div>
                            </div>
                                -->

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
    </div>
