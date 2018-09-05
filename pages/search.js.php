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
$var['hidden_asterisk'] = '<i class="fa fa-asterisk mr-2"></i><i class="fa fa-asterisk mr-2"></i><i class="fa fa-asterisk mr-2"></i><i class="fa fa-asterisk mr-2"></i><i class="fa fa-asterisk"></i>';

?>


<script type="text/javascript">
    //Launch the datatables pluggin
    var oTable = $("#search-results-items").DataTable({
        "paging": true,
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
        "pagingType": "full_numbers",
        "searching": true,
        "info": true,
        "order": [[1, "asc"]],
        "processing": true,
        "serverSide": true,
        "ajax": {
            url: "<?php echo $SETTINGS['cpassman_url']; ?>/sources/find.queries.php",
            type: 'GET'
        },
        "language": {
            "url": "<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user_language']; ?>.txt"
        },
        "columns": [
            {"width": "10%", class: "details-control", defaultContent: ""},
            {"width": "15%"},
            {"width": "10%"},
            {"width": "25%"},
            {"width": "10%"},
            {"width": "15%"},
            {"width": "15%"}
        ],
        "responsive": true,
        "select": false,
        "stateSave": true,
    });

    var detailRows = [];

    $("#search-results-items tbody").on( 'click', '.item-detail', function () {
        var tr = $(this).closest('tr');
        var row = oTable.row(tr);
        var idx = $.inArray(tr.attr('id'), detailRows);
        var itemGlobal = row.data();
        var item = $(this);

        if ( row.child.isShown() ) {
            row.child.hide();

            // Change eye icon
            $(this)
                .removeClass('fa-eye-slash text-warning')
                .addClass('fa-eye');
 
            // Remove from the 'open' array
            detailRows.splice( idx, 1 );
        }
        else {
            // Change eye icon
            $(this)
                .removeClass('fa-eye')
                .addClass('fa-eye-slash text-warning');

            // Add loader
            $(this)
                .after('<i class="fa fa-refresh fa-spin fa-fw" id="search-loader"></i>');

            // Get content of item
            row.child(showItemInfo(itemGlobal, tr, item), 'new-row').show();
 
            // Add to the 'open' array
            if ( idx === -1 ) {
                detailRows.push( tr.attr('id') );
            }
        }
    } );

    function showItemInfo (d, tr, item) {
        // prepare data
        var data = {
            'id' : $(item).data('id'),
            'folder_id' : $(item).data('tree-id'),
            'salt_key_required' : $(item).data('perso'),
            'salt_key_set' : $('#personal_saltkey_set').val(),
            'expired_item' : $(item).data('expired'),
            'restricted' : $(item).data('restricted-to'),
            'page' : 'find'
        };
        console.log(data)

        // Launch query
        $.post(
            'sources/items.queries.php',
            {
                type :  'show_details_item',
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
                        '<div id="pwd-show_'+data.id+'" class="unhide_masked_data" style="height: 20px;"><?php echo $var['hidden_asterisk']; ?></div>'+
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

    var mouseStillDown = false;
    var showPwdContinuous = function(elem_id){
        var itemId = elem_id.split('_')[1];
        if(mouseStillDown === true) {
            // Prepare data to show
            // Is data crypted?
            var data = unCryptData($('#pwd-hidden_' + itemId).val());
            
            if (data !== false) {
                $('#pwd-hidden_' + itemId).val(
                    data.password
                );
            }

            $('#pwd-show_' + itemId)
                .html(
                    '<span style="cursor:none;">' +
                    $('#pwd-hidden_' + itemId).val() +
                    '</span>'
                );
            
            setTimeout('showPwdContinuous("pwd-show_' + itemId + '")', 50);
            // log password is shown
            if ($("#pwd-is-shown_" + itemId).val() === "0") {
                itemLog(
                    'at_password_shown',
                    itemId,
                    $('#pwd-label_' + itemId).text()
                );
                $("#pwd-is-shown_" + itemId).val('1');
            }
        } else {
            $('#pwd-show_' + itemId).html('<?php echo $var['hidden_asterisk']; ?>');
        }
    };

    
    var selectedItems = '',
        selectedAction = '',
        listOfFolders = '';
    $("#search-results-items tbody").on( 'change', '.mass_op_cb', function () {
        // Check if at least one CB is checked
        if ($("#search-results-items input[type=checkbox]:checked").length > 0) {
            // Show selection menu
            if ($('#search-select').hasClass('menuset') === false) {
                $('#search-select')
                    .addClass('menuset')
                    .html(
                        '<?php echo langHdl('actions'); ?>' +
                        '<i class="fa fa-share ml-2 pointer infotip mass-operation" title="<?php echo langHdl('move_items'); ?>" data-action="move"></i>' +
                        '<i class="fa fa-trash ml-2 pointer infotip mass-operation" title="<?php echo langHdl('delete_items'); ?>" data-action="delete"></i>'
                    );

                // Prepare tooltips
                $('.infotip').tooltip();

                // Now move or trash
                $('.mass-operation').click(function() {
                    $('#dialog-mass-operation').removeClass('hidden');

                    // Define
                    var item_id,
                        sel_items_txt = '<ul>',
                        testToShow = '';
                    
                    // Init
                    selectedAction = $(this).data('action');
                    selectedItems = '';

                    // Selected items
                    $('.mass_op_cb:checkbox:checked').each(function () {
                        item_id = $(this).data('id') ;
                        selectedItems += item_id + ';';
                        sel_items_txt += '<li>' + $('#item_label-'+item_id).text() + '</li>';
                    });
                    sel_items_txt += '</ul>';

                    if (selectedAction === 'move') {
                        // get list of folders
                        var htmlFolders = '';
                        $.post(
                            'sources/folders.queries.php',
                            {
                                type    : 'get_list_of_folders',
                                key     : '<?php echo $_SESSION['key']; ?>'
                            },
                            function(data) {
                                $('#div_loading').hide();
                                // check/uncheck checkbox
                                if (data[0].list_folders !== '') {
                                    var tmp = data[0].list_folders.split(';');
                                    for (var i = tmp.length - 1; i >= 0; i--) {
                                        listOfFolders += tmp[i];
                                    }
                                }

                                // destination folder
                                htmlFolders += '<div><?php echo langHdl('import_keepass_to_folder'); ?>:&nbsp;&nbsp;' +
                                '<select class="form-control form-item-control select2" style="width:100%;" id="mass_move_destination_folder_id">' +
                                data[0].list_folders + '</select>'+
                                '</div>';

                                //display to user
                                $('#dialog-mass-operation-html').html(
                                    '<?php echo langHdl('you_decided_to_move_items'); ?>: ' +
                                    '<div><ul>' + sel_items_txt + '</ul></div>' + htmlFolders +
                                    '<div class="mt-3 alert alert-info"><i class="fa fa-warning fa-lg mr-2"></i><?php echo langHdl('confirm_item_move'); ?></div>'
                                );
                            },
                            'json'
                        );
                        
                    } else if (selectedAction === 'delete') {
                        $('#dialog-mass-operation-html').html(
                            '<?php echo langHdl('you_decided_to_delete_items'); ?>: ' +
                            '<div><ul>' + sel_items_txt + '</ul></div>' +
                            '<div class="mt-3 alert alert-danger"><i class="fa fa-warning fa-lg mr-2"></i><?php echo langHdl('confirm_deletion'); ?></div>'
                        );
                    }
                });
            }
        } else {
            $('#dialog-mass-operation').addClass('hidden');

            $('#search-select')
                .removeClass('menuset')
                .html('&nbsp;');
                
            $('#dialog-mass-operation-html').html('');
        }
    });


    // Perform action expected by user
    $('#dialog-mass-operation-button').click(function() {
        console.log(selectedItems);

        if (selectedItems === "") {
            alertify
                .warning('<i class="fa fa-ban mr-2"></i><?php echo langHdl('none_selected_text'); ?>', 3000)
                .dismissOthers();
            return false;
        }

        // Show to user
        alertify
            .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();
        
        if (selectedAction === 'delete') {
            // Delete selected items
            $.post(
                'sources/items.queries.php',
                {
                    type        : 'mass_delete_items',
                    item_ids    : selectedItems,
                    key         : '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    //check if format error
                    if (data[0].error !== '') {
                        alertify
                            .error('<i class="fa fa-warning fa-lg mr-2"></i>' + data[0].error, 3)
                            .dismissOthers();
                        return false;
                    } else if (data[0].status === 'ok') {
                        //reload search
                        oTable.ajax.reload();

                        alertify
                            .success('<?php echo langHdl('success'); ?>', 1)
                            .dismissOthers();

                        // Finalize template
                        $('#dialog-mass-operation').addClass('hidden');
                        $('#search-select')
                            .removeClass('menuset')
                            .html('&nbsp;');                            
                        $('#dialog-mass-operation-html').html('');
                    }
                },
                'json'
            );
        } else if (selectedAction === 'move') {
            $.post(
                'sources/items.queries.php',
                {
                    type        : 'mass_move_items',
                    item_ids    : selectedItems,
                    folder_id   : $('#mass_move_destination_folder_id').val(),
                    key         : '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    //check if format error
                    if (data[0].error !== '') {
                        alertify
                            .error('<i class="fa fa-warning fa-lg mr-2"></i>' + data[1].error_text, 3)
                            .dismissOthers();
                        return false;
                    } else if (data[0].status === 'ok') {
                        //reload search
                        oTable.ajax.reload();

                        alertify
                            .success('<?php echo langHdl('success'); ?>', 1)
                            .dismissOthers();

                        // Finalize template
                        $('#dialog-mass-operation').addClass('hidden');
                        $('#search-select')
                            .removeClass('menuset')
                            .html('&nbsp;');                            
                        $('#dialog-mass-operation-html').html('');
                    }
                },
                'json'
            );
        }
    });
    
    
/*
    $("#div_mass_op").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 500,
        height: 400,
        title: "<?php echo langHdl('mass_operation'); ?>",
        open: function() {
            var html = sel_items = sel_items_txt = item_id = '';

            $("#div_mass_op_msg").html("").hide();

            // selected items
            $(".mass_op_cb:checkbox:checked").each(function () {
                item_id = $(this).attr('id').split('-')[1] ;
                sel_items += item_id + ";";
                if (sel_items_txt === "") {
                    sel_items_txt = '<li>' + $("#item_label-"+item_id).text() + '</li>';
                } else {
                    sel_items_txt += "<li>" + $("#item_label-"+item_id).text() + '</li>';
                }
            });

            // prepare display
            if ($("#div_mass_op").data('action') === "move") {
                html = '<?php echo langHdl('you_decided_to_move_items'); ?>: ' +
                '<div><ul>' + sel_items_txt + '</ul></div>';
                var folder_options = '';

                // get list of folders
                $.post(
                    "sources/folders.queries.php",
                    {
                        type    : "get_list_of_folders",
                        key     : "<?php echo $_SESSION['key']; ?>"
                    },
                    function(data) {
                        $("#div_loading").hide();
                        // check/uncheck checkbox
                        if (data[0].list_folders !== "") {
                            var tmp = data[0].list_folders.split(";");
                            for (var i = tmp.length - 1; i >= 0; i--) {
                                folder_options += tmp[i];
                            }
                        }

                        // destination folder
                         html += '<div style=""><?php echo langHdl('import_keepass_to_folder'); ?>:&nbsp;&nbsp;' +
                         '<select id="mass_move_destination_folder_id">' + data[0].list_folders + '</select>' +
                         '</div>';

                        //display to user
                        $("#div_mass_html").html(html);
                    },
                    "json"
                );
            } else if ($("#div_mass_op").data('action') === "delete") {
                html = '<?php echo langHdl('you_decided_to_delete_items'); ?>: ' +
                '<div><ul>' + sel_items_txt + '</ul></div>' +
                '<div style="padding:10px;" class="ui-corner-all ui-state-error"><span class="fa fa-warning fa-lg"></span>&nbsp;<?php echo langHdl('confirm_deletion'); ?></div>';

                $("#div_mass_html").html(html);
            }

        },
        buttons: {
            "<?php echo langHdl('ok'); ?>": function() {
                $("#div_mass_op_msg")
                    .addClass("ui-state-highlight")
                    .html('<span class="fa fa-cog fa-spin fa-lg"></span>&nbsp;<?php echo langHdl('please_wait'); ?>')
                    .show();

                var sel_items = '';

                // selected items
                $(".mass_op_cb:checkbox:checked").each(function () {
                    sel_items += $(this).attr('id').split('-')[1] + ";";
                });

                if (sel_items === "") {
                    $("#div_mass_op_msg")
                        .addClass("ui-state-error")
                        .html('<span class="fa fa-warning fa-lg"></span>&nbsp;<?php echo langHdl('must_select_items'); ?>')
                        .show().delay(2000).fadeOut(1000);
                    return false;
                }

                if ($("#div_mass_op").data('action') === "move") {
                // MASS MOVE

                    //Send query
                    $.post(
                        "sources/items.queries.php",
                        {
                            type        : "mass_move_items",
                            item_ids    : sel_items,
                            folder_id   : $("#mass_move_destination_folder_id").val(),
                            key         : "<?php echo $_SESSION['key']; ?>"
                        },
                        function(data) {
                            //check if format error
                            if (data[0].error !== "") {
                                $("#div_mass_op_msg").html(data[1].error_text).show();
                            }
                            //if OK
                            if (data[0].status == "ok") {
                                //reload search
                                oTable.api().ajax.reload();

                                $("#main_info_box_text").html("<?php echo langHdl('alert_message_done'); ?>");
                                    $("#main_info_box").show().position({
                                        my: "center",
                                        at: "center top+75",
                                        of: "#top"
                                    });
                                    setTimeout(function(){$("#main_info_box").effect( "fade", "slow" );}, 1000);

                                // show finished
                                $("#div_mass_op").dialog("close");
                            }
                        },
                        "json"
                    );
                } else if ($("#div_mass_op").data('action') === "delete") {
                // MASS DELETE

                    //Send query
                    $.post(
                        "sources/items.queries.php",
                        {
                            type        : "mass_delete_items",
                            item_ids    : sel_items,
                            key         : "<?php echo $_SESSION['key']; ?>"
                        },
                        function(data) {
                            //check if format error
                            if (data[0].error !== "") {
                                $("#div_mass_op_msg").html(data[0].error).show();
                            }
                            //if OK
                            if (data[0].status == "ok") {
                                //reload search
                                oTable.api().ajax.reload();

                                $("#main_info_box_text").html("<?php echo langHdl('alert_message_done'); ?>");
                                    $("#main_info_box").show().position({
                                        my: "center",
                                        at: "center top+75",
                                        of: "#top"
                                    });
                                    setTimeout(function(){$("#main_info_box").effect( "fade", "slow" );}, 1000);

                                // show finished
                                $("#div_mass_op").dialog("close");
                            }
                        },
                        "json"
                    );
                }
            },
            "<?php echo langHdl('cancel_button'); ?>": function() {
                $(this).dialog('close');
            }
        }
    });

    $("#div_item_data").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 450,
        height: 220,
        title: "<?php echo langHdl('see_item_title'); ?>",
        open:
          function(event, ui) {
              $("#div_item_data_show_error").html("<?php echo langHdl('admin_info_loading'); ?>").show();

              // prepare data
              var data = {
                  "id" : $('#id_selected_item').val(),
                  "folder_id" : $('#folder_id_of_item').val(),
                  "salt_key_required" : $('#personalItem').val(),
                  "salt_key_set" : $('#personal_sk_set').val(),
                  "expired_item" : $("#expired_item").val(),
                  "restricted" : $("#restricted_item").val(),
                  "page" : "find"
              };

              $.post(
                  "sources/items.queries.php",
                  {
                      type :  "show_details_item",
                      data :  prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                      key  :  "<?php echo $_SESSION['key']; ?>"
                  },
                  function(data) {
                      //decrypt data
                      data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                      var return_html = "";
                      if (data.show_detail_option != "0" || data.show_details == 0) {
                          //item expired
                          return_html = "<?php echo langHdl('not_allowed_to_see_pw_is_expired'); ?>";
                      } else if (data.show_details == "0") {
                          //Admin cannot see Item
                          return_html = "<?php echo langHdl('not_allowed_to_see_pw'); ?>";
                      } else {
                          return_html = "<table>"+
                              "<tr><td valign='top' class='td_title'><span class='fa fa-caret-right'></span>&nbsp;<?php echo langHdl('label'); ?> :</td><td style='font-style:italic;display:inline;' id='item_label'>"+data.label+"</td></tr>"+
                              "<tr><td valign='top' class='td_title'><span class='fa fa-caret-right'></span>&nbsp;<?php echo langHdl('description'); ?> :</td><td style='font-style:italic;display:inline;'>"+data.description+"</td></tr>"+
                              "<tr><td valign='top' class='td_title'><span class='fa fa-caret-right'></span>&nbsp;<?php echo langHdl('pw'); ?> :</td>"+
                              "<td style='font-style:italic;display:inline;'>"+
                              '<div id="id_pw" class="unhide_masked_data" style="float:left; cursor:pointer; width:300px;"></div>'+
                              '<div id="hid_pw" class="hidden"></div>'+
                              '<input type="hidden" id="pw_shown" value="0" />'+
                              "</td></tr>"+
                              "<tr><td valign='top' class='td_title'><span class='fa fa-caret-right'></span>&nbsp;<?php echo langHdl('index_login'); ?> :</td><td style='font-style:italic;display:inline;'>"+data.login+"</td></tr>"+
                              "<tr><td valign='top' class='td_title'><span class='fa fa-caret-right'></span>&nbsp;<?php echo langHdl('url'); ?> :</td><td style='font-style:italic;display:inline;'>"+data.url+"</td></tr>"+
                          "</table>";
                      }
                      $("#div_item_data_show_error").html("").hide();
                      $("#div_item_data_text").html(return_html);
                      $('#id_pw').html('<?php echo $var['hidden_asterisk']; ?>');
                      $('#hid_pw').html(unsanitizeString(data.pw));
                  }
            );
          }
        ,
        close:
          function(event, ui) {
              $("#div_item_data_text").html("");
          }
          ,
        buttons: {
            "<?php echo langHdl('ok'); ?>": function() {
                $(this).dialog('close');
            }
        }
    });
*/
/*
function copy_item(item_id)
{
    $('#id_selected_item').val(item_id);

    $.post(
        "sources/items.queries.php",
        {
            type    : "refresh_visible_folders",
            key        : "<?php echo $_SESSION['key']; ?>"
        },
        function(data) {
            data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
            $("#copy_in_folder").find('option').remove().end().append(data.selectFullVisibleFoldersOptions);
            $('#div_copy_item_to_folder').dialog('open');
        }
    );

}

$("#div_copy_item_to_folder").dialog({
      bgiframe: true,
      modal: true,
      autoOpen: false,
      width: 400,
      height: 200,
      title: "<?php echo langHdl('item_menu_copy_elem'); ?>",
      buttons: {
          "<?php echo langHdl('ok'); ?>": function() {
              //Send query
                $.post(
                    "sources/items.queries.php",
                    {
                        type    : "copy_item",
                        item_id : $('#id_selected_item').val(),
                        folder_id : $('#copy_in_folder').val(),
                        key        : "<?php echo $_SESSION['key']; ?>"
                    },
                    function(data) {
                        //check if format error
                        if (data[0].error !== "") {
                            $("#copy_item_to_folder_show_error").html(data[1].error_text).show();
                        }
                        //if OK
                        if (data[0].status == "ok") {
                            $("#div_dialog_message_text").html("<?php echo langHdl('alert_message_done'); ?>");
                            $("#div_dialog_message").dialog('open');
                            $("#div_copy_item_to_folder").dialog('close');
                        }
                    },
                    "json"
               );
          },
          "<?php echo langHdl('cancel_button'); ?>": function() {
              $("#copy_item_to_folder_show_error").html("").hide();
              $(this).dialog('close');
          }
      }
  });
*/


function unCryptData(data)
{
    if (data.substr(0, 7) === 'crypted') {
        return prepareExchangedData(
            data.substr(7),
            'decode',
            '<?php echo $_SESSION['key']; ?>'
        )
    }
    return false;
}
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

</script>
