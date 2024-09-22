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
 * @file      utilities.logs.php
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('utilities.logs') === false) {
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
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-history mr-2"></i><?php echo $lang->get('logs'); ?></h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->


<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-tabs">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#connections" aria-controls="connections" aria-selected="true"><?php echo $lang->get('connections'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#failed" role="tab" aria-controls="failed" aria-selected="false"><?php echo $lang->get('failed_logins'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#errors" role="tab" aria-controls="errors" aria-selected="false"><?php echo $lang->get('errors'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#copy" role="tab" aria-controls="copy" aria-selected="false"><?php echo $lang->get('at_copy'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#admin" role="tab" aria-controls="admin" aria-selected="false"><?php echo $lang->get('admin'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#items" role="tab" aria-controls="items" aria-selected="false"><?php echo $lang->get('items'); ?></a>
                            </li>
                        </ul>


                        <div class="tab-content mt-1" id="myTabContent">
                            <div class="tab-pane fade show active" id="connections" role="tabpanel" aria-labelledby="connections-tab">
                                <table class="table table-striped nowrap table-responsive-sm" id="table-connections" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th><?php echo $lang->get('date'); ?></th>
                                            <th><?php echo $lang->get('action'); ?></th>
                                            <th><?php echo $lang->get('user'); ?></th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                            <div class="tab-pane fade" id="errors" role="tabpanel" aria-labelledby="errors-tab">
                                <table class="table table-striped nowrap table-responsive-sm" id="table-errors" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th><?php echo $lang->get('date'); ?></th>
                                            <th><?php echo $lang->get('label'); ?></th>
                                            <th><?php echo $lang->get('user'); ?></th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                            <div class="tab-pane fade" id="copy" role="tabpanel" aria-labelledby="copy-tab">
                                <table class="table table-striped nowrap table-responsive-sm" id="table-copy" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th><?php echo $lang->get('date'); ?></th>
                                            <th><?php echo $lang->get('label'); ?></th>
                                            <th><?php echo $lang->get('user'); ?></th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                            <div class="tab-pane fade" id="admin" role="tabpanel" aria-labelledby="admin-tab">
                                <table class="table table-striped nowrap table-responsive-sm" id="table-admin" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th><?php echo $lang->get('date'); ?></th>
                                            <th><?php echo $lang->get('author'); ?></th>
                                            <th><?php echo $lang->get('action'); ?></th>
                                            <th><?php echo $lang->get('who'); ?></th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                            <div class="tab-pane fade" id="items" role="tabpanel" aria-labelledby="items-tab">
                                <table class="table table-striped nowrap table-responsive-sm" id="table-items" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th><?php echo $lang->get('date'); ?></th>
                                            <th><?php echo $lang->get('id'); ?></th>
                                            <th><?php echo $lang->get('label'); ?></th>
                                            <th><?php echo $lang->get('folder'); ?></th>
                                            <th><?php echo $lang->get('user'); ?></th>
                                            <th><?php echo $lang->get('action'); ?></th>
                                            <th><?php echo $lang->get('at_personnel'); ?></th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                            <div class="tab-pane fade" id="failed" role="tabpanel" aria-labelledby="failed-tab">
                                <table class="table table-striped nowrap table-responsive-sm" id="table-failed">
                                    <thead>
                                        <tr>
                                            <th><?php echo $lang->get('date'); ?></th>
                                            <th><?php echo $lang->get('label'); ?></th>
                                            <th><?php echo $lang->get('user'); ?></th>
                                            <th><?php echo $lang->get('ip'); ?></th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer<?php
                                            echo $session->has('user-admin') && (int) $session->get('user-admin') && null !== $session->get('user-admin') && (int) $session->get('user-admin') === 1 ? '' : ' hidden';
                                            ?>">
                        <div class="form-group">
                            <h5><i class="fas fa-broom mr-2"></i><?php echo $lang->get('purge') . ' ' . $lang->get('date_range'); ?></h5>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">
                                        <i class="fas fa-calendar"></i>
                                    </span>
                                </div>
                                <input type="text" class="form-control float-right" id="purge-date-range">
                                <span class="input-group-append">
                                    <button type="button" class="btn btn-info btn-flat" id="clear-purge-date"><i class="fas fa-broom"></i></button>
                                </span>
                            </div>
                        </div>

                        <h5><i class="fas fa-filter mr-2"></i><?php echo $lang->get('filters'); ?></h5>
                        <div class="row">
                            <div class="col-sm-6">
                                <!-- select -->
                                <div class="form-group">
                                    <label><i class="fas fa-user mr-2"></i><?php echo $lang->get('user'); ?>:</label>
                                    <select class="form-control" id="purge-filter-user">
                                        <option value="-1"><?php echo $lang->get('all'); ?></option>
                                    <?php
                                    $rows = DB::query('SELECT id, name, lastname FROM ' . prefixTable('users') . ' WHERE admin = 0');
foreach ($rows as $record) {
    echo '
                                        <option value="'.$record['id'].'">'.$record['name'].' '.$record['lastname'].'</option>';
}
                                    ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group hidden" id="selector-purge-action">
                                    <label><i class="fas fa-cog mr-2"></i><?php echo $lang->get('action'); ?>:</label>
                                    <select class="form-control" id="purge-filter-action">
                                        <option value="all"><?php echo $lang->get('all'); ?></option>
                                        <option value="at_shown"><?php echo $lang->get('at_shown'); ?></option>
                                        <option value="at_export"><?php echo $lang->get('at_export'); ?></option>
                                        <option value="at_restored"><?php echo $lang->get('at_restored'); ?></option>
                                        <option value="at_delete"><?php echo $lang->get('at_delete'); ?></option>
                                        <option value="at_copy"><?php echo $lang->get('at_copy'); ?></option>
                                        <option value="at_moved"><?php echo $lang->get('at_moved'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-2 group-confirm-purge hidden">
                            <input type="checkbox" class="form-check-input form-item-control" id="checkbox-purge-confirm">
                            <label class="form-check-label ml-2" for="checkbox-purge-confirm">
                                <?php echo $lang->get('please_confirm_deletion'); ?>
                            </label>
                        </div>
                        <div class="form-group mt-2 group-confirm-purge hidden">
                            <button class="btn btn-danger" id="button-perform-purge"><?php echo $lang->get('submit'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.col-md-6 -->
        </div>
        <!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content -->
