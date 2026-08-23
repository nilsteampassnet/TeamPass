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
 * @file      statistics.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('statistics') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}
?>


<script type='text/javascript'>
    // Operational statistics dashboard
    initOperationalStatistics();


    // Re-render charts when a tab becomes visible (Chart.js needs visible canvases)
    $(document).on('shown.bs.tab', '#tp-ops-tabs a[data-toggle="tab"]', function(event) {
        $('.tp-ops-standard-filter').toggle($(event.target).attr('href') !== '#tp-ops-lapr');
        tpOpsRerenderFromCache();
    });


    // ----------------------------------------------------------------------------------
    // Operational statistics
    // ----------------------------------------------------------------------------------

    var tpOpsCharts = {
        usersActivity: null,
        rolesTop: null,
        itemsPasswordCompliance: null,
        itemsCreatedSource: null,
        itemsCreatedComplexity: null,
        itemsHibp: null,
        itemsComplexity: null,
        usersRanking: null,
        laprRotations: null,
        laprStates: null,
        laprFailures: null,
        laprPolicies: null
    };

    // Cache last payload to allow instant re-render when tabs become visible (Chart.js needs visible canvases)
    var tpOpsLastData = null;

    function tpOpsPalette() {
        return {
            blue: 'rgba(54, 162, 235, 1)',
            blueFill: 'rgba(54, 162, 235, 0.18)',
            green: 'rgba(75, 192, 192, 1)',
            greenFill: 'rgba(75, 192, 192, 0.18)',
            orange: 'rgba(255, 159, 64, 1)',
            orangeFill: 'rgba(255, 159, 64, 0.18)',
            purple: 'rgba(153, 102, 255, 1)',
            purpleFill: 'rgba(153, 102, 255, 0.18)',
            red: 'rgba(255, 99, 132, 1)',
            redFill: 'rgba(255, 99, 132, 0.18)',
            gray: 'rgba(201, 203, 207, 1)',
            grayFill: 'rgba(201, 203, 207, 0.25)'
        };
    }

    function tpOpsPercent(value, total) {
        return total > 0 ? Math.round((value / total) * 100) : 0;
    }

    function tpOpsRerenderFromCache() {
        if (tpOpsLastData) {
            // Re-render without network call, useful when switching tabs (canvas becomes visible)
            renderOperationalStatistics(tpOpsLastData);

            try {
                if (tpOpsCharts.usersActivity) { tpOpsCharts.usersActivity.resize(); }
                if (tpOpsCharts.rolesTop) { tpOpsCharts.rolesTop.resize(); }
                if (tpOpsCharts.itemsPasswordCompliance) { tpOpsCharts.itemsPasswordCompliance.resize(); }
                if (tpOpsCharts.itemsCreatedSource) { tpOpsCharts.itemsCreatedSource.resize(); }
                if (tpOpsCharts.itemsCreatedComplexity) { tpOpsCharts.itemsCreatedComplexity.resize(); }
                if (tpOpsCharts.itemsHibp) { tpOpsCharts.itemsHibp.resize(); }
                if (tpOpsCharts.itemsComplexity) { tpOpsCharts.itemsComplexity.resize(); }
                if (tpOpsCharts.usersRanking) { tpOpsCharts.usersRanking.resize(); }
                if (tpOpsCharts.laprRotations) { tpOpsCharts.laprRotations.resize(); }
                if (tpOpsCharts.laprStates) { tpOpsCharts.laprStates.resize(); }
                if (tpOpsCharts.laprFailures) { tpOpsCharts.laprFailures.resize(); }
                if (tpOpsCharts.laprPolicies) { tpOpsCharts.laprPolicies.resize(); }
            } catch (e) {}
        }
    }

    function tpOpsEnsureLoadedOrRerender() {
        if (tpOpsLastData) {
            tpOpsRerenderFromCache();
        } else {
            loadOperationalStatistics();
        }
    }


    function initOperationalStatistics() {
        // Only if the card exists (defensive)
        if ($('#tp-operational-stats-card').length === 0) {
            return;
        }

        // Events: refresh + filters
        $(document).on('click', '#tp-ops-refresh', function() {
            loadOperationalStatistics();
        });
        $(document).on('change', '#tp-ops-period', function() {
            loadOperationalStatistics();
        });
        $(document).on('change', '#tp-users-ranking-metric', function() {
            if (tpOpsLastData) {
                renderUserRanking(tpOpsLastData);
            }
        });

        // Some instances use iCheck (ifChanged), others rely on native change
        $(document).on('change ifChanged', '#tp-ops-include-personal, #tp-ops-include-api', function() {
            loadOperationalStatistics();
        });

        // Ensure Chart.js is available and load the dashboard
        ensureChartJs(function() {
            tpOpsEnsureLoadedOrRerender();
        });
    }

    function ensureChartJs(callback) {
        if (typeof Chart !== 'undefined') {
            callback();
            return;
        }

        var scriptId = 'tp-chartjs-loader';
        var existing = document.getElementById(scriptId);
        if (existing) {
            existing.addEventListener('load', callback);
            return;
        }

        var s = document.createElement('script');
        s.id = scriptId;
        s.src = "<?php echo $SETTINGS['cpassman_url']; ?>/plugins/chart.js/Chart.min.js";
        s.onload = callback;
        document.head.appendChild(s);
    }

    function loadOperationalStatistics() {
        var payload = {
            period: $('#tp-ops-period').val() || '30d',
            include_personal: $('#tp-ops-include-personal').is(':checked') ? 1 : 0,
            include_api: $('#tp-ops-include-api').is(':checked') ? 1 : 0,
            top_roles_limit: 15,
            top_items_limit: 20
        };

        // UI loading state
        $('#tp-ops-refresh').prop('disabled', true);
        $('#tp-ops-refresh i').addClass('fa-spin');
        $('#tp-roles-top-body').html("<tr><td colspan='5' class='text-center text-muted'><i class='fas fa-circle-notch fa-spin'></i></td></tr>");
        $('#tp-users-ranking-body').html("<tr><td colspan='3' class='text-center text-muted'><i class='fas fa-circle-notch fa-spin'></i></td></tr>");
        $('#tp-items-topcopied-body').html("<tr><td colspan='6' class='text-center text-muted'><i class='fas fa-circle-notch fa-spin'></i></td></tr>");
        $('#tp-lapr-endpoints-body').html("<tr><td colspan='5' class='text-center text-muted'><i class='fas fa-circle-notch fa-spin'></i></td></tr>");

        $.post(
            "sources/admin.queries.php",
            {
                type: "get_operational_statistics",
                data: prepareExchangedData(JSON.stringify(payload), 'encode', '<?php echo $session->get('key'); ?>'),
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                try {
                    data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                } catch (e) {
                    toastr.remove();
                    toastr.error("<?php echo addslashes($lang->get('ops_stats_load_error')); ?>");
                    $('#tp-ops-refresh').prop('disabled', false);
                    $('#tp-ops-refresh i').removeClass('fa-spin');
                    return;
                }

                if (data.error === true) {
                    toastr.remove();
                    toastr.error(data.message ? data.message : "<?php echo addslashes($lang->get('ops_stats_db_error')); ?>");
                    $('#tp-ops-refresh').prop('disabled', false);
                    $('#tp-ops-refresh i').removeClass('fa-spin');
                    return;
                }

                tpOpsLastData = data;

                renderOperationalStatistics(data);

                $('#tp-ops-refresh').prop('disabled', false);
                $('#tp-ops-refresh i').removeClass('fa-spin');
            }
        ).fail(function() {
            toastr.remove();
            toastr.error("<?php echo addslashes($lang->get('ops_stats_load_error')); ?>");
            $('#tp-ops-refresh').prop('disabled', false);
            $('#tp-ops-refresh i').removeClass('fa-spin');
        });
    }

    function renderOperationalStatistics(data) {
        // ---------------- USERS KPIs
        var totalEnabled = parseInt(data.users.enabled_users || 0, 10);
        var active = parseInt(data.users.active_users || 0, 10);
        var inactive = parseInt(data.users.inactive_users || 0, 10);
        var disabled = parseInt(data.users.disabled_users || 0, 10);

        $('#tp-kpi-users-active').text(active);
        $('#tp-kpi-users-inactive').text(inactive);
        $('#tp-kpi-users-disabled').text(disabled);
        $('#tp-overview-users-enabled').text(totalEnabled);
        $('#tp-overview-users-disabled').text(disabled + " <?php echo addslashes($lang->get('ops_kpi_users_disabled')); ?>");

        $('#tp-kpi-users-active-ratio').text(totalEnabled > 0 ? Math.round((active / totalEnabled) * 100) + "% <?php echo addslashes($lang->get('ops_users_ratio_active')); ?>" : "");
        $('#tp-kpi-users-inactive-ratio').text(totalEnabled > 0 ? Math.round((inactive / totalEnabled) * 100) + "% <?php echo addslashes($lang->get('ops_users_ratio_inactive')); ?>" : "");

        var actions = data.users && data.users.actions ? data.users.actions : {};
        $('#tp-kpi-views-total').text(actions.views ? actions.views : 0);
        $('#tp-kpi-copies-total').text(actions.copies ? actions.copies : 0);
        $('#tp-kpi-pwshown-total').text(actions.pw_shown ? actions.pw_shown : 0);
        $('#tp-kpi-created-total').text(actions.created ? actions.created : 0);
        // Creation sources are rendered once, from data.items.created, in the dedicated card below.
        $('#tp-kpi-modified-total').text(actions.modified ? actions.modified : 0);

        // Users chart
        if (typeof Chart !== 'undefined') {
            if (tpOpsCharts.usersActivity) {
                tpOpsCharts.usersActivity.destroy();
            }
            var ctxUEl = document.getElementById('tp-users-activity-chart');
            if (ctxUEl) {
                var ctxU = ctxUEl.getContext('2d');
                var pal = tpOpsPalette();
                tpOpsCharts.usersActivity = new Chart(ctxU, {
                    type: 'line',
                    data: {
                        labels: (data.users.series && data.users.series.labels) ? data.users.series.labels : [],
                        datasets: [
                            { label: '<?php echo addslashes($lang->get('ops_metric_views')); ?>', data: (data.users.series && data.users.series.views) ? data.users.series.views : [], borderColor: pal.blue, backgroundColor: pal.blueFill, lineTension: 0.3, pointRadius: 1.5, fill: false },
                            { label: '<?php echo addslashes($lang->get('ops_kpi_copies_total')); ?>', data: (data.users.series && data.users.series.copies) ? data.users.series.copies : [], borderColor: pal.orange, backgroundColor: pal.orangeFill, lineTension: 0.3, pointRadius: 1.5, fill: false },
                            { label: '<?php echo addslashes($lang->get('ops_metric_pw_shown')); ?>', data: (data.users.series && data.users.series.pw_shown) ? data.users.series.pw_shown : [], borderColor: pal.purple, backgroundColor: pal.purpleFill, lineTension: 0.3, pointRadius: 1.5, fill: false },
                            { label: '<?php echo addslashes($lang->get('ops_metric_created')); ?>', data: (data.users.series && data.users.series.created) ? data.users.series.created : [], borderColor: pal.green, backgroundColor: pal.greenFill, lineTension: 0.3, pointRadius: 1.5, fill: false }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        legend: { display: true },
                        scales: {
                            xAxes: [{ ticks: { maxRotation: 0, autoSkip: true } }],
                            yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }]
                        }
                    }
                });
            }
        }
        renderUserRanking(data);

        // ---------------- ROLES KPIs
        $('#tp-kpi-roles-total').text(data.roles.total !== undefined ? data.roles.total : 0);
        $('#tp-kpi-roles-active').text(data.roles.active !== undefined ? data.roles.active : 0);
        $('#tp-kpi-roles-users-active').text((data.roles.kpis && data.roles.kpis.users_active) ? data.roles.kpis.users_active : 0);
        $('#tp-kpi-roles-items-unique').text((data.roles.kpis && data.roles.kpis.items_unique) ? data.roles.kpis.items_unique : 0);

        // Roles chart + table
        var rlabels = [];
        var rviews = [];
        var rcopies = [];
        var rrows = "";

        if (data.roles.top && data.roles.top.length > 0) {
            $.each(data.roles.top, function(_, r) {
                rlabels.push(r.title ? r.title : ("role#" + r.role_id));
                rviews.push(parseInt(r.views || 0, 10));
                rcopies.push(parseInt(r.copies || 0, 10));

                rrows += "<tr>" +
                    "<td>" + escapeHtml(r.title ? r.title : ("role#" + r.role_id)) + "</td>" +
                    "<td class='text-center'>" + (r.users_active !== undefined ? r.users_active : '-') + "</td>" +
                    "<td class='text-center'>" + (r.views !== undefined ? r.views : '-') + "</td>" +
                    "<td class='text-center'>" + (r.copies !== undefined ? r.copies : '-') + "</td>" +
                    "<td class='text-center'>" + (r.items_unique !== undefined ? r.items_unique : '-') + "</td>" +
                    "</tr>";
            });
        } else {
            rrows = "<tr><td colspan='5' class='text-center text-muted'><?php echo addslashes($lang->get('ops_no_data')); ?></td></tr>";
        }
        $('#tp-roles-top-body').html(rrows);

        if (typeof Chart !== 'undefined') {
            if (tpOpsCharts.rolesTop) {
                tpOpsCharts.rolesTop.destroy();
            }
            var ctxREl = document.getElementById('tp-roles-top-chart');
            if (ctxREl) {
                var ctxR = ctxREl.getContext('2d');
                tpOpsCharts.rolesTop = new Chart(ctxR, {
                    type: 'bar',
                    data: {
                        labels: rlabels,
                        datasets: [
                            { label: '<?php echo addslashes($lang->get('ops_metric_views')); ?>', data: rviews, backgroundColor: tpOpsPalette().blueFill, borderColor: tpOpsPalette().blue, borderWidth: 1 },
                            { label: '<?php echo addslashes($lang->get('ops_kpi_copies_total')); ?>', data: rcopies, backgroundColor: tpOpsPalette().orangeFill, borderColor: tpOpsPalette().orange, borderWidth: 1 }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        legend: { display: true },
                        scales: {
                            xAxes: [{ ticks: { autoSkip: true, maxRotation: 0 } }],
                            yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }]
                        }
                    }
                });
            }
        }

        // ---------------- ITEMS KPIs
        var inv = data.items && data.items.inventory ? data.items.inventory : {};
        var created = data.items && data.items.created ? data.items.created : {};
        var hibp = data.items && data.items.hibp ? data.items.hibp : {};
        var pwSec = data.items && data.items.password_security ? data.items.password_security : null;
        renderOperationalSummary(data, {
            totalEnabled: totalEnabled,
            active: active,
            inactive: inactive,
            disabled: disabled,
            actions: actions,
            inventory: inv,
            created: created,
            hibp: hibp,
            passwordSecurity: pwSec
        });

        $('#tp-kpi-items-total').text(inv.total_active !== undefined ? inv.total_active : 0);
        $('#tp-kpi-items-personal').text(inv.personal_active !== undefined ? inv.personal_active : 0);
        $('#tp-kpi-items-shared').text(inv.shared_active !== undefined ? inv.shared_active : 0);
        $('#tp-overview-items-detail').text((inv.shared_active || 0) + " <?php echo addslashes($lang->get('ops_kpi_items_shared')); ?> / " + (inv.personal_active || 0) + " <?php echo addslashes($lang->get('ops_kpi_items_personal')); ?>");
        $('#tp-kpi-items-complexity-avg').text(inv.avg_complexity !== null && inv.avg_complexity !== undefined ? inv.avg_complexity : "-");
        $('#tp-kpi-items-pwlen-avg').text(inv.avg_pw_len !== null && inv.avg_pw_len !== undefined ? inv.avg_pw_len : "-");
        if (pwSec && pwSec.secure_score !== undefined && pwSec.secure_score !== null) {
            $('#tp-kpi-items-secure-score').text(pwSec.secure_score + "/100");
            $('#tp-sec-kpi-policy-score').text(pwSec.secure_score + "/100");
            var assessed = parseInt(pwSec.assessed_total || 0, 10);
            var compliant = parseInt(pwSec.compliant || 0, 10);
            var nonCompliant = parseInt(pwSec.non_compliant || 0, 10);
            var unknownPolicy = parseInt(pwSec.unknown || 0, 10);
            $('#tp-sec-kpi-policy-detail').text(nonCompliant + " <?php echo addslashes($lang->get('ops_label_non_compliant')); ?> / " + unknownPolicy + " <?php echo addslashes($lang->get('ops_label_unknown')); ?>");
            if (assessed > 0) {
                var ratio = Math.round((compliant / assessed) * 100);
                var details = ratio + "% <?php echo addslashes($lang->get('ops_label_compliant')); ?> (" + compliant + "/" + assessed + ")";
                $('#tp-kpi-items-secure-details').text(details);
            } else {
                $('#tp-kpi-items-secure-details').text("");
            }
        } else {
            $('#tp-kpi-items-secure-score').text("-");
            $('#tp-kpi-items-secure-details').text("");
            $('#tp-sec-kpi-policy-score').text("-");
            $('#tp-sec-kpi-policy-detail').text("-");
        }
        $('#tp-kpi-items-stale-90').text(inv.stale_90 !== undefined ? inv.stale_90 : 0);
        $('#tp-kpi-items-created-total').text(created.total !== undefined ? created.total : 0);
        var createdSourceTotal = parseInt(created.web || 0, 10)
            + parseInt(created.api || 0, 10)
            + parseInt(created.imported || 0, 10);
        $('#tp-kpi-items-created-source').text(
            (created.web || 0) + " <?php echo addslashes($lang->get('ops_source_web')); ?> (" + tpOpsPercent(parseInt(created.web || 0, 10), createdSourceTotal) + "%) / " +
            (created.api || 0) + " <?php echo addslashes($lang->get('ops_source_api')); ?> (" + tpOpsPercent(parseInt(created.api || 0, 10), createdSourceTotal) + "%) / " +
            (created.imported || 0) + " <?php echo addslashes($lang->get('ops_source_import')); ?> (" + tpOpsPercent(parseInt(created.imported || 0, 10), createdSourceTotal) + "%)"
        );
        $('#tp-kpi-items-created-weak').text(created.weak_complexity !== undefined ? created.weak_complexity : 0);
        $('#tp-kpi-items-created-unknown').text((created.unknown_complexity || 0) + " <?php echo addslashes($lang->get('ops_label_unknown')); ?>");
        var createdHibp = created.hibp ? created.hibp : {};
        $('#tp-kpi-items-created-hibp').text((createdHibp.pwned || 0) + " <?php echo addslashes($lang->get('ops_created_hibp_pwned')); ?>");
        $('#tp-kpi-items-hibp-pwned').text(hibp.pwned !== undefined ? hibp.pwned : 0);
        $('#tp-kpi-items-hibp-occurrences').text((hibp.pwned_occurrences || 0) + " <?php echo addslashes($lang->get('ops_hibp_occurrences')); ?>");
        $('#tp-kpi-items-hibp-coverage').text(hibp.coverage_pct !== undefined ? hibp.coverage_pct + "%" : "-");
        $('#tp-kpi-items-hibp-unknown').text((hibp.unknown || 0) + " <?php echo addslashes($lang->get('ops_hibp_unknown')); ?> / " + (hibp.stale || 0) + " <?php echo addslashes($lang->get('ops_hibp_stale')); ?>");
        $('#tp-sec-kpi-hibp-pwned').text(hibp.pwned !== undefined ? hibp.pwned : 0);
        $('#tp-sec-kpi-hibp-occurrences').text((hibp.pwned_occurrences || 0) + " <?php echo addslashes($lang->get('ops_hibp_occurrences')); ?>");
        $('#tp-sec-kpi-hibp-coverage').text(hibp.coverage_pct !== undefined ? hibp.coverage_pct + "%" : "-");
        $('#tp-sec-kpi-hibp-coverage-detail').text((hibp.unknown || 0) + " <?php echo addslashes($lang->get('ops_hibp_unknown')); ?> / " + (hibp.stale || 0) + " <?php echo addslashes($lang->get('ops_hibp_stale')); ?>");

        if (inv.unknown_complexity !== undefined && inv.total_active !== undefined && parseInt(inv.total_active, 10) > 0) {
            var ratioUnknown = Math.round((parseInt(inv.unknown_complexity, 10) / Math.max(1, parseInt(inv.total_active, 10))) * 100);
            $('#tp-kpi-items-complexity-unknown').text(inv.unknown_complexity + " <?php echo addslashes($lang->get('ops_unknown')); ?> (" + ratioUnknown + "%)");
            $('#tp-sec-kpi-complexity-unknown').text(inv.unknown_complexity);
            $('#tp-sec-kpi-complexity-detail').text(ratioUnknown + "% - <?php echo addslashes($lang->get('ops_kpi_avg_complexity')); ?> " + (inv.avg_complexity !== null && inv.avg_complexity !== undefined ? inv.avg_complexity : "-"));
        } else {
            $('#tp-kpi-items-complexity-unknown').text("");
            $('#tp-sec-kpi-complexity-unknown').text("-");
            $('#tp-sec-kpi-complexity-detail').text("-");
        }

        // Items charts
        if (typeof Chart !== 'undefined') {
            // Created items by source
            if (tpOpsCharts.itemsCreatedSource) {
                tpOpsCharts.itemsCreatedSource.destroy();
            }
            var sourceValues = [
                parseInt(created.web || 0, 10),
                parseInt(created.api || 0, 10),
                parseInt(created.imported || 0, 10)
            ];
            var sourceTotal = sourceValues.reduce(function(total, value) {
                return total + value;
            }, 0);
            var sourceNames = [
                '<?php echo addslashes($lang->get('ops_source_web')); ?>',
                '<?php echo addslashes($lang->get('ops_source_api')); ?>',
                '<?php echo addslashes($lang->get('ops_source_import')); ?>'
            ];
            var sourceLabels = sourceNames.map(function(label, index) {
                return label + ' — ' + sourceValues[index] + ' (' + tpOpsPercent(sourceValues[index], sourceTotal) + '%)';
            });
            var ctxCreatedSourceEl = document.getElementById('tp-items-created-source-chart');
            if (ctxCreatedSourceEl) {
                tpOpsCharts.itemsCreatedSource = new Chart(ctxCreatedSourceEl.getContext('2d'), {
                    type: 'horizontalBar',
                    data: {
                        labels: sourceLabels,
                        datasets: [
                            {
                                label: '<?php echo addslashes($lang->get('ops_kpi_created_period')); ?>',
                                data: sourceValues,
                                backgroundColor: [
                                    tpOpsPalette().greenFill,
                                    tpOpsPalette().blueFill,
                                    tpOpsPalette().purpleFill
                                ],
                                borderColor: [
                                    tpOpsPalette().green,
                                    tpOpsPalette().blue,
                                    tpOpsPalette().purple
                                ],
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        legend: { display: false },
                        tooltips: {
                            callbacks: {
                                label: function(tooltipItem, chartData) {
                                    var dataset = chartData.datasets[tooltipItem.datasetIndex];
                                    var value = parseInt(dataset.data[tooltipItem.index] || 0, 10);
                                    return value + ' (' + tpOpsPercent(value, sourceTotal) + '%)';
                                }
                            }
                        },
                        scales: {
                            xAxes: [{ ticks: { beginAtZero: true, precision: 0 } }],
                            yAxes: [{ gridLines: { display: false } }]
                        }
                    }
                });
            }

            // Created items: current complexity distribution
            if (tpOpsCharts.itemsCreatedComplexity) {
                tpOpsCharts.itemsCreatedComplexity.destroy();
            }
            var createdComp = data.items && data.items.created_complexity ? data.items.created_complexity : {labels:[], counts:[]};
            var ctxCreatedCompEl = document.getElementById('tp-items-created-complexity-chart');
            if (ctxCreatedCompEl) {
                tpOpsCharts.itemsCreatedComplexity = new Chart(ctxCreatedCompEl.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: createdComp.labels ? createdComp.labels : [],
                        datasets: [
                            { label: '<?php echo addslashes($lang->get('items')); ?>', data: createdComp.counts ? createdComp.counts : [], backgroundColor: tpOpsPalette().orangeFill, borderColor: tpOpsPalette().orange, borderWidth: 1 }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        legend: { display: false },
                        scales: { yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }] }
                    }
                });
            }

            // Password compliance
            if (tpOpsCharts.itemsPasswordCompliance) {
                tpOpsCharts.itemsPasswordCompliance.destroy();
            }
            var pwSecChart = data.items && data.items.password_security ? data.items.password_security : null;
            var ctxPCEl = document.getElementById('tp-items-password-compliance-chart');
            if (ctxPCEl) {
                var ctxPC = ctxPCEl.getContext('2d');

                // Precompute values (avoid legend confusion when one slice is very small)
                var compliantV = pwSecChart && pwSecChart.compliant !== undefined ? parseInt(pwSecChart.compliant, 10) : 0;
                var nonCompliantV = pwSecChart && pwSecChart.non_compliant !== undefined ? parseInt(pwSecChart.non_compliant, 10) : 0;
                var unknownV = pwSecChart && pwSecChart.unknown !== undefined ? parseInt(pwSecChart.unknown, 10) : 0;
                if (isNaN(compliantV)) compliantV = 0;
                if (isNaN(nonCompliantV)) nonCompliantV = 0;
                if (isNaN(unknownV)) unknownV = 0;

                tpOpsCharts.itemsPasswordCompliance = new Chart(ctxPC, {
                    type: 'doughnut',
                    data: {
                        labels: [
                            '<?php echo addslashes($lang->get('ops_label_compliant')); ?>' + ' (' + compliantV + ')',
                            '<?php echo addslashes($lang->get('ops_label_non_compliant')); ?>' + ' (' + nonCompliantV + ')',
                            '<?php echo addslashes($lang->get('ops_label_unknown')); ?>' + ' (' + unknownV + ')'
                        ],
                        datasets: [
                            {
                                backgroundColor: [tpOpsPalette().greenFill, tpOpsPalette().redFill, tpOpsPalette().grayFill],
                                borderColor: [tpOpsPalette().green, tpOpsPalette().red, tpOpsPalette().gray],
                                borderWidth: 1,
                                data: [
                                    compliantV,
                                    nonCompliantV,
                                    unknownV
                                ]
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        legend: { display: true },
                        tooltips: {
                            callbacks: {
                                label: function(tooltipItem, chartData) {
                                    var label = chartData.labels[tooltipItem.index] || '';
                                    var dataset = chartData.datasets[tooltipItem.datasetIndex];
                                    var value = parseInt(dataset.data[tooltipItem.index] || 0, 10);
                                    var total = 0;
                                    if (Array.isArray(dataset.data)) {
                                        for (var i = 0; i < dataset.data.length; i++) {
                                            total += parseInt(dataset.data[i] || 0, 10);
                                        }
                                    }
                                    return label + ' — ' + tpOpsPercent(value, total) + '%';
                                }
                            }
                        }
                    }
                });

                // Policy hint
                if (pwSecChart && pwSecChart.policy) {
                    var policyText = "<?php echo addslashes($lang->get('ops_items_password_policy')); ?>";
                    var minComplexity = pwSecChart.policy.min_complexity_label
                        ? pwSecChart.policy.min_complexity_label + ' (' + pwSecChart.policy.min_complexity + ')'
                        : pwSecChart.policy.min_complexity;
                    $('#tp-items-password-policy').text(
                        policyText
                            .replace('{min_len}', pwSecChart.policy.min_len)
                            .replace('{min_complexity}', minComplexity)
                    );
                } else {
                    $('#tp-items-password-policy').text('');
                }
            }

            // HIBP status
            if (tpOpsCharts.itemsHibp) {
                tpOpsCharts.itemsHibp.destroy();
            }
            var hibpChart = data.items && data.items.hibp ? data.items.hibp : null;
            var ctxHibpEl = document.getElementById('tp-items-hibp-chart');
            if (ctxHibpEl) {
                var hibpSafe = hibpChart && hibpChart.safe !== undefined ? parseInt(hibpChart.safe, 10) : 0;
                var hibpPwned = hibpChart && hibpChart.pwned !== undefined ? parseInt(hibpChart.pwned, 10) : 0;
                var hibpUnknown = hibpChart && hibpChart.unknown !== undefined ? parseInt(hibpChart.unknown, 10) : 0;
                if (isNaN(hibpSafe)) hibpSafe = 0;
                if (isNaN(hibpPwned)) hibpPwned = 0;
                if (isNaN(hibpUnknown)) hibpUnknown = 0;

                tpOpsCharts.itemsHibp = new Chart(ctxHibpEl.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: [
                            '<?php echo addslashes($lang->get('ops_hibp_safe')); ?>' + ' (' + hibpSafe + ')',
                            '<?php echo addslashes($lang->get('ops_hibp_pwned')); ?>' + ' (' + hibpPwned + ')',
                            '<?php echo addslashes($lang->get('ops_hibp_unknown')); ?>' + ' (' + hibpUnknown + ')'
                        ],
                        datasets: [
                            {
                                backgroundColor: [tpOpsPalette().greenFill, tpOpsPalette().redFill, tpOpsPalette().grayFill],
                                borderColor: [tpOpsPalette().green, tpOpsPalette().red, tpOpsPalette().gray],
                                borderWidth: 1,
                                data: [hibpSafe, hibpPwned, hibpUnknown]
                            }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: false, legend: { display: true } }
                });

                if (hibpChart && parseInt(hibpChart.enabled || 0, 10) === 1) {
                    $('#tp-items-hibp-note').text((hibpChart.stale || 0) + " <?php echo addslashes($lang->get('ops_hibp_stale')); ?> - " + "<?php echo addslashes($lang->get('ops_hibp_interval')); ?>".replace('{days}', hibpChart.interval_days || 0));
                } else {
                    $('#tp-items-hibp-note').text("<?php echo addslashes($lang->get('ops_hibp_disabled')); ?>");
                }
            }

            // Complexity distribution
            if (tpOpsCharts.itemsComplexity) {
                tpOpsCharts.itemsComplexity.destroy();
            }
            var comp = data.items && data.items.complexity ? data.items.complexity : {labels:[], counts:[]};
            var ctxICEl = document.getElementById('tp-items-complexity-chart');
            if (ctxICEl) {
                var complexityCounts = (comp.counts || []).map(function(value) {
                    return parseInt(value || 0, 10);
                });
                var complexityTotal = complexityCounts.reduce(function(total, value) {
                    return total + value;
                }, 0);
                var complexityLabels = (comp.labels || []).map(function(label, index) {
                    var count = complexityCounts[index] || 0;
                    return label + ' — ' + count + ' (' + tpOpsPercent(count, complexityTotal) + '%)';
                });
                var strengthColors = [
                    { background: 'rgba(220, 53, 69, 0.22)', border: 'rgba(220, 53, 69, 1)' },
                    { background: 'rgba(255, 159, 64, 0.24)', border: 'rgba(255, 159, 64, 1)' },
                    { background: 'rgba(255, 193, 7, 0.25)', border: 'rgba(214, 158, 0, 1)' },
                    { background: 'rgba(40, 167, 69, 0.20)', border: 'rgba(40, 167, 69, 1)' },
                    { background: 'rgba(25, 135, 84, 0.28)', border: 'rgba(25, 135, 84, 1)' }
                ];
                var strengthValues = [
                    parseInt('<?php echo (int) TP_PW_STRENGTH_1; ?>', 10),
                    parseInt('<?php echo (int) TP_PW_STRENGTH_2; ?>', 10),
                    parseInt('<?php echo (int) TP_PW_STRENGTH_3; ?>', 10),
                    parseInt('<?php echo (int) TP_PW_STRENGTH_4; ?>', 10),
                    parseInt('<?php echo (int) TP_PW_STRENGTH_5; ?>', 10)
                ];
                var complexityBackgrounds = [];
                var complexityBorders = [];
                (comp.values || []).forEach(function(value) {
                    var numericValue = parseInt(value, 10);
                    if (numericValue < 0) {
                        complexityBackgrounds.push(tpOpsPalette().grayFill);
                        complexityBorders.push(tpOpsPalette().gray);
                        return;
                    }

                    var strengthIndex = strengthValues.indexOf(numericValue);
                    if (strengthIndex < 0) {
                        strengthIndex = 0;
                        for (var index = 0; index < strengthValues.length; index++) {
                            if (numericValue >= strengthValues[index]) {
                                strengthIndex = index;
                            }
                        }
                    }
                    var color = strengthColors[Math.min(strengthIndex, strengthColors.length - 1)];
                    complexityBackgrounds.push(color.background);
                    complexityBorders.push(color.border);
                });

                tpOpsCharts.itemsComplexity = new Chart(ctxICEl.getContext('2d'), {
                    type: 'horizontalBar',
                    data: {
                        labels: complexityLabels,
                        datasets: [
                            {
                                label: '<?php echo addslashes($lang->get('items')); ?>',
                                data: complexityCounts,
                                backgroundColor: complexityBackgrounds,
                                borderColor: complexityBorders,
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        legend: { display: false },
                        tooltips: {
                            callbacks: {
                                label: function(tooltipItem, chartData) {
                                    var dataset = chartData.datasets[tooltipItem.datasetIndex];
                                    var value = parseInt(dataset.data[tooltipItem.index] || 0, 10);
                                    return value + ' (' + tpOpsPercent(value, complexityTotal) + '%)';
                                }
                            }
                        },
                        scales: {
                            xAxes: [{ ticks: { beginAtZero: true, precision: 0 } }],
                            yAxes: [{ gridLines: { display: false } }]
                        }
                    }
                });
            }
        }

        renderTopCopiedItems(data);
        renderLaprStatistics(data);
    }

    function renderLaprStatistics(data) {
        var lapr = data && data.lapr ? data.lapr : {};
        var endpoints = lapr.endpoints || {};
        var accounts = lapr.accounts || {};
        var rotations = lapr.rotations || {};
        var $availability = $('#tp-lapr-availability');
        $('#tp-ops-lapr-tab').closest('li').show();

        if (lapr.enabled === false) {
            $('#tp-lapr-content').hide();
            $('#tp-lapr-kpi-endpoints,#tp-lapr-kpi-accounts,#tp-lapr-kpi-rotations,#tp-lapr-kpi-success-rate').text('—');
            $('#tp-lapr-retention-warning,#tp-lapr-worker-warning').hide();
            $('#tp-lapr-endpoints-body').html("<tr><td colspan='5' class='text-center text-muted'><?php echo addslashes($lang->get('lapr_monitor_module_disabled')); ?></td></tr>");
            ['laprRotations', 'laprStates', 'laprFailures', 'laprPolicies'].forEach(function(chartKey) {
                if (tpOpsCharts[chartKey]) {
                    tpOpsCharts[chartKey].destroy();
                    tpOpsCharts[chartKey] = null;
                }
            });
            $availability.removeClass('alert-danger alert-warning').addClass('alert-info')
                .text("<?php echo addslashes($lang->get('lapr_monitor_module_disabled')); ?>").show();
            return;
        }

        $('#tp-lapr-content').show();

        if (lapr.error) {
            $availability.removeClass('alert-info alert-warning').addClass('alert-danger')
                .text("<?php echo addslashes($lang->get('lapr_monitor_query_failed')); ?>").show();
        } else if (!lapr.available) {
            $availability.removeClass('alert-info alert-warning').addClass(lapr.enabled ? 'alert-danger' : 'alert-info')
                .text(lapr.enabled
                    ? "<?php echo addslashes($lang->get('lapr_monitor_schema_missing')); ?>"
                    : "<?php echo addslashes($lang->get('lapr_monitor_module_disabled')); ?>")
                .show();
        } else if (!lapr.enabled) {
            $availability.removeClass('alert-danger alert-warning').addClass('alert-info')
                .text("<?php echo addslashes($lang->get('lapr_monitor_module_disabled')); ?>").show();
        } else if (parseInt(accounts.total || 0, 10) === 0) {
            $availability.removeClass('alert-danger alert-warning').addClass('alert-info')
                .text("<?php echo addslashes($lang->get('lapr_monitor_no_accounts')); ?>").show();
        } else {
            $availability.hide().text('');
        }

        $('#tp-lapr-kpi-endpoints').text((endpoints.active || 0) + '/' + (endpoints.total || 0));
        $('#tp-lapr-kpi-accounts').text((accounts.compliant || 0) + '/' + (accounts.total || 0));
        $('#tp-lapr-kpi-rotations').text(rotations.total !== undefined ? rotations.total : 0);
        $('#tp-lapr-kpi-success-rate').text(rotations.success_rate === null || rotations.success_rate === undefined ? '-' : rotations.success_rate + '%');

        $('#tp-lapr-retention-warning').toggle(!!(lapr.period && lapr.period.retention_limited));
        var workerFailures = parseInt(rotations.worker_failures || 0, 10);
        $('#tp-lapr-worker-warning')
            .text(tpOpsText("<?php echo addslashes($lang->get('ops_lapr_worker_failures')); ?>", {count: workerFailures}))
            .toggle(workerFailures > 0);

        var topEndpoints = Array.isArray(lapr.top_endpoints) ? lapr.top_endpoints : [];
        var endpointRows = '';
        if (topEndpoints.length === 0) {
            endpointRows = "<tr><td colspan='5' class='text-center text-muted'><?php echo addslashes($lang->get('ops_lapr_no_problem_endpoints')); ?></td></tr>";
        } else {
            $.each(topEndpoints, function(_, endpoint) {
                endpointRows += '<tr>' +
                    '<td>' + escapeHtml(endpoint.label || ('#' + endpoint.id)) + '</td>' +
                    '<td class="text-center">' + parseInt(endpoint.successes || 0, 10) + '</td>' +
                    '<td class="text-center">' + parseInt(endpoint.failures || 0, 10) + '</td>' +
                    '<td class="text-center">' + (endpoint.success_rate === null ? '-' : endpoint.success_rate + '%') + '</td>' +
                    '<td class="text-center">' + escapeHtml(tpOpsLaprDate(endpoint.last_failure_at)) + '</td>' +
                    '</tr>';
            });
        }
        $('#tp-lapr-endpoints-body').html(endpointRows);

        if (typeof Chart === 'undefined') {
            return;
        }

        if (tpOpsCharts.laprRotations) tpOpsCharts.laprRotations.destroy();
        var rotationCanvas = document.getElementById('tp-lapr-rotation-chart');
        var rotationSeries = rotations.series || {labels: [], successes: [], failures: []};
        if (rotationCanvas) {
            tpOpsCharts.laprRotations = new Chart(rotationCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: rotationSeries.labels || [],
                    datasets: [
                        { label: "<?php echo addslashes($lang->get('ops_lapr_successes')); ?>", data: rotationSeries.successes || [], backgroundColor: tpOpsPalette().greenFill, borderColor: tpOpsPalette().green, borderWidth: 1 },
                        { label: "<?php echo addslashes($lang->get('ops_lapr_failures')); ?>", data: rotationSeries.failures || [], backgroundColor: tpOpsPalette().redFill, borderColor: tpOpsPalette().red, borderWidth: 1 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { display: true },
                    scales: {
                        xAxes: [{ stacked: true, ticks: { autoSkip: true, maxRotation: 0 } }],
                        yAxes: [{ stacked: true, ticks: { beginAtZero: true, precision: 0 } }]
                    }
                }
            });
        }

        if (tpOpsCharts.laprStates) tpOpsCharts.laprStates.destroy();
        var stateCanvas = document.getElementById('tp-lapr-state-chart');
        if (stateCanvas) {
            tpOpsCharts.laprStates = new Chart(stateCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: [
                        "<?php echo addslashes($lang->get('lapr_monitor_state_healthy')); ?>",
                        "<?php echo addslashes($lang->get('lapr_monitor_state_scheduled')); ?>",
                        "<?php echo addslashes($lang->get('lapr_monitor_state_manual_only')); ?>",
                        "<?php echo addslashes($lang->get('lapr_monitor_state_retrying')); ?>",
                        "<?php echo addslashes($lang->get('lapr_monitor_state_overdue')); ?>",
                        "<?php echo addslashes($lang->get('lapr_monitor_state_error')); ?>",
                        "<?php echo addslashes($lang->get('lapr_monitor_state_paused')); ?>"
                    ],
                    datasets: [{
                        data: [accounts.healthy || 0, accounts.scheduled || 0, accounts.manual_only || 0, accounts.retrying || 0, accounts.overdue || 0, accounts.error || 0, accounts.paused || 0],
                        backgroundColor: [tpOpsPalette().greenFill, tpOpsPalette().blueFill, tpOpsPalette().grayFill, tpOpsPalette().orangeFill, tpOpsPalette().purpleFill, tpOpsPalette().redFill, tpOpsPalette().grayFill],
                        borderColor: [tpOpsPalette().green, tpOpsPalette().blue, tpOpsPalette().gray, tpOpsPalette().orange, tpOpsPalette().purple, tpOpsPalette().red, tpOpsPalette().gray],
                        borderWidth: 1
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, legend: { display: true } }
            });
        }

        var failureNames = {
            connectivity: "<?php echo addslashes($lang->get('lapr_monitor_failure_connectivity')); ?>",
            authentication: "<?php echo addslashes($lang->get('lapr_monitor_failure_authentication')); ?>",
            hostkey: "<?php echo addslashes($lang->get('lapr_monitor_failure_hostkey')); ?>",
            password_change: "<?php echo addslashes($lang->get('lapr_monitor_failure_password_change')); ?>",
            synchronization: "<?php echo addslashes($lang->get('lapr_monitor_failure_synchronization')); ?>",
            password_generation: "<?php echo addslashes($lang->get('lapr_monitor_failure_password_generation')); ?>",
            encryption: "<?php echo addslashes($lang->get('lapr_monitor_failure_encryption')); ?>",
            configuration: "<?php echo addslashes($lang->get('lapr_monitor_failure_configuration')); ?>",
            other: "<?php echo addslashes($lang->get('lapr_monitor_failure_other')); ?>"
        };
        var failureCategories = Array.isArray(lapr.failure_categories) ? lapr.failure_categories : [];
        if (tpOpsCharts.laprFailures) tpOpsCharts.laprFailures.destroy();
        var failureCanvas = document.getElementById('tp-lapr-failure-chart');
        if (failureCanvas) {
            tpOpsCharts.laprFailures = new Chart(failureCanvas.getContext('2d'), {
                type: 'horizontalBar',
                data: {
                    labels: failureCategories.map(function(row) { return failureNames[row.category] || failureNames.other; }),
                    datasets: [{
                        label: "<?php echo addslashes($lang->get('ops_lapr_failures')); ?>",
                        data: failureCategories.map(function(row) { return parseInt(row.count || 0, 10); }),
                        backgroundColor: tpOpsPalette().redFill,
                        borderColor: tpOpsPalette().red,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { display: false },
                    scales: { xAxes: [{ ticks: { beginAtZero: true, precision: 0 } }], yAxes: [{ gridLines: { display: false } }] }
                }
            });
        }

        var policies = Array.isArray(lapr.policies) ? lapr.policies : [];
        if (tpOpsCharts.laprPolicies) tpOpsCharts.laprPolicies.destroy();
        var policyCanvas = document.getElementById('tp-lapr-policy-chart');
        if (policyCanvas) {
            tpOpsCharts.laprPolicies = new Chart(policyCanvas.getContext('2d'), {
                type: 'horizontalBar',
                data: {
                    labels: policies.map(function(row) { return row.label || '-'; }),
                    datasets: [{
                        label: "<?php echo addslashes($lang->get('lapr_accounts')); ?>",
                        data: policies.map(function(row) { return parseInt(row.count || 0, 10); }),
                        backgroundColor: tpOpsPalette().blueFill,
                        borderColor: tpOpsPalette().blue,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { display: false },
                    scales: { xAxes: [{ ticks: { beginAtZero: true, precision: 0 } }], yAxes: [{ gridLines: { display: false } }] }
                }
            });
        }
    }

    function tpOpsLaprDate(value) {
        if (!value) return '-';
        var date = new Date(String(value).replace(' ', 'T'));
        return isNaN(date.getTime()) ? String(value) : date.toLocaleString(navigator.language || undefined);
    }

    function renderTopCopiedItems(data) {
        var topCopied = (data.items && data.items.top_copied) ? data.items.top_copied : [];
        var rows = "";

        if (topCopied.length === 0) {
            rows = "<tr><td colspan='6' class='text-center text-muted'><?php echo addslashes($lang->get('ops_no_data')); ?></td></tr>";
        } else {
            $.each(topCopied, function(_, r) {
                rows += "<tr>" +
                    "<td>" + escapeHtml(r.label ? r.label : ("item#" + r.id)) + "</td>" +
                    "<td>" + escapeHtml(r.folder_title ? r.folder_title : "-") + "</td>" +
                    "<td class='text-center'>" + (parseInt(r.perso || 0, 10) === 1 ? "<?php echo addslashes($lang->get('yes')); ?>" : "<?php echo addslashes($lang->get('no')); ?>") + "</td>" +
                    "<td class='text-center'>" + (r.copies !== undefined ? r.copies : '-') + "</td>" +
                    "<td class='text-center'>" + (r.users_unique !== undefined ? r.users_unique : '-') + "</td>" +
                    "<td class='text-center'>" + (r.last_activity ? fmtTs(r.last_activity) : '-') + "</td>" +
                    "</tr>";
            });
        }

        $('#tp-items-topcopied-body').html(rows);
    }

    function renderUserRanking(data) {
        var metric = $('#tp-users-ranking-metric').val() || 'overall';
        var metricConfig = {
            overall: {
                field: 'activity_total',
                label: '<?php echo addslashes($lang->get('ops_users_ranking_overall')); ?>'
            },
            views: {
                field: 'views',
                label: '<?php echo addslashes($lang->get('ops_metric_views')); ?>'
            },
            created: {
                field: 'created',
                label: '<?php echo addslashes($lang->get('ops_metric_created')); ?>'
            },
            modified: {
                field: 'modified',
                label: '<?php echo addslashes($lang->get('ops_metric_modified')); ?>'
            },
            password_actions: {
                field: 'password_actions',
                label: '<?php echo addslashes($lang->get('ops_users_ranking_password_actions')); ?>'
            }
        };
        var config = metricConfig[metric] || metricConfig.overall;
        var rankings = data.users && data.users.rankings ? data.users.rankings : {};
        var rows = rankings[metric] || [];
        var chartLabels = [];
        var chartValues = [];
        var tableRows = '';

        $('#tp-users-ranking-value-label').text(config.label);

        if (rows.length === 0) {
            tableRows = "<tr><td colspan='3' class='text-center text-muted'><?php echo addslashes($lang->get('ops_no_data')); ?></td></tr>";
        } else {
            $.each(rows.slice(0, 5), function(index, user) {
                var fullName = $.trim(((user.name || '') + ' ' + (user.lastname || '')));
                var login = user.login || ('user#' + user.id);
                var displayName = fullName || login;
                var chartLabel = fullName && fullName !== login
                    ? fullName + ' (' + login + ')'
                    : login;
                var value = parseInt(user[config.field] || 0, 10);
                var loginDetail = fullName && fullName !== login
                    ? "<small class='tp-users-ranking-login'>" + escapeHtml(login) + "</small>"
                    : '';

                chartLabels.push(chartLabel);
                chartValues.push(value);
                tableRows += "<tr>" +
                    "<td class='text-center'><span class='badge badge-light tp-users-ranking-position'>" + (index + 1) + "</span></td>" +
                    "<td><strong>" + escapeHtml(displayName) + "</strong>" + loginDetail + "</td>" +
                    "<td class='text-right font-weight-bold'>" + value + "</td>" +
                    "</tr>";
            });
        }
        $('#tp-users-ranking-body').html(tableRows);

        if (tpOpsCharts.usersRanking) {
            tpOpsCharts.usersRanking.destroy();
            tpOpsCharts.usersRanking = null;
        }
        var rankingChart = document.getElementById('tp-users-ranking-chart');
        if (typeof Chart === 'undefined' || !rankingChart || chartValues.length === 0) {
            return;
        }

        tpOpsCharts.usersRanking = new Chart(rankingChart.getContext('2d'), {
            type: 'horizontalBar',
            data: {
                labels: chartLabels,
                datasets: [
                    {
                        label: config.label,
                        data: chartValues,
                        backgroundColor: tpOpsPalette().blueFill,
                        borderColor: tpOpsPalette().blue,
                        borderWidth: 1,
                        maxBarThickness: 34
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                tooltips: {
                    callbacks: {
                        label: function(tooltipItem, chartData) {
                            var dataset = chartData.datasets[tooltipItem.datasetIndex];
                            return config.label + ': ' + parseInt(dataset.data[tooltipItem.index] || 0, 10);
                        }
                    }
                },
                scales: {
                    xAxes: [{ ticks: { beginAtZero: true, precision: 0 } }],
                    yAxes: [{ gridLines: { display: false } }]
                }
            }
        });
    }

    function renderOperationalSummary(data, summary) {
        var actions = summary.actions || {};
        var inv = summary.inventory || {};
        var created = summary.created || {};
        var hibp = summary.hibp || {};
        var pwSec = summary.passwordSecurity || {};

        var totalEnabled = parseInt(summary.totalEnabled || 0, 10);
        var active = parseInt(summary.active || 0, 10);
        var inactive = parseInt(summary.inactive || 0, 10);
        var pwned = parseInt(hibp.pwned || 0, 10);
        var hibpUnknown = parseInt(hibp.unknown || 0, 10);
        var unknownComplexity = parseInt(inv.unknown_complexity || 0, 10);
        var weakCreated = parseInt(created.weak_complexity || 0, 10);
        var secureScore = pwSec && pwSec.secure_score !== undefined && pwSec.secure_score !== null ? parseInt(pwSec.secure_score, 10) : null;

        if (isNaN(pwned)) pwned = 0;
        if (isNaN(hibpUnknown)) hibpUnknown = 0;
        if (isNaN(unknownComplexity)) unknownComplexity = 0;
        if (isNaN(weakCreated)) weakCreated = 0;
        if (secureScore !== null && isNaN(secureScore)) secureScore = null;

        var health = 'good';
        var healthText = "<?php echo addslashes($lang->get('ops_health_good')); ?>";
        if (pwned > 0 || (secureScore !== null && secureScore < 70)) {
            health = 'critical';
            healthText = "<?php echo addslashes($lang->get('ops_health_critical')); ?>";
        } else if (weakCreated > 0 || unknownComplexity > 0 || hibpUnknown > 0) {
            health = 'warning';
            healthText = "<?php echo addslashes($lang->get('ops_health_warning')); ?>";
        }

        $('#tp-overview-health-card')
            .removeClass('tp-ops-health-good tp-ops-health-warning tp-ops-health-critical')
            .addClass('tp-ops-health-' + health);
        $('#tp-overview-health').text(healthText);

        var healthDetail = secureScore !== null
            ? secureScore + "/100 <?php echo addslashes($lang->get('ops_kpi_items_secure_score')); ?>"
            : "<?php echo addslashes($lang->get('ops_health_no_score')); ?>";
        if (pwned > 0) {
            healthDetail += " - " + pwned + " <?php echo addslashes($lang->get('ops_hibp_pwned')); ?>";
        }
        $('#tp-overview-health-detail').text(healthDetail);

        var apiViews = parseInt(actions.api_views || 0, 10);
        var views = parseInt(actions.views || 0, 10);
        var apiShare = views > 0 ? Math.round((apiViews / Math.max(1, views)) * 100) : 0;
        $('#tp-overview-api-share').text(apiShare + "%");
        $('#tp-overview-api-detail').text(apiViews + " / " + views + " <?php echo addslashes($lang->get('ops_metric_views_short')); ?>");

        var priorities = [];
        if (pwned > 0) {
            priorities.push({
                level: 'danger',
                icon: 'fas fa-exclamation-circle',
                title: tpOpsText("<?php echo addslashes($lang->get('ops_priority_pwned')); ?>", {count: pwned}),
                detail: "<?php echo addslashes($lang->get('ops_priority_action_passwords')); ?>"
            });
        }
        if (weakCreated > 0) {
            priorities.push({
                level: 'warning',
                icon: 'fas fa-unlock-alt',
                title: tpOpsText("<?php echo addslashes($lang->get('ops_priority_weak_created')); ?>", {count: weakCreated}),
                detail: "<?php echo addslashes($lang->get('ops_priority_action_quality')); ?>"
            });
        }
        if (unknownComplexity > 0) {
            priorities.push({
                level: 'warning',
                icon: 'fas fa-question-circle',
                title: tpOpsText("<?php echo addslashes($lang->get('ops_priority_unknown_complexity')); ?>", {count: unknownComplexity}),
                detail: "<?php echo addslashes($lang->get('ops_priority_action_quality')); ?>"
            });
        }
        if (hibpUnknown > 0) {
            priorities.push({
                level: 'info',
                icon: 'fas fa-user-secret',
                title: tpOpsText("<?php echo addslashes($lang->get('ops_priority_hibp_unknown')); ?>", {count: hibpUnknown}),
                detail: "<?php echo addslashes($lang->get('ops_priority_action_coverage')); ?>"
            });
        }
        if (inactive > 0) {
            priorities.push({
                level: 'info',
                icon: 'fas fa-user-clock',
                title: tpOpsText("<?php echo addslashes($lang->get('ops_priority_inactive_users')); ?>", {count: inactive}),
                detail: "<?php echo addslashes($lang->get('ops_priority_action_usage')); ?>"
            });
        }
        var rows = "";
        if (priorities.length === 0) {
            rows = "<div class='tp-ops-priority-item tp-ops-priority-ok'>" +
                "<i class='fas fa-check-circle'></i>" +
                "<div><strong><?php echo addslashes($lang->get('ops_priority_ok')); ?></strong>" +
                "<span><?php echo addslashes($lang->get('ops_priority_ok_detail')); ?></span></div>" +
                "</div>";
        } else {
            $.each(priorities.slice(0, 5), function(_, p) {
                rows += "<div class='tp-ops-priority-item tp-ops-priority-" + p.level + "'>" +
                    "<i class='" + p.icon + "'></i>" +
                    "<div><strong>" + escapeHtml(p.title) + "</strong><span>" + escapeHtml(p.detail) + "</span></div>" +
                    "</div>";
            });
        }
        $('#tp-ops-priority-list').html(rows);
    }

    function tpOpsText(template, values) {
        return String(template).replace(/\{([a-zA-Z0-9_]+)\}/g, function(_, key) {
            return values[key] !== undefined ? values[key] : '';
        });
    }

    function fmtTs(ts) {
        try {
            var d = new Date(parseInt(ts, 10) * 1000);
            return d.toLocaleString((navigator.language || undefined));
        } catch (e) {
            return "-";
        }
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
</script>
