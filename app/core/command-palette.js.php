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
 * @file      command-palette.js.php
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
     * Universal Search / Command Palette (F15).
     *
     * Ctrl+K / Cmd+K opens a keyboard-first overlay searching, in one box:
     *  - items (labels/logins/urls/tags from the ACL-bound search cache),
     *  - folders the user can see,
     *  - the pages of the left menu (client-side, from the rendered sidebar).
     * Arrow keys navigate, Enter opens, Escape closes. Results respect the
     * user's folder ACL server-side; no password value is ever returned.
     */
    (function () {
        'use strict'

        var sessionKey = '<?php echo $session->get('key'); ?>'

        var L = {
            placeholder: <?php echo json_encode($lang->get('palette_placeholder'), JSON_UNESCAPED_UNICODE); ?>,
            items: <?php echo json_encode($lang->get('items'), JSON_UNESCAPED_UNICODE); ?>,
            folders: <?php echo json_encode($lang->get('folders'), JSON_UNESCAPED_UNICODE); ?>,
            pages: <?php echo json_encode($lang->get('palette_pages'), JSON_UNESCAPED_UNICODE); ?>,
            noResult: <?php echo json_encode($lang->get('no_data_to_display'), JSON_UNESCAPED_UNICODE); ?>,
            hint: <?php echo json_encode($lang->get('palette_hint'), JSON_UNESCAPED_UNICODE); ?>
        }

        var state = {
            open: false,
            selected: -1,
            entries: [],
            requestSeq: 0,
            debounceTimer: null
        }

        function escapeText(value) {
            return $('<div>').text(String(value)).html()
        }

        // Pages index, built once from the rendered sidebar (already ACL-filtered).
        var pagesIndex = null
        function buildPagesIndex() {
            if (pagesIndex !== null) return pagesIndex
            pagesIndex = []
            $('.main-sidebar a.nav-link[data-name]').each(function () {
                var name = String($(this).data('name') || '')
                var label = $(this).find('p').text().trim() || $(this).text().trim()
                if (name !== '' && label !== '') {
                    pagesIndex.push({ name: name, label: label })
                }
            })
            return pagesIndex
        }

        function ensureOverlay() {
            if ($('#tp-palette-overlay').length > 0) return

            $('body').append(
                '<div id="tp-palette-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:10500;" role="dialog" aria-modal="true" aria-label="' + escapeText(L.placeholder) + '">' +
                '<div id="tp-palette-box" class="card elevation-4" style="max-width:640px;margin:10vh auto 0;overflow:hidden;">' +
                '<div class="card-header p-2">' +
                '<input type="text" id="tp-palette-input" class="form-control form-control-lg border-0" placeholder="' + escapeText(L.placeholder) + '" autocomplete="off" aria-label="' + escapeText(L.placeholder) + '">' +
                '</div>' +
                '<div id="tp-palette-results" class="card-body p-0" style="max-height:50vh;overflow-y:auto;" role="listbox"></div>' +
                '<div class="card-footer p-1 text-muted text-center text-xs">' + escapeText(L.hint) + '</div>' +
                '</div></div>'
            )

            $('#tp-palette-overlay').on('mousedown', function (e) {
                if (e.target === this) closePalette()
            })
            $('#tp-palette-input').on('input', function () {
                scheduleSearch($(this).val())
            })
        }

        function openPalette() {
            ensureOverlay()
            state.open = true
            $('#tp-palette-overlay').show()
            $('#tp-palette-input').val('')
            renderResults({ items: [], folders: [] }, '')
            setTimeout(function () { $('#tp-palette-input').trigger('focus') }, 50)
        }

        function closePalette() {
            state.open = false
            state.selected = -1
            state.entries = []
            $('#tp-palette-overlay').hide()
        }

        function scheduleSearch(term) {
            if (state.debounceTimer) clearTimeout(state.debounceTimer)
            state.debounceTimer = setTimeout(function () {
                runSearch(String(term || ''))
            }, 200)
        }

        function runSearch(term) {
            term = term.trim()
            if (term.length < 2) {
                renderResults({ items: [], folders: [] }, term)
                return
            }
            var seq = ++state.requestSeq
            $.post('sources/palette.queries.php', {
                type: 'palette_search',
                term: term,
                key: sessionKey
            }, function (response) {
                if (seq !== state.requestSeq || state.open === false) return
                var data
                try {
                    data = prepareExchangedData(response, 'decode', sessionKey)
                } catch (e) {
                    return
                }
                if (!data || data.error === true) return
                renderResults(data, term)
            })
        }

        function matchPages(term) {
            var needle = term.toLowerCase()
            if (needle.length < 2) return []
            return buildPagesIndex().filter(function (page) {
                return page.label.toLowerCase().indexOf(needle) !== -1
            }).slice(0, 4)
        }

        function renderResults(data, term) {
            var entries = []
            var html = ''

            function section(title, rows, makeRow) {
                if (rows.length === 0) return
                html += '<div class="px-3 py-1 text-uppercase text-muted text-xs font-weight-bold">' + escapeText(title) + '</div>'
                rows.forEach(function (row) {
                    var index = entries.length
                    entries.push(row)
                    html += makeRow(row, index)
                })
            }

            section(L.items, data.items || [], function (item, index) {
                item._href = 'index.php?page=items&group=' + parseInt(item.folder_id, 10) + '&id=' + parseInt(item.id, 10)
                return '<a href="' + item._href + '" class="dropdown-item tp-palette-entry py-2" data-index="' + index + '" role="option">' +
                    '<i class="fa-solid fa-key mr-2 text-primary"></i>' + escapeText(item.label) +
                    (item.login ? ' <span class="text-muted text-sm">(' + escapeText(item.login) + ')</span>' : '') +
                    '<span class="float-right text-muted text-xs">' + escapeText(item.path) + '</span></a>'
            })

            section(L.folders, data.folders || [], function (folder, index) {
                folder._href = 'index.php?page=items&group=' + parseInt(folder.id, 10)
                return '<a href="' + folder._href + '" class="dropdown-item tp-palette-entry py-2" data-index="' + index + '" role="option">' +
                    '<i class="fa-solid fa-folder-open mr-2 text-warning"></i>' + escapeText(folder.title) +
                    '<span class="float-right text-muted text-xs">' + escapeText(folder.path) + '</span></a>'
            })

            section(L.pages, matchPages(term), function (page, index) {
                page._href = 'index.php?page=' + encodeURIComponent(page.name)
                return '<a href="' + page._href + '" class="dropdown-item tp-palette-entry py-2" data-index="' + index + '" role="option">' +
                    '<i class="fa-solid fa-arrow-right mr-2 text-info"></i>' + escapeText(page.label) + '</a>'
            })

            if (entries.length === 0) {
                html = '<div class="p-3 text-muted">' + escapeText(term.length < 2 ? L.hint : L.noResult) + '</div>'
            }

            state.entries = entries
            state.selected = entries.length > 0 ? 0 : -1
            $('#tp-palette-results').html(html)
            highlightSelected()
        }

        function highlightSelected() {
            $('.tp-palette-entry').removeClass('active')
            if (state.selected >= 0) {
                var $entry = $('.tp-palette-entry[data-index="' + state.selected + '"]')
                $entry.addClass('active')
                if ($entry.length > 0) {
                    $entry[0].scrollIntoView({ block: 'nearest' })
                }
            }
        }

        function openSelected() {
            if (state.selected < 0 || !state.entries[state.selected]) return
            var entry = state.entries[state.selected]
            if (entry._href) {
                window.location.href = entry._href
            }
        }

        $(document).on('keydown', function (e) {
            // Open: Ctrl+K / Cmd+K — a global affordance, hence preventDefault.
            if ((e.ctrlKey || e.metaKey) && !e.altKey && String(e.key).toLowerCase() === 'k') {
                e.preventDefault()
                if (state.open) { closePalette() } else { openPalette() }
                return
            }
            if (!state.open) return

            if (e.key === 'Escape') {
                e.preventDefault()
                closePalette()
            } else if (e.key === 'ArrowDown') {
                e.preventDefault()
                if (state.entries.length > 0) {
                    state.selected = (state.selected + 1) % state.entries.length
                    highlightSelected()
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault()
                if (state.entries.length > 0) {
                    state.selected = (state.selected - 1 + state.entries.length) % state.entries.length
                    highlightSelected()
                }
            } else if (e.key === 'Enter') {
                e.preventDefault()
                openSelected()
            }
        })

        // Toolbar search affordance — mirrors Ctrl+K so the palette is discoverable.
        $(document).on('click', '#tp-navbar-search', function (e) {
            e.preventDefault()
            if (state.open) { closePalette() } else { openPalette() }
        })
    })()
    //]]>
</script>
