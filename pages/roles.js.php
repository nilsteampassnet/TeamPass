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
 * @file      roles.js.php
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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'profile', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    // Manage memory
    browserSession(
        'init',
        'teampassApplication', {
            lastItemSeen: '',
            selectedFolder: '',
            itemsListStop: '',
            itemsListStart: '',
            selectedFolder: '',
            itemsListFolderId: '',
            itemsListRestricted: '',
            itemsShownByQuery: '',
            foldersList: [],
            personalSaltkeyRequired: 0,
            uploadedFileId: '',
        }
    );

    // Preapre select drop list
    $('#roles-list.select2').select2({
        language: '<?php echo $_SESSION['user_language_code']; ?>',
        placeholder: '<?php echo langHdl('select_a_role'); ?>',
        allowClear: true
    });
    $('#roles-list').val('').change();

    // Populate
    var $options = $("#roles-list > option").clone();
    $('#folders-compare').append($options);



    $('#form-complexity-list.select2').select2({
        language: '<?php echo $_SESSION['user_language_code']; ?>'
    });

    //iCheck for checkbox and radio inputs
    $('input[type="checkbox"]').iCheck({
        checkboxClass: 'icheckbox_flat-blue'
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
                $(this).find(':selected').text() +
                ' <i class="' + $(this).find(':selected').data('complexity-icon') + ' infotip ml-3" ' +
                'title="<?php echo langHdl('complexity'); ?>: ' +
                $(this).find(':selected').data('complexity-text') + '"></i>' +
                (parseInt($(this).find(':selected').data('allow-edit-all')) === 1 ?
                    '<i class="ml-3 fas fa-exclamation-triangle text-warning infotip" ' +
                    'title="<?php echo langHdl('role_can_edit_any_visible_item'); ?>"></i>' :
                    '')
            );

            $('.infotip').tooltip();

            refreshMatrix(selectedRoleId);
        }
    });

    /**
     */
    function refreshMatrix(selectedRoleId) {
        // Show
        $('#card-role-details').removeClass('hidden');

        // 
        $('#role-details').html('');

        // Show spinner
        toastr.remove();
        toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // Build matrix
        $.post(
            'sources/roles.queries.php', {
                type: 'build_matrix',
                role_id: selectedRoleId,
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                console.log(data);
                if (data.error === true) {
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
                    // Build html
                    var newHtml = '',
                        ident = '',
                        path = '',
                        max_folder_depth = 1;
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
                                '<i class="fas fa-eraser mr-2 text-danger infotip" title="<?php echo langHdl('delete_not_allowed'); ?>"></i>';
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

                        // Max depth
                        if (parseInt(value.ident) > max_folder_depth) {
                            max_folder_depth = parseInt(value.ident);
                        }

                        // Finalize
                        newHtml += '<tr data-level="' + value.ident + '" class="' + (value.ident === 1 ? 'parent' : 'descendant') + '" data-id="' + value.id + '">' +
                            '<td width="35px"><input type="checkbox" id="cb-' + value.id + '" data-id="' + value.id + '" class="folder-select"></td>' +
                            '<td class="pointer modify folder-name" data-id="' + value.id + '" data-access="' + value.access + '">' + value.title + '</td>' +
                            '<td class="font-italic pointer modify" data-id="' + value.id + '" data-access="' + value.access + '"><small class="text-muted">' + path + '</small></td>' +
                            '<td class="pointer modify td-100 text-center" data-id="' + value.id + '" data-access="' + value.access + '">' + access + '</td>' +
                            '<td class="hidden compare tp-borders td-100 text-center"></td>'
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

                    // Adapt select
                    $('#folders-depth').val('').change();
                    $('#folders-depth').append('<option value="all"><?php echo langHdl('all'); ?></option>');
                    for (x = 1; x < max_folder_depth; x++) {
                        $('#folders-depth').append('<option value="' + x + '">' + x + '</option>');
                    }
                    $('#folders-depth').val('all').change();

                    // Inform user
                    toastr.remove();
                    toastr.info(
                        '<?php echo langHdl('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );

                    // Now check if role comparision is enabled
                    if ($('#folders-compare').val() !== '') {
                        buildRoleCompare(store.get('teampassUser').compareRole);
                    }
                }
            }
        );
    }

    var operationOngoin = false;
    $(document).on('ifChecked', '.folder-select', function() {
        if (operationOngoin === false) {
            operationOngoin = true;

            // Show spinner
            toastr.remove();
            toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Show selection of folders
            var selected_cb = $(this),
                id = $(this).data('id');

            // change language string
            if ($(this).attr('id') === 'cb-all-selection') {
                $('#cb-all-selection-lang').html('<?php echo langHdl('unselect_all'); ?>');
            }

            // Now get subfolders
            $.post(
                'sources/folders.queries.php', {
                    type: 'select_sub_folders',
                    id: id,
                    key: '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                    // check/uncheck checkbox
                    if (data.subfolders !== '') {
                        $.each(JSON.parse(data.subfolders), function(i, value) {
                            $('#cb-' + value).iCheck('check');
                        });
                    }
                    operationOngoin = false;

                    toastr.remove();
                    toastr.info(
                        '<?php echo langHdl('done'); ?>',
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
            toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Show selection of folders
            var selected_cb = $(this),
                id = $(this).data('id');

            // change language string
            if ($(this).attr('id') === 'cb-all-selection') {
                $('#cb-all-selection-lang').html('<?php echo langHdl('select_all'); ?>');
            }

            // Now get subfolders
            $.post(
                'sources/folders.queries.php', {
                    type: 'select_sub_folders',
                    id: id,
                    key: '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                    // check/uncheck checkbox
                    if (data.subfolders !== '') {
                        $.each(JSON.parse(data.subfolders), function(i, value) {
                            $('#cb-' + value).iCheck('uncheck');
                        });
                    }
                    operationOngoin = false;

                    toastr.remove();
                    toastr.info(
                        '<?php echo langHdl('done'); ?>',
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
    $(document).on('click', '.modify', function() {
        // Manage edition of rights card
        if (currentFolderEdited !== '' && currentFolderEdited !== $(this).data('id')) {
            $('.temp-row').remove();
        } else if (currentFolderEdited === $(this).data('id')) {
            $('.temp-row').remove();
            currentFolderEdited = '';
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
            '<input type="checkbox" class="form-check-input form-control cb-right" id="right-no-delete">' +
            '<label class="form-check-label pointer ml-2" for="right-no-delete"><?php echo langHdl('role_cannot_delete_item'); ?></label>' +
            '</div>' +
            '<div class="form-check">' +
            '<input type="checkbox" class="form-check-input form-control cb-right" id="right-no-edit">' +
            '<label class="form-check-label pointer ml-2" for="right-no-edit"><?php echo langHdl('role_cannot_edit_item'); ?></label>' +
            '</div>' +
            '</div>' +
            '<div class="callout callout-danger">' +
            '<div class="form-group mt-2">' +
            '<input type="checkbox" class="form-check-input form-item-control" id="propagate-rights-to-descendants">' +
            '<label class="form-check-label ml-2" for="propagate-rights-to-descendants">' +
            '<?php echo langHdl('propagate_rights_to_descendants'); ?>' +
            '</label>' +
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
            radioClass: 'iradio_flat-orange',
            checkboxClass: 'icheckbox_flat-orange',
        });

        // Uncheck the checkboxes
        $('#right-no-delete').iCheck('uncheck');
        $('#right-no-edit').iCheck('uncheck');

        // Prepare radio and checkboxes depending on existing right on selected folder
        if (folderAccess === 'R') {
            $('#right-read').iCheck('check');
            $('.cb-right').iCheck('disable');
        } else if (folderAccess === 'none') {
            $('#right-noaccess').iCheck('check');
            $('.cb-right').iCheck('disable');
        } else if (folderAccess === 'W') {
            $('#right-write').iCheck('check');
        } else if (folderAccess === 'ND') {
            $('#right-write').iCheck('check');
            $('#right-no-delete').iCheck('check');
        } else if (folderAccess === 'NE') {
            $('#right-write').iCheck('check');
            $('#right-no-edit').iCheck('check');
        } else if (folderAccess === 'NDNE') {
            $('#right-write').iCheck('check');
            $('#right-no-edit, #right-no-delete').iCheck('check');
        }
    });

    /**
     * Handle the rights change buttons
     */
    $(document).on('click', 'button', function() {
        // Init
        var selectedFolderText = $('#roles-list').find(':selected').text();

        if ($(this).data('action') === 'cancel-edition') {
            $('#card-role-definition').addClass('hidden');
            $('#card-role-details, #card-role-selection').removeClass('hidden');
            $('#form-role-label').val('');
        } else if ($(this).data('action') === 'cancel-deletion') {
            $('#card-role-details, #card-role-selection').removeClass('hidden');
            $('#card-role-deletion').addClass('hidden');
            $('#form-role-delete').iCheck('uncheck');
        } else if ($(this).data('action') === 'submit-edition') {
            // STORE ROLE CHANGES

            // Show spinner
            toastr.remove();
            toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Prepare data
            var data = {
                'label': $('#form-role-label').val(),
                'complexity': $('#form-complexity-list').val(),
                'folderId': $('#roles-list').find(':selected').val(),
                'allowEdit': $('#form-role-privilege').is(":checked") === true ? 1 : 0,
                'action': store.get('teampassApplication').formUserAction
            }
            var oldLabel = selectedFolderText;

            // Launch action
            $.post(
                'sources/roles.queries.php', {
                    type: 'change_role_definition',
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                    key: '<?php echo $_SESSION['key']; ?>'
                },
                function(data) { //decrypt data
                    data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
                    console.log(data);

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
                        if (store.get('teampassApplication').formUserAction === 'edit_role') {
                            // Adapt card header
                            $('#role-detail-header').html(
                                $('#form-role-label').val() +
                                '<i class="' + data.icon + ' infotip ml-3" ' +
                                'title="<?php echo langHdl('complexity'); ?>: ' +
                                $('#form-complexity-list').find(':selected').text() + '"></i>' +
                                (parseInt(data.allow_pw_change) === 1 ?
                                    '<i class="ml-3 fas fa-exclamation-triangle text-warning infotip" ' +
                                    'title="<?php echo langHdl('role_can_edit_any_visible_item'); ?>"></i>' :
                                    '')
                            );
                            $('.infotip').tooltip();
                        } else {
                            // Add new folder to roles listbox
                            var newOption = new Option(
                                $('#form-role-label').val(),
                                data.new_role_id,
                                false,
                                true
                            );
                            $('#roles-list').append(newOption).trigger('change');
                        }

                        // Manage change in select
                        $("#roles-list").select2("destroy");
                        var selectedOption = $('#roles-list option[value=' + $('#roles-list').find(':selected').val() + ']');
                        selectedOption.text($('#form-role-label').val());
                        selectedOption.data('allow-edit-all', data.allow_pw_change);
                        selectedOption.data('complexity-text', data.text);
                        selectedOption.data('complexity-icon', data.icon);
                        selectedOption.data('complexity', data.value);
                        $("#roles-list").select2();

                        // Misc
                        $('#card-role-definition').addClass('hidden');
                        $('#card-role-details, #card-role-selection').removeClass('hidden');
                        $('#form-role-label').val('');

                        // OK
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
            //---
        } else if ($(this).data('action') === 'edit' && $('#button-edit').hasClass('disabled') === false) {
            // SHOW ROLE EDITION FORM
            if ($('#card-role-details').hasClass('hidden') === false) {
                $('#form-role-label').val(selectedFolderText);
                $('#form-complexity-list').val($('#roles-list').find(':selected').data('complexity')).trigger('change');

                if (parseInt($('#roles-list').find(':selected').data('allow-edit-all')) === 1) {
                    $('#form-role-privilege').iCheck('check');
                } else {
                    $('#form-role-privilege').iCheck('uncheck');
                }

                // What type of form? Edit or new user
                store.update(
                    'teampassApplication',
                    function(teampassApplication) {
                        teampassApplication.formUserAction = 'edit_role';
                    }
                );

                // Show form
                $('#card-role-definition').removeClass('hidden');
                $('#card-role-deletion, #card-role-details, #card-role-selection').addClass('hidden');
            }
            //---
        } else if ($(this).data('action') === 'delete' && $('#button-delete').hasClass('disabled') === false) {
            // SHOW ROLE DELETION FORM
            if ($('#card-role-details').hasClass('hidden') === false) {
                $('#span-role-delete').html('- <?php echo langHdl('role'); ?> <b>' + selectedFolderText + '</b>');

                $('#card-role-deletion').removeClass('hidden');
                $('#card-role-definition, #card-role-details, #card-role-selection').addClass('hidden');
            }
        } else if ($(this).data('action') === 'new') {
            // SHOW NEW FOLDER DEFINITION
            $('#form-role-label').val('');
            $('#form-role-privilege').iCheck('uncheck');
            $("#form-complexity-list").val('').trigger('change');

            // What type of form? Edit or new user
            store.update(
                'teampassApplication',
                function(teampassApplication) {
                    teampassApplication.formUserAction = 'add_role';
                }
            );

            $('#card-role-definition').removeClass('hidden');
            $('#card-role-deletion, #card-role-details, #card-role-selection').addClass('hidden');
            //---
        } else if ($(this).data('action') === 'cancel') {
            $('.temp-row').remove();
            //---
        } else if ($(this).data('action') === 'submit') {
            // Store the new access rights for the selected folder(s)

            // Show spinner
            toastr.remove();
            toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Get list of selected folders
            var selectedFolders = [];
            $("input:checkbox[class=folder-select]:checked").each(function() {
                selectedFolders.push($(this).data('id'));
            });
            if (selectedFolders.length === 0) {
                selectedFolders.push($(this).data('id'));
            }

            // Get defined rights
            var access = $('input[name=right]:checked').data('type');
            if ($('#right-no-delete').is(':checked') === true &&
                $('#right-no-edit').is(':checked') === true
            ) {
                access = 'NDNE';
            } else if ($('#right-no-delete').is(':checked') === true &&
                $('#right-no-edit').is(':checked') === false
            ) {
                access = 'ND';
            } else if ($('#right-no-delete').is(':checked') === false &&
                $('#right-no-edit').is(':checked') === true
            ) {
                access = 'NE';
            }

            // Prepare data
            var data = {
                'roleId': $('#roles-list').val(),
                'selectedFolders': selectedFolders,
                'access': access,
                'propagate': $('#propagate-rights-to-descendants').is(':checked') === true ? 1 : 0,
            }
            console.log(data)
            // Launch action
            $.post(
                'sources/roles.queries.php', {
                    type: 'change_access_right_on_folder',
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                    key: '<?php echo $_SESSION['key']; ?>'
                },
                function(data) { //decrypt data
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
                        refreshMatrix($('#roles-list').val());

                        // OK
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
        } else if ($(this).data('action') === 'submit-deletion') {
            // DELETE SELECTED ROLE

            if ($('#form-role-delete').is(':checked') === false) {
                return false;
            }

            // Show spinner
            toastr.remove();
            toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Prepare data
            var data = {
                'roleId': $('#roles-list').find(':selected').val(),
            }

            // Launch action
            $.post(
                'sources/roles.queries.php', {
                    type: 'delete_role',
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
                        // Manage change in select
                        $("#roles-list").select2("destroy");
                        var selectedOption = $('#roles-list option[value=' + $('#roles-list').find(':selected').val() + ']');
                        selectedOption.remove();
                        $("#roles-list").select2({
                            language: '<?php echo $_SESSION['user_language_code']; ?>',
                            placeholder: '<?php echo langHdl('select_a_role'); ?>',
                            allowClear: true
                        });

                        // Misc
                        $('#card-role-deletion').addClass('hidden');
                        $('#card-role-selection, #card-role-details').removeClass('hidden');
                        $('#form-role-delete').iCheck('uncheck');

                        // OK
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
            //---
        }
        currentFolderEdited = '';
    });

    /**
     * Handle the user rights choices
     */
    $(document).on('ifChecked', '.form-radio-input', function() {
        if ($(this).data('type') === 'W') {
            $('.cb-right').iCheck('enable');
        } else {
            $('.cb-right').iCheck('disable');
            $('.cb-right').iCheck('uncheck');
        }
    });

    /**
     * Handle option when role is displayed
     */
    $(document).on('change', '#folders-depth', function() {
        if ($('#folders-depth').val() === 'all') {
            $('tr').removeClass('hidden');
        } else {
            $('tr').filter(function() {
                if ($(this).data('level') <= $('#folders-depth').val()) {
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
            toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Load the rights for this folder
            $.post(
                'sources/roles.queries.php', {
                    type: 'build_matrix',
                    role_id: $('#folders-compare').val(),
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

    function buildRoleCompare(data) {
        // Loop on array
        $(data).each(function(i, value) {
            var row = $('tr[data-id="' + value.id + '"]');
            if (row !== undefined) {
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
                        '<i class="fas fa-eraser mr-2 text-danger infotip" title="<?php echo langHdl('delete_not_allowed'); ?>"></i>';
                } else if (value.access === 'R') {
                    access = '<i class="fas fa-book-reader mr-2 text-warning infotip" title="<?php echo langHdl('read_only'); ?>"></i>';
                } else {
                    access = '<i class="fas fa-ban mr-2 text-danger infotip" title="<?php echo langHdl('no_access'); ?>"></i>';
                }
                row.find('td:last-child').html(access).removeClass('hidden');
            }
        });

        // Tooltips
        $('.infotip').tooltip();
    }
</script>
