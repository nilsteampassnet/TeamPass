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
 * @copyright 2009-2026 Teampass.net
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('mfa') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

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
                            <div class="col-3 d-flex justify-content-end">
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
                                <select class='form-control form-control-sm select2' id='mfa_for_roles' onchange='' multiple="multiple" style="width:100%;">
                                    <?php
                                    // Get selected groups
                                    $arrRolesMFA = json_decode($SETTINGS['mfa_for_roles'], true);
                                    if ($arrRolesMFA === 0 || empty($arrRolesMFA) === true) {
                                        $arrRolesMFA = [];
                                    }
                                    // Get full list
                                    $roles = getRolesTitles();
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
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#webauthn-login" role="tab" aria-controls="webauthn-login" aria-selected="false"><?php echo $lang->get('webauthn_passkeys'); ?></a>
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
                                    <div class="col-3 d-flex justify-content-end">
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
                                    <div class="col-3 d-flex justify-content-end">
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
                                    <div class="col-3 d-flex justify-content-end">
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
                                    <div class="col-3 d-flex justify-content-end">
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

                            <?php
                            $webauthnLoginMode = (int) ($SETTINGS['webauthn_login_mode'] ?? 0);
                            $webauthnDefaultRpId = strtolower((string) parse_url((string) ($SETTINGS['cpassman_url'] ?? ''), PHP_URL_HOST));
                            // Changing the relying party ID orphans the registered passkeys: 2fa.js.php
                            // asks for a confirmation when some exist.
                            require_once __DIR__ . '/../sources/webauthn_login_logic.php';
                            $webauthnEffectiveRpId = webauthnLoginRpId($SETTINGS);
                            $webauthnLoginPasskeyCount = (int) DB::queryFirstField(
                                'SELECT COUNT(*) FROM ' . prefixTable('user_webauthn_credentials') . ' WHERE rp_id IS NULL OR rp_id = %s',
                                $webauthnEffectiveRpId
                            );
                            $webauthnLoginOrphanedCount = (int) DB::queryFirstField(
                                'SELECT COUNT(*) FROM ' . prefixTable('user_webauthn_credentials') . ' WHERE rp_id IS NOT NULL AND rp_id != %s',
                                $webauthnEffectiveRpId
                            );
                            ?>
                            <div class="tab-pane" id="webauthn-login" role="tabpanel" aria-labelledby="webauthn-login-tab">
                                <?php
                                // Browsers only run passkeys in a secure context: say so before anyone enables them
                                $webauthnLoginOrigin = webauthnLoginOriginOf((string) ($SETTINGS['cpassman_url'] ?? ''));
                                if ($webauthnLoginOrigin !== '' && webauthnLoginOriginIsSecure($webauthnLoginOrigin) === false) {
                                    echo '
                                <div class="alert alert-warning" id="webauthn-login-https-warning">
                                    <i class="fa-solid fa-triangle-exclamation mr-2"></i>' . htmlspecialchars(sprintf($lang->get('webauthn_login_https_required'), $webauthnLoginOrigin), ENT_QUOTES, 'UTF-8') . '
                                </div>';
                                }
                                // Passkeys bound to a previous relying party ID: no longer asked at sign-in
                                if ($webauthnLoginOrphanedCount > 0) {
                                    echo '
                                <div class="alert alert-info" id="webauthn-login-orphaned">
                                    <i class="fa-solid fa-circle-info mr-2"></i>' . htmlspecialchars(sprintf($lang->get('webauthn_login_orphaned_passkeys'), $webauthnLoginOrphanedCount), ENT_QUOTES, 'UTF-8') . '
                                </div>';
                                }
                                ?>
                                <div class="row mb-2">
                                    <div class="col-7">
                                        <?php echo $lang->get('webauthn_login_mode'); ?>
                                        <small class='form-text text-muted'>
                                            <?php echo $lang->get('webauthn_login_mode_tip'); ?>
                                        </small>
                                    </div>
                                    <div class="col-5">
                                        <select class='form-control form-control-sm select2' id='webauthn_login_mode' style="width:100%;">
                                            <?php
                                            foreach ([0, 1, 2] as $mode) {
                                                echo '
                                            <option value="' . $mode . '"', $webauthnLoginMode === $mode ? ' selected' : '', '>' . $lang->get('webauthn_login_mode_' . $mode) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-9">
                                        <?php echo $lang->get('webauthn_login_require_prf'); ?>
                                        <small class='form-text text-muted'>
                                            <?php echo $lang->get('webauthn_login_require_prf_tip'); ?>
                                        </small>
                                    </div>
                                    <div class="col-3 d-flex justify-content-end">
                                        <div class="toggle toggle-modern" id="webauthn_login_require_prf" data-toggle-on="<?php echo (int) ($SETTINGS['webauthn_login_require_prf'] ?? 0) === 1 ? 'true' : 'false'; ?>"></div><input type="hidden" id="webauthn_login_require_prf_input" value="<?php echo (int) ($SETTINGS['webauthn_login_require_prf'] ?? 0) === 1 ? '1' : '0'; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-9">
                                        <?php echo $lang->get('webauthn_passwordless_satisfies_mfa'); ?>
                                        <small class='form-text text-muted'>
                                            <?php echo $lang->get('webauthn_passwordless_satisfies_mfa_tip'); ?>
                                        </small>
                                    </div>
                                    <div class="col-3 d-flex justify-content-end">
                                        <div class="toggle toggle-modern" id="webauthn_passwordless_satisfies_mfa" data-toggle-on="<?php echo isset($SETTINGS['webauthn_passwordless_satisfies_mfa']) === false || (int) $SETTINGS['webauthn_passwordless_satisfies_mfa'] === 1 ? 'true' : 'false'; ?>"></div><input type="hidden" id="webauthn_passwordless_satisfies_mfa_input" value="<?php echo isset($SETTINGS['webauthn_passwordless_satisfies_mfa']) === false || (int) $SETTINGS['webauthn_passwordless_satisfies_mfa'] === 1 ? '1' : '0'; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-9">
                                        <?php echo $lang->get('webauthn_email_on_add'); ?>
                                        <small class='form-text text-muted'>
                                            <?php echo $lang->get('webauthn_login_email_on_add_tip'); ?>
                                        </small>
                                    </div>
                                    <div class="col-3 d-flex justify-content-end">
                                        <div class="toggle toggle-modern" id="webauthn_email_on_add" data-toggle-on="<?php echo isset($SETTINGS['webauthn_email_on_add']) === false || (int) $SETTINGS['webauthn_email_on_add'] === 1 ? 'true' : 'false'; ?>"></div><input type="hidden" id="webauthn_email_on_add_input" value="<?php echo isset($SETTINGS['webauthn_email_on_add']) === false || (int) $SETTINGS['webauthn_email_on_add'] === 1 ? '1' : '0'; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-7">
                                        <?php echo $lang->get('webauthn_rp_id'); ?>
                                        <small class='form-text text-muted'>
                                            <?php echo $lang->get('webauthn_rp_id_tip'); ?>
                                        </small>
                                    </div>
                                    <div class="col-5">
                                        <input type="text" class="form-control form-control-sm purify no-save" data-field="label" data-effective="<?php echo htmlspecialchars($webauthnEffectiveRpId, ENT_QUOTES, 'UTF-8'); ?>" data-passkeys="<?php echo $webauthnLoginPasskeyCount; ?>" id="webauthn_rp_id" placeholder="<?php echo htmlspecialchars($webauthnDefaultRpId, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars(html_entity_decode((string) ($SETTINGS['webauthn_rp_id'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-7">
                                        <?php echo $lang->get('webauthn_rp_name'); ?>
                                        <small class='form-text text-muted'>
                                            <?php echo $lang->get('webauthn_rp_name_tip'); ?>
                                        </small>
                                    </div>
                                    <div class="col-5">
                                        <input type="text" class="form-control form-control-sm purify" data-field="label" id="webauthn_rp_name" placeholder="TeamPass" value="<?php echo htmlspecialchars(html_entity_decode((string) ($SETTINGS['webauthn_rp_name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
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
