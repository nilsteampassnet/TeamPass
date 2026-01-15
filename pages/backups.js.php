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
    function tpForceLogout() {
        try { window.onbeforeunload = null; } catch (e) {}
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
    function tpResponseLooksLikeHtml(resp) {
        if (typeof resp !== 'string') return false;
        var s = resp.trim();
        return s !== '' && s.charAt(0) === '<';
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
                if (!this._logoutScheduled) { this._logoutScheduled = true; setTimeout(function(){ tpForceLogout(); }, 900); }
                $('#tp-restore-progress-bar').removeClass('progress-bar-animated');
            } catch (e) {}
        }
    };


    // Bootstrap tooltips on demand
    $(function() {
        try { $('body').tooltip({selector:'[data-toggle="tooltip"]'}); } catch (e) {}
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
            data: prepareExchangedData(JSON.stringify(payload), "encode", "<?php echo $session->get('key'); ?>"),
            key: "<?php echo $session->get('key'); ?>"
        },
        function (resp) {
            var r = prepareExchangedData(resp, "decode", "<?php echo $session->get('key'); ?>");
            if (!r || r.error === true) {
                tpToast('error', (r && r.message) ? r.message : "<?php echo addslashes($lang->get('error')); ?>");
                return;
            }

            if (r.is_compatible === true) {
                if (typeof onOk === 'function') onOk(r);
                return;
            }

            if ((r.reason || '') === 'LEGACY_NO_METADATA') {
                tpToast('error', tpBckLegacyNoMeta);
                return;
            }

            tpShowRestoreIncompatMessage(r.backup_tp_files_version || '', r.expected_tp_files_version || '');
        }
    );
}
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
                var r = prepareExchangedData(resp, "decode", "<?php echo $session->get('key'); ?>");
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




    $(document).on('click', '.onthefly-server-backup-delete', function () {
        const fileName = $(this).data('filename');
        if (!fileName) return;

        if (!confirm("<?php echo addslashes($lang->get('bck_onthefly_confirm_delete')); ?>\n\n" + fileName)) {
            return;
        }

        $.post(
            "sources/backups.queries.php",
            {
                type: "onthefly_delete_backup",
                data: prepareExchangedData(JSON.stringify({ file: fileName }), "encode", "<?php echo $session->get('key'); ?>"),
                key: "<?php echo $session->get('key'); ?>"
            },
            function (data) {
            if (tpResponseLooksLikeHtml(data)) { $('#tp-disk-usage').hide(); return; }
            try {
                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
            } catch (e) {
                $('#tp-disk-usage').hide();
                return;
            }
                if (data.error === true) {
                    toastr.error(data.message || "<?php echo addslashes($lang->get('error')); ?>");
                    return;
                }

                // If deleted file was selected for restore, clear selection
                if ($('#onthefly-restore-serverfile').val() === fileName) {
                    $('#onthefly-restore-serverfile').val('');
        $('#onthefly-restore-server-file').val('');
                    $('#onthefly-restore-server-scope').val('');
                    $('#onthefly-restore-server-file').val('');
                    $('#onthefly-restore-file-text').text('');
                    $('#onthefly-restore-server-selected').hide();
                    $('#onthefly-restore-server-selected-name').text('');
                }

                toastr.success("<?php echo addslashes($lang->get('bck_onthefly_deleted')); ?>");
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
                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");

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

        function loadOnTheFlyServerBackups() {
            if ($('#onthefly-server-backups-tbody').length === 0) {
                return;
            }

            $('#onthefly-server-backups-tbody').html(
                '<tr><td colspan="4" class="text-muted"><?php echo addslashes($lang->get('bck_onthefly_loading')); ?></td></tr>'
            );

            $.post(
                "sources/backups.queries.php",
                {
                    type: "onthefly_list_backups",
                    data: prepareExchangedData(JSON.stringify({}), "encode", "<?php echo $session->get('key'); ?>"),
                    key: "<?php echo $session->get('key'); ?>"
                },
                function (data) {
                    data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");

                    if (data.error === true) {
                        toastr.error(data.message || "<?php echo addslashes($lang->get('error')); ?>");
                        return;
                    }

                    let html = '';
                    const currentSelected = ($('#onthefly-restore-serverfile').val() || '').toString();
                    if (!data.files || data.files.length === 0) {
                        html = '<tr><td colspan="4" class="text-muted"><?php echo addslashes($lang->get('bck_onthefly_no_backups_found')); ?></td></tr>';
                        $('#onthefly-server-backups-tbody').html(html);
                        return;
                    }

                    data.files.forEach(function (f, idx) {
                        const name = (f.name || '');
                        const dt = new Date((parseInt(f.mtime, 10) || 0) * 1000).toLocaleString();
                        const sz = tpFmtBytes(f.size_bytes);
                        const dl = (f.download || '');
                        const isSelected = (currentSelected !== '' && currentSelected === name);
                        const fileKey = encodeURIComponent(name);

                        // Avoid breaking HTML attribute if filename contains quotes
                        const titleName = (name + '').replace(/"/g, '&quot;');

                        html += '<tr title="' + titleName + '" data-file="' + fileKey + '" class="' + (isSelected ? 'table-active' : '') + '">';
                        html += '  <td class="text-nowrap">';
                        html += '    <div class="form-check m-0">';
                        html += '      <input class="form-check-input tp-onthefly-backup-radio" type="radio" name="onthefly-server-backup-radio" id="onthefly-bck-' + idx + '" value="' + titleName + '"' + (isSelected ? ' checked' : '') + '>';
                        html += '      <label class="form-check-label" for="onthefly-bck-' + idx + '">' + dt + '</label>';
                        html += '    </div>';
                        html += '  </td>';
                        html += '  <td>' + sz + '</td>';
                        var tpv = (f.tp_files_version || '');
                        html += '  <td class="text-nowrap">' + tpFmtTpVersion(tpv) + '</td>';
                        html += '  <td class="text-right text-nowrap">';
                        html += '    <div class="btn-group btn-group-sm" role="group">';
                        if (dl !== '') {
                            html += '      <a class="btn btn-outline-primary" href="' + dl + '" download title="<?php echo addslashes($lang->get('bck_onthefly_download')); ?>" aria-label="<?php echo addslashes($lang->get('bck_onthefly_download')); ?>" data-toggle="tooltip">';
                            html += '        <i class="fas fa-download"></i>';
                            html += '      </a>';
                        }
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
                        data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');
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
            // PERFORM A RESTORE
            if ($('#onthefly-restore-key').val() !== '') {
                        const opId = $('#onthefly-restore-file').data('operation-id');
                        const serverFile = tpGetOnTheFlyServerFile();
                        if ((opId === undefined || opId === null || opId === '') && (serverFile === undefined || serverFile === null || serverFile === '')) {
                            toastr.error("<?php echo addslashes($lang->get('bck_onthefly_select_backup_first')); ?>");
                            return false;
                        }
                // Compatibility preflight (schema-level) before any lock/maintenance
                var pfPayload = {};
                var pfOpId = $('#onthefly-restore-file').data('operation-id');
                var pfServerScope = $('#onthefly-restore-server-scope').val() || '';
                var pfServerFile = tpGetOnTheFlyServerFile();
                if (pfOpId !== undefined && pfOpId !== null && pfOpId !== '') {
                    pfPayload.operation_id = pfOpId;
                } else {
                    pfPayload.serverScope = pfServerScope;
                    pfPayload.serverFile = pfServerFile;
                }
                tpPreflightRestoreCompatibility(pfPayload, function() {
                    tpExclusiveUsers.ensure(function() {
                // Pre-check: don't lock UI until we are sure the backup can be decrypted.
                // This prevents the "stuck" progress modal when the key is wrong.
                tpProgressToast.show('<?php echo addslashes($lang->get('bck_restore_checking_key')); ?> <i class="fas fa-circle-notch fa-spin"></i>');

                $('#onthefly-restore-progress').addClass('hidden');

                var restoreToken = '';
                var lockStarted = false;

                function ensureLockStarted(pct) {
                    if (lockStarted !== true) {
                        lockStarted = true;
                        try { tpProgressToast.hide(); } catch (e) {}
                        try { $('#onthefly-restore-progress').removeClass('hidden'); } catch (e) {}
                        tpRestoreLock.start();
                    }
                    tpRestoreLock.update(pct);
                }

                function restoreDatabase(offset, clearFilename, totalSize) {
                    var serverScope = $('#onthefly-restore-server-scope').val() || '';
                    var serverFile  = tpGetOnTheFlyServerFile();

                    var opId = $('#onthefly-restore-file').data('operation-id') || 0;

                    // If an upload is selected, ignore the server selection
                    if (opId !== 0) {
                        serverScope = '';
                        serverFile = '';
                        $('#onthefly-restore-server-scope').val('');
                        $('#onthefly-restore-server-file').val('');
                        $('#onthefly-restore-server-selected').hide();
                    }

                    var encryptionKey = simplePurifier($('#onthefly-restore-key').val());

                    // For scheduled restores, don't force a key in the UI (the server will use the instance key)
                    if (serverScope === 'scheduled') {
                        encryptionKey = '';
                    }

                    var data = {
                        'encryptionKey': encryptionKey,
                        'backupFile': opId,
                        'serverScope': serverScope,
                        'serverFile': serverFile,
                        'clearFilename': clearFilename,
                        'offset': (parseInt(offset, 10) || 0),
                        'totalSize': (parseInt(totalSize, 10) || 0)};

                    $.post(
                        "sources/backups.queries.php", 
                        {
                            type: "onthefly_restore",
                            restore_token: restoreToken,
                            data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                            key: "<?php echo $session->get('key'); ?>"
                        },
                        function(response) {
                        // If the restore invalidates the session, PHP can return an HTML error page.
// In that case, don't try to decrypt (avoid JSON errors) and stop the process.
                        if (tpResponseLooksLikeHtml(response)) {
                            tpToast('error', "<?php echo addslashes($lang->get('server_answer_error')); ?>");
                            $('#onthefly-restore-progress').addClass('hidden');
                            $('#onthefly-restore-progress-text').html('0');
                            try { tpProgressToast.hide(); } catch (e) {}
                            toastr.remove();
                            ProcessInProgress = false;
                            tpRestoreLock.cancel();
                            lockStarted = false;
                            return;
                        }
                        data = decodeQueryReturn(response, '<?php echo $session->get('key'); ?>');
                        if (data && data.restore_token) { restoreToken = data.restore_token; }
                        if (!data.error) {
                            if (data.finished === false) {
                                // block time counter
                                ProcessInProgress = true;
                                
                                // Continue with new offset
                                updateProgressBar(data.newOffset, data.totalSize); // Update progress (also starts lock)
                                restoreDatabase(data.newOffset, data.clearFilename, data.totalSize);
                            } else {
                                // SHOW LINK
                                $('#onthefly-restore-finished')
                                    .removeClass('hidden')
                                    .html('<div class="alert alert-success alert-dismissible ml-2">' +
                                        '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>' +
                                        '<h5><i class="icon fa fa-check mr-2"></i><?php echo addslashes($lang->get('done')); ?></h5>' +
                                        '<?php echo addslashes($lang->get('restore_done_now_logout')); ?>' +
                                        '</div>');

                                // Clean progress info
                                $('#onthefly-restore-progress').addClass('hidden');
                                $('#onthefly-restore-progress-text').html('0');                                    

                                // Inform user
                                toastr.remove();
                                toastr.success(
                                    '<?php echo addslashes($lang->get('done')); ?>',
                                    '', {
                                        timeOut: 1000
                                    }
                                );

                                // restart time expiration counter
                                ProcessInProgress = false;

                                // At the end of a restore, it's safer to force logout (DB changed / session may be invalid)
                                ensureLockStarted(100);
                                tpRestoreLock.finish();
}
                        } else {
                            // ERROR
                            try { tpProgressToast.hide(); } catch (e) {}
                            toastr.remove();
                            var errMsg = (data && (data.message || data.error)) ? (data.message || data.error) : "<?php echo addslashes($lang->get('error')); ?>";
                            tpToast('error', errMsg, "<?php echo addslashes($lang->get('error')); ?>", { timeOut: 7000 });

                            // Clean progress info
                            $('#onthefly-restore-progress').addClass('hidden');
                            $('#onthefly-restore-progress-text').html('0');

                            // Ensure UI is unlocked
                            if (lockStarted === true) {
                                tpRestoreLock.cancel();
                                lockStarted = false;
                            }

                            // restart time expiration counter
                            ProcessInProgress = false; 
                        }
	                    });
	                }

                function updateProgressBar(offset, totalSize) {
                    // Show progress to user
                    var percentage = 0;
                    if (totalSize && totalSize > 0) {
                        percentage = Math.round((offset / totalSize) * 100);
                        if (percentage > 100) percentage = 100;
                    }
                    $('#onthefly-restore-progress-text').text(percentage);
                    ensureLockStarted(percentage);
                }

	                // Start restoration
	                restoreDatabase(0, '', 0);
	            });
	        });

                return;
            }
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

                    var r;
                    if (typeof decodeQueryReturn === 'function') {
                        r = decodeQueryReturn(response, '<?php echo $session->get('key'); ?>');
                    } else {
                        r = prepareExchangedData(response, "decode", "<?php echo $session->get('key'); ?>");
                    }

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
            tpScheduledRestoreStart(serverFile, overrideKey);
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
                            up.start();
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
        var myData = prepareExchangedData(object.response, "decode", "<?php echo $session->get('key'); ?>");
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
  
  toggleEmailUI: function() {
    var enabled = ($('#scheduled-email-report-enabled_input').val() === '1');
    $('#scheduled-email-report-only-failures-wrap').toggleClass('d-none', !enabled);
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

        // If session is invalidated, PHP may return an HTML error page.
        if (tpResponseLooksLikeHtml(resp)) {
          tpForceLogout();
          return;
        }

        var r = null;
        try {
          r = prepareExchangedData(resp, "decode", "<?php echo $session->get('key'); ?>");
        } catch (e) {
          tpScheduled.showAlert('danger', '<?php echo addslashes($lang->get('server_answer_error')); ?>');
          return;
        }

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
      $('#backup-scheduled-enabled_input').val(enabledVal);
      try { $('#backup-scheduled-enabled').attr('data-toggle-on', enabledVal === '1' ? 'true' : 'false'); } catch (e) {}
      $('#scheduled-frequency').val(s.frequency || 'daily');
      $('#scheduled-time').val(s.time || '02:00');
      $('#scheduled-dow').val(String(s.dow || 1));
      $('#scheduled-dom').val(String(s.dom || 1));
      $('#scheduled-retention').val(String(s.retention_days || 30));
      $('#scheduled-output-dir').val(s.output_dir || '');

      tpScheduled.toggleFreqUI();

      var emailEnabledVal = (parseInt(s.email_report_enabled || 0, 10) === 1) ? '1' : '0';
      $('#scheduled-email-report-enabled_input').val(emailEnabledVal);
      try { $('#scheduled-email-report-enabled').attr('data-toggle-on', emailEnabledVal === '1' ? 'true' : 'false'); } catch (e) {}

      var onlyFailuresVal = (parseInt(s.email_report_only_failures || 0, 10) === 1) ? '1' : '0';
      $('#scheduled-email-report-only-failures_input').val(onlyFailuresVal);
      try { $('#scheduled-email-report-only-failures').attr('data-toggle-on', onlyFailuresVal === '1' ? 'true' : 'false'); } catch (e) {}

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
      enabled: $('#backup-scheduled-enabled_input').val(),
      frequency: $('#scheduled-frequency').val(),
      time: $('#scheduled-time').val(),
      dow: parseInt($('#scheduled-dow').val(), 10),
      dom: parseInt($('#scheduled-dom').val(), 10),
      retention_days: parseInt($('#scheduled-retention').val(), 10),
      output_dir: $('#scheduled-output-dir').val(),
      email_report_enabled: $('#scheduled-email-report-enabled_input').val(),
      email_report_only_failures: $('#scheduled-email-report-only-failures_input').val()
    };
    console.log(payload)

    tpScheduled.ajax('scheduled_save_settings', payload, function(r) {
      if (!r || r.error) {
        tpScheduled.showAlert('danger', (r && r.message) ? r.message : 'Save failed');
        return;
      }
      tpScheduled.showAlert('success', 'Saved. Next run will be recomputed.');
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
            if (!confirm("<?php echo addslashes($lang->get('bck_onthefly_confirm_delete')); ?>\n\n" + (f.name || ''))) return;
            tpScheduled.ajax('scheduled_delete_backup', { file: f.name }, function(rr) {
              if (rr && rr.error) {
                tpScheduled.showAlert('danger', rr.message || 'Delete failed');
                return;
              }
              // If the selected file was deleted, clear selection
              if ((($('#scheduled-restore-server-file').val() || '').toString()) === (f.name || '')) {
                $('#scheduled-restore-server-file').val('');
                $('#scheduled-restore-selected').hide();
                $('#scheduled-restore-selected-name').text('');
                $('#scheduled-restore-start').prop('disabled', true);
              }
              tpScheduled.loadFiles();
              tpScheduled.loadSettings();
            });
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
    tpScheduled.loadSettings();
    tpScheduled.loadFiles();
    loadDiskUsage();
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
    $('#scheduled-email-report-enabled').on('click', function() {
      // Let the toggle plugin update the hidden input first
      setTimeout(function(){ tpScheduled.toggleEmailUI(); }, 0);
    });

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
    $(document).on('click', '#scheduled-refresh-btn', function(e){ e.preventDefault(); e.stopPropagation(); tpScheduled.refreshAll(); });


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
                key: "<?php echo $session->get('key'); ?>"
            },
            function (data) {
                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
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

    $.post(
        "sources/backups.queries.php",
        {
            type: "disk_usage",
            key: "<?php echo $session->get('key'); ?>"
        },
        function (data) {
            data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");

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
