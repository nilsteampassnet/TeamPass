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
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
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
            'type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
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

// Check user access and import enabled (admin can always access)
echo $checkUserAccess->caseHandler();
if ((int) $session->get('user-admin') === 0
    && ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('import') === false
    || isset($SETTINGS['allow_import']) === false || (int) $SETTINGS['allow_import'] !== 1)
) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //
 
if ((int) $session->get('user-admin') === 1) {
    $folderOptions = '';
    $rows = DB::query('SELECT id, title FROM ' . prefixTable('nested_tree') . ' WHERE personal_folder = %i', 0);
    foreach ($rows as $record) {
        $folderOptions .= '<option value="' . strval($record['id']) . '">' . htmlspecialchars(strval($record['title']), ENT_QUOTES, 'UTF-8') . '</option>';
    }
}

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

                    <!-- Single source selector (replaces the former CSV/Keepass/Managers tabs) -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="import-source"><?php echo $lang->get('import_select_source'); ?></label>
                            <select id="import-source" class="form-control select2" style="width:100%;">
                                <option value="csv"><?php echo $lang->get('csv'); ?></option>
                                <option value="keepass"><?php echo $lang->get('keepass'); ?></option>
                                <option value="bitwarden"><?php echo $lang->get('import_format_bitwarden'); ?></option>
                                <option value="lastpass"><?php echo $lang->get('import_format_lastpass'); ?></option>
                                <option value="1password"><?php echo $lang->get('import_format_1password'); ?></option>
                                <option value="keepassxc"><?php echo $lang->get('import_format_keepassxc'); ?></option>
                            </select>
                        </div>
                    </div>


                    <div class="mt-1" id="import-zones">
                        <!-- CSV -->
                        <div class="import-zone" id="csv">
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

                            <!-- ITEMS TO IMPORT -->
                            <div class="row mt-4 hidden csv-setup">
                                <div class="col-12">
                                    <div id="accordion">
                                        <div class="card card-primary">
                                            <div class="card-header">
                                                <h4 class="card-title" id="csv-file-info">
                                                </h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- OPTIONS -->
                            <div class="row mt-2 hidden csv-setup">
                                <div class="col-12">
                                    <h5><?php echo $lang->get('options'); ?></h5>                                    

                                    <div class="form-group">                                        
                                        <label for="import-csv-keys-strategy"><?php echo $lang->get('import_csv_keys_generation_strategy'); ?></label>
                                        <span class="ml-2 text-muted"><?php echo $lang->get('import_csv_keys_generation_strategy_tip'); ?></span>
                                        <select id="import-csv-keys-strategy" class="form-control form-item-control select2" style="width:100%;">
                                            <option value="import"><?php echo $lang->get('during_import'); ?></option>
                                            <option value="tasksHandler"><?php echo $lang->get('with_tasks_handler'); ?></option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <input type="checkbox" class="flat-blue import-csv-cb" id="import-csv-edit-all-checkbox">
                                        <label for="import-csv-edit-all-checkbox" class="ml-2"><?php echo $lang->get('import_csv_anyone_can_modify_txt'); ?></label>
                                    </div>

                                    <div class="form-group">
                                        <input type="checkbox" class="flat-blue import-csv-cb" id="import-csv-edit-role-checkbox">
                                        <label for="import-csv-edit-role-checkbox" class="ml-2"><?php echo $lang->get('import_csv_anyone_can_modify_in_role_txt'); ?></label>
                                    </div>

                                    <div class="form-group csv-folder">
                                        <label for="import-csv-complexity"><?php echo $lang->get('password_minimal_complexity_target_for_folders'); ?></label>
                                        <select id="import-csv-complexity" class="form-control form-item-control select2" style="width:100%;">
                                        <?php
$complexitySelect = '';
foreach (TP_PW_COMPLEXITY as $level) {
    $complexitySelect .= '<option value="' . $level[0] . '">' . $level[1] . '</option>';
}
echo $complexitySelect;
                                        ?>
                                        </select>
                                    </div>

                                    <div class="form-group csv-folder">
                                        <label for="import-csv-access-right"><?php echo $lang->get('access_right_for_roles_for_folders'); ?></label>
                                        <select id="import-csv-access-right" class="form-control form-item-control select2" style="width:100%;">
                                            <option value=""><?php echo $lang->get('no_access'); ?></option>
                                            <option value="R"><?php echo $lang->get('read'); ?></option>
                                            <option value="W"><?php echo $lang->get('write'); ?></option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="import-csv-target-folder"><?php echo $lang->get('target_folder'); ?></label>
                                        <select id="import-csv-target-folder" class="form-control form-item-control select2" style="width:100%;">
<?php
echo isset($folderOptions) ? $folderOptions : '';
?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- PROGRESS BAR -->
                            <div class="row mt-2 hidden" id="csv-setup-progress">
                                <div class="form-group col-12">
                                    <h5>
                                        <?php echo $lang->get('progress'); ?>
                                    </h5>
                                    <div class="progress">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="import-csv-progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%"></div>
                                    </div>
                                    <div class="mt-2">
                                        <div class="text-muted text-center" id="import-csv-progress-text"></div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- KEEPASS -->
                        <div class="import-zone hidden" id="keepass">
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
                                    <select class="form-control select2" style="width:100%;" id="import-keepass-target-folder">
<?php
echo isset($folderOptions) ? $folderOptions : '';
?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- OTHER PASSWORD MANAGERS (Bitwarden / LastPass / 1Password / KeePassXC) -->
                        <div class="import-zone hidden" id="managers">
                            <div class="callout callout-info">
                                <i class="far fa-lightbulb text-warning fa-lg mr-2"></i>
                                <a href="<?php echo READTHEDOC_URL; ?>" target="_blank" class="text-info"><?php echo $lang->get('get_tips_about_importation'); ?></a>
                            </div>

                            <!-- Selected format is driven by the source selector above -->
                            <input type="hidden" id="import-mgr-format" value="bitwarden">

                            <!-- Export instructions for the selected manager -->
                            <div class="row mt-3">
                                <div class="col-md-8">
                                    <div class="callout callout-info mb-0 p-2" id="import-mgr-format-hint"></div>
                                </div>
                            </div>

                            <!-- FILE -->
                            <div class="row mt-3">
                                <div class="col-6" id="import-mgr-upload-zone">
                                    <h5><?php echo $lang->get('select_file'); ?></h5>
                                    <div>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="import-mgr-attach-pickfile">
                                            <label class="custom-file-label" for="import-mgr-attach-pickfile" id="import-mgr-attach-pickfile-text"><?php echo $lang->get('select_file'); ?></label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ITEMS TO IMPORT -->
                            <div class="row mt-4 hidden mgr-setup">
                                <div class="col-12">
                                    <div class="card card-primary">
                                        <div class="card-header">
                                            <h4 class="card-title" id="mgr-file-info"></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- OPTIONS -->
                            <div class="row mt-2 hidden mgr-setup">
                                <div class="col-12">
                                    <h5><?php echo $lang->get('options'); ?></h5>

                                    <div class="form-group">
                                        <label for="import-mgr-keys-strategy"><?php echo $lang->get('import_csv_keys_generation_strategy'); ?></label>
                                        <span class="ml-2 text-muted"><?php echo $lang->get('import_csv_keys_generation_strategy_tip'); ?></span>
                                        <select id="import-mgr-keys-strategy" class="form-control form-item-control select2" style="width:100%;">
                                            <option value="import"><?php echo $lang->get('during_import'); ?></option>
                                            <option value="tasksHandler"><?php echo $lang->get('with_tasks_handler'); ?></option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <input type="checkbox" class="flat-blue import-mgr-cb" id="import-mgr-edit-all-checkbox">
                                        <label for="import-mgr-edit-all-checkbox" class="ml-2"><?php echo $lang->get('import_csv_anyone_can_modify_txt'); ?></label>
                                    </div>

                                    <div class="form-group">
                                        <input type="checkbox" class="flat-blue import-mgr-cb" id="import-mgr-edit-role-checkbox">
                                        <label for="import-mgr-edit-role-checkbox" class="ml-2"><?php echo $lang->get('import_csv_anyone_can_modify_in_role_txt'); ?></label>
                                    </div>

                                    <div class="form-group mgr-folder">
                                        <label for="import-mgr-complexity"><?php echo $lang->get('password_minimal_complexity_target_for_folders'); ?></label>
                                        <select id="import-mgr-complexity" class="form-control form-item-control select2" style="width:100%;">
                                        <?php
$complexitySelectMgr = '';
foreach (TP_PW_COMPLEXITY as $level) {
    $complexitySelectMgr .= '<option value="' . $level[0] . '">' . $level[1] . '</option>';
}
echo $complexitySelectMgr;
                                        ?>
                                        </select>
                                    </div>

                                    <div class="form-group mgr-folder">
                                        <label for="import-mgr-access-right"><?php echo $lang->get('access_right_for_roles_for_folders'); ?></label>
                                        <select id="import-mgr-access-right" class="form-control form-item-control select2" style="width:100%;">
                                            <option value=""><?php echo $lang->get('no_access'); ?></option>
                                            <option value="R"><?php echo $lang->get('read'); ?></option>
                                            <option value="W"><?php echo $lang->get('write'); ?></option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="import-mgr-target-folder"><?php echo $lang->get('target_folder'); ?></label>
                                        <select id="import-mgr-target-folder" class="form-control form-item-control select2" style="width:100%;">
<?php
echo isset($folderOptions) ? $folderOptions : '';
?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- PROGRESS BAR -->
                            <div class="row mt-2 hidden" id="mgr-setup-progress">
                                <div class="form-group col-12">
                                    <h5><?php echo $lang->get('progress'); ?></h5>
                                    <div class="progress">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="import-mgr-progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%"></div>
                                    </div>
                                    <div class="mt-2">
                                        <div class="text-muted text-center" id="import-mgr-progress-text"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- IMPORT FOLLOW-UP (recent imports history) -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card card-outline card-secondary">
                                <div class="card-header py-2">
                                    <h5 class="card-title mb-0"><i class="fas fa-clock-rotate-left mr-2"></i><?php echo $lang->get('import_followup_title'); ?></h5>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" id="import-followup-refresh" title="<?php echo $lang->get('refresh'); ?>"><i class="fas fa-sync-alt"></i></button>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th><?php echo $lang->get('import_followup_format'); ?></th>
                                                <th><?php echo $lang->get('import_followup_status'); ?></th>
                                                <th class="text-center"><?php echo $lang->get('import_followup_items'); ?></th>
                                                <th class="text-center"><?php echo $lang->get('import_followup_failed'); ?></th>
                                                <th class="text-center"><?php echo $lang->get('import_followup_folders'); ?></th>
                                                <th><?php echo $lang->get('import_followup_date'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="import-followup-body">
                                            <tr><td colspan="6" class="text-center text-muted py-3" id="import-followup-empty"><?php echo $lang->get('import_followup_empty'); ?></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FEEDBACK -->
                    <div class="row alert alert-info mt-3 hidden" id="import-feedback">
                        <h5><i class="icon fas fa-info-circle mr-2"></i><?php echo $lang->get('info'); ?></h5>
                        <div class="row hidden" id="import-feedback-result"></div>
                        <div class="form-group col-12" id="import-feedback-progress">
                            <div id="import-feedback-progress-text"></div>
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
