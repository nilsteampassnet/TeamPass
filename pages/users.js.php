<?php

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      users.js.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2019 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
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
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit();
}
?>


<script type='text/javascript'>
    //<![CDATA[
    // Initialization
    var userDidAChange = false;

    browserSession(
        'init',
        'teampassApplication', {
            foldersSelect: '',
            complexityOptions: '',
        }
    );

    // Prepare tooltips
    $('.infotip').tooltip();

    // Prepare Select2
    $('.select2').select2({
        language: '<?php echo $_SESSION['user_language_code']; ?>'
    });

    // Prepare iCheck format for checkboxes
    $('input[type="checkbox"].flat-blue, input[type="radio"].flat-blue').iCheck({
        radioClass: 'iradio_flat-blue',
        checkboxClass: 'icheckbox_flat-blue',
    });
    $('#create-special-folder').iCheck('disable');

    // Prevent submit on button
    $('.btn-no-click')
        .click(function(e) {
            e.preventDefault();
        });


    //Launch the datatables pluggin
    var oTable = $('#table-users').DataTable({
        'paging': true,
        'searching': true,
        'order': [
            [1, 'asc']
        ],
        'info': true,
        'processing': false,
        'serverSide': true,
        'responsive': true,
        'select': false,
        'stateSave': true,
        'autoWidth': true,
        'ajax': {
            url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/users.datatable.php',
        },
        'language': {
            'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user_language']; ?>.txt'
        },
        'columns': [{
                'width': '80px',
                className: 'details-control',
                'render': function(data, type, row, meta) {
                    return '<span class="input-group-btn btn-user-action">' +
                        '<button type="button" class="btn btn-default dropdown-toggle btn-sm" data-toggle="dropdown">' +
                        '<i class="fas fa-gear"></i>' +
                        '</button>' +
                        '<ul class="dropdown-menu" role="menu">' +
                        '<li class="dropdown-item pointer tp-action" data-id="' + $(data).data('id') + '" data-action="new-password"><i class="fas fa-lock mr-2"></i><?php echo langHdl('change_login_password'); ?></li>' +
                        '<li class="dropdown-item pointer tp-action" data-id="' + $(data).data('id') + '" data-action="edit"><i class="fas fa-pen mr-2"></i><?php echo langHdl('edit'); ?></li>' +
                        '<li class="dropdown-item pointer tp-action" data-id="' + $(data).data('id') + '" data-fullname="' + $(data).data('fullname') + '" data-action="logs"><i class="fas fa-newspaper mr-2"></i><?php echo langHdl('see_logs'); ?></li>' +
                        '<li class="dropdown-item pointer tp-action" data-id="' + $(data).data('id') + '" data-action="qrcode"><i class="fas fa-qrcode mr-2"></i><?php echo langHdl('user_ga_code'); ?></li>' +
                        '<li class="dropdown-item pointer tp-action" data-id="' + $(data).data('id') + '" data-fullname="' + $(data).data('fullname') + '"data-action="access-rights"><i class="fas fa-sitemap mr-2"></i><?php echo langHdl('user_folders_rights'); ?></li>' +
                        '</ul>' +
                        '</span>';
                }
            },
            {
                className: 'dt-body-left'
            },
            {
                className: 'dt-body-left'
            },
            {
                className: 'dt-body-left'
            },
            {
                className: 'dt-body-left'
            },
            {
                className: 'dt-body-left'
            },
            {
                'width': '70px',
                className: 'dt-body-center'
            },
            {
                'width': '70px',
                className: 'dt-body-center'
            },
            {
                'width': '70px',
                className: 'dt-body-center'
            }
        ],
        'preDrawCallback': function() {
            toastr.remove();
            toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');
        },
        'drawCallback': function() {
            // Tooltips
            $('.infotip').tooltip();

            // Inform user
            toastr.remove();
            toastr.success(
                '<?php echo langHdl('done'); ?>',
                '', {
                    timeOut: 1000
                }
            );
        },
        /*'createdRow': function( row, data, dataIndex ) {
            var newClasses = $(data[6]).filter('#row-class-' + dataIndex).val();
            $(row).addClass(newClasses);
        }*/
    });







    $('#form-email').change(function() {
        //extract domain from email
        var domain = $(this).val().split('@')[1];
        if (domain === undefined) {
            return false;
        }
        domain = domain.toLowerCase()

        //check if domain exists
        $.post("sources/users.queries.php", {
                type: "check_domain",
                domain: domain
            },
            function(data) {
                data = $.parseJSON(data);
                console.log(data);
                $("#new_folder_role_domain").attr("disabled", "disabled");
                if (data.folder === 'not_exists' && data.role === 'not_exists' && domain !== '') {
                    $('#create-special-folder').iCheck('enable');
                    $('#form-special-folder').val(domain);
                } else {
                    $('#create-special-folder').iCheck('disable');
                    $('#form-special-folder').val('');
                }
            }
        );
    });

    /**
     * BUILD AND CHECK THE USER LOGIN
     */
    $('.build-login').change(function() {
        // Build login only if it is empty
        if ($("#form-login").val() === '') {
            //return false;
        }
        // Build login
        if ($(this).attr('id') !== 'form-login') {
            $("#form-login").val(
                $("#form-name")
                .val()
                .toLowerCase()
                .replace(/ /g, "") + "." + $("#form-lastname").val().toLowerCase().replace(/ /g, "")
            );
        }

        // Check if login exists
        $.post(
            'sources/users.queries.php', {
                type: 'is_login_available',
                login: $('#form-login').val(),
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
                        '<?php echo langHdl('caution'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    // Show result
                    if (data.login_exists === 0) {
                        $('#form-login')
                            .removeClass('is-invalid')
                            .addClass('is-valid');
                        $('#form-login-conform').val(true);
                    } else {
                        $('#form-login')
                            .removeClass('is-valid')
                            .addClass('is-invalid');
                        $('#form-login-conform').val(false);
                    }
                }
            }
        );
    })


    /**
     * TOP MENU BUTTONS ACTIONS
     */
    $(document).on('click', '.tp-action', function() {
        // Ensure that password strength indicator is reseted
        //$('#form-password').focus();

        // Hide if user is not admin
        if (store.get('teampassUser').user_admin === 1 || store.get('teampassUser').user_can_manage_all_users === 1) {
            $('.only-admin').removeClass('hidden');
        } else {
            $('.only-admin').addClass('hidden');
        }

        if ($(this).data('action') === 'new') {
            // ADD NEW USER
            $('#row-list, #group-form-user-disabled').addClass('hidden');
            $('#row-form, #group-create-special-folder').removeClass('hidden');

            // Prepare checks
            $('#privilege-user').iCheck('check');
            $('#create-special-folder').iCheck('disable');

            // Personal folder
            if (store.get('teampassSettings').enable_pf_feature === '1') {
                $('#form-create-personal-folder')
                    .iCheck('enable')
                    .iCheck('check');
            } else {
                $('#form-create-personal-folder').iCheck('disable');
            }

            // What type of form? Edit or new user
            store.update(
                'teampassApplication',
                function(teampassApplication) {
                    teampassApplication.formUserAction = 'add_new_user';
                }
            );

        } else if ($(this).data('action') === 'edit') {
            // SHow user
            toastr.remove();
            toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // EDIT EXISTING USER
            $('#row-list, #group-create-special-folder, #group-delete-user').addClass('hidden');
            $('#row-form, #group-form-user-disabled').removeClass('hidden');
            $('.form-check-input').iCheck('enable');

            // Personal folder
            if (parseInt(store.get('teampassSettings').enable_pf_feature) === 0) {
                $('#form-create-personal-folder').iCheck('disable');
            }

            // HIDE FROM FORM ELEMENTS ONLY FOR ADMIN
            if (parseInt(store.get('teampassUser').user_admin) === 1) {
                $('input[type=radio].only-admin').iCheck('enable');
            } else {
                $('input[type=radio].only-admin').iCheck('disable');
            }

            // What type of form? Edit or new user
            var userID = $(this).data('id');
            store.update(
                'teampassApplication',
                function(teampassApplication) {
                    teampassApplication.formUserAction = 'store_user_changes',
                        teampassApplication.formUserId = userID; // Store user ID
                }
            );
            $.post(
                "sources/users.queries.php", {
                    type: "get_user_info",
                    id: userID,
                    key: "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                    console.log(data);

                    if (data.error === false) {
                        // Prefil with user data
                        $('#form-login').val(data.login);
                        $('#form-email').val(data.email);
                        $('#form-name').val(data.name);
                        $('#form-lastname').val(data.lastname);
                        $('#form-create-root-folder').iCheck(data.can_create_root_folder === 1 ? 'check' : 'uncheck');
                        $('#form-user-disabled').iCheck(data.disabled === 1 ? 'check' : 'uncheck');
                        $('#form-create-personal-folder').iCheck(data.personal_folder === 1 ? 'check' : 'uncheck');

                        // Case of user locked
                        if (data.disabled === 1) {
                            $('#group-delete-user').removeClass('hidden');
                            $('#form-delete-user-confirm').iCheck('uncheck');
                        }

                        // Clear selects
                        $('#form-roles, #form-managedby, #form-auth, #form-forbid')
                            .find('option')
                            .remove();

                        var tmp = '';
                        $(data.foldersAllow).each(function(i, value) {
                            tmp += '<option value="' + value.id + '" ' + value.selected + '>' + value.title + '</option>';
                        });
                        $('#form-auth').append(tmp);

                        tmp = '';
                        $(data.foldersForbid).each(function(i, value) {
                            tmp += '<option value="' + value.id + '" ' + value.selected + '>' + value.title + '</option>';
                        });
                        $('#form-forbid').append(tmp);

                        tmp = '';
                        $(data.managedby).each(function(i, value) {
                            tmp += '<option value="' + value.id + '" ' + value.selected + '>' + value.title + '</option>';
                        });
                        $('#form-managedby').append(tmp);

                        tmp = '';
                        $(data.function).each(function(i, value) {
                            tmp += '<option value="' + value.id + '" ' + value.selected + '>' + value.title + '</option>';
                        });
                        $('#form-roles').append(tmp);

                        // Prepare default password
                        //$('#form-password, #form-confirm').val(data.password);

                        // Generate select2
                        $('#form-roles, #form-managedby, #form-auth, #form-forbid').select2();

                        // User's current privilege
                        if (data.admin === 1) {
                            $('#privilege-admin').iCheck('check');
                        } else if (data.can_manage_all_users === 1) {
                            $('#privilege-hr').iCheck('check');
                        } else if (data.gestionnaire === 1) {
                            $('#privilege-manager').iCheck('check');
                        } else if (data.read_only === 1) {
                            $('#privilege-ro').iCheck('check');
                        } else {
                            $('#privilege-user').iCheck('check');
                        }

                        $('input:radio[name=privilege]').on('ifChanged', function() {
                            userDidAChange = true;
                            $(this).data('change-ongoing', true);
                        });

                        // Inform user
                        toastr.remove();
                        toastr.success(
                            '<?php echo langHdl('done'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );
                    } else {
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '<?php echo langHdl('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                        return false;
                    }
                }
            );
        } else if ($(this).data('action') === 'submit') {
            // Manage case of delete
            if ($('#form-delete-user-confirm').prop('checked') === true) {
                // Prepare modal
                showModalDialogBox(
                    '#warningModal',
                    '<i class="fas fa-user-minus fa-lg warning mr-2"></i><?php echo langHdl('please_confirm'); ?>',
                    '<?php echo langHdl('please_confirm_user_deletion'); ?>',
                    '<?php echo langHdl('confirm'); ?>',
                    '<?php echo langHdl('cancel'); ?>'
                );

                // Actions on modal buttons
                $(document).on('click', '#warningModalButtonClose', function() {
                    // Nothing
                });
                $(document).on('click', '#warningModalButtonAction', function() {
                    // SHow user
                    toastr.remove();
                    toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

                    // Action
                    var data = {
                        'user_id': store.get('teampassApplication').formUserId,
                    }
                    // Send query to server
                    $.post(
                        'sources/users.queries.php', {
                            type: 'delete_user',
                            data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                            key: "<?php echo $_SESSION['key']; ?>"
                        },
                        function(data) {
                            data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                            console.log(data);

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

                                // clear form fields
                                $(".clear-me").val('');
                                $('.select2').val('').change();
                                //$('#privilege-user').iCheck('check');
                                $('.form-check-input')
                                    .iCheck('disable')
                                    .iCheck('uncheck');

                                // refresh table content
                                oTable.ajax.reload();

                                // Show list of users
                                $('#row-form').addClass('hidden');
                                $('#row-list').removeClass('hidden');
                            } else {
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
                });

                return false;
            }

            // Loop on all changed fields
            var arrayQuery = [];
            $('.form-control').each(function(i, obj) {
                if ($(this).data('change-ongoing') === true
                    //|| $('#form-password').val() !== 'do_not_change'
                ) {
                    arrayQuery.push({
                        'field': $(this).prop('id'),
                        'value': $(this).val(),
                    });
                }
            });

            if (arrayQuery.length > 0) {
                // Now save
                // get lists
                var forbidFld = [],
                    authFld = [],
                    groups = [];
                $("#form-roles option:selected").each(function() {
                    groups.push($(this).val())
                });
                $("#form-auth option:selected").each(function() {
                    authFld.push($(this).val())
                });
                $("#form-forbid option:selected").each(function() {
                    forbidFld.push($(this).val())
                });

                // Mandatory?
                var validated = true;
                $('.required').each(function(i, obj) {
                    if ($(this).val() === '' && $(this).hasClass('select2') === false) {
                        $(this).addClass('is-invalid');
                        validated = false;
                    } else if ($('#' + $(this).attr('id') + ' :selected').length === 0 && $(this).hasClass('select2') === true) {
                        $('#' + $(this).attr('id') + ' + span').addClass('is-invalid');
                        validated = false;
                    } else {
                        $(this).removeClass('is-invalid');
                        $('#' + $(this).attr('id') + ' + span').removeClass('is-invalid');
                    }
                });
                if (validated === false) {
                    toastr.remove();
                    toastr.error(
                        '<?php echo langHdl('fields_with_mandatory_information_are_missing'); ?>',
                        '<?php echo langHdl('caution'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                    return false;
                }

                // SHow user
                toastr.remove();
                toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

                //prepare data
                var data = {
                    'user_id': store.get('teampassApplication').formUserId,
                    'login': $('#form-login').val(),
                    'name': $('#form-name').val(),
                    'lastname': $('#form-lastname').val(),
                    //'pw' : $('#form-password').val(),
                    'email': $('#form-email').val(),
                    'admin': $('#privilege-admin').prop('checked'),
                    'manager': $('#privilege-manager').prop('checked'),
                    'hr': $('#privilege-hr').prop('checked'),
                    'read_only': $('#privilege-ro').prop('checked'),
                    'personal_folder': $('#form-create-personal-folder').prop('checked'),
                    'new_folder_role_domain': $('#create-special-folder').prop('checked'),
                    'domain': $('#form-special-folder').val(),
                    'isAdministratedByRole': $('#form-managedby').val(),
                    'groups': groups,
                    'allowed_flds': authFld,
                    'forbidden_flds': forbidFld,
                    'action_on_user': 'update',
                    'form-create-root-folder': $('#form-create-root-folder').prop('checked'),
                    'form-user-disabled': $('#form-user-disabled').prop('checked'),
                };
                console.log(data);

                $.post(
                    'sources/users.queries.php', {
                        type: store.get('teampassApplication').formUserAction,
                        data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                        key: "<?php echo $_SESSION['key']; ?>"
                    },
                    function(data) {
                        data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                        console.log(data);

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
                            // clear form fields
                            $(".clear-me").val('');
                            $('.select2').val('').change();
                            //$('#privilege-user').iCheck('check');
                            $('.form-check-input')
                                .iCheck('disable')
                                .iCheck('uncheck');

                            // refresh table content
                            oTable.ajax.reload();

                            // Show list of users
                            $('#row-form').addClass('hidden');
                            $('#row-list').removeClass('hidden');

                            // Inform user
                            toastr.remove();
                            toastr.success(
                                '<?php echo langHdl('done'); ?>',
                                '', {
                                    timeOut: 1000
                                }
                            );
                        }

                        // Remove action from store
                        store.update(
                            'teampassApplication',
                            function(teampassApplication) {
                                teampassApplication.formUserAction = '',
                                    teampassApplication.formUserId = '';
                            }
                        );
                    }
                )
            } else {
                // No change performed on form
                toastr.remove();
                toastr.success(
                    '<?php echo langHdl('no_change_performed'); ?>',
                    '', {
                        timeOut: 1000
                    }
                );
            }
        } else if ($(this).data('action') === 'cancel') {
            $('.clear-me').val('');
            $('.select2').val('').change();
            $('.extra-form, #row-folders').addClass('hidden');
            $('#row-list').removeClass('hidden');

            // Prepare checks
            $('.form-check-input')
                .iCheck('disable')
                .iCheck('uncheck');

            // Remove action from store
            store.update(
                'teampassApplication',
                function(teampassApplication) {
                    teampassApplication.formUserAction = '',
                        teampassApplication.formUserId = '';
                }
            );
        } else if ($(this).data('action') === 'qrcode') {
            toastr.remove();
            toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // This sends a GA Code by email to user
            data = {
                'user_id': $(this).data('id'),
                'demand_origin': 'users_management_list',
                'send_email': 1
            }

            $.post(
                'sources/main.queries.php', {
                    type: 'ga_generate_qr',
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                    key: "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                    console.log(data);

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
                        // Inform user
                        toastr.remove();
                        toastr.success(
                            '<?php echo langHdl('share_sent_ok'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );
                    }
                }
            );
            // ---
        } else if ($(this).data('action') === 'new-password') {
            // HIde
            $('.content-header, .content').addClass('hidden');

            // SHow form
            $('#dialog-encryption-keys').removeClass('hidden');

            $('#sharekeys_reencryption_target_user').val($(this).data('id'));
            // ---
        } else if ($(this).data('action') === 'logs') {
            $('#row-list, #row-folders').addClass('hidden');
            $('#row-logs').removeClass('hidden');
            $('#row-logs-title').text(
                $(this).data('fullname')
            )
            var userID = $(this).data('id');

            //Launch the datatables pluggin
            var oTableLogs = $('#table-logs').DataTable({
                'destroy': true,
                'paging': true,
                'searching': true,
                'order': [
                    [1, 'asc']
                ],
                'info': true,
                'processing': false,
                'serverSide': true,
                'responsive': true,
                'select': true,
                'stateSave': false,
                'retrieve': false,
                'autoWidth': true,
                'ajax': {
                    url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/user.logs.datatables.php',
                    data: function(d) {
                        d.userId = userID;
                    }
                },
                'language': {
                    'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user_language']; ?>.txt'
                },
                'columns': [{
                        className: 'dt-body-left'
                    },
                    {
                        className: 'dt-body-left'
                    },
                    {
                        className: 'dt-body-left'
                    }
                ],
                'preDrawCallback': function() {
                    toastr.remove();
                    toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');
                },
                'drawCallback': function() {
                    // Tooltips
                    $('.infotip').tooltip();

                    // Inform user
                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                },
            });


        } else if ($(this).data('action') === 'access-rights') {
            $('#row-list, #row-logs').addClass('hidden');
            $('#row-folders').removeClass('hidden');
            $('#row-folders-title').text(
                $(this).data('fullname')
            )
            var userID = $(this).data('id');

            // Show spinner
            toastr.remove();
            toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            $('#row-folders-results').html('');

            // Send query
            $.post(
                'sources/users.queries.php', {
                    type: 'user_folders_rights',
                    user_id: userID,
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
                            '<?php echo langHdl('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        // Show table
                        $('#row-folders-results').html(data.html);

                        // Prepare tooltips
                        $('.infotip').tooltip();
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
            //
            // --- END
            //
        } else if ($(this).data('action') === 'refresh') {
            $('.form').addClass('hidden');
            $('#users-list')
                .removeClass('hidden');
            toastr.remove();
            toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');
            oTable.ajax.reload();
            //
            // --- END
            //
        } else if ($(this).data('action') === 'propagate') {
            $('#row-list, #row-folders').addClass('hidden');
            $('#row-propagate').removeClass('hidden');

            // Show spinner
            toastr.remove();
            toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            // Load list of users
            $.post(
                'sources/users.queries.php', {
                    type: 'get_list_of_users_for_sharing',
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
                            '<?php echo langHdl('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        // Build select
                        var html = '';
                        $.each(data.values, function(i, value) {
                            html += '<option value="' + value.id + '" data-groups="' + value.groups + '" data-managed-by="' + value.managedBy + '" data-folders-allowed="' + value.foldersAllowed + '" data-folders-forbidden="' + value.foldersForbidden + '" data-groups-id="' + value.groupIds + '" data-managed-by-id="' + value.managedById + '" data-folders-allowed-id="' + value.foldersAllowedIds + '" data-folders-forbidden-id="' + value.foldersForbiddenIds + '" data-admin="' + value.admin + '" data-manager="' + value.manager + '" data-hr="' + value.hr + '" data-read-only="' + value.readOnly + '" data-personal-folder="' + value.personalFolder + '" data-root-folder="' + value.rootFolder + '">' + value.name + ' ' + value.lastname + ' [' + value.login + ']</option>';
                        });

                        $('#propagate-from, #propagate-to')
                            .find('option')
                            .remove()
                            .end()
                            .append(html)
                            .change();

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
            //
            // --- END
            //
        } else if ($(this).data('action') === 'do-propagate') {
            // Show spinner
            toastr.remove();
            toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');


            // destination users
            var userIds = $('#propagate-to').val();

            if (userIds.length === 0) return false;

            // Prepare data
            var data = {
                source_id: $("#propagate-from option:selected").val(),
                destination_ids: userIds,
                user_functions: $("#propagate-from option:selected").data('groups-id'),
                user_managedby: $("#propagate-from option:selected").data('managed-by-id'),
                user_fldallowed: $("#propagate-from option:selected").data('folders-allowed-id'),
                user_fldforbid: $("#propagate-from option:selected").data('folders-forbidden-id'),
                user_admin: $("#propagate-from option:selected").data('admin'),
                user_manager: $("#propagate-from option:selected").data('manager'),
                user_hr: $("#propagate-from option:selected").data('hr'),
                user_readonly: $("#propagate-from option:selected").data('read-only'),
                user_personalfolder: $("#propagate-from option:selected").data('personal-folder'),
                user_rootfolder: $("#propagate-from option:selected").data('root-folder'),
            };
            console.log(data);
            $.post(
                "sources/users.queries.php", {
                    type: "update_users_rights_sharing",
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                    key: "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    //decrypt data
                    data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

                    if (data.error === true) {
                        // ERROR
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '<?php echo langHdl('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        $('.clear-me').val('');
                        $('.select2').val('').change();
                        $('.extra-form, #row-folders').addClass('hidden');
                        $('#row-list').removeClass('hidden');

                        // Prepare checks
                        $('.form-check-input')
                            .iCheck('disable')
                            .iCheck('uncheck');

                        // Remove action from store
                        store.update(
                            'teampassApplication',
                            function(teampassApplication) {
                                teampassApplication.formUserAction = '',
                                    teampassApplication.formUserId = '';
                            }
                        );

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

            //
            // --- END
            //
        } else if ($(this).data('action') === 'ldap-sync') {
            $('.form').addClass('hidden');
            $('#row-ldap').removeClass('hidden');

            refreshListUsersLDAP();

            //
            // --- END
            //
        } else if ($(this).data('action') === 'close') {
            $('.extra-form').addClass('hidden');
            $('#users-list').removeClass('hidden');

            //
            // --- END
            //
        } else if ($(this).data('action') === 'ldap-existing-users') {
            refreshListUsersLDAP();

            //
            // --- END
            //
        } else if ($(this).data('action') === 'ldap-add-role') {
            $('#ldap-users-table').addClass('hidden');
            $('#ldap-new-role').removeClass('hidden');

            //
            // --- END
            //
        } else if ($(this).data('action') === 'close-new-role') {
            $('#ldap-users-table').removeClass('hidden');
            $('#ldap-new-role').addClass('hidden');

            //
            // --- END
            //
        } else if ($(this).data('action') === 'add-new-role') {
            if ($('#ldap-new-role-selection').val() === '') {
                // ERROR
                toastr.remove();
                toastr.error(
                    '<?php echo langHdl('error_field_is_mandatory'); ?>',
                    '<?php echo langHdl('caution'); ?>', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
            } else {
                // Add new role to Teampass

                // Prepare data
                var data = {
                    'label': $('#ldap-new-role-selection').val(),
                    'complexity': $('#ldap-new-role-complexity').val(),
                    'allowEdit': 0,
                    'action': 'add_folder'
                }
                $.post(
                    'sources/roles.queries.php', {
                        type: 'change_role_definition',
                        data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                        key: '<?php echo $_SESSION['key']; ?>'
                    },
                    function(data) {
                        //decrypt data
                        data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
                        console.log(data);

                        if (data.error === true) {
                            // ERROR
                            toastr.remove();
                            toastr.error(
                                data.message,
                                '<?php echo langHdl('caution'); ?>', {
                                    timeOut: 5000,
                                    progressBar: true
                                }
                            );
                        } else {
                            $('#ldap-new-role-selection').val('');
                            $('#ldap-users-table').removeClass('hidden');
                            $('#row-ldap-body').html('');
                            $('#ldap-new-role').addClass('hidden');

                            refreshListUsersLDAP();
                        }
                    }
                );

            }
        }
    });


    /**
     * Permit to show some info while selecting a  User
     */
    $(document).on('change', '#propagate-from', function() {
        var selectedOption = $(this).find('option:selected');
        $('#propagate-user-roles').html($(selectedOption).data('groups'));
        $('#propagate-user-managedby').html($(selectedOption).data('managed-by'));
        $('#propagate-user-allowed').html($(selectedOption).data('folders-allowed'));
        $('#propagate-user-fordidden').html($(selectedOption).data('folders-forbidden'));
    });


    /**
     * TRACK CHANGES IN FORM
     */
    $('#form-user .track-change')
        .on('change', function() {
            if ($(this).val() !== null && $(this).val().length > 0) {
                userDidAChange = true;
                $(this).data('change-ongoing', true);
            } else {
                $(this).data('change-ongoing', false);
            }
        })
        .on('ifChecked', function() {
            userDidAChange = true;
            $(this).data('change-ongoing', true);
        });

    //************************************************************* */



    /**
     * EDIT EACH ROW
     */
    var currentText = '',
        item = '',
        initialColumnWidth = '',
        actionOnGoing = false,
        field = '',
        columnId = '',
        tableDef = {
            'login': {
                'column': 2
            },
            'name': {
                'column': 3
            },
            'lastname': {
                'column': 4
            },
            'isAdministratedByRole': {
                'column': 5
            },
            'fonction_id': {
                'column': 6
            }
        };

    /**
     * EDIT TEXT INPUT
     */
    $(document).on('click', '.edit-text', function() {
        currentText = $(this).text();
        item = $(this);
        field = $(this).data('field');
        columnId = tableDef[field].column;

        $(this)
            .addClass('hidden')
            .after('<input type="text" class="form-control form-item-control remove-me save-me" value="' + currentText + '">');

        // Store current width and change it
        initialColumnWidth = $('#table-users thead th:eq(' + (columnId - 1) + ')').width();
        $('#table-users thead th:eq(' + (columnId - 1) + ')').width('300');
        console.log('Width ' + initialColumnWidth)

        // Launch save on focus lost
        $('.save-me')
            .focus()
            .focusout(function() {
                if (actionOnGoing === false) {
                    actionOnGoing = true;
                    saveChange(item, currentText, $(this), field);
                }
            });
    });

    /**
     * EDIT SELECT LIST
     */
    $(document).on('click', '.edit-select', function() {
        currentText = $(this).text();
        item = $(this);
        field = $(this).data('field');
        columnId = tableDef[field].column;
        console.log(columnId)

        $(this).addClass('hidden');

        // Show select
        $("#select-managedBy")
            .insertAfter('#' + $(this).attr('id'))
            .after('<i class="fa fa-close text-danger pointer temp-button mr-3" id="select-managedBy-close"></i>');
        $('#select-managedBy option[value="' + $(this).data('value') + '"]').prop('selected', true);

        // Store current width and change it
        initialColumnWidth = $('#table-users thead th:eq(' + (columnId - 1) + ')').width();
        $('#table-users thead th:eq(' + (columnId - 1) + ')').width('300');

        // Launch save on focus lost
        $('.save-me')
            .focus()
            .focusout(function() {
                if (actionOnGoing === false) {
                    actionOnGoing = true;
                    saveChange(item, currentText, $(this), field);
                }
            });

        $('#select-managedBy-close').click(function() {
            $("#select-managedBy").detach().appendTo('#hidden-managedBy');
            $('#table-users thead th:eq(' + (columnId - 1) + ')').width(initialColumnWidth);
            $('.edit-select').removeClass('hidden');
            $('.tmp-loader, .temp-button').remove();
        });
    });


    /**
     * MANAGE USER KEYS PRESSED
     */
    $(document).keyup(function(e) {
        if (e.keyCode === 27) {
            // Escape Key
            $('.remove-me, .tmp-loader').remove();
            $('.edit-text').removeClass('hidden');
        }
        if (e.keyCode === 13 && actionOnGoing === false) {
            // Enter key
            actionOnGoing = true;
            saveChange(item, currentText, $(':focus'), field);
        }
    });


    function saveChange(item, currentText, change, field) {
        if (change.val() !== currentText) {
            change
                .after('<i class="fa fa-refresh fa-spin fa-fw tmp-loader"></i>');

            // prepare data
            var data = {
                'user_id': item.data('id'),
                'field': field,
                'value': change.val()
            };
            console.log(data)
            // Save
            $.post(
                'sources/users.queries.php', {
                    type: 'save_user_change',
                    data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                    key: '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    if (change.is('input') === true) {
                        change.remove();
                        $('.tmp-loader').remove();
                        item
                            .text(change.val())
                            .removeClass('hidden');
                        $('#table-users thead th:eq(' + (columnId - 1) + ')').width(initialColumnWidth)
                    } else if (change.is('select') === true) {
                        $("#select-managedBy").detach().appendTo('#hidden-managedBy');
                        $('#table-users thead th:eq(' + (columnId - 1) + ')').width(initialColumnWidth)
                        $('.tmp-loader, .temp-button').remove();

                        // Show change
                        console.log(change)
                        item
                            .html(change.text())
                            .attr('data-value', change.val())
                            .removeClass('hidden');
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
            $('#table-users thead th:eq(' + (columnId - 1) + ')').width(initialColumnWidth)
        }
    }

    /**
     * Refreshing list of users from LDAP
     *
     * @return void
     */
    function refreshListUsersLDAP() {
        // FIND ALL USERS IN LDAP
        toastr.remove();
        toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        $('#row-ldap-body')
            .addClass('overlay')
            .html('');

        $.post(
            "sources/users.queries.php", {
                type: "get_list_of_users_in_ldap",
                key: "<?php echo $_SESSION['key']; ?>"
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
                        '<?php echo langHdl('caution'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    // loop on users list
                    var html = '',
                        groupsNumber = 0,
                        userLogin,
                        group;
                    var entry;
                    $.each(data.entries, function(i, entry) {
                        userLogin = entry[store.get('teampassSettings').ldap_user_attribute] !== undefined ? entry[store.get('teampassSettings').ldap_user_attribute][0] : '';
                        // CHeck if not empty
                        if (userLogin !== '') {
                            html += '<tr>' +
                                '<td>' + userLogin +
                                '</td>' +
                                '<td>' +
                                '<i class="fas fa-info-circle ml-3 infotip text-info pointer text-center" data-toggle="tooltip" data-html="true" title="' +
                                '<p class=\'text-left\'><i class=\'fas fa-user mr-1\'></i>' +
                                (entry.displayname !== undefined ? '' + entry.displayname[0] + '' : '') + '</p>' +
                                '<p class=\'text-left\'><i class=\'fas fa-envelope mr-1\'></i>' + (entry.mail !== undefined ? '' + entry.mail[0] + '' : '') + '</p>' +
                                '"></i>' +
                                '</td>' +
                                '<td>' + (entry.teampass !== undefined && entry.teampass.id !== undefined ? '<i class="fas fa-toggle-on text-info mr-1 text-center"></i>' : '<i class="fas fa-toggle-off mr-1 text-center"></i>') + '</td>' +
                                '<td>';
                            groupsNumber = 0;
                            $.each(entry.memberof, function(j, group) {
                                var regex = String(group).replace('CN=', 'cn').match(/(cn=)(.*?),/g);
                                if (regex !== null) {
                                    group = regex[0].replace('cn=', '').replace(',', '');
                                    // Check if this user has this group in Teampass
                                    if (entry.teampass !== undefined && entry.teampass.groups.filter(p => p.title === group).length > 0) {
                                        html += group + '<i class="far fa-check-circle text-success ml-2 infotip" title="<?php echo langHdl('user_has_this_role_in_teampass'); ?>"></i><br>';
                                    } else {
                                        // Check if this group exists in Teampass and propose to add it
                                        tmp = data.teampassGroups.filter(p => p.title === group);
                                        if (tmp.length > 0 && entry.teampass !== undefined) {
                                            html += group + '<i class="fas fa-user-graduate text-primary ml-2 pointer infotip action-add-role-to-user" title="<?php echo langHdl('add_user_to_role'); ?>" data-user-id="' + entry.teampass.id + '" data-role-id="' + tmp[0].id + '"></i><br>';
                                        } else {
                                            html += group + '<br>';
                                        }
                                    }
                                    groupsNumber++;
                                }
                            });
                            html += '</td><td>';

                            // Action icons
                            html += (entry.teampass === undefined ? '<i class="fas fa-user-plus text-warning ml-2 infotip pointer add-user-icon" title="<?php echo langHdl('add_user_in_teampass'); ?>" data-user-login="' + userLogin + '" data-user-email="' + entry.mail[0] + '" data-user-name="' + (entry.givenname !== undefined ? entry.givenname[0] : '') + '" data-user-lastname="' + entry.sn[0] + '"></i>' : '');

                            // Only of not admin
                            if (userLogin !== 'admin') {
                                html += (entry.teampass.auth === 'ldap' ? '<i class="fas fa-link text-success ml-2 infotip pointer auth-local" title="<?php echo langHdl('ldap_user_password_is_used_for_authentication'); ?>" data-user-id="' + entry.teampass.id + '"></i>' : '<i class="fas fa-unlink text-orange ml-2 infotip pointer auth-ldap" title="<?php echo langHdl('local_user_password_is_used_for_authentication'); ?>" data-user-id="' + entry.teampass.id + '"></i>');
                            }

                            html += '</td></tr>';
                        }
                    });

                    $('#row-ldap-body').html(html);

                    $('#row-ldap-body').removeClass('overlay');

                    $('.infotip').tooltip('update');

                    // Build list box of new roles that could be created
                    $('#ldap-new-role-selection')
                        .empty()
                        .append('<option value="">--- <?php echo langHdl('select'); ?> ---</option>');
                    $.each(data.adGroups, function(i, group) {
                        tmp = data.teampassGroups.filter(p => p.title === group);
                        if (tmp.length === 0) {
                            $('#ldap-new-role-selection').append(
                                '<option value="' + group + '">' + group + '</option>'
                            );
                        }
                    });

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
     * Permits to add a role to a Teampass user
     *
     * @return void
     */
    function addRoleToUser() {
        toastr.remove();
        toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // prepare data
        var data = {
            'user_id': $('.selected-role').data('user-id'),
            'field': 'fonction_id',
            'value': $('.selected-role').data('role-id'),
            'context': 'add_one_role_to_user'
        };

        $.post(
            'sources/users.queries.php', {
                type: 'save_user_change',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                console.log(data);

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
                    // CHange icon
                    $('.selected-role')
                        .removeClass('fas fa-user-graduate text-primary pointer action-add-role-to-user')
                        .addClass('far fa-check-circle text-success')
                        .prop('title', '<?php echo langHdl('user_has_this_role_in_teampass'); ?>');

                    $('.infotip').tooltip();

                    // Inform user
                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                }
                $('.selected-role').removeClass('selected-role');
            }
        );
    }

    $(document).on('click', '.action-add-role-to-user', function() {
        $(this).addClass('selected-role');

        toastr.warning(
            '&nbsp;<button type="button" class="btn clear btn-toastr" style="width:100%;" onclick="addRoleToUser()"><?php echo langHdl('please_confirm'); ?></button>',
            '<?php echo langHdl('info'); ?>', {
                positionClass: 'toast-top-center',
                closeButton: true
            }
        );
    });


    /**
     * Permits to add an AD user in Teampass
     *
     * @return void
     */
    function addUserInTeampass() {
        toastr.remove();
        toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // prepare data
        var data = {
            'login': $('.selected-user').data('user-login'),
            'name': $('.selected-user').data('user-name'),
            'lastname': $('.selected-user').data('user-lastname'),
            'email': $('.selected-user').data('user-email'),
        };
        console.log(data)

        $.post(
            'sources/users.queries.php', {
                type: 'add_user_from_ldap',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                console.log(data);

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
                    refreshListUsersLDAP()
                }
            }
        );
    }

    /**
     * Permits to change the auth type of the user
     *
     * @return void
     */
    function changeUserAuthType(auth) {
        toastr.remove();
        toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // prepare data
        var data = {
            'id': $('.selected-user').data('user-id'),
            'auth_type': auth
        };
        console.log(data)

        $.post(
            'sources/users.queries.php', {
                type: 'change_user_auth_type',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                console.log(data);

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
                    refreshListUsersLDAP()
                }
            }
        );
    }

    $(document)
        .on('click', '.add-user-icon', function() {
            $(this).addClass('selected-user');

            toastr.warning(
                '&nbsp;<button type="button" class="btn clear btn-toastr" style="width:100%;" onclick="addUserInTeampass()"><?php echo langHdl('please_confirm'); ?></button>',
                '<?php echo langHdl('add_user_in_teampass'); ?>', {
                    positionClass: 'toast-top-center',
                    closeButton: true
                }
            );
        })
        .on('click', '.auth-ldap', function() {
            $(this).addClass('selected-user');

            toastr.warning(
                '&nbsp;<button type="button" class="btn clear btn-toastr" style="width:100%;" onclick="changeUserAuthType(\'ldap\')"><?php echo langHdl('please_confirm'); ?></button>',
                '<?php echo langHdl('change_authentification_type_to_ldap'); ?>', {
                    positionClass: 'toast-top-center',
                    closeButton: true
                }
            );
        })
        .on('click', '.auth-local', function() {
            $(this).addClass('selected-user');

            toastr.warning(
                '&nbsp;<button type="button" class="btn clear btn-toastr" style="width:100%;" onclick="changeUserAuthType(\'local\')"><?php echo langHdl('please_confirm'); ?></button>',
                '<?php echo langHdl('change_authentification_type_to_local'); ?>', {
                    positionClass: 'toast-top-center',
                    closeButton: true
                }
            );
        });





    //]]>
</script>