<?php
/**
 * @file          roles.load.php
 * @author        Nils Laumaillé
 * @version       2.1.24
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
$(function() {
$("#add_new_role").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 240,
        title: "<?php echo $LANG["give_function_title"];?>",
        buttons: {
            "<?php echo $LANG["save_button"];?>": function() {
            	$("#new_role_error").hide().html("");
            	if ($("#new_role_complexity").val() != "") {
                    $.post(
                        "sources/roles.queries.php",
                        {
                            type    : "add_new_role",
                            name    : $("#new_function").val(),
                            complexity    : $("#new_role_complexity").val()
                        },
                        function(data) {
                            if (data[0].error == "no") {
                                $("#new_function").val("");
                                $("#add_new_role").dialog("close");
                                refresh_roles_matrix("reload");
                            } else {
                            	$("#new_role_error").show().html(data[0].message);
                            }
                        },
                        "json"
                   );
            	} else {
            		$("#new_role_error").show().html("<?php echo addslashes($LANG['error_role_complex_not_set']);?>");
            	}
            },
            "<?php echo $LANG["cancel_button"];?>": function() {
                $(this).dialog("close");
            }
        }
    });

    $("#delete_role").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 150,
        title: "<?php echo $LANG["admin_action"];?>",
        buttons: {
            "<?php echo $LANG["ok"];?>": function() {
                $.post(
                    "sources/roles.queries.php",
                    {
                        type    : "delete_role",
                        id        : $("#delete_role_id").val()
                    },
                    function(data) {
                        if (data[0].error == "no") {
                            $("#delete_role").dialog("close");
                            refresh_roles_matrix("reload");
                        }
                    },
                    "json"
               );
            },
            "<?php echo $LANG["cancel_button"];?>": function() {
                $(this).dialog("close");
            }
        }
    });

    $("#edit_role").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 260,
        title: "<?php echo $LANG["admin_action"];?>",
        buttons: {
            "<?php echo $LANG["ok"];?>": function() {
            	$("#edit_role_error").hide().html("");
                $.post(
                    "sources/roles.queries.php",
                    {
                        type    : "edit_role",
                        id      : $("#edit_role_id").val(),
                        start    : $('#role_start').val(),
                        title      : $("#edit_role_title").val(),
                        complexity    : $("#edit_role_complexity").val()
                    },
                    function(data) {
                        if (data[0].error == "yes") {
                            $("#edit_role_error").show().html(data[0].message);
                        } else {
                            $("#edit_role_title").val("");
                            $("#edit_role").dialog("close");
                            $("#div_loading").show();
                            refresh_roles_matrix("reload");
                        }
                    },
                    "json"
               );
            },
            "<?php echo $LANG["cancel_button"];?>": function() {
                $("#edit_role_error").html("").hide();
                $(this).dialog("close");
            }
        }
    });

    $("#help_on_roles").dialog({
        bgiframe: false,
        modal: false,
        autoOpen: false,
        width: 850,
        height: 500,
        title: "<?php echo $LANG["admin_help"];?>",
        buttons: {
            "<?php echo $LANG["close"];?>": function() {
                $(this).dialog("close");
            }
        },
        open: function() {
            $("#accordion").accordion({ autoHeight: false, navigation: true, collapsible: true, active: false });
        }
    });

    $("#type_of_rights").dialog({
        bgiframe: false,
        modal: false,
        autoOpen: false,
        width: 300,
        height: 190,
        title: "<?php echo $LANG["change_right_access"];?>",
        buttons: {
            "<?php echo $LANG["save_button"];?>": function() {
            	$("#edit_role_error").hide().html("");
                $.post(
                    "sources/roles.queries.php",
                    {
                        type    : "change_role_via_tm",
                        access  : $("input[name=right_types_radio]:checked").attr("id").substring(6),
                        folder  : $("#change_folder").val(),
                        role    : $('#change_role').val(),
                        line    : $("#change_line").val()
                    },
                    function(data) {
                        $("#div_loading").show();
                        refresh_roles_matrix("reload");
                        $("#type_of_rights").dialog("close");
                    },
                    "json"
               );
            },
            "<?php echo $LANG["close"];?>": function() {
                $(this).dialog("close");
            }
        },
        open: function() {
            $("#accordion").accordion({ autoHeight: false, navigation: true, collapsible: true, active: false });
        }
    });
    
    

    refresh_roles_matrix();
});

//###########
//## FUNCTION : Change the actual right of the role other the select folder
//###########
function tm_change_role(role,folder,cell_id,allowed)
{
    $("#div_loading").show();
    $.post(
        "sources/roles.queries.php",
        {
            type    : "change_role_via_tm",
            role    : role,
            folder    : folder,
            cell_id    : cell_id,
            allowed    : allowed
        },
        function(data) {
            refresh_roles_matrix("reload");
            $("#div_loading").hide();
        }
   );
}

function delete_this_role(id,name)
{
    $("#delete_role_id").val(id);
    $("#delete_role_show").html(name);
    $("#delete_role").dialog("open");
}

function edit_this_role(id,name,complexity)
{
    $("#edit_role_id").val(id);
    $("#edit_role_show").html(name);
    $("#edit_role_title").val(name);
    $("#edit_role_complexity").val(complexity);
    $("#edit_role").dialog("open");
}

function allow_pw_change_for_role(id, value)
{
    $("#div_loading").show();
    //Send query
    $.post(
        "sources/roles.queries.php",
        {
            type    : "allow_pw_change_for_role",
            id      : id,
            value      : value
        },
        function(data) {
            if (value == 0)
                $("#img_apcfr_"+id).attr("src","includes/images/ui-text-field-password-red.png");
            else
                $("#img_apcfr_"+id).attr("src","includes/images/ui-text-field-password-green.png");
            $("#div_loading").hide();
        }
   );
}

/**
 *
 * @access public
 * @return void
 **/
function refresh_roles_matrix(order)
{
	$("#div_loading").show();

    //clean up
    $("#roles_next, #roles_previous").hide();

    //manage start query
    if (order == "next") {
        var start = $('#next_role').val();
    } else if (order == "previous") {
        var start = $('#previous_role').val();
    } else if (order == "reload") {
        var start = $('#role_start').val();
    } else {
        var start = 0;
    }
    $('#role_start').val(start);

    //send query
    $.post(
        "sources/roles.queries.php",
        {
            type    : "refresh_roles_matrix",
            start    : start
        },
        function(data) {
            //decrypt data
            data = $.parseJSON(data);
            $("#matrice_droits").html("");
            if (data.new_table != "") {
                $("#matrice_droits").html(data.new_table);
                if (data.next < data.all) {
                    $("#roles_next").show();
                }
                if (data.next >= 9 && data.previous >= 0) {
                    $("#roles_previous").show();
                }
                //manage next & previous arrows
                $('#next_role').val(data.next);
                $('#previous_role').val(data.previous);
            } else {
                $("#matrice_droits").html(data.error);
            }
            $("#div_loading").hide();
        }
   );
}

function openRightsDialog(role, folder, line, right)
{
    if (right == "W") {
        $("#right_write").prop("checked", true);
    } else if (right == "R") {
        $("#right_read").prop("checked", true);
    } else {
        $("#right_noaccess").prop("checked", true);
    }
    $("#change_role").val(role);
    $("#change_folder").val(folder);
    $("#change_line").val(line);
    $("#type_of_rights").dialog("open");
}
</script>
