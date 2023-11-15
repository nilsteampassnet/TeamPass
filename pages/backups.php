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
 * @file      backups.php
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
use TeampassClasses\Language\Language;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\PerformChecks\PerformChecks;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();
$lang = new Language($superGlobal->get('user_language', 'SESSION', 'user')); 

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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('backups') === false) {
    // Not allowed page
    $superGlobal->put('code', ERR_NOT_ALLOWED, 'SESSION', 'error');
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //

// Decrypt key
$localEncryptionKey = isset($SETTINGS['bck_script_passkey']) === true ?
    cryption($SETTINGS['bck_script_passkey'], '', 'decrypt', $SETTINGS)['string'] : '';
?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-database mr-2"></i><?php echo $lang->get('backups'); ?></h1>
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
                        <h3 class='card-title'><?php echo $lang->get('backup_and_restore'); ?></h3>
                    </div>

                    <div class='card-body'>
                        <ul class="nav nav-tabs">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#oneshot" role="tab" aria-controls="oneshot"><?php echo $lang->get('on_the_fly'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#scheduled" role="tab" aria-controls="scheduled"><?php echo $lang->get('scheduled'); ?></a>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="oneshot" role="tabpanel" aria-labelledby="oneshot-tab">

                                <div class="mt-4">
                                    <div class="callout callout-info">
                                        <h4><?php echo $lang->get('admin_action_db_backup'); ?></h4>
                                        <small class="form-text text-muted mt-4">
                                            <?php echo $lang->get('explanation_for_oneshot_backup'); ?>
                                        </small>

                                        <div class="form-group mt-4">
                                            <div class="row mt-1">
                                                <div class="col-3">
                                                    <span class="text-bold"><?php echo $lang->get('encrypt_key'); ?></span>
                                                </div>
                                                <div class="col-9">
                                                    <div class="input-group mb-0">
                                                        <input type="text" class="form-control form-control-sm" id="onthefly-backup-key" value="<?php echo $localEncryptionKey; ?>">
                                                        <div class="input-group-append">
                                                            <button class="btn btn-outline-secondary btn-no-click infotip key-generate" title="<?php echo $lang->get('pw_generate'); ?>"><i class="fas fa-random"></i></button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row mt-3 hidden" id="onthefly-backup-progress">

                                            </div>
                                            <div class="row mt-3">
                                                <button class="btn btn-info ml-1 start btn-choose-file" data-action="onthefly-backup"><?php echo $lang->get('perform_backup'); ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <div class="callout callout-info">
                                        <h4><?php echo $lang->get('admin_action_db_restore'); ?></h4>
                                        <small class="form-text text-muted mt-4">
                                            <?php echo $lang->get('explanation_for_oneshot_restore'); ?>
                                        </small>

                                        <div class="form-group mt-4">
                                            <div class="row mt-1">
                                                <div class="col-3">
                                                    <span class="text-bold"><?php echo $lang->get('encrypt_key'); ?></span>
                                                </div>
                                                <div class="col-9">
                                                    <input type="text" class="form-control form-control-sm" id="onthefly-restore-key" value="<?php echo $localEncryptionKey; ?>">
                                                </div>
                                            </div>
                                            <div class="row mt-1">
                                                <div class="col-3">
                                                    <span class="text-bold"><?php echo $lang->get('backup_select'); ?></span>
                                                </div>
                                                <div class="col-9 input-group" id="onthefly-restore-file">
                                                    <button class="btn btn-default btn-choose-file" id="onthefly-restore-file-select"><?php echo $lang->get('choose_file'); ?></button>
                                                    <span class="ml-2" id="onthefly-restore-file-text"></span>
                                                </div>
                                            </div>
                                            <div class="row mt-3 hidden" id="onthefly-restore-progress">

                                            </div>
                                            <div class="row mt-3">
                                                <button class="btn btn-info ml-1 start" data-action="onthefly-restore"><?php echo $lang->get('perform_restore'); ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="tab-pane fade show active" id="scheduled" role="tabpanel" aria-labelledby="scheduled-tab">

                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
