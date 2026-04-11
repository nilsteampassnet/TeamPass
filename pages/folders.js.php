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
 * @file      folders.js.php
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('folders') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[

    // Clear
    $('#folders-search').val('');

    // Generation counter: incremented on each buildTable() call so stale batch loops self-cancel
    var _buildGeneration = 0

    buildTable();

    // Prepare iCheck format for checkboxes
    $('input[type="checkbox"].form-check-input').iCheck({
        checkboxClass: 'icheckbox_flat-blue',
    });

    $('input[type="checkbox"].form-check-red-input').iCheck({
        checkboxClass: 'icheckbox_flat-red',
    });

    // Prepare buttons
    var deletionList = []
    // Cached after buildTable() for use in insertFolderRow()
    var _userIsAdmin = 0
    var _userCanCreateRootFolder = 0
    $('.tp-action').click(function() {
        if ($(this).data('action') === 'new') {
            //--- NEW FOLDER MODAL
            // Reset simple fields (select2 and focus handled in shown.bs.modal)
            $('#modal-folder-new .clear-me').val('')
            $('#modal-folder-new .form-check-input').iCheck('uncheck')

            $('#modal-folder-new').modal('show')

        } else if ($(this).data('action') === 'new-submit') {
            //--- SAVE NEW FOLDER

            // Sanitize text fields
            purifyRes = fieldDomPurifierLoop('#modal-folder-new .purify');
            if (purifyRes.purifyStop === true) {
                // if purify failed, stop
                return false;
            }
            // Show spinner
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Prepare data
            var data = {
                'title': purifyRes.arrFields['title'],
                'parentId': parseInt($('#new-parent').val()),
                'complexity': parseInt($('#new-complexity').val()),
                'accessRight': $('#new-access-right').val(),
                'renewalPeriod': $('#new-renewal').val() === '' ? 0 : parseInt($('#new-renewal').val()),
                'addRestriction': $('#new-add-restriction').prop("checked") === true ? 1 : 0,
                'editRestriction': $('#new-edit-restriction').prop("checked") === true ? 1 : 0,
                'icon': purifyRes.arrFields['icon'],
                'iconSelected': purifyRes.arrFields['iconSelected'],
            }
            
            // Launch action
            $.post(
                'sources/folders.queries.php', {
                    type: 'add_folder',
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    //decrypt data
                    data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');
                    console.log(data)
                    if (data.error === true) {
                        // ERROR
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '<?php echo $lang->get('error'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        // Insert new row directly — no full table rebuild needed
                        if (data.rowData) {
                            insertFolderRow(data.rowData)
                        } else {
                            buildTable()
                        }

                        // Add new folder to the list 'new-parent'
                        // Launch action
                        $.post(
                            'sources/folders.queries.php', {
                                type: 'refresh_folders_list',
                                key: '<?php echo $session->get('key'); ?>'
                            },
                            function(data) { //decrypt data
                                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');
                                console.log(data);

                                // prepare options list
                                var prev_level = 0,
                                    droplist = '';

                                $(data.subfolders).each(function(i, folder) {
                                    droplist += '<option value="' + folder['id'] + '">' +
                                        folder['label'] +
                                        folder['path'] +
                                        '</option>';
                                });

                                $('#new-parent')
                                    .empty()
                                    .append(droplist);
                            }
                        );

                        $('#modal-folder-new').modal('hide')
                    }
                }
            );

        } else if ($(this).data('action') === 'delete') {
            //--- DELETE FOLDER MODAL
            if ($('#table-folders input[type=checkbox]:checked').length === 0) {
                toastr.remove();
                toastr.warning(
                    '<?php echo $lang->get('you_need_to_select_at_least_one_folder'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }

            // Reset confirm checkbox and build folder list
            $('#delete-confirm').iCheck('uncheck');
            var selectedFolders = '<ul>';
            $("input:checkbox[class=checkbox-folder]:checked").each(function() {
                var folderText = $('#folder-' + $(this).data('id')).text();
                selectedFolders += '<li>' + $('<div>').text(folderText).html() + '</li>';
            });
            $('#delete-list').html(selectedFolders + '</ul>');

            $('#modal-folder-delete').modal('show')

        } else if ($(this).data('action') === 'delete-submit') {
            console.log('delete-submit')
            //--- DELETE FOLDERS
            // Show spinner
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Get list of selected folders
            var selectedFolders = [];
            $("input:checkbox[class=checkbox-folder]:checked").each(function() {
                selectedFolders.push($(this).data('id'));
            });

            // Prepare data
            var data = {
                'selectedFolders': selectedFolders,
            }

            console.log(data)

            // Launch action
            $.post(
                'sources/folders.queries.php', {
                    type: 'delete_folders',
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) { //decrypt data
                    data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');

                    if (data.error === true) {
                        // ERROR
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '<?php echo $lang->get('error'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        // Remove deleted rows (and all their descendants) directly from the DOM
                        selectedFolders.forEach(function(folderId) {
                            $('#table-folders tbody tr[data-id="' + folderId + '"]').remove()
                            $('#table-folders tbody .p' + folderId).remove()
                        })

                        $('#modal-folder-delete').modal('hide')

                        toastr.remove()
                        toastr.success('<?php echo $lang->get('done'); ?>', '', { timeOut: 1000 })
                    }
                }
            );

        } else if ($(this).data('action') === 'refresh') {
            //--- REFRESH FOLDERS LIST
            buildTable();
        }
    });

    /**
     * Handle delete button status
     */
    $(document).on('ifChecked', '#delete-confirm', function() {
        $('#delete-submit').removeClass('disabled');
    });
    $(document).on('ifUnchecked', '#delete-confirm', function() {
        $('#delete-submit').addClass('disabled');
    });

    // Reset confirm checkbox when delete modal closes
    $('#modal-folder-delete').on('hidden.bs.modal', function() {
        $('#delete-confirm').iCheck('uncheck')
    })


    /**
     * Build the folders table with batch rendering and a progress bar.
     * Folders are rendered 25 at a time so the browser stays responsive
     * and the user sees incremental progress on large installations.
     *
     * @return void
     */
    function buildTable() {
        const BATCH_SIZE = 25
        const myGeneration = ++_buildGeneration

        // Clear table and reset progress bar
        $('#table-folders > tbody').html('')
        $('#folders-load-progress').show()
        $('#folders-load-progress .progress-bar').css('width', '0%').attr('aria-valuenow', 0)
        $('#folders-load-progress .folders-load-text').text('')

        // Clear any leftover action-level toast; progress bar handles loading feedback
        toastr.remove()

        $.post(
            'sources/folders.queries.php', {
                type: 'build_matrix',
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>')
                console.log(data)

                if (data.error !== false) {
                    toastr.remove()
                    toastr.error(data.message, '<?php echo $lang->get('error'); ?>', {
                        timeOut: 5000,
                        progressBar: true
                    })
                    $('#folders-load-progress').hide()
                    return
                }

                const total = data.matrix.length
                let offset = 0
                let foldersSelect = '<option value="0"><?php echo $lang->get('root'); ?></option>'
                let max_folder_depth = 0

                /**
                 * Render the next batch of BATCH_SIZE rows, then yield to the
                 * browser via setTimeout so the progress bar and DOM updates
                 * are painted before the next batch starts.
                 */
                function renderBatch() {
                    // A newer buildTable() call has started — discard this stale loop
                    if (_buildGeneration !== myGeneration) return

                    const end = Math.min(offset + BATCH_SIZE, total)
                    let batchHtml = ''

                    for (let i = offset; i < end; i++) {
                        const value = data.matrix[i]
                        batchHtml += buildFolderRowHtml(value, data.userIsAdmin, data.userCanCreateRootFolder)
                        foldersSelect += '<option value="' + value.id + '">' + value.title + '</option>'
                        if (parseInt(value.level) > max_folder_depth) {
                            max_folder_depth = parseInt(value.level)
                        }
                    }

                    // Append this batch to the DOM
                    $('#table-folders > tbody').append(batchHtml)
                    offset = end

                    // Update progress bar
                    const pct = total > 0 ? Math.round((offset / total) * 100) : 100
                    $('#folders-load-progress .progress-bar').css('width', pct + '%').attr('aria-valuenow', pct)
                    $('#folders-load-progress .folders-load-text').text(offset + ' / ' + total)

                    if (offset < total) {
                        // Yield to browser so it can paint the progress update
                        setTimeout(renderBatch, 0)
                    } else {
                        // All rows rendered — finalize
                        $('#table-folders input[type="checkbox"]').iCheck({
                            checkboxClass: 'icheckbox_flat-blue'
                        })
                        $('.infotip').tooltip()

                        store.update('teampassApplication', function(teampassApplication) {
                            teampassApplication.foldersSelect = foldersSelect
                        })

                        let complexity = ''
                        $(data.fullComplexity).each(function(i, option) {
                            complexity += '<option value="' + option.value + '">' + option.text + '</option>'
                        })
                        store.update('teampassApplication', function(teampassApplication) {
                            teampassApplication.complexityOptions = complexity
                        })

                        $('#folders-depth').empty().append('<option value="all"><?php echo $lang->get('all'); ?></option>')
                        for (let x = 1; x < max_folder_depth; x++) {
                            $('#folders-depth').append('<option value="' + x + '">' + x + '</option>')
                        }
                        const storedDepth = store.get('teampassUser') && store.get('teampassUser').foldersDepthFilter !== undefined
                            ? store.get('teampassUser').foldersDepthFilter
                            : 'all'
                        const depthToApply = $('#folders-depth option[value="' + storedDepth + '"]').length > 0 ? storedDepth : 'all'
                        $('#folders-depth').val(depthToApply).trigger('change')

                        // Populate complexity filter (keep current selection if possible)
                        const prevComplexity = $('#folders-complexity').val()
                        $('#folders-complexity').empty().append('<option value="all"><?php echo $lang->get('all'); ?></option>')
                        $(data.fullComplexity).each(function(i, opt) {
                            $('#folders-complexity').append('<option value="' + opt.value + '">' + opt.text + '</option>')
                        })
                        $('#folders-complexity').val(prevComplexity || 'all')

                        // Cache admin flags for insertFolderRow() and updateFolderRow()
                        _userIsAdmin = data.userIsAdmin
                        _userCanCreateRootFolder = data.userCanCreateRootFolder

                        $('#folders-load-progress').hide()
                        toastr.success('<?php echo $lang->get('done'); ?>', '', {
                            timeOut: 2000,
                            closeButton: true,
                            tapToDismiss: true
                        })
                    }
                }

                renderBatch()
            }
        )
    }


    /**
     * Build HTML string for a single folder table row.
     * Shared by buildTable() (via renderBatch), insertFolderRow(), and updateFolderRow().
     */
    function buildFolderRowHtml(value, userIsAdmin, userCanCreateRootFolder) {
        let parentsClass = ''
        $(value.parents).each(function(j, id) {
            parentsClass += 'p' + id + ' '
        })

        const complexityVal = value.folderComplexity !== '' && value.folderComplexity.value !== undefined
            ? value.folderComplexity.value : ''
        let row = '<tr data-id="' + value.id + '" data-parent-id="' + value.parentId + '" data-level="' + value.level + '" data-complexity="' + complexityVal + '" class="' + parentsClass + '"><td>'

        // Column 1 — checkbox + collapse icon
        if ((value.parentId === 0 && (userIsAdmin === 1 || userCanCreateRootFolder === 1)) || value.parentId !== 0) {
            row += '<input type="checkbox" class="checkbox-folder" id="cb-' + value.id + '" data-id="' + value.id + '">'
            if (value.numOfChildren > 0) {
                row += '<i class="fas fa-folder-minus infotip ml-2 pointer icon-collapse" data-id="' + value.id + '" title="<?php echo $lang->get('collapse'); ?>"></i>'
            }
        }
        row += '</td>'

        // Column 2 — folder name with left indent proportional to tree depth + item count badge
        const indent = (value.level - 1) * 16
        let nameCell = '<span id="folder-' + value.id + '" data-id="' + value.id + '" class="infotip folder-name" data-html="true" title="<?php echo $lang->get('id'); ?>: ' + value.id + '<br><?php echo $lang->get('level'); ?>: ' + value.level + '<br><?php echo $lang->get('nb_items'); ?>: ' + value.nbItems + '">' + value.title + '</span>'
        if (value.nbItems > 0) {
            nameCell += ' <span class="badge badge-secondary ml-1">' + value.nbItems + '</span>'
        }
        row += '<td class="modify pointer" style="padding-left:' + indent + 'px">' + nameCell + '</td>'

        // Column 3 — parent path breadcrumb
        let path = ''
        $(value.path).each(function(j, folder) {
            path = path === '' ? folder : path + '<i class="fas fa-angle-right fa-sm ml-1 mr-1"></i>' + folder
        })
        row += '<td class="modify pointer" min-width="200px" data-value="' + value.parentId + '"><small class="text-muted">' + path + '</small></td>'

        // Column 4 — complexity
        row += '<td class="modify pointer text-center">'
        if (value.folderComplexity !== '' && value.folderComplexity.value !== undefined) {
            row += '<i class="' + value.folderComplexity.class + ' infotip" data-value="' + value.folderComplexity.value + '" title="' + value.folderComplexity.text + '"></i>'
        } else {
            row += '<i class="fas fa-exclamation-triangle text-danger infotip" data-value="" title="<?php echo $lang->get('no_value_defined_please_fix'); ?>"></i>'
        }
        row += '</td>'

        // Column 5 — renewal period
        row += '<td class="modify pointer text-center">' + value.renewalPeriod + '</td>'

        // Column 6 — add restriction
        row += '<td class="modify pointer text-center" data-value="' + value.add_is_blocked + '">'
        row += value.add_is_blocked === 1 ? '<i class="fas fa-toggle-on text-info"></i>' : '<i class="fas fa-toggle-off"></i>'
        row += '</td>'

        // Column 7 — edit restriction
        row += '<td class="modify pointer text-center" data-value="' + value.edit_is_blocked + '">'
        row += value.edit_is_blocked === 1 ? '<i class="fas fa-toggle-on text-info"></i>' : '<i class="fas fa-toggle-off"></i>'
        row += '</td>'

        // Column 8 — folder icon
        row += '<td class="modify pointer text-center" data-value="' + value.icon + '"><i class="' + value.icon + '"></td>'

        // Column 9 — selected folder icon
        row += '<td class="modify pointer text-center" data-value="' + value.iconSelected + '">'
        if (value.iconSelected !== '') {
            row += '<i class="' + value.iconSelected + '">'
        }
        row += '</td></tr>'

        return row
    }


    /**
     * Insert a newly created folder row at the correct position in the table,
     * without triggering a full table rebuild.
     */
    function insertFolderRow(rowData) {
        const rowHtml = buildFolderRowHtml(rowData, _userIsAdmin, _userCanCreateRootFolder)

        // Insert after the last row of the parent's subtree
        // (.p{parentId} matches all descendants of the parent folder)
        const $parentRow = $('#table-folders tbody tr[data-id="' + rowData.parentId + '"]')
        const $subtree = $parentRow.length > 0
            ? $parentRow.add($('#table-folders tbody .p' + rowData.parentId))
            : $()

        if ($subtree.length > 0) {
            $subtree.last().after(rowHtml)
        } else {
            $('#table-folders tbody').append(rowHtml)
        }

        // Add a collapse icon to the parent row if it had no children before.
        // iCheck wraps the checkbox in a div.icheckbox_flat-blue, so insert after
        // the wrapper (not the hidden input) to avoid visual overlap.
        if ($parentRow.length > 0 && $parentRow.find('.icon-collapse').length === 0) {
            const $cb = $parentRow.find('input.checkbox-folder')
            const $ichecWrapper = $cb.closest('.icheckbox_flat-blue')
            const $insertAfter = $ichecWrapper.length > 0 ? $ichecWrapper : $cb
            $insertAfter.after(
                '<i class="fas fa-folder-minus infotip ml-2 pointer icon-collapse" data-id="' + rowData.parentId + '" title="<?php echo $lang->get('collapse'); ?>"></i>'
            )
        }

        // Init iCheck and tooltips for the new row
        $('#cb-' + rowData.id).iCheck({ checkboxClass: 'icheckbox_flat-blue' })
        $('#table-folders tbody tr[data-id="' + rowData.id + '"] .infotip').tooltip()

        // Append the new folder to the stored select options
        store.update('teampassApplication', function(app) {
            app.foldersSelect += '<option value="' + rowData.id + '">' + rowData.title + '</option>'
        })

        toastr.remove()
        toastr.success('<?php echo $lang->get('done'); ?>', '', { timeOut: 1000 })
    }


    /**
     * Update an existing folder row in-place after an edit.
     * Only called when the parent folder has not changed (otherwise buildTable() is used).
     */
    function updateFolderRow(rowData) {
        const $row = $('#table-folders tbody tr[data-id="' + rowData.id + '"]')
        if ($row.length === 0) {
            buildTable()
            return
        }

        // col 2 — folder name + updated depth indentation + item count badge
        const indent = (rowData.level - 1) * 16
        const $nameTd = $row.find('td:eq(1)').css('padding-left', indent + 'px')
        $('#folder-' + rowData.id)
            .text(rowData.title)
            .attr('title', '<?php echo $lang->get('id'); ?>: ' + rowData.id +
                '<br><?php echo $lang->get('level'); ?>: ' + rowData.level +
                '<br><?php echo $lang->get('nb_items'); ?>: ' + rowData.nbItems)
        $nameTd.find('.badge').remove()
        if (rowData.nbItems > 0) {
            $nameTd.append(' <span class="badge badge-secondary ml-1">' + rowData.nbItems + '</span>')
        }
        // Update complexity data attribute for the filters
        const updatedComplexity = rowData.folderComplexity !== '' && rowData.folderComplexity.value !== undefined
            ? rowData.folderComplexity.value : ''
        $row.attr('data-complexity', updatedComplexity)

        // col 3 — parent path
        let path = ''
        $(rowData.path).each(function(j, folder) {
            path = path === '' ? folder : path + '<i class="fas fa-angle-right fa-sm ml-1 mr-1"></i>' + folder
        })
        $row.find('td:eq(2)').data('value', rowData.parentId).find('small').html(path)

        // col 4 — complexity
        const $complexTd = $row.find('td:eq(3)').empty()
        if (rowData.folderComplexity !== '' && rowData.folderComplexity.value !== undefined) {
            $complexTd.append('<i class="' + rowData.folderComplexity.class + ' infotip" data-value="' +
                rowData.folderComplexity.value + '" title="' + rowData.folderComplexity.text + '"></i>')
        } else {
            $complexTd.append('<i class="fas fa-exclamation-triangle text-danger infotip" data-value="" title="<?php echo $lang->get('no_value_defined_please_fix'); ?>"></i>')
        }

        // col 5 — renewal period
        $row.find('td:eq(4)').text(rowData.renewalPeriod)

        // col 6 — add restriction
        $row.find('td:eq(5)').data('value', rowData.add_is_blocked).html(
            rowData.add_is_blocked === 1 ? '<i class="fas fa-toggle-on text-info"></i>' : '<i class="fas fa-toggle-off"></i>'
        )

        // col 7 — edit restriction
        $row.find('td:eq(6)').data('value', rowData.edit_is_blocked).html(
            rowData.edit_is_blocked === 1 ? '<i class="fas fa-toggle-on text-info"></i>' : '<i class="fas fa-toggle-off"></i>'
        )

        // col 8 — folder icon
        $row.find('td:eq(7)').data('value', rowData.icon).html('<i class="' + rowData.icon + '">')

        // col 9 — selected folder icon
        const $iconSelTd = $row.find('td:eq(8)').data('value', rowData.iconSelected).empty()
        if (rowData.iconSelected !== '') {
            $iconSelTd.html('<i class="' + rowData.iconSelected + '">')
        }

        $row.find('.infotip').tooltip()
        closeSidebar()
        toastr.remove()
        toastr.success('<?php echo $lang->get('done'); ?>', '', { timeOut: 1000 })
    }


    /**
     * Build list of folders
     */
    function refreshFoldersList() {
        // Launch action
        $.post(
            'sources/folders.queries.php', {
                type: 'select_sub_folders',
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) { //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');

            }
        );
    }


    /**
     * Apply all active filters (depth, complexity, search) simultaneously.
     * Centralising the logic avoids filters overriding each other.
     */
    function applyFilters() {
        const depthVal = $('#folders-depth').val()
        const complexityVal = $('#folders-complexity').val()
        const searchVal = $('#folders-search').val().toLowerCase()

        $('#table-folders tbody tr[data-id]').each(function() {
            const depthOk = depthVal === 'all' || parseInt($(this).data('level')) <= parseInt(depthVal)
            const complexityOk = complexityVal === 'all' || String($(this).data('complexity')) === String(complexityVal)
            const searchOk = searchVal === '' || $(this).find('.folder-name').text().toLowerCase().indexOf(searchVal) !== -1

            if (depthOk && complexityOk && searchOk) {
                $(this).removeClass('hidden')
            } else {
                $(this).addClass('hidden')
            }
        })
    }

    $(document).on('change', '#folders-depth', function() {
        const depth = $(this).val()
        if (depth !== null && depth !== '') {
            store.update('teampassUser', function(teampassUser) {
                teampassUser.foldersDepthFilter = depth
            })
        }
        applyFilters()
    })
    $(document).on('change', '#folders-complexity', applyFilters)
    $('#folders-search').on('keyup', applyFilters)

    /**
     * Check / Uncheck children folders
     */
    var operationOngoin = false;
    $(document).on('ifChecked', '.checkbox-folder', function() {
        if (operationOngoin === false) {
            operationOngoin = true;

            // Show spinner
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Show selection of folders
            var selected_cb = $(this),
                id = $(this).data('id');

            // Now get subfolders
            $.post(
                'sources/folders.queries.php', {
                    type: 'select_sub_folders',
                    id: id,
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                    console.log(data)
                    // check/uncheck checkbox
                    if (data.subfolders !== '') {
                        $.each(JSON.parse(data.subfolders), function(i, value) {
                            $('#cb-' + value).iCheck('check');
                        });
                    }
                    operationOngoin = false;

                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                }
            );
        }
    });

    $(document).on('ifUnchecked', '.checkbox-folder', function() {
        if (operationOngoin === false) {
            operationOngoin = true;

            // Show spinner
            toastr.remove();
            toastr.info('<?php echo $lang->get('loading_data'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Show selection of folders
            var selected_cb = $(this),
                id = $(this).data('id');

            // Now get subfolders
            $.post(
                'sources/folders.queries.php', {
                    type: 'select_sub_folders',
                    id: id,
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                    // check/uncheck checkbox
                    if (data.subfolders !== '') {
                        $.each(JSON.parse(data.subfolders), function(i, value) {
                            $('#cb-' + value).iCheck('uncheck');
                        });
                    }
                    operationOngoin = false;

                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                }
            );
        }
    });


    /**
     * Sidebar: current folder id being edited
     */
    var _sidebarFolderId = null

    /**
     * Open the edit sidebar for the given table row.
     */
    function openSidebar($row) {
        const folderId              = $row.data('id')
        const folderTitle           = $row.find('td:eq(1) .folder-name').text()
        const folderParent          = $row.find('td:eq(2)').data('value')
        const folderComplexity      = $row.find('td:eq(3) > i').data('value')
        const folderRenewal         = $row.find('td:eq(4)').text()
        const folderAddRestriction  = $row.find('td:eq(5)').data('value')
        const folderEditRestriction = $row.find('td:eq(6)').data('value')
        const folderIcon            = $row.find('td:eq(7)').data('value') || ''
        const folderIconSel         = $row.find('td:eq(8)').data('value') || ''

        _sidebarFolderId = folderId
        $('#sidebar-submit').data('id', folderId)

        // Header
        $('#sidebar-folder-name').text(folderTitle)

        // Fields
        $('#folder-edit-title').val(folderTitle)
        $('#folder-edit-renewal').val(folderRenewal)
        $('#folder-edit-icon').val(folderIcon)
        $('#folder-edit-icon-selected').val(folderIconSel)

        // Slide in first so Select2 initializes inside a visible container
        $('#folder-edit-overlay').fadeIn(150)
        $('#folder-edit-sidebar').addClass('open')

        // Populate selects from stored options then set values
        $('#folder-edit-parent').html(store.get('teampassApplication').foldersSelect)
        $('#folder-edit-complexity').html(store.get('teampassApplication').complexityOptions)

        // Re-initialize Select2 cleanly each time the sidebar opens
        if ($('#folder-edit-parent').hasClass('select2-hidden-accessible')) {
            $('#folder-edit-parent').select2('destroy')
        }
        if ($('#folder-edit-complexity').hasClass('select2-hidden-accessible')) {
            $('#folder-edit-complexity').select2('destroy')
        }

        $('#folder-edit-parent').select2({
            language: '<?php echo $session->get('user-language_code'); ?>',
            dropdownParent: $('#folder-edit-sidebar'),
            width: '100%'
        }).val(String(folderParent)).trigger('change')

        $('#folder-edit-complexity').select2({
            language: '<?php echo $session->get('user-language_code'); ?>',
            dropdownParent: $('#folder-edit-sidebar'),
            width: '100%'
        }).val(String(folderComplexity)).trigger('change')

        // Checkboxes
        if (folderAddRestriction === 1) {
            $('#folder-edit-add-restriction').iCheck('check')
        } else {
            $('#folder-edit-add-restriction').iCheck('uncheck')
        }
        if (folderEditRestriction === 1) {
            $('#folder-edit-edit-restriction').iCheck('check')
        } else {
            $('#folder-edit-edit-restriction').iCheck('uncheck')
        }

        // Highlight the row being edited
        $('#table-folders tbody tr.editing-active').removeClass('editing-active')
        $row.addClass('editing-active')

    }

    /**
     * Close the edit sidebar.
     */
    function closeSidebar() {
        $('#folder-edit-sidebar').removeClass('open')
        $('#folder-edit-overlay').fadeOut(150)
        $('#table-folders tbody tr.editing-active').removeClass('editing-active')
        _sidebarFolderId = null
    }

    /**
     * Open sidebar on row click — toggle if same row clicked again
     */
    $('#table-folders').on('click', '.modify', function() {
        const $row = $(this).closest('tr')
        if (_sidebarFolderId !== null && _sidebarFolderId === $row.data('id')) {
            closeSidebar()
            return false
        }
        openSidebar($row)
    })

    // Init select2 with dropdownParent when the new-folder modal opens
    $('#modal-folder-new').on('shown.bs.modal', function() {
        $('#new-parent').html(store.get('teampassApplication').foldersSelect)
            .select2({
                language: '<?php echo $session->get('user-language_code'); ?>',
                dropdownParent: $('#modal-folder-new')
            }).val('0').trigger('change')
        $('#new-complexity').html(store.get('teampassApplication').complexityOptions)
            .select2({
                language: '<?php echo $session->get('user-language_code'); ?>',
                dropdownParent: $('#modal-folder-new')
            }).val('0').trigger('change')
        $('#new-title').focus()
    })

    // Close buttons
    $('#sidebar-close, #sidebar-cancel').on('click', function() {
        closeSidebar()
    })

    // Overlay click closes sidebar
    $('#folder-edit-overlay').on('click', function() {
        closeSidebar()
    })

    // Close sidebar on Escape key
    $(document).keyup(function(e) {
        if (e.keyCode === 27 && _sidebarFolderId !== null) {
            closeSidebar()
        }
    })

    // Submit from sidebar
    $('#sidebar-submit').on('click', function() {
        const currentFolderId = _sidebarFolderId
        if (!currentFolderId) return

        purifyRes = fieldDomPurifierLoop('#folder-edit-sidebar .purify')
        if (purifyRes.purifyStop === true) {
            return false
        }

        const data = {
            'id': currentFolderId,
            'title': purifyRes.arrFields['title'],
            'parentId': $('#folder-edit-parent').val(),
            'complexity': $('#folder-edit-complexity').val(),
            'renewalPeriod': $('#folder-edit-renewal').val() === '' ? 0 : parseInt($('#folder-edit-renewal').val()),
            'addRestriction': $('#folder-edit-add-restriction').prop('checked') === true ? 1 : 0,
            'editRestriction': $('#folder-edit-edit-restriction').prop('checked') === true ? 1 : 0,
            'icon': purifyRes.arrFields['icon'],
            'iconSelected': purifyRes.arrFields['iconSelected'],
        }

        $.post(
            'sources/folders.queries.php', {
                type: 'update_folder',
                data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>')

                if (data.error === true) {
                    toastr.remove()
                    toastr.error(
                        data.message,
                        '<?php echo $lang->get('error'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    )
                } else {
                    // Parent changed → subtree moved → full rebuild needed
                    // Parent unchanged → update cells in-place
                    if (data.info_parent_changed === true || !data.rowData) {
                        closeSidebar()
                        buildTable()
                    } else {
                        updateFolderRow(data.rowData)
                    }
                }
            }
        )
    })


    // Manage collapse/expand
    $(document).on('click', '.icon-collapse', function() {
        const folderId = $(this).data('id')
        if ($(this).hasClass('fa-folder-minus') === true) {
            // Collapse: hide all descendants (they all carry the pX ancestor class)
            $(this)
                .removeClass('fa-folder-minus')
                .addClass('fa-folder-plus text-primary');
            $('.p' + folderId).addClass('hidden');
        } else {
            // Expand: only reveal direct children; deeper rows stay hidden per their own collapsed state
            $(this)
                .removeClass('fa-folder-plus text-primary')
                .addClass('fa-folder-minus');
            $('#table-folders tbody tr[data-parent-id="' + folderId + '"]').removeClass('hidden');
        }
    });


    //]]>
</script>
