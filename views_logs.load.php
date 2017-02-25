<?php

/**
 * @file          views_logs.load.php
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
var oTable0;
var oTable1;
var oTable2;
var oTable3;
var oTable4;
var oTable5;
var oTable6;

/**
 * Manage display of divs
 */
function manage_div_display(show_id){
    var all_divs = new Array();
    all_divs[0] = "tab6_0";
    all_divs[1] = "tab6_1";
    all_divs[2] = "tab6_2";
    all_divs[3] = "tab6_3";
    all_divs[4] = "tab6_4";
    all_divs[5] = "tab6_5";
    all_divs[6] = "tab6_6";
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
    if (table_id == "t_connections") {
        $("#type_log_displayed").val("connections_logs");
        oTable0 = $("#t_connections").dataTable({
            "aaSorting": [[ 1, "asc" ]],
            "sPaginationType": "full_numbers",
            "bProcessing": true,
            "bDestroy": true,
            "bServerSide": true,
            "sAjaxSource": "sources/datatable/datatable.logs.php?action=connections",
            "bJQueryUI": true,
            "oLanguage": {
                "sUrl": "includes/language/datatables.<?php echo $_SESSION['user_language'];?>.txt"
            },
            "fnInitComplete": function() {
                $("#t_connections_page input").focus();
            }
        });
    } else if (table_id == "t_errors") {
        $("#type_log_displayed").val("errors_logs");
        oTable1 = $("#t_errors").dataTable({
            "aaSorting": [[ 1, "asc" ]],
            "sPaginationType": "full_numbers",
            "bProcessing": true,
            "bDestroy": true,
            "bServerSide": true,
            "sAjaxSource": "sources/datatable/datatable.logs.php?action=errors",
            "bJQueryUI": true,
            "oLanguage": {
                "sUrl": "includes/language/datatables.<?php echo $_SESSION['user_language'];?>.txt"
            },
            "fnInitComplete": function() {
                $("#t_errors_page input").focus();
            }
        });
    } else if (table_id == "t_access") {
        $("#type_log_displayed").val("access_logs");
        oTable2 = $("#t_access").dataTable({
            "aaSorting": [[ 1, "asc" ]],
            "sPaginationType": "full_numbers",
            "bProcessing": true,
            "bDestroy": true,
            "bServerSide": true,
            "sAjaxSource": "sources/datatable/datatable.logs.php?action=access",
            "bJQueryUI": true,
            "oLanguage": {
                "sUrl": "includes/language/datatables.<?php echo $_SESSION['user_language'];?>.txt"
            },
            "fnInitComplete": function() {
                $("#t_access_page input").focus();
            }
        });
    } else if (table_id == "t_copy") {
        $("#type_log_displayed").val("copy_logs");
        oTable3 = $("#t_copy").dataTable({
            "aaSorting": [[ 1, "asc" ]],
            "sPaginationType": "full_numbers",
            "bProcessing": true,
            "bDestroy": true,
            "bServerSide": true,
            "sAjaxSource": "sources/datatable/datatable.logs.php?action=copy",
            "bJQueryUI": true,
            "oLanguage": {
                "sUrl": "includes/language/datatables.<?php echo $_SESSION['user_language'];?>.txt"
            },
            "fnInitComplete": function() {
                $("#t_copy_page input").focus();
            }
        });
    } else if (table_id == "t_admin") {
        $("#type_log_displayed").val("admin_logs");
        oTable4 = $("#t_admin").dataTable({
            "aaSorting": [[ 1, "asc" ]],
            "sPaginationType": "full_numbers",
            "bProcessing": true,
            "bDestroy": true,
            "bServerSide": true,
            "sAjaxSource": "sources/datatable/datatable.logs.php?action=admin",
            "bJQueryUI": true,
            "oLanguage": {
                "sUrl": "includes/language/datatables.<?php echo $_SESSION['user_language'];?>.txt"
            },
            "fnInitComplete": function() {
                $("#t_admin_page input").focus();
            }
        });
    } else if (table_id == "t_items") {
        $("#type_log_displayed").val("items_logs");
        oTable5 = $("#t_items").dataTable({
            "aaSorting": [[ 1, "asc" ]],
            "sPaginationType": "full_numbers",
            "bProcessing": true,
            "bDestroy": true,
            "bServerSide": true,
            "sAjaxSource": "sources/datatable/datatable.logs.php?action=items",
            "bJQueryUI": true,
            "oLanguage": {
                "sUrl": "includes/language/datatables.<?php echo $_SESSION['user_language'];?>.txt"
            },
            "fnInitComplete": function() {
                $("#t_items_page input").focus();
            }
        });
    } else if (table_id == "t_failed_auth") {
        $("#type_log_displayed").val("failed_auth_logs");
        oTable6 = $("#t_failed_auth").dataTable({
            "aaSorting": [[ 1, "asc" ]],
            "sPaginationType": "full_numbers",
            "bProcessing": true,
            "bDestroy": true,
            "bServerSide": true,
            "sAjaxSource": "sources/datatable/datatable.logs.php?action=failed_auth",
            "bJQueryUI": true,
            "oLanguage": {
                "sUrl": "includes/language/datatables.<?php echo $_SESSION['user_language'];?>.txt"
            },
            "fnInitComplete": function() {
                $("#t_items_page input").focus();
            }
        });
    }
}

$(function() {
    $("#radio_log")
    .buttonset()
    .click(function(e) {
        $("#div_log_purge").show();
    });
    $(".button").button();
    $("#log_jours").datepicker({
        regional: 'fr',
        dateFormat : 'dd/mm/yy'
    });

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

    /*
    * PURGE
    */
    $("#butPurge").button().click(function(e) {
        // prepare dialogbox
        $("#div_dialog_message").dialog("option", "title", "<?php echo $LANG['admin_main'];?>");
        $("#div_dialog_message").dialog("option", "buttons", {
            "<?php echo $LANG['ok'];?>": function() {
                $(this).dialog("close");
            }
        });

        // send query
        $.post(
            "sources/views.queries.php",
            {
                type       : "purgeLogs",
                purgeTo    : $("#purgeTo").val(),
                purgeFrom  : $("#purgeFrom").val(),
                logType    : $("#type_log_displayed").val()
            },
            function(data) {
                if (data[0].status == "ok") {
                    $("#div_dialog_message_text").html("<?php echo $LANG['purge_done'];?> "+data[0].nb);
                    $("#div_dialog_message").dialog("open");
                    // refresh table
                    if ($("#type_log_displayed").val() == "connections_logs") oTable0.api().ajax.reload();
                    else if ($("#type_log_displayed").val() == "errors_logs") oTable1.api().ajax.reload();
                    else if ($("#type_log_displayed").val() == "access_logs") oTable2.api().ajax.reload();  
                    else if ($("#type_log_displayed").val() == "copy_logs") oTable3.api().ajax.reload(); 
                    else if ($("#type_log_displayed").val() == "admin_logs") oTable4.api().ajax.reload(); 
                    else if ($("#type_log_displayed").val() == "items_logs") oTable5.api().ajax.reload(); 
                    else if ($("#type_log_displayed").val() == "failed_auth_logs") oTable6.api().ajax.reload();                 
                }
                $("#purgeTo, #purgeFrom").val("");
            },
            "json"
       );
    });

    $( "#purgeFrom" ).datepicker({
        defaultDate: "today",
        changeMonth: true,
        changeYear: true,
        numberOfMonths: 1,
        onClose: function( selectedDate ) {
            var minDate = new Date(Date.parse(selectedDate));
            minDate.setDate(minDate.getDate() + 1);
            $( "#to" ).datepicker( "option", "minDate", minDate );
        }
    });
    $( "#purgeTo" ).datepicker({
        defaultDate: "+1w",
        changeMonth: true,
        changeYear: true,
        numberOfMonths: 1,
        onClose: function( selectedDate ) {
            var maxDate = new Date(Date.parse(selectedDate));
            maxDate.setDate(maxDate.getDate() + 1);
            $( "#from" ).datepicker( "option", "maxDate", maxDate );
        }
    });
});
//]]>
</script>