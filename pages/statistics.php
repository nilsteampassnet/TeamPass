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
use TeampassClasses\NestedTree\NestedTree;
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('statistics') === false) {
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


// get current statistics items
$statistics_items = [];
if (isset($SETTINGS['send_statistics_items'])) {
    $statistics_items = array_filter(explode(';', $SETTINGS['send_statistics_items']));
}

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-chart-bar mr-2"></i><?php echo $lang->get('statistics'); ?></h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->


<style>
    /* Prevent Chart.js doughnut charts from growing when canvases are rendered inside tabs */
    .tp-ops-doughnut-wrap {
        position: relative;
        height: 220px;
        max-height: 220px;
    }
    .tp-ops-doughnut-wrap canvas {
        width: 100% !important;
        height: 220px !important;
        max-height: 220px;
    }
</style>


<!-- Main content -->
<div class='content'>
    <div class='container-fluid'>
        <div class='row'>
            <div class='col-md-12'>

                
                <!-- Statistics main tabs -->
                <ul class='nav nav-tabs mb-3' id='tp-stats-main-tabs' role='tablist'>
                    <li class='nav-item'>
                        <a class='nav-link active' id='tp-stats-main-tab-ops' data-toggle='tab' href='javascript:void(0)' data-target='#tp-stats-main-ops' role='tab'>
                            <i class='fas fa-chart-line mr-1'></i><?php echo $lang->get('ops_tab_operational'); ?>
                        </a>
                    </li>
                    <li class='nav-item'>
                        <a class='nav-link' id='tp-stats-main-tab-legacy' data-toggle='tab' href='javascript:void(0)' data-target='#tp-stats-main-legacy' role='tab'>
                            <i class='fas fa-history mr-1'></i><?php echo $lang->get('ops_tab_legacy'); ?>
                        </a>
                    </li>
                </ul>

                <div class='tab-content' id='tp-stats-main-tabs-content'>
                    <div class='tab-pane fade show active' id='tp-stats-main-ops' role='tabpanel'>
<!-- Operational Statistics -->
                <div class='card card-info' id='tp-operational-stats-card'>
                    <div class='card-header'>
                        <h3 class='card-title'><i class='fas fa-chart-line mr-2'></i><?php echo $lang->get('ops_dashboard_title'); ?></h3>
                        <div class='card-tools'>
                            <div class='form-inline'>
                                <label class='mr-2' for='tp-ops-period'><?php echo $lang->get('ops_period'); ?></label>
                                <select class='form-control form-control-sm mr-3' id='tp-ops-period'>
                                    <option value='24h'><?php echo $lang->get('ops_period_24h'); ?></option>
                                    <option value='7d'><?php echo $lang->get('ops_period_7d'); ?></option>
                                    <option value='30d'><?php echo $lang->get('ops_period_30d'); ?></option>
                                    <option value='90d'><?php echo $lang->get('ops_period_90d'); ?></option>
                                </select>

                                <div class='form-check mr-3'>
                                    <input class='form-check-input flat-blue' type='checkbox' id='tp-ops-include-personal' checked>
                                    <label class='form-check-label' for='tp-ops-include-personal'><?php echo $lang->get('ops_include_personal'); ?></label>
                                </div>

                                <div class='form-check mr-3'>
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
                                <a class='nav-link active' id='tp-ops-tab-users' data-toggle='tab' href='javascript:void(0)' data-target='#tp-ops-users' role='tab'>
                                    <i class='fas fa-users mr-1'></i><?php echo $lang->get('users'); ?>
                                </a>
                            </li>
                            <li class='nav-item'>
                                <a class='nav-link' id='tp-ops-tab-roles' data-toggle='tab' href='javascript:void(0)' data-target='#tp-ops-roles' role='tab'>
                                    <i class='fas fa-user-shield mr-1'></i><?php echo $lang->get('roles'); ?>
                                </a>
                            </li>
                            <li class='nav-item'>
                                <a class='nav-link' id='tp-ops-tab-items' data-toggle='tab' href='javascript:void(0)' data-target='#tp-ops-items' role='tab'>
                                    <i class='fas fa-key mr-1'></i><?php echo $lang->get('items'); ?>
                                </a>
                            </li>
                        </ul>

                        <div class='tab-content mt-3' id='tp-ops-tabs-content'>

                            <!-- USERS TAB -->
                            <div class='tab-pane fade show active' id='tp-ops-users' role='tabpanel'>
                                <div class='row'>
                                    <div class='col-md-2 col-sm-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-body p-2'>
                                                <div class='text-muted small'><?php echo $lang->get('ops_kpi_users_active'); ?></div>
                                                <div class='h4 m-0' id='tp-kpi-users-active'>-</div>
                                                <div class='small text-muted' id='tp-kpi-users-active-ratio'></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class='col-md-2 col-sm-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-body p-2'>
                                                <div class='text-muted small'><?php echo $lang->get('ops_kpi_users_inactive'); ?></div>
                                                <div class='h4 m-0' id='tp-kpi-users-inactive'>-</div>
                                                <div class='small text-muted' id='tp-kpi-users-inactive-ratio'></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class='col-md-2 col-sm-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-body p-2'>
                                                <div class='text-muted small'><?php echo $lang->get('ops_kpi_users_disabled'); ?></div>
                                                <div class='h4 m-0' id='tp-kpi-users-disabled'>-</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class='col-md-2 col-sm-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-body p-2'>
                                                <div class='text-muted small'><?php echo $lang->get('ops_kpi_connections_web'); ?></div>
                                                <div class='h4 m-0' id='tp-kpi-connections-web'>-</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class='col-md-2 col-sm-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-body p-2'>
                                                <div class='text-muted small'><?php echo $lang->get('ops_kpi_connections_api'); ?></div>
                                                <div class='h4 m-0' id='tp-kpi-connections-api'>-</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class='col-md-2 col-sm-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-body p-2'>
                                                <div class='text-muted small'><?php echo $lang->get('ops_kpi_copies_total'); ?></div>
                                                <div class='h4 m-0' id='tp-kpi-copies-total'>-</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class='row'>
                                    <div class='col-lg-8 mb-3'>
                                        <div class='card'>
                                            <div class='card-header'>
                                                <h3 class='card-title'><?php echo $lang->get('ops_activity_title'); ?></h3>
                                            </div>
                                            <div class='card-body'>
                                                <canvas id='tp-users-activity-chart' height='110'></canvas>
                                            </div>
                                        </div>
                                    </div>
                                    <div class='col-lg-4 mb-3'>
                                        <div class='card'>
                                            <div class='card-header'>
                                                <h3 class='card-title'><?php echo $lang->get('ops_actions_summary'); ?></h3>
                                            </div>
                                            <div class='card-body'>
                                                <div class='row'>
                                                    <div class='col-6'>
                                                        <div class='text-muted small'><?php echo $lang->get('ops_metric_views'); ?></div>
                                                        <div class='h4' id='tp-kpi-views-total'>-</div>
                                                    </div>
                                                    <div class='col-6'>
                                                        <div class='text-muted small'><?php echo $lang->get('ops_metric_pw_shown'); ?></div>
                                                        <div class='h4' id='tp-kpi-pwshown-total'>-</div>
                                                    </div>
                                                </div>
                                                <hr/>
                                                <div class='row'>
                                                    <div class='col-6'>
                                                        <div class='text-muted small'><?php echo $lang->get('ops_metric_created'); ?></div>
                                                        <div class='h4' id='tp-kpi-created-total'>-</div>
                                                    </div>
                                                    <div class='col-6'>
                                                        <div class='text-muted small'><?php echo $lang->get('ops_metric_modified'); ?></div>
                                                        <div class='h4' id='tp-kpi-modified-total'>-</div>
                                                    </div>
                                                </div>
                                                <hr/>
                                                <div class='small text-muted'>
                                                    <?php echo $lang->get('ops_api_marker_info'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class='card'>
                                    <div class='card-header'>
                                        <h3 class='card-title'><?php echo $lang->get('ops_top_users'); ?></h3>
                                    </div>
                                    <div class='card-body table-responsive'>
                                        <table class='table table-sm table-hover'>
                                            <thead>
                                                <tr>
                                                    <th><?php echo $lang->get('user'); ?></th>
                                                    <th class='text-center'><?php echo $lang->get('ops_table_score'); ?></th>
                                                    <th class='text-center'><?php echo $lang->get('ops_metric_views_short'); ?></th>
                                                    <th class='text-center'><?php echo $lang->get('ops_metric_copies_short'); ?></th>
                                                    <th class='text-center'><?php echo $lang->get('ops_metric_pw_shown'); ?></th>
                                                    <th class='text-center'><?php echo $lang->get('ops_table_items_unique'); ?></th>
                                                    <th class='text-center'><?php echo $lang->get('ops_table_folders_unique'); ?></th>
                                                    <th class='text-center'><?php echo $lang->get('ops_table_last_activity'); ?></th>
                                                    <th class='text-center'><?php echo $lang->get('ops_table_api_views_pct'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody id='tp-users-top-body'>
                                                <tr><td colspan='9' class='text-center text-muted'>-</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- ROLES TAB -->
                            <div class='tab-pane fade' id='tp-ops-roles' role='tabpanel'>
                                <div class='row'>
                                    <div class='col-md-3 col-sm-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-body p-2'>
                                                <div class='text-muted small'><?php echo $lang->get('ops_kpi_roles_total'); ?></div>
                                                <div class='h4 m-0' id='tp-kpi-roles-total'>-</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class='col-md-3 col-sm-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-body p-2'>
                                                <div class='text-muted small'><?php echo $lang->get('ops_kpi_roles_active'); ?></div>
                                                <div class='h4 m-0' id='tp-kpi-roles-active'>-</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class='col-md-3 col-sm-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-body p-2'>
                                                <div class='text-muted small'><?php echo $lang->get('ops_kpi_roles_users_active'); ?></div>
                                                <div class='h4 m-0' id='tp-kpi-roles-users-active'>-</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class='col-md-3 col-sm-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-body p-2'>
                                                <div class='text-muted small'><?php echo $lang->get('ops_kpi_roles_items_unique'); ?></div>
                                                <div class='h4 m-0' id='tp-kpi-roles-items-unique'>-</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class='row'>
                                    <div class='col-lg-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-header'>
                                                <h3 class='card-title'><?php echo $lang->get('ops_top_roles'); ?></h3>
                                            </div>
                                            <div class='card-body'>
                                                <canvas id='tp-roles-top-chart' height='130'></canvas>
                                            </div>
                                        </div>
                                    </div>
                                    <div class='col-lg-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-header'>
                                                <h3 class='card-title'><?php echo $lang->get('ops_top_roles_details'); ?></h3>
                                            </div>
                                            <div class='card-body table-responsive'>
                                                <table class='table table-sm table-hover'>
                                                    <thead>
                                                        <tr>
                                                            <th><?php echo $lang->get('role'); ?></th>
                                                            <th class='text-center'><?php echo $lang->get('ops_table_users_active'); ?></th>
                                                            <th class='text-center'><?php echo $lang->get('ops_metric_views_short'); ?></th>
                                                            <th class='text-center'><?php echo $lang->get('ops_metric_copies_short'); ?></th>
                                                            <th class='text-center'><?php echo $lang->get('ops_table_items_unique'); ?></th>
                                                            <th class='text-center'><?php echo $lang->get('ops_table_items_accessible'); ?></th>
                                                            <th class='text-center'><?php echo $lang->get('ops_table_last_activity'); ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id='tp-roles-top-body'>
                                                        <tr><td colspan='7' class='text-center text-muted'>-</td></tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ITEMS TAB -->
                            <div class='tab-pane fade' id='tp-ops-items' role='tabpanel'>
                                <div class='row'>
                                    <div class='col-md-2 col-sm-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-body p-2'>
                                                <div class='text-muted small'><?php echo $lang->get('ops_kpi_items_active'); ?></div>
                                                <div class='h4 m-0' id='tp-kpi-items-total'>-</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class='col-md-2 col-sm-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-body p-2'>
                                                <div class='text-muted small'><?php echo $lang->get('ops_kpi_items_personal'); ?></div>
                                                <div class='h4 m-0' id='tp-kpi-items-personal'>-</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class='col-md-2 col-sm-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-body p-2'>
                                                <div class='text-muted small'><?php echo $lang->get('ops_kpi_items_shared'); ?></div>
                                                <div class='h4 m-0' id='tp-kpi-items-shared'>-</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class='col-md-2 col-sm-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-body p-2'>
                                                <div class='text-muted small'><?php echo $lang->get('ops_kpi_avg_complexity'); ?></div>
                                                <div class='h4 m-0' id='tp-kpi-items-complexity-avg'>-</div>
                                                <div class='small text-muted' id='tp-kpi-items-complexity-unknown'></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class='col-md-2 col-sm-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-body p-2'>
                                                <div class='text-muted small'><?php echo $lang->get('ops_kpi_avg_pw_len'); ?></div>
                                                <div class='h4 m-0' id='tp-kpi-items-pwlen-avg'>-</div>
                                            </div>
                                        </div>
                                    </div>
                                                                        <div class='col-md-2 col-sm-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-body p-2'>
                                                <div class='text-muted small'><?php echo $lang->get('ops_kpi_items_secure_score'); ?></div>
                                                <div class='h4 m-0' id='tp-kpi-items-secure-score'>-</div>
                                                <div class='small text-muted' id='tp-kpi-items-secure-details'></div>
                                            </div>
                                        </div>
                                    </div>
<div class='col-md-2 col-sm-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-body p-2'>
                                                <div class='text-muted small'><?php echo $lang->get('ops_kpi_items_stale_90'); ?></div>
                                                <div class='h4 m-0' id='tp-kpi-items-stale-90'>-</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                                                <div class='row'>
                                    <div class='col-lg-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-header'>
                                                <h3 class='card-title'><?php echo $lang->get('ops_items_personal_vs_shared_inventory'); ?></h3>
                                            </div>
                                            <div class='card-body'>
                                                <div class='tp-ops-doughnut-wrap'>
                                                <canvas id='tp-items-personal-chart'></canvas>
                                            </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class='col-lg-6 mb-3'>
                                        <div class='card'>
                                            <div class='card-header'>
                                                <h3 class='card-title'><?php echo $lang->get('ops_items_password_compliance'); ?></h3>
                                            </div>
                                            <div class='card-body'>
                                                <div class='tp-ops-doughnut-wrap'>
                                                <canvas id='tp-items-password-compliance-chart'></canvas>
                                            </div>
                                                <div class='small text-muted mt-2' id='tp-items-password-policy'></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class='row'>
                                    <div class='col-lg-12 mb-3'>
                                        <div class='card'>
                                            <div class='card-header'>
                                                <h3 class='card-title'><?php echo $lang->get('ops_items_complexity_distribution'); ?></h3>
                                            </div>
                                            <div class='card-body'>
                                                <canvas id='tp-items-complexity-chart' height='110'></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </div>

                                <div class='card'>
                                    <div class='card-header'>
                                        <h3 class='card-title'><?php echo $lang->get('ops_top_items_copied'); ?></h3>
                                    </div>
                                    <div class='card-body table-responsive'>
                                        <table class='table table-sm table-hover'>
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

                        </div>
                    </div>
                </div>

                    </div>
                    <div class='tab-pane fade' id='tp-stats-main-legacy' role='tabpanel'>
<div class='card card-primary'>
                    <div class='card-header'>
                        <h3 class='card-title'><?php echo $lang->get('configuration'); ?></h3>
                    </div>

                    <div class='card-body'>
                        <div class='row mb-5'>
                            <div class='col-10'>
                                <?php echo $lang->get('sending_anonymous_statistics'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('sending_anonymous_statistics_details'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='send_stats' data-toggle-on='<?php echo isset($SETTINGS['send_stats']) === true && (int) $SETTINGS['send_stats'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='send_stats_input' value='<?php echo isset($SETTINGS['send_stats']) === true && (int) $SETTINGS['send_stats'] === 1 ? '1' : '0'; ?>' />
                            </div>
                        </div>

                        <div class='row mb-5' id='statistics-options'>
                            <table class="table table-bordered table-responsive">
                                <tr>
                                    <th style="width:60%;"><?php echo $lang->get('characteristic'); ?></th>
                                    <th style="width:40%;"><?php echo $lang->get('current_value'); ?></th>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_country" style="margin-right:15px;" <?php echo in_array('stat_country', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?> class="stat_option"><label class="ml-2" for="stat_country"><b><?php echo $lang->get('country'); ?></b></label>
                                        <i class="fas fa-question-circle infotip ml-2" title="<?php echo $lang->get('country_statistics'); ?>"></i>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_country" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_users" style="margin-right:15px;" <?php echo in_array('stat_users', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?> class="stat_option"><label class="ml-2" for="stat_users"><b><?php echo $lang->get('users'); ?></b></label>
                                        <i class="fas fa-question-circle infotip ml-2" title="<?php echo $lang->get('users_statistics'); ?>"></i>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_users" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_items" style="margin-right:15px;" <?php echo in_array('stat_items', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_items"><b><?php echo $lang->get('items'); ?></b></label>
                                        <i class="fas fa-question-circle infotip ml-2" title="<?php echo $lang->get('items_statistics'); ?>"></i>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_items" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_items_shared" style="margin-right:15px;" <?php echo in_array('stat_items_shared', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_items_shared"><b><?php echo $lang->get('items_shared'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_items_shared" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_folders" style="margin-right:15px;" <?php echo in_array('stat_folders', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_folders"><b><?php echo $lang->get('folders_all'); ?></b></label>
                                        <i class="fas fa-question-circle infotip ml-2" title="<?php echo $lang->get('folders_statistics'); ?>"></i>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_folders" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_folders_shared" style="margin-right:15px;" <?php echo in_array('stat_folders_shared', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_folders_shared"><b><?php echo $lang->get('folders_shared'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_folders_shared" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_admins" style="margin-right:15px;" <?php echo in_array('stat_admins', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_admins"><b><?php echo $lang->get('administrators_number'); ?></b></label>
                                        <i class="fas fa-question-circle infotip ml-2" title="<?php echo $lang->get('administrators_number_statistics'); ?>"></i>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_admin" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_managers" style="margin-right:15px;" <?php echo in_array('stat_managers', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_managers"><b><?php echo $lang->get('managers_number'); ?></b></label>
                                        <i class="fas fa-question-circle infotip ml-2" title="<?php echo $lang->get('managers_number_statistics'); ?>"></i>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_manager" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_ro" style="margin-right:15px;" <?php echo in_array('stat_ro', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_ro"><b><?php echo $lang->get('readonly_number'); ?></b></label>
                                        <i class="fas fa-question-circle infotip ml-2" title="<?php echo $lang->get('readonly_number_statistics'); ?>"></i>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_ro" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_mysqlversion" style="margin-right:15px;" <?php echo in_array('stat_mysqlversion', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_mysqlversion"><b><?php echo $lang->get('mysql_version'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_mysql" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_phpversion" style="margin-right:15px;" <?php echo in_array('stat_phpversion', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_phpversion"><b><?php echo $lang->get('php_version'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_php" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_teampassversion" style="margin-right:15px;" <?php echo in_array('stat_teampassversion', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_teampassversion"><b><?php echo $lang->get('teampass_version'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_teampassv" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_languages" style="margin-right:15px;" <?php echo in_array('stat_languages', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_languages"><b><?php echo $lang->get('languages_used'); ?></b></label>
                                        <i class="fas fa-question-circle infotip ml-2" title="<?php echo $lang->get('languages_statistics'); ?>"></i>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_languages" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_kb" style="margin-right:15px;" <?php echo in_array('stat_kb', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_kb"><b><?php echo $lang->get('kb_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_kb" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_suggestion" style="margin-right:15px;" <?php echo in_array('stat_suggestion', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_suggestion"><b><?php echo $lang->get('suggestion_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_suggestion" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_customfields" style="margin-right:15px;" <?php echo in_array('stat_customfields', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_customfields"><b><?php echo $lang->get('customfields_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_customfields" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_api" style="margin-right:15px;" <?php echo in_array('stat_api', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_api"><b><?php echo $lang->get('api_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_api" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_2fa" style="margin-right:15px;" <?php echo in_array('stat_2fa', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_2fa"><b><?php echo $lang->get('2fa_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_2fa" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_agses" style="margin-right:15px;" <?php echo in_array('stat_agses', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_agses"><b><?php echo $lang->get('agses_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_agses" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_duo" style="margin-right:15px;" <?php echo in_array('stat_duo', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_duo"><b><?php echo $lang->get('duo_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_duo" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_ldap" style="margin-right:15px;" <?php echo in_array('stat_ldap', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_ldap"><b><?php echo $lang->get('ldap_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_ldap" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_syslog" style="margin-right:15px;" <?php echo in_array('stat_syslog', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_syslog"><b><?php echo $lang->get('syslog_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_syslog" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_stricthttps" style="margin-right:15px;" <?php echo in_array('stat_stricthttps', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_stricthttps"><b><?php echo $lang->get('stricthttps_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_https" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_fav" style="margin-right:15px;" <?php echo in_array('stat_fav', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_fav"><b><?php echo $lang->get('favourites_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_fav" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_pf" style="margin-right:15px;" <?php echo in_array('stat_pf', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_pf"><b><?php echo $lang->get('personalfolders_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_pf" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2">
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="cb_select_all" style="margin:10px 15px 0 4px;"><label class="ml-2" for="cb_select_all"><b><?php echo $lang->get('select_all'); ?></b></label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="button" class="btn btn-primary tp-action" id="statistics-save"><?php echo $lang->get('save'); ?></button>
                    </div>
                </div>

                
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>
