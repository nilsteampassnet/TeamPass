<?php
/**
 * Teampass - a collaborative passwords manager.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category Teampass
 *
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2018 Nils Laumaillé
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 *
 * @version GIT: <git_id>
 *
 * @see      http://www.teampass.net
 */
if (isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1
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
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], curPage($SETTINGS), $SETTINGS)) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

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
    itemEditor,
    itemEditorSuggestion,
    userDidAChange = false,
    userUploadedFile = false,
    selectedFolder = '',
    selectedFolderId = '',
    itemClipboard,
    startedItemsListQuery = false,
    itemStorageInformation = '',
    applicationVars,
    initialPageLoad = true;



// Manage memory
browserSession(
    'init',
    'teampassApplication',
    {
        lastItemSeen : '',
        selectedFolder : '',
        itemsListStop : '',
        itemsListStart : '',
        selectedFolder : '',
        itemsListFolderId : '',
        itemsListRestricted : '',
        itemsShownByQuery : '',
        foldersList : [],
        personalSaltkeyRequired : 0,
    }
);

browserSession(
    'init',
    'teampassItem',
    {
        IsPersonalFolder : '',
        hasAccessLevel : '',
        hasCustomCategories : '',
        id : '',
        timestamp : ''
    }
);


// Build tree
$('#jstree').jstree({
    'core' : {
        'animation' : 0,
        'check_callback' : true,
        'data' : {
            'url' : './sources/tree.php',
            'dataType' : 'json',
            'data' : function (node) {
                return {
                    'id' : node.id.split('_')[1] ,
                    'force_refresh' : store.get('teampassApplication') !== undefined ?
                        store.get('teampassApplication').jstreeForceRefresh : 0
                };
            }
        },
        'strings' : {
            'Loading ...' : '<?php echo langHdl('loading'); ?>...'
        },
    },
    'plugins' : [
        'state', 'search'
    ]
})
// On node select
.bind('select_node.jstree', function (e, data) {
    /*if (initialPageLoad === true
        || store.get('teampassApplication') === undefined
        || store.get('teampassApplication').selectedFolder === undefined
        || store.get('teampassApplication').selectedFolder === ''
    ) {*/
        console.log('JSTREE BIND')
        selectedFolder = $('#jstree').jstree('get_selected', true)[0];
        selectedFolderId = selectedFolder.id.split('_')[1];
        console.info('SELECTED NODE ' + selectedFolderId);
        console.log(selectedFolder);

        store.each(function(value, key) {
            console.log(key, '==', value)
        })

        store.update(
            'teampassApplication',
            function (teampassApplication)
            {
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
    //}
})
// Search in tree
.bind('search.jstree', function (e, data) {
    if (data.nodes.length == 1) {
        //open the folder
        ListerItems($('#jstree li>a.jstree-search').attr('id').split('_')[1], '', 0);
    }
});

// Find folders in jstree
$('#jstree_search')
    .keypress(function(e) {
        if (e.keyCode === 13) {
            $('#jstree').jstree('search',$('#jstree_search').val());
        } 
    })
    .focus(function() {
        $(this).val('');
    })
    .blur(function() {
        $(this).val(folderSearchCriteria);
    });



store.each(function(value, key) {
        console.log(key, '==', value)
    })


// Is this a short url
var queryDict = {},
    showItemOnPageLoad = false,
    itemIdToShow = '';
location.search.substr(1).split("&").forEach(function(item) {queryDict[item.split("=")[0]] = item.split("=")[1]});

if (queryDict['group'] !== undefined && queryDict['group'] !== ''
    && queryDict['id'] !== undefined && queryDict['id'] !== ''
) {
    // Store current view
    savePreviousView();

    // Store the folder to open
    store.set(
        'teampassApplication',
        {
            selectedFolder : queryDict['group'],
        }
    );

    showItemOnPageLoad = true;
    itemIdToShow = queryDict['id'];

    $('.item-details-card').removeClass('hidden');
    $('#folders-tree-card').addClass('hidden');
    
    Details(itemIdToShow, 'show', true);

    // refresh selection in jstree
    $('#jstree').jstree('deselect_all');
    $('#jstree').jstree('select_node', '#li_'+itemIdToShow);
} else {
    /*// On page load, refresh list of items
    selectedFolder = $('#jstree').jstree('get_selected', true)[0];
    console.log(selectedFolder);
    selectedFolderId = selectedFolder.id.split('_')[1];
    console.info('SELECTED NODE ' + selectedFolderId);
    console.log(selectedFolder);

    

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
if (store.get('teampassApplication') !== undefined
    && store.get('teampassApplication').selectedFolder !== undefined
    && store.get('teampassApplication').selectedFolder !== ''
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

// Edit on e key
$(document).keyup(function(e) {
    if (e.keyCode == 69 && $('.item-details-card').is(':visible') === true) {
        if ($('#form-item').hasClass('hidden') === false) {
            showItemEditForm(store.get('teampassItem').id);
        }
    }
});

// load list of visible folders for current user
$(this).delay(500).queue(function() {
    refreshVisibleFolders();
    
    $(this).dequeue();
});

   

// Ensure correct height of folders tree
$('#jstree').height(screenHeight - 200);

// Prepare iCheck format for checkboxes
$('input[type="checkbox"].flat-blue, input[type="radio"].flat-blue').iCheck({
    checkboxClass: 'icheckbox_flat-blue',
    radioClass   : 'iradio_flat-blue'
});


// Manage folders action
$('.tp-action').click(function() {
    if ($(this).data('folder-action') === 'refresh') {
        // Force refresh
        store.update(
            'teampassApplication',
            function (teampassApplication) {
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
            function(teampassApplication)
            {
                teampassApplication.jstreeForceRefresh = 0
            }
        );
            
        //
        // > END <
        //
    } else if ($(this).data('folder-action') === 'expand') {
        $('#jstree').jstree('open_all');
        
        //
        // > END <
        //
    } else if ($(this).data('folder-action') === 'collapse') {
        $('#jstree').jstree('close_all');
        
        //
        // > END <
        //
    } else if ($(this).data('folder-action') === 'add') {
        console.info('SHOW ADD FOLDER');

        // Store current view
        savePreviousView();

        // Store last
        // Show copy form
        $('.form-item, .item-details-card, .form-item-action, #folders-tree-card').addClass('hidden');
        $('.form-folder-add').removeClass('hidden');
        // Prepare some data in the form
        $('#form-folder-add-parent').val(selectedFolder.parent.split('_')[1]).change();
        $('#form-folder-add-label')
            .val('')
            .focus();
        // Set type of action for the form
        $('#form-folder-add').data('action', 'add');
        
        //
        // > END <
        //
    } else if ($(this).data('folder-action') === 'edit') {
        console.info('SHOW EDIT FOLDER');
        // Store current view
        savePreviousView();

        // Show edit form
        $('.form-item, .item-details-card, .form-item-action, #folders-tree-card').addClass('hidden');
        $('.form-folder-add').removeClass('hidden');
        // Prepare some data in the form
        $("#form-folder-add-parent option[value='" + store.get('teampassApplication').selectedFolder + "']")
            .prop('disabled', true);
        $('#form-folder-add-parent').val(store.get('teampassApplication').selectedFolderParentId).change();
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
        console.info('SHOW COPY FOLDER');

        // Store current view
        savePreviousView();

        // Show copy form
        $('.form-item, .item-details-card, .form-item-action, #folders-tree-card').addClass('hidden');
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
        console.info('SHOW DELETE FOLDER');

        // Store current view
        savePreviousView();

        // Show copy form
        $('.form-item, .item-details-card, .form-item-action, #folders-tree-card').addClass('hidden');
        $('.form-folder-delete').removeClass('hidden');

        // Prepare some data in the form
        $('#form-folder-delete-selection').val(store.get('teampassApplication').selectedFolder).change();
        $('#form-folder-confirm-delete').iCheck('uncheck');

        //
        // > END <
        //
    } else if ($(this).data('folder-action') === 'import') {
        // IMPORT ITEMS
        console.info('SHOW IMPORT ITEMS');

        // Store current view
        savePreviousView();

        
        // Show import form
        $('.form-item, .item-details-card, .form-item-action, #folders-tree-card').addClass('hidden');
        $('.form-folder-import').removeClass('hidden');

        //
        // > END <
        //
    } else if ($(this).data('item-action') === 'new') {
        console.info('SHOW NEW ITEM');

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
                function (teampassItem)
                {
                    teampassItem.isNewItem = 1,
                    teampassItem.id = ''
                }
            );
            
            // Check if personal SK is needed and set
            if (store.get('teampassApplication').personalSaltkeyRequired === 1
                && (store.get('teampassUser').pskDefinedInDatabase !== 1
                || store.get('teampassUser').pskSetForSession === ''
                || store.get('teampassUser').pskSetForSession === undefined)
                && store.get('teampassItem').folderIsPersonal === 1
            ) {
                // SHow PSK form
                showPersonalSKDialog();

                // Clear ongoing request status
                requestRunning = false;

                // Finished
                return false;
            }

            // Show Visibility and minimum complexity
            $('#card-item-visibility').html(store.get('teampassItem').itemVisibility);
            $('#card-item-minimum-complexity').html(store.get('teampassItem').itemMinimumComplexity);
            
            // HIde
            $('.form-item-copy, #folders-tree-card, #form-item-password-options, .form-item-action, #form-item-attachments-zone')
                .addClass('hidden');
            // Destroy editor
            if (itemEditor) itemEditor.destroy();
            // Clean select2 lists
            $('.select2').val('');
            /*if ($('.select2') !== null) {console.log($('.select2').length)
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
            ClassicEditor
                .create(
                    document.querySelector('#form-item-description'), {
                        toolbar: [ 'heading', 'bold', 'italic', 'bulletedList', 'numberedList', 'blockQuote', 'link' , 'undo', 'redo'  ]
                    }
                )
                .then( editor => {
                    itemEditor = editor;
                } )
                .catch( error => {
                    console.log( error );
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
            // Update variable
            userDidAChange = false;
        });
        
        //
        // > END <
        //
    } else if ($(this).data('item-action') === 'edit') {
        console.info('SHOW EDIT ITEM');

        // Store current view
        savePreviousView();
        
        // Store not a new item
        store.update(
            'teampassItem',
            function (teampassItem)
            {
                teampassItem.isNewItem = 0
            }
        );

        // Remove validated class
        $('#form-item').removeClass('was-validated');

        // Now manage edtion
        showItemEditForm(selectedFolderId);
        
        //
        // > END <
        //
    } else if ($(this).data('item-action') === 'copy') {
        console.info('SHOW COPY ITEM');

        // Store current view
        savePreviousView();

        if (store.get('teampassItem').user_can_modify === 1) {
            // Show copy form
            $('.form-item, .item-details-card, .form-item-action').addClass('hidden');
            $('.form-item-copy, .item-details-card-menu').removeClass('hidden');
            // Prepare some data in the form
            $('#form-item-copy-new-label').val($('#form-item-label').val());
            $('#form-item-copy-destination').val($('#form-item-folder').val()).change();
        } else {
            alertify
                .error(
                    '<i class="fas fa-ban fa-lg mr-2"></i>' + store.get('teampassItem').message,
                    5
                )
                .dismissOthers();
        }
        
        //
        // > END <
        //
    } else if ($(this).data('item-action') === 'delete') {
        // Store current view
        savePreviousView();

        console.info('SHOW DELETE ITEM');
        if (store.get('teampassItem').user_can_modify === 1) {
            // Show delete form
            $('.form-item, .item-details-card, .form-item-action').addClass('hidden');
            $('.form-item-delete, .item-details-card-menu').removeClass('hidden');
        } else {
            alertify
                .error(
                    '<i class="fas fa-ban fa-lg mr-2"></i>' + store.get('teampassItem').message,
                    5
                )
                .dismissOthers();
        }
        
        //
        // > END <
        //
    } else if ($(this).data('item-action') === 'share') {
        console.info('SHOW SHARE ITEM');

        // Store current view
        savePreviousView();

        // Show share form
        $('.form-item, .item-details-card, .form-item-action').addClass('hidden');
        $('.form-item-share, .item-details-card-menu').removeClass('hidden');
        
        //
        // > END <
        //
    } else if ($(this).data('item-action') === 'notify') {
        console.info('SHOW NOTIFY ITEM');

        // Store current view
        savePreviousView();

        $('#form-item-notify-checkbox').iCheck('uncheck');
        // Show notify form
        $('.form-item, .item-details-card, .form-item-action').addClass('hidden');
        $('.form-item-notify, .item-details-card-menu').removeClass('hidden');
        
        //
        // > END <
        //
    }
});



function savePreviousView()
{
    var element = '';
    if ($('#folders-tree-card').hasClass('hidden') === false) {
        element = '#folders-tree-card';
    } else if ($('.form-item').hasClass('hidden') === false) {
        element = '.form-item';
    } else if ($('.item-details-card-menu').hasClass('hidden') === false) {
        element = '.item-details-card';
    }
    
    store.update(
        'teampassUser',
        function (teampassUser)
        {
            teampassUser.previousView = element;
        }
    );
}
$('.but-back').click(function() {
    // Hide all
    $('.form-item, .form-item-action,.form-folder-action, .item-details-card, #folders-tree-card').addClass('hidden');

    // Show expected one
    $(store.get('teampassUser').previousView).removeClass('hidden');

    // Destroy editor
    if (itemEditor) itemEditor.destroy();

/*
    #folders-tree-card
    .form-item-action
    .form-folder-action
    .item-details-card
    .form-item
*/
});


// Quit item details card back to items list
$('.but-back-to-list').click(function() {
    closeItemDetailsCard();
});



// Manage if change is performed by user
$('#form-item .track-change').on('change', function() {
    if ($(this).val().length > 0) {
        userDidAChange = true;
        $(this).data('change-ongoing', true);

        // SHow button in sticky footer
        //$('#form-item-buttons').addClass('sticky-footer');
    }
});

/**
 * Click on perform IMPORT
 */
$(document).on('click', '#form-item-import-perform', function() {
    console.log('START IMPORT');
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
        function (teampassItem)
        {
            teampassItem.id = selectedItemId;
        }
    );
    
    // Show user
    $('.form-item, .item-details-card, .form-item-action, #folders-tree-card').addClass('hidden');
    $('.form-item-request-access').removeClass('hidden');
});

/**
 * Send an access request to author
 */
$(document).on('click', '#form-item-request-access-perform', function() {
    // No reason is provided
    if ($('#form-item-request-access-reason').val() === '') {
        alertify
            .error(
                '<i class="fas fa-ban fa-lg mr-2"></i><?php echo langHdl('error_provide_reason'); ?>',
                5
            )
            .dismissOthers();
        return false;
    }
    
    var data = {
        'id'     : store.get('teampassItem').id,
        'email'  : $('#form-item-request-access-reason').val(),
    }
    // NOw send the email
    $.post(
        "sources/items.queries.php",
        {
            type : 'send_request_access',
            data : prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
            key  : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
            console.log(data);

            if (data.error !== false) {
                // Show error
                alertify
                    .error('<i class="fa fa-ban mr-2"></i>' + data.message, 3)
                    .dismissOthers();
            } else {
                // Change view
                $('.form-item-request-access').addClass('hidden');
                $('#folders-tree-card').removeClass('hidden');

                // Inform user
                alertify
                    .success('<?php echo langHdl('done'); ?>', 1)
                    .dismissOthers();
            }
        }
    );
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
        console.log('teampass-folders');
        console.log(store.get('teampass-folders'))
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

    // Show cog
    alertify
        .message('<i class="fas fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();

    var data = {
        'notification_status' : $('#form-item-notify-checkbox').is(':checked') === true ? 1 : 0,
        'item_id' : store.get('teampassItem').id,
    }
    
    // Launch action
    $.post(
        'sources/items.queries.php',
        {
            type    : 'save_notification_status',
            data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
            key     : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');

            if (data.error !== false) {
                // Show error
                alertify
                    .error('<i class="fa fa-ban mr-2"></i>' + data.message, 3)
                    .dismissOthers();
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
                alertify
                    .success('<?php echo langHdl('done'); ?>', 1)
                    .dismissOthers();

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
    alertify
        .message('<i class="fas fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();

    // Prepare data
    var data = {
        'id'        : store.get('teampassItem').id,
        'receipt'   : $('#form-item-share-email').val(),
        'cat'       : 'share_this_item',
    }

    // Launch action
    $.post(
        'sources/items.queries.php',
        {
            type    : 'send_email',
            data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
            key     : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');

            if (data.error !== false) {
                // Show error
                alertify
                    .error('<i class="fa fa-ban mr-2"></i>' + data.message, 3)
                    .dismissOthers();
            } else {
                $('.item-details-card').removeClass('hidden');
                $('.form-item-share').addClass('hidden');

                // Inform user
                alertify
                    .success('<?php echo langHdl('done'); ?>', 1)
                    .dismissOthers();

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
    alertify
        .message('<i class="fas fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();
    
    // Force user did a change to false
    userDidAChange = false;
    userUploadedFile = false;

    var data = {
        'item_id'   : store.get('teampassItem').id,
        'folder_id' : selectedFolderId,
        'label' : $('#form-item-copy-new-label').val(),
    }

    // Launch action
    $.post(
        'sources/items.queries.php',
        {
            type    : 'delete_item',
            data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
            key     : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {//decrypt data
            data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

            if (data.error !== true) {
                $('.form-item-action, .item-details-card-menu').addClass('hidden');
                // Warn user
                alertify.success('<?php echo langHdl('success'); ?>', 1);
                // Refresh tree
                refreshTree(selectedFolderId, true);
                // Load list of items
                ListerItems(selectedFolderId, '', 0);
                // Close
                closeItemDetailsCard();
            } else {
                // ERROR
                alertify
                    .error(
                        '<i class="fas fa-warning fa-lg mr-2"></i>Message: ' + data.message,
                        0
                    )
                    .dismissOthers();
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
        'sources/items.queries.php',
        {
            type    : 'notify_user_on_item_change',
            id      : store.get('teampassItem').id,
            value   : $('#form-item-anyoneCanModify').is(':checked') === true ? 1 : 0,
            key     : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            if (data[0].error === '') {
                $('.form-item, .item-details-card, .form-item-action').removeClass('hidden');
                $('.form-item-share, .item-details-card-menu').addClass('hidden');
                // Warn user
                alertify.success('<?php echo langHdl('success'); ?>', 1);
                // Clear
                $('#form-item-anyoneCanModify').attr('checked', '');
            } else {
                // ERROR
                alertify
                    .error(
                        '<i class="fas fa-warning fa-lg mr-2"></i>Message: ' + data[0].message,
                        0
                    )
                    .dismissOthers();
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
        alertify
            .error('<i class="fas fa-ban fa-lg mr-3"></i><?php echo langHdl('error_field_is_mandatory'); ?>', 0)
            .dismissOthers();
        return false;
    }

    // Show cog
    alertify
        .message('<i class="fas fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();

    // Force user did a change to false
    userDidAChange = false;
    userUploadedFile= false;

    var data = {
        'item_id'   : store.get('teampassItem').id,
        'source_id' : selectedFolderId,
        'dest_id'   : $('#form-item-copy-destination').val(),
        'new_label' : $('#form-item-copy-new-label').val(),
    }

    // Launch action
    $.post(
        'sources/items.queries.php',
        {
            type    : 'copy_item',
            data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
            key     : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            if (data[0].error !== '') {
                // ERROR
                alertify
                    .error(
                        '<i class="fas fa-warning fa-lg mr-2"></i>Message: ' + data[1].error_text,
                        0
                    )
                    .dismissOthers();
            } else {
                // Warn user
                alertify.success('<?php echo langHdl('success'); ?>', 1);
                // Refresh tree
                refreshTree($('#form-item-copy-destination').val(), true);
                // Load list of items
                ListerItems($('#form-item-copy-destination').val(), '', 0);
                // Close
                closeItemDetailsCard();
            }
        },
        'json'
    );
});


/**
 * SUGGESTION - perform new suggestion on item
 */
$('#form-item-suggestion-perform').click(function() {
    var form = $('#form-item-suggestion');

    if (form[0].checkValidity() === false) {
        form.addClass('was-validated');
        return false;
    }

    // Show cog
    alertify
        .message('<i class="fas fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();

    // Force user did a change to false
    userDidAChange = false;
    userUploadedFile= false;

    var data = {
        'label'         : $('#form-item-suggestion-label').val(),
        'login'         : $('#form-item-suggestion-login').val(),
        'password'      : $('#form-item-suggestion-password').val(),
        'email'         : $('#form-item-suggestion-email').val(),
        'url'           : $('#form-item-suggestion-url').val(),
        'description'   : itemEditorSuggestion.getData(),
        'comment'       : $('#form-item-suggestion-comment').val(),
        'folder_id'     : selectedFolderId,
        'item_id'       : store.get('teampassItem').id
    }

    // Launch action
    $.post(
        'sources/items.queries.php',
        {
            type    : 'suggest_item_change',
            data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
            key     : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            //decrypt data//decrypt data
            data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

            if (data.error === true) {
                // ERROR
                alertify
                    .error(
                        '<i class="fas fa-warning fa-lg mr-2"></i>Message: ' + data.message,
                        0
                    )
                    .dismissOthers();
            } else {
                // Warn user
                alertify
                    .success('<?php echo langHdl('success'); ?>', 1)
                    .dismissOthers();
                // Clear form
                $('.form-item-suggestion').val('');
                itemEditorSuggestion.setData('');
                // Collapse form
                $('.card-item-extra').collapse('toggle');
            }
        }
    );
});


/**
 * FOLDER NEW - Add a new folder
 */
$('#form-folder-add-perform').click(function() {
    var form = $('#form-folder-add');

    if (form[0].checkValidity() === false) {
        form.addClass('was-validated');
        return false;
    }

    // Error if folder text is only numeric
    if (/^\d+$/.test($('#form-folder-add-label').val())) {
        $('#form-folder-add-label').addClass('is-invalid');
        alertify
            .error(
                '<i class="fas fa-ban fa-lg mr-3"></i><?php echo langHdl('error_only_numbers_in_folder_name'); ?>',
                5,
                'top-right'
            )
            .dismissOthers();
        
        return false;
    }

    // Show cog
    alertify
        .message('<i class="fas fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();

    // Force user did a change to false
    userDidAChange = false;
    userUploadedFile= false;

    var data = {
        'title'       : $('#form-folder-add-label').val(),
        'parentId'    : $('#form-folder-add-parent option:selected').val(),
        'complexity'  : $('#form-folder-add-complexicity option:selected').val(),
        'id'          : selectedFolderId,
    }
    console.log(data);

    // Launch action
    $.post(
        'sources/folders.queries.php',
        {
            type    : $('#form-folder-add').data('action') + '_folder',
            data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
            key     : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            //decrypt data//decrypt data
            data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
            
            if (data.error === true) {
                // ERROR
                alertify
                    .error(
                        '<i class="fas fa-warning fa-lg mr-2"></i>Message: ' + data.message,
                        0
                    )
                    .dismissOthers();
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
                        function(teampassApplication)
                        {
                            teampassApplication.jstreeForceRefresh = 1;
                        }
                    );
                    refreshTree(selectedFolderId, true);
                    // Refresh list of items inside the folder
                    ListerItems(selectedFolderId, '', 0);
                    store.update(
                        'teampassApplication',
                        function(teampassApplication)
                        {
                            teampassApplication.jstreeForceRefresh = 0;
                        }
                    );
                }
                // Back to list
                closeItemDetailsCard();
                // Warn user
                alertify
                    .success('<?php echo langHdl('success'); ?>', 1)
                    .dismissOthers();
            }
            // Enable the parent in select
            $("#form-folder-add-parent option[value='"+selectedFolder.id.split('_')[1]+"']")
            .prop('disabled', false);
        }
    );
});


/**
 * FOLDER DELETE - Delete an existing folder
 */
$('#form-folder-delete-perform').click(function() {
    // Do check
    if ($('#form-folder-confirm-delete').is(':checked') === false) {
        alertify
            .error('<i class="fas fa-ban fa-lg mr-3"></i><?php echo langHdl('please_confirm'); ?>', 0)
            .dismissOthers();
        return false;
    } else if ($('#form-folder-delete-selection option:selected').text() === '<?php echo $_SESSION['login']; ?>') {
        alertify
            .error('<i class="fas fa-ban fa-lg mr-3"></i><?php echo langHdl('error_not_allowed_to'); ?>', 0)
            .dismissOthers();
        return false;
    }

    // Show cog
    alertify
        .message('<i class="fas fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();


    var selectedFolders = [],
        data = {
            'selectedFolders' : [$('#form-folder-delete-selection option:selected').val()]
        }
    console.log(data)
    
    // Launch action
    $.post(
        'sources/folders.queries.php',
        {
            type    : 'delete_folders',
            data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
            key     : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            //decrypt data
            data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

            if (data.error === true) {
                // ERROR
                alertify
                    .error(
                        '<i class="fas fa-warning fa-lg mr-2"></i>Message: ' + data.message,
                        0
                    )
                    .dismissOthers();
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
                alertify
                    .success('<?php echo langHdl('success'); ?>', 1)
                    .dismissOthers();
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
        alertify
            .error('<i class="fas fa-ban fa-lg mr-3"></i><?php echo langHdl('error_must_enter_all_fields'); ?>', 0)
            .dismissOthers();
        return false;
    } else if ($("#form-folder-copy-source").val() === $("#form-folder-copy-destination").val()) {
        alertify
            .error('<i class="fas fa-ban fa-lg mr-3"></i><?php echo langHdl('error_source_and_destination_are_equal'); ?>', 0)
            .dismissOthers();
        return false;
    }

    // Show cog
    alertify
        .message('<i class="fas fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();

    var data = {
        'source_folder_id'  : $('#form-folder-copy-source option:selected').val(),
        'target_folder_id'  : $('#form-folder-copy-destination option:selected').val(),
        'folder_label'      : $('#form-folder-copy-label').val(),
    }
    
    // Launch action
    $.post(
        'sources/folders.queries.php',
        {
            type    : 'copy_folder',
            data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
            key     : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            //decrypt data
            data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

            if (data.error === true) {
                // ERROR
                alertify
                    .error(
                        '<i class="fas fa-warning fa-lg mr-2"></i>Message: ' + data.message,
                        0
                    )
                    .dismissOthers();
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
                alertify
                    .success('<?php echo langHdl('success'); ?>', 1)
                    .dismissOthers();
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
function closeItemDetailsCard()
{
    console.log('CLOSE - user did a change? '+userDidAChange)
    if (userDidAChange === true) {
        alertify.confirm(
            '<?php echo TP_TOOL_NAME; ?>',
            '<?php echo langHdl('changes_ongoing'); ?>',
            function(){
                alertify.success('<?php echo langHdl('ok'); ?>', 1);
                userDidAChange = false;
                closeItemDetailsCard();
            },
            function(){
                alertify.error('<?php echo langHdl('cancel'); ?>', 1);
            }
        );
    } else {
        if (store.get('teampassUser').previousView === '.item-details-card') {
            $('#folders-tree-card').removeClass('hidden');
            $('.item-details-card, .form-item-action, .form-item, .form-folder-action').addClass('hidden');
        } else {
            // Hide all
            $('.form-item, .form-item-action, .form-folder-action, .item-details-card, #folders-tree-card').addClass('hidden');

            // Show expected one
            $(store.get('teampassUser').previousView).removeClass('hidden');
        }

        // Do some form cleaning
        $('.clear-me-val').val('');
        $('.item-details-card').find('.form-control').val('');
        $('.clear-me-html, .card-item-field-value').html('');
        $('.form-item-control').val('');
        $('.form-check-input').attr('checked', '');
        $('.card-item-extra').collapse();
        $('.to_be_deleted').remove();

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
        if (itemEditor) {
            itemEditor.destroy();
        }
        if (itemEditorSuggestion) {
            itemEditorSuggestion.destroy();
        }

        // Collapse accordion
        $('.collapseme').addClass('collapsed-card');

        // Restore scroll position
        $(window).scrollTop(userScrollPosition);

        userDidAChange = false;
    
        // Enable the parent in select
        $("#form-folder-add-parent option[value='"+selectedFolder.id.split('_')[1]+"']")
            .prop('disabled', false);

        console.log('Edit for closed');
    }
}


 
/**
 * Click on item
 */
$(document)
    .on('click', '.list-item-clicktoshow', function() {
        showAlertify(
            '<span class="fas fa-cog fa-spin fa-2x"></span>',
            0,
            'bottom-right',
            'message'
        );

        // Load item info
        Details($(this).closest('tr'), 'show');
    })
    .on('click', '.list-item-clicktoedit', function() {
        showAlertify(
            '<span class="fas fa-cog fa-spin fa-2x"></span>',
            0,
            'bottom-right',
            'message'
        );
        console.log('EDIT ME')
        // Set type of action
        $('#form-item-button-save').data('action', 'update_item');

        // Load item info
        Details($(this).closest('tr'), 'edit');
    })
    .on('click', '#card-item-otv-generate-button', function() {        
        prepareOneTimeView();
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
        console.log('> '+userDidAChange)
    });

$('.form-check-input-template').on('ifChecked', function() {
    $('.form-check-input-template').not(this).iCheck('uncheck');
    userDidAChange = true;
    $('.form-check-input-template').data('change-ongoing', true);
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
            alertify
                .message('<?php echo langHdl('success'); ?>', 0);

            $.post('sources/items.queries.php',
                {
                    type    : 'action_on_quick_icon',
                    item_id : $(this).data('item-id'),
                    action  : $(this).data('item-favourited'),
                    key     : '<?php echo $_SESSION['key']; ?>'
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

                    alertify
                        .success('<?php echo langHdl('success'); ?>', 1)
                        .dismissOthers();
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
})
.on('mouseleave', '.unhide_masked_data', function(event) {
     mouseStillDown = false;
});
var showPwdContinuous = function(){
    if(mouseStillDown === true) {
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
        if ($("#pw_shown").val() === "0") {
            itemLog(
                'at_password_shown',
                store.get('teampassItem').id,
                $('#card-item-label').text()
            );
            $("#pw_shown").val('1');
        }
    } else {
        $('#card-item-pwd').html('<?php echo $var['hidden_asterisk']; ?>');
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
    if(mouseStillDown){
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
    if(code == 13) {
        searchItems($(this).val());
    }
});

$('#find_items_button').click(function() {
    if ($('#find_items').val() !== '') {
        searchItems($('#find_items').val());
    }
});


// For Personal Saltkey
$("#form-item-password").simplePassMeter({
    "requirements": {},
    "container": "#form-item-password-strength",
    "defaultText" : "<?php echo langHdl('index_pw_level_txt'); ?>",
    "ratings": [
        {"minScore": 0,
            "className": "meterFail",
            "text": "<?php echo langHdl('complex_level0'); ?>"
        },
        {"minScore": 25,
            "className": "meterWarn",
            "text": "<?php echo langHdl('complex_level1'); ?>"
        },
        {"minScore": 50,
            "className": "meterWarn",
            "text": "<?php echo langHdl('complex_level2'); ?>"
        },
        {"minScore": 60,
            "className": "meterGood",
            "text": "<?php echo langHdl('complex_level3'); ?>"
        },
        {"minScore": 70,
            "className": "meterGood",
            "text": "<?php echo langHdl('complex_level4'); ?>"
        },
        {"minScore": 80,
            "className": "meterExcel",
            "text": "<?php echo langHdl('complex_level5'); ?>"
        },
        {"minScore": 90,
            "className": "meterExcel",
            "text": "<?php echo langHdl('complex_level6'); ?>"
        }
    ]
});
$("#form-item-password").bind({
    "score.simplePassMeter" : function(jQEvent, score) {
        $("#form-item-password-complex").val(score);
    }
}).change({
    "score.simplePassMeter" : function(jQEvent, score) {
        $("#form-item-password-complex").val(score);
    }
});

$("#form-item-suggestion-password").simplePassMeter({
    "requirements": {},
    "container": "#form-item-suggestion-password-strength",
    "defaultText" : "<?php echo langHdl('index_pw_level_txt'); ?>",
    "ratings": [
        {"minScore": 0,
            "className": "meterFail",
            "text": "<?php echo langHdl('complex_level0'); ?>"
        },
        {"minScore": 25,
            "className": "meterWarn",
            "text": "<?php echo langHdl('complex_level1'); ?>"
        },
        {"minScore": 50,
            "className": "meterWarn",
            "text": "<?php echo langHdl('complex_level2'); ?>"
        },
        {"minScore": 60,
            "className": "meterGood",
            "text": "<?php echo langHdl('complex_level3'); ?>"
        },
        {"minScore": 70,
            "className": "meterGood",
            "text": "<?php echo langHdl('complex_level4'); ?>"
        },
        {"minScore": 80,
            "className": "meterExcel",
            "text": "<?php echo langHdl('complex_level5'); ?>"
        },
        {"minScore": 90,
            "className": "meterExcel",
            "text": "<?php echo langHdl('complex_level6'); ?>"
        }
    ]
});
$("#form-item-suggestion-password").bind({
    "score.simplePassMeter" : function(jQEvent, scorescore) {
        $("#form-item-suggestion-password-complex").val(score);
    }
}).change({
    "score.simplePassMeter" : function(jQEvent, scorescore) {
        $("#form-item-suggestion-password-complex").val(score);
    }
});


/**
 * PLUPLOAD
 */
var uploader_attachments = new plupload.Uploader({
    runtimes : 'html5,flash,silverlight,html4',
    browse_button : 'form-item-attach-pickfiles',
    container : 'form-item-upload-zone',
    max_file_size : '<?php
    if (strrpos($SETTINGS['upload_maxfilesize'], 'mb') === false) {
        echo $SETTINGS['upload_maxfilesize'].'mb';
    } else {
        echo $SETTINGS['upload_maxfilesize'];
    }
    ?>',
    chunk_size : '1mb',
    dragdrop : true,
    url : '<?php echo $SETTINGS['cpassman_url']; ?>/sources/upload.attachments.php',
    flash_swf_url : '<?php echo $SETTINGS['cpassman_url']; ?>/includes/libraries/Plupload/Moxie.swf',
    silverlight_xap_url : '<?php echo $SETTINGS['cpassman_url']; ?>/includes/libraries/Plupload/Moxie.xap',
    filters : {
        mime_types : [
            <?php
            if (isset($SETTINGS['upload_all_extensions_file']) === false
                || (isset($SETTINGS['upload_all_extensions_file']) === true
                && $SETTINGS['upload_all_extensions_file'] === '0')
            ) {
                ?>
            {title : 'Image files', extensions : '<?php echo $SETTINGS['upload_imagesext']; ?>'},
            {title : 'Package files', extensions : '<?php echo $SETTINGS['upload_pkgext']; ?>'},
            {title : 'Documents files', extensions : '<?php echo $SETTINGS['upload_docext']; ?>'},
            {title : 'Other files', extensions : '<?php echo $SETTINGS['upload_otherext']; ?>'}
                <?php
            }
            ?>
                    ],
            <?php
            if (isset($SETTINGS['upload_zero_byte_file']) === true && $SETTINGS['upload_zero_byte_file'] === '1') {
                ?>
                    prevent_empty : false
                <?php
            }
            ?>
        },
        <?php
        if ($SETTINGS['upload_imageresize_options'] === '1') {
            ?>
    resize : {
        width : <?php echo $SETTINGS['upload_imageresize_width']; ?>,
        height : <?php echo $SETTINGS['upload_imageresize_height']; ?>,
        quality : <?php echo $SETTINGS['upload_imageresize_quality']; ?>
    },
            <?php
        }
        ?>
    init: {
        BeforeUpload: function (up, file) {
            showAlertify(
                '<?php echo langHdl('please_wait'); ?>',
                1,
                'bottom-right',
                'message'
            );

            // Get random number
            store.update(
                'teampassApplication',
                function (teampassApplication) {
                    teampassApplication.uploadedFileId = CreateRandomString(9, 'num_no_0');
                }
            );

            up.setOption('multipart_params', {
                PHPSESSID       : '<?php echo $_SESSION['user_id']; ?>',
                itemId          : store.get('teampassItem').id,
                type_upload     : 'item_attachments',
                isNewItem       : store.get('teampassItem').isNewItem,
                edit_item       : false,
                user_token      : store.get('teampassApplication').attachmentToken,
                randomId        : store.get('teampassApplication').uploadedFileId,
                files_number    : $('#form-item-hidden-pickFilesNumber').val()
            });
        },
        UploadComplete: function(up, files) {
            //console.log(files)
            //console.log('----')
            userUploadedFile = true;
            alertify
                .success('<?php echo langHdl('success'); ?>', 1)
                .dismissOthers();
            $('#form-item-hidden-pickFilesNumber').val(0);
        }
    }
});

// Uploader options
uploader_attachments.bind('UploadProgress', function(up, file) {
    $('#upload-file_' + file.id).html('<i class="fas fa-file fa-sm mr-2"></i>' + file.name + ' - ' + file.percent + '%');
});
uploader_attachments.bind('Error', function(up, err) {
    alertify
        .error(
            '<i class="fas fa-warning fa-lg mr-2"></i>Message: ' +
            err.message + (err.file ? ', File: ' + err.file.name : ''),
            0
        )
        .dismissOthers();
        
    up.refresh(); // Reposition Flash/Silverlight
});
uploader_attachments.bind('FilesAdded', function(up, file) {
    $('#upload-file_' + file.id + '')
        .html('<i class="fas fa-file fa-sm mr-2"></i>' + file.name + ' <?php echo langHdl('uploaded'); ?>');
});

$("#form-item-upload-pickfiles").click(function(e) {
    if ($('#form-item-upload-pickfilesList').text() !== '') {
        // generate and save token
        $.post(
            "sources/main.queries.php",
            {
                type : "save_token",
                size : 25,
                capital: true,
                numeric: true,
                ambiguous: true,
                reason: "item_attachments",
                duration: 10
            },
            function(data) {
                store.update(
                    'teampassApplication',
                    function (teampassApplication) {
                        teampassApplication.attachmentToken = data[0].token;
                    }
                );
                uploader_attachments.start();
            },
            "json"
        );
        e.preventDefault();
    } else {
        alertify
            .warning(
                '<i class="fas fa-warning fa-lg mr-2"></i><?php echo langHdl('no_file_to_upload'); ?>',
                2
            )
            .dismissOthers();
    }
});
uploader_attachments.init();
uploader_attachments.bind('FilesAdded', function(up, files) {
    $('#form-item-upload-pickfilesList').removeClass('hidden');
    var addedFiles = '';
    $.each(files, function(i, file) {
        $('#form-item-upload-pickfilesList').append(
            '<div id="upload-file_' + file.id + '">' +
                '<span id="upload-file-remove_' + file.id +
                '><a href="#" onclick="$(this).closest(\'div\').remove();"><i class=" fa fa-trash mr-2 pointer"></i></a></span> ' +
            file.name + ' (' + plupload.formatSize(file.size) + ')' +
        '</div>');
        $("#form-item-hidden-pickFilesNumber").val(
            parseInt($("#form-item-hidden-pickFilesNumber").val()) + 1
        );
        console.log(file);
    });
    up.refresh(); // Reposition Flash/Silverlight
});
//->



/**
 * Save item changes
 */
$('#form-item-button-save').click(function() {
    var arrayQuery = [],
        originalFolderId = $('#form-item-folder').val();

    // What action is this?
    if ($('#form-item-button-save').data('action') === ''
        || $('#form-item-button-save').data('action') === undefined
    ) {
        alertify
            .error('<i class="fas fa-ban fa-lg mr-3"></i><?php echo langHdl('error_no_action_identified'); ?>', 10)
            .dismissOthers();
        return false;
    }

    // Don't save if no change
    if (userDidAChange === false && userUploadedFile === false) {
        alertify
            .warning('<i class="fas fa-info fa-lg mr-3"></i><?php echo langHdl('no_change_performed'); ?>', 3)
            .dismissOthers();
        return false;
    }

    // Validate form
    var form = $('#form-item');
    if (form[0].checkValidity() === false) {
        form.addClass('was-validated');
        return false;
    }

    // Check if description has changed
    if ($('#form-item-description').val().localeCompare(itemEditor.getData()) !== 0) {
        $('#form-item-description').val(itemEditor.getData());
        $('#form-item-description').data('change-ongoing', true);
    }
    
    // Loop on all changed fields
    $('.form-item-control').each(function(i, obj) {
        //console.log($(this).data('field-name'))
        //console.log($(this))
        if ($(this).data('change-ongoing') === true) {
            //Complete url format
            if ($(this).data('field-name') === 'url') {
                var url = $(this).val();
                if (url.substring(0,7) !== 'http://'
                    && url !== ''
                    && url.substring(0,8) !== 'https://'
                    && url.substring(0,6) !== 'ftp://'
                    && url.substring(0,6) !== 'ssh://'
                ) {
                    $(this).val('http://' + url);
                }
            }

            arrayQuery.push({
                'field' : $(this).data('field-name'),
                'value' : $(this).val(),
            });
        }
    });


    // Do checks
    if (arrayQuery.length > 0) {
        var reg = new RegExp("[.|,|;|:|!|=|+|-|*|/|#|\"|'|&]");

        // Do some easy checks
        if ($('#form-item-label').val() === '') {
            // Label is empty
            alertify
                .error('<i class="fas fa-ban fa-lg mr-3"></i><?php echo langHdl('error_label'); ?>', 10)
                .dismissOthers();
            return false;
        } else if ($('#form-item-tags').val() !== ''
            && reg.test($('#form-item-tags').val())
        ) {
            // Tags not wel formated
            alertify
                .error('<i class="fas fa-ban fa-lg mr-3"></i><?php echo langHdl('error_tags'); ?>', 10)
                .dismissOthers();
            return false;
        } else if ($('#form-item-folder option:selected').val() === ''
            || typeof  $('#form-item-folder option:selected').val() === 'undefined'
        ) {
            // No folder selected
            alertify
                .error('<i class="fas fa-ban fa-lg mr-3"></i><?php echo langHdl('error_no_selected_folder'); ?>', 10)
                .dismissOthers();
            return false;
        } else if (store.get('teampassApplication').personalSaltkeyRequired === 1
            && store.get('teampassUser').pskDefinedInDatabase !== 1
        ) {
            // No folder selected
            alertify
                .error('<i class="fas fa-ban fa-lg mr-3"></i><?php echo langHdl('error_personal_saltkey_is_not_set'); ?>', 10)
                .dismissOthers();
            return false;
        } else {
            // Continue preparation of saving query
            
            //Manage restriction
            var restriction = new Array(),
                restrictionRole = new Array(),
                userInRestrictionList = false;
            $('#form-item-restrictedto option:selected').each(function () {
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
            $('#form-item-anounce option:selected').each(function () {
                diffusion.push($(this).val());
            });

            // Get item field values
            // Ensure that mandatory ones are filled in too
            var fields = [];
            var errorExit = false;
            $('.form-item-field-custom').each(function(key, data){
                fields.push({
                    'id' : $(this).data('field-name'),
                    'value' : $(this).val(),
                });
                // Mandatory?
                if ($(this).data('field-mandatory') === 1
                    && $(this).val() === ''
                    && $(this).is('visible') === true
                ) {
                    console.log($(this))
                    errorExit = true;
                    return false;
                }
            });
            if (errorExit === true) {
                alertify
                    .error('<i class="fas fa-ban fa-lg mr-3"></i><?php echo langHdl('error_field_is_mandatory'); ?>', 5)
                    .dismissOthers();
                return false;
            }
            
            //prepare data
            var data = {
                'anyone_can_modify': $('#form-item-anyoneCanModify').is(':checked') ? 1 : 0,
                'complexity_level': parseInt($('#form-item-password-complex').val()),
                'description': itemEditor.getData(),
                'diffusion_list' : diffusion,
                'folder': parseInt($('#form-item-folder').val()),
                'email': $('#form-item-email').val(),
                'fields': fields,
                'folder_is_personal': (store.get('teampassApplication').personalSaltkeyRequired === 1
                    && store.get('teampassUser').pskDefinedInDatabase === 1) ? 1 : 0,
                'id'   : store.get('teampassItem').id,
                'label': $('#form-item-label').val(),
                'login': $('#form-item-login').val(),
                'pw': $('#form-item-password').val(),
                'restricted_to': restriction,
                'restricted_to_roles': restrictionRole,
                'tags': $('#form-item-tags').val(),
                'template_id': parseInt($('input.form-check-input-template:checkbox:checked').data('category-id')),
                'to_be_deleted_after_date': ($('#form-item-deleteAfterDate') !== undefined
                    && $('#form-item-deleteAfterDate').val() !== '') ? $('#form-item-deleteAfterDate').val() : '',
                'to_be_deleted_after_x_views': ($('#form-item-deleteAfterShown') !== undefined
                    && $('#form-item-deleteAfterShown').val() !== '' && $('#form-item-deleteAfterShown').val() >= 1) ?
                    parseInt($('#form-item-deleteAfterShown').val()) : '',
                'url': $('#form-item-url').val(),
                'user_id' : parseInt('<?php echo $_SESSION['user_id']; ?>'),
                'uploaded_file_id' : store.get('teampassApplication').uploadedFileId,
            };
console.log('SAVING DATA');
console.log(data);
            // Inform user
            alertify
                .message('<?php echo langHdl('opening_folder'); ?><i class="fas fa-cog fa-spin ml-2"></i>', 0)
                .dismissOthers();

            // CLear tempo var
            store.update(
                'teampassApplication',
                function(teampassApplication)
                {
                    teampassApplication.uploadedFileId = '';
                }
            );
                
            //Send query
            $.post(
                "sources/items.queries.php",
                {
                    type    : $('#form-item-button-save').data('action'),
                    data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                    key     : "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    //decrypt data
                    try {
                        data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
                    } catch (e) {
                        // error
                        $("#div_loading").addClass("hidden");
                        requestRunning = false;
                        $("#div_dialog_message_text").html("An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />"+data);
                        $("#div_dialog_message").dialog("open");

                        alertify
                            .error('<i class="fas fa-ban fa-lg mr-3"></i>An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />' + data, 0)
                            .dismissOthers();
                        return false;
                    }
console.log('RETURNED DATA');
console.log(data)
                    if (data.error === true) {
                        alertify
                            .error('<i class="fas fa-warning fa-lg mr-2"></i>' + data.message, 0)
                            .dismissOthers();
                        return false;
                    } else {
                        // Refresh tree
                        if ($('#form-item-button-save').data('action') === 'update_item') {
                            if ($('#form-item-folder').val() !== ''
                                && originalFolderId !== $('#form-item-folder').val()
                            ) {
                                refreshTree($('#form-item-folder').val(), false);
                            }
                        } else {
                            refreshTree($('#form-item-folder').val(), true);
                        }

                        // Refresh list of items inside the folder
                        ListerItems($('#form-item-folder').val(), '', 0);

                        // Inform user
                        alertify
                            .success('<?php echo langHdl('success'); ?>', 1)
                            .dismissOthers();

                        // Close
                        userDidAChange = false;
                        userUploadedFile = false;
                        
                        closeItemDetailsCard();
                    }
                }
            );
        }
    } else if (userUploadedFile === true) {
        // Inform user
        alertify
            .success('<?php echo langHdl('success'); ?>', 1)
            .dismissOthers();

        // Close
        userUploadedFile = false;
        closeItemDetailsCard();
    } else {
        console.info('NOTHING TO SAVE');
        alertify
            .warning('<?php echo langHdl('nothing_to_save'); ?>', 2)
            .dismissOthers();
    }
});
//->


//autocomplete for TAGS
$("#form-item-tags")
    //.focus()
    .bind( "keydown", function( event ) {
        if ( event.keyCode === $.ui.keyCode.TAB &&
                $(this).data("autocomplete").menu.active ) {
            event.preventDefault();
        }
    })
    .autocomplete({
        source: function(request, response) {
            $.getJSON( "sources/items.queries.php?type=autocomplete_tags&t=1", {
                term: extractLast( request.term )
            }, response );
        },
        focus: function() {
            // prevent value inserted on focus
            return false;
        },
        search: function() {
            var term = extractLast( this.value );
        },
        select: function( event, ui ) {
            var terms = split( this.value );
            // remove the current input
            terms.pop();
            // add the selected item
            terms.push( ui.item.value );
            // add placeholder to get the comma-and-space at the end
            terms.push( "" );
            this.value = terms.join( " " );

            return false;
        }
    }
);


function showItemEditForm(selectedFolderId)
{
    console.info('SHOW EDIT ITEM ' + selectedFolderId);
        
    $.when(
        getPrivilegesOnItem(selectedFolderId, 0)
    ).then(function() {
        // Now read
        if (store.get('teampassItem').error !== '') {
            alertify
                .error('<i class="fas fa-ban mr-2"></i>' + store.get('teampassItem').message, 3)
                .dismissOthers();
        } else {
            $('#card-item-visibility').html(store.get('teampassItem').itemVisibility);
            $('#card-item-minimum-complexity').html(store.get('teampassItem').itemMinimumComplexity);
            // Show edition form
            $('.form-item, #form-item-attachments-zone')
                .removeClass('hidden');
            $('.item-details-card, .form-item-copy, #form-item-password-options, .form-item-action')
                .addClass('hidden');
            userDidAChange = false;
            // Force update of simplepassmeter
            $('#form-item-password').focus();
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
            // ---
        }
    });
}


/**
 * Start items search
 */
function searchItems(criteria)
{
    if (criteria !== '') {
        // stop items loading (if on-going)
        store.update(
            'teampassApplication',
            function(teampassApplication)
            {
                teampassApplication.itemsListStop = 1;
            }
        );

        // wait
        alertify
            .message('<?php echo langHdl('searching'); ?>', 0);

        // clean
        $('#id_label, #id_desc, #id_pw, #id_login, #id_email, #id_url, #id_files, #id_restricted_to ,#id_tags, #id_kbs, .fields_div, .fields, #item_extra_info').html('');
        $('#button_quick_login_copy, #button_quick_pw_copy').addClass('hidden');
        $('#teampass_items_list').html('');

console.log(store.get('teampassApplication'))
        // send query
        $.get(
            'sources/find.queries.php',
            {
                type    : 'search_for_items',
                limited : $('#limited-search').is(":checked") === true ? store.get('teampassApplication').selectedFolder : false,
                search  : criteria,
                key     : '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                var pwd_error = '',
                    icon_login,
                    icon_pwd,
                    icon_favorite;

                data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');

                // Ensure correct div is not hidden
                $('#info_teampass_items_list').addClass('hidden');
                $('#table_teampass_items_list').removeClass('hidden');

                // Show Items list
                sList(data.html_json);

                alertify
                    .success(data.message, 5)
                    .dismissOthers();

                // Do some post treatments
                $('#form-folder-path').html('');
                $('#find_items').val('');

                adjustElemsSize();
            }
        );
    }
}


/**
 * Undocumented function
 *
 * @return void
 */
function refreshVisibleFolders()
{
    $.post(
        'sources/items.queries.php',
        {
            type : 'refresh_visible_folders',
            key  : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            //decrypt data
            data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

            //check if format error
            if (data.error !== true) {
                // Build html lists
                var html_visible = '',
                    html_full_visible = '',
                    html_active_visible = '',
                    indentation = '',
                    disabled = '';

                refreshFoldersInfo(data.html_json.folders, 'clear');

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
                        value.title + (value.path !=='' ? ' [' + value.path + ']' : '') + '</option>';
                });

                // append new list
                $('#form-item-folder, #form-item-copy-destination, #form-folder-add-parent,' +
                    '#form-folder-delete-selection, #form-folder-copy-source, #form-folder-copy-destination')
                    .find('option')
                    .remove()
                    .end()
                    .append(html_visible);
                $(".no-root option[value='0']").remove();

                // Store in teampassUser
                store.update(
                    'teampassUser',
                    function (teampassUser) {
                        teampassUser.folders = html_visible;
                    }
                );
                

                // remove ROOT option if exists
                $('#form-item-copy-destination option[value="0"]').remove();
            } else {
                alertify
                    .error('<i class="fas fa-ban fa-lg mr-3"></i>' + data.message, 0)
                    .dismissOthers();
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
function refreshFoldersInfo(folders, action)
{
    var action = action || '',
        sending = '';

    if (action === 'clear') {
        sending = JSON.stringify(folders.map(a => a.id));console.log(sending)
    } else if (action === 'update') {
        sending = JSON.stringify([folders]);console.log(sending)
    }
    // 
    $.post(
        'sources/items.queries.php',
        {
            type : 'refresh_folders_other_info',
            data : sending,
            key  : '<?php echo $_SESSION['key']; ?>'
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
                        function (teampassApplication)
                        {
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
                                function (teampassApplication) {
                                    foldersList = currentFoldersList;
                                }
                            );
                            return true;
                        }
                    });

                }
            } else {
                alertify
                    .error('<i class="fas fa-ban fa-lg mr-3"></i>' + data.message, 0)
                    .dismissOthers();
                return false;
            }
        }
    );
}


/*
* builds the folders tree
*/
function refreshTree(node_to_select, do_refresh, refresh_visible_folders)
{
    do_refresh = do_refresh || ''
    node_to_select = node_to_select || '';
    refresh_visible_folders = refresh_visible_folders || true;

    if (refresh_visible_folders !== true) {
        $('#jstree').jstree('deselect_all');
        $('#jstree').jstree('select_node', '#li_'+groupe_id);
        return false;
    }

    if (do_refresh === true) {
        $('#jstree').jstree(true).refresh();
    }

    if (node_to_select !== '') {
        $('#jstree').jstree('deselect_all');

        $('#jstree')
        .one('refresh.jstree', function(e, data) {
            data.instance.select_node('#li_'+node_to_select);
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
function ListerItems(groupe_id, restricted, start, stop_listing_current_folder)
{
    var me = $(this);
    stop_listing_current_folder = stop_listing_current_folder || '0';
    console.log('LIST OF ITEMS FOR FOLDER '+groupe_id)

    // case where we should stop listing the items
    if (store.get('teampassApplication') !== undefined && store.get('teampassApplication') .itemsListStop === 1) {
        requestRunning = false;
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
        function(teampassApplication)
        {
            teampassApplication.itemsShownByQuery = Math.max(Math.round((screenHeight-450)/23),2);
        }
    );

    if (stop_listing_current_folder === 1) {
        me.data('requestRunning', false);
        // Store listing criteria
        store.update(
            'teampassApplication',
            function (teampassApplication)
            {
                teampassApplication.itemsListFolderId = groupe_id,
                teampassApplication.itemsListRestricted = restricted,
                teampassApplication.itemsListStart = start,
                teampassApplication.itemsListStop = 0,
                teampassApplication.lastItemSeen = ''
            }
        );
    } else {
        store.update(
            'teampassApplication',
            function(teampassApplication) {
                teampassApplication.itemsListStop = 0,
                teampassApplication.lastItemSeen = ''
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
            request.abort();    //kill previous query if needed
        }
        query_in_progress = groupe_id;
        if (start == 0) {
            //clean form
            $('#teampass_items_list, #items_folder_path').html('');
        }

        store.update(
            'teampassApplication',
            function(teampassApplication)
            {
                teampassApplication.selectedFolder = groupe_id
            }
        );

        if ($('.tr_fields') !== undefined) {
            $('.tr_fields, .newItemCat, .editItemCat').addClass('hidden');
        }

        // Inform user
        alertify
            .message('<?php echo langHdl('opening_folder'); ?>&nbsp;<i class="fas fa-cog fa-spin"></i>', 0)
            .dismissOthers();
            
        //ajax query
        var request = $.post('sources/items.queries.php',
            {
                type                     : 'do_items_list_in_folder',
                id                       : store.get('teampassApplication').selectedFolder,
                restricted               : restricted,
                start                    : start,
                uniqueLoadData           : store.get('teampassApplication').queryUniqueLoad,
                key                      : '<?php echo $_SESSION['key']; ?>',
                nb_items_to_display_once : store.get('teampassApplication').itemsShownByQuery
            },
            function(retData) {

                if (retData == 'Hacking attempt...') {
                    alert('Hacking attempt...');
                    return false;
                }
                //get data
                data = decodeQueryReturn(retData, '<?php echo $_SESSION['key']; ?>');

                console.log('LIST ITEMS');
                console.log(data);

                // reset doubleclick prevention
                requestRunning = false;

                // manage not allowed
                if (data.error === 'not_allowed') {
                    alertify
                        .error('<i class="fas fa-warning fa-lg mr-2"></i>' + data.error_text, 0)
                        .dismissOthers();
                   return false;
                }

                // Hide New button if restricted folder
                if (data.access_level === 1) {
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
                        function (teampassItem)
                        {
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
                    if ($('#jstree').jstree('get_selected', true)[0] !== undefined
                        && 'li_' + groupe_id !== $('#jstree').jstree('get_selected', true)[0].id
                    ) {
                        $('#jstree').jstree('deselect_all');
                        $('#jstree').jstree('select_node', '#li_'+groupe_id);
                    }

                    // Delete existing clipboard
                    if (clipboardForPassword) {
                        clipboardForPassword.destroy();
                    }
                    if (clipboardForLogin) {
                        clipboardForLogin.destroy();
                    }

                    // Prepare clipboard items
                    clipboardForLogin = new Clipboard('.fa-clickable-login');
                    clipboardForLogin.on('success', function(e) {
                        showAlertify(
                            '<?php echo langHdl('copy_to_clipboard'); ?>',
                            1,
                            'top-right',
                            'message'
                        );
                        e.clearSelection();
                    });

                    // Prepare clipboard for PAssword
                    // This will request a query to server to get the pwd
                    clipboardForPassword = new Clipboard('.fa-clickable-password', {
                        text: function(trigger) {
                            // Send query and get password
                            var result = '',
                                error = false;
                            
                            if (store.get('teampassUser').pskSetForSession === '') {
                                // ERROR
                                alertify
                                    .error(
                                        '<i class="fa fa-warning fa-lg mr-2"></i><?php echo langHdl('empty_psk'); ?>',
                                        3
                                    )
                                    .dismissOthers();
                                return;
                            }

                            // Notify user to wait
                            alertify.set('notifier','position', 'top-right');
                            alertify
                                .message('<i class="fas fa-cog fa-spin fa-2x"></i>', 0)
                                .dismissOthers();

                            $.ajax({
                                type: "POST",
                                async: false,
                                url: 'sources/items.queries.php',
                                data: 'type=show_item_password&item_id=' + trigger.getAttribute('data-item-id') +
                                    //'&psk=' + store.get('teampassUser').pskSetForSession +
                                    '&key=<?php echo $_SESSION['key']; ?>',
                                dataType: "",
                                success: function (data) {
                                    //decrypt data
                                    try {
                                        data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
                                    } catch (e) {
                                        // error
                                        alertify.alert()
                                            .setting({
                                                'label' : '<?php echo langHdl('ok'); ?>',
                                                'message' : '<i class="fas fa-info-circle text-error mr-2"></i><?php echo langHdl('no_item_to_display'); ?>'
                                            })
                                            .show(); 
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
                        
                        showAlertify(
                            '<?php echo langHdl('copy_to_clipboard'); ?>',
                            2,
                            'top-right',
                            'message'
                        );
                        e.clearSelection();
                    });
                } else if (data.error === 'not_authorized') {
                    $('#items_folder_path').html('<i class="fas fa-folder-open-o"></i>&nbsp;'+rebuildPath(data.arborescence));
                } else {
                    // Store query results
                    store.update(
                        'teampassApplication',
                        function(teampassApplication)
                        {
                            teampassApplication.queryUniqueLoad = data.uniqueLoadData;
                        }
                    );
                    if ($('#items_loading_progress').length == 0) {
                        $('#items_list_loader').after('<span id="items_loading_progress">' + Math.round(data.next_start*100/data.counter_full, 0) + '%</span>');
                    } else {
                        $('#items_loading_progress').html(Math.round(data.next_start*100/data.counter_full, 0) + '%');
                    }
                }
                //-----
                if (data.array_items !== undefined
                    && data.array_items.length === 0
                    && $('#teampass_items_list').html() === ''
                ) {
                    // Show warning to user
                    $('#info_teampass_items_list')
                        .html('<div class="alert alert-primary text-center col col-lg-10" role="alert">' +
                            '<i class="fas fa-info-circle mr-2"></i><?php echo langHdl('no_item_to_display'); ?></b>' +
                            '</div>')
                        .removeClass('hidden');
                }

                if (data.error === 'is_pf_but_no_saltkey') {
                    //warn user about his saltkey
                    alertify
                        .warning('<i class="fas fa-warning mr-2"></i><?php echo langHdl('home_personal_saltkey_label'); ?>')
                        .dismissOthers();

                    return false;
                } else if (data.error === 'not_authorized' || data.access_level === '') {
                    // Show warning to user
                    $('#info_teampass_items_list')
                        .html('<div class="alert alert-primary text-center col col-lg-10" role="alert">' +
                            '<i class="fas fa-warning mr-2"></i><?php echo langHdl('not_allowed_to_see_pw'); ?></b>' +
                            '</div>')
                        .removeClass('hidden');

                } else if ((store.get('teampassApplication').userIsReadObly === 1) //&& data.folder_requests_psk == 0
                    || data.access_level == 1
                ) {
                    //readonly user
                    $('#item_details_no_personal_saltkey, #item_details_nok').addClass('hidden');
                    $('#item_details_ok, #items_list').removeClass('hidden');
                    //$('#more_items').remove();

                    store.update(
                        'teampassApplication',
                        function (teampassApplication) {
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
                            function (teampassApplication) {
                                teampassApplication.itemsListStart = parseInt(data.next_start);
                            }
                        );
                    } else {
                        store.update(
                            'teampassApplication',
                            function (teampassApplication) {
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
                        function (teampassApplication) {
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
                            function (teampassApplication) {
                                teampassApplication.itemsListStart = parseInt(data.next_start);
                            }
                        );
                    } else {
                        store.update(
                            'teampassApplication',
                            function (teampassApplication) {
                                teampassApplication.itemsListStart = data.list_to_be_continued;
                            }
                        );
                        $('.card-item-category').addClass('hidden');
                    }

                    proceed_list_update(stop_listing_current_folder);
                }
            }
        );
    }
}

function sList(data)
{
    console.log(data);
    var counter = 0;
    $.each(data, function(i, value) {
        var new_line = '',
            pwd_error = '',
            icon_all_can_modify = '',
            icon_cannot_see = '',
            icon_login = '',
            icon_pwd = '',
            icon_favorite = '',
            item_flag = '',
            item_grippy = '',
            visible_by_user = '';

        counter += 1;

        // Check access restriction
        if (value.rights > 0) {
            // Prepare anyone can modify icon
            if (value.anyone_can_modify === 1 || value.open_edit === 1) {
                icon_all_can_modify = '<span class="fa-stack fa-clickable pointer infotip list-item-clicktoedit mr-2" title="<?php echo langHdl('edit'); ?>"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-pen fa-stack-1x fa-inverse"></i></span>';
            }
            
            // Prepare mini icons
            if (store.get('teampassSettings') !== undefined && parseInt(store.get('teampassSettings').copy_to_clipboard_small_icons) === 1
                && value.rights > 10
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

            // Prepare Favorite icon
            if (parseInt(store.get('teampassSettings').enable_favourites) === 1
                && value.rights > 10
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

            // Prepare flag
            if (value.expired === 1) {
                item_flag = '<i class="fas fa-ban fa-sm"></i>&nbsp;';
            }
            
            $('#teampass_items_list').append(
                '<tr class="list-item-row' + (value.canMove === 1 ? ' is-draggable' : '') +'" id="list-item-row_'+value.item_id+'" data-item-edition="' + value.open_edit + '" data-item-id="'+value.item_id+'" data-item-sk="'+value.sk+'" data-item-expired="'+value.expired+'" data-item-rights="'+value.rights+'" data-item-display="'+value.display+'" data-item-open-edit="'+value.open_edit+'" data-item-tree-id="'+value.tree_id+'" data-is-search-result="'+value.is_result_of_search+'">' +
                    '<td class="list-item-description" style="width: 100%;">' +
                    // Show user a grippy bar to move item
                    (value.canMove === 1 && value.is_result_of_search === 0 ? '<i class="fas fa-ellipsis-v mr-2 dragndrop"></i>' : '') +
                    // Show user that Item is not accessible
                    (value.rights === 10 ? '<i class="far fa-eye-slash fa-xs mr-2 text-primary infotip" title="<?php echo langHdl('item_with_restricted_access'); ?>"></i>' : '') +
                    // Show user that password is badly encrypted
                    (value.pw_status === 'encryption_error' ? '<i class="fas fa-exclamation-triangle  fa-xs text-danger infotip mr-1" title="<?php echo langHdl('pw_encryption_error'); ?>"></i>' : '') +
                    '<span class="list-item-clicktoshow' + (value.rights === 10 ? '' : ' pointer') + '" data-item-id="' + value.item_id + '">' +
                    '<span class="list-item-row-description' + (value.rights === 10 ? ' font-weight-light' : '') + '">' + value.label + '</span>' + (value.rights === 10 ? '' : value.desc) + '</span>' +
                    '<span class="list-item-actions hidden">' +
                    (value.rights === 10 ?
                    '<span class="fa-stack fa-clickable fa-clickable-access-request pointer infotip mr-2" title="<?php echo langHdl('need_access'); ?>"><i class="fas fa-circle fa-stack-2x text-danger"></i><i class="far fa-handshake fa-stack-1x fa-inverse"></i></span>' : 
                    pwd_error + icon_all_can_modify + icon_login + icon_pwd + icon_favorite) +
                    '</span>' +
                    '</td>'
                + '</tr>'
            );

//---------------------
        }
    });
    
    // Sort entries
    var $tbody = $('#teampass_items_list');
    $tbody.find('tr').sort(function (a, b) {
        var tda = $(a).find('.list-item-row-description').text();
        var tdb = $(b).find('.list-item-row-description').text();
        // if a < b return 1
        return tda > tdb ? 1
            : tda < tdb ? -1   
            : 0;
    }).appendTo($tbody);

    // Trick for list with only one entry
    if (counter === 1) {
        $('#teampass_items_list')
            .append('<tr class="row"><td class="">&nbsp;</td></tr>');
    }
    adjustElemsSize();    
}


function adjustElemsSize()
{
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
function rebuildPath(data)
{
    var new_path = new_path_elem = '';
    $.each((data), function(i, value) {
        new_path_elem = '';
        if (value['visible'] === 1) {
            new_path_elem = ' class="pointer" onclick="ListerItems('+value['id']+', \'\', 0)"';
        }

        new_path += '<li class="breadcrumb-item" id="path_elem_'+value['id']+'"'+new_path_elem+'>'+value['title']+'</li>';
    });

    return new_path;
}

/**

 */
function proceed_list_update(stop_proceeding)
{
    stop_proceeding = stop_proceeding || '';
        
    if (stop_proceeding === '1'
        || (store.get('teampassApplication').itemsListFolderId !== ''
        && store.get('teampassApplication').itemsListStart !== 'end')
    ) {
        // Clear storage
        store.update(
            'teampassApplication',
            function(teampassApplication)
            {
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

        alertify
            .success('<?php echo langHdl('success'); ?>', 1)
            .dismissOthers();


        // Prepare items dragable on folders
        $('.is-draggable').draggable({
            cursor: 'move',
            cursorAt: { top: -5, left: -5 },
            opacity: 0.8,
            appendTo: 'body',
            stop: function(event, ui) {
                $(this).removeClass('bg-warning');
            },
            start: function(event, ui) {
                $(this).addClass('bg-warning');
            },
            helper: function(event) {
                return $('<div class="bg-gray p-2 font-weight-light">'+$(this).find('.list-item-row-description').text()+'</div>');
            }
        });
        $('.folder').droppable({
            hoverClass: 'bg-warning',
            tolerance: 'pointer',
            drop: function(event, ui) {
                alertify
                    .message('<i class="fas fa-cog fa-spin fa-2x"></i>', 0)
                    .dismissOthers();

                // Hide helper
                ui.draggable.addClass('hidden');
                
                //move item
                $.post(
                    'sources/items.queries.php',
                    {
                        type      : 'move_item',
                        item_id   : ui.draggable.data('item-id'),
                        folder_id : $(this).attr('id').substring(4),
                        key       : '<?php echo $_SESSION['key']; ?>'
                    },
                    function(data) {
                        //decrypt data
                        data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
                        
                        if (data.error !== '') {
                            alertify
                                .error('<i class="fas fa-ban mr-2"></i>' + data.error, 3)
                                .dismissOthers();
                            ui.draggable.removeClass('hidden');
                            return false;
                        }

                        //increment / decrement number of items in folders
                        $('#itcount_'+data.from_folder).text(Math.floor($('#itcount_'+data.from_folder).text())-1);
                        $('#itcount_'+data.to_folder).text(Math.floor($('#itcount_'+data.to_folder).text())+1);

                        alertify
                            .success('<?php echo langHdl('success'); ?>', 1)
                            .dismissOthers();
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
                console.log(data);
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
function Details(itemDefinition, actionType, hotlink = false)
{
    console.info('EXPECTED ACTION '+actionType+' -- ')

    // Store current view
    savePreviousView();

    store.each(function(value, key) {
        console.log(key, '==', value)
    })
    console.log("Request is running: "+requestRunning)
    // If a request is already launched, then kill new.
    /*if (requestRunning === true) {
        console.log('ABORT')
        //request.abort();
        return false;
    }*/
    
    // Store status query running
    requestRunning = true;
    
    // Init
    if (hotlink === false) {
        var itemId          = parseInt($(itemDefinition).data('item-id')) || '';
        var itemTreeId      = parseInt($(itemDefinition).data('item-tree-id')) || '';
        var itemSk          = parseInt($(itemDefinition).data('item-sk')) || 0;
        var itemExpired     = parseInt($(itemDefinition).data('item-expired')) || '';
        var itemRestricted  = parseInt($(itemDefinition).data('item-restricted-id')) || '';
        var itemDisplay     = parseInt($(itemDefinition).data('item-display')) || 0;
        var itemOpenEdit    = parseInt($(itemDefinition).data('item-open-edit')) || 0;
        var itemReload      = parseInt($(itemDefinition).data('item-reload')) || 0;
    } else {
        var itemId          = itemDefinition || '';
        var itemTreeId      = store.get('teampassApplication').selectedFolder || '';
        var itemSk          = 0;
        var itemExpired     = '';
        var itemRestricted  = '';
        var itemDisplay     = 1;
        var itemOpenEdit    = 0;
        var itemReload      = 0;
    }
    userDidAChange      = false;

    

    // Select tab#1
    $('#form-item-nav-pills li:first-child a').tab('show');

    // Don't show details
    if (itemDisplay === 'no_display') {
        // Inform user
        alertify.alert()
            .setting({
                'label' : '<?php echo langHdl('ok'); ?>',
                'message' : '<i class="fas fa-info-circle text-error"></i>&nbsp;<?php echo langHdl('no_item_to_display'); ?>'
            })
            .show(); 

        // Clear ongoing request status
        requestRunning = false;

        // Finished
        return false;
    }

    // If opening new item, reinit hidden fields
    if (store.get('teampassApplication').lastItemSeen !== itemId) {
        store.update(
            'teampassApplication',
            function (teampassApplication) {
                teampassApplication.lastItemSeen = parseInt(itemId);
            }
        );
    }
    
    // Check if personal SK is needed and set
    if ((store.get('teampassApplication').personalSaltkeyRequired === 1
        && store.get('teampassUser').pskDefinedInDatabase !== 1)
        && itemSk === 1
    ) {
        $('#set_personal_saltkey_warning').html('<div style="font-size:16px;"><span class="fas fa-warning fa-lg"></span>&nbsp;</span><?php echo langHdl('alert_message_personal_sk_missing'); ?></div>').show(1).delay(2500).fadeOut(1000);
        $('#div_set_personal_saltkey').dialog('open');

        showPersonalSKDialog();

        // Clear ongoing request status
        requestRunning = false;

        // Finished
        return false;
    } else if ((store.get('teampassApplication').personalSaltkeyRequired === 0 || store.get('teampassApplication').personalSaltkeyRequired === undefined)
        || (store.get('teampassApplication').personalSaltkeyRequired === 1 && store.get('teampassUser').pskDefinedInDatabase === 1)
    ) {
        // Clear
        $('#card-item-history')
            .html('<div class="overlay"><i class="fa fa-refresh fa-spin"></i></div>');
        

        // Prepare data to be sent
        var data = {
            'id'                    : itemId,
            'folder_id'             : itemTreeId,
            'salt_key_required'     : itemSk,
            'expired_item'          : itemExpired,
            'restricted'            : itemRestricted,
            'folder_access_level'   : store.get('teampassItem').hasAccessLevel,
            'page'                  : 'items'
        };

        console.log("SEND");
        console.log(data);

        //Send query
        $.post(
            'sources/items.queries.php',
            {
                type : 'show_details_item',
                data : prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                key  : '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
                console.log("RECEIVED");
                console.log(data);
                
                if (data.error !== '') {
                    alertify
                        .error('<i class="fas fa-ban mr-2"></i>' + data.error, 3)
                        .dismissOthers();
                    return false;
                } else if (data.user_can_modify === 0 && actionType === 'edit') {
                    alertify
                        .error('<i class="fas fa-ban mr-2"></i><?php echo langHdl('not_allowed_to_see_pw'); ?>', 3)
                        .dismissOthers();
                    return false;
                }

                alertify
                    .success('<?php echo langHdl('success'); ?>', 1)
                    .dismissOthers();
                
                // Store scroll position
                userScrollPosition = $(window).scrollTop();

                // Scroll to top
                $(window).scrollTop(0);

                // SHould we show?
                if (data.show_detail_option === '1') {
                    // SHow expiration alert
                    $('#card-item-expired').removeClass('hidden');
                } else if (data.show_detail_option === '2') {
                    // Don't show anything
                    alertify.alert(
                        '<?php echo langHdl('warning'); ?>',
                        '<?php echo langHdl('not_allowed_to_see_pw'); ?>'
                    );

                    return false;
                }

                // Show header info
                $('#card-item-visibility').html(store.get('teampassItem').itemVisibility);
                $('#card-item-minimum-complexity').html(store.get('teampassItem').itemMinimumComplexity);
                
                // Uncrypt the pwd
                data.pw = atob(data.pw);

                // Update hidden variables
                store.update(
                    'teampassItem',
                    function (teampassItem)
                    {
                        teampassItem.id = parseInt(data.id),
                        teampassItem.timestamp = data.timestamp,
                        teampassItem.user_can_modify = data.user_can_modify,
                        teampassItem.anyone_can_modify = data.anyone_can_modify,
                        teampassItem.edit_item_salt_key = data.edit_item_salt_key,
                        teampassItem.id_restricted_to = data.id_restricted_to,
                        teampassItem.id_restricted_to_roles = data.id_restricted_to_roles
                    }
                );
                
                // Prepare forms
                $('#folders-tree-card').addClass('hidden');
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
                $('#form-item-password, #form-item-password-confirmation').val(data.pw);
                $('#card-item-login').html(data.login);
                $('#form-item-login, #form-item-suggestion-login').val(data.login);
                
                $('#card-item-email').text(data.email);
                $('#form-item-email, #form-item-suggestion-email').val(data.email);
                $('#card-item-url').html(data.url);
                $('#form-item-url, #form-item-suggestion-url').val(data.url);
                $('#form-item-restrictedToUsers').val(JSON.stringify(data.id_restricted_to));
                $('#form-item-restrictedToRoles').val(JSON.stringify(data.id_restricted_to_roles));
                $('#form-item-folder').val(data.folder);
                $('#form-item-tags').val(data.tags.join(' '));
                
                $('#form-item-password').focus();
                $('#form-item-label').focus();

                // Editor for description field
                ClassicEditor
                    .create(
                        document.querySelector('#form-item-description'), {
                            toolbar: [ 'heading', 'bold', 'italic', 'bulletedList', 'numberedList', 'blockQuote', 'link' , 'undo', 'redo'  ]
                        }
                    )
                    .then( editor => {
                        editor.setData(data.description);
                        $('#form-item-description').val(editor.getData());

                        // On change
                        editor.model.document.on('change', () => {
                            if (userDidAChange === false) {
                                userDidAChange = true;
                                $('#form-item-description').data('change-ongoing', true);

                                // SHow button in sticky footer
                                //$('#form-item-buttons').addClass('sticky-footer');
                            }
                        });

                        // Define variable name
                        itemEditor = editor;
                    } )
                    .catch( error => {
                        console.log( error );
                    });
                
                ClassicEditor
                    .create(
                        document.querySelector('#form-item-suggestion-description'), {
                            toolbar: [ 'heading', 'bold', 'italic', 'bulletedList', 'numberedList', 'blockQuote', 'link' , 'undo', 'redo'  ]
                        }
                    )
                    .then( editor => {
                        editor.setData(data.description);
                        $('#form-item-suggestion-description').val(editor.getData());
                        itemEditorSuggestion = editor;
                    } )
                    .catch( error => {
                        console.log( error );
                    });

                //prepare nice list of users / groups
                var html_users = '',
                    html_groups = '',
                    html_tags = '',
                    html_kbs = '';                   

                $(data.tags).each(function(index, value){
                    html_tags += '<span class="badge badge-success pointer tip mr-2" title="<?php echo langHdl('list_items_with_tag'); ?>" onclick="searchItemsWithTags(\"'+value+'\")"><i class="fas fa-tag fa-sm"></i>&nbsp;<span class="item_tag">'+value+'</span></span>';
                });
                if (html_tags === '') {
                    $('#card-item-tags').html('<?php echo langHdl('none'); ?>');
                } else {
                    $('#card-item-tags').html(html_tags);
                } 

                $(data.links_to_kbs).each(function(index, value){
                    html_kbs += '<a class="badge badge-primary pointer tip mr-2" href="<?php echo $SETTINGS['cpassman_url']; ?>/index.php?page=kb&id='+value['id']+'"><i class="fas fa-map-pin fa-sm"></i>&nbsp;'+value['label']+'</a>';

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
                                $('#card-item-field-'+field.id)
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
                            $('#template_' + data.template_id).attr('checked', true);

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
                            if (data.categories.length === 1) {
                                $('#item-details-card-categories').addClass('hidden');
                            } else {
                                $('#item-details-card-categories').removeClass('hidden');
                            }
                        }
                    }
                }

                
                // Waiting
                $('#card-item-attachments').html("<?php echo langHdl('please_wait'); ?>");

                // Manage clipboard button
                if (itemClipboard) itemClipboard.destroy();
                itemClipboard = new Clipboard('.btn-copy-clipboard-clear', {
                        text: function(e) {
                            //console.log($(e).data('clipboard-target'))
                            return ($($(e).data('clipboard-target')).val());
                        }
                    })
                    .on('success', function(e) {
                        showAlertify(
                            '<?php echo langHdl('copy_to_clipboard'); ?>',
                            1,
                            'top-right',
                            'message'
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
                    new Clipboard('#card-item-pwd-button', {
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

                        showAlertify(
                            '<?php echo langHdl('copy_to_clipboard'); ?>',
                            1,
                            'top-right',
                            'message'
                        );

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
                        .append('<span class="fas fa-shield infotip mr-4" title="<?php
                            echo langHdl('auto_update_enabled');
                        ?>&nbsp;' + data.auto_update_pwd_frequency + '"></span>');
                }

                // Prepare counter
                $('#card-item-misc')
                    .append('<span class="icon-badge mr-5"><span class="far fa-eye infotip" title="<?php
                        echo langHdl('viewed_number');
                    ?>"></span><span class="badge badge-info icon-badge-text icon-badge-far">' + data.viewed_no + '</span></span>');

                //Anyone can modify button
                if (data.anyone_can_modify === '1') {
                    console.log($('#form-item-anyoneCanModify'))
                    $('#form-item-anyoneCanModify').iCheck('check');
                } else {
                    $('#form-item-anyoneCanModify').iCheck('uncheck');
                }

                // Delete after X views
                if (data.to_be_deleted !== '') {
                    if (data.to_be_deleted_type === '1') {
                        $('#form-item-deleteAfterShown').val(data.to_be_deleted);
                        $('#form-item-deleteAfterDate').val('');
                    } else {
                        $('#form-item-deleteAfterShown').val('');
                        $('#form-item-deleteAfterDate').val(data.to_be_deleted);
                    }
                    // Show icon
                    $('#card-item-misc')
                        .append('<span class="icon-badge mr-6"><span class="far fa-trash-alt infotip" title="<?php
                            echo langHdl('automatic_deletion_engaged');
                        ?>"></span><span class="badge badge-danger icon-badge-text">' + data.to_be_deleted + '</span></span>');
                }

                // Show Notification engaged
                if (data.notification_status === true) {
                    $('#card-item-misc')
                        .append('<span class="ml-4 icon-badge" id="card-item-misc-notification"><span class="far fa-bell infotip text-success" title="<?php
                            echo langHdl('notification_engaged');
                        ?>"></span></span>');
                } else {
                    $('#card-item-misc')
                        .append('<span class="ml-4 icon-badge" id="card-item-misc-notification"><span class="far fa-bell-slash infotip text-warning" title="<?php
                            echo langHdl('notification_not_engaged');
                        ?>"></span></span>');
                }

                // reset password shown info
                $('#pw_shown').val('0');
                
                

                if (data.show_details == '1' && data.show_detail_option != '2') {
                    // continue loading data
                    showDetailsStep2(itemId, actionType);
                } else if (data.show_details === '1' && data.show_detail_option === '2') {
                    $('#item_details_nok').addClass('hidden');
                    $('#item_details_ok').addClass('hidden');
                    $('#item_details_expired_full').show();
                    $('#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item, #menu_button_add_fav, #menu_button_del_fav, #menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link').attr('disabled','disabled');
                    $('#div_loading').addClass('hidden');
                } else {
                    //Dont show details
                    $('#item_details_nok').removeClass('hidden');
                    $('#item_details_nok_restriction_list').html('<div style="margin:10px 0 0 20px;"><b><?php echo langHdl('author'); ?>: </b>' + data.author + '<br /><b><?php echo langHdl('restricted_to'); ?>: </b>' + data.restricted_to + '<br /><br /><u><a href="#" onclick="openReasonToAccess()"><?php echo langHdl('request_access_ot_item'); ?></a></u></div>');

                    $('#reason_to_access').remove();
                    $('#item_details_nok')
                        .append('<input type="hidden" id="reason_to_access" value="'+data.id + ',' + data.id_user+'">');

                    // Protect
                    $('#item_details_ok').addClass('hidden');
                    $('#item_details_expired').addClass('hidden');
                    $('#item_details_expired_full').addClass('hidden');
                    $('#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item, #menu_button_add_fav, #menu_button_del_fav, #menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link').attr('disabled','disabled');
                    $('#div_loading').addClass('hidden');
                }
                requestRunning = false;
            }
        );
    }
}


/*
* Loading Item details step 2
*/
function showDetailsStep2(id, actionType)
{
    $('#div_loading').removeClass('hidden');
    $.post(
        'sources/items.queries.php',
        {
        type    : 'showDetailsStep2',
        id      : id
        },
        function(data) {
            //decrypt data
            data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

            console.log('RECEIVED STEP2')
            console.log(data);

            // Attachments
            if (data.attachments.length === 0) {
                $('#card-item-attachments')
                    .html('<?php echo langHdl('no_attachment'); ?>')
                    .closest('.card-default')
                    .addClass('collapsed-card');
            } else {
                var html = '',
                    htmlFull = '',
                    counter = 1;
                $.each(data.attachments, function(i, value) {
                    // Manage new row
                    if (counter === 1) {
                        htmlFull += '<div class="row">';
                        html += '<div class="row">';
                    }

                    html += '<div class="col-6">' +
                        '<div class="callout callout-info">' +
                        '<i class="' + value.icon + ' mr-2 text-info"></i>';

                    if (value.is_image === 1) {
                        html += 
                        '<i class="fas fa-eye infotip preview-image pointer mr-2" ' +
                        'title="<?php echo langHdl('see'); ?>" ' +
                        'data-file-id="' + value.id + '" data-file-title="' + value.title + '"></i>';
                    } else {
                        html += 
                        '<a class="text-secondary infotip mr-2" href="sources/downloadFile.php?name=' + encodeURI(value.filename) + '&key=<?php echo $_SESSION['key']; ?>&key_tmp=' + value.key + '&fileid=' + value.id + '" title="<?php echo langHdl('download'); ?>">' + 
                        '<i class="fas fa-file-download"></i></a>';
                    }

                    html += 
                        '<span class="font-weight-bold mr-3">' + value.filename + '</span>' +
                        '<span class="mr-2 font-weight-light">(' + value.extension + ')</span>' +
                        '<span class="font-italic">' + value.size + '</span>' +
                        '</div></div>';

                    htmlFull += '<div class="col-6 edit-attachment-div"><div class="info-box bg-secondary-gradient">' +  
                        '<span class="info-box-icon bg-info"><i class="' + value.icon + '"></i></span>' +
                        '<div class="info-box-content"><span class="info-box-text">' + value.filename + '</span>' +
                        '<span class="info-box-text"><i class="fas fa-trash pointer delete-file" data-file-id="' + value.id + '"></i></span></div>' +
                        '</div></div>';

                    
                    if (counter === 2) {
                        htmlFull += '</div>';
                        html += '</div>';
                        counter = 1;
                    } else {
                        counter += 1;
                    }
                });
                $('#card-item-attachments').html(html);
                $('#form-item-attachments').html(htmlFull);
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
            

            $('#edit_past_pwds').attr('title', (data.history_of_pwds));//htmlspecialchars_decode 
            $('#edit_past_pwds_div').html((data.history_of_pwds));//htmlspecialchars_decode 

            //$('#id_files').html(data.files_id);
            //$('#hid_files').val(data.files_id);
            //$('#item_edit_list_files').html(data.files_edit);

            //$('#div_last_items').html(htmlspecialchars_decode(data.div_last_items));

            // function calling image lightbox when clicking on link
            $('a.image_dialog').click(function(event) {
                event.preventDefault();
                PreviewImage($(this).attr('href'),$(this).attr('title'));
            });

            /*//Set favourites icon
            if (data.favourite == '1') {
                $('#menu_button_add_fav').attr('disabled','disabled');
                $('#menu_button_del_fav').prop('disabled', false);
            } else {
                $('#menu_button_add_fav').prop('disabled', false);
                $('#menu_button_del_fav').attr('disabled','disabled');
            }*/

            // set indicator if item has change proposal
            if (parseInt(data.has_change_proposal) > 0) {
                $('#item_extra_info').prepend('<i class="fas fa-lightbulb-o fa-sm mi-yellow tip" title="<?php echo langHdl('item_has_change_proposal'); ?>"></i>&nbsp;');
            }


            $('.infotip').tooltip();

            // Now load History
            if (actionType === 'show') {
                $.post(
                    "sources/items.queries.php",
                    {
                        type    : "load_item_history",
                        item_id : store.get('teampassItem').id,
                        key     : "<?php echo $_SESSION['key']; ?>"
                    },
                    function(data) {
                        //decrypt data
                        data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
                        console.info('History:');
                        console.log(data);
                        if (data.error === '') {
                            var html = '';
                            $.each(data.history, function(i, value) {
                                html += '<div class="direct-chat-msg"><div class="direct-chat-info clearfix">' +
                                '<span class="direct-chat-name float-left">' + value.name + '</span>' +
                                '<span class="direct-chat-timestamp float-right">' + value.date + '</span>' +
                                '</div>' +
                                '<img class="direct-chat-img" src="' + value.avatar + '" alt="Message User Image">' +
                                '<div class="direct-chat-text"><span class="text-capitalize">' +
                                (value.action === '' ? '' : (value.action)) + '</span> ' +
                                (value.detail === '' ? '' : (' | ' + value.detail)) + '</div></div>';
                            });
                            // Display
                            $('#card-item-history').html(html);
                        }

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

            $('.select2').select2({
                language: '<?php echo $_SESSION['user_language_code']; ?>'
            });


            $('#form-item-deleteAfterDate').datepicker({
                format: '<?php echo str_replace(array('Y', 'M'), array('yyyy', 'mm'), $SETTINGS['date_format']); ?>',
                language: '<?php echo $_SESSION['user_language_code']; ?>'
            });
         }
     );
};

// When click on Trash attachment icon
$(document).on('click', '.delete-file', function() {
    var thisButton = $(this),
        thisFileId = thisButton.data('file-id');
    
    if (thisFileId !== undefined && thisFileId !== '') {
        // Delete the file
        var data = {
            'file_id' : thisFileId,
        };
        
        $.post(
            'sources/items.queries.php',
            {
                type    : 'delete_attached_file',
                data    :  prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                key     : '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
                console.log(data);

                //check if format error
                if (data.error === true) {
                    // ERROR
                    alertify
                        .error(
                            '<i class="fas fa-warning fa-lg mr-2"></i>' + data.message,
                            3
                        )
                        .dismissOthers();
                } else {
                    // Remove the file in UI
                    thisButton.closest('.edit-attachment-div').remove();

                    // Inform user
                    alertify
                        .success('<?php echo langHdl('done'); ?>', 1)
                        .dismissOthers();
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
    alertify
        .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();
    
    $.post(
        "sources/items.queries.php",
        {
            type    : "image_preview_preparation",
            id      : fileId,
            key     : "<?php echo $_SESSION['key']; ?>"
        },
        function(data) {
            //decrypt data
            data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
            console.log(data);

            $("#card-item-preview").html('<img id="image_files" src="">');
            //Get the HTML Elements
            imageDialog = $("#card-item-preview");
            imageTag = $('#image_files');

            //Set the image src
            if (data.image_secure === '1') {
                imageTag.attr("src", "data:" + data.file_type + ";base64," + data.file_content);
            } else {
                imageTag.attr("src", data.file_path);
            }

            alertify
                .success('<?php echo langHdl('done'); ?>', 1)
                .dismissOthers();

            //When the image has loaded, display the dialog
            var pre = document.createElement('pre');
            pre.style.textAlign = "center";
            $(pre).append($(imageDialog).html());
            alertify
                .confirm(pre)
                .set({
                    labels:{
                        cancel: '<?php echo langHdl('close'); ?>'
                    },
                    closable: false,
                    padding: false,
                    title: data.filename,
                    onclose: function() {
                        // delete file
                        $.post(
                            "sources/main.queries.php",
                            {
                                type    : "file_deletion",
                                filename: data.file_path,
                                key     : "<?php echo $_SESSION['key']; ?>"
                            }
                        );
                    }
                });
        }
    );
};

/**
 */
function itemLog(logCase, itemId, itemLabel)
{
    itemId = itemId || $('#id_item').val();

    var data = {
        "id" : itemId,
        "label" : itemLabel,
        "user_id" : "<?php echo $_SESSION['user_id']; ?>",
        "action" : logCase,
        "login" : "<?php echo $_SESSION['login']; ?>"
    };
    
    $.post(
        "sources/items.logs.php",
        {
            type    : "log_action_on_item",
            data    :  prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
            key     : "<?php echo $_SESSION['key']; ?>"
        }
    );
}

/**
 * Undocumented function
 *
 * @return void
 */
function prepareOneTimeView()
{
    //Send query
    $.post(
        "sources/items.queries.php",
        {
            type    : "generate_OTV_url",
            id      : store.get('teampassItem').id,
            key     : "<?php echo $_SESSION['key']; ?>"
        },
        function(data) {
            //check if format error
            if (data.error == "") {
                $('#card-item-otv').val(data.url);
                // prepare clipboard
                var clipboard = new Clipboard("#card-item-otv-copy-button", {
                    text: function() {
                        return data.url;
                    }
                });
                clipboard.on('success', function(e) {
                    showAlertify(
                        '<?php echo langHdl('copy_to_clipboard'); ?>',
                        1,
                        'top-right',
                        'message'
                    );
                    e.clearSelection();
                });

                alertify
                    .success('<?php echo langHdl('success'); ?>', 0);

            } else {
                $('#card-item-otv').html(data.error);
            }
        },
        "json"
   );
}

/**
 */
function getPrivilegesOnItem(val, edit, context)
{
    context = context || "";    // make context optional

    // Clear memory
    //localStorage.setItem("teampassItem", '');
    console.log('Get privilege for folder '+val)

    return $.post(
        "sources/items.queries.php",
        {
            type    : "get_complixity_level",
            groupe  : val,
            context : context,
            item_id : store.get('teampassItem').id
        },
        function(data) {
            //decrypt data
            data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

            console.info('GET COMPLEXITY LEVEL');
            console.log(data);
            var executionStatus = true;
            
            if (data.error == undefined
                || data.error === ''
            ) {
                // Do some prepartion

                // Prepare list of users where needed
                $('#form-item-restrictedto, #form-item-anounce').empty().val('').change();
                // Users restriction list
                var html_restrictions = '';

                $(data.usersList).each(function(index, value) {
                    // Prepare list for FORM
                    $("#form-item-restrictedto")
                        .append('<option value="' + value.id + '" class="restriction_is_user">' + value.name + '</option>');

                    // Prepare list of emailers
                    $('#form-item-anounce').append('<option value="'+value.email+'">'+value.name+'</option>');

                    // Prepare list for CARD
                    html_restrictions += '<span class="badge badge-info mr-2 mb-1"><i class="fas fa-user fa-sm mr-1"></i>' + value.name + '</span>';
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
                        
                        // Prepare list for CARD
                        html_restrictions += 
                            '<span class="badge badge-info mr-2 mb-1"><i class="fas fa-group fa-sm mr-1"></i>' +
                            value.title + '</span>';
                    });
                    /// Add a group label for Groups
                    $('.restriction_is_role').wrapAll($('<optgroup label="<?php echo langHdl('roles'); ?>">'));
                }

                // Show badges
                if (html_restrictions === '') {
                    $('#card-item-restrictedto').html('<?php echo langHdl('no_special_restriction'); ?>');
                } else {
                    $('#card-item-restrictedto').html(html_restrictions);
                }

                // Prepare Select2
                $('.select2').select2({
                    language: '<?php echo $_SESSION['user_language_code']; ?>'
                });
                
                // Show selected restricted inputs
                $('#form-item-restrictedto')
                    .val(store.get('teampassItem').id_restricted_to.concat(
                        store.get('teampassItem').id_restricted_to_roles.map(i => 'role_' + i))
                    )
                    .change();
            }

            store.update(
                'teampassItem',
                function (teampassItem)
                {
                    teampassItem.folderId = val,
                    teampassItem.error = data.error === undefined ? '' : data.error,
                    teampassItem.message = data.message === undefined ? '' : data.message,
                    teampassItem.folderComplexity = data.val === undefined ? '' : parseInt(data.val),
                    teampassItem.folderIsPersonal = data.personal === undefined ? '' : parseInt(data.personal),
                    teampassItem.itemMinimumComplexity = data.complexity === undefined ? '' : data.complexity,
                    teampassItem.itemVisibility = data.visibility === undefined ? '' : data.visibility
                }
            );
            console.log(store.get('teampassItem'))
        }
    );
}

$('#item-button-password-generate').click(function() {
    $('#form-item-password').focus();

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
        "sources/main.queries.php",
        {
            type        : "generate_password",
            size        : $('#pwd-definition-size').val(),
            lowercase   : $('#pwd-definition-lcl').prop("checked"),
            numerals    : $('#pwd-definition-numeric').prop("checked"),
            capitalize  : $('#pwd-definition-ucl').prop("checked"),
            symbols     : $('#pwd-definition-symbols').prop("checked"),
            secure_pwd  : secure_pwd,
            force       : "false"
        },
        function(data) {
            data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
            console.log(data)
            if (data.error == "true") {
                // error
                alertify
                    .alert()
                    .setting({
                        'label' : '<?php echo langHdl('error'); ?>',
                        'message' : '<i class="fas fa-info-circle mr-2"></i>' + data.error_msg
                    })
                    .show(); 
                return false;
            } else {
                $("#form-item-password").val(data.key).focus();
                
                // Form has changed
                userDidAChange = true;
                $('#form-item-password').data('change-ongoing', true);

                // SHow button in sticky footer
                //$('#form-item-buttons').addClass('sticky-footer');
            }
        }
   );
});

$('#item-button-password-copy').click(function() {
    $('#form-item-password-confirmation').val($('#form-item-password').val());
});


/*
// Get parameters from url
var currentTeampassUrl = new URL(window.location.href);
var actionFromUrl = currentTeampassUrl.searchParams.get('action');
if (actionFromUrl !== undefined && atob(actionFromUrl) === 'reset_private_key') {
    // Case where we need to re-encrypt all share keys
    console.log("ACTION RE-ENCRYPTION OF SHAREKEYS");

    $('#dialog-encryption-keys').removeClass('hidden');    

    // Hide other
    $('.content-header, .content').addClass('hidden');

    alertify.dismissAll();
}
*/

</script>
