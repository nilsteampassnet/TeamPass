<?php

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
 * @file      uploads.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as RequestLocal;
use TeampassClasses\Language\Language;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = RequestLocal::createFromGlobals();
$lang = new Language(); 

// Load config if $SETTINGS not defined
if (empty($SETTINGS)) {
    $configManager = new ConfigManager();
    $SETTINGS = $configManager->getAllSettings();
}

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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('uploads') === false) {
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
$ldap_type = isset($SETTINGS['ldap_type']) ? $SETTINGS['ldap_type'] : '';

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-file-upload mr-2"></i><?php echo $lang->get('uploads'); ?></h1>
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
                        <h3 class='card-title'><?php echo $lang->get('uploads_configuration'); ?></h3>
                    </div>
                    <!-- /.card-header -->
                    <!-- form start -->
                    <div class='card-body'>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo $lang->get('settings_upload_maxfilesize'); ?>
                                <small class="form-text text-muted">
                                    <?php echo $lang->get('settings_upload_maxfilesize_tip'); ?>
                                </small>
                            </div>
                            <div class="col-4">
                                <input type="text" class="form-control form-control-sm" id="upload_maxfilesize" value="<?php echo isset($SETTINGS['upload_maxfilesize']) === true ? $SETTINGS['upload_maxfilesize'] : ''; ?>">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo $lang->get('upload_empty_file'); ?>
                            </div>
                            <div class="col-4">
                                <div class="toggle toggle-modern" id="upload_zero_byte_file" data-toggle-on="<?php echo isset($SETTINGS['upload_zero_byte_file']) === true && $SETTINGS['upload_zero_byte_file'] === '1' ? 'true' : 'false'; ?>"></div><input type="hidden" id="upload_zero_byte_file_input" value="<?php echo isset($SETTINGS['upload_zero_byte_file']) && $SETTINGS['upload_zero_byte_file'] === '1' ? '1' : '0'; ?>">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo $lang->get('upload_any_extension_file'); ?>
                                <small class="form-text text-muted">
                                    <?php echo $lang->get('upload_any_extension_file_tip'); ?>
                                </small>
                            </div>
                            <div class="col-4">
                                <div class="toggle toggle-modern" id="upload_all_extensions_file" data-toggle-on="<?php echo isset($SETTINGS['upload_all_extensions_file']) === true && $SETTINGS['upload_all_extensions_file'] === '1' ? 'true' : 'false'; ?>"></div><input type="hidden" id="upload_all_extensions_file_input" value="<?php echo isset($SETTINGS['upload_all_extensions_file']) && $SETTINGS['upload_all_extensions_file'] === '1' ? '1' : '0'; ?>">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo $lang->get('settings_upload_docext'); ?>
                                <small class="form-text text-muted">
                                    <?php echo $lang->get('settings_upload_docext_tip'); ?>
                                </small>
                            </div>
                            <div class="col-4">
                                <input type="text" class="form-control form-control-sm" id="upload_docext" value="<?php echo isset($SETTINGS['upload_docext']) === true ? $SETTINGS['upload_docext'] : 'doc,docx,dotx,xls,xlsx,xltx,rtf,csv,txt,pdf,ppt,pptx,pot,dotx,xltx'; ?>">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo $lang->get('settings_upload_imagesext'); ?>
                                <small class="form-text text-muted">
                                    <?php echo $lang->get('settings_upload_imagesext_tip'); ?>
                                </small>
                            </div>
                            <div class="col-4">
                                <input type="text" class="form-control form-control-sm" id="upload_imagesext" value="<?php echo isset($SETTINGS['upload_imagesext']) === true ? $SETTINGS['upload_imagesext'] : 'jpg,jpeg,gif,png'; ?>">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo $lang->get('settings_upload_pkgext'); ?>
                                <small class="form-text text-muted">
                                    <?php echo $lang->get('settings_upload_pkgext_tip'); ?>
                                </small>
                            </div>
                            <div class="col-4">
                                <input type="text" class="form-control form-control-sm" id="upload_pkgext" value="<?php echo isset($SETTINGS['upload_pkgext']) === true ? $SETTINGS['upload_pkgext'] : '7z,rar,tar,zip'; ?>">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo $lang->get('settings_upload_otherext'); ?>
                                <small class="form-text text-muted">
                                    <?php echo $lang->get('settings_upload_otherext_tip'); ?>
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
                        <h3 class='card-title'><?php echo $lang->get('settings_upload_imageresize_options'); ?></h3>
                    </div>
                    <!-- /.card-header -->
                    <!-- form start -->
                    <div class='card-body'>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo $lang->get('settings_upload_imageresize_options'); ?>
                                <small class="form-text text-muted">
                                    <?php echo $lang->get('settings_upload_imageresize_options_tip'); ?>
                                </small>
                            </div>
                            <div class="col-4">
                                <div class="toggle toggle-modern" id="upload_imageresize_options_input" data-toggle-on="<?php echo isset($SETTINGS['upload_imageresize_options_input']) === true && $SETTINGS['upload_imageresize_options_input'] === '1' ? 'true' : 'false'; ?>"></div><input type="hidden" id="upload_imageresize_options_input" value="<?php echo isset($SETTINGS['upload_imageresize_options_input']) && $SETTINGS['upload_imageresize_options_input'] === '1' ? '1' : '0'; ?>">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo $lang->get('settings_upload_imageresize_options_w'); ?>
                            </div>
                            <div class="col-4">
                                <input type="text" class="form-control form-control-sm" id="upload_imageresize_width" value="<?php echo isset($SETTINGS['upload_imageresize_width']) === true ? $SETTINGS['upload_imageresize_width'] : '800'; ?>">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo $lang->get('settings_upload_imageresize_options_h'); ?>
                            </div>
                            <div class="col-4">
                                <input type="text" class="form-control form-control-sm" id="upload_imageresize_height" value="<?php echo isset($SETTINGS['upload_imageresize_height']) === true ? $SETTINGS['upload_imageresize_height'] : '600'; ?>">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-8">
                                <?php echo $lang->get('settings_upload_imageresize_options_q'); ?>
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