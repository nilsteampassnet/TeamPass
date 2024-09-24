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
 * @file      utilities.deletion.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('utilities.deletion') === false) {
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
 
?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">
                    <i class="fas fa-trash-alt mr-2"></i><?php echo $lang->get('recycled_bin'); ?>
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
                            <i class="fas fa-undo-alt mr-2"></i><?php echo $lang->get('restore'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="delete">
                            <i class="far fa-trash-alt mr-2"></i><?php echo $lang->get('delete'); ?>
                        </button>
                    </div><!-- /.card-header -->

                    <div class="card-header p-2 hidden" id="recycled-bin-confirm-restore">
                        <div class="card card-warning">
                            <div class="card-header">
                                <h4><?php echo $lang->get('restore'); ?></h4>
                            </div>
                            <div class="card-body">

                            </div>
                            <div class="card-footer">
                                <button type="button" class="btn btn-warning tp-action" data-action="submit-restore"><?php echo $lang->get('submit'); ?></button>
                                <button type="button" class="btn btn-default float-right tp-action" data-action="cancel-restore"><?php echo $lang->get('cancel'); ?></button>
                            </div>
                        </div>
                    </div>

                    <div class="card-header p-2 hidden" id="recycled-bin-confirm-delete">
                        <div class="card card-danger">
                            <div class="card-header">
                                <h4><?php echo $lang->get('delete'); ?></h4>
                            </div>
                            <div class="card-body">

                            </div>
                            <div class="card-footer">
                                <button type="button" class="btn btn-danger tp-action" data-action="submit-delete"><?php echo $lang->get('submit'); ?></button>
                                <button type="button" class="btn btn-default float-right tp-action" data-action="cancel-delete"><?php echo $lang->get('cancel'); ?></button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <h5><?php echo $lang->get('deleted_folders'); ?></h5>
                        <div class="table table-responsive" id="recycled-folders"></div>

                        <h5><?php echo $lang->get('deleted_items'); ?></h5>
                        <div class="table table-responsive" id="recycled-items"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
