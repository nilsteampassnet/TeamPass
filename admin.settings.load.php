<?php
/**
 * @file          admin.settings.load.php
 * @author        Nils Laumaillé
 * @version       2.1.19
 * @copyright     (c) 2009-2013 Nils Laumaillé
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
/*
* Add a new field to a category
*/
function fieldAdd(id) {
	$("#post_id").val(id);
	$("#add_new_field").dialog("open");
}

/*
* Add a new category
*/
function categoryAdd() {
    $("#div_loading").show();
	//send query
    $.post(
        "sources/categories.queries.php",
        {
            type    : "addNewCategory",
            title   : sanitizeString($("#new_category_label").val())
        },
        function(data) {
            // build new row
            $("#tbl_categories").append(
                '<tr id="t_cat_'+data[0].id+'"><td colspan="2">'+
                '<input type="text" id="catOrd_'+data[0].id+'" size="1" class="category_order" value="1" />&nbsp;'+
                '<input type="radio" name="sel_item" id="item_'+data[0].id+'_cat" />'+
                '<label for="item_'+data[0].id+'_cat" id="item_'+data[0].id+'">'+
                $("#new_category_label").val()+'</label><a href="#" title="<?php echo $txt['field_add_in_category'];?>" onclick="fieldAdd('+
                data[0].id+')" class="cpm_button tip" style="margin-left:20px;"><img  src="includes/images/zone--plus.png" /></a></td><td></td>');
            // Add new cat
        	$("#moveItemTo").append('<option value="'+data[0].id+'">'+$("#new_category_label").val()+'</option>');
        	// clean
            $("#new_category_label, #new_item_title").val("");
            $("#no_category").hide();
            $("#div_loading").hide();
        },
        "json"
   );
}

/*
* rename an Element
*/
function renameItem() {
    var data = $("input[name=sel_item]:checked").attr("id").split('_');
	$("#post_id").val(data[1]);
	$("#post_type").val("renameItem");
	$("#category_confirm_text").html("<?php echo $txt['confirm_rename'];?>");
	$("#category_confirm").dialog("open");
}

/*
* Delete an Element
*/
function deleteItem() {
    var data = $("input[name=sel_item]:checked").attr("id").split('_');
	$("#post_id").val(data[1]);
	$("#post_type").val("deleteCategory");
	$("#category_confirm_text").html("<?php echo $txt['confirm_deletion'];?>");
	$("#category_confirm").dialog("open");
}

/*
* Move an Element
*/
function moveItem() {
    var data = $("input[name=sel_item]:checked").attr("id").split('_');
	$("#post_id").val(data[1]);
	$("#post_type").val("moveItem");
	$("#category_confirm_text").html("<?php echo $txt['confirm_moveto'];?>");
	$("#category_confirm").dialog("open");
}

/*
* Save the position of the Categories
*/
function storePosition() {
    $("#div_loading").show();
    // prepare listing to save
    var data = "";
    var id;
    var val;
    $('input[class$="category_order"]').each(function(index) {
        id = $(this).attr("id").split("_");
        if ($(this).val() == "") {
            val = "1";
        } else {
            val = $(this).val();
        }
        if (data == "") {
            data = id[1]+":"+val;
        } else {
            data += ";"+id[1]+":"+val;
        }
    });

    //send query
    $.post(
        "sources/categories.queries.php",
        {
            type    : "saveOrder",
            data   : data
        },
        function(data) {
            $("#div_loading").hide();
        },
        "json"
   );
}

/*
* Reload table
*/
function loadFieldsList() {
    $("#div_loading").show();
	//send query
    $.post(
        "sources/categories.queries.php",
        {
            type    : "loadFieldsList",
            title   : prepareExchangedData(sanitizeString($("#new_category_label").val()), "encode")
        },
        function(data) {
            var newList = '<table id="tbl_categories" style="">';
            // parse json table and disaply
            var json = $.parseJSON(data);
            $(json).each(function(i,val){
                if (val[0] == 1) {
                    newList += '<tr id="t_cat_'+val[1]+'"><td colspan="2">'+
                    '<input type="text" id="catOrd_'+val[1]+'" size="1" class="category_order" value="'+val[3]+'" />&nbsp;'+
                    '<input type="radio" name="sel_item" id="item_'+val[1]+'_cat" />'+
                    '<label for="item_'+val[1]+'_cat" id="item_'+val[1]+'">'+val[2]+'</label>'+
                    '<a href="#" title="<?php echo $txt['field_add_in_category'];?>" onclick="fieldAdd('+val[1]+')" class="cpm_button tip" style="margin-left:20px;">'+
                    '<img  src="includes/images/zone--plus.png"  /></a></td><td></td></tr>';
                } else {
                    newList += '<tr id="t_field_'+val[1]+'"><td width="20px"></td>'+
                    '<td><input type="text" id="catOrd_'+val[1]+'" size="1" class="category_order" value="'+val[3]+'" />&nbsp;'+
                    '<input type="radio" name="sel_item" id="item_'+val[1]+'_cat" />'+
                    '<label for="item_'+val[1]+'_cat" id="item_'+val[1]+'">'+val[2]+'</label>'+
                    '</td><td></td></tr>';
                }
            });

            // display
            newList += '</table>';
        	$("#new_item_title").val("");
            $("#categories_list").html(newList);
            $("#div_loading").hide();
        }
   );
}

// Init
$(function() {
	$("input[type=button]").button();

    $('#tbl_categories tr').click(function (event) {
        $("#selected_row").val($(this).attr("id"));
    });

    // display text of selected item
	$(document).on("click","input[name=sel_item]",function(){
        var data = $("input[name=sel_item]:checked").attr("id").split('_');
        $("#new_item_title").val($("#item_"+data[1]).html());
    });

    // confirm dialogbox
    $("#category_confirm").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 120,
        title: "<?php echo $txt['confirm'];?>",
        buttons: {
            "<?php echo $txt['confirm'];?>": function() {
                $("#div_loading").show();
                var $this = $(this);
                // prepare data to send
                var data = "";
                if ($("#post_type").val() == "renameItem") {
                    data = sanitizeString($("#new_item_title").val());
                } else if ($("#post_type").val() == "moveItem") {
                    data = $("#moveItemTo").val();
                }
            	// send query
                $.post(
                    "sources/categories.queries.php",
                    {
                        type    : $("#post_type").val(),
                        id      : $("#post_id").val(),
                        data    : data
                    },
                    function(data) {
                        if ($("#post_type").val() == "deleteCategory") {
                            $("#t_field_"+$("#post_id").val()).hide();
                        } else if ($("#post_type").val() == "renameItem") {
                            $("#item_"+$("#post_id").val()).html($("#new_item_title").val());
                        } else if ($("#post_type").val() == "moveItem") {
                            // reload table
                            loadFieldsList();
                        }
                        $("#new_category_label, #new_item_title").val("");
                        $("#div_loading").hide();
                        $this.dialog("close");
                    },
                    "json"
               );
            },
            "<?php echo $txt['cancel_button'];?>": function() {
                $("#div_loading").hide();
                $(this).dialog("close");
            }
        }
    });

    $("#add_new_field").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 500,
        height: 150,
        title: "<?php echo $txt['confirm_creation'];?>",
        buttons: {
            "<?php echo $txt['confirm'];?>": function() {
                if ($("#new_field_title").val() != "" && $("#post_id").val() != "") {
                    $("#div_loading").show();
                    var $this = $(this);
                	//send query
                    $.post(
                        "sources/categories.queries.php",
                        {
                            type    : "addNewField",
                            title   : sanitizeString($("#new_field_title").val()),
                            id      : $("#post_id").val()
                        },
                        function(data) {
                        	$("#new_field_title").val("");
                        	// reload table
                            loadFieldsList();
                            $this.dialog("close");
                        },
                        "json"
                    );
                }
            },
            "<?php echo $txt['cancel_button'];?>": function() {
                $("#div_loading").hide();
                $(this).dialog("close");
            }
        }
    });
});
</script>