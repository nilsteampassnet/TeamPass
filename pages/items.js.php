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
 * @file      items.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as RequestLocal;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\Language\Language;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();
$session = SessionManager::getSession();
$request = RequestLocal::createFromGlobals();
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('items') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

$var = [];
$var['hidden_asterisk'] = '<i class="fa-solid fa-asterisk mr-2"></i><i class="fa-solid fa-asterisk mr-2"></i><i class="fa-solid fa-asterisk mr-2"></i><i class="fa-solid fa-asterisk mr-2"></i><i class="fa-solid fa-asterisk"></i>';

?>


<script type="text/javascript">
    var requestRunning = false,
        clipboardForLogin,
        clipboardForPassword,
        clipboardForPasswordListItems,
        clipboardForLink,
        clipboardOTPCode,
        query_in_progress = 0,
        screenHeight = $(window).height(),
        quick_icon_query_status = true,
        first_group = 1,
        folderSearchCriteria = $('#jstree_search').val(),
        userDidAChange = false,
        userUploadedFile = false,
        selectedFolder = false,
        selectedFolderId = false,
        itemClipboard,
        startedItemsListQuery = false,
        itemStorageInformation = '',
        applicationVars,
        initialPageLoad = true,
        previousSelectedFolder = -1,
        intervalId = false,
        debugJavascript = false;

    // Manage memory
    browserSession(
        'init',
        'teampassApplication', {
            lastItemSeen: false,
            itemsListStop: '',
            itemsListStart: '',
            selectedFolder: '',
            itemsListFolderId: false,
            itemsListRestricted: '',
            itemsShownByQuery: '',
            foldersList: [],
            personalSaltkeyRequired: 0,
            uploadedFileId: '',
            tempScrollTop: 0,
            highlightSelected: parseInt(<?php echo $SETTINGS['highlight_selected']; ?>),
            highlightFavorites: parseInt(<?php echo $SETTINGS['highlight_favorites']; ?>)
        }
    );

    browserSession(
        'init',
        'teampassItem', {
            IsPersonalFolder: '',
            hasAccessLevel: '',
            hasCustomCategories: '',
            id: '',
            timestamp: '',
            folderId: ''
        }
    );

    if (debugJavascript === true) {
        console.log('User information')
        console.log(store.get('teampassUser'))
    }

    // Show loader
    toastr.remove();
    toastr.info('<?php echo $lang->get('loading_data'); ?> ... <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>');

    // Build tree
    $('#jstree').jstree({
        'core': {
            'animation': 0,
            'check_callback': true,
            'data': {
                'url': './sources/tree.php',
                'dataType': 'json',
                'icons': false,
                'data': function(node) {
                    if (debugJavascript === true) {
                        console.info('Les répertoires sont chargés');
                        console.log(node);
                    }
                    return {
                        'id': node.id.split('_')[1],
                        'force_refresh': store.get('teampassApplication') !== undefined ?
                            store.get('teampassApplication').jstreeForceRefresh : 0
                    };
                }
            },
            'strings': {
                'Loading ...': '<?php echo $lang->get('loading'); ?>...'
            },
            'themes': {
                'icons': false,
            },
        },
        'plugins': [
            'state', 'search'
        ]
    })
    // On node select
    .bind('select_node.jstree', function(e, data) {
        if (debugJavascript === true) console.log('JSTREE BIND');
        selectedFolder = $('#jstree').jstree('get_selected', true)[0]
        selectedFolderId = parseInt(selectedFolder.id.split('_')[1]);

        // manage icon open/closed
        var selectedFolderIcon = $('#fld_'+selectedFolderId).children('.tree-folder').attr('data-folder'),
            selectedFolderIconSelected = $('#fld_'+selectedFolderId).children('.tree-folder').attr('data-folder-selected');

        // remove selected on previous folder
        $($('#fld_'+previousSelectedFolder).children('.tree-folder'))
            .removeClass($('#fld_'+previousSelectedFolder).children('.tree-folder').attr('data-folder-selected'))
            .addClass($('#fld_'+previousSelectedFolder).children('.tree-folder').attr('data-folder'));
        // show selected icon
        $('#fld_'+selectedFolderId).children('.tree-folder')
            .removeClass(selectedFolderIcon)
            .addClass(selectedFolderIconSelected);

        if (debugJavascript === true) {
            console.info('SELECTED NODE ' + selectedFolderId + " -- " + startedItemsListQuery);
            console.log(selectedFolder);
            console.log(selectedFolder.original.is_pf)
        }

        store.update(
            'teampassApplication',
            function(teampassApplication) {
                teampassApplication.selectedFolder = selectedFolderId,
                teampassApplication.selectedFolderTitle = selectedFolder.a_attr['data-title'],
                teampassApplication.selectedFolderParentId = selectedFolder.parent !== "#" ? selectedFolder.parent.split('_')[1] : 0,
                teampassApplication.selectedFolderParentTitle = selectedFolder.a_attr['data-title'],
                teampassApplication.selectedFolderIcon = selectedFolderIcon,
                teampassApplication.selectedFolderIconSelected = selectedFolderIconSelected,
                teampassApplication.selectedFolderIsPF = selectedFolder.original.is_pf,
                teampassApplication.userCanEdit = selectedFolder.original.can_edit
            }
        )
        store.update(
            'teampassItem',
            function(teampassItem) {
                teampassItem.folderId = selectedFolderId
            }
        );
        
        // Prepare list of items
        if (startedItemsListQuery === false) {
            startedItemsListQuery = true;
            ListerItems(selectedFolderId, '', 0);
        }

        previousSelectedFolder = selectedFolderId;
        initialPageLoad = false;
    })
    // Search in tree
    .bind('search.jstree', function(e, data) {
        if (data.nodes.length == 1) {
            //open the folder
            ListerItems($('#jstree li>a.jstree-search').attr('id').split('_')[1], '', 0);
        }
    });

    // Find folders in jstree
    $('#jstree_search')
        .keypress(function(e) {
            if (e.keyCode === 13) {
                $('#jstree').jstree('search', $('#jstree_search').val());
            }
        })
        .focus(function() {
            $(this).val('');
        })
        .blur(function() {
            $(this).val(folderSearchCriteria);
        });

    // Is this a short url
    var queryDict = {},
        showItemOnPageLoad = false,
        itemIdToShow = '';
    location.search.substr(1).split("&").forEach(function(item) {
        queryDict[item.split("=")[0]] = item.split("=")[1]
    });

    if (queryDict['group'] !== undefined && queryDict['group'] !== '' &&
        queryDict['id'] !== undefined && queryDict['id'] !== ''
    ) {
        // Show cog
        toastr.remove();
        toastr.info('<?php echo $lang->get('loading_item'); ?> ... <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>');

        // Store current view
        savePreviousView();

        // Store the folder to open
        store.set(
            'teampassApplication', {
                selectedFolder: parseInt(queryDict['group']),
                itemsListFolderId: parseInt(queryDict['group']),
                selectedItem: parseInt(queryDict['id']),
                highlightSelected: parseInt(<?php echo $SETTINGS['highlight_selected']; ?>),
                highlightFavorites: parseInt(<?php echo $SETTINGS['highlight_favorites']; ?>)
            }
        );
        store.update(
            'teampassItem',
            function(teampassItem) {
                teampassItem.folderId = parseInt(queryDict['group'])
            }
        );

        showItemOnPageLoad = true;
        itemIdToShow = queryDict['id'];
        startedItemsListQuery = true;
    }

    // Close on escape key
    $(document).keyup(function(e) {
        if (e.keyCode == 27) {
            closeItemDetailsCard();
        }
    });

    // load list of visible folders for current user
    // Refresh data later to avoid php session lock which slows page display.
    $(this).delay(500).queue(function() {
        refreshVisibleFolders(true);

        // show correct folder in Tree
        let groupe_id = store.get('teampassApplication').itemsListFolderId;
        if (groupe_id !== false && 
            ($('#jstree').jstree('get_selected', true)[0] === undefined ||
            'li_' + groupe_id !== $('#jstree').jstree('get_selected', true)[0].id)
        ) {
            $('#jstree').jstree('deselect_all');
            $('#jstree').jstree('select_node', '#li_' + groupe_id);
        }

        $(this).dequeue();
    });

    // Preload list of items
    if (store.get('teampassApplication') !== undefined &&
        store.get('teampassApplication').selectedFolder !== undefined &&
        store.get('teampassApplication').selectedFolder !== '' && 
        showItemOnPageLoad === true
    ) {
        startedItemsListQuery = true;
        ListerItems(store.get('teampassApplication').itemsListFolderId, '', 0);
    }

    // Show details of item
    if (showItemOnPageLoad === true) {
        // Display item details
        $.when(
            Details(itemIdToShow, 'show', true)
        ).then(function() {
            // Force previous view to Tree folders
            store.update(
                'teampassUser',
                function(teampassUser) {
                    teampassUser.previousView = '#folders-tree-card';
                }
            );
        });
    }

    // Keep the scroll position
    $(window).on("scroll", function() {
        if ($('#folders-tree-card').hasClass('hidden') === false) {
            store.update(
                'teampassApplication',
                function(teampassApplication) {
                    tempScrollTop: $(window).scrollTop()
                }
            );
        }
    });


    // Ensure correct height of folders tree
    $('#jstree').height(screenHeight - 270);
    $('.card-body .table-responsive').height(screenHeight - 270);
    $('#items-details-container').height(screenHeight - 270 + 59); // 59 = tables header/borders

    // Prepare iCheck format for checkboxes
    $('input[type="checkbox"].flat-blue, input[type="radio"].flat-blue').iCheck({
        checkboxClass: 'icheckbox_flat-blue',
        radioClass: 'iradio_flat-blue'
    });

    // Prepare some UI elements
    $('#limited-search').prop('checked', <?php echo (int) $SETTINGS['limited_search_default'] === 1 ? true : false; ?>);

    $(document).on('blur', '#form-item-icon', function() {
        $('#form-item-icon-show').html('<i class="fas '+$(this).val()+'"></i>');
    });

    // Manage the password show button
    // including autohide after a couple of seconds
    let isPasswordVisible = false;
    let passwordTimeout = null;
    $(document).on('click', '#card-item-pwd-toggle-button', function () {
        const $icon = $('.pwd-toggle-icon');
        const $button = $(this);
        const $pwdField = $('#card-item-pwd');

        // Toggle password visibility
        if (!isPasswordVisible) {
            // Get the password
            getItemPassword(
                'at_password_shown',
                'item_id',
                store.get('teampassItem').id
            ).then(item_pwd => {
                if (item_pwd) {
                    isPasswordVisible = true;

                    // Update UI
                    $icon
                        .removeClass('fa-regular fa-eye')
                        .addClass('fa-solid fa-eye-slash text-warning');

                    $pwdField
                        .text(item_pwd)
                        .addClass('pointer_none');

                    // Optional auto-hide after delay
                    clearTimeout(passwordTimeout);
                    passwordTimeout = setTimeout(() => {
                        hidePassword($icon, $pwdField);
                    }, <?php echo isset($SETTINGS['password_overview_delay']) && (int) $SETTINGS['password_overview_delay'] > 0 ? $SETTINGS['password_overview_delay'] * 1000 : 4000; ?>);
                }
            });
        } else {
            // Hide password immediately
            clearTimeout(passwordTimeout);
            hidePassword($icon, $pwdField);
        }
    });

    function hidePassword($icon, $pwdField) {
        isPasswordVisible = false;
        $icon
            .removeClass('fa-solid fa-eye-slash text-warning')
            .addClass('fa-regular fa-eye');
        $pwdField
            .html('<?php echo $var['hidden_asterisk']; ?>')
            .removeClass('pointer_none');
    }

    function resetPasswordDisplay() {
        const $icon = $('.pwd-toggle-icon');
        const $pwdField = $('#card-item-pwd');

        isPasswordVisible = false;
        clearTimeout(passwordTimeout);

        $icon
            .removeClass('fa-solid fa-eye-slash text-warning')
            .addClass('fa-regular fa-eye');

        $pwdField
            .html('<?php echo $var['hidden_asterisk']; ?>')
            .removeClass('pointer_none');
    }


    // Manage folders action
    $('.tp-action').click(function() {
        // Ensure that the local storage data is consistent with what is
        // displayed on the screen.
        const item_dom_id = parseInt($('#items-details-container').data('id'));
        const item_storage_id = parseInt(store.get('teampassItem').id);

        // Prevent usage of items actions when local storage and DOM are out
        // of sync. Let the user create a new item or refresh data even if the
        // IDs don't match.
        if ($(this).data('folder-action') === undefined
            && $(this).data('item-action') !== undefined
            && $(this).data('item-action') !== 'new'
            && $(this).data('item-action') !== 'reload' 
            && item_dom_id !== item_storage_id) {

            // Display error and stop
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('data_inconsistency'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        }

        // SHow user
        toastr.remove();
        toastr.info('<?php echo $lang->get('in_progress'); ?><i class="fa-solid fa-circle-notch fa-spin fa-2x ml-3"></i>');

        if ($(this).data('folder-action') === 'refresh') {
            // Force refresh
            store.update(
                'teampassApplication',
                function(teampassApplication) {
                    teampassApplication.jstreeForceRefresh = 1
                }
            );
            if (selectedFolderId !== '') {
                refreshTree(selectedFolderId, true);
            } else {
                refreshTree();
            }
            store.update(
                'teampassApplication',
                function(teampassApplication) {
                    teampassApplication.jstreeForceRefresh = 0
                }
            );
            toastr.remove();

            //
            // > END <
            //
        } else if ($(this).data('folder-action') === 'expand') {
            $('#jstree').jstree('open_all');
            toastr.remove();

            //
            // > END <
            //
        } else if ($(this).data('folder-action') === 'collapse') {
            $('#jstree').jstree('close_all');
            toastr.remove();

            //
            // > END <
            //
        } else if ($(this).data('folder-action') === 'add') {
            if (debugJavascript === true) console.info('SHOW ADD FOLDER');
            toastr.remove();

            // Check privileges
            if (store.get('teampassItem').hasAccessLevel < 20 &&
                store.get('teampassUser').can_create_root_folder === 0
            ) {
                toastr.error(
                    '<?php echo $lang->get('error_not_allowed_to'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }

            // Store current view
            savePreviousView('.form-folder-add');

            // Store last
            // Show copy form
            $('.form-item, .form-item-action, #folders-tree-card, .columns-position').addClass('hidden');
            $('.form-folder-add').removeClass('hidden');

            // Prepare some data in the form
            if (selectedFolder.parent !== undefined && selectedFolder.parent !== '') {
                $('#form-folder-add-parent').val(selectedFolder.id.split('_')[1]).change();
            }

            $('#form-folder-add-label, #form-folder-add-parent').prop('disabled', false);

            $('#form-folder-add-label')
                .val('')
                .focus();
            $('#form-folder-add-icon-selected, #form-folder-add-icon').val('');
            // Set type of action for the form
            $('#form-folder-add').data('action', 'add');

            //
            // > END <
            //
        } else if ($(this).data('folder-action') === 'edit') {
            if (debugJavascript === true) console.info('SHOW EDIT FOLDER');
            toastr.remove();
            // Check privileges
            if (store.get('teampassItem').hasAccessLevel < 20) {
                toastr.error(
                    '<?php echo $lang->get('error_not_allowed_to'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }
            if (debugJavascript === true) console.log(store.get('teampassApplication'));

            // Store current view
            savePreviousView('.form-folder-add');

            // Show edit form
            $('.form-item, .form-item-action, #folders-tree-card, .columns-position').addClass('hidden');
            $('.form-folder-add').removeClass('hidden');
            // Prepare some data in the form
            $("#form-folder-add-parent option[value='" + store.get('teampassApplication').selectedFolder + "']")
                .prop('disabled', true);
            $('#form-folder-add-parent').val(store.get('teampassApplication').selectedFolderParentId).change();
            $("#form-folder-add-parent option[value='" + store.get('teampassApplication').selectedFolderParentId + "']")
                .prop('disabled', false);
            $('#form-folder-add-label')
                .val(store.get('teampassApplication').selectedFolderParentTitle)
                .focus();
            // is PF 1st level
            if (store.get('teampassApplication').selectedFolderIsPF === 1 && store.get('teampassApplication').selectedFolderParentId !== 0) {
                $('#form-folder-add-label, #form-folder-add-parent').prop('disabled', false);
            } else if (store.get('teampassApplication').userCanEdit === 0) {
                $('#form-folder-add-label, #form-folder-add-parent').prop('disabled', true);
            } else {
                $('#form-folder-add-label, #form-folder-add-parent').prop('disabled', false);
            }

            $('#form-folder-add-complexicity').val(store.get('teampassItem').folderComplexity).change();
            $('#form-folder-add-icon')
                .val(store.get('teampassApplication').selectedFolderIcon);
            $('#form-folder-add-icon-selected')
                .val(store.get('teampassApplication').selectedFolderIconSelected);
            // Set type of action for the form
            $('#form-folder-add').data('action', 'update');

            //
            // > END <
            //
        } else if ($(this).data('folder-action') === 'copy') {
            if (debugJavascript === true) console.info('SHOW COPY FOLDER');
            toastr.remove();
            // Check privileges
            if (store.get('teampassItem').hasAccessLevel < 20) {
                toastr.error(
                    '<?php echo $lang->get('error_not_allowed_to'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }

            // Store current view
            savePreviousView('.form-folder-copy');

            // Show copy form
            $('.form-item, .form-item-action, #folders-tree-card, .columns-position').addClass('hidden');
            $('.form-folder-copy').removeClass('hidden');
            // Prepare some data in the form
            $('#form-folder-copy-source').val(store.get('teampassApplication').selectedFolder).change();
            //$("#form-folder-copy-destination option[value='"+selectedFolder.id.split('_')[1]+"']")
            //.prop('disabled', true);
            $('#form-folder-copy-destination').val(0).change();
            $('#form-folder-copy-label')
                .val(store.get('teampassApplication').selectedFolderTitle + ' <?php echo strtolower($lang->get('copy')); ?>')
                .focus();

            //
            // > END <
            //
        } else if ($(this).data('folder-action') === 'delete') {
            if (debugJavascript === true) console.info('SHOW DELETE FOLDER');
            toastr.remove();
            // Check privileges
            if (store.get('teampassItem').hasAccessLevel < 30) {
                toastr.error(
                    '<?php echo $lang->get('error_not_allowed_to'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }

            // Store current view
            savePreviousView('.form-folder-delete');

            // Show copy form
            $('.form-item, .form-item-action, #folders-tree-card, .columns-position').addClass('hidden');
            $('.form-folder-delete').removeClass('hidden');

            // Prepare some data in the form
            console.log("> "+store.get('teampassApplication').selectedFolder);
            $('#form-folder-delete-selection').val(store.get('teampassApplication').selectedFolder).change();
            $('#form-folder-confirm-delete').iCheck('uncheck');

            //
            // > END <
            //
        } else if ($(this).data('folder-action') === 'import') {
            // IMPORT ITEMS
            if (debugJavascript === true) console.info('SHOW IMPORT ITEMS');
            toastr.remove();

            // Store current view
            savePreviousView('.form-folder-import');


            // Show import form
            $('.form-item, .form-item-action, #folders-tree-card, .columns-position').addClass('hidden');
            $('.form-folder-import').removeClass('hidden');

            //
            // > END <
            //
        } else if ($(this).data('item-action') === 'new') {
            if (debugJavascript === true) console.info('SHOW NEW ITEM');
            toastr.remove();
            // Store current view
            savePreviousView();

            // Remove validated class
            $('#form-item').removeClass('was-validated');

            // Get some info
            $.when(
                getPrivilegesOnItem(store.get('teampassApplication').itemsListFolderId, 0)
            ).then(function(retData) {
                if (debugJavascript === true) {
                    console.log('getPrivilegesOnItem 1')
                    console.log(retData)
                }
                if (retData.error === true) {
                    requestRunning = false;
                    toastr.remove();
                    toastr.error(
                        retData.message,
                        '<?php echo $lang->get('error'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                    // Finished
                    return false;
                }
                // If previous item was seen then clean session
                store.update(
                    'teampassItem',
                    function(teampassItem) {
                        teampassItem.isNewItem = 1,
                        teampassItem.id = ''
                    }
                );

                // Show Visibility and minimum complexity
                $('#card-item-visibility').html(store.get('teampassItem').itemVisibility);
                $('#card-item-minimum-complexity').html(store.get('teampassItem').itemMinimumComplexity);

                // HIde
                $('.form-item-copy, #folders-tree-card, .columns-position, #form-item-password-options, .form-item-action, #form-item-attachments-zone')
                    .addClass('hidden');
                // Destroy editor
                $('#form-item-description').summernote('destroy');

                // Clean select2 lists
                $('.select2').val('');
                /*if ($('.select2') !== null) {if (debugJavascript === true) console.log($('.select2').length)
                    $('.select2').change();
                }*/
                // Do some form cleaning
                $('.clear-me-val').val('');
                $('.item-details-card').find('.form-control').val('');
                $('.clear-me-html').html('');
                $('.form-item-control').val('');
                // Show edition form
                $('.form-item').removeClass('hidden');
                // Force update of simplepassmeter
                $('#form-item-password').focus();
                $('#form-item-label').focus();
                // Prepare editor
                $('#form-item-description').summernote({
                    toolbar: [
                        ['style', ['style']],
                        ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
                        ['fontsize', ['fontsize']],
                        ['color', ['color']],
                        ['para', ['ul', 'ol', 'paragraph']],
                        ['insert', ['link', 'picture']],
                        //['height', ['height']],
                        ['view', ['codeview']]
                    ],
                    codeviewFilter: true,
                    codeviewIframeFilter: true
                });

                // Buttons more theme compliants
                $('.btn-light').addClass('btn-secondary').removeClass('btn-light');

                // Set folder
                $('#form-item-folder').val(selectedFolderId).change();
                // Select tab#1
                $('#form-item-nav-pills li:first-child a').tab('show');
                // Preselect
                $('#pwd-definition-size').val(20);
                // Set type of action
                $('#form-item-button-save').data('action', 'new_item');
                // Does this folder contain Custom Fields
                if (store.get('teampassItem').hasCustomCategories.length > 0) {
                    $('#form-item-field').removeClass('hidden');
                    $.each(store.get('teampassItem').hasCustomCategories, function(i, category) {
                        $('#form-item-category-' + category).removeClass('hidden');
                    })
                } else {
                    $('#form-item-field, .form-item-category').addClass('hidden');
                }



                // Prepare datePicker
                $('#form-item-deleteAfterDate, .datepicker').datepicker({
                    format: '<?php echo str_replace(['Y', 'M'], ['yyyy', 'mm'], $SETTINGS['date_format']); ?>',
                    todayHighlight: true,
                    todayBtn: true,
                    language: '<?php $userLang = $session->get('user-language_code'); echo isset($userLang) === null ? $userLang : 'en'; ?>'
                });
                
                // Add track-change class
                //$('#form-item-label, #form-item-description, #form-item-login, #form-item-password, #form-item-email, #form-item-url, #form-item-folder, #form-item-restrictedto, #form-item-tags, #form-item-anyoneCanModify, #form-item-deleteAfterShown, #form-item-deleteAfterDate, #form-item-anounce, .form-item-field-custom').addClass('track-change');

                // Update variable
                userDidAChange = false;

                toastr.remove();
            });

            //
            // > END <
            //
        } else if ($(this).data('item-action') === 'edit') {
            const item_tree_id = store.get('teampassItem').tree_id;
            if (debugJavascript === true) console.info('SHOW EDIT ITEM');
            // Reset item
            store.update(
                'teampassItem',
                function(teampassItem) {
                    teampassItem.otp_code_generate = false;
                }
            );
            

            // if item is ready
            if (store.get('teampassItem').readyToUse === false) {
                toastr.remove();
                toastr.warning(
                    '<?php echo $lang->get('item_action_not_yet_possible'); ?>',
                    '', {
                        timeOut: 3000,
                        progressBar: true
                    }
                );
                return false;
            }

            $.when(
                getPrivilegesOnItem(item_tree_id, 1)
            ).then(function(retData) {
                if (retData.error === true) {
                    toastr.remove();
                    toastr.error(
                        retData.message,
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );

                    requestRunning = false;

                    // Finished
                    return false;
                }

                // Is user allowed
                if (store.get('teampassItem').item_rights < 20) {
                    toastr.remove();
                    toastr.error(
                        '<?php echo $lang->get('error_not_allowed_to'); ?>',
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                    return false;
                }

                // Store current view
                savePreviousView();

                // Store not a new item
                store.update(
                    'teampassItem',
                    function(teampassItem) {
                        teampassItem.isNewItem = 0
                    }
                );

                // Remove validated class
                $('#form-item').removeClass('was-validated');

                // Now manage edtion
                showItemEditForm(item_tree_id);
            });

            //
            // > END <
            //
        } else if ($(this).data('item-action') === 'copy') {
            if (debugJavascript === true) console.info('SHOW COPY ITEM');

            // if item is ready
            if (store.get('teampassItem').readyToUse === false) {
                toastr.remove();
                toastr.warning(
                    '<?php echo $lang->get('item_action_not_yet_possible'); ?>',
                    '', {
                        timeOut: 3000,
                        progressBar: true
                    }
                );
                return false;
            }
            
            // Store current view
            savePreviousView('.form-item-copy');

            if (store.get('teampassItem').user_can_modify === 1) {
                // Show copy form
                $('.form-item, #folders-tree-card, .form-item-action').addClass('hidden');
                $('.form-item-copy, .item-details-card-menu').removeClass('hidden');
                // Prepare some data in the form
                $('#form-item-copy-new-label').val($('#form-item-label').val());
                $('#form-item-copy-destination').val($('#form-item-folder').val()).change();
                toastr.remove();
            } else {
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('error_not_allowed_to'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
            }

            //
            // > END <
            //
        } else if ($(this).data('item-action') === 'delete') {
            // if item is ready
            if (store.get('teampassItem').readyToUse === false) {
                toastr.remove();
                toastr.warning(
                    '<?php echo $lang->get('item_action_not_yet_possible'); ?>',
                    '', {
                        timeOut: 3000,
                        progressBar: true
                    }
                );
                return false;
            }

            toastr.remove();

            // Store current view
            savePreviousView('.form-item-delete');

            $.when(
                checkAccess(store.get('teampassItem').id, store.get('teampassItem').folderId, <?php echo $session->get('user-id'); ?>, 'delete')
            ).then(function(retData) {
                // Is the user allowed?
                if (retData.access === false || retData.delete === false) {
                    toastr.remove();
                    toastr.error(
                        '<?php echo $lang->get('error_not_allowed_to'); ?>',
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );

                    requestRunning = false;

                    // Finished
                    return false;
                }

                if (debugJavascript === true) console.info('SHOW DELETE ITEM');
                if (store.get('teampassItem').user_can_modify === 1) {
                    // Show delete form
                    $('.form-item, #folders-tree-card, .form-item-action').addClass('hidden');
                    $('.form-item-delete, .item-details-card-menu').removeClass('hidden');
                } else {
                    toastr.remove();
                    toastr.error(
                        '<?php echo $lang->get('error_not_allowed_to'); ?>',
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                }
            });

            //
            // > END <
            //
        } else if ($(this).data('item-action') === 'share') {
            if (debugJavascript === true) console.info('SHOW SHARE ITEM');
            toastr.remove();

            // Store current view
            savePreviousView('.form-item-share');

            // Show share form
            $('.form-item, #folders-tree-card, .form-item-action').addClass('hidden');
            $('.form-item-share').removeClass('hidden');

            //
            // > END <
            //
        } else if ($(this).data('item-action') === 'notify') {
            if (debugJavascript === true) console.info('SHOW NOTIFY ITEM');
            toastr.remove();

            // Store current view
            savePreviousView('.form-item-notify');

            $('#form-item-notify-checkbox').iCheck('uncheck');
            // Show notify form
            $('.form-item, #folders-tree-card, .form-item-action').addClass('hidden');
            $('.form-item-notify, .item-details-card-menu').removeClass('hidden');

            //
            // > END <
            //
        } else if ($(this).data('item-action') === 'otv') {
            if (debugJavascript === true) console.info('SHOW OTV ITEM');
            toastr.remove();

            // Store current view
            savePreviousView('.form-item-otv');


            // Generate link
            $('#form-item-otv-days').val($('#form-item-otv-days').attr('max'));
            $('#form-item-otv-views').val('1');
            prepareOneTimeView();

            $('#form-item-otv-link').val('');
            // Show notify form
            $('#folders-tree-card').addClass('hidden');
            $('.form-item-otv, .item-details-card-menu').removeClass('hidden');

            //
            // > END <
            //
        } else if ($(this).data('item-action') === 'reload') {            
            if (debugJavascript === true) console.info('RELOAD ITEM');
            toastr.remove();
            toastr.info('<?php echo $lang->get('loading_item'); ?> ... <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>');

            $.when(
                Details(store.get('teampassItem').id, 'show', true)
            ).then(function() {
                // Nothing
            });

            //
            // > END <
            //
        } else if ($(this).data('item-action') === 'server') {
            if (debugJavascript === true) console.info('SHOW SERVER UPDATE ITEM');
            toastr.remove();

            // Is user allowed
            var levels = [50, 70];
            if (levels.includes(store.get('teampassItem').item_rights) === false) {
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('error_not_allowed_to'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }

            // Store current view
            savePreviousView('.form-item-server');

            $('#form-item-notify-checkbox').iCheck('uncheck');
            // Show notify form
            $('.form-item, .form-item-action').addClass('hidden');
            $('.form-item-server, .item-details-card-menu').removeClass('hidden');

            //
            // > END <
            //
        } else if ($(this).data('item-action') === 'link') {
            // Add link to clipboard.
            navigator.clipboard.writeText("<?php echo $SETTINGS['cpassman_url'];?>/index.php?page=items&group="+store.get('teampassItem').folderId+"&id="+store.get('teampassItem').id);

            // Display message.
            toastr.remove();
            toastr.info(
                '<?php echo $lang->get('copy_to_clipboard'); ?>',
                '', {
                    timeOut: 2000,
                    progressBar: true,
                    positionClass: 'toast-bottom-right'
                }
            );

            //
            // > END <
            //
        } else if ($(this).data('folder-action') === 'items-checkbox') {
            // Vérifier si les cases à cocher existent déjà
            var checkboxesExist = $('.list-item-description .icon-container input[type="checkbox"]').length > 0;

            // Reset the checkbox
            $('#items-selection-checkbox').iCheck('uncheck');
            
            if (!checkboxesExist) {
                // Ajouter les cases à cocher
                $('.list-item-description .icon-container').each(function() {
                    var $container = $(this);
                    var $row = $container.closest('tr');
                    var itemId = $row.data('item-id');
                    
                    // Créer la case à cocher
                    var checkbox = '<input type="checkbox" class="item-checkbox mr-2" data-item-id="' + itemId + '" value="' + itemId + '">';
                    
                    // Insérer la case à cocher au début du conteneur
                    $container.prepend(checkbox);
                });

                $('.show-delete-checkbox').removeClass('hidden');
            } else {
                // Supprimer les cases à cocher si elles existent déjà
                $('.list-item-description .icon-container .item-checkbox').remove();
                $('#select-all-checkbox').closest('th').remove();
                $('.show-delete-checkbox').addClass('hidden');
            }

            // Remove loader.
            toastr.remove();

            //
            // > END <
            //
        } else if ($(this).data('folder-action') === 'items-delete') {
            // Fonction utilitaire pour obtenir les IDs des éléments sélectionnés
            function getSelectedItemIds() {
                var selectedIds = [];
                $('.item-checkbox:checked').each(function() {
                    selectedIds.push($(this).data('item-id'));
                });
                return selectedIds;
            }

            
            $("#items-delete-user-confirm").modal('show');

            // Handle delete task
            $("#modal-btn-items-delete-launch").on("click", function() {
                toastr.remove();
                toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');
                
                var selectedItemIds = getSelectedItemIds();

                if (selectedItemIds.length === 0) {
                    toastr.remove();
                    toastr.error(
                        '<?php echo $lang->get('no_item_selected'); ?>',
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                    $("#items-delete-user-confirm").modal('hide');
                    return false;
                }

                var data = {
                    'selectedItemIds': JSON.stringify(selectedItemIds),
                }
                
                $.post(
                    "sources/items.queries.php", {
                        type: "items_delete",
                        data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                        key: "<?php echo $session->get('key'); ?>"
                    },
                    function(data) {
                        data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");

                        $("#items-delete-user-confirm").modal('hide');

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
                        }
                        
                        // Inform user
                        toastr.remove();
                        toastr.success(
                            '<?php echo $lang->get('message'); ?>',
                            '', {
                                timeOut: 3000
                            }
                        );

                        // Reset the checkbox
                        $('#items-selection-checkbox').iCheck('uncheck');

                        // Force refresh of the tree
                        refreshVisibleFolders(true)

                        // Reload items list
                        ListerItems(
                            store.get('teampassItem').folderId,
                            '',
                            0,
                            0,
                            true
                        );
                        
                        // Inform user
                        toastr.remove();
                        toastr.success(
                            '<?php echo $lang->get('message'); ?>',
                            '', {
                                timeOut: 3000
                            }
                        );
                    }
                );
            });

            $("#modal-btn-items-delete-cancel").on("click", function(){
                $("#items-delete-user-confirm").modal('hide');
            });

            //
            // > END <
            //
        }

        return false;
    });

    
    // Gestionnaire pour la case "Sélectionner tout"
    $('#items-selection-checkbox').on('change', function() {
        var isChecked = $(this).prop('checked');
        
        // Cocher/décocher toutes les cases à cocher des éléments
        $('.item-checkbox').prop('checked', isChecked);
        
        // Optionnel : Ajouter une classe visuelle aux lignes sélectionnées
        if (isChecked) {
            $('.item-checkbox').closest('tr').addClass('selected-row');
        } else {
            $('.item-checkbox').closest('tr').removeClass('selected-row');
        }
    });

    /**
     * Saves the current view of user
     */
    function savePreviousView(newElement = '') {
        var element = '';
        if ($('#folders-tree-card').hasClass('hidden') === false) {
            element = '#folders-tree-card';
        } else if ($('.form-item').hasClass('hidden') === false) {
            element = '.form-item';
        } else if ($('.item-details-card-menu').hasClass('hidden') === false) {
            element = '.item-details-card';
        }
        
        if (debugJavascript === true) {console.log('>>> ' + element + ' -- ' + newElement);}

        if (element === '.item-details-card') element = '#folders-tree-card';

        // Store current view
        store.update(
            'teampassUser',
            function(teampassUser) {
                teampassUser.previousView = element;
            }
        );

        // Store the new one to display
        store.update(
            'teampassUser',
            function(teampassUser) {
                teampassUser.currentView = newElement;
            }
        );
    }


    $('.but-back').click(function() {
        userDidAChange = false;
        if ($(this).hasClass('but-back-to-item') === false) {
            // Is this form the edition one?
            if ($(this).hasClass('item-edit') === true) {
                // release existing edition lock
                data = {
                    'item_id': store.get('teampassItem').id,
                    'action': 'release_lock',
                }
                $.post(
                    'sources/items.queries.php', {
                        type: 'handle_item_edition_lock',
                        data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                        key: '<?php echo $session->get('key'); ?>'
                    }
                );

                if (userUploadedFile === true) {
                    // Do some operation such as cancel file upload
                    var data = {
                        'item_id': store.get('teampassItem').id,
                    }

                    $.post(
                        "sources/items.queries.php", {
                            type: 'delete_uploaded_files_but_not_saved',
                            data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                            key: '<?php echo $session->get('key'); ?>'
                        },
                        function(data) {
                            data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>', 'items.queries.php', 'delete_uploaded_files_but_not_saved');
                            if (debugJavascript === true) console.log(data);
                        }
                    );
                }
            }

            // Clear pickfiles div
            $('#form-item-upload-pickfilesList').html('');

            // Hide all
            $('.form-item, .form-item-action, .form-folder-action, #folders-tree-card, .columns-position, #form-item-upload-pickfilesList')
                .addClass('hidden');

            // Show expected one
            $(store.get('teampassUser').previousView).removeClass('hidden');
        } else {
            $(store.get('teampassUser').previousView).removeClass('hidden');
            $(store.get('teampassUser').currentView).addClass('hidden');
        }
        $('.but-prev-item, .but-next-item').addClass('hidden').text('');
    });


    // Quit item details card back to items list
    $('.but-back-to-list').click(function() {
        closeItemDetailsCard();
        resetPasswordDisplay();
    });



    // Manage if change is performed by user
    $('#form-item .form-item-control')
        .on('change', function() {
            if (requestRunning === false) {
                userDidAChange = true;
                if (debugJavascript === true) console.log('User did a change on #form-item > ' + userDidAChange + " - Element " + $(this).attr('id'));
                //$(this).attr('data-change-ongoing', true);
            }
        })
        .on('ifToggled', function() {
            if (requestRunning === false) {
                userDidAChange = true;
                if (debugJavascript === true) console.log('User did a change on ifToggled > ' + userDidAChange);
                //$(this).attr('data-change-ongoing', true);
            }
        });

    /**
     * Click on perform IMPORT
     */
    $(document).on('click', '#form-item-import-perform', function() {
        if (debugJavascript === true) console.log('START IMPORT');
    });


    /**
     * Click on ITEM REQUEST ACCESS
     */
    $(document).on('click', '.fa-clickable-access-request', function() {
        // Store current view
        savePreviousView();

        // Adapt the form
        $('#form-item-request-access-label')
            .html($(this).closest('.list-item-description').find('.list-item-row-description').text());

        // Store current item ID
        var selectedItemId = $(this).closest('.list-item-row').data('item-id');
        store.update(
            'teampassItem',
            function(teampassItem) {
                teampassItem.id = selectedItemId;
            }
        );

        // Show user
        $('.form-item, .form-item-action, #folders-tree-card, .columns-position').addClass('hidden');
        $('.form-item-request-access').removeClass('hidden');
    });

    /**
     * Send an access request to author
     */
    $(document).on('click', '#form-item-request-access-perform', function() {
        // No reason is provided
        if ($('#form-item-request-access-reason').val() === '') {
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('error_provide_reason'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        }

        var data = {
            'id': store.get('teampassItem').id,
            'email': DOMPurify.sanitize($('#form-item-request-access-reason').val()),
        }
        // NOw send the email
        $.post(
            "sources/items.queries.php", {
                type: 'send_request_access',
                data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>', 'items.queries.php', 'send_request_access');
                if (debugJavascript === true) console.log(data);

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
                    // Change view
                    $('.form-item-request-access').addClass('hidden');
                    $('#folders-tree-card, .columns-position').removeClass('hidden');

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

        scrollBackToPosition();
    });


    /**
     * Show/Hide the Password generation options
     */
    $('#item-button-password-showOptions').click(function() {
        if ($('#form-item-password-options').hasClass('hidden') === true) {
            $('#form-item-password-options').removeClass('hidden');
        } else {
            $('#form-item-password-options').addClass('hidden');
        }
    });



    /**
     * Adapt the top rules of item form on change of folders
     */
    $('#form-item-folder').change(function() {
        if ($(this).val() !== null && store.get('teampass-folders') !== undefined) {
            if (debugJavascript === true) {
                console.log('teampass-folders');
                console.log(store.get('teampass-folders'))
            }
            var folders = JSON.parse(store.get('teampass-folders'));
            $('#card-item-visibility').html(folders[$(this).val()].visibilityRoles);
            $('#card-item-minimum-complexity').html(folders[$(this).val()].complexity.text);
        }

    });

    /**
     * NOTIFY - Perform save
     */
    $('#form-item-notify-perform').click(function() {
        var form = $('#form-item-notify');


        var data = {
            'notification_status': $('#form-item-notify-checkbox').is(':checked') === true ? 1 : 0,
            'item_id': store.get('teampassItem').id,
        }

        // Launch action
        $.post(
            'sources/items.queries.php', {
                type: 'save_notification_status',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>', 'items.queries.php', 'save_notification_status');

                if (data.error !== false) {
                    // Show error
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '<?php echo $lang->get('caution'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    // Change the icon for Notification
                    if ($('#form-item-notify-checkbox').is(':checked') === true) {
                        $('#card-item-misc-notification')
                            .html('<span class="fa-regular fa-bell infotip text-success" title="<?php echo $lang->get('notification_engaged'); ?>"></span>');
                    } else {
                        $('#card-item-misc-notification')
                            .html('<span class="fa-regular fa-bell-slash infotip text-warning" title="<?php echo $lang->get('notification_not_engaged'); ?>"></span>');
                    }

                    // Show/hide forms
                    $('#folders-tree-card').removeClass('hidden');
                    $('.form-item-notify').addClass('hidden');

                    $('.infotip').tooltip();

                    // Inform user
                    toastr.success(
                        '<?php echo $lang->get('success'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );

                    // Clear
                    $('#form-item-notify-checkbox').iCheck('uncheck');
                }
            }
        );
    });



    /**
     * SHARE - validate the email
     */
    $('#form-item-share-perform').click(function() {
        var form = $('#form-item-share');

        if (form[0].checkValidity() === false) {
            form.addClass('was-validated');
            return false;
        }

        // Show cog
        toastr
            .info('<?php echo $lang->get('loading_item'); ?> ... <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>');

        // Prepare data
        var data = {
            'id': store.get('teampassItem').id,
            'receipt': DOMPurify.sanitize($('#form-item-share-email').val()),
            'cat': 'share_this_item',
        }

        // Launch action
        $.post(
            'sources/items.queries.php', {
                type: 'send_email',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>', 'items.queries.php', 'send_email');

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
                    $('#folders-tree-card').removeClass('hidden');
                    $('.form-item-share').addClass('hidden');

                    // Inform user
                    toastr.remove();
                    toastr.info(
                        '<?php echo $lang->get('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );

                    // Clear
                    $('#form-item-share-email').val('');
                }
            }
        );
    });


    /**
     * DELETE - recycle item
     */
    $('#form-item-delete-perform').click(function() {
        goDeleteItem(
            store.get('teampassItem').id,
            store.get('teampassItem').item_key !== undefined ? store.get('teampassItem').item_key : '',
            selectedFolderId,
            store.get('teampassItem').hasAccessLevel,
            true
        );
    });

    function goDeleteItem(itemId, itemKey, folderId, hasAccessLevel, closeItemCard = true)
    {
        // Show cog
        toastr
            .info('<?php echo $lang->get('loading_item'); ?> ... <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>');

        // Force user did a change to false
        userDidAChange = false;
        userUploadedFile = false;

        var data = {
            'item_id': itemId,
            'item_key': itemKey,
            'folder_id': folderId,
            'access_level': hasAccessLevel,
        }
        if (debugJavascript === true) {
            console.log(data);
        }

        // Launch action
        $.post(
            'sources/items.queries.php', {
                type: 'delete_item',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>', 'items.queries.php', 'delete_item');

                if (typeof data !== 'undefined' && data.error !== true) {
                    $('.form-item-action, .item-details-card-menu').addClass('hidden');
                    // Warn user
                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('success'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                    
                    // Refresh tree
                    refreshTree(folderId, true);
                    // Close
                    if (closeItemCard === true) {
                        closeItemDetailsCard();
                    }
                    requestRunning = false;
                } else {
                    // ERROR
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                }
            }
        );
    }


    /**
     * NOTIFY - save status
     */
    $('#form-item-share-perform').click(function() {
        // Launch action
        $.post(
            'sources/items.queries.php', {
                type: 'notify_user_on_item_change',
                id: store.get('teampassItem').id,
                value: $('#form-item-anyoneCanModify').is(':checked') === true ? 1 : 0,
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                if (data[0].error === '') {
                    $('.form-item, .item-details-card, .form-item-action').removeClass('hidden');
                    $('.form-item-share, .item-details-card-menu').addClass('hidden');
                    // Warn user
                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('success'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                    // Clear
                    $('#form-item-anyoneCanModify').attr('checked', '');
                } else {
                    // ERROR
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                }
            },
            'json'
        );
    });


    /**
     * COPY - perform copy item
     */
    $('#form-item-copy-perform').click(function() {
        // Do check
        if ($('#form-item-copy-new-label').val() === '') {
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('error_field_is_mandatory'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        }

        // Show cog
        toastr.remove();
        toastr.info('<?php echo $lang->get('item_copying'); ?> ... <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>');

        // Force user did a change to false
        userDidAChange = false;
        userUploadedFile = false;

        var data = {
            'item_id': store.get('teampassItem').id,
            'source_id': selectedFolderId,
            'dest_id': $('#form-item-copy-destination').val(),
            'new_label': DOMPurify.sanitize($('#form-item-copy-new-label').val()),
        }
        
        console.log("COPY ITEM data:");
        console.log(data);

        // Launch action
        $.post(
            'sources/items.queries.php', {
                type: 'copy_item',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");

                if (typeof data !== 'undefined' && data.error !== true) {
                    // Warn user
                    toastr.success(
                        '<?php echo $lang->get('success'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );

                    // Select folder of new item in jstree
                    $('#jstree').jstree('deselect_all');
                    $('#jstree').jstree('select_node', '#li_' + $('#form-item-copy-destination').val());

                    // Refresh tree
                    refreshTree(parseInt($('#form-item-copy-destination').val()), true);
                    // Load list of items
                    ListerItems(parseInt($('#form-item-copy-destination').val()), '', 0);

                    // Reopen Item details form
                    Details(
                        data.new_id,
                        'show',
                        true
                    );
                    
                    // Close
                    $('#folders-tree-card').removeClass('hidden');
                    $('.form-item-copy').addClass('hidden');
                } else {
                    // ERROR
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                }
            }
        );
    });


    /**
     * SERVER - perform server update
     */
    $('#form-item-server-perform').click(function() {
        // Decide what action is performing the user

        if ($('#tab-one-shot').hasClass('active') === true) {
            // Do check
            if ($('#form-item-server-login').val() === '' ||
                $('#form-item-server-old-password').val() === '' ||
                $('#form-item-server-password').val() === ''
            ) {
                toastr.error(
                    '<?php echo $lang->get('error_field_is_mandatory'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }

            // Show cog
            toastr.remove();
            toastr.info(
                '<i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>',
            );

            // Force user did a change to false
            userDidAChange = false;
            userUploadedFile = false;

            var data = {
                'item_id': store.get('teampassItem').id,
                'new_pwd': DOMPurify.sanitize($('#form-item-server-password').val()),
                'ssh_root': DOMPurify.sanitize($('#form-item-server-login').val()),
                'ssh_pwd': DOMPurify.sanitize($('#form-item-server-old-password').val()),
                'user_id': <?php echo $session->get('user-id'); ?>,
            }

            $.post(
                "sources/utils.queries.php", {
                    type: "server_auto_update_password",
                    data: prepareExchangedData(data, "encode", "<?php echo $session->get('key'); ?>"),
                    key: "<?php echo $session->get('key'); ?>"
                },
                function(data) {
                    data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                    if (debugJavascript === true) console.log(data);
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
                    } else {
                        // Warn user
                        toastr.success(
                            '<?php echo $lang->get('success'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );

                        // Info
                        $("#form-item-server-status")
                            .html("<?php echo $lang->get('done'); ?> " + data.text)
                            .removeClass('hidden');
                    }
                }
            );
        } else if ($('#tab-scheduled').hasClass('active') === true) {
            $.post(
                "sources/utils.queries.php", {
                    type: "server_auto_update_password_frequency",
                    id: store.get('teampassItem').id,
                    freq: $('#form-item-server-cron-frequency').val(),
                    key: "<?php echo $session->get('key'); ?>"
                },
                function(data) {
                    if (data[0].error != "") {
                        toastr.remove();
                        toastr.error(
                            data[0].error,
                            '', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        $('#form-item-server-cron-frequency').val(0).change();
                        toastr.success(
                            '<?php echo $lang->get('success'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );
                    }
                },
                "json"
            );
        }
    });


    /**
     * SUGGESTION - perform new suggestion on item
     */
    $('#form-item-suggestion-perform').click(function() {
        var form = $('#form-item-suggestion');

        if (form[0].checkValidity() === false) {
            form.addClass('was-validated');

            // Send alert to user
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('form_presents_inconsistencies'); ?>',
                '', {
                    timeOut: 10000,
                    progressBar: true
                }
            );

            return false;
        }

        // Show cog
        toastr
            .info('<?php echo $lang->get('loading_item'); ?> ... <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>');

        // Force user did a change to false
        userDidAChange = false;
        userUploadedFile = false;

        var data = {
            'label': DOMPurify.sanitize($('#form-item-suggestion-label').val()),
            'login': DOMPurify.sanitize($('#form-item-suggestion-login').val()),
            'password': DOMPurify.sanitize($('#form-item-suggestion-password').val()),
            'email': DOMPurify.sanitize($('#form-item-suggestion-email').val()),
            'url': DOMPurify.sanitize($('#form-item-suggestion-url').val()),
            'description': DOMPurify.sanitize($('#form-item-suggestion-description').summernote('code'), {USE_PROFILES: {html: true}}),
            'comment': DOMPurify.sanitize($('#form-item-suggestion-comment').val(), {USE_PROFILES: {html: true}}),
            'folder_id': selectedFolderId,
            'item_id': store.get('teampassItem').id
        }

        // Launch action
        $.post(
            'sources/items.queries.php', {
                type: 'suggest_item_change',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data//decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>', 'items.queries.php', 'suggest_item_change');

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
                    // Warn user
                    toastr.success(
                        '<?php echo $lang->get('success'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                    // Clear form
                    $('.form-item-suggestion').html('');

                    // Collapse form
                    //$('.card-item-extra').collapse('toggle');
                }
            }
        );
    });


    /**
     * FOLDER NEW - Add a new folder
     */
    $('#form-folder-add-perform').click(function() {
        var form = $('#form-folder-add');
        if (debugJavascript === true) {
            console.log(form[0]);
            console.log(form[0].checkValidity());
        }
        if (form[0].checkValidity() === false) {
            form.addClass('was-validated');

            // Send alert to user
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('form_presents_inconsistencies'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );

            return false;
        }

        // Error if folder text is only numeric
        if (/^\d+$/.test($('#form-folder-add-label').val())) {
            $('#form-folder-add-label').addClass('is-invalid');
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('error_only_numbers_in_folder_name'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );

            return false;
        }

        // Show cog
        toastr
            .info('<?php echo $lang->get('loading_item'); ?> ... <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>');

        // Force user did a change to false
        userDidAChange = false;
        userUploadedFile = false;

        // Sanitize text fields
        let formLabel = $('#form-folder-add-label').val(),
            formIcon = fieldDomPurifier('#form-folder-add-icon', false, false, false),
            formIconSelected = fieldDomPurifier('#form-folder-add-icon-selected', false, false, false);
        if (formLabel === false || formIcon === false || formIconSelected === false) {
            // Label is empty
            toastr.remove();
            toastr.warning(
                'XSS attempt detected. Field has been emptied.',
                'Error', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        }

        var data = {
            'title': formLabel,
            'parentId': $('#form-folder-add-parent option:selected').val(),
            'complexity': $('#form-folder-add-complexicity option:selected').val(),
            //'access_rights_strategy': $('#form-folder-add-rights option:selected').val(),
            'icon': formIcon,
            'iconSelected': formIconSelected,
            'id': selectedFolderId,
        }
        if (debugJavascript === true) console.log(data);

        // Launch action
        $.post(
            'sources/folders.queries.php', {
                type: $('#form-folder-add').data('action') + '_folder',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data//decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>', 'folders.queries.php', $('#form-folder-add').data('action') + '_folder');
                if (debugJavascript === true) {
                    console.log(data);
                }
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
                    // Refresh list of folders
                    refreshVisibleFolders(true);
                    if ($('#form-folder-add').data('action') === 'add') {
                        // select new folder on jstree
                        $('#jstree').jstree('deselect_all');
                        $('#jstree').jstree('select_node', '#li_' + data.newId);
                        // Refresh tree
                        refreshTree(data.newId, true);
                        // Refresh list of items inside the folder
                        ListerItems(data.newId, '', 0);
                    } else {
                        // Refresh tree
                        store.update(
                            'teampassApplication',
                            function(teampassApplication) {
                                teampassApplication.jstreeForceRefresh = 1;
                            }
                        );
                        refreshTree(selectedFolderId, true);
                        // Refresh list of items inside the folder
                        ListerItems(selectedFolderId, '', 0);
                        store.update(
                            'teampassApplication',
                            function(teampassApplication) {
                                teampassApplication.jstreeForceRefresh = 0;
                            }
                        );
                    }
                    // Back to list
                    closeItemDetailsCard();
                    // Warn user
                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('success'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                }
                // Enable the parent in select
                if (selectedFolder.id !== undefined) {
                    $("#form-folder-add-parent option[value='" + selectedFolder.id.split('_')[1] + "']")
                        .prop('disabled', false);
                }
            }
        );
    });


    /**
     * FOLDER DELETE - Delete an existing folder
     */
    $('#form-folder-delete-perform').click(function() {
        // Do check
        if ($('#form-folder-confirm-delete').is(':checked') === false) {
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('please_confirm'); ?>',
                '',
                {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        } else if ($('#form-folder-delete-selection option:selected').text() === '<?php echo $session->get('user-login'); ?>') {
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('error_not_allowed_to'); ?>',
                '',
                {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        }

        // Is a folder selected
        if ($('#form-folder-delete-selection option:selected').val() === '') {
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('please_select_a_folder'); ?>',
                '',
                {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        
        // Ensure Root is not selected
        } else if (parseInt($('#form-folder-delete-selection option:selected').val()) === 0 || $('#form-folder-delete-selection option:selected').length === 0) {
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('please_select_a_folder'); ?>',
                '',
                {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        }
        
        // Show cog
        toastr
            .info('<?php echo $lang->get('loading_item'); ?> ... <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>');


        var selectedFolders = [],
            data = {
                'selectedFolders': [$('#form-folder-delete-selection option:selected').val()]
            }
        if (debugJavascript === true) {
            console.log(data)
        }

        // Launch action
        $.post(
            'sources/folders.queries.php', {
                type: 'delete_folders',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>', 'folders.queries.php', 'delete_folders');

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
                    // Refresh list of folders
                    refreshVisibleFolders(true);
                    // Refresh tree
                    refreshTree(data.parent_id, true);
                    // Refresh list of items inside the folder
                    ListerItems(data.parent_id, '', 0);
                    // Back to list
                    closeItemDetailsCard();
                    // Warn user
                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('success'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                }

                $('#form-folder-confirm-delete').iCheck('uncheck');
            }
        );
    });


    /**
     * FOLDER COPY - Copy an existing folder
     */
    $('#form-folder-copy-perform').click(function() {
        // Do check
        if ($("#form-folder-copy-source").val() === "" || $("#form-folder-copy-destination").val() === "") {
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('error_must_enter_all_fields'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        } else if ($("#form-folder-copy-source").val() === $("#form-folder-copy-destination").val()) {
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('error_source_and_destination_are_equal'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        }

        // Show cog
        toastr.remove();
        toastr
            .info('<?php echo $lang->get('loading_item'); ?> ... <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>');

        var data = {
            'source_folder_id': $('#form-folder-copy-source option:selected').val(),
            'target_folder_id': $('#form-folder-copy-destination option:selected').val(),
            'folder_label': DOMPurify.sanitize($('#form-folder-copy-label').val(), {USE_PROFILES: {html: false}}),
        }

        // Launch action
        $.post(
            'sources/folders.queries.php', {
                type: 'copy_folder',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>', 'folders.queries.php', 'copy_folder');

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
                    // Refresh list of folders
                    refreshVisibleFolders(true);
                    // Refresh tree
                    refreshTree($('#form-folder-copy-destination option:selected').val(), true);
                    // Refresh list of items inside the folder
                    ListerItems($('#form-folder-copy-destination option:selected').val(), '', 0);
                    // Back to list
                    closeItemDetailsCard();
                    // Warn user
                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('success'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                }
            }
        );
    });

    $(document).on('change', '#form-folder-copy-source', function() {
        $('#form-folder-copy-label')
            .val($('#form-folder-copy-source option:selected').text()
                .substring(0, $('#form-folder-copy-source option:selected').text().lastIndexOf('[')).trim() +
                ' <?php echo strtolower($lang->get('copy')); ?>');
    });


    /**
     * Undocumented function
     *
     * @return void
     */
    function closeItemDetailsCard() {
        if (debugJavascript === true) console.log('CLOSE - user did a change? ' + userDidAChange + " - User previous view: " + store.get('teampassUser').previousView);
        if (userDidAChange === true) {
            toastr
                .warning(
                    '<?php echo $lang->get('changes_ongoing'); ?><br>' +
                    '<button type="button" class="btn clear" id="discard-changes"><?php echo $lang->get('yes'); ?></button>' +
                    '<button type="button" class="btn clear ml-2" id="keep-changes"><?php echo $lang->get('no'); ?></button>',
                    '<?php echo $lang->get('caution'); ?>', {
                        closeButton: true
                    }
                );
            $(document).on('click', '#discard-changes', function() {
                userDidAChange = false;
                //$('.form-item-control').attr('data-change-ongoing', "");
                closeItemDetailsCard();
            });
        } else {
            if (store.get('teampassUser').previousView === '.item-details-card' &&
                $('.item-details-card').hasClass('hidden') === false
            ) {
                $('.item-details-card').removeClass('hidden');
                $('#folders-tree-card, .columns-position, .form-item-action, .form-item, .form-folder-action, #card-item-expired')
                    .addClass('hidden');

                // Force previous view to folders list
                store.update(
                    'teampassUser',
                    function(teampassUser) {
                        teampassUser.previousView = '#folders-tree-card';
                    }
                );
            } else {
                if (store.get('teampassUser').previousView === '.item-details-card') {
                    // Force previous view to folders list
                    store.update(
                        'teampassUser',
                        function(teampassUser) {
                            teampassUser.previousView = '#folders-tree-card';
                        }
                    );
                    // Reopen Item details form
                    Details(
                        store.get('teampassItem').id,
                        'show',
                        true
                    );
                    return false;
                }
                if (store.get('teampassUser').previousView === '#folders-tree-card' ||
                    $('.item-details-card').hasClass('hidden') === false
                ) {
                    $('#folders-tree-card, .columns-position').removeClass('hidden');
                    $('.item-details-card, .form-item-action, .form-item, .form-folder-action, #card-item-expired')
                        .addClass('hidden');
                    $('#folder-tree-container').addClass('col-md-5').removeClass('col-md-3').removeClass('hidden');
                    $('#items-list-container').addClass('col-md-7').removeClass('col-md-4').removeClass('hidden');
                    $('#items-details-container').addClass('hidden');

                    // Remove selected item highlighting in list
                    if (store.get('teampassApplication').highlightSelected === 1) {
                        $('.list-item-row .list-item-description').removeClass('bg-black');
                    }

                } else {
                    // Hide all
                    $('.form-item, .form-item-action, .form-folder-action, .item-details-card, #folders-tree-card, #card-item-expired')
                        .addClass('hidden');
                    // Show expected one
                    $(store.get('teampassUser').previousView).removeClass('hidden');
                }

                // Do some form cleaning
                $('.clear-me-val, .form-item-control').val('');
                $('.item-details-card').find('.form-control').val('');
                $('.clear-me-html, .card-item-field-value').html('');
                $('.form-check-input').attr('checked', '');
                //$('.card-item-extra').collapse();
                $('.collapse').removeClass('show');
                $('.to_be_deleted').remove();
                $('#card-item-attachments, #card-item-history').html('');
                $('#card-item-attachments-badge').html('<?php echo $lang->get('none'); ?>');
                $('#form-item-otp').iCheck('uncheck');

                // Move back fields
                $('.fields-to-move')
                    .detach()
                    .appendTo('#card-item-fields');

                // Ensure the form is correct
                $('#list-group-item-main, #item-details-card-categories')
                    .children('.list-group')
                    .removeClass('hidden');

                // SHow save button in card
                //$('#form-item-buttons').removeClass('sticky-footer');

                // Destroy editors
                $('#form-item-description').summernote('destroy');
                $('#form-item-suggestion-description').summernote('destroy');

                // Show loading
                $('.overlay').removeClass('hidden');

                // Collapse accordion
                //$('.collapseme').addClass('collapsed-card');

                // Restore scroll position
                $(window).scrollTop(userScrollPosition);

                userDidAChange = false;                
                //$('.form-item-control').attr('data-change-ongoing', "");

                // Enable the parent in select
                if (selectedFolder.id !== undefined) {
                    $("#form-folder-add-parent option[value='" + selectedFolder.id.split('_')[1] + "']")
                        .prop('disabled', false);
                }
            }

            // Reset item
            store.update(
                'teampassItem',
                function(teampassItem) {
                    teampassItem.otp_code_generate = false;
                }
            );
            if (clipboardOTPCode) {
                clipboardOTPCode.destroy();
            }

            if (intervalId) {
                clearInterval(intervalId);
            }

            if (debugJavascript === true) console.log('Edit for closed');
        }

        // Scroll back to position
        scrollBackToPosition();

        // Extend menu size and trigger event listener
        if ($('body').hasClass('sidebar-collapse') === true) {
            $('a[data-widget="pushmenu"]').click();
        }
    }


    /**
     * Click on button with class but-navigate-item
     */
    $(document)
        .on('click', '.but-navigate-item', function() {
            toastr.remove();
            toastr.info('<?php echo $lang->get('loading_item'); ?> ... <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>');

            if (clipboardOTPCode) {
                clipboardOTPCode.destroy();
            }

            // Refresh password visibility
            resetPasswordDisplay();

            // Load item info
            Details(
                //$(this).hasClass('but-prev-item') === true ? $('#list-item-row_' + $(this).attr('data-prev-item-key')) : $('#list-item-row_' + $(this).attr('data-next-item-key')),
                $(this).hasClass('but-prev-item') === true ? $('#list-item-row_' + $(this).attr('data-prev-item-id')) : $('#list-item-row_' + $(this).attr('data-next-item-id')),
                'show'
            );

            $('.but-navigate-item').addClass('hidden');
        });


    /**
     * Click on item
     */
    $(document)
        .on('click', '.list-item-clicktoshow', function() {
            toastr.remove();
            toastr.info('<?php echo $lang->get('loading_item'); ?> ... <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>');

            // show top back buttons
            $('#but_back_top_left, #but_back_top_right').removeClass('hidden');

            // Load item info
            Details($(this).closest('tr'), 'show');
        })
        .on('click', '.list-item-clicktoedit', function() {
            toastr.remove();
            toastr.info('<?php echo $lang->get('loading_item'); ?> ... <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>');

            if (debugJavascript === true) console.log('EDIT ME');
            // Set type of action
            $('#form-item-button-save').data('action', 'update_item');

            // Hide top back buttons
            $('#but_back_top_left, #but_back_top_right').addClass('hidden');
            
            // Load item info
            Details($(this).closest('tr'), 'edit');
        })
        .on('click', '.list-item-clicktodelete', function(event) {
            event.preventDefault();
            // Delete item
            if (debugJavascript === true) {
                console.info('SHOW DELETE ITEM '+$(this).data('item-id'));
            }
            startedItemsListQuery = false;

            // check if user still has access
            var itemIdToDelete = $(this).data('item-id');

            $.when(
                checkAccess($(this).data('item-key'), $(this).data('item-tree-id'), <?php echo $session->get('user-id'); ?>, 'delete')
            ).then(function(retData) {
                // Is the user allowed?
                if (retData.access === false || retData.delete === false) {
                    toastr.remove();
                    toastr.error(
                        '<?php echo $lang->get('error_not_allowed_to'); ?>',
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );

                    requestRunning = false;

                    // Finished
                    return false;
                }

                // SHow dialog
                showModalDialogBox(
                    '#warningModal',
                    '<i class="fa-solid fa-triangle-exclamation mr-2 text-warning"></i><?php echo $lang->get('caution'); ?>',
                    '<?php echo $lang->get('please_confirm_deletion'); ?>',
                    '<?php echo $lang->get('delete'); ?>',
                    '<?php echo $lang->get('close'); ?>',
                    false,
                    false,
                    false
                );
                
                // Launch deletion
                $(document).on('click', '#warningModalButtonAction', {itemKey:$(this).data('item-key')}, function(event2) {
                    event2.preventDefault();
                    
                    goDeleteItem(
                        itemIdToDelete,
                        '',
                        selectedFolderId,
                        '',
                        false
                    );
                    $('#warningModal').modal('hide');
                });
                $(document).on('click', '#warningModalButtonClose', function() {
                    requestRunning = false;
                });
            });
        });


    /**
     *  Manage mini icons on mouse over
     */
    $(document)
        .on('mouseenter', '.list-item-row', function() {
            $(this).find(".list-item-actions").removeClass('hidden');
        })
        .on('mouseleave', '.list-item-row', function() {
            $(this).find(".list-item-actions").addClass('hidden');
        });

    $(document)
        .on('change', '.form-check-input-template', function() {
            $('.form-check-input-template').not(this).prop('checked', false);
            userDidAChange = true;
            if (debugJavascript === true) console.log('User did a change on .form-check-input-template > ' + userDidAChange);
        });

    $('.form-check-input-template').on('ifChecked', function() {
        $('.form-check-input-template').not(this).iCheck('uncheck');
        userDidAChange = true;
        if (debugJavascript === true) console.log('User did a change on .form-check-input-template > ' + userDidAChange);
        //$('.form-check-input-template').attr('data-change-ongoing', true);;
    });

    /**
     * Manage change of color
     */
    $(document)
        .on('mouseenter', '.fa-clickable', function() {
            if ($(this).hasClass('warn-user')) $(this).addClass('text-danger');
            else $(this).addClass('text-info');
        })
        .on('mouseleave', '.fa-clickable', function() {
            if ($(this).hasClass('warn-user')) $(this).removeClass('text-danger');
            else $(this).removeClass('text-info');
        });

    $('#form-item-label').change(function() {
        $('#form-item-title').text($(this).val());
    });

    /**
     * Make the item favourite by clicking on icon
     */
    $(document)
        .on('click', '.item-favourite', function() {
            if (quick_icon_query_status === true) {
                quick_icon_query_status = false;
                var elem = $(this);

                //Send query
                toastr.remove();
                toastr.info(
                    '<?php echo $lang->get('success'); ?>',
                    '', {
                        timeOut: 1000
                    }
                );

                var data = {
                    item_id: $(this).data('item-id'),
                    action: $(this).data('item-favourited'),
                }

                console.log(data)

                $.post('sources/items.queries.php', {
                        type: 'action_on_quick_icon',
                        data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                        key: '<?php echo $session->get('key'); ?>'
                    },
                    function(ret) {
                        //change quick icon
                        if (elem.data('item-favourited') === 0) {
                            $(elem)
                                .html('<span class="fa-stack fa-clickable item-favourite pointer infotip mr-2" title="<?php echo $lang->get('unfavorite'); ?>" data-item-id="' + data.item_id + '" data-item-favourited="1"><i class="fa-solid fa-circle fa-stack-2x"></i><i class="fa-solid fa-star fa-stack-1x fa-inverse text-warning"></i></span>');

                            // Remove highlighting
                            if (store.get('teampassApplication').highlightFavorites === 1) {
                                $('#list-item-row_' + data.item_id).addClass('bg-yellow');
                                $('#list-item-row_' + data.item_id + ' .item-favorite-star').addClass('fa-star mr-1');
                            }
                        } else {
                            $(elem)
                                .html('<span class="fa-stack fa-clickable item-favourite pointer infotip mr-2" title="<?php echo $lang->get('favorite'); ?>" data-item-id="' + data.item_id + '" data-item-favourited="0"><i class="fa-solid fa-circle fa-stack-2x"></i><i class="fa-solid fa-star fa-stack-1x fa-inverse"></i></span>');
                            
                            // Add highlighting
                            if (store.get('teampassApplication').highlightFavorites === 1) {
                                $('#list-item-row_' + data.item_id).removeClass('bg-yellow');
                                $('#list-item-row_' + data.item_id + ' .item-favorite-star').removeClass('fa-star mr-1');
                            }
                        }

                        toastr.remove();
                        toastr.info(
                            '<?php echo $lang->get('success'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );
                        quick_icon_query_status = true;
                    }
                );
            }
        });

    // Click to reaveal password
    $('#item-button-password-show')
        .mouseup(function() {
            $('#form-item-password').attr('type', 'password');
        })
        .mousedown(function() {
            // Allow $('#form-item .form-item-control').on('change') to be fired
            $('#form-item .form-item-control').blur();
            // Display cleartext password
            $('#form-item-password').attr('type', 'text');
        });
    $('.btn-no-click')
        .click(function(e) {
            e.preventDefault();
        });



    // show password during longpress
    var mouseStillDown = false;
    $('.item-details-card').on('mousedown', '.unhide_masked_data', function(event) {
            mouseStillDown = true;
            showPwdContinuous();
        })
        .on('mouseup', '.unhide_masked_data', function(event) {
            mouseStillDown = false;
            showPwdContinuous();
        })
        .on('mouseleave', '.unhide_masked_data', function(event) {
            mouseStillDown = false;
            showPwdContinuous();
        });

    const showPwdContinuous = function() {
        if (mouseStillDown === true 
            && !$('#card-item-pwd').hasClass('pwd-shown')
        ) {
            getItemPassword(
                'at_password_shown',
                'item_id',
                store.get('teampassItem').id
            ).then(item_pwd => {
                if (item_pwd) {                    
                    $('#card-item-pwd').text(item_pwd);
                    $('#card-item-pwd').addClass('pwd-shown');

                    // Auto hide password
                    setTimeout('showPwdContinuous("card-item-pwd")', 50);
                }
            });
            
        } else if(mouseStillDown !== true) {
            $('#card-item-pwd')
                .html('<?php echo $var['hidden_asterisk']; ?>')
                .removeClass('pwd-shown');
        }
    };

    // Fields - show masked field
    var selectedElement;
    $('.item-details-card').on('mousedown', '.replace-asterisk', function(event) {
            mouseStillDown = true;
            selectedElement = $(this);
            showContinuousMasked();
        })
        .on('mouseup', '.replace-asterisk', function(event) {
            mouseStillDown = false;
        })
        .on('mouseleave', '.replace-asterisk', function(event) {
            mouseStillDown = false;
        });
    var showContinuousMasked = function() {
        if (mouseStillDown) {
            $(selectedElement)
                .text($('#hidden-card-item-field-value-' + selectedElement.data('field-id')).val());

            setTimeout('showContinuousMasked()', 50);
        } else {
            $(selectedElement).html('<?php echo $var['hidden_asterisk']; ?>');
        }
    };


    /**
     * Launch the items search
     */
    $('#find_items').bind('keypress', function(e) {
        var code = e.keyCode || e.which;
        if (code == 13) {
            searchItems($(this).val());
        }
    });

    $('#find_items_button').click(function() {
        if ($('#find_items').val() !== '') {
            searchItems($('#find_items').val());
        }
    });


    // Password strength
    var pwdOptions = {
        common: {
            zxcvbn: true,
            debug: false,
            minChar: 4,
            onScore: function (options, word, totalScoreCalculated) {
                if (word.length === 20 && totalScoreCalculated < options.ui.scores[1]) {
                    // Score doesn't meet the score[1]. So we will return the min
                    // numbers of points to get that score instead.
                    return options.ui.score[1]
                }
                $("#form-item-password-complex").val(totalScoreCalculated);
                return totalScoreCalculated;
            },
            usernameField: "#form-item-login",
        },
        rules: {},
        ui: {
            colorClasses: ["text-danger", "text-danger", "text-danger", "text-warning", "text-warning", "text-success"],
            showPopover: false,
            showStatus: true,
            showErrors: false,
            showVerdictsInsideProgressBar: true,
            container: "#tab_1",
            viewports: {
                progress: "#form-item-password-strength",
                score: "#form-item-password-strength"
            },
            scores: [<?php echo TP_PW_STRENGTH_1;?>, <?php echo TP_PW_STRENGTH_2;?>, <?php echo TP_PW_STRENGTH_3;?>, <?php echo TP_PW_STRENGTH_4;?>, <?php echo TP_PW_STRENGTH_5;?>],
        },
        i18n : {
            t: function (key) {
                var phrases = {
                    weak: '<?php echo $lang->get('complex_level1'); ?>',
                    normal: '<?php echo $lang->get('complex_level2'); ?>',
                    medium: '<?php echo $lang->get('complex_level3'); ?>',
                    strong: '<?php echo $lang->get('complex_level4'); ?>',
                    veryStrong: '<?php echo $lang->get('complex_level5'); ?>'
                };
                var result = phrases[key];

                return result === key ? '' : result;
            }
        }
    };
    $('#form-item-password').pwstrength(pwdOptions);
    


    /**
     * PLUPLOAD
     */
    <?php
    $max_file_size = '';
    if (strrpos($SETTINGS['upload_maxfilesize'], 'mb') === false) {
        $max_file_size = $SETTINGS['upload_maxfilesize'] . 'mb';
    } else {
        $max_file_size = $SETTINGS['upload_maxfilesize'];
    }

    $mime_types = [];
    if (
        isset($SETTINGS['upload_all_extensions_file']) === false
        || (isset($SETTINGS['upload_all_extensions_file']) === true
            && (int) $SETTINGS['upload_all_extensions_file'] === 0)
    ) {
        $mime_types = [
            [
                'title' => 'Image files',
                'extensions' => $SETTINGS['upload_imagesext']
            ],
            [
                'title' => 'Package files',
                'extensions' => $SETTINGS['upload_pkgext']
            ],
            [
                'title' => 'Documents files',
                'extensions' => $SETTINGS['upload_docext']
            ],
            [
                'title' => 'Other files',
                'extensions' => $SETTINGS['upload_otherext']
            ]
        ];
    }

    $prevent_empty = isset($SETTINGS['upload_zero_byte_file']) === true && (int) $SETTINGS['upload_zero_byte_file'] === 1 ? false : true;

    $resize = null;
    if ((int) $SETTINGS['upload_imageresize_options'] === 1) {
        $resize = [
            'width' => $SETTINGS['upload_imageresize_width'],
            'height' => $SETTINGS['upload_imageresize_height'],
            'quality' => $SETTINGS['upload_imageresize_quality']
        ];
    }
    ?>

    var max_file_size = '<?php echo $max_file_size; ?>';
    var mime_types = <?php echo json_encode($mime_types); ?>;
    var prevent_empty = <?php echo json_encode($prevent_empty); ?>;
    var resize = <?php echo json_encode($resize); ?>;
    let toastrElement;
    let fileId;

    var uploader_attachments = new plupload.Uploader({
        runtimes: 'html5,flash,silverlight,html4',
        browse_button: 'form-item-attach-pickfiles',
        container: 'form-item-upload-zone',
        max_file_size: max_file_size,
        chunk_size: '1mb',
        dragdrop: true,
        url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/upload.attachments.php',
        flash_swf_url: '<?php echo $SETTINGS['cpassman_url']; ?>/plugins/plupload/js/Moxie.swf',
        silverlight_xap_url: '<?php echo $SETTINGS['cpassman_url']; ?>/plugins/plupload/js/Moxie.xap',
        filters: {
            mime_types: mime_types,
            prevent_empty: prevent_empty
        },
        resize: resize,
        init: {
            BeforeUpload: function(up, file) {
                fileId = file.id;
                toastr.remove();         
                toastrElement = toastr.info('<?php echo $lang->get('loading_item'); ?> ... <span id="plupload-progress" class="mr-2 ml-2 strong">0%</span><i class="fas fa-cloud-arrow-up fa-bounce fa-2x"></i>');
                // Show file name
                $('#upload-file_' + file.id).html('<i class="fa-solid fa-file fa-sm mr-2"></i>' + htmlEncode(file.name) + '<span id="fileStatus_'+file.id+'"><i class="fa-solid fa-circle-notch fa-spin  ml-2"></i></span>');

                // Get random number
                if (store.get('teampassApplication').uploadedFileId === '') {
                    store.update(
                        'teampassApplication',
                        function(teampassApplication) {
                            teampassApplication.uploadedFileId = CreateRandomString(9, 'num_no_0');
                        }
                    );
                }

                up.setOption('multipart_params', {
                    PHPSESSID: '<?php echo $session->get('user-id'); ?>',
                    itemId: store.get('teampassItem').id,
                    type_upload: 'item_attachments',
                    isNewItem: store.get('teampassItem').isNewItem,
                    isPersonal: store.get('teampassItem').folderIsPersonal,
                    edit_item: false,
                    user_upload_token: store.get('teampassApplication').attachmentToken,
                    randomId: store.get('teampassApplication').uploadedFileId,
                    files_number: $('#form-item-hidden-pickFilesNumber').val(),
                    file_size: file.size
                });
            },
            UploadProgress: function(up, file) {
                // Update only the percentage inside the Toastr message
                $('#plupload-progress').text(file.percent + '%');
            },
            UploadComplete: function(up, files) {
                // Inform user
                toastr.remove();
            },
            Error: function(up, args) {
                console.log("ERROR arguments:");
                console.log(args);
            }
        }
    });

    uploader_attachments.bind('FileUploaded', function(up, file) {
        $('#fileStatus_'+file.id).html('<i class="fa-solid fa-circle-check text-success ml-2 fa-1x"></i>');
        userUploadedFile = true;
        userDidAChange = true;
        toastr.remove();
    });
    uploader_attachments.bind('Error', function(up, err) {
        toastr.remove();
        // Extraire le message d'erreur
        let errorMessage = 'An unknown error occurred.';
        if (err.response) {
            try {
                const response = JSON.parse(err.response);
                if (response.error && response.error.message) {
                    errorMessage = response.error.message;
                }
            } catch (e) {
                errorMessage = err.response; // Si la réponse n'est pas JSON
            }
        }

        // Vérifie si l'erreur est due à un dépassement de taille ou une autre erreur critique
        if (err.code === -200 || err.status === 413) {
            // Arrêter l'upload des chunks
            up.stop();
            errorMessage += ' - Upload stopped.';

            // Affiche l'erreur dans l'interface utilisateur
            toastr.error(
                errorMessage + (err.file ? ', File: ' + err.file.name : ''),
                '', {
                    timeOut: 10000,
                    progressBar: true
                }
            );

            $('#fileStatus_'+fileId).html('<i class="fa-solid fa-circle-xmark text-danger ml-2 fa-1x"></i>');
            return false;
        } else {
            up.refresh(); // Reposition Flash/Silverlight
        }
    });

    $("#form-item-upload-pickfiles").click(function(e) {
        if ($('#form-item-upload-pickfilesList').text() !== '') {
            // generate and save token
            $.post(
                "sources/main.queries.php", {
                    type: "save_token",
                    type_category: 'action_system',
                    size: 25,
                    capital: true,
                    numeric: true,
                    ambiguous: true,
                    reason: "item_attachments",
                    duration: 10,
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    store.update(
                        'teampassApplication',
                        function(teampassApplication) {
                            teampassApplication.attachmentToken = data[0].token;
                        }
                    );
                    console.log('start upload')
                    uploader_attachments.start();
                },
                "json"
            );
            e.preventDefault();
        } else {
            toastr.remove();
            toastr.warning(
                '<?php echo $lang->get('no_file_to_upload'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
        }
    });
    uploader_attachments.init();
    uploader_attachments.bind('FilesAdded', function(up, files) {
        if (debugJavascript === true) {
            console.log('uploader_attachments.FilesAdded')
        }
        $('#form-item-upload-pickfilesList').removeClass('hidden');
        var addedFiles = '';
        $.each(files, function(i, file) {
            $('#form-item-upload-pickfilesList').append(
                '<div id="upload-file_' + file.id + '">' +
                '<span id="upload-file-remove_' + file.id +
                '><a href="#" onclick="$(this).closest(\'div\').remove();"><i class=" fa fa-trash mr-2 pointer"></i></a></span> ' +
                htmlEncode(file.name) + ' (' + plupload.formatSize(file.size) + ')' +
                '</div>');
            $("#form-item-hidden-pickFilesNumber").val(
                parseInt($("#form-item-hidden-pickFilesNumber").val()) + 1
            );
            if (debugJavascript === true) {
                console.info('Info du fichier :');
                console.log(file);
            }
        });
        up.refresh(); // Reposition Flash/Silverlight
    });
    //->



    /**
     * Save item changes
     */
    $('#form-item-button-save').click(function() {
        var arrayQuery = [],
            originalFolderId = $('#form-item-folder').val(),
            itemsList = [],
            userItemRight = '';

        // What action is this?
        if ($('#form-item-button-save').data('action') === '' ||
            $('#form-item-button-save').data('action') === undefined
        ) {
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('error_no_action_identified'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        }

        // Don't save if no change
        if (userDidAChange === false && userUploadedFile === false) {
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('no_change_performed'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        }

        // Validate form
        var form = $('#form-item');
        if (form[0].checkValidity() === false) {
            form.addClass('was-validated');
            // Send alert to user
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('form_presents_inconsistencies'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );

            return false;
        }

        // Show loading
        toastr.remove();
        toastr.info(
            '<i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>',
            '<?php echo $lang->get('please_wait'); ?>'
        );

        // Loop on all changed fields
        $('.form-item-field-custom').each(function(i, obj) {
            if ($(this).data('change-ongoing') === true) {
                // Create an array with changed inputs
                arrayQuery.push({
                    'input': $(this).attr('id'),
                    'field': $(this).data('field-name'),
                    'value': $(this).val(),
                });
            }
        });
        if (debugJavascript === true) {
            console.log('CHANGED FIELDS '+userUploadedFile + ' ' + userDidAChange);
            console.log(arrayQuery);
        }

        // is user allowed to edit this item
        if (typeof store.get('teampassApplication').itemsList !== 'undefined') {
            itemsList = JSON.parse(store.get('teampassApplication').itemsList);
        }
        if (itemsList.length > 0) {
            userItemRight = itemsList[store.get('teampassItem').id]?.rights;
        }

        

        // Do checks
        if (arrayQuery.length > 0 || userDidAChange === true) {
            var reg = new RegExp("[.|,|;|:|!|=|+|-|*|/|#|\"|'|&]");

            // Sanitize text fields
            purifyRes = fieldDomPurifierLoop('#form-item .purify');
            if (purifyRes.purifyStop === true) {
                // if purify failed, stop
                return false;
            }
            
            // Do some easy checks
            if (purifyRes.arrFields['label'] === '') {
                // Label is empty
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('error_label'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            } else if (purifyRes.arrFields['tags'] !== '' && reg.test(purifyRes.arrFields['tags'])
            ) {
                // Tags not wel formated
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('error_tags'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            } else if ($('#form-item-folder option:selected').val() === '' ||
                typeof $('#form-item-folder option:selected').val() === 'undefined'
            ) {
                // No folder selected
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('error_no_selected_folder'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            } else if ($('#form-item-folder option:selected').attr('disabled') === 'disabled' && userItemRight && userItemRight <= 40) {
                // Folder is not allowed
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('error_folder_not_allowed'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            } else {
                // Continue preparation of saving query

                //Manage restriction
                var restriction = new Array(),
                    restrictionRole = new Array(),
                    userInRestrictionList = false;
                $('#form-item-restrictedto option:selected').each(function() {
                    if ($(this).val() !== '') {
                        if ($(this).hasClass('restriction_is_role') === true) {
                            restrictionRole.push($(this).val().substring(5));
                        } else {
                            restriction.push($(this).val());
                            // Is the user part of the restriction option
                            if ($(this).val() === '<?php echo $session->get('user-id'); ?>') {
                                userInRestrictionList = true;
                            }
                        }
                    }
                });
                // IF any restriction, then ensure the author is in
                if (userInRestrictionList === false && restriction.length > 0) {
                    restriction.push('<?php echo $session->get('user-id'); ?>;');
                }

                // Manage diffusion list
                var diffusion = new Array();
                var diffusionNames = new Array();
                $('#form-item-anounce option:selected').each(function() {
                    diffusion.push($(this).val());
                    diffusionNames.push($(this).text());
                });

                // Get item field values
                // Ensure that mandatory ones are filled in too
                // and they are compliant to regexes
                var fields = [];
                var errorExit = false;
                var reason = '';
                $('.form-item-field-custom').each(function(key, data) {
                    fields.push({
                        'id': $(this).data('field-name'),
                        'value': $(this).val(),
                    });

                    // Mandatory?
                    if (parseInt($(this).data('field-mandatory')) === 1 &&
                        $(this).val() === '' &&
                        $('#form-item-field-' + $(this).data('field-name')).parent().hasClass('hidden') === false
                    ) {
                        //if (debugJavascript === true) console.log($(this))
                        errorExit = true;
                        return false;
                    }
                    if ($(this).val().length > 0 && $(this).data('field-regex').length > 0 &&
                        !$(this).val().match($(this).data('field-regex'))
                    ) {
                        //if (debugJavascript === true) console.log($(this))
                        errorExit = true;
                        reason = 'regex';
                        return false;
                    }
                });
                if (errorExit === true) {
                    toastr.remove();
                    if (reason === 'regex') {
                        toastr.error(
                            '<?php echo $lang->get('error_field_regex'); ?>',
                            '', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        toastr.error(
                            '<?php echo $lang->get('error_field_is_mandatory'); ?>',
                            '', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    }
                    return false;
                }

                //prepare data
                var data = {
                    'anyone_can_modify': $('#form-item-anyoneCanModify').is(':checked') ? 1 : 0,
                    'complexity_level': parseInt($('#form-item-password-complex').val()),
                    'description': $('#form-item-description').summernote('code') === '<p><br></p>' ? '' : $('#form-item-description').summernote('code'),
                    'diffusion_list': diffusion,
                    'diffusion_list_names': diffusionNames,
                    'folder': parseInt($('#form-item-folder').val()),
                    'email': purifyRes.arrFields['email'],
                    'fields': fields,
                    'folder_is_personal': store.get('teampassItem').IsPersonalFolder === 1 ? 1 : 0,
                    'id': store.get('teampassItem').id,
                    'label': purifyRes.arrFields['label'],
                    'login': purifyRes.arrFields['login'],
                    'pw': $('#form-item-password').val(),
                    'restricted_to': restriction,
                    'restricted_to_roles': restrictionRole,
                    'tags': purifyRes.arrFields['tags'],
                    'template_id': parseInt($('input.form-check-input-template:checkbox:checked').data('category-id')),
                    'to_be_deleted_after_date': purifyRes.arrFields['deleteAfterDate'] !== '' ? purifyRes.arrFields['deleteAfterDate'] : '',
                    'to_be_deleted_after_x_views': parseInt(purifyRes.arrFields['deleteAfterShown']) > 0 ? parseInt(purifyRes.arrFields['deleteAfterShown']) : '',
                    'url': purifyRes.arrFields['url'],
                    'user_id': parseInt('<?php echo $session->get('user-id'); ?>'),
                    'uploaded_file_id': store.get('teampassApplication').uploadedFileId === undefined ? '' : store.get('teampassApplication').uploadedFileId,
                    'fa_icon': purifyRes.arrFields['icon'],
                    'otp_is_enabled': $('#form-item-otp').is(':checked') ? 1 : 0,
                    'otp_phone_number': purifyRes.arrFields['otpPhoneNumber'] !== '' ? purifyRes.arrFields['otpPhoneNumber'] : '',
                    'otp_secret': purifyRes.arrFields['otpSecret'] !== '' ? purifyRes.arrFields['otpSecret'] : '',
                };
                if (debugJavascript === true) {
                    console.log('SAVING DATA');
                    console.log(data);
                }

                // CLear tempo var
                store.update(
                    'teampassApplication',
                    function(teampassApplication) {
                        teampassApplication.uploadedFileId = '';
                    }
                );

                // Purify now
                data = purifyData(data, true, false, false);

                //Send query
                $.post(
                    "sources/items.queries.php", {
                        type: $('#form-item-button-save').data('action'),
                        data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>", '', '', false),   // don't purify, already done
                        key: "<?php echo $session->get('key'); ?>"
                    },
                    function(data) {
                        //decrypt data
                        try {
                            data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                        } catch (e) {
                            // error
                            $("#div_loading").addClass("hidden");
                            //requestRunning = false;
                            $("#div_dialog_message_text").html("An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />" + data);
                            $("#div_dialog_message").dialog("open");

                            toastr.remove();
                            toastr.error(
                                'An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />' + data,
                                '', {
                                    timeOut: 5000,
                                    progressBar: true
                                }
                            );
                            return false;
                        }
                        if (debugJavascript === true) {
                            console.log('RETURNED DATA');
                            console.log(data);
                        }
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
                            // Refresh tree
                            if ($('#form-item-button-save').data('action') === 'update_item') {
                                if ($('#form-item-folder').val() !== '' &&
                                    originalFolderId !== $('#form-item-folder').val()
                                ) {
                                    refreshTree($('#form-item-folder').val(), false);
                                }
                                // Send query to confirm attachments
                                var data = {
                                    'item_id': store.get('teampassItem').id,
                                }
                                $.post(
                                    "sources/items.queries.php", {
                                        type: 'confirm_attachments',
                                        data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                                        key: '<?php echo $session->get('key'); ?>'
                                    }
                                );

                                // Select new folder of item in jstree
                                $('#jstree').jstree('deselect_all');
                                $('#jstree').jstree('select_node', '#li_' + $('#form-item-folder').val());

                            } else if ($('#form-item-button-save').data('action') === 'new_item') {
                                window.location.href = './index.php?page=items&group='+$('#form-item-folder').val()+'&id='+data.item_id;
                                return;
                            } else {
                                refreshTree($('#form-item-folder').val(), true);
                            }

                            // Refresh list of items inside the folder
                            ListerItems($('#form-item-folder').val(), '', 0);

                            // Inform user
                            toastr.remove();
                            toastr.info(
                                '<?php echo $lang->get('success'); ?>',
                                '', {
                                    timeOut: 1000
                                }
                            );

                            // Close
                            userDidAChange = false;
                            userUploadedFile = false;

                            // Close edit form and reopen folders-tree-card with refreshed item.
                            $('.form-item, #form-item-attachments-zone').addClass('hidden');
                            $('#folders-tree-card').removeClass('hidden');
                            item_id = store.get('teampassItem').id !== '' ? store.get('teampassItem').id : data.item_id;                         
                            Details(item_id, 'show', true);
                        }
                    }
                );
            }
        } else if (userUploadedFile === true) {
            // Send query to confirm attachments
            var data = {
                'item_id': store.get('teampassItem').id,
            }

            $.post(
                "sources/items.queries.php", {
                    type: 'confirm_attachments',
                    data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                    key: '<?php echo $session->get('key'); ?>'
                }
            );

            store.update(
                'teampassItem',
                function(teampassItem) {
                    teampassItem.isNewItem = 0
                }
            );

            // Inform user
            toastr.info(
                '<?php echo $lang->get('done'); ?>',
                '', {
                    timeOut: 1000
                }
            );

            // Close
            userUploadedFile = false;
            closeItemDetailsCard();
        } else {
            if (debugJavascript === true) console.info('NOTHING TO SAVE');
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('nothing_to_save'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
        }
    });
    //->


    //autocomplete for TAGS
    $("#form-item-tags")
        //.focus()
        .bind("keydown", function(event) {
            if (event.keyCode === $.ui.keyCode.TAB &&
                $(this).data("autocomplete").menu.active) {
                event.preventDefault();
            }
        })
        .autocomplete({
            source: function(request, response) {
                $.getJSON("sources/items.queries.php?type=autocomplete_tags&t=1", {
                    term: extractLast(request.term)
                }, response);
            },
            focus: function() {
                // prevent value inserted on focus
                return false;
            },
            search: function() {
                var term = extractLast(this.value);
            },
            select: function(event, ui) {
                var terms = split(this.value);
                // remove the current input
                terms.pop();
                // add the selected item
                terms.push(ui.item.value);
                // add placeholder to get the comma-and-space at the end
                terms.push("");
                this.value = terms.join(" ");

                return false;
            }
        });

    // Warn in case of limited search
    $(document).on('click', '#limited-search', function() {
        if ($(this).is(":checked") !== <?php echo (int) $SETTINGS['limited_search_default'] === 1 ? 'true' : 'false'; ?>) {
            $('#find_items').addClass('bg-red');
        } else {
            $('#find_items').removeClass('bg-red');
        }
    });


    function showItemEditForm(selectedFolderId) {
        if (debugJavascript === true) console.info('SHOW EDIT ITEM ' + selectedFolderId);
        // Reset item
        store.update(
            'teampassItem',
            function(teampassItem) {
                teampassItem.otp_code_generate = false;
            }
        );
        
        if (store.get('teampassItem').error === true) {
            toastr.remove();
            toastr.error(
                store.get('teampassItem').message,
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
        } else {
            // Get password and fill the field.
            getItemPassword(
                'at_password_shown_edit_form',
                'item_id',
                store.get('teampassItem').id
            ).then(item_pwd => {
                if (item_pwd) {
                    $('#form-item-password').val(item_pwd);

                    $('#card-item-visibility').html(store.get('teampassItem').itemVisibility);
                    $('#card-item-minimum-complexity').html(store.get('teampassItem').itemMinimumComplexity);

                    // Set selected folder id
                    $('#form-item-folder').val(selectedFolderId).change();

                    // show top back buttons
                    $('#but_back_top_left, #but_back_top_right').addClass('hidden');

                    // Show edition form
                    $('.form-item, #form-item-attachments-zone')
                        .removeClass('hidden');
                    $('.form-item-copy, #form-item-password-options, .form-item-action, #folders-tree-card, .columns-position')
                        .addClass('hidden');

                    // Initial 'user did a change'
                    userDidAChange = false;

                    // Force update of simplepassmeter
                    $('#form-item-password').pwstrength("forceUpdate");
                    $('#form-item-label').focus();

                    // Set type of action
                    $('#form-item-button-save').data('action', 'update_item');

                    // Does this folder contain Custom Fields
                    if (store.get('teampassItem').hasCustomCategories.length > 0) {
                        $('#form-item-field').removeClass('hidden');
                        $.each(store.get('teampassItem').hasCustomCategories, function(i, category) {
                            $('#form-item-category-' + category).removeClass('hidden');
                        })
                    } else {
                        $('#form-item-field, .form-item-category').addClass('hidden');
                    }            

                    // is user allowed to edit this item - overpass readonly folder
                    if (typeof store.get('teampassApplication').itemsList !== 'undefined') {
                        var itemsList = JSON.parse(store.get('teampassApplication').itemsList);
                        userItemRight = itemsList[store.get('teampassItem').id]?.rights;
                        if (userItemRight && userItemRight > 40 && $('#form-item-folder option:selected').attr('disabled') === 'disabled') {
                            $('#form-item-folder option:selected').removeAttr('disabled');
                        }
                    }

                    toastr.remove();
                }
            });
            // ---
        }
        //});
    }


    /**
     * Start items search
     */
    function searchItems(criteria) {
        if (criteria !== '') {
            // stop items loading (if on-going)
            store.update(
                'teampassApplication',
                function(teampassApplication) {
                    teampassApplication.itemsListStop = 1;
                }
            );

            // wait
            toastr.remove();
            toastr.info(
                '<?php echo $lang->get('searching'); ?>'
            );

            // clean
            $('#id_label, #id_desc, #id_pw, #id_login, #id_email, #id_url, #id_files, #id_restricted_to ,#id_tags, #id_kbs, .fields_div, .fields, #item_extra_info').html('');
            $('#button_quick_login_copy, #button_quick_pw_copy').addClass('hidden');
            $('#teampass_items_list').html('');

            // Continu the list of results
            finishingItemsFind(
                'search_for_items',
                $('#limited-search').is(":checked") === true ? store.get('teampassApplication').selectedFolder : false,
                criteria,
                0,
                0
            );
        }
    }

    /**
     * 
     */
    function finishingItemsFind(type, limited, criteria, start, totalItems) {
        // send query
        $.get(
            'sources/find.queries.php', {
                type: type,
                limited: limited,
                search: criteria,
                start: start,
                length: 10,
                totalItems: totalItems,
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                var pwd_error = '',
                    icon_login,
                    incon_link,
                    icon_pwd,
                    icon_favorite,
                    total_items = 0;

                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>', 'find.queries.php', type);
                if (debugJavascript === true) {
                    console.log('CE que nous avons trouvé');
                    console.log(data);
                }

                // Ensure correct div is not hidden
                $('#info_teampass_items_list').addClass('hidden');
                $('#table_teampass_items_list').removeClass('hidden');

                // Show Items list
                sList(data.html_json);
                total_items = parseInt(data.totalItems);

                if (data.start !== -1 && (data.start <= data.total)) {
                    // Continu the list of results
                    finishingItemsFind(
                        'search_for_items',
                        $('#limited-search').is(":checked") === true ?
                        store.get('teampassApplication').selectedFolder : false,
                        criteria,
                        data.start,
                        total_items
                    )
                } else {
                    toastr.remove();
                    toastr.info(
                        total_items + data.message,
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );

                    // Do some post treatments
                    $('#form-folder-path').html('');
                    $('#find_items').val('');

                    // Do drag'n'drop for the folders
                    prepareFolderDragNDrop();
                }
            }
        );
    }


    /**
     * Undocumented function
     *
     * @return void
     */
    function refreshVisibleFolders(forceRefreshCache = false) {
        var data = {
            'force_refresh_cache': forceRefreshCache,
        }
        if (debugJavascript === true) {
            console.log('Refresh visible folders');
            console.log(data);
        }

        $.post(
            'sources/items.queries.php', {
                type: 'refresh_visible_folders',
                data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>', 'items.queries.php', 'refresh_visible_folders');
                if (debugJavascript === true) {
                    console.log('TREE');
                    console.log(data);
                }
                //check if format error
                if (typeof data !== 'undefined' && data.error !== true) {
                    // Build html lists
                    var html_visible = '',
                        html_full_visible = '',
                        html_active_visible = '',
                        indentation = '',
                        disabled = '';

                    if (typeof data.html_json === 'undefined' || typeof data.html_json.folders === 'undefined') {
                        $('#jstree').html('<div class="alert alert-warning mt-3 mr-1 ml-1"><i class="fa-solid fa-exclamation-triangle mr-2"></i>' +
                            '<?php echo $lang->get('no_data_to_display'); ?>' +
                            '</div>');
                    } else {
                        refreshFoldersInfo(data.html_json.folders, 'clear');
                    }

                    // Shall we show the root folder
                    if (data.html_json.can_create_root_folder === 1) {
                        html_visible = '<option value="0"><?php echo $lang->get('root'); ?></option>';
                        html_full_visible = '<option value="0"><?php echo $lang->get('root'); ?></option>';
                        html_active_visible = '<option value="0"><?php echo $lang->get('root'); ?></option>';
                    } else {
                        html_visible = '<option value="0" disabled="disabled"><?php echo $lang->get('root'); ?></option>';
                    }

                    //
                    if (data.extra === "to_be_parsed") {
                        //data.html_json.folders = JSON.parse(data.html_json.folders);
                    }
                    let foldersArray = Array.isArray(data.html_json.folders) ? data.html_json.folders : [data.html_json.folders];
                    //console.log(foldersArray);

                    $.each(foldersArray, function(i, value) {
                        // Prepare options lists
                        html_visible += '<option value="' + value.id + '"' +
                            ((value.disabled === 1) ? ' disabled="disabled"' : '') +
                            ' data-parent-id="' + value.parent_id + '">' +
                            '&nbsp;'.repeat(value.level) +
                            value.title + (value.path !== '' ? ' [' + value.path + ']' : '') + '</option>';
                    });
                    
                    // Append new list
                    $('#form-item-folder, #form-item-copy-destination, #form-folder-add-parent,' +
                            '#form-folder-delete-selection, #form-folder-copy-source, #form-folder-copy-destination')
                        .find('option')
                        .remove()
                        .end()
                        .append(html_visible);
                    $(".no-root option[value='0']").remove();

                    if (debugJavascript === true) {
                        console.info('HTML VISIBLE:')
                        console.log(html_visible);
                    }

                    // Store in teampassUser
                    store.update(
                        'teampassUser',
                        function(teampassUser) {
                            teampassUser.folders = html_visible;
                        }
                    );


                    // remove ROOT option if exists
                    $('#form-item-copy-destination option[value="0"]').remove();
                } else {
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                    return false;
                }
            }
        );
    }


    /**
     * Get more info about folders (Categories)
     *
     * @return void
     */
    function refreshFoldersInfo(folders, action) {
        var action = action || '',
            sending = '';

        if (null === folders) return false;
        
        if (action === 'clear') {
            let foldersArray = Array.isArray(folders) ? folders : [folders];
            sending = JSON.stringify(foldersArray.map(a => parseInt(a.id)));
        } else if (action === 'update') {
            sending = JSON.stringify([folders]);
        }
        if (debugJavascript === true) {
            console.info('INPUTS for refresh_folders_other_info');
            console.log(sending);
        }
        
        $.post(
            'sources/items.queries.php', {
                type: 'refresh_folders_other_info',
                data: sending,
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>', 'items.queries.php', 'refresh_folders_other_info');
                if (debugJavascript === true) {
                    console.info('RESULTS for refresh_folders_other_info');
                    console.log(data);
                }

                startedItemsListQuery = false;

                //check if format error
                if (typeof data !== 'undefined' && data.error !== true) {
                    // Store in session
                    if (action === 'clear') {
                        // Handle the data
                        $.each(folders, function(index, item) {
                            if (typeof data.result !== 'undefined' && typeof data.result[item.id] !== 'undefined') {
                                folders[index]['categories'] = data.result[item.id].categories;
                                folders[index]['complexity'] = data.result[item.id].complexity;
                                folders[index]['visibilityRoles'] = data.result[item.id].visibilityRoles;
                            }
                        });
                        // Stare the data
                        store.update(
                            'teampassApplication',
                            function(teampassApplication) {
                                teampassApplication.foldersList = folders;
                            }
                        );
                    } else if (action === 'update') {
                        // Store the data
                        var currentFoldersList = store.get('teampassApplication').foldersList;
                        $.each(currentFoldersList, function(index, item) {
                            if (item.id === parseInt(folders) && typeof data.result[folders] !== 'undefined') {
                                currentFoldersList[index].categories = data.result[folders].categories;
                                currentFoldersList[index].complexity = data.result[folders].complexity;
                                currentFoldersList[index].visibilityRoles = data.result[folders].visibilityRoles;

                                store.update(
                                    'teampassApplication',
                                    function(teampassApplication) {
                                        foldersList = currentFoldersList;
                                    }
                                );
                                return true;
                            }
                        });

                    }
                } else {
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                    return false;
                }
                toastr.remove();
            }
        );
    }


    /*
     * builds the folders tree
     */
    function refreshTree(node_to_select, do_refresh, refresh_visible_folders) {
        do_refresh = do_refresh || ''
        node_to_select = node_to_select || '';
        refresh_visible_folders = refresh_visible_folders || true;

        if (refresh_visible_folders !== true) {
            $('#jstree').jstree('deselect_all');
            $('#jstree').jstree('select_node', '#li_' + groupe_id);
            return false;
        }

        if (do_refresh === true || store.get('teampassApplication').jstreeForceRefresh === 1) {
            $('#jstree').jstree(true).refresh();
        }

        if (node_to_select !== '') {
            $('#jstree').jstree('deselect_all');

            $('#jstree')
                .one('refresh.jstree', function(e, data) {
                    data.instance.select_node('#li_' + node_to_select);
                });
        }

        $(this).delay(500).queue(function() {
            refreshVisibleFolders(true);
            $(this).dequeue();
        });
    }

    /**
     * 
     */
    function ListerItems(groupe_id, restricted, start, stop_listing_current_folder = 0, showToastr = false) {
        var me = $(this);
        stop_listing_current_folder = stop_listing_current_folder || '0';
        if (debugJavascript === true) console.log('LIST OF ITEMS FOR FOLDER ' + groupe_id);
        // Exit if no folder is selected
        if (groupe_id === undefined) return false;

        // prevent launch of similar query in case of doubleclick
        if (requestRunning === true) {
            if (debugJavascript === true) console.log('Request ABORTED as already running!');
            return false;
        }
        requestRunning = true;

        // case where we should stop listing the items
        if (store.get('teampassApplication') !== undefined && store.get('teampassApplication').itemsListStop === 1) {
            //requestRunning = false;
            store.update(
                'teampassApplication',
                function(teampassApplication) {
                    teampassApplication.itemsListStop = 0;
                }
            );
        }

        // Evaluate number of items to display
        let nb_items_by_query = '<?php echo !empty($SETTINGS['nb_items_by_query']) ? htmlspecialchars($SETTINGS['nb_items_by_query']) : 'auto'; ?>';
        if (nb_items_by_query === 'auto' || isNaN(parseInt(nb_items_by_query))) {
            // Based on screen height
            nb_items_by_query = Math.max(Math.round((screenHeight - 450) / 23), 2);
        } else {
            // Admin choosen value
            nb_items_by_query = parseInt(nb_items_by_query);
        }

        // Store parameter in teampassApplication.itemsShownByQuery
        store.update(
            'teampassApplication',
            function(teampassApplication) {
                teampassApplication.itemsShownByQuery = nb_items_by_query;
            }
        );

        if (stop_listing_current_folder === 1) {
            me.data('requestRunning', false);
            // Store listing criteria
            store.update(
                'teampassApplication',
                function(teampassApplication) {
                    teampassApplication.itemsListFolderId = parseInt(groupe_id),
                        teampassApplication.itemsListRestricted = restricted,
                        teampassApplication.itemsListStart = start,
                        teampassApplication.itemsListStop = 0
                }
            );
        } else {
            store.update(
                'teampassApplication',
                function(teampassApplication) {
                    teampassApplication.itemsListStop = 0
                }
            );
        }

        // Hide any info
        $('#info_teampass_items_list').addClass('hidden');

        if (groupe_id !== undefined || groupe_id !== '') {
            //refreshTree(groupe_id);
            if (query_in_progress != 0 && query_in_progress != groupe_id && request !== undefined) {
                request.abort(); //kill previous query if needed
            }
            query_in_progress = groupe_id;
            if (start == 0) {
                //clean form
                $('#teampass_items_list, #items_folder_path').html('');
            }

            store.update(
                'teampassApplication',
                function(teampassApplication) {
                    teampassApplication.selectedFolder = parseInt(groupe_id),
                    teampassApplication.itemsList = ''
                }
            );

            if ($('.tr_fields') !== undefined) {
                $('.tr_fields, .newItemCat, .editItemCat').addClass('hidden');
            }

            // Inform user
            if (showToastr === true) {
                toastr.remove();
                toastr.info(
                    '<?php echo $lang->get('opening_folder'); ?><i class="fa-solid fa-circle-notch fa-spin ml-2"></i>'
                );
            }

            // clear storage 
            store.update(
                'teampassUser',
                function(teampassUser) {
                    teampassUser.itemsList = '';
                }
            );

            // Prepare data to be sent
            var dataArray = {
                id: store.get('teampassApplication').selectedFolder,
                restricted: restricted === "" ? "" : restricted,
                start: start !== undefined ? start : 0,
                uniqueLoadData: store.get('teampassApplication').queryUniqueLoad !== undefined ? store.get('teampassApplication').queryUniqueLoad : "",
                nb_items_to_display_once: store.get('teampassApplication').itemsShownByQuery,
            };

            if (debugJavascript === true) {
                console.log('Do list of items in folder with next parameters:');
                console.log(JSON.stringify(dataArray));
            }
            
            //ajax query
            var request = $.post('sources/items.queries.php', {
                    type: 'do_items_list_in_folder',
                    data: prepareExchangedData(JSON.stringify(dataArray), 'encode', '<?php echo $session->get('key'); ?>'),
                    key: '<?php echo $session->get('key'); ?>',
                },
                function(retData) {
                    //get data
                    data = decodeQueryReturn(retData, '<?php echo $session->get('key'); ?>', 'items.queries.php', 'do_items_list_in_folder');

                    if (debugJavascript === true) {
                        console.log('LIST ITEMS');
                        console.log(data);
                    }

                    // reset doubleclick prevention
                    requestRunning = false;

                    // manage not allowed
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
                    }

                    // Hide New button if restricted folder
                    if (data.access_level === 10) {
                        $('#btn-new-item').addClass('hidden');
                    } else {
                        $('#btn-new-item').removeClass('hidden');
                    }

                    // to be done only in 1st list load
                    if (data.list_to_be_continued === 'end') {
                        var initialQueryData = $.parseJSON(data.uniqueLoadData);

                        // Update hidden variables
                        store.update(
                            'teampassItem',
                            function(teampassItem) {
                                teampassItem.IsPersonalFolder = parseInt(data.IsPersonalFolder),
                                    teampassItem.hasAccessLevel = parseInt(data.access_level),
                                    teampassItem.folderComplexity = parseInt(data.folder_complexity),
                                    teampassItem.hasCustomCategories = data.categoriesStructure
                            }
                        );
                        

                        // display path of folders
                        if ((initialQueryData.path.length > 0)) {
                            $('#form-folder-path')
                                .html('')
                                .append(rebuildPath(initialQueryData.path));
                        } else {
                            $('#form-folder-path').html('');
                        }

                    } else if (data.error === 'not_authorized') {
                        $('#items_folder_path').html('<i class="fa-solid fa-folder-open-o"></i>&nbsp;' + rebuildPath(data.arborescence));
                    } else {
                        // Store query results
                        store.update(
                            'teampassApplication',
                            function(teampassApplication) {
                                teampassApplication.queryUniqueLoad = data.uniqueLoadData;
                            }
                        );
                        if ($('#items_loading_progress').length == 0) {
                            $('#items_list_loader').after('<span id="items_loading_progress">' + Math.round(data.next_start * 100 / data.counter_full, 0) + '%</span>');
                        } else {
                            $('#items_loading_progress').html(Math.round(data.next_start * 100 / data.counter_full, 0) + '%');
                        }
                    }
                    //-----
                    if (data.array_items !== undefined &&
                        data.array_items.length === 0 &&
                        $('#teampass_items_list').html() === ''
                    ) {
                        // Show warning to user
                        $('#info_teampass_items_list')
                            .html('<div class="alert alert-info text-center col col-10" role="alert">' +
                                '<i class="fa-solid fa-info-circle mr-2"></i><?php echo $lang->get('no_item_to_display'); ?></b>' +
                                '</div>')
                            .removeClass('hidden');
                    }

                    if (data.error === 'is_pf_but_no_saltkey') {
                        //warn user about his saltkey
                        toastr.remove();
                        toastr.warning(
                            '<?php echo $lang->get('home_personal_saltkey_label'); ?>',
                            '', {
                                timeOut: 10000
                            }
                        );
                        return false;
                    } else if (data.error === 'not_authorized' || data.access_level === '') {
                        // Show warning to user
                        $('#info_teampass_items_list')
                            .html('<div class="alert alert-info text-center col col-lg-10" role="alert">' +
                                '<i class="fa-solid fa-warning mr-2"></i><?php echo $lang->get('not_allowed_to_see_pw'); ?></b>' +
                                '</div>')
                            .removeClass('hidden');

                    } else if ((store.get('teampassApplication').userIsReadOnly === 1) //&& data.folder_requests_psk == 0
                        ||
                        data.access_level === 10
                    ) {
                        //readonly user
                        $('#item_details_no_personal_saltkey, #item_details_nok').addClass('hidden');
                        $('#item_details_ok, #items_list').removeClass('hidden');
                        //$('#more_items').remove();

                        store.update(
                            'teampassApplication',
                            function(teampassApplication) {
                                teampassApplication.bypassComplexityOnCreation = parseInt(data.bloquer_creation_complexite);
                                teampassApplication.bypassComplexityOnEdition = parseInt(data.bloquer_modification_complexite);
                                //teampassApplication.personalSaltkeyRequired = parseInt(data.saltkey_is_required);
                            }
                        );

                        // show items
                        sList(data.html_json);

                        if (data.list_to_be_continued === 'yes') {
                            //set next start for query
                            store.update(
                                'teampassApplication',
                                function(teampassApplication) {
                                    teampassApplication.itemsListStart = parseInt(data.next_start);
                                }
                            );
                        } else {
                            store.update(
                                'teampassApplication',
                                function(teampassApplication) {
                                    teampassApplication.itemsListStart = data.list_to_be_continued;
                                }
                            );
                            $('.card-item-category').addClass('hidden');
                        }

                        proceed_list_update(stop_listing_current_folder);
                    } else {
                        //Display items
                        $('#item_details_no_personal_saltkey, #item_details_nok').addClass('hidden');
                        $('#item_details_ok, #items_list').removeClass('hidden');

                        store.update(
                            'teampassApplication',
                            function(teampassApplication) {
                                teampassApplication.bypassComplexityOnCreation = parseInt(data.bloquer_creation_complexite);
                                teampassApplication.bypassComplexityOnEdition = parseInt(data.bloquer_modification_complexite);
                                //teampassApplication.personalSaltkeyRequired = parseInt(data.saltkey_is_required);
                            }
                        );

                        // show items
                        sList(data.html_json);

                        // Prepare next iteration if needed
                        if (data.list_to_be_continued === 'yes') {
                            //set next start for query
                            store.update(
                                'teampassApplication',
                                function(teampassApplication) {
                                    teampassApplication.itemsListStart = parseInt(data.next_start);
                                }
                            );
                        } else {
                            store.update(
                                'teampassApplication',
                                function(teampassApplication) {
                                    teampassApplication.itemsListStart = data.list_to_be_continued;
                                }
                            );
                            $('.card-item-category').addClass('hidden');

                            if (debugJavascript === true) {
                                console.log('Liste complete des items')
                                console.log(JSON.parse(store.get('teampassApplication').itemsList));
                            }
                        }

                        proceed_list_update(stop_listing_current_folder);
                    }
                }
            );
        }
    }

    function sList(listOfItems) {
        if (debugJavascript === true) {
            console.log(listOfItems);
        }
        var counter = 0,
            prevIdForNextItem = -1;

        // Manage store
        if (store.get('teampassApplication').itemsList === '' || store.get('teampassApplication').itemsList === undefined) {
            var stored_datas = listOfItems;
        } else {
            var stored_datas = String(JSON.parse(store.get('teampassApplication').itemsList)).concat(listOfItems);
        }
        store.update(
            'teampassApplication',
            function(teampassApplication) {
                teampassApplication.itemsList = JSON.stringify(stored_datas);
            }
        );
        
        $.each(listOfItems, function(i, value) {
            var new_line = '',
                pwd_error = '',
                icon_all_can_modify = '',
                icon_cannot_see = '',
                icon_open= '',
                icon_login = '',
                icon_link = '',
                icon_pwd = '',
                icon_favorite = '',
                item_flag = '',
                item_grippy = '',
                visible_by_user = '';

            counter += 1;

            // ENsure numbers are ints
            value.anyone_can_modify = parseInt(value.anyone_can_modify);
            value.canMove = parseInt(value.canMove);
            value.expired = parseInt(value.expired);
            value.is_favorite = parseInt(value.is_favorite);
            value.is_result_of_search = parseInt(value.is_result_of_search);
            value.item_id = parseInt(value.item_id);
            value.open_edit = parseInt(value.open_edit);
            value.rights = parseInt(value.rights);
            value.tree_id = parseInt(value.tree_id);
            value.display = parseInt(value.display);
            value.display_item = parseInt(value.display_item);
            value.enable_favourites = parseInt(value.enable_favourites);

            // Check access restriction
            if (value.rights > 0 && value.user_restriction_allowed_for_user === true) {
                // Should I populate previous item with this new id
                if (debugJavascript === true) {
                    console.log('current id: '+value.item_id);
                    console.log(prevIdForNextItem);
                }
                if (prevIdForNextItem !== -1) {
                    //$('#list-item-row_' + value.item_id).attr('data-next-item-id', prevIdForNextItem.item_id);
                    //$('#list-item-row_' + value.item_id).attr('data-next-item-label', value.label);
                    $('[data-item-key="'+value.item_key+'"]')
                        //.attr('data-next-item-id', prevIdForNextItem.item_id)
                        .attr('data-next-item-key', prevIdForNextItem.item_key)
                        .attr('data-next-item-label', value.label);
                }
                
                // Prepare anyone can modify icon
                if ((value.anyone_can_modify === 1 || value.open_edit === 1)) {
                    icon_all_can_modify = '<span class="fa-stack fa-clickable pointer infotip list-item-clicktoedit mr-2" title="<?php echo $lang->get('edit'); ?>"><i class="fa-solid fa-circle fa-stack-2x"></i><i class="fa-solid fa-pen fa-stack-1x fa-inverse"></i></span>';
                }

                // Open item icon
                icon_open = '<span class="fa-stack fa-clickable pointer infotip list-item-clicktoshow mr-2" title="<?php echo $lang->get('open'); ?>"><i class="fa-solid fa-circle fa-stack-2x"></i><i class="fa-solid fa-book-open-reader fa-stack-1x fa-inverse"></i></span>';

                // Prepare mini icons
                if (store.get('teampassSettings') !== undefined && parseInt(store.get('teampassSettings').copy_to_clipboard_small_icons) === 1 &&
                    value.rights > 10
                ) {
                    // Login icon
                    if (value.login !== '') {
                        icon_login = '<span class="fa-stack fa-clickable fa-clickable-login pointer infotip mr-2" title="<?php echo $lang->get('item_menu_copy_login'); ?>" data-clipboard-text="' + sanitizeString(value.login) + '"><i class="fa-solid fa-circle fa-stack-2x"></i><i class="fa-solid fa-user fa-stack-1x fa-inverse"></i></span>';
                    }
                    // Pwd icon
                    if (value.pw_status !== 'pw_is_empty' && value.pw_status !== 'encryption_error') {
                        icon_pwd = '<span class="fa-stack fa-clickable fa-clickable-password pointer infotip mr-2" title="<?php echo $lang->get('item_menu_copy_pw'); ?>" data-item-key="' + value.item_key + '" data-item-label="' + value.label + '" data-item-id="' + value.item_id + '"><i class="fa-solid fa-circle fa-stack-2x"></i><i class="fa-solid fa-key fa-stack-1x fa-inverse"></i></span>';
                    }

                    // Now check if pwd is empty. If it is then warn user
                    if (value.pw_status === 'pw_is_empty') {
                        pwd_error = '<span class="fa-stack fa-clickable fa-clickable-password pointer infotip mr-2" title="<?php echo $lang->get('password_is_empty'); ?>"><i class="fa-solid fa-circle fa-stack-2x"></i><i class="fa-solid fa-exclamation-triangle text-warning fa-stack-1x fa-inverse"></i></span>';
                    }
                }

                // Link icon
                if (value.link !== '') {
                    icon_link = '<span class="fa-stack fa-clickable pointer infotip mr-2" title="<?php echo $lang->get('open_website'); ?>"><a href="' + sanitizeString(value.link) + '" target="_blank" class="no-link"><i class="fa-solid fa-circle fa-stack-2x"></i><i class="fa-solid fa-link fa-stack-1x fa-inverse"></i></a></span>';
                }

                // Prepare Favorite icon
                if (store.get('teampassSettings') !== undefined && parseInt(store.get('teampassSettings').enable_favourites) === 1 &&
                    value.rights > 10
                ) {
                    if (value.is_favourited === 1) {
                        icon_favorite = '<span class="fa-stack fa-clickable item-favourite pointer infotip mr-2" title="<?php echo $lang->get('unfavorite'); ?>" data-item-id="' + value.item_id + '" data-item-key="' + value.item_key + '" data-item-favourited="1"><i class="fa-solid fa-circle fa-stack-2x"></i><i class="fa-solid fa-star fa-stack-1x fa-inverse text-warning"></i></span>';
                    } else {
                        icon_favorite = '<span class="fa-stack fa-clickable item-favourite pointer infotip mr-2" title="<?php echo $lang->get('favorite'); ?>" data-item-id="' + value.item_id + '" data-item-key="' + value.item_key + '" data-item-favourited="0"><i class="fa-solid fa-circle fa-stack-2x"></i><i class="fa-regular fa-star fa-stack-1x fa-inverse"></i></span>';
                    }
                }

                // Trash icon
                trash_link = '<span class="fa-stack fa-clickable warn-user pointer infotip mr-2 list-item-clicktodelete" title="<?php echo $lang->get('delete'); ?>" data-item-id="' + value.item_id + '" data-item-tree-id="' + value.tree_id + '"><i class="fa-solid fa-circle fa-stack-2x"></i><i class="fa-solid fa-trash fa-stack-1x fa-inverse"></i></span>';

                var description = '',
                    itemLabel = '';
                // Add username, email and url if requested
                if (store.get('teampassSettings') !== undefined && parseInt(store.get('teampassSettings').show_item_data) === 1) {
                    if (value.login !== '' || value.email !== '' || value.link !== '') {
                        itemLabel =
                            (value.login !== '' ? '<i class="fa-regular fa-circle-user mr-1 ml-2"></i>' + value.login : '') +
                            (value.email !== undefined && value.email !== '' ? '<i class="fa-solid fa-at mr-1 ml-2"></i>' + value.email : '') +
                            (value.link !== '' ? '<i class="fa-solid fa-link mr-1 ml-2"></i>' + value.link : '');
                    }
                }
                // Add Description
                value.desc = htmlDecode(value.desc)
                description = (value.desc.replace(/<.*>/gi, '').trim() !== '' ? '<i>'+itemLabel + '</i><i class="fa-solid fa-heading mr-1 ml-2"></i>' + value.desc : '<i>'+itemLabel + '</i>');
                // Consolidate item label
                if (description !== '') {
                    description = '<span class="text-secondary small d-inline-block text-truncate">' + description + '</span>';
                }

                $('#teampass_items_list').append(
                    '<tr class="list-item-row' + (value.canMove === 1 ? ' is-draggable' : '') + ((store.get('teampassApplication').highlightFavorites === 1 && value.is_favourited === 1) ? ' bg-yellow' : '') + '" id="list-item-row_' + value.item_id + '" data-item-key="' + value.item_key + '" data-item-edition="' + value.open_edit + '" data-item-id="' + value.item_id + '" data-item-sk="' + value.sk + '" data-item-expired="' + value.expired + '" data-item-rights="' + value.rights + '" data-item-display="' + value.display + '" data-item-open-edit="' + value.open_edit + '" data-item-tree-id="' + value.tree_id + '" data-is-search-result="' + value.is_result_of_search + '" data-label="' + escape(value.label) + '">' +
                    '<td class="list-item-description px-3 py-0 align-middle d-flex">' +
                    '<span class="icon-container">' +
                    // Show user a grippy bar to move item
                    (value.canMove === 1  ? '<i class="fa-solid fa-ellipsis-v mr-2 dragndrop"></i>' : '') + //&& value.is_result_of_search === 0
                    // Show user a ban icon if expired
                    (value.expired === 1 ? '<i class="fa-regular fa-calendar-times mr-2 text-warning infotip" title="<?php echo $lang->get('not_allowed_to_see_pw_is_expired'); ?>"></i>' : '') +
                    // Show user that Item is not accessible
                    (value.rights === 10 ? '<i class="fa-regular fa-eye-slash fa-xs mr-2 text-primary infotip" title="<?php echo $lang->get('item_with_restricted_access'); ?>"></i>' : '') +
                    // Show user that password is badly encrypted
                    (value.pw_status === 'encryption_error' ? '<i class="fa-solid fa-exclamation-triangle fa-xs text-danger infotip mr-1" title="<?php echo $lang->get('pw_encryption_error'); ?>"></i>' : '') +
                    // Prepare item info
                    '</span>' +
                    '<span class="list-item-clicktoshow d-inline-flex' + (value.rights === 10 ? '' : ' pointer') + '" data-item-id="' + value.item_id + '" data-item-key="' + value.item_key + '">' +
                    // Show item fa_icon if set
                    (value.fa_icon !== '' ? '<i class="'+value.fa_icon+' mr-1 user-fa-icon"></i>' : '') +
                    '<span class="list-item-row-description d-inline-block' + (value.rights === 10 ? ' font-weight-light' : '') + '"><i class="item-favorite-star fa-solid' + ((store.get('teampassApplication').highlightFavorites === 1 && value.is_favourited === 1) ? ' fa-star mr-1' : '') + '"></i>' + value.label + '</span>' + (value.rights === 10 ? '' : description) +
                    '<span class="list-item-row-description-extend"></span>' +
                    '</span>' +
                    '<span class="list-item-actions hidden">' +
                    (value.rights === 10 ?
                        '<span class="fa-stack fa-clickable fa-clickable-access-request pointer infotip mr-2" title="<?php echo $lang->get('need_access'); ?>"><i class="fa-solid fa-circle fa-stack-2x text-danger"></i><i class="fa-regular fa-handshake fa-stack-1x fa-inverse"></i></span>' :
                        pwd_error + icon_open + icon_all_can_modify + icon_login + icon_pwd + icon_link + icon_favorite + trash_link) +
                    '</span>' +
                    (value.folder !== undefined ?
                        '<br><span class="text-secondary small font-italic pointer open-folder" data-tree-id="' +
                        value.tree_id + '"">[' + value.folder + ']</span>' : '') +
                    '</td>' +
                    '</tr>'
                );

                // Save id for usage
                prevIdForNextItem = {
                    //'item_id' : value.item_id,
                    'item_key' : value.item_key,
                    'label': value.label,
                };

                //---------------------
            }
        });

        // Sort entries
        var $tbody = $('#teampass_items_list');
        $tbody.find('tr').sort(function(a, b) {
            var tda = $(a).find('.list-item-row-description').text().toLowerCase();
            var tdb = $(b).find('.list-item-row-description').text().toLowerCase();
            // if a < b return 1
            return tda > tdb ? 1 :
                tda < tdb ? -1 :
                0;
        }).appendTo($tbody);

        // Show tooltips
        $('.infotip').tooltip();
    }

    $(document).on('click', '.open-folder', function() {
        if ($(this).data('tree-id') !== undefined) {
            if (debugJavascript === true) console.log($(this).data('tree-id'))

            // Prepare
            store.update(
                'teampassApplication',
                function(teampassApplication) {
                    teampassApplication.itemsListFolderId = parseInt($(this).data('tree-id'));
                }
            );
            store.update(
                'teampassApplication',
                function(teampassApplication) {
                    teampassApplication.selectedFolder = parseInt($(this).data('tree-id'));
                }
            );
            store.update(
                'teampassApplication',
                function(teampassApplication) {
                    teampassApplication.itemsListStart = 0;
                }
            );

            // Show
            ListerItems(
                $(this).data('tree-id'),
                '',
                0
            );

            $('#jstree').jstree('deselect_all');
            $('#jstree').jstree('select_node', '#li_' + $(this).data('tree-id'));
        }
    });

    /**
     * Builds the HTML path
     * @param  {[type]} data [description]
     * @return {[type]}      [description]
     */
    function rebuildPath(data) {
        var new_path = new_path_elem = '';
        $.each((data), function(i, value) {
            new_path_elem = '';
            if (value['visible'] === 1) {
                new_path_elem = ' data-id="' + value['id'] + '"';
            }

            new_path += '<li class="breadcrumb-item pointer path-elem" id="path_elem_' + value['id'] + '"' + new_path_elem + '>' + value['title'] + '</li>';
        });

        return new_path;
    }

    /**

     */
    function proceed_list_update(stop_proceeding) {
        stop_proceeding = stop_proceeding || '';

        if (stop_proceeding === '1' ||
            (store.get('teampassApplication').itemsListFolderId !== '' &&
                store.get('teampassApplication').itemsListStart !== 'end')
        ) {
            // Clear storage
            store.update(
                'teampassApplication',
                function(teampassApplication) {
                    teampassApplication.itemsListStop = 0;
                }
            );
            // Perform listing
            ListerItems(
                store.get('teampassApplication').itemsListFolderId,
                store.get('teampassApplication').itemsListRestricted,
                store.get('teampassApplication').itemsListStart,
                store.get('teampassApplication').itemsListStop
            );
            return false;
        }

        if (store.get('teampassApplication').itemsListStart !== 'end') {
            //Check if nb of items do display > to 0
            if (store.get('teampassApplication').itemsShownByQuery > 0) {
                ListerItems(
                    store.get('teampassApplication').selectedFolder,
                    '',
                    store.get('teampassApplication').itemsListStart
                );
            }
        } else {
            // Show tooltips
            $('.infotip').tooltip();

            // Update silently the info about the folder
            refreshFoldersInfo(
                store.get('teampassApplication').selectedFolder,
                'update'
            );
            toastr.remove();
            toastr.info(
                '<?php echo $lang->get('data_refreshed'); ?>',
                '', {
                    timeOut: 1000
                }
            );

            // Do drag'n'drop for the folders
            prepareFolderDragNDrop();

            
            // Prepare clipboard for PAssword
            // Click handler to copy passwords
            document.querySelectorAll('.fa-clickable-password').forEach(element => {
                element.addEventListener('click', async function() {
                    try {
                        // Get the item key
                        const itemKey = this.getAttribute('data-item-key');

                        // Fetch the password from the server
                        const password = await getItemPassword('at_password_copied', 'item_key', itemKey);

                        if (!password) {
                            // A password can be empty. Just exit.
                            return;
                        }

                        // Copy the password to the clipboard
                        await navigator.clipboard.writeText(password);

                        // User notifications
                        const clipboardDuration = parseInt(store.get('teampassSettings').clipboard_life_duration) || 0;
                        if (clipboardDuration === 0) {
                            toastr.info('<?php echo $lang->get("copy_to_clipboard"); ?>', '', {
                                timeOut: 2000,
                                positionClass: 'toast-bottom-right',
                                progressBar: true
                            });
                        } else {
                            toastr.warning('<?php echo $lang->get("clipboard_will_be_cleared"); ?>', '', {
                                timeOut: clipboardDuration * 1000,
                                progressBar: true
                            });

                            // Clear the clipboard content after a delay
                            const cleaner = new ClipboardCleaner(clipboardDuration);
                            cleaner.scheduleClearing(
                                () => {
                                    const clipboardStatus = JSON.parse(localStorage.getItem('clipboardStatus'));
                                    if (clipboardStatus.status === 'unsafe') {
                                        return;                                        
                                    }
                                    toastr.success('<?php echo $lang->get("clipboard_cleared"); ?>', '', {
                                        timeOut: 2000,
                                        positionClass: 'toast-bottom-right'
                                    });
                                },
                                (error) => {
                                    return;
                                }
                            );
                        }
                    } catch (error) {
                        toastr.error('<?php echo $lang->get("clipboard_error"); ?>', '', {
                            timeOut: 3000,
                            positionClass: 'toast-bottom-right',
                            progressBar: true
                        });
                    }
                });
            });

            // Select all elements with class .fa-clickable-login
            document.querySelectorAll('.fa-clickable-login').forEach(element => {
                element.addEventListener('click', async function() {
                    try {
                        // Get text in attribute data-clipboard-text
                        const loginText = this.getAttribute('data-clipboard-text');

                        if (!loginText) {
                            return;
                        }

                        // Copy text to clipboard
                        await navigator.clipboard.writeText(loginText);

                        // Send notification to user
                        toastr.info(
                            '<?php echo $lang->get("copy_to_clipboard"); ?>',
                            '', {
                                timeOut: 2000,
                                positionClass: 'toast-bottom-right',
                                progressBar: true
                            }
                        );
                    } catch (error) {
                        toastr.error(
                            '<?php echo $lang->get("clipboard_error"); ?>',
                            '', {
                                timeOut: 3000,
                                positionClass: 'toast-bottom-right',
                                progressBar: true
                            }
                        );
                    }
                });
            });

        }
    }


    function checkAccess(itemId, treeId, userId, actionType) {
        var data = {
            'itemId': parseInt(itemId),
            'treeId': parseInt(treeId),
            'userId': parseInt(userId),
            'action': actionType,
        };
        if (debugJavascript === true) {
            console.log(data);
        }

        // Create a new Promise
        return new Promise(function(resolve, reject) {
            $.post(
                'sources/items.queries.php', {
                    type: 'check_current_access_rights',
                    data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>', 'items.queries.php', 'show_details_item');
                    if (debugJavascript === true) {
                        console.log("DEBUG: checkAccess");
                        console.log(data);
                    }

                    resolve(data);
                }
            );
        });
    }


    /**
     *
     */
    function Details(itemDefinition, actionType, hotlink = false)
    {
        if (debugJavascript === true) {
            console.info('EXPECTED ACTION on ' + itemDefinition + ' is ' + actionType + ' -- ');
            console.log(itemDefinition);
        }

        // Clear old editors (otherwise the content is not updated)
        $('#form-item-description').summernote('destroy');
        $('#form-item-suggestion-description').summernote('destroy');

        // Init
        var hasItemAccess = false;
        if (hotlink === false) {
            var itemId = parseInt($(itemDefinition).data('item-id')) || '';
            var itemKey = parseInt($(itemDefinition).data('item-key')) || '';
            var itemTreeId = parseInt($(itemDefinition).data('item-tree-id')) || '';
            var itemSk = parseInt($(itemDefinition).data('item-sk')) || 0;
            var itemExpired = parseInt($(itemDefinition).data('item-expired')) || '';
            var itemRestricted = parseInt($(itemDefinition).data('item-restricted-id')) || '';
            var itemDisplay = parseInt($(itemDefinition).data('item-display')) || 0;
            var itemOpenEdit = parseInt($(itemDefinition).data('item-open-edit')) || 0;
            var itemReload = parseInt($(itemDefinition).data('item-reload')) || 0;
            var itemRights = parseInt($(itemDefinition).data('item-rights')) || 10;
        } else {
            var itemId = itemDefinition || '';
            var itemKey = itemDefinition || '';
            var itemTreeId = store.get('teampassApplication').selectedFolder || '';
            var itemSk = 0;
            var itemExpired = '';
            var itemRestricted = '';
            var itemDisplay = 1;
            var itemOpenEdit = 0;
            var itemReload = 0;
            var itemRights = parseInt($(itemDefinition).data('item-rights')) || 10;
        }

        // check if user still has access
        $.when(
            checkAccess(itemId, itemTreeId, <?php echo $session->get('user-id'); ?>, actionType)
        ).then(function(retData) {
            // is there an error?
            if (retData.error === true) {
                toastr.remove();
                toastr.error(
                    retData.message,
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                // Finished
                requestRunning = false;
                return false;
            }

            // if edition and retData.edition_locked === true then show message
            if (actionType === 'edit' && retData.edition_locked === true) {
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('error_item_currently_being_updated').'<br/>'.
                        $lang->get('remaining_lock_time'); ?>' +
                        (retData.edition_locked_delay === null ? 
                        '' 
                        :
                        ' : ' + retData.edition_locked_delay + ' <?php echo $lang->get('seconds');?>'),
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                // Finished
                requestRunning = false;
                return false;
            }
            
            // Is the user allowed?
            if (retData.access === false
                || (actionType === 'edit' && retData.edit === false)
                || (actionType === 'delete' && retData.delete === false)
            ) {
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('error_not_allowed_to'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                // Finished
                requestRunning = false;
                return false;
            }

            // Store current view
            savePreviousView();
            
            if (debugJavascript === true) console.log("Request is running: " + requestRunning)

            // Store status query running
            requestRunning = true;
            userDidAChange = false;

            // Select tab#1
            $('#form-item-nav-pills li:first-child a').tab('show');

            // Don't show details
            if (itemDisplay === 'no_display') {
                // Inform user
                toastr.remove();
                toastr.warning(
                    '<?php echo $lang->get('no_item_to_display'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );

                // Clear ongoing request status
                requestRunning = false;

                // Finished
                return false;
            }

            // If opening new item, reinit hidden fields
            if (store.get('teampassApplication').lastItemSeen !== itemId) {
                store.update(
                    'teampassApplication',
                    function(teampassApplication) {
                        teampassApplication.lastItemSeen = parseInt(itemId);
                    }
                );
                if (debugJavascript === true) console.log("Last seen item " + store.get('teampassApplication').lastItemSeen)
            }

            // do
            $('#card-item-password-history-button').addClass('hidden');

            // Prepare data to be sent
            var data = {
                'id': parseInt(itemId),
                'folder_id': parseInt(itemTreeId),
                'salt_key_required': itemSk,
                'expired_item': itemExpired,
                'restricted': itemRestricted,
                'folder_access_level': store.get('teampassItem').hasAccessLevel,
                'page': 'items',
                'rights': itemRights,
            };

            if (debugJavascript === true) {
                console.log("SEND");
                console.log(data);
            }

            //Send query
            $.post(
                'sources/items.queries.php', {
                    type: 'show_details_item',
                    data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    //decrypt data
                    data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>', 'items.queries.php', 'show_details_item');
                    requestRunning = true;
                    if (debugJavascript === true) {
                        console.log("RECEIVED object details");
                        console.log(data);
                    }

                    // Store not a new item
                    store.update(
                        'teampassItem',
                        function(teampassItem) {
                            teampassItem.isNewItem = 0
                        }
                    );

                    $('.delete-after-usage').remove();

                    // remove any track-change class on item form
                    //$('.form-item-control').removeClass('track-change');

                    if (data.error === true) {
                        toastr.remove();
                        requestRunning = false;

                        // Manage personal items key error
                        if (data.error_type !== 'undefined' && data.error_type === 'private_items_to_encrypt') {
                            toastr.error(
                                data.message,
                                '', {
                                    timeOut: 5000,
                                    progressBar: true
                                }
                            );

                            store.update(
                                'teampassUser', {},
                                function(teampassUser) {
                                    teampassUser.special = 'private_items_to_encrypt';
                                }
                            );
                            document.location.href = "index.php?page=items";
                        } else if (data.error_type !== 'undefined' && data.error_type === 'user_should_reencrypt_private_key' && store.get('teampassUser').temporary_code === '') {
                            // we have to ask the user to re-encrypt his privatekey
                            toastr.error(
                                data.message,
                                '', {
                                    timeOut: 10000,
                                    progressBar: true
                                }
                            );
                            
                            if (debugJavascript === true) console.log('LDAP user password has to encrypt his private key with hos new LDAP password')
                            // HIde
                            $('.content-header, .content').addClass('hidden');

                            // Show passwords inputs and form
                            $('#dialog-ldap-user-change-password-info')
                                .html('<i class="icon fa-solid fa-info mr-2"></i><?php echo $lang->get('ldap_user_has_changed_his_password');?>')
                                .removeClass('hidden');
                            $('#dialog-ldap-user-change-password').removeClass('hidden');
                        } else if (data.error_type !== 'undefined') {
                            toastr.warning(
                                data.message,
                                '', {
                                    // no parameter
                                }
                            );

                            // On toastr button click
                            $(document).on('click', '.toastr-inside-button', function() {
                                if (debugJavascript === true) console.log('LDAP user password has to change his auth password')
                                // HIde
                                $('.content-header, .content').addClass('hidden');

                                // Show passwords inputs and form
                                $('#dialog-ldap-user-change-password-info')
                                    .html('<i class="icon fa-solid fa-info mr-2"></i><?php echo $lang->get('ldap_user_has_changed_his_password');?>')
                                    .removeClass('hidden');
                                $('#dialog-ldap-user-change-password').removeClass('hidden');
                            });
                            
                        }


                        return false;
                    } else if ((data.user_can_modify === 0 && actionType === 'edit') ||
                        parseInt(data.show_details) === 0
                    ) {
                        toastr.remove();
                        toastr.error(
                            '<?php echo $lang->get('error_not_allowed_to'); ?>',
                            '', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                        requestRunning = false;
                        return false;
                    }


                    // Store scroll position
                    userScrollPosition = $(window).scrollTop();

                    // Scroll to top
                    $(window).scrollTop(0);

                    // SHould we show?
                    if (parseInt(data.show_detail_option) === 1 || itemExpired === 1) {
                        // SHow expiration alert
                        $('#card-item-expired').removeClass('hidden');
                    } else if (parseInt(data.show_detail_option) === 2) {
                        // Don't show anything
                        toastr.remove();
                        toastr.error(
                            '<?php echo $lang->get('not_allowed_to_see_pw'); ?>',
                            '<?php echo $lang->get('warning'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );

                        return false;
                    }

                    // Show header info
                    $('#card-item-visibility').html(store.get('teampassItem').itemVisibility);
                    $('#card-item-minimum-complexity').html(store.get('teampassItem').itemMinimumComplexity);

                    // Hide NEW button in case access_level <span 30
                    if (store.get('teampassItem').hasAccessLevel === 10) {
                        $('#item-form-new-button').addClass('hidden');
                    } else {
                        $('#item-form-new-button').removeClass('hidden');
                    }

                    // we have an error in the password : pwd_encryption_error
                    if (data.pwd_encryption_error === 'inconsistent_password') {
                        $('#card-item-pwd').after('<i class="fa-solid fa-bell text-orange fa-shake ml-3 delete-after-usage infotip" title="'+data.pwd_encryption_error_message+'"></i>');
                    }

                    // Update hidden variables
                    store.update(
                        'teampassItem',
                        function(teampassItem) {
                            teampassItem.id = parseInt(data.id),
                            teampassItem.tree_id = parseInt(data.folder),
                            teampassItem.folderId = parseInt(data.folder),
                            teampassItem.timestamp = data.timestamp,
                            teampassItem.user_can_modify = data.user_can_modify,
                            teampassItem.anyone_can_modify = data.anyone_can_modify,
                            teampassItem.edit_item_salt_key = data.edit_item_salt_key,
                            teampassItem.id_restricted_to = data.id_restricted_to,
                            teampassItem.id_restricted_to_roles = data.id_restricted_to_roles,
                            teampassItem.item_rights = itemRights
                        }
                    );

                    if (actionType === 'show') {
                        // Prepare Views
                        $('.item-details-card, #item-details-card-categories').removeClass('hidden');
                        $('.form-item').addClass('hidden');

                        // Highlight selected item in list
                        if (store.get('teampassApplication').highlightSelected === 1) {
                            $('.list-item-row .list-item-description').removeClass('bg-black');
                            $('#list-item-row_' + data.id + ' .list-item-description').addClass('bg-black');
                        }

                        // show split mode or not
                        if (store.get('teampassUser').split_view_mode === 1) {
                            // Optionnal splited item view
                            $('#folder-tree-container').removeClass('col-md-5').addClass('col-md-3');
                            $('#items-list-container').removeClass('col-md-7').addClass('col-md-4');
                            $('#items-details-container').removeClass('col-md-12').addClass('col-md-5');
                            // Reduce menu size and trigger event listener
                            if ($('body').hasClass('sidebar-collapse') === false) {
                                $('a[data-widget="pushmenu"]').click();
                            }
                        } else {
                            // Defaut = full screen item view
                            $('#folder-tree-container').removeClass('col-md-5').addClass('hidden');
                            $('#items-list-container').removeClass('col-md-7').addClass('hidden');
                            $('#items-details-container').removeClass('col-md-5').addClass('col-md-12');
                        }
                        
                        // Show item details
                        $('#items-details-container').removeClass('hidden');

                        $('#form-item-suggestion-password').focus();
                        // If Description empty then remove it
                        if (data.description === '<p>&nbsp;</p>') {
                            $('#card-item-description')
                                .parents('.item-details-card')
                                .addClass('hidden');
                        } else {
                            $('#card-item-description')
                                .parents('.item-details-card')
                                .removeClass('hidden');
                        }
                    } else {
                        $('.form-item').removeClass('hidden');
                        $('#folders-tree-card').addClass('hidden');
                    }
                    $('#pwd-definition-size').val(data.pw_length);

                    // Store current item id in the DOM (cannot be updated in
                    // an other tab or window)
                    $('#items-details-container').data('id', data.id);

                    // Prepare card
                    const itemIcon = (data.fa_icon !== "") ? '<i class="'+data.fa_icon+' mr-1"></i>' : '';
                    $('#card-item-label, #form-item-title').html(itemIcon + data.label);
                    $('#form-item-label, #form-item-suggestion-label').val($('<div>').html(data.label).text());
                    $('#card-item-description, #form-item-suggestion-description').html(htmlDecode(data.description));
                    if (data.description === '') {
                        $('#card-item-description').addClass('hidden');
                    } else {
                        $('#card-item-description').removeClass('hidden');
                    }
                    $('#card-item-pwd').html('<?php echo $var['hidden_asterisk']; ?>');
                    $('#card-item-login').html(data.login);
                    $('#form-item-login, #form-item-suggestion-login, #form-item-server-login').val(data.login);

                    $('#card-item-email').text(data.email);
                    $('#form-item-email, #form-item-suggestion-email').val(data.email);
                    $('#card-item-url-text').text(data.url);
                    $('#card-item-url').attr("href", $('#card-item-url-text').text());
                    $('#form-item-url, #form-item-suggestion-url').val($('#card-item-url-text').text());
                    $('#form-item-restrictedToUsers').val(JSON.stringify(data.id_restricted_to));
                    $('#form-item-restrictedToRoles').val(JSON.stringify(data.id_restricted_to_roles));
                    $('#form-item-folder').val(data.folder);
                    $('#form-item-tags').val($('<div>').html(data.tags.join(' ')).text());
                    $('#form-item-icon').val(data.fa_icon);
                    $('#form-item-icon-show').html(itemIcon);

                    $('#form-item-password').pwstrength("forceUpdate");
                    $('#form-item-label').focus();

                    // Editor for description field
                    if (debugJavascript === true) {
                        console.log('>>>> create summernote');
                    }
                    $('#form-item-description')
                        .html(htmlDecode(data.description))
                        .summernote({
                            toolbar: [
                                ['style', ['style']],
                                ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
                                ['fontsize', ['fontsize']],
                                ['color', ['color']],
                                ['para', ['ul', 'ol', 'paragraph']],
                                ['insert', ['link', 'picture']],
                                //['height', ['height']],
                                ['view', ['codeview']]
                            ],
                            codeviewFilter: true,
                            codeviewIframeFilter: true,
                            callbacks: {
                                onChange: function(contents, $editable) {
                                    if (debugJavascript === true) console.log('Editor onChange:' + userDidAChange + " - " + requestRunning);
                                    if (userDidAChange === false && requestRunning === false) {
                                        if (debugJavascript === true) console.log('onChange:', contents, $editable);
                                        userDidAChange = true;
                                        if (debugJavascript === true) console.log('User did a change on #form-item-description > ' + userDidAChange);
                                        //$('#form-item-description').attr('data-change-ongoing', true);;
                                    }
                                }
                            }
                        })
                    //.summernote('editor.insertText', data.description);

                    $('#form-item-suggestion-description')
                        .html(htmlDecode(data.description))
                        .summernote({
                            toolbar: [
                                ['style', ['style']],
                                ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
                                ['fontsize', ['fontsize']],
                                ['color', ['color']],
                                ['para', ['ul', 'ol', 'paragraph']],
                                ['insert', ['link', 'picture']],
                                //['height', ['height']],
                                ['view', ['codeview']]
                            ],
                            codeviewFilter: true,
                            codeviewIframeFilter: true,
                            callbacks: {
                                onChange: function(contents, $editable) {
                                    if (userDidAChange === false && requestRunning === false) {
                                        if (debugJavascript === true) console.log('onChange:', contents, $editable);
                                        userDidAChange = true;
                                        if (debugJavascript === true) console.log('User did a change on #form-item-suggestion-description > ' + userDidAChange);
                                    }
                                }
                            }
                        });

                    // Buttons more theme compliants
                    $('.btn-light').addClass('btn-secondary').removeClass('btn-light');

                    //prepare nice list of users / groups
                    var html_users = '',
                        html_groups = '',
                        html_tags = '',
                        html_kbs = '';

                    $(data.tags).each(function(index, value) {
                        html_tags += '<span class="badge badge-success pointer tip mr-2" title="<?php echo $lang->get('list_items_with_tag'); ?>" onclick="searchItemsWithTags(\'' + value + '\')"><i class="fa-solid fa-tag fa-sm"></i>&nbsp;<span class="item_tag">' + value + '</span></span>';
                    });
                    if (html_tags === '') {
                        $('#card-item-tags').html('<?php echo $lang->get('none'); ?>');
                    } else {
                        $('#card-item-tags').html(html_tags);
                    }

                    $(data.links_to_kbs).each(function(index, value) {
                        html_kbs += '<a class="badge badge-primary pointer tip mr-2" href="<?php echo $SETTINGS['cpassman_url']; ?>/index.php?page=kb&id=' + value['id'] + '"><i class="fa-solid fa-map-pin fa-sm"></i>&nbsp;' + value['label'] + '</a>';

                    });
                    if (html_kbs === '') {
                        $('#card-item-kbs').html('<?php echo $lang->get('none'); ?>');
                    } else {
                        $('#card-item-kbs').html(html_kbs);
                    }


                    // Manage CATEGORIES / CUSTOM FIELDS
                    if (data.categories.length === 0) {
                        $('.card-item-category, .card-item-field, .form-item-category, #item-details-card-categories')
                            .addClass('hidden');
                        $('.no-item-fields').removeClass('hidden');
                        $('#card-item-fields').closest().addClass('collapsed');
                    } else {
                        // 
                        if (data.template_id === '') {
                            $('#list-group-item-main')
                                .children('.list-group')
                                .removeClass('hidden');
                            $('#card-item-category').removeClass('hidden');
                        }

                        if (data.fields.length === 0) {
                            if (actionType === 'show') {
                                $('#item-details-card-categories').addClass('hidden');
                                // Refresh last item seen
                                refreshListLastSeenItems();
                            } else {
                                // Show the inputs for EDITION
                                $(data.categories).each(function(index, category) {
                                    $('#form-item-field, #form-item-category-' + category).removeClass('hidden');
                                });
                            }
                        } else {
                            // Show expected categories
                            $('.no-item-fields, .form-item-category, .card-item-category').addClass('hidden');

                            // In edition mode, show all fields in expected Categories
                            $(data.categories).each(function(index, category) {
                                $('#form-item-field, #form-item-category-' + category).removeClass('hidden');
                            });
                            
                            // Now show expected fields and values
                            $(data.fields).each(function(index, field) {
                                // Show cateogry
                                $('#card-item-category-' + field.parent_id).removeClass('hidden');

                                // Is data encrypted
                                // Then base64 decode is required
                                if (field.encrypted === 1) {
                                    if (data.item_ready === true) {
                                        field.value = atob(field.value);
                                    } else {
                                        field.value = '';
                                        $('#card-item-field-title-' + field.id).after('<i class="fa-solid fa-ban text-danger ml-3 delete-after-usage"></i>');
                                    }
                                }

                                // Show field
                                if (field.masked === 1) {
                                    // Item card
                                    $('#card-item-field-' + field.id)
                                        .removeClass('hidden')
                                        .children(".card-item-field-value")
                                        .html(
                                            '<span data-field-id="' + field.id + '" class="pointer replace-asterisk"><?php echo $var['hidden_asterisk']; ?></span>' +
                                            '<input type="text" style="width:0px; height:0px; border:0px;" id="hidden-card-item-field-value-' + field.id + '" value="' + (field.value) + '">'
                                        )
                                    $('#card-item-field-' + field.id)
                                        .children(".btn-copy-clipboard-clear")
                                        .attr('data-clipboard-target', '#hidden-card-item-field-value-' + field.id);
                                } else {
                                    // Show Field
                                    $('#card-item-field-' + field.id)
                                        .removeClass('hidden')
                                        .children(".card-item-field-value")
                                        .html(field.value);
                                }
                                // Item edit form
                                $('#form-item-field-' + field.id)
                                    .children(".form-item-field-custom")
                                    .val(field.value);
                            });

                            // Manage template to show
                            if (data.template_id !== '' && $.inArray(data.template_id, data.categories) > -1) {
                                // Tick the box in edit mode
                                $('#template_' + data.template_id).iCheck('check');

                                // Hide existing data as replaced by Category template                                
                                $('#list-group-item-main, #item-details-card-categories')
                                    .children('.list-group')
                                    .addClass('hidden');

                                // Move the template in place of item main  
                                $('#card-item-category-' + data.template_id)
                                    .addClass('fields-to-move')
                                    .detach()
                                    .appendTo('#list-group-item-main');

                                // If only one category of Custom Fields
                                // Then hide the CustomFields div
                                if (actionType === 'show') {
                                    if (data.categories.length === 1) {
                                        $('#item-details-card-categories').addClass('hidden');
                                    } else {
                                        $('#item-details-card-categories').removeClass('hidden');
                                    }
                                }
                            }
                        }
                    }


                    // Handle the fact that KEYS may not yet be ready for this user
                    if (data.item_ready === false) {
                        $('#card-item-label').after('<i class="fa-solid fa-bell fa-shake fa-lg infotip ml-4 text-warning delete-after-usage" title="<?php echo $lang->get('sharekey_not_ready'); ?>"></i>');
                        store.update(
                            'teampassItem',
                            function(teampassItem) {
                                teampassItem.readyToUse = false
                            }
                        );
                    } else {
                        store.update(
                            'teampassItem',
                            function(teampassItem) {
                                teampassItem.readyToUse = true
                            }
                        );
                    }

                    // Waiting
                    $('#card-item-attachments').html("<?php echo $lang->get('please_wait'); ?>");

                    // Manage clipboard button
                    // Select all buttons with the class .btn-copy-clipboard-clear
                    document.querySelectorAll('.btn-copy-clipboard-clear').forEach(element => {
                        element.addEventListener('click', async function() {
                            try {
                                // Retrieve the target defined by data-clipboard-target
                                const targetId = this.getAttribute('data-clipboard-target');
                                if (!targetId) {
                                    return; // Stop if no target ID is defined
                                }

                                // Retrieve the value of the target field
                                const targetElement = document.getElementById(targetId);
                                if (!targetElement || !targetElement.textContent) {
                                    return; // Stop if the target element or its value is empty
                                }

                                // Copy the value to the clipboard
                                await navigator.clipboard.writeText(targetElement.textContent);

                                // Display a success notification
                                toastr.remove();
                                toastr.info(
                                    '<?php echo $lang->get("copy_to_clipboard"); ?>',
                                    '', {
                                        timeOut: 2000,
                                        progressBar: true,
                                        positionClass: 'toast-bottom-right'
                                    }
                                );
                            } catch (error) {
                                toastr.error(
                                    '<?php echo $lang->get("clipboard_error"); ?>',
                                    '', {
                                        timeOut: 3000,
                                        progressBar: true,
                                        positionClass: 'toast-bottom-right'
                                    }
                                );
                            }
                        });
                    });


                    // Prepare clipboard - COPY LOGIN
                    if (data.login !== '') {
                        $('#card-item-login-btn').removeClass('hidden');
                    } else {
                        $('#card-item-login-btn').addClass('hidden');
                    }
                    $('#pwd_empty_igloo').remove();

                    // Prepare clipboard - COPY PASSWORD
                    if (data.pw_length > 0 && store.get('teampassItem').readyToUse === true) {
                        // Delete existing clipboard
                        if (clipboardForPasswordListItems) {
                            clipboardForPasswordListItems.destroy();
                        }
                        $('#card-item-pwd-button').attr('data-id', data.id);

                        // Prepare clipboard using async function
                        const button = document.getElementById('card-item-pwd-button');
                        // Clone button in order to avoid issues with event listeners
                        const newButton = button.cloneNode(true);
                        button.parentNode.replaceChild(newButton, button);
                        // Add event listener to the new button
                        newButton.addEventListener('click', async function() {
                            try {
                                // Retrieve the password
                                const password = await getItemPassword('at_password_copied', 'item_id', data.id);

                                if (!password) {
                                    // A password can be empty. Just exit.
                                    return;
                                }

                                // Copy to clipboard
                                await navigator.clipboard.writeText(password);

                                // Notification for the user
                                const clipboardDuration = parseInt(store.get('teampassSettings').clipboard_life_duration) || 0;
                                if (clipboardDuration === 0) {
                                    toastr.info('<?php echo $lang->get("copy_to_clipboard"); ?>', '', {
                                        timeOut: 2000,
                                        positionClass: 'toast-bottom-right',
                                        progressBar: true
                                    });
                                } else {
                                    toastr.warning('<?php echo $lang->get("clipboard_will_be_cleared"); ?>', '', {
                                        timeOut: clipboardDuration * 1000,
                                        progressBar: true
                                    });

                                    // Clear the clipboard content after a delay
                                    const cleaner = new ClipboardCleaner(clipboardDuration);
                                    cleaner.scheduleClearing(
                                        () => {
                                            const clipboardStatus = JSON.parse(localStorage.getItem('clipboardStatus'));
                                            if (clipboardStatus.status === 'unsafe') {
                                                return;                                        
                                            }
                                            toastr.success('<?php echo $lang->get("clipboard_cleared"); ?>', '', {
                                                timeOut: 2000,
                                                positionClass: 'toast-bottom-right'
                                            });
                                        },
                                        (error) => {
                                            console.error('Error clearing clipboard:', error);
                                        }
                                    );
                                }
                            } catch (error) {
                                toastr.error('<?php echo $lang->get("clipboard_error"); ?>', '', {
                                    timeOut: 3000,
                                    positionClass: 'toast-bottom-right',
                                    progressBar: true
                                });
                            }
                        });
                        $('#card-item-pwd-button, #card-item-pwd, #card-item-pwd-show-button').removeClass('hidden');
                    } else {
                        $('#card-item-pwd-button, #card-item-pwd, #card-item-pwd-show-button').addClass('hidden');
                        // Case where pw is not ready (encryption on going)
                        if (data.pw_decrypt_info === 'error_no_sharekey_yet') {
                            $('#card-item-label').after('<i class="fa-solid fa-bell fa-shake fa-lg infotip ml-4 text-warning delete-after-usage" title="<?php echo $lang->get('sharekey_not_ready'); ?>"></i>');
                        }
                        $('#card-item-pwd-show-button').before('<i class="fa-solid fa-igloo infotip ml-2" id="pwd_empty_igloo" style="float:right" title="<?php echo $lang->get('password_is_empty'); ?>"></i>');
                        $('#card-item-pwd').after('<i class="fa-solid fa-ban text-teal ml-3 delete-after-usage"></i>');
                    }

                    // Prepare clipboard - COPY EMAIL
                    if (data.email !== '') {
                        $('#card-item-email-btn').removeClass('hidden');
                    } else {
                        $('#card-item-email-btn').addClass('hidden');
                    }

                    // Copy and open URL buttons
                    if (data.url !== '') {
                        $('#card-item-url-text-btn').removeClass('hidden');
                        $('#card-item-url').removeClass('hidden');
                    } else {
                        $('#card-item-url-text-btn').addClass('hidden');
                        $('#card-item-url').addClass('hidden');
                    }

                    // Prepare auto_update info
                    $('#card-item-misc').html('');
                    if (parseInt(data.auto_update_pwd_frequency) !== '0') {
                        $('#card-item-misc')
                            .append('<span class="fa-solid fa-shield infotip mr-4" title="<?php echo $lang->get('auto_update_enabled'); ?>&nbsp;' + data.auto_update_pwd_frequency + '"></span>');
                    }

                    // Show Notification engaged
                    if (data.notification_status === true) {
                        $('#card-item-misc')
                            .append('<span class="mr-4 icon-badge" id="card-item-misc-notification"><span class="fa-regular fa-bell infotip text-success" title="<?php echo $lang->get('notification_engaged'); ?>"></span></span>');
                    } else {
                        $('#card-item-misc')
                            .append('<span class="mr-4 icon-badge" id="card-item-misc-notification"><span class="fa-regular fa-bell-slash infotip text-warning" title="<?php echo $lang->get('notification_not_engaged'); ?>"></span></span>');
                    }

                    // Prepare counter
                    $('#card-item-misc')
                        .append('<span class="icon-badge mr-4"><span class="fa-regular fa-eye infotip" title="<?php echo $lang->get('viewed_number'); ?>"></span><span class="badge badge-info icon-badge-text icon-badge-far">' + data.viewed_no + '</span></span>');

                    // Delete after X views
                    if (data.to_be_deleted !== '') {
                        if (parseInt(data.to_be_deleted_type) === 1) {
                            $('#form-item-deleteAfterShown').val(data.to_be_deleted);
                            $('#form-item-deleteAfterDate').val('');
                        } else {
                            $('#form-item-deleteAfterShown').val('');
                            $('#form-item-deleteAfterDate').val(data.to_be_deleted);
                        }
                        // Show icon
                        $('#card-item-misc')
                            .append('<span class="icon-badge mr-5"><span class="fa-regular fa-trash-alt infotip" title="<?php echo $lang->get('automatic_deletion_engaged'); ?>"></span><span class="badge badge-danger icon-badge-text-bottom-right">' + data.to_be_deleted + '</span></span>');
                    }

                    // reset password shown info
                    $('#card-item-pwd').removeClass('pwd-shown');

                    //Anyone can modify button
                    if (parseInt(data.anyone_can_modify) === 1) {
                        $('#form-item-anyoneCanModify').iCheck('check');
                    } else {
                        $('#form-item-anyoneCanModify').iCheck('uncheck');
                    }

                    if (parseInt(data.show_details) === 1 && parseInt(data.show_detail_option) !== 2) {
                        // continue loading data
                        showDetailsStep2(itemId, actionType);
                    } else if (parseInt(data.show_details) === 1 && parseInt(data.show_detail_option) === 2) {
                        $('#item_details_nok').addClass('hidden');
                        $('#item_details_ok').addClass('hidden');
                        $('#item_details_expired_full').show();
                        $('#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item, #menu_button_add_fav, #menu_button_del_fav, #menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link').attr('disabled', 'disabled');
                        $('#div_loading').addClass('hidden');
                    } else {
                        //Dont show details
                        $('#item_details_nok').removeClass('hidden');
                        $('#item_details_nok_restriction_list').html('<div style="margin:10px 0 0 20px;"><b><?php echo $lang->get('author'); ?>: </b>' + data.author + '<br /><b><?php echo $lang->get('restricted_to'); ?>: </b>' + data.restricted_to + '<br /><br /><u><a href="#" onclick="openReasonToAccess()"><?php echo $lang->get('request_access_ot_item'); ?></a></u></div>');

                        $('#reason_to_access').remove();
                        $('#item_details_nok')
                            .append('<input type="hidden" id="reason_to_access" value="' + data.id + ',' + data.id_user + '">');

                        // Protect
                        $('#item_details_ok').addClass('hidden');
                        $('#item_details_expired').addClass('hidden');
                        $('#item_details_expired_full').addClass('hidden');
                        $('#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item, #menu_button_add_fav, #menu_button_del_fav, #menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link').attr('disabled', 'disabled');
                        $('#div_loading').addClass('hidden');
                    }

                    // Prepare bottom buttons
                    if ($('#list-item-row_'+data.id).prev('.list-item-row').attr('data-item-id') !== undefined) {
                        $('.but-prev-item')
                            .html('<i class="fa-solid fa-arrow-left mr-2"></i>' + unescape($('#list-item-row_'+data.id).prev('.list-item-row').attr('data-label')))
                            .attr('data-prev-item-id', $('#list-item-row_'+data.id).prev('.list-item-row').attr('data-item-id'))
                            .removeClass('hidden');
                    }
                    if ($('#list-item-row_'+data.id).next('.list-item-row').attr('data-item-id') !== undefined) {
                        $('.but-next-item')
                            .html('<i class="fa-solid fa-arrow-right mr-2"></i>' + unescape($('#list-item-row_'+data.id).next('.list-item-row').attr('data-label')))
                            .attr('data-next-item-id', $('#list-item-row_'+data.id).next('.list-item-row').attr('data-item-id'))
                            .removeClass('hidden');
                    }

                    // Inform user
                    toastr.remove();
                    toastr.info(
                        '<?php echo $lang->get('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );

                    return true;
                }
            );
        });
    }


    /*
     * Loading Item details step 2
     */
    function showDetailsStep2(id, actionType) {
        requestRunning = true;
        $.post(
            'sources/items.queries.php', {
                type: 'showDetailsStep2',
                id: id,
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>', 'items.queries.php', 'showDetailsStep2');

                if (debugJavascript === true) {
                    console.log('RECEIVED STEP2 - used key: <?php echo $session->get('key'); ?>');
                    console.log(data);
                }

                // Attachments
                if (data !== false) {
                    if (data.attachments.length === 0) {
                        $('#card-item-attachments-badge').html('<?php echo $lang->get('none'); ?>');
                        $('#card-item-attachments')
                            .html('<?php echo $lang->get('no_attachment'); ?>')
                            .parent()
                            .addClass('collapsed');
                    } else {
                        var html = '',
                            htmlFull = '',
                            counter = 1,
                            nbFiles = 0;
                        $.each(data.attachments, function(i, value) {
                            // Manage new row
                            if (counter === 1) {
                                htmlFull += '<div class="row">';
                                html += '<div class="row">';
                            }

                            html += '<div class="col-6">' +
                                '<div class="callout callout-info">' +
                                '<i class="' + value.icon + ' mr-2 text-info"></i>';

                            // Show VIEW image icon
                            if (value.is_image === 1) {
                                // Prepare filename
                                let filename = decodeFilename(value.filename);

                                html +=
                                    '<i class="fa-solid fa-eye infotip preview-image pointer mr-2" ' +
                                    'title="<?php echo $lang->get('see'); ?>" ' +
                                    'data-file-id="' + value.id + '" data-file-title="' +
                                    (isBase64(value.filename) === true ? atob(value.filename) : value.filename) + '"></i>';
                            }

                            // Show DOWNLOAD icon
                            downloadIcon =
                                '<a class="text-secondary infotip mr-2" href="sources/downloadFile.php?name=' + encodeURI(value.filename) + '&key=<?php echo $session->get('key'); ?>&key_tmp=' + value.key + '&fileid=' + value.id + '" title="<?php echo $lang->get('download'); ?>">' +
                                '<i class="fa-solid fa-file-download"></i></a>';
                            html += downloadIcon;

                            // Prepare filename
                            let filename = decodeFilename(value.filename);

                            // Show other info
                            html +=
                                '<span class="font-weight-bold mr-3">' + filename + '</span>' +
                                '<span class="mr-2 font-weight-light">(' + value.extension + ')</span>' +
                                '<span class="font-italic">' + value.size + '</span>' +
                                '</div></div>';

                            htmlFull += '<div class="col-6 edit-attachment-div"><div class="info-box bg-secondary-gradient">' +
                                '<span class="info-box-icon bg-info"><i class="' + value.icon + '"></i></span>' +
                                '<div class="info-box-content"><span class="info-box-text">' + filename + '.' + value.extension + '</span>' +
                                '<span class="info-box-text">' + downloadIcon +'</span>' +
                                '<span class="info-box-text"><i class="fa-solid fa-trash pointer delete-file" data-file-id="' + value.id + '"></i></span></div>' +
                                '</div></div>';

                            if (counter === 2) {
                                htmlFull += '</div>';
                                html += '</div>';
                                counter = 1;
                            } else {
                                counter += 1;
                            }
                            nbFiles += 1;
                        });
                        $('#card-item-attachments').html(html);
                        $('#card-item-attachments-badge').html(nbFiles);
                        $('#form-item-attachments').html(htmlFull);
                        $('#form-item-attachments-zone').removeClass('hidden');
                    }
                }
                // Hide loading state
                $('#card-item-attachments').nextAll().addClass('hidden');

                // Show restrictions with Badges
                var html_restrictions = '';
                $.each(store.get('teampassItem').id_restricted_to, function(i, value) {
                    html_restrictions +='<span class="badge badge-info mr-2 mb-1"><i class="fa-solid fa-group fa-sm mr-1"></i>' +
                        data.users_list.find(x => x.id === parseInt(value)).name + '</span>';
                }); 
                        
                $.each(store.get('teampassItem').id_restricted_to_roles, function(i, value) {                   
                    const role = data.roles_list.find(x => x.id === parseInt(value));
                    html_restrictions += (role ? '<span class="badge badge-info mr-2 mb-1"><i class="fa-solid fa-group fa-sm mr-1"></i>' + role.title  + '</span>' : '');
                });     
                        
                if (html_restrictions === '') {
                    $('#card-item-restrictedto').html('<?php echo $lang->get('no_special_restriction'); ?>');
                } else {
                    $('#card-item-restrictedto').html(html_restrictions);
                }


                $('#edit_past_pwds').attr('title', (data.history_of_pwds)); //htmlspecialchars_decode 
                $('#edit_past_pwds_div').html((data.history_of_pwds)); //htmlspecialchars_decode 

                //$('#id_files').html(data.files_id);
                //$('#hid_files').val(data.files_id);
                //$('#item_edit_list_files').html(data.files_edit);

                //$('#index-last-pwds').html(htmlspecialchars_decode(data.div_last_items));

                // function calling image lightbox when clicking on link
                $('a.image_dialog').click(function(event) {
                    event.preventDefault();
                    PreviewImage($(this).attr('href'), $(this).attr('title'));
                });


                // set indicator if item has change proposal
                if (parseInt(data.has_change_proposal) > 0) {
                    $('#item_extra_info').prepend('<i class="fa-solid fa-lightbulb-o fa-sm mi-yellow tip" title="<?php echo $lang->get('item_has_change_proposal'); ?>"></i>&nbsp;');
                }


                $('.infotip').tooltip();

                // Now load History
                if (actionType === 'show') {
                    loadItemHistory(store.get('teampassItem').id);
                } else if (actionType === 'edit') {
                    $.when(
                        getPrivilegesOnItem(selectedFolderId, 1)
                    ).then(function(retData) {
                        if (debugJavascript === true) {
                            console.log('getPrivilegesOnItem 3');
                            console.log(retData);
                        }
                        if (retData.error === true) {
                            toastr.remove();
                            toastr.error(
                                retData.message,
                                '', {
                                    timeOut: 5000,
                                    progressBar: true
                                }
                            );

                            requestRunning = false;

                            // Finished
                            return false;
                        } else {
                            // Retrieve the password
                            getItemPassword(
                                'at_password_shown_edit_form',
                                'item_id',
                                id
                            ).then(item_pwd => {
                                if (item_pwd) {
                                    $('#form-item-password').val(item_pwd);
                                }
                            });
                        }
                    });
                }

                // Prepare Select2 inputs
                $('.select2').select2({
                    language: '<?php echo $userLang = $session->get('user-language_code'); echo isset($userLang) === null ? $userLang : 'en'; ?>',
                    theme: "bootstrap4",
                });

                // Prepare datePicker
                $('#form-item-deleteAfterDate, .datepicker').datepicker({
                    format: '<?php echo str_replace(['Y', 'M'], ['yyyy', 'mm'], $SETTINGS['date_format']); ?>',
                    todayHighlight: true,
                    todayBtn: true,
                    language: '<?php echo $userLang = $session->get('user-language_code'); echo isset($userLang) === null ? $userLang : 'en'; ?>'
                });

                // Prepare Date range picker with time picker
                $('.timepicker').timepicker({
                    minuteStep: 5,
                    template: false,
                    showSeconds: true,
                    showMeridian: false,
                    showInputs: false,
                    explicitMode: true
                });

                // Valid OTV links
                if (data.otv_links !== undefined && data.otv_links > 0) {
                    $('#card-item-misc')
                        .append('<span class="icon-badge mr-4"><span class="fa-regular fa-handshake infotip" title="<?php echo $lang->get('existing_valid_otv_links'); ?>"></span><span class="badge badge-info icon-badge-text icon-badge-far">' + data.otv_links + '</span></span>');
                }

                // Manage if OTP is enabled for item
                if (data.otp_for_item_enabled === 1) {
                    $('#form-item-otp').iCheck('check');
                } else {
                    $('#form-item-otp').iCheck('uncheck');
                }
                $('#form-item-otpPhoneNumber').val(data.otp_phone_number);
                $('#form-item-otpSecret').val(data.otp_secret);

                // Delete inputs related files uploaded but not confirmed
                var data = {
                    'item_id': store.get('teampassItem').id,
                }

                $.post(
                    "sources/items.queries.php", {
                        type: 'delete_uploaded_files_but_not_saved',
                        data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                        key: '<?php echo $session->get('key'); ?>'
                    },
                    function (data) {
                        /*// add track-change class on item form
                        setTimeout(
                            $('#form-item-label, #form-item-description, #form-item-login, #form-item-password, #form-item-email, #form-item-url, #form-item-folder, #form-item-restrictedto, #form-item-tags, #form-item-anyoneCanModify, #form-item-deleteAfterShown, #form-item-deleteAfterDate, #form-item-anounce, .form-item-field-custom').addClass('track-change'),
                            2000
                        );*/

                        requestRunning = false;
                    }
                );

                // Load OTP stuff
                store.update(
                    'teampassItem',
                    function(teampassItem) {
                        teampassItem.otp_code_generate = true
                    }
                );

                // Display OTP Code
                showOTPCode(id);
            }
        );
    };

    function showOTPCode(id) {
        if (store.get('teampassItem').otp_code_generate === false) {
            clearInterval(intervalId);
            return false;
        }
        return new Promise((resolve, reject) => {
            $.post(
                'sources/items.queries.php', {
                    type: 'show_opt_code',
                    id: id,
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    //decrypt data
                    data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>', 'items.queries.php', 'showDetailsStep3');

                    if (data.otp_code !== '' && data.otp_expires_in !== '' && parseInt(data.otp_enabled) === 1 && data.message === '') {
                        $('#card-item-opt_code').html(data.otp_code+'</span><i class="fa-regular fa-copy ml-2 text-secondary pointer" id="clipboard_otpcode"></i><span class="ml-2">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span id="countdown_otp" style="position: absolute;right:0px;"></span><span>');   
                        
                        // show countdown
                        $("#countdown_otp").countdown360({
                            radius      : 10,
                            seconds     : data.otp_expires_in,
                            fillStyle   : '#56bbd9',
                            strokeStyle : '#007bff',
                            fontSize    : 11,
                            fontColor   : '#FFFFFF',
                            label: false,
                            autostart: false,
                        }).start();
                        

                        // Prepare Clipboard for OTP Code                        
                        document.getElementById('clipboard_otpcode').addEventListener('click', async function() {
                            try {
                                const otpCode = data.otp_code;

                                if (!otpCode) {
                                    return;
                                }

                                // Copy to clipboard
                                await navigator.clipboard.writeText(otpCode);

                                // Send success notification
                                toastr.info(
                                    '<?php echo $lang->get("copy_to_clipboard"); ?>',
                                    '', {
                                        timeOut: 2000,
                                        positionClass: 'toast-bottom-right',
                                        progressBar: true
                                    }
                                );
                            } catch (error) {
                                toastr.error(
                                    '<?php echo $lang->get("clipboard_error"); ?>',
                                    '', {
                                        timeOut: 3000,
                                        positionClass: 'toast-bottom-right',
                                        progressBar: true
                                    }
                                );
                            }
                        });

                        if (debugJavascript === true) {
                            console.log("-------------");
                            console.log(data);
                        }

                        // Prepare recursive call to get new OTP code
                        var replayDelayInMilliseconds = data.otp_expires_in*1000;
                        intervalId = setTimeout(function() {
                            showOTPCode(id);
                        }, replayDelayInMilliseconds);

                        resolve(replayDelayInMilliseconds);
                    } else {
                        if (data.error === false) {
                            $('#card-item-opt_code').html('<?php echo $lang->get('none'); ?>');
                        } else {
                            $('#card-item-opt_code_error').html('<span class="text-warning pointer infotip" title="<?php echo $lang->get('error_otp_secret'); ?>"><i class="fa-solid fa-triangle-exclamation mr-1"></i><?php echo $lang->get('error'); ?></span>');
                            $('.infotip').tooltip();
                        }
                    }

                    resolve(false);
                }
            );
        });
    }


    // Clear history form
    $(document)
        .on('click', '#form-item-history-clear', function() {
            $('.history').val('');
        })
        .on('click', '#form-item-history-insert', function() {
            if ($('#form-item-history-label').val() === '' ||
                $('#form-item-history-date').val() === '' ||
                $('#form-item-history-time').val() === ''
            ) {
                // Inform user
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('all_fields_mandatory'); ?>',
                    '<?php echo $lang->get('warning'); ?>', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }

            // Insert new entry
            var data = {
                'item_id': store.get('teampassItem').id,
                'label': DOMPurify.sanitize($('#form-item-history-label').val()),
                'date': DOMPurify.sanitize($('#form-item-history-date').val()),
                'time': DOMPurify.sanitize($('#form-item-history-time').val()),
            }
            $.post(
                "sources/items.queries.php", {
                    type: 'history_entry_add',
                    data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>', 'items.queries.php', 'history_entry_add');
                    if (debugJavascript === true) console.log(data);
                    $('.history').val('');

                    // Inform user
                    toastr.info(
                        '<?php echo $lang->get('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                }
            );
        })
        .on('click', '.nav-link', function() {
            if ($(this).attr('href') === '#tab_5') {
                $('#form-item-buttons').addClass('hidden');
            } else {
                $('#form-item-buttons').removeClass('hidden');
            }

        });

    // When click on Trash attachment icon
    $(document).on('click', '.delete-file', function() {
        var thisButton = $(this),
            thisFileId = thisButton.data('file-id');

        if (thisFileId !== undefined && thisFileId !== '') {
            // Delete the file
            var data = {
                'file_id': thisFileId,
            };

            $.post(
                'sources/items.queries.php', {
                    type: 'delete_attached_file',
                    data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    //decrypt data
                    data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>', 'items.queries.php', 'delete_attached_file');
                    if (debugJavascript === true) console.log(data);

                    //check if format error
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
                        // Remove the file in UI
                        thisButton.closest('.edit-attachment-div').remove();

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

    // Handle quick add button for History and Attachments
    /*$(document).on('click', '#add-item-attachment', function() {
        if ($(this).next().hasClass('hidden') === true && store.get('teampassItem').user_can_modify === 1) {
            $(this).next().removeClass('hidden');
        } else {
            $(this).next().addClass('hidden');
        }
    });*/
    $(document).on('click', '#add-item-history', function() {
        if ($(this).next().hasClass('hidden') === true && store.get('teampassItem').user_can_modify === 1) {
            if (parseInt(store.get('teampassSettings').insert_manual_entry_item_history) === 1) {
                $(this).next().removeClass('hidden');
            }
        } else {
            if (parseInt(store.get('teampassSettings').insert_manual_entry_item_history) === 1) {
                $(this).next().addClass('hidden');
            }
        }
    });
    
    // Handle the icon + is clicked
    $(document).on('click', '.add-button', function() {
        if ($(this).data('add-type') === 'history') {
            // HISTORY
            // SHow dialog
            showModalDialogBox(
                '#warningModal',
                "<?php echo $lang->get('history_insert_entry'); ?>",
                $('#add_history_element').html(),
                '<?php echo $lang->get('history_insert_entry'); ?>',
                '<?php echo $lang->get('close'); ?>',
                'modal-xl'
            );

            // Prepare datepicker
            $('.datepicker').datepicker({
                format: '<?php echo str_replace(['Y', 'M'], ['yyyy', 'mm'], $SETTINGS['date_format']); ?>',
                todayHighlight: true,
                todayBtn: true,
                language: '<?php echo $userLang = $session->get('user-language_code'); echo isset($userLang) === null ? $userLang : 'en'; ?>'
            });

            $('#warningModal #add-history-label').focus();

            // Launch insertion
            $(document).on('click', '#warningModalButtonAction', function() {
                console.log($('#warningModal #add-history-label').val())
                if ($('#warningModal #add-history-label').val() === '' ||
                    $('#warningModal #add-history-date').val() === '' ||
                    $('#warningModal #add-history-time').val() === ''
                ) {
                    // Inform user
                    toastr.remove();
                    toastr.error(
                        '<?php echo $lang->get('all_fields_mandatory'); ?>',
                        '<?php echo $lang->get('warning'); ?>', {
                            timeOut: 2000,
                            progressBar: true
                        }
                    );
                    return false;
                }

                // Insert new entry
                var data = {
                    'item_id': store.get('teampassItem').id,
                    'label': DOMPurify.sanitize($('#warningModal #add-history-label').val()),
                    'date': DOMPurify.sanitize($('#warningModal #add-history-date').val()),
                    'time': DOMPurify.sanitize($('#warningModal #add-history-time').val()),
                }
                $.post(
                    "sources/items.queries.php", {
                        type: 'history_entry_add',
                        data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                        key: '<?php echo $session->get('key'); ?>'
                    },
                    function(data) {
                        data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>', 'items.queries.php', 'history_entry_add');
                        if (debugJavascript === true) console.log(data);
                        $('#warningModal .history').val('');

                        // Reload history
                        loadItemHistory(store.get('teampassItem').id);

                        // Inform user
                        toastr.info(
                            '<?php echo $lang->get('done'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );
                    }
                );
            });
            
        } else if ($(this).data('add-type') === 'attachment') {
            // ATTACHMENTS
            console.log('attachment')
        }
    });


    //calling image lightbox when clicking on link
    $(document).on('click', '.preview-image', function(event) {
        event.preventDefault();
        PreviewImage($(this).data('file-id'));
    });

    PreviewImage = function(fileId) {
        toastr.remove();
        toastr.info(
            '<?php echo $lang->get('loading_image'); ?>...<i class="fa-solid fa-circle-notch fa-spin fa-2x ml-2"></i>'
        );

        $.post(
            "sources/items.queries.php", {
                type: "image_preview_preparation",
                id: fileId,
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                //decrypt data
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>', 'items.queries.php', 'image_preview_preparation');
                if (debugJavascript === true) {
                    console.log('DEBUG : image preview');
                    console.log(data);
                }

                //check if format error
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
                    $("#card-item-preview").html('<img id="image_files" src="">');
                    //Get the HTML Elements
                    imageDialog = $("#card-item-preview");
                    imageTag = $('#image_files');

                    //Set the image src
                    imageTag.attr("src", "data:" + data.file_type + ";base64," + data.file_content);
                    imageTag.attr("class", "img-fluid");

                    //When the image has loaded, display the dialog
                    showModalDialogBox(
                        '#warningModal',
                        data.filename,
                        $(imageDialog).html(),
                        '',
                        'Close',
                        'modal-xl'
                    );

                    toastr.remove();
                    toastr.info(
                        '<?php echo $lang->get('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );


                    /*
                                        var pre = document.createElement('pre');
                                        pre.style.textAlign = "center";
                                        $(pre).append($(imageDialog).html());
                                        alertify
                                            .alert(pre)
                                            .set({
                                                label: '<?php echo $lang->get('close'); ?>',
                                                closable: false,
                                                padding: false,
                                                title: data.filename,
                                                resizable: true,
                                            })
                                            .resizeTo('90%', '250px');*/
                }
            }
        );
    };

    function loadItemHistory(item_id, collapsed = true)
    {
        $.post(
            "sources/items.queries.php", {
                type: "load_item_history",
                item_id: item_id,
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>', 'items.queries.php', 'load_item_history');
                if (debugJavascript === true) {
                    console.info('History:');
                    console.log(data);
                }
                if (data.error === '') {
                    var html = '',
                        nbHistoryEvents = 0,
                        previousPasswords = '<h6 class="mb-3"><?php echo $lang->get('next_passwords_were_valid_until_date'); ?></h6>';
                    $.each(data.history, function(i, value) {
                        html += '<div class="direct-chat-msg"><div class="direct-chat-info clearfix">' +
                            '<span class="direct-chat-name float-left">' + value.name + '</span>' +
                            '<span class="direct-chat-timestamp float-right">' + value.date + '</span>' +
                            '</div>' +
                            '<img class="direct-chat-img" src="' + value.avatar + '" alt="Message User Image">' +
                            '<div class="direct-chat-text"><span class="text-capitalize">' +
                            (value.action === '' ? '' : (value.action)) + '</span> ' +
                            (value.detail === '' ? '' : (' | ' + value.detail)) + '</div></div>';
                        nbHistoryEvents += 1;
                    });
                    // Display
                    $('#card-item-history').html(html);
                    $('#card-item-history-badge').html(nbHistoryEvents);
                }

                // Collapse History
                if (collapsed === true) {
                    $('#card-item-history').closest().addClass('collapsed');
                }

                // Display password history
                if (data.previous_passwords.length > 0) {
                    $.each(data.previous_passwords, function(i, value) {
                        previousPasswords += '<div class="row"><div class="col-8"><i class="fa-solid fa-key fa-2xs mr-2"></i><code>' + value.password + '</code></div><div class="col-4"><span class="badge bg-info text-dark">' + value.date + '</span></div></div>';
                    });
                    
                    // SHow dialog
                    $(document).on('click', '#card-item-password-history-button', function() {
                        showModalDialogBox(
                            '#warningModal',
                            '<i class="fa-solid fa-clock-rotate-left mr-2"></i><?php echo $lang->get('previously_used_passwords'); ?>',
                            previousPasswords,
                            '',
                            '<?php echo $lang->get('close'); ?>',
                            'modal-xl'
                        );
                    });

                    // show button
                    $('#card-item-password-history-button').removeClass('hidden');
                } else {
                    // hide button
                    $('#card-item-password-history-button').addClass('hidden');
                }

                // Hide loading state
                $('#card-item-history').nextAll().addClass('hidden');
            }
        );
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

    /**
     * Undocumented function
     *
     * @return void
     */
    function prepareOneTimeView() {
        var data = {
            "id": store.get('teampassItem').id,
            "days": $('#form-item-otv-days').val(),
            "views": $('#form-item-otv-views').val(),
        };

        //Send query
        $.post(
            "sources/items.queries.php", {
                type: "generate_OTV_url",
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                //check if format error
                if (data.error == "") {
                    $('#form-item-otv-link').val(data.url);
                    $('#form-item-otv-link').data('otv-id', data.otv_id);

                    // prepare clipboard
                    // Prepare Clipboard for OTV url                        
                    document.getElementById('form-item-otv-copy-button').addEventListener('click', async function() {
                        try {
                            const urlOtv = data.url;

                            if (!urlOtv) {
                                return;
                            }

                            // Copy to clipboard
                            await navigator.clipboard.writeText(urlOtv);

                            // Send success notification
                            toastr.info(
                                '<?php echo $lang->get("copy_to_clipboard"); ?>',
                                '', {
                                    timeOut: 2000,
                                    positionClass: 'toast-bottom-right',
                                    progressBar: true
                                }
                            );
                        } catch (error) {
                            toastr.error(
                                '<?php echo $lang->get("clipboard_error"); ?>',
                                '', {
                                    timeOut: 3000,
                                    positionClass: 'toast-bottom-right',
                                    progressBar: true
                                }
                            );
                        }
                    });
                }
            },
            "json"
        );
    }

    // Handle update OTV button
    $(document).on('click', '#form-item-otv-update', function() {
        var data = {
            "otv_id": $('#form-item-otv-link').data('otv-id'),
            "days": $('#form-item-otv-days').val(),
            "views": $('#form-item-otv-views').val(),
            "shared_globaly": $('#form-item-otv-subdomain').is(":checked") === true ? 1 : 0,
            "original_link": $('#form-item-otv-link').val(),
        };

        $.post(
            "sources/items.queries.php", {
                type: "update_OTV_url",
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                // Display new url
                if (data.new_url !== undefined) {
                    $('#form-item-otv-link').val(data.new_url);

                    // Prepare Clipboard for OTV url                        
                    document.getElementById('form-item-otv-copy-button').addEventListener('click', async function() {
                        try {
                            const urlOtv = data.url;

                            if (!urlOtv) {
                                return;
                            }

                            // Copy to clipboard
                            await navigator.clipboard.writeText(urlOtv);

                            // Send success notification
                            toastr.info(
                                '<?php echo $lang->get("copy_to_clipboard"); ?>',
                                '', {
                                    timeOut: 2000,
                                    positionClass: 'toast-bottom-right',
                                    progressBar: true
                                }
                            );
                        } catch (error) {
                            toastr.error(
                                '<?php echo $lang->get("clipboard_error"); ?>',
                                '', {
                                    timeOut: 3000,
                                    positionClass: 'toast-bottom-right',
                                    progressBar: true
                                }
                            );
                        }
                    });
                }
                toastr.remove();
                toastr.info(
                    '<?php echo $lang->get('updated'); ?>',
                    '', {
                        timeOut: 2000,
                        progressBar: true
                    }
                );
            }
        );
    });

    // Handle max value for OTV days number
    $('#form-item-otv-days').change(function () {
        console.log(parseInt($(this).attr('max')));
        if ($(this).val() > parseInt($(this).attr('max'))) {
            $(this).val($(this).attr('max'));
        }
    });

    /**
     */
    function getPrivilegesOnItem(val, edit, context) {
        context = context || ""; // make context optional

        // make sure to use correct selected folder
        if (val === false) {
            val = selectedFolderId;
        }
        if (debugJavascript === true) console.log('Get privilege for folder ' + val);        

        return new Promise(function(resolve, reject) {
            if (val === "" || typeof val === "undefined" || val === false) {
                /*toastr.remove();
                toastr.error(
                    '',
                    '<?php echo $lang->get('please_select_a_folder'); ?>',
                    {
                        timeOut: 5000,
                        positionClass: 'toast-bottom-right',
                        progressBar: true
                    }
                );*/
                resolve({
                    "error": true,
                    "message": '<?php echo $lang->get('please_select_a_folder'); ?>',
                });

                return false;
            }

            $.when(
                checkAccess(store.get('teampassItem').id, val, <?php echo $session->get('user-id'); ?>, 'edit')
            ).then(function(retData) {
                // is there an error?
                if (retData.error === true) {
                    toastr.remove();
                    toastr.error(
                        retData.message,
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                    // Finished
                    requestRunning = false;
                    return false;
                }

                // if edition and retData.edition_locked === true then show message
                if (retData.edition_locked === true) {
                    toastr.remove();
                    toastr.error(
                        '<?php echo $lang->get('error_item_currently_being_updated'); ?>',
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                    // Finished
                    requestRunning = false;
                    return false;
                }
                
                // Is the user allowed?
                if (retData.access === false
                    || retData.edit === false
                ) {
                    toastr.remove();
                    toastr.error(
                        '<?php echo $lang->get('error_not_allowed_to'); ?>',
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                    // Finished
                    requestRunning = false;
                    return false;
                }
                $.post(
                    "sources/items.queries.php", {
                        type: "get_complixity_level",
                        folder_id: val,
                        context: context,
                        item_id: store.get('teampassItem').id,
                        key: '<?php echo $session->get('key'); ?>'
                    },
                    function(data) {
                        //decrypt data
                        data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>', 'items.queries.php', 'get_complixity_level');

                        if (debugJavascript === true) {
                            console.info('GET COMPLEXITY LEVEL');
                            console.log(data);
                        }
                        var executionStatus = true;

                        if (data.error === false) {
                            // Do some prepartion

                            // Prepare list of users where needed
                            $('#form-item-restrictedto, #form-item-anounce').empty().change(); //.val('')
                            // Users restriction list
                            var html_restrictions = '';

                            $(data.usersList).each(function(index, value) {
                                // Prepare list for FORM
                                $("#form-item-restrictedto")
                                    .append('<option value="' + value.id + '" class="restriction_is_user">' + value.name + '</option>');

                                // Prepare list of emailers
                                $('#form-item-anounce').append('<option value="' + value.email + '">' + value.name + '</option>');
                            });
                            if (data.setting_restricted_to_roles === 1) {
                                //add optgroup
                                var optgroup = $('<optgroup label="<?php echo $lang->get('users'); ?>">');
                                $(".restriction_is_user").wrapAll(optgroup);

                                // Now add the roles to the list
                                $(data.rolesList).each(function(index, value) {
                                    $("#form-item-restrictedto")
                                        .append('<option value="role_' + value.id + '" class="restriction_is_role">' +
                                            value.title + '</option>');
                                });
                                /// Add a group label for Groups
                                $('.restriction_is_role').wrapAll($('<optgroup label="<?php echo $lang->get('roles'); ?>">'));
                            }


                            //
                            $('#card-item-visibility').html(data.visibility);

                            // Prepare Select2
                            $('.select2').select2({
                                language: '<?php echo $session->get('user-language_code'); ?>',
                                theme: "bootstrap4",
                            });

                            // Show selected restricted inputs
                            $('#form-item-restrictedto')
                                .val(data.usersList.concat(
                                    data.rolesList.map(i => 'role_' + i)))
                                .change();

                            // If restricted to Users then select them
                            if (store.get('teampassItem').id_restricted_to !== undefined) {
                                $('#form-item-restrictedto')
                                    .val(store.get('teampassItem').id_restricted_to)
                                    .trigger('change');
                            }
                        }

                        store.update(
                            'teampassItem',
                            function(teampassItem) {
                                teampassItem.folderId = val,
                                    teampassItem.error = data.error === undefined ? '' : data.error,
                                    teampassItem.message = data.message === undefined ? '' : data.message,
                                    teampassItem.folderComplexity = data.val === undefined ? '' : parseInt(data.val),
                                    teampassItem.folderIsPersonal = data.personal === undefined ? '' : parseInt(data.personal),
                                    teampassItem.itemMinimumComplexity = data.complexity === undefined ? '' : data.complexity,
                                    teampassItem.itemVisibility = data.visibility === undefined ? '' : data.visibility,
                                    teampassItem.id_restricted_to = data.usersList === undefined ? '' : data.usersList,
                                    teampassItem.id_restricted_to_roles = data.rolesList === undefined ? '' : data.rolesList,
                                    teampassItem.item_rights = data.itemAccessRight === undefined ? '' : data.itemAccessRight
                            }
                        );
                        //if (debugJavascript === true) console.log('Content of teampassItem;')
                        //if (debugJavascript === true) console.log(store.get('teampassItem'))
                        resolve({
                            "error": data.error === undefined ? '' : data.error,
                            "message": data.message === undefined ? '' : data.message,
                        });
                    }
                );
            });
        });
    }

    $('.password-generate').click(function() {
        var elementId = $(this).data('id');
        $('#' + elementId).focus();
        if (debugJavascript === true) console.log(elementId);

        // If no criteria is set then do secure
        var secure_pwd = false;
        var anyBoxesChecked = false;
        if ($('.password-definition:checked').length > 0) {
            anyBoxesChecked = true;
        }
        if (anyBoxesChecked === false || $('#pwd-definition-secure').prop('checked') === true) {
            secure_pwd = true;
        }

        $.post(
            "sources/main.queries.php", {
                type: "generate_password",
                type_category: 'action_user',
                size: $('#pwd-definition-size').val() ?? 20,
                lowercase: $('#pwd-definition-lcl').prop("checked"),
                numerals: $('#pwd-definition-numeric').prop("checked"),
                capitalize: $('#pwd-definition-ucl').prop("checked"),
                symbols: $('#pwd-definition-symbols').prop("checked"),
                secure_pwd: secure_pwd,
                force: "false",
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                if (debugJavascript === true) console.log(data)
                if (data.error == "true") {
                    // error
                    toastr.remove();
                    toastr.error(
                        data.error_msg,
                        '<?php echo $lang->get('error'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                    return false;
                } else {
                    $("#" + elementId).val(data.key).focus();

                    // Form has changed
                    userDidAChange = true;
                    if (debugJavascript === true) console.log('User did a change during generate_password > ' + userDidAChange);
                    //$('#' + elementId).attr('data-change-ongoing', true);;

                    $("#form-item-password").pwstrength("forceUpdate");

                    // SHow button in sticky footer
                    //$('#form-item-buttons').addClass('sticky-footer');
                }
            }
        );
    });

    /**
     * On tag badge click, launch the search query
     */
    function searchItemsWithTags(criteria) {
        if (criteria !== '') {
            $('#folders-tree-card, .columns-position').removeClass('hidden');
            $('.form-item-action, .form-item, .form-folder-action').addClass('hidden');

            $('#find_items').val(criteria);

            searchItems(criteria);
        }
    }

    /**
     * Checks if string is base64 encoded
     *
     * @return bool
     */
    function isBase64(str) {
        try {
            return btoa(atob(str)) == str;
        } catch (err) {
            return false;
        }
    }

    /**
     * Scroll back to previous vertical position
     *
     * @return void
     */
    function scrollBackToPosition() {
        // Scroll back to position
        if (store.get('teampassApplication').tempScrollTop > 0) {
            window.scrollTo({
                top: store.get('teampassApplication').tempScrollTop
            });
        }
    }


    function prepareFolderDragNDrop()
    {
        $('.is-draggable').draggable({
            cursor: 'move',
            cursorAt: {
                top: -5,
                left: -5
            },
            opacity: 0.8,
            appendTo: 'body',
            stop: function(event, ui) {
                $(this).removeClass('bg-warning');
            },
            start: function(event, ui) {
                $(this).addClass('bg-warning');
            },
            helper: function(event) {
                return $('<div>')
                            .addClass('bg-gray p-2 font-weight-light')
                            .text($(this).find('.list-item-row-description').text());
            }
        });
        $('.folder').droppable({
            hoverClass: 'bg-warning',
            tolerance: 'pointer',
            drop: function(event, ui) {
                // Check if same folder
                if (parseInt($(this).attr('id').substring(4)) === parseInt(ui.draggable.data('item-tree-id'))) {
                    toastr.remove();
                    toastr.error(
                        '<?php echo $lang->get('error_not_allowed_to'); ?>',
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                    return false;
                }

                // Warn user that it starts
                toastr.info(
                    '<i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>'
                );

                // Hide helper
                ui.draggable.addClass('hidden');

                //move item
                var data = {
                    'item_id': ui.draggable.data('item-id'),
                    'folder_id': $(this).attr('id').substring(4)
                }
                $.post(
                    'sources/items.queries.php', {
                        type: 'move_item',
                        data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                        key: '<?php echo $session->get('key'); ?>'
                    },
                    function(data) {
                        //decrypt data
                        data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>', 'items.queries.php', 'move_item');

                        if (debugJavascript === true) console.log(data)

                        if (data.error === true) {
                            toastr.remove();
                            toastr.error(
                                data.message,
                                '', {
                                    timeOut: 5000,
                                    progressBar: true
                                }
                            );
                            ui.draggable.removeClass('hidden');
                            return false;
                        }
                        
                        //increment / decrement number of items in folders
                        $('#itcount_' + data.from_folder).text(refreshFolderCounters($('#itcount_' + data.from_folder).text(), 'decrement'));
                        $('#itcount_' + data.to_folder).text(refreshFolderCounters($('#itcount_' + data.to_folder).text(), 'increment'));

                        toastr.remove();
                        toastr.info(
                            '<?php echo $lang->get('success'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );
                    }
                );
            }
        });
    }

    /**
     * Permits to refresh the folder counters when performing an item drag and drop
     */
    function refreshFolderCounters(counter, operation)
    {
        var splitCounter = counter.split('/');
        if (splitCounter.length <= 3) {
            if (operation === 'increment') {
                splitCounter[0]++;
                if (splitCounter.length === 3) {
                    splitCounter[1]++;
                }
            } else {
                splitCounter[0]--;
                if (splitCounter.length === 3) {
                    splitCounter[1]--;
                }
            }
        }
        
        return splitCounter.join('/');
    }

    $(document).ready(function() {
        let saveInProgress = false;

        // Prevent Enter key from propagating in label and password fields and do single save
        // Enter triggers save in these:
        $('#form-item-label, #form-item-password').on('keydown keyup keypress', function(e) {
            if ((e.key === 'Enter' || e.which === 13)) {
                e.preventDefault();
                e.stopPropagation();
                if (!saveInProgress) {
                    saveInProgress = true;
                    $('#form-item-button-save').click();
                    setTimeout(() => { saveInProgress = false; }, 1000);
                }
                return false;
            }
        });

        // Enter does nothing in these:
        $('#form-item-login, #form-item-email, #form-item-url, #form-item-icon').on('keydown keyup keypress', function(e) {
            if ((e.key === 'Enter' || e.which === 13)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });
        // Event listener for path elems
        $(document).on('click', '.path-elem', function() {
            // Read folder id
            let folder_id = $(this).data('id');

            // Only if valid id
            if (!isNaN(folder_id)) {
                // List items on folder
                ListerItems(folder_id, '', 0);

                // Update jstree selection
                $('#jstree').jstree('deselect_all');
                $('#jstree').jstree('select_node', '#li_' + folder_id);
            }
        });
/*
        //import SecureClipboardHandler from '../includes/js/secure-clipboard-handler.js';
        var languageVariables = {
            copy_to_clipboard: "<?php echo $lang->get('copy_to_clipboard'); ?>",
            clipboard_will_be_cleared: "<?php echo $lang->get('clipboard_will_be_cleared'); ?>",
            clipboard_cleared: "<?php echo $lang->get('clipboard_cleared'); ?>",
            password_copy_error: "<?php echo $lang->get('password_copy_error'); ?>",
            password_fetch_error: "<?php echo $lang->get('password_fetch_error'); ?>"
        };
        document.addEventListener('DOMContentLoaded', () => {
            const clipboardHandler = new SecureClipboardHandler(languageVariables);
        });*/
    });
</script>
