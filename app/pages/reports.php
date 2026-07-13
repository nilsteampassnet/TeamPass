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
 * @file      reports.php
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
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('reports') === false) {
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

$reportsEnabled = (int) ($SETTINGS['compliance_reports_enabled'] ?? 0) === 1;

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-file-contract mr-2"></i><?php echo $lang->get('compliance_reports'); ?></h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<!-- Main content -->
<div class='content'>
    <div class='container-fluid'>
<?php if ($reportsEnabled === false) : ?>
        <div class='row'>
            <div class='col-12'>
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-triangle-exclamation mr-2"></i><?php echo $lang->get('compliance_reports_disabled_tip'); ?>
                </div>
            </div>
        </div>
<?php else : ?>
        <div class='row'>
            <div class='col-md-12'>
                <div class='card card-primary'>
                    <div class='card-header'>
                        <h3 class='card-title'><?php echo $lang->get('compliance_reports_generate'); ?></h3>
                    </div>

                    <div class='card-body'>
                        <div class='row'>
                            <div class='col-md-4'>
                                <label for='report-type'><?php echo $lang->get('compliance_reports_type'); ?></label>
                                <select class='form-control' id='report-type'>
                                    <option value='access_matrix'><?php echo $lang->get('compliance_report_access_matrix'); ?></option>
                                    <option value='access_changes'><?php echo $lang->get('compliance_report_access_changes'); ?></option>
                                    <option value='posture_summary'><?php echo $lang->get('compliance_report_posture_summary'); ?></option>
                                    <option value='rotation_evidence'><?php echo $lang->get('compliance_report_rotation_evidence'); ?></option>
<?php if ((int) ($SETTINGS['data_classification_enabled'] ?? 0) === 1) : ?>
                                    <option value='classification'><?php echo $lang->get('compliance_report_classification'); ?></option>
<?php endif; ?>
<?php if ((int) ($SETTINGS['rotation_tracking_enabled'] ?? 0) === 1) : ?>
                                    <option value='rotation_overdue'><?php echo $lang->get('compliance_report_rotation_overdue'); ?></option>
                                    <option value='rotation_sla'><?php echo $lang->get('compliance_report_rotation_sla'); ?></option>
<?php endif; ?>
                                </select>
                                <small class='form-text text-muted' id='report-type-tip'></small>
                            </div>
                            <div class='col-md-3 report-period' style='display:none'>
                                <label for='report-start'><?php echo $lang->get('from'); ?></label>
                                <input type='date' class='form-control' id='report-start'>
                            </div>
                            <div class='col-md-3 report-period' style='display:none'>
                                <label for='report-end'><?php echo $lang->get('to'); ?></label>
                                <input type='date' class='form-control' id='report-end'>
                            </div>
                        </div>
                    </div>

                    <div class='card-footer'>
                        <button class='btn btn-primary' id='report-generate'>
                            <i class='fas fa-gears mr-2'></i><?php echo $lang->get('compliance_reports_run'); ?>
                        </button>
                        <button class='btn btn-default float-right' id='report-export-csv' disabled>
                            <i class='fas fa-file-csv mr-2'></i><?php echo $lang->get('compliance_reports_export_csv'); ?>
                        </button>
                    </div>
                </div>

                <div class='card card-default' id='report-results-card' style='display:none'>
                    <div class='card-header'>
                        <h3 class='card-title' id='report-results-title'></h3>
                    </div>
                    <div class='card-body table-responsive p-0' id='report-results'>
                    </div>
                </div>
            </div>
        </div>
<?php endif; ?>
    </div>
</div>
