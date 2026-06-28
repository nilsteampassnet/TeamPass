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
 * @file      dashboard.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as RequestLocal;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\Language\Language;

// Load functions
require_once __DIR__ . '/../sources/main.functions.php';

// init
loadClasses();
$session = SessionManager::getSession();
$request = RequestLocal::createFromGlobals();
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('dashboard') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}
?>

<script type="text/javascript">
    // Language strings + per-flag presentation for the Security Posture Dashboard.
    const TP_DASH = {
        key: '<?php echo $session->get('key'); ?>',
        isAdmin: <?php echo (int) $session->get('user-admin') === 1 ? 'true' : 'false'; ?>,
        chunk: 50,
        strings: {
            never: <?php echo json_encode($lang->get('security_dashboard_never'), JSON_UNESCAPED_UNICODE); ?>,
            noIssues: <?php echo json_encode($lang->get('security_dashboard_no_issues'), JSON_UNESCAPED_UNICODE); ?>,
            scanning: <?php echo json_encode($lang->get('security_dashboard_scanning'), JSON_UNESCAPED_UNICODE); ?>,
            scanButton: <?php echo json_encode($lang->get('security_dashboard_scan_button'), JSON_UNESCAPED_UNICODE); ?>,
            done: <?php echo json_encode($lang->get('security_dashboard_scan_done'), JSON_UNESCAPED_UNICODE); ?>,
            fix: <?php echo json_encode($lang->get('security_nudges_fix_worst'), JSON_UNESCAPED_UNICODE); ?>
        },
        flags: {
            flag_weak: { label: <?php echo json_encode($lang->get('security_dashboard_weak'), JSON_UNESCAPED_UNICODE); ?>, cls: 'badge-warning' },
            flag_reused: { label: <?php echo json_encode($lang->get('security_dashboard_reused'), JSON_UNESCAPED_UNICODE); ?>, cls: 'badge-warning' },
            flag_breached: { label: <?php echo json_encode($lang->get('security_dashboard_breached'), JSON_UNESCAPED_UNICODE); ?>, cls: 'badge-danger' },
            flag_overdue: { label: <?php echo json_encode($lang->get('security_dashboard_overdue'), JSON_UNESCAPED_UNICODE); ?>, cls: 'badge-warning' },
            flag_no_expiry: { label: <?php echo json_encode($lang->get('security_dashboard_no_expiry'), JSON_UNESCAPED_UNICODE); ?>, cls: 'badge-info' },
            flag_overshared: { label: <?php echo json_encode($lang->get('security_dashboard_overshared'), JSON_UNESCAPED_UNICODE); ?>, cls: 'badge-info' },
            flag_orphaned: { label: <?php echo json_encode($lang->get('security_dashboard_orphaned'), JSON_UNESCAPED_UNICODE); ?>, cls: 'badge-secondary' }
        }
    };

    // Post helper using the standard encrypted client-server exchange.
    function dashboardPost(payload, callback) {
        payload.key = TP_DASH.key;
        $.post('sources/dashboard.queries.php', payload, function (data) {
            data = prepareExchangedData(data, 'decode', TP_DASH.key);
            callback(data);
        });
    }

    function dashboardRenderCounts(counts) {
        Object.keys(counts).forEach(function (k) {
            const el = document.getElementById('dashboard-count-' + k);
            if (el !== null) {
                el.textContent = counts[k];
            }
        });
        $('#dashboard-total-items').text(counts.total);
    }

    function dashboardRenderList(list) {
        const tbody = $('#dashboard-flagged-tbody');
        tbody.empty();
        if (!list || list.length === 0) {
            tbody.append($('<tr>').append(
                $('<td>').attr('colspan', 3).addClass('text-center text-muted p-3').text(TP_DASH.strings.noIssues)
            ));
            return;
        }
        list.forEach(function (item) {
            const folderId = parseInt(item.folder_id, 10) || 0;
            const itemId = parseInt(item.id, 10) || 0;
            const itemLink = 'index.php?page=items&group=' + folderId + '&id=' + itemId;

            const tr = $('<tr>');
            tr.append($('<td>').append($('<a>').attr('href', itemLink).text(item.label)));
            tr.append($('<td>').addClass('text-muted small').text(item.path));

            const tdBadges = $('<td>');
            Object.keys(TP_DASH.flags).forEach(function (f) {
                if (item[f] === 1) {
                    tdBadges.append($('<span>').addClass('badge ' + TP_DASH.flags[f].cls + ' mr-1').text(TP_DASH.flags[f].label));
                }
            });
            // F8: one-click "fix" — deep-link straight to the item editor (generator ready).
            tdBadges.append(
                $('<a>').attr('href', itemLink + '&action=edit')
                    .addClass('btn btn-xs btn-outline-danger ml-1 infotip')
                    .attr('title', TP_DASH.strings.fix)
                    .html('<i class="fa-solid fa-wrench"></i>')
            );
            tr.append(tdBadges);
            tbody.append(tr);
        });
    }

    // Active filters: by grouping (folder) and by issue type (clicked card).
    let dashboardFilterFolder = 0;
    let dashboardFilterFlag = '';

    function dashboardBuildFolders(folders) {
        const sel = $('#dashboard-folder-filter');
        const current = String(dashboardFilterFolder);
        sel.find('option:not(:first)').remove();
        (folders || []).forEach(function (f) {
            sel.append($('<option>').attr('value', String(parseInt(f.id, 10) || 0)).text(f.path));
        });
        sel.val(current);
        // Selected grouping no longer has flagged items → fall back to "all".
        if (sel.val() === null) {
            dashboardFilterFolder = 0;
            sel.val('0');
        }
    }

    function dashboardApplyFilterUI() {
        $('.dashboard-card').removeClass('active-filter');
        if (dashboardFilterFlag !== '') {
            $('.dashboard-card[data-flag="' + dashboardFilterFlag + '"]').addClass('active-filter');
            const meta = TP_DASH.flags['flag_' + dashboardFilterFlag];
            $('#dashboard-active-flag').text(meta ? meta.label : dashboardFilterFlag).show();
        } else {
            $('#dashboard-active-flag').hide();
        }
        $('#dashboard-clear-filter').toggle(dashboardFilterFlag !== '' || dashboardFilterFolder > 0);
    }

    function dashboardLoadSummary() {
        dashboardPost({ type: 'get_summary', offset: 0, limit: 200, filter_flag: dashboardFilterFlag, filter_folder: dashboardFilterFolder }, function (data) {
            $('.dashboard-card').removeClass('loading');
            if (!data || data.error === true) {
                return;
            }
            dashboardRenderCounts(data.counts);
            dashboardBuildFolders(data.folders);
            dashboardRenderList(data.list);
            dashboardApplyFilterUI();
            $('#dashboard-last-scan').text(
                data.last_scan > 0 ? new Date(data.last_scan * 1000).toLocaleString() : TP_DASH.strings.never
            );
        });
    }

    function dashboardLoadAdmin() {
        if (TP_DASH.isAdmin !== true) {
            return;
        }
        dashboardPost({ type: 'get_admin_aggregate' }, function (data) {
            if (!data || data.error === true) {
                return;
            }
            $('#admin-total').text(data.metadata.total);
            $('#admin-weak').text(data.metadata.weak);
            $('#admin-breached').text(data.metadata.breached);
            $('#admin-overdue').text(data.metadata.overdue);
            $('#admin-no_expiry').text(data.metadata.no_expiry);
            $('#admin-overshared').text(data.metadata.overshared);
            $('#admin-reused').text(data.health.reused_items);
            $('#admin-scanned-users').text(data.health.scanned_users);
            $('#admin-total-users').text(data.health.total_users);
        });
    }

    function dashboardScanChunk(offset, includeHibp) {
        dashboardPost({ type: 'deep_scan_chunk', offset: offset, limit: TP_DASH.chunk, include_hibp: includeHibp }, function (data) {
            if (!data || data.error === true) {
                dashboardScanFinish(false);
                return;
            }
            const pct = data.total > 0 ? Math.min(100, Math.round((data.next_offset / data.total) * 100)) : 100;
            $('#dashboard-progress-bar').css('width', pct + '%').text(pct + '%');
            if (data.done === true) {
                dashboardPost({ type: 'finalize_scan' }, function () {
                    dashboardScanFinish(true);
                });
            } else {
                dashboardScanChunk(data.next_offset, includeHibp);
            }
        });
    }

    function dashboardScanFinish(success) {
        const btn = $('#dashboard-scan-btn');
        btn.prop('disabled', false).html('<i class="fa-solid fa-magnifying-glass-chart mr-1"></i>' + TP_DASH.strings.scanButton);
        $('#dashboard-progress-wrap').hide();
        if (success === true && typeof toastr !== 'undefined') {
            toastr.success(TP_DASH.strings.done);
        }
        dashboardLoadSummary();
        dashboardLoadAdmin();
    }

    $(function () {
        dashboardLoadSummary();
        dashboardLoadAdmin();

        $('#dashboard-scan-btn').on('click', function () {
            const includeHibp = $('#dashboard-include-hibp').is(':checked') ? 1 : 0;
            $(this).prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-1"></i>' + TP_DASH.strings.scanning);
            $('#dashboard-progress-bar').css('width', '0%').text('0%');
            $('#dashboard-progress-wrap').show();
            dashboardScanChunk(0, includeHibp);
        });

        // Click an indicator card to filter the list by that issue type (toggle).
        $(document).on('click', '.dashboard-card', function () {
            const flag = String($(this).data('flag'));
            dashboardFilterFlag = (dashboardFilterFlag === flag) ? '' : flag;
            $(this).addClass('loading');
            dashboardLoadSummary();
        });

        // Filter the list by grouping (folder).
        $('#dashboard-folder-filter').on('change', function () {
            dashboardFilterFolder = parseInt($(this).val(), 10) || 0;
            dashboardLoadSummary();
        });

        // Reset both filters.
        $('#dashboard-clear-filter').on('click', function () {
            dashboardFilterFlag = '';
            dashboardFilterFolder = 0;
            $('#dashboard-folder-filter').val('0');
            dashboardLoadSummary();
        });
    });
</script>
