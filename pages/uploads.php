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
 * @copyright 2009-2023 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */

use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\PerformChecks\PerformChecks;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => returnIfSet($superGlobal->get('type', 'POST')),
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($superGlobal->get('user_id', 'SESSION'), null),
        'user_key' => returnIfSet($superGlobal->get('key', 'SESSION'), null),
        'CPM' => returnIfSet($superGlobal->get('CPM', 'SESSION'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('uploads') === false) {
    // Not allowed page
    $superGlobal->put('code', ERR_NOT_ALLOWED, 'SESSION', 'error');
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Load language file
require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$superGlobal->get('user_language', 'SESSION', 'user').'.php';

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //
 

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