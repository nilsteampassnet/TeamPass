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
 * @file      import.php
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

// Check user access and import enabled
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('import') === false
    || isset($SETTINGS['allow_import']) === false || (int) $SETTINGS['allow_import'] !== 1) {
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
                <h1 class="m-0 text-dark"><i class="fas fa-file-import mr-2"></i><?php echo $lang->get('import_new_items'); ?></h1>
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
                    <div class="callout callout-primary mb-3">
                        <?php echo $lang->get('data_type_for_import'); ?>
                    </div>
                    <ul class="nav nav-tabs mb-3" id="import-type">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#csv" role="tab" aria-controls="csv" aria-selected="true"><?php echo $lang->get('csv'); ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#keepass" role="tab" aria-controls="keepass" aria-selected="false"><?php echo $lang->get('keepass'); ?></a>
                        </li>
                    </ul>


                    <div class="tab-content mt-1" id="myTabContent">
                        <!-- CSV -->
                        <div class="tab-pane fade show active" id="csv" role="tabpanel" aria-labelledby="csv-tab">
                            <div class="callout callout-info">
                                <i class="far fa-lightbulb text-warning fa-lg mr-2"></i>
                                <a href="<?php echo READTHEDOC_URL; ?>" target="_blank" class="text-info"><?php echo $lang->get('get_tips_about_importation'); ?></a>
                            </div>

                            <div class="row mt-3">
                                <div class="col-6" id="import-csv-upload-zone">
                                    <h5 class=""><?php echo $lang->get('select_file'); ?></h5>

                                    <div>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="import-csv-attach-pickfile-csv">
                                            <label class="custom-file-label" for="import-csv-attach-pickfile-csv" id="import-csv-attach-pickfile-csv-text"><?php echo $lang->get('select_file'); ?></label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- OPTIONS -->
                            <div class="row mt-3 hidden csv-setup">
                                <div class="col-6">
                                    <h5><?php echo $lang->get('options'); ?></h5>
                                    <div class="form-group">
                                        <input type="checkbox" class="flat-blue import-csv-cb" id="import-csv-edit-all-checkbox">
                                        <label for="import-csv-edit-all-checkbox" class="ml-2"><?php echo $lang->get('import_csv_anyone_can_modify_txt'); ?></label>
                                    </div>

                                    <div class="form-group">
                                        <input type="checkbox" class="flat-blue import-csv-cb" id="import-csv-edit-role-checkbox">
                                        <label for="import-csv-edit-role-checkbox" class="ml-2"><?php echo $lang->get('import_csv_anyone_can_modify_in_role_txt'); ?></label>
                                    </div>
                                </div>
                            </div>

                            <!-- TARGET FOLDER -->
                            <div class="row mt-3 hidden csv-setup">
                                <div class="form-group col-12">
                                    <h5><?php echo $lang->get('target_folder'); ?></h5>
                                    <label><?php echo $lang->get('where_shall_items_be_created'); ?></label>
                                    <select class="form-control select2" style="width:100%;" id="import-csv-target-folder"></select>
                                </div>
                            </div>

                            <!-- ITEMS TO IMPORT -->
                            <div class="row mt-3 hidden csv-setup">
                                <div class="col-12">
                                    <div id="accordion">
                                        <div class="card card-primary">
                                            <div class="card-header">
                                                <h4 class="card-title">
                                                    <a data-toggle="collapse" data-parent="#accordion" href="#collapseOne">
                                                        <?php echo $lang->get('selected_items_to_be_imported'); ?>:
                                                        <span class="ml-2 text-bold" id="csv-items-number"></span>
                                                    </a>
                                                </h4>
                                            </div>
                                            <div id="collapseOne" class="panel-collapse collapse in">
                                                <div class="card-body" id="csv-items-list"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- KEEPASS -->
                        <div class="tab-pane fade" id="keepass" role="tabpanel" aria-labelledby="keepass-tab">
                            <div class="callout callout-info">
                                <i class="far fa-lightbulb text-warning fa-lg mr-2"></i>
                                <a href="<?php echo READTHEDOC_URL; ?>" target="_blank" class="text-info"><?php echo $lang->get('get_tips_about_importation'); ?></a>
                            </div>

                            <div class="row mt-3">
                                <div class="col-6" id="import-keepass-upload-zone">
                                    <h5 class=""><?php echo $lang->get('select_file'); ?></h5>

                                    <div>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="import-keepass-attach-pickfile-keepass">
                                            <label class="custom-file-label" for="import-keepass-attach-pickfile-keepass" id="import-keepass-attach-pickfile-keepass-text"><?php echo $lang->get('select_file'); ?></label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- OPTIONS -->
                            <div class="row mt-3 hidden keepass-setup">
                                <div class="col-6">
                                    <h5><?php echo $lang->get('options'); ?></h5>
                                    <div class="form-group">
                                        <input type="checkbox" class="flat-blue import-csv-keepass" id="import-keepass-edit-all-checkbox">
                                        <label for="import-keepass-edit-all-checkbox" class="ml-2"><?php echo $lang->get('import_csv_anyone_can_modify_txt'); ?></label>
                                    </div>

                                    <div class="form-group">
                                        <input type="checkbox" class="flat-blue import-keepass-cb" id="import-keepass-edit-role-checkbox">
                                        <label for="import-keepass-edit-role-checkbox" class="ml-2"><?php echo $lang->get('import_csv_anyone_can_modify_in_role_txt'); ?></label>
                                    </div>
                                </div>
                            </div>

                            <!-- TARGET FOLDER -->
                            <div class="row mt-3 hidden keepass-setup">
                                <div class="form-group col-12">
                                    <h5><?php echo $lang->get('target_folder'); ?></h5>
                                    <label><?php echo $lang->get('where_shall_items_be_created'); ?></label>
                                    <select class="form-control select2" style="width:100%;" id="import-keepass-target-folder"></select>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- FEEDBACK -->
                    <div class="row alert alert-info mt-3 hidden" id="import-feedback">
                        <h5><i class="icon fas fa-info-circle mr-2"></i><?php echo $lang->get('info'); ?></h5>
                        <div class="row hidden" id="import-feedback-result"></div>
                        <div class="row" id="import-feedback-progress">
                            <span id="import-feedback-progress-text"></span>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary" id="form-item-import-perform"><?php echo $lang->get('perform'); ?></button>
                    <button type="submit" class="btn btn-default float-right" id="form-item-import-cancel"><?php echo $lang->get('cancel'); ?></button>
                </div>
            </div>
        </div>
    </div>
</section>
