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
    // Find options (search)
    // -------------------------
    function clearOptionsSearch() {
        $('.option').removeClass('hidden');
    }

    function activateTabForOption($opt) {
        if (!$opt || !$opt.length) {
            return;
        }

        const $pane = $opt.closest('.tab-pane');
        if (!$pane.length) {
            return;
        }

        const paneId = $pane.attr('id');
        const $link = $('#settings-nav-tab a.nav-link[href="#' + paneId + '"]');

        try {
            if ($link.length && typeof $link.tab === 'function') {
                $link.tab('show');
            }
        } catch (e) {
            // Keep page usable even if tabs plugin is unavailable.
        }
    }

    function searchKeyword(criteria) {
        criteria = (criteria || '').toString().trim().toLowerCase();

        if (criteria === '') {
            clearOptionsSearch();
            return;
        }

        const $allOptions = $('.option');
        const $matches = $allOptions.filter(function() {
            const kw = ($(this).data('keywords') || '').toString().toLowerCase();
            return kw.indexOf(criteria) !== -1;
        });

        if ($matches.length === 0) {
            clearOptionsSearch();
            return;
        }

        $allOptions.addClass('hidden');
        $matches.removeClass('hidden');
        activateTabForOption($matches.first());
    }

    $(document).on('click', '#button-find-options', function() {
        searchKeyword($('#find-options').val());
    });

    $('#find-options').on('keyup search', function() {
        if ($(this).val() === '') {
            clearOptionsSearch();
            return false;
        }

        searchKeyword($(this).val());
    });

    const tpOptionsFavorites = (function() {
        const sessionKey = "<?php echo $session->get('key'); ?>";
        const t = {
            server_answer_error: "<?php echo addslashes($lang->get('server_answer_error')); ?>",
            add_title: "<?php echo addslashes($lang->get('settings_favorite_add')); ?>",
            remove_title: "<?php echo addslashes($lang->get('settings_favorite_remove')); ?>",
            added: "<?php echo addslashes($lang->get('settings_favorite_added')); ?>",
            removed: "<?php echo addslashes($lang->get('settings_favorite_removed')); ?>",
            goto_label: "<?php echo addslashes($lang->get('settings_favorite_action_goto')); ?>",
            remove_label: "<?php echo addslashes($lang->get('settings_favorite_action_remove')); ?>",
            category_label: "<?php echo addslashes($lang->get('settings_favorite_category')); ?>",
            menu_title: "<?php echo addslashes($lang->get('settings_category_favorites_title')); ?>"
        };

        let favorites = [];

        function safeDecode(resp) {
            try {
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
                'sources/options_favorites.queries.php',
                {
                    type: type,
                    data: prepareExchangedData(JSON.stringify(payload || {}), 'encode', sessionKey),
                    key: sessionKey
                },
                function(resp) {
                    const decoded = safeDecode(resp);
                    if (typeof cb === 'function') {
                        cb(decoded);
                    }
                }
            );
        }

        function slugify(value) {
            return (value || '').toString().toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '');
        }

        function escapeHtml(value) {
            return $('<div>').text((value || '').toString()).html();
        }

        function getOptionControl($option) {
            let $control = $option.find('.toggle[id]').first();
            if ($control.length) {
                return $control;
            }

            $control = $option.find('input[id]').filter(function() {
                const type = ($(this).attr('type') || '').toLowerCase();
                return type !== 'hidden';
            }).first();
            if ($control.length) {
                return $control;
            }

            $control = $option.find('select[id], textarea[id]').first();
            return $control;
        }

        function getOptionKey($option) {
            const $control = getOptionControl($option);
            if (!$control.length) {
                return '';
            }

            return (($control.attr('id') || '').toString()).replace(/_input$/, '');
        }

        function getLabelCell($option) {
            let $cell = $option.children('.col-10, .col-8, .col-7, .col-6, .col-4, .col-sm-10, .col-12').first();
            if ($cell.length) {
                return $cell;
            }

            $cell = $option.children('label').first();
            if ($cell.length) {
                return $cell;
            }

            return $option;
        }

        function getOptionLabel($option) {
            const key = $option.data('option-key') || '';
            const $clone = getLabelCell($option).clone();
            $clone.find('.form-text, small, .text-muted').remove();
            const text = $.trim($clone.text().replace(/\s+/g, ' '));
            return text !== '' ? text : key;
        }

        function getSectionLabel($option) {
            return ($option.closest('.tab-pane').data('section-label') || '').toString();
        }

        function isFavorite(key) {
            return favorites.indexOf(key) !== -1;
        }

        function showAlert(type, message) {
            const $alert = $('#settings-favorites-alert');
            if (!$alert.length) {
                return;
            }

            $alert.removeClass('d-none alert-success alert-danger alert-warning alert-info')
                .addClass('alert-' + type)
                .text(message || '');
        }

        function hideAlert() {
            const $alert = $('#settings-favorites-alert');
            if (!$alert.length) {
                return;
            }

            $alert.addClass('d-none').text('');
        }

        function updateOptionState($option) {
            const key = ($option.data('option-key') || '').toString();
            if (key === '') {
                return;
            }

            $option.toggleClass('tp-option-is-favorite', isFavorite(key));
        }

        function updateAllOptionStates() {
            $('.option[data-option-key]').each(function() {
                updateOptionState($(this));
            });
        }

        function decorateOptions() {
            $('.option').each(function() {
                const $option = $(this);
                const key = getOptionKey($option);
                if (key === '') {
                    return;
                }

                $option.attr('data-option-key', key);
                if (!$option.attr('id')) {
                    $option.attr('id', 'option-row-' + slugify(key));
                }
            });
        }

        function buildSectionMenuItem($option) {
            const key = ($option.data('option-key') || '').toString();
            if (key === '') {
                return '';
            }

            const active = isFavorite(key);
            const iconClass = active ? 'fa-solid text-warning' : 'fa-regular text-muted';
            const title = active ? t.remove_title : t.add_title;
            const label = escapeHtml(getOptionLabel($option));

            return '' +
                '<button type="button" class="dropdown-item tp-favorite-menu-item" data-option-key="' + escapeHtml(key) + '" title="' + escapeHtml(title) + '">' +
                    '<i class="fa-star ' + iconClass + '"></i>' +
                    '<span class="tp-favorite-menu-label">' + label + '</span>' +
                '</button>';
        }

        function renderSectionMenus() {
            $('#settings-tab-content .tab-pane').not('#settings-tab-favorites').each(function() {
                const $pane = $(this);
                const $card = $pane.children('.card.card-info').first();
                const $header = $card.children('.card-header').first();
                if (!$card.length || !$header.length) {
                    return;
                }

                let $tools = $header.children('.card-tools.tp-section-favorites-tools');
                if (!$tools.length) {
                    $tools = $(
                        '<div class="card-tools tp-section-favorites-tools">' +
                            '<div class="btn-group">' +
                                '<button type="button" class="btn btn-tool dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="' + escapeHtml(t.menu_title) + '" aria-label="' + escapeHtml(t.menu_title) + '">' +
                                    '<i class="fa-solid fa-star"></i>' +
                                '</button>' +
                                '<div class="dropdown-menu dropdown-menu-right tp-section-favorites-menu"></div>' +
                            '</div>' +
                        '</div>'
                    );
                    $header.append($tools);
                }

                const itemsHtml = $pane.find('.option[data-option-key]').map(function() {
                    return buildSectionMenuItem($(this));
                }).get().join('');

                $tools.find('.tp-section-favorites-menu').html(itemsHtml);
            });
        }

        function buildFavoriteItem(key) {
            const $option = $('.option[data-option-key="' + key + '"]').first();
            if (!$option.length) {
                return '';
            }

            const label = escapeHtml(getOptionLabel($option));
            const section = escapeHtml(getSectionLabel($option));

            return '' +
                '<div class="list-group-item">' +
                    '<div class="d-flex justify-content-between align-items-center flex-wrap">' +
                        '<div class="mr-3">' +
                            '<div class="font-weight-bold">' + label + '</div>' +
                            '<small class="text-muted">' + t.category_label + ': ' + section + '</small>' +
                        '</div>' +
                        '<div class="tp-favorite-actions">' +
                            '<button type="button" class="btn btn-xs btn-outline-primary tp-favorite-go" data-option-key="' + escapeHtml(key) + '">' + t.goto_label + '</button>' +
                            '<button type="button" class="btn btn-xs btn-outline-secondary tp-favorite-remove" data-option-key="' + escapeHtml(key) + '">' + t.remove_label + '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';
        }

        function renderFavorites() {
            const $list = $('#settings-favorites-list');
            const $empty = $('#settings-favorites-empty');
            if (!$list.length || !$empty.length) {
                return;
            }

            const rendered = favorites.map(function(key) {
                return buildFavoriteItem(key);
            }).filter(function(html) {
                return html !== '';
            });

            if (rendered.length === 0) {
                $list.empty().addClass('d-none');
                $empty.removeClass('d-none');
                return;
            }

            $empty.addClass('d-none');
            $list.html(rendered.join('')).removeClass('d-none');
        }

        function refreshUi() {
            renderFavorites();
            updateAllOptionStates();
            renderSectionMenus();
        }

        function goToOption(key) {
            const $option = $('.option[data-option-key="' + key + '"]').first();
            if (!$option.length) {
                return;
            }

            hideAlert();
            clearOptionsSearch();
            activateTabForOption($option);

            setTimeout(function() {
                const target = $option.get(0);
                if (target && typeof target.scrollIntoView === 'function') {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                $option.addClass('tp-favorite-highlight');
                setTimeout(function() {
                    $option.removeClass('tp-favorite-highlight');
                }, 2200);
            }, 150);
        }

        function loadFavorites() {
            ajax('options_favorites_get', {}, function(response) {
                if (!response || response.error) {
                    showAlert('danger', (response && response.message) ? response.message : t.server_answer_error);
                    return;
                }

                favorites = Array.isArray(response.result && response.result.option_keys) ? response.result.option_keys : [];
                refreshUi();
            });
        }

        function addFavorite(key) {
            ajax('options_favorites_add', { option_key: key }, function(response) {
                if (!response || response.error) {
                    showAlert('danger', (response && response.message) ? response.message : t.server_answer_error);
                    return;
                }

                if (!isFavorite(key)) {
                    favorites.push(key);
                }
                refreshUi();
                showAlert('success', t.added);
            });
        }

        function removeFavorite(key) {
            ajax('options_favorites_remove', { option_key: key }, function(response) {
                if (!response || response.error) {
                    showAlert('danger', (response && response.message) ? response.message : t.server_answer_error);
                    return;
                }

                favorites = favorites.filter(function(entry) {
                    return entry !== key;
                });
                refreshUi();
                showAlert('success', t.removed);
            });
        }

        function bindEvents() {
            $(document).on('click', '.tp-favorite-menu-item', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const key = ($(this).data('option-key') || '').toString();
                if (key === '') {
                    return;
                }

                if (isFavorite(key)) {
                    removeFavorite(key);
                } else {
                    addFavorite(key);
                }
            });

            $(document).on('click', '.tp-favorite-go', function() {
                goToOption(($(this).data('option-key') || '').toString());
            });

            $(document).on('click', '.tp-favorite-remove', function() {
                removeFavorite(($(this).data('option-key') || '').toString());
            });
        }

        function init() {
            decorateOptions();
            renderSectionMenus();
            bindEvents();
            loadFavorites();
        }

        return {
            init: init,
            goToOption: goToOption
        };
    })();

    $(document).ready(function() {
        const $favorites = $('#settings-nav-favorites');
        try {
            if ($favorites.length && typeof $favorites.tab === 'function') {
                $favorites.tab('show');
            }
        } catch (e) {
            // Silent
        }

        tpOptionsFavorites.init();
    });

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


    const tpNetworkAcl = (function() {
        const sessionKey = "<?php echo $session->get('key'); ?>";
        const t = {
            server_answer_error: "<?php echo addslashes($lang->get('server_answer_error')); ?>",
            enabled: "<?php echo addslashes($lang->get('enabled')); ?>",
            disabled: "<?php echo addslashes($lang->get('disabled')); ?>",
            edit: "<?php echo addslashes($lang->get('network_security_edit')); ?>",
            delete_label: "<?php echo addslashes($lang->get('network_security_delete')); ?>",
            enable_label: "<?php echo addslashes($lang->get('network_security_enable')); ?>",
            disable_label: "<?php echo addslashes($lang->get('network_security_disable')); ?>",
            no_rule: "<?php echo addslashes($lang->get('network_security_no_rule')); ?>",
            yes: "<?php echo addslashes($lang->get('yes')); ?>",
            no: "<?php echo addslashes($lang->get('no')); ?>",
            saved: "<?php echo addslashes($lang->get('done')); ?>"
        };

        function safeDecode(resp) {
            try {
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
                'sources/admin.queries.php',
                {
                    type: type,
                    data: prepareExchangedData(JSON.stringify(payload || {}), 'encode', sessionKey),
                    key: sessionKey
                },
                function(resp) {
                    const decoded = safeDecode(resp);
                    if (typeof cb === 'function') {
                        cb(decoded);
                    }
                }
            );
        }

        function escapeHtml(value) {
            return $('<div>').text((value || '').toString()).html();
        }
        function norm01(value) {
            return String(value) === '1' ? '1' : '0';
        }

        function applyToggleState(toggleId, on) {
            const $t = $('#' + toggleId);
            const $i = $('#' + toggleId.replace('_toggle', '_input'));
            const val = on ? '1' : '0';

            if ($i.length) {
                $i.val(val);
            }

            try {
                $t.attr('data-toggle-on', on ? 'true' : 'false');
            } catch (e) {}

            try {
                if (typeof $t.toggles === 'function') {
                    try {
                        $t.toggles(on);
                    } catch (e1) {
                        $t.toggles({on: on});
                    }
                }
            } catch (e) {}

            try {
                if (typeof $t.bootstrapSwitch === 'function') {
                    $t.bootstrapSwitch('state', on, true);
                }
            } catch (e) {}

            try {
                if (typeof $t.bootstrapToggle === 'function') {
                    $t.bootstrapToggle(on ? 'on' : 'off');
                }
            } catch (e) {}

            try {
                $t.toggleClass('on', on).toggleClass('off', !on).toggleClass('active', on);
            } catch (e) {}
        }

        function bindToggleFix(toggleId) {
            const $t = $('#' + toggleId);
            const $i = $('#' + toggleId.replace('_toggle', '_input'));

            if (!$t.length || !$i.length) {
                return;
            }

            $t.on('mousedown', function() {
                $t.data('tp_prev_state', norm01($i.val()));
            });

            $t.on('click', function() {
                setTimeout(function() {
                    const prev = $t.data('tp_prev_state');
                    let now = norm01($i.val());

                    if (prev === now) {
                        now = prev === '1' ? '0' : '1';
                    }

                    $i.val(now);
                }, 0);
            });
        }


        function showAlert(type, message) {
            const $alert = $('#network-security-alert');
            if (!$alert.length) {
                return;
            }

            $alert.removeClass('d-none alert-success alert-danger alert-warning alert-info')
                .addClass('alert-' + type)
                .text(message || '');
        }

        function applyContext(context) {
            context = context || {};
            $('#network-detected-ip').text((context.detected_ip || '').toString());
            $('#network-remote-addr').text((context.remote_addr || '').toString());
            $('#network-server-ip').text((context.server_ip || '').toString());
            $('#network-context-mode').text((context.mode || '').toString());
            $('#network-context-header').text((context.header_name || '').toString());
            $('#network-context-proxy-used').text(context.trusted_proxy_used ? t.yes : t.no);
        }

        function buildRow(rule) {
            const enabled = parseInt(rule.enabled || 0, 10) === 1;
            const nextEnabled = enabled ? 0 : 1;
            const toggleClass = enabled ? 'btn-outline-warning' : 'btn-outline-success';
            const toggleTitle = enabled ? t.disable_label : t.enable_label;
            const toggleIcon = enabled ? 'fa-toggle-off' : 'fa-toggle-on';

            return '' +
                '<tr>' +
                    '<td>' + escapeHtml(rule.rule_definition || '') + '</td>' +
                    '<td>' + escapeHtml(rule.comment || '') + '</td>' +
                    '<td>' + (enabled ? escapeHtml(t.enabled) : escapeHtml(t.disabled)) + '</td>' +
                    '<td class="text-right text-nowrap">' +
                        '<button type="button" class="btn btn-xs btn-outline-primary mr-1 network-rule-edit" title="' + escapeHtml(t.edit) + '" aria-label="' + escapeHtml(t.edit) + '" data-id="' + escapeHtml(rule.id) + '" data-type="' + escapeHtml(rule.type) + '" data-rule="' + escapeHtml(rule.rule_definition || '') + '" data-comment="' + escapeHtml(rule.comment || '') + '" data-enabled="' + escapeHtml(enabled ? '1' : '0') + '"><i class="fa-solid fa-pen-to-square"></i></button>' +
                        '<button type="button" class="btn btn-xs ' + toggleClass + ' mr-1 network-rule-toggle" title="' + escapeHtml(toggleTitle) + '" aria-label="' + escapeHtml(toggleTitle) + '" data-id="' + escapeHtml(rule.id) + '" data-enabled="' + escapeHtml(String(nextEnabled)) + '"><i class="fa-solid ' + toggleIcon + '"></i></button>' +
                        '<button type="button" class="btn btn-xs btn-outline-danger network-rule-delete" title="' + escapeHtml(t.delete_label) + '" aria-label="' + escapeHtml(t.delete_label) + '" data-id="' + escapeHtml(rule.id) + '"><i class="fa-solid fa-trash"></i></button>' +
                    '</td>' +
                '</tr>';
        }

        function renderRules(rules) {
            const whitelist = Array.isArray(rules && rules.whitelist) ? rules.whitelist : [];
            const blacklist = Array.isArray(rules && rules.blacklist) ? rules.blacklist : [];

            $('#network-whitelist-rules-body').html(whitelist.length ? whitelist.map(buildRow).join('') : '<tr><td colspan="4" class="text-center text-muted">' + escapeHtml(t.no_rule) + '</td></tr>');
            $('#network-blacklist-rules-body').html(blacklist.length ? blacklist.map(buildRow).join('') : '<tr><td colspan="4" class="text-center text-muted">' + escapeHtml(t.no_rule) + '</td></tr>');
        }

        function applySettings(settings) {
            settings = settings || {};
            applyToggleState('network_blacklist_enabled_toggle', String(settings.network_blacklist_enabled || '0') === '1');
            applyToggleState('network_whitelist_enabled_toggle', String(settings.network_whitelist_enabled || '0') === '1');
            $('#network_security_mode').val((settings.network_security_mode || 'direct').toString());
            $('#network_security_header').val((settings.network_security_header || 'x-forwarded-for').toString());
            $('#network_trusted_proxies').val((settings.network_trusted_proxies || '').toString());
        }

        function resetForm(listType) {
            $('#network-' + listType + '-rule-id').val('0');
            $('#network-' + listType + '-rule-definition').val('');
            $('#network-' + listType + '-rule-comment').val('');
            $('#network-' + listType + '-rule-enabled').prop('checked', true);
        }

        function loadAll() {
            ajax('network_get_rules', {}, function(response) {
                if (!response || response.error) {
                    showAlert('danger', (response && response.message) ? response.message : t.server_answer_error);
                    return;
                }
                const result = response.result || {};
                applyContext(result.context || {});
                renderRules(result.rules || {});
                applySettings(result.settings || {});
            });
        }

        function saveSettings() {
            const payload = {
                network_blacklist_enabled: norm01($('#network_blacklist_enabled_input').val()) === '1' ? 1 : 0,
                network_whitelist_enabled: norm01($('#network_whitelist_enabled_input').val()) === '1' ? 1 : 0,
                network_security_mode: $('#network_security_mode').val(),
                network_security_header: $('#network_security_header').val(),
                network_trusted_proxies: $('#network_trusted_proxies').val()
            };

            ajax('network_save_settings', payload, function(response) {
                if (!response || response.error) {
                    showAlert('danger', (response && response.message) ? response.message : t.server_answer_error);
                    return;
                }
                const result = response.result || {};
                applyContext(result.context || {});
                renderRules(result.rules || {});
                applySettings(result.settings || payload);
                showAlert('success', response.message || '');
            });
        }

        function saveRule(listType) {
            ajax('network_save_rule', {
                id: $('#network-' + listType + '-rule-id').val(),
                list_type: listType,
                rule_definition: $('#network-' + listType + '-rule-definition').val(),
                comment: $('#network-' + listType + '-rule-comment').val(),
                enabled: $('#network-' + listType + '-rule-enabled').is(':checked') ? 1 : 0
            }, function(response) {
                if (!response || response.error) {
                    showAlert('danger', (response && response.message) ? response.message : t.server_answer_error);
                    return;
                }
                const result = response.result || {};
                applyContext(result.context || {});
                renderRules(result.rules || {});
                resetForm(listType);
                showAlert('success', response.message || '');
            });
        }

        function addSpecialRule(source) {
            ajax('network_add_special_rule', { source: source }, function(response) {
                if (!response || response.error) {
                    showAlert('danger', (response && response.message) ? response.message : t.server_answer_error);
                    return;
                }
                const result = response.result || {};
                applyContext(result.context || {});
                renderRules(result.rules || {});
                showAlert('success', response.message || '');
            });
        }

        function bindEvents() {
            $(document).on('click', '#network-security-save-settings', function(e) {
                e.preventDefault();
                saveSettings();
            });
            $(document).on('click', '#network-add-current-ip', function(e) {
                e.preventDefault();
                addSpecialRule('current_ip');
            });
            $(document).on('click', '#network-add-server-ip', function(e) {
                e.preventDefault();
                addSpecialRule('server_ip');
            });
            $(document).on('click', '#network-whitelist-rule-save', function(e) {
                e.preventDefault();
                saveRule('whitelist');
            });
            $(document).on('click', '#network-blacklist-rule-save', function(e) {
                e.preventDefault();
                saveRule('blacklist');
            });
            $(document).on('click', '#network-whitelist-rule-reset', function(e) {
                e.preventDefault();
                resetForm('whitelist');
            });
            $(document).on('click', '#network-blacklist-rule-reset', function(e) {
                e.preventDefault();
                resetForm('blacklist');
            });
            $(document).on('click', '.network-rule-edit', function() {
                const listType = ($(this).data('type') || '').toString();
                if (listType !== 'whitelist' && listType !== 'blacklist') {
                    return;
                }
                $('#network-' + listType + '-rule-id').val(($(this).data('id') || '0').toString());
                $('#network-' + listType + '-rule-definition').val(($(this).data('rule') || '').toString());
                $('#network-' + listType + '-rule-comment').val(($(this).data('comment') || '').toString());
                $('#network-' + listType + '-rule-enabled').prop('checked', String($(this).data('enabled') || '0') === '1');
            });
            $(document).on('click', '.network-rule-delete', function() {
                ajax('network_delete_rule', { id: $(this).data('id') }, function(response) {
                    if (!response || response.error) {
                        showAlert('danger', (response && response.message) ? response.message : t.server_answer_error);
                        return;
                    }
                    const result = response.result || {};
                    applyContext(result.context || {});
                    renderRules(result.rules || {});
                    showAlert('success', response.message || '');
                });
            });
            $(document).on('click', '.network-rule-toggle', function() {
                ajax('network_toggle_rule', { id: $(this).data('id'), enabled: $(this).data('enabled') }, function(response) {
                    if (!response || response.error) {
                        showAlert('danger', (response && response.message) ? response.message : t.server_answer_error);
                        return;
                    }
                    const result = response.result || {};
                    applyContext(result.context || {});
                    renderRules(result.rules || {});
                    showAlert('success', response.message || '');
                });
            });
        }

        function init() {
            if (!$('#network-security-block').length) {
                return;
            }
            bindToggleFix('network_blacklist_enabled_toggle');
            bindToggleFix('network_whitelist_enabled_toggle');
            bindEvents();
            loadAll();
        }

        return { init: init };
    })();

const tpHealthLogSettings = (function() {
    const sessionKey = "<?php echo $session->get('key'); ?>";
    const t = {
        server_answer_error: "<?php echo addslashes($lang->get('server_answer_error')); ?>",
        saved: "<?php echo addslashes($lang->get('done')); ?>"
    };

    function safeDecode(resp) {
        try {
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
            'sources/admin.queries.php',
            {
                type: type,
                data: prepareExchangedData(JSON.stringify(payload || {}), 'encode', sessionKey),
                key: sessionKey
            },
            function(resp) {
                const decoded = safeDecode(resp);
                if (typeof cb === 'function') {
                    cb(decoded);
                }
            }
        );
    }

    function showAlert(type, message) {
        const $alert = $('#health-logs-settings-alert');
        if (!$alert.length) {
            return;
        }

        $alert.removeClass('d-none alert-success alert-danger alert-warning alert-info')
            .addClass('alert-' + type)
            .text(message || '');
    }

    function toggleManualRows(mode, clearValues) {
        const isManual = String(mode || 'auto') === 'manual';
        const shouldClear = clearValues === true;

        $('#health-log-teampass-path-row').toggleClass('hidden', !isManual);
        $('#health-log-php-fpm-path-row').toggleClass('hidden', !isManual);
        $('#health-logs-settings-save-row').toggleClass('hidden', !isManual);

        if (isManual === false && shouldClear === true) {
            $('#health_teampass_log_path').val('');
            $('#health_php_fpm_log_path').val('');
        }
    }

    function applySettings(settings) {
        settings = settings || {};
        const mode = (settings.health_logs_mode || 'auto').toString();
        $('#health_logs_mode').val(mode);
        $('#health_teampass_log_path').val((settings.health_teampass_log_path || '').toString());
        $('#health_php_fpm_log_path').val((settings.health_php_fpm_log_path || '').toString());
        toggleManualRows(mode);
    }

    function loadSettings() {
        ajax('health_logs_get_settings', {}, function(response) {
            if (!response || response.error) {
                showAlert('danger', (response && response.message) ? response.message : t.server_answer_error);
                return;
            }

            applySettings((response.result || {}).settings || {});
        });
    }

    function saveSettings(showSuccessAlert) {
        const mode = ($('#health_logs_mode').val() || 'auto').toString();

        ajax(
            'health_logs_save_settings',
            {
                health_logs_mode: mode,
                health_teampass_log_path: mode === 'manual' ? $('#health_teampass_log_path').val() : '',
                health_php_fpm_log_path: mode === 'manual' ? $('#health_php_fpm_log_path').val() : ''
            },
            function(response) {
                if (!response || response.error) {
                    showAlert('danger', (response && response.message) ? response.message : t.server_answer_error);
                    return;
                }

                applySettings((response.result || {}).settings || {});
                if (showSuccessAlert === true) {
                    showAlert('success', response.message || t.saved);
                } else {
                    $('#health-logs-settings-alert').addClass('d-none').text('');
                }
            }
        );
    }

    function bindEvents() {
        $(document).on('change', '#health_logs_mode', function() {
            const selectedMode = ($(this).val() || 'auto').toString();
            const clearValues = selectedMode !== 'manual';

            toggleManualRows(selectedMode, clearValues);
            saveSettings(selectedMode !== 'manual');
        });

        $(document).on('click', '#health-logs-settings-save', function(e) {
            e.preventDefault();
            saveSettings(true);
        });
    }

    function init() {
        if (!$('#health-logs-settings-block').length) {
            return;
        }

        bindEvents();
        loadSettings();
    }

    return {
        init: init
    };
})();

$(function() {
    tpHealthLogSettings.init();
});



    $(function() {
        tpNetworkAcl.init();
    });
</script>
