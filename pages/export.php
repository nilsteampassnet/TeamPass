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
 * @file      export.php
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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], curPage($SETTINGS), $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Load
require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

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
                    <div class="form-group">
                        <label><?php echo langHdl('export_format_type'); ?></label>
                        <select class="form-control select2" style="width:100%;" id="export-format">
                            <option value="csv"><?php echo langHdl('csv'); ?></option>
                            <option value="pdf"><?php echo langHdl('pdf'); ?></option>
                        </select>
                    </div>

                    <div class="row mt-3">
                        <div class="form-group col-12">
                            <label><?php echo langHdl('select_folders_to_export'); ?></label>
                            <select class="form-control select2-all" style="width:100%;" id="export-folders" multiple>
                            </select>
                        </div>
                    </div>

                    <div class="form-group hidden" id="pdf-password">
                        <label><?php echo langHdl('file_protection_password'); ?></label>
                        <input type="password" class="form-control form-item-control" id="export-password">
                    </div>

                    <div class="form-group">
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
                    <button type="submit" class="btn btn-primary" id="form-item-export-perform"><?php echo langHdl('perform'); ?></button>
                </div>
            </div>
        </div>
    </div>
</section>
