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
/**
 * Refreshes the table with list of folders
 *
 * @return void
 */
function refreshTable()
{
    // Launch action
    $.post(
        'sources/folders.queries.php',
        {
            type    : 'refresh_list',
            key     : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {//decrypt data
            data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

            if (data.error !== true) {
                $.each(data)
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
}

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

//Launch the datatables pluggin
var oTable = $("#table-folders").dataTable({
    "paging": false,
    "searching": true,
    "order": [[1, "asc"]],
    "processing": false,
    "serverSide": true,
    "responsive": true,
    "select": false,
    "stateSave": true,
    "autoWidth": true,
    "ajax": {
        url: "<?php echo $SETTINGS['cpassman_url']; ?>/sources/datatable.folders.php",
        /*data: function(d) {
            d.letter = _alphabetSearch
        }*/
    },
    "language": {
        "url": "<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user_language']; ?>.txt"
    },
    "columns": [
        {"width": "80px"},
        {className: "dt-body-left"},
        {className: "dt-body-left"},
        {"width": "70px", className: "dt-body-center"},
        {"width": "70px", className: "dt-body-center"},
        {"width": "70px", className: "dt-body-center"},
        {"width": "90px", className: "dt-body-center"}
    ],
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
    },
    "createdRow": function( row, data, dataIndex ) {
        var newClasses = $(data[6]).filter('#row-class-' + dataIndex).val();
        $(row).addClass(newClasses);
    }
});

oTable
    .on('preXhr.dt', function () {
        alertify
            .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();
    } )
    .on('draw.dt', function () {
        alertify
            .success('<?php echo langHdl('done'); ?>', 1)
            .dismissOthers();
    } );


/*
// manage the Alphabet
var alphabet = $('<div class="alphabet"/>').append( 'Search: ' );
$('<span class="clear active"/>')
    .data( 'letter', '' )
    .html( 'None' )
    .appendTo( alphabet );
for ( var i=0 ; i<26 ; i++ ) {
    var letter = String.fromCharCode( 65 + i );

    $('<span/>')
        .data( 'letter', letter )
        .html( letter )
        .appendTo( alphabet );
}
alphabet.insertBefore( "#folders-alphabet" );
alphabet.on( 'click', 'span', function () {
    alphabet.find( '.active' ).removeClass( 'active' );
    $(this).addClass( 'active' );

    _alphabetSearch = $(this).data('letter');

    oTable.api().ajax.reload();
} );
*/

// Manage collapse/expend
$(document).on('click', '.icon-collapse', function() {
    if ($(this).hasClass('fa-minus-square-o') === true) {
        $(this)
            .removeClass('fa-minus-square-o')
            .addClass('fa-plus-square-o text-primary');

        $('.p' + $(this).data('id')).addClass('hidden');
    } else {
        $(this)
            .removeClass('fa-plus-square-o text-primary')
            .addClass('fa-minus-square-o');
            $('.p' + $(this).data('id')).removeClass('hidden');
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
    
    $(this)
        .addClass('hidden')
        .after('<input type="text" class="form-control form-item-control remove-me save-me" value="' + currentText + '">');
    
    if ($(this).hasClass('field-renewal')) {
        initialColumnWidth = $('#table-folders thead th')[4].style.width;
        $('#table-folders thead th')[4].style.width = '100px';
        field = 'renewal_period';
    } else if ($(this).hasClass('field-title')) {
        field = 'title';
        initialColumnWidth = $('#table-folders thead th')[2].style.width;
    }


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

    // Show select
    $("#select-complexity")
        .insertAfter('#' + $(this).attr('id'))
        .after('<i class="fa fa-close text-danger pointer temp-button mr-3" id="select-complexity-close"></i>');
    $('#select-complexity option[value="' + $(this).data('value') + '"]').prop('selected', true);
    console.log($(this).data('value'))
    
    if ($(this).hasClass('field-complex')) {
        initialColumnWidth = $('#table-folders thead th')[3].style.width;
        $('#table-folders thead th')[3].style.width = '200px';
        field = 'complexity';
    } else if ($(this).hasClass('field-title')) {
        field = 'title';
        initialColumnWidth = $('#table-folders thead th')[2].style.width;
    }


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
        
        // Save
        $.post(
            'sources/folders.queries.php',
            {
                type :  'save_folder_change',
                data :  prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                key  :  '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
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
                        .html(data.return.html)
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
                type :  'delete_multiple_folders1',
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
    } else if ($(this).data('action') === 'cancel') {
        deletionList = [];
        $('.clear-me').val('');
        $('#new-duration').val('0');
        $('.select2').val('').change();
        $('.form').addClass('hidden');
        $('#folders-list').removeClass('hidden');
    }
});

/*
$(document).on('click', '.icon-edit', function() {
    var tr = $(this).closest('tr');
    var row = oTable.row(tr);
    var itemId = $(this).data('id');
    
    // Change eye icon
    $(this)
        .removeClass('fa-eye')
        .addClass('fa-eye-slash text-warning');

    // Add loader
    $(this)
        .after('<i class="fa fa-refresh fa-spin fa-fw" id="search-loader"></i>');

    // Get content of item
    row.child(showFolderInfo(tr, itemId), 'new-row').show();

});


function showFolderInfo (tr, item) {
    // prepare data
    var data = {
        'folder_id' : item,
    };

    // Launch query
    $.post(
        'sources/folders.queries.php',
        {
            type :  'folder_details',
            data :  prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
            key  :  '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            //decrypt data
            data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
            console.info(data);
            var return_html = '';
            if (data.show_detail_option !== 0 || data.show_details === 0) {
                //item expired
                return_html = '<?php echo langHdl('not_allowed_to_see_pw_is_expired'); ?>';
            } else if (data.show_details === '0') {
                //Admin cannot see Item
                return_html = '<?php echo langHdl('not_allowed_to_see_pw'); ?>';
            } else {
                return_html = '<td colspan="7"><div class="alert bg-gray disabled">' +
                    '<h5  id="pwd-label_'+data.id+'">'+data.label+'</h5><dl>' +
                    '<dt><?php echo langHdl('description'); ?></dt><dd>'+data.description+'</dd>' +
                    '<dt><?php echo langHdl('pw'); ?></dt><dd>' +
                    '<div id="pwd-show_'+data.id+'" class="unhide_masked_data" style="height: 20px;"></div>'+
                    '<input id="pwd-hidden_'+data.id+'" type="hidden" value="' + unsanitizeString(data.pw) + '">' +
                    '<input type="hidden" id="pwd-is-shown_'+data.id+'" value="0"></dd>' +
                    '<dt><?php echo langHdl('index_login'); ?></dt><dd>'+data.login+'</dd>' +
                    '<dt><?php echo langHdl('url'); ?></dt><dd>'+data.url+'</dd>' +
                    '</dl></div></td>';
            }
            $(tr).next('tr').html(return_html);
            $('.unhide_masked_data').css('cursor', 'pointer');

            // show password during longpress
            $('.unhide_masked_data').mousedown(function(event) {
                mouseStillDown = true;
                showPwdContinuous($(this).attr('id'));
            }).mouseup(function(event) {
                mouseStillDown = false;
            }).mouseleave(function(event) {
                mouseStillDown = false;
            });

            $('#search-loader').remove();

            $('.infotip').tooltip();
        }
    );
    return data;
}
*/

$("#div_add_group").dialog({
    bgiframe: true,
    modal: true,
    autoOpen: false,
    width: 450,
    height: 460,
    title: "<?php echo $LANG['add_new_group']; ?>",
    open: function(event, ui) {
        $("#new_folder_wait").hide();

        //empty dialogbox
        $("#div_add_group input, #div_add_group select, #new_rep_roles").val("");
        $("#add_node_renewal_period").val("0");
        $("#folder_block_modif, #folder_block_creation").val("0");
        $("#parent_id").val("na");
    },
    buttons: {
        "<?php echo $LANG['save_button']; ?>": function() {
            //Check if renewal_period is an integer
            if (isInteger(document.getElementById("add_node_renewal_period").value) === false) {
                document.getElementById("addgroup_show_error").innerHTML = "<?php echo $LANG['error_renawal_period_not_integer']; ?>";
                $("#addgroup_show_error").show();
            } else if (document.getElementById("new_rep_complexite").value == "") {
                document.getElementById("addgroup_show_error").innerHTML = "<?php echo $LANG['error_group_complex']; ?>";
                $("#addgroup_show_error").show();
            } else if (document.getElementById("parent_id").value == "" || isNaN(document.getElementById("parent_id").value)) {
                document.getElementById("addgroup_show_error").innerHTML = "<?php echo $LANG['error_no_selected_folder']; ?>";
                $("#addgroup_show_error").show();
            } else {
                if (document.getElementById("ajouter_groupe_titre").value != "" && document.getElementById("parent_id").value != "na") {
                    $("#new_folder_wait").show();
                    $("#addgroup_show_error").hide();
                    //prepare data
                    var data = {
                        "title":$('#ajouter_groupe_titre').val().replace(/"/g,'&quot;') ,
                        "complexity": $('#new_rep_complexite').val().replace(/"/g,'&quot;'),
                        "parent_id": $('#parent_id').val().replace(/"/g,'&quot;') ,
                        "renewal_period": $('#add_node_renewal_period').val().replace(/"/g,'&quot;') ,
                        "block_creation": $("#folder_block_creation").val() ,
                        "block_modif": $("#folder_block_modif").val(),
                        "access_level": $("#new_rep_roles").val()
                    };

                    //send query
                    $.post(
                        "sources/folders.queries.php",
                        {
                            type    : "add_folder",
                            data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                            key     : "<?php echo $_SESSION['key']; ?>"
                        },
                        function(data) {
                            //Check errors
                            if (data[0].error == "error_group_exist") {
                                $("#div_add_group").dialog("open");
                                $("#addgroup_show_error").html("<?php echo $LANG['error_group_exist']; ?>");
                                $("#addgroup_show_error").show();
                            } else if (data[0].error == "error_html_codes") {
                                $("#div_add_group").dialog("open");
                                $("#addgroup_show_error").html("<?php echo $LANG['error_html_codes']; ?>");
                                $("#addgroup_show_error").show();
                            } else if (data[0].error == "error_title_only_with_numbers") {
                                $("#div_add_group").dialog("open");
                                $("#addgroup_show_error").html("<?php echo $LANG['error_only_numbers_in_folder_name']; ?>");
                                $("#addgroup_show_error").show();
                            } else if (data[0].error == "error_pwd_compexity_not_reached") {
                                $("#div_add_group").dialog("open");
                                $("#addgroup_show_error").html(data[0].msg);
                                $("#addgroup_show_error").show();
                            } else {
                                oTable.api().ajax.reload();
                                $("#parent_id, #edit_parent_id")
                                    .empty()
                                    .append(data[0].droplist);
                                $("#div_add_group").dialog("close");
                            }
                            $("#new_folder_wait").hide();
                        },
                        "json"
                    );
                } else {
                    document.getElementById("addgroup_show_error").innerHTML = "<?php echo $LANG['error_fields_2']; ?>";
                    $("#addgroup_show_error").show();
                }
            }
        },
        "<?php echo $LANG['cancel_button']; ?>": function() {
            $("#addgroup_show_error").html("").hide();
            $(this).dialog("close");
        }
    }
});

$("#div_edit_folder").dialog({
    bgiframe: true,
    modal: true,
    autoOpen: false,
    width: 450,
    height: 460,
    title: "<?php echo $LANG['at_category']; ?>",
    open: function(event, ui) {
        var id = $("#folder_id_to_edit").val();
        $("#edit_folder_wait").hide();
        
        //update dialogbox with data
        $("#edit_folder_title").val($("#title_"+id).text());
        $("#edit_folder_renewal_period").val($("#renewal_"+id).text());
        $("#edit_folder_complexite").val($("#renewal_id_"+id).val());
        $("#edit_parent_id option[value='"+$("#parent_id_"+id).val()+"']").prop('selected', true);
        $("#edit_folder_block_creation").val($("#block_creation_"+id).val());
        $("#edit_folder_block_modif").val($("#block_modif_"+id).val());
    },
    buttons: {
        "<?php echo $LANG['delete']; ?>": function() {
            if (confirm("<?php echo $LANG['confirm_delete_group']; ?>")) {
                //send query
                $.post(
                    "sources/folders.queries.php",
                    {
                        type    : "delete_folder",
                        id      : $("#folder_id_to_edit").val(),
                        key     : "<?php echo $_SESSION['key']; ?>"
                    },
                    function(data) {
                        oTable.api().ajax.reload();
                        $("#div_edit_folder").dialog("close");
                    }
                );
            }

            //Close
            $("#div_edit_folder").dialog("close");
        },
        "<?php echo $LANG['save_button']; ?>": function() {
            if ($('#edit_folder_complexite').val() == "") {
                    $("#edit_folder_show_error").html("<?php echo $LANG['error_group_complex']; ?>").show();
                    return;
            }if ($('#edit_folder_title').val() == "") {
                    $("#edit_folder_show_error").html("<?php echo $LANG['error_group_label']; ?>").show();
                    return;
            }if ($('#edit_parent_id').val() == "na") {
                    $("#edit_folder_show_error").html("<?php echo $LANG['error_no_selected_folder']; ?>").show();
                    return;
            }
            $("#edit_folder_wait").show();
            //prepare data
            var data = '{"id":"'+$("#folder_id_to_edit").val()+'", "title":"'+$('#edit_folder_title').val().replace(/"/g,'&quot;') + '", "complexity":"'+$('#edit_folder_complexite').val().replace(/"/g,'&quot;')+'", '+
            '"parent_id":"'+$('#edit_parent_id').val().replace(/"/g,'&quot;')+'", "renewal_period":"'+$('#edit_folder_renewal_period').val().replace(/"/g,'&quot;')+'" , "block_creation":"'+$("#edit_folder_block_creation").val()+'" , "block_modif":"'+$("#edit_folder_block_modif").val()+'"}';

            //send query
            $.post(
                "sources/folders.queries.php",
                {
                    type    : "update_folder",
                    data      : prepareExchangedData(data, "encode", "<?php echo $_SESSION['key']; ?>"),
                    key        : "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    //decrypt data
                    data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");

                    $("#edit_folder_wait").hide();
                    //Check errors
                    if (data.error === "error_title_only_with_numbers") {
                        $("#edit_folder_show_error").html("<?php echo $LANG['error_only_numbers_in_folder_name']; ?>").show();
                    } else if (data.error === "error_group_exist") {
                        $("#edit_folder_show_error").html("<?php echo $LANG['error_group_exist']; ?>").show();
                    } else if (data.error === "error_html_codes") {
                        $("#edit_folder_show_error").html("<?php echo $LANG['error_html_codes']; ?>").show();
                    } else if (data.error === "error_folder_complexity_lower_than_top_folder") {
                        $("#edit_folder_show_error").html(data.error_msg).show();
                    } else {
                        $("#folder_id_to_edit").val("");    //clear id
                        oTable.api().ajax.reload();
                        $("#parent_id, #edit_parent_id")
                            .find('option')
                            .remove()
                            .end()
                            .append(data.droplist);
                        $("#div_edit_folder").dialog("close");
                    }
                }
            );
        },
        "<?php echo $LANG['cancel_button']; ?>": function() {
            //clear id
            $("#folder_id_to_edit").val("");
            $("#edit_folder_show_error").html("");

            //Close
            $("#div_edit_folder").dialog("close");
        }
    }
});

$(document).on('click', '.cb_selected_folder', function() {
    // Show selection of folders
    var selected_cb = $(this),
        id = $(this).data('id');
    if ($(this).prop('checked') === true) {
        $('#folder-' +id)
            .css({'font-weight':'bold'})
            .css({'background-color':'#E9FF00'});
    } else {
        $('#folder-' + id)
            .css({'font-weight':''})
            .css({'background-color':'#FFF'});
    }

    // Now get subfolders
    $.post(
        'sources/folders.queries.php',
        {
            type    : 'select_sub_folders',
            id      : id,
            key     : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            // check/uncheck checkbox
            if (data[0].subfolders !== '') {
                var tmp = data[0].subfolders.split(';');
                for (var i = tmp.length - 1; i >= 0; i--) {
                    if (selected_cb.prop('checked') === true) {
                        $('#checkbox-' + tmp[i]).prop('checked', true).prop('disabled', true);
                        $('#folder-' +tmp[i])
                            .css({'font-weight':'bold'})
                            .css({'background-color':'#E9FF00'});
                    } else {
                        $('#checkbox-' + tmp[i]).prop('checked', false).prop('disabled', false);
                        $('#folder-' + tmp[i])
                            .css({'font-weight':''})
                            .css({'background-color':'#FFF'});
                    }
                }
            }
        },
        'json'
    );
})


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


// manage the click on toggle icons
/*$(document).on({
    click: function (event) {
        if ($(this).attr('tp1') === undefined) {
            // case of folder selection
            var selected_cb = $(this);
            var elem = $(this).attr("id").split("-");
            if ($(this).prop("checked") === true) {
                $("#row_"+elem[1]).css({"font-weight":"bold"});
                $("#title_"+elem[1]).css({"background-color":"#E9FF00"});
            } else {
                $("#row_"+elem[1]).css({"font-weight":""});
                $("#title_"+elem[1]).css({"background-color":"#FFF"});
            }

            // send change to be stored
            $.post(
                "sources/folders.queries.php",
                {
                    type    : "select_sub_folders",
                    id      : elem[1],
                    key     : "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    $("#div_loading").hide();
                    // check/uncheck checkbox
                    if (data[0].subfolders !== "") {
                        var tmp = data[0].subfolders.split(";");
                        for (var i = tmp.length - 1; i >= 0; i--) {
                            if (selected_cb.prop("checked") === true) {
                                $("#cb_selected-" + tmp[i]).prop("checked", true).prop("disabled", true);
                                $("#row_" + tmp[i]).css({"font-weight":"bold"});
                                $("#title_" + tmp[i]).css({"background-color":"#E9FF00"});
                            } else {
                                $("#cb_selected-" + tmp[i]).prop("checked", false).prop("disabled", false);
                                $("#row_" + tmp[i]).css({"font-weight":""});
                                $("#title_" + tmp[i]).css({"background-color":"#FFF"});
                            }
                        }
                    }
                },
                "json"
            );
        } else {
            var tmp = $(this).attr('tp').split('-');    //[0]>ID ; [1]>action  ; [2]>NewValue

            // send change to be stored
            $.post(
                "sources/folders.queries.php",
                {
                    type    : tmp[1],
                    value   : tmp[2],
                    id      : tmp[0],
                    key        : "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    $("#div_loading").hide();
                    // refresh table content
                    oTable.api().ajax.reload();
                }
            );
        }
    }
},
".fa-toggle-off, .fa-toggle-on, .cb_selected_folder"
);*/

//
$( "#click_delete_multiple_folders" ).click(function() {
    var list_i = "";
    $(".cb_selected_folder:checked").each(function() {
        var elem = $(this).attr("id").split("-");
        if (list_i == "") list_i = elem[1];
        else list_i = list_i+';'+elem[1];
    });
    if (list_i != "" && $("#action_on_going").val() == "" && confirm("<?php echo addslashes($LANG['confirm_deletion']); ?>")) {
        $("#div_loading").show();
        $("#action_on_going").val("multiple_folders");
        var data = '{"foldersList":"'+list_i+'"}';
        //send query
        $.post(
            "sources/folders.queries.php",
            {
                type    : "delete_multiple_folders",
                data    : prepareExchangedData(data, "encode", "<?php echo $_SESSION['key']; ?>"),
                key     : "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                oTable.api().ajax.reload();
                $("#action_on_going").val("");
                $("#div_loading").hide();
            },
            "json"
        );
    }
});

$("#click_refresh_folders_list").click(function() {
    oTable.api().ajax.reload();
});

$("#parent_id").change(function() {
    if ($(this).val() === "0") {
        $("#span_new_rep_roles").show();
    } else {
        $("#span_new_rep_roles").hide();
    }
})


/**
 *
 * @access public
 * @return void
 **/
function open_edit_folder_dialog(id)
{
    $("#folder_id_to_edit").val(id);console.log(">"+id);
    $("#div_edit_folder").dialog("open");
}
//]]>
</script>