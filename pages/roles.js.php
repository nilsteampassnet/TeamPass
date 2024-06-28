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
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request;
use TeampassClasses\Language\Language;
// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();
$session = SessionManager::getSession();
$request = Request::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

if ($session->get('key') === null) {
    die('Hacking attempt...');
}

// Load config if $SETTINGS not defined
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => $request->request->get('type', '') !== '' ? htmlspecialchars($request->request->get('type')) : '',
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
            rolesSelectOptions: '',
        }
    );

    // Globals
    var currentThis = '';

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
        language: '<?php echo $session->get('user-language_code'); ?>'
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
     */
    function refreshMatrix(selectedRoleId) {
        // Show
        $('#card-role-details').removeClass('hidden');

        // 
        $('#role-details').html('');

        // Show spinner
        toastr.remove();
        toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // Build matrix
        $.post(
            'sources/roles.queries.php', {
                type: 'build_matrix',
                role_id: selectedRoleId,
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
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
                        '<table id="table-role-details" class="table table-hover table-striped table-responsive" style="width:100%"><tbody>' +
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
                    $('#folders-depth').append('<option value="all"><?php echo $lang->get('all'); ?></option>');
                    for (x = 1; x < max_folder_depth; x++) {
                        $('#folders-depth').append('<option value="' + x + '">' + x + '</option>');
                    }
                    $('#folders-depth').val('all').change();

                    // Inform user
                    toastr.remove();
                    toastr.info(
                        '<?php echo $lang->get('done'); ?>',
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
            '<div class="form-group ml-2 mt-2"><?php echo $lang->get('right_types_label'); ?></div>' +
            '<div class="form-group ml-2">' +
            '<input type="radio" class="form-radio-input form-control ml-1" id="right-write" name="right" data-type="W">' +
            '<label class="form-radio-label pointer mr-2" for="right-write"><?php echo $lang->get('write'); ?></label>' +
            '<input type="radio" class="form-radio-input form-control ml-1" id="right-read" name="right" data-type="R">' +
            '<label class="form-radio-label pointer mr-2" for="right-read"><?php echo $lang->get('read'); ?></label>' +
            '<input type="radio" class="form-radio-input form-control ml-1" id="right-noaccess" name="right" data-type="">' +
            '<label class="form-radio-label pointer" for="right-noaccess"><?php echo $lang->get('no_access'); ?></label>' +
            '</div>' +
            '<div class="form-group ml-2" id="folder-rights-tuned">' +
            '<div class="form-check">' +
            '<input type="checkbox" class="form-check-input form-control cb-right" id="right-no-delete">' +
            '<label class="form-check-label pointer ml-2" for="right-no-delete"><?php echo $lang->get('role_cannot_delete_item'); ?></label>' +
            '</div>' +
            '<div class="form-check">' +
            '<input type="checkbox" class="form-check-input form-control cb-right" id="right-no-edit">' +
            '<label class="form-check-label pointer ml-2" for="right-no-edit"><?php echo $lang->get('role_cannot_edit_item'); ?></label>' +
            '</div>' +
            '</div>' +
            '<div class="callout callout-danger">' +
            '<div class="form-group mt-2">' +
            '<input type="checkbox" class="form-check-input form-item-control" id="propagate-rights-to-descendants">' +
            '<label class="form-check-label ml-2" for="propagate-rights-to-descendants">' +
            '<?php echo $lang->get('propagate_rights_to_descendants'); ?>' +
            '</label>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '<div class="card-footer">' +
            '<button type="button" class="btn btn-warning tp-action" data-action="submit" data-id="' + currentFolderEdited + '"><?php echo $lang->get('submit'); ?></button>' +
            '<button type="button" class="btn btn-default float-right tp-action" data-action="cancel"><?php echo $lang->get('cancel'); ?></button>' +
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
        console.log("Click: "+$(this).data('action'));

        if ($(this).data('action') === 'cancel-edition') {
            $('#card-role-definition').addClass('hidden');
            $('#card-role-details, #card-role-selection').removeClass('hidden');
            $('#form-role-label').val('');
            $('#form-role-delete').iCheck('uncheck');

        } else if ($(this).data('action') === 'cancel-deletion') {
            $('#card-role-details, #card-role-selection').removeClass('hidden');
            $('#card-role-deletion').addClass('hidden');
            $('#form-role-delete').iCheck('uncheck');
            $('#form-role-delete').iCheck('uncheck');

        } else if ($(this).data('action') === 'cancel-ldap') {
            $('#card-role-selection').removeClass('hidden');
            $('#card-roles-ldap-sync').addClass('hidden');
            //$('#form-role-delete').iCheck('uncheck');
            //$('#form-role-delete').iCheck('uncheck');

        } else if ($(this).data('action') === 'submit-edition') {
            // STORE ROLE CHANGES

            // Show spinner
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Sanitize value
            value = fieldDomPurifierWithWarning('#form-role-label');
            if (value === false) {
                return false;
            }
            $('#form-role-label').val(value);

            // Prepare data
            var data = {
                'label': value,
                'complexity': $('#form-complexity-list').val() === null ? 0 : $('#form-complexity-list').val(),
                'folderId': $('#roles-list').find(':selected').val(),
                'allowEdit': $('#form-role-privilege').is(":checked") === true ? 1 : 0,
                'action': store.get('teampassApplication').formUserAction
            }
            var oldLabel = selectedFolderText;
            console.log(data);

            // Launch action
            $.post(
                'sources/roles.queries.php', {
                    type: 'change_role_definition',
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) { //decrypt data
                    data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');                    
                    console.log('DID CHANGES')
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
                                'title="<?php echo $lang->get('complexity'); ?>: ' +
                                $('#form-complexity-list').find(':selected').text() + '"></i>' +
                                (parseInt(data.allow_pw_change) === 1 ?
                                    '<i class="ml-3 fas fa-exclamation-triangle text-warning infotip" ' +
                                    'title="<?php echo $lang->get('role_can_edit_any_visible_item'); ?>"></i>' :
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
                            '<?php echo $lang->get('done'); ?>',
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
                $('#form-role-label').focus();
            }
            //---
        } else if ($(this).data('action') === 'delete' && $('#button-delete').hasClass('disabled') === false) {
            // SHOW ROLE DELETION FORM
            if ($('#card-role-details').hasClass('hidden') === false) {
                selectedFolderText = $('<div>').text(selectedFolderText).html();
                $('#span-role-delete').html('- <?php echo $lang->get('role'); ?> <b>' + selectedFolderText + '</b>');

                $('#card-role-deletion').removeClass('hidden');
                $('#card-role-definition, #card-role-details, #card-role-selection').addClass('hidden');
            }
        
        } else if ($(this).data('action') === 'ldap') {
            // SHOW LDAP SYNC FORM
            console.log('LDAP SYNC');
            if ($('#card-roles-ldap-sync').hasClass('hidden') === true) {
                //$('#span-role-delete').html('- <?php echo $lang->get('role'); ?> <b>' + selectedFolderText + '</b>');

                $('#card-roles-ldap-sync').removeClass('hidden');
                $('#card-role-definition, #card-role-details, #card-role-selection').addClass('hidden');

                refreshLdapGroups();
            }

        } else if ($(this).data('action') === 'ldap-refresh') {
            // REFRESH LDAP GROUPS LIST
            refreshLdapGroups();

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
            $('#form-role-label').focus();
            //---
        } else if ($(this).data('action') === 'cancel') {
            $('.temp-row').remove();
            //---
        } else if ($(this).data('action') === 'submit') {
            // Store the new access rights for the selected folder(s)

            // Show spinner
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

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
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) { //decrypt data
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
                        refreshMatrix($('#roles-list').val());

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
        } else if ($(this).data('action') === 'submit-deletion') {
            // DELETE SELECTED ROLE

            if ($('#form-role-delete').is(':checked') === false) {
                return false;
            }

            // Show spinner
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Prepare data
            var data = {
                'roleId': $('#roles-list').find(':selected').val(),
            }

            // Launch action
            $.post(
                'sources/roles.queries.php', {
                    type: 'delete_role',
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
                        $("#roles-list").select2("destroy");
                        var selectedOption = $('#roles-list option[value=' + $('#roles-list').find(':selected').val() + ']');
                        selectedOption.remove();
                        $("#roles-list").select2({
                            language: '<?php echo $session->get('user-language_code'); ?>',
                            placeholder: '<?php echo $lang->get('select_a_role'); ?>',
                            allowClear: true
                        });

                        // Misc
                        $('#card-role-deletion, #card-role-details').addClass('hidden');
                        $('#card-role-selection').removeClass('hidden');
                        $('#form-role-delete').iCheck('uncheck');

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

        } else if ($(this).data('action') === 'do-adgroup-role-mapping') {
            var groupId = $(this).data('id'),
                roleId = parseInt($('.select-role').val()),
                groupTitle = $('.select-role option:selected').text();

            if (roleId === '') {
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
                        // Get group name
                        html += '<tr>' +
                            '<td>' + ad_group.ad_group_title + '</td>' +
                            '<td><i class="fa-solid fa-arrow-right-long"></i></td>' +
                            '<td class="pointer change_adgroup_mapping" data-id="'+ad_group.ad_group_id+'">' + 
                                (ad_group.role_title === "" ? '<i class="fa-solid fa-xmark text-danger infotip" title="<?php echo $lang->get('none'); ?>"></i>' : ad_group.role_title) + 
                            '</td>' +
                            '</tr>';
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
