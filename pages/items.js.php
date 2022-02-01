<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 *
 * @file      items.js.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2022 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

if (
    isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id']) === true
    || isset($_SESSION['key']) === false || empty($_SESSION['key']) === true
) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php') === true) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php') === true) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception('Error file "/includes/config/tp.config.php" not exists', 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
if (! checkUser($_SESSION['user_id'], $_SESSION['key'], curPage($SETTINGS), $SETTINGS)) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

$var = [];
$var['hidden_asterisk'] = '<i class="fas fa-asterisk mr-2"></i><i class="fas fa-asterisk mr-2"></i><i class="fas fa-asterisk mr-2"></i><i class="fas fa-asterisk mr-2"></i><i class="fas fa-asterisk"></i>';

?>


<script type="text/javascript">
    var requestRunning = false,
        clipboardForLogin,
        clipboardForPassword,
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
        initialPageLoad = true;

    var debugJavascript = true;

    // Manage memory
    browserSession(
        'init',
        'teampassApplication', {
            lastItemSeen: false,
            selectedFolder: false,
            itemsListStop: '',
            itemsListStart: '',
            selectedFolder: '',
            itemsListFolderId: false,
            itemsListRestricted: '',
            itemsShownByQuery: '',
            foldersList: [],
            personalSaltkeyRequired: 0,
            uploadedFileId: '',
        }
    );

    browserSession(
        'init',
        'teampassItem', {
            IsPersonalFolder: '',
            hasAccessLevel: '',
            hasCustomCategories: '',
            id: '',
            timestamp: ''
        }
    );

console.log('User infor')
console.log(store.get('teampassUser'))

    // Build tree
    $('#jstree').jstree({
            'core': {
                'animation': 0,
                'check_callback': true,
                'data': {
                    'url': './sources/tree.php',
                    'dataType': 'json',
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
                    'Loading ...': '<?php echo langHdl('loading'); ?>...'
                },
            },
            'plugins': [
                'state', 'search'
            ]
        })
        // On node select
        .bind('select_node.jstree', function(e, data) {
            if (debugJavascript === true) console.log('JSTREE BIND');
            selectedFolder = $('#jstree').jstree('get_selected', true)[0];
            selectedFolderId = selectedFolder.id.split('_')[1];
            if (debugJavascript === true) console.info('SELECTED NODE ' + selectedFolderId + " -- " + startedItemsListQuery);
            if (debugJavascript === true) console.log(selectedFolder);

            store.update(
                'teampassApplication',
                function(teampassApplication) {
                    teampassApplication.selectedFolder = selectedFolderId,
                        teampassApplication.selectedFolderTitle = selectedFolder.a_attr['data-title'],
                        teampassApplication.selectedFolderParentId = selectedFolder.parent.split('_')[1],
                        teampassApplication.selectedFolderParentTitle = selectedFolder.a_attr['data-title']
                }
            )

            // Prepare list of items
            if (startedItemsListQuery === false) {
                startedItemsListQuery = true;
                ListerItems(selectedFolderId, '', 0);
            }

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
        toastr.info('<?php echo langHdl('loading_item'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // Store current view
        savePreviousView();

        // Store the folder to open
        store.set(
            'teampassApplication', {
                selectedFolder: queryDict['group'],
            }
        );

        showItemOnPageLoad = true;
        itemIdToShow = queryDict['id'];
        startedItemsListQuery = true;

        $('.item-details-card').removeClass('hidden');
        $('#folders-tree-card, .columns-position').addClass('hidden');

        // refresh selection in jstree
        $('#jstree').jstree('deselect_all');
        $('#jstree').jstree('select_node', '#li_' + itemIdToShow);

        // Get list of items in this folder
        startedItemsListQuery = true;
        ListerItems(store.get('teampassApplication').selectedFolder, '', 0);

        // Show details
        $.when(
            Details(itemIdToShow, 'show', true)
        ).then(function() {
            //requestRunning = false;
            console.log('Item detail affiché')
            // Force previous view to Tree folders
            store.update(
                'teampassUser',
                function(teampassUser) {
                    teampassUser.previousView = '#folders-tree-card';
                }
            );
        });

    } else {
        /*// On page load, refresh list of items
        selectedFolder = $('#jstree').jstree('get_selected', true)[0];
        if (debugJavascript === true) console.log(selectedFolder);
        selectedFolderId = selectedFolder.id.split('_')[1];
        if (debugJavascript === true) console.info('SELECTED NODE ' + selectedFolderId);
        if (debugJavascript === true) console.log(selectedFolder);

        

        store.update(
            'teampassApplication',
            function (teampassApplication)
            {
                teampassApplication.selectedFolder = selectedFolderId;
            }
        )
        

        // Prepare list of items
        if (startedItemsListQuery === false) {
            startedItemsListQuery = true;
            ListerItems(selectedFolderId, '', 0);
        }*/
    }

    // Preload list of items
    if (store.get('teampassApplication') !== undefined &&
        store.get('teampassApplication').selectedFolder !== undefined &&
        store.get('teampassApplication').selectedFolder !== ''
    ) {
        startedItemsListQuery = true;

        ListerItems(store.get('teampassApplication').selectedFolder, '', 0);

    }



    // Close on escape key
    $(document).keyup(function(e) {
        if (e.keyCode == 27) {
            closeItemDetailsCard();
        }
    });

    /*// Edit on e key
    $(document).keyup(function(e) {
        if (e.keyCode == 69 && $('.item-details-card').is(':visible') === true) {
            if ($('#form-item').hasClass('hidden') === false) {
                showItemEditForm(store.get('teampassItem').id);
            }
        }
    });
    */

    // load list of visible folders for current user
    $(this).delay(500).queue(function() {
        refreshVisibleFolders();

        $(this).dequeue();
    });

    // Keep the scroll position
    $(window).on("scroll", function() {
        if ($('#folders-tree-card').hasClass('hidden') === false) {
            store.set(
                'teampassApplication', {
                    tempScrollTop: $(window).scrollTop(),
                }
            );
        }
    });


    // Ensure correct height of folders tree
    $('#jstree').height(screenHeight - 200);

    // Prepare iCheck format for checkboxes
    $('input[type="checkbox"].flat-blue, input[type="radio"].flat-blue').iCheck({
        checkboxClass: 'icheckbox_flat-blue',
        radioClass: 'iradio_flat-blue'
    });

    // Prepare some UI elements
    $('#limited-search').prop('checked', false);

    // Manage the password show button
    // including autohide after a couple of seconds
    $(document).on('click', '#card-item-pwd-show-button', function() {
        if ($(this).hasClass('pwd-shown') === false) {
            $(this).addClass('pwd-shown');
            // Prepare data to show
            // Is data crypted?
            var data = unCryptData($('#hidden-item-pwd').val(), '<?php echo $_SESSION['key']; ?>');
            if (data !== false && data !== undefined) {
                $('#hidden-item-pwd').val(
                    data.password
                );
            }

            // Change class and show spinner
            $('.pwd-show-spinner')
                .removeClass('far fa-eye')
                .addClass('fas fa-circle-notch fa-spin text-warning');


            $('#card-item-pwd')
                .html(
                    '<span style="cursor:none;">' +
                    $('#hidden-item-pwd').val()
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;') +
                    '</span>'
                );

            // log password is shown
            itemLog(
                'at_password_shown',
                store.get('teampassItem').id,
                $('#card-item-label').text()
            );

            // Autohide
            setTimeout(() => {
                $(this).removeClass('pwd-shown');
                $('#card-item-pwd').html('<?php echo $var['hidden_asterisk']; ?>');
                $('.pwd-show-spinner')
                    .removeClass('fas fa-circle-notch fa-spin text-warning')
                    .addClass('far fa-eye');
            }, <?php echo isset($SETTINGS['password_overview_delay']) === true ? $SETTINGS['password_overview_delay'] * 1000 : 4000; ?>);
        } else {
            $('#card-item-pwd').html('<?php echo $var['hidden_asterisk']; ?>');
        }
    });


    // Manage folders action
    $('.tp-action').click(function() {
        // SHow user
        toastr.remove();
        toastr.info('<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>');

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
            if (store.get('teampassItem').hasAccessLevel < 30 &&
                store.get('teampassUser').can_create_root_folder === 0
            ) {
                toastr.error(
                    '<?php echo langHdl('error_not_allowed_to'); ?>',
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
            $('.form-item, .item-details-card, .form-item-action, #folders-tree-card, .columns-position').addClass('hidden');
            $('.form-folder-add').removeClass('hidden');
            // Prepare some data in the form
            if (selectedFolder !== undefined && selectedFolder !== '') {
                $('#form-folder-add-parent').val(selectedFolder.parent.split('_')[1]).change();
            }
            $('#form-folder-add-label')
                .val('')
                .focus();
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
                    '<?php echo langHdl('error_not_allowed_to'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }

            // Store current view
            savePreviousView('.form-folder-add');

            // Show edit form
            $('.form-item, .item-details-card, .form-item-action, #folders-tree-card, .columns-position').addClass('hidden');
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
            $('#form-folder-add-complexicity').val(store.get('teampassItem').folderComplexity).change();
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
                    '<?php echo langHdl('error_not_allowed_to'); ?>',
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
            $('.form-item, .item-details-card, .form-item-action, #folders-tree-card, .columns-position').addClass('hidden');
            $('.form-folder-copy').removeClass('hidden');
            // Prepare some data in the form
            $('#form-folder-copy-source').val(store.get('teampassApplication').selectedFolder).change();
            //$("#form-folder-copy-destination option[value='"+selectedFolder.id.split('_')[1]+"']")
            //.prop('disabled', true);
            $('#form-folder-copy-destination').val(0).change();
            $('#form-folder-copy-label')
                .val(store.get('teampassApplication').selectedFolderTitle + ' <?php echo strtolower(langHdl('copy')); ?>')
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
                    '<?php echo langHdl('error_not_allowed_to'); ?>',
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
            $('.form-item, .item-details-card, .form-item-action, #folders-tree-card, .columns-position').addClass('hidden');
            $('.form-folder-delete').removeClass('hidden');

            // Prepare some data in the form
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
            $('.form-item, .item-details-card, .form-item-action, #folders-tree-card, .columns-position').addClass('hidden');
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
            ).then(function() {
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
                $('.item-details-card').addClass('hidden');
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

                // Set folder
                $('#form-item-folder').val(selectedFolderId).change();
                // Select tab#1
                $('#form-item-nav-pills li:first-child a').tab('show');
                // Preselect
                $('#pwd-definition-size').val(12);
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
            if (debugJavascript === true) console.info('SHOW EDIT ITEM');
            $.when(
                getPrivilegesOnItem(selectedFolderId, 1)
            ).then(function() {
                // Is user allowed
                if (store.get('teampassItem').item_rights < 20) {
                    toastr.remove();
                    toastr.error(
                        '<?php echo langHdl('error_not_allowed_to'); ?>',
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
                showItemEditForm(selectedFolderId);
            });

            //
            // > END <
            //
        } else if ($(this).data('item-action') === 'copy') {
            if (debugJavascript === true) console.info('SHOW COPY ITEM');
            toastr.remove();
            // Store current view
            savePreviousView('.form-item-copy');

            if (store.get('teampassItem').user_can_modify === 1) {
                // Show copy form
                $('.form-item, .item-details-card, .form-item-action').addClass('hidden');
                $('.form-item-copy, .item-details-card-menu').removeClass('hidden');
                // Prepare some data in the form
                $('#form-item-copy-new-label').val($('#form-item-label').val());
                $('#form-item-copy-destination').val($('#form-item-folder').val()).change();
            } else {
                toastr.remove();
                toastr.error(
                    '<?php echo langHdl('error_not_allowed_to'); ?>',
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
            // Is user allowed
            var levels = [50, 70];
            if (levels.includes(store.get('teampassItem').item_rights) === false) {
                toastr.remove();
                toastr.error(
                    '<?php echo langHdl('error_not_allowed_to'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }
            toastr.remove();

            // Store current view
            savePreviousView('.form-item-delete');

            if (debugJavascript === true) console.info('SHOW DELETE ITEM');
            if (store.get('teampassItem').user_can_modify === 1) {
                // Show delete form
                $('.form-item, .item-details-card, .form-item-action').addClass('hidden');
                $('.form-item-delete, .item-details-card-menu').removeClass('hidden');
            } else {
                toastr.remove();
                toastr.error(
                    '<?php echo langHdl('error_not_allowed_to'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
            }

            //
            // > END <
            //
        } else if ($(this).data('item-action') === 'share') {
            if (debugJavascript === true) console.info('SHOW SHARE ITEM');
            toastr.remove();

            // Store current view
            savePreviousView('.form-item-share');

            // Show share form
            $('.form-item, .item-details-card, .form-item-action').addClass('hidden');
            $('.form-item-share, .item-details-card-menu').removeClass('hidden');

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
            $('.form-item, .item-details-card, .form-item-action').addClass('hidden');
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
            prepareOneTimeView();

            $('#form-item-otv-link').val('');
            // Show notify form
            $('.form-item, .item-details-card, .form-item-action').addClass('hidden');
            $('.form-item-otv, .item-details-card-menu').removeClass('hidden');

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
                    '<?php echo langHdl('error_not_allowed_to'); ?>',
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
            $('.form-item, .item-details-card, .form-item-action').addClass('hidden');
            $('.form-item-server, .item-details-card-menu').removeClass('hidden');

            //
            // > END <
            //
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
            if ($(this).hasClass('item-edit') === true && userUploadedFile === true) {
                // Do some operation such as cancel file upload
                var data = {
                    'item_id': store.get('teampassItem').id,
                }

                $.post(
                    "sources/items.queries.php", {
                        type: 'delete_uploaded_files_but_not_saved',
                        data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                        key: '<?php echo $_SESSION['key']; ?>'
                    },
                    function(data) {
                        data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                        if (debugJavascript === true) console.log(data);
                    }
                );
            }

            // Clear pickfiles div
            $('#form-item-upload-pickfilesList').html('');

            // Hide all
            $('.form-item, .form-item-action, .form-folder-action, .item-details-card, #folders-tree-card, .columns-position, #item-details-card-categories, #form-item-upload-pickfilesList, #card-item-expired')
                .addClass('hidden');

            // Show expected one
            $(store.get('teampassUser').previousView).removeClass('hidden');

            closeItemDetailsCard();
        } else {
            $(store.get('teampassUser').previousView).removeClass('hidden');
            $(store.get('teampassUser').currentView).addClass('hidden');
        }
        $('.but-prev-item, .but-next-item').addClass('hidden').text('');
    });


    // Quit item details card back to items list
    $('.but-back-to-list').click(function() {
        closeItemDetailsCard();
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
        $('.form-item, .item-details-card, .form-item-action, #folders-tree-card, .columns-position').addClass('hidden');
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
                '<?php echo langHdl('error_provide_reason'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        }

        var data = {
            'id': store.get('teampassItem').id,
            'email': $('#form-item-request-access-reason').val(),
        }
        // NOw send the email
        $.post(
            "sources/items.queries.php", {
                type: 'send_request_access',
                data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
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
                        '<?php echo langHdl('done'); ?>',
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
            //if (debugJavascript === true) console.log('teampass-folders');
            //if (debugJavascript === true) console.log(store.get('teampass-folders'))
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
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');

                if (data.error !== false) {
                    // Show error
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '<?php echo langHdl('caution'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    // Change the icon for Notification
                    if ($('#form-item-notify-checkbox').is(':checked') === true) {
                        $('#card-item-misc-notification')
                            .html('<span class="far fa-bell infotip text-success" title="<?php echo langHdl('notification_engaged'); ?>"></span>');
                    } else {
                        $('#card-item-misc-notification')
                            .html('<span class="far fa-bell-slash infotip text-warning" title="<?php echo langHdl('notification_not_engaged'); ?>"></span>');
                    }

                    // Show/hide forms
                    $('.item-details-card').removeClass('hidden');
                    $('.form-item-notify').addClass('hidden');

                    $('.infotip').tooltip();

                    // Inform user
                    toastr.success(
                        '<?php echo langHdl('success'); ?>',
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
            .info('<?php echo langHdl('loading_item'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // Prepare data
        var data = {
            'id': store.get('teampassItem').id,
            'receipt': $('#form-item-share-email').val(),
            'cat': 'share_this_item',
        }

        // Launch action
        $.post(
            'sources/items.queries.php', {
                type: 'send_email',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');

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
                    $('.item-details-card').removeClass('hidden');
                    $('.form-item-share').addClass('hidden');

                    // Inform user
                    toastr.remove();
                    toastr.info(
                        '<?php echo langHdl('done'); ?>',
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
        // Show cog
        toastr
            .info('<?php echo langHdl('loading_item'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // Force user did a change to false
        userDidAChange = false;
        userUploadedFile = false;

        var data = {
            'item_id': store.get('teampassItem').id,
            'folder_id': selectedFolderId,
            'label': $('#form-item-copy-new-label').val(),
            'access_level': store.get('teampassItem').hasAccessLevel,
        }

        // Launch action
        $.post(
            'sources/items.queries.php', {
                type: 'delete_item',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

                if (data.error !== true) {
                    $('.form-item-action, .item-details-card-menu').addClass('hidden');
                    // Warn user
                    toastr.success(
                        '<?php echo langHdl('success'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                    // Refresh tree
                    refreshTree(selectedFolderId, true);
                    // Load list of items
                    ListerItems(selectedFolderId, '', 0);
                    // Close
                    closeItemDetailsCard();
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
     * NOTIFY - save status
     */
    $('#form-item-share-perform').click(function() {
        // Launch action
        $.post(
            'sources/items.queries.php', {
                type: 'notify_user_on_item_change',
                id: store.get('teampassItem').id,
                value: $('#form-item-anyoneCanModify').is(':checked') === true ? 1 : 0,
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                if (data[0].error === '') {
                    $('.form-item, .item-details-card, .form-item-action').removeClass('hidden');
                    $('.form-item-share, .item-details-card-menu').addClass('hidden');
                    // Warn user
                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('success'); ?>',
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
                '<?php echo langHdl('error_field_is_mandatory'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        }

        // Show cog
        toastr.remove();
        toastr.info('<?php echo langHdl('loading_item'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // Force user did a change to false
        userDidAChange = false;
        userUploadedFile = false;

        var data = {
            'item_id': store.get('teampassItem').id,
            'source_id': selectedFolderId,
            'dest_id': $('#form-item-copy-destination').val(),
            'new_label': $('#form-item-copy-new-label').val(),
        }

        // Launch action
        $.post(
            'sources/items.queries.php', {
                type: 'copy_item',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");

                if (data.error !== true) {
                    // Warn user
                    toastr.success(
                        '<?php echo langHdl('success'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                    // Refresh tree
                    refreshTree($('#form-item-copy-destination').val(), true);
                    // Load list of items
                    ListerItems($('#form-item-copy-destination').val(), '', 0);
                    // Close
                    $('.item-details-card').removeClass('hidden');
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
                    '<?php echo langHdl('error_field_is_mandatory'); ?>',
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
                '<i class="fas fa-circle-notch fa-spin fa-2x"></i>',
            );

            // Force user did a change to false
            userDidAChange = false;
            userUploadedFile = false;

            var data = {
                'item_id': store.get('teampassItem').id,
                'new_pwd': $('#form-item-server-password').val(),
                'ssh_root': $('#form-item-server-login').val(),
                'ssh_pwd': $('#form-item-server-old-password').val(),
                'user_id': <?php echo $_SESSION['user_id']; ?>,
            }

            $.post(
                "sources/utils.queries.php", {
                    type: "server_auto_update_password",
                    data: prepareExchangedData(data, "encode", "<?php echo $_SESSION['key']; ?>"),
                    key: "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
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
                            '<?php echo langHdl('success'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );

                        // Info
                        $("#form-item-server-status")
                            .html("<?php echo langHdl('done'); ?> " + data.text)
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
                    key: "<?php echo $_SESSION['key']; ?>"
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
                            '<?php echo langHdl('success'); ?>',
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
                '<?php echo langHdl('form_presents_inconsistencies'); ?>',
                '', {
                    timeOut: 10000,
                    progressBar: true
                }
            );

            return false;
        }

        // Show cog
        toastr
            .info('<?php echo langHdl('loading_item'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // Force user did a change to false
        userDidAChange = false;
        userUploadedFile = false;

        var data = {
            'label': $('#form-item-suggestion-label').val(),
            'login': $('#form-item-suggestion-login').val(),
            'password': $('#form-item-suggestion-password').val(),
            'email': $('#form-item-suggestion-email').val(),
            'url': $('#form-item-suggestion-url').val(),
            'description': $('#form-item-suggestion-description').summernote('code'),
            'comment': $('#form-item-suggestion-comment').val(),
            'folder_id': selectedFolderId,
            'item_id': store.get('teampassItem').id
        }

        // Launch action
        $.post(
            'sources/items.queries.php', {
                type: 'suggest_item_change',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data//decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

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
                        '<?php echo langHdl('success'); ?>',
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
        if (debugJavascript === true) console.log(form[0]);
        if (debugJavascript === true) console.log(form[0].checkValidity());
        if (form[0].checkValidity() === false) {
            form.addClass('was-validated');

            // Send alert to user
            toastr.remove();
            toastr.error(
                '<?php echo langHdl('form_presents_inconsistencies'); ?>',
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
                '<?php echo langHdl('error_only_numbers_in_folder_name'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );

            return false;
        }

        // Show cog
        toastr
            .info('<?php echo langHdl('loading_item'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // Force user did a change to false
        userDidAChange = false;
        userUploadedFile = false;

        var data = {
            'title': $('#form-folder-add-label').val(),
            'parentId': $('#form-folder-add-parent option:selected').val(),
            'complexity': $('#form-folder-add-complexicity option:selected').val(),
            'id': selectedFolderId,
        }
        if (debugJavascript === true) console.log(data);

        // Launch action
        $.post(
            'sources/folders.queries.php', {
                type: $('#form-folder-add').data('action') + '_folder',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data//decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

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
                    refreshVisibleFolders();
                    if ($('#form-folder-add').data('action') === 'add') {
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
                        '<?php echo langHdl('success'); ?>',
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
                '<?php echo langHdl('please_confirm'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        } else if ($('#form-folder-delete-selection option:selected').text() === '<?php echo $_SESSION['login']; ?>') {
            toastr.remove();
            toastr.error(
                '<?php echo langHdl('error_not_allowed_to'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        }

        // Show cog
        toastr
            .info('<?php echo langHdl('loading_item'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');


        var selectedFolders = [],
            data = {
                'selectedFolders': [$('#form-folder-delete-selection option:selected').val()]
            }
        if (debugJavascript === true) console.log(data)

        // Launch action
        $.post(
            'sources/folders.queries.php', {
                type: 'delete_folders',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

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
                    refreshVisibleFolders();
                    // Refresh tree
                    refreshTree(data.parent_id, true);
                    // Refresh list of items inside the folder
                    ListerItems(data.parent_id, '', 0);
                    // Back to list
                    closeItemDetailsCard();
                    // Warn user
                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('success'); ?>',
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
                '<?php echo langHdl('error_must_enter_all_fields'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        } else if ($("#form-folder-copy-source").val() === $("#form-folder-copy-destination").val()) {
            toastr.remove();
            toastr.error(
                '<?php echo langHdl('error_source_and_destination_are_equal'); ?>',
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
            .info('<?php echo langHdl('loading_item'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        var data = {
            'source_folder_id': $('#form-folder-copy-source option:selected').val(),
            'target_folder_id': $('#form-folder-copy-destination option:selected').val(),
            'folder_label': $('#form-folder-copy-label').val(),
        }

        // Launch action
        $.post(
            'sources/folders.queries.php', {
                type: 'copy_folder',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

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
                    refreshVisibleFolders();
                    // Refresh tree
                    refreshTree($('#form-folder-copy-destination option:selected').val(), true);
                    // Refresh list of items inside the folder
                    ListerItems($('#form-folder-copy-destination option:selected').val(), '', 0);
                    // Back to list
                    closeItemDetailsCard();
                    // Warn user
                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('success'); ?>',
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
                ' <?php echo strtolower(langHdl('copy')); ?>');
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
                    '<?php echo langHdl('changes_ongoing'); ?><br>' +
                    '<button type="button" class="btn clear" id="discard-changes"><?php echo langHdl('yes'); ?></button>' +
                    '<button type="button" class="btn clear ml-2" id="keep-changes"><?php echo langHdl('no'); ?></button>',
                    '<?php echo langHdl('caution'); ?>', {
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
                $('#card-item-attachments-badge').html('<?php echo langHdl('none'); ?>');

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

            if (debugJavascript === true) console.log('Edit for closed');
        }



        // Scroll back to position
        scrollBackToPosition();
    }


    /**
     * Click on button with class but-navigate-item
     */
    $(document)
        .on('click', '.but-navigate-item', function() {
            toastr.remove();
            toastr.info('<?php echo langHdl('loading_item'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Load item info
            Details(
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
            toastr.info('<?php echo langHdl('loading_item'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Load item info
            Details($(this).closest('tr'), 'show');
        })
        .on('click', '.list-item-clicktoedit', function() {
            toastr.remove();
            toastr.info('<?php echo langHdl('loading_item'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            if (debugJavascript === true) console.log('EDIT ME');
            // Set type of action
            $('#form-item-button-save').data('action', 'update_item');
            
            // Load item info
            Details($(this).closest('tr'), 'edit');
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
            $(this).addClass('text-info');
        })
        .on('mouseleave', '.fa-clickable', function() {
            $(this).removeClass('text-info');
        });

    $('#form-item-label').change(function() {
        $('#form-item-title').html($(this).val());
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
                    '<?php echo langHdl('success'); ?>',
                    '', {
                        timeOut: 1000
                    }
                );

                $.post('sources/items.queries.php', {
                        type: 'action_on_quick_icon',
                        item_id: $(this).data('item-id'),
                        action: $(this).data('item-favourited'),
                        key: '<?php echo $_SESSION['key']; ?>'
                    },
                    function(ret) {
                        //change quick icon
                        if (elem.data('item-favourited') === 0) {
                            $(elem)
                                .html('<span class="fa-stack fa-clickable item-favourite pointer infotip mr-2" title="<?php echo langHdl('unfavorite'); ?>" data-item-id="' + elem.item_id + '" data-item-favourited="1"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-star fa-stack-1x fa-inverse text-warning"></i></span>');
                        } else {
                            $(elem)
                                .html('<span class="fa-stack fa-clickable item-favourite pointer infotip mr-2" title="<?php echo langHdl('favorite'); ?>" data-item-id="' + elem.item_id + '" data-item-favourited="0"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-star fa-stack-1x fa-inverse"></i></span>');
                        }

                        toastr.remove();
                        toastr.info(
                            '<?php echo langHdl('success'); ?>',
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
    var showPwdContinuous = function() {
        if (mouseStillDown === true) {
            // Prepare data to show
            // Is data crypted?
            var data = unCryptData($('#hidden-item-pwd').val(), '<?php echo $_SESSION['key']; ?>');
            if (data !== false && data !== undefined) {
                $('#hidden-item-pwd').val(
                    data.password
                );
            }

            $('#card-item-pwd')
                .html(
                    '<span style="cursor:none;">' +
                    $('#hidden-item-pwd').val()
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;') +
                    '</span>'
                );

            setTimeout('showPwdContinuous("card-item-pwd")', 50);
            // log password is shown
            if ($('#card-item-pwd').hasClass('pwd-shown') === false) {
                itemLog(
                    'at_password_shown',
                    store.get('teampassItem').id,
                    $('#card-item-label').text()
                );
                $('#card-item-pwd').addClass('pwd-shown');
            }
        } else {
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
    var pwdOptions = {};
    pwdOptions = {
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
        },
        i18n : {
            t: function (key) {
                var phrases = {
                    veryWeak: '<?php echo langHdl('complex_level0'); ?>',
                    weak: '<?php echo langHdl('complex_level1'); ?>',
                    normal: '<?php echo langHdl('complex_level2'); ?>',
                    medium: '<?php echo langHdl('complex_level3'); ?>',
                    strong: '<?php echo langHdl('complex_level4'); ?>',
                    veryStrong: '<?php echo langHdl('complex_level5'); ?>'
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
    var uploader_attachments = new plupload.Uploader({
        runtimes: 'html5,flash,silverlight,html4',
        browse_button: 'form-item-attach-pickfiles',
        container: 'form-item-upload-zone',
        max_file_size: '<?php
                        if (strrpos($SETTINGS['upload_maxfilesize'], 'mb') === false) {
                            echo $SETTINGS['upload_maxfilesize'] . 'mb';
                        } else {
                            echo $SETTINGS['upload_maxfilesize'];
                        }
                        ?>',
        chunk_size: '1mb',
        dragdrop: true,
        url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/upload.attachments.php',
        flash_swf_url: '<?php echo $SETTINGS['cpassman_url']; ?>/includes/libraries/Plupload/Moxie.swf',
        silverlight_xap_url: '<?php echo $SETTINGS['cpassman_url']; ?>/includes/libraries/Plupload/Moxie.xap',
        filters: {
            mime_types: [
                <?php
                if (
                    isset($SETTINGS['upload_all_extensions_file']) === false
                    || (isset($SETTINGS['upload_all_extensions_file']) === true
                        && (int) $SETTINGS['upload_all_extensions_file'] === 0)
                ) {
                    ?> {
                        title: 'Image files',
                        extensions: '<?php echo $SETTINGS['upload_imagesext']; ?>'
                    },
                    {
                        title: 'Package files',
                        extensions: '<?php echo $SETTINGS['upload_pkgext']; ?>'
                    },
                    {
                        title: 'Documents files',
                        extensions: '<?php echo $SETTINGS['upload_docext']; ?>'
                    },
                    {
                        title: 'Other files',
                        extensions: '<?php echo $SETTINGS['upload_otherext']; ?>'
                    }
                <?php
                }
                ?>
            ],
            <?php
            if (isset($SETTINGS['upload_zero_byte_file']) === true && (int) $SETTINGS['upload_zero_byte_file'] === 1) {
                ?>
                prevent_empty: false
            <?php
            }
            ?>
        },
        <?php
        if ((int) $SETTINGS['upload_imageresize_options'] === 1) {
            ?>
            resize: {
                width: <?php echo $SETTINGS['upload_imageresize_width']; ?>,
                height: <?php echo $SETTINGS['upload_imageresize_height']; ?>,
                quality: <?php echo $SETTINGS['upload_imageresize_quality']; ?>
            },
        <?php
        }
        ?>
        init: {
            BeforeUpload: function(up, file) {
                toastr.info(
                    '<?php echo langHdl('please_wait'); ?>',
                    '', {
                        timeOut: 1000
                    }
                );

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
                    PHPSESSID: '<?php echo $_SESSION['user_id']; ?>',
                    itemId: store.get('teampassItem').id,
                    type_upload: 'item_attachments',
                    isNewItem: store.get('teampassItem').isNewItem,
                    isPersonal: store.get('teampassItem').folderIsPersonal,
                    edit_item: false,
                    user_token: store.get('teampassApplication').attachmentToken,
                    randomId: store.get('teampassApplication').uploadedFileId,
                    files_number: $('#form-item-hidden-pickFilesNumber').val()
                });
            }
        }
    });

    // Uploader options
    uploader_attachments.bind('UploadProgress', function(up, file) {
        console.log('uploader_attachments.bind')
        $('#upload-file_' + file.id).html('<i class="fas fa-file fa-sm mr-2"></i>' + htmlEncode(file.name) + ' - ' + file.percent + '%');
    });
    uploader_attachments.bind('Error', function(up, err) {
        toastr.remove();
        toastr.error(
            err.message + (err.file ? ', File: ' + err.file.name : ''),
            '', {
                timeOut: 5000,
                progressBar: true
            }
        );

        up.refresh(); // Reposition Flash/Silverlight
    });
    uploader_attachments.bind('FilesAdded', function(up, file) {
        userUploadedFile = true;
        $('#upload-file_' + file.id + '')
            .html('<i class="fas fa-file fa-sm mr-2"></i>' + htmlEncode(file.name) + ' <?php echo langHdl('uploaded'); ?>');
        toastr
            .info(
                '<?php echo langHdl('success'); ?>',
                '', {
                    timeOut: 1000
                }
            );
        $('#form-item-hidden-pickFilesNumber').val(0);
    });

    $("#form-item-upload-pickfiles").click(function(e) {
        if ($('#form-item-upload-pickfilesList').text() !== '') {
            // generate and save token
            $.post(
                "sources/main.queries.php", {
                    type: "save_token",
                    size: 25,
                    capital: true,
                    numeric: true,
                    ambiguous: true,
                    reason: "item_attachments",
                    duration: 10,
                    key: '<?php echo $_SESSION['key']; ?>'
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
                '<?php echo langHdl('no_file_to_upload'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
        }
    });
    uploader_attachments.init();
    uploader_attachments.bind('FilesAdded', function(up, files) {
        if (debugJavascript === true) console.log('uploader_attachments.FilesAdded')
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
                '<?php echo langHdl('error_no_action_identified'); ?>',
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
                '<?php echo langHdl('no_change_performed'); ?>',
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
                '<?php echo langHdl('form_presents_inconsistencies'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );

            return false;
        }

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
        if (debugJavascript === true) console.log('CHANGED FIELDS');
        if (debugJavascript === true) console.log(arrayQuery);

        // is user allowed to edit this item
        if (store.get('teampassApplication').itemsList !== undefined) {
            itemsList = JSON.parse(store.get('teampassApplication').itemsList);
        }
        if (itemsList.length > 0) {
            userItemRight = itemsList[store.get('teampassItem').id].rights;
        }

        // Do checks
        if (arrayQuery.length > 0 || userDidAChange === true) {
            var reg = new RegExp("[.|,|;|:|!|=|+|-|*|/|#|\"|'|&]");

            // Do some easy checks
            if ($('#form-item-label').val() === '') {
                // Label is empty
                toastr.remove();
                toastr.error(
                    '<?php echo langHdl('error_label'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            } else if ($('#form-item-tags').val() !== '' &&
                reg.test($('#form-item-tags').val())
            ) {
                // Tags not wel formated
                toastr.remove();
                toastr.error(
                    '<?php echo langHdl('error_tags'); ?>',
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
                    '<?php echo langHdl('error_no_selected_folder'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            } else if ($('#form-item-folder option:selected').attr('disabled') === 'disabled' && userItemRight <= 40) {
                // Folder is not allowed
                toastr.remove();
                toastr.error(
                    '<?php echo langHdl('error_folder_not_allowed'); ?>',
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
                            if ($(this).val() === '<?php echo $_SESSION['user_id']; ?>') {
                                userInRestrictionList = true;
                            }
                        }
                    }
                });
                // IF any restriction, then ensure the author is in
                if (userInRestrictionList === false && restriction.length > 0) {
                    restriction.push('<?php echo $_SESSION['user_id']; ?>;');
                }

                // Manage diffusion list
                var diffusion = new Array();
                $('#form-item-anounce option:selected').each(function() {
                    diffusion.push($(this).val());
                });

                // Get item field values
                // Ensure that mandatory ones are filled in too
                var fields = [];
                var errorExit = false;
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
                });
                if (errorExit === true) {
                    toastr.remove();
                    toastr.error(
                        '<?php echo langHdl('error_field_is_mandatory'); ?>',
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                    return false;
                }
                //prepare data
                var data = {
                    'anyone_can_modify': $('#form-item-anyoneCanModify').is(':checked') ? 1 : 0,
                    'complexity_level': parseInt($('#form-item-password-complex').val()),
                    'description': $('#form-item-description').summernote('code') !== "<p><br></p>" ? $('#form-item-description').summernote('code') : '',
                    'diffusion_list': diffusion,
                    'folder': parseInt($('#form-item-folder').val()),
                    'email': $('#form-item-email').val(),
                    'fields': fields,
                    'folder_is_personal': store.get('teampassItem').IsPersonalFolder === 1 ? 1 : 0,
                    'id': store.get('teampassItem').id,
                    'label': $('#form-item-label').val(),
                    'login': $('#form-item-login').val(),
                    'pw': $('#form-item-password').val(),
                    'restricted_to': restriction,
                    'restricted_to_roles': restrictionRole,
                    'tags': $('#form-item-tags').val(),
                    'template_id': parseInt($('input.form-check-input-template:checkbox:checked').data('category-id')),
                    'to_be_deleted_after_date': ($('#form-item-deleteAfterDate').length !== 0 &&
                        $('#form-item-deleteAfterDate').val() !== '') ? $('#form-item-deleteAfterDate').val() : '',
                    'to_be_deleted_after_x_views': ($('#form-item-deleteAfterShown').length !== 0 &&
                            $('#form-item-deleteAfterShown').val() !== '' && $('#form-item-deleteAfterShown').val() >= 1) ?
                        parseInt($('#form-item-deleteAfterShown').val()) : '',
                    'url': $('#form-item-url').val(),
                    'user_id': parseInt('<?php echo $_SESSION['user_id']; ?>'),
                    'uploaded_file_id': store.get('teampassApplication').uploadedFileId === undefined ? '' : store.get('teampassApplication').uploadedFileId,
                };
                if (debugJavascript === true) console.log('SAVING DATA');
                if (debugJavascript === true) console.log(data);
                // Inform user
                toastr.remove();
                toastr.info(
                    '<?php echo langHdl('opening_folder'); ?><i class="fas fa-circle-notch fa-spin ml-2"></i>'
                );

                // CLear tempo var
                store.update(
                    'teampassApplication',
                    function(teampassApplication) {
                        teampassApplication.uploadedFileId = '';
                    }
                );

                //Send query
                $.post(
                    "sources/items.queries.php", {
                        type: $('#form-item-button-save').data('action'),
                        data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                        key: "<?php echo $_SESSION['key']; ?>"
                    },
                    function(data) {
                        //decrypt data
                        try {
                            data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
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
                        if (debugJavascript === true) console.log('RETURNED DATA');
                        if (debugJavascript === true) console.log(data);
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
                                        data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                                        key: '<?php echo $_SESSION['key']; ?>'
                                    }
                                );
                            } else {
                                refreshTree($('#form-item-folder').val(), true);
                            }

                            // Refresh list of items inside the folder
                            ListerItems($('#form-item-folder').val(), '', 0);

                            // Inform user
                            toastr.info(
                                '<?php echo langHdl('success'); ?>',
                                '', {
                                    timeOut: 1000
                                }
                            );

                            // Close
                            userDidAChange = false;
                            userUploadedFile = false;

                            closeItemDetailsCard();
                            /*
                            // Hide all
                            $('.form-item, .form-item-action, .form-folder-action, .item-details-card, #folders-tree-card, #card-item-expired').addClass('hidden');

                            // Show expected one
                            $(store.get('teampassUser').previousView).removeClass('hidden');
                            */
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
                    data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                    key: '<?php echo $_SESSION['key']; ?>'
                }
            );


            // Inform user
            toastr.info(
                '<?php echo langHdl('done'); ?>',
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
                '<?php echo langHdl('nothing_to_save'); ?>',
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
        if ($(this).is(":checked") === true) {
            $('#find_items').css({
                "background-color": "#f56954"
            });
        } else {
            $('#find_items').css({
                "background-color": "#FFF"
            })
        }
    });


    function showItemEditForm(selectedFolderId) {
        if (debugJavascript === true) console.info('SHOW EDIT ITEM ' + selectedFolderId);

        //$.when(
        //    getPrivilegesOnItem(selectedFolderId, 0)
        //).then(function() {
        // Now read
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
            $('#card-item-visibility').html(store.get('teampassItem').itemVisibility);
            $('#card-item-minimum-complexity').html(store.get('teampassItem').itemMinimumComplexity);

            // Show edition form
            $('.form-item, #form-item-attachments-zone')
                .removeClass('hidden');
            $('.item-details-card, .form-item-copy, #form-item-password-options, .form-item-action, #item-details-card-categories')
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
            var itemsList = JSON.parse(store.get('teampassApplication').itemsList);
            userItemRight = itemsList[store.get('teampassItem').id].rights;
            if (userItemRight > 40 && $('#form-item-folder option:selected').attr('disabled') === 'disabled') {
                $('#form-item-folder option:selected').removeAttr('disabled');
            }

            toastr.remove();
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
                '<?php echo langHdl('searching'); ?>'
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
                0
            );
        }
    }

    /**
     * 
     */
    function finishingItemsFind(type, limited, criteria, start) {
        // send query
        $.get(
            'sources/find.queries.php', {
                type: type,
                limited: limited,
                search: criteria,
                start: start,
                length: 10,
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                var pwd_error = '',
                    icon_login,
                    incon_link,
                    icon_pwd,
                    icon_favorite;

                data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                if (debugJavascript === true) console.log(data);

                // Ensure correct div is not hidden
                $('#info_teampass_items_list').addClass('hidden');
                $('#table_teampass_items_list').removeClass('hidden');

                // Show Items list
                sList(data.html_json);

                if (data.start !== -1 && (data.start <= data.total)) {
                    // Continu the list of results
                    finishingItemsFind(
                        'search_for_items',
                        $('#limited-search').is(":checked") === true ?
                        store.get('teampassApplication').selectedFolder : false,
                        criteria,
                        data.start
                    )
                } else {
                    toastr.remove();
                    toastr.info(
                        data.message,
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );

                    // Do some post treatments
                    $('#form-folder-path').html('');
                    $('#find_items').val('');

                    adjustElemsSize();
                }
            }
        );
    }


    /**
     * Undocumented function
     *
     * @return void
     */
    function refreshVisibleFolders() {
        $.post(
            'sources/items.queries.php', {
                type: 'refresh_visible_folders',
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
                if (debugJavascript === true) {
                    console.log('TREE');
                    console.log(data);
                }
                //check if format error
                if (data.error !== true) {
                    // Build html lists
                    var html_visible = '',
                        html_full_visible = '',
                        html_active_visible = '',
                        indentation = '',
                        disabled = '';

                    if (data.html_json.folders === undefined) {
                        $('#jstree').html('<div class="alert alert-warning mt-3 mr-1 ml-1"><i class="fas fa-exclamation-triangle mr-2"></i>' +
                            '<?php echo langHdl('no_data_to_display'); ?>' +
                            '</div>');
                        //return false;
                    } else {
                        refreshFoldersInfo(data.html_json.folders, 'clear');
                    }

                    // Shall we show the root folder
                    if (data.html_json.can_create_root_folder === 1) {
                        html_visible = '<option value="0"><?php echo langHdl('root'); ?></option>';
                        html_full_visible = '<option value="0"><?php echo langHdl('root'); ?></option>';
                        html_active_visible = '<option value="0"><?php echo langHdl('root'); ?></option>';
                    }

                    //
                    $.each(data.html_json.folders, function(i, value) {
                        // Prepare options lists
                        html_visible += '<option value="' + value.id + '"' +
                            ((value.disabled === 1) ? ' disabled="disabled"' : '') +
                            ' data-parent-id="' + value.parent_id + '">' +
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
                    if (debugJavascript === true) console.log(html_visible);
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

        if (action === 'clear') {
            sending = JSON.stringify(folders.map(a => a.id));
            if (debugJavascript === true) console.log(sending)
        } else if (action === 'update') {
            sending = JSON.stringify([folders]);
            if (debugJavascript === true) console.log(sending)
        }
        // 
        $.post(
            'sources/items.queries.php', {
                type: 'refresh_folders_other_info',
                data: sending,
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

                //check if format error
                if (data.error !== true) {
                    // Store in session
                    if (action === 'clear') {
                        // Handle the data
                        $.each(folders, function(index, item) {
                            if (data.result[item.id] !== null) {
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
                            if (item.id === parseInt(folders)) {
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

        if (do_refresh === true) {
            $('#jstree').jstree(true).refresh();
        }

        if (node_to_select !== '') {
            $('#jstree').jstree('deselect_all');

            $('#jstree')
                .one('refresh.jstree', function(e, data) {
                    data.instance.select_node('#li_' + node_to_select);
                });
        }

        if (refresh_visible_folders === 1) {
            $(this).delay(500).queue(function() {
                refreshVisibleFolders();
                $(this).dequeue();
            });
        }
    }

    /**
     * 
     */
    function ListerItems(groupe_id, restricted, start, stop_listing_current_folder) {
        var me = $(this);
        stop_listing_current_folder = stop_listing_current_folder || '0';
        if (debugJavascript === true) console.log('LIST OF ITEMS FOR FOLDER ' + groupe_id)

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

        //Evaluate number of items to display - depends on screen height
        //adapt to the screen height
        store.update(
            'teampassApplication',
            function(teampassApplication) {
                teampassApplication.itemsShownByQuery = Math.max(Math.round((screenHeight - 450) / 23), 2);
            }
        );

        if (stop_listing_current_folder === 1) {
            me.data('requestRunning', false);
            // Store listing criteria
            store.update(
                'teampassApplication',
                function(teampassApplication) {
                    teampassApplication.itemsListFolderId = groupe_id,
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


        // prevent launch of similar query in case of doubleclick
        if (requestRunning === true) {
            return false;
        }
        requestRunning = true;

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
                    teampassApplication.selectedFolder = groupe_id,
                    teampassApplication.itemsList = ''
                }
            );

            if ($('.tr_fields') !== undefined) {
                $('.tr_fields, .newItemCat, .editItemCat').addClass('hidden');
            }

            // Inform user
            toastr.remove();
            toastr.info(
                '<?php echo langHdl('opening_folder'); ?><i class="fas fa-circle-notch fa-spin ml-2"></i>'
            );

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
                restricted: restricted,
                start: start !== undefined ? start : 0,
                uniqueLoadData: store.get('teampassApplication').queryUniqueLoad,
                nb_items_to_display_once: store.get('teampassApplication').itemsShownByQuery,
            };
            
            //ajax query
            var request = $.post('sources/items.queries.php', {
                    type: 'do_items_list_in_folder',
                    data: prepareExchangedData(JSON.stringify(dataArray), 'encode', '<?php echo $_SESSION['key']; ?>'),
                    key: '<?php echo $_SESSION['key']; ?>',
                },
                function(retData) {

                    if (retData == 'Hacking attempt...') {
                        toastr.remove();
                        toastr.error(
                            'Hacking attempt...'
                        );
                        return false;
                    }
                    //get data
                    data = decodeQueryReturn(retData, '<?php echo $_SESSION['key']; ?>');

                    if (debugJavascript === true) console.log('LIST ITEMS');
                    if (debugJavascript === true) console.log(data);

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

                        /*
                        // PSK is requested but not set
                        if (data.folder_requests_psk === 1
                            && (store.get('teampassUser').pskSetForSession === ''
                            || store.get('teampassUser').pskSetForSession === undefined)
                        ) {
                            showPersonalSKDialog();
                        }
                        */

                        // show correct fodler in Tree
                        if ($('#jstree').jstree('get_selected', true)[0] !== undefined &&
                            'li_' + groupe_id !== $('#jstree').jstree('get_selected', true)[0].id
                        ) {
                            $('#jstree').jstree('deselect_all');
                            $('#jstree').jstree('select_node', '#li_' + groupe_id);
                        }

                        // Delete existing clipboard
                        if (clipboardForPassword) {
                            clipboardForPassword.destroy();
                        }
                        if (clipboardForLogin) {
                            clipboardForLogin.destroy();
                        }

                        // Prepare clipboard items
                        clipboardForLogin = new ClipboardJS('.fa-clickable-login');
                        clipboardForLogin.on('success', function(e) {
                            toastr.info(
                                '<?php echo langHdl('copy_to_clipboard'); ?>',
                                '', {
                                    timeOut: 2000,
                                    positionClass: 'toast-top-right',
                                    progressBar: true
                                }
                            );
                            e.clearSelection();
                        });

                        // Prepare clipboard for PAssword
                        // This will request a query to server to get the pwd
                        clipboardForPassword = new ClipboardJS('.fa-clickable-password', {
                            text: function(trigger) {
                                // Send query and get password
                                var result = '',
                                    error = false;

                                // Warn user that it starts
                                toastr.remove();
                                toastr.info(
                                    '<i class="fas fa-circle-notch fa-spin fa-2x"></i>'
                                );

                                $.ajax({
                                    type: "POST",
                                    async: false,
                                    url: 'sources/items.queries.php',
                                    data: 'type=show_item_password&item_id=' + trigger.getAttribute('data-item-id') +
                                        '&key=<?php echo $_SESSION['key']; ?>',
                                    dataType: "",
                                    success: function(data) {
                                        //decrypt data
                                        try {
                                            data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                                        } catch (e) {
                                            // error
                                            toastr.remove();
                                            toastr.warning(
                                                '<?php echo langHdl('no_item_to_display'); ?>'
                                            );
                                            return false;
                                        }
                                        if (data.error === true) {
                                            error = true;
                                        } else {
                                            if (data.password_error !== '') {
                                                error = true;
                                            } else {
                                                result = atob(data.password);
                                            }
                                        }

                                        // Remove cog
                                        toastr.remove();
                                    }
                                });
                                return result;
                            }
                        });
                        clipboardForPassword.on('success', function(e) {
                            itemLog(
                                'at_password_copied',
                                e.trigger.dataset.itemId,
                                e.trigger.dataset.itemLabel
                            );
                            
                            // Warn user about clipboard clear
                            if (store.get('teampassSettings').clipboard_life_duration === undefined || parseInt(store.get('teampassSettings').clipboard_life_duration) === 0) {
                                toastr.remove();
                                toastr.info(
                                    '<?php echo langHdl('copy_to_clipboard'); ?>',
                                    '', {
                                        timeOut: 2000,
                                        positionClass: 'toast-top-right',
                                        progressBar: true
                                    }
                                );
                            } else {
                                toastr.remove();
                                toastr.warning(
                                    '<?php echo langHdl('clipboard_will_be_cleared'); ?>',
                                    '', {
                                        timeOut: store.get('teampassSettings').clipboard_life_duration * 1000,
                                        progressBar: true
                                    }
                                );

                                // Set clipboard eraser
                                clearClipboardTimeout(
                                    store.get('teampassSettings').clipboard_life_duration
                                );
                            }

                            e.clearSelection();
                        });
                    } else if (data.error === 'not_authorized') {
                        $('#items_folder_path').html('<i class="fas fa-folder-open-o"></i>&nbsp;' + rebuildPath(data.arborescence));
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
                                '<i class="fas fa-info-circle mr-2"></i><?php echo langHdl('no_item_to_display'); ?></b>' +
                                '</div>')
                            .removeClass('hidden');
                    }

                    if (data.error === 'is_pf_but_no_saltkey') {
                        //warn user about his saltkey
                        toastr.remove();
                        toastr.warning(
                            '<?php echo langHdl('home_personal_saltkey_label'); ?>',
                            '', {
                                timeOut: 10000
                            }
                        );
                        return false;
                    } else if (data.error === 'not_authorized' || data.access_level === '') {
                        // Show warning to user
                        $('#info_teampass_items_list')
                            .html('<div class="alert alert-info text-center col col-lg-10" role="alert">' +
                                '<i class="fas fa-warning mr-2"></i><?php echo langHdl('not_allowed_to_see_pw'); ?></b>' +
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

                            console.log('Liste complete des items')
                            console.log(JSON.parse(store.get('teampassApplication').itemsList));
                        }

                        proceed_list_update(stop_listing_current_folder);
                    }
                }
            );
        }
    }

    function sList(listOfItems) {
        if (debugJavascript === true) console.log(listOfItems);
        var counter = 0,
            prevIdForNextItem = -1;

        // Manage store
        if (store.get('teampassApplication').itemsList === '' || store.get('teampassApplication').itemsList === undefined) {
            var stored_datas = listOfItems;
        } else {
            var stored_datas = JSON.parse(store.get('teampassApplication').itemsList).concat(listOfItems);
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
                icon_login = '',
                icon_link = '',
                icon_pwd = '',
                icon_favorite = '',
                item_flag = '',
                item_grippy = '',
                visible_by_user = '';

            counter += 1;

            // Check access restriction
            if (value.rights > 0) {
                // Should I populate previous item with this new id
                console.log('current id: '+value.item_id);
                console.log(prevIdForNextItem)
                if (prevIdForNextItem !== -1) {
                    $('#list-item-row_' + value.item_id).attr('data-next-item-id', prevIdForNextItem.item_id);
                    $('#list-item-row_' + value.item_id).attr('data-next-item-label', value.label);
                }
                
                // Prepare anyone can modify icon
                if (value.anyone_can_modify === 1 || value.open_edit === 1) {
                    icon_all_can_modify = '<span class="fa-stack fa-clickable pointer infotip list-item-clicktoedit mr-2" title="<?php echo langHdl('edit'); ?>"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-pen fa-stack-1x fa-inverse"></i></span>';
                }

                // Prepare mini icons
                if (store.get('teampassSettings') !== undefined && parseInt(store.get('teampassSettings').copy_to_clipboard_small_icons) === 1 &&
                    value.rights > 10
                ) {
                    // Login icon
                    if (value.login !== '') {
                        icon_login = '<span class="fa-stack fa-clickable fa-clickable-login pointer infotip mr-2" title="<?php echo langHdl('item_menu_copy_login'); ?>" data-clipboard-text="' + sanitizeString(value.login) + '"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-user fa-stack-1x fa-inverse"></i></span>';
                    }
                    // Pwd icon
                    if (value.pw_status !== 'pw_is_empty' && value.pw_status !== 'encryption_error') {
                        icon_pwd = '<span class="fa-stack fa-clickable fa-clickable-password pointer infotip mr-2" title="<?php echo langHdl('item_menu_copy_pw'); ?>" data-item-id="' + value.item_id + '" data-item-label="' + value.label + '"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-key fa-stack-1x fa-inverse"></i></span>';
                    }

                    // Now check if pwd is empty. If it is then warn user
                    if (value.pw_status === 'pw_is_empty') {
                        pwd_error = '<span class="fa-stack fa-clickable fa-clickable-password pointer infotip mr-2" title="<?php echo langHdl('password_is_empty'); ?>"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-exclamation-triangle text-warning fa-stack-1x fa-inverse"></i></span>';
                    }
                }

                // Link icon
                if (value.link !== '') {
                    icon_link = '<span class="fa-stack fa-clickable pointer infotip mr-2" title="<?php echo langHdl('open_website'); ?>"><a href="' + sanitizeString(value.link) + '" target="_blank" class="no-link"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-link fa-stack-1x fa-inverse"></i></a></span>';
                }

                // Prepare Favorite icon
                if (store.get('teampassSettings') !== undefined && parseInt(store.get('teampassSettings').enable_favourites) === 1 &&
                    value.rights > 10
                ) {
                    if (value.is_favourited === 1) {
                        icon_favorite = '<span class="fa-stack fa-clickable item-favourite pointer infotip mr-2" title="<?php echo langHdl('unfavorite'); ?>" data-item-id="' + value.item_id + '" data-item-favourited="1"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-star fa-stack-1x fa-inverse text-warning"></i></span>';
                    } else {
                        icon_favorite = '<span class="fa-stack fa-clickable item-favourite pointer infotip mr-2" title="<?php echo langHdl('favorite'); ?>" data-item-id="' + value.item_id + '" data-item-favourited="0"><i class="fas fa-circle fa-stack-2x"></i><i class="far fa-star fa-stack-1x fa-inverse"></i></span>';
                    }
                }

                // Prepare Description
                if (value.desc !== '') {
                    value.desc = ' <span class="text-secondary small">- ' + value.desc + '</span>';
                }

                $('#teampass_items_list').append(
                    '<tr class="list-item-row' + (value.canMove === 1 ? ' is-draggable' : '') + '" id="list-item-row_' + value.item_id + '" data-item-edition="' + value.open_edit + '" data-item-id="' + value.item_id + '" data-item-sk="' + value.sk + '" data-item-expired="' + value.expired + '" data-item-rights="' + value.rights + '" data-item-display="' + value.display + '" data-item-open-edit="' + value.open_edit + '" data-item-tree-id="' + value.tree_id + '" data-is-search-result="' + value.is_result_of_search + '" data-label="' + escape(value.label) + '">' +
                    '<td class="list-item-description" style="width: 100%;">' +
                    // Show user a grippy bar to move item
                    (value.canMove === 1 && value.is_result_of_search === 0 ? '<i class="fas fa-ellipsis-v mr-2 dragndrop"></i>' : '') +
                    // Show user a ban icon if expired
                    (value.expired === 1 ? '<i class="far fa-calendar-times mr-2 text-warning infotip" title="<?php echo langHdl('not_allowed_to_see_pw_is_expired'); ?>"></i>' : '') +
                    // Show user that Item is not accessible
                    (value.rights === 10 ? '<i class="far fa-eye-slash fa-xs mr-2 text-primary infotip" title="<?php echo langHdl('item_with_restricted_access'); ?>"></i>' : '') +
                    // Show user that password is badly encrypted
                    (value.pw_status === 'encryption_error' ? '<i class="fas fa-exclamation-triangle  fa-xs text-danger infotip mr-1" title="<?php echo langHdl('pw_encryption_error'); ?>"></i>' : '') +
                    '<span class="list-item-clicktoshow' + (value.rights === 10 ? '' : ' pointer') + '" data-item-id="' + value.item_id + '">' +
                    '<span class="list-item-row-description' + (value.rights === 10 ? ' font-weight-light' : '') + '">' + value.label + '</span>' + (value.rights === 10 ? '' : value.desc) + '</span>' +
                    '<span class="list-item-actions hidden">' +
                    (value.rights === 10 ?
                        '<span class="fa-stack fa-clickable fa-clickable-access-request pointer infotip mr-2" title="<?php echo langHdl('need_access'); ?>"><i class="fas fa-circle fa-stack-2x text-danger"></i><i class="far fa-handshake fa-stack-1x fa-inverse"></i></span>' :
                        pwd_error + icon_all_can_modify + icon_login + icon_pwd + icon_link + icon_favorite) +
                    '</span>' +
                    (value.folder !== undefined ?
                        '<br><span class="text-secondary small font-italic pointer open-folder" data-tree-id="' +
                        value.tree_id + '"">[' + value.folder + ']</span>' : '') +
                    '</td>' +
                    '</tr>'
                );

                // Save id for usage
                prevIdForNextItem = {
                    'item_id' : value.item_id,
                    'label': value.label,
                };

                //---------------------
            }
        });

        // Sort entries
        var $tbody = $('#teampass_items_list');
        $tbody.find('tr').sort(function(a, b) {
            var tda = $(a).find('.list-item-row-description').text();
            var tdb = $(b).find('.list-item-row-description').text();
            // if a < b return 1
            return tda > tdb ? 1 :
                tda < tdb ? -1 :
                0;
        }).appendTo($tbody);

        // Trick for list with only one entry
        if (counter === 1) {
            $('#teampass_items_list')
                .append('<tr class="row"><td class="">&nbsp;</td></tr>');
        }
        adjustElemsSize();

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


    function adjustElemsSize() {
        // Adjust height of folders tree
        if ($('#teampass_items_list').height() > (screenHeight - 215)) {
            $('#jstree').height($('#teampass_items_list').height() + 75);
        } else {
            $('#jstree').height($(window).height() - 215);
        }
    }

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
                new_path_elem = ' class="pointer" onclick="ListerItems(' + value['id'] + ', \'\', 0)"';
            }

            new_path += '<li class="breadcrumb-item" id="path_elem_' + value['id'] + '"' + new_path_elem + '>' + value['title'] + '</li>';
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
                '<?php echo langHdl('data_refreshed'); ?>',
                '', {
                    timeOut: 1000
                }
            );


            // Prepare items dragable on folders
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
                    return $('<div class="bg-gray p-2 font-weight-light">' + $(this).find('.list-item-row-description').text() + '</div>');
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
                            '<?php echo langHdl('error_not_allowed_to'); ?>',
                            '', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                        return false;
                    }

                    // Warn user that it starts
                    toastr.info(
                        '<i class="fas fa-circle-notch fa-spin fa-2x"></i>'
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
                            data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                            key: '<?php echo $_SESSION['key']; ?>'
                        },
                        function(data) {
                            //decrypt data
                            data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

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
                            $('#itcount_' + data.from_folder).text(Math.floor($('#itcount_' + data.from_folder).text()) - 1);
                            $('#itcount_' + data.to_folder).text(Math.floor($('#itcount_' + data.to_folder).text()) + 1);

                            toastr.remove();
                            toastr.info(
                                '<?php echo langHdl('success'); ?>',
                                '', {
                                    timeOut: 1000
                                }
                            );
                        }
                    );
                }
            });

            // refine users list to the related roles
            /*$.post(
                'sources/items.queries.php',
                {
                    type        : 'get_refined_list_of_users',
                    iFolderId   : $('#hid_cat').val(),
                    key         : '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
                    if (debugJavascript === true) console.log(data);
                    // *** restricted_to_list ***
                    $('#form-item-restrictedto')
                        .find('option').remove().end()
                        .val('');
                    // add list of users
                    if ($('#form-item-restrictedToUsers').val() !== undefined) {
                        $('#form-item-restrictedto').append(data.selOptionsUsers);
                        if (data.setting_restricted_to_roles === 1) {
                            //add optgroup
                            var optgroup = $('<optgroup>');
                            optgroup.attr('label', '<?php echo langHdl('users'); ?>');
                            $('.folder_rights_user').wrapAll(optgroup);
                        }
                    }
                    //Add list of roles if option is set
                    if (data.setting_restricted_to_roles === 1 && $('#form-item-restrictedto').val() !== undefined) {
                        //add optgroup
                        var optgroup = $('<optgroup>');
                        optgroup.attr('label', '<?php echo langHdl('roles'); ?>');
                        $('#form-item-restrictedto').append(data.selOptionsRoles);
                        $('.folder_rights_role').wrapAll(optgroup);
                    }
                }
            );*/
        }
    }




    /**
     *
     */
    function Details(itemDefinition, actionType, hotlink = false) {
        if (debugJavascript === true) console.info('EXPECTED ACTION on ' + itemDefinition + ' is ' + actionType + ' -- ')

        // Store current view
        savePreviousView();
        
        if (debugJavascript === true) console.log("Request is running: " + requestRunning)

        // Store status query running
        requestRunning = true;

        // Init
        if (hotlink === false) {
            var itemId = parseInt($(itemDefinition).data('item-id')) || '';
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
            var itemTreeId = store.get('teampassApplication').selectedFolder || '';
            var itemSk = 0;
            var itemExpired = '';
            var itemRestricted = '';
            var itemDisplay = 1;
            var itemOpenEdit = 0;
            var itemReload = 0;
            var itemRights = parseInt($(itemDefinition).data('item-rights')) || 10;
        }

        userDidAChange = false;

        // Select tab#1
        $('#form-item-nav-pills li:first-child a').tab('show');

        // Don't show details
        if (itemDisplay === 'no_display') {
            // Inform user
            toastr.remove();
            toastr.warning(
                '<?php echo langHdl('no_item_to_display'); ?>',
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

        if (debugJavascript === true) console.log("SEND");
        if (debugJavascript === true) console.log(data);

        //Send query
        $.post(
            'sources/items.queries.php', {
                type: 'show_details_item',
                data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
                console.log(data);
                requestRunning = true;
                if (debugJavascript === true) console.log("RECEIVED");
                if (debugJavascript === true) console.log(data);

                // remove any track-change class on item form
                //$('.form-item-control').removeClass('track-change');

                if (data.error === true) {
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                    requestRunning = false;
                    return false;
                } else if ((data.user_can_modify === 0 && actionType === 'edit') ||
                    data.show_details === 0
                ) {
                    toastr.remove();
                    toastr.error(
                        '<?php echo langHdl('error_not_allowed_to'); ?>',
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
                if (data.show_detail_option === 1 || itemExpired === 1) {
                    // SHow expiration alert
                    $('#card-item-expired').removeClass('hidden');
                } else if (data.show_detail_option === 2) {
                    // Don't show anything
                    toastr.remove();
                    toastr.error(
                        '<?php echo langHdl('not_allowed_to_see_pw'); ?>',
                        '<?php echo langHdl('warning'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );

                    return false;
                }

                // Show header info
                $('#card-item-visibility').html(store.get('teampassItem').itemVisibility);
                $('#card-item-minimum-complexity').html(store.get('teampassItem').itemMinimumComplexity);

                // Hide NEW button in case access_level < 30
                if (store.get('teampassItem').hasAccessLevel === 10) {
                    $('#item-form-new-button').addClass('hidden');
                } else {
                    $('#item-form-new-button').removeClass('hidden');
                }

                // Uncrypt the pwd
                if (data.pw !== undefined) {
                    data.pw = atob(data.pw);
                }

                // Update hidden variables
                store.update(
                    'teampassItem',
                    function(teampassItem) {
                        teampassItem.id = parseInt(data.id),
                            teampassItem.timestamp = data.timestamp,
                            teampassItem.user_can_modify = data.user_can_modify,
                            teampassItem.anyone_can_modify = data.anyone_can_modify,
                            teampassItem.edit_item_salt_key = data.edit_item_salt_key,
                            teampassItem.id_restricted_to = data.id_restricted_to,
                            teampassItem.id_restricted_to_roles = data.id_restricted_to_roles,
                            teampassItem.item_rights = itemRights
                    }
                );

                // Prepare forms
                $('#folders-tree-card, .columns-position').addClass('hidden');
                if (actionType === 'show') {
                    // Prepare Views
                    $('.item-details-card, #item-details-card-categories').removeClass('hidden');
                    $('.form-item').addClass('hidden');

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
                    $('.item-details-card, #item-details-card-categories').addClass('hidden');
                }
                $('#pwd-definition-size').val(data.pw.length);

                // Prepare card
                $('#card-item-label, #form-item-title').html(data.label);
                $('#form-item-label, #form-item-suggestion-label').val(data.label);
                $('#card-item-description, #form-item-suggestion-description').html(data.description);
                if (data.description === '') {
                    $('#card-item-description').addClass('hidden');
                } else {
                    $('#card-item-description').removeClass('hidden');
                }
                $('#card-item-pwd').html('<?php echo $var['hidden_asterisk']; ?>');
                $('#hidden-item-pwd, #form-item-suggestion-password').val(data.pw);
                $('#form-item-password, #form-item-password-confirmation, #form-item-server-old-password').val(data.pw);
                $('#card-item-login').html(data.login);
                $('#form-item-login, #form-item-suggestion-login, #form-item-server-login').val(data.login);

                $('#card-item-email').text(data.email);
                $('#form-item-email, #form-item-suggestion-email').val(data.email);
                $('#card-item-url').html(data.url);
                $('#form-item-url, #form-item-suggestion-url').val($(data.url).text());
                $('#form-item-restrictedToUsers').val(JSON.stringify(data.id_restricted_to));
                $('#form-item-restrictedToRoles').val(JSON.stringify(data.id_restricted_to_roles));
                $('#form-item-folder').val(data.folder);
                $('#form-item-tags').val(data.tags.join(' '));

                $('#form-item-password').pwstrength("forceUpdate");
                $('#form-item-label').focus();

                // Editor for description field
                if (debugJavascript === true) {console.log('>>>> create summernote');}
                $('#form-item-description')
                    .html(data.description)
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
                    .html(data.description)
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
                                    //$('#form-item-suggestion-description').attr('data-change-ongoing', true);;
                                }
                            }
                        }
                    });


                //prepare nice list of users / groups
                var html_users = '',
                    html_groups = '',
                    html_tags = '',
                    html_kbs = '';

                $(data.tags).each(function(index, value) {
                    html_tags += '<span class="badge badge-success pointer tip mr-2" title="<?php echo langHdl('list_items_with_tag'); ?>" onclick="searchItemsWithTags(\'' + value + '\')"><i class="fas fa-tag fa-sm"></i>&nbsp;<span class="item_tag">' + value + '</span></span>';
                });
                if (html_tags === '') {
                    $('#card-item-tags').html('<?php echo langHdl('none'); ?>');
                } else {
                    $('#card-item-tags').html(html_tags);
                }

                $(data.links_to_kbs).each(function(index, value) {
                    html_kbs += '<a class="badge badge-primary pointer tip mr-2" href="<?php echo $SETTINGS['cpassman_url']; ?>/index.php?page=kb&id=' + value['id'] + '"><i class="fas fa-map-pin fa-sm"></i>&nbsp;' + value['label'] + '</a>';

                });
                if (html_kbs === '') {
                    $('#card-item-kbs').html('<?php echo langHdl('none'); ?>');
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
                        $('.no-item-fields, .form-item-category').addClass('hidden');

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
                                field.value = atob(field.value);
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
                                    .text(field.value);
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


                // Waiting
                $('#card-item-attachments').html("<?php echo langHdl('please_wait'); ?>");

                // Manage clipboard button
                if (itemClipboard) itemClipboard.destroy();
                itemClipboard = new ClipboardJS('.btn-copy-clipboard-clear', {
                        text: function(e) {
                            return ($($(e).data('clipboard-target')).val());
                        }
                    })
                    .on('success', function(e) {
                        toastr.remove();
                        toastr.info(
                            '<?php echo langHdl('copy_to_clipboard'); ?>',
                            '', {
                                timeOut: 2000,
                                progressBar: true,
                                positionClass: 'toast-top-right'
                            }
                        );
                        e.clearSelection();
                    });

                // Prepare clipboard - COPY LOGIN
                if (data.login !== '') {
                    $('#card-item-login-btn').removeClass('hidden');
                } else {
                    $('#card-item-login-btn').addClass('hidden');
                }

                // Prepare clipboard - COPY PASSWORD
                if (data.pw !== '') {
                    new ClipboardJS('#card-item-pwd-button', {
                            text: function() {
                                return (data.pw);
                            }
                        })
                        .on('success', function(e) {
                            itemLog(
                                'at_password_copied',
                                e.trigger.dataset.clipboardId,
                                $('#card-item-label').text()
                            );

                            // Warn user about clipboard clear
                            if (store.get('teampassSettings').clipboard_life_duration === undefined || parseInt(store.get('teampassSettings').clipboard_life_duration) === 0) {
                                toastr.remove();
                                toastr.info(
                                    '<?php echo langHdl('copy_to_clipboard'); ?>',
                                    '', {
                                        timeOut: 2000,
                                        positionClass: 'toast-top-right',
                                        progressBar: true
                                    }
                                );
                            } else {
                                toastr.warning(
                                    '<?php echo langHdl('clipboard_will_be_cleared'); ?>',
                                    '', {
                                        timeOut: store.get('teampassSettings').clipboard_life_duration * 1000,
                                        progressBar: true
                                    }
                                );

                                // Set clipboard eraser
                                clearClipboardTimeout(
                                    store.get('teampassSettings').clipboard_life_duration
                                );
                            }

                            e.clearSelection();
                        });
                    $('#card-item-pwd-button').removeClass('hidden');
                } else {
                    $('#card-item-pwd-button').addClass('hidden');
                }

                // Prepare clipboard - COPY EMAIL
                if (data.email !== '') {
                    $('#card-item-email-btn').removeClass('hidden');
                } else {
                    $('#card-item-email-btn').addClass('hidden');
                }

                // Prepare auto_update info
                $('#card-item-misc').html('');
                if (data.auto_update_pwd_frequency !== '0') {
                    $('#card-item-misc')
                        .append('<span class="fas fa-shield infotip mr-4" title="<?php echo langHdl('auto_update_enabled'); ?>&nbsp;' + data.auto_update_pwd_frequency + '"></span>');
                }

                // Show Notification engaged
                if (data.notification_status === true) {
                    $('#card-item-misc')
                        .append('<span class="mr-4 icon-badge" id="card-item-misc-notification"><span class="far fa-bell infotip text-success" title="<?php echo langHdl('notification_engaged'); ?>"></span></span>');
                } else {
                    $('#card-item-misc')
                        .append('<span class="mr-4 icon-badge" id="card-item-misc-notification"><span class="far fa-bell-slash infotip text-warning" title="<?php echo langHdl('notification_not_engaged'); ?>"></span></span>');
                }

                // Prepare counter
                $('#card-item-misc')
                    .append('<span class="icon-badge mr-4"><span class="far fa-eye infotip" title="<?php echo langHdl('viewed_number'); ?>"></span><span class="badge badge-info icon-badge-text icon-badge-far">' + data.viewed_no + '</span></span>');

                // Delete after X views
                if (data.to_be_deleted !== '') {
                    if (data.to_be_deleted_type === 1) {
                        $('#form-item-deleteAfterShown').val(data.to_be_deleted);
                        $('#form-item-deleteAfterDate').val('');
                    } else {
                        $('#form-item-deleteAfterShown').val('');
                        $('#form-item-deleteAfterDate').val(data.to_be_deleted);
                    }
                    // Show icon
                    $('#card-item-misc')
                        .append('<span class="icon-badge mr-5"><span class="far fa-trash-alt infotip" title="<?php echo langHdl('automatic_deletion_engaged'); ?>"></span><span class="badge badge-danger icon-badge-text-bottom-right">' + data.to_be_deleted + '</span></span>');
                }

                // reset password shown info
                $('#card-item-pwd').removeClass('pwd-shown');

                //Anyone can modify button
                if (data.anyone_can_modify === 1) {
                    $('#form-item-anyoneCanModify').iCheck('check');
                } else {
                    $('#form-item-anyoneCanModify').iCheck('uncheck');
                }



                if (data.show_details === 1 && data.show_detail_option !== 2) {
                    // continue loading data
                    showDetailsStep2(itemId, actionType);
                } else if (data.show_details === 1 && data.show_detail_option === 2) {
                    $('#item_details_nok').addClass('hidden');
                    $('#item_details_ok').addClass('hidden');
                    $('#item_details_expired_full').show();
                    $('#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item, #menu_button_add_fav, #menu_button_del_fav, #menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link').attr('disabled', 'disabled');
                    $('#div_loading').addClass('hidden');
                } else {
                    //Dont show details
                    $('#item_details_nok').removeClass('hidden');
                    $('#item_details_nok_restriction_list').html('<div style="margin:10px 0 0 20px;"><b><?php echo langHdl('author'); ?>: </b>' + data.author + '<br /><b><?php echo langHdl('restricted_to'); ?>: </b>' + data.restricted_to + '<br /><br /><u><a href="#" onclick="openReasonToAccess()"><?php echo langHdl('request_access_ot_item'); ?></a></u></div>');

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
                        .html('<i class="fas fa-arrow-left mr-2"></i>' + unescape($('#list-item-row_'+data.id).prev('.list-item-row').attr('data-label')))
                        .attr('data-prev-item-id', $('#list-item-row_'+data.id).prev('.list-item-row').attr('data-item-id'))
                        .removeClass('hidden');
                }
                if ($('#list-item-row_'+data.id).next('.list-item-row').attr('data-item-id') !== undefined) {
                    $('.but-next-item')
                        .html('<i class="fas fa-arrow-right mr-2"></i>' + unescape($('#list-item-row_'+data.id).next('.list-item-row').attr('data-label')))
                        .attr('data-next-item-id', $('#list-item-row_'+data.id).next('.list-item-row').attr('data-item-id'))
                        .removeClass('hidden');
                }
                console.log("PREV: " + $('#list-item-row_'+data.id).prev('.list-item-row').attr('data-item-id') + " - NEXT: " + $('#list-item-row_'+data.id).next('.list-item-row').attr('data-item-id'));

                // Inform user
                toastr.remove();
                toastr.info(
                    '<?php echo langHdl('done'); ?>',
                    '', {
                        timeOut: 1000
                    }
                );

                return true;
            }
        );
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
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

                if (debugJavascript === true) console.log('RECEIVED STEP2')
                if (debugJavascript === true) console.log(data);

                // Attachments
                if (data !== false) {
                    if (data.attachments.length === 0) {
                        $('#card-item-attachments-badge').html('<?php echo langHdl('none'); ?>');
                        $('#card-item-attachments')
                            .html('<?php echo langHdl('no_attachment'); ?>')
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
                                html +=
                                    '<i class="fas fa-eye infotip preview-image pointer mr-2" ' +
                                    'title="<?php echo langHdl('see'); ?>" ' +
                                    'data-file-id="' + value.id + '" data-file-title="' +
                                    (isBase64(value.filename) === true ? atob(value.filename) : value.filename) + '"></i>';
                            }

                            // Show DOWNLOAD icon
                            downloadIcon =
                                '<a class="text-secondary infotip mr-2" href="sources/downloadFile.php?name=' + encodeURI(value.filename) + '&key=<?php echo $_SESSION['key']; ?>&key_tmp=' + value.key + '&fileid=' + value.id + '" title="<?php echo langHdl('download'); ?>">' +
                                '<i class="fas fa-file-download"></i></a>';
                            html += downloadIcon;

                            // Show other info
                            html +=
                                '<span class="font-weight-bold mr-3">' +
                                (isBase64(value.filename) === true ? atob(value.filename) : value.filename) + '</span>' +
                                '<span class="mr-2 font-weight-light">(' + value.extension + ')</span>' +
                                '<span class="font-italic">' + value.size + '</span>' +
                                '</div></div>';

                            htmlFull += '<div class="col-6 edit-attachment-div"><div class="info-box bg-secondary-gradient">' +
                                '<span class="info-box-icon bg-info"><i class="' + value.icon + '"></i></span>' +
                                '<div class="info-box-content"><span class="info-box-text">' +
                                (isBase64(value.filename) === true ? atob(value.filename) : value.filename) + '.' + value.extension + '</span>' +
                                '<span class="info-box-text">' + downloadIcon +'</span>' +
                                '<span class="info-box-text"><i class="fas fa-trash pointer delete-file" data-file-id="' + value.id + '"></i></span></div>' +
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
                    html_restrictions +=
                        '<span class="badge badge-info mr-2 mb-1"><i class="fas fa-group fa-sm mr-1"></i>' +
                        data.users_list.find(x => x.id === parseInt(value)).name + '</span>';
                });
                $.each(store.get('teampassItem').id_restricted_to_roles, function(i, value) {
                    html_restrictions +=
                        '<span class="badge badge-info mr-2 mb-1"><i class="fas fa-group fa-sm mr-1"></i>' +
                        data.roles_list.find(x => x.id === parseInt(value)).title + '</span>';
                });
                if (html_restrictions === '') {
                    $('#card-item-restrictedto').html('<?php echo langHdl('no_special_restriction'); ?>');
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
                    $('#item_extra_info').prepend('<i class="fas fa-lightbulb-o fa-sm mi-yellow tip" title="<?php echo langHdl('item_has_change_proposal'); ?>"></i>&nbsp;');
                }


                $('.infotip').tooltip();

                // Now load History
                if (actionType === 'show') {
                    $.post(
                        "sources/items.queries.php", {
                            type: "load_item_history",
                            item_id: store.get('teampassItem').id,
                            key: "<?php echo $_SESSION['key']; ?>"
                        },
                        function(data) {
                            //decrypt data
                            data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
                            if (debugJavascript === true) console.info('History:');
                            if (debugJavascript === true) console.log(data);
                            if (data.error === '') {
                                var html = '',
                                    nbHistoryEvents = 0;
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
                            $('#card-item-history').closest().addClass('collapsed');

                            // Hide loading state
                            $('#card-item-history').nextAll().addClass('hidden');
                        }
                    );
                } else if (actionType === 'edit') {
                    getPrivilegesOnItem(
                        selectedFolderId,
                        0
                    );
                }

                // Prepare Select2 inputs
                $('.select2').select2({
                    language: '<?php echo isset($_SESSION['user_language_code']) === true ? $_SESSION['user_language_code'] : 'en'; ?>'
                });

                // Prepare datePicker
                $('#form-item-deleteAfterDate, .datepicker').datepicker({
                    format: '<?php echo str_replace(['Y', 'M'], ['yyyy', 'mm'], $SETTINGS['date_format']); ?>',
                    todayHighlight: true,
                    todayBtn: true,
                    language: '<?php echo isset($_SESSION['user_language_code']) === true ? $_SESSION['user_language_code'] : 'en'; ?>'
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

                // Delete inputs related files uploaded but not confirmed
                var data = {
                    'item_id': store.get('teampassItem').id,
                }

                $.post(
                    "sources/items.queries.php", {
                        type: 'delete_uploaded_files_but_not_saved',
                        data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                        key: '<?php echo $_SESSION['key']; ?>'
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
            }
        );
    };

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
                    '<?php echo langHdl('all_fields_mandatory'); ?>',
                    '<?php echo langHdl('warning'); ?>', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }

            // Insert new entry
            var data = {
                'item_id': store.get('teampassItem').id,
                'label': $('#form-item-history-label').val(),
                'date': $('#form-item-history-date').val(),
                'time': $('#form-item-history-time').val(),
            }
            $.post(
                "sources/items.queries.php", {
                    type: 'history_entry_add',
                    data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                    key: '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                    if (debugJavascript === true) console.log(data);
                    $('.history').val('');

                    // Inform user
                    toastr.info(
                        '<?php echo langHdl('done'); ?>',
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
                    data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                    key: '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    //decrypt data
                    data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
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
                            '<?php echo langHdl('done'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );
                    }
                }
            );
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
            '<?php echo langHdl('loading_image'); ?>...<i class="fa fa-circle-notch fa-spin fa-2x ml-2"></i>'
        );

        $.post(
            "sources/items.queries.php", {
                type: "image_preview_preparation",
                id: fileId,
                key: "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                //decrypt data
                data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                //if (debugJavascript === true) console.log(data);

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
                        '<?php echo langHdl('done'); ?>',
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
                                                label: '<?php echo langHdl('close'); ?>',
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

    /**
     */
    function itemLog(logCase, itemId, itemLabel) {
        itemId = itemId || $('#id_item').val();

        var data = {
            "id": itemId,
            "label": itemLabel,
            "user_id": "<?php echo $_SESSION['user_id']; ?>",
            "action": logCase,
            "login": "<?php echo $_SESSION['login']; ?>"
        };

        $.post(
            "sources/items.logs.php", {
                type: "log_action_on_item",
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: "<?php echo $_SESSION['key']; ?>"
            }
        );
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    function prepareOneTimeView() {
        //Send query
        $.post(
            "sources/items.queries.php", {
                type: "generate_OTV_url",
                id: store.get('teampassItem').id,
                key: "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                //check if format error
                if (data.error == "") {
                    $('#form-item-otv-link').val(data.url);
                    // prepare clipboard
                    var clipboard = new ClipboardJS("#form-item-otv-copy-button", {
                        text: function() {
                            return data.url;
                        }
                    });
                    clipboard.on('success', function(e) {
                        toastr.remove();
                        toastr.info(
                            '<?php echo langHdl('copy_to_clipboard'); ?>',
                            '', {
                                timeOut: 2000,
                                positionClass: 'toast-top-right',
                                progressBar: true
                            }
                        );
                        e.clearSelection();
                    });
                }
            },
            "json"
        );
    }

    /**
     */
    function getPrivilegesOnItem(val, edit, context) {
        context = context || ""; // make context optional

        // Clear memory
        //localStorage.setItem("teampassItem", '');
        if (debugJavascript === true) console.log('Get privilege for folder ' + val);
            
        if (val === "") {
            toastr.remove();
            toastr.error(
                '<?php echo langHdl('error'); ?>',
                '<?php echo langHdl('data_inconsistency'); ?>',
                {
                    timeOut: 5000,
                    positionClass: 'toast-top-right',
                    progressBar: true
                }
            );
            return false;
        }

        return $.post(
            "sources/items.queries.php", {
                type: "get_complixity_level",
                groupe: val,
                context: context,
                item_id: store.get('teampassItem').id,
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

                if (debugJavascript === true) console.info('GET COMPLEXITY LEVEL');
                if (debugJavascript === true) console.log(data);
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
                        var optgroup = $('<optgroup label="<?php echo langHdl('users'); ?>">');
                        $(".restriction_is_user").wrapAll(optgroup);

                        // Now add the roles to the list
                        $(data.rolesList).each(function(index, value) {
                            $("#form-item-restrictedto")
                                .append('<option value="role_' + value.id + '" class="restriction_is_role">' +
                                    value.title + '</option>');
                        });
                        /// Add a group label for Groups
                        $('.restriction_is_role').wrapAll($('<optgroup label="<?php echo langHdl('roles'); ?>">'));
                    }


                    //
                    $('#card-item-visibility').html(data.visibility);

                    // Prepare Select2
                    $('.select2').select2({
                        language: '<?php echo $_SESSION['user_language_code']; ?>'
                    });

                    // Show selected restricted inputs
                    $('#form-item-restrictedto')
                        .val(data.usersList.concat(
                            data.rolesList.map(i => 'role_' + i)))
                        .change();
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
            }
        );
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
                size: $('#pwd-definition-size').val(),
                lowercase: $('#pwd-definition-lcl').prop("checked"),
                numerals: $('#pwd-definition-numeric').prop("checked"),
                capitalize: $('#pwd-definition-ucl').prop("checked"),
                symbols: $('#pwd-definition-symbols').prop("checked"),
                secure_pwd: secure_pwd,
                force: "false",
                key: "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                if (debugJavascript === true) console.log(data)
                if (data.error == "true") {
                    // error
                    toastr.remove();
                    toastr.error(
                        data.error_msg,
                        '<?php echo langHdl('error'); ?>', {
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

    $('#item-button-password-copy').click(function() {
        $('#form-item-password-confirmation').val($('#form-item-password').val());
    });

    /**
     * On tag badge click, launch the search query
     */
    function searchItemsWithTags(criteria) {
        if (criteria !== '') {
            $('#folders-tree-card, .columns-position').removeClass('hidden');
            $('.item-details-card, .form-item-action, .form-item, .form-folder-action').addClass('hidden');

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

    /*
    // Get parameters from url
    var currentTeampassUrl = new URL(window.location.href);
    var actionFromUrl = currentTeampassUrl.searchParams.get('action');
    if (actionFromUrl !== undefined && atob(actionFromUrl) === 'reset_private_key') {
        // Case where we need to re-encrypt all share keys
        if (debugJavascript === true) console.log("ACTION RE-ENCRYPTION OF SHAREKEYS");

        $('#dialog-encryption-keys').removeClass('hidden');    

        // Hide other
        $('.content-header, .content').addClass('hidden');

        alertify.dismissAll();
    }
    */
</script>
