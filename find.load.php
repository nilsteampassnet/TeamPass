<?php
/**
 * @file 		find.load.php
 * @author		Nils Laumaillé
 * @version 	2.1
 * @copyright 	(c) 2009-2011 Nils Laumaillé
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

if (!isset($_SESSION['CPM'] ) || $_SESSION['CPM'] != 1)
	die('Hacking attempt...');

?>

<script type="text/javascript">
/*
* Copying an item from find page
*/
//###########
//## FUNCTION : prepare copy item dialogbox
//###########
function copy_item(item_id) {
	$('#id_selected_item').val(item_id);
	$('#div_copy_item_to_folder').dialog('open');
}

$("#div_copy_item_to_folder").dialog({
      bgiframe: true,
      modal: true,
      autoOpen: false,
      width: 400,
      height: 200,
      title: "<?php echo $txt['item_menu_copy_elem'];?>",
      buttons: {
          "<?php echo $txt['ok'];?>": function() {
              //Send query
				$.post(
					"sources/items.queries.php",
					{
						type    : "copy_item",
						item_id : $('#id_selected_item').val(),
						folder_id : $('#copy_in_folder').val(),
						key		: "<?php echo $_SESSION['key'];?>"
					},
					function(data){
						//check if format error
			            if (data[0].error == "no_item") {
			                $("#copy_item_to_folder_show_error").html(data[1].error_text).show();
			            }
			            else if (data[0].error == "not_allowed") {
			                $("#copy_item_to_folder_show_error").html(data[1].error_text).show();
			            }
						//if OK
						if (data[0].status == "ok") {
							$("#div_dialog_message_text").html("<?php echo $txt['alert_message_done'];?>");
							$("#div_dialog_message").dialog('open');
							$("#div_copy_item_to_folder").dialog('close');
						}
					},
					"json"
				);
          },
          "<?php echo $txt['cancel_button'];?>": function() {
          	$("#copy_item_to_folder_show_error").html("").hide();
              $(this).dialog('close');
          }
      }
  });

/*
* Open a dialogbox with item data
*/
function see_item(item_id) {
	$('#id_selected_item').val(item_id);
	$('#div_copy_item_to_folder').dialog('open');
}

$("#div_item_data").dialog({
      bgiframe: true,
      modal: true,
      autoOpen: false,
      width: 400,
      height: 200,
      title: "<?php echo $txt['see_item_title'];?>",
	  open:
		//Send query
		function(event, ui) {
			$.post(
				"sources/items.queries.php",
				{
					type    : "show_details_item",
					item_id : $('#id_selected_item').val(),
					key		: "<?php echo $_SESSION['key'];?>"
				},
				function(data){
					alert(data[0]);
					if (data[0].status == "ok") {
						$("#div_dialog_message_text").html("<?php echo $txt['alert_message_done'];?>");
						$("#div_dialog_message").dialog('open');
						$("#div_item_data").dialog('close');
					}
				},
				"json"
			);
		}
	  ,
      buttons: {
          "<?php echo $txt['cancel_button'];?>": function() {
          	$("#copy_item_to_folder_show_error").html("").hide();
              $(this).dialog('close');
          }
      }
  });
</script>