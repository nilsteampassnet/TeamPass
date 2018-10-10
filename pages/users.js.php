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

$('.infotip').tooltip();

$('.select2').select2({
    language: '<?php echo $_SESSION['user_language_code']; ?>'
});

// Prepare iCheck format for checkboxes
$('input[type="checkbox"].flat-red').iCheck({
    checkboxClass: 'icheckbox_flat-red',
});

var times = [
	"12:00 am", 
	"1:00 am", 
	"2:00 am"
];

//Launch the datatables pluggin
var oTable = $("#table-users").DataTable({
    "paging": true,
    "searching": true,
    "order": [[1, "asc"]],
    "info": true,
    "processing": false,
    "serverSide": true,
    "responsive": true,
    "select": false,
    "stateSave": true,
    "autoWidth": true,
    "ajax": {
        url: "<?php echo $SETTINGS['cpassman_url']; ?>/sources/users.datatable.php",
        /*data: function(d) {
            d.letter = _alphabetSearch
        }*/
    },
    "language": {
        "url": "<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user_language']; ?>.txt"
    },
    "columns": [
        {
            "width": "80px",
            className: "details-control",
            "render": function(data, type, row, meta){
                return '<span class="input-group-btn btn-user-action">' +
                   '<button type="button" class="btn btn-default dropdown-toggle btn-sm" data-toggle="dropdown">' +
                   '<i class="fas fa-gear"></i>' +
                   '</button>' +
                   '<ul class="dropdown-menu" role="menu">' +
                   '<li class="dropdown-item pointer tp-action" data-id="' + $(data).data('id') + '" data-action="edit"><i class="fas fa-pen mr-2"></i><?php echo langHdl('edit'); ?></li>' +
                   '<li class="dropdown-item pointer tp-action" data-id="' + $(data).data('id') + '" data-action="pwd"><i class="fas fa-key mr-2"></i><?php echo langHdl('change_password'); ?></li>' +
                   '<li class="dropdown-item pointer tp-action" data-id="' + $(data).data('id') + '" data-action="logs"><i class="fas fa-newspaper mr-2"></i><?php echo langHdl('see_logs'); ?></li>' +
                   '<li class="dropdown-item pointer tp-action" data-id="' + $(data).data('id') + '" data-action="qrcode"><i class="fas fa-qrcode mr-2"></i><?php echo langHdl('user_ga_code'); ?></li>' +
                   '<li class="dropdown-item pointer tp-action" data-id="' + $(data).data('id') + '" data-action="folders"><i class="fas fa-sitemap mr-2"></i><?php echo langHdl('user_folders_rights'); ?></li>' +
                   '<li class="dropdown-item pointer tp-action" data-id="' + $(data).data('id') + '" data-action="delete"><i class="fas fa-trash mr-2"></i><?php echo langHdl('delete'); ?></li>' +
                   '</ul>'
                   '</span>';
            }
        },
        {className: "dt-body-left"},
        {className: "dt-body-left"},
        {className: "dt-body-left"},
        {className: "dt-body-left"},
        {className: "dt-body-left"},
        {"width": "70px", className: "dt-body-center"},
        {"width": "70px", className: "dt-body-center"},
        {"width": "70px", className: "dt-body-center"},
        {"width": "70px", className: "dt-body-center"},
        {"width": "70px", className: "dt-body-center"},
        {"width": "70px", className: "dt-body-center"}
    ],
    "preDrawCallback": function() {
        alertify
            .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();
    },
    "drawCallback": function() {
        // Tooltips
        $('.infotip').tooltip();

        // Hide checkbox if search filtering
        var searchCriteria = $("body").find("[aria-controls='table-folders']");
        if (searchCriteria.val() !== '' ) {
            $(document).find('.cb_selected_folder').addClass('hidden');
        } else {
            $(document).find('.cb_selected_folder').removeClass('hidden');
        }

        // Inform user
        alertify
            .success('<?php echo langHdl('done'); ?>', 1)
            .dismissOthers();
    },
    "createdRow": function( row, data, dataIndex ) {
        var newClasses = $(data[6]).filter('#row-class-' + dataIndex).val();
        $(row).addClass(newClasses);
    }
});

// Array to track the ids of the details displayed rows
var detailRows = [];
 
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
        /*'<select id="speed">' +
        '<option>Slower</option>' +
          '<option>Slower</option>' +
          '<option>Slower</option>' +
        '<option>Slower</option>' +
        '</select>'
            ,*/
            'new-row'
        ).show();

        //$( "#speed" ).selectmenu();
     }
 } );

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


/**
 * TOP MENU BUTTONS ACTIONS
 */
$(document).on('click', '.tp-action', function() {
    if ($(this).data('action') === 'new') {
        $('#row-list').addClass('hidden');
        $('#row-form').removeClass('hidden');

    }  else if ($(this).data('action') === 'edit') {
        $('#row-list').addClass('hidden');
        $('#row-form').removeClass('hidden');


        $.post(
            "sources/users.queries.php",
            {
                type : "get_user_info",
                id   : $(this).data('id'),
                key  : "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                console.log(data)
                if (data.error === "no") {
                    $('#form-login').val(data.login);
                    $('#form-email').val(data.email);
                    $('#form-name').val(data.name);
                    $('#form-lastname').val(data.lastname);

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


                    $('#form-roles, #form-managedby, #form-auth, #form-forbid').select2();
                } else {
                   
                }
            },
            "json"
        );
    }  else if ($(this).data('action') === 'submit') {
        //


        // Now save
        // get lists
        var forbidFld = {},
            authFld = {},
            groups = {};
        $("#new_user_groups option:selected").each(function () {
            //groups += $(this).val() + ";";
            groups.push($(this).val())
        });
        $("#new_user_auth_folders option:selected").each(function () {
            //authFld += $(this).val() + ";";
            authFld.push($(this).val())
        });
        $("#new_user_forbid_folders option:selected").each(function () {
            //forbidFld += $(this).val() + ";";
            forbidFld.push($(this).val())
        });

        //prepare data
        var data = {
            "login" : $('#new_login').val(),
            "name" : $('#new_name').val(),
            "lastname" : $('#new_lastname').val(),
            "pw" : $('#new_pwd').val(),
            "email" : $("#new_email").val(),
            "admin" : $("#new_admin").prop("checked"),
            "manager" : $("#new_manager").prop("checked"),
            "read_only" : $("#new_read_only").prop("checked"),
            "personal_folder" : $("#new_personal_folder").prop("checked"),
            "new_folder_role_domain" : $("#new_folder_role_domain").prop("checked"),
            "domain" : $('#new_domain').val(),
            "isAdministratedByRole" : $("#new_is_admin_by").val(),
            "groups" : groups,
            "allowed_flds" : authFld,
            "forbidden_flds" : forbidFld
        };

        $.post(
            "sources/users.queries.php",
            {
                type    :"add_new_user",
                data     : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key    : "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                $("#add_new_user_info").hide().html("");
                if (data[0].error === "no") {
                    // clear form fields
                    $("#new_name, #new_lastname, #new_login, #new_pwd, #new_is_admin_by, #new_email, #new_domain").val("");
                    $("#new_admin, #new_manager, #new_read_only, #new_personal_folder").prop("checked", false);

                    // refresh table content
                    oTable.ajax.reload();

                    $("#add_new_user").dialog("close");
                } else {
                    $("#add_new_user_error").html(data[0].error).show(1).delay(1000).fadeOut(1000);
                }
            },
            "json"
        )

    }  else if ($(this).data('action') === 'cancel') {
        $('.clear-me').val('');
        $('.select2').val('').change();
        $('#row-form').addClass('hidden');
        $('#row-list').removeClass('hidden');

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
//]]>
</script>