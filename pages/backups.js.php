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
 * @file      backups.js.php
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
$userLanguage = $session->get('user-language');
$lang = new Language(($userLanguage !== null && $userLanguage !== '') ? $userLanguage : 'english');

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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('backups') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[

    // Common toastr options for this page (prevents sticky toasts and keeps UI consistent)
    function tpToast(type, message, title, options) {
        if (typeof toastr === 'undefined') return null;
        // Keep Teampass global toastr settings (notably bottom-right position)
        var base = $.extend(true, {}, (toastr.options || {}));
        if (!base.positionClass) base.positionClass = 'toast-bottom-right';

        try { toastr.clear(); } catch (e) {}

        var defaults = {
            closeButton: true,
            progressBar: true,
            newestOnTop: true,
            timeOut: 3500,
            extendedTimeOut: 1000,
            tapToDismiss: true
        };

        toastr.options = $.extend(true, base, defaults, (options || {}));
        if (typeof title === 'undefined' || title === null) title = '';

        if (typeof toastr[type] === 'function') {
            return toastr[type](message, title);
        } else {
            return toastr.info(message, title);
        }

    }

    // A single sticky "in progress" toast (for long operations like backups).
    var tpProgressToast = {
        _toast: null,
        show: function(message, title) {
            if (typeof toastr === 'undefined') return null;

            var opts = {
                closeButton: false,
                progressBar: false,
                timeOut: 0,
                extendedTimeOut: 0,
                tapToDismiss: false,
                newestOnTop: true
            };

            // Close any previous progress toast
            try {
                if (this._toast) { toastr.clear(this._toast); }
            } catch (e) {}
            this._toast = null;

            this._toast = tpToast('info', message, (title || ''), opts);
            return this._toast;
        },
        hide: function() {
            try {
                if (typeof toastr === 'undefined') return;
                if (this._toast) { toastr.clear(this._toast); }
            } catch (e) {}
            this._toast = null;
        }
    };
    
    function tpCopyToClipboard(text) {
        text = (text || '').toString();
        if (text === '') return Promise.reject(new Error('Empty'));
        if (navigator && navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function(resolve, reject) {
            try {
                var $tmp = $('<textarea readonly></textarea>').css({position:'absolute', left:'-9999px', top:'-9999px'}).val(text).appendTo('body');
                $tmp[0].select();
                var ok = document.execCommand('copy');
                $tmp.remove();
                if (ok) resolve();
                else reject(new Error('copy failed'));
            } catch (e) {
                reject(e);
            }
        });
    }

    // Force logout using the existing logout link when possible (CSRF token already embedded in href).
    // Fallback to the generic logout endpoint.
    function tpExtractLogoutUrlFromHtml(resp) {
        if (typeof resp !== 'string') return null;
        // Look for logout link already rendered by TeamPass error page
        var m = resp.match(/href=["']([^"']*includes\/core\/logout\.php\?token=[^"']+)["']/i);
        if (m && m[1]) return m[1];
        return null;
    }

    // Force logout using the existing logout link when possible (CSRF token already embedded in href).
    // If an HTML response contains a logout link with token, use it.
    // Fallback to the generic logout endpoint.
    function tpForceLogout(resp) {
        if (window.tpLogoutInProgress === true) return;
        window.tpLogoutInProgress = true;

        try { window.onbeforeunload = null; } catch (e) {}

        try {
            var urlFromResp = tpExtractLogoutUrlFromHtml(resp);
            if (urlFromResp) {
                window.location.href = urlFromResp;
                return;
            }
        } catch (e) {}

        try {
            var $a = $('a[href*="includes/core/logout.php"]:first');
            if ($a.length && $a.attr('href')) {
                window.location.href = $a.attr('href');
                return;
            }
        } catch (e) {}

        window.location.href = 'includes/core/logout.php';
    }


    // Simple detection: when session is invalidated, PHP returns an HTML error page instead of JSON/encoded payload.
    // Normalize response to string for detection (jQuery may give us a parsed object)
    function tpNormalizeAjaxResponse(resp) {
        try {
            if (resp === null || typeof resp === 'undefined') return '';
            if (typeof resp === 'string') return resp;
            if (typeof resp === 'object') {
                try { return JSON.stringify(resp); } catch (e) {}
            }
            return (resp.toString) ? resp.toString() : '';
        } catch (e) {
            return '';
        }
    }

    // Simple detection: when session is invalidated, the server may return HTML (error page) instead of an encoded payload.
    function tpResponseLooksLikeHtml(resp) {
        var s = tpNormalizeAjaxResponse(resp);
        s = s.replace(/^﻿/, '').trim();
        return s !== '' && s.charAt(0) === '<';
    }

    // Some code paths may return plain JSON when session is invalidated.
    function tpResponseLooksLikeJson(resp) {
        var s = tpNormalizeAjaxResponse(resp);
        s = s.replace(/^﻿/, '').trim();
        return s !== '' && (s.charAt(0) === '{' || s.charAt(0) === '[');
    }

    function tpIsLikelySessionInvalidJson(resp) {
        try {
            var s = tpNormalizeAjaxResponse(resp).replace(/^﻿/, '').trim();
            if (s === '' || (s.charAt(0) !== '{' && s.charAt(0) !== '[')) return false;
            var o = JSON.parse(s);
            if (!o || typeof o !== 'object') return false;
            var msg = ((o.message || o.msg || '') + '').toLowerCase();
            var code = ((o.error_code || o.code || '') + '').toLowerCase();
            return (o.error === true || o.status === 'error') && (msg.indexOf('disconnected') !== -1 || msg.indexOf('not allowed') !== -1 || code.indexOf('not_allowed') !== -1);
        } catch (e) {
            return false;
        }
    }

	// Session key (used for encrypted AJAX payloads)
	var tpSessionKey = "<?php echo $session->get('key'); ?>";

	// Disable background refreshes that could trigger decrypt errors once the session gets invalidated
	function tpDisableBackgroundRefresh() {
        try {
            window.tpBackgroundRefreshDisabled = true;

            // Stop disk usage refresh
            if (window.tpDiskUsageInterval) {
                clearInterval(window.tpDiskUsageInterval);
                window.tpDiskUsageInterval = null;
            }

            // Stop "run now" poll if active
            try {
                if (typeof tpScheduled !== 'undefined' && tpScheduled && typeof tpScheduled.stopRunNowPoll === 'function') {
                    tpScheduled.stopRunNowPoll();
                }
            } catch (e) {}

            // Abort any further background POSTs once we are in CLI restore mode
            if (!window.tpCliAjaxPrefilterInstalled && typeof $ !== 'undefined' && typeof $.ajaxPrefilter === 'function') {
                window.tpCliAjaxPrefilterInstalled = true;
                $.ajaxPrefilter(function(options, originalOptions, jqXHR) {
                    try {
                        if (window.tpBackgroundRefreshDisabled !== true) return;

                        // Allow explicit calls marked as allowed during CLI flow
                        if (originalOptions && originalOptions.tpAllowDuringCli === true) return;

                        var url = (options && options.url) ? options.url.toString() : '';
                        if (url.indexOf('includes/core/logout.php') !== -1 || url.indexOf('includes/core/login.php') !== -1) return;

                        // This page does not need server refreshes once the CLI command is displayed.
                        if (((options.type || '') + '').toUpperCase() === 'POST') {
                            try { jqXHR.abort(); } catch (e) {}
                        }
                    } catch (e) {}
                });
            }

            // Wrap decode helpers to avoid showing the built-in "Malformed UTF-8" modal when server returns HTML/JSON
            try {
                if (!window.tpDecodeWrapped && typeof decodeQueryReturn === 'function') {
                    window.tpDecodeWrapped = true;
                    window.tpOriginalDecodeQueryReturn = decodeQueryReturn;
                    decodeQueryReturn = function(resp, key) {
                        if (window.tpBackgroundRefreshDisabled === true && (tpResponseLooksLikeHtml(resp) || tpIsLikelySessionInvalidJson(resp))) {
                            try { tpForceLogout(resp); } catch (e) {}
                            return '{}';
                        }
                        return window.tpOriginalDecodeQueryReturn(resp, key);
                    };
                }
            } catch (e) {}

            try {
                if (!window.tpPrepareWrapped && typeof prepareExchangedData === 'function') {
                    window.tpPrepareWrapped = true;
                    window.tpOriginalPrepareExchangedData = prepareExchangedData;
                    prepareExchangedData = function(resp, mode, key) {
                        if (window.tpBackgroundRefreshDisabled === true && mode === 'decode' && (tpResponseLooksLikeHtml(resp) || tpIsLikelySessionInvalidJson(resp))) {
                            try { tpForceLogout(resp); } catch (e) {}
                            return '{}';
                        }
                        return window.tpOriginalPrepareExchangedData(resp, mode, key);
                    };
                }
            } catch (e) {}

        } catch (e) {}
    }

    function tpSafeDecodeQueryReturn(resp) {
        var raw = tpNormalizeAjaxResponse(resp);
        if (tpResponseLooksLikeHtml(raw) || tpIsLikelySessionInvalidJson(raw)) {
            tpDisableBackgroundRefresh();
            tpForceLogout(raw);
            return null;
        }
        try {
            return decodeQueryReturn(raw, tpSessionKey);
        } catch (e) {
            // Most common case: CryptoJS "Malformed UTF-8 data" when server returned HTML/JSON (session invalid)
            tpDisableBackgroundRefresh();
            tpForceLogout(raw);
            return null;
        }
    }
function tpSafePrepareDecode(resp) {
        var raw = tpNormalizeAjaxResponse(resp);
        if (tpResponseLooksLikeHtml(raw) || tpIsLikelySessionInvalidJson(raw)) {
            tpDisableBackgroundRefresh();
            tpForceLogout(raw);
            return null;
        }
        try {
            return prepareExchangedData(raw, "decode", tpSessionKey);
        } catch (e) {
            tpDisableBackgroundRefresh();
            tpForceLogout(raw);
            return null;
        }
    }
// Lock UI during DB restore and show a global progress modal.
    var tpRestoreLock = {
        active: false,
        start: function() {
            this.active = true;
            // Stop background refreshes during restore (prevents decrypt errors when session becomes invalid)
            try {
                if (window.tpDiskUsageInterval) { clearInterval(window.tpDiskUsageInterval); window.tpDiskUsageInterval = null; }
            } catch (e) {}
            this._logoutScheduled = false;
            try { $('#tp-restore-progress-bar').addClass('progress-bar-animated'); } catch (e) {}
            try {
                $('#tp-restore-progress-title').text('<?php echo addslashes($lang->get('in_progress')); ?>');
                $('#tp-restore-progress-msg').text('<?php echo addslashes($lang->get('restore_in_progress_auto_logout')); ?>');
            } catch (e) {}
            this.update(0);

            try {
                $('#tp-restore-progress-modal').modal({ backdrop: 'static', keyboard: false });
            } catch (e) {}

            // Prevent navigation/refresh while restore is running
            window.onbeforeunload = function() { return ''; };
        },
        update: function(pct) {
            pct = Math.max(0, Math.min(100, parseInt(pct || 0, 10)));
            try { $('#tp-restore-progress-pct').text(pct); } catch (e) {}
            try {
                $('#tp-restore-progress-bar')
                    .css('width', pct + '%')
                    .attr('aria-valuenow', pct);
            } catch (e) {}

            // Move the cat along the progress track (small personal touch)
            try {
                var catPct = Math.max(2, Math.min(98, pct));
                $('#tp-restore-progress-cat').css('left', catPct + '%');
            } catch (e) {}
        },
        cancel: function() {
            // Cancel restore UI lock (used if restore cannot start)
            this.update(0);
            try { window.onbeforeunload = null; } catch (e) {}
            try { $('#tp-restore-progress-modal').modal('hide'); } catch (e) {}
            try { $('#tp-restore-progress-bar').removeClass('progress-bar-animated'); } catch (e) {}
            this.active = false;
        },
        finish: function() {
            // Keep modal open; only action is logout
            this.update(100);
            try {
                $('#tp-restore-progress-title').text('<?php echo addslashes($lang->get('done')); ?>');
                $('#tp-restore-progress-msg').text('<?php echo addslashes($lang->get('restore_done_now_logout')); ?>');
                try { window.onbeforeunload = null; } catch (e) {}
                if (!this._logoutScheduled) { this._logoutScheduled = true; setTimeout(function(){ tpForceLogout(''); }, 900); }
                $('#tp-restore-progress-bar').removeClass('progress-bar-animated');
            } catch (e) {}
        }
    };


    // Bootstrap tooltips on demand
    $(function() {
        // Initialize tooltips globally on the body for dynamic elements
        try { 
            $('body').tooltip({
                selector: '[data-toggle="tooltip"]',
                boundary: 'window',
                trigger: 'hover' // Forces trigger on hover only to avoid click issues
            }); 
        } catch (e) {
            console.error("Tooltip initialization failed", e);
        }
    });

    // Confirm restore modal helper
    var tpConfirmRestoreCallback = null;
    function tpShowConfirmRestore(messageHtml, onConfirm) {
        tpConfirmRestoreCallback = (typeof onConfirm === 'function') ? onConfirm : null;
        $('#tp-confirm-restore-body').html(messageHtml);
        $('#tp-confirm-restore-modal').modal('show');
    }

    function tpGetOnTheFlyServerFile() {
        return ($('#onthefly-restore-server-file').val() || $('#onthefly-restore-serverfile').val() || '').toString();
    }

    // ---------------------------------------------------------------------
    // Restore compatibility (schema-level) preflight
    // ---------------------------------------------------------------------
    var tpBckVersionUnknown = "<?php echo addslashes($lang->get('bck_version_unknown')); ?>";
    var tpBckIncompatTitle = "<?php echo addslashes($lang->get('bck_restore_incompatible_version_title')); ?>";
    var tpBckIncompatBody = "<?php echo addslashes($lang->get('bck_restore_incompatible_version_body')); ?>";
    var tpBckBackupVersionLabel = "<?php echo addslashes($lang->get('bck_restore_backup_version')); ?>";
    var tpBckExpectedVersionLabel = "<?php echo addslashes($lang->get('bck_restore_expected_version')); ?>";
    var tpBckLegacyNoMeta = "<?php echo addslashes($lang->get('bck_restore_legacy_no_metadata')); ?>";

// ---------------------------------------------------------------------
// On-the-fly upload safety (prevent duplicates in <files>)
// ---------------------------------------------------------------------
// Template uses {FILENAME}
var tpBckUploadFileAlreadyExistsTpl = "<?php echo addslashes($lang->get('bck_upload_error_file_already_exists')); ?>";

// ---------------------------------------------------------------------
// Orphan metadata (.meta.json) monitoring & purge
// ---------------------------------------------------------------------
// Templates use {TOTAL} {FILES} {SCHEDULED} and {DELETED}
var tpBckMetaOrphansTooltipTpl = "<?php echo addslashes($lang->get('bck_meta_orphans_tooltip')); ?>";
var tpBckMetaOrphansTooltipNone = "<?php echo addslashes($lang->get('bck_meta_orphans_tooltip_none')); ?>";
var tpBckMetaOrphansPurgeDoneTpl = "<?php echo addslashes($lang->get('bck_meta_orphans_purge_done')); ?>";
var tpBckMetaOrphansPurgeNone = "<?php echo addslashes($lang->get('bck_meta_orphans_purge_none')); ?>";



    function tpFmtTpVersion(v) {
        v = (v || '').toString().trim();
        return v !== '' ? v : tpBckVersionUnknown;
    }

    function tpShowRestoreIncompatMessage(backupVersion, expectedVersion) {
        var msg = '<div class="mb-2">' + tpBckIncompatBody + '</div>';
        msg += '<div><b>' + tpBckBackupVersionLabel + ':</b> ' + tpFmtTpVersion(backupVersion) + '</div>';
        msg += '<div><b>' + tpBckExpectedVersionLabel + ':</b> ' + tpFmtTpVersion(expectedVersion) + '</div>';

        // Prefer bootstrap modal if available, fallback to toast
        try {
            $('#tp-confirm-restore-title').text(tpBckIncompatTitle);
            $('#tp-confirm-restore-body').html(msg);
            $('#tp-confirm-restore-yes').hide();
            $('#tp-confirm-restore-modal').modal('show');
        } catch (e) {
            tpToast('error', msg);
        }
    }

    function tpPreflightRestoreCompatibility(payload, onOk) {
        payload = payload || {};
        $.post(
            "sources/backups.queries.php",
            {
                type: "preflight_restore_compatibility",
	                data: prepareExchangedData(JSON.stringify(payload), "encode", tpSessionKey),
	                key: tpSessionKey
            },
            function (resp) {
	                var r = tpSafePrepareDecode(resp);
	                if (r === null) {
	                    return;
	                }
                if (!r || r.error === true) {
                    tpToast('error', (r && r.message) ? r.message : "<?php echo addslashes($lang->get('error')); ?>");
                    return;
                }

                if (r.is_compatible === true) {
                    if (typeof onOk === 'function') onOk(r);
                    return;
                }

                var rs = (r.reason || '');
                if (rs === 'LEGACY_NO_METADATA' || rs === 'MISSING_VERSION_METADATA') {
                    tpToast('error', tpBckLegacyNoMeta);
                    return;
                }

                if (rs === 'SCHEMA_MISMATCH' || rs === 'MISSING_SCHEMA') {
                    tpToast('error', "<?php echo addslashes($lang->get('bck_restore_schema_mismatch')); ?>" + ' ' + (r.backup_schema_level || '?') + ' / ' + (r.expected_schema_level || '?') + ' (<?php echo addslashes($lang->get('bck_restore_expected_version')); ?> ' + (r.expected_tp_files_version || '?') + ')');
                    return;
                }

                if (rs === 'FILE_NOT_FOUND') {
                    tpToast('error', "<?php echo addslashes($lang->get('bck_restore_file_not_found')); ?>");
                    return;
                }

                tpShowRestoreIncompatMessage(r.backup_tp_files_version || '', r.expected_tp_files_version || '');
            }
        );
    }

    function tpPrepareRestoreCli(payload, onOk, onErr) {
        payload = payload || {};
        $.post(
            "sources/backups.queries.php",
            {
                type: "prepare_restore_cli",
	            data: prepareExchangedData(JSON.stringify(payload), "encode", tpSessionKey),
	            key: tpSessionKey
            },
            function(data) {
	            data = tpSafeDecodeQueryReturn(data);
	            if (data === null) {
	                return;
	            }

                if (data.error === true) {
                    if (typeof onErr === 'function') onErr(data);
                    return;
                }
                if (typeof onOk === 'function') onOk(data);
            }
        ).fail(function(xhr) {
            if (typeof onErr === 'function') onErr({error: true, message: 'Request failed', xhr: xhr});
        });
    }

	function tpShowRestoreCliModal(cmd, cmdNoCd, expiresAt, warnings, variants) {
        warnings = warnings || [];
	    variants = variants || [];

	        try {
	            $('#tp-cli-restore-command').val(cmd || '');
	
	            var raw = '';
	            if (Array.isArray(variants) && variants.length > 0) {
	                var parts = [];
	                variants.forEach(function(v) {
	                    if (!v || !v.command) return;
	                    var lbl = (v.label || '').toString().trim();
	                    if (lbl !== '') {
	                        parts.push('[' + lbl + ']\n' + v.command);
	                    } else {
	                        parts.push(v.command);
	                    }
	                });
	                raw = parts.join("\n\n");
	            } else {
	                raw = (cmdNoCd ? (cmdNoCd + "\n") : "") + (cmd ? cmd : '');
	            }
	            $('#tp-cli-restore-command-raw').text((raw || '').trim());

            if (expiresAt && parseInt(expiresAt, 10) > 0) {
                var dt = new Date(parseInt(expiresAt, 10) * 1000);
                $('#tp-cli-restore-expires').text("<?php echo addslashes($lang->get('bck_restore_cli_token_expires')); ?>" + ' ' + dt.toLocaleString());
            } else {
                $('#tp-cli-restore-expires').text('');
            }

            if (warnings.length > 0) {
                var msgs = [];
                warnings.forEach(function(w) {
                    if (w === 'VERSION_NOT_VERIFIED') {
                        msgs.push("<?php echo addslashes($lang->get('bck_restore_cli_warning_version_not_verified')); ?>");
                    } else {
                        msgs.push(w);
                    }
                });
                $('#tp-cli-restore-warnings').html('<b><?php echo addslashes($lang->get('bck_restore_cli_warnings')); ?></b><br>' + msgs.join('<br>')).show();
            } else {
                $('#tp-cli-restore-warnings').hide().empty();
            }

	            // Prevent background refreshes from triggering CryptoJS errors once the session is invalidated
	            tpDisableBackgroundRefresh();
	
	            $('#tp-cli-restore-modal').modal('show');
        } catch (e) {
            tpToast('info', cmd || cmdNoCd || '');
        }
    }

    $(document).on('click', '#tp-cli-restore-copy', function(e) {
        e.preventDefault();
        var v = $('#tp-cli-restore-command').val() || '';
        if (v === '') return;

        try {
            navigator.clipboard.writeText(v);
            tpToast('success', "<?php echo addslashes($lang->get('copied')); ?>");
        } catch (e) {
            // Fallback
            var $tmp = $('<textarea>');
            $('body').append($tmp);
            $tmp.val(v).select();
            document.execCommand('copy');
            $tmp.remove();
            tpToast('success', "<?php echo addslashes($lang->get('copied')); ?>");
        }
    });

	// Clean logout for the initiator (avoids CryptoJS "Malformed UTF-8" errors when the CLI restore disconnects the session)
	$(document).on('click', '#tp-cli-restore-logout', function(e) {
	    e.preventDefault();
	    tpDisableBackgroundRefresh();
	    // Give the browser a chance to close the modal cleanly
	    try { $('#tp-cli-restore-modal').modal('hide'); } catch (ignored) {}
	    setTimeout(function() {
	        tpForceLogout('');
	    }, 150);
	});


        $(document).on('click', '#tp-confirm-restore-yes', function(e) {
            e.preventDefault();
            $('#tp-confirm-restore-modal').modal('hide');

            if (typeof tpConfirmRestoreCallback === 'function') {
                var cb = tpConfirmRestoreCallback;
                tpConfirmRestoreCallback = null;
                cb();
            }
        });

        // Restore key modal helper (used when a scheduled backup cannot be decrypted with the current instance key)
        var tpRestoreKeyCallback = null;
        function tpShowRestoreKeyModal(errorMessage, onConfirm, presetKey) {
            tpRestoreKeyCallback = (typeof onConfirm === 'function') ? onConfirm : null;

            var msg = (errorMessage || '').toString();
            if (msg !== '') {
                $('#tp-restore-key-modal-error').removeClass('hidden').text(msg);
            } else {
                $('#tp-restore-key-modal-error').addClass('hidden').text('');
            }

            $('#tp-restore-key-modal-input').val((presetKey || '').toString());
            $('#tp-restore-key-modal').modal('show');
        }

        $(document).on('click', '#tp-restore-key-modal-yes', function(e) {
            e.preventDefault();
            var k = ($('#tp-restore-key-modal-input').val() || '').toString();
            if (k === '') {
                tpToast('error', "<?php echo addslashes($lang->get('bck_restore_key_required')); ?>");
                return;
            }
            $('#tp-restore-key-modal').modal('hide');
            if (typeof tpRestoreKeyCallback === 'function') {
                var cb = tpRestoreKeyCallback;
                tpRestoreKeyCallback = null;
                cb(k);
            }
        });

    // Copy instance key (scheduled note info icon)
    $(document).on('click', '.tp-copy-instance-key', function(e) {
        e.preventDefault();
        e.stopPropagation();

        $.post(
            "sources/backups.queries.php",
            {
                type: "copy_instance_key",
                data: prepareExchangedData(JSON.stringify({}), "encode", "<?php echo $session->get('key'); ?>"),
                key: "<?php echo $session->get('key'); ?>"
            },
            function (resp) {
                var r = tpSafePrepareDecode(resp);
                if (r === null) { return; }
                if (!r || r.error === true) {
                    tpToast('error', (r && r.message) ? r.message : "<?php echo addslashes($lang->get('bck_instance_key_copy_failed')); ?>");
                    return;
                }
                tpCopyToClipboard(r.instanceKey || '')
                    .then(function() {
                        tpToast('success', "<?php echo addslashes($lang->get('bck_instance_key_copied')); ?>");
                    })
                    .catch(function() {
                        tpToast('error', "<?php echo addslashes($lang->get('bck_instance_key_copy_failed')); ?>");
                    });
            }
        );
    });

    // Delete on-the-fly server backup
    $(document).on('click', '.onthefly-server-backup-delete', function () {
        const fileName = $(this).data('filename');
        console.log('Delete on-the-fly server backup:', fileName);
        if (!fileName) return;
        
        // Call confirm dialog
        launchConfirmDialog(
            "<?php echo addslashes($lang->get('del_button')); ?>", // Title
            "<?php echo addslashes($lang->get('bck_onthefly_confirm_delete')); ?><br><br><b>" + fileName + "</b>", // Message
            function () {
                $.post(
                    "sources/backups.queries.php",
                    {
                        type: "onthefly_delete_backup",
                        data: prepareExchangedData(JSON.stringify({ file: fileName }), "encode", "<?php echo $session->get('key'); ?>"),
                        key: "<?php echo $session->get('key'); ?>"
                    },
                    function (data) {
                        data = tpSafePrepareDecode(data);
                        if (data === null) { return; }
                        
                        if (data.error === true) {
                            toastr.error(data.message || "<?php echo addslashes($lang->get('error')); ?>");
                            return;
                        }

                        // Clean restoration fields
                        if ($('#onthefly-restore-serverfile').val() === fileName) {
                            $('#onthefly-restore-serverfile, #onthefly-restore-server-file, #onthefly-restore-server-scope').val('');
                            $('#onthefly-restore-file-text, #onthefly-restore-server-selected-name').text('');
                            $('#onthefly-restore-server-selected').hide();
                        }

                        toastr.success("<?php echo addslashes($lang->get('bck_onthefly_deleted')); ?>");
                        loadOnTheFlyServerBackups();
                    }
                );
            },
        );
    });

    // Edit comment for on-the-fly server backup
    $(document).on('click', '.onthefly-server-backup-edit-comment', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const fileName = $(this).data('filename');
        if (!fileName) return;

        const $row = $(this).closest('tr');
        let commentRaw = '';
        try {
            commentRaw = decodeURIComponent($row.attr('data-comment') || '');
        } catch (err) {
            commentRaw = '';
        }

        $('#tp-onthefly-comment-file').text(fileName);
        $('#tp-onthefly-comment-text').val(commentRaw);
        $('#tp-onthefly-comment-error').addClass('hidden').text('');
        $('#tp-onthefly-comment-modal').data('filename', fileName);
        $('#tp-onthefly-comment-modal').modal('show');
    });

    $(document).on('click', '#tp-onthefly-comment-save', function () {
        const fileName = ($('#tp-onthefly-comment-modal').data('filename') || '').toString();
        if (fileName === '') return;
        const comment = ($('#tp-onthefly-comment-text').val() || '').toString();

        $.post(
            "sources/backups.queries.php",
            {
                type: "onthefly_update_comment",
                data: prepareExchangedData(JSON.stringify({ file: fileName, comment: simplePurifier(comment) }), "encode", "<?php echo $session->get('key'); ?>"),
                key: "<?php echo $session->get('key'); ?>"
            },
            function (data) {
                data = tpSafePrepareDecode(data);
                if (data === null) { return; }

                if (data.error === true) {
                    $('#tp-onthefly-comment-error').removeClass('hidden').text(data.message || "<?php echo addslashes($lang->get('error')); ?>");
                    return;
                }

                $('#tp-onthefly-comment-modal').modal('hide');
                toastr.success("<?php echo addslashes($lang->get('bck_onthefly_comment_saved')); ?>");
                loadOnTheFlyServerBackups();
            }
        );
    });


    $(document).on('click', '.key-generate', function() {
        $.post(
            "sources/main.queries.php", {
                type: "generate_password",
                type_category: 'action_user',
                size: "<?php echo $SETTINGS['pwd_maximum_length']; ?>",
                lowercase: "true",
                numerals: "true",
                capitalize: "true",
                symbols: "false",
                secure: "true",
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                data = tpSafePrepareDecode(data);
                if (data === null) { return; }

                if (data.key !== "") {
                    $('#onthefly-backup-key').val(data.key);
                }
            }
        );
    });

    $(document).on('click', '.btn-choose-file', function() {
        $('#onthefly-restore-finished, #onthefly-backup-progress')
            .addClass('hidden')
            .html('');
    });

        // Load existing on-the-fly backups stored on server (<files> directory)
// Simple template helper: replaces {KEY} tokens
function tpTpl(str, map) {
    str = (str || '').toString();
    map = map || {};
    try {
        Object.keys(map).forEach(function (k) {
            str = str.split('{' + k + '}').join((map[k] !== null && typeof map[k] !== 'undefined') ? String(map[k]) : '');
        });
    } catch (e) {}
    return str;
}

// ---------------------------------------------------------------------
// Orphan metadata (.meta.json) monitoring & purge (files + files/backups)
// ---------------------------------------------------------------------
var tpMetaOrphansState = { total: 0, files: 0, scheduled: 0 };

function tpUpdateMetaOrphansButton(state) {
    state = state || { total: 0, files: 0, scheduled: 0 };
    var total = parseInt(state.total || 0, 10);
    var filesCnt = parseInt(state.files || 0, 10);
    var schedCnt = parseInt(state.scheduled || 0, 10);

    tpMetaOrphansState = { total: total, files: filesCnt, scheduled: schedCnt };

    var $btn = $('#onthefly-meta-orphans-btn');
    var $badge = $('#onthefly-meta-orphans-badge');

    if (!$btn.length) return;

    // Badge + visual hint only when orphans exist
    if (total > 0) {
        $badge.removeClass('d-none').text(total);
        try { $btn.addClass('btn-warning').removeClass('btn-outline-warning'); } catch (e) {}
        $btn.attr('aria-label', 'Meta orphans: ' + total);
        $btn.data('tp-has-orphans', 1);
        $btn.attr('title', tpTpl(tpBckMetaOrphansTooltipTpl, {
            TOTAL: total,
            FILES: filesCnt,
            SCHEDULED: schedCnt
        }));
    } else {
        $badge.addClass('d-none').text('0');
        try { $btn.addClass('btn-outline-warning').removeClass('btn-warning'); } catch (e) {}
        $btn.data('tp-has-orphans', 0);
        $btn.attr('title', tpBckMetaOrphansTooltipNone);
    }

    // Refresh tooltip content if bootstrap tooltips are used
    try {
        if (typeof $btn.tooltip === 'function') {
            $btn.tooltip('dispose').tooltip({ boundary: 'window', trigger: 'hover' });
        }
    } catch (e) {}
}

function tpRefreshMetaOrphansIndicator() {
    if ($('#onthefly-meta-orphans-btn').length === 0) return;

    $.post(
        "sources/backups.queries.php",
        {
            type: "bck_meta_orphans_status",
            data: prepareExchangedData(JSON.stringify({}), "encode", tpSessionKey),
            key: tpSessionKey
        },
        function (resp) {
            var r = tpSafePrepareDecode(resp);
            if (r === null) { return; }
            if (r.error === true) {
                // Keep button usable but do not spam toasts
                return;
            }
            tpUpdateMetaOrphansButton({
                total: r.total || 0,
                files: r.files || 0,
                scheduled: r.scheduled || 0
            });
        }
    );
}

$(document).on('click', '#onthefly-meta-orphans-btn', function (e) {
    e.preventDefault();
    e.stopPropagation();

    // Always refresh just before action (cheap + avoids stale badge)
    tpRefreshMetaOrphansIndicator();

    // If none, tell the user and bail
    if (parseInt(tpMetaOrphansState.total || 0, 10) <= 0) {
        toastr.remove();
        toastr.info(tpBckMetaOrphansPurgeNone, '', { timeOut: 2500 });
        return;
    }

    tpProgressToast.show('<?php echo addslashes($lang->get('in_progress')); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

    $.post(
        "sources/backups.queries.php",
        {
            type: "bck_meta_orphans_purge",
            data: prepareExchangedData(JSON.stringify({}), "encode", tpSessionKey),
            key: tpSessionKey
        },
        function (resp) {
            tpProgressToast.hide();
            var r = tpSafePrepareDecode(resp);
            if (r === null) { return; }

            if (r.error === true) {
                toastr.remove();
                toastr.error(r.message || "<?php echo addslashes($lang->get('error')); ?>", '', { timeOut: 6000, progressBar: true });
                return;
            }

            var deleted = parseInt(r.deleted || 0, 10);
            if (deleted > 0) {
                toastr.remove();
                toastr.warning(tpTpl(tpBckMetaOrphansPurgeDoneTpl, { DELETED: deleted }), '', { timeOut: 4000, progressBar: true });
            } else {
                toastr.remove();
                toastr.info(tpBckMetaOrphansPurgeNone, '', { timeOut: 2500 });
            }

            // Refresh indicator + list (best effort)
            tpRefreshMetaOrphansIndicator();
            loadOnTheFlyServerBackups();
        }
    );
});

        function tpFmtBytes(bytes) {
            if (bytes === null || bytes === undefined) return '';
            let b = parseInt(bytes, 10);
            if (isNaN(b) || b < 0) return '';
            const units = ['B','KB','MB','GB','TB'];
            let u = 0;
            while (b >= 1024 && u < units.length - 1) {
                b = b / 1024;
                u++;
            }
            return (u === 0 ? Math.round(b) : b.toFixed(1)) + ' ' + units[u];
        }


        function tpEscapeHtml(str) {
            str = (str || '').toString();
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function tpOneLine(str) {
            return (str || '').toString()
                .replace(/[\r\n]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        }

        function tpTruncate(str, maxLen) {
            str = (str || '').toString();
            maxLen = parseInt(maxLen, 10) || 0;
            if (maxLen <= 0 || str.length <= maxLen) {
                return str;
            }
            return str.substring(0, Math.max(1, maxLen - 1)) + '…';
        }

        function loadOnTheFlyServerBackups() {
            if ($('#onthefly-server-backups-tbody').length === 0) {
                return;
            }

            // Refresh orphan metadata indicator (best effort)
            tpRefreshMetaOrphansIndicator();

            $('#onthefly-server-backups-tbody').html(
                '<tr><td colspan="5" class="text-muted"><?php echo addslashes($lang->get('bck_onthefly_loading')); ?></td></tr>'
            );

            $.post(
                "sources/backups.queries.php",
                {
                    type: "onthefly_list_backups",
                    data: prepareExchangedData(JSON.stringify({}), "encode", "<?php echo $session->get('key'); ?>"),
                    key: "<?php echo $session->get('key'); ?>"
                },
                function (data) {
                    data = tpSafePrepareDecode(data);
                if (data === null) { return; }

                    if (data.error === true) {
                        toastr.error(data.message || "<?php echo addslashes($lang->get('error')); ?>");
                        return;
                    }

                    let html = '';
                    const currentSelected = ($('#onthefly-restore-serverfile').val() || '').toString();
                    if (!data.files || data.files.length === 0) {
                        html = '<tr><td colspan="5" class="text-muted"><?php echo addslashes($lang->get('bck_onthefly_no_backups_found')); ?></td></tr>';
                        $('#onthefly-server-backups-tbody').html(html);
                        return;
                    }

                    data.files.forEach(function (f, idx) {
                        const name = (f.name || '');
                        const dt = new Date((parseInt(f.mtime, 10) || 0) * 1000).toLocaleString();
                        const sz = tpFmtBytes(f.size_bytes);
                        const dl = (f.download || '');
                        const commentRaw = (f.comment || '').toString();
                        const commentOneLine = tpOneLine(commentRaw);
                        const commentDisplay = tpTruncate(commentOneLine, 80);
                        const commentAttr = encodeURIComponent(commentRaw);

                        const isSelected = (currentSelected !== '' && currentSelected === name);
                        const fileKey = encodeURIComponent(name);

                        // Avoid breaking HTML attribute if filename contains quotes
                        const titleName = (name + '').replace(/"/g, '&quot;');

                        html += '<tr title="' + titleName + '" data-file="' + fileKey + '" data-comment="' + commentAttr + '" class="' + (isSelected ? 'table-active' : '') + '">';
                        html += '  <td class="text-nowrap">';
                        html += '    <div class="form-check m-0">';
                        html += '      <input class="form-check-input tp-onthefly-backup-radio" type="radio" name="onthefly-server-backup-radio" id="onthefly-bck-' + idx + '" value="' + titleName + '"' + (isSelected ? ' checked' : '') + '>';
                        html += '      <label class="form-check-label" for="onthefly-bck-' + idx + '">' + dt + '</label>';
                        html += '    </div>';
                        html += '  </td>';
                        html += '  <td>' + sz + '</td>';
                        var tpv = (f.tp_files_version || '');
                        html += '  <td class="text-nowrap">' + tpFmtTpVersion(tpv) + '</td>';
                        if (commentOneLine !== '') {
                            html += '  <td class="text-truncate" style="max-width:260px;">';
                            html += '    <span data-toggle="tooltip" title="' + tpEscapeHtml(commentOneLine) + '">' + tpEscapeHtml(commentDisplay) + '</span>';
                            html += '  </td>';
                        } else {
                            html += '  <td class="text-muted">-</td>';
                        }

                        html += '  <td class="text-right text-nowrap">';
                        html += '    <div class="btn-group btn-group-sm" role="group">';
                        if (dl !== '') {
                            html += '      <a class="btn btn-outline-primary" href="' + dl + '" download title="<?php echo addslashes($lang->get('bck_onthefly_download')); ?>" aria-label="<?php echo addslashes($lang->get('bck_onthefly_download')); ?>" data-toggle="tooltip">';
                            html += '        <i class="fas fa-download"></i>';
                            html += '      </a>';
                        }
                        html += '      <button type="button" class="btn btn-outline-secondary onthefly-server-backup-edit-comment" data-filename="' + titleName + '" title="<?php echo addslashes($lang->get('bck_onthefly_edit_comment')); ?>" aria-label="<?php echo addslashes($lang->get('bck_onthefly_edit_comment')); ?>" data-toggle="tooltip"><i class="fas fa-pen"></i></button>';
                        html += '      <button type="button" class="btn btn-outline-danger onthefly-server-backup-delete" data-filename="' + titleName + '" title="<?php echo addslashes($lang->get('delete')); ?>" aria-label="<?php echo addslashes($lang->get('delete')); ?>" data-toggle="tooltip">';
                        html += '        <i class="fas fa-trash"></i>';
                        html += '      </button>';
                        html += '    </div>';
                        html += '  </td>';
                        html += '</tr>';
                    });

                    $('#onthefly-server-backups-tbody').html(html);
                }
            );
        }

        $(document).on('click', '#onthefly-server-backups-refresh', function () {
            loadOnTheFlyServerBackups();
        });

        function tpSelectOnTheFlyServerBackup(fileName) {
            fileName = (fileName || '').toString();
            if (fileName === '') return;

            // Select server-side file for restore and clear any uploaded operation id
            // This is an on-the-fly server backup stored under <files> (not scheduled scope)
            $('#onthefly-restore-serverfile').val(fileName); // used for validation
            $('#onthefly-restore-server-scope').val('');
            $('#onthefly-restore-server-file').val(fileName);

            // Scheduled-selection info box is not relevant here
            $('#onthefly-restore-server-selected').hide();
            $('#onthefly-restore-server-selected-name').text('');

            // Clear any uploaded operation id
            $('#onthefly-restore-file').data('operation-id', '');
            $('#onthefly-restore-file-text').text(fileName + ' (server)');

            // Highlight row
            try {
                var fileKey = encodeURIComponent(fileName);
                $('#onthefly-server-backups-tbody tr').removeClass('table-active');
                $('#onthefly-server-backups-tbody tr[data-file="' + fileKey + '"]').addClass('table-active');
            } catch (e) {}

            tpToast("info", "<?php echo addslashes($lang->get('bck_onthefly_selected_backup')); ?>" + " : " + fileName);
        }

        // Radio selection (same behavior as scheduled)
        $(document).on('change', '.tp-onthefly-backup-radio', function () {
            tpSelectOnTheFlyServerBackup($(this).val());
        });

        // Row click selects the radio (excluding buttons/links)
        $(document).on('click', '#onthefly-server-backups-tbody tr', function (e) {
            if ($(e.target).closest('a,button,input,label').length) {
                return;
            }
            var radio = $(this).find('.tp-onthefly-backup-radio');
            if (radio.length) {
                radio.prop('checked', true).trigger('change');
            }
        });

    $(document).on('click', '.start', function() {
        var action = $(this).data('action');

        if (action === 'onthefly-backup') {
            // PERFORM ONE BACKUP
            if ($('#onthefly-backup-key').val() !== '') {
                tpExclusiveUsers.ensure(function() {
                // Show cog
                tpProgressToast.show('<?php echo addslashes($lang->get('in_progress')); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

                // Prepare data
                var data = {
                    'encryptionKey': simplePurifier($('#onthefly-backup-key').val()),
                    'comment': simplePurifier($('#onthefly-backup-comment').val() || ''),
                };

                //send query
                $.post(
                    "sources/backups.queries.php", {
                        type: "onthefly_backup",
                        data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                        key: "<?php echo $session->get('key'); ?>"
                    },
                    function(data) {
                        //decrypt data
                        var _decoded = tpSafeDecodeQueryReturn(data);
                        if (_decoded === null) { return; }
                        data = _decoded;
                        console.log(data);

                        if (data.error === true) {
                            // ERROR
                            tpProgressToast.hide();
                            toastr.remove();
                            toastr.error(
                                '<?php echo addslashes($lang->get('server_answer_error') . '<br />' . $lang->get('server_returned_data') . ':<br />'); ?>' + data.error,
                                '<?php echo addslashes($lang->get('error')); ?>', {
                                    timeOut: 5000,
                                    progressBar: true
                                }
                            );
                        } else {
                            // Do NOT store the key in DB. It is only used to encrypt/decrypt the backup.
                            tpProgressToast.hide();
                            // No inline green alert here (bottom-right toast + server list refresh are enough)
                            $('#onthefly-backup-progress').addClass('hidden').empty();
                            $('#onthefly-backup-comment').val('');


                            // Refresh on-the-fly server backups list (new file is stored in <files>)
                            try { loadOnTheFlyServerBackups(); } catch (e) {}

                            // Inform user
                            toastr.remove();
                            toastr.success(
                                '<?php echo addslashes($lang->get('done')); ?>',
                                '', {
                                    timeOut: 1000
                                }
                            );
                        }
                    }
                );

                });

                return;

            }
        } else if (action === 'onthefly-restore') {
            // CLI-only: prepare an authorization token and show the shell command to execute.
            const opId = $('#onthefly-restore-file').data('operation-id');
            const serverScope = $('#onthefly-restore-server-scope').val() || '';
            const serverFile = tpGetOnTheFlyServerFile();

            if ((opId === undefined || opId === null || opId === '') && (serverFile === undefined || serverFile === null || serverFile === '')) {
                toastr.error("<?php echo addslashes($lang->get('bck_onthefly_select_backup_first')); ?>");
                return false;
            }

            var encryptionKey = simplePurifier($('#onthefly-restore-key').val());
            var overrideKey = '';

            // If a scheduled backup is selected from this panel, the instance key will be used server-side.
            // An override key may be provided (migration case).
            if (serverScope === 'scheduled') {
                encryptionKey = '';
                overrideKey = ($('#scheduled-restore-override-key').val() || '').toString();
            }

            if (encryptionKey === '' && serverScope !== 'scheduled') {
                toastr.error("<?php echo addslashes($lang->get('bck_restore_key_required')); ?>");
                return false;
            }

            // Compatibility preflight (schema-level) before any token generation
            var pfPayload = {};
            if (opId !== undefined && opId !== null && opId !== '') {
                pfPayload.operation_id = opId;
            } else {
                pfPayload.serverScope = serverScope;
                pfPayload.serverFile = serverFile;
            }

            tpPreflightRestoreCompatibility(pfPayload, function() {
                tpExclusiveUsers.ensure(function() {
                    tpProgressToast.show('<?php echo addslashes($lang->get('bck_restore_prepare_in_progress')); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

                    var prepPayload = {};
                    if (opId !== undefined && opId !== null && opId !== '') {
                        prepPayload.operation_id = opId;
                    } else {
                        prepPayload.serverScope = serverScope;
                        prepPayload.serverFile = serverFile;
                    }
                    prepPayload.encryptionKey = encryptionKey;
                    prepPayload.overrideKey = overrideKey;

                    tpPrepareRestoreCli(
                        prepPayload,
                        function(r) {
                            tpProgressToast.hide();
	                            tpShowRestoreCliModal(r.command, r.command_no_cd, r.token_expires_at, r.warnings || [], r.command_variants || []);
                        },
                        function(err) {
                            tpProgressToast.hide();
                            var errCode = err.error_code || '';
                            var errMsg = err.message || "<?php echo addslashes($lang->get('error_unknown')); ?>";

                            if (errCode === 'DECRYPT_FAILED') {
                                tpToast('error', "<?php echo addslashes($lang->get('bck_restore_key_invalid')); ?>");
                                tpShowRestoreKeyModal(
                                    errMsg,
                                    function(newKey) {
                                        newKey = (newKey || '').toString();
                                        if (serverScope === 'scheduled') {
                                            prepPayload.overrideKey = newKey;
                                            $('#scheduled-restore-override-key').val(newKey);
                                        } else {
                                            prepPayload.encryptionKey = newKey;
                                            $('#onthefly-restore-key').val(newKey);
                                        }

                                        tpPrepareRestoreCli(
                                            prepPayload,
                                            function(r2) {
	                                                tpShowRestoreCliModal(r2.command, r2.command_no_cd, r2.token_expires_at, r2.warnings || [], r2.command_variants || []);
                                            },
                                            function(err2) {
                                                tpToast('error', err2.message || errMsg);
                                            }
                                        );
                                    },
                                    (serverScope === 'scheduled') ? overrideKey : encryptionKey
                                );
                                return;
                            }

                            tpToast('error', errMsg);
                        }
                    );
                });
            });

            return;
        }
    });

    // Scheduled restore (from scheduled backups list)
    // - by default uses the instance key
    // - if decryption fails (migration case), user can provide an override key
    function tpScheduledRestoreStart(serverFile, overrideKey) {
        serverFile = (serverFile || '').toString();
        overrideKey = (overrideKey || '').toString();
        if (serverFile === '') {
            tpToast('error', "<?php echo addslashes($lang->get('bck_onthefly_select_backup_first')); ?>");
            return;
        }

        // Pre-check: don't lock UI until we are sure the backup can be decrypted.
        tpProgressToast.show('<?php echo addslashes($lang->get('bck_restore_checking_key')); ?> <i class="fas fa-circle-notch fa-spin"></i>');

        $('#scheduled-restore-progress').addClass('hidden');
        $('#scheduled-restore-finished').addClass('hidden').empty();
        $('#scheduled-restore-progress-text').text('0');

        var lockStarted = false;

        function ensureLockStarted(pct) {
            if (lockStarted !== true) {
                lockStarted = true;
                try { tpProgressToast.hide(); } catch (e) {}
                try { $('#scheduled-restore-progress').removeClass('hidden'); } catch (e) {}
                tpRestoreLock.start();
            }
            tpRestoreLock.update(pct);
        }

        function updateScheduledProgress(offset, totalSize) {
            var percentage = 0;
            if (totalSize && totalSize > 0) {
                percentage = Math.round((offset / totalSize) * 100);
                if (percentage > 100) percentage = 100;
            }
            $('#scheduled-restore-progress-text').text(percentage);
            ensureLockStarted(percentage);
        }

        var restoreToken = '';

        function doRestore(offset, clearFilename, totalSize) {
            var data = {
                // Scheduled backups are encrypted using the instance key (server-side)
                'encryptionKey': '',
                'overrideKey': overrideKey,
                'backupFile': 0,
                'serverScope': 'scheduled',
                'serverFile': serverFile,
                'clearFilename': clearFilename,
                'offset': (parseInt(offset, 10) || 0),
                'totalSize': (parseInt(totalSize, 10) || 0)
            };

            $.post(
                "sources/backups.queries.php",
                {
                    type: "onthefly_restore",
                    restore_token: restoreToken,
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                    key: "<?php echo $session->get('key'); ?>"
                },
                function (response) {
                    // If the restore invalidates the session, PHP can return an HTML error page.
                    // Don't try to decrypt (avoid JSON errors) and stop the process.
                    if (tpResponseLooksLikeHtml(response)) {
                        try { tpProgressToast.hide(); } catch (e) {}
                        tpToast('error', "<?php echo addslashes($lang->get('server_answer_error')); ?>");
                        $('#scheduled-restore-progress').addClass('hidden');
                        $('#scheduled-restore-progress-text').text('0');
                        if (lockStarted === true) tpRestoreLock.cancel();
                        lockStarted = false;
                        return;
                    }

                    var r = tpSafePrepareDecode(response);
                    if (r === null) { return; }
if (r && r.restore_token) { restoreToken = r.restore_token; }

                    if (r && r.error === false) {
                        if (r.finished === false) {
                            updateScheduledProgress(r.newOffset, r.totalSize);
                            doRestore(r.newOffset, r.clearFilename, r.totalSize);
                        } else {
                            $('#scheduled-restore-finished')
                                .removeClass('hidden')
                                .html('<div class="alert alert-success alert-dismissible ml-2">' +
                                    '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>' +
                                    '<h5><i class="icon fa fa-check mr-2"></i><?php echo addslashes($lang->get('done')); ?></h5>' +
                                    '<?php echo addslashes($lang->get('restore_done_now_logout')); ?>' +
                                    '</div>');

                            $('#scheduled-restore-progress').addClass('hidden');
                            $('#scheduled-restore-progress-text').text('0');

                            tpToast('success', '<?php echo addslashes($lang->get('done')); ?>', '', { timeOut: 1000 });

                            // At the end of a restore, it's safer to force logout (DB changed / session may be invalid)
                            ensureLockStarted(100);
                            tpRestoreLock.finish();
                        }
                    } else {
                        try { tpProgressToast.hide(); } catch (e) {}
                        var errCode = (r && r.error_code) ? r.error_code : '';
                        var errMsg = (r && (r.message || r.error)) ? (r.message || r.error) : "<?php echo addslashes($lang->get('error')); ?>";

                        // Unlock UI (if lock had started)
                        if (lockStarted === true) {
                            tpRestoreLock.cancel();
                            lockStarted = false;
                        }

                        $('#scheduled-restore-progress').addClass('hidden');
                        $('#scheduled-restore-progress-text').text('0');

                        // If the instance key does not match (migration), allow user to provide a key and retry.
                        if (errCode === 'DECRYPT_FAILED') {
                            tpToast('error', "<?php echo addslashes($lang->get('bck_restore_key_invalid')); ?>");
                            tpShowRestoreKeyModal(
                                errMsg,
                                function(newKey) {
                                    $('#scheduled-restore-override-key').val(newKey);
                                    tpScheduledRestoreStart(serverFile, newKey);
                                },
                                overrideKey
                            );
                            return;
                        }

                        tpToast('error', errMsg, "", { timeOut: 7000 });
                    }
                }
            );
        }
        doRestore(0, '', 0);
    }

    // Optional manual key override (migration case)
    $(document).on('click', '#scheduled-restore-change-key', function(e) {
        e.preventDefault();
        var current = ($('#scheduled-restore-override-key').val() || '').toString();
        tpShowRestoreKeyModal('', function(newKey) {
            $('#scheduled-restore-override-key').val(newKey);
            tpToast('success', "<?php echo addslashes($lang->get('done')); ?>", '', { timeOut: 800 });
        }, current);
    });

    $(document).on('click', '#scheduled-restore-start', function(e) {
        e.preventDefault();

        var serverFile = $('#scheduled-restore-server-file').val() || '';
        if (serverFile === '') {
            tpToast('error', "<?php echo addslashes($lang->get('bck_onthefly_select_backup_first')); ?>");
            return;
        }

        var selfBtn = this;
        if (!$(selfBtn).data('tp-confirmed')) {
            var msg = "<?php echo addslashes($lang->get('bck_confirm_restore_from_backup')); ?>";
            msg = msg.replace('%s', '<code>' + $('<div/>').text(serverFile).html() + '</code>');
            tpShowConfirmRestore(msg, function() {
                $(selfBtn).data('tp-confirmed', true);
                $(selfBtn).trigger('click');
                $(selfBtn).data('tp-confirmed', false);
            });
            return;
        }

        var overrideKey = $('#scheduled-restore-override-key').val() || '';
        // Compatibility preflight (schema-level) before any lock/maintenance
        tpPreflightRestoreCompatibility({ serverScope: 'scheduled', serverFile: serverFile }, function() {
            tpExclusiveUsers.ensure(function() {
            // Prepare CLI restore command (server-side)
                tpProgressToast.show('<?php echo addslashes($lang->get('bck_restore_prepare_in_progress')); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

                var prepPayload = {
                    serverScope: 'scheduled',
                    serverFile: serverFile,
                    encryptionKey: '',
                    overrideKey: overrideKey
                };

                tpPrepareRestoreCli(
                    prepPayload,
                    function(r) {
                        tpProgressToast.hide();
	                        tpShowRestoreCliModal(r.command, r.command_no_cd, r.token_expires_at, r.warnings || [], r.command_variants || []);
                    },
                    function(err) {
                        tpProgressToast.hide();
                        var errCode = err.error_code || '';
                        var errMsg = err.message || "<?php echo addslashes($lang->get('error_unknown')); ?>";

                        if (errCode === 'DECRYPT_FAILED') {
                            tpToast('error', "<?php echo addslashes($lang->get('bck_restore_key_invalid')); ?>");
                            tpShowRestoreKeyModal(
                                errMsg,
                                function(newKey) {
                                    $('#scheduled-restore-override-key').val(newKey);
                                    prepPayload.overrideKey = newKey;
                                    tpPrepareRestoreCli(
                                        prepPayload,
                                        function(r2) {
	                                                tpShowRestoreCliModal(r2.command, r2.command_no_cd, r2.token_expires_at, r2.warnings || [], r2.command_variants || []);
                                        },
                                        function(err2) {
                                            tpToast('error', err2.message || errMsg);
                                        }
                                    );
                                },
                                overrideKey
                            );
                            return;
                        }

                        tpToast('error', errMsg);
                    }
                );
        });
        });
    });



// PREPARE UPLOADER with plupload
<?php
$maxFileSize = (strrpos($SETTINGS['upload_maxfilesize'], 'mb') === false)
    ? $SETTINGS['upload_maxfilesize'] . 'mb'
    : $SETTINGS['upload_maxfilesize'];

$maxFileSizeDisplay = strtoupper((string) $maxFileSize);
$maxFileSizeDisplay = preg_replace('/\s*(MB|GB)$/', ' $1', $maxFileSizeDisplay);
?>

    let toastrElement;
    var restoreOperationId = '',
        uploader_restoreDB = new plupload.Uploader({
            runtimes: "gears,html5,flash,silverlight,browserplus",
            browse_button: "onthefly-restore-file-select",
            container: "onthefly-restore-file",
            max_file_size: "<?php echo $maxFileSize; ?>",
            chunk_size: "2mb",  // adapted to standard PHP configuration
            unique_names: true,
            dragdrop: true,
            multiple_queues: false,
            multi_selection: false,
            max_file_count: 1,
            url: "<?php echo $SETTINGS['cpassman_url']; ?>/sources/upload.files.php",
            flash_swf_url: "<?php echo $SETTINGS['cpassman_url']; ?>/includes/libraries/plupload/js/plupload.flash.swf",
            silverlight_xap_url: "<?php echo $SETTINGS['cpassman_url']; ?>/includes/libraries/plupload/js/plupload.silverlight.xap",
            filters: [{
                title: "SQL files",
                extensions: "sql"
            }],
            init: {
                FilesAdded: function(up, files) {
                    // generate and save token
                    $.post(
                        "sources/main.queries.php", {
                            type: "save_token",
                            type_category: 'action_system',
                            size: 25,
                            capital: true,
                            numeric: true,
                            ambiguous: true,
                            reason: "restore_db",
                            duration: 10,
                            key: '<?php echo $session->get('key'); ?>'
                        },
                        function(data) {
                            console.log(data);
                            store.update(
                                'teampassUser',
                                function(teampassUser) {
                                    teampassUser.uploadToken = data[0].token;
                                }
                            );
// Before starting upload, block if original filename already exists in <files>
var fn = (files && files.length && files[0] && files[0].name) ? (files[0].name + '') : '';
$.post(
    "sources/backups.queries.php",
    {
        type: "onthefly_check_upload_filename",
        data: prepareExchangedData(JSON.stringify({ filename: fn }), "encode", tpSessionKey),
        key: tpSessionKey
    },
    function(resp) {
        var r = tpSafePrepareDecode(resp);
        if (r === null) { return; }

        if (r.error === true) {
            toastr.remove();
            toastr.error(r.message || "<?php echo addslashes($lang->get('error')); ?>", '', {timeOut: 6000, progressBar: true});
            try { up.removeFile(files[0]); } catch (e) {}
            return;
        }

        if (r.exists === true) {
            toastr.remove();
            toastr.error(
                tpTpl(tpBckUploadFileAlreadyExistsTpl, {FILENAME: fn}),
                "<?php echo addslashes($lang->get('error')); ?>",
                {timeOut: 8000, progressBar: true}
            );
            try { up.removeFile(files[0]); } catch (e) {}
            return;
        }

        up.start();
    }
);

                        },
                        "json"
                    );
                },
                BeforeUpload: function(up, file) {
                    // Show cog
                    toastr.remove();
                    toastrElement = toastr.info('<?php echo addslashes($lang->get('loading_item')); ?> ... <span id="plupload-progress" class="mr-2 ml-2 strong">0%</span><i class="fas fa-circle-notch fa-spin fa-2x"></i>');

                    up.setOption('multipart_params', {
                        PHPSESSID: '<?php echo $session->get('user-id'); ?>',
                        type_upload: 'restore_db',
                        File: file.name,
                        user_token: store.get('teampassUser').uploadToken
                    });
                },
                UploadProgress: function(up, file) {
                    // Update only the percentage inside the Toastr message
                    $('#plupload-progress').text(file.percent + '%');
                },
                UploadComplete: function(up, files) {
                    store.update(
                        'teampassUser',
                        function(teampassUser) {
                            teampassUser.uploadFileObject = restoreOperationId;
                        }
                    );
                    
                    $('#onthefly-restore-file-text').text(up.files[0].name);

                    // Inform user
                    toastr.remove();
                    toastr.success(
                        '<?php echo addslashes($lang->get('done')); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                },
                Error: function(up, args) {
                    console.log("ERROR arguments:");
                    console.log(args);
                }
            }
        });

    // Uploader options
    uploader_restoreDB.bind('FileUploaded', function(upldr, file, object) {
        var myData = tpSafePrepareDecode(object.response);
        if (myData === null) { return; }
        $('#onthefly-restore-file').data('operation-id', myData.operation_id);
        $('#onthefly-restore-serverfile').val('');
        $('#onthefly-restore-server-file').val('');
    });

    uploader_restoreDB.bind("Error", function(up, err) {
        // Improve UX by providing a clearer, localized message for common Plupload errors
        var rawMsg = (err && err.message) ? String(err.message) : '';
        var code = (err && typeof err.code !== 'undefined') ? err.code : null;

        var msg = rawMsg;

        // Plupload codes: FILE_SIZE_ERROR = -600, FILE_EXTENSION_ERROR = -601
        var isFileSizeError = (typeof plupload !== 'undefined' && code === plupload.FILE_SIZE_ERROR) || code === -600;
        var isFileExtError = (typeof plupload !== 'undefined' && code === plupload.FILE_EXTENSION_ERROR) || code === -601;

        if (isFileSizeError) {
            msg = "<?php echo addslashes(sprintf($lang->get('bck_upload_error_file_too_large'), $maxFileSizeDisplay)); ?>";
        } else if (isFileExtError) {
            msg = "<?php echo addslashes($lang->get('bck_upload_error_file_extension')); ?>";
        }

        // Inline feedback (restore block)
        $("#onthefly-restore-progress")
            .removeClass('hidden')
            .html('<div class="alert alert-danger alert-dismissible ml-2">' +
                '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>' +
                '<h5><i class="icon fas fa-ban mr-2"></i><?php echo addslashes($lang->get('error')); ?></h5>' +
                simplePurifier(msg) +
                '</div>');

        // Bottom-right toaster (consistent with the rest of the page)
        toastr.remove();
        toastr.error(
            msg,
            "<?php echo addslashes($lang->get('error')); ?>",
            {timeOut: 8000, progressBar: true}
        );

        up.refresh(); // Reposition Flash/Silverlight
    });

    uploader_restoreDB.init();

    // Display the upload limit (from TeamPass setting "upload_maxfilesize") near the file picker
    if ($('#onthefly-restore-upload-hint').length === 0) {
        $('#onthefly-restore-file').after(
            '<small id="onthefly-restore-upload-hint" class="form-text text-muted mt-1 ml-1"><?php echo addslashes(sprintf($lang->get('bck_upload_max_file_size'), $maxFileSizeDisplay)); ?></small>'
        );
    }
//]]>
/**
 * Scheduled backups UI
 */
var tpScheduled = {
  tz: 'UTC',

  showAlert: function(type, msg) {
    var $a = $('#scheduled-alert');
    $a.removeClass('d-none alert-success alert-danger alert-warning')
      .addClass('alert-' + type)
      .text(msg);
  },

  hideAlert: function() {
    $('#scheduled-alert').addClass('d-none').text('');
  },

  fmtBytes: function(b) {
    b = parseInt(b || 0, 10);
    if (b < 1024) return b + ' B';
    var u = ['KB','MB','GB','TB'];
    var i = -1;
    do { b = b / 1024; i++; } while (b >= 1024 && i < u.length - 1);
    return b.toFixed(1) + ' ' + u[i];
  },

  fmtTs: function(ts) {
    ts = parseInt(ts || 0, 10);
    if (!ts) return '-';
    try {
      return new Date(ts * 1000).toLocaleString(undefined, { timeZone: tpScheduled.tz });
    } catch (e) {
      return new Date(ts * 1000).toLocaleString();
    }
  },

  toggleFreqUI: function() {
    var f = $('#scheduled-frequency').val();
    $('#scheduled-weekly-wrap').toggleClass('d-none', f !== 'weekly');
    $('#scheduled-monthly-wrap').toggleClass('d-none', f !== 'monthly');
  },

  // Helpers for Teampass "toggle" widgets (div + hidden input)
  isTruthy: function(v) {
    return (v === 1 || v === '1' || v === true || v === 'true' || v === 'on' || v === 'yes');
  },

  norm01: function(v) {
    return tpScheduled.isTruthy(v) ? '1' : '0';
  },

  applyToggleState: function(toggleId, on) {
    var $t = $('#' + toggleId);
    var $i = $('#' + toggleId + '_input');
    var val = on ? '1' : '0';

    if ($i.length) $i.val(val);
    try { $t.attr('data-toggle-on', on ? 'true' : 'false'); } catch (e) {}

    // Try common toggle plugins used in Teampass (keep it resilient)
    try {
      if (typeof $t.toggles === 'function') {
        try { $t.toggles(on); } catch (e1) { $t.toggles({on: on}); }
      }
    } catch (e) {}

    try { if (typeof $t.bootstrapSwitch === 'function') { $t.bootstrapSwitch('state', on, true); } } catch (e) {}
    try { if (typeof $t.bootstrapToggle === 'function') { $t.bootstrapToggle(on ? 'on' : 'off'); } } catch (e) {}

    // Fallback: update common classes
    try { $t.toggleClass('on', on).toggleClass('off', !on).toggleClass('active', on); } catch (e) {}
  },

  bindToggleFix: function(toggleId, afterFn) {
    var $t = $('#' + toggleId);
    var $i = $('#' + toggleId + '_input');
    if (!$t.length || !$i.length) return;

    // Capture previous state before click (mousedown happens before click)
    $t.on('mousedown', function() {
      $t.data('tp_prev_state', tpScheduled.norm01($i.val()));
    });

    $t.on('click', function() {
      setTimeout(function() {
        var prev = $t.data('tp_prev_state');
        var now = tpScheduled.norm01($i.val());

        // Some toggle implementations update the UI but not the hidden input.
        // If value didn't change, invert it.
        if (prev === now) {
          now = (prev === '1') ? '0' : '1';
          $i.val(now);
        } else {
          $i.val(now);
        }

        if (typeof afterFn === 'function') afterFn(now);
      }, 0);
    });
  },

  toggleEmailUI: function() {
    var enabled = tpScheduled.isTruthy($('#scheduled-email-report-enabled_input').val());
    $('#scheduled-email-report-only-failures-wrap').toggleClass('d-none', !enabled);

    // If email reports are disabled, force only-failures off for consistency
    if (!enabled) {
      tpScheduled.applyToggleState('scheduled-email-report-only-failures', false);
    }
  },


  ajax: function(type, data, cb) {
    $.post(
      "sources/backups.queries.php",
      {
        type: type,
        data: prepareExchangedData(JSON.stringify(data || {}), "encode", "<?php echo $session->get('key'); ?>"),
        key: "<?php echo $session->get('key'); ?>"
      },
      function(response) {
        var resp = (response || '').toString();

        // Avoid throwing "Unexpected end of JSON input" when server returned nothing.
        if (resp.trim() === '') {
          tpScheduled.showAlert('danger', '<?php echo addslashes($lang->get('server_answer_error')); ?>');
          return;
        }

        var r = tpSafePrepareDecode(resp);
        if (r === null) { return; }

        // Some code paths return a JSON string, others an already-parsed object
        if (typeof r === 'string') {
          try { r = $.parseJSON(r); } catch (e) {}
        }
        if (typeof cb === 'function') cb(r);
      }
    );
  },

  loadSettings: function(cb) {
    tpScheduled.hideAlert();
    tpScheduled.ajax('scheduled_get_settings', {}, function(r) {
      if (!r || r.error) {
        tpScheduled.showAlert('danger', (r && r.message) ? r.message : 'Unable to load settings');
        if (typeof cb === 'function') cb(null);
        return;
      }
      var s = r.settings || {};
      tpScheduled.tz = s.timezone || 'UTC';

      var enabledVal = (parseInt(s.enabled || 0, 10) === 1) ? '1' : '0';
      tpScheduled.applyToggleState('backup-scheduled-enabled', enabledVal === '1');
      $('#scheduled-frequency').val(s.frequency || 'daily');
      $('#scheduled-time').val(s.time || '02:00');
      $('#scheduled-dow').val(String(s.dow || 1));
      $('#scheduled-dom').val(String(s.dom || 1));
      $('#scheduled-retention').val(String(s.retention_days || 30));
      $('#scheduled-output-dir').val(s.output_dir || '');

      tpScheduled.toggleFreqUI();

      var emailEnabledVal = (parseInt(s.email_report_enabled || 0, 10) === 1) ? '1' : '0';
      tpScheduled.applyToggleState('scheduled-email-report-enabled', emailEnabledVal === '1');

      var onlyFailuresVal = (parseInt(s.email_report_only_failures || 0, 10) === 1) ? '1' : '0';
      tpScheduled.applyToggleState('scheduled-email-report-only-failures', onlyFailuresVal === '1');

      tpScheduled.toggleEmailUI();


      $('#scheduled-next-run').text(tpScheduled.fmtTs(s.next_run_at));
      $('#scheduled-last-run').text(tpScheduled.fmtTs(s.last_run_at));
      $('#scheduled-last-status').text(s.last_status || '-');
      $('#scheduled-last-message').text(s.last_message || '-');
      $('#scheduled-last-purge').text(tpScheduled.fmtTs(s.last_purge_at));
      $('#scheduled-last-purge-deleted').text(String(s.last_purge_deleted || 0));
    if (typeof cb === 'function') cb(s);
    });
  },

  saveSettings: function() {
    tpScheduled.hideAlert();
    var payload = {
      enabled: tpScheduled.norm01($('#backup-scheduled-enabled_input').val()),
      frequency: $('#scheduled-frequency').val(),
      time: $('#scheduled-time').val(),
      dow: parseInt($('#scheduled-dow').val(), 10),
      dom: parseInt($('#scheduled-dom').val(), 10),
      retention_days: parseInt($('#scheduled-retention').val(), 10),
      output_dir: $('#scheduled-output-dir').val(),
      email_report_enabled: tpScheduled.norm01($('#scheduled-email-report-enabled_input').val()),
      email_report_only_failures: tpScheduled.norm01($('#scheduled-email-report-only-failures_input').val())
    };

    if (payload.email_report_enabled !== '1') {
      payload.email_report_only_failures = '0';
      tpScheduled.applyToggleState('scheduled-email-report-only-failures', false);
    }

    tpScheduled.ajax('scheduled_save_settings', payload, function(r) {
      if (!r || r.error) {
        tpScheduled.showAlert('danger', (r && r.message) ? r.message : 'Save failed');
        return;
      }
      tpScheduled.showAlert('success', 'Saved. Next run will be recomputed.');
      if (typeof tpToast === 'function') {
        tpToast('success', '<?php echo addslashes($lang->get('saved')); ?>', '', { timeOut: 1200 });
      }
      tpScheduled.loadSettings();
    });
  },

  loadFiles: function() {
    tpScheduled.ajax('scheduled_list_backups', {}, function(r) {
      if (!r || r.error) return;

      var $tb = $('#scheduled-backups-tbody');
      $tb.empty();

      var selected = ($('#scheduled-restore-server-file').val() || '').toString();
      var foundSelected = false;

      (r.files || []).forEach(function(f) {
        var tr = $('<tr/>');

        var fn = (f.name || '');
        tr.attr('data-file', fn).css('cursor','pointer');

        var dtTxt = tpScheduled.fmtTs(f.mtime);

        // Date cell with a reliable radio selector (works even when only 1 backup is present)
        var $dateTd = $('<td/>').addClass('text-nowrap').attr('title', fn);

        var $radio = $('<input/>')
          .attr('type', 'radio')
          .attr('name', 'scheduled_backup_select')
          .addClass('scheduled-backup-radio mr-2')
          .attr('data-file', fn);

        if (selected !== '' && selected === fn) {
          $radio.prop('checked', true);
          foundSelected = true;
        }

        $dateTd.append($radio).append($('<span/>').text(dtTxt));
        tr.append($dateTd);

        tr.append($('<td/>').addClass('text-nowrap').text(tpScheduled.fmtBytes(f.size_bytes)));
        tr.append($('<td/>').addClass('text-nowrap').text(tpFmtTpVersion(f.tp_files_version || '')));

        var dl = (f.download || '');
        var $cell = $('<td/>').addClass('text-right text-nowrap');

        var $grp = $('<div/>').addClass('btn-group btn-group-sm').attr('role', 'group');

        if (dl !== '') {
          var $dl = $('<a/>')
            .addClass('btn btn-outline-primary')
            .attr('href', dl)
            .attr('download', f.name || '')
            .attr('title', '<?php echo addslashes($lang->get('bck_onthefly_download')); ?>')
            .attr('aria-label', '<?php echo addslashes($lang->get('bck_onthefly_download')); ?>')
            .html('<i class="fas fa-download"></i>');
          $grp.append($dl);
        }

        var $del = $('<button/>')
            .attr('type', 'button')
            .addClass('btn btn-outline-danger')
            .attr('title', '<?php echo addslashes($lang->get('bck_scheduled_delete')); ?>')
            .attr('aria-label', '<?php echo addslashes($lang->get('bck_scheduled_delete')); ?>')
            .html('<i class="fas fa-trash"></i>')
            .on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const fileName = f.name || '';
                // Appel de la fonction générique
                launchConfirmDialog(
                    "<?php echo addslashes($lang->get('del_button')); ?>",
                    "<?php echo addslashes($lang->get('bck_onthefly_confirm_delete')); ?><br><br><b>" + fileName + "</b>",
                    function() {
                        // --- CALLBACK DE CONFIRMATION ---
                        tpScheduled.ajax('scheduled_delete_backup', { file: fileName }, function(rr) {
                            if (rr && rr.error) {
                                tpScheduled.showAlert('danger', rr.message || 'Delete failed');
                                return;
                            }
                            
                            // Nettoyage de l'interface si le fichier sélectionné est supprimé
                            if (($('#scheduled-restore-server-file').val() || '').toString() === fileName) {
                                $('#scheduled-restore-server-file').val('');
                                $('#scheduled-restore-selected').hide();
                                $('#scheduled-restore-selected-name').text('');
                                $('#scheduled-restore-start').prop('disabled', true);
                            }

                            // Rechargement des listes
                            tpScheduled.loadFiles();
                            tpScheduled.loadSettings();
                        });
                    },
                );
            });

        $grp.append($del);
        $cell.append($grp);
        tr.append($cell);
        $tb.append(tr);
      });

      // If selection no longer exists, clear it
      if (selected !== '' && foundSelected === false) {
        $('#scheduled-restore-server-file').val('');
        $('#scheduled-restore-selected').hide();
        $('#scheduled-restore-selected-name').text('');
        $('#scheduled-restore-start').prop('disabled', true);
      }
    });
  },

  refreshAll: function() {
    tpScheduled.loadSettings(function(s) {
      if (s === null) return;

      tpScheduled.loadFiles();
      loadDiskUsage();

      if (typeof tpToast === 'function') {
        tpToast('success', '<?php echo addslashes($lang->get('refreshed')); ?>', '', { timeOut: 1200 });
      }
    });
  },


  selectForRestore: function(fileName) {
    if (!fileName) return;
    // Reset any previous override key when selecting a new backup
    try { $('#scheduled-restore-override-key').val(''); } catch (e) {}
    $('#scheduled-restore-server-file').val(fileName);
    $('#scheduled-restore-selected-name').text(fileName);
    $('#scheduled-restore-selected').show();
    $('#scheduled-restore-start').prop('disabled', false);
    tpToast('info', "<?php echo addslashes($lang->get('bck_onthefly_selected_backup')); ?>" + " : " + fileName);
  },

  runNow: function() {
    tpScheduled.hideAlert();

    // Scheduled backups run in background tasks. Keep a sticky toast while it runs.
    var startedAt = Math.floor(Date.now() / 1000);
    tpProgressToast.show('<?php echo addslashes($lang->get('in_progress')); ?> ... <i class="fas fa-circle-notch fa-spin"></i>');

    tpScheduled.ajax('scheduled_run_now', {}, function(r) {
      if (!r || r.error) {
        tpProgressToast.hide();
        tpScheduled.showAlert('danger', (r && r.message) ? r.message : 'Unable to enqueue task');
        return;
      }
      tpScheduled.showAlert('success', 'Task enqueued.');
      tpScheduled.loadSettings();
      tpScheduled.startRunNowPoll(startedAt);
    });
  },

  _runNowTimer: null,
  _runNowTries: 0,

  startRunNowPoll: function(startedAt) {
    tpScheduled.stopRunNowPoll();
    tpScheduled._runNowTries = 0;

    var maxTries = 120;      // 10 minutes @ 5s
    var intervalMs = 5000;

    function checkOnce() {
      tpScheduled.loadSettings(function(s) {
        if (!s) return;

        var st = (s.last_status || '').toString().toLowerCase();
        var completedAt = parseInt(s.last_completed_at || 0, 10) || 0;

        var isRunning = (st === 'queued' || st === 'running' || st === 'new' || st === 'in_progress');
        var isClearlyDone = (!isRunning && st !== '' && st !== '-');
        var isCompletedAtDone = (completedAt && completedAt >= (parseInt(startedAt || 0, 10) || 0) && !isRunning);

        if (isClearlyDone || isCompletedAtDone) {
          tpScheduled.stopRunNowPoll();
          tpProgressToast.hide();

          if (st === 'error' || st === 'failed' || st === 'ko') {
            tpToast('error', (s.last_message || 'Backup failed'));
          } else {
            tpToast('success', '<?php echo addslashes($lang->get('done')); ?>', '', { timeOut: 2000 });
          }

          tpScheduled.loadFiles();
        }
      });
    }

    // Immediate check then poll
    checkOnce();
    tpScheduled._runNowTimer = setInterval(function() {
      tpScheduled._runNowTries++;
      checkOnce();

      if (tpScheduled._runNowTries >= maxTries) {
        tpScheduled.stopRunNowPoll();
        tpProgressToast.hide();
        tpToast('info', "<?php echo addslashes($lang->get('in_progress')); ?>");
      }
    }, intervalMs);
  },

  stopRunNowPoll: function() {
    try { if (tpScheduled._runNowTimer) clearInterval(tpScheduled._runNowTimer); } catch (e) {}
    tpScheduled._runNowTimer = null;
    tpScheduled._runNowTries = 0;
  },

  init: function() {
    // load when tab is shown
    $('a[href="#scheduled"]').on('shown.bs.tab', function() {
        tpScheduled.refreshAll();
    });

    $('#scheduled-frequency').on('change', tpScheduled.toggleFreqUI);

    // Ensure toggle hidden inputs stay consistent (some toggle libs don't update them reliably)
    tpScheduled.bindToggleFix('backup-scheduled-enabled');
    tpScheduled.bindToggleFix('scheduled-email-report-enabled', function() {
        tpScheduled.toggleEmailUI();
    });
    tpScheduled.bindToggleFix('scheduled-email-report-only-failures');
    $('#scheduled-save-btn').on('click', function(e){ e.preventDefault(); e.stopPropagation(); tpScheduled.saveSettings(); });
    $('#scheduled-run-btn').on('click', function(e){
        e.preventDefault();
        e.stopPropagation();
        if (typeof tpExclusiveUsers !== 'undefined' && tpExclusiveUsers && typeof tpExclusiveUsers.ensure === 'function') {
            tpExclusiveUsers.ensure(function() {
                tpScheduled.runNow();
            });
        } else {
            tpScheduled.runNow();
        }
    });
    $('#scheduled-refresh-btn').on('click', function(e){ e.preventDefault(); e.stopPropagation(); tpScheduled.refreshAll(); });
    // Select a backup for restore (radio selector + row click)
    $(document).on('change', '.scheduled-backup-radio', function(e) {
      e.preventDefault();
      e.stopPropagation();
      var fileName = $(this).attr('data-file') || '';
      if (fileName) tpScheduled.selectForRestore(fileName);
    });

    // Allow selecting by clicking anywhere on the row (excluding buttons/links/inputs)
    $(document).on('click', '#scheduled-backups-tbody tr', function(e) {
      if ($(e.target).closest('button,a,input').length) return;
      var $r = $(this).find('.scheduled-backup-radio');
      if ($r.length) {
        $r.prop('checked', true).trigger('change');
      }
    });

// If scheduled tab is already active (rare), load immediately
    if ($('#scheduled').hasClass('active') || $('#scheduled').hasClass('show')) {
      tpScheduled.refreshAll();
    }
  }
};

/**
 * Strict mode: ensure no other users are connected before starting backup/restore
 * (reuse the same design/components as Utilities > Database > Logged-in users)
 */
var tpExclusiveUsers = {
    currentUserId: <?php echo (int) $session->get('user-id'); ?>,
    oTable: null,
    pendingCallback: null,

    initTable: function () {
        var self = this;

        if (self.oTable !== null) {
            return;
        }

        self.oTable = $('#tp-connected-users-table').DataTable({
            'retrieve': true,
            'paging': true,
            'sPaginationType': 'listbox',
            'searching': true,
            'order': [[1, 'asc']],
            'info': true,
            'processing': false,
            'serverSide': true,
            'responsive': true,
            'stateSave': true,
            'autoWidth': true,
            'ajax': {
                url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/logs.datatables.php?action=users_logged_in'
            },
            'language': {
                'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $session->get('user-language'); ?>.txt'
            },
            'preDrawCallback': function () {
                toastr.remove();
                toastr.info('<?php echo addslashes($lang->get('loading_data')); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');
            },
            'drawCallback': function () {
                toastr.remove();
                toastr.success('<?php echo addslashes($lang->get('done')); ?>', '', { timeOut: 800 });
                self.refreshContinueState();
            },
            'columnDefs': [
                {
                    'width': '80px',
                    'targets': 0,
                    'render': function (data, type, row, meta) {
                        var uid = $(data).data('id');
                        if (uid === self.currentUserId) {
                            return '';
                        }
                        // Disconnect Icon (same as Utilities > Database)
                        return '<i class="far fa-trash-alt text-danger tp-exclusive-kick-user" data-id="' + uid + '"></i>';
                    }
                },
                {
                    /// 0 = action, 1 = user, 2 = role, 3 = connected since, 4 = API
                    'targets': 4,
                    'orderable': false,
                    'searchable': false,
                    'className': 'text-center',
                    'width': '48px',
                    'render': function (data, type, row, meta) {
                        if (type !== 'display') {
                            return data;
                        }
                        const isApi = (data === 1 || data === '1' || data === true || data === 'true');
                        return isApi ? '<i class="fas fa-circle text-success" title="API"></i>' : '';
                    }
                }
            ],
            'rowCallback': function (row, data) {
                // Hide current admin from the list (excluded from strict mode)
                var uid = $(data[0]).data('id');
                if (uid === self.currentUserId) {
                    $(row).hide();
                }
            }
        });
    },

    check: function (cb) {
        $.post(
            "sources/backups.queries.php",
            {
                type: "check_connected_users",
	            key: tpSessionKey
            },
            function (data) {
	            data = tpSafePrepareDecode(data);
	            if (data === null) {
	                return;
	            }
                cb(data || { error: true });
            }
        );
    },

    refreshContinueState: function () {
        this.check(function (r) {
            var count = parseInt((r && r.connected_count) ? r.connected_count : 0, 10);
            $('#tp-connected-users-continue').prop('disabled', count > 0);
        });
    },

    openModal: function (cb) {
        this.pendingCallback = cb;
        this.initTable();
        this.oTable.ajax.reload();
        $('#tp-connected-users-modal').modal('show');
        this.refreshContinueState();
    },

    ensure: function (cb) {
        var self = this;

        self.check(function (r) {
            var count = parseInt((r && r.connected_count) ? r.connected_count : 0, 10);

            // In doubt, be strict: show modal
            if (!r || r.error) {
                self.openModal(cb);
                return;
            }

            if (count > 0) {
                self.openModal(cb);
                return;
            }

            cb();
        });
    }
};

// UI handlers for strict mode modal
$(document).on('click', '#tp-connected-users-refresh', function () {
    if (tpExclusiveUsers.oTable) {
        tpExclusiveUsers.oTable.ajax.reload();
    }
    tpExclusiveUsers.refreshContinueState();
});

$(document).on('click', '.tp-exclusive-kick-user', function () {
    var uid = $(this).data('id');

    $.post(
        "sources/users.queries.php",
        {
            type: "disconnect_user",
            user_id: uid,
            key: "<?php echo $session->get('key'); ?>"
        },
        function () {
            if (tpExclusiveUsers.oTable) {
                tpExclusiveUsers.oTable.ajax.reload();
            }
            tpExclusiveUsers.refreshContinueState();
        }
    );
});

$(document).on('click', '#tp-connected-users-disconnect-all', function () {
    $.post(
        "sources/users.queries.php",
        {
            type: "disconnect_users_logged_in",
            exclude_user_id: tpExclusiveUsers.currentUserId,
            key: "<?php echo $session->get('key'); ?>"
        },
        function () {
            if (tpExclusiveUsers.oTable) {
                tpExclusiveUsers.oTable.ajax.reload();
            }
            tpExclusiveUsers.refreshContinueState();
        }
    );
});

$(document).on('click', '#tp-connected-users-continue', function () {
    var cb = tpExclusiveUsers.pendingCallback;
    tpExclusiveUsers.pendingCallback = null;
    $('#tp-connected-users-modal').modal('hide');
    if (typeof cb === 'function') {
        cb();
    }
});

function loadDiskUsage() {
    if (!$('#tp-disk-usage').length) return;

    if (typeof tpRestoreLock !== 'undefined' && tpRestoreLock.active === true) return;

    // If we are preparing a CLI restore, we intentionally stop background refreshes
    if (window.tpBackgroundRefreshDisabled === true) return;

    $.post(
        "sources/backups.queries.php",
        {
            type: "disk_usage",
            key: tpSessionKey
        },
        function (data) {
            data = tpSafePrepareDecode(data);
            if (data === null) {
                return;
            }

            if (!data || data.error) {
                $('#tp-disk-usage').hide();
                return;
            }

            const pct = parseFloat(data.used_percent || 0);
            const $bar = $('#tp-disk-usage-bar');
            $bar.css('width', pct + '%').attr('aria-valuenow', pct);

            // Color thresholds
            $bar.removeClass('bg-success bg-warning bg-danger');
            if (pct >= 90) {
                $bar.addClass('bg-danger');
            } else if (pct >= 75) {
                $bar.addClass('bg-warning');
            } else {
                $bar.addClass('bg-success');
            }

            $('#tp-disk-usage-label').text(data.label || (pct + '%'));
            $('#tp-disk-usage .progress').attr('title', data.tooltip || '');
            $('#tp-disk-usage').show();
        }
    );
}



$(document).ready(function () {
    tpScheduled.init();

    loadDiskUsage();
    window.tpDiskUsageInterval = setInterval(loadDiskUsage, 60000);

    // On-the-fly server list (if present)
    if ($('#onthefly-server-backups-tbody').length) {
        loadOnTheFlyServerBackups();
    }
});

</script>
