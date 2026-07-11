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
 * @file      micro-learning.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;

require_once __DIR__ . '/../sources/learning.functions.php';

$session = SessionManager::getSession();
$lang = new Language($session->get('user-language') ?? 'english');

// Localise the catalogue once, server-side.
$microLearningTips = [];
foreach (microLearningTipCatalogue() as $tip) {
    $microLearningTips[] = [
        'id' => $tip['id'],
        'context' => $tip['context'],
        'text' => $lang->get($tip['lang']),
    ];
}
?>
<script type="text/javascript">
    //<![CDATA[
    /**
     * In-Context Micro-Learning (F11).
     *
     * Short, dismissible security tips at the moment of action (opening the
     * item form, focusing the password field, opening the share dialog) plus
     * a one-a-day rotation of general tips. Never blocking: every tip can be
     * dismissed for good and the whole feature muted in one click. Dismissals
     * are kept client-side (localStorage) — deliberately data-light.
     */
    (function () {
        'use strict'

        var TIPS = <?php echo json_encode($microLearningTips, JSON_UNESCAPED_UNICODE); ?>;

        var L = {
            title: <?php echo json_encode($lang->get('microlearning_title'), JSON_UNESCAPED_UNICODE); ?>,
            gotIt: <?php echo json_encode($lang->get('microlearning_got_it'), JSON_UNESCAPED_UNICODE); ?>,
            muteAll: <?php echo json_encode($lang->get('microlearning_mute_all'), JSON_UNESCAPED_UNICODE); ?>
        }

        var STORE_KEY = 'tpMicroLearning'

        function readState() {
            try {
                var raw = localStorage.getItem(STORE_KEY)
                var state = raw ? JSON.parse(raw) : {}
                return {
                    dismissed: Array.isArray(state.dismissed) ? state.dismissed : [],
                    muted: state.muted === true,
                    lastDaily: String(state.lastDaily || '')
                }
            } catch (e) {
                return { dismissed: [], muted: false, lastDaily: '' }
            }
        }

        function writeState(state) {
            try {
                localStorage.setItem(STORE_KEY, JSON.stringify(state))
            } catch (e) { /* storage unavailable — tips simply repeat */ }
        }

        function escapeText(value) {
            return $('<div>').text(String(value)).html()
        }

        function findTip(tipId) {
            return TIPS.find(function (tip) { return tip.id === tipId })
        }

        function showTip(tipId) {
            var state = readState()
            if (state.muted || state.dismissed.indexOf(tipId) !== -1) return
            var tip = findTip(tipId)
            if (!tip || typeof toastr === 'undefined') return

            toastr.info(
                escapeText(tip.text) +
                '<div class="mt-2">' +
                '<a href="#" class="tp-tip-dismiss mr-3 text-bold" data-tip="' + escapeText(tip.id) + '">' + escapeText(L.gotIt) + '</a>' +
                '<a href="#" class="tp-tip-mute text-muted">' + escapeText(L.muteAll) + '</a>' +
                '</div>',
                escapeText(L.title),
                { timeOut: 15000, extendedTimeOut: 5000, closeButton: true, progressBar: true, escapeHtml: false }
            )
        }

        // "Got it" — never show this tip again.
        $(document).on('click', '.tp-tip-dismiss', function (e) {
            e.preventDefault()
            var state = readState()
            var tipId = String($(this).data('tip'))
            if (state.dismissed.indexOf(tipId) === -1) {
                state.dismissed.push(tipId)
            }
            writeState(state)
            $(this).closest('.toast').find('.toast-close-button').trigger('click')
        })

        // "Mute all tips" — switch the whole feature off for this browser.
        $(document).on('click', '.tp-tip-mute', function (e) {
            e.preventDefault()
            var state = readState()
            state.muted = true
            writeState(state)
            $(this).closest('.toast').find('.toast-close-button').trigger('click')
        })

        // ------ Moments of action (delegated: the dialogs are created late) --

        // Opening the new-item form: why a unique password matters.
        $(document).on('click', '#btn-new-item', function () {
            showTip('unique_password')
        })

        // Focusing the password field: suggest the generator / a passphrase.
        $(document).on('focus', '#form-item-password', function () {
            showTip('passphrase')
        })

        // Opening the one-time-view share dialog: why not email/chat.
        $(document).on('shown.bs.modal', '#modal-item-otv', function () {
            showTip('secure_share')
        })

        // ------ Daily rotation (one general tip a day, deterministic) --------

        $(function () {
            setTimeout(function () {
                var state = readState()
                if (state.muted) return
                var today = new Date().toISOString().slice(0, 10)
                if (state.lastDaily === today) return

                var daily = TIPS.filter(function (tip) {
                    return tip.context === 'daily' && state.dismissed.indexOf(tip.id) === -1
                })
                if (daily.length === 0) return

                var dayIndex = Math.floor(Date.now() / 86400000)
                showTip(daily[dayIndex % daily.length].id)

                state.lastDaily = today
                writeState(state)
            }, 2500)
        })
    })()
    //]]>
</script>
