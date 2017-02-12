<?php
/**
 * @file          folders.load.php
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
$(function() {

    //Launch the datatables pluggin
    var tableFolders = $("#t_folders").dataTable({
        "order": [[ 1, "asc" ]],
        "ordering": false,
        "searching": false,
        "paging": false,
        "processing": true,
        "serverSide": true,
        "ajax": {
            url: "sources/datatable/datatable.folders.php"
        },
        "language": {
            "url": "includes/language/datatables.<?php echo $_SESSION['user_language'];?>.txt"
        },
        "columns": [
            {"width": "7%"},
            {"width": "5%"},
            {className: "dt-body-left"},
            {"width": "5%"},
            {"width": "15%"},
            {"width": "10%"},
            {"width": "10%"},
            {"width": "5%"},
            {"width": "5%"},
            {"width": "5%"}
        ]
    })
    .on('xhr.dt', function ( e, settings, json, xhr ) {
        //$(".tip").tooltipster();
    } );


    $("#div_add_group").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 350,
        height: 450,
        title: "<?php echo $LANG['add_new_group'];?>",
        open: function(event, ui) {
            $("#new_folder_wait").hide();

            //empty dialogbox
            $("#div_add_group input, #div_add_group select").val("");
            $("#add_node_renewal_period").val("0");
            $("#folder_block_modif, #folder_block_creation").val("0");
            $("#parent_id").val("na");
        },
        buttons: {
            "<?php echo $LANG['save_button'];?>": function() {
                //Check if renewal_period is an integer
                if (isInteger(document.getElementById("add_node_renewal_period").value) == false) {
                    document.getElementById("addgroup_show_error").innerHTML = "<?php echo $LANG['error_renawal_period_not_integer'];?>";
                    $("#addgroup_show_error").show();
                } else if (document.getElementById("new_rep_complexite").value == "") {
                    document.getElementById("addgroup_show_error").innerHTML = "<?php echo $LANG['error_group_complex'];?>";
                    $("#addgroup_show_error").show();
                } else if (document.getElementById("parent_id").value == "" || isNaN(document.getElementById("parent_id").value)) {
                    document.getElementById("addgroup_show_error").innerHTML = "<?php echo $LANG['error_no_selected_folder'];?>";
                    $("#addgroup_show_error").show();
                } else {
                    if (document.getElementById("ajouter_groupe_titre").value != "" && document.getElementById("parent_id").value != "na") {
                        $("#new_folder_wait").show();
                        $("#addgroup_show_error").hide();
                        //prepare data
                        var data = '{"title":"'+$('#ajouter_groupe_titre').val().replace(/"/g,'&quot;') + '", "complexity":"'+$('#new_rep_complexite').val().replace(/"/g,'&quot;')+'", '+
                        '"parent_id":"'+$('#parent_id').val().replace(/"/g,'&quot;')+'", "renewal_period":"'+$('#add_node_renewal_period').val().replace(/"/g,'&quot;')+'" , "block_creation":"'+$("#folder_block_creation").val()+'" , "block_modif":"'+$("#folder_block_modif").val()+'"}';
                        
                        //send query
                        $.post(
                            "sources/folders.queries.php",
                            {
                                type    : "add_folder",
                                data    : prepareExchangedData(data, "encode", "<?php echo $_SESSION['key'];?>"),
                                key     : "<?php echo $_SESSION['key'];?>"
                            },
                            function(data) {
                                //Check errors
                                if (data[0].error == "error_group_exist") {
                                    $("#div_add_group").dialog("open");
                                    $("#addgroup_show_error").html("<?php echo $LANG['error_group_exist'];?>");
                                    $("#addgroup_show_error").show();
                                } else if (data[0].error == "error_html_codes") {
                                    $("#div_add_group").dialog("open");
                                    $("#addgroup_show_error").html("<?php echo $LANG['error_html_codes'];?>");
                                    $("#addgroup_show_error").show();
                                } else if (data[0].error == "error_title_only_with_numbers") {
                                    $("#div_add_group").dialog("open");
                                    $("#addgroup_show_error").html("<?php echo $LANG['error_only_numbers_in_folder_name'];?>");
                                    $("#addgroup_show_error").show();
                                } else {
                                    tableFolders.api().ajax.reload();
                                    $("#parent_id, #edit_parent_id").empty().append(data[0].droplist);
                                    $("#div_add_group").dialog("close");
                                }
                                $("#new_folder_wait").hide();
                            },
                            "json"
                       );
                    } else {
                        document.getElementById("addgroup_show_error").innerHTML = "<?php echo $LANG['error_fields_2'];?>";
                        $("#addgroup_show_error").show();
                    }
                }
            },
            "<?php echo $LANG['cancel_button'];?>": function() {
                $("#addgroup_show_error").html("").hide();
                $(this).dialog("close");
            }
        }
    });

    $("#div_edit_folder").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 350,
        height: 450,
        title: "<?php echo $LANG['at_category'];?>",
        open: function(event, ui) {
            var id = $("#folder_id_to_edit").val();
            $("#edit_folder_wait").hide();

            //update dialogbox with data
            $("#edit_folder_title").val($("#title_"+id).text());
            $("#edit_folder_renewal_period").val($("#renewal_"+id).text());
            $("#edit_folder_complexite").val($("#renewal_id_"+id).val());
            $("#edit_parent_id").val($("#parent_id_"+id).val());
            $("#edit_folder_block_creation").val($("#block_creation_"+id).val());
            $("#edit_folder_block_modif").val($("#block_modif_"+id).val());
        },
        buttons: {
            "<?php echo $LANG['delete'];?>": function() {
                if (confirm("<?php echo $LANG['confirm_delete_group'];?>")) {
                    //send query
                    $.post(
                        "sources/folders.queries.php",
                        {
                            type    : "delete_folder",
                            id      : $("#folder_id_to_edit").val(),
                            key     : "<?php echo $_SESSION['key'];?>"
                        },
                        function(data) {
                            tableFolders.api().ajax.reload();
                            $("#div_edit_folder").dialog("close");
                        }
                    );
                }

                //Close
                $("#div_edit_folder").dialog("close");
            },
            "<?php echo $LANG['save_button'];?>": function() {
                if ($('#edit_folder_complexite').val() == "") {
                     $("#edit_folder_show_error").html("<?php echo $LANG['error_group_complex'];?>").show();
                     return;
                }if ($('#edit_folder_title').val() == "") {
                     $("#edit_folder_show_error").html("<?php echo $LANG['error_group_label'];?>").show();
                     return;
                }if ($('#edit_parent_id').val() == "na") {
                     $("#edit_folder_show_error").html("<?php echo $LANG['error_no_selected_folder'];?>").show();
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
                        data      : prepareExchangedData(data, "encode", "<?php echo $_SESSION['key'];?>"),
                        key        : "<?php echo $_SESSION['key'];?>"
                    },
                    function(data) {
                        //Check errors
                        if (data[0].error == "error_group_exist") {
                            $("#edit_folder_show_error").html("<?php echo $LANG['error_group_exist'];?>").show();
                            LoadingPage();
                        } else if (data[0].error == "error_html_codes") {
                            $("#edit_folder_show_error").html("<?php echo $LANG['error_html_codes'];?>").show();
                            LoadingPage();
                        } else if (data[0].error == "error_title_only_with_numbers") {
                            $("#edit_folder_show_error").html("<?php echo $LANG['error_only_numbers_in_folder_name'];?>").show();
                            $("#edit_folder_wait").hide();
                        } else {
                            $("#folder_id_to_edit").val("");    //clear id
                            tableFolders.api().ajax.reload();
                            $("#parent_id, #edit_parent_id").empty().append(data[0].droplist);
                            $("#div_edit_folder").dialog("close");
                        }
                        $("#edit_folder_wait").hide();
                    },
                    "json"
               );
            },
            "<?php echo $LANG['cancel_button'];?>": function() {
                //clear id
                $("#folder_id_to_edit").val("");
                $("#edit_folder_show_error").html("");

                //Close
                $("#div_edit_folder").dialog("close");
            }
        }
    });

    // manage the click on toggle icons
    $(document).on({
        click: function (event) {
            $("#div_loading").show();
            if ($(this).attr('tp') === undefined) {
                // case of folder selection
                var selected_cb = $(this);
                var elem = $(this).attr("id").split("-");
                if ($(this).prop("checked") == true) {
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
                        key     : "<?php echo $_SESSION['key'];?>"
                    },
                    function(data) {
                        $("#div_loading").hide();
                        // check/uncheck checkbox
                        if (data[0].subfolders !== "") {
                            var tmp = data[0].subfolders.split(";");
                            for (var i = tmp.length - 1; i >= 0; i--) {
                                if (selected_cb.prop("checked") == true) {
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
                        key        : "<?php echo $_SESSION['key'];?>"
                    },
                    function(data) {
                        $("#div_loading").hide();
                        // refresh table content
                        tableFolders.api().ajax.reload();
                    }
                );
            }
        }
    },
    ".fa-toggle-off, .fa-toggle-on, .cb_selected_folder"
    );

    //
    $( "#click_delete_multiple_folders" ).click(function() {
        var list_i = "";
        $(".cb_selected_folder:checked").each(function() {
            var elem = $(this).attr("id").split("-");
            if (list_i == "") list_i = elem[1];
            else list_i = list_i+';'+elem[1];
        });
        if (list_i != "" && $("#action_on_going").val() == "" && confirm("<?php echo addslashes($LANG['confirm_deletion']);?>")) {
            $("#div_loading").show();
            $("#action_on_going").val("multiple_folders");
            var data = '{"foldersList":"'+list_i+'"}';
            //send query
            $.post(
                "sources/folders.queries.php",
                {
                    type    : "delete_multiple_folders",
                    data    : prepareExchangedData(data, "encode", "<?php echo $_SESSION['key'];?>"),
                    key     : "<?php echo $_SESSION['key'];?>"
                },
                function(data) {
                    tableFolders.api().ajax.reload();
                    $("#action_on_going").val("");
                    $("#div_loading").hide();
                },
                "json"
           );
        }
    });

    $("#click_refresh_folders_list").click(function() {
        tableFolders.api().ajax.reload();
    });
});


/**
 *
 * @access public
 * @return void
 **/
function open_edit_folder_dialog(id)
{
    $("#folder_id_to_edit").val(id);
    $("#div_edit_folder").dialog("open");
}
//]]>
</script>
