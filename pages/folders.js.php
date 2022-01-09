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
 * @file      folders.js.php
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
            folderEditRestriction = $(currentRow).find("td:eq(6)").data('value');
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
            '<div class="form-group ml-2" id="folder-rights-tuned">' +
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


    //************************************************************** */

    /*
    $('.infotip').tooltip();

    $('.select2').select2({
        language: '<?php echo $_SESSION['user_language_code']; ?>'
    });

    // Prepare iCheck format for checkboxes
    $('input[type="checkbox"].flat-red').iCheck({
        checkboxClass: 'icheckbox_flat-red',
    });

    var calcDataTableHeight = function() {
      return $(window).height() * 55 / 100;
    };

    //Launch the datatables pluggin
    var oTable = $('#table-folders').dataTable({
        'paging': false,
        //'searching': true,
        //'order': [[1, 'asc']],
        'info':             false,
        'processing':       false,
        'serverSide':       true,
        'responsive':       true,
        'select':           false,
        'stateSave':        true,
        'autoWidth':        true,
        'scrollY':          calcDataTableHeight(),
        'deferRender':      true,
        'scrollCollapse' :  true,
        'ajax': {
            url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/folders.datatable.php',
        },
        'language': {
            'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user_language']; ?>.txt'
        },
        'columns': [
            {'width': '90px'},
            {className: 'dt-body-left'},
            {className: 'dt-body-left'},
            {'width': '70px', className: 'dt-body-center'},
            {'width': '70px', className: 'dt-body-center'},
            {'width': '70px', className: 'dt-body-center'},
            {'width': '90px', className: 'dt-body-center'}
        ],
        'preDrawCallback': function() {
            alertify
                .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
                .dismissOthers();
        },
        'drawCallback': function() {
            // Tooltips
            $('.infotip').tooltip();

            // Hide checkbox if search filtering
            var searchCriteria = $('body').find('[aria-controls="table-folders"]');
            if (searchCriteria.val() !== '' ) {
                $(document).find('.cb_selected_folder').addClass('hidden');
            } else {
                $(document).find('.cb_selected_folder').removeClass('hidden');
            }

            //iCheck for checkbox and radio inputs
            $('#table-folders input[type="checkbox"]').iCheck({
                checkboxClass: 'icheckbox_flat-blue'
            });

            alertify
                .message('<?php echo langHdl('done'); ?>', 1)
                .dismissOthers();
        },
        'createdRow': function( row, data, dataIndex ) {
            var newClasses = $(data[6]).filter('#row-class-' + dataIndex).val();
            $(row).addClass(newClasses);
        }
    });




    var currentText = '',
        item = '',
        initialColumnWidth = '',
        actionOnGoing = false;

    // Edit folder label
    $(document).on('click', '.edit-text', function() {
        var field = '';
        currentText = $(this).text();
        item = $(this); 
        
        if ($(this).hasClass('field-renewal')) {
            initialColumnWidth = $('#table-folders thead th')[4].style.width;
            $('#table-folders thead th')[4].style.width = '100px';
            field = 'renewal_period';
        } else if ($(this).hasClass('field-title')) {
            field = 'title';
            initialColumnWidth = $('#table-folders thead th')[1].style.width;
        } else {
            return false;
        }

        $(this)
            .addClass('hidden')
            .after('<input type="text" class="form-control form-item-control remove-me save-me" value="' + currentText + '">');

        $('.save-me')
            .focus()
            .focusout(function() {
                saveChange(item, currentText, $(this), field);
            });
    });

    // Edit folder label
    $(document).on('click', '.edit-select', function() {
        var field = '',
            change = '';
        currentText = $(this).text();
        item = $(this);
        
        // Hide existing
        $(this).addClass('hidden');
        if ($(this).hasClass('field-complex')) {
            initialColumnWidth = $('#table-folders thead th')[3].style.width;
            $('#table-folders thead th')[3].style.width = '200px';
            field = 'complexity';
        } else {
            return false;
        }

        // Show select
        $("#select-complexity")
            .insertAfter('#' + $(this).attr('id'))
            .after('<i class="fa fa-close text-danger pointer temp-button mr-3" id="select-complexity-close"></i>');
        $('#select-complexity option[value="' + $(this).data('value') + '"]').prop('selected', true);
        console.log($(this).data('value'))
        


        $('.save-me')
            .change(function() {
                if (actionOnGoing === false) {
                    actionOnGoing = true;
                    saveChange(item, currentText, $(this), field)
                }
            });

        $('#select-complexity-close').click(function() {
            $("#select-complexity").detach().appendTo('#hidden-select-complexity');
            $('#table-folders thead th')[3].style.width = initialColumnWidth;
            $('.edit-select').removeClass('hidden');
            $('.tmp-loader, .temp-button').remove();
        });
    });

    $(document).keyup(function(e) {
        if (e.keyCode === 27) {
            $('.remove-me, .tmp-loader').remove();
            $('.edit-text').removeClass('hidden');
        }
        if (e.keyCode === 13) {
            var $focused = $(':focus');
            //console.log($focused)
            console.log(currentText)
        }
    });




    // 
    function saveChange(item, currentText, change, field)
    {
        if (change.val() !== currentText) {
            change
                .after('<i class="fa fa-refresh fa-spin fa-fw tmp-loader"></i>');

            // prepare data
            var data = {
                'folder_id' : item.data('id'),
                'field'     : field,
                'value'     : change.val()
            };
            console.log(data);
            // Save
            $.post(
                'sources/folders.queries.php',
                {
                    type :  'save_folder_change',
                    data :  prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                    key  :  '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {console.log(data);
                    if (field === 'renewal_period' || field === 'title') {
                        change.remove();
                        $('.tmp-loader').remove();
                        item
                            .text(change.val())
                            .removeClass('hidden');
                        $('#table-folders thead th')[4].style.width = initialColumnWidth;
                    } else if (field === 'complexity') {
                        $("#select-complexity").detach().appendTo('#hidden-select-complexity');
                        $('#table-folders thead th')[3].style.width = initialColumnWidth;
                        $('.tmp-loader, .temp-button').remove();
                        
                        // Show change
                        item
                            .html('<i class="' + data.return.html + '"></i>')
                            .attr('data-original-title', data.return.tip)
                            .attr('data-value', data.return.value)
                            .removeClass('hidden');
                        
                        $('.infotip').tooltip();
                    }
                    actionOnGoing = false;
                },
                'json'
            );
        } else {
            change.remove();
            $('.tmp-loader').remove();
            item
                .text(change.val())
                .removeClass('hidden');
        }
    }


    // NEW FORM
    var deletionList = [];
    $('.tp-action').click(function() {
        if ($(this).data('action') === 'new') {
            $('.form').addClass('hidden');
            $('#folders-new')
                .removeClass('hidden');
        } else if ($(this).data('action') === 'delete') {
            // Build list of folders to delete
            var list = '<ul>';
            $(".cb_selected_folder:checked").each(function() {
                list += '<li>' +
                    $($('#table-folders tbody tr')[$(this).data('row')]).find('.field-title').text() +
                    '</li>';
                    deletionList.push($(this).data('id'));
            });
            $('#delete-list').html(list);

            // If selection then enable button 
            $('#delete-submit').addClass('disabled');
            if (deletionList.length > 0) {
                $('.form').addClass('hidden');
                $('#folders-delete')
                    .removeClass('hidden');
            } else {
                // Inform user
                alertify.set('notifier','position', 'top-center');
                alertify
                    .warning(
                        '<i class="fa fa-warning fa-lg mr-2"></i><?php echo langHdl('no_selection_done'); ?>',
                        5
                    )
                    .dismissOthers();
                alertify.set('notifier','position', 'bottom-right');
            }

            
        } else if ($(this).data('action') === 'refresh') {
            $('.form').addClass('hidden');
            $('#folders-list')
                .removeClass('hidden');
            alertify
                .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
                .dismissOthers();
            oTable.api().ajax.reload();
        }
    });

    $('.btn').click(function() {
        if ($(this).data('action') === 'new-submit') {
            // SHow loader
            alertify
                .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
                .dismissOthers();

            // prepare data
            var data = {
                'label' : $('#new-label').val(),
                'parent' : $('#new-parent').val(),
                'complexity' : $('#new-minimal-complexity').val(),
                'access-right' : $('#new-access-right').val(),
                'duration' : $('#new-duration').val(),
                'create-without' : $('#new-create-without').val(),
                'edit-without' : $('#new-edit-without').val(),
            };
            
            // Save
            $.post(
                'sources/folders.queries.php',
                {
                    type :  'add_folder',
                    data :  prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                    key  :  '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    //decrypt data
                    data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                    console.info(data);

                    if (data.newId === '') {
                        alertify
                            .error(
                                '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' + data.message,
                                0
                            )
                            .dismissOthers();
                    } else {
                        alertify
                            .success('<?php echo langHdl('done'); ?>', 1)
                            .dismissOthers();
                    }

                    // Clear
                    $('.clear-me').val('');
                    $('#new-duration').val('0');
                    $('.select2').val('').change();

                    // Reload
                    oTable.api().ajax.reload();

                    // Show
                    $('.form').addClass('hidden');
                    $('#folders-list').removeClass('hidden');
                }
            );
        } else if ($(this).data('action') === 'delete-submit' && $(this).hasClass('disabled') === false) {
            // prepare data
            var data = {
                'folders-list' : deletionList,
            };
            
            // If no selection then 
            if (deletionList.length === 0) {
                return;
            }

            if ($('#delete-confirm').is(':checked') === false) {
                alertify.set('notifier','position', 'top-center');
                alertify
                    .warning(
                        '<i class="fa fa-warning fa-lg mr-2"></i><?php echo langHdl('tick_confirmation_box'); ?>',
                        5
                    )
                    .dismissOthers();
                alertify.set('notifier','position', 'bottom-right');
                return;
            }

            // SHow loader
            alertify
                .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
                .dismissOthers();

            // Save
            $.post(
                'sources/folders.queries.php',
                {
                    type :  'delete_multiple_folders',
                    data :  prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                    key  :  '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    //decrypt data
                    data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');

                    if (data.error === true) {
                        alertify
                            .error(
                                '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' + data.message,
                                0
                            )
                            .dismissOthers();
                    } else {
                        alertify
                            .success('<?php echo langHdl('done'); ?>', 1)
                            .dismissOthers();
                    }

                    // Clear
                    $('.clear-me').val('');
                    $('#new-duration').val('0');
                    $('.select2').val('').change();

                    // Reload
                    oTable.api().ajax.reload();

                    // Show
                    $('.form').addClass('hidden');
                    $('#folders-list').removeClass('hidden');
                }
            );
        } else if ($(this).data('action') === 'cancel') {
            deletionList = [];
            $('.clear-me').val('');
            $('#new-duration').val('0');
            $('.select2').val('').change();
            $('.form').addClass('hidden');
            $('#folders-list').removeClass('hidden');
        }
    });


    var operationOngoin = false;
    $(document).on('ifChecked', '.cb_selected_folder', function() {
        if (operationOngoin === false) {
            operationOngoin = true;
            
            // Show spinner
            alertify
                .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
                .dismissOthers();

            // Show selection of folders
            var selected_cb = $(this),
                id = $(this).data('id');

            // Show selected
            $(this).closest('tr').css("background-color", "#c2e6fc");

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
                            $('#checkbox-' + value).iCheck('check');
                            $('#checkbox-' + value).closest('tr').css("background-color", "#c2e6fc");
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


    $(document).on('ifUnchecked', '.cb_selected_folder', function() {
        if (operationOngoin === false) {
            operationOngoin = true;
            
            // Show spinner
            alertify
                .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
                .dismissOthers();

            // Show selection of folders
            var selected_cb = $(this),
                id = $(this).data('id');

            $(this).closest('tr').css("background-color", "");

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
                            $('#checkbox-' + value).iCheck('uncheck');
                            $('#checkbox-' + value).closest('tr').css("background-color", "");
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

    // Toogle icon
    $(document).on('click', '.toggle', function() {
        // send change to be stored
        $.post(
            "sources/folders.queries.php",
            {
                type    : $(this).data('type'),
                value   : $(this).data('set'),
                id      : $(this).data('id'),
                key     : "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                // refresh table content
                oTable.api().ajax.reload();
            }
        );
    });


    // On checkbox confirm, enable button
    $('#delete-confirm')
        .on('ifChecked',function() {
            $('#delete-submit').removeClass('disabled');
        })
        .on('ifUnchecked',function() {
            $('#delete-submit').addClass('disabled');
        });

    */



    //]]>
</script>
