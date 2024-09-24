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
 * @file      oauth.php
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
use TeampassClasses\OAuth2Controller\OAuth2Controller;

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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('oauth') === false) {
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
 
// LDAP type currently loaded
$ldap_type = $SETTINGS['ldap_type'] ?? '';

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-plug mr-2"></i><?php echo $lang->get('oauth'); ?></h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->


<!-- Main content -->
<div class='content'>
    <div class='container-fluid'>
        <div class='row'>
            <div class='col-md-12'>
                <div class='card card-primary'>
                    <div class='card-header'>
                        <h3 class='card-title'><?php echo $lang->get('admin_oauth_configuration'); ?></h3>
                    </div>
                    <!-- /.card-header -->
                    <!-- form start -->
                    <form role='form-horizontal'>
                        <div class='card-body'>

                            <div class='row mb-2'>
                                <div class='col-12'>
                                    <div class="alert alert-warning" role="alert">
                                    <i class="fa-solid fa-flask-vial mr-3"></i>Expiremental feature. Use at your own risk.
                                    </div>
                                </div>
                            </div>

                            <div class='row mb-2'>
                                <div class='col-10'>
                                    <?php echo $lang->get('settings_oauth_mode'); ?>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo $lang->get('settings_oauth_mode_tip'); ?>
                                    </small>
                                </div>
                                <div class='col-2'>
                                    <div class='toggle toggle-modern' id='oauth2_enabled' data-toggle-on='<?php echo isset($SETTINGS['oauth2_enabled']) === true && (int) $SETTINGS['oauth2_enabled'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='oauth2_enabled_input' value='<?php echo isset($SETTINGS['oauth2_enabled']) && (int) $SETTINGS['oauth2_enabled'] === 1 ? 1 : 0; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2'>
                                <div class='col-10'>
                                    <?php echo $lang->get('settings_oauth_auto_login'); ?>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo $lang->get('settings_oauth_auto_login_tip'); ?>
                                    </small>
                                </div>
                                <div class='col-2'>
                                    <div class='toggle toggle-modern' id='oauth2_auto_login' data-toggle-on='<?php echo isset($SETTINGS['oauth2_auto_login']) === true && (int) $SETTINGS['oauth2_auto_login'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='oauth2_auto_login_input' value='<?php echo isset($SETTINGS['oauth2_auto_login']) && (int) $SETTINGS['oauth2_auto_login'] === 1 ? 1 : 0; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap'>
                                <div class='col-5'>
                                    <?php echo $lang->get('app_name'); ?>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo $lang->get('app_name_tip'); ?>
                                    </small>
                                </div>
                                <div class='col-7'>
                                    <input type='text' class='form-control form-control-sm setting-oauth' id='oauth2_client_appname' value='<?php echo $SETTINGS['oauth2_client_appname'] ?? 'Login with Azure'; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap'>
                                <div class='col-5'>
                                    <?php echo $lang->get('callback_url'); ?>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo $lang->get('callback_url_tip'); ?>
                                    </small>
                                </div>
                                <div class='col-7'>
                                    <input type='text' class='form-control form-control-sm setting-oauth' id='oauth2_callback_url' value='<?php echo $SETTINGS['cpassman_url'].'/'.OAUTH2_REDIRECTURI; ?>' disabled>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap'>
                                <div class='col-5'>
                                    <?php echo $lang->get('client_id'); ?>
                                </div>
                                <div class='col-7'>
                                    <input type='text' class='form-control form-control-sm setting-oauth' id='oauth2_client_id' value='<?php echo $SETTINGS['oauth2_client_id'] ?? ''; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap'>
                                <div class='col-5'>
                                    <?php echo $lang->get('client_secret'); ?>
                                </div>
                                <div class='col-7'>
                                    <input type='password' class='form-control form-control-sm setting-oauth' id='oauth2_client_secret' value='<?php echo $SETTINGS['oauth2_client_secret'] ?? ''; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap'>
                                <div class='col-5'>
                                    <?php echo $lang->get('tenant_id'); ?>
                                </div>
                                <div class='col-7'>
                                    <input type='text' class='form-control form-control-sm setting-oauth' id='oauth2_tenant_id' value='<?php echo $SETTINGS['oauth2_tenant_id'] ?? ''; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap'>
                                <div class='col-5'>
                                    <?php echo $lang->get('oauth2_client_urlResourceOwnerDetails'); ?>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo $lang->get('oauth2_client_urlResourceOwnerDetails_tip'); ?>
                                    </small>
                                </div>
                                <div class='col-7'>
                                    <input type='text' class='form-control form-control-sm setting-oauth' id='oauth2_client_urlResourceOwnerDetails' value='<?php echo $SETTINGS['oauth2_client_urlResourceOwnerDetails'] ?? ''; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap'>
                                <div class='col-5'>
                                    <?php echo $lang->get('scopes'); ?>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo $lang->get('scopes_tip'); ?>
                                    </small>
                                </div>
                                <div class='col-7'>
                                    <input type='text' class='form-control form-control-sm setting-oauth' id='oauth2_client_scopes' value='<?php echo $SETTINGS['oauth2_client_scopes'] ?? 'openid,profile,email,User.Read,Group.Read.All'; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap'>
                                <div class='col-5'>
                                    <?php echo $lang->get('authorization_endpoint'); ?>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo $lang->get('replace_tenant_id'); ?>
                                    </small>
                                </div>
                                <div class='col-7'>
                                    <input type='text' class='form-control form-control-sm setting-oauth' id='oauth2_client_endpoint' value='<?php echo $SETTINGS['oauth2_client_endpoint'] ?? 'https://login.microsoftonline.com/{tenant-id}/oauth2/v2.0/authorize'; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap'>
                                <div class='col-5'>
                                    <?php echo $lang->get('token_endpoint'); ?>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo $lang->get('replace_tenant_id'); ?>
                                    </small>
                                </div>
                                <div class='col-7'>
                                    <input type='text' class='form-control form-control-sm setting-oauth' id='oauth2_client_token' value='<?php echo $SETTINGS['oauth2_client_token'] ?? 'https://login.microsoftonline.com/{tenant-id}/oauth2/v2.0/token'; ?>'>
                                </div>
                            </div>

                            </div>

                        </form>
                    </div>
                
            </div>
            <!-- /.col-md-6 -->
        </div>
        <!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content -->
