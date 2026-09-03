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
 * @file      utilities.health.php
 * @author    Teampass Community
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
require_once __DIR__ . '/../sources/main.functions.php';

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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('utilities.health') === false) {
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

?>

    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-8">
                    <h1>
                        <i class="fas fa-heartbeat mr-2"></i><?php echo $lang->get('system_health'); ?>
                        <small class="text-muted ml-2" id="health-generated-at"></small>
                    </h1>
                </div>
                <div class="col-sm-4">
                    <div class="float-sm-right">
                        <button type="button" class="btn btn-primary btn-sm" id="health-refresh-btn">
                            <i class="fas fa-sync-alt mr-1"></i><?php echo $lang->get('health_refresh'); ?>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="health-export-btn" disabled>
                            <i class="fas fa-file-export mr-1"></i><?php echo $lang->get('health_export'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="row" id="health-loading-row" style="display:none;">
                <div class="col-12">
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-spinner fa-spin mr-2"></i><?php echo $lang->get('loading'); ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="content">

        <div class="card card-outline card-primary">
            <div class="card-header p-0 border-bottom-0">
                <ul class="nav nav-tabs" id="health-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="tab-health-overview" data-toggle="pill" href="#health-overview" role="tab" aria-controls="health-overview" aria-selected="true">
                            <i class="fas fa-tachometer-alt mr-1"></i><?php echo $lang->get('health_overview'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-health-system" data-toggle="pill" href="#health-system" role="tab" aria-controls="health-system" aria-selected="false">
                            <i class="fas fa-server mr-1"></i><?php echo $lang->get('health_system'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-health-file-integrity" data-toggle="pill" href="#health-file-integrity" role="tab" aria-controls="health-file-integrity" aria-selected="false">
                            <i class="fas fa-file-shield mr-1"></i><?php echo $lang->get('health_file_integrity'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-health-database" data-toggle="pill" href="#health-database" role="tab" aria-controls="health-database" aria-selected="false">
                            <i class="fas fa-database mr-1"></i><?php echo $lang->get('health_database'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-health-crypto" data-toggle="pill" href="#health-crypto" role="tab" aria-controls="health-crypto" aria-selected="false">
                            <i class="fas fa-key mr-1"></i><?php echo $lang->get('health_crypto_migration'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-health-backups" data-toggle="pill" href="#health-backups" role="tab" aria-controls="health-backups" aria-selected="false">
                            <i class="fas fa-archive mr-1"></i><?php echo $lang->get('health_backups'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-health-lapr" data-toggle="pill" href="#health-lapr" role="tab" aria-controls="health-lapr" aria-selected="false">
                            <i class="fas fa-sync-alt mr-1"></i><?php echo $lang->get('lapr_monitor_tab'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-health-logs" data-toggle="pill" href="#health-logs" role="tab" aria-controls="health-logs" aria-selected="false">
                            <i class="fas fa-file-alt mr-1"></i><?php echo $lang->get('health_logs'); ?>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <div class="tab-content" id="health-tabs-content">

                    <!-- OVERVIEW -->
                    <div class="tab-pane fade show active" id="health-overview" role="tabpanel" aria-labelledby="tab-health-overview">
                        <div class="row">
                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-info">
                                    <div class="inner">
                                        <h3 id="health-encryption-status">-</h3>
                                        <p><?php echo $lang->get('health_encryption_status'); ?></p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-lock"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-success">
                                    <div class="inner">
                                        <h3 id="health-sessions-count">0</h3>
                                        <p><?php echo $lang->get('health_active_sessions'); ?></p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-dark">
                                    <div class="inner">
                                        <h3 id="health-cron-status">-</h3>
                                        <p><?php echo $lang->get('health_cron_status'); ?></p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-secondary" id="health-file-integrity-overview-box">
                                    <div class="inner">
                                        <h3 id="health-file-integrity-overview-status">-</h3>
                                        <p><?php echo $lang->get('health_file_integrity'); ?></p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-6 col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-chart-line mr-2"></i><?php echo $lang->get('health_migration_progress'); ?></h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between">
                                                <span><?php echo $lang->get('health_migration_users'); ?></span>
                                                <span class="text-muted" id="health-migration-users-text">-</span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar" role="progressbar" id="health-migration-users-bar" style="width:0%"></div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between">
                                                <span><?php echo $lang->get('health_migration_sharekeys_items'); ?></span>
                                                <span class="text-muted" id="health-migration-sharekeys-text">-</span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar" role="progressbar" id="health-migration-sharekeys-bar" style="width:0%"></div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between">
                                                <span><?php echo $lang->get('health_migration_personal_items'); ?></span>
                                                <span class="text-muted" id="health-migration-personal-text">-</span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar" role="progressbar" id="health-migration-personal-bar" style="width:0%"></div>
                                            </div>
                                        </div>

                                        <div class="mb-0">
                                            <div class="d-flex justify-content-between">
                                                <span><?php echo $lang->get('health_aes_v2_migration'); ?></span>
                                                <span class="text-muted" id="health-migration-aes-v2-text">-</span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar bg-success" role="progressbar" id="health-migration-aes-v2-bar" style="width:0%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6 col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-flag-checkered mr-2"></i><?php echo $lang->get('health_findings'); ?></h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-warning"><i class="fas fa-unlink"></i></span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text"><?php echo $lang->get('health_sharekeys_orphans'); ?></span>
                                                        <span class="info-box-number" id="health-orphans-total">0</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-danger"><i class="fas fa-shield-alt"></i></span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text"><?php echo $lang->get('health_hash_integrity'); ?></span>
                                                        <span class="info-box-number" id="health-integrity-issues">0</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-sm-6">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-info"><i class="fas fa-users-cog"></i></span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text"><?php echo $lang->get('health_migration_inconsistent_users'); ?></span>
                                                        <span class="info-box-number" id="health-inconsistent-users">0</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-danger"><i class="fas fa-bug"></i></span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text"><?php echo $lang->get('health_corrupted_items'); ?></span>
                                                        <span class="info-box-number" id="health-overview-corrupted-items">0</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-sm-6">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-success"><i class="fas fa-archive"></i></span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text"><?php echo $lang->get('health_backup_status'); ?></span>
                                                        <span class="info-box-number" id="health-backup-status">-</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-info"><i class="fas fa-sync-alt"></i></span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text"><?php echo $lang->get('lapr_monitor_overall'); ?></span>
                                                        <span class="info-box-number" id="health-lapr-overview-status">-</span>
                                                        <span class="small text-muted" id="health-lapr-overview-detail"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="small text-muted" id="health-findings-details"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SYSTEM -->
                    <div class="tab-pane fade" id="health-system" role="tabpanel" aria-labelledby="tab-health-system">
                        <div class="row">
                            <div class="col-lg-4 col-12">
                                <div class="small-box bg-info">
                                    <div class="inner">
                                        <h3 id="health-cpu-load">-</h3>
                                        <p><?php echo $lang->get('health_cpu_load'); ?></p>
                                        <div class="small text-white-50" id="health-cpu-cores">-</div>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-microchip"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4 col-12">
                                <div class="small-box bg-success">
                                    <div class="inner">
                                        <h3 id="health-mem-usage">-</h3>
                                        <p><?php echo $lang->get('health_memory'); ?></p>
                                        <div class="progress">
                                            <div class="progress-bar" id="health-mem-bar" style="width:0%"></div>
                                        </div>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-memory"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4 col-12">
                                <div class="small-box bg-warning">
                                    <div class="inner">
                                        <h3 id="health-disk-summary">-</h3>
                                        <p><?php echo $lang->get('health_disk'); ?></p>
                                        <div class="small text-white-50" id="health-disk-details">-</div>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-hdd"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fab fa-php mr-2"></i><?php echo $lang->get('health_php_configuration'); ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                        <tr>
                                            <th><?php echo $lang->get('health_setting'); ?></th>
                                            <th><?php echo $lang->get('health_value'); ?></th>
                                        </tr>
                                        </thead>
                                        <tbody id="health-php-ini"></tbody>
                                    </table>
                                </div>

                                <div class="mt-3">
                                    <h5><i class="fas fa-bolt mr-2"></i><?php echo $lang->get('health_opcache'); ?></h5>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped">
                                            <thead>
                                            <tr>
                                                <th><?php echo $lang->get('health_setting'); ?></th>
                                                <th><?php echo $lang->get('health_value'); ?></th>
                                            </tr>
                                            </thead>
                                            <tbody id="health-opcache"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <h5><i class="fas fa-sliders-h mr-2"></i><?php echo $lang->get('health_teampass_settings'); ?></h5>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped">
                                            <thead>
                                            <tr>
                                                <th><?php echo $lang->get('health_setting'); ?></th>
                                                <th><?php echo $lang->get('health_value'); ?></th>
                                            </tr>
                                            </thead>
                                            <tbody id="health-teampass-settings"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <h5><i class="fas fa-check-circle mr-2"></i><?php echo $lang->get('health_checks'); ?></h5>
                                    <div id="health-system-checks"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FILE INTEGRITY -->
                    <div class="tab-pane fade" id="health-file-integrity" role="tabpanel" aria-labelledby="tab-health-file-integrity">
                        <div class="card card-outline card-primary">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-file-shield mr-2"></i><?php echo $lang->get('health_file_integrity'); ?>
                                </h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-primary btn-sm" id="health-file-integrity-scan-btn">
                                        <i class="fas fa-search mr-1"></i><?php echo $lang->get('health_file_integrity_scan'); ?>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="text-muted"><?php echo $lang->get('health_file_integrity_intro'); ?></p>
                                <div class="alert alert-info" id="health-file-integrity-running" style="display:none;">
                                    <i class="fas fa-spinner fa-spin mr-2"></i><?php echo $lang->get('health_file_integrity_running'); ?>
                                </div>
                                <div class="alert alert-danger" id="health-file-integrity-error" style="display:none;"></div>

                                <div class="row mb-3">
                                    <div class="col-md-4 col-12">
                                        <strong><?php echo $lang->get('health_status'); ?>:</strong>
                                        <span id="health-file-integrity-status" class="ml-1">-</span>
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <strong><?php echo $lang->get('health_file_integrity_last_scan'); ?>:</strong>
                                        <span id="health-file-integrity-last-scan" class="ml-1">-</span>
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <strong><?php echo $lang->get('health_file_integrity_duration'); ?>:</strong>
                                        <span id="health-file-integrity-duration" class="ml-1">-</span>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-6 col-12">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-info"><i class="fas fa-check"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text"><?php echo $lang->get('health_file_integrity_checked'); ?></span>
                                                <span class="info-box-number" id="health-file-integrity-checked">0</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 col-12">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-danger"><i class="fas fa-pen"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text"><?php echo $lang->get('health_file_integrity_modified'); ?></span>
                                                <span class="info-box-number" id="health-file-integrity-modified">0</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 col-12">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-danger"><i class="fas fa-file-circle-xmark"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text"><?php echo $lang->get('health_file_integrity_missing'); ?></span>
                                                <span class="info-box-number" id="health-file-integrity-missing">0</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 col-12">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-warning"><i class="fas fa-question"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text"><?php echo $lang->get('health_file_integrity_unknown'); ?></span>
                                                <span class="info-box-number" id="health-file-integrity-unknown">0</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-4 col-md-6 col-12">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-warning"><i class="fas fa-box-archive"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text"><?php echo $lang->get('health_file_integrity_legacy'); ?></span>
                                                <span class="info-box-number" id="health-file-integrity-legacy">0</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 col-md-6 col-12">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-secondary"><i class="fas fa-code"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text"><?php echo $lang->get('health_file_integrity_development'); ?></span>
                                                <span class="info-box-number" id="health-file-integrity-development">0</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 col-md-6 col-12">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-warning"><i class="fas fa-triangle-exclamation"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text"><?php echo $lang->get('health_file_integrity_warnings'); ?></span>
                                                <span class="info-box-number" id="health-file-integrity-warnings">0</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card card-outline card-info mt-2">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-user-shield mr-2"></i><?php echo $lang->get('health_file_permissions'); ?>
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted"><?php echo $lang->get('health_file_permissions_intro'); ?></p>
                                        <div class="alert alert-info" id="health-file-permissions-notice" style="display:none;"></div>
                                        <div class="row">
                                            <div class="col-lg-3 col-md-6 col-12">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-info"><i class="fas fa-list-check"></i></span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text"><?php echo $lang->get('health_file_permissions_checked'); ?></span>
                                                        <span class="info-box-number" id="health-file-permissions-checked">0</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-6 col-12">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-warning"><i class="fas fa-shield-halved"></i></span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text"><?php echo $lang->get('health_file_permissions_issues'); ?></span>
                                                        <span class="info-box-number" id="health-file-permissions-issues">0</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-6 col-12">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-secondary"><i class="fab fa-linux"></i></span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text"><?php echo $lang->get('health_file_permissions_distribution'); ?></span>
                                                        <span class="info-box-number text-sm" id="health-file-permissions-distribution">-</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-6 col-12">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-secondary"><i class="fas fa-user-gear"></i></span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text"><?php echo $lang->get('health_file_permissions_web_user'); ?></span>
                                                        <span class="info-box-number text-sm" id="health-file-permissions-web-user">-</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div id="health-file-integrity-results" style="display:none;">
                                    <div class="form-group row align-items-center">
                                        <label for="health-file-integrity-category" class="col-sm-auto col-form-label">
                                            <?php echo $lang->get('health_file_integrity_category'); ?>
                                        </label>
                                        <div class="col-sm-4">
                                            <select class="form-control form-control-sm" id="health-file-integrity-category">
                                                <option value="all"><?php echo $lang->get('health_file_integrity_all_issues'); ?></option>
                                                <option value="modified"><?php echo $lang->get('health_file_integrity_modified'); ?></option>
                                                <option value="missing"><?php echo $lang->get('health_file_integrity_missing'); ?></option>
                                                <option value="unknown"><?php echo $lang->get('health_file_integrity_unknown'); ?></option>
                                                <option value="legacy"><?php echo $lang->get('health_file_integrity_legacy'); ?></option>
                                                <option value="development"><?php echo $lang->get('health_file_integrity_development'); ?></option>
                                                <option value="warnings"><?php echo $lang->get('health_file_integrity_warnings'); ?></option>
                                                <option value="permissions"><?php echo $lang->get('health_file_permissions'); ?></option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped">
                                            <thead>
                                            <tr>
                                                <th><?php echo $lang->get('health_file_integrity_category'); ?></th>
                                                <th><?php echo $lang->get('health_file_integrity_path'); ?></th>
                                                <th><?php echo $lang->get('health_file_integrity_details'); ?></th>
                                            </tr>
                                            </thead>
                                            <tbody id="health-file-integrity-list"></tbody>
                                        </table>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <button type="button" class="btn btn-default btn-sm" id="health-file-integrity-prev">
                                            <i class="fas fa-chevron-left mr-1"></i><?php echo $lang->get('health_file_integrity_previous'); ?>
                                        </button>
                                        <span class="small text-muted" id="health-file-integrity-page-info"></span>
                                        <button type="button" class="btn btn-default btn-sm" id="health-file-integrity-next">
                                            <?php echo $lang->get('health_file_integrity_next'); ?><i class="fas fa-chevron-right ml-1"></i>
                                        </button>
                                    </div>

                                    <div class="card card-outline card-secondary">
                                        <div class="card-header">
                                            <h3 class="card-title"><i class="fas fa-terminal mr-2"></i><?php echo $lang->get('health_file_integrity_cleanup'); ?></h3>
                                            <div class="card-tools">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" id="health-file-integrity-cleanup-copy-btn" disabled>
                                                    <i class="far fa-copy mr-1"></i><?php echo $lang->get('health_copy_ssh_commands'); ?>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <p class="text-muted"><?php echo $lang->get('health_file_integrity_cleanup_help'); ?></p>
                                            <pre class="mb-0"><code id="health-file-integrity-cleanup-command"></code></pre>
                                        </div>
                                    </div>

                                    <div class="card card-outline card-info">
                                        <div class="card-header">
                                            <h3 class="card-title"><i class="fas fa-terminal mr-2"></i><?php echo $lang->get('health_file_permissions_remediation'); ?></h3>
                                            <div class="card-tools">
                                                <button type="button" class="btn btn-outline-info btn-sm" id="health-file-permissions-copy-btn" disabled>
                                                    <i class="far fa-copy mr-1"></i><?php echo $lang->get('health_copy_ssh_commands'); ?>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <p class="text-muted"><?php echo $lang->get('health_file_permissions_remediation_help'); ?></p>
                                            <pre class="mb-0"><code id="health-file-permissions-command"></code></pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DATABASE -->
                    <div class="tab-pane fade" id="health-database" role="tabpanel" aria-labelledby="tab-health-database">
                        <div class="row">
                            <div class="col-md-4 col-12">
                                <div class="small-box bg-info">
                                    <div class="inner">
                                        <h3 id="health-db-version">-</h3>
                                        <p><?php echo $lang->get('health_database_version'); ?></p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-database"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4 col-12">
                                <div class="small-box bg-success">
                                    <div class="inner">
                                        <h3 id="health-db-latency">-</h3>
                                        <p><?php echo $lang->get('health_database_latency'); ?></p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-tachometer-alt"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4 col-12">
                                <div class="small-box bg-warning">
                                    <div class="inner">
                                        <h3 id="health-db-size">-</h3>
                                        <p><?php echo $lang->get('health_database_size'); ?></p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-weight-hanging"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-table mr-2"></i><?php echo $lang->get('health_database_tables_sizes'); ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                        <tr>
                                            <th><?php echo $lang->get('health_table'); ?></th>
                                            <th><?php echo $lang->get('health_rows'); ?></th>
                                            <th><?php echo $lang->get('health_size'); ?></th>
                                            <th><?php echo $lang->get('health_free'); ?></th>
                                            <th><?php echo $lang->get('health_engine'); ?></th>
                                            <th><?php echo $lang->get('health_status'); ?></th>
                                        </tr>
                                        </thead>
                                        <tbody id="health-db-tables"></tbody>
                                    </table>
                                </div>
                                <div class="small text-muted mt-2" id="health-db-note"></div>
                            </div>
                        </div>
                    </div>

                    <!-- CRYPTO -->
                    <div class="tab-pane fade" id="health-crypto" role="tabpanel" aria-labelledby="tab-health-crypto">
                        <div class="row">
                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-info">
                                    <div class="inner">
                                        <h3 id="health-crypto-orphans">0</h3>
                                        <p><?php echo $lang->get('health_sharekeys_orphans'); ?></p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-unlink"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-info">
                                    <div class="inner">
                                        <h3 id="health-crypto-integrity-issues">0</h3>
                                        <p><?php echo $lang->get('health_hash_integrity'); ?></p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-shield-alt"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-info">
                                    <div class="inner">
                                        <h3 id="health-crypto-inconsistent-users">0</h3>
                                        <p><?php echo $lang->get('health_migration_inconsistent_users'); ?></p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-user-shield"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-info">
                                    <div class="inner">
                                        <h3 id="health-crypto-users-migration">0%</h3>
                                        <p><?php echo $lang->get('health_migration_progress'); ?></p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-percentage"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-lock mr-2"></i><?php echo $lang->get('health_aes_v2_title'); ?></h3>
                                <div class="card-tools" id="health-aes-v2-write-status">-</div>
                            </div>
                            <div class="card-body">
                                <p class="text-muted"><?php echo $lang->get('health_aes_v2_help'); ?></p>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <strong><?php echo $lang->get('health_aes_v2_overall'); ?></strong>
                                        <span id="health-aes-v2-overall-text">-</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" role="progressbar" id="health-aes-v2-overall-bar" style="width:0%"></div>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                        <tr>
                                            <th><?php echo $lang->get('health_store'); ?></th>
                                            <th><?php echo $lang->get('health_migrated'); ?></th>
                                            <th><?php echo $lang->get('health_legacy'); ?></th>
                                            <th><?php echo $lang->get('health_migration_progress'); ?></th>
                                        </tr>
                                        </thead>
                                        <tbody id="health-aes-v2-stores"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-key mr-2"></i><?php echo $lang->get('health_sharekeys'); ?></h3>
                            </div>
                            <div class="card-body">
                                <h5><?php echo $lang->get('health_sharekeys_distribution'); ?></h5>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                        <tr>
                                            <th><?php echo $lang->get('health_sharekeys_table'); ?></th>
                                            <th><?php echo $lang->get('health_sharekeys_total'); ?></th>
                                            <th><?php echo $lang->get('health_sharekeys_v1'); ?></th>
                                            <th><?php echo $lang->get('health_sharekeys_v3'); ?></th>
                                            <th><?php echo $lang->get('health_sharekeys_null'); ?></th>
                                        </tr>
                                        </thead>
                                        <tbody id="health-sharekeys-stats"></tbody>
                                    </table>
                                </div>

                                <h5 class="mt-4"><?php echo $lang->get('health_sharekeys_items_personal_shared'); ?></h5>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                        <tr>
                                            <th><?php echo $lang->get('health_items_scope'); ?></th>
                                            <th><?php echo $lang->get('health_sharekeys_v1'); ?></th>
                                            <th><?php echo $lang->get('health_sharekeys_v3'); ?></th>
                                            <th><?php echo $lang->get('health_sharekeys_total'); ?></th>
                                        </tr>
                                        </thead>
                                        <tbody id="health-sharekeys-items-perso"></tbody>
                                    </table>
                                </div>

                                <h5 class="mt-4"><?php echo $lang->get('health_sharekeys_orphans'); ?></h5>
                                <div id="health-sharekeys-orphans"></div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-shield-alt mr-2"></i><?php echo $lang->get('health_hash_integrity'); ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-4 col-12">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-warning"><i class="fas fa-question-circle"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text"><?php echo $lang->get('health_hash_missing'); ?></span>
                                                <span class="info-box-number" id="health-integrity-missing">0</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-danger"><i class="fas fa-times-circle"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text"><?php echo $lang->get('health_hash_mismatch'); ?></span>
                                                <span class="info-box-number" id="health-integrity-mismatch">0</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text"><?php echo $lang->get('health_hash_ok'); ?></span>
                                                <span class="info-box-number" id="health-integrity-ok">0</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                        <tr>
                                            <th><?php echo $lang->get('id'); ?></th>
                                            <th><?php echo $lang->get('login'); ?></th>
                                            <th><?php echo $lang->get('health_reason'); ?></th>
                                        </tr>
                                        </thead>
                                        <tbody id="health-integrity-users"></tbody>
                                    </table>
                                </div>
                                <div class="small text-muted" id="health-integrity-note"></div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-bug mr-2"></i><?php echo $lang->get('health_corrupted_items'); ?></h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-primary btn-sm" id="health-corrupted-items-scan-btn">
                                        <i class="fas fa-search mr-1"></i><?php echo $lang->get('health_corrupted_items_scan'); ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="health-corrupted-items-show-btn" disabled>
                                        <i class="fas fa-list mr-1"></i><?php echo $lang->get('health_corrupted_items_show_list'); ?>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap align-items-center justify-content-between">
                                    <div class="mr-3">
                                        <span class="h4 mb-0" id="health-corrupted-items-count">0</span>
                                        <small class="text-muted ml-2" id="health-corrupted-items-last-scan"></small>
                                    </div>
                                    <div class="text-muted small" id="health-corrupted-items-note">
                                        <?php echo $lang->get('health_corrupted_items_note'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>



                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-exchange-alt mr-2"></i><?php echo $lang->get('health_phpseclib_migration'); ?></h3>
                            </div>
                            <div class="card-body">
                                <h5><?php echo $lang->get('health_migration_inconsistent_users'); ?></h5>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                        <tr>
                                            <th><?php echo $lang->get('id'); ?></th>
                                            <th><?php echo $lang->get('login'); ?></th>
                                            <th><?php echo $lang->get('health_sharekeys_v1'); ?></th>
                                        </tr>
                                        </thead>
                                        <tbody id="health-inconsistent-users-table"></tbody>
                                    </table>
                                </div>

                                <h5 class="mt-4"><?php echo $lang->get('health_sharekeys_user_mismatch'); ?></h5>
                                <div class="row">
                                    <div class="col-lg-6 col-12">
                                        <div class="callout callout-warning">
                                            <h6><?php echo $lang->get('health_v3_users_with_v1_sharekeys'); ?></h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                    <tr>
                                                        <th><?php echo $lang->get('login'); ?></th>
                                                        <th><?php echo $lang->get('health_sharekeys'); ?></th>
                                                    </tr>
                                                    </thead>
                                                    <tbody id="health-v3-users-v1"></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 col-12">
                                        <div class="callout callout-info">
                                            <h6><?php echo $lang->get('health_v1_users_with_v3_sharekeys'); ?></h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                    <tr>
                                                        <th><?php echo $lang->get('login'); ?></th>
                                                        <th><?php echo $lang->get('health_sharekeys'); ?></th>
                                                    </tr>
                                                    </thead>
                                                    <tbody id="health-v1-users-v3"></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="small text-muted" id="health-crypto-note"></div>
                            </div>
                        </div>
                    </div>

                    <!-- BACKUPS -->
                    <div class="tab-pane fade" id="health-backups" role="tabpanel" aria-labelledby="tab-health-backups">

                        <!-- Indicators -->
                        <div class="row">
                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-info">
                                    <div class="inner">
                                        <h3 id="health-backups-scheduled-compatible">0/0</h3>
                                        <p><?php echo $lang->get('health_backup_scheduled_compatible'); ?></p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-info">
                                    <div class="inner">
                                        <h3 id="health-backups-onthefly-compatible">0/0</h3>
                                        <p><?php echo $lang->get('health_backup_onthefly_compatible'); ?></p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-bolt"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-info">
                                    <div class="inner">
                                        <h3 id="health-backups-externalized-compatible">0/0</h3>
                                        <p><?php echo $lang->get('health_backup_externalized_compatible'); ?></p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-upload"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-info">
                                    <div class="inner">
                                        <h3 id="health-backups-last-job">-</h3>
                                        <p><?php echo $lang->get('health_backup_last_job'); ?></p>
                                        <div class="small text-white-50" id="health-backups-last-job-at"></div>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-tasks"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-info">
                                    <div class="inner">
                                        <h3 id="health-backups-anomalies">0</h3>
                                        <p><?php echo $lang->get('health_backup_anomalies'); ?></p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-6 col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-folder-open mr-2"></i><?php echo $lang->get('health_backup_directories'); ?></h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <span class="text-muted"><?php echo $lang->get('health_backup_scheduled'); ?>:</span>
                                            <code id="health-backup-scheduled-path">-</code>
                                        </div>
                                        <div class="mb-3">
                                            <span class="text-muted"><?php echo $lang->get('health_backup_onthefly'); ?>:</span>
                                            <code id="health-backup-onthefly-path">-</code>
                                        </div>
                                        <div class="mb-3">
                                            <span class="text-muted"><?php echo $lang->get('health_backup_externalized'); ?>:</span>
                                            <code id="health-backup-externalized-path">-</code>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped">
                                                <thead>
                                                <tr>
                                                    <th><?php echo $lang->get('health_backup_metric'); ?></th>
                                                    <th><?php echo $lang->get('health_backup_scheduled'); ?></th>
                                                    <th><?php echo $lang->get('health_backup_onthefly'); ?></th>
                                                    <th><?php echo $lang->get('health_backup_externalized'); ?></th>
                                                </tr>
                                                </thead>
                                                <tbody id="health-backups-dirs-summary"></tbody>
                                            </table>
                                        </div>

                                        <div class="small text-muted" id="health-backups-dirs-note"></div>
                                        <div class="small" id="health-backups-dirs-status"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6 col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-clock mr-2"></i><?php echo $lang->get('health_backup_scheduler'); ?></h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <span class="text-muted"><?php echo $lang->get('health_backup_path'); ?>:</span>
                                            <code id="health-backups-scheduler-output-dir">-</code>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <tbody>
                                                <tr>
                                                    <td class="text-muted"><?php echo $lang->get('health_backup_next_run'); ?></td>
                                                    <td id="health-backups-scheduler-next-run">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted"><?php echo $lang->get('health_backup_last_run'); ?></td>
                                                    <td id="health-backups-scheduler-last-run">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted"><?php echo $lang->get('health_backup_last_status'); ?></td>
                                                    <td id="health-backups-scheduler-last-status">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted"><?php echo $lang->get('health_backup_last_message'); ?></td>
                                                    <td id="health-backups-scheduler-last-message">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted"><?php echo $lang->get('health_backup_last_completed'); ?></td>
                                                    <td id="health-backups-scheduler-last-completed">-</td>
                                                </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="small text-muted" id="health-backups-scheduler-note"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-archive mr-2"></i><?php echo $lang->get('health_backup_latest_dumps'); ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                        <tr>
                                            <th><?php echo $lang->get('health_backup_type'); ?></th>
                                            <th><?php echo $lang->get('health_backup_last_backup'); ?></th>
                                            <th><?php echo $lang->get('health_backup_last_compatible_backup'); ?></th>
                                            <th><?php echo $lang->get('health_backup_compatibility'); ?></th>
                                        </tr>
                                        </thead>
                                        <tbody id="health-backups-key-files"></tbody>
                                    </table>
                                </div>

                                <div class="small text-muted" id="health-backups-key-files-note"></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-history mr-2"></i><?php echo $lang->get('health_backup_history'); ?></h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped">
                                                <thead>
                                                <tr>
                                                    <th><?php echo $lang->get('health_created'); ?></th>
                                                    <th><?php echo $lang->get('health_backup_type'); ?></th>
                                                    <th><?php echo $lang->get('health_backup_file'); ?></th>
                                                    <th><?php echo $lang->get('health_size'); ?></th>
                                                    <th><?php echo $lang->get('health_backup_compatibility'); ?></th>
                                                    <th><?php echo $lang->get('health_backup_comment'); ?></th>
                                                </tr>
                                                </thead>
                                                <tbody id="health-backups-history"></tbody>
                                            </table>
                                        </div>

                                        <div class="small text-muted" id="health-backups-history-note"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- LAPR -->
                    <div class="tab-pane fade" id="health-lapr" role="tabpanel" aria-labelledby="tab-health-lapr">
                        <div class="alert alert-info" id="health-lapr-availability" style="display:none;"></div>

                        <div class="row">
                            <div class="col-xl col-md-6">
                                <div class="small-box bg-info">
                                    <div class="inner">
                                        <h3 id="health-lapr-status">-</h3>
                                        <p><?php echo $lang->get('lapr_monitor_overall'); ?></p>
                                    </div>
                                    <div class="icon"><i class="fas fa-heartbeat"></i></div>
                                </div>
                            </div>
                            <div class="col-xl col-md-6">
                                <div class="small-box bg-primary">
                                    <div class="inner">
                                        <h3 id="health-lapr-endpoints">0</h3>
                                        <p><?php echo $lang->get('lapr_monitor_endpoints'); ?></p>
                                    </div>
                                    <div class="icon"><i class="fas fa-server"></i></div>
                                </div>
                            </div>
                            <div class="col-xl col-md-6">
                                <div class="small-box bg-success">
                                    <div class="inner">
                                        <h3 id="health-lapr-accounts">0</h3>
                                        <p><?php echo $lang->get('lapr_monitor_accounts_compliant'); ?></p>
                                    </div>
                                    <div class="icon"><i class="fas fa-user-check"></i></div>
                                </div>
                            </div>
                            <div class="col-xl col-md-6">
                                <div class="small-box bg-secondary">
                                    <div class="inner">
                                        <h3 id="health-lapr-scheduler">-</h3>
                                        <p><?php echo $lang->get('lapr_monitor_scheduler'); ?></p>
                                    </div>
                                    <div class="icon"><i class="fas fa-clock"></i></div>
                                </div>
                            </div>
                            <div class="col-xl col-md-6">
                                <div class="small-box bg-warning">
                                    <div class="inner">
                                        <h3 id="health-lapr-operators">0</h3>
                                        <p><?php echo $lang->get('lapr_monitor_active_operators'); ?></p>
                                    </div>
                                    <div class="icon"><i class="fas fa-user-shield"></i></div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-warning py-2" id="health-lapr-disabled-grants" style="display:none;"></div>

                        <div class="row">
                            <div class="col-lg-6">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-tasks mr-2"></i><?php echo $lang->get('lapr_monitor_account_states'); ?></h3>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <tbody id="health-lapr-account-states"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-cogs mr-2"></i><?php echo $lang->get('lapr_monitor_scheduler_details'); ?></h3>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <tbody id="health-lapr-scheduler-details"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-exclamation-triangle mr-2"></i><?php echo $lang->get('lapr_monitor_action_items'); ?></h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th><?php echo $lang->get('lapr_monitor_severity'); ?></th>
                                                <th><?php echo $lang->get('lapr_endpoint'); ?></th>
                                                <th><?php echo $lang->get('lapr_username'); ?></th>
                                                <th><?php echo $lang->get('lapr_monitor_check'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="health-lapr-action-items"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-history mr-2"></i><?php echo $lang->get('lapr_monitor_recent_failures'); ?></h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th><?php echo $lang->get('date'); ?></th>
                                                <th><?php echo $lang->get('lapr_endpoint'); ?></th>
                                                <th><?php echo $lang->get('lapr_username'); ?></th>
                                                <th><?php echo $lang->get('lapr_monitor_failure_cause'); ?></th>
                                                <th><?php echo $lang->get('lapr_trigger'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="health-lapr-recent-failures"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- LOGS -->
                    <div class="tab-pane fade" id="health-logs" role="tabpanel" aria-labelledby="tab-health-logs">
                        <div class="row">
                            <div class="col-lg-4 col-12">
                                <div class="small-box bg-info">
                                    <div class="inner">
                                        <h3 id="health-total-logs">0</h3>
                                        <p><?php echo $lang->get('health_total_logs'); ?></p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4 col-12">
                                <div class="small-box bg-danger">
                                    <div class="inner">
                                        <h3 id="health-error-logs">0</h3>
                                        <p><?php echo $lang->get('health_error_logs'); ?></p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-exclamation-circle"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4 col-12">
                                <div class="small-box bg-success">
                                    <div class="inner">
                                        <h3 id="health-connection-logs">0</h3>
                                        <p><?php echo $lang->get('health_connection_logs'); ?></p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-sign-in-alt"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-exclamation-triangle mr-2"></i><?php echo $lang->get('health_runtime_logs'); ?></h3>
                                <div class="card-tools">
                                    <div class="input-group input-group-sm" style="width: 260px;">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><?php echo $lang->get('health_runtime_log_lines'); ?></span>
                                        </div>
                                        <select class="custom-select" id="health-runtime-log-lines">
                                            <option value="50" selected>50</option>
                                            <option value="100">100</option>
                                            <option value="200">200</option>
                                            <option value="500">500</option>
                                            <option value="1000">1000</option>
                                        </select>
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-primary" id="health-runtime-logs-check-btn">
                                                <i class="fas fa-search mr-1"></i><?php echo $lang->get('health_runtime_log_check'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="small text-muted mb-3" id="health-runtime-logs-context"></div>

                                <div class="card card-outline card-secondary mb-3">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-server mr-2"></i><?php echo $lang->get('health_server_error_log'); ?></h3>
                                        <div class="card-tools">
                                            <button type="button" class="btn btn-secondary btn-sm" id="health-server-log-copy-btn" disabled>
                                                <i class="fas fa-copy mr-1"></i><?php echo $lang->get('copy'); ?>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-danger" id="health-server-log-error" style="display:none;"></div>
                                        <div class="alert alert-warning" id="health-server-log-fix" style="display:none;">
                                            <div class="mb-2" id="health-server-log-fix-text"></div>
                                            <pre class="mb-0" id="health-server-log-fix-cmd" style="white-space: pre-wrap;"></pre>
                                        </div>
                                        <pre id="health-server-log-content" class="p-2 mb-0" style="display:none; max-height: 380px; overflow:auto; background-color: #f8f9fa; border: 1px solid #dee2e6; white-space: pre-wrap;"></pre>
                                        <div class="small text-muted mt-2" id="health-server-log-meta" style="display:none;"></div>
                                    </div>
                                </div>

                                <div class="card card-outline card-secondary mb-3">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-exchange-alt mr-2"></i><?php echo $lang->get('health_server_access_log'); ?></h3>
                                        <div class="card-tools">
                                            <button type="button" class="btn btn-secondary btn-sm" id="health-server-access-log-copy-btn" disabled>
                                                <i class="fas fa-copy mr-1"></i><?php echo $lang->get('copy'); ?>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-danger" id="health-server-access-log-error" style="display:none;"></div>
                                        <div class="alert alert-warning" id="health-server-access-log-fix" style="display:none;">
                                            <div class="mb-2" id="health-server-access-log-fix-text"></div>
                                            <pre class="mb-0" id="health-server-access-log-fix-cmd" style="white-space: pre-wrap;"></pre>
                                        </div>
                                        <pre id="health-server-access-log-content" class="p-2 mb-0" style="display:none; max-height: 380px; overflow:auto; background-color: #f8f9fa; border: 1px solid #dee2e6; white-space: pre-wrap;"></pre>
                                        <div class="small text-muted mt-2" id="health-server-access-log-meta" style="display:none;"></div>
                                    </div>
                                </div>

                                <div class="card card-outline card-secondary mb-3">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-file-alt mr-2"></i><?php echo $lang->get('health_teampass_error_log'); ?></h3>
                                        <div class="card-tools">
                                            <button type="button" class="btn btn-secondary btn-sm" id="health-teampass-log-copy-btn" disabled>
                                                <i class="fas fa-copy mr-1"></i><?php echo $lang->get('copy'); ?>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-danger" id="health-teampass-log-error" style="display:none;"></div>
                                        <div class="alert alert-warning" id="health-teampass-log-fix" style="display:none;">
                                            <div class="mb-2" id="health-teampass-log-fix-text"></div>
                                            <pre class="mb-0" id="health-teampass-log-fix-cmd" style="white-space: pre-wrap;"></pre>
                                        </div>
                                        <pre id="health-teampass-log-content" class="p-2 mb-0" style="display:none; max-height: 380px; overflow:auto; background-color: #f8f9fa; border: 1px solid #dee2e6; white-space: pre-wrap;"></pre>
                                        <div class="small text-muted mt-2" id="health-teampass-log-meta" style="display:none;"></div>
                                    </div>
                                </div>

                                <div class="card card-outline card-secondary mb-3">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-code-branch mr-2"></i><?php echo $lang->get('health_php_fpm_error_log'); ?></h3>
                                        <div class="card-tools">
                                            <button type="button" class="btn btn-secondary btn-sm" id="health-php-fpm-log-copy-btn" disabled>
                                                <i class="fas fa-copy mr-1"></i><?php echo $lang->get('copy'); ?>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-danger" id="health-php-fpm-log-error" style="display:none;"></div>
                                        <div class="alert alert-warning" id="health-php-fpm-log-fix" style="display:none;">
                                            <div class="mb-2" id="health-php-fpm-log-fix-text"></div>
                                            <pre class="mb-0" id="health-php-fpm-log-fix-cmd" style="white-space: pre-wrap;"></pre>
                                        </div>
                                        <pre id="health-php-fpm-log-content" class="p-2 mb-0" style="display:none; max-height: 380px; overflow:auto; background-color: #f8f9fa; border: 1px solid #dee2e6; white-space: pre-wrap;"></pre>
                                        <div class="small text-muted mt-2" id="health-php-fpm-log-meta" style="display:none;"></div>
                                    </div>
                                </div>

                                <div class="card card-outline card-secondary">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-plug mr-2"></i><?php echo $lang->get('health_websocket_log'); ?></h3>
                                        <div class="card-tools">
                                            <button type="button" class="btn btn-secondary btn-sm" id="health-websocket-log-copy-btn" disabled>
                                                <i class="fas fa-copy mr-1"></i><?php echo $lang->get('copy'); ?>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-danger" id="health-websocket-log-error" style="display:none;"></div>
                                        <div class="alert alert-warning" id="health-websocket-log-fix" style="display:none;">
                                            <div class="mb-2" id="health-websocket-log-fix-text"></div>
                                            <pre class="mb-0" id="health-websocket-log-fix-cmd" style="white-space: pre-wrap;"></pre>
                                        </div>
                                        <pre id="health-websocket-log-content" class="p-2 mb-0" style="display:none; max-height: 380px; overflow:auto; background-color: #f8f9fa; border: 1px solid #dee2e6; white-space: pre-wrap;"></pre>
                                        <div class="small text-muted mt-2" id="health-websocket-log-meta" style="display:none;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>



                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-tags mr-2"></i><?php echo $lang->get('health_top_labels'); ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                        <tr>
                                            <th><?php echo $lang->get('label'); ?></th>
                                            <th><?php echo $lang->get('count'); ?></th>
                                        </tr>
                                        </thead>
                                        <tbody id="health-top-labels"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-user mr-2"></i><?php echo $lang->get('health_top_users'); ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                        <tr>
                                            <th><?php echo $lang->get('user'); ?></th>
                                            <th><?php echo $lang->get('count'); ?></th>
                                        </tr>
                                        </thead>
                                        <tbody id="health-top-users"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-history mr-2"></i><?php echo $lang->get('health_recent_events'); ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                        <tr>
                                            <th><?php echo $lang->get('date'); ?></th>
                                            <th><?php echo $lang->get('type'); ?></th>
                                            <th><?php echo $lang->get('label'); ?></th>
                                            <th><?php echo $lang->get('user'); ?></th>
                                        </tr>
                                        </thead>
                                        <tbody id="health-recent-events"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /.tab-content -->
            </div><!-- /.card-body -->
        </div><!-- /.card -->

    

        <div class="modal fade" id="health-corrupted-items-modal" tabindex="-1" role="dialog" aria-labelledby="healthCorruptedItemsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="healthCorruptedItemsModalLabel"><?php echo $lang->get('health_corrupted_items'); ?></h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo $lang->get('close'); ?>">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="small text-muted mb-2" id="health-corrupted-items-modal-note"></div>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                <tr>
                                    <th><?php echo $lang->get('id'); ?></th>
                                    <th><?php echo $lang->get('label'); ?></th>
                                    <th><?php echo $lang->get('health_reason'); ?></th>
                                    <th><?php echo $lang->get('health_corrupted_len_stored'); ?></th>
                                    <th><?php echo $lang->get('health_corrupted_len_actual'); ?></th>
                                    <th><?php echo $lang->get('health_updated'); ?></th>
                                </tr>
                                </thead>
                                <tbody id="health-corrupted-items-list"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $lang->get('close'); ?></button>
                    </div>
                </div>
            </div>
        </div>

</section>
