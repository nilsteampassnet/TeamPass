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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'profile', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}
?>


<script type='text/javascript'>

// Preapre select drop list
$('#roles-list.select2').select2({
    language    : '<?php echo $_SESSION['user_language_code']; ?>',
    placeholder : '<?php echo langHdl('select_a_role'); ?>',
    allowClear: true
});

$('#form-complexity-list.select2').select2({
    language    : '<?php echo $_SESSION['user_language_code']; ?>'
});

//iCheck for checkbox and radio inputs
$('#card-folder-definition, #card-folder-deletion input[type="checkbox"]').iCheck({
    checkboxClass: 'icheckbox_flat-blue'
})


// On role selection
$(document).on('change', '#roles-list', function() {
    if ($(this).find(':selected').text() === '') {
        // Hide
        $('#card-role-details').addClass('hidden');
    } else {
        var selectedRoleId = $(this).find(':selected').val();

        // Prepare card header
        $('#role-detail-header').html(
            $(this).find(':selected').text() +
            ' <i class="' + $(this).find(':selected').data('complexity-icon') + ' infotip ml-3" ' +
            'title="<?php echo langHdl('complexity'); ?>: ' +
            $(this).find(':selected').data('complexity-text') + '"></i>' +
            (parseInt($(this).find(':selected').data('allow-edit-all')) === 1 ?
            '<i class="ml-3 fas fa-exclamation-triangle text-warning infotip" ' +
            'title="<?php echo langHdl('role_can_edit_any_visible_item'); ?>"></i>'
            : '')
        );

        $('.infotip').tooltip();

        refreshMatrix(selectedRoleId);
    }
});

/**
 */
function refreshMatrix(selectedRoleId)
{
    // Show
    $('#card-role-details').removeClass('hidden');

    // 
    $('#role-details').html('');

    // Show spinner
    alertify
        .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();

    // Build matrix
    $.post(
        'sources/roles.queries.php',
        {
            type    : 'build_matrix',
            role_id : selectedRoleId,
            key     : '<?php echo $_SESSION['key']; ?>'
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
                // Build html
                var newHtml = '',
                    ident = '',
                    path = '';
                $(data.matrix).each(function(i, value) {
                    // Access
                    access = '';
                    if (value.access === 'W') {
                        access = '<i class="fas fa-indent mr-2 text-success infotip" title="<?php echo langHdl('add_allowed'); ?>"></i>' +
                            '<i class="fas fa-pen mr-2 text-success infotip" title="<?php echo langHdl('edit_allowed'); ?>"></i>' +
                            '<i class="fas fa-eraser mr-2 text-success infotip" title="<?php echo langHdl('delete_allowed'); ?>"></i>';
                    } else if (value.access === 'ND') {
                        access = '<i class="fas fa-indent mr-2 text-success infotip" title="<?php echo langHdl('add_allowed'); ?>"></i>' +
                            '<i class="fas fa-pen mr-2 text-success infotip" title="<?php echo langHdl('edit_allowed'); ?>"></i>' +
                            '<i class="fas fa-eraser mr-2 text-danger infotip" title="<?php echo langHdl('delete_not_allowed'); ?>"></i>';
                    } else if (value.access === 'NE') {
                        access = '<i class="fas fa-indent mr-2 text-success infotip" title="<?php echo langHdl('add_allowed'); ?>"></i>' +
                            '<i class="fas fa-pen mr-2 text-danger infotip" title="<?php echo langHdl('edit_not_allowed'); ?>"></i>' +
                            '<i class="fas fa-eraser mr-2 text-success infotip" title="<?php echo langHdl('delete_allowed'); ?>"></i>';
                    } else if (value.access === 'NDNE') {
                        access = '<i class="fas fa-indent mr-2 text-success infotip" title="<?php echo langHdl('add_allowed'); ?>"></i>' +
                            '<i class="fas fa-pen mr-2 text-danger infotip" title="<?php echo langHdl('edit_not_allowed'); ?>"></i>' +
                            '<i class="fas fa-eraser mr-2 text-danger infotip" title="<?php echo langHdl('delete_anot_llowed'); ?>"></i>';
                    } else if (value.access === 'R') {
                        access = '<i class="fas fa-book-reader mr-2 text-warning infotip" title="<?php echo langHdl('read_only'); ?>"></i>';
                    } else {
                        access = '<i class="fas fa-ban mr-2 text-danger infotip" title="<?php echo langHdl('no_access'); ?>"></i>';
                    }

                    // Build path
                    path = '';
                    $(value.path).each(function(j, valuePath) {
                        if (path === '') {
                            path = valuePath;
                        } else {
                            path += ' / ' + valuePath;
                        }
                    });

                    // Finalize
                    newHtml += '<tr>' +
                        '<td width="35px"><input type="checkbox" id="cb-' + value.id + '" data-id="' + value.id + '" class="folder-select"></td>' +
                        '<td class="pointer modify" data-id="' + value.id + '" data-access="' + value.access + '">' + value.title + '</td>' +
                        '<td class="font-italic pointer modify" data-id="' + value.id + '" data-access="' + value.access + '">' + path + '</td>' +
                        '<td class="pointer modify" data-id="' + value.id + '" data-access="' + value.access + '">' + access + '</td>' +
                        '</tr>'
                });

                // Show result
                $('#role-details').html(
                    '<table id="table-role-details" class="table table-hover table-striped" style="width:100%"><tbody>' +
                    newHtml +
                    '</tbody></table>'
                );

                //iCheck for checkbox and radio inputs
                $('#role-details input[type="checkbox"]').iCheck({
                    checkboxClass: 'icheckbox_flat-blue'
                });

                $('.infotip').tooltip();

                // Inform user
                alertify
                    .success('<?php echo langHdl('done'); ?>', 1)
                    .dismissOthers();
            }
        }
    );
}

var operationOngoin = false;
$(document).on('ifChecked', '.folder-select', function() {
    if (operationOngoin === false) {
        operationOngoin = true;
        
        // Show spinner
        alertify
            .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();

        // Show selection of folders
        var selected_cb = $(this),
            id = $(this).data('id');

        // Now get subfolders
        $.post(
            'sources/folders.queries.php',
            {
                type    : 'select_sub_folders',
                id      : id,
                key     : '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
                // check/uncheck checkbox
                if (data.subfolders !== '') {
                    $.each(JSON.parse(data.subfolders), function(i, value) {
                        $('#cb-' + value).iCheck('check');
                    });
                }
                operationOngoin = false;

                alertify
                    .success('<?php echo langHdl('done'); ?>', 1)
                    .dismissOthers();
            }
        );
    }
});

$(document).on('ifUnchecked', '.folder-select', function() {
    if (operationOngoin === false) {
        operationOngoin = true;
        
        // Show spinner
        alertify
            .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();
            
        // Show selection of folders
        var selected_cb = $(this),
            id = $(this).data('id');

        // Now get subfolders
        $.post(
            'sources/folders.queries.php',
            {
                type    : 'select_sub_folders',
                id      : id,
                key     : '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
                // check/uncheck checkbox
                if (data.subfolders !== '') {
                    $.each(JSON.parse(data.subfolders), function(i, value) {
                        $('#cb-' + value).iCheck('uncheck');
                    });
                }
                operationOngoin = false;

                alertify
                    .success('<?php echo langHdl('done'); ?>', 1)
                    .dismissOthers();
            }
        );
    }
});

/**
 * Handle the form for folder access rights change
 */
var currentFolderEdited = '';
$(document).on('click', '.modify', function() {
    // Manage edition of rights card
    if (currentFolderEdited !== '' && currentFolderEdited !== $(this).data('id')) {
        $('.temp-row').remove();
    } else if (currentFolderEdited === $(this).data('id')) {
        return false;
    }

    // Init
    var currentRow = $(this).closest('tr'),
        folderAccess = $(this).data('access');
    currentFolderEdited = $(this).data('id');

    // Now show
    $(currentRow).after(
        '<tr class="temp-row"><td colspan="' + $(currentRow).children('td').length + '">' +
        '<div class="card card-warning card-outline">' +
        '<div class="card-body">' +
        '<div class="form-group ml-2 mt-2"><?php echo langHdl('right_types_label'); ?></div>' +
        '<div class="form-group ml-2">' +
        '<input type="radio" class="form-radio-input form-control ml-1" id="right-write" name="right" data-type="W">' +
        '<label class="form-radio-label pointer mr-2" for="right-write"><?php echo langHdl('write'); ?></label>' +
        '<input type="radio" class="form-radio-input form-control ml-1" id="right-read" name="right" data-type="R">' +
        '<label class="form-radio-label pointer mr-2" for="right-read"><?php echo langHdl('read'); ?></label>' +
        '<input type="radio" class="form-radio-input form-control ml-1" id="right-noaccess" name="right" data-type="">' +
        '<label class="form-radio-label pointer" for="right-noaccess"><?php echo langHdl('no_access'); ?></label>' +
        '</div>' +
        '<div class="form-group ml-2" id="folder-rights-tuned">' +
        '<div class="form-check">' +
        '<input type="checkbox" class="form-check-input form-control" id="right-no-delete">' +
        '<label class="form-check-label pointer ml-2" for="right-no-delete"><?php echo langHdl('role_cannot_delete_item'); ?></label>' +
        '</div>' +
        '<div class="form-check">' +
        '<input type="checkbox" class="form-check-input form-control" id="right-no-edit">' +
        '<label class="form-check-label pointer ml-2" for="right-no-edit"><?php echo langHdl('role_cannot_edit_item'); ?></label>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '<div class="card-footer">' +
        '<button type="button" class="btn btn-warning tp-action" data-action="submit" data-id="' + currentFolderEdited + '"><?php echo langHdl('submit'); ?></button>' +
        '<button type="button" class="btn btn-default float-right tp-action" data-action="cancel"><?php echo langHdl('cancel'); ?></button>' +
        '</div>' +
        '</div>' +
        '</td></tr>'
    );

    // Prepare iCheck format for checkboxes
    $('input[type="checkbox"].form-check-input, input[type="radio"].form-radio-input').iCheck({
        radioClass      : 'iradio_flat-orange',
        checkboxClass   : 'icheckbox_flat-orange',
    });

    // Uncheck the checkboxes
    $('#right-no-delete').iCheck('uncheck');
    $('#right-no-edit').iCheck('uncheck');

    // Prepare radio and checkboxes depending on existing right on selected folder
    if (folderAccess === 'R') {
        $('#right-read').iCheck('check');
        $('.form-check-input').iCheck('disable');
    } else if (folderAccess === 'none') {
        $('#right-noaccess').iCheck('check');
        $('.form-check-input').iCheck('disable');
    } else if (folderAccess === 'W') {
        $('#right-write').iCheck('check');
    } else if (folderAccess === 'ND') {
        $('#right-write').iCheck('check');
        $('#right-no-delete').iCheck('check');
    } else if (folderAccess === 'NE') {
        $('#right-write').iCheck('check');
        $('#right-no-edit').iCheck('check');
    } else if (folderAccess === 'WNDNE') {
        $('#right-write').iCheck('check');
        $('#right-no-edit').iCheck('check');
    }
});

/**
 * Handle the rights change buttons
 */
$(document).on('click', 'button', function() {
    // Init
    var selectedFolderText = $('#roles-list').find(':selected').text();

    if ($(this).data('action') === 'cancel-edition') {
        $('#card-folder-definition').addClass('hidden');
        $('#form-folder-label').val('');
    } else if ($(this).data('action') === 'cancel-deletion') {
        $('#card-role-details').removeClass('hidden');
        $('#card-folder-deletion').addClass('hidden');
        $('#form-folder-delete').iCheck('uncheck');
    } else if ($(this).data('action') === 'submit-edition') {
        // STORE ROLE CHANGES

        // Show spinner
        alertify
            .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();

        // Prepare data
        var data = {
            'label'         : $('#form-folder-label').val(),
            'complexity'    : $('#form-complexity-list').val(),
            'folderId'      : $('#roles-list').find(':selected').val(),
            'allowEdit'     : $('#form-folder-privilege').prop("checked") === true ? 1 : 0,
            'action'        : store.get('teampassApplication').formUserAction
        }
        var oldLabel = selectedFolderText;
        
        // Launch action
        $.post(
            'sources/roles.queries.php',
            {
                type    : 'change_folder_definition',
                data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key     : '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {//decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
                console.log(data);

                if (data.error === true) {
                    // ERROR
                    alertify
                        .error(
                            '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' + data.message,
                            0
                        )
                        .dismissOthers();
                } else {
                    if (store.get('teampassApplication').formUserAction === 'edit_folder') {
                        // Adapt card header
                        $('#role-detail-header').html(
                            $('#form-folder-label').val() +
                            '<i class="' + data.icon + ' infotip ml-3" ' +
                            'title="<?php echo langHdl('complexity'); ?>: ' +
                            $('#form-complexity-list').find(':selected').text() + '"></i>' +
                            (parseInt(data.allow_pw_change) === 1 ?
                            '<i class="ml-3 fas fa-exclamation-triangle text-warning infotip" ' +
                            'title="<?php echo langHdl('role_can_edit_any_visible_item'); ?>"></i>'
                            : '')
                        );
                        $('.infotip').tooltip();
                    } else {
                        // Add new folder to roles listbox
                        var newOption = new Option(
                            $('#form-folder-label').val(),
                            data.new_role_id,
                            false,
                            true
                        );
                        $('#roles-list').append(newOption).trigger('change');
                    }

                    // Manage change in select
                    $("#roles-list").select2("destroy");
                    var selectedOption = $('#roles-list option[value=' + $('#roles-list').find(':selected').val() + ']');
                    selectedOption.text($('#form-folder-label').val());
                    selectedOption.data('allow-edit-all', data.allow_pw_change);
                    selectedOption.data('complexity-text', data.text);
                    selectedOption.data('complexity-icon', data.icon);
                    selectedOption.data('complexity', data.value);
                    $("#roles-list").select2();

                    // Misc
                    $('#card-folder-definition').addClass('hidden');
                    $('#form-folder-label').val('');

                    // OK
                    alertify
                        .success('<?php echo langHdl('done'); ?>', 1)
                        .dismissOthers();
                }
            }
        );
        //---
    } else if ($(this).data('action') === 'edit') {
        // SHOW ROLE EDITION FORM
        if ($('#card-role-details').hasClass('hidden') === false) {
            $('#form-folder-label').val(selectedFolderText);
            $('#form-complexity-list').val($('#roles-list').find(':selected').data('complexity')).trigger('change');
            
            if (parseInt($('#roles-list').find(':selected').data('allow-edit-all')) === 1) {
                $('#form-folder-privilege').iCheck('check');
            } else {
                $('#form-folder-privilege').iCheck('uncheck');
            }

            // What type of form? Edit or new user
            store.update(
                'teampassApplication',
                function (teampassApplication)
                {
                    teampassApplication.formUserAction = 'edit_folder';
                }
            );

            // Show form
            $('#card-folder-definition').removeClass('hidden');
        }
        //---
    } else if ($(this).data('action') === 'delete') {
        // SHOW ROLE DELETION FORM
        if ($('#card-role-details').hasClass('hidden') === false) {
            $('#span-folder-delete').text('- <?php echo langHdl('folder'); ?> ' + selectedFolderText);

            $('#card-folder-deletion').removeClass('hidden');
            $('#card-folder-definition, #card-role-details').addClass('hidden');
        }
    } else if ($(this).data('action') === 'new') {
        // SHOW NEW FOLDER DEFINITION
        $('#form-folder-label').val('');
        $('#form-folder-privilege').iCheck('uncheck');
        $("#form-complexity-list").val('').trigger('change');

        // What type of form? Edit or new user
        store.update(
            'teampassApplication',
            function (teampassApplication)
            {
                teampassApplication.formUserAction = 'add_folder';
            }
        );
        
        $('#card-folder-definition').removeClass('hidden');
        //---
    } else if ($(this).data('action') === 'cancel') {
        $('.temp-row').remove();
        //---
    } else if ($(this).data('action') === 'submit') {
        // Store the new access rights for the selected folder(s)
        
        // Show spinner
        alertify
            .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();

        // Get list of selected folders
        var selectedFolders = [];
        $("input:checkbox[class=folder-select]:checked").each(function(){
            selectedFolders.push($(this).data('id'));
        });
        if (selectedFolders.length === 0) {
            selectedFolders.push($(this).data('id'));
        }

        // Get defined rights
        var access = $('input[name=right]:checked').data('type');
        if ($('#right-no-delete').prop("checked") === true
            && $('#right-no-edit').prop("checked") === true
        ) {
            access= 'NDNE';
        } else if ($('#right-no-delete').prop("checked") === true
            && $('#right-no-edit').prop("checked") === false
        ) {
            access= 'ND';
        } else if ($('#right-no-delete').prop("checked") === false
            && $('#right-no-edit').prop("checked") === true
        ) {
            access= 'NE';
        }

        // Prepare data
        var data = {
            'roleId'            : $('#roles-list').val(),
            'selectedFolders'   : selectedFolders,
            'access'            : access,
        }
        
        // Launch action
        $.post(
            'sources/roles.queries.php',
            {
                type    : 'change_access_right_on_folder',
                data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key     : '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {//decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

                if (data.error === true) {
                    // ERROR
                    alertify
                        .error(
                            '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' + data.message,
                            0
                        )
                        .dismissOthers();
                } else {
                    refreshMatrix($('#roles-list').val());

                    // OK
                    alertify
                        .success('<?php echo langHdl('done'); ?>', 1)
                        .dismissOthers();
                }
            }
        );
    } else if ($(this).data('action') === 'submit-deletion') {
        // DELETE SELECTED ROLE

        if ($('#form-folder-delete').prop('checked') === false) {
            return false;
        }

        // Show spinner
        alertify
            .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();

        // Prepare data
        var data = {
            'roleId'  : $('#roles-list').find(':selected').val(),
        }
        
        // Launch action
        $.post(
            'sources/roles.queries.php',
            {
                type    : 'delete_role',
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
                            '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' + data.message,
                            0
                        )
                        .dismissOthers();
                } else {
                    // Manage change in select
                    $("#roles-list").select2("destroy");
                    var selectedOption = $('#roles-list option[value=' + $('#roles-list').find(':selected').val() + ']');
                    selectedOption.remove();
                    $("#roles-list").select2({
                        language    : '<?php echo $_SESSION['user_language_code']; ?>',
                        placeholder : '<?php echo langHdl('select_a_role'); ?>',
                        allowClear: true
                    });

                    // Misc
                    $('#card-folder-deletion').addClass('hidden');
                    $('#form-folder-delete').iCheck('uncheck');

                    // OK
                    alertify
                        .success('<?php echo langHdl('done'); ?>', 1)
                        .dismissOthers();
                }
            }
        );
        //---
    }
    currentFolderEdited = '';
});

/**
 * Handle the user rights choices
 */
$(document).on('ifChecked', '.form-radio-input', function() {
    if ($(this).data('type') === 'W') {
        $('.form-check-input').iCheck('enable');
    } else {
        $('.form-check-input').iCheck('disable');
        $('.form-check-input').iCheck('uncheck');
    }
});


</script>