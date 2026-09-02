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
 * @file      statistics.php
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
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('statistics') === false) {
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
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
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-chart-bar mr-2"></i><?php echo $lang->get('statistics'); ?></h1>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="./assets/css/statistics.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">

<!-- Main content -->
<div class='content'>
    <div class='container-fluid'>
        <div class='card card-info' id='tp-operational-stats-card'>
            <div class='card-header'>
                <h3 class='card-title'><i class='fas fa-chart-line mr-2'></i><?php echo $lang->get('ops_dashboard_title'); ?></h3>
                <div class='card-tools'>
                    <div class='form-inline tp-ops-toolbar'>
                        <div class='form-group mb-0 mr-3 tp-ops-period-filter'>
                            <label class='sr-only' for='tp-ops-period'><?php echo $lang->get('ops_period'); ?></label>
                            <select class='form-control form-control-sm' id='tp-ops-period'>
                                <option value='24h'><?php echo $lang->get('ops_period_24h'); ?></option>
                                <option value='current_week'><?php echo $lang->get('ops_period_current_week'); ?></option>
                                <option value='current_month'><?php echo $lang->get('ops_period_current_month'); ?></option>
                                <option value='7d'><?php echo $lang->get('ops_period_7d'); ?></option>
                                <option value='30d' selected><?php echo $lang->get('ops_period_30d'); ?></option>
                                <option value='90d'><?php echo $lang->get('ops_period_90d'); ?></option>
                            </select>
                        </div>

                        <div class='form-check mr-3 tp-ops-standard-filter'>
                            <input class='form-check-input flat-blue' type='checkbox' id='tp-ops-include-personal' checked>
                            <label class='form-check-label' for='tp-ops-include-personal'><?php echo $lang->get('ops_include_personal'); ?></label>
                        </div>

                        <div class='form-check mr-3 tp-ops-standard-filter'>
                            <input class='form-check-input flat-blue' type='checkbox' id='tp-ops-include-api' checked>
                            <label class='form-check-label' for='tp-ops-include-api'><?php echo $lang->get('ops_include_api'); ?></label>
                        </div>

                        <button type='button' class='btn btn-sm btn-light' id='tp-ops-refresh'>
                            <i class='fas fa-sync-alt mr-1'></i><?php echo $lang->get('refresh'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class='card-body'>
                <ul class='nav nav-tabs' id='tp-ops-tabs' role='tablist'>
                    <li class='nav-item'>
                        <a class='nav-link active' id='tp-ops-overview-tab' data-toggle='tab' href='#tp-ops-overview' role='tab' aria-controls='tp-ops-overview' aria-selected='true'>
                            <i class='fas fa-tachometer-alt mr-1'></i><?php echo $lang->get('ops_tab_overview'); ?>
                        </a>
                    </li>
                    <li class='nav-item'>
                        <a class='nav-link' id='tp-ops-security-tab' data-toggle='tab' href='#tp-ops-security' role='tab' aria-controls='tp-ops-security' aria-selected='false'>
                            <i class='fas fa-shield-alt mr-1'></i><?php echo $lang->get('ops_tab_security'); ?>
                        </a>
                    </li>
                    <li class='nav-item'>
                        <a class='nav-link' id='tp-ops-activity-tab' data-toggle='tab' href='#tp-ops-activity' role='tab' aria-controls='tp-ops-activity' aria-selected='false'>
                            <i class='fas fa-wave-square mr-1'></i><?php echo $lang->get('ops_tab_activity'); ?>
                        </a>
                    </li>
                    <li class='nav-item'>
                        <a class='nav-link' id='tp-ops-users-tab' data-toggle='tab' href='#tp-ops-users' role='tab' aria-controls='tp-ops-users' aria-selected='false'>
                            <i class='fas fa-users mr-1'></i><?php echo $lang->get('ops_tab_users'); ?>
                        </a>
                    </li>
                    <li class='nav-item'>
                        <a class='nav-link' id='tp-ops-lapr-tab' data-toggle='tab' href='#tp-ops-lapr' role='tab' aria-controls='tp-ops-lapr' aria-selected='false'>
                            <i class='fas fa-sync-alt mr-1'></i><?php echo $lang->get('ops_tab_lapr'); ?>
                        </a>
                    </li>
                </ul>

                <div class='tab-content pt-3' id='tp-ops-tabs-content'>
                    <div class='tab-pane fade show active' id='tp-ops-overview' role='tabpanel' aria-labelledby='tp-ops-overview-tab'>
                        <div class='row'>
                            <div class='col-xl-4 col-md-6'>
                                <div class='info-box mb-3 tp-ops-info-box' id='tp-overview-health-card'>
                                    <span class='info-box-icon bg-secondary'><i class='fas fa-shield-alt'></i></span>
                                    <div class='info-box-content'>
                                        <span class='info-box-text'><?php echo $lang->get('ops_overview_health'); ?></span>
                                        <span class='info-box-number' id='tp-overview-health'>-</span>
                                        <span class='text-muted small' id='tp-overview-health-detail'></span>
                                    </div>
                                </div>
                            </div>

                            <div class='col-xl-4 col-md-6'>
                                <div class='info-box mb-3 tp-ops-info-box'>
                                    <span class='info-box-icon bg-info'><i class='fas fa-key'></i></span>
                                    <div class='info-box-content'>
                                        <span class='info-box-text'><?php echo $lang->get('ops_kpi_items_active'); ?></span>
                                        <span class='info-box-number' id='tp-kpi-items-total'>-</span>
                                        <span class='text-muted small' id='tp-overview-items-detail'></span>
                                    </div>
                                </div>
                            </div>

                            <div class='col-xl-4 col-md-6'>
                                <div class='info-box mb-3 tp-ops-info-box'>
                                    <span class='info-box-icon bg-primary'><i class='fas fa-users'></i></span>
                                    <div class='info-box-content'>
                                        <span class='info-box-text'><?php echo $lang->get('ops_overview_users'); ?></span>
                                        <span class='info-box-number' id='tp-overview-users-enabled'>-</span>
                                        <span class='text-muted small' id='tp-overview-users-disabled'></span>
                                    </div>
                                </div>
                            </div>

                            <div class='col-xl-4 col-md-6'>
                                <div class='info-box mb-3 tp-ops-info-box'>
                                    <span class='info-box-icon bg-success'><i class='fas fa-check-circle'></i></span>
                                    <div class='info-box-content'>
                                        <span class='info-box-text'><?php echo $lang->get('ops_kpi_items_secure_score'); ?></span>
                                        <span class='info-box-number' id='tp-kpi-items-secure-score'>-</span>
                                        <span class='text-muted small' id='tp-kpi-items-secure-details'></span>
                                    </div>
                                </div>
                            </div>

                            <div class='col-xl-4 col-md-6'>
                                <div class='info-box mb-3 tp-ops-info-box'>
                                    <span class='info-box-icon bg-danger'><i class='fas fa-user-secret'></i></span>
                                    <div class='info-box-content'>
                                        <span class='info-box-text'><?php echo $lang->get('ops_hibp_pwned'); ?></span>
                                        <span class='info-box-number' id='tp-kpi-items-hibp-pwned'>-</span>
                                        <span class='text-muted small' id='tp-kpi-items-hibp-occurrences'></span>
                                    </div>
                                </div>
                            </div>

                            <div class='col-xl-4 col-md-6'>
                                <div class='info-box mb-3 tp-ops-info-box'>
                                    <span class='info-box-icon bg-warning'><i class='fas fa-search'></i></span>
                                    <div class='info-box-content'>
                                        <span class='info-box-text'><?php echo $lang->get('ops_hibp_coverage'); ?></span>
                                        <span class='info-box-number' id='tp-kpi-items-hibp-coverage'>-</span>
                                        <span class='text-muted small' id='tp-kpi-items-hibp-unknown'></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class='row'>
                            <div class='col-xl-5 mb-3'>
                                <div class='card h-100'>
                                    <div class='card-header'>
                                        <h3 class='card-title'><i class='fas fa-clipboard-check mr-2'></i><?php echo $lang->get('ops_priority_title'); ?></h3>
                                    </div>
                                    <div class='card-body p-0'>
                                        <div class='list-group list-group-flush tp-ops-priority-list' id='tp-ops-priority-list'>
                                            <div class='list-group-item text-muted'>-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class='col-xl-7 mb-3'>
                                <div class='card h-100'>
                                    <div class='card-header'>
                                        <h3 class='card-title'><i class='fas fa-list-ul mr-2'></i><?php echo $lang->get('ops_current_state'); ?></h3>
                                    </div>
                                    <div class='card-body p-0'>
                                        <div class='table-responsive'>
                                            <table class='table table-sm mb-0 tp-ops-status-table'>
                                                <tbody>
                                                    <tr>
                                                        <th><?php echo $lang->get('ops_kpi_items_shared'); ?></th>
                                                        <td class='text-right' id='tp-kpi-items-shared'>-</td>
                                                        <th><?php echo $lang->get('ops_kpi_items_personal'); ?></th>
                                                        <td class='text-right' id='tp-kpi-items-personal'>-</td>
                                                    </tr>
                                                    <tr>
                                                        <th><?php echo $lang->get('ops_kpi_avg_complexity'); ?></th>
                                                        <td class='text-right'>
                                                            <span id='tp-kpi-items-complexity-avg'>-</span>
                                                            <span class='d-block text-muted small' id='tp-kpi-items-complexity-unknown'></span>
                                                        </td>
                                                        <th><?php echo $lang->get('ops_kpi_avg_pw_len'); ?></th>
                                                        <td class='text-right' id='tp-kpi-items-pwlen-avg'>-</td>
                                                    </tr>
                                                    <tr>
                                                        <th><?php echo $lang->get('ops_kpi_items_stale_90'); ?></th>
                                                        <td class='text-right' id='tp-kpi-items-stale-90'>-</td>
                                                        <th><?php echo $lang->get('ops_kpi_users_disabled'); ?></th>
                                                        <td class='text-right' id='tp-kpi-users-disabled'>-</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class='tab-pane fade' id='tp-ops-security' role='tabpanel' aria-labelledby='tp-ops-security-tab'>
                        <div class='row'>
                            <div class='col-xl-3 col-md-6'>
                                <div class='small-box bg-success tp-ops-small-box tp-security-small-box'>
                                    <div class='inner'>
                                        <h3 id='tp-sec-kpi-policy-score'>-</h3>
                                        <p>
                                            <?php echo $lang->get('ops_kpi_items_secure_score'); ?><br>
                                            <small id='tp-sec-kpi-policy-detail'>-</small>
                                        </p>
                                    </div>
                                    <div class='icon'><i class='fas fa-check-circle'></i></div>
                                </div>
                            </div>

                            <div class='col-xl-3 col-md-6'>
                                <div class='small-box bg-danger tp-ops-small-box tp-security-small-box'>
                                    <div class='inner'>
                                        <h3 id='tp-sec-kpi-hibp-pwned'>-</h3>
                                        <p>
                                            <?php echo $lang->get('ops_hibp_pwned'); ?><br>
                                            <small id='tp-sec-kpi-hibp-occurrences'>-</small>
                                        </p>
                                    </div>
                                    <div class='icon'><i class='fas fa-user-secret'></i></div>
                                </div>
                            </div>

                            <div class='col-xl-3 col-md-6'>
                                <div class='small-box bg-info tp-ops-small-box tp-security-small-box'>
                                    <div class='inner'>
                                        <h3 id='tp-sec-kpi-hibp-coverage'>-</h3>
                                        <p>
                                            <?php echo $lang->get('ops_hibp_coverage'); ?><br>
                                            <small id='tp-sec-kpi-hibp-coverage-detail'>-</small>
                                        </p>
                                    </div>
                                    <div class='icon'><i class='fas fa-search'></i></div>
                                </div>
                            </div>

                            <div class='col-xl-3 col-md-6'>
                                <div class='small-box bg-warning tp-ops-small-box tp-security-small-box'>
                                    <div class='inner'>
                                        <h3 id='tp-sec-kpi-complexity-unknown'>-</h3>
                                        <p>
                                            <?php echo $lang->get('ops_kpi_complexity_unknown'); ?><br>
                                            <small id='tp-sec-kpi-complexity-detail'>-</small>
                                        </p>
                                    </div>
                                    <div class='icon'><i class='fas fa-question-circle'></i></div>
                                </div>
                            </div>
                        </div>

                        <div class='row'>
                            <div class='col-lg-6 mb-3'>
                                <div class='card h-100'>
                                    <div class='card-header'>
                                        <h3 class='card-title'><i class='fas fa-lock mr-2'></i><?php echo $lang->get('ops_items_password_compliance'); ?></h3>
                                    </div>
                                    <div class='card-body'>
                                        <div class='tp-ops-chart tp-ops-chart-doughnut' style='height: 220px;'>
                                            <canvas id='tp-items-password-compliance-chart'></canvas>
                                        </div>
                                        <div class='small text-muted mt-2' id='tp-items-password-policy'></div>
                                    </div>
                                </div>
                            </div>

                            <div class='col-lg-6 mb-3'>
                                <div class='card h-100'>
                                    <div class='card-header'>
                                        <h3 class='card-title'><i class='fas fa-user-secret mr-2'></i><?php echo $lang->get('ops_hibp_title'); ?></h3>
                                    </div>
                                    <div class='card-body'>
                                        <div class='tp-ops-chart tp-ops-chart-doughnut' style='height: 220px;'>
                                            <canvas id='tp-items-hibp-chart'></canvas>
                                        </div>
                                        <div class='small text-muted mt-2' id='tp-items-hibp-note'></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class='row'>
                            <div class='col-12 mb-3'>
                                <div class='card h-100'>
                                    <div class='card-header'>
                                        <h3 class='card-title'><i class='fas fa-thermometer-half mr-2'></i><?php echo $lang->get('ops_items_complexity_distribution'); ?></h3>
                                    </div>
                                    <div class='card-body'>
                                        <div class='tp-ops-chart' style='height: 340px;'>
                                            <canvas id='tp-items-complexity-chart'></canvas>
                                        </div>
                                        <div class='small text-muted mt-2'>
                                            <?php echo $lang->get('ops_complexity_chart_help'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class='tab-pane fade' id='tp-ops-activity' role='tabpanel' aria-labelledby='tp-ops-activity-tab'>
                        <div class='card mb-3'>
                            <div class='card-header'>
                                <h3 class='card-title'><i class='fas fa-clock mr-2'></i><?php echo $lang->get('ops_activity_summary'); ?></h3>
                            </div>
                            <div class='card-body'>
                                <div class='row'>
                                    <div class='col-xl col-md-4 col-sm-6'>
                                        <div class='small-box bg-info tp-ops-small-box'>
                                            <div class='inner'>
                                                <h3 id='tp-kpi-views-total'>-</h3>
                                                <p><?php echo $lang->get('ops_metric_views'); ?></p>
                                            </div>
                                            <div class='icon'><i class='fas fa-eye'></i></div>
                                        </div>
                                    </div>
                                    <div class='col-xl col-md-4 col-sm-6'>
                                        <div class='small-box bg-success tp-ops-small-box'>
                                            <div class='inner'>
                                                <h3 id='tp-kpi-items-created-total'>-</h3>
                                                <p><?php echo $lang->get('ops_kpi_created_period'); ?></p>
                                            </div>
                                            <div class='icon'><i class='fas fa-plus-circle'></i></div>
                                        </div>
                                    </div>
                                    <div class='col-xl col-md-4 col-sm-6'>
                                        <div class='small-box bg-warning tp-ops-small-box'>
                                            <div class='inner'>
                                                <h3 id='tp-kpi-copies-total'>-</h3>
                                                <p><?php echo $lang->get('ops_kpi_copies_total'); ?></p>
                                            </div>
                                            <div class='icon'><i class='fas fa-copy'></i></div>
                                        </div>
                                    </div>
                                    <div class='col-xl col-md-4 col-sm-6'>
                                        <div class='small-box bg-danger tp-ops-small-box'>
                                            <div class='inner'>
                                                <h3 id='tp-kpi-pwshown-total'>-</h3>
                                                <p><?php echo $lang->get('ops_metric_pw_shown'); ?></p>
                                            </div>
                                            <div class='icon'><i class='fas fa-unlock-alt'></i></div>
                                        </div>
                                    </div>
                                    <div class='col-xl col-md-4 col-sm-6'>
                                        <div class='small-box bg-primary tp-ops-small-box'>
                                            <div class='inner'>
                                                <h3 id='tp-overview-api-share'>-</h3>
                                                <p>
                                                    <?php echo $lang->get('ops_overview_api_share'); ?><br>
                                                    <small id='tp-overview-api-detail'>-</small>
                                                </p>
                                            </div>
                                            <div class='icon'><i class='fas fa-code'></i></div>
                                        </div>
                                    </div>
                                </div>

                                <div class='tp-ops-chart' style='height: 260px;'>
                                    <canvas id='tp-users-activity-chart'></canvas>
                                </div>

                                <div class='row mt-3'>
                                    <div class='col-lg-3 col-sm-6'>
                                        <dl class='mb-0 tp-ops-activity-kpi'>
                                            <dt><?php echo $lang->get('ops_kpi_users_active'); ?></dt>
                                            <dd>
                                                <span id='tp-kpi-users-active'>-</span>
                                                <small id='tp-kpi-users-active-ratio'></small>
                                            </dd>
                                        </dl>
                                    </div>
                                    <div class='col-lg-3 col-sm-6'>
                                        <dl class='mb-0 tp-ops-activity-kpi'>
                                            <dt><?php echo $lang->get('ops_kpi_users_inactive'); ?></dt>
                                            <dd>
                                                <span id='tp-kpi-users-inactive'>-</span>
                                                <small id='tp-kpi-users-inactive-ratio'></small>
                                            </dd>
                                        </dl>
                                    </div>
                                    <div class='col-lg-3 col-sm-6'>
                                        <dl class='mb-0 tp-ops-activity-kpi'>
                                            <dt><?php echo $lang->get('ops_metric_modified'); ?></dt>
                                            <dd><span id='tp-kpi-modified-total'>-</span></dd>
                                        </dl>
                                    </div>
                                    <div class='col-lg-3 col-sm-6'>
                                        <dl class='mb-0 tp-ops-activity-kpi'>
                                            <dt><?php echo $lang->get('ops_metric_created'); ?></dt>
                                            <dd><span id='tp-kpi-created-total'>-</span></dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class='row'>
                            <div class='col-xl-4 col-lg-6 mb-3'>
                                <div class='card h-100'>
                                    <div class='card-header'>
                                        <h3 class='card-title'><i class='fas fa-plus mr-2'></i><?php echo $lang->get('ops_created_activity_title'); ?></h3>
                                    </div>
                                    <div class='card-body'>
                                        <div class='tp-ops-chart' style='height: 210px;'>
                                            <canvas id='tp-items-created-source-chart'></canvas>
                                        </div>
                                        <div class='small text-muted mt-2' id='tp-kpi-items-created-source'></div>
                                        <div class='small text-muted mt-1'>
                                            <?php echo $lang->get('ops_created_source_help'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class='col-xl-4 col-lg-6 mb-3'>
                                <div class='card h-100'>
                                    <div class='card-header'>
                                        <h3 class='card-title'><i class='fas fa-key mr-2'></i><?php echo $lang->get('ops_created_complexity_title'); ?></h3>
                                    </div>
                                    <div class='card-body'>
                                        <div class='tp-ops-chart' style='height: 210px;'>
                                            <canvas id='tp-items-created-complexity-chart'></canvas>
                                        </div>
                                        <div class='small text-muted mt-2'><?php echo $lang->get('ops_created_current_level_notice'); ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class='col-xl-4 mb-3'>
                                <div class='card h-100'>
                                    <div class='card-header'>
                                        <h3 class='card-title'><i class='fas fa-exclamation-triangle mr-2'></i><?php echo $lang->get('ops_created_quality_title'); ?></h3>
                                    </div>
                                    <div class='card-body'>
                                        <div class='info-box bg-light'>
                                            <span class='info-box-icon'><i class='fas fa-unlock-alt'></i></span>
                                            <div class='info-box-content'>
                                                <span class='info-box-text'><?php echo $lang->get('ops_kpi_created_weak'); ?></span>
                                                <span class='info-box-number' id='tp-kpi-items-created-weak'>-</span>
                                                <span class='small text-muted' id='tp-kpi-items-created-unknown'></span>
                                                <span class='small text-muted' id='tp-kpi-items-created-hibp'></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class='card'>
                            <div class='card-header'>
                                <h3 class='card-title'><i class='fas fa-user-shield mr-2'></i><?php echo $lang->get('ops_activity_roles_title'); ?></h3>
                            </div>
                            <div class='card-body'>
                                <div class='row'>
                                    <div class='col-lg-3 col-sm-6'>
                                        <dl class='tp-ops-activity-kpi'>
                                            <dt><?php echo $lang->get('ops_kpi_roles_total'); ?></dt>
                                            <dd><span id='tp-kpi-roles-total'>-</span></dd>
                                        </dl>
                                    </div>
                                    <div class='col-lg-3 col-sm-6'>
                                        <dl class='tp-ops-activity-kpi'>
                                            <dt><?php echo $lang->get('ops_kpi_roles_active'); ?></dt>
                                            <dd><span id='tp-kpi-roles-active'>-</span></dd>
                                        </dl>
                                    </div>
                                    <div class='col-lg-3 col-sm-6'>
                                        <dl class='tp-ops-activity-kpi'>
                                            <dt><?php echo $lang->get('ops_kpi_roles_users_active'); ?></dt>
                                            <dd><span id='tp-kpi-roles-users-active'>-</span></dd>
                                        </dl>
                                    </div>
                                    <div class='col-lg-3 col-sm-6'>
                                        <dl class='tp-ops-activity-kpi'>
                                            <dt><?php echo $lang->get('ops_kpi_roles_items_unique'); ?></dt>
                                            <dd><span id='tp-kpi-roles-items-unique'>-</span></dd>
                                        </dl>
                                    </div>
                                </div>

                                <div class='row'>
                                    <div class='col-lg-5 mb-3 mb-lg-0'>
                                        <div class='tp-ops-chart' style='height: 240px;'>
                                            <canvas id='tp-roles-top-chart'></canvas>
                                        </div>
                                    </div>
                                    <div class='col-lg-7'>
                                        <div class='table-responsive'>
                                            <table class='table table-sm table-hover mb-0'>
                                                <thead>
                                                    <tr>
                                                        <th><?php echo $lang->get('role'); ?></th>
                                                        <th class='text-center'><?php echo $lang->get('ops_table_users_active'); ?></th>
                                                        <th class='text-center'><?php echo $lang->get('ops_metric_views_short'); ?></th>
                                                        <th class='text-center'><?php echo $lang->get('ops_metric_copies_short'); ?></th>
                                                        <th class='text-center'><?php echo $lang->get('ops_table_items_unique'); ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody id='tp-roles-top-body'>
                                                    <tr><td colspan='5' class='text-center text-muted'>-</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class='card'>
                            <div class='card-header'>
                                <h3 class='card-title'><i class='fas fa-copy mr-2'></i><?php echo $lang->get('ops_top_items_copied'); ?></h3>
                            </div>
                            <div class='card-body'>
                                <div class='table-responsive'>
                                    <table class='table table-sm table-hover mb-0'>
                                        <thead>
                                            <tr>
                                                <th><?php echo $lang->get('ops_table_item'); ?></th>
                                                <th><?php echo $lang->get('folder'); ?></th>
                                                <th class='text-center'><?php echo $lang->get('ops_table_personal_short'); ?></th>
                                                <th class='text-center'><?php echo $lang->get('ops_metric_copies_short'); ?></th>
                                                <th class='text-center'><?php echo $lang->get('ops_table_users_unique'); ?></th>
                                                <th class='text-center'><?php echo $lang->get('ops_table_last_activity'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id='tp-items-topcopied-body'>
                                            <tr><td colspan='6' class='text-center text-muted'>-</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class='small text-muted mt-3'>
                            <?php echo $lang->get('ops_api_marker_info'); ?>
                        </div>
                    </div>

                    <div class='tab-pane fade' id='tp-ops-users' role='tabpanel' aria-labelledby='tp-ops-users-tab'>
                        <div class='card'>
                            <div class='card-header'>
                                <h3 class='card-title'>
                                    <i class='fas fa-users mr-2'></i><?php echo $lang->get('ops_users_ranking_title'); ?>
                                </h3>
                                <div class='card-tools'>
                                    <label class='sr-only' for='tp-users-ranking-metric'>
                                        <?php echo $lang->get('ops_users_ranking_metric'); ?>
                                    </label>
                                    <select class='form-control form-control-sm' id='tp-users-ranking-metric'>
                                        <option value='overall'><?php echo $lang->get('ops_users_ranking_overall'); ?></option>
                                        <option value='views'><?php echo $lang->get('ops_metric_views'); ?></option>
                                        <option value='created'><?php echo $lang->get('ops_metric_created'); ?></option>
                                        <option value='modified'><?php echo $lang->get('ops_metric_modified'); ?></option>
                                        <option value='password_actions'><?php echo $lang->get('ops_users_ranking_password_actions'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class='card-body'>
                                <div class='alert alert-light border py-2'>
                                    <i class='fas fa-info-circle text-info mr-1'></i>
                                    <?php echo $lang->get('ops_users_ranking_notice'); ?>
                                </div>

                                <div class='row'>
                                    <div class='col-lg-7 mb-3 mb-lg-0'>
                                        <div class='tp-ops-chart' style='height: 290px;'>
                                            <canvas id='tp-users-ranking-chart'></canvas>
                                        </div>
                                    </div>
                                    <div class='col-lg-5'>
                                        <div class='table-responsive'>
                                            <table class='table table-sm table-hover mb-0 tp-users-ranking-table'>
                                                <thead>
                                                    <tr>
                                                        <th class='text-center'><?php echo $lang->get('ops_rank'); ?></th>
                                                        <th><?php echo $lang->get('user'); ?></th>
                                                        <th class='text-right' id='tp-users-ranking-value-label'>
                                                            <?php echo $lang->get('actions'); ?>
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody id='tp-users-ranking-body'>
                                                    <tr><td colspan='3' class='text-center text-muted'>-</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <div class='small text-muted mt-3' id='tp-users-ranking-context'>
                                    <?php echo $lang->get('ops_users_ranking_scope'); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class='tab-pane fade' id='tp-ops-lapr' role='tabpanel' aria-labelledby='tp-ops-lapr-tab'>
                        <div class='alert alert-info py-2' id='tp-lapr-availability' style='display:none;'></div>
                        <div id='tp-lapr-content'>
                        <div class='alert alert-light border py-2'>
                            <i class='fas fa-info-circle text-info mr-1'></i><?php echo $lang->get('ops_lapr_snapshot_note'); ?>
                            <span class='d-block small text-muted mt-1'><?php echo $lang->get('ops_lapr_filters_not_applicable'); ?></span>
                        </div>

                        <div class='row'>
                            <div class='col-xl-3 col-md-6'>
                                <div class='small-box bg-primary tp-ops-small-box'>
                                    <div class='inner'>
                                        <h3 id='tp-lapr-kpi-endpoints'>-</h3>
                                        <p><?php echo $lang->get('lapr_monitor_endpoints'); ?></p>
                                        <div class='small' id='tp-lapr-kpi-endpoint-detail'></div>
                                    </div>
                                    <div class='icon'><i class='fas fa-server'></i></div>
                                </div>
                            </div>
                            <div class='col-xl-3 col-md-6'>
                                <div class='small-box bg-success tp-ops-small-box'>
                                    <div class='inner'>
                                        <h3 id='tp-lapr-kpi-accounts'>-</h3>
                                        <p><?php echo $lang->get('lapr_monitor_accounts_compliant'); ?></p>
                                    </div>
                                    <div class='icon'><i class='fas fa-user-check'></i></div>
                                </div>
                            </div>
                            <div class='col-xl-3 col-md-6'>
                                <div class='small-box bg-info tp-ops-small-box'>
                                    <div class='inner'>
                                        <h3 id='tp-lapr-kpi-rotations'>-</h3>
                                        <p><?php echo $lang->get('ops_lapr_rotations'); ?></p>
                                    </div>
                                    <div class='icon'><i class='fas fa-sync-alt'></i></div>
                                </div>
                            </div>
                            <div class='col-xl-3 col-md-6'>
                                <div class='small-box bg-warning tp-ops-small-box'>
                                    <div class='inner'>
                                        <h3 id='tp-lapr-kpi-success-rate'>-</h3>
                                        <p><?php echo $lang->get('ops_lapr_success_rate'); ?></p>
                                    </div>
                                    <div class='icon'><i class='fas fa-percentage'></i></div>
                                </div>
                            </div>
                        </div>

                        <div class='alert alert-warning py-2' id='tp-lapr-retention-warning' style='display:none;'>
                            <i class='fas fa-exclamation-triangle mr-1'></i><?php echo $lang->get('ops_lapr_retention_limited'); ?>
                        </div>
                        <div class='alert alert-danger py-2' id='tp-lapr-worker-warning' style='display:none;'></div>

                        <div class='row'>
                            <div class='col-xl-8 mb-3'>
                                <div class='card h-100'>
                                    <div class='card-header'>
                                        <h3 class='card-title'><i class='fas fa-chart-line mr-2'></i><?php echo $lang->get('ops_lapr_rotation_trend'); ?></h3>
                                    </div>
                                    <div class='card-body'>
                                        <div class='tp-ops-chart' style='height: 290px;'>
                                            <canvas id='tp-lapr-rotation-chart'></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class='col-xl-4 mb-3'>
                                <div class='card h-100'>
                                    <div class='card-header'>
                                        <h3 class='card-title'><i class='fas fa-tasks mr-2'></i><?php echo $lang->get('ops_lapr_current_states'); ?></h3>
                                    </div>
                                    <div class='card-body'>
                                        <div class='tp-ops-chart tp-ops-chart-doughnut' style='height: 290px;'>
                                            <canvas id='tp-lapr-state-chart'></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class='row'>
                            <div class='col-lg-6 mb-3'>
                                <div class='card h-100'>
                                    <div class='card-header'>
                                        <h3 class='card-title'><i class='fas fa-exclamation-circle mr-2'></i><?php echo $lang->get('ops_lapr_failure_causes'); ?></h3>
                                    </div>
                                    <div class='card-body'>
                                        <div class='tp-ops-chart' style='height: 250px;'>
                                            <canvas id='tp-lapr-failure-chart'></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class='col-lg-6 mb-3'>
                                <div class='card h-100'>
                                    <div class='card-header'>
                                        <h3 class='card-title'><i class='fas fa-shield-alt mr-2'></i><?php echo $lang->get('ops_lapr_policy_distribution'); ?></h3>
                                    </div>
                                    <div class='card-body'>
                                        <div class='tp-ops-chart' style='height: 250px;'>
                                            <canvas id='tp-lapr-policy-chart'></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class='card'>
                            <div class='card-header'>
                                <h3 class='card-title'><i class='fas fa-server mr-2'></i><?php echo $lang->get('ops_lapr_problem_endpoints'); ?></h3>
                            </div>
                            <div class='card-body p-0'>
                                <div class='table-responsive'>
                                    <table class='table table-sm table-hover mb-0'>
                                        <thead>
                                            <tr>
                                                <th><?php echo $lang->get('lapr_endpoint'); ?></th>
                                                <th class='text-center'><?php echo $lang->get('ops_lapr_successes'); ?></th>
                                                <th class='text-center'><?php echo $lang->get('ops_lapr_failures'); ?></th>
                                                <th class='text-center'><?php echo $lang->get('ops_lapr_success_rate'); ?></th>
                                                <th class='text-center'><?php echo $lang->get('ops_lapr_last_failure'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id='tp-lapr-endpoints-body'>
                                            <tr><td colspan='5' class='text-center text-muted'>-</td></tr>
                                        </tbody>
                                    </table>
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
