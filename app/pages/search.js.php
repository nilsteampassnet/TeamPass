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
  * @file      search.js.php
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('search') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}
$var = [];
$var['hidden_asterisk'] = '<i class="fas fa-asterisk mr-2"></i><i class="fas fa-asterisk mr-2"></i><i class="fas fa-asterisk mr-2"></i><i class="fas fa-asterisk mr-2"></i><i class="fas fa-asterisk"></i>';

?>


<script type="text/javascript">
    // Current facet selection. Sent as one JSON blob so the server can
    // validate it against its own allow-lists in a single place.
    var searchFilters = {}
    const searchFilterStateVersion = 2

    // Out-of-order responses need no guard here: DataTables discards any
    // response whose "draw" counter is older than the current one.

    // Collect the facet panel into the canonical payload.
    const collectFilters = () => {
      const filters = {
        term: $('#search-term').val() || '',
        fields: $('.search-field-cb:checked').map((i, el) => $(el).val()).get()
      }

      // Multi-value checkbox facets (classification, health, rotation status).
      $('.search-facet:checked').each(function() {
        const facet = $(this).data('facet')
        if (!filters[facet]) filters[facet] = []
        filters[facet].push($(this).val())
      })

      $('.search-facet-bool:checked').each(function() {
        filters[$(this).data('facet')] = true
      })

      $('.search-facet-text').each(function() {
        const value = ($(this).val() || '').trim()
        if (value !== '') filters[$(this).data('facet')] = value
      })

      // Comma-separated free input (attachment extensions).
      $('.search-facet-csv').each(function() {
        const value = ($(this).val() || '').trim()
        if (value !== '') {
          filters[$(this).data('facet')] = value.split(',')
            .map((v) => v.trim().replace(/^\./, ''))
            .filter((v) => v !== '')
        }
      })

      // Dates arrive as yyyy-mm-dd and go out as unix timestamps.
      $('.search-facet-date').each(function() {
        const value = $(this).val()
        if (value) {
          const stamp = Math.floor(new Date(value).getTime() / 1000)
          if (!isNaN(stamp)) filters[$(this).data('facet')] = stamp
        }
      })

      $('.search-facet-single').each(function() {
        const value = $(this).val()
        if (value) filters[$(this).data('facet')] = $(this).data('facet') === 'custom_field_id' ? [value] : value
      })

      $('.search-facet-select').each(function() {
        const value = $(this).val()
        if (value && value.length > 0) filters[$(this).data('facet')] = value
      })

      return filters
    }

    // Facet values whose <option> elements are fetched asynchronously; they
    // can only be restored once the list has arrived.
    var pendingSelectRestore = null

    // Push a saved payload back into the panel controls.
    const restoreFilters = (filters) => {
      if (!filters) return

      if (filters.term) $('#search-term').val(filters.term)
      if (Array.isArray(filters.fields) && filters.fields.length > 0) {
        $('.search-field-cb').each(function() {
          $(this).prop('checked', filters.fields.indexOf($(this).val()) !== -1)
        })
      }

      $('.search-facet').each(function() {
        const values = filters[$(this).data('facet')]
        $(this).prop('checked', Array.isArray(values) && values.indexOf($(this).val()) !== -1)
      })
      $('.search-facet-bool').each(function() {
        $(this).prop('checked', filters[$(this).data('facet')] === true)
      })
      $('.search-facet-text').each(function() {
        $(this).val(filters[$(this).data('facet')] || '')
      })
      $('.search-facet-csv').each(function() {
        const values = filters[$(this).data('facet')]
        $(this).val(Array.isArray(values) ? values.join(', ') : '')
      })
      $('.search-facet-date').each(function() {
        const stamp = filters[$(this).data('facet')]
        $(this).val(stamp ? new Date(stamp * 1000).toISOString().slice(0, 10) : '')
      })

      // Deferred: the tag and custom-field lists are not loaded yet.
      pendingSelectRestore = filters

      // This runs while DataTables is still being constructed, i.e. before the
      // first ajax call, so the restored facets must already be in the payload.
      // Nothing defined further down may be called from here.
      searchFilters = collectFilters()
    }

    const folderResultLabel = <?php echo json_encode(
        $lang->get('folder'),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ); ?>

    // Folder results stay outside the item DataTable: they navigate directly
    // to their tree node and can never inherit item actions or mass selection.
    const renderFolderResults = (folders, truncated) => {
      const section = $('#search-folder-results')
      const list = $('#search-folder-list').empty()

      if (Array.isArray(folders) === false || folders.length === 0) {
        $('#search-folder-count').text('0')
        $('#search-folder-more').addClass('hidden')
        section.addClass('hidden')
        return
      }

      let rendered = 0
      folders.forEach((folder) => {
        const id = Number.parseInt(folder.id, 10)
        const title = String(folder.title || '').trim()
        if (Number.isInteger(id) === false || id <= 0 || title === '') {
          return
        }

        const link = $('<a></a>')
          .addClass('list-group-item list-group-item-action search-folder-result')
          .attr('href', 'index.php?page=items&group=' + encodeURIComponent(id))
        const icon = $('<span></span>')
          .addClass('search-folder-icon')
          .attr('aria-hidden', 'true')
        $('<i></i>').addClass('fa-solid fa-folder-open').appendTo(icon)

        const content = $('<span></span>').addClass('search-folder-content')
        $('<span></span>').addClass('search-folder-title').text(title).appendTo(content)
        const path = String(folder.path || '').trim()
        if (path !== '') {
          $('<small></small>')
            .addClass('search-folder-path text-muted')
            .text(path)
            .appendTo(content)
        }

        const badge = $('<span></span>')
          .addClass('badge badge-warning ml-auto')
          .text(folderResultLabel)
        link.append(icon, content, badge).appendTo(list)
        rendered++
      })

      if (rendered === 0) {
        section.addClass('hidden')
        return
      }

      $('#search-folder-count').text(String(rendered) + (truncated === true ? '+' : ''))
      $('#search-folder-more').toggleClass('hidden', truncated !== true)
      section.removeClass('hidden')
    }

    //Launch the datatables pluggin
    // Mass operation drives the selection column: without it the column would
    // sit empty on every row.
    const massOperationEnabled = <?php echo (int) ($SETTINGS['enable_massive_move_delete'] ?? 0) === 1 ? 'true' : 'false'; ?>;

    var oTable = $("#search-results-items").DataTable({
        "paging": true,
        "lengthMenu": [
            [10, 25, 50, 100],
            [10, 25, 50, 100]
        ],
        "pagingType": "full_numbers",
        // The page has its own search bar and facet panel.
        "searching": false,
        "info": true,
        "order": [
            [1, "asc"]
        ],
        "processing": true,
        "serverSide": true,
        "responsive": true,
        "select": false,
        "stateSave": true,
        // Ride on the DataTables state rather than keeping a parallel store,
        // so the facets survive a reload exactly like the paging does.
        "stateSaveParams": function (settings, data) {
            data.tpFilters = collectFilters()
            data.tpFiltersVersion = searchFilterStateVersion
        },
        "stateLoadParams": function (settings, data) {
            // Saved states predate folder search. Add the new default once;
            // version 2 states preserve an explicit user opt-out.
            if (data.tpFiltersVersion !== searchFilterStateVersion
                && data.tpFilters
                && Array.isArray(data.tpFilters.fields)
                && data.tpFilters.fields.indexOf('folder') === -1
            ) {
                data.tpFilters.fields.push('folder')
            }
            restoreFilters(data.tpFilters)
            // Column visibility is part of the saved state: a state written
            // before an admin turned mass operation off would bring the empty
            // selection column back.
            if (data.columns !== undefined && data.columns[0] !== undefined) {
                data.columns[0].visible = massOperationEnabled
            }
        },
        "autoWidth": true,
        "ajax": {
            url: "<?php echo $SETTINGS['cpassman_url']; ?>/sources/search.queries.php",
            // POST keeps search terms out of web server access logs and Referer.
            type: 'POST',
            "data": function ( d ) {
                d.type = 'search'
                d.key = '<?php echo $session->get('key'); ?>'
                d.filters = JSON.stringify(searchFilters)
                return d
            },
            "dataSrc": function ( json ) {
                // Cells are already HTML-escaped server-side and sent as plain
                // JSON: no base64 round-trip to decode any more.
                renderFolderResults(json.folders, json.folders_truncated === true)
                return json.data
            }
        },
        "language": {
            "url": "<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $session->get('user-language'); ?>.txt"
        },
        // Column 0 carries the mass-operation checkbox, the last one the row
        // actions; neither is sortable. Indexes 1..6 must stay put: the server
        // maps them to SQL columns in searchSortColumnMap() - hence hiding the
        // selection column rather than dropping it when the feature is off,
        // which would shift every index by one.
        // 'all' keeps both out of the Responsive collapse: one is the only way
        // to select a row, the other holds no flow content to measure.
        "columns": [{
                class: "search-col-select all",
                orderable: false,
                visible: massOperationEnabled,
                defaultContent: ""
            },
            // The six content columns now claim the whole width: with the two
            // technical columns reduced to nothing, leftover space would
            // otherwise be handed back to them.
            {
                "width": "15%",
                responsivePriority: 1
            },
            {
                "width": "10%"
            },
            {
                "width": "35%"
            },
            {
                "width": "10%"
            },
            {
                "width": "15%"
            },
            {
                "width": "15%"
            },
            {
                "width": "1px",
                class: "search-col-actions all",
                orderable: false,
                defaultContent: ""
            }
        ],
        "drawCallback": function() {
            // Tooltips
            $('.infotip').tooltip();

            //iCheck for checkbox and radio inputs
            $('#search-results-items input[type="checkbox"]').iCheck({
                checkboxClass: 'icheckbox_flat-blue'
            });
        }
    });

    // -----------------------------------------------------------------
    // Facet panel
    // -----------------------------------------------------------------

    // Human-readable label for a chip, read from the facet control itself so
    // the wording always follows the translated panel.
    const facetChipLabel = (facet, value) => {
      const byValue = $('.search-facet[data-facet="' + facet + '"][value="' + value + '"]')
      if (byValue.length > 0) {
        return byValue.next('label').text().trim()
      }
      const asBool = $('.search-facet-bool[data-facet="' + facet + '"]')
      if (asBool.length > 0) {
        return asBool.next('label').text().trim()
      }
      const asSelect = $('.search-facet-single[data-facet="' + facet + '"], .search-facet-select[data-facet="' + facet + '"]')
      if (asSelect.length > 0) {
        const opt = asSelect.find('option[value="' + value + '"]')
        if (opt.length > 0) return opt.text().trim()
      }
      return String(value)
    }

    // Repaint the chips row from the current selection.
    const renderChips = () => {
      const chips = []
      Object.keys(searchFilters).forEach((facet) => {
        if (facet === 'term' || facet === 'fields') return
        const value = searchFilters[facet]
        if (Array.isArray(value)) {
          value.forEach((v) => chips.push({facet: facet, value: v, label: facetChipLabel(facet, v)}))
        } else if (value === true) {
          chips.push({facet: facet, value: true, label: facetChipLabel(facet, true)})
        } else {
          chips.push({facet: facet, value: value, label: facetChipLabel(facet, value)})
        }
      })

      const container = $('#search-chips').empty()
      chips.forEach((chip) => {
        // .text() on the label keeps any translated string inert.
        $('<span class="badge badge-primary mr-1 mb-1 search-chip" style="cursor:pointer;"></span>')
          .attr('data-facet', chip.facet)
          .attr('data-value', chip.value)
          .text(chip.label)
          .append(' <i class="fas fa-times"></i>')
          .appendTo(container)
      })

      $('#search-chips-row').toggleClass('hidden', chips.length === 0)
      $('#search-filters-count')
        .text(chips.length)
        .toggleClass('hidden', chips.length === 0)
    }

    // Rebuild the payload and refresh the chips row and the empty hint.
    const refreshPanelState = () => {
      searchFilters = collectFilters()
      renderChips()

      // Mirrors the server rule: a bare term under 2 characters with no facet
      // is not searched, so say so instead of showing an empty table.
      const hasTerm = (searchFilters.term || '').trim().length > 1
      const hasFacet = Object.keys(searchFilters).some((k) => k !== 'term' && k !== 'fields')
      $('#search-empty-hint').toggleClass('hidden', hasTerm || hasFacet)
    }

    // Refresh the panel, then reload the table.
    var searchDebounce = null
    const runSearch = (immediate) => {
      refreshPanelState()

      clearTimeout(searchDebounce)
      searchDebounce = setTimeout(() => {
        oTable.ajax.reload(null, true)
      }, immediate === true ? 0 : 300)
    }

    $('#search-term').on('keyup', () => runSearch())
    $(document).on('change', '.search-field-cb, .search-facet, .search-facet-bool, .search-facet-single, .search-facet-select', () => runSearch(true))
    $(document).on('change', '.search-facet-date', () => runSearch(true))
    $(document).on('keyup', '.search-facet-text, .search-facet-csv', () => runSearch())

    // Remove a single filter by clicking its chip.
    $(document).on('click', '.search-chip', function() {
      const facet = $(this).data('facet')
      const value = String($(this).data('value'))

      $('.search-facet[data-facet="' + facet + '"]').filter(function() {
        return $(this).val() === value
      }).prop('checked', false)
      $('.search-facet-bool[data-facet="' + facet + '"]').prop('checked', false)
      $('.search-facet-text[data-facet="' + facet + '"], .search-facet-csv[data-facet="' + facet + '"]').val('')
      $('.search-facet-date[data-facet="' + facet + '"]').val('')
      $('.search-facet-single[data-facet="' + facet + '"]').val('')
      $('.search-facet-select[data-facet="' + facet + '"]').val([])

      runSearch(true)
    })

    $('#search-clear-all').on('click', () => {
      $('.search-facet, .search-facet-bool').prop('checked', false)
      $('.search-facet-text, .search-facet-csv, .search-facet-date, .search-facet-single').val('')
      $('.search-facet-select').val([])
      runSearch(true)
    })

    // Show/hide the panel and give the results column the freed width back.
    $('#search-toggle-filters').on('click', function() {
      const panel = $('#search-filters-panel')
      const shown = panel.hasClass('hidden')
      panel.toggleClass('hidden', !shown)
      $(this).attr('aria-expanded', shown ? 'true' : 'false')
      $('#search-results-column')
        .toggleClass('col-12', !shown)
        .toggleClass('col-md-9 col-xl-10', shown)
      oTable.columns.adjust()
    })

    // Populate the dropdowns that depend on what this user may see. The lists
    // are scoped server-side to the same folders as the search itself.
    $.post(
      'sources/search.queries.php', {
        type: 'filter_options',
        key: '<?php echo $session->get('key'); ?>'
      },
      function(options) {
        if (options.tags) {
          options.tags.forEach((tag) => {
            $('<option></option>').attr('value', tag).text(tag).appendTo('#search-tags')
          })
        }
        if (options.custom_fields) {
          options.custom_fields.forEach((field) => {
            $('<option></option>').attr('value', field.id).text(field.title).appendTo('#search-custom-field')
          })
        }

        // Now that the options exist, apply any selection restored from state.
        if (pendingSelectRestore !== null) {
          if (Array.isArray(pendingSelectRestore.tags)) {
            $('#search-tags').val(pendingSelectRestore.tags)
          }
          if (Array.isArray(pendingSelectRestore.custom_field_id)) {
            $('#search-custom-field').val(pendingSelectRestore.custom_field_id[0])
          }
          if (pendingSelectRestore.scope_perso) {
            $('#search-scope-perso').val(pendingSelectRestore.scope_perso)
          }
          const needsReload = Array.isArray(pendingSelectRestore.tags)
            || Array.isArray(pendingSelectRestore.custom_field_id)
            || !!pendingSelectRestore.scope_perso
          pendingSelectRestore = null
          if (needsReload) {
            runSearch(true)
            return
          }
        }
        // Reflect the facets restored from state in the chips row and hint.
        refreshPanelState()
      },
      'json'
    )

    // -----------------------------------------------------------------
    // Result rows: quick actions + detail modal
    //
    // The detail used to be injected as a DataTables child row. That collided
    // with the Responsive extension (it drives row.child() too) and pushed
    // every row below out of place. It now opens in a dedicated modal, and the
    // three gestures a user actually performs after a search - copy the login,
    // copy the password, jump to the item - are one click away on the row.
    // -----------------------------------------------------------------

    const MASKED_PASSWORD = '<?php echo $var['hidden_asterisk']; ?>';

    // Copy a value, warn the user, and schedule the clipboard purge when the
    // instance is configured for it. Same behaviour as the item card.
    const copySearchValue = async (value, isSecret) => {
        try {
            if (await tpClipboardCopy(value) === false) {
                throw new Error('Clipboard unavailable');
            }
        } catch (error) {
            toastr.remove();
            toastr.error('<?php echo $lang->get('clipboard_error'); ?>', '', {
                timeOut: 3000,
                positionClass: 'toast-bottom-right',
                progressBar: true
            });
            return;
        }

        const clipboardDuration = parseInt(store.get('teampassSettings').clipboard_life_duration) || 0;
        toastr.remove();

        if (isSecret !== true || clipboardDuration === 0) {
            toastr.info('<?php echo $lang->get('copy_to_clipboard'); ?>', '', {
                timeOut: 2000,
                positionClass: 'toast-bottom-right',
                progressBar: true
            });
            return;
        }

        toastr.warning('<?php echo $lang->get('clipboard_will_be_cleared'); ?>', '', {
            timeOut: clipboardDuration * 1000,
            progressBar: true
        });

        const cleaner = new ClipboardCleaner(clipboardDuration);
        cleaner.scheduleClearing(
            () => {
                const clipboardStatus = JSON.parse(localStorage.getItem('clipboardStatus'));
                if (clipboardStatus.status === 'unsafe') {
                    return;
                }
                toastr.success('<?php echo $lang->get('clipboard_cleared'); ?>', '', {
                    timeOut: 2000,
                    positionClass: 'toast-bottom-right'
                });
            },
            (error) => console.error('Error clearing clipboard:', error)
        );
    };

    // Metadata of the row the modal is currently showing. Read once on open so
    // the modal keeps working even if the table is redrawn behind it.
    let currentItem = null;

    // Text of a cell, read from the DataTables row rather than from the DOM:
    // Responsive hides cells on narrow screens, and a hidden cell has no <td>.
    const cellText = (rowData, index) => {
        if (Array.isArray(rowData) === false || rowData[index] === undefined) {
            return '';
        }
        return $('<div></div>').html(rowData[index]).text().trim();
    };

    // Read the payload the server attached to the row actions. The holder is
    // parsed out of the row data for the same reason.
    const readRowMeta = (tr) => {
        const rowData = oTable.row(tr).data();
        if (Array.isArray(rowData) === false) {
            return null;
        }
        const holder = $(rowData[7]);
        if (holder.hasClass('search-row-actions') === false) {
            return null;
        }

        return {
            id: holder.data('id'),
            folderId: holder.data('tree-id'),
            perso: holder.data('perso'),
            expired: holder.data('expired'),
            restrictedTo: holder.data('restricted-to'),
            rights: holder.data('rights'),
            // Already in the row: no reason to ask the server for them again.
            // The label cell also holds the classification badge, so read the
            // button alone rather than the whole cell.
            label: $('<div></div>').html(rowData[1]).find('.search-label-btn').text().trim(),
            badge: $('<div></div>').html(rowData[1]).find('.badge').prop('outerHTML') || '',
            login: cellText(rowData, 2),
            folderPath: cellText(rowData, 6)
        };
    };

    const goToItem = (meta) => {
        document.location.href = 'index.php?page=items&group=' + encodeURIComponent(meta.folderId) +
            '&id=' + encodeURIComponent(meta.id);
    };

    // Reset the modal to its loading state before each open, so a slow answer
    // never shows the previous item's data.
    const resetItemModal = (meta) => {
        // The label is painted from the row so the header is right immediately,
        // then confirmed by the server answer.
        $('#search-item-modal-label').text(meta.label);
        // Server-built markup (the classification badge), carried over as is.
        $('#search-item-badge').html(meta.badge);
        $('#search-item-path').text(meta.folderPath);
        $('#search-item-loading').removeClass('hidden');
        $('#search-item-content').addClass('hidden');
        $('#search-item-message').addClass('hidden').text('');
        $('#search-item-open').prop('disabled', false);
        $('#search-item-login-row, #search-item-url-row, #search-item-tags-row, ' +
          '#search-item-description-row').addClass('hidden');
        $('#search-item-pwd-holder').empty();
    };

    const fillItemModal = (data) => {
        $('#search-item-modal-label').text(data.label);
        // fa_icon is item data: only ever let it be a list of class names.
        const icon = String(data.fa_icon || '').trim();
        $('#search-item-glyph').attr(
            'class',
            /^[a-z0-9 -]+$/i.test(icon) === true && icon !== '' ? icon : 'fa-solid fa-key'
        );

        if (data.login) {
            $('#search-item-login').text(data.login);
            $('#search-item-login-row').removeClass('hidden');
        }

        // The reveal button and the long-press both key off this per-item id.
        $('#search-item-pwd-holder').html(
            '<span id="pwd-show_' + parseInt(data.id, 10) + '" class="unhide_masked_data pointer">' +
            MASKED_PASSWORD + '</span>'
        );
        // The shared .btn-show-pwd handler reads this to find the span above.
        $('#search-item-show-pwd').data('id', data.id).attr('data-id', data.id);

        if (data.url) {
            $('#search-item-url').text(data.url);
            // Only http(s) reaches the anchor: a javascript: URL stored on the
            // item would otherwise become clickable here.
            const safeUrl = /^https?:\/\//i.test(data.url) ? data.url : '';
            $('#search-item-open-url')
                .attr('href', safeUrl === '' ? '#' : safeUrl)
                .toggleClass('disabled', safeUrl === '');
            $('#search-item-url-row').removeClass('hidden');
        }

        // The server returns an array of tags; older payloads sent a single
        // space separated string.
        const tags = Array.isArray(data.tags)
            ? data.tags
            : String(data.tags || '').split(' ');
        const shownTags = tags.filter((tag) => String(tag).trim() !== '');
        if (shownTags.length > 0) {
            const container = $('#search-item-tags').empty();
            shownTags.forEach((tag) => {
                // .text() keeps a tag containing markup inert.
                $('<span class="badge badge-secondary mr-1"></span>').text(tag).appendTo(container);
            });
            $('#search-item-tags-row').removeClass('hidden');
        }

        const descriptionHtml = DOMPurify.sanitize(
            htmlDecode(data.description || ''),
            {USE_PROFILES: {html: true}}
        );
        if (descriptionHtml !== '') {
            $('#search-item-description').html(descriptionHtml);
            $('#search-item-description-row').removeClass('hidden');
        }

        $('#search-item-loading').addClass('hidden');
        $('#search-item-content').removeClass('hidden');
        $('.infotip').tooltip();
    };

    const showItemMessage = (message) => {
        $('#search-item-loading').addClass('hidden');
        $('#search-item-content').addClass('hidden');
        $('#search-item-message').text(message).removeClass('hidden');
    };

    const openItemModal = (meta) => {
        currentItem = meta;
        resetItemModal(meta);
        $('#search-item-modal').modal('show');

        const payload = {
            'id': meta.id,
            'folder_id': meta.folderId,
            'salt_key_required': meta.perso,
            'salt_key_set': store.get('teampassUser').pskSetForSession,
            'expired_item': meta.expired,
            'restricted': meta.restrictedTo,
            'page': 'find',
            'rights': meta.rights,
        };

        $.post(
            'sources/items.queries.php', {
                type: 'show_details_item',
                data: prepareExchangedData(JSON.stringify(payload), 'encode', '<?php echo $session->get('key'); ?>'),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');

                if (currentItem === null) {
                    return;
                }
                // A row opened meanwhile: this answer is no longer the one shown.
                // Refusal payloads carry no id, so they can only be correlated by
                // the fact that a request is still pending.
                const answeredId = parseInt(data.id, 10);
                if (isNaN(answeredId) === false && parseInt(currentItem.id, 10) !== answeredId) {
                    return;
                }

                if (data.error === true) {
                    showItemMessage(data.message);
                    return;
                }
                // Order matters: show_details === 0 means the caller may not see
                // the item at all (an administrator, typically). The former code
                // tested it as a string, which never matched, so those users got
                // the expiry message instead.
                if (parseInt(data.show_details, 10) === 0) {
                    showItemMessage('<?php echo $lang->get('not_allowed_to_see_pw'); ?>');
                    return;
                }
                if (data.show_detail_option !== 0) {
                    showItemMessage('<?php echo $lang->get('not_allowed_to_see_pw_is_expired'); ?>');
                    return;
                }

                fillItemModal(data);
            }
        ).fail(function() {
            if (currentItem !== null) {
                showItemMessage('<?php echo $lang->get('server_answer_error'); ?>');
            }
        });
    };

    // Row actions. Stop the propagation so acting on a row never also opens the
    // modal behind it.
    $('#search-results-items tbody').on('click', '.search-row-action', async function(event) {
        event.preventDefault();
        event.stopPropagation();

        const meta = readRowMeta($(this).closest('tr'));
        if (meta === null) {
            return;
        }
        const action = $(this).data('action');

        if (action === 'open') {
            goToItem(meta);
            return;
        }

        if (action === 'login') {
            // The login is already in the row: no round-trip, no audit entry -
            // reading a login is not a password access.
            copySearchValue(meta.login, false);
            return;
        }

        if (action === 'password') {
            const icon = $(this).find('i');
            const original = icon.attr('class');
            icon.attr('class', 'fa-solid fa-circle-notch fa-spin');
            // Access checked server-side and logged as at_password_copied.
            const password = await getItemPassword('at_password_copied', 'item_id', meta.id);
            icon.attr('class', original);
            if (password) {
                copySearchValue(password, true);
            }
        }
    });

    // The row itself opens the detail. The mass-operation checkbox is the one
    // control inside a row that must not.
    $('#search-results-items tbody').on('click', 'tr', function(event) {
        // The selection cell belongs to the mass operation, not to the detail.
        // iCheck replaces the checkbox with its own markup, so match the cell.
        if ($(event.target).closest('.search-col-select, .search-row-action').length > 0) {
            return;
        }
        const meta = readRowMeta(this);
        if (meta === null) {
            return;
        }
        openItemModal(meta);
    });

    // Modal actions, bound once instead of on every open.
    $('#search-item-open').on('click', function() {
        if (currentItem !== null) {
            goToItem(currentItem);
        }
    });

    $('#search-item-copy-login').on('click', function() {
        copySearchValue($('#search-item-login').text(), false);
    });

    $('#search-item-copy-url').on('click', function() {
        copySearchValue($('#search-item-url').text(), false);
    });

    $('#search-item-copy-pwd').on('click', async function() {
        if (currentItem === null) {
            return;
        }
        const icon = $(this).find('i');
        const original = icon.attr('class');
        icon.attr('class', 'fa-solid fa-circle-notch fa-spin');
        const password = await getItemPassword('at_password_copied', 'item_id', currentItem.id);
        icon.attr('class', original);
        if (password) {
            copySearchValue(password, true);
        }
    });

    // Drop the reference so a late answer for this item is discarded.
    $('#search-item-modal').on('hidden.bs.modal', function() {
        currentItem = null;
    });

    // show password during longpress
    let mouseStillDown = false;
    // Bound on the document: the masked password moved from a table child row
    // to the detail modal, which is not inside #search-results-items.
    $(document)
        .on('mousedown', '.unhide_masked_data', function(event) {
            mouseStillDown = true;

            showPwdContinuous($(this).attr('id'));
        })
        .on('mouseup', '.unhide_masked_data', function(event) {
            mouseStillDown = false;
            showPwdContinuous($(this).attr('id'));
        })
        .on('mouseleave', '.unhide_masked_data', function(event) {
            mouseStillDown = false;
            showPwdContinuous($(this).attr('id'));
        });

    const showPwdContinuous = function showPwdContinuous(elem_id) {
        const itemId = elem_id.split('_')[1];
        if (mouseStillDown === true 
            && !$('#pwd-show_' + itemId).hasClass('pwd-shown')
        ) {
            getItemPassword(
                'at_password_shown',
                'item_id',
                itemId
            ).then(item_pwd => {
                if (item_pwd) {                    
                    $('#pwd-show_' + itemId).text(item_pwd);
                    $('#pwd-show_' + itemId).addClass('pwd-shown');

                    // Auto hide password
                    setTimeout('showPwdContinuous("pwd-show_' + itemId + '")', 50);
                }
            });
        } else if(mouseStillDown !== true) {
            $('#pwd-show_' + itemId)
                .html('<?php echo $var['hidden_asterisk']; ?>')
                .removeClass('pwd-shown');
        }
    };

    // Manage the password show button
    // including autohide after a couple of seconds
    $(document).on('click', '.btn-show-pwd', function() {
        const itemId = $(this).data('id');
        // Show the password if it is not already shown
        if ($(this).hasClass('pwd-shown') === false) {
            $(this).addClass('pwd-shown');  // Set the class

            getItemPassword(
                'at_password_shown',
                'item_id',
                itemId
            ).then(item_pwd => {
                $(this).removeClass('pwd-shown');   // Reset the class
                // Display the password if it exists
                if (item_pwd) {
                    $('.pwd-show-spinner')
                        .removeClass('fa-regular fa-eye')
                        .addClass('fa-solid fa-eye fa-beat-fade text-warning');

                    // display raw password
                    $('#pwd-show_' + itemId)
                        .text(item_pwd)
                        .addClass('pointer_none');

                    // Autohide
                    setTimeout(() => {
                        $('#pwd-show_' + itemId)
                            .html('<?php echo $var['hidden_asterisk']; ?>')
                            .removeClass('pointer_none');
                        $('.pwd-show-spinner')
                            .removeClass('fa-solid fa-eye fa-beat-fade text-warning')
                            .addClass('fa-regular fa-eye');
                    }, <?php echo isset($SETTINGS['password_overview_delay']) && (int) $SETTINGS['password_overview_delay'] > 0 ? $SETTINGS['password_overview_delay'] * 1000 : 4000; ?>);
                }
            });
        } else {
            $('#pwd-show_' + itemId).html('<?php echo $var['hidden_asterisk']; ?>');
        }
    });


    var selectedItems = '',
        selectedAction = '',
        listOfFolders = '';
    $("#search-results-items tbody").on('ifToggled', '.mass_op_cb', function() {
        // Check if at least one CB is checked
        if ($("#search-results-items input[type=checkbox]:checked").length > 0) {
            // Show selection menu
            if ($('#search-select').hasClass('menuset') === false) {
                $('#search-select')
                    .addClass('menuset')
                    .html(
                        '<?php echo $lang->get('actions'); ?>' +
                        '<i class="fas fa-share ml-2 pointer infotip mass-operation" title="<?php echo $lang->get('move_items'); ?>" data-action="move"></i>' +
                        '<i class="fas fa-trash ml-2 pointer infotip mass-operation" title="<?php echo $lang->get('delete_items'); ?>" data-action="delete"></i>'
                    );

                // Prepare tooltips
                $('.infotip').tooltip();
            }

            // Add selected to list


            // Now move or trash
            $('.mass-operation').click(function() {
                $('#dialog-mass-operation').removeClass('hidden');

                // Define
                var item_id,
                    sel_items_txt = '<ul>',
                    testToShow = '';

                // Init
                selectedAction = $(this).data('action');
                selectedItems = '';

                // Selected items
                $('.mass_op_cb:checkbox:checked').each(function() {
                    item_id = $(this).data('id');
                    selectedItems += item_id + ';';
                    sel_items_txt += '<li>' + $('#item_label-' + item_id).text() + '</li>';
                });
                sel_items_txt += '</ul>';

                if (selectedAction === 'move') {
                    // destination folder
                    var folders = '';
                    $.each(store.get('teampassApplication').foldersList, function(index, item) {
                        if (item.disabled === 0) {
                            folders += '<option value="' + item.id + '">' + htmlEncode(item.title) +
                                '   [' +
                                (item.path === '' ? '<?php echo $lang->get('root'); ?>' : htmlEncode(item.path)) +
                                ']</option>';
                        }
                    });

                    htmlFolders = '<div><?php echo $lang->get('import_keepass_to_folder'); ?>:&nbsp;&nbsp;' +
                        '<select class="form-control form-item-control select2" style="width:100%;" id="mass_move_destination_folder_id">' + folders + '</select>' +
                        '</div>';

                    //display to user
                    $('#dialog-mass-operation-html').html(
                        '<?php echo $lang->get('you_decided_to_move_items'); ?>: ' +
                        '<div><ul>' + sel_items_txt + '</ul></div>' + htmlFolders +
                        '<div class="mt-3 alert alert-info"><i class="fas fa-warning fa-lg mr-2"></i><?php echo $lang->get('confirm_item_move'); ?></div>'
                    );

                } else if (selectedAction === 'delete') {
                    $('#dialog-mass-operation-html').html(
                        '<?php echo $lang->get('you_decided_to_delete_items'); ?>: ' +
                        '<div><ul>' + sel_items_txt + '</ul></div>' +
                        '<div class="mt-3 alert alert-danger"><i class="fas fa-warning fa-lg mr-2"></i><?php echo $lang->get('confirm_deletion'); ?></div>'
                    );
                }
            });

        } else {
            $('#dialog-mass-operation').addClass('hidden');

            $('#search-select')
                .removeClass('menuset')
                .html('&nbsp;');

            $('#dialog-mass-operation-html').html('');
        }
    });


    // Perform action expected by user
    $('#dialog-mass-operation-button').click(function() {
        if (selectedItems === "") {
            toastr.remove();
            toastr.warning(
                '<?php echo $lang->get('none_selected_text'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        }

        // Show to user
        toastr.remove();
        toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        if (selectedAction === 'delete') {
            // Delete selected items
            // prepare data
            var data = {
                'item_ids': selectedItems,
            };

            // Launch query
            $.post(
                'sources/items.queries.php', {
                    type: 'mass_delete_items',
                    data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    //decrypt data
                    data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                    console.info(data);

                    //check if format error
                    if (data.error === true) {
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                        return false;
                    } else {
                        //reload search
                        oTable.ajax.reload();

                        toastr.remove();
                        toastr.info(
                            '<?php echo $lang->get('done'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );

                        // Finalize template
                        $('#dialog-mass-operation').addClass('hidden');
                        $('#search-select')
                            .removeClass('menuset')
                            .html('&nbsp;');
                        $('#dialog-mass-operation-html').html('');
                    }
                }
            );
        } else if (selectedAction === 'move') {
            // prepare data
            var data = {
                'item_ids': selectedItems,
                'folder_id': $('#mass_move_destination_folder_id').val(),
            };

            // Launch query
            $.post(
                'sources/items.queries.php', {
                    type: 'mass_move_items',
                    data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    //decrypt data
                    data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                    console.info(data);

                    //check if format error
                    if (data.error === true) {
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                        return false;
                    } else {
                        //reload search
                        oTable.ajax.reload();

                        toastr.remove();
                        toastr.info(
                            '<?php echo $lang->get('done'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );

                        // Finalize template
                        $('#dialog-mass-operation').addClass('hidden');
                        $('#search-select')
                            .removeClass('menuset')
                            .html('&nbsp;');
                        $('#dialog-mass-operation-html').html('');
                    }
                }
            );
        }
    });




    function unCryptData1(data) {
        if (data.substr(0, 7) === 'crypted') {
            return prepareExchangedData(
                data.substr(7),
                'decode',
                '<?php echo $session->get('key'); ?>'
            )
        }
        return false;
    }
    /**
     */
    function itemLog(logCase, itemId, itemLabel) {
        itemId = itemId || $('#id_item').val();

        var data = {
            "id": itemId,
            "label": DOMPurify.sanitize(itemLabel),
            "user_id": "<?php echo $session->get('user-id'); ?>",
            "action": logCase,
            "login": "<?php echo $session->get('user-login'); ?>"
        };

        $.post(
            "sources/items.logs.php", {
                type: "log_action_on_item",
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: "<?php echo $session->get('key'); ?>"
            }
        );
    }
</script>
