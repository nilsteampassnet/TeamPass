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
 * @file      extension-autoconfig.js.php
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
     * Browser-extension auto-configuration bridge.
     *
     * Detects the TeamPass browser extension via a same-origin window.postMessage
     * handshake (the extension content script answers from this page), then offers a
     * one-click configuration. When the extension is detected but not yet configured,
     * the user is auto-prompted. A downloadable JSON config file is the fallback when
     * no extension answers the handshake.
     */
    (function () {
        'use strict'

        var SOURCE_WEBAPP = 'teampass-webapp'
        var SOURCE_EXTENSION = 'teampass-extension'
        var sessionKey = '<?php echo $session->get('key'); ?>'

        var state = {
            detected: false,
            configured: null,
            version: null
        }
        var pendingApply = {}

        function makeRequestId() {
            return 'tpext_' + Math.random().toString(36).slice(2) + Date.now().toString(36)
        }

        // --- Receive replies from the extension content script -----------------
        window.addEventListener('message', function (event) {
            if (event.source !== window) return
            var data = event.data
            if (!data || data.source !== SOURCE_EXTENSION) return

            if (data.type === 'TP_EXT_PONG') {
                state.detected = true
                state.configured = data.configured === true
                state.version = data.version || null
                onExtensionDetected()
            } else if (data.type === 'TP_EXT_APPLY_RESULT') {
                var cb = pendingApply[data.requestId]
                if (cb) {
                    delete pendingApply[data.requestId]
                    cb(data)
                }
            }
        })

        // --- Handshake ----------------------------------------------------------
        var pingAttempts = 0
        var maxPingAttempts = 6
        function pingExtension() {
            if (state.detected) return
            pingAttempts += 1
            window.postMessage({
                source: SOURCE_WEBAPP,
                type: 'TP_EXT_PING',
                requestId: makeRequestId()
            }, window.location.origin)

            if (pingAttempts < maxPingAttempts) {
                setTimeout(pingExtension, 400)
            } else if (!state.detected) {
                onExtensionNotDetected()
            }
        }

        // Active, on-demand probe used when the user explicitly clicks "Configure".
        // The initial handshake may have missed the content script (it injects at
        // document_idle); re-ping and wait briefly before deciding, so a one-off
        // timing miss does not force the file path on a browser where the
        // extension is actually present.
        function probeExtension(callback) {
            if (state.detected) {
                callback(true)
                return
            }
            window.postMessage({
                source: SOURCE_WEBAPP,
                type: 'TP_EXT_PING',
                requestId: makeRequestId()
            }, window.location.origin)

            var waited = 0
            var iv = setInterval(function () {
                waited += 150
                if (state.detected) {
                    clearInterval(iv)
                    callback(true)
                } else if (waited >= 1200) {
                    clearInterval(iv)
                    callback(false)
                }
            }, 150)
        }

        // --- Toastr helpers -----------------------------------------------------
        function showProgress() {
            if (typeof toastr === 'undefined') return
            toastr.remove()
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin"></i>', '', {
                timeOut: 0,
                extendedTimeOut: 0
            })
        }
        function clearToasts() {
            if (typeof toastr !== 'undefined') toastr.remove()
        }
        function errorToast() {
            clearToasts()
            if (typeof toastr !== 'undefined') {
                toastr.error('<?php echo $lang->get('extension_autoconfig_failed'); ?>', '', { timeOut: 5000 })
            }
        }

        function onExtensionDetected() {
            // Manual button stays the primary entry-point; hide the file fallback.
            $('#extension-autoconfig-download').addClass('hidden')

            if (state.configured === false) {
                // Auto-prompt once per browser session.
                if (sessionStorage.getItem('tpExtAutoconfigPrompted') !== '1') {
                    sessionStorage.setItem('tpExtAutoconfigPrompted', '1')
                    promptAutoConfigure()
                }
            }
        }

        function onExtensionNotDetected() {
            // No extension answered: expose the downloadable-file fallback.
            $('#extension-autoconfig-download').removeClass('hidden')
        }

        function promptAutoConfigure() {
            if (typeof toastr === 'undefined') return
            toastr.remove()
            toastr.info(
                '<?php echo $lang->get('extension_autoconfig_prompt'); ?>',
                '<?php echo $lang->get('extension_autoconfig_detected'); ?>', {
                    timeOut: 0,
                    extendedTimeOut: 0,
                    closeButton: true,
                    tapToDismiss: false,
                    onclick: function () {
                        configure()
                    }
                }
            )
        }

        // --- Server bundle ------------------------------------------------------
        function fetchBundle(onSuccess, onError) {
            $.post(
                'sources/users.queries.php', {
                    type: 'build_extension_autoconfig',
                    data: prepareExchangedData(JSON.stringify({}), 'encode', sessionKey),
                    key: sessionKey
                },
                function (response) {
                    var data
                    try {
                        data = prepareExchangedData(response, 'decode', sessionKey)
                    } catch (e) {
                        if (onError) onError()
                        return
                    }
                    if (!data || data.error !== false || !data.bundle) {
                        if (onError) onError(data)
                        return
                    }
                    onSuccess(data.bundle)
                }
            ).fail(function () {
                if (onError) onError()
            })
        }

        // --- Live bridge apply --------------------------------------------------
        function applyViaBridge(bundle) {
            var requestId = makeRequestId()
            var settled = false

            pendingApply[requestId] = function (result) {
                settled = true
                toastr.remove()
                if (result.ok === true && result.pending === true) {
                    // The extension opened a confirmation window; the final result
                    // (success/failure) is shown there.
                    toastr.info('<?php echo $lang->get('extension_autoconfig_confirm_pending'); ?>', '', { timeOut: 6000 })
                } else if (result.ok === true) {
                    toastr.success('<?php echo $lang->get('extension_autoconfig_success'); ?>', '', { timeOut: 3000 })
                } else {
                    toastr.error(result.error || '<?php echo $lang->get('extension_autoconfig_failed'); ?>', '', { timeOut: 5000 })
                }
            }

            window.postMessage({
                source: SOURCE_WEBAPP,
                type: 'TP_EXT_APPLY_CONFIG',
                requestId: requestId,
                bundle: bundle
            }, window.location.origin)

            // Safety net: if the extension never answers, reveal the file fallback
            // (the download button) and report the failure.
            setTimeout(function () {
                if (settled) return
                delete pendingApply[requestId]
                onExtensionNotDetected()
                toastr.remove()
                toastr.warning('<?php echo $lang->get('extension_autoconfig_failed'); ?>', '', { timeOut: 5000 })
            }, 6000)
        }

        // --- Downloadable file fallback ----------------------------------------
        function downloadBundle(bundle) {
            var blob = new Blob([JSON.stringify(bundle, null, 2)], { type: 'application/json' })
            var url = URL.createObjectURL(blob)
            var a = document.createElement('a')
            a.href = url
            a.download = 'teampass-extension-config.json'
            document.body.appendChild(a)
            a.click()
            document.body.removeChild(a)
            URL.revokeObjectURL(url)

            if (typeof toastr !== 'undefined') {
                // Drop any leftover "in progress" toast before showing the warning,
                // and let a click anywhere on the toast dismiss it (not only the X).
                toastr.remove()
                toastr.warning('<?php echo $lang->get('extension_autoconfig_file_warning'); ?>', '', {
                    timeOut: 0,
                    extendedTimeOut: 0,
                    closeButton: true,
                    tapToDismiss: true
                })
            }
        }

        // --- Public actions -----------------------------------------------------
        // "Configure my extension": live, one-click setup over the bridge. It never
        // downloads a file — if the extension does not answer, it reveals the
        // download button and tells the user, so the two buttons stay distinct.
        function configure() {
            showProgress()
            probeExtension(function (detected) {
                if (!detected) {
                    clearToasts()
                    onExtensionNotDetected()
                    if (typeof toastr !== 'undefined') {
                        toastr.info('<?php echo $lang->get('extension_autoconfig_not_detected'); ?>', '', { timeOut: 8000 })
                    }
                    return
                }
                fetchBundle(function (bundle) {
                    applyViaBridge(bundle)
                }, errorToast)
            })
        }

        // "Download configuration file": always produces the JSON file for manual
        // import — the explicit fallback when the live bridge is unavailable.
        function download() {
            showProgress()
            fetchBundle(function (bundle) {
                downloadBundle(bundle)
            }, errorToast)
        }

        // Expose the API for any page that wants a manual entry-point.
        window.tpExtAutoconfig = {
            state: state,
            configure: configure,
            download: download
        }

        // Wire the profile-page buttons when present.
        $(document).on('click', '#extension-autoconfig-configure', function (e) {
            e.preventDefault()
            configure()
        })
        $(document).on('click', '#extension-autoconfig-download', function (e) {
            e.preventDefault()
            download()
        })

        // Kick off detection shortly after load (content script injects at document_idle).
        setTimeout(pingExtension, 300)
    })()
    //]]>
</script>
