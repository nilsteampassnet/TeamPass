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
 * @file      admin.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
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

// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('admin') === false) {
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

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">
                    <i class="fas fa-tachometer-alt"></i> <?php echo $lang->get('admin_tab_dashboard'); ?>
                </h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php?page=items"><?php echo $lang->get('home'); ?></a></li>
                    <li class="breadcrumb-item active"><?php echo $lang->get('admin'); ?></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Info Box for last update and GitHub link -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="alert alert-info alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5>
                        <i class="icon fas fa-info"></i> <?php echo $lang->get('admin_info_header'); ?>
                    </h5>
                    <span>
                        <i class="fa-regular fa-eye mr-2"></i><?php echo $lang->get('currently_using_version')." <b>".TP_VERSION.".<i>".TP_VERSION_MINOR."</i></b>"; ?>
                    </span> | 
                    <span id="admin-last-refresh"><?php echo $lang->get('admin_last_refresh'); ?>: <span class="badge badge-light" id="last-refresh-time">--:--:--</span></span>
                     | 
                    <span class="ml-3">
                        <a href="https://github.com/nilsteampassnet/TeamPass/releases" target="_blank" class="text-white">
                            <i class="fab fa-github"></i> <?php echo $lang->get('admin_view_changelog_github'); ?>
                        </a>
                    </span>
                </div>
            </div>
        </div>

        <!-- Main tabs card -->
        <div class="card card-primary card-outline card-outline-tabs">
            <div class="card-body">
                <div class="tab-content" id="admin-tabs-content">
                    
                    <!-- ============================================ -->
                    <!-- DASHBOARD TAB -->
                    <!-- ============================================ -->
                    <div class="tab-pane fade show active" id="tab-dashboard" role="tabpanel" aria-labelledby="tab-dashboard-link">
                        
                        <!-- Statistics Cards Row -->
                        <div class="row">
                            <!-- Users Statistics -->
                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-info">
                                    <div class="inner">
                                        <h3 id="stat-users-active">-</h3>
                                        <p><?php echo $lang->get('admin_stat_active_users'); ?></p>
                                        <div class="small text-white">
                                            <span><?php echo $lang->get('admin_stat_online'); ?>: <strong id="stat-users-online">-</strong></span><br>
                                            <span><?php echo $lang->get('admin_stat_blocked'); ?>: <strong id="stat-users-blocked">-</strong></span>
                                        </div>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <a href="index.php?page=users" class="small-box-footer">
                                        <?php echo $lang->get('admin_stat_more_info'); ?> <i class="fas fa-arrow-circle-right"></i>
                                    </a>
                                    <div class="overlay" id="loading-stat-users" style="display:none;">
                                        <i class="fas fa-sync fa-spin"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Items Statistics -->
                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-success">
                                    <div class="inner">
                                        <h3 id="stat-items-total">-</h3>
                                        <p><?php echo $lang->get('admin_stat_total_items'); ?></p>
                                        <div class="small text-white">
                                            <span><?php echo $lang->get('admin_stat_shared'); ?>: <strong id="stat-items-shared">-</strong></span><br>
                                            <span><?php echo $lang->get('admin_stat_personal'); ?>: <strong id="stat-items-personal">-</strong></span>
                                        </div>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-key"></i>
                                    </div>
                                    <a href="index.php?page=utilities.deletion" class="small-box-footer">
                                        <?php echo $lang->get('admin_stat_more_info'); ?> <i class="fas fa-arrow-circle-right"></i>
                                    </a>
                                    <div class="overlay" id="loading-stat-items" style="display:none;">
                                        <i class="fas fa-sync fa-spin"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Folders Statistics -->
                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-warning">
                                    <div class="inner">
                                        <h3 id="stat-folders-total">-</h3>
                                        <p><?php echo $lang->get('admin_stat_total_folders'); ?></p>
                                        <div class="small text-white">
                                            <span><?php echo $lang->get('admin_stat_public'); ?>: <strong id="stat-folders-public">-</strong></span><br>
                                            <span><?php echo $lang->get('admin_stat_personal'); ?>: <strong id="stat-folders-personal">-</strong></span>
                                        </div>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-folder-open"></i>
                                    </div>
                                    <a href="index.php?page=folders" class="small-box-footer">
                                        <?php echo $lang->get('admin_stat_more_info'); ?> <i class="fas fa-arrow-circle-right"></i>
                                    </a>
                                    <div class="overlay" id="loading-stat-folders" style="display:none;">
                                        <i class="fas fa-sync fa-spin"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Logs Statistics -->
                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-danger">
                                    <div class="inner">
                                        <h3 id="stat-logs-actions">-</h3>
                                        <p><?php echo $lang->get('admin_stat_logs_24h'); ?></p>
                                        <div class="small text-white">
                                            <span><?php echo $lang->get('admin_stat_accesses'); ?>: <strong id="stat-logs-accesses">-</strong></span><br>
                                            <span><?php echo $lang->get('admin_stat_errors'); ?>: <strong id="stat-logs-errors">-</strong></span>
                                        </div>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <a href="index.php?page=utilities.logs" class="small-box-footer">
                                        <?php echo $lang->get('admin_stat_more_info'); ?> <i class="fas fa-arrow-circle-right"></i>
                                    </a>
                                    <div class="overlay" id="loading-stat-logs" style="display:none;">
                                        <i class="fas fa-sync fa-spin"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Live Activity and System Status Row -->
                        <div class="row">
                            <!-- Live Activity Card -->
                            <div class="col-lg-6">
                                <div class="card card-warning">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-circle text-danger blink"></i> 
                                            <?php echo $lang->get('admin_live_activity'); ?>
                                        </h3>
                                        <div class="card-tools">
                                            <span class="badge badge-warning" id="activity-refresh-countdown">10s</span>
                                        </div>
                                    </div>
                                    <div class="card-body p-0" style="max-height: 350px; overflow-y: auto;">
                                        <ul class="list-group list-group-flush" id="live-activity-list">
                                            <li class="list-group-item text-center text-muted">
                                                <i class="fas fa-sync fa-spin"></i> <?php echo $lang->get('admin_loading'); ?>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="card-footer clearfix">
                                        <a href="index.php?page=items_logs" class="btn btn-sm btn-warning float-right">
                                            <?php echo $lang->get('admin_view_all_logs'); ?>
                                        </a>
                                    </div>
                                    <div class="overlay" id="loading-activity" style="display:none;">
                                        <i class="fas fa-sync fa-spin"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- System Status Card -->
                            <div class="col-lg-6">
                                <div class="card card-primary">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-server"></i> 
                                            <?php echo $lang->get('admin_tasks_status'); ?>
                                        </h3>
                                        <div class="card-tools">
                                            <span class="badge badge-primary" id="status-refresh-countdown">60s</span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mt-3">
                                            <div class="col-6">
                                                <div class="description-block">
                                                    <h5 class="description-header" id="system-last-cron">--</h5>
                                                    <span class="description-text"><?php echo $lang->get('admin_system_last_cron'); ?></span>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="description-block">
                                                    <h5 class="description-header" id="system-tasks">-</h5>
                                                    <span class="description-text"><?php echo $lang->get('admin_system_tasks_queue'); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <div class="alert alert-warning hidden" id="task_duration_status"></div>
                                            </div>
                                        </div>
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
                                        <div class="row mt-3">
                                            <div class="col-12">
                                            <div class="callout callout-info alert-dismissible mt-3" role="alert">
                                                <h5><i class="fa-solid fa-info mr-2"></i><?php echo $lang->get('information'); ?></h5>
                                                <?php echo str_replace("#teampass_path#", $SETTINGS['cpassman_dir'], $lang->get('tasks_information')); ?>
                                                <div class="mt-2">
                                                    <a href="index.php?page=tasks#settings" class="btn btn-info" role="button"><i class="fa-solid fa-arrow-up-right-from-square mr-2"></i><?php echo $lang->get('open_tasks_settings'); ?></a>
                                                </div>
                                            </div>
                                            </div>
                                        </div>
        <?php
    }
}
catch (Exception $e) {
    if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
        error_log('TEAMPASS Error - admin page - '.$e->getMessage());
    }
    echo 'An error occurred. Please refer to server logs.';
}
?>
                                    </div>
                                    <div class="card-footer">
                                        <a href="index.php?page=tasks" class="btn btn-sm btn-primary">
                                            <?php echo $lang->get('admin_view_tasks'); ?>
                                        </a>
                                    </div>
                                    <div class="overlay" id="loading-status" style="display:none;">
                                        <i class="fas fa-sync fa-spin"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Health and Teampass Information Row -->
                        <div class="row">
                            <!-- System Health Card -->
                            <div class="col-lg-6">
                                <div class="card card-success">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-heartbeat"></i> 
                                            <?php echo $lang->get('admin_system_health'); ?>
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-lock text-success"></i> <?php echo $lang->get('admin_health_encryption'); ?></span>
                                                <span class="badge badge-success" id="health-encryption">-</span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-database text-success"></i> <?php echo $lang->get('admin_health_database'); ?></span>
                                                <span class="badge badge-success" id="health-database">-</span>
                                            </li>
                                            <!--
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-file-code text-success"></i> <?php echo $lang->get('database_integrity'); ?></span>
                                                <span class="badge badge-success" id="health-database-integrity">-</span>
                                            </li>
-->
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-user-clock text-success"></i> <?php echo $lang->get('admin_health_sessions'); ?></span>
                                                <span class="badge badge-info" id="health-sessions">-</span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-history text-success"></i> <?php echo $lang->get('admin_health_cron'); ?></span>
                                                <span class="badge badge-success" id="health-cron">-</span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-file-code text-warning"></i> <?php echo $lang->get('admin_health_unknown_files'); ?></span>
                                                <span class="badge badge-warning" id="health-unknown-files">-</span>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="overlay" id="loading-health" style="display:none;">
                                        <i class="fas fa-sync fa-spin"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Teampass Information Card -->
                            <div class="col-lg-6">
                                <div class="card card-default">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fa-solid fa-barcode"></i>
                                            <?php echo $lang->get('teampass_information'); ?>
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-group list-group-flush">                                
                                        <?php   
                                        if (isset($SETTINGS['enable_tasks_manager']) === true && (int) $SETTINGS['enable_tasks_manager'] === 0) {
                                            echo '<div class="alert bg-orange disabled" role="alert">
                                                <h5><i class="fa-solid fa-exclamation-triangle mr-2"></i>Since 3.0.0.23, TASKS manager is enabled by default and is mandatory.</h5>
                                                <p>Please ensure that cron job is set and enabled.<br />Open Tasks page and check status.</p>
                                                <p><a href="https://documentation.teampass.net/#/manage/tasks" target="_blank"><i class="fa-solid fa-book mr-2"></i>Check documentation</a>.</p>
                                            </div>';
                                        } else {
                                            ?>
                                            <div class="">
                                        <?php
                                        
                                        ?>
                                            </div>
                                        <?php                        
                                        }
                                        ?>


                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><i class="fa-solid fa-file-shield text-info"></i> <?php echo $lang->get('perform_file_integrity_check'); ?></span>
                                            <span>
                                                <button type="button" class="btn btn-primary btn-sm ml-2" id="check-project-files-btn" onclick="performProjectFilesIntegrityCheck()">
                                                <i class="fas fa-caret-right"></i>
                                            </button>
                                            </span>
                                        </li>
                                        <?php

                                        // Has the transparent recovery migration been done?
                                        DB::query(
                                            "SELECT id FROM " . prefixTable('users') . "
                                            WHERE (user_derivation_seed IS NULL
                                            OR private_key_backup IS NULL)
                                            AND disabled = 0"
                                        );
                                        if (DB::count() !== 0) {
                                            ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><i class="fa-solid fa-chart-bar text-info"></i> <?php echo $lang->get('perform_transparent_recovery_check'); ?></span>
                                            <span>
                                                <button type="button" class="btn btn-primary btn-sm ml-2" id="check-transparent-recovery-btn" onclick="performTransparentRecoveryCheck()">
                                                <i class="fas fa-caret-right"></i>
                                            </button>
                                            </span>
                                        </li>
                                            <?php
                                        }

                                        // Has the personal items migration been done for users?
                                        $stats = DB::query(
                                            "SELECT 
                                                COUNT(*) as total_users,
                                                SUM(CASE WHEN personal_items_migrated = 1 THEN 1 ELSE 0 END) as migrated_users,
                                                SUM(CASE WHEN personal_items_migrated = 0 THEN 1 ELSE 0 END) as pending_users
                                            FROM " . prefixTable('users') . "
                                            WHERE disabled = 0 AND deleted_at IS NULL"
                                        );
                                        $progressPercent = ($stats[0]['migrated_users'] / $stats[0]['total_users']) * 100;
                                        if ($progressPercent !== 100) {
                                            ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><i class="fa-solid fa-chart-bar text-info"></i> <?php echo $lang->get('get_personal_items_migration_status'); ?></span>
                                            <span>
                                                <button type="button" class="btn btn-primary btn-sm ml-2" id="personal-items-migration-btn" onclick="performPersonalItemsMigrationCheck()">
                                                <i class="fas fa-caret-right"></i>
                                            </button>
                                            </span>
                                        </li>
                                            <?php
                                        }
                                        // Status on users passwords migration to new encryption Symfony Password
                                        DB::query(
                                            "SELECT id 
                                            FROM ".prefixTable('users')." 
                                            WHERE pw LIKE '$2y$10$%' 
                                            AND pw NOT LIKE '$2y$13$%' 
                                            AND id NOT IN (9999991,9999997,9999998,9999999)
                                            AND (login NOT LIKE '%_deleted_%'
                                            OR deleted_at IS NULL)"
                                        );
                                        if (DB::count() > 0) {
                                            ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><i class="fa-solid fa-triangle-exclamation text-warning"></i>
                                            Password Encryption Migration Required<br>
                                            <?php echo DB::count();?> user accounts still use the legacy encryption library and must be migrated before upgrading to version 3.2.0.<br>
                                            To migrate: Users must either log in once or have their password updated via the Users management page.
                                            </span>
                                        </li>
                                            <?php
                                        }

                                        // Check if tp.config.php file is still present
                                        if (file_exists(__DIR__.'/../includes/config/tp.config.php') === true) {
                                            echo '<div class="mt-3 alert alert-warning" role="alert"><i class="fa-solid fa-circle-exclamation mr-2"></i>File tp.config.php requires to be deleted. Please do it and refresh this page. This warning shall not be visible anymore.</div>';
                                        }
                                        ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions Row -->
                         <!--
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="card card-info">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-bolt"></i> 
                                            <?php echo $lang->get('admin_quick_actions'); ?>
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <button type="button" class="btn btn-primary btn-block mb-2" id="btn-reload-cache">
                                            <i class="fas fa-sync"></i> <?php echo $lang->get('admin_action_reload_cache'); ?>
                                        </button>
                                        <button type="button" class="btn btn-warning btn-block mb-2" id="btn-clean-logs">
                                            <i class="fas fa-broom"></i> <?php echo $lang->get('admin_action_clean_logs'); ?>
                                        </button>
                                        <button type="button" class="btn btn-info btn-block mb-2" id="btn-export-stats">
                                            <i class="fas fa-download"></i> <?php echo $lang->get('admin_action_export_stats'); ?>
                                        </button>
                                        <hr>
                                        <a href="index.php?page=users&action=new" class="btn btn-secondary btn-block mb-2">
                                            <i class="fas fa-user-plus"></i> <?php echo $lang->get('admin_action_create_user'); ?>
                                        </a>
                                        <a href="index.php?page=folders&action=new" class="btn btn-secondary btn-block">
                                            <i class="fas fa-folder-plus"></i> <?php echo $lang->get('admin_action_create_folder'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        -->
                        
                        <!-- Sponsoring Row -->
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="card card-outline card-primary">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-heart text-danger"></i> 
                                            <?php echo $lang->get('admin_support_teampass'); ?>
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <p class="mb-2">
                                                    <strong><?php echo $lang->get('admin_support_message'); ?></strong>
                                                </p>
                                                <p class="text-muted mb-3">
                                                    <?php echo $lang->get('admin_support_description'); ?>
                                                </p>
                                            </div>
                                            <div class="col-md-4 text-center">
                                                <a href="https://github.com/sponsors/nilsteampassnet" target="_blank" class="btn btn-danger btn-lg mb-2">
                                                    <i class="fab fa-github"></i> <?php echo $lang->get('admin_support_github_sponsors'); ?>
                                                </a>
                                                <br>
                                                <a href="https://www.paypal.com/donate/?hosted_button_id=XUVWYJ7J92X6L" target="_blank" class="btn btn-primary btn-lg">
                                                    <i class="fab fa-paypal"></i> <?php echo $lang->get('admin_support_paypal'); ?>
                                                </a>
                                                <p class="text-muted mt-3 small">
                                                    <i class="fas fa-users"></i> <?php echo $lang->get('admin_support_thank_you'); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- SYSTEM TAB -->
                    <!-- ============================================ -->
                    <div class="tab-pane fade" id="tab-system" role="tabpanel" aria-labelledby="tab-system-link">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> <?php echo $lang->get('admin_system_tab_info'); ?>
                        </div>
                        <!-- System settings will be loaded here via JavaScript -->
                        <div id="system-settings-container">
                            <div class="text-center">
                                <i class="fas fa-sync fa-spin fa-3x text-muted"></i>
                                <p class="mt-3"><?php echo $lang->get('admin_loading'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- USERS TAB -->
                    <!-- ============================================ -->
                    <div class="tab-pane fade" id="tab-users" role="tabpanel" aria-labelledby="tab-users-link">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> <?php echo $lang->get('admin_users_tab_info'); ?>
                        </div>
                        <!-- Users content will be loaded here -->
                        <div id="users-content-container">
                            <div class="text-center">
                                <i class="fas fa-sync fa-spin fa-3x text-muted"></i>
                                <p class="mt-3"><?php echo $lang->get('admin_loading'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- FOLDERS TAB -->
                    <!-- ============================================ -->
                    <div class="tab-pane fade" id="tab-folders" role="tabpanel" aria-labelledby="tab-folders-link">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> <?php echo $lang->get('admin_folders_tab_info'); ?>
                        </div>
                        <!-- Folders statistics and shortcuts -->
                        <div id="folders-content-container">
                            <div class="text-center">
                                <i class="fas fa-sync fa-spin fa-3x text-muted"></i>
                                <p class="mt-3"><?php echo $lang->get('admin_loading'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- SECURITY TAB -->
                    <!-- ============================================ -->
                    <div class="tab-pane fade" id="tab-security" role="tabpanel" aria-labelledby="tab-security-link">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo $lang->get('admin_security_tab_info'); ?>
                        </div>
                        <!-- Security dashboard will be loaded here -->
                        <div id="security-content-container">
                            <div class="text-center">
                                <i class="fas fa-sync fa-spin fa-3x text-muted"></i>
                                <p class="mt-3"><?php echo $lang->get('admin_loading'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- DATABASE TAB -->
                    <!-- ============================================ -->
                    <div class="tab-pane fade" id="tab-database" role="tabpanel" aria-labelledby="tab-database-link">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> <?php echo $lang->get('admin_database_tab_info'); ?>
                        </div>
                        <!-- Database stats and tools will be loaded here -->
                        <div id="database-content-container">
                            <div class="text-center">
                                <i class="fas fa-sync fa-spin fa-3x text-muted"></i>
                                <p class="mt-3"><?php echo $lang->get('admin_loading'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- TOOLS TAB -->
                    <!-- ============================================ -->
                    <div class="tab-pane fade" id="tab-tools" role="tabpanel" aria-labelledby="tab-tools-link">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> <?php echo $lang->get('admin_tools_tab_info'); ?>
                        </div>
                        <!-- Admin tools will be loaded here -->
                        <div id="tools-content-container">
                            <div class="text-center">
                                <i class="fas fa-sync fa-spin fa-3x text-muted"></i>
                                <p class="mt-3"><?php echo $lang->get('admin_loading'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
        
    </div>
</section>
