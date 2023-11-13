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
 * @file      export.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
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
$checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('export') === false) {
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
 

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark"><i class="fas fa-file-export mr-2"></i><?php echo langHdl('export_items'); ?></h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<!-- Main content -->
<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div class="row mt-3">
                        <div class="form-group col-12">
                            <label><?php echo langHdl('select_folders_to_export'); ?></label>
                            <select class="form-control select2-all" style="width:100%;" id="export-folders" multiple>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><?php echo langHdl('export_format_type'); ?></label>
                        <select class="form-control select2" style="width:100%;" id="export-format">
                            <option value="csv"><?php echo langHdl('csv'); ?></option>
                            <?php
                            if (isset($SETTINGS['settings_offline_mode']) === true && (int) $SETTINGS['settings_offline_mode'] === 1) {
                                echo '<option value="html">'.strtoupper(langHdl('html')).'</option>';
                            }
                            ?>
                            <option value="pdf"><?php echo langHdl('pdf'); ?></option>
                        </select>
                    </div>

                    <div id="pwd" class="hidden">
                        <div class="form-group mb-1" id="pdf-password">
                            <label><?php echo langHdl('file_protection_password'); ?></label>
                            <input type="password" class="form-control form-item-control col-md-12" id="export-password">
                        </div>
                        <div class="container-fluid">
                            <div class="row">
                                <div class="col-md-12 justify-content-center">
                                    <div id="export-password-strength" class="justify-content-center" style=""></div>
                                    <input type="hidden" id="export-password-complex" value="0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <label><?php echo langHdl('filename'); ?></label>
                        <input type="text" class="form-control form-item-control" id="export-filename" value="Teampass_export_<?php echo time(); ?>">
                    </div>

                    <div class="alert alert-warning mb-3 mt-3 hidden" id="export-progress">
                        <div class="card-body">
                            <span></span>
                        </div>
                    </div>

                </div>

                <div class="card-footer">
                    <a class="hidden" href='' target='_blank' id="download-export-file">
                        <button type="button" class="btn btn-success"><i class="fa-solid fa-file-export mr-2"></i><?php echo langHdl('download'); ?></button>
                    </a>
                    <button type="submit" class="btn btn-primary" id="form-item-export-perform"><?php echo langHdl('perform'); ?></button>
                </div>
            </div>
        </div>
    </div>
</section>
