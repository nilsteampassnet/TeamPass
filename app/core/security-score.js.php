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
 * @file      security-score.js.php
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
     * Personal Security Score (F10) — always-on topbar badge.
     *
     * On load, asks the server for the canonical 0–100 score (computed once, server-side,
     * so it matches the dashboard gauge exactly) and injects a small colour-coded pill in
     * the navbar that links to the Security posture dashboard. Re-fetches when a scan
     * completes (live `security_nudge` WebSocket event). Counts/score only — no plaintext.
     */
    (function () {
        'use strict'

        var sessionKey = '<?php echo $session->get('key'); ?>'
        var userId = '<?php echo (int) $session->get('user-id'); ?>'

        // Stale-while-revalidate cache (per user, per tab): paint the last known score
        // instantly on navigation, then refresh in the background. Score + band only.
        var CACHE_KEY = 'tp_score_v1_' + userId
        function readScoreCache() {
            try {
                var raw = sessionStorage.getItem(CACHE_KEY)
                return raw ? JSON.parse(raw) : null
            } catch (e) { return null }
        }
        function writeScoreCache(data) {
            try {
                sessionStorage.setItem(CACHE_KEY, JSON.stringify({ score: data.score, band: data.band }))
            } catch (e) { /* ignore quota / serialization errors */ }
        }

        var L = {
            title: <?php echo json_encode($lang->get('security_score_badge_title'), JSON_UNESCAPED_UNICODE); ?>,
            excellent: <?php echo json_encode($lang->get('security_score_band_excellent'), JSON_UNESCAPED_UNICODE); ?>,
            good: <?php echo json_encode($lang->get('security_score_band_good'), JSON_UNESCAPED_UNICODE); ?>,
            fair: <?php echo json_encode($lang->get('security_score_band_fair'), JSON_UNESCAPED_UNICODE); ?>,
            poor: <?php echo json_encode($lang->get('security_score_band_poor'), JSON_UNESCAPED_UNICODE); ?>
        }

        var BAND_COLOR = { excellent: '#28a745', good: '#20c997', fair: '#ffc107', poor: '#dc3545' }

        // Render (or update) the pill from a decoded get_score response.
        function injectBadge(data) {
            var score = parseInt(data.score, 10)
            if (isNaN(score)) return
            var band = String(data.band || 'poor')
            var color = BAND_COLOR[band] || BAND_COLOR.poor
            var bandLabel = L[band] || L.poor
            var textColor = (band === 'fair') ? '#212529' : '#fff'
            var titleText = L.title + ': ' + score + '/100 — ' + bandLabel

            var $item = $('#tp-score-badge-item')
            if ($item.length === 0) {
                var contentHtml =
                    '<a href="index.php?page=dashboard" class="nav-link tp-topbar-btn p-1 infotip" id="tp-score-badge">' +
                    '<span class="badge badge-pill" id="tp-score-badge-value"></span>' +
                    '</a>'
                // Preferred: fill the fixed server-rendered slot (deterministic order, no reflow).
                var $slot = $('#tp-slot-score')
                if ($slot.length > 0) {
                    $item = $slot.attr('id', 'tp-score-badge-item').html(contentHtml)
                } else {
                    // Fallback: legacy prepend when the fixed slot is absent.
                    var $nav = $('.main-header .navbar-nav.ml-auto').first()
                    if ($nav.length === 0) return
                    $item = $('<li class="nav-item d-flex align-items-center mr-2" id="tp-score-badge-item">' + contentHtml + '</li>')
                    $nav.prepend($item)
                }
            }
            $item.find('#tp-score-badge').attr('title', titleText)
            $item.find('#tp-score-badge-value')
                .css({ 'background-color': color, 'color': textColor, 'font-size': '0.85rem' })
                .html('<i class="fa-solid fa-shield-halved mr-1"></i>' + score)
        }

        function loadScore() {
            $.post('sources/dashboard.queries.php', { type: 'get_score', key: sessionKey }, function (response) {
                var data
                try {
                    data = prepareExchangedData(response, 'decode', sessionKey)
                } catch (e) {
                    return
                }
                if (!data || data.error === true) return
                injectBadge(data)
                writeScoreCache(data)
            })
        }

        // Refresh the pill when a scan completes (best-effort; only when nudges emit events).
        function wireLiveRefresh() {
            if (typeof window.tpWebSocket === 'undefined' || !window.tpWebSocket) return
            window.tpWebSocket.on('security_nudge', function () {
                loadScore()
            })
        }

        $(function () {
            // Stale-while-revalidate: paint the last known score instantly (no wait),
            // then refresh in the background — quickly when we already showed something.
            var cached = readScoreCache()
            if (cached) injectBadge(cached)
            setTimeout(loadScore, cached ? 250 : 900)
            setTimeout(wireLiveRefresh, 1500)
        })
    })()
    //]]>
</script>
