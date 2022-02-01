<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 *
 * @file      ldap.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2022 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

if (
    isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id']) === true
    || isset($_SESSION['key']) === false || empty($_SESSION['key']) === true
) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php') === true) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php') === true) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'ldap', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Load template
require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
// LDAP type currently loaded
$ldap_type = $SETTINGS['ldap_type'] ?? '';

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-id-card mr-2"></i><?php echo langHdl('ldap'); ?></h1>
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
                        <?php echo langHdl('ldap_extension_not_loaded'); ?>
                    </div>
                <?php
                } else {
                    ?>
                    <div class='card card-primary'>
                        <div class='card-header'>
                            <h3 class='card-title'><?php echo langHdl('admin_ldap_configuration'); ?></h3>
                        </div>
                        <!-- /.card-header -->
                        <!-- form start -->
                        <form role='form-horizontal'>
                            <div class='card-body'>

                                <div class='row mb-5'>
                                    <div class='col-10'>
                                        <?php echo langHdl('settings_ldap_mode'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_ldap_mode_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-2'>
                                        <div class='toggle toggle-modern' id='ldap_mode' data-toggle-on='<?php echo isset($SETTINGS['ldap_mode']) === true && (int) $SETTINGS['ldap_mode'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='ldap_mode_input' value='<?php echo isset($SETTINGS['ldap_mode']) && (int) $SETTINGS['ldap_mode'] === 1 ? 1 : 0; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo langHdl('hosts'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_ldap_hosts_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_hosts' value='<?php echo $SETTINGS['ldap_hosts'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo langHdl('settings_ldap_port'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_ldap_port_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_port' value='<?php echo $SETTINGS['ldap_port'] ?? '389'; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo langHdl('base_distiguished_name'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_ldap_bdn_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_bdn' value='<?php echo $SETTINGS['ldap_bdn'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo langHdl('username'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_ldap_username_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_username' value='<?php echo $SETTINGS['ldap_username'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo langHdl('password'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_ldap_password_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_password' value='<?php echo $SETTINGS['ldap_password'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo langHdl('settings_ldap_user_dn_attribute'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_ldap_user_dn_attribute_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='settings_ldap_user_dn_attribute' value='<?php echo $SETTINGS['settings_ldap_user_dn_attribute'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo langHdl('settings_ldap_user_attribute'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_ldap_user_attribute_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_user_attribute' value='<?php echo $SETTINGS['ldap_user_attribute'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo langHdl('settings_ldap_additional_user_dn'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_ldap_additional_user_dn_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_dn_additional_user_dn' value='<?php echo $SETTINGS['ldap_dn_additional_user_dn'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo langHdl('settings_ldap_user_object_filter'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_ldap_user_object_filter_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_user_object_filter' value='<?php echo $SETTINGS['ldap_user_object_filter'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2'>
                                    <div class='col-5'>
                                        <?php echo langHdl('settings_ldap_type'); ?>
                                    </div>
                                    <div class='col-7'>
                                        <select class='form-control form-control-sm' id='ldap_type'>
                                            <option value=''>-- <?php echo langHdl('select'); ?> --</option>
                                            <option value="ActiveDirectory" <?php echo isset($SETTINGS['ldap_type']) === true && $SETTINGS['ldap_type'] === 'ActiveDirectory' ? ' selected' : ''; ?>>Active Directory</option>
                                            <option value="OpenLDAP" <?php echo isset($SETTINGS['ldap_type']) === true && $SETTINGS['ldap_type'] === 'OpenLDAP' ? ' selected' : ''; ?>>OpenLDAP</option>
                                            <option value="FreeIPA" <?php echo isset($SETTINGS['ldap_type']) === true && $SETTINGS['ldap_type'] === 'FreeIPA' ? ' selected' : ''; ?>>FreeIPA</option>
                                        </select>
                                    </div>
                                </div>







<!--
                                <div class='row mb-2'>
                                    <div class='col-5'>
                                        <?php echo langHdl('settings_ldap_type'); ?>
                                    </div>
                                    <div class='col-7'>
                                        <select class='form-control form-control-sm' id='ldap_type'>
                                            <option value=''>-- <?php echo langHdl('select'); ?> --</option>
                                            <option value="windows" <?php echo isset($SETTINGS['ldap_type']) === true && $SETTINGS['ldap_type'] === 'windows' ? ' selected' : ''; ?>>Windows / Active Directory</option>
                                            <option value="posix" <?php echo isset($SETTINGS['ldap_type']) === true && $SETTINGS['ldap_type'] === 'posix' ? ' selected' : ''; ?>>Posix / OpenLDAP (RFC2307)</option>
                                            <option value="posix-search" <?php echo isset($SETTINGS['ldap_type']) === true && $SETTINGS['ldap_type'] === 'posix-search' ? ' selected' : ''; ?>>Posix / OpenLDAP (RFC2307) Search Based</option>
                                        </select>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap tr-windows tr-posix tr-posix-search<?php echo isset($ldap_type) === true ? '' : ' hidden'; ?>'>
                                    <div class='col-5'>
                                        <?php echo langHdl('settings_ldap_domain'); ?>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_suffix' value='<?php echo $SETTINGS['ldap_suffix'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-windows tr-ldap tr-posix<?php echo isset($ldap_type) === true && $ldap_type !== 'posix-search' ? '' : ' hidden'; ?>'>
                                    <div class='col-5'>
                                        <?php echo langHdl('settings_ldap_domain_dn'); ?>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_domain_dn' value='<?php echo $SETTINGS['ldap_domain_dn'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap tr-posix-search<?php echo isset($ldap_type) === true && $ldap_type === 'posix-search' ? '' : ' hidden'; ?>'>
                                    <div class='col-5'>
                                        <?php echo langHdl('settings_ldap_object_class'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_ldap_object_class_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_object_class' value='<?php echo $SETTINGS['ldap_object_class'] ?? 'posixAccount'; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap tr-posix-search<?php echo isset($ldap_type) === true && $ldap_type === 'posix-search' ? '' : ' hidden'; ?>'>
                                    <div class='col-5'>
                                        <?php echo langHdl('settings_ldap_user_attribute'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_ldap_user_attribute_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_user_attribute' value='<?php echo $SETTINGS['ldap_user_attribute'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap tr-posix-search<?php echo isset($ldap_type) === true && $ldap_type === 'posix-search' ? '' : ' hidden'; ?>'>
                                    <div class='col-5'>
                                        <?php echo langHdl('settings_ldap_usergroup'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_ldap_usergroup_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_usergroup' value='<?php echo $SETTINGS['ldap_usergroup'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap tr-posix-search<?php echo isset($ldap_type) === true && $ldap_type === 'posix-search' ? '' : ' hidden'; ?>'>
                                    <div class='col-5'>
                                        <?php echo langHdl('settings_ldap_bind_dn'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_ldap_bind_dn_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_bind_dn' value='<?php echo $SETTINGS['ldap_bind_dn'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap tr-posix-search<?php echo isset($ldap_type) === true && $ldap_type === 'posix-search' ? '' : ' hidden'; ?>'>
                                    <div class='col-5'>
                                        <?php echo langHdl('settings_ldap_bind_passwd'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_ldap_bind_passwd_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_bind_passwd' value='<?php echo $SETTINGS['ldap_bind_passwd'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap tr-windows<?php echo isset($ldap_type) === true && $ldap_type === 'posix-search' ? '' : ' hidden'; ?>'>
                                    <div class='col-5'>
                                        <?php echo langHdl('settings_ldap_search_base'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_ldap_search_base_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_search_base' value='<?php echo $SETTINGS['ldap_bdn'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap tr-posix-search<?php echo isset($ldap_type) === true && $ldap_type === 'windows' ? '' : ' hidden'; ?>'>
                                    <div class='col-5'>
                                        <?php echo langHdl('settings_ldap_allowed_usergroup'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_ldap_allowed_usergroup_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_allowed_usergroup' value='<?php echo $SETTINGS['ldap_allowed_usergroup'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap tr-posix-search<?php echo isset($ldap_type) === true && $ldap_type === 'windows' ? '' : ' hidden'; ?>'>
                                    <div class='col-5'>
                                        <?php echo langHdl('settings_ldap_domain_controler'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_ldap_domain_controler_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-7'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_domain_controler' value='<?php echo $SETTINGS['ldap_hosts'] ?? ''; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap tr-windows tr-posix tr-posix-search'>
                                    <div class='col-10'>
                                        <?php echo langHdl('settings_ldap_port'); ?>
                                    </div>
                                    <div class='col-2'>
                                        <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_port' value='<?php echo $SETTINGS['ldap_port'] ?? '389'; ?>'>
                                    </div>
                                </div>
-->
                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-10'>
                                        <?php echo langHdl('settings_ldap_ssl'); ?>
                                    </div>
                                    <div class='col-2'>
                                        <div class='toggle toggle-modern' id='ldap_ssl' data-toggle-on='<?php echo isset($SETTINGS['ldap_ssl']) === true && $SETTINGS['ldap_ssl'] === '1' ? 'true' : 'false'; ?>'></div><input type='hidden' id='ldap_ssl_input' value='<?php echo isset($SETTINGS['ldap_ssl']) && (int) $SETTINGS['ldap_ssl'] === 1 ? 1 : 0; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-10'>
                                        <?php echo langHdl('settings_ldap_tls'); ?>
                                    </div>
                                    <div class='col-2'>
                                        <div class='toggle toggle-modern' id='ldap_tls' data-toggle-on='<?php echo isset($SETTINGS['ldap_tls']) === true && $SETTINGS['ldap_tls'] === '1' ? 'true' : 'false'; ?>'></div><input type='hidden' id='ldap_tls_input' value='<?php echo isset($SETTINGS['ldap_tls']) && (int) $SETTINGS['ldap_tls'] === 1 ? 1 : 0; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-10'>
                                        <?php echo langHdl('settings_ldap_elusers'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_ldap_elusers_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-2'>
                                        <div class='toggle toggle-modern' id='ldap_elusers' data-toggle-on='<?php echo isset($SETTINGS['ldap_elusers']) === true && $SETTINGS['ldap_elusers'] === '1' ? 'true' : 'false'; ?>'></div><input type='hidden' id='ldap_elusers_input' value='<?php echo isset($SETTINGS['ldap_elusers']) && (int) $SETTINGS['ldap_elusers'] === 1 ? 1 : 0; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-10'>
                                        <?php echo langHdl('settings_ldap_and_local_authentication'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_ldap_and_local_authentication_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-2'>
                                        <div class='toggle toggle-modern' id='ldap_and_local_authentication' data-toggle-on='<?php echo isset($SETTINGS['ldap_and_local_authentication']) === true && (int) $SETTINGS['ldap_and_local_authentication'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='ldap_and_local_authentication_input' value='<?php echo isset($SETTINGS['ldap_and_local_authentication']) && (int) $SETTINGS['ldap_and_local_authentication'] === 1 ? 1 : 0; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-10'>
                                        <?php echo langHdl('settings_disable_forgot_password_link'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('settings_disable_forgot_password_link_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-2'>
                                        <div class='toggle toggle-modern' id='disable_show_forgot_pwd_link' data-toggle-on='<?php echo isset($SETTINGS['disable_show_forgot_pwd_link']) === true && (int) $SETTINGS['disable_show_forgot_pwd_link'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='disable_show_forgot_pwd_link_input' value='<?php echo isset($SETTINGS['disable_show_forgot_pwd_link']) && (int) $SETTINGS['disable_show_forgot_pwd_link'] === 1 ? 1 : 0; ?>'>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo langHdl('newly_created_user_is_administrated_by'); ?>
                                    </div>
                                    <div class='col-7'>
                                        <select class='form-control form-control-sm select2' id='ldap_new_user_is_administrated_by'>
                                        </select>
                                    </div>
                                </div>

                                <div class='row mb-2 tr-ldap'>
                                    <div class='col-5'>
                                        <?php echo langHdl('newly_created_user_role'); ?>
                                    </div>
                                    <div class='col-7'>
                                        <select class='form-control form-control-sm select2' id='ldap_new_user_role'>
                                        </select>
                                    </div>
                                </div>


                            </div>
                        </form>
                    </div>

                    <div class='card card-primary'>
                        <div class='card-header'>
                            <h3 class='card-title'><?php echo langHdl('actions'); ?></h3>
                        </div>
                        <!-- /.card-header -->
                        <!-- form start -->
                        <form role='form-horizontal'>
                            <div class='card-body'>
                                <ul class='nav nav-tabs' id='' role='tablist'>
                                    <li class='nav-item'>
                                        <a class='nav-link active' id='test-tab' data-toggle='tab' href='#test' role='tab' aria-controls='test' aria-selected='true'>
                                            <i class='fas fa-vial mr-2'></i><?php echo langHdl('ldap_test_config'); ?>
                                        </a>
                                    </li>
                                </ul>
                                <div class='tab-content mt-2' id=''>
                                    <div class='tab-pane fade show active' id='test' role='tabpanel' aria-labelledby='test-tab'>
                                        <div class='row mb-2'>
                                            <div class='col-8'>
                                                <?php echo langHdl('ldap_test_username'); ?>
                                            </div>
                                            <div class='col-4'>
                                                <input type='text' class='form-control form-control-sm' id='ldap-test-config-username' value=''>
                                            </div>
                                        </div>
                                        <div class='row mb-2'>
                                            <div class='col-8'>
                                                <?php echo langHdl('ldap_test_username_pwd'); ?>
                                            </div>
                                            <div class='col-4'>
                                                <input type='text' class='form-control form-control-sm' id='ldap-test-config-pwd' value=''>
                                            </div>
                                        </div>
                                        <div class='card mb-2 hidden info' id='ldap-test-config-results'>
                                            <div class='card-header'>
                                                <?php echo langHdl('output'); ?>
                                            </div>
                                            <div class='card-body'>
                                                <p class='card-text' id='ldap-test-config-results-text'></p>
                                            </div>
                                        </div>
                                        <div class='row mb-2'>
                                            <button type='button' class='btn btn-primary btn-sm tp-action mr-2' data-action='ldap-test-config'>
                                                <i class='fas fa-cog mr-2'></i><?php echo langHdl('perform'); ?>
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
