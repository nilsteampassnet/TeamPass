<?php

/**
 * @file           views_database.load.php
 * @author        Nils Laumaillé
 * @version         2.1.13
 * @copyright     (c) 2009-2012 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link            http://www.teampass.net
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
function killEntry(item_id)
{
	$.post(
            "sources/items.queries.php",
            {
                type : "free_item_for_edition",
                id : item_id
            },
            function(data) {
            	oTable.fnDraw(false);
            }
       );
}

$(function() {
    $("#radio_database").buttonset();

    //Prepare DB table
    oTable = $("#t_items_edited").dataTable({
        "aaSorting": [[ 1, "asc" ]],
        "sPaginationType": "full_numbers",
        "bProcessing": true,
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
});
</script>