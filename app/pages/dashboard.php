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
 * @file      dashboard.php
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
require_once __DIR__ . '/../sources/main.functions.php';
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
if (
    $checkUserAccess->checkSession() === false
    || $checkUserAccess->userAccessPage('dashboard') === false
    || (int) ($SETTINGS['security_dashboard_enabled'] ?? 0) !== 1
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

$isAdmin = (int) $session->get('user-admin') === 1;
?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark"><i class="fa-solid fa-shield-halved mr-2"></i><?php echo $lang->get('security_dashboard'); ?></h1>
            </div>
        </div>
    </div>
</div>

<style>
    .dashboard-card { transition: box-shadow .15s ease, transform .15s ease; }
    .dashboard-card .small-box-footer { background: rgba(0, 0, 0, .12); }
    .dashboard-card.active-filter { box-shadow: 0 0 0 3px rgba(0, 0, 0, .5) inset; transform: translateY(-2px); }
    .dashboard-card-loader { position: absolute; top: 6px; right: 10px; z-index: 5; display: none; color: rgba(255, 255, 255, .9); font-size: .9rem; }
    .dashboard-card.loading .dashboard-card-loader { display: block; }
</style>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">

        <p class="text-muted"><?php echo $lang->get('security_dashboard_desc'); ?></p>

        <!-- My security posture -->
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-user-shield mr-2"></i><?php echo $lang->get('security_dashboard_my_posture'); ?></h3>
                <div class="card-tools">
                    <span class="mr-3 text-muted small">
                        <?php echo $lang->get('security_dashboard_last_scan'); ?>:
                        <span id="dashboard-last-scan"><?php echo $lang->get('security_dashboard_never'); ?></span>
                    </span>
                    <label class="mr-2 small mb-0"><input type="checkbox" id="dashboard-include-hibp"> <?php echo $lang->get('security_dashboard_scan_hibp'); ?></label>
                    <button type="button" class="btn btn-sm btn-primary" id="dashboard-scan-btn">
                        <i class="fa-solid fa-magnifying-glass-chart mr-1"></i><?php echo $lang->get('security_dashboard_scan_button'); ?>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Scan progress -->
                <div id="dashboard-progress-wrap" class="progress mb-3" style="display:none;">
                    <div id="dashboard-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%;">0%</div>
                </div>

                <!-- Indicator cards -->
                <div class="row" id="dashboard-cards">
                    <?php
                    $cards = [
                        ['key' => 'weak', 'icon' => 'fa-thermometer-empty', 'color' => 'bg-warning'],
                        ['key' => 'reused', 'icon' => 'fa-clone', 'color' => 'bg-warning'],
                        ['key' => 'breached', 'icon' => 'fa-triangle-exclamation', 'color' => 'bg-danger'],
                        ['key' => 'overdue', 'icon' => 'fa-clock-rotate-left', 'color' => 'bg-warning'],
                        ['key' => 'no_expiry', 'icon' => 'fa-hourglass-end', 'color' => 'bg-info'],
                        ['key' => 'overshared', 'icon' => 'fa-users', 'color' => 'bg-info'],
                        ['key' => 'orphaned', 'icon' => 'fa-link-slash', 'color' => 'bg-secondary'],
                    ];
                    foreach ($cards as $c) {
                        echo '
                    <div class="col-lg-3 col-6">
                        <div class="small-box ' . $c['color'] . ' dashboard-card" data-flag="' . $c['key'] . '" style="cursor:pointer;" title="' . $lang->get('security_dashboard_filter') . '">
                            <div class="dashboard-card-loader"><i class="fa-solid fa-circle-notch fa-spin"></i></div>
                            <div class="inner">
                                <h3 id="dashboard-count-' . $c['key'] . '">0</h3>
                                <p>' . $lang->get('security_dashboard_' . $c['key']) . '</p>
                            </div>
                            <div class="icon"><i class="fa-solid ' . $c['icon'] . '"></i></div>
                            <span class="small-box-footer">' . $lang->get('security_dashboard_filter') . ' <i class="fa-solid fa-filter ml-1"></i></span>
                        </div>
                    </div>';
                    }
                    ?>
                </div>
                <p class="text-muted small mb-0">
                    <?php echo $lang->get('security_dashboard_total_items'); ?>:
                    <strong id="dashboard-total-items">0</strong>
                </p>
            </div>
        </div>

        <!-- Flagged items list -->
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-list-check mr-2"></i><?php echo $lang->get('security_dashboard_flagged_items'); ?>
                    <span id="dashboard-active-flag" class="badge badge-secondary ml-2" style="display:none;"></span>
                </h3>
                <div class="card-tools">
                    <select id="dashboard-folder-filter" class="form-control form-control-sm no-save d-inline-block align-middle" style="width:auto;">
                        <option value="0"><?php echo $lang->get('security_dashboard_all_groupings'); ?></option>
                    </select>
                    <button type="button" id="dashboard-clear-filter" class="btn btn-sm btn-default ml-1" style="display:none;">
                        <i class="fa-solid fa-xmark mr-1"></i><?php echo $lang->get('security_dashboard_clear_filter'); ?>
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th><?php echo $lang->get('security_dashboard_item'); ?></th>
                            <th><?php echo $lang->get('security_dashboard_location'); ?></th>
                            <th><?php echo $lang->get('security_dashboard_issues'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="dashboard-flagged-tbody">
                        <tr><td colspan="3" class="text-center text-muted p-3"><?php echo $lang->get('security_dashboard_no_issues'); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($isAdmin === true) { ?>
        <!-- Admin organisation-wide posture (counts only, no plaintext) -->
        <div class="card card-outline card-danger">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-building-shield mr-2"></i><?php echo $lang->get('security_dashboard_admin_view'); ?></h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr><td><?php echo $lang->get('security_dashboard_total_items'); ?></td><td class="text-right"><strong id="admin-total">0</strong></td></tr>
                            <tr><td><?php echo $lang->get('security_dashboard_weak'); ?></td><td class="text-right"><strong id="admin-weak">0</strong></td></tr>
                            <tr><td><?php echo $lang->get('security_dashboard_breached'); ?></td><td class="text-right"><strong id="admin-breached">0</strong></td></tr>
                            <tr><td><?php echo $lang->get('security_dashboard_overdue'); ?></td><td class="text-right"><strong id="admin-overdue">0</strong></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr><td><?php echo $lang->get('security_dashboard_no_expiry'); ?></td><td class="text-right"><strong id="admin-no_expiry">0</strong></td></tr>
                            <tr><td><?php echo $lang->get('security_dashboard_overshared'); ?></td><td class="text-right"><strong id="admin-overshared">0</strong></td></tr>
                            <tr><td><?php echo $lang->get('security_dashboard_reused'); ?></td><td class="text-right"><strong id="admin-reused">0</strong></td></tr>
                            <tr><td><?php echo $lang->get('security_dashboard_scanned_users'); ?></td><td class="text-right"><strong id="admin-scanned-users">0</strong> / <span id="admin-total-users">0</span></td></tr>
                        </table>
                    </div>
                </div>
                <p class="text-muted small mb-0"><i class="fa-solid fa-lock mr-1"></i><?php echo $lang->get('security_dashboard_admin_zk_note'); ?></p>
            </div>
        </div>
        <?php } ?>

    </div>
</section>
<!-- /.content -->
