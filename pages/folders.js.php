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
 * @version   3.0.0.23
 * @file      folders.js.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'folders', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[

    // Clear
    $('#folders-search').val('');

    buildTable();

    browserSession(
        'init',
        'teampassApplication', {
            foldersSelect: '',
            complexityOptions: '',
        }
    );

    // Prepare iCheck format for checkboxes
    $('input[type="checkbox"].form-check-input').iCheck({
        checkboxClass: 'icheckbox_flat-blue',
    });

    $('input[type="checkbox"].form-check-red-input').iCheck({
        checkboxClass: 'icheckbox_flat-red',
    });

    // Prepare buttons
    var deletionList = [];
    $('.tp-action').click(function() {
        if ($(this).data('action') === 'new') {
            //--- NEW FOLDER FORM
            // Prepare form
            $('.form-check-input').iCheck('uncheck');
            $('.clear-me').val('');
            $('#new-parent').val('na').change();
            $('#new-minimal-complexity').val(0).change();

            // Show
            $('.form').addClass('hidden');
            $('#folder-new').removeClass('hidden');
            $('#folders-list').addClass('hidden');
            $('#new-title').focus();

        } else if ($(this).data('action') === 'new-submit') {
            //--- SAVE NEW FOLDER
            // Prepare data
            var data = {
                'title': $('#new-title').val(),
                'parentId': parseInt($('#new-parent').val()),
                'complexity': parseInt($('#new-complexity').val()),
                'accessRight': $('#new-access-right').val(),
                'renewalPeriod': $('#new-renewal').val() === '' ? 0 : parseInt($('#new-renewal').val()),
                'addRestriction': $('#new-add-restriction').prop("checked") === true ? 1 : 0,
                'editRestriction': $('#new-edit-restriction').prop("checked") === true ? 1 : 0,
                'icon': $('#new-folder-add-icon').val(),
                'iconSelected': $('#new-folder-add-icon-selected').val(),
            }
            console.log(data)
            // Launch action
            $.post(
                'sources/folders.queries.php', {
                    type: 'add_folder',
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                    key: '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    //decrypt data
                    data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
                    console.log(data)
                    if (data.error === true) {
                        // ERROR
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '<?php echo langHdl('error'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        buildTable();

                        // Add new folder to the list 'new-parent'
                        // Launch action
                        $.post(
                            'sources/folders.queries.php', {
                                type: 'refresh_folders_list',
                                key: '<?php echo $_SESSION['key']; ?>'
                            },
                            function(data) { //decrypt data
                                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
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

                        $('.form').addClass('hidden');
                        $('#folders-list').removeClass('hidden');
                    }
                }
            );

        } else if ($(this).data('action') === 'cancel') {
            //--- NEW FORM CANCEL
            $('.form').addClass('hidden');
            $('#folders-list').removeClass('hidden');

        } else if ($(this).data('action') === 'delete') {
            //--- DELETE FORM SHOW
            if ($('#table-folders input[type=checkbox]:checked').length === 0) {
                toastr.remove();
                toastr.warning(
                    '<?php echo langHdl('you_need_to_select_at_least_one_folder'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }

            // Prepare
            $('#delete-confirm').iCheck('uncheck');

            // Build list
            var selectedFolders = '<ul>';
            $("input:checkbox[class=checkbox-folder]:checked").each(function() {
                selectedFolders += '<li>' + $('#folder-' + $(this).data('id')).text() + '</li>';
            });
            $('#delete-list').html(selectedFolders + '</ul>');


            // Show
            $('.form').addClass('hidden');
            $('#folder-delete').removeClass('hidden');
            $('#folders-list').addClass('hidden');

        } else if ($(this).data('action') === 'delete-submit') {
            console.log('delete-submit')
            //--- DELETE FOLDERS
            // Show spinner
            toastr.remove();
            toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

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
                            '<?php echo langHdl('error'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        // refresh
                        buildTable();

                        // Show list
                        $('.form').addClass('hidden');
                        $('#folders-list').removeClass('hidden');

                        // OK
                        toastr.remove();
                        toastr.success(
                            '<?php echo langHdl('done'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );
                    }
                }
            );

        } else if ($(this).data('action') === 'refresh') {
            //--- REFRESH FOLDERS LIST
            $('.form').addClass('hidden');
            $('#folders-list').removeClass('hidden');

            // Build matrix
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


    /**
     * Undocumented function
     *
     * @return void
     */
    function buildTable() {
        // Clear
        $('#table-folders > tbody').html('');

        // Show spinner
        toastr.remove();
        toastr.info('<?php echo langHdl('loading_data'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // Build matrix
        $.post(
            'sources/folders.queries.php', {
                type: 'build_matrix',
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                console.log(data);
                if (data.error !== false) {
                    // Show error
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '<?php echo langHdl('error'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    // Build html
                    var newHtml = '',
                        ident = '',
                        path = '',
                        columns = '',
                        rowCounter = 0,
                        path = '',
                        parentsClass = '',
                        max_folder_depth = 0,
                        foldersSelect = '<option value="0"><?php echo langHdl('root'); ?></option>';

                    $(data.matrix).each(function(i, value) {
                        // List of parents
                        parentsClass = '';
                        $(value.parents).each(function(i, id) {
                            parentsClass += 'p' + id + ' ';
                        });

                        // Row
                        columns += '<tr data-id="' + value.id + '" data-level="' + value.level + '" class="' + parentsClass + '"><td>';

                        // Column 1
                        if ((value.parentId === 0 &&
                                (data.userIsAdmin === 1 || data.userCanCreateRootFolder === 1)) ||
                            value.parentId !== 0
                        ) {
                            columns += '<input type="checkbox" class="checkbox-folder" id="cb-' + value.id + '" data-id="' + value.id + '">';

                            if (value.numOfChildren > 0) {
                                columns += '<i class="fas fa-folder-minus infotip ml-2 pointer icon-collapse" data-id="' + value.id + '" title="<?php echo langHdl('collapse'); ?>"></i>';
                            }
                        }
                        columns += '</td>';

                        // Column 2
                        columns += '<td class="modify pointer" min-width="200px">' +
                            '<span id="folder-' + value.id + '" data-id="' + value.id + '" class="infotip folder-name" data-html="true" title="<?php echo langHdl('id'); ?>: ' + value.id + '<br><?php echo langHdl('level'); ?>: ' + value.level + '<br><?php echo langHdl('nb_items'); ?>: ' + value.nbItems + '">' + value.title + '</span></td>';


                        // Column 3
                        path = '';
                        $(value.path).each(function(i, folder) {
                            if (path === '') {
                                path = folder;
                            } else {
                                path += '<i class="fas fa-angle-right fa-sm ml-1 mr-1"></i>' + folder;
                            }
                        });
                        columns += '<td class="modify pointer" min-width="200px" data-value="' + value.parentId + '">' +
                            '<small class="text-muted">' + path + '</small></td>';


                        // Column 4
                        columns += '<td class="modify pointer text-center">';
                        if (value.folderComplexity.value !== undefined) {
                            columns += '<i class="' + value.folderComplexity.class + ' infotip" data-value="' + value.folderComplexity.value + '" title="' + value.folderComplexity.text + '"></i>';
                        } else {
                            columns += '<i class="fas fa-exclamation-triangle text-danger infotip" data-value="" title="<?php echo langHdl('no_value_defined_please_fix'); ?>"></i>';
                        }
                        columns += '</td>';


                        // Column 5
                        columns += '<td class="modify pointer text-center">' + value.renewalPeriod + '</td>';


                        // Column 6
                        columns += '<td class="modify pointer text-center" data-value="' + value.add_is_blocked + '">';
                        if (value.add_is_blocked === 1) {
                            columns += '<i class="fa fa-toggle-on text-info"></i>';
                        } else {
                            columns += '<i class="fa fa-toggle-off"></i>';
                        }
                        columns += '</td>';

                        // Column 7
                        columns += '<td class="modify pointer text-center" data-value="' + value.edit_is_blocked + '">';
                        if (value.edit_is_blocked === 1) {
                            columns += '<i class="fa fa-toggle-on text-info"></i>';
                        } else {
                            columns += '<i class="fa fa-toggle-off"></i>';
                        }
                        columns += '</td>';

                        // Column 
                        columns += '<td class="modify pointer text-center" data-value="' + value.icon + '"><i class="' + value.icon + '"></td>';

                        // Column 9
                        columns += '<td class="modify pointer text-center" data-value="' + value.iconSelected + '">';
                        if (value.iconSelected !== "") {
                            columns += '<i class="' + value.iconSelected + '">';
                        }
                        columns += '</td></tr>';


                        // Folder Select
                        foldersSelect += '<option value="' + value.id + '">' + value.title + '</option>';

                        // Max depth
                        if (parseInt(value.level) > max_folder_depth) {
                            max_folder_depth = parseInt(value.level);
                        }

                        rowCounter++;
                    });

                    // Show result
                    $('#table-folders > tbody').html(columns);

                    //iCheck for checkbox and radio inputs
                    $('#table-folders input[type="checkbox"]').iCheck({
                        checkboxClass: 'icheckbox_flat-blue'
                    });

                    $('.infotip').tooltip();

                    // store list of folders

                    store.update(
                        'teampassApplication',
                        function(teampassApplication) {
                            teampassApplication.foldersSelect = foldersSelect;
                        }
                    );

                    // store list of Complexity
                    complexity = '';
                    $(data.fullComplexity).each(function(i, option) {
                        complexity += '<option value="' + option.value + '">' + option.text + '</option>';
                    });

                    store.update(
                        'teampassApplication',
                        function(teampassApplication) {
                            teampassApplication.complexityOptions = complexity;
                        }
                    );

                    // Adapt select
                    $('#folders-depth').empty();
                    $('#folders-depth').append('<option value="all"><?php echo langHdl('all'); ?></option>');
                    for (x = 1; x < max_folder_depth; x++) {
                        $('#folders-depth').append('<option value="' + x + '">' + x + '</option>');
                    }
                    $('#folders-depth').val('all').change();

                    // Inform user
                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                }
            }
        );
    }


    /**
     * Build list of folders
     */
    function refreshFoldersList() {
        // Launch action
        $.post(
            'sources/folders.queries.php', {
                type: 'select_sub_folders',
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) { //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

            }
        );
    }


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

    /**
     * Check / Uncheck children folders
     */
    var operationOngoin = false;
    $(document).on('ifChecked', '.checkbox-folder', function() {
        if (operationOngoin === false) {
            operationOngoin = true;

            // Show spinner
            toastr.remove();
            toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Show selection of folders
            var selected_cb = $(this),
                id = $(this).data('id');

            // Now get subfolders
            $.post(
                'sources/folders.queries.php', {
                    type: 'select_sub_folders',
                    id: id,
                    key: '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
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
                        '<?php echo langHdl('done'); ?>',
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
            toastr.info('<?php echo langHdl('loading_data'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Show selection of folders
            var selected_cb = $(this),
                id = $(this).data('id');

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
                    toastr.success(
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
     * Handle the form for folder edit
     */
    var currentFolderEdited = '';
    $('#table-folders').on('click', '.modify', function() {
        // Manage edition of rights card
        if ((currentFolderEdited !== '' && currentFolderEdited !== $(this).data('id')) ||
            $('.temp-row').length > 0
        ) {
            $('.temp-row').remove();
        } else if (currentFolderEdited === $(this).data('id')) {
            return false;
        }

        // Init
        var currentRow = $(this).closest('tr'),
            folderTitle = $(currentRow).find("td:eq(1)").text(),
            folderParent = $(currentRow).find("td:eq(2)").data('value'),
            folderComplexity = $(currentRow).find("td:eq(3) > i").data('value'),
            folderRenewal = $(currentRow).find("td:eq(4)").text(),
            folderAddRestriction = $(currentRow).find("td:eq(5)").data('value'),
            folderEditRestriction = $(currentRow).find("td:eq(6)").data('value'),
            folderIcon = $(currentRow).find("td:eq(7)").data('value'),
            folderIconSelection = $(currentRow).find("td:eq(8)").data('value');
        currentFolderEdited = currentRow.data('id');


        // Now show
        $(currentRow).after(
            '<tr class="temp-row"><td colspan="' + $(currentRow).children('td').length + '">' +
            '<div class="card card-warning card-outline form">' +
            '<div class="card-body">' +
            '<div class="form-group ml-2">' +
            '<label for="folder-edit-title"><?php echo langHdl('label'); ?></label>' +
            '<input type="text" class="form-control clear-me" id="folder-edit-title" value="' + folderTitle + '">' +
            '</div>' +
            '<div class="form-group ml-2">' +
            '<label for="folder-edit-parent"><?php echo langHdl('parent'); ?></label><br>' +
            '<select id="folder-edit-parent" class="form-control form-item-control select2 clear-me">' + store.get('teampassApplication').foldersSelect + '</select>' +
            '</div>' +
            '<div class="form-group ml-2">' +
            '<label for="folder-edit-complexity"><?php echo langHdl('password_minimal_complexity_target'); ?></label><br>' +
            '<select id="folder-edit-complexity" class="form-control form-item-control select2 clear-me">' + store.get('teampassApplication').complexityOptions + '</select>' +
            '</div>' +
            '<div class="form-group ml-2">' +
            '<label for="folder-edit-renewal"><?php echo langHdl('renewal_delay'); ?></label>' +
            '<input type="text" class="form-control clear-me" id="folder-edit-renewal" value="' + folderRenewal + '">' +
            '</div>' +
            '<div class="form-group ml-2">' +
            '<label><?php echo langHdl('icon'); ?></label>' +
            '<input type="text" class="form-control form-folder-control" id="folder-edit-icon" value="'+folderIcon+'">' +
            '<small class="form-text text-muted">' +
            '<?php echo langHdl('fontawesome_icon_tip'); ?><a href="<?php echo FONTAWESOME_URL;?>" target="_blank"><i class="fas fa-external-link-alt ml-1"></i></a>' +
            '</small>' +
            '</div>' +
            '<div class="form-group ml-2">' +
            '<label><?php echo langHdl('icon_on_selection'); ?></label>' +
            '<input type="text" class="form-control form-folder-control" id="folder-edit-icon-selected" value="'+folderIconSelection+'">' +
            '<small class="form-text text-muted">' +
            '<?php echo langHdl('fontawesome_icon_tip'); ?><a href="<?php echo FONTAWESOME_URL;?>" target="_blank"><i class="fas fa-external-link-alt ml-1"></i></a>' +
            '</small>' +
            '</div>' +
            '<div class="form-group ml-2" id="folder-rights-tuned">' +
            '<label><?php echo langHdl('special'); ?></label>' +
            '<div class="form-check">' +
            '<input type="checkbox" class="form-check-input form-control" id="folder-edit-add-restriction">' +
            '<label class="form-check-label pointer ml-2" for="folder-edit-add-restriction"><?php echo langHdl('create_without_password_minimal_complexity_target'); ?></label>' +
            '</div>' +
            '<div class="form-check">' +
            '<input type="checkbox" class="form-check-input form-control" id="folder-edit-edit-restriction">' +
            '<label class="form-check-label pointer ml-2" for="folder-edit-edit-restriction"><?php echo langHdl('edit_without_password_minimal_complexity_target'); ?></label>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '<div class="card-footer">' +
            '<button type="button" class="btn btn-warning tp-action-edit" data-action="submit" data-id="' + currentFolderEdited + '"><?php echo langHdl('submit'); ?></button>' +
            '<button type="button" class="btn btn-default float-right tp-action-edit" data-action="cancel"><?php echo langHdl('cancel'); ?></button>' +
            '</div>' +
            '</div>' +
            '</td></tr>'
        );

        // Prepare iCheck format for checkboxes
        $('input[type="checkbox"].form-check-input, input[type="radio"].form-radio-input').iCheck({
            radioClass: 'iradio_flat-orange',
            checkboxClass: 'icheckbox_flat-orange',
        });

        $('.select2').select2({
            language: '<?php echo $_SESSION['user_language_code']; ?>'
        });

        // Manage status of the checkboxes
        if (folderAddRestriction === 0) {
            $('#folder-edit-add-restriction').iCheck('uncheck');
        } else {
            $('#folder-edit-add-restriction').iCheck('check');
        }
        if (folderEditRestriction === 0) {
            $('#folder-edit-edit-restriction').iCheck('uncheck');
        } else {
            $('#folder-edit-edit-restriction').iCheck('check');
        }

        // Prepare select2
        $('#folder-edit-parent').val(folderParent).change();
        $('#folder-edit-complexity').val(folderComplexity).change();

        currentFolderEdited = '';
    });


    $(document).on('click', '.tp-action-edit', function() {
        if ($(this).data('action') === 'cancel') {
            $('.temp-row').remove();
        } else if ($(this).data('action') === 'submit') {
            // STORE CHANGES
            var currentFolderId = $(this).data('id');

            // Prepare data
            var data = {
                'id': currentFolderId,
                'title': $('#folder-edit-title').val(),
                'parentId': $('#folder-edit-parent').val(),
                'complexity': $('#folder-edit-complexity').val(),
                'renewalPeriod': $('#folder-edit-renewal').val(),
                'addRestriction': $('#folder-edit-add-restriction').prop("checked") === true ? 1 : 0,
                'editRestriction': $('#folder-edit-edit-restriction').prop("checked") === true ? 1 : 0,
                'icon': $('#folder-edit-icon').val(),
                'iconSelected': $('#folder-edit-icon-selected').val(),
            }
            console.log(data)
            // Launch action
            $.post(
                'sources/folders.queries.php', {
                    type: 'update_folder',
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
                            '<?php echo langHdl('error'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        buildTable();
                        /* TODO
                        if (data.info_parent_changed === 0) {
                            // Update
                            var row = $('tr[data-id="' + currentFolderId + '"]');
                            console.log(row)

                            $(row).find()

                        } else {
                            buildTable();
                        }*/
                    }
                }
            );
        }
    });

    // Close on escape key
    $(document).keyup(function(e) {
        if (e.keyCode === 27) {
            if ($('.temp-row').length > 0) {
                $('.temp-row').remove();
            }
        }
    });


    // Manage collapse/expend
    $(document).on('click', '.icon-collapse', function() {
        if ($(this).hasClass('fa-folder-minus') === true) {
            $(this)
                .removeClass('fa-folder-minus')
                .addClass('fa-folder-plus text-primary');

            $('.p' + $(this).data('id')).addClass('hidden');
        } else {
            $(this)
                .removeClass('fa-folder-plus  text-primary')
                .addClass('fa-folder-minus');
            $('.p' + $(this).data('id')).removeClass('hidden');
        }
    });


    //]]>
</script>
