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
 * @file      security-nudges.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;

$session = SessionManager::getSession();
$lang = new Language($session->get('user-language') ?? 'english');
?>
<script type="text/javascript">
    //<![CDATA[
    /**
     * Proactive Health Nudges (F8) — in-app banner.
     *
     * On load, asks the server for the user's actionable security posture (breached /
     * weak / reused / overdue COUNTS only) and, when something needs attention, shows a
     * dismissible banner that links to the dashboard ("Review") or straight to the most
     * urgent item's editor ("Fix the most urgent"). Rate-limited to once per browser
     * session, with a 24h snooze on dismiss. Also reacts to live `security_nudge`
     * WebSocket events emitted when a scan finishes.
     */
    (function () {
        'use strict'

        var sessionKey = '<?php echo $session->get('key'); ?>'
        var SNOOZE_KEY = 'tpNudgeSnoozeUntil'
        var SESSION_KEY = 'tpNudgeShownSession'
        var SNOOZE_MS = 24 * 60 * 60 * 1000

        var L = {
            title: <?php echo json_encode($lang->get('security_nudges_banner_title'), JSON_UNESCAPED_UNICODE); ?>,
            one: <?php echo json_encode($lang->get('security_nudges_banner_one'), JSON_UNESCAPED_UNICODE); ?>,
            many: <?php echo json_encode($lang->get('security_nudges_banner_many'), JSON_UNESCAPED_UNICODE); ?>,
            review: <?php echo json_encode($lang->get('security_nudges_review'), JSON_UNESCAPED_UNICODE); ?>,
            fix: <?php echo json_encode($lang->get('security_nudges_fix_worst'), JSON_UNESCAPED_UNICODE); ?>,
            dismiss: <?php echo json_encode($lang->get('security_nudges_dismiss'), JSON_UNESCAPED_UNICODE); ?>,
            rescan: <?php echo json_encode($lang->get('security_nudges_rescan_hint'), JSON_UNESCAPED_UNICODE); ?>,
            breached: <?php echo json_encode($lang->get('security_dashboard_breached'), JSON_UNESCAPED_UNICODE); ?>,
            weak: <?php echo json_encode($lang->get('security_dashboard_weak'), JSON_UNESCAPED_UNICODE); ?>,
            reused: <?php echo json_encode($lang->get('security_dashboard_reused'), JSON_UNESCAPED_UNICODE); ?>,
            overdue: <?php echo json_encode($lang->get('security_dashboard_overdue'), JSON_UNESCAPED_UNICODE); ?>
        }

        function isSnoozed() {
            var until = parseInt(window.localStorage.getItem(SNOOZE_KEY) || '0', 10)
            return until > 0 && Date.now() < until
        }

        function buildBreakdown(counts) {
            var parts = []
            if (counts.breached > 0) parts.push(counts.breached + ' ' + L.breached.toLowerCase())
            if (counts.weak > 0) parts.push(counts.weak + ' ' + L.weak.toLowerCase())
            if (counts.reused > 0) parts.push(counts.reused + ' ' + L.reused.toLowerCase())
            if (counts.overdue > 0) parts.push(counts.overdue + ' ' + L.overdue.toLowerCase())
            return parts.join(' · ')
        }

        // Render the banner once. `data` is the decoded get_nudge_summary response.
        function renderBanner(data) {
            if (document.getElementById('tp-nudge-banner') !== null) return
            var counts = data.counts || {}
            var total = parseInt(counts.total, 10) || 0
            if (total <= 0) return

            var headline = total === 1 ? L.one : L.many.replace('%d', total)
            var breakdown = buildBreakdown(counts)

            var $banner = $(
                '<div id="tp-nudge-banner" class="alert alert-warning alert-dismissible fade show m-2 shadow-sm" role="alert">' +
                '<i class="fa-solid fa-shield-halved mr-2"></i>' +
                '<strong class="tp-nudge-headline"></strong> ' +
                '<span class="tp-nudge-breakdown text-muted"></span>' +
                '<span class="tp-nudge-actions ml-2"></span>' +
                '<div class="tp-nudge-rescan small text-muted mt-1' + (data.stale === true ? '' : ' d-none') + '"></div>' +
                '<button type="button" class="close" aria-label="Close" id="tp-nudge-dismiss">&times;</button>' +
                '</div>'
            )
            $banner.find('.tp-nudge-headline').text(headline)
            if (breakdown !== '') {
                $banner.find('.tp-nudge-breakdown').text('— ' + breakdown)
            }
            $banner.find('.tp-nudge-rescan').text(L.rescan)

            // Actions: Review (dashboard) + optional "Fix the most urgent" (deep-link edit).
            var $review = $('<a>')
                .attr('href', 'index.php?page=dashboard')
                .addClass('btn btn-sm btn-warning ml-1')
                .text(L.review)
            $banner.find('.tp-nudge-actions').append($review)

            if (data.worst_item && parseInt(data.worst_item.id, 10) > 0) {
                var folderId = parseInt(data.worst_item.folder_id, 10) || 0
                var itemId = parseInt(data.worst_item.id, 10) || 0
                var fixLink = 'index.php?page=items&group=' + folderId + '&id=' + itemId + '&action=edit'
                var $fix = $('<a>')
                    .attr('href', fixLink)
                    .addClass('btn btn-sm btn-light ml-1')
                    .html('<i class="fa-solid fa-wrench mr-1"></i>' + L.fix)
                $banner.find('.tp-nudge-actions').append($fix)
            }

            var $target = $('.content-wrapper').first()
            if ($target.length === 0) $target = $('body')
            $target.prepend($banner)

            // Dismiss = snooze for 24h (across sessions).
            $banner.on('click', '#tp-nudge-dismiss', function (e) {
                e.preventDefault()
                window.localStorage.setItem(SNOOZE_KEY, String(Date.now() + SNOOZE_MS))
                $banner.alert('close')
            })
        }

        // Fetch the actionable posture and decide whether to show the banner.
        function loadNudge() {
            if (isSnoozed()) return
            if (window.sessionStorage.getItem(SESSION_KEY) === '1') return

            $.post('sources/dashboard.queries.php', { type: 'get_nudge_summary', key: sessionKey }, function (response) {
                var data
                try {
                    data = prepareExchangedData(response, 'decode', sessionKey)
                } catch (e) {
                    return
                }
                if (!data || data.error === true) return
                var total = data.counts ? (parseInt(data.counts.total, 10) || 0) : 0
                if (total <= 0) return
                // Mark shown for this session so page navigation does not re-prompt.
                window.sessionStorage.setItem(SESSION_KEY, '1')
                renderBanner(data)
            })
        }

        // Live nudge after a scan completes (WebSocket user-target event).
        function wireLiveNudge() {
            if (typeof window.tpWebSocket === 'undefined' || !window.tpWebSocket) return
            window.tpWebSocket.on('security_nudge', function (d) {
                var total = parseInt(d && d.total, 10) || 0
                if (total <= 0) return
                var headline = total === 1 ? L.one : L.many.replace('%d', total)
                var message = headline + ' — ' + buildBreakdown(d)
                if (typeof window.tpWsShowNotification === 'function') {
                    window.tpWsShowNotification('warning', L.title, message)
                } else if (typeof toastr !== 'undefined') {
                    toastr.warning(message, L.title)
                }
            })
        }

        $(function () {
            // Defer a touch so the page chrome (content-wrapper) and websocket init exist.
            setTimeout(loadNudge, 800)
            setTimeout(wireLiveNudge, 1500)
        })
    })()
    //]]>
</script>
