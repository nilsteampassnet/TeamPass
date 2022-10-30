<?php

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      uploads.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2022 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
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
    exit();
}

// Load template
require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

// LDAP type currently loaded
$ldap_type = isset($SETTINGS['ldap_type']) ? $SETTINGS['ldap_type'] : '';

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-file-upload mr-2"></i><?php echo langHdl('uploads'); ?></h1>
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
                        <h3 class='card-title'><?php echo langHdl('uploads_configuration'); ?></h3>
                    </div>
                    <!-- /.card-header -->
                    <!-- form start -->
                    <div class='card-body'>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo langHdl('settings_upload_maxfilesize'); ?>
                                <small class="form-text text-muted">
                                    <?php echo langHdl('settings_upload_maxfilesize_tip'); ?>
                                </small>
                            </div>
                            <div class="col-4">
                                <input type="text" class="form-control form-control-sm" id="upload_maxfilesize" value="<?php echo isset($SETTINGS['upload_maxfilesize']) === true ? $SETTINGS['upload_maxfilesize'] : ''; ?>">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo langHdl('upload_empty_file'); ?>
                            </div>
                            <div class="col-4">
                                <div class="toggle toggle-modern" id="upload_zero_byte_file" data-toggle-on="<?php echo isset($SETTINGS['upload_zero_byte_file']) === true && $SETTINGS['upload_zero_byte_file'] === '1' ? 'true' : 'false'; ?>"></div><input type="hidden" id="upload_zero_byte_file_input" value="<?php echo isset($SETTINGS['upload_zero_byte_file']) && $SETTINGS['upload_zero_byte_file'] === '1' ? '1' : '0'; ?>">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo langHdl('upload_any_extension_file'); ?>
                                <small class="form-text text-muted">
                                    <?php echo langHdl('upload_any_extension_file_tip'); ?>
                                </small>
                            </div>
                            <div class="col-4">
                                <div class="toggle toggle-modern" id="upload_all_extensions_file" data-toggle-on="<?php echo isset($SETTINGS['upload_all_extensions_file']) === true && $SETTINGS['upload_all_extensions_file'] === '1' ? 'true' : 'false'; ?>"></div><input type="hidden" id="upload_all_extensions_file_input" value="<?php echo isset($SETTINGS['upload_all_extensions_file']) && $SETTINGS['upload_all_extensions_file'] === '1' ? '1' : '0'; ?>">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo langHdl('settings_upload_docext'); ?>
                                <small class="form-text text-muted">
                                    <?php echo langHdl('settings_upload_docext_tip'); ?>
                                </small>
                            </div>
                            <div class="col-4">
                                <input type="text" class="form-control form-control-sm" id="upload_docext" value="<?php echo isset($SETTINGS['upload_docext']) === true ? $SETTINGS['upload_docext'] : 'doc,docx,dotx,xls,xlsx,xltx,rtf,csv,txt,pdf,ppt,pptx,pot,dotx,xltx'; ?>">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo langHdl('settings_upload_imagesext'); ?>
                                <small class="form-text text-muted">
                                    <?php echo langHdl('settings_upload_imagesext_tip'); ?>
                                </small>
                            </div>
                            <div class="col-4">
                                <input type="text" class="form-control form-control-sm" id="upload_imagesext" value="<?php echo isset($SETTINGS['upload_imagesext']) === true ? $SETTINGS['upload_imagesext'] : 'jpg,jpeg,gif,png'; ?>">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo langHdl('settings_upload_pkgext'); ?>
                                <small class="form-text text-muted">
                                    <?php echo langHdl('settings_upload_pkgext_tip'); ?>
                                </small>
                            </div>
                            <div class="col-4">
                                <input type="text" class="form-control form-control-sm" id="upload_pkgext" value="<?php echo isset($SETTINGS['upload_pkgext']) === true ? $SETTINGS['upload_pkgext'] : '7z,rar,tar,zip'; ?>">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo langHdl('settings_upload_otherext'); ?>
                                <small class="form-text text-muted">
                                    <?php echo langHdl('settings_upload_otherext_tip'); ?>
                                </small>
                            </div>
                            <div class="col-4">
                                <input type="text" class="form-control form-control-sm" id="upload_otherext" value="<?php echo isset($SETTINGS['upload_otherext']) === true ? $SETTINGS['upload_otherext'] : 'sql,xml'; ?>">
                            </div>
                        </div>

                    </div>
                </div>

                <div class='card card-primary'>
                    <div class='card-header'>
                        <h3 class='card-title'><?php echo langHdl('settings_upload_imageresize_options'); ?></h3>
                    </div>
                    <!-- /.card-header -->
                    <!-- form start -->
                    <div class='card-body'>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo langHdl('settings_upload_imageresize_options'); ?>
                                <small class="form-text text-muted">
                                    <?php echo langHdl('settings_upload_imageresize_options_tip'); ?>
                                </small>
                            </div>
                            <div class="col-4">
                                <div class="toggle toggle-modern" id="upload_imageresize_options_input" data-toggle-on="<?php echo isset($SETTINGS['upload_imageresize_options_input']) === true && $SETTINGS['upload_imageresize_options_input'] === '1' ? 'true' : 'false'; ?>"></div><input type="hidden" id="upload_imageresize_options_input" value="<?php echo isset($SETTINGS['upload_imageresize_options_input']) && $SETTINGS['upload_imageresize_options_input'] === '1' ? '1' : '0'; ?>">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo langHdl('settings_upload_imageresize_options_w'); ?>
                            </div>
                            <div class="col-4">
                                <input type="text" class="form-control form-control-sm" id="upload_imageresize_width" value="<?php echo isset($SETTINGS['upload_imageresize_width']) === true ? $SETTINGS['upload_imageresize_width'] : '800'; ?>">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo langHdl('settings_upload_imageresize_options_h'); ?>
                            </div>
                            <div class="col-4">
                                <input type="text" class="form-control form-control-sm" id="upload_imageresize_height" value="<?php echo isset($SETTINGS['upload_imageresize_height']) === true ? $SETTINGS['upload_imageresize_height'] : '600'; ?>">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo langHdl('settings_upload_imageresize_options_q'); ?>
                            </div>
                            <div class="col-4">
                                <input type="text" class="form-control form-control-sm" id="upload_imageresize_quality" value="<?php echo isset($SETTINGS['upload_imageresize_quality']) === true ? $SETTINGS['upload_imageresize_quality'] : '90'; ?>">
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>