<?php
/**
 * Teampass - a collaborative passwords manager.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 *
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2018 Nils Laumaillé
* @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
*
 * @version   GIT: <git_id>
 *
 * @see      http://www.teampass.net
 */
if (isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1
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
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'ldap', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

// Load template
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

// LDAP type currently loaded
$ldap_type = isset($SETTINGS['ldap_type']) ? $SETTINGS['ldap_type'] : '';

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
                                    <div class='toggle toggle-modern' id='ldap_mode' data-toggle-on='<?php echo isset($SETTINGS['ldap_mode']) === true && $SETTINGS['ldap_mode'] === '1' ? 'true' : 'false'; ?>'></div><input type='hidden' id='ldap_mode_input' value='<?php echo isset($SETTINGS['ldap_mode']) && $SETTINGS['ldap_mode'] === '1' ? '1' : '0'; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2'>
                                <div class='col-5'>
                                    <?php echo langHdl('settings_ldap_type'); ?>
                                </div>
                                <div class='col-7'>
                                    <select class='form-control form-control-sm' id='ldap_type' >
                                        <option value=''>-- <?php echo langHdl('select'); ?> --</option>
                                        <option value="windows"
                                        <?php echo isset($SETTINGS['ldap_type']) === true && $SETTINGS['ldap_type'] === 'windows' ? ' selected' : ''; ?>
                                        >Windows / Active Directory</option>
                                        <option value="posix"
                                        <?php echo isset($SETTINGS['ldap_type']) === true && $SETTINGS['ldap_type'] === 'posix' ? ' selected' : ''; ?>
                                        >Posix / OpenLDAP (RFC2307)</option>
                                        <option value="posix-search"
                                        <?php echo isset($SETTINGS['ldap_type']) === true && $SETTINGS['ldap_type'] === 'posix-search' ? ' selected' : ''; ?>
                                        >Posix / OpenLDAP (RFC2307) Search Based</option>
                                    </select>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap tr-windows tr-posix tr-posix-search<?php echo (isset($ldap_type) === true) ? '' : ' hidden'; ?>'>
                                <div class='col-5'>
                                    <?php echo langHdl('settings_ldap_domain'); ?>
                                </div>
                                <div class='col-7'>
                                    <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_suffix' value='<?php echo isset($SETTINGS['ldap_suffix']) ? $SETTINGS['ldap_suffix'] : ''; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-windows tr-ldap tr-posix<?php echo (isset($ldap_type) === true && $ldap_type !== 'posix-search') ? '' : ' hidden'; ?>'>
                                <div class='col-5'>
                                    <?php echo langHdl('settings_ldap_domain_dn'); ?>
                                </div>
                                <div class='col-7'>
                                    <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_domain_dn' value='<?php echo isset($SETTINGS['ldap_domain_dn']) ? $SETTINGS['ldap_domain_dn'] : ''; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap tr-posix-search<?php echo (isset($ldap_type) === true && $ldap_type === 'posix-search') ? '' : ' hidden'; ?>'>
                                <div class='col-5'>
                                    <?php echo langHdl('settings_ldap_object_class'); ?>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo langHdl('settings_ldap_object_class_tip'); ?>
                                    </small>
                                </div>
                                <div class='col-7'>
                                    <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_object_class' value='<?php echo isset($SETTINGS['ldap_object_class']) ? $SETTINGS['ldap_object_class'] : 'posixAccount'; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap tr-posix-search<?php echo (isset($ldap_type) === true && $ldap_type === 'posix-search') ? '' : ' hidden'; ?>'>
                                <div class='col-5'>
                                    <?php echo langHdl('settings_ldap_user_attribute'); ?>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo langHdl('settings_ldap_user_attribute_tip'); ?>
                                    </small>
                                </div>
                                <div class='col-7'>
                                    <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_user_attribute' value='<?php echo isset($SETTINGS['ldap_user_attribute']) ? $SETTINGS['ldap_user_attribute'] : ''; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap tr-posix-search<?php echo (isset($ldap_type) === true && $ldap_type === 'posix-search') ? '' : ' hidden'; ?>'>
                                <div class='col-5'>
                                    <?php echo langHdl('settings_ldap_usergroup'); ?>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo langHdl('settings_ldap_usergroup_tip'); ?>
                                    </small>
                                </div>
                                <div class='col-7'>
                                    <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_usergroup' value='<?php echo isset($SETTINGS['ldap_usergroup']) ? $SETTINGS['ldap_usergroup'] : ''; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap tr-posix-search<?php echo (isset($ldap_type) === true && $ldap_type === 'posix-search') ? '' : ' hidden'; ?>'>
                                <div class='col-5'>
                                    <?php echo langHdl('settings_ldap_bind_dn'); ?>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo langHdl('settings_ldap_bind_dn_tip'); ?>
                                    </small>
                                </div>
                                <div class='col-7'>
                                    <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_bind_dn' value='<?php echo isset($SETTINGS['ldap_bind_dn']) ? $SETTINGS['ldap_bind_dn'] : ''; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap tr-posix-search<?php echo (isset($ldap_type) === true && $ldap_type === 'posix-search') ? '' : ' hidden'; ?>'>
                                <div class='col-5'>
                                    <?php echo langHdl('settings_ldap_bind_passwd'); ?>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo langHdl('settings_ldap_bind_passwd_tip'); ?>
                                    </small>
                                </div>
                                <div class='col-7'>
                                    <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_bind_passwd' value='<?php echo isset($SETTINGS['ldap_bind_passwd']) ? $SETTINGS['ldap_bind_passwd'] : ''; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap tr-windows<?php echo (isset($ldap_type) === true && $ldap_type === 'posix-search') ? '' : ' hidden'; ?>'>
                                <div class='col-5'>
                                    <?php echo langHdl('settings_ldap_search_base'); ?>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo langHdl('settings_ldap_search_base_tip'); ?>
                                    </small>
                                </div>
                                <div class='col-7'>
                                    <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_search_base' value='<?php echo isset($SETTINGS['ldap_search_base']) ? $SETTINGS['ldap_search_base'] : ''; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap tr-posix-search<?php echo (isset($ldap_type) === true && $ldap_type === 'windows') ? '' : ' hidden'; ?>'>
                                <div class='col-5'>
                                    <?php echo langHdl('settings_ldap_allowed_usergroup'); ?>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo langHdl('settings_ldap_allowed_usergroup_tip'); ?>
                                    </small>
                                </div>
                                <div class='col-7'>
                                    <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_allowed_usergroup' value='<?php echo isset($SETTINGS['ldap_allowed_usergroup']) ? $SETTINGS['ldap_allowed_usergroup'] : ''; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap tr-posix-search<?php echo (isset($ldap_type) === true && $ldap_type === 'windows') ? '' : ' hidden'; ?>'>
                                <div class='col-5'>
                                    <?php echo langHdl('settings_ldap_domain_controler'); ?>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo langHdl('settings_ldap_domain_controler_tip'); ?>
                                    </small>
                                </div>
                                <div class='col-7'>
                                    <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_domain_controler' value='<?php echo isset($SETTINGS['ldap_domain_controler']) ? $SETTINGS['ldap_domain_controler'] : ''; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap tr-windows tr-posix tr-posix-search'>
                                <div class='col-10'>
                                    <?php echo langHdl('settings_ldap_port'); ?>
                                </div>
                                <div class='col-2'>
                                    <input type='text' class='form-control form-control-sm setting-ldap' id='ldap_port' value='<?php echo isset($SETTINGS['ldap_port']) ? $SETTINGS['ldap_port'] : '389'; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap tr-windows tr-posix tr-posix-search'>
                                <div class='col-10'>
                                    <?php echo langHdl('settings_ldap_ssl'); ?>
                                </div>
                                <div class='col-2'>
                                    <div class='toggle toggle-modern' id='ldap_ssl' data-toggle-on='<?php echo isset($SETTINGS['ldap_ssl']) === true && $SETTINGS['ldap_ssl'] === '1' ? 'true' : 'false'; ?>'></div><input type='hidden' id='ldap_ssl_input' value='<?php echo isset($SETTINGS['ldap_ssl']) && $SETTINGS['ldap_ssl'] === '1' ? '1' : '0'; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap tr-windows tr-posix tr-posix-search'>
                                <div class='col-10'>
                                    <?php echo langHdl('settings_ldap_tls'); ?>
                                </div>
                                <div class='col-2'>
                                    <div class='toggle toggle-modern' id='ldap_tls' data-toggle-on='<?php echo isset($SETTINGS['ldap_tls']) === true && $SETTINGS['ldap_tls'] === '1' ? 'true' : 'false'; ?>'></div><input type='hidden' id='ldap_tls_input' value='<?php echo isset($SETTINGS['ldap_tls']) && $SETTINGS['ldap_tls'] === '1' ? '1' : '0'; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap tr-windows tr-posix tr-posix-search'>
                                <div class='col-10'>
                                    <?php echo langHdl('settings_ldap_elusers'); ?>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo langHdl('settings_ldap_elusers_tip'); ?>
                                    </small>
                                </div>
                                <div class='col-2'>
                                    <div class='toggle toggle-modern' id='ldap_elusers' data-toggle-on='<?php echo isset($SETTINGS['ldap_elusers']) === true && $SETTINGS['ldap_elusers'] === '1' ? 'true' : 'false'; ?>'></div><input type='hidden' id='ldap_elusers_input' value='<?php echo isset($SETTINGS['ldap_elusers']) && $SETTINGS['ldap_elusers'] === '1' ? '1' : '0'; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap tr-windows tr-posix tr-posix-search'>
                                <div class='col-10'>
                                    <?php echo langHdl('settings_ldap_and_local_authentication'); ?>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo langHdl('settings_ldap_and_local_authentication_tip'); ?>
                                    </small>
                                </div>
                                <div class='col-2'>
                                    <div class='toggle toggle-modern' id='ldap_and_local_authentication' data-toggle-on='<?php echo isset($SETTINGS['ldap_and_local_authentication']) === true && $SETTINGS['ldap_and_local_authentication'] === '1' ? 'true' : 'false'; ?>'></div><input type='hidden' id='ldap_and_local_authentication_input' value='<?php echo isset($SETTINGS['ldap_and_local_authentication']) && $SETTINGS['ldap_and_local_authentication'] === '1' ? '1' : '0'; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap tr-windows tr-posix tr-posix-search'>
                                <div class='col-10'>
                                    <?php echo langHdl('settings_disable_forgot_password_link'); ?>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo langHdl('settings_disable_forgot_password_link_tip'); ?>
                                    </small>
                                </div>
                                <div class='col-2'>
                                    <div class='toggle toggle-modern' id='disable_show_forgot_pwd_link' data-toggle-on='<?php echo isset($SETTINGS['disable_show_forgot_pwd_link']) === true && $SETTINGS['disable_show_forgot_pwd_link'] === '1' ? 'true' : 'false'; ?>'></div><input type='hidden' id='disable_show_forgot_pwd_link_input' value='<?php echo isset($SETTINGS['disable_show_forgot_pwd_link']) && $SETTINGS['disable_show_forgot_pwd_link'] === '1' ? '1' : '0'; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap tr-windows tr-posix tr-posix-search'>
                                <div class='col-5'>
                                    <?php echo langHdl('newly_created_user_is_administrated_by'); ?>
                                </div>
                                <div class='col-7'>
                                    <select class='form-control form-control-sm select2' id='ldap_new_user_is_administrated_by'>
                                    </select>
                                </div>
                            </div>

                            <div class='row mb-2 tr-ldap tr-windows tr-posix tr-posix-search'>
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
