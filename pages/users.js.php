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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'folders', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}
?>


<script type='text/javascript'>
//<![CDATA[
    
/*
$.extend($.expr[":"], {
    "containsIN": function(elem, i, match, array) {
        return (elem.textContent || elem.innerText || "").toLowerCase().indexOf((match[3] || "").toLowerCase()) >= 0;
    }
});

// prepare Alphabet
var _alphabetSearch = '';
$.fn.dataTable.ext.search.push( function ( settings, searchData ) {
    if ( ! _alphabetSearch ) {
        return true;
    }
    if ( searchData[0].charAt(0) === _alphabetSearch ) {
        return true;
    }
    return false;
} );
*/
// Initialization
var userDidAChange = false;

store.each(function(value, key) {
        console.log(key, '==', value)
    })

// Prepare tooltips
$('.infotip').tooltip();

// Prepare Select2
$('.select2').select2({
    language: '<?php echo $_SESSION['user_language_code']; ?>'
});

// Prepare iCheck format for checkboxes
$('input[type="checkbox"].flat-blue, input[type="radio"].flat-blue').iCheck({
    radioClass   : 'iradio_flat-blue',
    checkboxClass   : 'icheckbox_flat-blue',
});
$('#create-special-folder').iCheck('disable');

// Prevent submit on button
$('.btn-no-click')
    .click(function(e) {
        e.preventDefault();
    });

// For Personal Saltkey
$("#form-password").simplePassMeter({
    "requirements": {},
    "container": "#form-password-strength",
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
$("#form-password").bind({
    "score.simplePassMeter" : function(jQEvent, score) {
        $("#form-password-complex").val(score);
    }
}).change({
    "score.simplePassMeter" : function(jQEvent, score) {
        $("#form-password-complex").val(score);
    }
});


//Launch the datatables pluggin
var oTable = $('#table-users').DataTable({
    'paging': true,
    'searching': true,
    'order': [[1, 'asc']],
    'info': true,
    'processing': false,
    'serverSide': true,
    'responsive': true,
    'select': false,
    'stateSave': true,
    'autoWidth': true,
    'ajax': {
        url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/users.datatable.php',
        /*data: function(d) {
            d.letter = _alphabetSearch
        }*/
    },
    'language': {
        'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user_language']; ?>.txt'
    },
    'columns': [
        {
            'width': '80px',
            className: 'details-control',
            'render': function(data, type, row, meta){
                return '<span class="input-group-btn btn-user-action">' +
                   '<button type="button" class="btn btn-default dropdown-toggle btn-sm" data-toggle="dropdown">' +
                   '<i class="fas fa-gear"></i>' +
                   '</button>' +
                   '<ul class="dropdown-menu" role="menu">' +
                   '<li class="dropdown-item pointer tp-action" data-id="' + $(data).data('id') + '" data-action="edit"><i class="fas fa-pen mr-2"></i><?php echo langHdl('edit'); ?></li>' +
                   //'<li class="dropdown-item pointer tp-action" data-id="' + $(data).data('id') + '" data-action="pwd"><i class="fas fa-key mr-2"></i><?php echo langHdl('change_password'); ?></li>' +
                   '<li class="dropdown-item pointer tp-action" data-id="' + $(data).data('id') + '" data-fullname="' + $(data).data('fullname') + '" data-action="logs"><i class="fas fa-newspaper mr-2"></i><?php echo langHdl('see_logs'); ?></li>' +
                   '<li class="dropdown-item pointer tp-action" data-id="' + $(data).data('id') + '" data-action="qrcode"><i class="fas fa-qrcode mr-2"></i><?php echo langHdl('user_ga_code'); ?></li>' +
                   '<li class="dropdown-item pointer tp-action" data-id="' + $(data).data('id') + '" data-action="folders"><i class="fas fa-sitemap mr-2"></i><?php echo langHdl('user_folders_rights'); ?></li>' +
                   //'<li class="dropdown-item pointer tp-action" data-id="' + $(data).data('id') + '" data-action="delete"><i class="fas fa-trash mr-2"></i><?php echo langHdl('delete'); ?></li>' +
                   '</ul>'
                   '</span>';
            }
        },
        {className: 'dt-body-left'},
        {className: 'dt-body-left'},
        {className: 'dt-body-left'},
        {className: 'dt-body-left'},
        {className: 'dt-body-left'},
        {'width': '70px', className: 'dt-body-center'},
        {'width': '70px', className: 'dt-body-center'},
        {'width': '70px', className: 'dt-body-center'}
    ],
    'preDrawCallback': function() {
        alertify
            .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();
    },
    'drawCallback': function() {
        // Tooltips
        $('.infotip').tooltip();

        /*// Hide checkbox if search filtering
        var searchCriteria = $('body').find('[aria-controls="table-folders"]');
        if (searchCriteria.val() !== '' ) {
            $(document).find('.cb_selected_folder').addClass('hidden');
        } else {
            $(document).find('.cb_selected_folder').removeClass('hidden');
        }*/

        // Inform user
        alertify
            .success('<?php echo langHdl('done'); ?>', 1)
            .dismissOthers();
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
    $.post("sources/users.queries.php",
        {
            type        : "check_domain",
            domain      : domain
        },
        function(data) {
            data = $.parseJSON(data);console.log(data)
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
    if ($("#form-login").val() !== '') {
        return false;
    }
    // Build login
    if ($(this).attr('id') !== 'form-login') {
        $("#form-login").val(
            $("#form-name").val().toLowerCase().replace(/ /g,"")+"."+$("#form-lastname").val().toLowerCase().replace(/ /g,"")
        );
    }

    // Check if login exists
    $.post(
        "sources/users.queries.php",
        {
            type    : "is_login_available",
            login   : $("#form-login").val(),
            key     : "<?php echo $_SESSION['key']; ?>"
        },
        function(data) {
            if (data[0].error === "") {
                if (data[0].exists === "0") {
                    $("#form-login")
                        .removeClass('is-invalid')
                        .addClass('is-valid');
                    $('#form-login-conform').val(true);
                } else {
                    $("#form-login")
                        .removeClass('is-valid')
                        .addClass('is-invalid');
                    $('#form-login-conform').val(false);
                }
            }
        },
        "json"
    );
})

/**
* PREPARE THE ACTION BUTTON BY USER
*//*
$('#table-users tbody').on( 'click', '.btn-action', function () {
    var tr = $(this).closest('tr');
    var row = oTable.row( tr );

    if ( row.child.isShown() ) {
    row.child.remove();
    $(this)
        .removeClass('text-warning gear-selected');
    }
    else {
    $('.new-row').remove();
    $('.gear-selected').removeClass('text-warning gear-selected');

    $(this)
        .addClass('text-warning gear-selected');        

    // Prepare
    row.child(
        '<button type="button" class="btn btn-secondary btn-sm tp-action mr-2" data-action="edit"><i class="fas fa-pen mr-2"></i><?php echo langHdl('edit'); ?></button>' +
        '<button type="button" class="btn btn-secondary btn-sm tp-action mr-2" data-action="password"><i class="fas fa-key mr-2"></i><?php echo langHdl('change_password'); ?></button>' +
        '<button type="button" class="btn btn-secondary btn-sm tp-action mr-2" data-action="logs"><i class="fas fa-newspaper mr-2"></i><?php echo langHdl('see_logs'); ?></button>' +
        '<button type="button" class="btn btn-secondary btn-sm tp-action mr-2" data-action="qrcode"><i class="fas fa-qrcode mr-2"></i><?php echo langHdl('user_ga_code'); ?></button>' +
        '<button type="button" class="btn btn-secondary btn-sm tp-action mr-2" data-action="sitemap"><i class="fas fa-sitemap mr-2"></i><?php echo langHdl('user_folders_rights'); ?></button>' +
        'new-row'
    ).show();
    }
} );
*/
 

/**
 * TOP MENU BUTTONS ACTIONS
 */
$(document).on('click', '.tp-action', function() {
    // Ensure that password strength indicator is reseted
    $('#form-password').focus();

    // Hide if user is not admin
    if (store.get('teampassUser').user_admin === 1 || store.get('teampassUser').user_can_manage_all_users === 1){
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
            function (teampassApplication)
            {
                teampassApplication.formUserAction = 'add_new_user';
            }
        );

    }  else if ($(this).data('action') === 'edit') {
        // SHow user
        alertify
            .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();

        // EDIT EXISTING USER
        $('#row-list, #group-create-special-folder, #group-delete-user').addClass('hidden');
        $('#row-form, #group-form-user-disabled').removeClass('hidden');
        $('.form-check-input').iCheck('enable');

        // Personal folder
        if (store.get('teampassSettings').enable_pf_feature === '0') {
            $('#form-create-personal-folder').iCheck('disable');
        }

        // HIDE FROM FORM ELEMENTS ONLY FOR ADMIN
        if (store.get('teampassApplication').user_admin === 1) {
            $('input[type=radio].only-admin').iCheck('enable');
        } else {
            $('input[type=radio].only-admin').iCheck('disable');
        }
        
        // What type of form? Edit or new user
        var userID = $(this).data('id');
        store.update(
            'teampassApplication',
            function (teampassApplication)
            {
                teampassApplication.formUserAction = 'store_user_changes',
                teampassApplication.formUserId = userID; // Store user ID
            }
        );

        $.post(
            "sources/users.queries.php",
            {
                type : "get_user_info",
                id   : userID,
                key  : "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
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
                        tmp += '<option value="'+value.id+'" '+value.selected+'>'+value.title+'</option>';
                    });
                    $('#form-auth').append(tmp);

                    tmp = '';
                    $(data.foldersForbid).each(function(i, value) {
                        tmp += '<option value="'+value.id+'" '+value.selected+'>'+value.title+'</option>';
                    });
                    $('#form-forbid').append(tmp);

                    tmp = '';
                    $(data.managedby).each(function(i, value) {
                        tmp += '<option value="'+value.id+'" '+value.selected+'>'+value.title+'</option>';
                    });
                    $('#form-managedby').append(tmp);

                    tmp = '';
                    $(data.function).each(function(i, value) {
                        tmp += '<option value="'+value.id+'" '+value.selected+'>'+value.title+'</option>';
                    });
                    $('#form-roles').append(tmp);

                    // Prepare default password
                    $('#form-password, #form-confirm').val(data.password);

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

                    // Inform user
                    alertify
                        .success('<?php echo langHdl('done'); ?>', 1)
                        .dismissOthers();
                } else {
                    alertify
                        .error('<i class="fa fa-ban mr-2"></i>' + data.message, 3)
                        .dismissOthers();
                    return false;
                }
            }
        );
    }  else if ($(this).data('action') === 'submit') {
        // Manage case of delete
        if ($('#form-delete-user-confirm').prop('checked') === true) {
            alertify.confirm(
                '<?php echo langHdl('please_confirm'); ?>',
                '<?php echo langHdl('please_confirm_user_deletion'); ?>',
                function() {
                    var data = {
                        'user_id'   : store.get('teampassApplication').formUserId,
                    }
                    // Send query to server
                    $.post(
                        'sources/users.queries.php',
                        {
                            type    : 'delete_user',
                            data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                            key     : "<?php echo $_SESSION['key']; ?>"
                        },
                        function(data) {
                            data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
                            console.log(data);
                            
                            if (data.error !== false) {
                                // Show error
                                alertify
                                    .error('<i class="fa fa-ban mr-2"></i>' + data.message, 3)
                                    .dismissOthers();

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
                                alertify
                                    .success('<?php echo langHdl('done'); ?>', 1)
                                    .dismissOthers();
                            }
                        }
                    );
                },
                function() {
                    alertify
                        .message('<?php echo langHdl('cancel'); ?>', 3)
                        .dismissOthers();
                }
            );
            return false;
        }

        // Loop on all changed fields
        var arrayQuery = [];
        $('.form-control').each(function(i, obj) {
            if ($(this).data('change-ongoing') === true) {
                arrayQuery.push({
                    'field' : $(this).prop('id'),
                    'value' : $(this).val(),
                });
            }
        });
console.log(arrayQuery);
        if (arrayQuery.length > 0) {
            // Now save
            // get lists
            var forbidFld = [],
                authFld = [],
                groups = [];
            $("#form-roles option:selected").each(function () {
                groups.push($(this).val())
            });
            $("#form-auth option:selected").each(function () {
                authFld.push($(this).val())
            });
            $("#form-forbid option:selected").each(function () {
                forbidFld.push($(this).val())
            });

            // Mandatory?
            var validated = true;
            $('.required').each(function(i, obj) {
                if($(this).val() === '' && $(this).hasClass('select2') === false) {
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
                alertify
                    .error('<i class="fa fa-ban mr-2"></i><?php echo langHdl('fields_with_mandatory_information_are_missing'); ?>', 3)
                    .dismissOthers();
                return false;
            }

            // Passwords are ok?
            if ($('#form-password').val() !== $('#form-password').val()) {
                alertify
                    .error('<i class="fa fa-ban mr-2"></i><?php echo langHdl('password_confirmation_is_different'); ?>', 3)
                    .dismissOthers();
                return false;
            } else if ($('#form-password').val().length <= 5) {
                alertify
                    .error('<i class="fa fa-ban mr-2"></i><?php echo langHdl('password_too_short'); ?>', 3)
                    .dismissOthers();
                return false;
            } else if ($('#form-login-conform').val() === false) {
                alertify
                    .error('<i class="fa fa-ban mr-2"></i><?php echo langHdl('error_user_exists'); ?>', 3)
                    .dismissOthers();
                return false;
            }

            // SHow user
            alertify
                .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
                .dismissOthers();

            //prepare data
            var data = {
                'user_id' : store.get('teampassApplication').formUserId,
                'login' : $('#form-login').val(),
                'name' : $('#form-name').val(),
                'lastname' : $('#form-lastname').val(),
                'pw' : $('#form-password').val(),
                'email' : $('#form-email').val(),
                'admin' : $('#privilege-admin').prop('checked'),
                'manager' : $('#privilege-manager').prop('checked'),
                'hr' : $('#privilege-hr').prop('checked'),
                'read_only' : $('#privilege-ro').prop('checked'),
                'personal_folder' : $('#form-create-personal-folder').prop('checked'),
                'new_folder_role_domain' : $('#create-special-folder').prop('checked'),
                'domain' : $('#form-special-folder').val(),
                'isAdministratedByRole' : $('#form-managedby').val(),
                'groups' : groups,
                'allowed_flds' : authFld,
                'forbidden_flds' : forbidFld,
                'action_on_user' : 'update',
                'form-create-root-folder' : $('#form-create-root-folder').prop('checked'),
                'form-user-disabled' : $('#form-user-disabled').prop('checked'),
            };
            console.log(data);

            $.post(
                'sources/users.queries.php',
                {
                    type    : store.get('teampassApplication').formUserAction,
                    data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                    key     : "<?php echo $_SESSION['key']; ?>"
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
                        // Inform user
                        alertify
                            .success('<?php echo langHdl('done'); ?>', 1)
                            .dismissOthers();

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
                    }

                    // Remove action from store
                    store.update(
                        'teampassApplication',
                        function (teampassApplication)
                        {
                            teampassApplication.formUserAction = '',
                            teampassApplication.formUserId = '';
                        }
                    );
                }
            )
        }
    }  else if ($(this).data('action') === 'cancel') {
        $('.clear-me').val('');
        $('.select2').val('').change();
        $('.extra-form').addClass('hidden');
        $('#row-list').removeClass('hidden');

        // Prepare checks
        $('.form-check-input')
            .iCheck('disable')
            .iCheck('uncheck');

        // Remove action from store
        store.update(
            'teampassApplication',
            function (teampassApplication)
            {
                teampassApplication.formUserAction = '',
                teampassApplication.formUserId = '';
            }
        );
    } else if ($(this).data('action') === 'qrcode') {
        // This sends a GA Code by email to user
        data = {
            'user_id'            : $(this).data('id'),
            'demand_origin' : 'users_management_list',
            'send_email'    : '1'
        }
        
        $.post(
            'sources/main.queries.php',
            {
                type    : 'ga_generate_qr',
                data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key     : "<?php echo $_SESSION['key']; ?>"
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
                    // Inform user
                    alertify
                        .success('<?php echo langHdl('share_sent_ok'); ?>', 1)
                        .dismissOthers();
                }
            }
        );

    } else if ($(this).data('action') === 'logs') {
        $('#row-list').addClass('hidden');
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
            'order': [[1, 'asc']],
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
                data: function (d) {
                    d.userId = userID;
                }
            },
            'language': {
                'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user_language']; ?>.txt'
            },
            'columns': [
                {className: 'dt-body-left'},
                {className: 'dt-body-left'},
                {className: 'dt-body-left'}
            ],
            'preDrawCallback': function() {
                alertify
                    .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
                    .dismissOthers();
            },
            'drawCallback': function() {
                // Tooltips
                $('.infotip').tooltip();

                // Inform user
                alertify
                    .success('<?php echo langHdl('done'); ?>', 1)
                    .dismissOthers();
            },
        });

        
    } else if ($(this).data('action') === 'folders') {


    } else if ($(this).data('action') === 'refresh') {
        $('.form').addClass('hidden');
        $('#users-list')
            .removeClass('hidden');
        alertify
            .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();
        oTable.ajax.reload();
    }
});



/**
 * GENERATE PASSWORD
 */
$('#button-password-generate').click(function() {
    $.post(
        'sources/main.queries.php',
        {
            type        : 'generate_password',
            size        : 10,
            secure_pwd  : 'true',
            key         : "<?php echo $_SESSION['key']; ?>"
        },
        function(data) {
            data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
            console.log(data);
            
            if (data.error == 'true') {
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
                $('#form-password, #form-confirm').val(data.key);
            }
            $('#form-password').focus();
        }
   );
});


/**
 * TRACK CHANGES IN FORM
 */
$('#form-user .track-change').on('change', function() {
    if ($(this).val() !== null && $(this).val().length > 0) {
        userDidAChange = true;
        $(this).data('change-ongoing', true);
    } else {
        $(this).data('change-ongoing', false);
    }
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
        'login' : {
            'column' : 2
        },
        'name' : {
            'column' : 3
        },
        'lastname' : {
            'column' : 4
        },
        'isAdministratedByRole' : {
            'column' : 5
        },
        'fonction_id' : {
            'column' : 6
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
    initialColumnWidth = $('#table-users thead th:eq('+(columnId-1)+')').width();
    $('#table-users thead th:eq('+(columnId-1)+')').width('300');
    console.log('Width '+initialColumnWidth)

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
    initialColumnWidth = $('#table-users thead th:eq('+(columnId-1)+')').width();
    $('#table-users thead th:eq('+(columnId-1)+')').width('300');

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
        $('#table-users thead th:eq('+(columnId-1)+')').width(initialColumnWidth);
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


function saveChange(item, currentText, change, field)
{
    if (change.val() !== currentText) {
        change
            .after('<i class="fa fa-refresh fa-spin fa-fw tmp-loader"></i>');

        // prepare data
        var data = {
            'user_id'   : item.data('id'),
            'field'     : field,
            'value'     : change.val()
        };
        console.log(data)
        // Save
        $.post(
            'sources/users.queries.php',
            {
                type :  'save_user_change',
                data :  prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                key  :  '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                if (change.is('input') === true) {
                    change.remove();
                    $('.tmp-loader').remove();
                    item
                        .text(change.val())
                        .removeClass('hidden');
                        $('#table-users thead th:eq('+(columnId-1)+')').width(initialColumnWidth)
                } else if (change.is('select') === true) {
                    $("#select-managedBy").detach().appendTo('#hidden-managedBy');
                    $('#table-users thead th:eq('+(columnId-1)+')').width(initialColumnWidth)
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
        $('#table-users thead th:eq('+(columnId-1)+')').width(initialColumnWidth)
    }
}



//]]>
</script>