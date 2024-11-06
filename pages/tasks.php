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
 * @file      tasks.php
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

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

loadClasses();
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('tasks') === false) {
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
                    <i class="fa-solid fa-tasks mr-2"></i><?php echo $lang->get('tasks_manager'); ?>
                </h1>
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
                        <ul class="nav nav-tabs" id="tasksSettingsPage">
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#settings" aria-controls="settings" aria-selected="false"><?php echo $lang->get('settings'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#in_progress" aria-controls="in_progress" aria-selected="true"><?php echo $lang->get('in_progress'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#finished" role="tab" aria-controls="done" aria-selected="false"><?php echo $lang->get('done'); ?></a>
                            </li>
                        </ul>


                        <div class="tab-content mt-1" id="myTabContent">
                            <!-- TAB SETTINGS -->
                            <div class="tab-pane fade show" id="settings" role="tabpanel" aria-labelledby="settings-tab">
                                <div class="card-body">

                                    <div class="">
                                        <?php

// Instantiate the adapter and repository
try {
    // Get last cron execution timestamp
    DB::query(
        'SELECT valeur
        FROM ' . prefixTable('misc') . '
        WHERE type = %s AND intitule = %s and valeur >= %d',
        'admin',
        'last_cron_exec',
        time() - 600 // max 10 minutes
    );

    if (DB::count() === 0) {
        ?>
                                            <div class="callout callout-info alert-dismissible mt-3">
                                                <h5><i class="fa-solid fa-info mr-2"></i><?php echo $lang->get('information'); ?></h5>
                                                <?php echo str_replace("#teampass_path#", $SETTINGS['cpassman_dir'], $lang->get('tasks_information')); ?>
                                            </div>
                                            <div class="alert alert-warning mt-2 " role="alert">
                                                <i class="fa-solid fa-cancel mr-2"></i><?php echo $lang->get('tasks_cron_not_running'); ?><br />
                                                <button class="btn btn-default action" type="button" data-type="add-new-job"><i class="fa-solid fa-play mr-2"></i><?php echo $lang->get('add_new_job'); ?></button>
                                                <div id="add-new-job-result">

                                                </div>
                                            </div>
        <?php
    } else {
        ?>
                                            <div class="alert alert-success mt-2 " role="alert">
                                                <i class="fa-solid fa-check mr-2"></i><?php echo $lang->get('tasks_cron_running'); ?>
                                            </div>
        <?php
    }
}
catch (Exception $e) {
    error_log('TEAMPASS Error - tasks page - '.$e->getMessage());
    // deepcode ignore ServerLeak: no critical information is provided
    echo "An error occurred.";
}?>
                                    </div>

                                    <h5><i class="fa-solid fa-hourglass-half mr-2"></i><?php echo $lang->get('frequency'); ?><i class="fa-solid fa-rotate fa-spin ml-2 hidden text-info" id="go_refresh"></i></h5>

                                    <div class='row ml-1 mt-3 mb-2'>
                                        <div class='col-9'>
                                            <i class="fa-solid fa-inbox mr-2"></i><?php echo $lang->get('sending_emails')." (".$lang->get('in_minutes').")"; ?>
                                            <span class="badge badge-secondary ml-2" id="sending_emails_job_frequency_badge"></span>
                                        </div>
                                        <div class='col-2'>
                                            <input type='range' class='form-control form-control-sm form-control-range range-slider' id='sending_emails_job_frequency' min='0' max="59" value='<?php echo $SETTINGS['sending_emails_job_frequency'] ?? '2'; ?>'>
                                        </div>
                                        <div class='col-1'>
                                            <input type='text' disabled class='form-control form-control-sm' id='sending_emails_job_frequency_text' value='<?php echo $SETTINGS['sending_emails_job_frequency'] ?? '2'; ?>'>
                                        </div>
                                    </div>

                                    <div class='row ml-1 mb-2'>
                                        <div class='col-9'>
                                            <i class="fa-solid fa-user-cog mr-2"></i><?php echo $lang->get('user_keys_management')." (".$lang->get('in_minutes').")"; ?>
                                            <span class="badge badge-secondary ml-2" id="user_keys_job_frequency_badge"></span>
                                        </div>
                                        <div class='col-2'>
                                            <input type='range' class='form-control form-control-sm form-control-range range-slider' id='user_keys_job_frequency' min='0' max="59" value='<?php echo $SETTINGS['user_keys_job_frequency'] ?? '1'; ?>'>
                                        </div>
                                        <div class='col-1'>
                                            <input type='text' disabled class='form-control form-control-sm' id='user_keys_job_frequency_text' value='<?php echo $SETTINGS['user_keys_job_frequency'] ?? '1'; ?>'>
                                        </div>
                                    </div>

                                    <div class='row ml-1 mb-2'>
                                        <div class='col-9'>
                                            <i class="fa-solid fa-tower-observation mr-2"></i><?php echo $lang->get('items_management')." (".$lang->get('in_minutes').")"; ?>
                                            <span class="badge badge-secondary ml-2" id="items_ops_job_frequency_badge"></span>
                                        </div>
                                        <div class='col-2'>
                                            <input type='range' class='form-control form-control-sm form-control-range range-slider' id='items_ops_job_frequency' min='0' max="59" value='<?php echo $SETTINGS['items_ops_job_frequency'] ?? '1'; ?>'>
                                        </div>
                                        <div class='col-1'>
                                            <input type='text' disabled class='form-control form-control-sm' id='items_ops_job_frequency_text' value='<?php echo $SETTINGS['items_ops_job_frequency'] ?? '1'; ?>'>
                                        </div>
                                    </div>

                                    <div class='row ml-1 mb-2'>
                                        <div class='col-9'>
                                            <i class="fa-solid fa-chart-simple mr-2"></i><?php echo $lang->get('items_and_folders_statistics')." (".$lang->get('in_minutes').")"; ?>
                                            <span class="badge badge-secondary ml-2" id="items_statistics_job_frequency_badge"></span>
                                        </div>
                                        <div class='col-2'>
                                            <input type='range' class='form-control form-control-sm form-control-range range-slider' id='items_statistics_job_frequency' min='0' max="59" value='<?php echo $SETTINGS['items_statistics_job_frequency'] ?? '5'; ?>'>
                                        </div>
                                        <div class='col-1'>
                                            <input type='text' disabled class='form-control form-control-sm' id='items_statistics_job_frequency_text' value='<?php echo $SETTINGS['items_statistics_job_frequency'] ?? '5'; ?>'>
                                        </div>
                                    </div>

                                    <div class='row ml-1 mb-2'>
                                        <div class='col-9'>
                                            <i class="fa-solid fa-screwdriver-wrench mr-2"></i><?php echo $lang->get('maintenance_operations'); ?>
                                        </div>
                                    </div>

                                    <div class='row ml-1 mb-2'>
                                        <div class='col-8'>
                                            <i class="fa-solid fa-person-shelter ml-4 mr-2"></i><span id="users_personal_folder_task_text"><?php echo $lang->get('users_personal_folder'); ?></span>
                                            <span class="badge badge-secondary ml-2" id="users_personal_folder_task_badge"></span>
                                        </div>
                                        <div class='col-2'>
                                            <?php
                                            $task = isset($SETTINGS['users_personal_folder_task']) === true ? explode(";", $SETTINGS['users_personal_folder_task']) : [];
                                            ?>
                                            <input type='text' disabled class='form-control form-control-sm' id='users_personal_folder_task_parameter' value='<?php echo isset($task[0]) === true && empty($task[0]) === false ? $lang->get($task[0])." ".(isset($task[2]) === true ? strtolower($lang->get('day')).' '.$task[2].' ' : '').$lang->get('at')." ".(isset($task[1]) === true ? $task[1] : '') : $lang->get('not_defined') ?>'>
                                            <input type='hidden' disabled class='form-control form-control-sm' id='users_personal_folder_task_parameter_value' value='<?php echo isset($task[0]) === true ? $task[0].";".(isset($task[1]) === true ? $task[1] : '').(isset($task[2]) === true ? $task[2] : '') : '';?>'>
                                        </div>
                                        <div class='col-2'>
                                            <button class="btn btn-primary task-define" data-task="users_personal_folder_task">
                                                <i class="fa-solid fa-cogs"></i>
                                            </button>
                                            <button class="btn btn-primary task-perform ml-1" data-task="users_personal_folder_task">
                                                <i class="fa-solid fa-play"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class='row ml-1 mb-2'>
                                        <div class='col-8'>
                                            <i class="fa-solid fa-binoculars ml-4 mr-2"></i><span id="clean_orphan_objects_task_text"><?php echo $lang->get('clean_orphan_objects'); ?></span>
                                            <span class="badge badge-secondary ml-2" id="clean_orphan_objects_task_badge"></span>
                                        </div>
                                        <div class='col-2'>
                                            <?php
                                            $task = isset($SETTINGS['clean_orphan_objects_task']) === true ? explode(";", $SETTINGS['clean_orphan_objects_task']) : [];
                                            ?>
                                            <input type='text' disabled class='form-control form-control-sm' id='clean_orphan_objects_task_parameter' value='<?php echo isset($task[0]) === true && empty($task[0]) === false ? $lang->get($task[0])." ".(isset($task[2]) === true ? strtolower($lang->get('day')).' '.$task[2].' ' : '').$lang->get('at')." ".(isset($task[1]) === true ? $task[1] : '') : $lang->get('not_defined') ?>'>
                                            <input type='hidden' disabled class='form-control form-control-sm' id='clean_orphan_objects_task_parameter_value' value='<?php echo isset($task[0]) === true ? $task[0].";".(isset($task[1]) === true ? $task[1] : '').(isset($task[2]) === true ? $task[2] : '') : '';?>'>
                                        </div>
                                        <div class='col-2'>
                                            <button class="btn btn-primary task-define" data-task="clean_orphan_objects_task">
                                                <i class="fa-solid fa-cogs"></i>
                                            </button>
                                            <button class="btn btn-primary task-perform ml-1" data-task="clean_orphan_objects_task">
                                                <i class="fa-solid fa-play"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class='row ml-1 mb-2'>
                                        <div class='col-8'>
                                            <i class="fa-solid fa-broom ml-4 mr-2"></i><span id="purge_temporary_files_task_text"><?php echo $lang->get('purge_temporary_files'); ?></span>
                                            <span class="badge badge-secondary ml-2" id="purge_temporary_files_task_badge"></span>
                                        </div>
                                        <div class='col-2'>
                                            <?php
                                            $task = isset($SETTINGS['purge_temporary_files_task']) === true ? explode(";", $SETTINGS['purge_temporary_files_task']) : [];
                                            ?>
                                            <input type='text' disabled class='form-control form-control-sm' id='purge_temporary_files_task_parameter' value='<?php echo isset($task[0]) === true && empty($task[0]) === false ? $lang->get($task[0])." ".(isset($task[2]) === true ? strtolower($lang->get('day')).' '.$task[2].' ' : '').$lang->get('at')." ".(isset($task[1]) === true ? $task[1] : '') : $lang->get('not_defined') ?>'>
                                            <input type='hidden' disabled class='form-control form-control-sm' id='purge_temporary_files_task_parameter_value' value='<?php echo isset($task[0]) === true ? $task[0].";".(isset($task[1]) === true ? $task[1] : '').(isset($task[2]) === true ? $task[2] : '') : '';?>'>
                                        </div>
                                        <div class='col-2'>
                                            <button class="btn btn-primary task-define" data-task="purge_temporary_files_task">
                                                <i class="fa-solid fa-cogs"></i>
                                            </button>
                                            <button class="btn btn-primary task-perform ml-1" data-task="purge_temporary_files_task">
                                                <i class="fa-solid fa-play"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class='row ml-1 mb-4'>
                                        <div class='col-8'>
                                            <i class="fa-solid fa-database ml-4 mr-2"></i><span id="reload_cache_table_task_text"><?php echo $lang->get('admin_action_reload_cache_table'); ?></span>
                                            <span class="badge badge-secondary ml-2" id="reload_cache_table_task_badge"></span>
                                        </div>
                                        <div class='col-2'>
                                            <?php
                                            $task = isset($SETTINGS['reload_cache_table_task']) === true ? explode(";", $SETTINGS['reload_cache_table_task']) : [];
                                            ?>
                                            <input type='text' disabled class='form-control form-control-sm' id='reload_cache_table_task_parameter' value='<?php echo isset($task[0]) === true && empty($task[0]) === false ? $lang->get($task[0])." ".(isset($task[2]) === true ? strtolower($lang->get('day')).' '.$task[2].' ' : '').$lang->get('at')." ".(isset($task[1]) === true ? $task[1] : '') : $lang->get('not_defined') ?>'>
                                            <input type='hidden' disabled class='form-control form-control-sm' id='reload_cache_table_task_parameter_value' value='<?php echo isset($task[0]) === true ? $task[0].";".(isset($task[1]) === true ? $task[1] : '').(isset($task[2]) === true ? $task[2] : '') : '';?>'>
                                        </div>
                                        <div class='col-2'>
                                            <button class="btn btn-primary task-define" data-task="reload_cache_table_task">
                                                <i class="fa-solid fa-cogs"></i>
                                            </button>
                                            <button class="btn btn-primary task-perform ml-1" data-task="reload_cache_table_task">
                                                <i class="fa-solid fa-play"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class='row mb-3 option'>
                                        <div class='col-10'>
                                        <h5><i class="fa-solid fa-clock mr-2"></i><?php echo $lang->get('enable_refresh_task_last_execution'); ?></h5>
                                        </div>
                                        <div class='col-2'>
                                            <div class='toggle toggle-modern' id='enable_refresh_task_last_execution' data-toggle-on='<?php echo isset($SETTINGS['enable_refresh_task_last_execution']) === true && (int) $SETTINGS['enable_refresh_task_last_execution'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_refresh_task_last_execution_input' value='<?php echo isset($SETTINGS['enable_refresh_task_last_execution']) && (int) $SETTINGS['enable_refresh_task_last_execution'] === 1 ? 1 : 0; ?>' />
                                        </div>
                                    </div>

                                    <div class='row mb-3 option'>
                                        <div class='col-10'>
                                        <h5><i class="fa-solid fa-rss mr-2"></i><?php echo $lang->get('enable_tasks_log'); ?></h5>
                                            <small class='form-text text-muted'>
                                                <?php echo $lang->get('enable_tasks_log_tip'); ?>
                                            </small>
                                        </div>
                                        <div class='col-2'>
                                            <div class='toggle toggle-modern' id='enable_tasks_log' data-toggle-on='<?php echo isset($SETTINGS['enable_tasks_log']) === true && (int) $SETTINGS['enable_tasks_log'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_tasks_log_input' value='<?php echo isset($SETTINGS['enable_tasks_log']) && (int) $SETTINGS['enable_tasks_log'] === 1 ? 1 : 0; ?>' />
                                        </div>
                                    </div>

                                    <div class='row mb-3 option'>
                                        <div class='col-10'>
                                            <h5><i class="fa-solid fa-clock-rotate-left mr-2"></i><?php echo $lang->get('tasks_log_retention_delay_in_days'); ?></h5>
                                            <small class='form-text text-muted'>
                                                <i class="fa-solid fa-database mr-2"></i><?php echo $lang->get('tasks_log_table_size'); ?>:<span id="tasks_log_table_size" class="ml-2"></span> MB
                                            </small>
                                        </div>
                                        <div class='col-2'>
                                            <input type='number' class='form-control form-control-sm' id='tasks_log_retention_delay' value='<?php echo isset($SETTINGS['tasks_log_retention_delay']) === true ? $SETTINGS['tasks_log_retention_delay'] : 3650; ?>'>
                                        </div>
                                    </div>

                                    <div class='row mb-3 option'>
                                        <div class='col-10'>
                                        <h5><i class="fa-solid fa-hourglass-start mr-2"></i><?php echo $lang->get('maximum_time_script_allowed_to_run'); ?></h5>
                                            <small id='passwordHelpBlock' class='form-text text-muted'>
                                                <?php echo $lang->get('maximum_time_script_allowed_to_run_tip'); ?>
                                            </small>
                                        </div>
                                        <div class='col-2'>
                                            <input type='number' class='form-control form-control-sm' id='task_maximum_run_time' value='<?php echo isset($SETTINGS['task_maximum_run_time']) === true ? $SETTINGS['task_maximum_run_time'] : 600; ?>'>
                                        </div>
                                    </div>

                                    <div class='row mb-3 option'>
                                        <div class='col-10'>
                                        <h5><i class="fa-solid fa-object-group mr-2"></i><?php echo $lang->get('maximum_number_of_items_to_treat'); ?></h5>
                                            <small id='passwordHelpBlock' class='form-text text-muted'>
                                                <?php echo $lang->get('maximum_number_of_items_to_treat_tip'); ?>
                                            </small>
                                        </div>
                                        <div class='col-2'>
                                            <input type='number' class='form-control form-control-sm' id='maximum_number_of_items_to_treat' value='<?php echo isset($SETTINGS['maximum_number_of_items_to_treat']) === true ? $SETTINGS['maximum_number_of_items_to_treat'] : NUMBER_ITEMS_IN_BATCH; ?>'>
                                        </div>
                                    </div>

                                    <div class='row mb-3 option'>
                                        <div class='col-10'>
                                        <h5><i class="fa-solid fa-database mr-2"></i><?php echo $lang->get('number_users_build_cache_tree'); ?></h5>
                                            <small id='passwordHelpBlock' class='form-text text-muted'>
                                                <?php echo $lang->get('number_users_build_cache_tree_tip'); ?>
                                            </small>
                                        </div>
                                        <div class='col-2'>
                                            <input type='number' class='form-control form-control-sm' id='number_users_build_cache_tree' value='<?php echo isset($SETTINGS['number_users_build_cache_tree']) === true ? $SETTINGS['number_users_build_cache_tree'] : 10; ?>'>
                                        </div>
                                    </div>

                                    <div class='row mb-3 option'>
                                        <div class='col-10'>
                                        <h5><i class="fa-solid fa-stopwatch-20 mr-2"></i><?php echo $lang->get('refresh_data_every_on_screen'); ?></h5>
                                            <small id='passwordHelpBlock' class='form-text text-muted'>
                                                <?php echo $lang->get('refresh_data_every_on_screen_tip'); ?>
                                            </small>
                                        </div>
                                        <div class='col-2'>
                                            <input type='number' class='form-control form-control-sm' id='tasks_manager_refreshing_period' value='<?php echo isset($SETTINGS['tasks_manager_refreshing_period']) === true ? $SETTINGS['tasks_manager_refreshing_period'] : 20; ?>'>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <div class="tab-pane fade show active" id="in_progress" role="tabpanel" aria-labelledby="in_progress-tab">
                                <table class="table table-striped" id="table-tasks_in_progress" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th><?php echo $lang->get('created_at'); ?></th>
                                            <th><?php echo $lang->get('progress'); ?></th>
                                            <th><?php echo $lang->get('type'); ?></th>
                                            <th><?php echo $lang->get('user'); ?></th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>

                            <div class="tab-pane fade" id="finished" role="tabpanel" aria-labelledby="finished-tab">
                                <table class="table table-striped" id="table-tasks_finished" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th><?php echo $lang->get('created_at'); ?></th>
                                            <th><?php echo $lang->get('started_at'); ?></th>
                                            <th><?php echo $lang->get('finished_at'); ?></th>
                                            <th><?php echo $lang->get('execution_time'); ?></th>
                                            <th><?php echo $lang->get('type'); ?></th>
                                            <th><?php echo $lang->get('user'); ?></th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.col-md-6 -->
        </div>
        <!-- /.row -->
    </div><!-- /.container-fluid -->

    <!-- USER LOGS -->
    <div class="row hidden extra-form" id="tasks-detail">
        <div class="col-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><?php echo $lang->get('logs_for_user'); ?> <span id="row-logs-title"></span></h3>
                </div>

                <!-- /.card-header -->
                <!-- table start -->
                <div class="card-body form" id="user-logs">
                    <table id="table-logs" class="table table-striped table-responsive" style="width:100%">
                        <thead>
                            <tr>
                                <th><?php echo $lang->get('date'); ?></th>
                                <th><?php echo $lang->get('activity'); ?></th>
                                <th><?php echo $lang->get('label'); ?></th>
                            </tr>
                        </thead>
                        <tbody>

                        </tbody>
                    </table>
                </div>

                <div class="card-footer">
                    <button type="button" class="btn btn-default float-right tp-action" data-action="cancel"><?php echo $lang->get('cancel'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- DELETE CONFIRM -->
    <div class="modal fade" tabindex="-1" role="dialog" aria-hidden="true" id="task-delete-user-confirm">
        <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
            <h4 class="modal-title"><?php echo $lang->get('please_confirm_deletion'); ?></h4>
            </div>
            <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="modal-btn-delete" data-id=""><?php echo $lang->get('yes'); ?></button>
            <button type="button" class="btn btn-default" id="modal-btn-cancel"><?php echo $lang->get('cancel'); ?></button>
            </div>
        </div>
        </div>
    </div>

    <!-- ADAPT TASK -->
    <div class="modal fade" tabindex="-1" role="dialog" aria-hidden="true" id="task-define-modal">
        <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><i class="fa-solid fa-calendar-check mr-2"></i><?php echo $lang->get('task_scheduling'); ?></h4>
            </div>
            
            <div class="modal-body" id="taskSchedulingModalBody">
                <div>
                    <h5><i class="fa-solid fa-clock-rotate-left mr-2"></i><?php echo $lang->get('frequency'); ?></h5>              
                    <select class='form-control form-control-sm no-save' id='task-define-modal-frequency' style="width:100%;">
                        <?php
                        echo '<optgroup label="'.$lang->get('run_once').'">
                        <option value="hourly">'.$lang->get('hourly').'</option>
                        <option value="daily">'.$lang->get('daily').'</option>
                        <option value="monthly">'.$lang->get('monthly').'</option>
                        </optgroup>
                        <optgroup label="'.$lang->get('run_weekday').'">
                        <option value="monday">'.$lang->get('monday').'</option>
                        <option value="tuesday">'.$lang->get('tuesday').'</option>
                        <option value="wednesday">'.$lang->get('wednesday').'</option>
                        <option value="thursday">'.$lang->get('thursday').'</option>
                        <option value="friday">'.$lang->get('friday').'</option>
                        <option value="saturday">'.$lang->get('saturday').'</option>
                        <option value="sunday">'.$lang->get('sunday').'</option>
                        </optgroup>';
                        ?>
                    </select>
                </div>

                <div id="task-define-modal-parameter-monthly" class="hidden ml-2">
                    <div class="row mt-2">
                        <h5><?php echo $lang->get('day_of_month'); ?></h5>              
                        <select class='form-control form-control-sm no-save' id='task-define-modal-parameter-monthly-value' style="width:100%;">
                            <?php
                            for ($i=1; $i<=31; $i++) {
                                echo '<option value="'.$i.'">'.$lang->get('day').' '.$i.'</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div id="task-define-modal-parameter-daily" class="hidden ml-2">
                    <div class="row mt-2">
                        <h5 class=""><?php echo $lang->get('at'); ?></h5>
                        <input type="time" class="form-control form-control-sm no-save" id="task-define-modal-parameter-daily-value" min="00:00" max="23:59" value="00:00"/>
                    </div>
                </div>

                <div id="task-define-modal-parameter-hourly" class="ml-2">
                    <div class="row mt-2">
                        <h5><?php echo $lang->get('at'); ?></h5>              
                        <input type="time" class="form-control form-control-sm no-save" id="task-define-modal-parameter-hourly-value" min="00:00" max="00:59" value="00:00"/>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-warning" id="modal-btn-task-reset" data-id=""><?php echo $lang->get('reset'); ?></button>
                <button type="button" class="btn btn-primary" id="modal-btn-task-save" data-id=""><?php echo $lang->get('save'); ?></button>
                <button type="button" class="btn btn-default" id="modal-btn-task-cancel"><?php echo $lang->get('cancel'); ?></button>
                <input type="hidden" id="task-define-modal-type"/>
            </div>
        </div>
        </div>
    </div>


</div>
<!-- /.content -->
