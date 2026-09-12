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
 * @file      utilities.logs.js.php
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
require_once __DIR__.'/../sources/logs_filter_logic.php';

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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('utilities.logs') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[

    // Same rule as in utilities.logs.php: knowledge base logs are administrator only
    var kbEnabled = <?php echo isset($SETTINGS['enable_kb']) === true && (int) $SETTINGS['enable_kb'] === 1 && (int) ($session->get('user-admin') ?? 0) === 1 ? 'true' : 'false'; ?>;
    const authenticationLockoutAdmin = <?php echo (int) ($session->get('user-admin') ?? 0) === 1 ? 'true' : 'false'; ?>;
    const authenticationLockoutMessages = <?php
        echo (string) json_encode(
            [
                'scopeLogin' => $lang->get('authentication_lockout_scope_login'),
                'scopeIp' => $lang->get('authentication_lockout_scope_ip'),
                'viewFailures' => $lang->get('authentication_lockout_view_failures'),
                'remove' => $lang->get('authentication_lockout_remove'),
                'remaining' => $lang->get('remaining_lock_time'),
                'expired' => $lang->get('authentication_lockout_expired'),
                'confirm' => $lang->get('authentication_lockout_remove_confirm'),
                'ipWarning' => $lang->get('authentication_lockout_remove_ip_warning'),
                'clientWarning' => $lang->get('authentication_lockout_client_warning'),
                'invalidTarget' => $lang->get('authentication_lockout_invalid_target'),
                'serverError' => $lang->get('server_answer_error'),
                'caution' => $lang->get('caution'),
                'close' => $lang->get('close'),
            ],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );
        ?>;

    // The column rule comes from logsVisibleColumns()'s own data, not from a second copy of the
    // mapping: the client needs its column set before the first ajax call, so it cannot wait for
    // the response to carry it.
    const logColumnRule = <?php echo (string) json_encode(logsSystemColumnRule(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const logFixedColumns = <?php echo (string) json_encode(
        ['items' => logsVisibleColumns('items', []), 'kb' => logsVisibleColumns('kb', [])],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ); ?>;

    const logColumnTitles = <?php echo (string) json_encode(
        [
            'date' => $lang->get('date'),
            'type' => $lang->get('logs_col_type'),
            'label' => $lang->get('label'),
            'user' => $lang->get('user'),
            'source' => $lang->get('logs_col_source'),
            'ip' => $lang->get('ip'),
            'channel' => $lang->get('authentication_channel'),
            'target' => $lang->get('logs_col_target'),
            'actions' => $lang->get('action'),
            'id' => $lang->get('id'),
            'folder' => $lang->get('folder'),
            'action' => $lang->get('action'),
            'api' => $lang->get('logs_channel_api'),
            'personal' => $lang->get('at_personnel'),
            'details' => $lang->get('details'),
        ],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    ); ?>;

    // Every label the table renders is translated here, so the JSON payload carries codes only
    // and never a sentence nor a fragment of markup.
    const logMessages = <?php echo (string) json_encode(
        [
            'yes' => $lang->get('yes'),
            'no' => $lang->get('no'),
            'web' => $lang->get('logs_channel_web'),
            'api' => $lang->get('logs_channel_api'),
            'sourceSystem' => $lang->get('logs_source_system'),
            'sourceItems' => $lang->get('logs_source_items'),
            'sourceKb' => $lang->get('logs_source_kb'),
            'blacklist' => $lang->get('network_security_add_ip_to_blacklist'),
            'purgeScope' => $lang->get('logs_purge_scope_summary'),
            'purgeNeedsDates' => $lang->get('logs_purge_needs_date_range'),
            'purgeBlocked' => $lang->get('logs_purge_blocked_facets'),
            'purgeEntries' => $lang->get('logs_purge_entries'),
            'facetTerm' => $lang->get('logs_facet_term'),
            'facetFolder' => $lang->get('folder'),
            'facetScope' => $lang->get('logs_facet_scope'),
            'facetUser' => $lang->get('user'),
            'facetDateFrom' => $lang->get('from'),
            'facetDateTo' => $lang->get('to'),
            'caution' => $lang->get('caution'),
            'serverError' => $lang->get('server_answer_error'),
            'confirmCheckbox' => $lang->get('please_confirm_by_clicking_checkbox'),
        ],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    ); ?>;

    const logTypeTitles = <?php echo (string) json_encode(
        [
            'connections' => $lang->get('logs_type_connections'),
            'failed' => $lang->get('logs_type_failed'),
            'errors' => $lang->get('logs_type_errors'),
            'admin' => $lang->get('logs_type_admin'),
        ],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    ); ?>;

    const logActionTitles = <?php echo (string) json_encode(
        array_combine(
            array_merge(logsAllowedItemActions(), logsAllowedKbActions()),
            array_map(
                static fn (string $action): string => (string) $lang->get($action),
                array_merge(logsAllowedItemActions(), logsAllowedKbActions())
            )
        ),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    ); ?>;

    const logsDataUrl = '<?php echo $SETTINGS['cpassman_url']; ?>/sources/logs.datatables.php';
    const logsSessionKey = '<?php echo $session->get('key'); ?>';

    var oTableLogs = null;
    var oTableAuthenticationLockouts = null;
    var authenticationLockoutTimersStarted = false;
    // The column signature the table was built with. A different one needs a rebuild, not a
    // reload: DataTables cannot change its column count in place.
    var logCurrentSignature = '';

    // Prepare tooltips
    $('.infotip').tooltip();

    /**
     * Decode HTML entities (e.g. &eacute; -> é, &amp; -> &).
     *
     * @param {*} str Value normalized server-side by normalizeLogDisplayValue().
     * @return {string}
     */
    function decodeHtmlEntities(str) {
        if (str === null || str === undefined) {
            return '';
        }
        const txt = document.createElement('textarea');
        txt.innerHTML = str;
        return txt.value;
    }

    /**
     * Decode the remaining display entity layer, then escape the value as HTML text.
     *
     * Reserved for display-only columns normalized server-side by normalizeLogDisplayValue(), so
     * legacy accents render correctly. Never use it on an operational identifier: the unlock
     * action targets the stored value, and a decoded one would no longer be that value.
     *
     * @param {*} value Value to display.
     * @return {string}
     */
    function renderLogText(value) {
        return escapeLogValue(decodeHtmlEntities(value));
    }

    /**
     * Column set for a source and its selected types.
     *
     * Reproduces logsVisibleColumns() over the rule the server serialised, so the two cannot
     * disagree about which column exists.
     *
     * @param {string} source Selected source.
     * @param {Array} types Selected interface type keys.
     * @return {Array}
     */
    function logVisibleColumns(source, types) {
        if (source !== 'system') {
            return (logFixedColumns[source] || []).slice()
        }

        const columns = logColumnRule.base.slice()
        logColumnRule.order.forEach((key) => {
            if (types.indexOf(key) === -1) return
            (logColumnRule.per_type[key] || []).forEach((column) => {
                if (columns.indexOf(column) === -1) columns.push(column)
            })
        })

        return columns
    }

    // ---------------------------------------------------------------- FILTERS

    // Current facet selection. Sent as one JSON blob so the server validates it against its own
    // allow-lists in a single place, and so the purge can be given the very same payload.
    var logFilters = {}

    const collectFilters = () => {
      const filters = {
        source: $('.logs-source:checked').val() || 'system',
        term: $('#logs-term').val() || ''
      }

      // Skipped, never collected-then-deleted: two groups can legitimately feed the same facet
      // (items and the knowledge base both select an action), and dropping the whole key because
      // one of them is hidden silently discarded the visible selection.
      const visible = function() {
        return $(this).closest('.logs-facet-group.hidden, .logs-facet-option.hidden').length === 0
      }

      $('.logs-facet:checked').filter(visible).each(function() {
        const facet = $(this).data('facet')
        if (!filters[facet]) filters[facet] = []
        filters[facet].push($(this).val())
      })

      $('.logs-facet-date, .logs-facet-single').filter(visible).each(function() {
        const value = ($(this).val() || '').toString().trim()
        if (value !== '') filters[$(this).data('facet')] = value
      })

      return filters
    }

    // Label of the current selection of a remote-loaded picker, or the raw id while its option
    // has not been received yet.
    const select2Text = (selector, value) => {
      const selection = $(selector).select2('data')
      return (Array.isArray(selection) && selection.length > 0 && selection[0].text)
        ? selection[0].text
        : String(value)
    }

    // Human label for one active facet value, used by the chips row.
    const facetChipLabel = (facet, value) => {
      if (facet === 'types') return logTypeTitles[value] || value
      if (facet === 'actions') return logActionTitles[value] || value
      if (facet === 'channel') return logMessages[value] || value
      if (facet === 'scope') return $('#logs-scope option[value="' + value + '"]').text().trim()
      if (facet === 'user_id') return logMessages.facetUser + ': ' + select2Text('#logs-user', value)
      if (facet === 'folder_id') return logMessages.facetFolder + ': ' + select2Text('#logs-folder', value)
      if (facet === 'date_from') return logMessages.facetDateFrom + ' ' + value
      if (facet === 'date_to') return logMessages.facetDateTo + ' ' + value
      return String(value)
    }

    const renderChips = () => {
      const chips = []
      Object.keys(logFilters).forEach((facet) => {
        if (facet === 'term' || facet === 'source') return
        const value = logFilters[facet]
        if (Array.isArray(value)) {
          value.forEach((v) => chips.push({facet: facet, value: v}))
        } else {
          chips.push({facet: facet, value: value})
        }
      })

      const container = $('#logs-chips').empty()
      chips.forEach((chip) => {
        $('<span class="badge badge-primary mr-1 mb-1 logs-chip" style="cursor:pointer;"></span>')
          .attr('data-facet', chip.facet)
          .attr('data-value', chip.value)
          .text(facetChipLabel(chip.facet, chip.value))
          .append(' <i class="fas fa-times"></i>')
          .appendTo(container)
      })

      $('#logs-chips-row').toggleClass('hidden', chips.length === 0)
      $('#logs-filters-count').text(chips.length).toggleClass('hidden', chips.length === 0)
    }

    // Show only the facet groups, and the individual options, the selected source understands.
    const applySourceVisibility = (source) => {
      $('.logs-facet-group[data-source], .logs-facet-option[data-source]').each(function() {
        const sources = ($(this).attr('data-source') || '').split(' ')
        $(this).toggleClass('hidden', sources.indexOf(source) === -1)
      })
    }

    // ------------------------------------------------------------------ TABLE

    // Cell renderers, one per column key. Every one of them escapes: the payload is data, and the
    // few pieces of markup the table shows (badges, the unlock button) are built here.
    const logCellRenderers = {
      type: (row) => '<span class="badge badge-info">' + escapeLogValue(logTypeTitles[row.type] || row.type) + '</span>',
      source: (row) => escapeLogValue(logMessages[row.source] || row.source),
      channel: (row) => escapeLogValue(logMessages[row.channel] || row.channel),
      action: (row) => escapeLogValue(logActionTitles[row.action] || row.action),
      api: (row) => escapeLogValue(row.api === true ? logMessages.yes : logMessages.no),
      personal: (row) => escapeLogValue(row.personal === true ? logMessages.yes : logMessages.no),
      actions: (row) => row.can_blacklist === true
        ? '<button type="button" class="btn btn-sm btn-outline-danger failed-auth-add-blacklist" data-ip="'
          + escapeLogAttribute(row.ip) + '" title="'
          + escapeLogAttribute(logMessages.blacklist) + '">'
          + '<i class="fa-solid fa-ban"></i></button>'
        : ''
    }

    /**
     * Build (or rebuild) the table for a column set.
     *
     * DataTables cannot change its column count in place, so a source or type change that alters
     * the set destroys the instance and starts a new one. Within one set a filter change is a
     * plain ajax reload.
     *
     * @param {Array} columns Column keys, in display order.
     * @return {void}
     */
    function buildLogsTable(source, columns) {
        // The source is part of the signature: two sources could otherwise agree on their column
        // list and keep a table still pointed at the previous endpoint.
        const signature = source + ':' + columns.join(',')
        if (signature === logCurrentSignature && oTableLogs !== null) {
            return
        }
        logCurrentSignature = signature

        if (oTableLogs !== null) {
            oTableLogs.destroy()
            oTableLogs = null
        }

        $('#table-logs tbody').empty()
        $('#table-logs thead tr').empty()
        columns.forEach((key) => {
            $('<th></th>').text(logColumnTitles[key] || key).appendTo('#table-logs thead tr')
        })

        oTableLogs = $('#table-logs').DataTable({
            'destroy': true,
            'paging': true,
            'sPaginationType': 'listbox',
            'lengthMenu': [10, 25, 50, 100],
            // The facet panel owns the search: a second box would filter a different set.
            'searching': false,
            'order': [[0, 'desc']],
            'info': true,
            // The built-in indicator replaces the pair of toasts every draw used to fire; with
            // filters that reload on each keystroke they were unreadable.
            'processing': true,
            'serverSide': true,
            'responsive': false,
            'scrollX': true,
            'autoWidth': false,
            // Knowledge base logs are misc rows owned by the knowledge base handler, which holds
            // their administrator gate; the other two families are SQL tables. Only the transport
            // differs, the filter payload is the same one.
            'ajax': source === 'kb'
                ? {
                    'url': 'sources/kb.queries.php',
                    'type': 'POST',
                    'data': function(params) {
                        params.type = 'datatables_logs'
                        params.key = logsSessionKey
                        params.filters = JSON.stringify(logFilters)
                        return params
                    }
                }
                : {
                    'url': logsDataUrl + '?action=logs',
                    'data': function(params) {
                        params.filters = JSON.stringify(logFilters)
                        return params
                    }
                },
            'columns': columns.map((key) => ({
                'data': key,
                'orderable': key !== 'actions',
                'className': (key === 'api' || key === 'personal' || key === 'actions') ? 'text-center' : '',
                'render': function(data, type, row) {
                    if (type !== 'display') {
                        return data
                    }
                    return logCellRenderers[key] ? logCellRenderers[key](row) : renderLogText(data)
                }
            })),
            'language': {
                'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $session->get('user-language'); ?>.txt'
            },
            'drawCallback': function() {
                refreshPurgePanel()
            }
        })
    }

    // Rebuild the payload, the chips and the table, then reload.
    var logsDebounce = null
    const runSearch = (immediate) => {
        const source = $('.logs-source:checked').val() || 'system'
        applySourceVisibility(source)
        logFilters = collectFilters()
        renderChips()
        refreshPurgePanel()

        clearTimeout(logsDebounce)
        logsDebounce = setTimeout(() => {
            buildLogsTable(source, logVisibleColumns(source, logFilters.types || []))
            oTableLogs.ajax.reload(null, true)
        }, immediate === true ? 0 : 300)
    }

    // Restore every criterion to the page defaults, so the compact reset button and "Clear all"
    // always behave identically.
    const resetSearch = () => {
        clearTimeout(logsDebounce)
        $('#logs-term').val('')
        $('#logs-source-system').prop('checked', true)
        $('.logs-facet').prop('checked', false)
        $('.logs-facet-date').val('')
        $('.logs-facet-single').val('')
        $('#logs-user').val(null).trigger('change.select2')
        $('#logs-folder').val(null).trigger('change.select2')
        runSearch(true)
    }

    $('#logs-term').on('keyup', () => runSearch())
    $(document).on('change', '.logs-source, .logs-facet, .logs-facet-date, .logs-facet-single', () => runSearch(true))
    $('#logs-reset, #logs-clear-all').on('click', resetSearch)

    // Remove a single filter by clicking its chip.
    $(document).on('click', '.logs-chip', function() {
        const facet = $(this).data('facet')
        const value = String($(this).data('value'))

        $('.logs-facet[data-facet="' + facet + '"]').filter(function() {
            return $(this).val() === value
        }).prop('checked', false)
        $('.logs-facet-date[data-facet="' + facet + '"]').val('')
        const single = $('.logs-facet-single[data-facet="' + facet + '"]')
        single.val('')
        if (single.hasClass('select2-hidden-accessible')) {
            single.val(null).trigger('change.select2')
        }

        runSearch(true)
    })

    // Show/hide the panel and give the results column the freed width back.
    $('#logs-toggle-filters').on('click', function() {
        const panel = $('#logs-filters-panel')
        const shown = panel.hasClass('hidden')
        panel.toggleClass('hidden', !shown)
        $(this).attr('aria-expanded', shown ? 'true' : 'false')
        $('#logs-results-column')
            .toggleClass('col-12', !shown)
            .toggleClass('col-md-9 col-xl-10', shown)
        if (oTableLogs !== null) {
            oTableLogs.columns.adjust()
        }
    })

    // Load the user and folder lists incrementally: rendering every account inline made the page
    // weigh proportionally to the number of accounts in the instance.
    const facetSelect2 = (selector, action, placeholder) => {
        $(selector).select2({
            width: '100%',
            theme: 'bootstrap4',
            placeholder: placeholder,
            allowClear: true,
            minimumInputLength: 0,
            ajax: {
                delay: 250,
                url: logsDataUrl + '?action=' + action,
                dataType: 'json',
                data: function(params) {
                    return { term: params.term || '', page: params.page || 1 }
                },
                processResults: function(data, params) {
                    params.page = params.page || 1
                    return {
                        results: data.results || [],
                        pagination: { more: !!(data.pagination && data.pagination.more) }
                    }
                }
            }
        })
    }
    facetSelect2('#logs-user', 'user_options', logMessages.facetUser)
    facetSelect2('#logs-folder', 'folder_options', logMessages.facetFolder)

    // ------------------------------------------------------------------ PURGE

    /**
     * State the exact scope the purge would delete, from the filters currently applied.
     *
     * The button stays unavailable while a facet the deletion cannot express is active: a purge
     * that silently ignored one would destroy more than the table announced.
     *
     * @return {void}
     */
    function refreshPurgePanel() {
        if ($('#logs-purge-footer').length === 0) {
            return
        }

        const blocking = []
        if ((logFilters.term || '').trim() !== '') blocking.push(logMessages.facetTerm)
        if (logFilters.folder_id) blocking.push(logMessages.facetFolder)
        if (logFilters.scope) blocking.push(logMessages.facetScope)

        const hasDates = !!logFilters.date_from && !!logFilters.date_to
        const count = oTableLogs !== null ? oTableLogs.page.info().recordsTotal : 0

        const parts = []
        $('#logs-chips .logs-chip').each(function() {
            parts.push($(this).text().replace(/\s*$/, ''))
        })
        $('#logs-purge-scope').text(
            logMessages.purgeScope
                .replace('#tp_count#', count)
                .replace('#tp_scope#', parts.length === 0 ? '-' : parts.join(' · '))
        )

        let message = ''
        if (blocking.length > 0) {
            message = logMessages.purgeBlocked.replace('#tp_facets#', blocking.join(', '))
        } else if (hasDates === false) {
            message = logMessages.purgeNeedsDates
        }

        $('#logs-purge-blocked').text(message).toggleClass('hidden', message === '')
        $('.group-confirm-purge').toggleClass('hidden', message !== '')
        if (message !== '') {
            $('#checkbox-purge-confirm').iCheck('uncheck')
        }
    }

    // iCheck for checkbox and radio inputs
    $('.card-footer input[type="checkbox"]').iCheck({
        checkboxClass: 'icheckbox_flat-blue'
    });

    $('#logs-purge-footer').on('ifChanged', '#checkbox-purge-confirm', function() {
        // Nothing to do beyond letting iCheck update the underlying input.
    });

    $('#button-perform-purge').click(function() {
        if ($('#checkbox-purge-confirm').prop('checked') !== true) {
            toastr.remove();
            toastr.warning(logMessages.confirmCheckbox, '', { timeOut: 5000, progressBar: true });
            return;
        }

        toastr.remove();
        toastr.info('<?php echo $lang->get('loading_data'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // The knowledge base stores its logs as misc rows, not in a log table, so it keeps its
        // own deletion route. Both routes read the same canonical payload.
        const target = logFilters.source === 'kb' ? 'sources/kb.queries.php' : 'sources/utilities.queries.php';
        const payload = { filters: logFilters };

        $.post(
            target, {
                type: 'purge_logs',
                data: prepareExchangedData(JSON.stringify(payload), 'encode', logsSessionKey),
                key: logsSessionKey
            },
            function(response) {
                let data;
                try {
                    data = prepareExchangedData(response, 'decode', logsSessionKey);
                } catch (error) {
                    data = null;
                }
                if (!data || typeof data !== 'object') {
                    data = { error: true, message: logMessages.serverError };
                }

                toastr.remove();
                if (data.error !== false) {
                    toastr.error(data.message, logMessages.caution, { timeOut: 5000, progressBar: true });
                    return;
                }

                $('#checkbox-purge-confirm').iCheck('uncheck');
                toastr.success(
                    (data.nb_deleted !== undefined ? data.nb_deleted + ' ' : '') + logMessages.purgeEntries,
                    '', { timeOut: 2500, progressBar: true }
                );
                if (oTableLogs !== null) {
                    oTableLogs.ajax.reload(null, false);
                }
            }
        );
    });

    /**
     * Escape a value before inserting it in an HTML text node.
     *
     * No entity decoding here: the value is rendered exactly as the server sent it. Used for the
     * raw lockout identifier, so what the administrator reads is the value the unlock action
     * targets.
     *
     * @param {*} value Value to escape.
     * @return {string}
     */
    function escapeLogValue(value) {
        return $('<div/>').text(value === null || value === undefined ? '' : value.toString()).html();
    }

    /**
     * Escape a value before inserting it in an HTML attribute.
     *
     * text().html() escapes '&', '<' and '>' but leaves quotes untouched, which would let a value
     * containing one break out of the attribute. Quotes are therefore escaped explicitly.
     *
     * @param {*} value Value to escape.
     * @return {string}
     */
    function escapeLogAttribute(value) {
        return escapeLogValue(value)
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /**
     * Format a lockout countdown in a compact, language-neutral form.
     *
     * @param {number} totalSeconds Number of seconds remaining.
     * @return {string}
     */
    function formatAuthenticationLockoutRemaining(totalSeconds) {
        if (totalSeconds <= 0) {
            return authenticationLockoutMessages.expired;
        }

        const days = Math.floor(totalSeconds / 86400);
        const hours = Math.floor((totalSeconds % 86400) / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;
        const parts = [];

        if (days > 0) {
            parts.push(days + 'd');
        }
        if (hours > 0 || days > 0) {
            parts.push(hours.toString().padStart(2, '0') + 'h');
        }
        parts.push(minutes.toString().padStart(2, '0') + 'm');
        parts.push(seconds.toString().padStart(2, '0') + 's');

        return parts.join(' ');
    }

    /**
     * Refresh every visible lockout countdown.
     *
     * @return {void}
     */
    function updateAuthenticationLockoutCountdowns() {
        const now = Math.floor(Date.now() / 1000);

        $('.authentication-lockout-remaining').each(function() {
            const unlockAt = parseInt($(this).attr('data-unlock-at'), 10);
            const remaining = Number.isNaN(unlockAt) ? 0 : Math.max(0, unlockAt - now);
            $(this).text(formatAuthenticationLockoutRemaining(remaining));
        });
    }

    /**
     * Start the countdown and auto-refresh timers, once and only for an administrator.
     *
     * @return {void}
     */
    function startAuthenticationLockoutTimers() {
        if (authenticationLockoutTimersStarted === true) {
            return;
        }
        authenticationLockoutTimersStarted = true;

        window.setInterval(updateAuthenticationLockoutCountdowns, 1000);
        window.setInterval(function() {
            if (
                oTableAuthenticationLockouts !== null
                && $('#authentication-lockouts').hasClass('active')
            ) {
                oTableAuthenticationLockouts.ajax.reload(null, false);
            }
        }, 30000);
    }

    /**
     * Initialize or refresh the active authentication lockouts table.
     *
     * @return {void}
     */
    function showAuthenticationLockouts() {
        if (authenticationLockoutAdmin !== true) {
            return;
        }

        if ($.fn.dataTable.isDataTable('#table-authentication-lockouts')) {
            oTableAuthenticationLockouts.ajax.reload(null, false);
            return;
        }

        oTableAuthenticationLockouts = $('#table-authentication-lockouts').DataTable({
            'paging': true,
            'sPaginationType': 'listbox',
            'lengthMenu': [10, 25, 50, 100],
            'searching': true,
            'order': [
                [6, 'asc']
            ],
            'info': true,
            'processing': true,
            'serverSide': true,
            'responsive': false,
            'stateSave': true,
            'autoWidth': false,
            'scrollX': true,
            'ajax': {
                url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/logs.datatables.php?action=authentication_lockouts'
            },
            'columns': [
                {
                    'data': 'source',
                    'render': function(data, type) {
                        if (type !== 'display') {
                            return data;
                        }
                        const label = data === 'remote_ip'
                            ? authenticationLockoutMessages.scopeIp
                            : authenticationLockoutMessages.scopeLogin;
                        const badge = data === 'remote_ip' ? 'badge-warning' : 'badge-info';
                        return '<span class="badge ' + badge + '">' + escapeLogValue(label) + '</span>';
                    }
                },
                {
                    'data': 'value',
                    'render': function(data, type) {
                        return type === 'display' ? escapeLogValue(data) : data;
                    }
                },
                {
                    'data': 'user_display',
                    'render': function(data, type) {
                        if (type !== 'display') {
                            return data;
                        }
                        return data === '' ? '&mdash;' : renderLogText(data);
                    }
                },
                {
                    'data': 'failure_count',
                    'className': 'text-center'
                },
                {
                    'data': 'first_failure',
                    'render': function(data, type) {
                        return type === 'display' ? escapeLogValue(data) : data;
                    }
                },
                {
                    'data': 'last_failure',
                    'render': function(data, type) {
                        return type === 'display' ? escapeLogValue(data) : data;
                    }
                },
                {
                    'data': 'unlock_at',
                    'render': function(data, type, row) {
                        if (type !== 'display') {
                            return row.unlock_at_timestamp;
                        }
                        return escapeLogValue(data)
                            + '<br><small class="text-muted">'
                            + escapeLogValue(authenticationLockoutMessages.remaining)
                            + ': <span class="authentication-lockout-remaining" data-unlock-at="'
                            + parseInt(row.unlock_at_timestamp, 10)
                            + '"></span></small>';
                    }
                },
                {
                    'data': null,
                    'orderable': false,
                    'searchable': false,
                    'className': 'text-nowrap text-center',
                    'render': function(data, type, row) {
                        if (type !== 'display') {
                            return '';
                        }
                        const source = row.source === 'remote_ip' ? 'remote_ip' : 'login';
                        const value = encodeURIComponent(row.value === null ? '' : row.value.toString());
                        return '<button type="button" class="btn btn-sm btn-outline-secondary authentication-lockout-view-failures mr-1"'
                            + ' data-value="' + value + '" title="'
                            + escapeLogAttribute(authenticationLockoutMessages.viewFailures) + '">'
                            + '<i class="fa-solid fa-magnifying-glass"></i></button>'
                            + '<button type="button" class="btn btn-sm btn-outline-danger authentication-lockout-remove"'
                            + ' data-source="' + source + '" data-value="' + value + '" title="'
                            + escapeLogAttribute(authenticationLockoutMessages.remove) + '">'
                            + '<i class="fa-solid fa-lock-open"></i></button>';
                    }
                }
            ],
            'language': {
                'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $session->get('user-language'); ?>.txt'
            },
            'drawCallback': function() {
                updateAuthenticationLockoutCountdowns();
            }
        });

        startAuthenticationLockoutTimers();
    }


    // The lockout row sends the administrator to the failed authentications it was built from:
    // the former dedicated tab is now the System source narrowed to that one type.
    $(document).on('click', '.authentication-lockout-view-failures', function(e) {
        e.preventDefault();

        let value = '';
        try {
            value = decodeURIComponent(($(this).attr('data-value') || '').toString());
        } catch (error) {
            toastr.error(authenticationLockoutMessages.invalidTarget, authenticationLockoutMessages.caution);
            return;
        }

        $('#logs-source-system').prop('checked', true);
        $('.logs-facet[data-facet="types"]').prop('checked', false);
        $('#logs-type-failed').prop('checked', true);
        $('#logs-term').val(value);
        $('a[href="#journals"]').tab('show');
        runSearch(true);
    });

    $(document).on('click', '.authentication-lockout-remove', function(e) {
        e.preventDefault();

        const source = ($(this).attr('data-source') || '').toString();
        let value = '';
        try {
            value = decodeURIComponent(($(this).attr('data-value') || '').toString());
        } catch (error) {
            toastr.error(authenticationLockoutMessages.invalidTarget, authenticationLockoutMessages.caution);
            return;
        }

        if ((source !== 'login' && source !== 'remote_ip') || value === '') {
            toastr.error(authenticationLockoutMessages.invalidTarget, authenticationLockoutMessages.caution);
            return;
        }

        const scopeLabel = source === 'remote_ip'
            ? authenticationLockoutMessages.scopeIp
            : authenticationLockoutMessages.scopeLogin;
        let modalBody = '<p>' + escapeLogValue(authenticationLockoutMessages.confirm) + '</p>'
            + '<p><strong>' + escapeLogValue(scopeLabel) + ':</strong> '
            + escapeLogValue(value) + '</p>'
            + '<div class="alert alert-warning mb-0"><i class="fa-solid fa-triangle-exclamation mr-2"></i>'
            + escapeLogValue(authenticationLockoutMessages.clientWarning);

        if (source === 'remote_ip') {
            modalBody += '<br><br>' + escapeLogValue(authenticationLockoutMessages.ipWarning);
        }
        modalBody += '</div>';

        showModalDialogBox(
            '#warningModal',
            '<i class="fa-solid fa-lock-open mr-2"></i>' + escapeLogValue(authenticationLockoutMessages.remove),
            modalBody,
            escapeLogValue(authenticationLockoutMessages.remove),
            escapeLogValue(authenticationLockoutMessages.close),
            false,
            true,
            true
        );

        $(document)
            .off('click.tpAuthenticationLockoutConfirm', '#warningModalButtonAction')
            .one('click.tpAuthenticationLockoutConfirm', '#warningModalButtonAction', function(event) {
                event.preventDefault();
                const $actionButton = $(this);
                $actionButton.prop('disabled', true);

                $.post(
                    'sources/admin.queries.php',
                    {
                        type: 'authentication_lockout_remove',
                        data: prepareExchangedData(
                            JSON.stringify({
                                source: source,
                                value: value
                            }),
                            'encode',
                            '<?php echo $session->get('key'); ?>'
                        ),
                        key: '<?php echo $session->get('key'); ?>'
                    },
                    function(response) {
                        let data;
                        try {
                            data = prepareExchangedData(response, 'decode', '<?php echo $session->get('key'); ?>');
                        } catch (error) {
                            data = null;
                        }

                        // A decode failure may return a falsy value instead of throwing, so the
                        // success path requires an explicit 'error: false' from the server.
                        if (!data || typeof data !== 'object') {
                            data = {
                                error: true,
                                message: authenticationLockoutMessages.serverError
                            };
                        }

                        if (data.error !== false) {
                            $actionButton.prop('disabled', false);
                            toastr.error(data.message || authenticationLockoutMessages.serverError, authenticationLockoutMessages.caution, {
                                timeOut: 5000,
                                progressBar: true
                            });
                            return;
                        }

                        $('#warningModal').modal('hide');
                        toastr.success(data.message, '', {
                            timeOut: 2500,
                            progressBar: true
                        });

                        if (oTableAuthenticationLockouts !== null) {
                            oTableAuthenticationLockouts.ajax.reload(null, false);
                        }
                        if (oTableLogs !== null) {
                            oTableLogs.ajax.reload(null, false);
                        }
                    }
                ).fail(function() {
                    $actionButton.prop('disabled', false);
                    toastr.error(
                        authenticationLockoutMessages.serverError,
                        authenticationLockoutMessages.caution,
                        {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                });
            });

        $('#warningModal')
            .off('hidden.bs.modal.tpAuthenticationLockout')
            .one('hidden.bs.modal.tpAuthenticationLockout', function() {
                $(document).off('click.tpAuthenticationLockoutConfirm', '#warningModalButtonAction');
                $('#warningModalButtonAction').prop('disabled', false);
            });
    });

    $(document).on('click', '.failed-auth-add-blacklist', function(e) {
        e.preventDefault();

        const $button = $(this);
        const ip = ($button.data('ip') || '').toString().trim();
        if (ip === '') {
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('network_security_invalid_rule'); ?>',
                '<?php echo $lang->get('caution'); ?>', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return;
        }

        $button.prop('disabled', true);
        toastr.remove();
        toastr.info('<?php echo $lang->get('loading_data'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        $.post(
            'sources/admin.queries.php',
            {
                type: 'network_blacklist_ip',
                data: prepareExchangedData(JSON.stringify({ ip: ip }), 'encode', '<?php echo $session->get('key'); ?>'),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(response) {
                let data;
                try {
                    data = prepareExchangedData(response, 'decode', '<?php echo $session->get('key'); ?>');
                } catch (error) {
                    data = {
                        error: true,
                        message: '<?php echo $lang->get('server_answer_error'); ?>'
                    };
                }

                $button.prop('disabled', false);
                toastr.remove();

                if (data.error !== false) {
                    toastr.error(
                        data.message,
                        '<?php echo $lang->get('caution'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                    return;
                }

                toastr.success(
                    '<?php echo $lang->get('network_security_add_ip_to_blacklist_success'); ?>',
                    '', {
                        timeOut: 2000,
                        progressBar: true
                    }
                );

                if (oTableLogs !== null) {
                    oTableLogs.ajax.reload(null, false);
                }
            }
        );
    });


    $(document).ready(function() {
        // First paint: the panel is collapsed, so the defaults have to be applied explicitly.
        runSearch(true);

        if (authenticationLockoutAdmin === true) {
            $('#authentication-lockouts-tab').on('shown.bs.tab', function() {
                showAuthenticationLockouts();
            });
        }

        // Honour a #hash pointing at one of the two remaining tabs.
        if (window.location.hash) {
            const tabTrigger = $('#logs-main-tabs a[href="' + window.location.hash + '"]');
            if (tabTrigger.length) {
                tabTrigger.tab('show');
            }
        }

        $('#logs-main-tabs a[data-toggle="tab"]').on('shown.bs.tab', function() {
            if (oTableLogs !== null) {
                oTableLogs.columns.adjust();
            }
        });
    });

    //]]>
</script>
