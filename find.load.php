<?php
/**
 * @file          find.load.php
 * @author        Nils Laumaillé
 * @version       2.1.25
 * @copyright     (c) 2009-2015 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}
?>

<script type="text/javascript">
function aes_decrypt(text)
{
    return Aes.Ctr.decrypt(text, "<?php echo $_SESSION['key'];?>", 256);
}

/*
* Copying an item from find page
*/
//###########
//## FUNCTION : prepare copy item dialogbox
//###########
function copy_item(item_id)
{
    $('#id_selected_item').val(item_id);
    $('#div_copy_item_to_folder').dialog('open');
}

$("#div_copy_item_to_folder").dialog({
      bgiframe: true,
      modal: true,
      autoOpen: false,
      width: 400,
      height: 200,
      title: "<?php echo $LANG['item_menu_copy_elem'];?>",
      buttons: {
          "<?php echo $LANG['ok'];?>": function() {
              //Send query
                $.post(
                    "sources/items.queries.php",
                    {
                        type    : "copy_item",
                        item_id : $('#id_selected_item').val(),
                        folder_id : $('#copy_in_folder').val(),
                        key        : "<?php echo $_SESSION['key'];?>"
                    },
                    function(data) {
                        //check if format error
                        if (data[0].error == "no_item") {
                            $("#copy_item_to_folder_show_error").html(data[1].error_text).show();
                        } else if (data[0].error == "not_allowed") {
                            $("#copy_item_to_folder_show_error").html(data[1].error_text).show();
                        }
                        //if OK
                        if (data[0].status == "ok") {
                            $("#div_dialog_message_text").html("<?php echo $LANG['alert_message_done'];?>");
                            $("#div_dialog_message").dialog('open');
                            $("#div_copy_item_to_folder").dialog('close');
                        }
                    },
                    "json"
               );
          },
          "<?php echo $LANG['cancel_button'];?>": function() {
              $("#copy_item_to_folder_show_error").html("").hide();
              $(this).dialog('close');
          }
      }
  });

/*
* Open a dialogbox with item data
*/
function see_item(item_id, personalItem)
{
    $('#id_selected_item').val(item_id);
    $("#personalItem").val(personalItem);
    $('#div_item_data').dialog('open');
}

$("#div_item_data").dialog({
      bgiframe: true,
      modal: true,
      autoOpen: false,
      width: 450,
      height: 220,
      title: "<?php echo $LANG['see_item_title'];?>",
      open:
        function(event, ui) {
              $("#div_item_data_show_error").html("<?php echo $LANG['admin_info_loading'];?>").show();
            $.post(
                "sources/items.queries.php",
                {
                    type    : "show_details_item",
                    id         : $('#id_selected_item').val(),
                    salt_key_required : $('#personalItem').val(),
                    salt_key_set : $('#personal_sk_set').val(),
                	page 	: "find",
                    key        : "<?php echo $_SESSION['key'];?>"
                },
                function(data) {
                    //decrypt data
                    data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key'];?>");
                    var return_html = "";
                    if (data.show_detail_option != "0" || data.show_details == 0) {
                        //item expired
                        return_html = "<?php echo $LANG['not_allowed_to_see_pw_is_expired'];?>";
                    } else if (data.show_details == "0") {
                        //Admin cannot see Item
                        return_html = "<?php echo $LANG['not_allowed_to_see_pw'];?>";
                    } else {
                        return_html = "<table>"+
                            "<tr><td valign='top' class='td_title'><span class='ui-icon ui-icon-carat-1-e' style='float: left; margin-right: .3em;'>&nbsp;</span><?php echo $LANG['label'];?> :</td><td style='font-style:italic;display:inline;'>"+data.label+"</td></tr>"+
                            "<tr><td valign='top' class='td_title'><span class='ui-icon ui-icon-carat-1-e' style='float: left; margin-right: .3em;'>&nbsp;</span><?php echo $LANG['description'];?> :</td><td style='font-style:italic;display:inline;'>"+data.description+"</td></tr>"+
                            "<tr><td valign='top' class='td_title'><span class='ui-icon ui-icon-carat-1-e' style='float: left; margin-right: .3em;'>&nbsp;</span><?php echo $LANG['pw'];?> :</td><td style='font-style:italic;display:inline;'>"+unsanitizeString(data.pw)+"</td></tr>"+
                            "<tr><td valign='top' class='td_title'><span class='ui-icon ui-icon-carat-1-e' style='float: left; margin-right: .3em;'>&nbsp;</span><?php echo $LANG['index_login'];?> :</td><td style='font-style:italic;display:inline;'>"+data.login+"</td></tr>"+
                            "<tr><td valign='top' class='td_title'><span class='ui-icon ui-icon-carat-1-e' style='float: left; margin-right: .3em;'>&nbsp;</span><?php echo $LANG['url'];?> :</td><td style='font-style:italic;display:inline;'>"+data.url+"</td></tr>"+
                        "</table>";
                    }
                    $("#div_item_data_show_error").html("").hide();
                    $("#div_item_data_text").html(return_html);
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
          "<?php echo $LANG['ok'];?>": function() {
              $(this).dialog('close');
          }
      }
  });

$(function() {
    //Launch the datatables pluggin
    oTable = $("#t_items").dataTable({
        "aaSorting": [[ 1, "asc" ]],
        "sPaginationType": "full_numbers",
        "bProcessing": true,
        "bServerSide": true,
        "sAjaxSource": "sources/find.queries.php",
        "bJQueryUI": true,
        "oLanguage": {
            "sUrl": "includes/language/datatables.<?php echo $_SESSION['user_language'];?>.txt"
        },
        "fnInitComplete": function() {
            $("#find_page input").focus();
        },
        "columns": [
            { "width": "10%" },
            null,
            null,
            null,
            null,
            null
        ]
    });
});
</script>
