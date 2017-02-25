<?php
/**
 * @file          views.load.php
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
function GenererLog()
{
    if ($("#log_jours").val() == "") return false;

    LoadingPage();
    $.post(
        "sources/views.queries.php",
        {
            type    : "log_generate",
            date     : $("#log_jours").val()
        },
        function(data) {
            $("#lien_pdf").html(data[0].text);
            $("#div_loading").hide();
        },
        "json"
   );
}

function ListerElemDel()
{
    LoadingPage();
    $.post(
        "sources/views.queries.php",
        {
            type    : "lister_suppression"
        },
        function(data) {
            $("#liste_elems_del").html(data[0].text);
            $('#item_deleted_select_all').click(
                function() {
                    if (!$('#item_deleted_select_all').attr('checked')) {
                        $("input[type='checkbox']:not([disabled='disabled'])").attr('checked', true);
                    } else {
                        $("input[type='checkbox']:not([disabled='disabled'])").removeAttr('checked');
                    }
                }
           );
           $(".button").button();
           $("#div_loading").hide();
        },
        "json"
   );
}

function displayLogs(type, page, order)
{
    if (type == "reorder") {
        type = $("#type_log_displayed").val();
        page = $("#log_page_displayed").val();
        if ($("#log_direction_displayed").val() == "ASC") $("#log_direction_displayed").val("DESC");
        else $("#log_direction_displayed").val("ASC")
    } else {
        $("#type_log_displayed").val(type);
        $("#log_page_displayed").val(page);
        if (type != $("#type_log_displayed").val())
            $("#log_direction_displayed").val("ASC");
    }

    if (order == "") order = "date";

    var filter = "";
    $("#div_show_system_logs").show();
    //Show or not the column URL
    if (type == "errors_logs") $("#th_url").show();
    else $("#th_url").hide();
    if (type == "access_logs" || type == "copy_logs" || type == "admin_logs") {
        $("#div_show_system_logs").show();
        $("#div_show_items_logs").hide();
        $("#filter_logs_button").attr("onclick","displayLogs(\'"+type+"\',1,\'date\')")
        $("#filter_logs_div").show();
        filter = $("#filter_logs").val();
    } else if (type == "items_logs") {
        $("#div_show_system_logs").hide();
        $("#filter_itemslogs_button").attr("onclick","displayLogs(\'"+type+"\',1,\'date\')")
        $("#div_show_items_logs").show();
        filter = $("#filter_itemslogs").val();
    } else {
        $("#filter_logs_div, #div_show_items_logs").hide();
        $("#div_show_system_logs").show()
    }

    $.post(
        "sources/views.queries.php",
        {
            type    : type,
            page    : page,
            filter    : filter,
            order    : order,
            direction:    $("#log_direction_displayed").val()
        },
        function(data) {
            if (type != "items_logs") {
                $("#tbody_logs").empty().append(data[0].tbody_logs);
                $("#log_pages").empty().append(data[0].log_pages);
            } else {
                $("#tbody_itemslogs").empty().append(data[0].tbody_logs);
                $("#itemslogs_pages").empty().append(data[0].log_pages);
            }

        },
        "json"
   );
}

//This permits to launch ajax query for generate a listing of expired items
function generate_renewal_listing()
{
    LoadingPage();

    $.post(
        "sources/views.queries.php",
        {
            type    : "generate_renewal_listing",
            period    : $("#expiration_period").val()
        },
        function(data) {
            $("#list_renewal_items").html(data[0].text);
            $("#list_renewal_items_pdf").val(data[0].pdf);
            $("#renewal_icon_pdf").show();
            $("#div_loading").hide();
        },
        "json"
   );
}

//FUNCTION permits to generate a PDF file
function generate_renewal_pdf()
{
    LoadingPage();

    $.post(
        "sources/views.queries.php",
        {
            type    : "generate_renewal_pdf",
            text    : $("#list_renewal_items_pdf").val()
        },
        function(data) {
            window.open(data[0].file, '_blank');
            $("#div_loading").hide();
        },
        "json"
   );
}

$(function() {
    $("#tabs").tabs({
        beforeLoad: function( event, ui ) {
            ui.panel.html('<div id="loader_tab"><i class="fa fa-cog fa-spin"></i>&nbsp;<?php echo $LANG['loading'];?>...</div>')
        },
        load: function( event, ui ) {
            $("#loader_tab").remove();
        }
    });

    ListerElemDel();

    $("#log_jours").datepicker({
        regional: 'fr',
        dateFormat : 'dd/mm/yy'
    });

    $("#radio_logs, #radio_database").buttonset();
    $("#radio_logs").click(function(e) {
        $("#div_log_purge").show();
    });


    $("#tab2_dialog").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 150,
        title: "<?php echo $LANG['please_confirm'];?>",
        open : function() {
            // check if one is ticked
            var list_i = "";
            $(".cb_deleted_item:checked").each(function() {
                if (list_i == "") list_i = $(this).val();
                else list_i = list_i+';'+$(this).val();
            });
            var list_f = "";
            $(".cb_deleted_folder:checked").each(function() {
                if (list_f == "") list_f = $(this).val();
                else list_f = list_f+';'+$(this).val();
            });
            if (list_f == "" && list_i == "") {
                $("#tab2_dialog").dialog("close");
                return false;
            }

            // confirm?
            if ($("#tab2_action").val() == "restoration") {
                $("#tab2_dialog_html").html("<?php echo $LANG['views_confirm_restoration'];?>");
            } else if ($("#tab2_action").val() == "deletion") {
                $("#tab2_dialog_html").html("<?php echo $LANG['views_confirm_items_deletion'];?>");
            }
        },
        buttons: {
            "<?php echo $LANG['confirm'];?>": function() {
                LoadingPage();
                var list_i = "";
                $(".cb_deleted_item:checked").each(function() {
                    if (list_i == "") list_i = $(this).val();
                    else list_i = list_i+';'+$(this).val();
                });
                var list_f = "";
                $(".cb_deleted_folder:checked").each(function() {
                    if (list_f == "") list_f = $(this).val();
                    else list_f = list_f+';'+$(this).val();
                });

                if ($("#tab2_action").val() == "restoration") {
                    $.post(
                        "sources/views.queries.php",
                        {
                            type    : "restore_deleted__items",
                            list_i    : list_i,
                            list_f    : list_f
                        },
                        function(data) {
                            ListerElemDel();
                            LoadingPage();
                            $("#tab2_dialog").dialog("close");
                        }
                    );
                } else if ($("#tab2_action").val() == "deletion") {
                    $.post(
                        "sources/views.queries.php",
                        {
                            type    : "really_delete_items",
                            items    : list_i,
                            folders    : list_f
                        },
                        function(data) {
                            ListerElemDel();
                            LoadingPage();
                            $("#tab2_dialog").dialog("close");
                        }
                    );
                }
            },
            "<?php echo $LANG['cancel_button'];?>": function() {
                $(this).dialog("close");
            }
        }
    });
});
//]]>
</script>
