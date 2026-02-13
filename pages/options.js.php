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
 * @file      options.js.php
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
require_once __DIR__ . '/../sources/main.functions.php';

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
            'type' => htmlspecialchars((string) $request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('options') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>

<script type='text/javascript'>
    // -------------------------
    // Existing "find options" UI
    // -------------------------
    $(document).on('click', '#button-find-options', function() {
        searchKeyword($('#find-options').val());
    });

    $("#find-options").on('keyup search', function() {
        if ($(this).val() === "") {
            $('.option').removeClass('hidden');
            return false;
        }

        searchKeyword($(this).val());
    });

    function searchKeyword(criteria) {
        var rows = $('[data-keywords*="' + criteria + '"]');

        if (rows.length > 0) {
            // Hide rows
            $('.option').addClass('hidden');

            // Show
            $.each(rows, function(i, value) {
                $(value).removeClass('hidden');
            });
        }
    }

    // ----------------------------------------
    // Inactive Users Management (IUM) - Options
    // ----------------------------------------
    const tpIum = (function() {
        const sessionKey = "<?php echo $session->get('key'); ?>";
        let tz = 'UTC';

        const t = {
            server_answer_error: "<?php echo addslashes($lang->get('server_answer_error')); ?>",
            saved: "<?php echo addslashes($lang->get('inactive_users_mgmt_msg_settings_saved')); ?>",
            task_enqueued: "<?php echo addslashes($lang->get('inactive_users_mgmt_msg_task_enqueued')); ?>",
            task_already_pending: "<?php echo addslashes($lang->get('inactive_users_mgmt_msg_task_already_pending')); ?>"
        };

        function norm01(v) {
            v = (v === null || typeof v === 'undefined') ? '' : String(v);
            v = v.trim();
            return (v === '1' || v.toLowerCase() === 'true') ? '1' : '0';
        }

        function fmtTs(ts) {
            ts = parseInt(ts || 0, 10);
            if (!ts) return '-';
            try {
                return new Date(ts * 1000).toLocaleString(undefined, { timeZone: tz });
            } catch (e) {
                return new Date(ts * 1000).toLocaleString();
            }
        }

        function showAlert(type, msg) {
            const $a = $('#ium-alert');
            if (!$a.length) return;
            $a.removeClass('d-none alert-success alert-danger alert-warning alert-info')
              .addClass('alert-' + type)
              .text(msg || '');
        }

        function hideAlert() {
            const $a = $('#ium-alert');
            if (!$a.length) return;
            $a.addClass('d-none').text('');
        }

        function safeDecode(resp) {
            try {
                // Avoid decoding empty payloads
                if ((resp || '').toString().trim() === '') {
                    return { error: true, message: t.server_answer_error };
                }
                return prepareExchangedData(resp, 'decode', sessionKey);
            } catch (e) {
                return { error: true, message: t.server_answer_error };
            }
        }

        function ajax(type, payload, cb) {
            $.post(
                'sources/inactive_users_mgmt.queries.php',
                {
                    type: type,
                    data: prepareExchangedData(JSON.stringify(payload || {}), 'encode', sessionKey),
                    key: sessionKey
                },
                function(resp) {
                    const decoded = safeDecode(resp);
                    if (typeof cb === 'function') cb(decoded);
                }
            );
        }

        function applyToggleState(toggleId, on) {
            const $t = $('#' + toggleId);
            const $i = $('#' + toggleId + '_input');
            const val = on ? '1' : '0';

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
        }

        function bindToggleFix(toggleId, afterFn) {
            const $t = $('#' + toggleId);
            const $i = $('#' + toggleId + '_input');
            if (!$t.length || !$i.length) return;

            // Capture previous state before click (mousedown happens before click)
            $t.on('mousedown', function() {
                $t.data('tp_prev_state', norm01($i.val()));
            });

            $t.on('click', function() {
                setTimeout(function() {
                    const prev = $t.data('tp_prev_state');
                    let now = norm01($i.val());

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
        }

        function setStatus(status) {
            status = status || {};
            $('#ium-status-next-run').text(fmtTs(status.next_run_at || 0));
            $('#ium-status-last-run').text(fmtTs(status.last_run_at || 0));
            $('#ium-status-last-status').text(status.last_status || '-');
            $('#ium-status-last-message').text(status.last_message || '-');

            // summary is already provided as safe HTML from backend (built from translated labels)
            $('#ium-status-last-summary').html(status.last_summary_html || '-');
        }

        function loadAll() {
            hideAlert();

            ajax('inactive_users_mgmt_get_settings', {}, function(r) {
                if (!r || r.error) {
                    showAlert('danger', (r && r.message) ? r.message : t.server_answer_error);
                    return;
                }

                const res = r.result || {};
                tz = res.tz || 'UTC';

                const s = (res.settings || {});
                applyToggleState('ium-enabled', parseInt(s.enabled || 0, 10) === 1);

                $('#ium-inactivity-days').val(String(s.inactivity_days || 90));
                $('#ium-grace-days').val(String(s.grace_days || 7));
                $('#ium-action').val(String(s.action || 'disable'));
                $('#ium-time').val(String(s.time || '02:00'));

                setStatus(res.status || {});
            });
        }

        function saveSettings() {
            hideAlert();

            const payload = {
                enabled: (norm01($('#ium-enabled_input').val()) === '1') ? 1 : 0,
                inactivity_days: parseInt($('#ium-inactivity-days').val() || '0', 10),
                grace_days: parseInt($('#ium-grace-days').val() || '0', 10),
                action: ($('#ium-action').val() || 'disable').toString(),
                time: ($('#ium-time').val() || '02:00').toString()
            };

            ajax('inactive_users_mgmt_save_settings', payload, function(r) {
                if (!r || r.error) {
                    showAlert('danger', (r && r.message) ? r.message : t.server_answer_error);
                    return;
                }
                showAlert('success', t.saved);
                loadAll();
            });
        }

        function runNow() {
            hideAlert();

            ajax('inactive_users_mgmt_run_now', {}, function(r) {
                if (!r || r.error) {
                    showAlert('danger', (r && r.message) ? r.message : t.server_answer_error);
                    return;
                }
                showAlert('success', t.task_enqueued);
                // refresh status after enqueue
                setTimeout(loadAll, 800);
            });
        }

        function init() {
            if (!$('#inactive-users-mgmt-block').length) return;

            // Ensure toggle hidden input stays coherent
            bindToggleFix('ium-enabled');

            // Buttons
            $(document).on('click', '#ium-save', function(e) {
                e.preventDefault();
                saveSettings();
            });

            $(document).on('click', '#ium-run-now', function(e) {
                e.preventDefault();
                runNow();
            });

            // Initial load
            loadAll();

            // Lightweight status refresh (avoid spamming)
            setInterval(function() {
                if (!$('#inactive-users-mgmt-block').length) return;
                ajax('inactive_users_mgmt_get_settings', {}, function(r) {
                    if (!r || r.error || !r.result) return;
                    const res = r.result || {};
                    tz = res.tz || tz;
                    setStatus(res.status || {});
                });
            }, 30000);
        }

        return { init: init };
    })();

    // Init module when ready
    $(function() {
        tpIum.init();
    });
</script>
