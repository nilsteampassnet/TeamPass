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
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    // calculate statistic values
    showStatsValues();
    // Operational statistics dashboard (V2)
    initOperationalStatistics();


    // Robust tab navigation (do not rely on Bootstrap tab plugin to prevent anchor scrolling)
    (function bindTpStatisticsTabs() {
        function getTarget($a) {
            var t = $a.attr('data-target') || $a.data('target') || $a.attr('href');
            if (!t) return null;
            // Accept '#id' or 'javascript:void(0)' with data-target
            if (typeof t === 'string' && t.charAt(0) === '#') return t;
            return null;
        }

        function activateTab($a, $content) {
            var target = getTarget($a);
            if (!target || $content.length === 0) return;

            // Activate nav link
            $a.closest('ul').find('a.nav-link').removeClass('active');
            $a.addClass('active');

            // Show target pane, hide others
            $content.children('.tab-pane').removeClass('active show');
            $(target).addClass('active show');

            // If switching back to Operational tab, re-render charts from cache (no extra SQL call)
            if (target === '#tp-stats-main-ops') {
                try { tpOpsRerenderFromCache(); } catch (e) {}
            } else if (target === '#tp-ops-users' || target === '#tp-ops-roles' || target === '#tp-ops-items') {
                try { tpOpsRerenderFromCache(); } catch (e) {}
            }
        }

        // Main tabs: Operational / Legacy
        $(document).on('click', '#tp-stats-main-tabs a.nav-link', function(e) {
            e.preventDefault();
            activateTab($(this), $('#tp-stats-main-tabs-content'));
        });

        // Sub tabs inside Operational: Users / Roles / Items
        $(document).on('click', '#tp-ops-tabs a.nav-link', function(e) {
            e.preventDefault();
            activateTab($(this), $('#tp-ops-tabs-content'));
        });
    })();


    // Prepare iCheck format for checkboxes
    $('input[type="checkbox"].flat-blue').iCheck({
        checkboxClass: 'icheckbox_flat-blue',
    });

    // Save on button click
    $(document).on('click', '#statistics-save', function() {
        // SHow user
        toastr.remove();
        toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // Send query
        saveOptions();
    });

    // Select/Deselect button
    $('#cb_select_all')
        .on('ifChecked', function() {
            $(".stat_option").iCheck('check');
        })
        .on('ifUnchecked', function() {
            $(".stat_option").iCheck('uncheck');
        });


    /**
     * Get the values to be shared when statistics enabled
     *
     * @return void
     */
    function showStatsValues() {
        // send query
        $.post(
            "sources/admin.queries.php", {
                type: "get_values_for_statistics",
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                try {
                    data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                } catch (e) {
                    // error
                    $("#message_box").html("An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />" + data).show().fadeOut(4000);

                    return;
                }
                if (data.error === "") {
                    $("#value_items").html(data.stat_items);
                    var ips = "";
                    $.each(data.stat_country, function(index, value) {
                        if (value > 0) {
                            if (ips === "") ips = index + ":" + value;
                            else ips += " ; " + index + ":" + value;
                        }
                    });
                    $("#value_country").html(ips);
                    $("#value_folders").html(data.stat_folders);
                    $("#value_items_shared").html(data.stat_items_shared);
                    $("#value_folders_shared").html(data.stat_folders_shared);
                    $("#value_php").html(data.stat_phpversion);
                    $("#value_users").html(data.stat_users);
                    $("#value_admin").html(data.stat_admins);
                    $("#value_manager").html(data.stat_managers);
                    $("#value_ro").html(data.stat_ro);
                    $("#value_teampassv").html(data.stat_teampassversion);
                    $("#value_duo").html(data.stat_duo);
                    $("#value_kb").html(data.stat_kb);
                    $("#value_pf").html(data.pf);
                    $("#value_ldap").html(data.stat_ldap);
                    $("#value_agses").html(data.stat_agses);
                    $("#value_suggestion").html(data.stat_suggestion);
                    $("#value_api").html(data.stat_api);
                    $("#value_customfields").html(data.stat_customfields);
                    $("#value_syslog").html(data.stat_syslog);
                    $("#value_2fa").html(data.stat_2fa);
                    $("#value_https").html(data.stat_stricthttps);
                    $("#value_mysql").html(data.stat_mysqlversion);
                    $("#value_pf").html(data.stat_pf);
                    $("#value_fav").html(data.stat_fav);
                    var langs = "";
                    $.each(data.stat_languages, function(index, value) {
                        if (value > 0) {
                            if (langs === "") langs = index + ":" + value;
                            else langs += " ; " + index + ":" + value;
                        }
                    });
                    $("#value_languages").html(langs);
                }
            }
        );
    }

    /**
     * Permits to save the statistics to be shared
     *
     * @return void
     */
    function saveOptions() {
        var list = "";
        $(".stat_option:checked").each(function() {
            list += $(this).attr("id") + ";";
        });

        // store in DB
        $.post(
            "sources/admin.queries.php", {
                type: "save_sending_statistics",
                list: list,
                status: $("#send_stats_input").val(),
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                if (data[0].error === false) {
                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );

                    // if enabled, then send stats right now
                    if (parseInt($("#send_stats_input").val()) === 1) {
                        // send statistics
                        $.post(
                            "sources/main.queries.php", {
                                type: "sending_statistics",
                                key: "<?php echo $session->get('key'); ?>"
                            }
                        );
                    }
                }
            },
            "json"
        );
    }

    // ----------------------------------------------------------------------------------
    // Operational statistics (3 tabs: Users / Roles / Items)
    // ----------------------------------------------------------------------------------

    var tpOpsCharts = {
        usersActivity: null,
        rolesTop: null,
        itemsPersonal: null,
        itemsComplexity: null
    };

    // Cache last payload to allow instant re-render when tabs become visible (Chart.js needs visible canvases)
    var tpOpsLastData = null;
    var tpOpsLastParams = null;

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

    function tpOpsRerenderFromCache() {
        if (tpOpsLastData) {
            // Re-render without network call, useful when switching tabs (canvas becomes visible)
            renderOperationalStatistics(tpOpsLastData);

            try {
                if (tpOpsCharts.usersActivity) { tpOpsCharts.usersActivity.resize(); }
                if (tpOpsCharts.rolesTop) { tpOpsCharts.rolesTop.resize(); }
                if (tpOpsCharts.itemsPersonal) { tpOpsCharts.itemsPersonal.resize(); }
                if (tpOpsCharts.itemsPasswordCompliance) { tpOpsCharts.itemsPasswordCompliance.resize(); }
                if (tpOpsCharts.itemsComplexity) { tpOpsCharts.itemsComplexity.resize(); }
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

        // Some instances use iCheck (ifChanged), others rely on native change
        $(document).on('change ifChanged', '#tp-ops-include-personal, #tp-ops-include-api', function() {
            loadOperationalStatistics();
        });

        // When switching between operational sub-tabs, re-render from cache (no need to click refresh)
        $(document).on('shown.bs.tab', '#tp-ops-tabs a[data-toggle="tab"]', function() {
            tpOpsRerenderFromCache();
        });

        // Ensure main page tabs always switch (some instances may not have Bootstrap tab data-API enabled)
        $(document).on('click', '#tp-stats-main-tabs a[data-toggle="tab"]', function(e) {
            e.preventDefault();
            try { $(this).tab('show'); } catch (err) {}
        });

        // When switching between main page tabs (Operational / Legacy), scroll to top so the newly displayed panel is visible

        $(document).on('shown.bs.tab', '#tp-stats-main-tabs a[data-toggle="tab"]', function(e) {
            var href = $(e.target).attr('href');
            // Bring the tab strip into view to avoid the "nothing happens" effect when switching while scrolled down
            try {
                var top = $('#tp-stats-main-tabs').offset().top;
                $('html, body').stop(true).animate({scrollTop: Math.max(0, top - 80)}, 200);
            } catch (err) {}
            if (href === '#tp-stats-main-ops') {
                tpOpsEnsureLoadedOrRerender();
            }
        });

        // Ensure Chart.js is available and load first view (only if the operational tab is visible/active)
        ensureChartJs(function() {
            if ($('#tp-stats-main-ops').hasClass('active') || $('#tp-stats-main-ops').hasClass('show') || $('#tp-stats-main-ops').length === 0) {
                tpOpsEnsureLoadedOrRerender();
            }
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
            period: $('#tp-ops-period').val(),
            include_personal: $('#tp-ops-include-personal').is(':checked') ? 1 : 0,
            include_api: $('#tp-ops-include-api').is(':checked') ? 1 : 0,
            top_users_limit: 15,
            top_roles_limit: 15,
            top_items_limit: 20
        };

        tpOpsLastParams = payload;

        // UI loading state
        $('#tp-ops-refresh').prop('disabled', true);
        $('#tp-ops-refresh i').addClass('fa-spin');
        $('#tp-users-top-body').html("<tr><td colspan='9' class='text-center text-muted'><i class='fas fa-circle-notch fa-spin'></i></td></tr>");
        $('#tp-roles-top-body').html("<tr><td colspan='7' class='text-center text-muted'><i class='fas fa-circle-notch fa-spin'></i></td></tr>");
        $('#tp-items-topcopied-body').html("<tr><td colspan='6' class='text-center text-muted'><i class='fas fa-circle-notch fa-spin'></i></td></tr>");

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
        );
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

        $('#tp-kpi-users-active-ratio').text(totalEnabled > 0 ? Math.round((active / totalEnabled) * 100) + "% <?php echo addslashes($lang->get('ops_users_ratio_active')); ?>" : "");
        $('#tp-kpi-users-inactive-ratio').text(totalEnabled > 0 ? Math.round((inactive / totalEnabled) * 100) + "% <?php echo addslashes($lang->get('ops_users_ratio_inactive')); ?>" : "");

        $('#tp-kpi-connections-web').text((data.users.connections && data.users.connections.web) ? data.users.connections.web : 0);
        $('#tp-kpi-connections-api').text((data.users.connections && data.users.connections.api) ? data.users.connections.api : 0);

        $('#tp-kpi-views-total').text((data.users.actions && data.users.actions.views) ? data.users.actions.views : 0);
        $('#tp-kpi-copies-total').text((data.users.actions && data.users.actions.copies) ? data.users.actions.copies : 0);
        $('#tp-kpi-pwshown-total').text((data.users.actions && data.users.actions.pw_shown) ? data.users.actions.pw_shown : 0);
        $('#tp-kpi-created-total').text((data.users.actions && data.users.actions.created) ? data.users.actions.created : 0);
        $('#tp-kpi-modified-total').text((data.users.actions && data.users.actions.modified) ? data.users.actions.modified : 0);

        // Users chart
        if (typeof Chart !== 'undefined') {
            if (tpOpsCharts.usersActivity) {
                tpOpsCharts.usersActivity.destroy();
            }
            var ctxU = document.getElementById('tp-users-activity-chart').getContext('2d');
            var pal = tpOpsPalette();
            tpOpsCharts.usersActivity = new Chart(ctxU, {
                type: 'line',
                data: {
                    labels: (data.users.series && data.users.series.labels) ? data.users.series.labels : [],
                    datasets: [
                        { label: '<?php echo addslashes($lang->get('ops_metric_views')); ?>', data: (data.users.series && data.users.series.views) ? data.users.series.views : [], borderColor: pal.blue, backgroundColor: pal.blueFill, tension: 0.3, pointRadius: 1.5, fill: false },
                        { label: '<?php echo addslashes($lang->get('ops_kpi_copies_total')); ?>', data: (data.users.series && data.users.series.copies) ? data.users.series.copies : [], borderColor: pal.orange, backgroundColor: pal.orangeFill, tension: 0.3, pointRadius: 1.5, fill: false },
                        { label: '<?php echo addslashes($lang->get('ops_metric_pw_shown')); ?>', data: (data.users.series && data.users.series.pw_shown) ? data.users.series.pw_shown : [], borderColor: pal.purple, backgroundColor: pal.purpleFill, tension: 0.3, pointRadius: 1.5, fill: false }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: true } },
                    scales: {
                        x: { ticks: { maxRotation: 0, autoSkip: true } },
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // Top users table
        var urows = "";
        if (data.users.top && data.users.top.length > 0) {
            $.each(data.users.top, function(_, r) {
                var full = (r.login ? r.login : '') + (r.name || r.lastname ? " (" + (r.name ? r.name : '') + " " + (r.lastname ? r.lastname : '') + ")" : "");
                var last = r.last_activity ? fmtTs(r.last_activity) : "-";
                var apiPct = "-";
                if (r.views && r.api_views) {
                    apiPct = Math.round((parseInt(r.api_views, 10) / Math.max(1, parseInt(r.views, 10))) * 100) + "%";
                } else if (r.views) {
                    apiPct = "0%";
                }
                urows += "<tr>" +
                    "<td>" + escapeHtml(full) + "</td>" +
                    "<td class='text-center'>" + (r.score !== undefined ? r.score : '-') + "</td>" +
                    "<td class='text-center'>" + (r.views !== undefined ? r.views : '-') + "</td>" +
                    "<td class='text-center'>" + (r.copies !== undefined ? r.copies : '-') + "</td>" +
                    "<td class='text-center'>" + (r.pw_shown !== undefined ? r.pw_shown : '-') + "</td>" +
                    "<td class='text-center'>" + (r.items_unique !== undefined ? r.items_unique : '-') + "</td>" +
                    "<td class='text-center'>" + (r.folders_unique !== undefined ? r.folders_unique : '-') + "</td>" +
                    "<td class='text-center'>" + last + "</td>" +
                    "<td class='text-center'>" + apiPct + "</td>" +
                    "</tr>";
            });
        } else {
            urows = "<tr><td colspan='9' class='text-center text-muted'><?php echo addslashes($lang->get('ops_no_data')); ?></td></tr>";
        }
        $('#tp-users-top-body').html(urows);

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
                    "<td class='text-center'>" + (r.items_accessible !== undefined ? r.items_accessible : '-') + "</td>" +
                    "<td class='text-center'>" + (r.last_activity ? fmtTs(r.last_activity) : '-') + "</td>" +
                    "</tr>";
            });
        } else {
            rrows = "<tr><td colspan='7' class='text-center text-muted'><?php echo addslashes($lang->get('ops_no_data')); ?></td></tr>";
        }
        $('#tp-roles-top-body').html(rrows);

        if (typeof Chart !== 'undefined') {
            if (tpOpsCharts.rolesTop) {
                tpOpsCharts.rolesTop.destroy();
            }
            var ctxR = document.getElementById('tp-roles-top-chart').getContext('2d');
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
                    plugins: { legend: { display: true } },
                    scales: {
                        x: { ticks: { autoSkip: true, maxRotation: 0 } },
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // ---------------- ITEMS KPIs
        var inv = data.items && data.items.inventory ? data.items.inventory : {};
        $('#tp-kpi-items-total').text(inv.total_active !== undefined ? inv.total_active : 0);
        $('#tp-kpi-items-personal').text(inv.personal_active !== undefined ? inv.personal_active : 0);
        $('#tp-kpi-items-shared').text(inv.shared_active !== undefined ? inv.shared_active : 0);
        $('#tp-kpi-items-complexity-avg').text(inv.avg_complexity !== null && inv.avg_complexity !== undefined ? inv.avg_complexity : "-");
        $('#tp-kpi-items-pwlen-avg').text(inv.avg_pw_len !== null && inv.avg_pw_len !== undefined ? inv.avg_pw_len : "-");
        var pwSec = data.items && data.items.password_security ? data.items.password_security : null;
        if (pwSec && pwSec.secure_score !== undefined && pwSec.secure_score !== null) {
            $('#tp-kpi-items-secure-score').text(pwSec.secure_score + "/100");
            var assessed = parseInt(pwSec.assessed_total || 0, 10);
            var compliant = parseInt(pwSec.compliant || 0, 10);
            if (assessed > 0) {
                var ratio = Math.round((compliant / assessed) * 100);
                $('#tp-kpi-items-secure-details').text(ratio + "% <?php echo addslashes($lang->get('ops_label_compliant')); ?> (" + compliant + "/" + assessed + ")");
            } else {
                $('#tp-kpi-items-secure-details').text("");
            }
        } else {
            $('#tp-kpi-items-secure-score').text("-");
            $('#tp-kpi-items-secure-details').text("");
        }
        $('#tp-kpi-items-stale-90').text(inv.stale_90 !== undefined ? inv.stale_90 : 0);

        if (inv.unknown_complexity !== undefined && inv.total_active !== undefined && parseInt(inv.total_active, 10) > 0) {
            var ratioUnknown = Math.round((parseInt(inv.unknown_complexity, 10) / Math.max(1, parseInt(inv.total_active, 10))) * 100);
            $('#tp-kpi-items-complexity-unknown').text(inv.unknown_complexity + " <?php echo addslashes($lang->get('ops_unknown')); ?> (" + ratioUnknown + "%)");
        } else {
            $('#tp-kpi-items-complexity-unknown').text("");
        }

        // Items charts
        if (typeof Chart !== 'undefined') {
            // Perso vs shared
            if (tpOpsCharts.itemsPersonal) {
                tpOpsCharts.itemsPersonal.destroy();
            }
            var ctxIP = document.getElementById('tp-items-personal-chart').getContext('2d');
            tpOpsCharts.itemsPersonal = new Chart(ctxIP, {
                type: 'doughnut',
                data: {
                    labels: ['<?php echo addslashes($lang->get('personal')); ?>', '<?php echo addslashes($lang->get('shared')); ?>'],
                    datasets: [
                        {
                            backgroundColor: [tpOpsPalette().greenFill, tpOpsPalette().blueFill],
                            borderColor: [tpOpsPalette().green, tpOpsPalette().blue],
                            borderWidth: 1,
                            data: [
                                inv.personal_active !== undefined ? inv.personal_active : 0,
                                inv.shared_active !== undefined ? inv.shared_active : 0
                            ]
                        }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: true } } }
            });

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
                        plugins: {
                            legend: { display: true },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        var label = context.label || '';
                                        var value = context.parsed || 0;
                                        var total = 0;
                                        if (context.dataset && Array.isArray(context.dataset.data)) {
                                            for (var i = 0; i < context.dataset.data.length; i++) {
                                                total += parseInt(context.dataset.data[i] || 0, 10);
                                            }
                                        }
                                        var pct = total > 0 ? Math.round((value / total) * 100) : 0;
                                        return label + ': ' + value + ' (' + pct + '%)';
                                    }
                                }
                            }
                        }
                    }
                });

                // Policy hint
                if (pwSecChart && pwSecChart.policy) {
                    var tpl = "<?php echo addslashes($lang->get('ops_items_password_policy')); ?>";
                    $('#tp-items-password-policy').text(
                        tpl.replace('{min_len}', pwSecChart.policy.min_len).replace('{min_complexity}', pwSecChart.policy.min_complexity)
                    );
                } else {
                    $('#tp-items-password-policy').text('');
                }
            }

            // Complexity distribution
            if (tpOpsCharts.itemsComplexity) {
                tpOpsCharts.itemsComplexity.destroy();
            }
            var comp = data.items && data.items.complexity ? data.items.complexity : {labels:[], counts:[]};
            var ctxIC = document.getElementById('tp-items-complexity-chart').getContext('2d');
            tpOpsCharts.itemsComplexity = new Chart(ctxIC, {
                type: 'bar',
                data: {
                    labels: comp.labels ? comp.labels : [],
                    datasets: [
                        { label: '<?php echo addslashes($lang->get('items')); ?>', data: comp.counts ? comp.counts : [], backgroundColor: tpOpsPalette().purpleFill, borderColor: tpOpsPalette().purple, borderWidth: 1 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }

        // Top copied items table
        var itrows = "";
        if (data.items.top_copied && data.items.top_copied.length > 0) {
            $.each(data.items.top_copied, function(_, r) {
                itrows += "<tr>" +
                    "<td>" + escapeHtml(r.label ? r.label : ("item#" + r.id)) + "</td>" +
                    "<td>" + escapeHtml(r.folder_title ? r.folder_title : "-") + "</td>" +
                    "<td class='text-center'>" + (parseInt(r.perso || 0, 10) === 1 ? "<?php echo addslashes($lang->get('yes')); ?>" : "<?php echo addslashes($lang->get('no')); ?>") + "</td>" +
                    "<td class='text-center'>" + (r.copies !== undefined ? r.copies : '-') + "</td>" +
                    "<td class='text-center'>" + (r.users_unique !== undefined ? r.users_unique : '-') + "</td>" +
                    "<td class='text-center'>" + (r.last_activity ? fmtTs(r.last_activity) : '-') + "</td>" +
                    "</tr>";
            });
        } else {
            itrows = "<tr><td colspan='6' class='text-center text-muted'><?php echo addslashes($lang->get('ops_no_data')); ?></td></tr>";
        }
        $('#tp-items-topcopied-body').html(itrows);
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
