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
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}
?>


<script src="./assets/js/folders-tree.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
<script type='text/javascript'>
    // Globals
    var currentThis = ''
    let _matrixGeneration = 0
    let _matrixRequest = null
    let _matrixLoading = false
    let _matrixRoleId = ''
    let _roleTree = new TeampassFolderTree()
    let _visibleLimit = 100
    let _syncingRoleSelection = false
    let _roleSearchTimer = null
    let _compareGeneration = 0
    let _compareRequest = null
    let _compareAccess = new Map()
    const _renderedRoleMarkup = new Map()
    const _roleLabels = <?php echo json_encode(array_combine(
        ['add_allowed', 'edit_allowed', 'delete_allowed', 'edit_not_allowed', 'delete_not_allowed', 'read_only', 'no_access', 'collapse'],
        array_map(fn($key) => $lang->get($key), ['add_allowed', 'edit_allowed', 'delete_allowed', 'edit_not_allowed', 'delete_not_allowed', 'read_only', 'no_access', 'collapse'])
    ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>

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
        toastr.remove()
        closeRightsSidebar()
        if ($(this).find(':selected').text() === '') {
            cancelMatrixRequests()
            _matrixRoleId = ''
            _roleTree = new TeampassFolderTree()
            renderRoleView()
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
                    '') +
                (parseInt($(this).find(':selected').data('allow-security-posture-fix')) === 0 ?
                    '<i class="ml-3 fa-solid fa-wrench text-muted infotip" ' +
                    'title="<?php echo $lang->get('role_security_posture_fix_disabled'); ?>"></i>' :
                    '')
            );

            $('.infotip').tooltip();

            refreshMatrix(selectedRoleId);
        }
    });

    /**
     * Build identical escaped permission badges for both matrix columns.
     */
    function buildMatrixAccessHtml(accessType) {
        const icon = (css, label) => '<i class="fas ' + css + ' mr-2 infotip" title="' + htmlEncode(_roleLabels[label]) + '"></i>'
        if (['W', 'ND', 'NE', 'NDNE'].includes(accessType)) {
            const noEdit = accessType === 'NE' || accessType === 'NDNE'
            const noDelete = accessType === 'ND' || accessType === 'NDNE'
            return icon('fa-indent text-success', 'add_allowed') +
                icon('fa-pen ' + (noEdit ? 'text-danger' : 'text-success'), noEdit ? 'edit_not_allowed' : 'edit_allowed') +
                icon('fa-eraser ' + (noDelete ? 'text-danger' : 'text-success'), noDelete ? 'delete_not_allowed' : 'delete_allowed')
        }
        return accessType === 'R' ? icon('fa-book-reader text-warning', 'read_only') : icon('fa-ban text-danger', 'no_access')
    }

    /** Render one folder's current and comparison permissions with escaped labels. */
    function buildMatrixRowHtml(value) {
        const access = buildMatrixAccessHtml(value.access)
        const comparing = ($('#folders-compare').val() || '') !== '' && _compareAccess.size > 0

        // Folder titles and the path built from them are user-supplied. purifyData() drops
        // tags, but a title stored double-encoded comes back out as live markup, so encode
        // before interpolating — same treatment as the folder table in users.js.php.
        var path = ''
        $(value.path).each(function(j, valuePath) {
            const safePath = htmlEncode(valuePath)
            path = path === '' ? safePath : path + ' / ' + safePath
        })

        var indent = (parseInt(value.ident) - 1) * 16
        var folderIcon = value.ident === 1
            ? '<i class="fas fa-folder text-warning mr-1"></i>'
            : '<i class="fas fa-folder-open text-warning mr-1" style="opacity:.7"></i>'

        const toggle = value.numOfChildren > 0
            ? '<button type="button" class="btn btn-link btn-sm p-0 mr-1 role-collapse" data-id="' + Number(value.id) + '" aria-label="' + htmlEncode(_roleLabels.collapse) + '" aria-expanded="' + _roleTree.expanded.has(Number(value.id)) + '"><i class="fas ' + (_roleTree.expanded.has(Number(value.id)) ? 'fa-folder-minus' : 'fa-folder-plus') + '"></i></button>'
            : ''

        return '<tr data-level="' + value.ident + '" class="' + (value.ident === 1 ? 'parent' : 'descendant') + '" data-id="' + value.id + '">' +
            '<td width="35px"><input type="checkbox" id="cb-' + value.id + '" data-id="' + value.id + '" class="folder-select"></td>' +
            '<td class="pointer modify folder-name" data-id="' + value.id + '" data-access="' + value.access + '" style="padding-left:' + indent + 'px">' + toggle + folderIcon + htmlEncode(value.title) + '</td>' +
            '<td class="font-italic pointer modify" data-id="' + value.id + '" data-access="' + value.access + '"><small class="text-muted">' + path + '</small></td>' +
            '<td class="pointer modify td-100 text-center" data-id="' + value.id + '" data-access="' + value.access + '">' + access + '</td>' +
            '<td class="' + (comparing ? '' : 'hidden ') + 'compare tp-borders td-100 text-center">' +
                (comparing ? buildMatrixAccessHtml(_compareAccess.get(Number(value.id)) || 'none') : '') + '</td>' +
            '</tr>'
    }

    /**
     * Load the authorized role snapshot and render only a bounded view.
     */
    function decodeRoleMatrixResponse(response) {
        // Folder names are plain text, including literal angle brackets. The generic
        // purifier treats decoded <...> as tags, so decode labels once and escape at
        // every rendering sink instead. Never pass the raw response to an HTML sink.
        const data = prepareExchangedData(response, 'decode', '<?php echo $session->get('key'); ?>', '', '', false)
        if (!data || data.error !== false) {
            return { error: true, message: purifyServerData(data && data.message ? data.message : '') }
        }
        const accessTypes = ['W', 'R', 'ND', 'NE', 'NDNE', 'none']
        return { error: false, matrix: data.matrix.map(row => ({
            id: Number(row.id),
            parentId: Number(row.parentId),
            level: Number(row.level),
            ident: Number(row.ident),
            parents: row.parents.map(Number),
            path: row.path.map(decodeStorageEntities),
            title: decodeStorageEntities(row.title),
            access: accessTypes.includes(row.access) ? row.access : 'none'
        })) }
    }

    /** Load the authorized snapshot; superseded responses cannot change the view. */
    function refreshMatrix(selectedRoleId) {
        cancelMatrixRequests()
        const generation = _matrixGeneration
        if (_matrixRoleId !== String(selectedRoleId)) {
            _roleTree = new TeampassFolderTree()
            _renderedRoleMarkup.clear()
            $('#role-details .infotip').tooltip('dispose')
            $('#role-details').html('<table id="table-role-details" class="table table-hover table-striped" style="width:100%"><tbody></tbody></table>')
        }
        _matrixRoleId = String(selectedRoleId)
        _matrixLoading = true
        closeRightsSidebar()
        $('#card-role-details').removeClass('hidden')
        $('#roles-load-progress').show()
        $('#roles-load-progress .progress-bar').css('width', '100%').attr('aria-valuenow', 100)
        $('#roles-load-progress .roles-load-text').text(<?php echo json_encode($lang->get('loading'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)
        _matrixRequest = $.post('sources/roles.queries.php', {
            type: 'build_matrix',
            role_id: selectedRoleId,
            key: '<?php echo $session->get('key'); ?>'
        }).done(function(response) {
            if (generation !== _matrixGeneration) return
            const data = decodeRoleMatrixResponse(response)
            if (data.error !== false) {
                _roleTree = new TeampassFolderTree()
                toastr.error(data.message)
                return
            }
            _roleTree.replace(data.matrix)
            const maxDepth = data.matrix.reduce((max, row) => Math.max(max, Number(row.level)), 0)
            const allLabel = <?php echo json_encode($lang->get('all'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>

            $('#folders-depth').empty().append(new Option(allLabel, 'all'))
            for (let depth = 1; depth <= maxDepth; depth++) {
                $('#folders-depth').append(new Option(String(depth), String(depth)))
            }
            const stored = store.get('teampassUser') || {}
            const depth = stored.rolesDepthFilter || (maxDepth >= 2 ? '2' : 'all')
            $('#folders-depth').val(depth === 'all' || Number(depth) <= maxDepth ? depth : 'all')
            _visibleLimit = 100
            _matrixLoading = false
            renderRoleView()
            refreshRoleComparison()
        }).fail(function(xhr, status) {
            if (status !== 'abort' && generation === _matrixGeneration) {
                _roleTree = new TeampassFolderTree()
                toastr.error(<?php echo json_encode($lang->get('error'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)
            }
        }).always(function() {
            if (generation !== _matrixGeneration) return
            _matrixLoading = false
            _matrixRequest = null
            $('#roles-load-progress').hide()
            renderRoleView()
        })
    }

    /** Invalidate pending callbacks before aborting their requests, including on clear. */
    function cancelMatrixRequests() {
        ++_matrixGeneration
        ++_compareGeneration
        if (_matrixRequest) _matrixRequest.abort()
        if (_compareRequest) _compareRequest.abort()
        _matrixRequest = null
        _compareRequest = null
        _matrixLoading = false
        _compareAccess.clear()
        $('#roles-load-progress').hide()
    }

    /** Render at most the requested number of rows and reuse unchanged row widgets. */
    function renderRoleView() {
        if (_matrixLoading) return
        const rows = _roleTree.visible({
            depth: $('#folders-depth').val() || 'all',
            term: $('#folders-search').val() || ''
        })
        const displayed = rows.slice(0, _visibleLimit)
        const $body = $('#table-role-details > tbody')
        if (!$body.length) return
        const desiredIds = new Set(displayed.map(row => Number(row.id)))
        const existing = new Map()
        $body.children('tr[data-id]').each(function() {
            const id = Number(this.dataset.id)
            if (desiredIds.has(id)) existing.set(id, this)
            else {
                $(this).find('.infotip').tooltip('dispose')
                $(this).remove()
                _renderedRoleMarkup.delete(id)
            }
        })
        const added = []
        let nextRow = $body[0].firstChild
        displayed.forEach(function(row) {
            const id = Number(row.id)
            const markup = buildMatrixRowHtml(row)
            let node = existing.get(id)
            if (!node || _renderedRoleMarkup.get(id) !== markup) {
                if (node) {
                    if (node === nextRow) nextRow = node.nextSibling
                    $(node).find('.infotip').tooltip('dispose')
                    $(node).remove()
                }
                node = $(markup)[0]
                _renderedRoleMarkup.set(id, markup)
                added.push(node)
            }
            if (node === nextRow) nextRow = node.nextSibling
            else $body[0].insertBefore(node, nextRow)
            $(node).toggleClass('editing-active', Number(_sidebarFolderId) === id)
        })
        _syncingRoleSelection = true
        added.forEach(function(node) {
            $(node).find('input.folder-select').iCheck({ checkboxClass: 'icheckbox_flat-blue' })
            $(node).find('.infotip').tooltip()
        })
        $body.find('input.folder-select').each(function() {
            const checked = _roleTree.selected.has(Number(this.dataset.id))
            if (this.checked !== checked) $(this).iCheck(checked ? 'check' : 'uncheck')
        })
        const allChecked = _roleTree.rows.length > 0 && _roleTree.selected.size === _roleTree.rows.length
        if ($('#cb-all-selection').is(':checked') !== allChecked) {
            $('#cb-all-selection').iCheck(allChecked ? 'check' : 'uncheck')
        }
        _syncingRoleSelection = false
        $('#cb-all-selection-lang').text(allChecked
            ? <?php echo json_encode($lang->get('unselect_all'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>
            : <?php echo json_encode($lang->get('select_all'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)
        $('#roles-show-more').prop('hidden', displayed.length >= rows.length)
        const count = <?php echo json_encode($lang->get('folders_view_count'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>

        $('#roles-view-count').text(count.replace('{shown}', displayed.length).replace('{total}', rows.length).replace('{selected}', _roleTree.selected.size))
    }

    /** Keep the complete authorized selection independent of collapsed or unrendered rows. */
    $(document).on('ifChecked ifUnchecked', '.folder-select', function(event) {
        if (_syncingRoleSelection || _matrixLoading) return
        const checked = event.type === 'ifChecked'
        if (this.id === 'cb-all-selection') {
            _roleTree.selected = checked ? new Set(_roleTree.byId.keys()) : new Set()
        } else {
            _roleTree.selectBranch($(this).data('id'), checked)
        }
        renderRoleView()
    })

    $('#roles-show-more').on('click', function() {
        _visibleLimit += 100
        renderRoleView()
    })

    $(document).on('click', '.role-collapse', function(event) {
        event.stopPropagation()
        if (_matrixLoading) return
        _roleTree.toggle($(this).data('id'))
        renderRoleView()
    })

    /**
     * Handle the form for folder access rights change
     */
    var currentFolderEdited = '';
    // Open the rights sidebar when the user clicks any cell of a matrix row
    $(document).on('click', '.modify', function(event) {
        if (_matrixLoading || $(event.target).closest('.role-collapse').length) return
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
        const selectedFolder = _roleTree.selectedRows()[0]
        if (_roleTree.selected.size === 1 && selectedFolder) {
            folderTitle = selectedFolder.title
            folderAccess = selectedFolder.access
        }

        // Highlight the row being edited
        $('#table-role-details tbody tr').removeClass('editing-active')
        $('tr[data-id="' + folderId + '"]').addClass('editing-active')

        // Header: show folder name or count when multi-selection is active
        const checkedCount = _roleTree.selected.size
        if (checkedCount > 1) {
            $('#sidebar-role-icon').removeClass('fa-graduation-cap').addClass('fa-layer-group')
            $('#sidebar-role-info').text(checkedCount + ' ' + <?php echo json_encode($lang->get('folders'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)
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
        // Collect selected folder IDs; fall back to the clicked folder if none selected
        if (_matrixLoading) return
        const selectedFolders = _roleTree.selectedRows().map(row => Number(row.id))
        if (selectedFolders.length === 0 && _roleTree.byId.has(Number(_sidebarFolderId))) {
            selectedFolders.push(Number(_sidebarFolderId))
        }
        if (selectedFolders.length === 0) return
        const submittedRoleId = _matrixRoleId
        const generation = _matrixGeneration
        toastr.remove()
        toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>')

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
            'roleId': submittedRoleId,
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
                if (generation !== _matrixGeneration || submittedRoleId !== _matrixRoleId) return
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
            $('#form-role-security-posture-fix').iCheck('check')
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
            if (parseInt($('#roles-list').find(':selected').data('allow-security-posture-fix')) === 0) {
                $('#form-role-security-posture-fix').iCheck('uncheck')
            } else {
                $('#form-role-security-posture-fix').iCheck('check')
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
                // Only rendered for administrators: -1 keeps the stored value.
                'allowSecurityPostureFix': $('#form-role-security-posture-fix').length === 0 ? -1 :
                    ($('#form-role-security-posture-fix').is(':checked') === true ? 1 : 0),
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
                                    '<i class="ml-3 fas fa-exclamation-triangle text-warning infotip" title="<?php echo $lang->get('role_can_edit_any_visible_item'); ?>"></i>' : '') +
                                (parseInt(data.allow_security_posture_fix) === 0 ?
                                    '<i class="ml-3 fa-solid fa-wrench text-muted infotip" title="<?php echo $lang->get('role_security_posture_fix_disabled'); ?>"></i>' : '')
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
                        selectedOption.data('allow-security-posture-fix', data.allow_security_posture_fix)
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
                        cancelMatrixRequests()
                        _matrixRoleId = ''
                        _roleTree = new TeampassFolderTree()
                        renderRoleView()
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
                        rolesSelectOptions += '<option value="' + role.id + '">' + htmlEncode(role.title) + '</option>';
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
        if (depth !== null && depth !== '') {
            store.update('teampassUser', function(user) { user.rolesDepthFilter = depth })
        }
        applyRoleFilters()
    })

    /** Apply depth and full-path search together without dropping hidden selections. */
    function applyRoleFilters() {
        _visibleLimit = 100
        renderRoleView()
    }

    $('#folders-search').on('input', function() {
        clearTimeout(_roleSearchTimer)
        _roleSearchTimer = setTimeout(applyRoleFilters, 200)
    })

    $(document).on('change', '#folders-compare', refreshRoleComparison)

    /** Index comparison permissions once; rows rendered later use the same snapshot. */
    function refreshRoleComparison() {
        const generation = ++_compareGeneration
        const matrixGeneration = _matrixGeneration
        if (_compareRequest) _compareRequest.abort()
        _compareRequest = null
        _compareAccess.clear()
        renderRoleView()
        const roleId = $('#folders-compare').val() || ''
        if (roleId === '' || _matrixRoleId === '' || _matrixLoading) return
        _compareRequest = $.post('sources/roles.queries.php', {
            type: 'build_matrix',
            role_id: roleId,
            key: '<?php echo $session->get('key'); ?>'
        }).done(function(response) {
            if (generation !== _compareGeneration || matrixGeneration !== _matrixGeneration) return
            const data = decodeRoleMatrixResponse(response)
            if (data.error !== false) {
                toastr.error(data.message)
                return
            }
            _compareAccess = new Map(data.matrix.map(row => [Number(row.id), row.access]))
            renderRoleView()
        }).fail(function(xhr, status) {
            if (status !== 'abort' && generation === _compareGeneration && matrixGeneration === _matrixGeneration) {
                toastr.error(<?php echo json_encode($lang->get('error'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)
            }
        }).always(function() {
            if (generation === _compareGeneration) _compareRequest = null
        })
    }

</script>
