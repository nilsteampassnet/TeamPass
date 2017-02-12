<?php

/**
 * @file          views_database.load.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
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
//<![CDATA[
var oTable1;
var oTable2;

/**
 * Kill an entry
 */
function killEntry(type, id)
{
    if (type == "items_edited") {
        $.post(
            "sources/items.queries.php",
            {
                type    : "free_item_for_edition",
                id      : id,
                key     : "<?php echo $_SESSION["key"];?>"
            },
            function(data) {
                oTable1.fnDraw(false);
            }
        );
    } else if (type == "disconnect_user") {
        $.post(
                "sources/users.queries.php",
                {
                    type    : "disconnect_user",
                    user_id : id,
                    key     : "<?php echo $_SESSION["key"];?>"
                },
                function(data) {
                    oTable2.fnDraw(false);
                }
            );
    }
}

/**
 * Manage display of divs
 */
function manage_div_display(show_id){
    var all_divs = new Array();
    all_divs[0] = "tab5_1";
    all_divs[1] = "tab5_2";
    for (i=0;i<all_divs.length;i++) {
        if (all_divs[i] == show_id) {
            $("#"+all_divs[i]).show();
        } else {
            $("#"+all_divs[i]).hide();
        }
    }
}

/**
 * Loads the associated data table
 */
function loadTable(table_id)
{
    if (table_id == "t_items_edited") {
        oTable1 = $("#t_items_edited").dataTable({
            "aaSorting": [[ 1, "asc" ]],
            "sPaginationType": "full_numbers",
            "bProcessing": true,
            "bDestroy": true,
            "bServerSide": true,
            "sAjaxSource": "sources/datatable/datatable.item_edition.php",
            "bJQueryUI": true,
            "oLanguage": {
                "sUrl": "includes/language/datatables.<?php echo $_SESSION['user_language'];?>.txt"
            },
            "fnInitComplete": function() {
                $("#items_edited_page input").focus();
            }
        });
    } else if (table_id == "t_users_logged") {
        oTable2 = $("#t_users_logged").dataTable({
            "aaSorting": [[ 1, "asc" ]],
            "sPaginationType": "full_numbers",
            "bProcessing": true,
            "bDestroy": true,
            "bServerSide": true,
            "sAjaxSource": "sources/datatable/datatable.users_logged.php",
            "bJQueryUI": true,
            "oLanguage": {
                "sUrl": "includes/language/datatables.<?php echo $_SESSION['user_language'];?>.txt"
            },
            "fnInitComplete": function() {
                $("#t_users_logged_page input").focus();
            }
        });
    }
}

$(function() {
    $("#radio_database").buttonset();
    $(".button").button();

    $("#div_dialog_message").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 150,
        title: "<?php echo $LANG["admin_action"];?>",
        buttons: {
            "<?php echo $LANG["ok"];?>": function() {
                $.post(
                    "sources/users.queries.php",
                    {
                        type   : "disconnect_all_users",
                        key    : "<?php echo $_SESSION["key"];?>"
                    },
                    function(data) {
                        oTable2.fnDraw(false);
                        $(this).dialog("close");
                    }
                );
            },
            "<?php echo $LANG["cancel_button"];?>": function() {
                $(this).dialog("close");
            }
        }
    });

    $("#but_disconnect_all_users").click(function() {
        $("#div_dialog_message_text").html("<?php echo $LANG["disconnect_all_users_sure"];?>");
        $("#div_dialog_message").dialog("open");
    });
});
//]]>
</script>