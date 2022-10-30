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
 * @file      utilities.deletion.php
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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'utilities.deletion', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Load template
require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">
                    <i class="fas fa-trash-alt mr-2"></i><?php echo langHdl('recycled_bin'); ?>
                </h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <!-- column Roles list -->
            <div class="col-md-12">
                <div class="card" id="recycled-bin">
                    <div class="card-header p-2">
                        <input type="checkbox" id="toggle-all">
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2 ml-2" data-action="restore">
                            <i class="fas fa-undo-alt mr-2"></i><?php echo langHdl('restore'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="delete">
                            <i class="far fa-trash-alt mr-2"></i><?php echo langHdl('delete'); ?>
                        </button>
                    </div><!-- /.card-header -->

                    <div class="card-header p-2 hidden" id="recycled-bin-confirm-restore">
                        <div class="card card-warning">
                            <div class="card-header">
                                <h4><?php echo langHdl('restore'); ?></h4>
                            </div>
                            <div class="card-body">

                            </div>
                            <div class="card-footer">
                                <button type="button" class="btn btn-warning tp-action" data-action="submit-restore"><?php echo langHdl('submit'); ?></button>
                                <button type="button" class="btn btn-default float-right tp-action" data-action="cancel-restore"><?php echo langHdl('cancel'); ?></button>
                            </div>
                        </div>
                    </div>

                    <div class="card-header p-2 hidden" id="recycled-bin-confirm-delete">
                        <div class="card card-danger">
                            <div class="card-header">
                                <h4><?php echo langHdl('delete'); ?></h4>
                            </div>
                            <div class="card-body">

                            </div>
                            <div class="card-footer">
                                <button type="button" class="btn btn-danger tp-action" data-action="submit-delete"><?php echo langHdl('submit'); ?></button>
                                <button type="button" class="btn btn-default float-right tp-action" data-action="cancel-delete"><?php echo langHdl('cancel'); ?></button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <h5><?php echo langHdl('deleted_folders'); ?></h5>
                        <div class="table table-responsive" id="recycled-folders"></div>

                        <h5><?php echo langHdl('deleted_items'); ?></h5>
                        <div class="table table-responsive" id="recycled-items"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
