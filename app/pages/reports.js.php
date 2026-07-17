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
 * @file      reports.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request AS SymfonyRequest;
use TeampassClasses\Language\Language;
// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

if ($session->get('key') === null) {
    die('Hacking attempt...');
}

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
?>


<script type='text/javascript'>
    //<![CDATA[

    // Column definitions per report type: [key, header label]
    const tpReportColumns = {
        'access_matrix': [
            ['login', '<?php echo $lang->get('login'); ?>'],
            ['name', '<?php echo $lang->get('name'); ?>'],
            ['role', '<?php echo $lang->get('role'); ?>'],
            ['folder', '<?php echo $lang->get('folder'); ?>'],
            ['access', '<?php echo $lang->get('accesses'); ?>'],
        ],
        'access_changes': [
            ['date', '<?php echo $lang->get('date'); ?>'],
            ['action', '<?php echo $lang->get('action'); ?>'],
            ['author', '<?php echo $lang->get('author'); ?>'],
            ['target', '<?php echo $lang->get('user'); ?>'],
        ],
        'posture_summary': [
            ['issue', '<?php echo $lang->get('compliance_report_issue'); ?>'],
            ['items', '<?php echo $lang->get('items'); ?>'],
            ['percent', '%'],
            ['source_display', '<?php echo $lang->get('compliance_report_freshness'); ?>'],
        ],
        'rotation_evidence': [
            ['label', '<?php echo $lang->get('label'); ?>'],
            ['folder', '<?php echo $lang->get('folder'); ?>'],
            ['leaver', '<?php echo $lang->get('leaver_risk'); ?>'],
            ['flagged_by', '<?php echo $lang->get('author'); ?>'],
            ['flagged_at', '<?php echo $lang->get('date'); ?>'],
            ['status', '<?php echo $lang->get('status'); ?>'],
        ],
        'classification': [
            ['level', '<?php echo $lang->get('classification'); ?>'],
            ['items', '<?php echo $lang->get('items'); ?>'],
            ['percent', '%'],
        ],
        'rotation_overdue': [
            ['label', '<?php echo $lang->get('label'); ?>'],
            ['folder', '<?php echo $lang->get('folder'); ?>'],
            ['sla_days', '<?php echo $lang->get('rotation_sla_days'); ?>'],
            ['last_change', '<?php echo $lang->get('leaver_risk_last_pw_change'); ?>'],
            ['due_at', '<?php echo $lang->get('rotation_due_at'); ?>'],
            ['days_overdue', '<?php echo $lang->get('rotation_days_overdue'); ?>'],
            ['status', '<?php echo $lang->get('status'); ?>'],
        ],
        'rotation_sla': [
            ['folder', '<?php echo $lang->get('folder'); ?>'],
            ['sla_days', '<?php echo $lang->get('rotation_sla_days'); ?>'],
            ['items', '<?php echo $lang->get('items'); ?>'],
            ['overdue', '<?php echo $lang->get('rotation_overdue'); ?>'],
        ],
    }

    const tpReportTips = {
        'access_matrix': '<?php echo $lang->get('compliance_report_access_matrix_tip'); ?>',
        'access_changes': '<?php echo $lang->get('compliance_report_access_changes_tip'); ?>',
        'posture_summary': '<?php echo $lang->get('compliance_report_posture_summary_tip'); ?>',
        'rotation_evidence': '<?php echo $lang->get('compliance_report_rotation_evidence_tip'); ?>',
        'classification': '<?php echo $lang->get('compliance_report_classification_tip'); ?>',
        'rotation_overdue': '<?php echo $lang->get('compliance_report_rotation_overdue_tip'); ?>',
        'rotation_sla': '<?php echo $lang->get('compliance_report_rotation_sla_tip'); ?>',
    }

    let tpReportCsv = ''
    let tpReportName = ''

    // Show/hide the period selectors + tip on report change
    function tpReportTypeChanged() {
        const reportType = $('#report-type').val()
        $('.report-period').toggle(reportType === 'access_changes')
        $('#report-type-tip').text(tpReportTips[reportType] || '')
    }
    $(document).on('change', '#report-type', tpReportTypeChanged)
    $(function() {
        tpReportTypeChanged()
    })

    // Generate the selected report
    $(document).on('click', '#report-generate', function() {
        const reportType = $('#report-type').val()

        toastr.remove();
        toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>');

        const postData = {
            type: 'report_' + reportType,
            key: '<?php echo $session->get('key'); ?>'
        }
        if (reportType === 'access_changes') {
            const startVal = $('#report-start').val()
            const endVal = $('#report-end').val()
            if (startVal !== '') {
                postData.start = Math.floor(new Date(startVal + 'T00:00:00').getTime() / 1000)
            }
            if (endVal !== '') {
                postData.end = Math.floor(new Date(endVal + 'T23:59:59').getTime() / 1000)
            }
        }

        $.post(
            'sources/reports.queries.php',
            postData,
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                if (debugJavascript === true) console.log(data);

                toastr.remove();
                if (data.error !== false) {
                    toastr.error(
                        data.message,
                        '<?php echo $lang->get('caution'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                    return;
                }

                tpReportCsv = data.csv || ''
                tpReportName = 'teampass-report-' + reportType + '-' + new Date().toISOString().slice(0, 10) + '.csv'
                $('#report-export-csv').prop('disabled', tpReportCsv === '')

                renderReportTable(reportType, data)
                toastr.success('<?php echo $lang->get('done'); ?>', '', { timeOut: 1000 });
            }
        );
    })

    // Download the last generated report as CSV
    $(document).on('click', '#report-export-csv', function() {
        if (tpReportCsv === '') {
            return
        }
        const blob = new Blob(['﻿' + tpReportCsv], { type: 'text/csv;charset=utf-8;' })
        const link = document.createElement('a')
        link.href = URL.createObjectURL(blob)
        link.download = tpReportName
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        URL.revokeObjectURL(link.href)
    })

    /**
     * Render the generated report as an HTML table.
     * @param {string} reportType - One of the tpReportColumns keys
     * @param {Object} data - Decoded handler response ({rows: [...]})
     */
    function renderReportTable(reportType, data) {
        const columns = tpReportColumns[reportType]
        const rows = Array.isArray(data.rows) ? data.rows : []

        let title = $('#report-type option[value="' + reportType + '"]').text()
        if (reportType === 'posture_summary') {
            // Live metadata flags are recomputed on every run; only reused/orphaned
            // are bound to the last deep scan, so their freshness is shown per row.
            const totalItems = data.total_items !== undefined ? data.total_items : data.scanned_items
            title += ' — ' + totalItems + ' <?php echo $lang->get('items'); ?>'
            const scanLabel = parseInt(data.last_scan_at) > 0
                ? '<?php echo $lang->get('compliance_report_source_scan'); ?> · ' + new Date(data.last_scan_at * 1000).toLocaleString()
                : '<?php echo $lang->get('compliance_report_scan_never'); ?>'
            rows.forEach(row => {
                row.source_display = row.source === 'scan'
                    ? scanLabel
                    : '<?php echo $lang->get('compliance_report_source_live'); ?>'
            })
        }
        if (reportType === 'rotation_sla' && data.folders_total !== undefined) {
            title += ' — ' + data.folders_with_sla + '/' + data.folders_total
                + ' <?php echo $lang->get('rotation_sla_coverage_suffix'); ?> (' + data.coverage_percent + '%)'
        }
        $('#report-results-title').text(title)

        if (rows.length === 0) {
            $('#report-results').html('<div class="alert alert-info m-3"><?php echo $lang->get('no_data_to_display'); ?></div>')
            $('#report-results-card').show()
            return
        }

        let html = '<table class="table table-bordered table-striped table-sm mb-0"><thead><tr>'
        columns.forEach(col => {
            html += '<th>' + col[1] + '</th>'
        })
        html += '</tr></thead><tbody>'

        rows.forEach(row => {
            html += '<tr>'
            columns.forEach(col => {
                html += '<td>' + htmlEncode(String(row[col[0]] !== undefined && row[col[0]] !== null ? row[col[0]] : '')) + '</td>'
            })
            html += '</tr>'
        })

        html += '</tbody></table>'
        $('#report-results').html(html)
        $('#report-results-card').show()
    }

    //]]>
</script>
