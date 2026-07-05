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
        chunk: 50,
        listPage: 100,
        strings: {
            never: <?php echo json_encode($lang->get('security_dashboard_never'), JSON_UNESCAPED_UNICODE); ?>,
            noIssues: <?php echo json_encode($lang->get('security_dashboard_no_issues'), JSON_UNESCAPED_UNICODE); ?>,
            scanning: <?php echo json_encode($lang->get('security_dashboard_scanning'), JSON_UNESCAPED_UNICODE); ?>,
            scanButton: <?php echo json_encode($lang->get('security_dashboard_scan_button'), JSON_UNESCAPED_UNICODE); ?>,
            done: <?php echo json_encode($lang->get('security_dashboard_scan_done'), JSON_UNESCAPED_UNICODE); ?>,
            fix: <?php echo json_encode($lang->get('security_nudges_fix_worst'), JSON_UNESCAPED_UNICODE); ?>,
            allGood: <?php echo json_encode($lang->get('security_score_all_good'), JSON_UNESCAPED_UNICODE); ?>,
            scoreHint: <?php echo json_encode($lang->get('security_score_scan_hint'), JSON_UNESCAPED_UNICODE); ?>,
            bandExcellent: <?php echo json_encode($lang->get('security_score_band_excellent'), JSON_UNESCAPED_UNICODE); ?>,
            bandGood: <?php echo json_encode($lang->get('security_score_band_good'), JSON_UNESCAPED_UNICODE); ?>,
            bandFair: <?php echo json_encode($lang->get('security_score_band_fair'), JSON_UNESCAPED_UNICODE); ?>,
            bandPoor: <?php echo json_encode($lang->get('security_score_band_poor'), JSON_UNESCAPED_UNICODE); ?>,
            deltaSince: <?php echo json_encode($lang->get('security_score_delta_since'), JSON_UNESCAPED_UNICODE); ?>,
            howCalculated: <?php echo json_encode($lang->get('security_score_how_calculated'), JSON_UNESCAPED_UNICODE); ?>,
            calcIntro: <?php echo json_encode($lang->get('security_score_calc_intro'), JSON_UNESCAPED_UNICODE); ?>,
            calcNotCounted: <?php echo json_encode($lang->get('security_score_calc_not_counted'), JSON_UNESCAPED_UNICODE); ?>,
            calcScanNote: <?php echo json_encode($lang->get('security_score_calc_scan_note'), JSON_UNESCAPED_UNICODE); ?>
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

    // F10: score band -> presentation (label + gauge colour, kept in sync with the CSS).
    const TP_SCORE_BANDS = {
        excellent: { cls: 'score-band-excellent', label: TP_DASH.strings.bandExcellent, color: '#28a745' },
        good: { cls: 'score-band-good', label: TP_DASH.strings.bandGood, color: '#20c997' },
        fair: { cls: 'score-band-fair', label: TP_DASH.strings.bandFair, color: '#ffc107' },
        poor: { cls: 'score-band-poor', label: TP_DASH.strings.bandPoor, color: '#dc3545' }
    };

    // F10: build the "how is this score calculated?" help, driven by the server-supplied
    // weights so the explanation can never drift from securityScoreCompute().
    function dashboardScoreInfoContent(weights) {
        const esc = function (s) { return $('<div>').text(String(s)).html(); };
        const w = weights || {};
        let scored = '';
        ['breached', 'reused', 'weak', 'overdue'].forEach(function (k) {
            const meta = TP_DASH.flags['flag_' + k];
            const label = meta ? meta.label : k;
            scored += '<li>' + esc(label) + ' <span class="text-muted">(×' + (parseInt(w[k], 10) || 0) + ')</span></li>';
        });
        let notCounted = '';
        ['no_expiry', 'overshared'].forEach(function (k) {
            const meta = TP_DASH.flags['flag_' + k];
            notCounted += '<li>' + esc(meta ? meta.label : k) + '</li>';
        });
        return '<p class="mb-1">' + esc(TP_DASH.strings.calcIntro) + '</p>'
            + '<ul class="pl-3 mb-2">' + scored + '</ul>'
            + '<p class="mb-1">' + esc(TP_DASH.strings.calcNotCounted) + '</p>'
            + '<ul class="pl-3 mb-2">' + notCounted + '</ul>'
            + '<p class="text-muted mb-0">' + esc(TP_DASH.strings.calcScanNote) + '</p>';
    }

    // Init the score-help tooltip once (weights are constant across reloads/scan).
    function dashboardScoreInfoInit(weights) {
        const $info = $('#dashboard-score-info');
        if ($info.length === 0 || $info.data('tp-info-ready') === true || typeof $info.tooltip !== 'function') {
            return;
        }
        $info
            .attr('title', dashboardScoreInfoContent(weights))
            .tooltip({
                html: true,
                trigger: 'hover focus',
                placement: 'right',
                boundary: 'window',
                template: '<div class="tooltip dashboard-score-tooltip" role="tooltip"><div class="arrow"></div><div class="tooltip-inner"></div></div>'
            });
        $info.data('tp-info-ready', true);
    }

    // F10: render the Personal Security Score hero (gauge + band + top 3 to fix).
    function dashboardRenderScore(data) {
        const score = parseInt(data.score, 10) || 0;
        const meta = TP_SCORE_BANDS[String(data.band)] || TP_SCORE_BANDS.poor;
        const deg = Math.round(score * 3.6);
        $('#dashboard-score-gauge').css('background',
            'conic-gradient(' + meta.color + ' ' + deg + 'deg, #e9ecef ' + deg + 'deg)');
        $('#dashboard-score-value').text(score);
        $('#dashboard-score-band')
            .removeClass('score-band-excellent score-band-good score-band-fair score-band-poor')
            .addClass(meta.cls).text(meta.label).show();

        // Progress delta frozen at the last scan ("+N since last scan"). Hidden when
        // unknown (no previous scan) or zero (no change), to stay constructive.
        const $delta = $('#dashboard-score-delta');
        const delta = (data.delta === null || typeof data.delta === 'undefined') ? null : parseInt(data.delta, 10);
        if (delta !== null && delta !== 0) {
            const up = delta > 0;
            $delta.html(
                '<i class="fa-solid ' + (up ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down') + ' mr-1"></i>' +
                (up ? '+' : '') + delta + ' ' + TP_DASH.strings.deltaSince
            ).css('color', up ? '#28a745' : '#dc3545').show();
        } else {
            $delta.hide();
        }

        // Honest hint: reuse/breach are only fresh once a deep scan has run.
        if (data.scanned !== true) {
            $('#dashboard-score-hint').text(TP_DASH.strings.scoreHint).show();
        } else {
            $('#dashboard-score-hint').hide();
        }

        // Top 3 issue categories to fix (reuses the flag labels already loaded).
        const ul = $('#dashboard-score-top3');
        ul.empty();
        if (!data.top3 || data.top3.length === 0) {
            ul.append($('<li>').addClass('text-muted').text(TP_DASH.strings.allGood));
        } else {
            data.top3.forEach(function (cat) {
                const flagMeta = TP_DASH.flags['flag_' + cat.key];
                const label = flagMeta ? flagMeta.label : cat.key;
                const cls = flagMeta ? flagMeta.cls : 'badge-secondary';
                ul.append($('<li>').addClass('mb-1').append(
                    $('<span>').addClass('badge ' + cls + ' mr-2').text(parseInt(cat.count, 10) || 0),
                    $('<span>').text(label)
                ));
            });
        }

        // Primary CTA: jump straight to the most urgent item's editor (F8 deep-link).
        const fix = $('#dashboard-score-fix');
        if (data.worst_item && parseInt(data.worst_item.id, 10) > 0) {
            const fid = parseInt(data.worst_item.folder_id, 10) || 0;
            const iid = parseInt(data.worst_item.id, 10) || 0;
            fix.attr('href', 'index.php?page=items&group=' + fid + '&id=' + iid + '&action=edit').show();
        } else {
            fix.hide();
        }

        // Score methodology help (server-supplied weights → no drift with the algorithm).
        dashboardScoreInfoInit(data.weights);
    }

    function dashboardLoadScore() {
        dashboardPost({ type: 'get_score' }, function (data) {
            if (!data || data.error === true) {
                return;
            }
            dashboardRenderScore(data);
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

    function dashboardRenderList(list, append) {
        const tbody = $('#dashboard-flagged-tbody');
        if (append !== true) {
            tbody.empty();
        }
        if ((!list || list.length === 0) && append !== true) {
            tbody.append($('<tr>').append(
                $('<td>').attr('colspan', 3).addClass('text-center text-muted p-3').text(TP_DASH.strings.noIssues)
            ));
            return;
        }
        (list || []).forEach(function (item) {
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
                    .addClass('btn btn-xs btn-light ml-1 infotip')
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

    // Incremental list paging state ("load more").
    let dashboardListShown = 0;
    let dashboardListTotal = 0;

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
        dashboardPost({ type: 'get_summary', offset: 0, limit: TP_DASH.listPage, filter_flag: dashboardFilterFlag, filter_folder: dashboardFilterFolder }, function (data) {
            $('.dashboard-card').removeClass('loading');
            if (!data || data.error === true) {
                return;
            }
            dashboardRenderCounts(data.counts);
            dashboardBuildFolders(data.folders);
            dashboardRenderList(data.list, false);
            dashboardListShown = (data.list || []).length;
            dashboardListTotal = parseInt(data.list_total, 10) || 0;
            dashboardUpdateLoadMore();
            dashboardApplyFilterUI();
            $('#dashboard-last-scan').text(
                data.last_scan > 0 ? new Date(data.last_scan * 1000).toLocaleString() : TP_DASH.strings.never
            );
        });
    }

    // Show/update the "load more" footer based on how many flagged items remain unshown.
    function dashboardUpdateLoadMore() {
        const remaining = dashboardListTotal - dashboardListShown;
        if (remaining > 0) {
            $('#dashboard-load-more-count').text(remaining);
            $('#dashboard-load-more-wrap').show();
        } else {
            $('#dashboard-load-more-wrap').hide();
        }
    }

    // Append the next page of flagged items, reusing the current filters. The global
    // posture counts and groupings are not recomputed (the server skips them past offset 0).
    function dashboardLoadMore() {
        const btn = $('#dashboard-load-more-btn');
        btn.prop('disabled', true);
        dashboardPost({ type: 'get_summary', offset: dashboardListShown, limit: TP_DASH.listPage, filter_flag: dashboardFilterFlag, filter_folder: dashboardFilterFolder }, function (data) {
            btn.prop('disabled', false);
            if (!data || data.error === true) {
                return;
            }
            dashboardRenderList(data.list, true);
            dashboardListShown += (data.list || []).length;
            if (typeof data.list_total !== 'undefined') {
                dashboardListTotal = parseInt(data.list_total, 10) || dashboardListTotal;
            }
            dashboardUpdateLoadMore();
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
        dashboardLoadScore();
    }

    $(function () {
        dashboardLoadSummary();
        dashboardLoadScore();

        $('#dashboard-load-more-btn').on('click', dashboardLoadMore);

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
