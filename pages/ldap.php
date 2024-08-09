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
 * @file      ldap.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */


use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request;
use TeampassClasses\Language\Language;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = Request::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config if $SETTINGS not defined
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('ldap') === false) {
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
                <h1 class="m-0 text-dark"><i class="fas fa-id-card mr-2"></i><?php echo $lang->get('ldap'); ?></h1>
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
                <?php
                if (extension_loaded('ldap') === false) {
                    ?>
                    <div class="alert alert-danger">
                        <h5><i class="icon fa fa-ban"></i> Alert!</h5>
                        <?php echo $lang->get('ldap_extension_not_loaded'); ?>
                    </div>
                <?php
                } else {
                    ?>
                    <div class='card card-primary'>
                        <div class='card-header'>
                            <h3 class='card-title'><?php echo $lang->get('admin_ldap_configuration'); ?></h3>
                        </div>
                        <!-- /.card-header -->
                        <!-- form start -->
                        <form role='form-horizontal'>
                            <div class='card-body'>

                                <div class='row mb-5'>
                                    <div class='col-10'>
                                        <?php echo $lang->get('settings_ldap_mode'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo $lang->get('settings_ldap_mode_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-2'>
                                        <div class='toggle toggle-modern' id='ldap_mode' data-toggle-on='<?php echo isset($SETTINGS['ldap_mode']) === true && (int) $SETTINGS['ldap_mode'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='ldap_mode_input' value='<?php echo isset($SETTINGS['ldap_mode']) && (int) $SETTINGS['ldap_mode'] === 1 ? 1 : 0; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo $lang->get('hosts'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo $lang->get('settings_ldap_hosts_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_hosts' value='<?php echo $SETTINGS['ldap_hosts'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo $lang->get('settings_ldap_port'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo $lang->get('settings_ldap_port_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_port' value='<?php echo $SETTINGS['ldap_port'] ?? '389'; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo $lang->get('base_distiguished_name'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo $lang->get('settings_ldap_bdn_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_bdn' value='<?php echo $SETTINGS['ldap_bdn'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo $lang->get('username'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo $lang->get('settings_ldap_username_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_username' value='<?php echo $SETTINGS['ldap_username'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo $lang->get('password'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo $lang->get('settings_ldap_password_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='password' class='form-control form-control-sm setting-ldap' id='ldap_password' value='<?php echo $SETTINGS['ldap_password'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo $lang->get('settings_ldap_user_dn_attribute'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo $lang->get('settings_ldap_user_dn_attribute_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_user_dn_attribute' value='<?php echo $SETTINGS['ldap_user_dn_attribute'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo $lang->get('settings_ldap_user_attribute'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo $lang->get('settings_ldap_user_attribute_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_user_attribute' value='<?php echo $SETTINGS['ldap_user_attribute'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo $lang->get('settings_ldap_additional_user_dn'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo $lang->get('settings_ldap_additional_user_dn_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_dn_additional_user_dn' value='<?php echo $SETTINGS['ldap_dn_additional_user_dn'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo $lang->get('settings_ldap_user_object_filter'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo $lang->get('settings_ldap_user_object_filter_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_user_object_filter' value='<?php echo $SETTINGS['ldap_user_object_filter'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo $lang->get('settings_ldap_group_objectclasses_attibute'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo $lang->get('settings_ldap_group_objectclasses_attibute_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_group_objectclasses_attibute' value='<?php echo $SETTINGS['ldap_group_objectclasses_attibute'] ?? 'top,groupofuniquenames'; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2'>
                                    <div class='col-5'>
                                        <?php echo $lang->get('settings_ldap_type'); ?>
                                    </div>
                                    <div class='col-7'>
                                        <select class='form-control form-control-sm' id='ldap_type'>
                                            <option value=''>-- <?php echo $lang->get('select'); ?> --</option>
                                            <option value="ActiveDirectory" <?php echo isset($SETTINGS['ldap_type']) === true && $SETTINGS['ldap_type'] === 'ActiveDirectory' ? ' selected' : ''; ?>>Active Directory</option>
                                            <option value="OpenLDAP" <?php echo isset($SETTINGS['ldap_type']) === true && $SETTINGS['ldap_type'] === 'OpenLDAP' ? ' selected' : ''; ?>>OpenLDAP</option>
                                            <option value="FreeIPA" <?php echo isset($SETTINGS['ldap_type']) === true && $SETTINGS['ldap_type'] === 'FreeIPA' ? ' selected' : ''; ?>>FreeIPA</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-10'>
                                        <?php echo $lang->get('settings_ldap_ssl'); ?>
                                    </div>
                                    <div class='col-2'>
                                        <div class='toggle toggle-modern' id='ldap_ssl' data-toggle-on='<?php echo isset($SETTINGS['ldap_ssl']) === true && $SETTINGS['ldap_ssl'] === '1' ? 'true' : 'false'; ?>'></div><input type='hidden' id='ldap_ssl_input' value='<?php echo isset($SETTINGS['ldap_ssl']) && (int) $SETTINGS['ldap_ssl'] === 1 ? 1 : 0; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-10'>
                                        <?php echo $lang->get('settings_ldap_tls'); ?>
                                    </div>
                                    <div class='col-2'>
                                        <div class='toggle toggle-modern' id='ldap_tls' data-toggle-on='<?php echo isset($SETTINGS['ldap_tls']) === true && $SETTINGS['ldap_tls'] === '1' ? 'true' : 'false'; ?>'></div><input type='hidden' id='ldap_tls_input' value='<?php echo isset($SETTINGS['ldap_tls']) && (int) $SETTINGS['ldap_tls'] === 1 ? 1 : 0; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2'>
                                    <div class='col-5'>
                                        <?php echo $lang->get('settings_ldap_tls_certifacte_check'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo $lang->get('settings_ldap_tls_certifacte_check_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <select class='form-control form-control-sm' id='ldap_tls_certifacte_check'>
                                            <option value="LDAP_OPT_X_TLS_NEVER" <?php echo isset($SETTINGS['ldap_tls_certifacte_check']) === true && $SETTINGS['ldap_tls_certifacte_check'] === 'LDAP_OPT_X_TLS_NEVER' ? ' selected' : ''; ?>>LDAP_OPT_X_TLS_NEVER</option>
                                            <option value="LDAP_OPT_X_TLS_HARD" <?php echo isset($SETTINGS['ldap_tls_certifacte_check']) === true && $SETTINGS['ldap_tls_certifacte_check'] === 'LDAP_OPT_X_TLS_HARD' ? ' selected' : ''; ?>>LDAP_OPT_X_TLS_HARD</option>
                                            <option value="LDAP_OPT_X_TLS_DEMAND" <?php echo isset($SETTINGS['ldap_tls_certifacte_check']) === true && $SETTINGS['ldap_tls_certifacte_check'] === 'LDAP_OPT_X_TLS_DEMAND' ? ' selected' : ''; ?>>LDAP_OPT_X_TLS_DEMAND</option>
                                            <option value="LDAP_OPT_X_TLS_ALLOW" <?php echo isset($SETTINGS['ldap_tls_certifacte_check']) === true && $SETTINGS['ldap_tls_certifacte_check'] === 'LDAP_OPT_X_TLS_ALLOW' ? ' selected' : ''; ?>>LDAP_OPT_X_TLS_ALLOW</option>
                                            <option value="LDAP_OPT_X_TLS_TRY" <?php echo isset($SETTINGS['ldap_tls_certifacte_check']) === true && $SETTINGS['ldap_tls_certifacte_check'] === 'LDAP_OPT_X_TLS_TRY' ? ' selected' : ''; ?>>LDAP_OPT_X_TLS_TRY</option>
                                        </select>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-10'>
                                        <?php echo $lang->get('settings_ldap_and_local_authentication'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo $lang->get('settings_ldap_and_local_authentication_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-2'>
                                        <div class='toggle toggle-modern' id='ldap_and_local_authentication' data-toggle-on='<?php echo isset($SETTINGS['ldap_and_local_authentication']) === true && (int) $SETTINGS['ldap_and_local_authentication'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='ldap_and_local_authentication_input' value='<?php echo isset($SETTINGS['ldap_and_local_authentication']) && (int) $SETTINGS['ldap_and_local_authentication'] === 1 ? 1 : 0; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-10'>
                                        <?php echo $lang->get('settings_ad_users_with_ad_groups'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo $lang->get('settings_ad_users_with_ad_groups_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-2'>
                                        <div class='toggle toggle-modern' id='enable_ad_users_with_ad_groups' data-toggle-on='<?php echo isset($SETTINGS['enable_ad_users_with_ad_groups']) === true && (int) $SETTINGS['enable_ad_users_with_ad_groups'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_ad_users_with_ad_groups_input' value='<?php echo isset($SETTINGS['enable_ad_users_with_ad_groups']) && (int) $SETTINGS['enable_ad_users_with_ad_groups'] === 1 ? 1 : 0; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo $lang->get('settings_ldap_guid_attibute'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo $lang->get('settings_ldap_guid_attibute_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_guid_attibute' value='<?php echo $SETTINGS['ldap_guid_attibute'] ?? 'objectguid'; ?>'>
                                    </div>
                                </div>

                                <!--<?php if (defined('WIP') === true && WIP === true) { ?>-->
                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-10'>
                                        <?php echo $lang->get('settings_ad_user_auto_creation'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo $lang->get('settings_ad_user_auto_creation_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-2'>
                                        <div class='toggle toggle-modern' id='enable_ad_user_auto_creation' data-toggle-on='<?php echo isset($SETTINGS['enable_ad_user_auto_creation']) === true && (int) $SETTINGS['enable_ad_user_auto_creation'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_ad_user_auto_creation_input' value='<?php echo isset($SETTINGS['enable_ad_user_auto_creation']) && (int) $SETTINGS['enable_ad_user_auto_creation'] === 1 ? 1 : 0; ?>'>
                                    </div>
                                </div>
                                <!--<?php } ?>-->

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-10'>
                                        <?php echo $lang->get('settings_disable_forgot_password_link'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo $lang->get('settings_disable_forgot_password_link_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-2'>
                                        <div class='toggle toggle-modern' id='disable_show_forgot_pwd_link' data-toggle-on='<?php echo isset($SETTINGS['disable_show_forgot_pwd_link']) === true && (int) $SETTINGS['disable_show_forgot_pwd_link'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='disable_show_forgot_pwd_link_input' value='<?php echo isset($SETTINGS['disable_show_forgot_pwd_link']) && (int) $SETTINGS['disable_show_forgot_pwd_link'] === 1 ? 1 : 0; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo $lang->get('newly_created_user_is_administrated_by'); ?>
                                    </div>
                                    <div class='col-7'>
                                        <select class='form-control form-control-sm select2' id='ldap_new_user_is_administrated_by'>
                                        </select>
                                    </div>
                                </div>

                            </div>
                        </form>
                    </div>

                    <div class='card card-primary'>
                        <div class='card-header'>
                            <h3 class='card-title'><?php echo $lang->get('actions'); ?></h3>
                        </div>
                        <!-- /.card-header -->
                        <!-- form start -->
                        <form role='form-horizontal'>
                            <div class='card-body'>
                                <ul class='nav nav-tabs' id='' role='tablist'>
                                    <li class='nav-item'>
                                        <a class='nav-link active' id='test-tab' data-toggle='tab' href='#test' role='tab' aria-controls='test' aria-selected='true'>
                                            <i class='fas fa-vial mr-2'></i><?php echo $lang->get('ldap_test_config'); ?>
                                        </a>
                                    </li>
                                </ul>
                                <div class='tab-content mt-2' id=''>
                                    <div class='tab-pane fade show active' id='test' role='tabpanel' aria-labelledby='test-tab'>
                                        <div class='row mb-2'>
                                            <div class='col-8'>
                                                <?php echo $lang->get('ldap_test_username'); ?>
                                            </div>
                                            <div class='col-4'>
                                                <input type='text' class='form-control' id='ldap-test-config-username' value=''>
                                            </div>
                                        </div>
                                        <div class='row mb-2'>
                                            <div class='col-8'>
                                                <?php echo $lang->get('ldap_test_username_pwd'); ?>
                                            </div>
                                            <div class='col-4'>
                                                <input type='password' class='form-control' id='ldap-test-config-pwd' value=''>
                                            </div>
                                        </div>
                                        <div class='card mb-2 hidden info' id='ldap-test-config-results'>
                                            <div class='card-header'>
                                                <?php echo $lang->get('output'); ?>
                                            </div>
                                            <div class='card-body'>
                                                <p class='card-text' id='ldap-test-config-results-text'></p>
                                            </div>
                                        </div>
                                        <div class='row mb-2'>
                                            <button type='button' class='btn btn-primary btn-sm tp-action mr-2' data-action='ldap-test-config'>
                                                <i class='fas fa-cog mr-2'></i><?php echo $lang->get('perform'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </form>
                    </div>
                <?php
                }
                ?>
            </div>
            <!-- /.col-md-6 -->
        </div>
        <!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content -->
