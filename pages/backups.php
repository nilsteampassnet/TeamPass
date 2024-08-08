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
 * @file      backups.php
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
$session = SessionManager::getSession();
$request = Request::createFromGlobals();
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('backups') === false) {
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
                                                            <button class="btn btn-secondary btn-no-click infotip key-generate" title="<?php echo $lang->get('pw_generate'); ?>"><i class="fas fa-random"></i></button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row mt-3 hidden" id="onthefly-backup-progress"></div>
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
                                            <div class="alert alert-info ml-2 mt-3 mr-2 hidden" id="onthefly-restore-progress">
                                                <h5><i class="icon fa fa-info mr-2"></i><?php echo $lang->get('in_progress'); ?></h5>
                                                <i class="mr-2 fa-solid fa-rocket fa-beat"></i><?php echo $lang->get('restore_in_progress');?> <b><span id="onthefly-restore-progress-text">0</span>%</b>
                                            </div>
                                            <div class="row mt-3 hidden" id="onthefly-restore-finished"></div>
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
