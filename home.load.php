<?php
/**
 *
 * @file          home.load.php
 * @author        Nils Laumaillé
 * @version       2.1.25
 * @copyright     (c) 2009-2015 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
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

	//build nice buttonset
    $("#radio_import_type, #connect_ldap_mode").buttonset();
    $("#personal_sk, #change_personal_sk, #reset_personal_sk, #pickfiles_csv, #pickfiles_kp").button();

    if ($("#personal_saltkey_set").val() != 1) {
        $("#change_personal_sk").button("disable");
    }

  //only numerics
    $(".numeric_only").numeric();

   //Simulate a CRON activity
    $.post(
        "sources/main.queries.php",
        {
            type    : "send_wainting_emails"
        },
        function(data) {
            //
        }
   );
});


function ChangeMyPass()
{
    if ($("#new_pw").val() != "" && $("#new_pw").val() == $("#new_pw2").val()) {
        if ($("#pw_strength_value").val() >= $("#user_pw_complexity").val()) {
            var data = "{\"new_pw\":\""+sanitizeString($("#new_pw").val())+"\"}";
            $.post(
                "sources/main.queries.php",
                {
                    type                : "change_pw",
                    change_pw_origine    : "first_change",
                    complexity            :    $("#user_pw_complexity").val(),
                    data                 :    prepareExchangedData(data, "encode", "<?php echo $_SESSION['key'];?>")
                },
                function(data) {
                    if (data[0].error == "complexity_level_not_reached") {
                        $("#new_pw, #new_pw2").val("");
                        $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<span><?php echo $LANG['error_complex_not_enought'];?></span>");
                    } else {
                        document.main_form.submit();
                    }
                },
                "json"
            );
        } else {
            $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<?php echo $LANG['error_complex_not_enought'];?>");
        }
    } else {
        $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<?php echo $LANG['index_pw_error_identical'];?>");
    }
}

function toggle_import_type(type)
{
    if (type == "csv") {
        $('#import_type_csv').show();
        $('#import_type_keepass').hide();
        $(".ui-dialog-buttonpane button:contains('<?php echo $LANG['import_button'];?>')").button("enable");
    } else {
        $('#import_type_csv').hide();
        $('#import_type_keepass').show();
        $(".ui-dialog-buttonpane button:contains('<?php echo $LANG['import_button'];?>')").button("disable");
    }
}




//Toggle details importation
function toggle_importing_details()
{
    $("#div_importing_kp_details").toggle();
}

//PRINT OUT: select folders
function print_out_items()
{
    $("#selected_folders").empty();
    $("#loading_folders_wait").show();

    //Lauchn ajax query that will build the select list
    $.post(
        "sources/main.queries.php",
        {
           type        : "get_folders_list",
           div_id    : "selected_folders"
        },
        function(data) {
            data = $.parseJSON(data);
            for (reccord in data) {
                $("#selected_folders").append("<option value='"+reccord+"'>"+data[reccord]+"</option>");
            }
            $("#loading_folders_wait").hide();
        }
   );

    //Open dialogbox
    $("#div_print_out").dialog("open");
}
</script>