<?php
/**
 * Teampass - a collaborative passwords manager.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 *
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2018 Nils Laumaillé
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 *
 * @version   GIT: <git_id>
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
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], curPage())) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

$var['hidden_asterisk'] = '<i class="fa fa-asterisk mr-2"></i><i class="fa fa-asterisk mr-2"></i><i class="fa fa-asterisk mr-2"></i><i class="fa fa-asterisk mr-2"></i><i class="fa fa-asterisk"></i>';

?>


<script type="text/javascript">


var requestRunning = false,
    clipboardForLogin,
    clipboardForPassword,
    query_in_progress = 0,
    screenHeight = $(window).height(),
    quick_icon_query_status = true,
    start = 0,
    first_group = 1,
    folderSearchCriteria = $('#jstree_search').val(),
    itemEditor,
    itemEditorSuggestion,
    userDidAChange = false,
    selectedFolder = '',
    selectedFolderId = '';


//Evaluate number of items to display - depends on screen height
if (parseInt($('#nb_items_to_display_once').val()) === false
    || $('#nb_items_to_display_once').val() === ''
    || $('#nb_items_to_display_once').val() === 'auto'
) {
    //adapt to the screen height
    $('#nb_items_to_display_once')
        .val(Math.max(Math.round(($(window).height()-450)/23),2));
}

//load items
if (parseInt($('#query_next_start').val()) > 0) start = parseInt($('#query_next_start').val());
else start = 0;

// load list of items
if ($('#form-item-hidden-last-folder-selected').val() !== '') {
    selectedFolderId = $('#form-item-hidden-last-folder-selected').val();
    ListerItems(selectedFolderId, '' , start);
}

// Build tree
$('#jstree').jstree({
    'core' : {
        'animation' : 0,
        'check_callback' : true,
        'data' : {
            'url' : './sources/tree.php',
            'dataType' : 'json',
            'async' : true,
            'data' : function (node) {
                return {
                    'id' : node.id.split('_')[1] ,
                    'force_refresh' : $('#form-item-hidden-jstree-force-refresh').val()
                };
            }
        },
        'strings' : {
            'Loading ...' : '<?php echo langHdl('loading'); ?>...'
        },
        'error' : {

        }
    },
    'plugins' : [
        'state', 'search'
    ]
})
// On node select
.bind('select_node.jstree', function (e, data) {
    selectedFolder = $('#jstree').jstree('get_selected', true)[0];
    selectedFolderId = selectedFolder.id.split('_')[1]
    console.info('SELECTED NODE');
    console.log(selectedFolder)
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


$(document).keyup(function(e) {
    if (e.keyCode == 27) {
        closeItemDetailsCard();
    }
});

// load list of visible folders for current user
$(this).delay(500).queue(function() {
    refreshVisibleFolders();
    $(this).dequeue();
});


// Ensure correct height of folders tree
$('#jstree').height(screenHeight - 200);

$('input[type="checkbox"].flat-blue, input[type="radio"].flat-blue').iCheck({
    checkboxClass: 'icheckbox_flat-blue',
    radioClass   : 'iradio_flat-blue'
})


// Manage folders action
$('.tp-action').click(function() {
    if ($(this).data('folder-action') === 'refresh') {
        // Force refresh
        $('#form-item-hidden-jstree-force-refresh').val('1');
        if (selectedFolderId !== '') {
            refreshTree(selectedFolderId, true);
        } else {
            refreshTree();
        }
        $('#form-item-hidden-jstree-force-refresh').val('');
    } else if ($(this).data('folder-action') === 'expand') {
        $('#jstree').jstree('open_all');
    } else if ($(this).data('folder-action') === 'collapse') {
        $('#jstree').jstree('close_all');
    } else if ($(this).data('folder-action') === 'add') {
        console.info('SHOW ADD FOLDER');
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
        // ---
    } else if ($(this).data('folder-action') === 'edit') {
        console.info('SHOW EDIT FOLDER');
        // Show copy form
        $('.form-item, .item-details-card, .form-item-action, #folders-tree-card').addClass('hidden');
        $('.form-folder-add').removeClass('hidden');
        // Prepare some data in the form
        $("#form-folder-add-parent option[value='"+selectedFolder.id.split('_')[1]+"']")
            .prop('disabled', true);
        $('#form-folder-add-parent').val(selectedFolder.parent.split('_')[1]).change();
        $('#form-folder-add-label')
            .val(selectedFolder.a_attr['data-title'])
            .focus();
        // Set type of action for the form
        $('#form-folder-add').data('action', 'update');
        // ---
    } else if ($(this).data('folder-action') === 'copy') {
        console.info('SHOW COPY FOLDER');
        // Show copy form
        $('.form-item, .item-details-card, .form-item-action, #folders-tree-card').addClass('hidden');
        $('.form-folder-copy').removeClass('hidden');
        // Prepare some data in the form
        $('#form-folder-copy-source').val(selectedFolder.id.split('_')[1]).change();
        $("#form-folder-copy-destination option[value='"+selectedFolder.id.split('_')[1]+"']")
            .prop('disabled', true);
        $('#form-folder-copy-destination').val(0).change();
    } else if ($(this).data('folder-action') === 'delete') {
        console.info('SHOW DELETE FOLDER');
        // Show copy form
        $('.form-item, .item-details-card, .form-item-action, #folders-tree-card').addClass('hidden');
        $('.form-folder-delete').removeClass('hidden');
        // Prepare some data in the form
        $('#form-folder-delete-selection').val(selectedFolder.parent.split('_')[1]).change();
    } else if ($(this).data('item-action') === 'new') {
        console.info('SHOW NEW ITEM');
        // HIde
        $('#items-list-card, .form-item-copy, #folders-tree-card, #form-item-password-options, .form-item-action, #form-item-attachments-zone')
            .addClass('hidden');
        // Destroy editor
        if (itemEditor) itemEditor.destroy();
        // Clean select2 lists
        $('.select2').val('').change();
        // Do some form cleaning
        $('#form-item-hidden-id, #request_lastItem, .clear-me-val').val('');
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
        // Get some info
        getPrivilegesOnItem(
            selectedFolderId,
            0
        );
        // Set folder
        $('#form-item-folder').val(selectedFolderId).change();
        // Select tab#1
        $('#form-item-nav-pills li:first-child a').tab('show');
        // Preselect
        $('#pwd-definition-size').val(12);
        // Set type of action
        $('#form-item-button-save').data('action', 'new_item');
        // Update variable
        userDidAChange = false;
        // ---
    } else if ($(this).data('item-action') === 'edit') {
        console.info('SHOW EDIT ITEM');
        // Show edition form
        $('.form-item, #form-item-attachments-zone').removeClass('hidden');
        $('.item-details-card, .form-item-copy, #form-item-password-options, .form-item-action').addClass('hidden');
        userDidAChange = false;
        // Force update of simplepassmeter
        $('#form-item-password').focus();
        $('#form-item-label').focus();
        // Get some info
        getPrivilegesOnItem(
            selectedFolderId,
            0
        );
        // Set type of action
        $('#form-item-button-save').data('action', 'update_item');
        // ---
    } else if ($(this).data('item-action') === 'copy') {
        console.info('SHOW COPY ITEM');
        // Show copy form
        $('.form-item, .item-details-card, .form-item-action').addClass('hidden');
        $('.form-item-copy, .item-details-card-menu').removeClass('hidden');
        // Prepare some data in the form
        $('#form-item-copy-new-label').val($('#form-item-label').val());
        $('#form-item-copy-destination').val($('#form-item-folder').val()).change();
        // ---
    } else if ($(this).data('item-action') === 'delete') {
        console.info('SHOW DELETE ITEM');
        // Show delete form
        $('.form-item, .item-details-card, .form-item-action').addClass('hidden');
        $('.form-item-delete, .item-details-card-menu').removeClass('hidden');
    } else if ($(this).data('item-action') === 'share') {
        console.info('SHOW SHARE ITEM');
        // Show share form
        $('.form-item, .item-details-card, .form-item-action').addClass('hidden');
        $('.form-item-share, .item-details-card-menu').removeClass('hidden');
    } else if ($(this).data('item-action') === 'notify') {
        console.info('SHOW NOTIFY ITEM');
        // Show notify form
        $('.form-item, .item-details-card, .form-item-action').addClass('hidden');
        $('.form-item-notify, .item-details-card-menu').removeClass('hidden');
    }
});

// Quit item details card back to items list
$('.but-back-to-list').click(function() {
    closeItemDetailsCard();
});

// BAck to item details card 
$('.but-back-to-item').click(function() {
    showItemDetailsCard();
});


// Manage if change is performed by user
$('#form-item .track-change').change(function() {
    if ($(this).val().length > 0) {
        userDidAChange = true;
        $(this).data('change-ongoing', true);
    }
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
        .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();

    // Launch action
    $.post(
        'sources/items.queries.php',
        {
            type    : 'send_email',
            id      : $('#form-item-hidden-id').val(),
            receipt : $('#form-item-share-email').val(),
            cat     : 'share_this_item',
            key     : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            if (data[0].error === '') {
                $('.form-item, .item-details-card, .form-item-action').removeClass('hidden');
                $('.form-item-share, .item-details-card-menu').addClass('hidden');
                // Warn user
                alertify.success('<?php echo langHdl('success'); ?>', 1);
                // Clear
                $('#form-item-share-email').val('');
            } else {
                // ERROR
                alertify
                    .error(
                        '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' + data[0].message,
                        0
                    )
                    .dismissOthers();
            }
        },
        'json'
    );
});


/**
 * DELETE - recycle item
 */
$('#form-item-delete-perform').click(function() {
    // Show cog
    alertify
        .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();
    
    // Force user did a change to false
    userDidAChange = false;

    var data = {
        'item_id'   : $('#form-item-hidden-id').val(),
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
        function(data) {
            //decrypt data
            try {
                data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
            } catch (e) {
                // error
                alertify
                    .error('<i class="fa fa-ban fa-lg mr-3"></i>An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />' + data, 0)
                    .dismissOthers();
                return false;
            }

            if (data.error === '') {
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
                        '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' + data.message,
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
            id      : $('#form-item-hidden-id').val(),
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
                        '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' + data[0].message,
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
            .error('<i class="fa fa-ban fa-lg mr-3"></i><?php echo langHdl('error_field_is_mandatory'); ?>', 0)
            .dismissOthers();
        return false;
    }

    // Show cog
    alertify
        .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();

    // Force user did a change to false
    userDidAChange = false;

    var data = {
        'item_id'   : $('#form-item-hidden-id').val(),
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
                        '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' + data[1].error_text,
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
        .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();

    // Force user did a change to false
    userDidAChange = false;

    var data = {
        'label'         : $('#form-item-suggestion-label').val(),
        'login'         : $('#form-item-suggestion-login').val(),
        'password'      : $('#form-item-suggestion-password').val(),
        'email'         : $('#form-item-suggestion-email').val(),
        'url'           : $('#form-item-suggestion-url').val(),
        'description'   : itemEditorSuggestion.getData(),
        'comment'       : $('#form-item-suggestion-comment').val(),
        'folder_id'     : selectedFolderId,
        'item_id'       : $('#form-item-hidden-id').val(),
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
            //decrypt data
            try {
                data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
            } catch (e) {
                // error
                alertify
                    .error('<i class="fa fa-ban fa-lg mr-3"></i>An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />' + data, 0)
                    .dismissOthers();
                return false;
            }

            if (data.error !== '') {
                // ERROR
                alertify
                    .error(
                        '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' + data.message,
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
                '<i class="fa fa-ban fa-lg mr-3"></i><?php echo langHdl('error_only_numbers_in_folder_name'); ?>',
                5,
                'top-right'
            )
            .dismissOthers();
        
        return false;
    }

    // Show cog
    alertify
        .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();

    // Force user did a change to false
    userDidAChange = false;

    var data = {
        'label'         : $('#form-folder-add-label').val(),
        'parent_id'     : $('#form-folder-add-parent option:selected').val(),
        'complexicity'  : $('#form-folder-add-complexicity option:selected').val(),
        'folder_id'     : selectedFolderId,
    }

    // Launch action
    $.post(
        'sources/folders.queries.php',
        {
            type    : $('#form-folder-add').data('action') + '_folder',
            data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
            key     : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            //decrypt data
            try {
                data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
            } catch (e) {
                // error
                alertify
                    .error('<i class="fa fa-ban fa-lg mr-3"></i>An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />' + data, 0)
                    .dismissOthers();
                return false;
            }
            
            if (data.error === true) {
                // ERROR
                alertify
                    .error(
                        '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' + data.message,
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
                    $('#form-item-hidden-jstree-force-refresh').val('1');
                    refreshTree(selectedFolderId, true);
                    // Refresh list of items inside the folder
                    ListerItems(selectedFolderId, '', 0);
                    $('#form-item-hidden-jstree-force-refresh').val('');
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
            .error('<i class="fa fa-ban fa-lg mr-3"></i><?php echo langHdl('please_confirm'); ?>', 0)
            .dismissOthers();
        return false;
    } else if ($('#form-folder-delete-selection option:selected').text() === '<?php echo $_SESSION['login']; ?>') {
        alertify
            .error('<i class="fa fa-ban fa-lg mr-3"></i><?php echo langHdl('error_not_allowed_to'); ?>', 0)
            .dismissOthers();
        return false;
    }

    // Show cog
    alertify
        .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();

    var data = {
        'folder_id' : $('#form-folder-delete-selection option:selected').val()
    }
    
    // Launch action
    $.post(
        'sources/folders.queries.php',
        {
            type    : 'delete_folder',
            data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
            key     : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            //decrypt data
            try {
                data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
            } catch (e) {
                // error
                alertify
                    .error('<i class="fa fa-ban fa-lg mr-3"></i>An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />' + data, 0)
                    .dismissOthers();
                return false;
            }

            if (data.error === true) {
                // ERROR
                alertify
                    .error(
                        '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' + data.message,
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
            .error('<i class="fa fa-ban fa-lg mr-3"></i><?php echo langHdl('error_must_enter_all_fields'); ?>', 0)
            .dismissOthers();
        return false;
    } else if ($("#form-folder-copy-source").val() === $("#form-folder-copy-destination").val()) {
        alertify
            .error('<i class="fa fa-ban fa-lg mr-3"></i><?php echo langHdl('error_source_and_destination_are_equal'); ?>', 0)
            .dismissOthers();
        return false;
    }

    // Show cog
    alertify
        .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();

    var data = {
        'source_folder_id' : $('#form-folder-copy-source option:selected').val(),
        'target_folder_id' : $('#form-folder-copy-destination option:selected').val()
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
            try {
                data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
            } catch (e) {
                // error
                alertify
                    .error('<i class="fa fa-ban fa-lg mr-3"></i>An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />' + data, 0)
                    .dismissOthers();
                return false;
            }
console.log(data)
            if (data.error === true) {
                // ERROR
                alertify
                    .error(
                        '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' + data.message,
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
        // Do some form cleaning
        $('#items-list-card, #folders-tree-card').removeClass('hidden');
        $('.item-details-card, .form-item-copy, .form-item, .form-folder-action').addClass('hidden');
        $('#form-item-hidden-id, #request_lastItem, .clear-me-val').val('');
        $('.item-details-card').find('.form-control').val('');
        $('.clear-me-html').html('');
        $('.form-item-control').val('');
        $('.form-check-input').attr('checked', '');
        $('.card-item-extra').collapse();
        
        // Destroy editor
        if (itemEditor) {
            itemEditor.destroy();
        }

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
 * Undocumented function
 *
 * @return void
 */
function showItemDetailsCard()
{
    $('.item-details-card').removeClass('hidden');
    $('.form-item-action').addClass('hidden');
}





 
/**
 * Click on item
 */
$(document)
    .on('click', '.list-item-clicktoshow', function() {
        showAlertify(
            '<span class="fa fa-cog fa-spin fa-2x"></span>',
            0,
            'bottom-right',
            'message'
        );

        // Load item info
        Details($(this).closest('tr'), 'show');
    })
    .on('click', '.list-item-clicktoedit', function() {
        showAlertify(
            '<span class="fa fa-cog fa-spin fa-2x"></span>',
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
        var thisWidth = $(this).find(".list-item-description").width(),
            miniIcons = 65;
        
        $(this).find(".list-item-actions")
            .css({
                left: thisWidth - miniIcons
            })
            .removeClass('hidden');
    })
    .on('mouseleave', '.list-item-row', function() {
        $(this).find(".list-item-actions").addClass('hidden');
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
                    if ($(this).data('item-favourited') === 0) {
                        $(elem)
                            .html('<i class="fa fa-sm fa-star-o text-warning"></i>')
                            .data('data-favourited', 1);
                    } else {
                        $(elem)
                            .html('<i class="fa fa-sm fa-star"></i>')
                            .data('data-favourited', 0);
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
    if(mouseStillDown){
        $('#card-item-pwd')
            .html('<span style="cursor:none;">' + $('#hidden-item-pwd').val().replace(/\n/g,"<br>") + '</span>');
        
        setTimeout('showPwdContinuous("card-item-pwd")', 50);
        // log password is shown
        if ($("#pw_shown").val() === "0") {
            itemLog(
                'at_password_shown',
                $('#form-item-hidden-id').val(),
                $('#card-item-label').text()
            );
            $("#pw_shown").val('1');
        }
    } else {
        $('#card-item-pwd').html('<?php echo $var['hidden_asterisk']; ?>');
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
    "score.simplePassMeter" : function(jQEvent, score) {
        $("#form-item-suggestion-password-complex").val(score);
    }
}).change({
    "score.simplePassMeter" : function(jQEvent, score) {
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

            if ($('#form-item-hidden-uploaded-file-id').val() === '') {
                var post_id = CreateRandomString(9,'num_no_0');
                $('#form-item-hidden-uploaded-file-id').val(post_id);
            }

            up.setOption('multipart_params', {
                PHPSESSID       : '<?php echo $_SESSION['user_id']; ?>',
                itemId          : $('#form-item-hidden-uploaded-file-id').val(),
                type_upload     : 'item_attachments',
                edit_item       : false,
                user_token      : $('#form-item-hidden-userToken').val(),
                files_number    : $('#form-item-hidden-pickFilesNumber').val()
            });
        },
        UploadComplete: function(up, files) {
            alertify
                .success('<?php echo langHdl('success'); ?>', 1)
                .dismissOthers();
            $('#form-item-hidden-pickFilesNumber').val(0);
        }
    }
});

// Uploader options
uploader_attachments.bind('UploadProgress', function(up, file) {
    $('#upload-file_' + file.id).html('<i class="fa fa-file fa-sm mr-2"></i>' + file.name + ' - ' + file.percent + '%');
});
uploader_attachments.bind('Error', function(up, err) {
    alertify
        .error(
            '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' +
            err.message + (err.file ? ', File: ' + err.file.name : ''),
            0
        )
        .dismissOthers();
        
    up.refresh(); // Reposition Flash/Silverlight
});
uploader_attachments.bind('FilesAdded', function(up, file) {
    $('#upload-file_' + file.id + '')
        .html('<i class="fa fa-file fa-sm mr-2"></i>' + file.name + ' <?php echo langHdl('uploaded'); ?>');
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
                $("#form-item-hidden-userToken").val(data[0].token);
                uploader_attachments.start();
            },
            "json"
        );
        e.preventDefault();
    } else {
        alertify
            .warning(
                '<i class="fa fa-warning fa-lg mr-2"></i><?php echo langHdl('no_file_to_upload'); ?>',
                2
            )
            .dismissOthers();
    }
});
uploader_attachments.init();
uploader_attachments.bind('FilesAdded', function(up, files) {
    $('#form-item-upload-pickfilesList').removeClass('hidden');
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
            .error('<i class="fa fa-ban fa-lg mr-3"></i><?php echo langHdl('error_no_action_identified'); ?>', 10)
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
                'value' : $(this).val()
            });
        }
    });
console.log(arrayQuery)
    if (arrayQuery.length > 0) {
        var reg = new RegExp("[.|,|;|:|!|=|+|-|*|/|#|\"|'|&]");

        // Do some easy checks
        if ($('#form-item-label').val() === '') {
            // Label is empty
            alertify
                .error('<i class="fa fa-ban fa-lg mr-3"></i><?php echo langHdl('error_label'); ?>', 10)
                .dismissOthers();
            return false;
        } else if ($('#form-item-tags').val() !== ''
            && reg.test($('#form-item-tags').val())
        ) {
            // Tags not wel formated
            alertify
                .error('<i class="fa fa-ban fa-lg mr-3"></i><?php echo langHdl('error_tags'); ?>', 10)
                .dismissOthers();
            return false;
        } else if ($('#form-item-folder option:selected').val() === ''
            || typeof  $('#form-item-folder option:selected').val() === 'undefined'
        ) {
            // No folder selected
            alertify
                .error('<i class="fa fa-ban fa-lg mr-3"></i><?php echo langHdl('error_no_selected_folder'); ?>', 10)
                .dismissOthers();
            return false;
        } else if ($('#form-item-hidden-isPersonalFolder').val() === '1'
            && $('#form-item-hidden-psk').val() !== '1'
        ) {
            // No folder selected
            alertify
                .error('<i class="fa fa-ban fa-lg mr-3"></i><?php echo langHdl('error_personal_saltkey_is_not_set'); ?>', 10)
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

            // get item field values
            var fields = "";
            $('.edit_item_field').each(function(i){
                id = $(this).attr('id').split('_');
                
                // Check if mandatory
                if ($(this).data('field-is-mandatory') === 1
                    && $(this).val() === ''
                    && $(this).is(':visible') === true
                ) {
                    fields = false; 
                    $('#edit_show_error')
                        .html("<?php echo addslashes($LANG['error_field_is_mandatory']); ?>")
                        .show();
                    $("#div_formulaire_edition_item_info")
                        .addClass("hidden")
                        .html("");
                    $("#div_formulaire_edition_item ~ .ui-dialog-buttonpane")
                        .find("button:contains('<?php echo addslashes($LANG['save_button']); ?>')")
                        .prop("disabled", false);
                    return false;
                }

                // Store data
                if (fields == "") fields = id[2] + '~~' + $(this).val();
                else fields += '_|_' + id[2] + '~~' + $(this).val();
            });           
            if (fields === false) {
                return false;
            }

            // get template
            var template = "";
            $('.item_edit_template').each(function(i){
                if ($(this).prop('checked') === true) {
                    template = $(this).data('category-id');
                    return false;
                }
            });

              //prepare data
            var data = {
                'anyone_can_modify': $('#form-item-anyoneCanModify').is(':checked') ? 1 : 0,
                'complexity_level': parseInt($('#form-item-password-complex').val()),
                'description': itemEditor.getData(),
                'diffusion_list' : diffusion,
                'folder': parseInt($('#form-item-folder').val()),
                'email': $('#form-item-email').val(),
                'fields': fields,
                'folder_is_personal': ($('#form-item-hidden-isPersonalFolder').val() === 1
                    && $('#form-item-hidden-psk').val() === '1') ? 1 : 0,
                'id': parseInt($('#form-item-hidden-id').val()),
                'label': $('#form-item-label').val(),
                'login': $('#form-item-login').val(),
                'pw': $('#form-item-password').val(),
                'restricted_to': restriction,
                'restricted_to_roles': restrictionRole,
                'salt_key_set': parseInt($('#form-item-hidden-psk').val()),
                'tags': $('#form-item-tags').val(),
                'template_id': template === '' ? '' : parseInt(template),
                'to_be_deleted_after_date': ($('#form-item-deleteAfterDate') !== undefined
                    && $('#form-item-deleteAfterDate').val() !== '') ? $('#form-item-deleteAfterDate').val() : '',
                'to_be_deleted_after_x_views': ($('#form-item-deleteAfterShown') !== undefined
                    && $('#form-item-deleteAfterShown').val() !== '' && $('#form-item-deleteAfterShown').val() >= 1) ?
                    parseInt($('#form-item-deleteAfterShown').val()) : '',
                'url': $('#form-item-url').val(),
                'user_id' : parseInt('<?php echo $_SESSION['user_id']; ?>'),
                'uploaded_file_id' : $('#form-item-hidden-uploaded-file-id').val(),
            };
console.log('SAVING DATA');
console.log(data);
            // Inform user
            alertify
                .message('<?php echo langHdl('opening_folder'); ?>&nbsp;<i class="fa fa-cog fa-spin"></i>', 0)
                .dismissOthers();
                
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
                        $("#request_ongoing").val("");
                        $("#div_dialog_message_text").html("An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />"+data);
                        $("#div_dialog_message").dialog("open");

                        alertify
                            .error('<i class="fa fa-ban fa-lg mr-3"></i>An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />' + data, 0)
                            .dismissOthers();
                        return false;
                    }
console.log('RETURNED DATA');
console.log(data)
                    if (data.error === true) {
                        alertify
                            .error('<i class="fa fa-warning fa-lg mr-2"></i>' + data.message, 0)
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
                        closeItemDetailsCard();
                    }
                }
            );
        }
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
        source: function( request, response ) {
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


/**
 * Start items search
 */
function searchItems(criteria)
{
    if (criteria !== '') {
        // stop items loading (if on-going)
        $('#items_listing_should_stop').val('1');

        // wait
        alertify
            .message('<?php echo langHdl('searching'); ?>', 0);

        // clean
        $('#id_label, #id_desc, #id_pw, #id_login, #id_email, #id_url, #id_files, #id_restricted_to ,#id_tags, #id_kbs, .fields_div, .fields, #item_extra_info').html('');
        $('#button_quick_login_copy, #button_quick_pw_copy').addClass('hidden');
        $('#teampass_items_list').html('');
        $('#form-item-hidden-id').val('');

        // send query
        $.get(
            'sources/find.queries.php',
            {
                type        : 'search_for_items',
                sSearch     : criteria,
                key         : '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                var pwd_error = '',
                    icon_login,
                    icon_pwd,
                    icon_favorite;

                data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
                
                $('#items_folder_path').html('<button class="btn btn-info btn-sm"><i class="fa fa-filter"></i>&nbsp;' + data.message + '</button>');
                
                // Build HTML list
                $.each(data.html_json, function(i, value) {

                    // Prepare error message
                    if (value.pw_status === 'encryption_error') {
                        pwd_error = '<i class="fa fa-warning fa-sm text-danger infotip" title="<?php echo langHdl('pw_encryption_error'); ?>"></i>&nbsp;';
                    }

                    // Prepare mini icons
                    if (value.copy_to_clipboard_small_icons === '1' && value.display === '') {
                        // Login icon
                        if (value.login !== '') {
                            icon_login = '<i class="fa fa-sm fa-user mini_login pointer infotip" data-clipboard-text="' + sanitizeString(value.login) + '" title="<?php echo langHdl('item_menu_copy_login'); ?>" id="minilogin_' + value.item_id + '"></i>&nbsp;';
                        }
                        // Pwd icon
                        if (value.pw !== '') {
                            icon_pwd = '<i class="fa fa-sm fa-lock mini_pw pointer infotip" data-clipboard-text="'+sanitizeString(value.pw)+'" title="<?php echo langHdl('item_menu_copy_pw'); ?>" data-clipboard-id="' + value.item_id + '" id="minipwd_'+value.item_id+'"></i>&nbsp;';
                        }

                        // Now check if pwd is empty. If it is then warn user
                        if (value.pw === '') {
                            pwd_error = '&nbsp;<i class="fa fa-exclamation-circle fa-sm text-warning infotip" title="<?php echo langHdl('password_is_empty'); ?>"></i>&nbsp;';
                        }
                    }

                    // Prepare Favorite icon
                    if (value.display === '' && value.enable_favourites === 1) {
                        if (value.is_favorite === 1) {
                            icon_favorite = '<span title="Manage Favorite" class="pointer infotip item-favourite" data-item-id="' + value.item_id + '" data-item-favourited="1">' +
                                '<i class="fa fa-sm fa-star text-warning"></i></span>';
                        } else {
                            icon_favorite = '<span title="Manage Favorite" class="pointer infotip item-favourite" data-item-id="' + value.item_id + '" data-item-favourited="0">' +
                                '<i class="fa fa-sm fa-star-o"></i></span>';
                        }
                    }

                    // Prepare Description
                    if (value.desc !== '') {
                        value.desc = '[' + value.desc + ']';
                    }

                    // Prepare flag
                    if (value.expiration_flag !== '') {
                        item_flag = '<i class="fa fa-flag ' + value.expiration_flag + ' fa-sm"></i>&nbsp;';
                    }
                    
                    $('#teampass_items_list').append(
                        '<tr data-edition="' + value.open_edit + '">' +
                        '<td><i class="fa fa-key"></i></td>' +
                        '<td class="list-item-clicktoshow pointer" data-item-id="' + value.item_id + '">' + value.label + '</td>' +
                        '<td style="max-width: 5%;">' + value.folder + '</td>' +
                        '<td class="text-truncate small" style="max-width: 30%;">' + value.desc + '</td>' +
                        '<td style="min-width: 50px;" class="text-right">' + pwd_error  + icon_login + icon_pwd + icon_favorite + '</td>' +
                        '</tr>'
                    );
                })

                alertify
                    .success('<?php echo langHdl('success'); ?>', 1)
                    .dismissOthers();

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
            type    : 'refresh_visible_folders',
            key        : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
            console.info('Visible folders:');
            console.log(data);
            //check if format error
            if (data.error === '') {
                // Build html lists
                var html_visible = '',
                    html_full_visible = '',
                    html_active_visible = '',
                    indentation = '',
                    disabled = '';

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
                    /*html_full_visible += '<option value="' + value.id + '">' +
                        value.title + (value.path !=='' ? ' [' + value.path + ']' : '') + '</option>';
                    html_active_visible += '<option value="' + value.id + '"' +
                        ((value.is_visible_active === 1) ? ' disabled="disabled"' : '') + '>' +
                        value.title + (value.path !=='' ? ' [' + value.path + ']' : '') + '</option>';*/
                });

                // append new list
                $('#form-item-folder, #form-item-copy-destination, #form-folder-add-parent,' +
                    '#form-folder-delete-selection, #form-folder-copy-source, #form-folder-copy-destination')
                    .find('option')
                    .remove()
                    .end()
                    .append(html_visible);
                //$('#move_folder_id').find('option').remove().end().append(html_full_visible);
                //$('#copy_in_folder').find('option').remove().end().append(html_active_visible);

                // remove ROOT option if exists
                $('#form-item-copy-destination option[value="0"]').remove();
                //$('#delete_rep_groupe option[value="0"]').remove();
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
        $('#hid_cat').val(node_to_select);
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


    // case where we should stop listing the items
    if ($('#items_listing_should_stop').val() === '1') {
        requestRunning = false;
        $('#items_listing_should_stop').val('0');
    }

    if (stop_listing_current_folder === 1) {
        me.data('requestRunning', false);
        $('#new_listing_characteristics').val(groupe_id+','+restricted+','+start+',0');
    } else {
        $('#new_listing_characteristics').val('');
    }


    // prevent launch of similar query in case of doubleclick
    if (requestRunning === true) {
        return false;
    }
    requestRunning = true;

    $('#request_lastItem, #form-item-hidden-id').val('');

    // Hide any info
    $('#info_teampass_items_list').addClass('hidden');

    if (groupe_id != undefined) {
        //refreshTree(groupe_id);
        if (query_in_progress != 0 && query_in_progress != groupe_id) {
            request.abort();    //kill previous query if needed
        }
        query_in_progress = groupe_id;
        //LoadingPage();
        $('#items_list_loader').removeClass('hidden');
        if (start == 0) {
            //clean form
            $('#id_label, #id_pw, #id_email, #id_url, #id_desc, #id_login, #id_info, #id_restricted_to, #id_files, #id_tags, #id_kbs, #item_extra_info, #item_viewed_x_times').html('');
            $('#teampass_items_list, #items_folder_path').html('');
        }

        $('#hid_cat').val(groupe_id);
        if ($('.tr_fields') !== undefined) {
            $('.tr_fields, .newItemCat, .editItemCat').addClass('hidden');
        }

        //Disable menu buttons
        $('#button_quick_login_copy, #button_quick_pw_copy').addClass('hidden');

        // Inform user
        alertify
            .message('<?php echo langHdl('opening_folder'); ?>&nbsp;<i class="fa fa-cog fa-spin"></i>', 0)
            .dismissOthers();

        //ajax query
        request = $.post('sources/items.queries.php',
            {
                type                     : 'lister_items_groupe',
                id                       : groupe_id,
                restricted               : restricted,
                start                    : start,
                uniqueLoadData           : $('#uniqueLoadData').val(),
                key                      : '<?php echo $_SESSION['key']; ?>',
                nb_items_to_display_once : $('#nb_items_to_display_once').val()
            },
            function(retData) {
                if (retData == 'Hacking attempt...') {
                    alert('Hacking attempt...');
                    return false;
                }
                //get data
                data = prepareExchangedData(retData, 'decode', '<?php echo $_SESSION['key']; ?>');

                console.log('LIST ITEMS');
                console.log(data);

                // reset doubleclick prevention
                requestRunning = false;

                // manage not allowed
                if (data.error == 'not_allowed') {
                    alertify
                        .error('<i class="fa fa-warning fa-lg mr-2"></i>' + data.error_text, 0)
                        .dismissOthers();
                   return false;
                }

                // to be done only in 1st list load
                if (data.list_to_be_continued === 'end') {
                    $('#form-item-hidden-isPersonalFolder').val(data.IsPersonalFolder);

                    // display path of folders
                    if ((data.arborescence !== undefined && data.arborescence !== '')) {
                        console.log(data.arborescence);
                        $('#form-folder-path')
                            .html('')
                            .append(rebuildPath(data.arborescence));
                    } else {
                        $('#form-folder-path').html('');
                    }

                    // store the categories to be displayed
                    if (data.displayCategories !== undefined) {
                        $('#form-item-hidden-hasCustomCategories').val(data.displayCategories);
                    }

                    // store type of access on folder
                    $('#form-item-hidden-hasAccessLevel').val(data.access_level);

                    // warn about a required change of personal SK
                    if ($('#personal_upgrade_needed').val() == '1' && data.folder_requests_psk === 1) {
                        $('#dialog_upgrade_personal_passwords').dialog('open');
                    }

                    // show correct fodler in Tree
                    $('#jstree').jstree('deselect_all');
                    $('#jstree').jstree('select_node', '#li_'+groupe_id);

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

                    clipboardForPassword = new Clipboard('.fa-clickable-password');
                    clipboardForPassword.on('success', function(e) {
                        itemLog(
                            'at_password_copied',
                            e.trigger.dataset.itemId,
                            e.trigger.dataset.itemLabel
                        );
                        
                        showAlertify(
                            '<?php echo langHdl('copy_to_clipboard'); ?>',
                            1,
                            'top-right',
                            'message'
                        );
                        e.clearSelection();
                    });
                } else if (data.error === 'not_authorized') {
                    $('#items_folder_path').html('<i class="fa fa-folder-open-o"></i>&nbsp;'+rebuildPath(data.arborescence));
                } else {
                    $('#uniqueLoadData').val(data.uniqueLoadData);
                    if ($('#items_loading_progress').length == 0) {
                        $('#items_list_loader').after('<span id="items_loading_progress">' + Math.round(data.next_start*100/data.counter_full, 0) + '%</span>');
                    } else {
                        $('#items_loading_progress').html(Math.round(data.next_start*100/data.counter_full, 0) + '%');
                    }
                    // Store arbo
                    if (data.arborescence !== undefined && data.arborescence !== '' && $('#tmp_arbo').length === 0) {
                        // Rebuild path
                        new_path = rebuildPath(data.arborescence);

                        // Store path in tempo element
                        $('body').append('<span class="hidden" id="tmp_arbo">'+new_path+'</span>');
                    }
                }
                
                if (data.array_items.length === 0 && $('#teampass_items_list').html() === '') {
                    // Show warning to user
                    $('#info_teampass_items_list')
                        .html('<div class="alert alert-primary text-center col col-lg-10" role="alert">' +
                            '<i class="fa fa-info-circle mr-2"></i><?php echo langHdl('no_item_to_display'); ?></b>' +
                            '</div>')
                        .removeClass('hidden');
                }

                if (data.error === 'is_pf_but_no_saltkey') {
                    //warn user about his saltkey
                    $('#item_details_no_personal_saltkey').removeClass('hidden');
                    $('#menu_button_add_item').prop('disabled', 'true');
                } else if (data.error === 'not_authorized' || data.access_level === '') {
                    //warn user
                    $('#hid_cat').val('');                    
                    $('#item_details_no_personal_saltkey').addClass('hidden');

                    // Show warning to user
                    $('#info_teampass_items_list')
                        .html('<div class="alert alert-primary text-center col col-lg-10" role="alert">' +
                            '<i class="fa fa-warning mr-2"></i><?php echo langHdl('not_allowed_to_see_pw'); ?></b>' +
                            '</div>')
                        .removeClass('hidden');

                } else if (($('#user_is_read_only').val() == 1 && data.folder_requests_psk == 0) || data.access_level == 1) {
                    //readonly user
                    $('#folder_requests_psk').val(data.saltkey_is_required);
                    $('#item_details_no_personal_saltkey, #item_details_nok').addClass('hidden');
                    $('#item_details_ok, #items_list').removeClass('hidden');

                    $('#more_items').remove();

                    // show items
                    sList(data.html_json);

                    if (data.list_to_be_continued === 'yes') {
                        //set next start for query
                        $('#query_next_start').val(data.next_start);
                    } else {
                        $('#query_next_start').val(data.list_to_be_continued);

                        // display Categories if needed
                        if ($('.tr_fields') !== undefined && data.displayCategories !== undefined && data.displayCategories !== '') {
                            var liste = data.displayCategories.split(';');
                            for (var i=0; i<liste.length; i++) {
                                $('.itemCatName_'+liste[i]+', #newItemCatName_'+liste[i]+', #editItemCatName_'+liste[i]).removeClass('hidden');
                            }
                        }
                        if (data.saltkey_is_required == 1) {
                            if ($('.tr_fields') != undefined) $('.tr_fields').addClass('hidden');
                        }
                    }

                    proceed_list_update(stop_listing_current_folder);
                } else {
                    $('#folder_requests_psk').val(data.saltkey_is_required);
                    //Display items
                    $('#item_details_no_personal_saltkey, #item_details_nok').addClass('hidden');
                    $('#item_details_ok, #items_list').removeClass('hidden');

                    $('#complexite_groupe').val(data.folder_complexity);
                    $('#bloquer_creation_complexite').val(data.bloquer_creation_complexite);
                    $('#bloquer_modification_complexite').val(data.bloquer_modification_complexite);

                    // show items
                    sList(data.html_json);

                    // Prepare next iteration if needed
                    if (data.list_to_be_continued === 'yes') {
                        //set next start for query
                        $('#query_next_start').val(data.next_start);
                    } else {
                        $('#query_next_start').val(data.list_to_be_continued);

                        // display Categories if needed
                        if ($('.tr_fields') != undefined && data.displayCategories !== undefined && data.displayCategories != '') {
                            var liste = data.displayCategories.split(';');
                            for (var i=0; i<liste.length; i++) {
                                $('.itemCatName_'+liste[i]+', #newItemCatName_'+liste[i]+', #editItemCatName_'+liste[i]).removeClass('hidden');
                            }
                        }
                        if (data.saltkey_is_required == 1) {
                            if ($('.tr_fields') != undefined) $('.tr_fields').addClass('hidden');
                        }
                    }

                    proceed_list_update(stop_listing_current_folder);
                }
            }
        );
    }
}

function sList(data)
{
    var counter = 0;
    $.each((data), function(i, value) {
        var new_line = '',
            pwd_error = '',
            icon_all_can_modify = '',
            icon_login = '',
            icon_pwd = '',
            icon_favorite = '',
            item_flag = '',
            item_grippy = '';

        counter += 1;

        // Prepare item icon
        if (value.canMove === 1 && value.accessLevel === 0) {
            item_grippy = '<i class="fa fa-ellipsis-v item_draggable grippy"></i>&nbsp;&nbsp;';
        }

        // Prepare error message
        if (value.pw_status === 'encryption_error') {
            pwd_error = '<i class="fa fa-warning fa-lg text-danger infotip" title="<?php echo langHdl('pw_encryption_error'); ?>"></i>&nbsp;&nbsp;';
        }

        // Prepare anyone can modify icon
        if (value.anyone_can_modify === '1') {
            icon_all_can_modify = '<i class="fa fa-pencil fa-lg fa-clickable pointer infotip list-item-clicktoedit mr-2" title="<?php echo langHdl('item_menu_collab_enable'); ?>"></i>';
        }

        // Prepare mini icons
        if (value.copy_to_clipboard_small_icons === 1 && value.display_item === 1) {
            // Login icon
            if (value.login !== '') {
                icon_login = '<i class="fa fa-user fa-lg fa-clickable fa-clickable-login pointer infotip mr-2" title="<?php echo langHdl('item_menu_copy_login'); ?>" data-clipboard-text="' + sanitizeString(value.login) + '"></i>';
            }
            // Pwd icon
            if (value.pw !== '') {
                icon_pwd = '<i class="fa fa-lock fa-lg fa-clickable fa-clickable-password pointer infotip mr-2" title="<?php echo langHdl('item_menu_copy_pw'); ?>" data-clipboard-text="' + sanitizeString(value.pw) + '" data-item-id="' + value.item_id + '" data-item-label="' + value.label + '"></i>';
            }

            // Now check if pwd is empty. If it is then warn user
            if (value.pw === '') {
                pwd_error = '<i class="fa fa-exclamation-circle fa-lg text-warning infotip mr-2" title="<?php echo langHdl('password_is_empty'); ?>"></i>';
            }
        }

        // Prepare Favorite icon
        if (value.display_item === 1 && value.enable_favourites === 1) {
            if (value.is_favourited === 1) {
                icon_favorite = '<span title="Manage Favorite" class="pointer infotip item-favourite" data-item-id="' + value.item_id + '" data-item-favourited="1" id="">' +
                    '<i class="fa fa-star fa-lg text-warning"></i></span>';
            } else {
                icon_favorite = '<span title="Manage Favorite" class="pointer infotip item-favourite" data-item-id="' + value.item_id + '" data-item-favourited="0">' +
                    '<i class="fa fa-star-o fa-lg"></i></span>';
            }
        }

        // Prepare Description
        if (value.desc !== '') {
            value.desc = ' <span class="text-secondary small">- ' + value.desc + '</span>';
        }

        // Prepare flag
        if (value.expired === 1) {
            item_flag = '<i class="fa fa-ban fa-sm"></i>&nbsp;';
        }
        
        $('#teampass_items_list').append(
            '<tr class="row col-md-12 list-item-row" id="list-item-row_'+value.item_id+'" data-item-edition="' + value.open_edit + '" data-item-id="'+value.item_id+'" data-item-sk="'+value.sk+'" data-item-expired="'+value.expired+'" data-item-restricted="'+value.restricted+'" data-item-display="'+value.display+'" data-item-open-edit="'+value.open_edit+'" data-item-reload="'+value.reload+'" data-item-tree-id="'+value.tree_id+'">' +
            '<td class="col-md-1">' + item_grippy + '&nbsp;<i class="fa ' + value.perso + ' fa-sm"></i></td>' +
            '<td class="col-md-11 list-item-description" id="">' +
                '<span class="list-item-clicktoshow pointer" data-item-id="' + value.item_id + '">' +
                value.label + value.desc + '</span>' +
                '<div class="list-item-actions hidden text-right">' +
                    '<span style="margin-top:-13px;">' + pwd_error + icon_all_can_modify + icon_login + icon_pwd + icon_favorite +
                '</span></div>' +
            '</td>' +
            '</tr>'
        );
    });
    
    // Sort entries
    var $tbody = $('#table_teampass_items_list tbody');
    $tbody.find('tr').sort(function (a, b) {
        var tda = $(a).find('td:eq(1)').text();
        var tdb = $(b).find('td:eq(1)').text();
        // if a < b return 1
        return tda > tdb ? 1
            : tda < tdb ? -1   
            : 0;
    }).appendTo($tbody);

    // Trick for list with only one entry
    if (counter === 1) {
        $('#teampass_items_list').append('<tr class="row col-md-12"><td class="col-md-12">&nbsp;</td></tr>');
    }
    adjustElemsSize();    
}


function adjustElemsSize()
{
    console.log('adjust height');
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
        || ($('#new_listing_characteristics').val() !== ''
        && $('#query_next_start').val() !== 'end')
    ) {
        var tmp = $('#new_listing_characteristics').val().split(',');
        $('#new_listing_characteristics').val('');
        ListerItems(tmp[0], tmp[1], tmp[2], tmp[3]);
        return false;
    }
    
    if ($('#query_next_start').val() !== 'end') {
        //Check if nb of items do display > to 0
        if ($('#nb_items_to_display_once').val() > 0) {
            ListerItems($('#hid_cat').val(),'', parseInt($('#query_next_start').val()));
        }
    } else {
        //$('ul#full_items_list>li').tsort('',{order:'asc',attr:'name'});

        // Show tooltips
        $('.infotip').tooltip();

        alertify
            .success('<?php echo langHdl('success'); ?>', 1)
            .dismissOthers();

        

        // Prepare items dragable on folders
        /*$('.item_draggable').draggable({
            handle: '.grippy',
            cursor: 'move',
            opacity: 0.4,
            appendTo: 'body',
            stop: function(event, ui) {
                $(this).removeClass('ui-state-highlight');
            },
            start: function(event, ui) {
                $(this).addClass('ui-state-highlight');
            },
            helper: function(event) {
                return $('<div class="ui-widget-header" id="drop_helper">'+'<?php echo langHdl('drag_drop_helper'); ?>'+'</div>');
            }
        });
        $('.folder').droppable({
            hoverClass: 'ui-state-error',
            tolerance: 'pointer',
            drop: function(event, ui) {
                ui.draggable.addClass('hidden');
                LoadingPage();
                //move item
                $.post(
                    'sources/items.queries.php',
                    {
                        type      : 'move_item',
                        item_id   : ui.draggable.attr('id'),
                        folder_id : $(this).attr('id').substring(4),
                        key       : '<?php echo $_SESSION['key']; ?>'
                    },
                    function(data) {
                        LoadingPage();
                        // check if errors
                        if (data[0].error !== '') {
                            if (data[0].error === 'ERR_PSK_REQUIRED') {
                                displayMessage('<?php echo langHdl('psk_required'); ?>');
                            } else {
                                displayMessage('<?php echo langHdl('error_not_allowed_to'); ?>');
                            }
                            ui.draggable.removeClass('hidden');
                            return false;
                        }
                        //increment / decrement number of items in folders
                        $('#itcount_'+data[0].from_folder).text(Math.floor($('#itcount_'+data[0].from_folder).text())-1);
                        $('#itcount_'+data[0].to_folder).text(Math.floor($('#itcount_'+data[0].to_folder).text())+1);
                        $('#id_label, #item_viewed_x_times, #id_desc, #id_pw, #id_login, #id_email, #id_url, #id_files, #id_restricted_to, #id_tags, #id_kbs').html('');
                        displayMessage('<?php echo langHdl('alert_message_done'); ?>');
                    },
                    'json'
               );
            }
        });*/

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
function Details(itemDefinition, actionType)
{
    $('#request_ongoing').val('');
    // If a request is already launched, then kill new.
    if ($('#request_ongoing').val() !== '') {
        console.log($('#request_ongoing').val()+" -- "+(parseInt(Math.floor(Date.now() / 1000))-parseInt($('#request_ongoing').val())));
        request.abort();
        return;
    }

    // Init
    var itemId          = parseInt($(itemDefinition).data('item-id')) || '';
    var itemTreeId      = parseInt($(itemDefinition).data('item-tree-id')) || '';
    var itemSk          = parseInt($(itemDefinition).data('item-sk')) || 0;
    var itemExpired     = parseInt($(itemDefinition).data('item-expired')) || '';
    var itemRestricted  = parseInt($(itemDefinition).data('item-restricted-id')) || '';
    var itemDisplay     = parseInt($(itemDefinition).data('item-display')) || 0;
    var itemOpenEdit    = parseInt($(itemDefinition).data('item-open-edit')) || 0;
    var itemReload      = parseInt($(itemDefinition).data('item-reload')) || 0;

    // Store status query running
    $('#request_ongoing').val(Math.floor(Date.now() / 1000));

    // Select tab#1
    $('#form-item-nav-pills li:first-child a').tab('show');

    // If opening new item, reinit hidden fields
    if (parseInt($('#request_lastItem').val()) !== itemId) {
        $('#request_lastItem').val('');
        $('#item_editable').val('');
    }

    // Don't show details
    if (itemDisplay === 'no_display') {
        // Inform user
        alertify.alert()
            .setting({
                'label' : '<?php echo langHdl('ok'); ?>',
                'message' : '<i class="fa fa-info-circle text-error"></i>&nbsp;<?php echo langHdl('no_item_to_display'); ?>'
            })
            .show(); 

        // Clear ongoing request status
        $('#request_ongoing').val('');

        // Finished
        return false;
    }

    // to keep?
    if ($('#edit_restricted_to') !== undefined) {
        $('#edit_restricted_to').val('');
    }

    // Check if personal SK is needed and set
    if (($('#folder_requests_psk').val() === '1'
        && $('#personal_sk_set').val() === '0')
        && itemSk === 1
    ) {
        $('#set_personal_saltkey_warning').html('<div style="font-size:16px;"><span class="fa fa-warning fa-lg"></span>&nbsp;</span><?php echo langHdl('alert_message_personal_sk_missing'); ?></div>').show(1).delay(2500).fadeOut(1000);
        $('#div_set_personal_saltkey').dialog('open');

        showPersonalSKDialog();

        // Clear ongoing request status
        $('#request_ongoing').val('');

        // Finished
        return false;
    } else if ($('#folder_requests_psk').val() === '0' || ($('#folder_requests_psk').val() === '1' && $('#personal_sk_set').val() === '1')) {
        // Double click
        
        if (parseInt($('#request_lastItem').val()) === itemId && itemReload !== 1) {
            $('#request_ongoing').val('');            
            alertify
                    .message('<span class="fa fa-cog fa-spin fa-2x"></span>', 1)
                    .dismissOthers();

            return;
        } else {
            $('#timestamp_item_displayed').val('');
            var data = {
                'id'                : itemId,
                'folder_id'         : itemTreeId,
                'salt_key_required' : itemSk,
                'salt_key_set'      : $('#form-item-hidden-psk').val(),
                'expired_item'      : itemExpired,
                'restricted'        : itemRestricted,
                'page'              : 'items'
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
                function(data_raw) {
                    //decrypt data
                    try {
                        data = prepareExchangedData(data_raw , 'decode', '<?php echo $_SESSION['key']; ?>');
                    } catch (e) {
                        // error
                        $('#div_loading').addClass('hidden');
                        $('#request_ongoing').val('');
                        $('#div_dialog_message_text').html('An error appears. Answer from Server cannot be parsed!<br /><br />Returned data:<br />'+data_raw);
                        $('#div_dialog_message').show();
                        return;
                    }
                    console.log("RECEIVED");
                    console.log(data);
                    
                    if (data.error != '') {
                        $('#div_dialog_message_text').html('An error appears. Answer from Server cannot be parsed!<br /><br />Returned data:<br />'+data.error);
                        $('#div_dialog_message').show();
                    }

                    alertify
                        .success('<?php echo langHdl('success'); ?>', 1)
                        .dismissOthers();

                    // Update hidden fields
                    $('#form-item-hidden-isForEdit').val(1);
                    
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
                    
                    // Prepare forms
                    $('#items-list-card, #folders-tree-card').addClass('hidden');
                    if (actionType === 'show') {
                        $('.item-details-card').removeClass('hidden');
                        $('.form-item').addClass('hidden');
                        $('#form-item-suggestion-password').focus();
                    } else {
                        $('.form-item').removeClass('hidden');
                        $('.item-details-card').addClass('hidden');
                        $('#pwd-definition-size').val(data.pw.length);
                        userDidAChange = false;
                    }
                    
                    // Prepare card
                    $('#form-item-hidden-id').html(data.id);
                    $('#card-item-label, #form-item-title').html(data.label);
                    $('#form-item-label, #form-item-suggestion-label').val(data.label);
                    $('#card-item-description, #form-item-suggestion-description').html(data.description);
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
                    $('#form-item-password').focus();
                    $('#form-item-label').focus();
                    $('#form-item-folder').val(data.folder);

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
                        html_tags += '<span class="badge badge-success pointer tip mr-2" title="<?php echo langHdl('list_items_with_tag'); ?>" onclick="searchItemsWithTags(\"'+value+'\")"><i class="fa fa-tag fa-sm"></i>&nbsp;<span class="item_tag">'+value+'</span></span>';
                    });
                    if (html_tags === '') {
                        $('#card-item-tags').html('<?php echo langHdl('none'); ?>');
                    } else {
                        $('#card-item-tags').html(html_tags);
                    } 

                    $(data.links_to_kbs).each(function(index, value){
                        html_kbs += '<a class="badge badge-primary pointer tip mr-2" href="<?php echo $SETTINGS['cpassman_url']; ?>/index.php?page=kb&id='+value['id']+'"><i class="fa fa-map-pin fa-sm"></i>&nbsp;'+value['label']+'</a>';

                    });
                    if (html_kbs === '') {
                        $('#card-item-kbs').html('<?php echo langHdl('none'); ?>');
                    } else {
                        $('#card-item-kbs').html(html_kbs);
                    } 




                    if (data.categories.length === 0) {
                        $('#card-item-fields').html("<?php echo langHdl('no_custom_fields'); ?>");
                    } else {
                        $('#card-item-fields').html('');
                    }
                    
                    // Waiting
                    $('#card-item-attachments').html("<?php echo langHdl('please_wait'); ?>");


                    // Prepare clipboard - COPY LOGIN
                    if (data.login !== '') {
                        new Clipboard('#card-item-login-button', {
                            text: function() {
                                return (data.login);
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
                        $('#card-item-login-button').removeClass('hidden');
                    } else {
                        $('#card-item-login-button').addClass('hidden');
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
                        new Clipboard('#card-item-email-button', {
                            text: function() {
                                return (data.email);
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
                        $('#card-item-email-button').removeClass('hidden');
                    } else {
                        $('#card-item-email-button').addClass('hidden');
                    }
                    
                    // Manage Fields
                    if (data.fields.length === 0) {
                        $('#tab_4').html(
                            '<div class="alert alert-info">' +
                            '<h5><i class="icon fa fa-info mr-3"></i><?php echo langHdl('information'); ?></h5>' +
                            '<?php echo langHdl('no_fields'); ?>' +
                            '</div>'
                        );
                    } else {

                    }


                    // --------

                    // reset password shown info
                    $('#pw_shown').val('0');

                    // show some info on top
                    if (data.auto_update_pwd_frequency != '0') var auto_update_pwd = '<i class="fa fa-shield tip" title="<?php echo langHdl('server_auto_update_password_enabled_tip'); ?>"></i>&nbsp;<b>'+data.auto_update_pwd_frequency+'</b>&nbsp;|&nbsp;';
                    else var auto_update_pwd = '';
                    $('#item_viewed_x_times').html(auto_update_pwd+'&nbsp;<i class="fa fa-sticky-note-o tip" title="Number of times item was displayed"></i>&nbsp;<b>'+data.viewed_no+'</b>');

                    // Show timestamp
                    $('#timestamp_item_displayed').val(data.timestamp);

                    //Change the class of this selected item
                    $('#form-item-hidden-id').val(data.id);
                    
                    
                    $('#item_details_nok').addClass('hidden');
                    $('#fileclass'+data.id).addClass('fileselected');
                    $('item_editable').val(0);

                    if (data.show_details == '1' && data.show_detail_option != '2') {
                        //unprotect data
                        data.login = unsanitizeString(data.login);

                        $('#id_files').html('');

                        //Display details
                        $('#id_label').html(data.label);
                        $('#hid_label').val(unsanitizeString(data.label));
                        if (data.pw === '') {
                            $('#id_pw').html('');
                        } else {
                            $('#id_pw').html('<?php echo $var['hidden_asterisk']; ?>');
                        }
                        
                        $('#hid_pw').text(unsanitizeString(data.pw));
                        if (data.url != '') {
                            $('#id_url').html(data.url+data.link);
                            $('#hid_url').val(data.url);
                        } else {
                            $('#id_url').html('');
                            $('#hid_url').val('');
                        }
                        $('#id_desc').html(data.description);
                        $('#hid_desc').val(data.description);
                        $('#id_login').html(data.login);
                        $('#hid_login').val(data.login);
                        $('#id_email').html(data.email);
                        $('#hid_email').val(data.email);

                        

                        //$('#hid_tags').val(item_tag);
                        $('#hid_anyone_can_modify').val(data.anyone_can_modify);
                        $('#id_categorie').val(data.folder);
                        $('#id_item').val(data.id);
                        $('#id_kbs').html(data.links_to_kbs);
                        $('.infotip').tooltip();

                        // ---
                        // Show Field values
                        /*$('.fields').val('');
                        $('.fields_div, .fields').html('');
                        // If no CF then hide
                        if (data.fields === '') {
                            $('.tr_fields').addClass('hidden');
                        } else {
                            $('.tr_cf, .tr_fields').addClass('hidden');
                            var liste = data.fields.split('_|_');
                            for (var i=0; i<liste.length; i++) {
                                var field = liste[i].split('~~');
                                if (data.template_id !== '') {
                                    if (data.template_id === field[2]) {
                                        $('#cf_tr_' + field[0] + ', #tr_catfield_' + field[2]).removeClass('hidden');
                                    }
                                } else {
                                    $('#cf_tr_' + field[0] + ', #tr_catfield_' + field[2]).removeClass('hidden');
                                }
                                
                                $('#hid_field_' + field[0] + '_' + field[2]).text(field[1].replace(/<br ?\/?>/g,''));
                                if (field[4] === '1') {
                                    $('#id_field_' + field[0] + '_' + field[2])
                                        .html('<?php echo $var['hidden_asterisk']; ?>');
                                } else {
                                    $('#id_field_' + field[0] + '_' + field[2])
                                        .text(field[1]);
                                }
                            }
                        }

                        // 
                        if (data.template_id !== '') {
                            $('.default_item_field, .tr_fields_header').addClass('hidden');
                        } else {
                            $('.default_item_field, .tr_fields_header').removeClass('hidden');
                        }

                        // Template id
                        $('#template_selected_id').val(data.template_id);

                        //Anyone can modify button
                        if (data.anyone_can_modify == '1') {
                            $('#edit_anyone_can_modify').attr('checked', true);
                            $('#new_history_entry_form').show();
                        } else {
                            $('#edit_anyone_can_modify').attr('checked', false);
                            $('#new_history_entry_form').addClass('hidden');
                        }
*/
                        //Show to be deleted in case activated
                        if (data.to_be_deleted == 'not_enabled') {
                            $('#edit_to_be_deleted').addClass('hidden');
                        } else {
                            $('#edit_to_be_deleted').show();
                            if (data.to_be_deleted != '') {
                                $('#edit_enable_delete_after_consultation').attr('checked',true);
                                if (data.to_be_deleted_type == 2) {
                                    $('#edit_times_before_deletion').val('');
                                    $('#edit_deletion_after_date').val(data.to_be_deleted);
                                } else {
                                    $('#edit_times_before_deletion').val(data.to_be_deleted);
                                    $('#edit_deletion_after_date').val('');
                                }
                            } else {
                                $('#edit_enable_delete_after_consultation').attr('checked',false);
                                $('#edit_times_before_deletion, #edit_deletion_after_date').val('');
                            }
                        }

                        //manage buttons
                        if ($('#user_is_read_only').val() == 1) {
                            $('#menu_button_add_item, #menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item').attr('disabled', 'disabled');
                        } else if (data.user_can_modify == 0) {
                            $('#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item').attr('disabled', 'disabled');
                        } else if (data.restricted == '1' || data.user_can_modify == '1') {
                            //$('#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item').prop('disabled', false);
                            var param = '#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item';
                            $('#new_history_entry_form').show();
                        } else {
                            //$('#menu_button_add_item, #menu_button_copy_item').prop('disabled', false);
                            var param = '#menu_button_del_item, #menu_button_copy_item';
                            $('#new_history_entry_form').show();
                        }
                        //$('#menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link, #menu_button_history').prop('disabled', false);

                        // disable share button for personal folder
                        if ($('#folder_requests_psk').val() == 1) {
                            $('#menu_button_share, #menu_button_otv').attr('disabled', 'disabled');
                        } else {
                            $('#menu_button_share, #menu_button_otv').prop('disabled', false);
                        }

                        //Manage to deleted information
                        if (data.to_be_deleted != 0 && data.to_be_deleted != null && data.to_be_deleted != 'not_enabled') {
                            $('#item_extra_info')
                                .html('<b><i class="fa fa-bell-o mi-red"></i></b>&nbsp;')
                                .attr('title', '<?php echo langHdl('automatic_deletion_activated'); ?>');
                            $('#item_extra_info').tooltip();
                        } else {
                            $('#item_extra_info').html('');
                        }

                        if (data.notification_status == 0 && data.id_user == <?php echo $_SESSION['user_id']; ?>) {
                            $('#menu_button_notify')
                                .prop('disabled', false)
                                .attr('title','<?php echo langHdl('enable_notify'); ?>')
                                .attr('onclick','notify_click(\'true\')');
                            $('#div_notify').attr('class', '<i class="fa fa-bell mi-green"></i>&nbsp;');
                        } else if (data.notification_status == 1 && data.id_user == <?php echo $_SESSION['user_id']; ?>) {
                            $('#menu_button_notify')
                                .prop('disabled', false)
                                .attr('title','<?php echo langHdl('disable_notify'); ?>')
                                .attr('onclick','notify_click(\'false\')');
                            $('#div_notify').attr('class', '<i class="fa fa-bell-slash mi-red"></i>&nbsp;');
                            $('#item_extra_info').html('<i><i class=\'fa fa-bell mi-green\"></i>&nbsp;<?php echo langHdl('notify_activated'); ?></i>');
                        } else {
                            $('#menu_button_notify').attr('disabled', 'disabled');
                            $('#div_notify').attr('class', '<i class="fa fa-bell mi-green"></i>&nbsp;');
                        }

                        //Prepare clipboard copies
                        if (data.pw != '') {
                            /*var clipboard_copy= new Clipboard('.btn-copy-clipboard');
                            clipboard_copy.on('success', function(e) {
                                console.log('coucou')
                                itemLog(
                                    'at_password_copied',
                                    e.trigger.dataset.clipboardId,
                                    $('#item_label_'+e.trigger.dataset.clipboardId).text()
                                );

                                e.clearSelection();
                            });*/

                            $('#button_quick_pw_copy').removeClass('hidden');
                        } else {
                            $('#button_quick_pw_copy').addClass('hidden');
                        }
                        if (data.login != '') {
                            var clipboard_login = new Clipboard('#menu_button_copy_login, #button_quick_login_copy', {
                                text: function() {
                                    return (data.login);
                                }
                            });
                            clipboard_login.on('success', function(e) {
                                $('#message_box').html('<?php echo langHdl('login_copied_clipboard'); ?>').show().fadeOut(1000);

                                e.clearSelection();
                            });
                            $('#button_quick_login_copy').removeClass('hidden');
                        } else {
                            $('#button_quick_login_copy').addClass('hidden');
                        }
                        // #525
                        if (data.url != '') {
                            var clipboard_url = new Clipboard('#menu_button_copy_url', {
                                text: function() {
                                    return unsanitizeString(data.url);
                                }
                            });
                            clipboard_url.on('success', function(e) {
                                $('#message_box').html('<?php echo langHdl('url_copied_clipboard'); ?>').show().fadeOut(1000);

                                e.clearSelection();
                            });
                        }

                        //prepare link to clipboard
                        var clipboard_link = new Clipboard('#menu_button_copy_link', {
                            text: function() {
                                return '<?php echo $SETTINGS['cpassman_url']; ?>'+'/index.php?page=items&group='+data.folder+'&id='+data.id;
                            }
                        });
                        clipboard_link.on('success', function(e) {
                            $('#message_box').html('<?php echo langHdl('url_copied'); ?>').show().fadeOut(1000);

                            e.clearSelection();
                        });


                        //set if user can edit
                        if (data.restricted == '1' || data.user_can_modify == '1') {
                            $('#item_editable').val(1);
                        }
                        
                        //Manage double click

                        // tags
                        $('.round-grey').addClass('ui-state-highlight ui-corner-all');

                        // continue loading data
                        showDetailsStep2(itemId, param, actionType);
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
                    $('#request_ongoing').val('');

                    // Double click managelment
                }
            );
       }

        //Store Item id shown
        $('#request_lastItem').val(itemId);
    }
}


/*
* Loading Item details step 2
*/
function showDetailsStep2(id, param, actionType)
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
            try {
                data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
            } catch (e) {
                // error
                alertify
                    .alert()
                    .setting({
                        'label' : '<?php echo langHdl('ok'); ?>',
                        'message' : '<i class="fa fa-info-circle text-error mr-2"></i>An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />' + data
                    })
                    .show();
                return false;
            }
            console.log('RECEIVED STEP2')
            console.log(data);

            // Attachments
            if (data.attachments.length === 0) {
                $('#card-item-attachments').html('<?php echo langHdl('no_attachment'); ?>');
            } else {
                var html = '',
                    htmlFull = '',
                    counter = 1;
                $.each(data.attachments, function(i, value) {
                    if (value.is_image === 1) {
                        html += '<div class=""><i class="fa ' + value.icon + ' mr-2" /></i><a class="" href="#' + value.id + '" title="' + value.filename + '">' + value.filename + '</a></div>';
                    } else {
                        html += '<div class=""><i class="fa ' + value.icon + ' mr-2" /></i><a class="" href="sources/downloadFile.php?name=' + encodeURI(value.filename) + '&key=<?php echo $_SESSION['key']; ?>&key_tmp=' + value.key + '&fileid=' + value.id + '">' + value.filename + '</a></div>';
                    }
                    
                    if (counter === 1) {
                        htmlFull += '<div class="row">';
                    }

                    htmlFull += '<div class="col-6"><div class="info-box bg-secondary-gradient">' +                            
                        '<span class="info-box-icon bg-info"><i class="fa fa-' + value.icon + '"></i></span>' +
                        '<div class="info-box-content"><span class="info-box-text">' + value.filename + '</span>' +
                        '<span class="info-box-text"><i class="fa fa-trash pointer"></i></span></div>' +
                        '</div></div>';

                    
                    if (counter === 2) {
                        htmlFull += '</div>';
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
            

            // -> MANAGE RESTRICTIONS
            var html_restrictions = '',
                preselect_list = [];
            
            // Clear existing values
            $('#form-item-restrictedto, #form-item-anounce').empty();

            // Users restriction list
            $(data.users_list).each(function(index, value) {
                // Prepare list for FORM
                $("#form-item-restrictedto")
                    .append('<option value="' + value.id + '" class="restriction_is_user">' + value.name + '</option>');
                // Prepare list of emailers
                $('#form-item-anounce').append('<option value="'+value.email+'">'+value.name+'</option>');
                // Select this droplist
                if ($.inArray(value.id, JSON.parse($('#form-item-restrictedToUsers').val())) !== -1) {
                    // 
                    preselect_list.push(value.id);
                    
                    // Prepare list for CARD
                    html_restrictions += '<span class="badge badge-info mr-2 mb-1"><i class="fa fa-user fa-sm mr-1"></i>' + value.name + '</span>';
                }
            });
            if (data.setting_restricted_to_roles === 1) {
                //add optgroup
                var optgroup = $('<optgroup label="<?php echo langHdl('users'); ?>">');
                $(".restriction_is_user").wrapAll(optgroup);
            
                // Now add the roles to the list
                $(data.roles_list).each(function(index, value) {
                    $("#form-item-restrictedto")
                            .append('<option value="role_' + value.id + '" class="restriction_is_role">' + value.title + '</option>');

                    if ($.inArray(value.id, JSON.parse($('#form-item-restrictedToRoles').val())) !== -1) {
                        // 
                        preselect_list.push('role_' + value.id);
                        // Prepare list for CARD
                        html_restrictions += 
                        '<span class="badge badge-info mr-2 mb-1"><i class="fa fa-group fa-sm mr-1"></i>' + value.title + '</span>';
                    }
                });
                /// Add a group label for Groups
                $('.restriction_is_role').wrapAll($('<optgroup label="<?php echo langHdl('roles'); ?>">'));
            }

            if (html_restrictions === '') {
                $('#card-item-restrictedto').html('<?php echo langHdl('no_special_restriction'); ?>');
            } else {
                $('#card-item-restrictedto').html(html_restrictions);
            } 

            // Now do the pre-selection            
            $('#form-item-restrictedto')
                .val(preselect_list);

            

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
                $('#item_extra_info').prepend('<i class="fa fa-lightbulb-o fa-sm mi-yellow tip" title="<?php echo langHdl('item_has_change_proposal'); ?>"></i>&nbsp;');
            }


            $('.infotip').tooltip();

            // Now load History
            if (actionType === 'show') {
                $.post(
                    "sources/items.queries.php",
                    {
                        type    : "load_item_history",
                        item_id : $("#form-item-hidden-id").val(),
                        key     : "<?php echo $_SESSION['key']; ?>"
                    },
                    function(data) {
                        //decrypt data
                        try {
                            data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
                        } catch (e) {
                            // error
                            alertify.alert()
                                .setting({
                                    'label' : '<?php echo langHdl('ok'); ?>',
                                    'message' : '<i class="fa fa-info-circle text-error"></i>&nbsp;<?php echo langHdl('no_item_to_display'); ?>'
                                })
                                .show(); 
                            return false;
                        }
                        console.info('History:');
                        console.log(data);
                        if (data.error === '') {
                            var html = '';
                            $.each(data.history, function(i, value) {
                                html += '<div class="direct-chat-msg"><div class="direct-chat-info clearfix">' +
                                '<span class="direct-chat-name float-left">' + value.login + '</span>' +
                                '<span class="direct-chat-timestamp float-right">' + value.date + '</span>' +
                                '</div>' +
                                '<img class="direct-chat-img" src="' + value.avatar + '" alt="Message User Image">' +
                                '<div class="direct-chat-text">' + value.action +
                                ' | ' + value.detail + '</div></div>';
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
            id      : $("#form-item-hidden-id").val(),
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

    var funcReturned = null;
    $.post(
        "sources/items.queries.php",
        {
            type    : "get_complixity_level",
            groupe  : val,
            context : context,
            item_id : $("#form-item-hidden-id").val()
        },
        function(data) {
            try {
                data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
            } catch (e) {
                // error
                alertify
                    .alert()
                    .setting({
                        'label' : '<?php echo langHdl('ok'); ?>',
                        'message' : '<i class="fa fa-info-circle text-error"></i>&nbsp;<?php echo langHdl('no_item_to_display'); ?>'
                    })
                    .show(); 
                return false;
            }
            console.info('GET COMPLEXITY LEVEL');
            console.log(data);

            funcReturned = 1;
            if (data.error == undefined
                || data.error === ''
            ) {
                $('#complexite_groupe').val(data.val);
                $('#selected_folder_is_personal').val(data.personal);
                $('#card-item-minimum-complexity').html(data.complexity);
                $('#card-item-visibility').html(data.visibility);
            } else if (data.error === 'no_edition_possible') {
                $('#div_dialog_message_text').html(data.error_msg);
                $('#div_dialog_message').dialog('open');
                funcReturned = 0;
            } else if (data.error === 'user_is_readonly') {
                displayMessage(data.message);
                funcReturned = 0;
            } else if (data.error === 'no_folder_creation_possible'
                || data.error === 'no_folder_edition_possible'
                || data.error === 'delete_folder') {
                displayMessage('<i class="fa fa-warning"></i>&nbsp;' + data.error_msg);
                $('#div_loading').addClass('hidden');
                funcReturned = 0;
            } else {
                $('#div_formulaire_edition_item').dialog('close');
                $('#div_dialog_message_text').html(data.error_msg);
                $('#div_dialog_message').dialog('open');
            }

            // Prepare list of users where needed
            $('#form-item-restrictedto, #form-item-anounce').empty().val('').change();
            // Users restriction list
            var preselect_list = [];
            $(data.usersList).each(function(index, value) {
                // Prepare list for FORM
                $("#form-item-restrictedto")
                    .append('<option value="' + value.id + '" class="restriction_is_user">' + value.name + '</option>');
                // Prepare list of emailers
                $('#form-item-anounce').append('<option value="'+value.email+'">'+value.name+'</option>');
            });
            if (data.setting_restricted_to_roles === 1) {
                //add optgroup
                var optgroup = $('<optgroup label="<?php echo langHdl('users'); ?>">');
                $(".restriction_is_user").wrapAll(optgroup);
            
                // Now add the roles to the list
                $(data.rolesList).each(function(index, value) {
                    $("#form-item-restrictedto")
                        .append('<option value="role_' + value.id + '" class="restriction_is_role">' + value.title + '</option>');
                });
                /// Add a group label for Groups
                $('.restriction_is_role').wrapAll($('<optgroup label="<?php echo langHdl('roles'); ?>">'));
            }

            // Prepare Select2
            $('.select2').select2({
                language: '<?php echo $_SESSION['user_language_code']; ?>'
            });
        }
    );
    $.ajaxSetup({async: true});
    return funcReturned;
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
                            'label' : '<?php echo langHdl('ok'); ?>',
                            'message' : '<i class="fa fa-info-circle mr-2"></i>' + data.error_msg
                        })
                        .show(); 
                    return false;
               } else {
                   $("#form-item-password").val(data.key).focus();
               }
        }
   );
});

$('#item-button-password-copy').click(function() {
    $('#form-item-password-confirmation').val($('#form-item-password').val());
});
</script>
