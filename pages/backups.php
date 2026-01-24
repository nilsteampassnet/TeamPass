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
 * @copyright 2009-2026 Teampass.net
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
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
loadClasses('DB');
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
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('backups') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

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
                        <div class="card-tools">
                            <div id="tp-disk-usage" class="d-flex align-items-center" style="gap:8px; display:none;">
                                <small class="text-white" id="tp-disk-usage-title"><?php echo $lang->get('bck_storage_usage'); ?></small>
                                <div class="progress" style="width:220px;height:10px;" title="">
                                    <div id="tp-disk-usage-bar" class="progress-bar" role="progressbar" style="width:0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <small class="text-white" id="tp-disk-usage-label"></small>
                            </div>
                        </div>
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
                                                    <input type="hidden" id="onthefly-restore-server-scope" value="">
                                                    <input type="hidden" id="onthefly-restore-server-file" value="">
                                                    <input type="hidden" id="onthefly-restore-serverfile" value="">
                                                    <div class="alert alert-info mt-2" id="onthefly-restore-server-selected" style="display:none;">
                                                        <i class="fas fa-info-circle"></i>
                                                        <?php echo sprintf($lang->get('bck_selected_server_backup'), '<b><span id="onthefly-restore-server-selected-name"></span></b>'); ?><br>
                                                        <?php echo $lang->get('encrypt_key'); ?>: <b><?php echo $lang->get('bck_instance_key'); ?></b> (<?php echo $lang->get('bck_no_manual_key_required'); ?>)
                                                    </div>
                                                    <button class="btn btn-default btn-choose-file" id="onthefly-restore-file-select"><?php echo $lang->get('choose_file'); ?></button>
                                                    <span class="ml-2" id="onthefly-restore-file-text"></span>
                                                </div>
                                            </div>
                                            <div class="alert alert-info ml-2 mt-3 mr-2 hidden" id="onthefly-restore-progress">
                                                <h5><i class="icon fa fa-info mr-2"></i><?php echo $lang->get('in_progress'); ?></h5>
                                                <i class="mr-2 fas fa-rocket"></i><?php echo $lang->get('restore_in_progress');?> <b><span id="onthefly-restore-progress-text">0</span>%</b>
                                            </div>
                                            <div class="row mt-3 hidden" id="onthefly-restore-finished"></div>
                                            <div class="row mt-3">
                                                <button class="btn btn-info ml-1 start" data-action="onthefly-restore"><?php echo $lang->get('prepare_restore'); ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4" id="onthefly-server-backups-block">
                                    <div class="callout callout-info">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h4 class="mb-0"><?php echo $lang->get('bck_onthefly_server_backups'); ?></h4>
                                            <button type="button" class="btn btn-sm btn-secondary" id="onthefly-server-backups-refresh" title="<?php echo $lang->get('bck_onthefly_refresh_list'); ?>">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>

                                        <div class="table-responsive mt-3">
                                            <table class="table table-sm table-hover mb-1">
                                                <thead>
                                                    <tr>
                                                        <th class="text-nowrap"><?php echo $lang->get('bck_onthefly_col_date'); ?></th>
                                                        <th class="text-nowrap"><?php echo $lang->get('bck_onthefly_col_size'); ?></th>
                                                        <th class="text-nowrap"><?php echo $lang->get('bck_col_teampass_version'); ?></th>
                                                        <th class="text-right text-nowrap"><?php echo $lang->get('bck_onthefly_col_action'); ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="onthefly-server-backups-tbody">
                                                    <tr>
                                                        <td colspan="4" class="text-muted"><?php echo $lang->get('bck_onthefly_loading'); ?></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        <small class="text-muted"><?php echo $lang->get('bck_onthefly_server_backups_help'); ?></small>
                                    </div>
                                </div>

                            </div>

                            <div class="tab-pane fade" id="scheduled" role="tabpanel" aria-labelledby="scheduled-tab">
                                <div class="alert alert-info mt-3" id="scheduled-restore-info" style="display:none;">
                                    <i class="fa-solid fa-info-circle"></i>
                                    <?php echo sprintf($lang->get('bck_restore_scheduled_info'), '<b>' . $lang->get('on_the_fly') . '</b>', '<b>' . $lang->get('bck_instance_encryption_key') . '</b>'); ?>
                                </div>
                                <div class="row">
                                    <div class="col-12 col-lg-6">
                                        <div class="card mt-3">
                                            <div class="card-header">
                                                <i class="fa-solid fa-cog mr-2"></i>
                                                <strong><?php echo $lang->get('bck_scheduled_title'); ?></strong>
                                            </div>
                                            <div class="card-body">

                                                <div id="scheduled-alert" class="alert d-none" role="alert"></div>

                                                <div class="row mb-4">
                                                    <div class="col-9">
                                                        <?php echo $lang->get('bck_scheduled_enabled'); ?>
                                                    </div>
                                                    <div class="col-3 d-flex justify-content-end">
                                                        <div class="toggle toggle-modern" id="backup-scheduled-enabled" data-toggle-on="<?php echo isset($SETTINGS['backup-scheduled-enabled']) && (int) $SETTINGS['backup-scheduled-enabled'] === 1 ? 'true' : 'false'; ?>"></div><input type="hidden" id="backup-scheduled-enabled_input" value="<?php echo isset($SETTINGS['backup-scheduled-enabled']) && (int) $SETTINGS['backup-scheduled-enabled'] === 1 ? '1' : '0'; ?>">
                                                    </div>
                                                </div>

                                                <div class="row mb-3">
                                                    <div class="col-6">
                                                        <label class="form-label" for="scheduled-frequency"><?php echo $lang->get('bck_scheduled_frequency'); ?></label>
                                                    </div>
                                                    <div class="col-6 d-flex justify-content-end">
                                                        <select class="form-control form-control-sm" id="scheduled-frequency">
                                                            <option value="daily"><?php echo $lang->get('bck_frequency_daily'); ?></option>
                                                            <option value="weekly"><?php echo $lang->get('bck_frequency_weekly'); ?></option>
                                                            <option value="monthly"><?php echo $lang->get('bck_frequency_monthly'); ?></option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="row mb-3">
                                                    <div class="col-6">
                                                        <label class="form-label" for="scheduled-time"><?php echo $lang->get('bck_scheduled_time'); ?></label>
                                                    </div>
                                                    <div class="col-6 d-flex justify-content-end">
                                                        <input class="form-control" type="time" id="scheduled-time" value="02:00">
                                                    </div>
                                                </div>

                                                <div class="row mb-3 d-none" id="scheduled-weekly-wrap">
                                                    <div class="col-6">
                                                        <label class="form-text" for="scheduled-dow"><?php echo $lang->get('bck_scheduled_dow'); ?></label>
                                                    </div>
                                                    <div class="col-6 d-flex justify-content-end">
                                                        <select class="form-control form-control-sm" id="scheduled-dow">
                                                            <option value="1"><?php echo $lang->get('monday'); ?></option>
                                                            <option value="2"><?php echo $lang->get('tuesday'); ?></option>
                                                            <option value="3"><?php echo $lang->get('wednesday'); ?></option>
                                                            <option value="4"><?php echo $lang->get('thursday'); ?></option>
                                                            <option value="5"><?php echo $lang->get('friday'); ?></option>
                                                            <option value="6"><?php echo $lang->get('saturday'); ?></option>
                                                            <option value="7"><?php echo $lang->get('sunday'); ?></option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="mb-3 d-none" id="scheduled-monthly-wrap">
                                                    <div class="col-6">
                                                        <label class="form-label" for="scheduled-dom"><?php echo $lang->get('bck_scheduled_dom'); ?></label>
                                                    </div>
                                                    <div class="col-6 d-flex justify-content-end">
                                                        <input class="form-control" type="number" min="1" max="31" id="scheduled-dom" value="1">
                                                    </div>
                                                </div>

                                                <div class="row mb-3">
                                                    <div class="col-6">
                                                        <label class="form-label" for="scheduled-retention"><?php echo $lang->get('bck_scheduled_retention_days'); ?></label>                                                    
                                                    </div>
                                                    <div class="col-6 d-flex justify-content-end">
                                                        <input class="form-control" type="number" min="1" max="3650" id="scheduled-retention" value="30">
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label" for="scheduled-output-dir"><?php echo $lang->get('bck_scheduled_output_dir'); ?></label>
                                                    <input class="form-control" type="text" id="scheduled-output-dir" placeholder="/path/to/files/backups">
                                                    <small class="form-text text-muted mt-4">
                                                        <?php echo $lang->get('bck_scheduled_output_dir_help'); ?>
                                                    </small>
                                                </div>

                                                
                                                <hr class="my-4">

                                                <div class="row mb-3">
                                                    <div class="col-9">
                                                        <?php echo $lang->get('bck_scheduled_email_report_enabled'); ?>
                                                        <small class="form-text text-muted">
                                                            <?php echo $lang->get('bck_scheduled_email_report_help'); ?>
                                                        </small>
                                                    </div>
                                                    <div class="col-3 d-flex justify-content-end align-items-start">
                                                        <div class="toggle toggle-modern" id="scheduled-email-report-enabled" data-toggle-on="false"></div>
                                                        <input type="hidden" id="scheduled-email-report-enabled_input" value="0">
                                                    </div>
                                                </div>

                                                <div class="row mb-4 d-none" id="scheduled-email-report-only-failures-wrap">
                                                    <div class="col-9">
                                                        <?php echo $lang->get('bck_scheduled_email_report_only_failures'); ?>
                                                    </div>
                                                    <div class="col-3 d-flex justify-content-end">
                                                        <div class="toggle toggle-modern" id="scheduled-email-report-only-failures" data-toggle-on="false"></div>
                                                        <input type="hidden" id="scheduled-email-report-only-failures_input" value="0">
                                                    </div>
                                                </div>

<div class="d-flex gap-2">
                                                    <button type="button" class="btn btn-primary" id="scheduled-save-btn">
                                                        <?php echo $lang->get('bck_scheduled_save'); ?>
                                                    </button>
                                                    <button type="button" class="btn btn-secondary ml-2" id="scheduled-run-btn">
                                                        <?php echo $lang->get('bck_scheduled_run_now'); ?>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-secondary ml-2" id="scheduled-refresh-btn">
                                                        <?php echo $lang->get('bck_scheduled_refresh'); ?>
                                                    </button>
                                                </div>

                                            </div>
                                        </div>

                                        <div class="card mt-3">
                                            <div class="card-header">
                                                <i class="fa-solid fa-eye mr-2"></i>
                                                <strong><?php echo $lang->get('bck_scheduled_status'); ?></strong>
                                            </div>
                                            <div class="card-body">
                                                <div><strong><?php echo $lang->get('bck_scheduled_next_run_at'); ?>:</strong> <span id="scheduled-next-run">-</span></div>
                                                <div><strong><?php echo $lang->get('bck_scheduled_last_run_at'); ?>:</strong> <span id="scheduled-last-run">-</span></div>
                                                <div><strong><?php echo $lang->get('bck_scheduled_last_status'); ?>:</strong> <span id="scheduled-last-status">-</span></div>
                                                <div><strong><?php echo $lang->get('bck_scheduled_last_message'); ?>:</strong> <span id="scheduled-last-message">-</span></div>
                                                <hr>
                                                <div><strong><?php echo $lang->get('bck_scheduled_last_purge_at'); ?>:</strong> <span id="scheduled-last-purge">-</span></div>
                                                <div><strong><?php echo $lang->get('bck_scheduled_last_purge_deleted'); ?>:</strong> <span id="scheduled-last-purge-deleted">0</span></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 col-lg-6">
                                        <div class="card mt-3">
                                            <div class="card-header d-flex align-items-center">
                                                <i class="fa-solid fa-rectangle-list mr-2"></i>
                                                <strong><?php echo $lang->get('bck_scheduled_backups_list'); ?></strong>
                                            </div>
                                            <div class="card-body">
                                            <div class="alert alert-info py-2 px-3 mb-3">
                                                <button type="button" class="btn btn-link p-0 tp-copy-instance-key" data-toggle="tooltip" title="<?php echo $lang->get('bck_instance_key_copy_tooltip'); ?>" aria-label="<?php echo $lang->get('bck_instance_key_copy_tooltip'); ?>"><i class="fas fa-info-circle"></i></button>
                                                <?php
                                                echo $lang->get('bck_scheduled_note_encrypted');?>
                                            </div>

                                                <div class="table-responsive">
                                                    <table class="table table-sm table-striped" id="scheduled-backups-table">
                                                        <thead>
                                                            <tr>
                                                                <th class="text-nowrap"><?php echo $lang->get('bck_onthefly_col_date'); ?></th>
                                                                <th class="text-nowrap"><?php echo $lang->get('bck_onthefly_col_size'); ?></th>
                                                                <th class="text-nowrap"><?php echo $lang->get('bck_col_teampass_version'); ?></th>
                                                                <th class="text-right text-nowrap"><?php echo $lang->get('bck_onthefly_col_action'); ?></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="scheduled-backups-tbody"></tbody>
                                                    </table>
                                                </div>

                                                <div class="mt-3" id="scheduled-restore-block">
                                                    <input type="hidden" id="scheduled-restore-server-file" value="">
                                                    <input type="hidden" id="scheduled-restore-override-key" value="">
                                                    <div class="alert alert-info py-2 px-3 mb-3" id="scheduled-restore-selected" style="display:none;">
                                                        <i class="fas fa-info-circle"></i>
                                                        <?php echo sprintf($lang->get('bck_selected_server_backup'), '<b><span id="scheduled-restore-selected-name"></span></b>'); ?><br>
                                                        <?php echo $lang->get('bck_instance_encryption_key'); ?> <b><?php echo $lang->get('bck_instance_key'); ?></b> (<?php echo $lang->get('bck_no_manual_key_required'); ?>)
                                                        <div class="mt-2">
                                                            <a href="#" class="small" id="scheduled-restore-change-key"><?php echo $lang->get('bck_restore_use_other_key'); ?></a>
                                                        </div>
                                                    </div>

                                                    <div class="alert alert-info ml-2 mt-3 mr-2 hidden" id="scheduled-restore-progress">
                                                        <h5><i class="icon fa fa-info mr-2"></i><?php echo $lang->get('in_progress'); ?></h5>
                                                        <i class="mr-2 fas fa-sync-alt"></i><?php echo $lang->get('restore_in_progress'); ?> <b><span id="scheduled-restore-progress-text">0</span>%</b>
                                                    </div>
                                                    <div class="row mt-3 hidden" id="scheduled-restore-finished"></div>
                                                    <div class="row mt-3">
                                                        <button type="button" class="btn btn-info ml-1" id="scheduled-restore-start" disabled><?php echo $lang->get('prepare_restore'); ?></button>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>



<!-- Confirm restore modal -->
<div class="modal fade" id="tp-confirm-restore-modal" tabindex="-1" role="dialog" aria-labelledby="tp-confirm-restore-title" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tp-confirm-restore-title"><?php echo $lang->get('confirm'); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo $lang->get('close'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="tp-confirm-restore-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $lang->get('cancel'); ?></button>
                <button type="button" class="btn btn-danger" id="tp-confirm-restore-yes"><?php echo $lang->get('prepare_restore'); ?></button>
            </div>
        </div>
    </div>
</div>


<!-- Restore key modal (used when a scheduled backup cannot be decrypted with the current instance key) -->
<div class="modal fade" id="tp-restore-key-modal" tabindex="-1" role="dialog" aria-labelledby="tp-restore-key-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tp-restore-key-modal-title"><?php echo $lang->get('bck_restore_key_modal_title'); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo $lang->get('close'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><?php echo $lang->get('bck_restore_key_modal_body'); ?></p>
                <div class="alert alert-danger hidden" id="tp-restore-key-modal-error" role="alert"></div>
                <input type="text" class="form-control form-control-sm" id="tp-restore-key-modal-input" placeholder="<?php echo $lang->get('encrypt_key'); ?>">
                <small class="form-text text-muted"><?php echo $lang->get('bck_restore_other_key_help'); ?></small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $lang->get('cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="tp-restore-key-modal-yes"><?php echo $lang->get('bck_restore_key_use'); ?></button>
            </div>
        </div>
    </div>
</div>



<style>
/* Fun restore progress: a little cat that moves along the progress bar */
.tp-restore-progress-track { position: relative; overflow: visible; }
.tp-restore-progress-cat { position: absolute; top: -18px; left: 2%; transform: translateX(-50%); font-size: 18px; }
</style>

<!-- Strict mode: Connected users modal -->
<div class="modal fade" id="tp-connected-users-modal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo $lang->get('bck_exclusive_users_title'); ?></h5>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-3">
                    <?php echo $lang->get('bck_exclusive_users_desc'); ?>
                </div>

                <table id="tp-connected-users-table" class="table table-striped table-sm" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width:80px;"></th>
                            <th><?php echo $lang->get('user'); ?></th>
                            <th><?php echo $lang->get('role'); ?></th>
                            <th><?php echo $lang->get('login_time'); ?></th>
                            <th class="text-center text-nowrap" style="min-width:48px;">API</th>
                        </tr>
                    </thead>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $lang->get('cancel'); ?></button>
                <button type="button" class="btn btn-default" id="tp-connected-users-refresh"><?php echo $lang->get('refresh'); ?></button>
                <button type="button" class="btn btn-danger" id="tp-connected-users-disconnect-all">
                    <i class="far fa-trash-alt"></i> <?php echo $lang->get('bck_exclusive_disconnect_all'); ?>
                </button>
                <button type="button" class="btn btn-primary" id="tp-connected-users-continue" disabled>
                    <?php echo $lang->get('bck_exclusive_continue'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Restore progress modal (locks UI during restore) -->
<div class="modal fade" id="tp-restore-progress-modal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tp-restore-progress-title"><?php echo $lang->get('in_progress'); ?></h5>
            </div>
            <div class="modal-body">
                <div id="tp-restore-progress-msg"><?php echo $lang->get('restore_in_progress_auto_logout'); ?></div>
                <div class="progress mt-3 tp-restore-progress-track">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" id="tp-restore-progress-bar" role="progressbar" style="width:0%" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
                    <i id="tp-restore-progress-cat" class="fas fa-cat tp-restore-progress-cat" aria-hidden="true"></i>
                </div>
                <div class="mt-2 text-muted"><span id="tp-restore-progress-pct">0</span>%</div>
            </div>
        </div>
    </div>
</div>


<!-- CLI restore command modal -->
    <div class="modal fade" id="tp-cli-restore-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info">
                    <h5 class="modal-title" id="tp-cli-restore-title"><?php echo $lang->get('bck_restore_cli_title'); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-warning" id="tp-cli-restore-warnings" style="display:none;"></div>

                    <p class="mb-2"><?php echo $lang->get('bck_restore_cli_prereq'); ?></p>
	                    <div class="alert alert-info" id="tp-cli-restore-logout-info">
	                        <?php echo $lang->get('bck_restore_cli_logout_info'); ?>
	                    </div>

                    <div class="form-group">
                        <label for="tp-cli-restore-command" class="text-bold"><?php echo $lang->get('bck_restore_cli_command'); ?></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="tp-cli-restore-command" readonly>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="tp-cli-restore-copy">
                                    <?php echo $lang->get('bck_restore_cli_copy'); ?>
                                </button>
                            </div>
                        </div>
                        <small class="text-muted" id="tp-cli-restore-expires"></small>
                    </div>

                    <pre class="bg-light p-2 rounded" id="tp-cli-restore-command-raw" style="white-space: pre-wrap;"></pre>
                </div>

                <div class="modal-footer">
	                    <button type="button" class="btn btn-danger" id="tp-cli-restore-logout"><?php echo $lang->get('bck_restore_cli_logout'); ?></button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $lang->get('close'); ?></button>
                </div>
            </div>
        </div>
    </div>
