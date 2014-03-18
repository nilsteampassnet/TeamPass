<?php
/**
 * @file          folders.load.php
 * @author        Nils Laumaillé
 * @version       2.1.19
 * @copyright     (c) 2009-2014 Nils Laumaillé
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

$(function() {

    //Prepare creation dialogbox
    $("#open_add_group_div").click(function() {
        $("#div_add_group").dialog("open");
    });

	$("#div_add_group").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 250,
        height: 330,
        title: "<?php echo $txt['add_new_group'];?>",
        buttons: {
            "<?php echo $txt['save_button'];?>": function() {
                add_new_folder();
            },
            "<?php echo $txt['cancel_button'];?>": function() {
                $(this).dialog("close");
            }
        }
    });

    $("#help_on_folders").dialog({
        bgiframe: false,
        modal: false,
        autoOpen: false,
        width: 850,
        height: 500,
        title: "<?php echo  $txt["admin_help"];?>",
        buttons: {
            "<?php echo $txt["close"];?>": function() {
                $(this).dialog("close");
            }
        },
        open: function() {
            $("#accordion").accordion({ autoHeight: false, navigation: true, collapsible: true, active: false });
        }
    });

    $("#div_edit_folder").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 250,
        height: 330,
        title: "<?php echo $txt['at_category'];?>",
        open: function(event, ui) {
            var id = $("#folder_id_to_edit").val();

            //update dialogbox with data
            $("#edit_folder_title").val($("#title_"+id).text());
            $("#edit_folder_renewal_period").val($("#renewal_"+id).text());
            $("#edit_folder_complexite").val($("#renewal_id_"+id).val());
            $("#edit_parent_id").val($("#parent_id_"+id).val());
        },
        buttons: {
            "<?php echo $txt['save_button'];?>": function() {
                if ($('#edit_folder_complexite').val() == "") {
                	 $("#edit_folder_show_error").html("<?php echo $txt['error_group_complex'];?>").show();
                	 return;
                }if ($('#edit_folder_title').val() == "") {
                	 $("#edit_folder_show_error").html("<?php echo $txt['error_group_label'];?>").show();
                	 return;
                }
                //prepare data
                var data = '{"id":"'+$("#folder_id_to_edit").val()+'", "title":"'+$('#edit_folder_title').val().replace(/"/g,'&quot;') + '", "complexity":"'+$('#edit_folder_complexite').val().replace(/"/g,'&quot;')+'", '+
                '"parent_id":"'+$('#edit_parent_id').val().replace(/"/g,'&quot;')+'", "renewal_period":"'+$('#edit_folder_renewal_period').val().replace(/"/g,'&quot;')+'"}';

                //send query
                $.post(
                    "sources/folders.queries.php",
                    {
                    type    : "update_folder",
                    data      : prepareExchangedData(data, "encode"),
                    },
                    function(data) {
                        //Check errors
                        if (data[0].error == "error_group_exist") {
                            $("#edit_folder_show_error").html("<?php echo $txt['error_group_exist'];?>").show();
                            LoadingPage();
                        } else if (data[0].error == "error_html_codes") {
                            $("#edit_folder_show_error").html("<?php echo $txt['error_html_codes'];?>").show();
                            LoadingPage();
                        } else {
                            $("#folder_id_to_edit").val("");    //clear id
                            window.location.href = "index.php?page=manage_folders";
                        }
                    },
                    "json"
               );
            },
            "<?php echo $txt['cancel_button'];?>": function() {
                //clear id
                $("#folder_id_to_edit").val("");

                //Close
                $(this).dialog("close");
            }
        }
    });
});

function supprimer_groupe(id)
{
    if (confirm("<?php echo $txt['confirm_delete_group'];?>")) {
        //send query
        $.post(
            "sources/folders.queries.php",
            {
                type    : "delete_folder",
                id      : id
            },
            function(data) {
                RefreshPage("form_groupes");
            }
       );
    }
}

function Changer_Droit_Complexite(id,type)
{
    var droit = 0;
    if (type == "creation") {
        if ($("#cb_droit_"+id).prop("checked") == true) droit = 1;
        type = "modif_droit_autorisation_sans_complexite";
    } else if (type == "modification") {
        if ($("#cb_droit_modif_"+id).prop("checked") == true) droit = 1;
        type = "modif_droit_modification_sans_complexite";
    }
    //send query
    $.post(
        "sources/folders.queries.php",
        {
            type    : type,
            id      : id,
            droit    : droit
        }
   );
}

function add_new_folder()
{
    //Check if renewal_period is an integer
    if (isInteger(document.getElementById("add_node_renewal_period").value) == false) {
        document.getElementById("addgroup_show_error").innerHTML = "<?php echo $txt['error_renawal_period_not_integer'];?>";
        $("#addgroup_show_error").show();
    } else if (document.getElementById("new_rep_complexite").value == "") {
        document.getElementById("addgroup_show_error").innerHTML = "<?php echo $txt['error_group_complex'];?>";
        $("#addgroup_show_error").show();
    } else {
        if (document.getElementById("ajouter_groupe_titre").value != "" && document.getElementById("parent_id").value != "na") {
            $("#addgroup_show_error").hide();
            LoadingPage();
            //prepare data
            var data = '{"title":"'+$('#ajouter_groupe_titre').val().replace(/"/g,'&quot;') + '", "complexity":"'+$('#new_rep_complexite').val().replace(/"/g,'&quot;')+'", '+
            '"parent_id":"'+$('#parent_id').val().replace(/"/g,'&quot;')+'", "renewal_period":"'+$('#add_node_renewal_period').val().replace(/"/g,'&quot;')+'"}';

            //send query
            $.post(
                "sources/folders.queries.php",
                {
                    type    : "add_folder",
                    data    : aes_encrypt(data)
                },
                function(data) {
                    //Check errors
                    if (data[0].error == "error_group_exist") {
                        $("#div_add_group").dialog("open");
                        $("#addgroup_show_error").html("<?php echo $txt['error_group_exist'];?>");
                        $("#addgroup_show_error").show();
                        LoadingPage();
                    } else if (data[0].error == "error_html_codes") {
                        $("#div_add_group").dialog("open");
                        $("#addgroup_show_error").html("<?php echo $txt['error_html_codes'];?>");
                        $("#addgroup_show_error").show();
                        LoadingPage();
                    } else {
                        window.location.href = "index.php?page=manage_folders";
                    }
                },
                "json"
           );
        } else {
            document.getElementById("addgroup_show_error").innerHTML = "<?php echo $txt['error_fields_2'];?>";
            $("#addgroup_show_error").show();
        }
    }
}

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

</script>
