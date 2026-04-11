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
 * @file      roles.js.php
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('roles') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    // Globals
    var currentThis = ''
    var _matrixGeneration = 0
    var _sidebarFolderId = ''

    // Preapre select drop list
    $('#roles-list.select2').select2({
        language: '<?php echo $session->get('user-language_code'); ?>',
        placeholder: '<?php echo $lang->get('select_a_role'); ?>',
        allowClear: true
    });
    $('#roles-list').val('').change();

    // Populate
    var $options = $("#roles-list > option").clone();
    $('#folders-compare').append($options);



    $('#form-complexity-list.select2').select2({
        language: '<?php echo $session->get('user-language_code'); ?>',
        dropdownParent: $('#modal-role-definition')
    });

    //iCheck for checkbox and radio inputs (exclude sidebar which uses orange style)
    $('input[type="checkbox"]').not('#role-edit-sidebar input').iCheck({
        checkboxClass: 'icheckbox_flat-blue'
    });
    // Sidebar elements: orange style, initialized once at page load
    $('#role-edit-sidebar input[type="checkbox"]').iCheck({
        checkboxClass: 'icheckbox_flat-orange'
    });
    $('#role-edit-sidebar input[type="radio"]').iCheck({
        radioClass: 'iradio_flat-orange'
    });

    // On role selection
    $(document).on('change', '#roles-list', function() {
        // initi checkboxes
        $('input[type="checkbox"]').iCheck('uncheck');
        if ($(this).find(':selected').text() === '') {
            // Hide
            $('#card-role-details').addClass('hidden');
            $('#button-edit, #button-delete').addClass('disabled');
        } else {
            var selectedRoleId = $(this).find(':selected').val();
            $('#button-edit, #button-delete').removeClass('disabled');

            // Prepare card header
            $('#role-detail-header').html(
                $('<div>').text($(this).find(':selected').text()).html() +
                ' <i class="' + $(this).find(':selected').data('complexity-icon') + ' infotip ml-3" ' +
                'title="<?php echo $lang->get('complexity'); ?>: ' +
                $(this).find(':selected').data('complexity-text') + '"></i>' +
                (parseInt($(this).find(':selected').data('allow-edit-all')) === 1 ?
                    '<i class="ml-3 fas fa-exclamation-triangle text-warning infotip" ' +
                    'title="<?php echo $lang->get('role_can_edit_any_visible_item'); ?>"></i>' :
                    '')
            );

            $('.infotip').tooltip();

            refreshMatrix(selectedRoleId);
        }
    });

    /**
     * Build the HTML string for a single row of the permissions matrix.
     */
    function buildMatrixRowHtml(value) {
        var access = ''
        if (value.access === 'W') {
            access = '<i class="fas fa-indent mr-2 text-success infotip" title="<?php echo $lang->get('add_allowed'); ?>"></i>' +
                '<i class="fas fa-pen mr-2 text-success infotip" title="<?php echo $lang->get('edit_allowed'); ?>"></i>' +
                '<i class="fas fa-eraser mr-2 text-success infotip" title="<?php echo $lang->get('delete_allowed'); ?>"></i>'
        } else if (value.access === 'ND') {
            access = '<i class="fas fa-indent mr-2 text-success infotip" title="<?php echo $lang->get('add_allowed'); ?>"></i>' +
                '<i class="fas fa-pen mr-2 text-success infotip" title="<?php echo $lang->get('edit_allowed'); ?>"></i>' +
                '<i class="fas fa-eraser mr-2 text-danger infotip" title="<?php echo $lang->get('delete_not_allowed'); ?>"></i>'
        } else if (value.access === 'NE') {
            access = '<i class="fas fa-indent mr-2 text-success infotip" title="<?php echo $lang->get('add_allowed'); ?>"></i>' +
                '<i class="fas fa-pen mr-2 text-danger infotip" title="<?php echo $lang->get('edit_not_allowed'); ?>"></i>' +
                '<i class="fas fa-eraser mr-2 text-success infotip" title="<?php echo $lang->get('delete_allowed'); ?>"></i>'
        } else if (value.access === 'NDNE') {
            access = '<i class="fas fa-indent mr-2 text-success infotip" title="<?php echo $lang->get('add_allowed'); ?>"></i>' +
                '<i class="fas fa-pen mr-2 text-danger infotip" title="<?php echo $lang->get('edit_not_allowed'); ?>"></i>' +
                '<i class="fas fa-eraser mr-2 text-danger infotip" title="<?php echo $lang->get('delete_not_allowed'); ?>"></i>'
        } else if (value.access === 'R') {
            access = '<i class="fas fa-book-reader mr-2 text-warning infotip" title="<?php echo $lang->get('read_only'); ?>"></i>'
        } else {
            access = '<i class="fas fa-ban mr-2 text-danger infotip" title="<?php echo $lang->get('no_access'); ?>"></i>'
        }

        var path = ''
        $(value.path).each(function(j, valuePath) {
            path = path === '' ? valuePath : path + ' / ' + valuePath
        })

        var indent = (parseInt(value.ident) - 1) * 16
        var folderIcon = value.ident === 1
            ? '<i class="fas fa-folder text-warning mr-1"></i>'
            : '<i class="fas fa-folder-open text-warning mr-1" style="opacity:.7"></i>'

        return '<tr data-level="' + value.ident + '" class="' + (value.ident === 1 ? 'parent' : 'descendant') + '" data-id="' + value.id + '">' +
            '<td width="35px"><input type="checkbox" id="cb-' + value.id + '" data-id="' + value.id + '" class="folder-select"></td>' +
            '<td class="pointer modify folder-name" data-id="' + value.id + '" data-access="' + value.access + '" style="padding-left:' + indent + 'px">' + folderIcon + value.title + '</td>' +
            '<td class="font-italic pointer modify" data-id="' + value.id + '" data-access="' + value.access + '"><small class="text-muted">' + path + '</small></td>' +
            '<td class="pointer modify td-100 text-center" data-id="' + value.id + '" data-access="' + value.access + '">' + access + '</td>' +
            '<td class="hidden compare tp-borders td-100 text-center"></td>' +
            '</tr>'
    }

    /**
     * Load and render the permissions matrix for a role, using batch rendering
     * so the browser can paint the progress bar between batches.
     */
    function refreshMatrix(selectedRoleId) {
        const BATCH_SIZE = 25
        const myGeneration = ++_matrixGeneration

        $('#card-role-details').removeClass('hidden')
        $('#role-details').html('')
        closeRightsSidebar()

        // Show progress bar, clear any previous toast
        $('#roles-load-progress').show()
        $('#roles-load-progress .progress-bar').css('width', '0%').attr('aria-valuenow', 0)
        $('#roles-load-progress .roles-load-text').text('')
        toastr.remove()

        $.post(
            'sources/roles.queries.php', {
                type: 'build_matrix',
                role_id: selectedRoleId,
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>')

                if (data.error === true) {
                    toastr.remove()
                    toastr.error(data.message, '', { timeOut: 5000, progressBar: true })
                    $('#roles-load-progress').hide()
                    return
                }

                const matrix = data.matrix
                const total = matrix.length
                let offset = 0
                let max_folder_depth = 1

                // Insert empty table structure up front
                $('#role-details').html(
                    '<table id="table-role-details" class="table table-hover table-striped table-responsive" style="width:100%">' +
                    '<tbody></tbody></table>'
                )
                const tableBody = $('#table-role-details > tbody')

                function renderBatch() {
                    // A newer refreshMatrix() call has started — discard this stale loop
                    if (_matrixGeneration !== myGeneration) return

                    const end = Math.min(offset + BATCH_SIZE, total)
                    let batchHtml = ''

                    for (let i = offset; i < end; i++) {
                        const value = matrix[i]
                        batchHtml += buildMatrixRowHtml(value)
                        if (parseInt(value.ident) > max_folder_depth) {
                            max_folder_depth = parseInt(value.ident)
                        }
                    }

                    tableBody.append(batchHtml)
                    offset = end

                    // Update progress bar
                    const pct = total > 0 ? Math.round((offset / total) * 100) : 100
                    $('#roles-load-progress .progress-bar').css('width', pct + '%').attr('aria-valuenow', pct)
                    $('#roles-load-progress .roles-load-text').text(offset + ' / ' + total)

                    if (offset < total) {
                        // Yield to browser so it can paint the progress update
                        setTimeout(renderBatch, 0)
                    } else {
                        // All rows rendered — finalize
                        $('#role-details input[type="checkbox"]').iCheck({
                            checkboxClass: 'icheckbox_flat-blue'
                        })
                        $('.infotip').tooltip()

                        $('#folders-depth').empty().change()
                        $('#folders-depth').append('<option value="all"><?php echo $lang->get('all'); ?></option>')
                        for (let x = 1; x < max_folder_depth; x++) {
                            $('#folders-depth').append('<option value="' + x + '">' + x + '</option>')
                        }
                        // Restore saved depth or default to 2 (if option exists)
                        const savedDepth = store.get('teampassUser') && store.get('teampassUser').rolesDepthFilter !== undefined
                            ? store.get('teampassUser').rolesDepthFilter
                            : '2'
                        const targetDepth = $('#folders-depth option[value="' + savedDepth + '"]').length > 0
                            ? savedDepth
                            : ($('#folders-depth option[value="2"]').length > 0 ? '2' : 'all')
                        $('#folders-depth').val(targetDepth).change()

                        $('#roles-load-progress').hide()
                        toastr.success('<?php echo $lang->get('done'); ?>', '', { timeOut: 2000, closeButton: true })

                        // Re-apply comparison column if one is selected
                        if ($('#folders-compare').val() !== '') {
                            buildRoleCompare(store.get('teampassUser').compareRole)
                        }
                    }
                }

                renderBatch()
            }
        )
    }

    var operationOngoin = false;
    $(document).on('ifChecked', '.folder-select', function() {
        if (operationOngoin === false) {
            operationOngoin = true;

            // Show spinner
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Show selection of folders
            var selected_cb = $(this),
                id = $(this).data('id');

            // change language string
            if ($(this).attr('id') === 'cb-all-selection') {
                $('#cb-all-selection-lang').html('<?php echo $lang->get('unselect_all'); ?>');
            }

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
                            $('#cb-' + value).iCheck('check');
                        });
                    }
                    operationOngoin = false;

                    toastr.remove();
                    toastr.info(
                        '<?php echo $lang->get('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                }
            );
        }
    });

    $(document).on('ifUnchecked', '.folder-select', function() {
        if (operationOngoin === false) {
            operationOngoin = true;

            // Show spinner
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Show selection of folders
            var selected_cb = $(this),
                id = $(this).data('id');

            // change language string
            if ($(this).attr('id') === 'cb-all-selection') {
                $('#cb-all-selection-lang').html('<?php echo $lang->get('select_all'); ?>');
            }

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
                    toastr.info(
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
     * Handle the form for folder access rights change
     */
    var currentFolderEdited = '';
    // Open the rights sidebar when the user clicks any cell of a matrix row
    $(document).on('click', '.modify', function() {
        var folderId = $(this).data('id')
        var folderAccess = $(this).data('access')
        var folderTitle = $(this).closest('tr').find('.folder-name').text()
        openRightsSidebar(folderId, folderAccess, folderTitle)
    })

    /**
     * Open the rights edit sidebar for a given folder.
     */
    function openRightsSidebar(folderId, folderAccess, folderTitle) {
        _sidebarFolderId = folderId

        // Highlight the row being edited
        $('#table-role-details tbody tr').removeClass('editing-active')
        $('tr[data-id="' + folderId + '"]').addClass('editing-active')

        // Header: show folder name or count when multi-selection is active
        var checkedCount = $('input.folder-select:checked').not('#cb-all-selection').length
        if (checkedCount > 1) {
            $('#sidebar-role-icon').removeClass('fa-graduation-cap').addClass('fa-layer-group')
            $('#sidebar-role-info').text(checkedCount + ' <?php echo $lang->get('folders'); ?>')
        } else {
            $('#sidebar-role-icon').removeClass('fa-layer-group').addClass('fa-graduation-cap')
            $('#sidebar-role-info').text(folderTitle)
        }

        // Reset all controls to a clean state
        $('#sb-right-no-delete, #sb-right-no-edit').iCheck('uncheck')
        $('#sb-right-no-delete, #sb-right-no-edit').iCheck('disable')
        $('#sb-propagate-rights').iCheck('uncheck')

        // Pre-fill based on current access level
        if (folderAccess === 'R') {
            $('#sb-right-read').iCheck('check')
        } else if (folderAccess === 'none' || folderAccess === '') {
            $('#sb-right-noaccess').iCheck('check')
        } else if (folderAccess === 'W') {
            $('#sb-right-write').iCheck('check')
            $('#sb-right-no-delete, #sb-right-no-edit').iCheck('enable')
        } else if (folderAccess === 'ND') {
            $('#sb-right-write').iCheck('check')
            $('#sb-right-no-delete, #sb-right-no-edit').iCheck('enable')
            $('#sb-right-no-delete').iCheck('check')
        } else if (folderAccess === 'NE') {
            $('#sb-right-write').iCheck('check')
            $('#sb-right-no-delete, #sb-right-no-edit').iCheck('enable')
            $('#sb-right-no-edit').iCheck('check')
        } else if (folderAccess === 'NDNE') {
            $('#sb-right-write').iCheck('check')
            $('#sb-right-no-delete, #sb-right-no-edit').iCheck('enable')
            $('#sb-right-no-delete, #sb-right-no-edit').iCheck('check')
        }

        // Slide in
        $('#role-edit-overlay').fadeIn(200)
        $('#role-edit-sidebar').addClass('open')
    }

    /**
     * Close the rights edit sidebar.
     */
    function closeRightsSidebar() {
        $('#role-edit-sidebar').removeClass('open')
        $('#role-edit-overlay').fadeOut(200)
        $('#table-role-details tbody tr').removeClass('editing-active')
        _sidebarFolderId = ''
    }

    // Sidebar close triggers
    $('#sidebar-role-close, #sidebar-role-cancel').on('click', closeRightsSidebar)
    $('#role-edit-overlay').on('click', closeRightsSidebar)
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#role-edit-sidebar').hasClass('open')) {
            closeRightsSidebar()
        }
    })

    // Sidebar submit
    $('#sidebar-role-submit').on('click', function() {
        toastr.remove()
        toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>')

        // Collect selected folder IDs; fall back to the clicked folder if none selected
        var selectedFolders = []
        $('input.folder-select:checked').not('#cb-all-selection').each(function() {
            selectedFolders.push($(this).data('id'))
        })
        if (selectedFolders.length === 0) {
            selectedFolders.push(_sidebarFolderId)
        }

        // Determine access type
        var access = $('input[name=sb-right]:checked').data('type')
        if ($('#sb-right-no-delete').is(':checked') === true && $('#sb-right-no-edit').is(':checked') === true) {
            access = 'NDNE'
        } else if ($('#sb-right-no-delete').is(':checked') === true) {
            access = 'ND'
        } else if ($('#sb-right-no-edit').is(':checked') === true) {
            access = 'NE'
        }

        var postData = {
            'roleId': $('#roles-list').val(),
            'selectedFolders': selectedFolders,
            'access': access,
            'propagate': $('#sb-propagate-rights').is(':checked') === true ? 1 : 0,
        }

        closeRightsSidebar()

        $.post(
            'sources/roles.queries.php', {
                type: 'change_access_right_on_folder',
                data: prepareExchangedData(JSON.stringify(postData), 'encode', '<?php echo $session->get('key'); ?>'),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>')
                if (data.error === true) {
                    toastr.remove()
                    toastr.error(data.message, '', { timeOut: 5000, progressBar: true })
                } else {
                    refreshMatrix($('#roles-list').val())
                    toastr.remove()
                }
            }
        )
    })

    // Focus label input when the role definition modal opens
    $('#modal-role-definition').on('shown.bs.modal', function() {
        $('#form-role-label').trigger('focus')
    })
    // Reset delete checkbox when deletion modal is hidden
    $('#modal-role-deletion').on('hidden.bs.modal', function() {
        $('#form-role-delete').iCheck('uncheck')
    })

    /**
     * Handle toolbar and form buttons
     */
    $(document).on('click', 'button', function() {
        var selectedFolderText = $('#roles-list').find(':selected').text()

        if ($(this).data('action') === 'new') {
            // Open modal for new role (blank form)
            $('#modal-role-definition-header').text('<?php echo $lang->get('new'); ?>')
            $('#form-role-label').val('')
            $('#form-role-privilege').iCheck('uncheck')
            $('#form-complexity-list').val('').trigger('change')
            store.update('teampassApplication', function(app) { app.formUserAction = 'add_role' })
            $('#modal-role-definition').modal('show')

        } else if ($(this).data('action') === 'edit' && $('#button-edit').hasClass('disabled') === false) {
            // Open modal pre-filled with current role values
            $('#modal-role-definition-header').text('<?php echo $lang->get('edit'); ?> - ' + selectedFolderText)
            $('#form-role-label').val(selectedFolderText)
            $('#form-complexity-list').val($('#roles-list').find(':selected').data('complexity')).trigger('change')
            if (parseInt($('#roles-list').find(':selected').data('allow-edit-all')) === 1) {
                $('#form-role-privilege').iCheck('check')
            } else {
                $('#form-role-privilege').iCheck('uncheck')
            }
            store.update('teampassApplication', function(app) { app.formUserAction = 'edit_role' })
            $('#modal-role-definition').modal('show')

        } else if ($(this).data('action') === 'delete' && $('#button-delete').hasClass('disabled') === false) {
            // Open deletion confirmation modal
            var safeText = $('<div>').text(selectedFolderText).html()
            $('#span-role-delete').html('<b>' + safeText + '</b>')
            $('#modal-role-deletion').modal('show')

        } else if ($(this).data('action') === 'ldap') {
            // Open LDAP sync modal and load groups
            $('#modal-roles-ldap-sync').modal('show')
            refreshLdapGroups()

        } else if ($(this).data('action') === 'ldap-refresh') {
            refreshLdapGroups()

        } else if ($(this).data('action') === 'submit-edition') {
            // Save new or edited role
            toastr.remove()
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>')

            var value = fieldDomPurifierWithWarning('#form-role-label')
            if (value === false) { return false }
            $('#form-role-label').val(value)

            var data = {
                'label': value,
                'complexity': $('#form-complexity-list').val() === null ? 0 : $('#form-complexity-list').val(),
                'folderId': $('#roles-list').find(':selected').val(),
                'allowEdit': $('#form-role-privilege').is(':checked') === true ? 1 : 0,
                'action': store.get('teampassApplication').formUserAction
            }

            $.post(
                'sources/roles.queries.php', {
                    type: 'change_role_definition',
                    data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>')

                    if (data.error === true) {
                        toastr.remove()
                        toastr.error(data.message, '', { timeOut: 5000, progressBar: true })
                    } else {
                        $('#modal-role-definition').modal('hide')

                        if (store.get('teampassApplication').formUserAction === 'edit_role') {
                            $('#role-detail-header').html(
                                $('#form-role-label').val() +
                                '<i class="' + data.icon + ' infotip ml-3" title="<?php echo $lang->get('complexity'); ?>: ' +
                                $('#form-complexity-list').find(':selected').text() + '"></i>' +
                                (parseInt(data.allow_pw_change) === 1 ?
                                    '<i class="ml-3 fas fa-exclamation-triangle text-warning infotip" title="<?php echo $lang->get('role_can_edit_any_visible_item'); ?>"></i>' : '')
                            )
                            $('.infotip').tooltip()
                        } else {
                            var newOption = new Option($('#form-role-label').val(), data.new_role_id, false, true)
                            $('#roles-list').append(newOption).trigger('change')
                        }

                        // Update the select2 option metadata
                        $('#roles-list').select2('destroy')
                        var selectedOption = $('#roles-list option[value=' + $('#roles-list').find(':selected').val() + ']')
                        selectedOption.text($('#form-role-label').val())
                        selectedOption.data('allow-edit-all', data.allow_pw_change)
                        selectedOption.data('complexity-text', data.text)
                        selectedOption.data('complexity-icon', data.icon)
                        selectedOption.data('complexity', data.value)
                        $('#roles-list').select2({
                            language: '<?php echo $session->get('user-language_code'); ?>',
                            placeholder: '<?php echo $lang->get('select_a_role'); ?>',
                            allowClear: true
                        })

                        toastr.remove()
                        toastr.info('<?php echo $lang->get('done'); ?>', '', { timeOut: 1000 })
                    }
                }
            )

        } else if ($(this).data('action') === 'submit-deletion') {
            // Delete the selected role
            if ($('#form-role-delete').is(':checked') === false) { return false }

            toastr.remove()
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>')

            var data = { 'roleId': $('#roles-list').find(':selected').val() }

            $.post(
                'sources/roles.queries.php', {
                    type: 'delete_role',
                    data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>')

                    if (data.error === true) {
                        toastr.remove()
                        toastr.error(data.message, '', { timeOut: 5000, progressBar: true })
                    } else {
                        $('#modal-role-deletion').modal('hide')

                        // Remove deleted role from select and hide matrix
                        $('#roles-list').select2('destroy')
                        $('#roles-list option[value=' + $('#roles-list').find(':selected').val() + ']').remove()
                        $('#roles-list').select2({
                            language: '<?php echo $session->get('user-language_code'); ?>',
                            placeholder: '<?php echo $lang->get('select_a_role'); ?>',
                            allowClear: true
                        })
                        $('#card-role-details').addClass('hidden')
                        $('#button-edit, #button-delete').addClass('disabled')

                        toastr.remove()
                        toastr.info('<?php echo $lang->get('done'); ?>', '', { timeOut: 1000 })
                    }
                }
            )

        } else if ($(this).data('action') === 'cancel') {
            $('.temp-row').remove()

        } else if ($(this).data('action') === 'do-adgroup-role-mapping') {
            var groupId = $(this).data('id'),
                roleId = parseInt($('.select-role').val()),
                groupTitle = $('.select-role option:selected').text();

            if (isNaN(roleId)) {
                return false;
            }

            // Show spinner
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Prepare data
            var data = {
                'roleId': roleId,
                'adGroupId': groupId,
                'adGroupLabel': groupTitle,
            }
            console.log(data)

            // Launch action
            $.post(
                'sources/roles.queries.php', {
                    type: 'map_role_with_adgroup',
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    //decrypt data
                    data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');

                    if (data.error === true) {
                        // ERROR
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        // Manage change in select
                        currentThis.html(groupTitle);

                        // Clean
                        $('.temp-row').remove();

                        // OK
                        toastr.remove();
                        toastr.info(
                            '<?php echo $lang->get('done'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );
                    }
                }
            );
            //---
        }
        currentFolderEdited = '';
    });


    /**
     * Refreshing list of groups from LDAP
     *
     * @return void
     */
    function refreshLdapGroups() {
        // FIND ALL USERS IN LDAP
        //toastr.remove();
        toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i><span class="close-toastr-progress"></span>');

        $('#row-ldap-body')
            .addClass('overlay')
            .html('');

        $.post(
            "sources/roles.queries.php", {
                type: "get_list_of_groups_in_ldap",
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');
                console.log(data)

                if (data.error === true) {
                    // ERROR
                    //toastr.remove();
                    toastr.error(
                        data.message,
                        '<?php echo $lang->get('caution'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    // loop on groups list
                    var html = '',
                        groupsNumber = 0,
                        group,
                        group_id;
                    var entry;
                    $.each(data.ldap_groups, function(i, ad_group) {
                        if (ad_group.ad_group_id !== -1) {
                            // Get group name
                            html += '<tr>' +
                                '<td>' + ad_group.ad_group_title + '</td>' +
                                '<td><i class="fa-solid fa-arrow-right-long"></i></td>' +
                                '<td class="pointer change_adgroup_mapping" data-id="'+ad_group.ad_group_id+'">' + 
                                    (ad_group.role_title === "" ? '<i class="fa-solid fa-xmark text-danger infotip" title="<?php echo $lang->get('none'); ?>"></i>' : ad_group.role_title) + 
                                '</td>' +
                                '</tr>';
                        }
                    });

                    $('#row-ldap-body').html(html);
                    $('#row-ldap-body').removeClass('overlay');
                    $('.infotip').tooltip('update');

                    // prepare select
                    rolesSelectOptions = '<option value="-1"><?php echo $lang->get('none'); ?></option>';;
                    $.each(data.teampass_groups, function(i, role) {
                        rolesSelectOptions += '<option value="' + role.id + '">' + role.title + '</option>';
                    });
                    store.update(
                        'teampassApplication',
                        function(teampassApplication) {
                            teampassApplication.rolesSelectOptions = rolesSelectOptions;
                        }
                    );


                    // Inform user
                    toastr.success(
                        '<?php echo $lang->get('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                    $('.close-toastr-progress').closest('.toast').remove();
                }
            }
        );
    }

    /**
     * Refreshing list of groups from LDAP
     *
     * @return void
     */
    $(document).on('click', '.change_adgroup_mapping', function() {
        // Init
        currentThis = $(this);
        var currentRow = $(this).closest('tr'),
            groupId = $(this).data('id');

        // Now show
        $(currentRow).after(
            '<tr class="temp-row"><td colspan="' + $(currentRow).children('td').length + '">' +
            '<div class="card card-warning card-outline">' +
            '<div class="card-body">' +
            '<div class="form-group ml-2 mt-2"><?php echo $lang->get('select_adgroup_mapping'); ?></div>' +
            '<div class="form-group ml-2">' +
            '<select class="select-role form-control form-item-control">' +
                store.get('teampassApplication').rolesSelectOptions + '</select>' +
            '</div>' +
            '<div class="card-footer">' +
            '<button type="button" class="btn btn-warning tp-action" data-action="do-adgroup-role-mapping" data-id="' + groupId + '"><?php echo $lang->get('submit'); ?></button>' +
            '<button type="button" class="btn btn-default float-right tp-action" data-action="cancel"><?php echo $lang->get('cancel'); ?></button>' +
            '</div>' +
            '</div>' +
            '</td></tr>'
        );
    });

    /**
     * Enable/disable granular right checkboxes based on selected radio (W vs R/none)
     */
    $(document).on('ifChecked', '.form-radio-input', function() {
        if ($(this).data('type') === 'W') {
            $('.cb-sb-right').iCheck('enable')
        } else {
            $('.cb-sb-right').iCheck('disable')
            $('.cb-sb-right').iCheck('uncheck')
        }
    })

    /**
     * Handle option when role is displayed
     */
    $(document).on('change', '#folders-depth', function() {
        const depth = $(this).val()

        // Persist selection (only when a valid value is set)
        if (depth !== null && depth !== '') {
            store.update('teampassUser', function(teampassUser) {
                teampassUser.rolesDepthFilter = depth
            })
        }

        if (depth === 'all' || depth === null || depth === '') {
            $('tr').removeClass('hidden');
        } else {
            const depthInt = parseInt(depth, 10)
            // Only filter rows that explicitly have a data-level attribute
            $('tr[data-level]').each(function() {
                if (parseInt($(this).data('level'), 10) <= depthInt) {
                    $(this).removeClass('hidden');
                } else {
                    $(this).addClass('hidden');
                }
            });
        }
    });

    /**
     * Handle search criteria
     */
    $('#folders-search').on('keyup', function() {
        var criteria = $(this).val();
        $('.folder-name').filter(function() {
            if ($(this).text().toLowerCase().indexOf(criteria) !== -1) {
                $(this).closest('tr').removeClass('hidden');
            } else {
                $(this).closest('tr').addClass('hidden');
            }
        });
    });

    $(document).on('change', '#folders-compare', function() {
        if ($(this).val() === '') {
            $('#table-role-details tr').find('th:last-child, td:last-child').addClass('hidden');
        } else {
            // Show spinner
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Load the rights for this folder
            $.post(
                'sources/roles.queries.php', {
                    type: 'build_matrix',
                    role_id: $('#folders-compare').val(),
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                    if (data.error !== false) {
                        // Show error
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        buildRoleCompare(data.matrix);

                        // Store in teampassUser
                        store.update(
                            'teampassUser',
                            function(teampassUser) {
                                teampassUser.compareRole = data.matrix;
                            }
                        );

                        // Inform user
                        toastr.remove();
                        toastr.info(
                            '<?php echo $lang->get('done'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );
                    }
                }
            );
        }
    });

    function buildRoleCompare(data) {
        // Loop on array
        $(data).each(function(i, value) {
            var row = $('tr[data-id="' + value.id + '"]');
            if (row !== undefined) {
                // Access
                access = '';
                if (value.access === 'W') {
                    access = '<i class="fas fa-indent mr-2 text-success infotip" title="<?php echo $lang->get('add_allowed'); ?>"></i>' +
                        '<i class="fas fa-pen mr-2 text-success infotip" title="<?php echo $lang->get('edit_allowed'); ?>"></i>' +
                        '<i class="fas fa-eraser mr-2 text-success infotip" title="<?php echo $lang->get('delete_allowed'); ?>"></i>';
                } else if (value.access === 'ND') {
                    access = '<i class="fas fa-indent mr-2 text-success infotip" title="<?php echo $lang->get('add_allowed'); ?>"></i>' +
                        '<i class="fas fa-pen mr-2 text-success infotip" title="<?php echo $lang->get('edit_allowed'); ?>"></i>' +
                        '<i class="fas fa-eraser mr-2 text-danger infotip" title="<?php echo $lang->get('delete_not_allowed'); ?>"></i>';
                } else if (value.access === 'NE') {
                    access = '<i class="fas fa-indent mr-2 text-success infotip" title="<?php echo $lang->get('add_allowed'); ?>"></i>' +
                        '<i class="fas fa-pen mr-2 text-danger infotip" title="<?php echo $lang->get('edit_not_allowed'); ?>"></i>' +
                        '<i class="fas fa-eraser mr-2 text-success infotip" title="<?php echo $lang->get('delete_allowed'); ?>"></i>';
                } else if (value.access === 'NDNE') {
                    access = '<i class="fas fa-indent mr-2 text-success infotip" title="<?php echo $lang->get('add_allowed'); ?>"></i>' +
                        '<i class="fas fa-pen mr-2 text-danger infotip" title="<?php echo $lang->get('edit_not_allowed'); ?>"></i>' +
                        '<i class="fas fa-eraser mr-2 text-danger infotip" title="<?php echo $lang->get('delete_not_allowed'); ?>"></i>';
                } else if (value.access === 'R') {
                    access = '<i class="fas fa-book-reader mr-2 text-warning infotip" title="<?php echo $lang->get('read_only'); ?>"></i>';
                } else {
                    access = '<i class="fas fa-ban mr-2 text-danger infotip" title="<?php echo $lang->get('no_access'); ?>"></i>';
                }
                row.find('td:last-child').html(access).removeClass('hidden');
            }
        });

        // Tooltips
        $('.infotip').tooltip();
    }
</script>
