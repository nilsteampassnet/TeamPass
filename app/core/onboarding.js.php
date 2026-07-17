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
 * @file      onboarding.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;
use TeampassClasses\ConfigManager\ConfigManager;

$session = SessionManager::getSession();
$lang = new Language($session->get('user-language') ?? 'english');

$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Detect the landing page (non-admin users are redirected to ?page=items).
$onboardingCurrentPage = (string) filter_input(INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$onboardingIsLandingPage = ($onboardingCurrentPage === '' || $onboardingCurrentPage === 'items');

// Step gating: each flag mirrors the condition that makes the related feature
// reachable for this user, so the wizard never points at something they cannot use.
$onboardingFlags = [
    'isLandingPage'      => $onboardingIsLandingPage,
    'firstConnection'    => (bool) $session->get('user-first_connection'),
    'onboardingCompleted'=> (int) ($session->get('user-onboarding_completed') ?? 0),
    // Create first folder: only managers / HR reach the folders page (see PerformChecks).
    'canCreateFolder'    => ((int) $session->get('user-manager') === 1
                              || (int) $session->get('user-can_manage_all_users') === 1),
    // Create first item: any user with write access (not read-only).
    'canCreateItem'      => ((int) $session->get('user-read_only') !== 1),
    // Send a secret (F14): the OTV engine must be enabled.
    'secureSendEnabled'  => ((int) ($SETTINGS['otv_is_enabled'] ?? 0) === 1),
    // Stand-alone "Send a secret" button only exists when ad-hoc note sends are on.
    'secureSendNotes'    => ((int) ($SETTINGS['secure_send_allow_notes'] ?? 0) === 1),
    'importEnabled'      => ((int) ($SETTINGS['allow_import'] ?? 0) === 1),
    'extensionEnabled'   => ((int) ($SETTINGS['api'] ?? 0) === 1),
];

$onboardingStrings = [
    'welcome_title'   => $lang->get('onboarding_welcome_title'),
    'welcome_body'    => $lang->get('onboarding_welcome_body'),
    'folder_title'    => $lang->get('onboarding_folder_title'),
    'folder_body'     => $lang->get('onboarding_folder_body'),
    'item_title'      => $lang->get('onboarding_item_title'),
    'item_body'       => $lang->get('onboarding_item_body'),
    'send_title'      => $lang->get('onboarding_send_title'),
    'send_body'       => $lang->get('onboarding_send_body'),
    'import_title'    => $lang->get('onboarding_import_title'),
    'import_body'     => $lang->get('onboarding_import_body'),
    'extension_title' => $lang->get('onboarding_extension_title'),
    'extension_body'  => $lang->get('onboarding_extension_body'),
    'extension_cta'   => $lang->get('onboarding_extension_cta'),
    'finish_title'    => $lang->get('onboarding_finish_title'),
    'finish_body'     => $lang->get('onboarding_finish_body'),
    'btn_next'        => $lang->get('onboarding_btn_next'),
    'btn_prev'        => $lang->get('onboarding_btn_prev'),
    'btn_done'        => $lang->get('onboarding_btn_done'),
];

$onboardingJsonFlags = json_encode($onboardingFlags, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$onboardingJsonStrings = json_encode($onboardingStrings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<link rel="stylesheet" href="plugins/driver.js/driver.css?v=1.3.6">
<script src="plugins/driver.js/driver.js.iife.js?v=1.3.6"></script>
<script type="text/javascript">
    //<![CDATA[
    /**
     * F12 — First-run onboarding wizard.
     *
     * A guided tour (driver.js) shown to non-admin users on their very first login.
     * It walks through the high-value setup actions that already exist in TeamPass
     * (create a folder, create an item, send a secret, import passwords, configure
     * the browser extension), each step shown only when applicable to the user.
     *
     * Auto-launch is gated by a genuine first connection AND a non-completed flag.
     * Returning users reach it on demand from the user menu (#onboarding-replay).
     */
    (function () {
        'use strict'

        var flags = <?php echo $onboardingJsonFlags; ?>;
        var L = <?php echo $onboardingJsonStrings; ?>;
        var sessionKey = '<?php echo $session->get('key'); ?>'
        var persisted = false

        function driverFactory() {
            return (window.driver && window.driver.js && window.driver.js.driver)
                ? window.driver.js.driver
                : null
        }

        // Persist completion server-side (idempotent). Called when the tour ends,
        // whether it was finished or skipped/closed.
        function persistCompletion() {
            if (persisted === true) return
            persisted = true
            if (typeof prepareExchangedData !== 'function') return
            $.post('sources/users.queries.php', {
                type: 'set_onboarding_completed',
                data: prepareExchangedData(JSON.stringify({}), 'encode', sessionKey),
                key: sessionKey
            })
        }

        // Build a step, attaching the highlighted element only when it is actually
        // present in the DOM so a missing target degrades to a centered popover.
        function step(selector, popover) {
            var s = { popover: popover }
            if (selector && document.querySelector(selector) !== null) {
                s.element = selector
            }
            return s
        }

        function buildSteps() {
            var steps = []

            steps.push({ popover: { title: L.welcome_title, description: L.welcome_body } })

            if (flags.canCreateFolder === true) {
                steps.push(step('a[data-name="folders"]', {
                    title: L.folder_title, description: L.folder_body, side: 'right', align: 'start'
                }))
            }

            if (flags.canCreateItem === true) {
                steps.push(step('#btn-new-item', {
                    title: L.item_title, description: L.item_body, side: 'bottom', align: 'start'
                }))
            }

            if (flags.secureSendEnabled === true) {
                steps.push(step(
                    flags.secureSendNotes === true ? '#secure-send-note-open' : null,
                    { title: L.send_title, description: L.send_body, side: 'bottom', align: 'start' }
                ))
            }

            if (flags.importEnabled === true) {
                steps.push(step('a[data-name="import"]', {
                    title: L.import_title, description: L.import_body, side: 'right', align: 'start'
                }))
            }

            if (flags.extensionEnabled === true) {
                steps.push({
                    popover: {
                        title: L.extension_title,
                        description: L.extension_body
                            + '<div class="mt-2"><button type="button" class="btn btn-sm btn-primary"'
                            + ' id="tp-onboarding-ext-configure">' + L.extension_cta + '</button></div>'
                    }
                })
            }

            steps.push({ popover: { title: L.finish_title, description: L.finish_body } })

            return steps
        }

        function start() {
            var factory = driverFactory()
            if (factory === null) return
            var steps = buildSteps()
            if (steps.length === 0) return

            persisted = false
            var tour = factory({
                showProgress: true,
                allowClose: true,
                nextBtnText: L.btn_next,
                prevBtnText: L.btn_prev,
                doneBtnText: L.btn_done,
                steps: steps,
                onDestroyed: function () {
                    persistCompletion()
                }
            })
            tour.drive()
        }

        // The extension step embeds a "Configure now" button reusing the existing bridge.
        $(document).on('click', '#tp-onboarding-ext-configure', function (e) {
            e.preventDefault()
            if (window.tpExtAutoconfig && typeof window.tpExtAutoconfig.configure === 'function') {
                window.tpExtAutoconfig.configure()
            }
        })

        // Replay entry-point from the user menu.
        $(document).on('click', '#onboarding-replay', function (e) {
            e.preventDefault()
            start()
        })

        // Public API for manual triggering.
        window.tpOnboarding = { start: start, reset: start }

        // Auto-launch only on a genuine first connection, on the landing page,
        // when not already completed.
        $(function () {
            if (flags.isLandingPage === true
                && flags.firstConnection === true
                && flags.onboardingCompleted !== 1
            ) {
                setTimeout(start, 1000)
            }
        })
    })()
    //]]>
</script>
