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
 * @file      tasks.php
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
use TiBeN\CrontabManager\CrontabJob;
use TiBeN\CrontabManager\CrontabAdapter;
use TiBeN\CrontabManager\CrontabRepository;

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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'tasks', $SETTINGS) === false) {
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
                    <i class="fa-solid fa-tasks mr-2"></i><?php echo langHdl('tasks_manager'); ?>
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
                                <a class="nav-link" data-toggle="tab" href="#settings" aria-controls="settings" aria-selected="false"><?php echo langHdl('settings'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#in_progress" aria-controls="in_progress" aria-selected="true"><?php echo langHdl('in_progress'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#finished" role="tab" aria-controls="done" aria-selected="false"><?php echo langHdl('done'); ?></a>
                            </li>
                        </ul>


                        <div class="tab-content mt-1" id="myTabContent">
                            <!-- TAB SETTINGS -->
                            <div class="tab-pane fade show" id="settings" role="tabpanel" aria-labelledby="settings-tab">
                                <div class="card-body">

                                    <div class="">
                                        <?php
require_once __DIR__.'/../includes/libraries/TiBeN/CrontabManager/CrontabAdapter.php';
require_once __DIR__.'/../includes/libraries/TiBeN/CrontabManager/CrontabJob.php';
require_once __DIR__.'/../includes/libraries/TiBeN/CrontabManager/CrontabRepository.php';

// Instantiate the adapter and repository
try {
    $crontabRepository = new CrontabRepository(new CrontabAdapter());
    $results = $crontabRepository->findJobByRegex('/Teampass\ scheduler/');
    if (count($results) === 0) {
        ?>
                                            <div class="callout callout-info alert-dismissible mt-3">
                                                <h5><i class="fa-solid fa-info mr-2"></i><?php echo langHdl('information'); ?></h5>
                                                <?php echo str_replace("#teampass_path#", $SETTINGS['cpassman_dir'], langHdl('tasks_information')); ?>
                                            </div>
                                            <div class="alert alert-warning mt-2 " role="alert">
                                                <i class="fa-solid fa-cancel mr-2"></i><?php echo langHdl('tasks_cron_not_running'); ?><br />
                                                <button class="btn btn-default action" type="button" data-type="add-new-job"><i class="fa-solid fa-play mr-2"></i><?php echo langHdl('add_new_job'); ?></button>
                                                <div id="add-new-job-result">

                                                </div>
                                            </div>
        <?php
    } else {
        ?>
                                            <div class="alert alert-success mt-2 " role="alert">
                                                <i class="fa-solid fa-check mr-2"></i><?php echo langHdl('tasks_cron_running'); ?>
                                            </div>
        <?php
    }
}
catch (Exception $e) {
    echo $e->getMessage();
}?>
                                    </div>

                                    <h5><i class="fa-solid fa-hourglass-half mr-2"></i><?php echo langHdl('frequency'); ?><i class="fa-solid fa-rotate fa-spin ml-2 hidden text-info" id="go_refresh"></i></h5>

                                    <div class='row ml-1 mt-3 mb-2'>
                                        <div class='col-9'>
                                            <i class="fa-solid fa-inbox mr-2"></i><?php echo langHdl('sending_emails')." (".langHdl('in_minutes').")"; ?>
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
                                            <i class="fa-solid fa-user-cog mr-2"></i><?php echo langHdl('user_keys_management')." (".langHdl('in_minutes').")"; ?>
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
                                            <i class="fa-solid fa-tower-observation mr-2"></i><?php echo langHdl('items_management')." (".langHdl('in_minutes').")"; ?>
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
                                            <i class="fa-solid fa-chart-simple mr-2"></i><?php echo langHdl('items_and_folders_statistics')." (".langHdl('in_minutes').")"; ?>
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
                                            <i class="fa-solid fa-screwdriver-wrench mr-2"></i><?php echo langHdl('maintenance_operations'); ?>
                                        </div>
                                    </div>

                                    <div class='row ml-1 mb-2'>
                                        <div class='col-8'>
                                            <i class="fa-solid fa-person-shelter ml-4 mr-2"></i><span id="users_personal_folder_task_text"><?php echo langHdl('users_personal_folder'); ?></span>
                                            <span class="badge badge-secondary ml-2" id="users_personal_folder_task_badge"></span>
                                        </div>
                                        <div class='col-2'>
                                            <?php
                                            $task = isset($SETTINGS['users_personal_folder_task']) === true ? explode(";", $SETTINGS['users_personal_folder_task']) : [];
                                            ?>
                                            <input type='text' disabled class='form-control form-control-sm' id='users_personal_folder_task_parameter' value='<?php echo isset($task[0]) === true && empty($task[0]) === false ? langHdl($task[0])." ".(isset($task[2]) === true ? strtolower(langHdl('day')).' '.$task[2].' ' : '').langHdl('at')." ".$task[1] : langHdl('not_defined') ?>'>
                                            <input type='hidden' disabled class='form-control form-control-sm' id='users_personal_folder_task_parameter_value' value='<?php echo isset($task[0]) === true ? $task[0].";".$task[1].(isset($task[2]) === true ? ';'.$task[2] : '') : '';?>'>
                                        </div>
                                        <div class='col-2'>
                                            <button class="btn btn-primary task-define" data-task="users_personal_folder_task">
                                                <i class="fa-solid fa-cogs"></i>
                                            </button>
                                            <?php
                                            if (defined('WIP') === true && WIP === true) {
                                                ?>
                                            <button class="btn btn-primary task-perform ml-1" data-task="users_personal_folder_task">
                                                <i class="fa-solid fa-play"></i>
                                            </button>
                                            <?php
                                            }
                                            ?>
                                        </div>
                                    </div>

                                    <div class='row ml-1 mb-2'>
                                        <div class='col-8'>
                                            <i class="fa-solid fa-binoculars ml-4 mr-2"></i><span id="clean_orphan_objects_task_text"><?php echo langHdl('clean_orphan_objects'); ?></span>
                                            <span class="badge badge-secondary ml-2" id="clean_orphan_objects_task_badge"></span>
                                        </div>
                                        <div class='col-2'>
                                            <?php
                                            $task = isset($SETTINGS['clean_orphan_objects_task']) === true ? explode(";", $SETTINGS['clean_orphan_objects_task']) : [];
                                            ?>
                                            <input type='text' disabled class='form-control form-control-sm' id='clean_orphan_objects_task_parameter' value='<?php echo isset($task[0]) === true && empty($task[0]) === false ? langHdl($task[0])." ".(isset($task[2]) === true ? strtolower(langHdl('day')).' '.$task[2].' ' : '').langHdl('at')." ".$task[1] : langHdl('not_defined') ?>'>
                                            <input type='hidden' disabled class='form-control form-control-sm' id='clean_orphan_objects_task_parameter_value' value='<?php echo isset($task[0]) === true ? $task[0].";".$task[1].(isset($task[2]) === true ? ';'.$task[2] : '') : '';?>'>
                                        </div>
                                        <div class='col-2'>
                                            <button class="btn btn-primary task-define" data-task="clean_orphan_objects_task">
                                                <i class="fa-solid fa-cogs"></i>
                                            </button>
                                            <?php
                                            if (defined('WIP') === true && WIP === true) {
                                                ?>
                                            <button class="btn btn-primary task-perform ml-1" data-task="clean_orphan_objects_task">
                                                <i class="fa-solid fa-play"></i>
                                            </button>
                                            <?php
                                            }
                                            ?>
                                        </div>
                                    </div>

                                    <div class='row ml-1 mb-2'>
                                        <div class='col-8'>
                                            <i class="fa-solid fa-broom ml-4 mr-2"></i><span id="purge_temporary_files_task_text"><?php echo langHdl('purge_temporary_files'); ?></span>
                                            <span class="badge badge-secondary ml-2" id="purge_temporary_files_task_badge"></span>
                                        </div>
                                        <div class='col-2'>
                                            <?php
                                            $task = isset($SETTINGS['purge_temporary_files_task']) === true ? explode(";", $SETTINGS['purge_temporary_files_task']) : [];
                                            ?>
                                            <input type='text' disabled class='form-control form-control-sm' id='purge_temporary_files_task_parameter' value='<?php echo isset($task[0]) === true && empty($task[0]) === false ? langHdl($task[0])." ".(isset($task[2]) === true ? strtolower(langHdl('day')).' '.$task[2].' ' : '').langHdl('at')." ".$task[1] : langHdl('not_defined') ?>'>
                                            <input type='hidden' disabled class='form-control form-control-sm' id='purge_temporary_files_task_parameter_value' value='<?php echo isset($task[0]) === true ? $task[0].";".$task[1].(isset($task[2]) === true ? ';'.$task[2] : '') : '';?>'>
                                        </div>
                                        <div class='col-2'>
                                            <button class="btn btn-primary task-define" data-task="purge_temporary_files_task">
                                                <i class="fa-solid fa-cogs"></i>
                                            </button>
                                            <?php
                                            if (defined('WIP') === true && WIP === true) {
                                                ?>
                                            <button class="btn btn-primary task-perform ml-1" data-task="purge_temporary_files_task">
                                                <i class="fa-solid fa-play"></i>
                                            </button>
                                            <?php
                                            }
                                            ?>
                                        </div>
                                    </div>

                                    <div class='row ml-1 mb-2'>
                                        <div class='col-8'>
                                            <i class="fa-solid fa-sliders ml-4 mr-2"></i><span id="rebuild_config_file_task_text"><?php echo langHdl('rebuild_config_file'); ?></span>
                                            <span class="badge badge-secondary ml-2" id="rebuild_config_file_task_badge"></span>
                                        </div>
                                        <div class='col-2'>
                                            <?php
                                            $task = isset($SETTINGS['rebuild_config_file_task']) === true ? explode(";", $SETTINGS['rebuild_config_file_task']) : [];
                                            ?>
                                            <input type='text' disabled class='form-control form-control-sm' id='rebuild_config_file_task_parameter' value='<?php echo isset($task[0]) === true && empty($task[0]) === false ? langHdl($task[0])." ".(isset($task[2]) === true ? strtolower(langHdl('day')).' '.$task[2].' ' : '').langHdl('at')." ".$task[1] : langHdl('not_defined') ?>'>
                                            <input type='hidden' disabled class='form-control form-control-sm' id='rebuild_config_file_task_parameter_value' value='<?php echo isset($task[0]) === true ? $task[0].";".$task[1].(isset($task[2]) === true ? ';'.$task[2] : '') : ''; ?>'>
                                        </div>
                                        <div class='col-2'>
                                            <button class="btn btn-primary task-define" data-task="rebuild_config_file_task">
                                                <i class="fa-solid fa-cogs"></i>
                                            </button>
                                            <?php
                                            if (defined('WIP') === true && WIP === true) {
                                                ?>
                                            <button class="btn btn-primary task-perform ml-1" data-task="rebuild_config_file_task">
                                                <i class="fa-solid fa-play"></i>
                                            </button>
                                            <?php
                                            }
                                            ?>
                                        </div>
                                    </div>

                                    <div class='row ml-1 mb-4'>
                                        <div class='col-8'>
                                            <i class="fa-solid fa-database ml-4 mr-2"></i><span id="reload_cache_table_task_text"><?php echo langHdl('admin_action_reload_cache_table'); ?></span>
                                            <span class="badge badge-secondary ml-2" id="reload_cache_table_task_badge"></span>
                                        </div>
                                        <div class='col-2'>
                                            <?php
                                            $task = isset($SETTINGS['reload_cache_table_task']) === true ? explode(";", $SETTINGS['reload_cache_table_task']) : [];
                                            ?>
                                            <input type='text' disabled class='form-control form-control-sm' id='reload_cache_table_task_parameter' value='<?php echo isset($task[0]) === true && empty($task[0]) === false ? langHdl($task[0])." ".(isset($task[2]) === true ? strtolower(langHdl('day')).' '.$task[2].' ' : '').langHdl('at')." ".$task[1] : langHdl('not_defined') ?>'>
                                            <input type='hidden' disabled class='form-control form-control-sm' id='reload_cache_table_task_parameter_value' value='<?php echo isset($task[0]) === true ? $task[0].";".$task[1].(isset($task[2]) === true ? ';'.$task[2] : '') : '';?>'>
                                        </div>
                                        <div class='col-2'>
                                            <button class="btn btn-primary task-define" data-task="reload_cache_table_task">
                                                <i class="fa-solid fa-cogs"></i>
                                            </button>
                                            <?php
                                            if (defined('WIP') === true && WIP === true) {
                                                ?>
                                            <button class="btn btn-primary task-perform ml-1" data-task="reload_cache_table_task">
                                                <i class="fa-solid fa-play"></i>
                                            </button>
                                            <?php
                                            }
                                            ?>
                                        </div>
                                    </div>

                                    <div class='row mb-3 option'>
                                        <div class='col-10'>
                                        <h5><i class="fa-solid fa-rss mr-2"></i><?php echo langHdl('enable_tasks_log'); ?></h5>
                                            <small class='form-text text-muted'>
                                                <?php echo langHdl('enable_tasks_log_tip'); ?>
                                            </small>
                                        </div>
                                        <div class='col-2'>
                                            <div class='toggle toggle-modern' id='enable_tasks_log' data-toggle-on='<?php echo isset($SETTINGS['enable_tasks_log']) === true && (int) $SETTINGS['enable_tasks_log'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_tasks_log_input' value='<?php echo isset($SETTINGS['enable_tasks_log']) && (int) $SETTINGS['enable_tasks_log'] === 1 ? 1 : 0; ?>' />
                                        </div>
                                    </div>

                                    <div class='row mb-3 option'>
                                        <div class='col-10'>
                                        <h5><i class="fa-solid fa-hourglass-start mr-2"></i><?php echo langHdl('maximum_time_script_allowed_to_run'); ?></h5>
                                            <small id='passwordHelpBlock' class='form-text text-muted'>
                                                <?php echo langHdl('maximum_time_script_allowed_to_run_tip'); ?>
                                            </small>
                                        </div>
                                        <div class='col-2'>
                                            <input type='number' class='form-control form-control-sm' id='task_maximum_run_time' value='<?php echo isset($SETTINGS['task_maximum_run_time']) === true ? $SETTINGS['task_maximum_run_time'] : 600; ?>'>
                                        </div>
                                    </div>

                                    <div class='row mb-3 option'>
                                        <div class='col-10'>
                                        <h5><i class="fa-solid fa-object-group mr-2"></i><?php echo langHdl('maximum_number_of_items_to_treat'); ?></h5>
                                            <small id='passwordHelpBlock' class='form-text text-muted'>
                                                <?php echo langHdl('maximum_number_of_items_to_treat_tip'); ?>
                                            </small>
                                        </div>
                                        <div class='col-2'>
                                            <input type='number' class='form-control form-control-sm' id='maximum_number_of_items_to_treat' value='<?php echo isset($SETTINGS['maximum_number_of_items_to_treat']) === true ? $SETTINGS['maximum_number_of_items_to_treat'] : NUMBER_ITEMS_IN_BATCH; ?>'>
                                        </div>
                                    </div>

                                    <div class='row mb-3 option'>
                                        <div class='col-10'>
                                        <h5><i class="fa-solid fa-stopwatch-20 mr-2"></i><?php echo langHdl('refresh_data_every_on_screen'); ?></h5>
                                            <small id='passwordHelpBlock' class='form-text text-muted'>
                                                <?php echo langHdl('refresh_data_every_on_screen_tip'); ?>
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
                                            <th style=""></th>
                                            <th style=""><?php echo langHdl('created_at'); ?></th>
                                            <th style=""><?php echo langHdl('updated_at'); ?></th>
                                            <th style=""><?php echo langHdl('type'); ?></th>
                                            <th style=""><?php echo langHdl('user'); ?></th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>

                            <div class="tab-pane fade" id="finished" role="tabpanel" aria-labelledby="finished-tab">
                                <table class="table table-striped" id="table-tasks_finished" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th style=""></th>
                                            <th style=""><?php echo langHdl('created_at'); ?></th>
                                            <th style=""><?php echo langHdl('started_at'); ?></th>
                                            <th style=""><?php echo langHdl('finished_at'); ?></th>
                                            <th style=""><?php echo langHdl('execution_time'); ?></th>
                                            <th style=""><?php echo langHdl('type'); ?></th>
                                            <th style=""><?php echo langHdl('user'); ?></th>
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
                    <h3 class="card-title"><?php echo langHdl('logs_for_user'); ?> <span id="row-logs-title"></span></h3>
                </div>

                <!-- /.card-header -->
                <!-- table start -->
                <div class="card-body form" id="user-logs">
                    <table id="table-logs" class="table table-striped table-responsive" style="width:100%">
                        <thead>
                            <tr>
                                <th><?php echo langHdl('date'); ?></th>
                                <th><?php echo langHdl('activity'); ?></th>
                                <th><?php echo langHdl('label'); ?></th>
                            </tr>
                        </thead>
                        <tbody>

                        </tbody>
                    </table>
                </div>

                <div class="card-footer">
                    <button type="button" class="btn btn-default float-right tp-action" data-action="cancel"><?php echo langHdl('cancel'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- DELETE CONFIRM -->
    <div class="modal fade" tabindex="-1" role="dialog" aria-hidden="true" id="task-delete-user-confirm">
        <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
            <h4 class="modal-title"><?php echo langHdl('please_confirm_deletion'); ?></h4>
            </div>
            <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="modal-btn-delete" data-id=""><?php echo langHdl('yes'); ?></button>
            <button type="button" class="btn btn-default" id="modal-btn-cancel"><?php echo langHdl('cancel'); ?></button>
            </div>
        </div>
        </div>
    </div>

    <!-- ADAPT TASK -->
    <div class="modal fade" tabindex="-1" role="dialog" aria-hidden="true" id="task-define-modal">
        <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><i class="fa-solid fa-calendar-check mr-2"></i><?php echo langHdl('task_scheduling'); ?></h4>
            </div>
            
            <div class="modal-body" id="taskSchedulingModalBody">
                <div>
                    <h5><i class="fa-solid fa-clock-rotate-left mr-2"></i><?php echo langHdl('frequency'); ?></h5>              
                    <select class='form-control form-control-sm no-save' id='task-define-modal-frequency' style="width:100%;">
                        <?php
                        echo '<optgroup label="'.langHdl('run_once').'">
                        <option value="hourly">'.langHdl('hourly').'</option>
                        <option value="daily">'.langHdl('daily').'</option>
                        <option value="monthly">'.langHdl('monthly').'</option>
                        </optgroup>
                        <optgroup label="'.langHdl('run_weekday').'">
                        <option value="monday">'.langHdl('monday').'</option>
                        <option value="tuesday">'.langHdl('tuesday').'</option>
                        <option value="wednesday">'.langHdl('wednesday').'</option>
                        <option value="thursday">'.langHdl('thursday').'</option>
                        <option value="friday">'.langHdl('friday').'</option>
                        <option value="saturday">'.langHdl('saturday').'</option>
                        <option value="sunday">'.langHdl('sunday').'</option>
                        </optgroup>';
                        ?>
                    </select>
                </div>

                <div id="task-define-modal-parameter-monthly" class="hidden ml-2">
                    <div class="row mt-2">
                        <h5><?php echo langHdl('day_of_month'); ?></h5>              
                        <select class='form-control form-control-sm no-save' id='task-define-modal-parameter-monthly-value' style="width:100%;">
                            <?php
                            for ($i=1; $i<=31; $i++) {
                                echo '<option value="'.$i.'">'.langHdl('day').' '.$i.'</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div id="task-define-modal-parameter-daily" class="hidden ml-2">
                    <div class="row mt-2">
                        <h5 class=""><?php echo langHdl('at'); ?></h5>
                        <input type="time" class="form-control form-control-sm no-save" id="task-define-modal-parameter-daily-value" min="00:00" max="23:59"/>
                    </div>
                </div>

                <div id="task-define-modal-parameter-hourly" class="ml-2">
                    <div class="row mt-2">
                        <h5><?php echo langHdl('at'); ?></h5>              
                        <input type="time" class="form-control form-control-sm no-save" id="task-define-modal-parameter-hourly-value" min="00:00" max="00:59"/>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-warning" id="modal-btn-task-reset" data-id=""><?php echo langHdl('reset'); ?></button>
                <button type="button" class="btn btn-primary" id="modal-btn-task-save" data-id=""><?php echo langHdl('save'); ?></button>
                <button type="button" class="btn btn-default" id="modal-btn-task-cancel"><?php echo langHdl('cancel'); ?></button>
                <input type="hidden" id="task-define-modal-type"/>
            </div>
        </div>
        </div>
    </div>


</div>
<!-- /.content -->
