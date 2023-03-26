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
 * @version   3.0.2
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
                        <ul class="nav nav-tabs">
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#settings" aria-controls="settings" aria-selected="false"><?php echo langHdl('settings'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#jobs" aria-controls="jobs" aria-selected="true"><?php echo langHdl('tasks'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#in_progress" aria-controls="in_progress" aria-selected="false"><?php echo langHdl('in_progress'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#finished" role="tab" aria-controls="done" aria-selected="false"><?php echo langHdl('done'); ?></a>
                            </li>
                        </ul>


                        <div class="tab-content mt-1" id="myTabContent">
                            <!-- TAB SETTINGS -->
                            <div class="tab-pane fade show" id="settings" role="tabpanel" aria-labelledby="settings-tab">
                                <div class="card-body">
                                    <!--
                                    <div class='row mb-2 option' data-keywords="server setting cron job task"></h5>
                                        <div class='col-10'>
                                            <?php echo langHdl('enable_tasks_manager'); ?>
                                            <small id='passwordHelpBlock' class='form-text text-muted'>
                                                <?php echo langHdl('enable_tasks_manager_tip'); ?>
                                            </small>
                                        </div>
                                        <div class='col-2'>
                                            <div class='toggle toggle-modern disabled' id='enable_tasks_manager' data-toggle-on='<?php echo isset($SETTINGS['enable_tasks_manager']) && (int) $SETTINGS['enable_tasks_manager'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_tasks_manager_input' value='<?php echo isset($SETTINGS['enable_tasks_manager']) && (int) $SETTINGS['enable_tasks_manager'] === 1 ? '1' : '0'; ?>' />
                                        </div>
                                    </div>

                                    <div class='row mb-3 option'>
                                        <div class='col-10'>
                                        <h5><i class="fa-solid fa-envelopes-bulk mr-2"></i><?php echo langHdl('enable_backlog_mail'); ?></h5>
                                        </div>
                                        <div class='col-2'>
                                            <div class='toggle toggle-modern' id='enable_backlog_mail' data-toggle-on='<?php echo isset($SETTINGS['enable_backlog_mail']) && (int) $SETTINGS['enable_backlog_mail'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_backlog_mail_input' value='<?php echo isset($SETTINGS['enable_backlog_mail']) && (int) $SETTINGS['enable_backlog_mail'] === 1 ? '1' : '0'; ?>' />
                                        </div>
                                    </div>
-->

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
                                            <input type='text' class='form-control form-control-sm' id='task_maximum_run_time' value='<?php echo isset($SETTINGS['task_maximum_run_time']) === true ? $SETTINGS['task_maximum_run_time'] : 600; ?>'>
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
                                            <input type='text' class='form-control form-control-sm' id='maximum_number_of_items_to_treat' value='<?php echo isset($SETTINGS['maximum_number_of_items_to_treat']) === true ? $SETTINGS['maximum_number_of_items_to_treat'] : NUMBER_ITEMS_IN_BATCH; ?>'>
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
                                            <input type='text' class='form-control form-control-sm' id='tasks_manager_refreshing_period' value='<?php echo isset($SETTINGS['tasks_manager_refreshing_period']) === true ? $SETTINGS['tasks_manager_refreshing_period'] : 20; ?>'>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB JOBS -->
                            <div class="tab-pane fade show active" id="jobs" role="tabpanel" aria-labelledby="jobs-tab">
                                <div class="card-body p-0">

                                    <div class="callout callout-info alert-dismissible mt-3">
                                        <h5><i class="fa-solid fa-info mr-2"></i><?php echo langHdl('information'); ?></h5>
                                        <?php echo str_replace("#teampass_path#", $SETTINGS['cpassman_dir'], langHdl('tasks_information')); ?>

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
                                    </div>

                                    <div class='row ml-1 mt-3 mb-2'>
                                        <div class='col-5'>
                                        <i class="fa-solid fa-inbox mr-2"></i><?php echo langHdl('sending_emails'); ?>
                                        </div>
                                        <div class='col-5 text-right'>
                                            <?php echo langHdl('task_frequency'); ?>
                                        </div>
                                        <div class='col-2'>
                                            <input type='text' class='form-control form-control-sm' id='sending_emails_job_frequency' value='<?php echo $SETTINGS['sending_emails_job_frequency'] ?? '2'; ?>'>
                                        </div>
                                    </div>

                                    <div class='row ml-1 mb-2'>
                                        <div class='col-5'>
                                        <i class="fa-solid fa-user-cog mr-2"></i><?php echo langHdl('user_keys_management'); ?>
                                        </div>
                                        <div class='col-5 text-right'>
                                            <?php echo langHdl('task_frequency'); ?>
                                        </div>
                                        <div class='col-2'>
                                            <input type='text' class='form-control form-control-sm' id='user_keys_job_frequency' value='<?php echo $SETTINGS['user_keys_job_frequency'] ?? '1'; ?>'>
                                        </div>
                                    </div>

                                    <div class='row ml-1 mb-2'>
                                        <div class='col-5'>
                                        <i class="fa-solid fa-chart-simple mr-2"></i><?php echo langHdl('items_and_folders_statistics'); ?>
                                        </div>
                                        <div class='col-5 text-right'>
                                            <?php echo langHdl('task_frequency'); ?>
                                        </div>
                                        <div class='col-2'>
                                            <input type='text' class='form-control form-control-sm' id='items_statistics_job_frequency' value='<?php echo $SETTINGS['items_statistics_job_frequency'] ?? '5'; ?>'>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <div class="tab-pane fade" id="in_progress" role="tabpanel" aria-labelledby="in_progress-tab">
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
                                <table class="table table-striped" id="table-tasks_finished" style="">
                                    <thead>
                                        <tr>
                                            <th style=""></th>
                                            <th style=""><?php echo langHdl('created_at'); ?></th>
                                            <th style=""><?php echo langHdl('finished_at'); ?></th>
                                            <th style=""><?php echo langHdl('type'); ?></th>
                                            <th style=""><?php echo langHdl('user'); ?></th>
                                            <th style=""><?php echo langHdl('execution_time'); ?></th>
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


</div>
<!-- /.content -->
