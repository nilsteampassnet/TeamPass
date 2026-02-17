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
    include $SETTINGS['cpassman_dir'] . '/error.php';
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
                                <div class="small-box bg-warning">
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
                                <div class="small-box bg-danger">
                                    <div class="inner">
                                        <h3 id="health-unknown-files-count">0</h3>
                                        <p><?php echo $lang->get('health_unknown_files'); ?></p>
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

                                        <div class="mb-0">
                                            <div class="d-flex justify-content-between">
                                                <span><?php echo $lang->get('health_migration_personal_items'); ?></span>
                                                <span class="text-muted" id="health-migration-personal-text">-</span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar" role="progressbar" id="health-migration-personal-bar" style="width:0%"></div>
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
                                                    <span class="info-box-icon bg-success"><i class="fas fa-archive"></i></span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text"><?php echo $lang->get('health_backup_status'); ?></span>
                                                        <span class="info-box-number" id="health-backup-status">-</span>
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

                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped">
                                                <thead>
                                                <tr>
                                                    <th><?php echo $lang->get('health_backup_metric'); ?></th>
                                                    <th><?php echo $lang->get('health_backup_scheduled'); ?></th>
                                                    <th><?php echo $lang->get('health_backup_onthefly'); ?></th>
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

    </section>
