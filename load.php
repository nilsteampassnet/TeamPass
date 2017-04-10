<?php
/**
 *
 * @file          load.php
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

// Common elements
$htmlHeaders = '
        <link rel="stylesheet" href="includes/js/jquery-ui/jquery-ui.min.css" type="text/css" />
        <link rel="stylesheet" href="includes/js/jquery-ui/jquery-ui.structure.min.css" type="text/css" />
        <link rel="stylesheet" href="includes/js/jquery-ui/jquery-ui.theme.min.css" type="text/css" />
        <script type="text/javascript" src="includes/js/jquery-ui/external/jquery/jquery.js"></script>
        <script type="text/javascript" src="includes/js/jquery-ui/jquery-ui.min.js"></script>
        <script src="includes/js/jeditable/jquery.jeditable.js" type="text/javascript"></script>
        <script type="text/javascript" src="includes/js/tooltipster/js/jquery.tooltipster.min.js"></script>
        <link rel="stylesheet" href="includes/js/tooltipster/css/tooltipster.css" type="text/css" />
        <script type="text/javascript" src="includes/js/simplePassMeter/simplePassMeter.js"></script>
        <script type="text/javascript" src="includes/libraries/Encryption/Crypt/aes.js"></script>
        <script type="text/javascript" src="includes/libraries/Encryption/Crypt/aes-ctr.js"></script>
        <script type="text/javascript" src="includes/libraries/Plupload/plupload.full.min.js"></script>
        <link rel="stylesheet" href="includes/js/nprogress/nprogress.css" type="text/css" />
        <script type="text/javascript" src="includes/js/nprogress/nprogress.js"></script>
        <script type="text/javascript" src="includes/js/functions.js"></script>
        <link rel="stylesheet" href="includes/font-awesome/css/font-awesome.min.css" type="text/css" />
        <link rel="stylesheet" href="includes/css/passman.css" type="text/css" />
        <link rel="stylesheet" href="includes/js/select2/css/select2.min.css" type="text/css" />
        <script type="text/javascript" src="includes/js/select2/js/select2.full.min.js"></script>


        <script type="text/javascript" src="includes/libraries/Authentication/agses/agses.jquery.js"></script>
        <link rel="stylesheet" href="includes/libraries/Authentication/agses/agses.css" type="text/css" />';
// For ITEMS page, load specific CSS files for treeview
if (isset($_GET['page']) && $_GET['page'] == "items") {
    $htmlHeaders .= '
        <link rel="stylesheet" href="includes/js/jstree/themes/default/style.css" type="text/css" />
        <script type="text/javascript" src="includes/js/jstree/jstree.min.js"></script>
        <script type="text/javascript" src="includes/js/jstree/jquery.cookie.js"></script>
        <script type="text/javascript" src="includes/js/bgiframe/jquery.bgiframe.min.js"></script>
        <script type="text/javascript" src="includes/js/ckeditor/ckeditor.js"></script>
        <script type="text/javascript" src="includes/js/ckeditor/adapters/jquery.js"></script>
        <link rel="stylesheet" type="text/css" href="includes/js/multiselect/jquery.multiselect.css" />
        <script type="text/javascript" src="includes/js/multiselect/jquery.multiselect.min.js"></script>
        <link rel="stylesheet" type="text/css" href="includes/js/multiselect/jquery.multiselect.filter.css" />
        <script type="text/javascript" src="includes/js/multiselect/jquery.multiselect.filter.js"></script>
        <script type="text/javascript" src="includes/js/tinysort/jquery.tinysort.min.js"></script>
        <script type="text/javascript" src="includes/js/clipboard/clipboard.min.js"></script>
        <!--
        <link rel="stylesheet" href="includes/bootstrap/css/bootstrap.min.css" />
        <script src="includes/bootstrap/js/bootstrap.min.js"></script>
        -->
        <link rel="stylesheet" type="text/css" href="includes/css/items.css" />';
} else if (isset($_GET['page']) && $_GET['page'] == "manage_settings") {
    $htmlHeaders .= '
        <link rel="stylesheet" href="includes/js/toggles/css/toggles.css" />
        <link rel="stylesheet" href="includes/js/toggles/css/toggles-modern.css" />
        <script src="includes/js/toggles/toggles.min.js" type="text/javascript"></script>
        <script type="text/javascript" src="includes/libraries/Plupload/plupload.full.min.js"></script>
        <link rel="stylesheet" type="text/css" href="includes/js/multiselect/jquery.multiselect.css" />
        <script type="text/javascript" src="includes/js/multiselect/jquery.multiselect.min.js"></script>
        <link rel="stylesheet" type="text/css" href="includes/js/multiselect/jquery.multiselect.filter.css" />
        <script type="text/javascript" src="includes/js/multiselect/jquery.multiselect.filter.js"></script>';
} else if (isset($_GET['page']) && $_GET['page'] == "manage_main") {
    $htmlHeaders .= '
        <link rel="stylesheet" href="includes/js/toggles/css/toggles.css" />
        <link rel="stylesheet" href="includes/js/toggles/css/toggles-modern.css" />
        <script src="includes/js/toggles/toggles.min.js" type="text/javascript"></script>';
} else if (isset($_GET['page']) && ($_GET['page'] == "manage_users" || $_GET['page'] == "manage_folders")) {
    $htmlHeaders .= '
        <link rel="stylesheet" type="text/css" href="includes/js/datatable/css/jquery.dataTables.min.css" />
        <link rel="stylesheet" type="text/css" href="includes/js/datatable/css/dataTables.jqueryui.min.css" />
        <script type="text/javascript" src="includes/js/datatable/js/jquery.dataTables.min.js"></script>
        <script type="text/javascript" src="includes/js/datatable/js/dataTables.jqueryui.min.js"></script>
        <link rel="stylesheet" type="text/css" href="includes/js/multiselect/jquery.multiselect.css" />
        <script type="text/javascript" src="includes/js/multiselect/jquery.multiselect.min.js"></script>
        <link rel="stylesheet" type="text/css" href="includes/js/multiselect/jquery.multiselect.filter.css" />
        <script type="text/javascript" src="includes/js/multiselect/jquery.multiselect.filter.js"></script>';
} else if (isset($_GET['page']) && $_GET['page'] == "manage_views") {
    $htmlHeaders .= '
        <link rel="stylesheet" type="text/css" href="includes/js/datatable/css/jquery.dataTables.min.css" />
        <link rel="stylesheet" type="text/css" href="includes/js/datatable/css/dataTables.jqueryui.min.css" />
        <script type="text/javascript" src="includes/js/datatable/js/jquery.dataTables.js"></script>
        <script type="text/javascript" src="includes/js/datatable/js/dataTables.jqueryui.js"></script>';
} else if (isset($_GET['page']) && ($_GET['page'] == "find" || $_GET['page'] == "kb")) {
    $htmlHeaders .= '
        <script type="text/javascript" src="includes/js/ckeditor/ckeditor.js"></script>
        <script type="text/javascript" src="includes/js/ckeditor/adapters/jquery.js"></script>
        <link rel="stylesheet" type="text/css" href="includes/js/datatable/css/jquery.dataTables.min.css" />
        <link rel="stylesheet" type="text/css" href="includes/js/datatable/css/dataTables.jqueryui.min.css" />
        <script type="text/javascript" src="includes/js/datatable/js/jquery.dataTables.min.js"></script>
        <script type="text/javascript" src="includes/js/datatable/js/dataTables.jqueryui.min.js"></script>
        <link rel="stylesheet" type="text/css" href="includes/js/ui-multiselect/css/ui.multiselect.css" />
        <script type="text/javascript" src="includes/js/ui-multiselect/js/ui.multiselect.min.js"></script>
        <link rel="stylesheet" type="text/css" href="includes/css/kb.css" />';
} else if (isset($_GET['page']) && ($_GET['page'] == "suggestion")) {
    $htmlHeaders .= '
        <link rel="stylesheet" type="text/css" href="includes/css/kb.css" />
        <link rel="stylesheet" type="text/css" href="includes/js/datatable/css/jquery.dataTables.min.css" />
        <link rel="stylesheet" type="text/css" href="includes/js/datatable/css/dataTables.jqueryui.min.css" />
        <script type="text/javascript" src="includes/js/datatable/js/jquery.dataTables.min.js"></script>
        <script type="text/javascript" src="includes/js/datatable/js/dataTables.jqueryui.min.js"></script>';
} else if (!isset($_GET['page'])) {
    $htmlHeaders .= '
        <script type="text/javascript" src="includes/js/numeric/jquery.numeric.js"></script>';
    if (!empty($_SESSION['user_id']) && isset($_SESSION['user_id'])) {
        $htmlHeaders .= '
        <!--<script type="text/javascript" src="includes/libraries/Plupload/plupload.full.min.js"></script>-->';
    }
}
// Get Favicon
$htmlHeaders .= isset($_SESSION['settings']['favicon']) ? '
        <link rel="icon" href="'.$_SESSION['settings']['favicon'].'" type="image/vnd.microsoft.ico" />' : '';

$htmlHeaders .= '
<script type="text/javascript">
<!-- // --><![CDATA[

    //Menu actions
    function MenuAction(val)
    {
        NProgress.start();
        if (val == "deconnexion") {
            sessionStorage.clear();
            window.location.href = "logout.php"
        } else {
            $("#menu_action").val("action");
            if (val == "") document.location.href="index.php";
            else document.location.href="index.php?page="+val;
        }
    }

    function aes_encrypt(text)
    {
        return Aes.Ctr.encrypt(text, "'.$_SESSION['key'].'", 256);
    }


    function launchIdentify(isDuo, redirect, psk)
    {
        $("#connection_error").hide();
        if (redirect == undefined) redirect = ""; //Check if redirection
        // Check form data
        if (psk == 1 && $("#psk").val() == "") {
            $("#psk").addClass("ui-state-error");
            return false;
        } else if (psk == 1) {
            $("#psk").removeClass("ui-state-error");
        }
        if ($("#pw").val() == "") {
            $("#pw").addClass("ui-state-error");
            return false;
        }
        if ($("#login").val() == "") {
            $("#login").addClass("ui-state-error");
            return false;
        }
        // launch identification
        $("#pw, #login").removeClass("ui-state-error");
        $("#ajax_loader_connexion").show();

        //create random string
        var randomstring =CreateRandomString(10);

        var data = "";
        if ($("#ga_code").val() != undefined) {
            data = \', "GACode":"\'+sanitizeString($("#ga_code").val())+\'"\';
        }
        if ($("#psk").val() != undefined) {
            data = \', "psk":"\'+sanitizeString($("#psk").val())+\'"\'+
                \', "psk_confirm":"\'+sanitizeString($("#psk_confirm").val())+\'"\';
        }

        // get timezone
        var d = new Date();
        var TimezoneOffset = d.getTimezoneOffset()*60;

        data = \'{"login":"\'+sanitizeString($("#login").val())+\'" , "pw":"\'+sanitizeString($("#pw").val())+\'" , "duree_session":"\'+$("#duree_session").val()+\'" , "screenHeight":"\'+$("body").innerHeight()+\'" , "randomstring":"\'+randomstring+\'" , "TimezoneOffset":"\'+TimezoneOffset+\'"\'+data+\'}\';

        // Handle if DUOSecurity is enabled
        if (isDuo == 0 || (isDuo == 1 && $("#login").val() == "admin")) {
            identifyUser(redirect, psk, data, randomstring);
        } else {
            $("#duo_data").val(data);
            loadDuoDialog();
        }
    }

    //Identify user
    function identifyUser(redirect, psk, data, randomstring)
    {
        //send query
        $.post(
            "sources/identify.php",
            {
                type : "identify_user",
                data : prepareExchangedData(data, "encode", "'.$_SESSION["key"].'")
            },
            function(data) {
                if (data[0].value == randomstring) {
                    $("#connection_error").hide();
                    //redirection for admin is specific
                    if (data[0].user_admin == "1") window.location.href="index.php?page=manage_main";
                    else if (data[0].initial_url != "") window.location.href=data[0].initial_url;
                    else window.location.href="index.php?page=items";
                } else if (data[0].value == "user_is_locked") {
                    $("#connection_error").html("'.addslashes($LANG['account_is_locked']).'").show();
                } else if (data[0].value == "bad_psk") {
                    $("#ajax_loader_connexion").hide();
                    $("#connection_error").html("'.addslashes($LANG['bad_psk']).'").show();
                } else if (data[0].value == "bad_psk_confirmation") {
                    $("#ajax_loader_connexion").hide();
                    $("#connection_error").html("'.addslashes($LANG['bad_psk_confirmation']).'").show();
                } else if (data[0].value == "psk_required") {
                    $("#ajax_loader_connexion").hide();
                    $("#connection_error").html("' . addslashes($LANG['psk_required']) . '");
                    $("#connection_error, #connect_psk_confirm").show();
                } else if (data[0].value == "user_not_exists") {
                    $("#connection_error").html("'.addslashes($LANG['error_bad_credentials']).'").show();
                } else if (!isNaN(parseFloat(data[0].value)) && isFinite(data[0].value)) {
                    $("#connection_error").html("'.addslashes($LANG['login_attempts_on'])."&nbsp;".(@$_SESSION['settings']['nb_bad_authentication'] + 1).'").show();
                } else if (data[0].value == "error") {
                    $("#mysql_error_warning").html(data[0].text).show();
                    $("#div_mysql_error").show().dialog("open");
                } else if (data[0].value == "new_ldap_account_created") {
                    $("#connection_error").html("'.addslashes($LANG['reload_page_after_user_account_creation']).'").show().switchClass("ui-state-error", "ui-state-default");
                    setTimeout(
                        function (){
                            window.location.href="index.php"
                        },
                        2000
                    );
                } else if (data[0].value == "false_onetimepw") {
                    $("#connection_error").html("'.addslashes($LANG['bad_onetime_password']).'").show();
                } else if (data[0].pwd_attempts >=3 ||data[0].error == "bruteforce_wait") {
                    // now user needs to wait 10 secs before new passwd
                    $("#connection_error").html("'.addslashes($LANG['error_bad_credentials_more_than_3_times']).'").show();
                } else if (data[0].error == "bad_credentials") {
                    $("#connection_error").html("'.addslashes($LANG['error_bad_credentials']).'").show();
                } else if (data[0].error == "ga_code_wrong") {
                    $("#connection_error").html("'.addslashes($LANG['ga_bad_code']).'").show();
                } else if (data[0].value === "agses_error") {
                    $("#connection_error").html(data[0].error).show();
                } else if (data[0].error == "ga_temporary_code_wrong") {
                    $("#connection_error").html("'.addslashes($LANG['ga_bad_code']).'").show();
                } else if (data[0].error == "ga_temporary_code_correct") {
                    $("#ga_code").val("").focus();
                    $("#2fa_new_code_div").html(data[0].value+"<br />'.addslashes($LANG['ga_flash_qr_and_login']).'").show();
                } else if (data[0].value === "install_error") {
                    $("#connection_error").html(data[0].error).show();
                } else {
                    $("#connection_error").html("'.addslashes($LANG['error_bad_credentials']).'").show();
                }

                $("#ajax_loader_connexion").hide();
            },
            "json"
       );
    }

    function getGASynchronization()
    {
        if ($("#login").val() != "" && $("#pw").val() != "") {
            $("#ajax_loader_connexion").show();
            $("#connection_error").hide();
            $("#div_ga_url").hide();
            data = \'{"login":"\'+sanitizeString($("#login").val())+\'" ,\'+
                   \'"pw":"\'+sanitizeString($("#pw").val())+\'"}\';
            //send query
            $.post(
                "sources/main.queries.php",
                {
                    type : "ga_generate_qr",
                    data : prepareExchangedData(data, "encode", "'.$_SESSION["key"].'"),
                    send_email : "1"
                },
                function(data) {
                    if (data[0].error === "0") {
                        //$("#ga_qr").attr("src", data[0].ga_url);
                        $("#div_ga_url").show();
                    } else if (data[0].error === "not_allowed") {
                        $("#connection_error").html("'.addslashes($LANG['2FA_new_code_by_user_not_allowed']).'").show();
                        $("#div_ga_url").hide();
                    } else {
                        $("#connection_error").html("'.addslashes($LANG['index_bas_pw']).'").show();
                        $("#div_ga_url").hide();
                    }
                    $("#ajax_loader_connexion").hide();
                },
                "json"
            );
        } else {
            $("#connection_error").html("'.addslashes($LANG['ga_enter_credentials']).'").show();
        }
    }

    function send_user_new_temporary_ga_code() {
        $("#div_loading").show();

        data = \'{"login":"\'+sanitizeString($("#login").val())+\'" ,\'+
                   \'"pw":"\'+sanitizeString($("#pw").val())+\'"}\';

        $.post(
            "sources/main.queries.php",
            {
                type : "ga_generate_qr",
                data : prepareExchangedData(data, "encode", "'.$_SESSION["key"].'"),
                send_email : "1"
            },
            function(data) {
                if (data[0].error === "0") {
                    $("#div_dialog_message").html(data[0].msg).dialog("open");
                } else {

                }
                $("#div_loading").hide();
            },
            "json"
        );
    }

    /*
    * Manage generation of new password
    */
    function GenerateNewPassword(key, login)
    {
        $("#ajax_loader_send_mail").show();
        // prepare data
        data = \'{"login":"\'+sanitizeString(login)+\'" ,\'+
            \'"key":"\'+sanitizeString(key)+\'"}\';
        //send query
        $.post("sources/main.queries.php", {
                type : "generate_new_password",
                data : prepareExchangedData(data, "encode", "'.$_SESSION["key"].'")
            },
            function(data) {
                if (data == "done") {
                    window.location.href="index.php";
                } else {
                    $("#generate_new_pw_error").show().html(data);
                }
                $("#ajax_loader_send_mail").hide();
            }
       );
    }


    function loadProfileDialog()
    {
        $("#dialog_user_profil").dialog({
            open: function(event, ui) {
                $("#div_user_profil").load(
                    "'.$_SESSION['settings']['cpassman_url'].'/profile.php?key='.$_SESSION['key'].'", function(){}
                );
            },
            close: function() {
                // in case of user changed language then reload the current page
                if ($("#userlanguage_'.$_SESSION['user_id'].'").text() !== "'.$_SESSION['user_language'].'") {
                    //location.reload();
                    //document.location.href="index.php?language=" + $("#userlanguage_'.$_SESSION['user_id'].'").text();
                    var url = window.location.href;
                    if (url.indexOf("?") > -1) {
                        url += "&language=" + $("#userlanguage_'.$_SESSION['user_id'].'").text();
                    } else {
                        url += "?language=" + $("#userlanguage_'.$_SESSION['user_id'].'").text();
                    }
                    document.location.href = url;
                }
            }
        }).dialog("open");
    }

    /*
    * Clean disconnection of user for security reasons.
    *
       $(window).bind("beforeunload", function() {
        if ($("#menu_action").val() == "") {
            sessionStorage.clear();
            //Forces the disconnection of the user
            $.ajax({
                type: "POST",
                url : "error.php",
                data : "session=expired"
            });
        }
    });*/


    function displayItemNumber (item_id, tree_id)
    {
        if (window.location.href.indexOf("page=items") == -1) {
            location.replace("'.$_SESSION['settings']['cpassman_url'].'/index.php?page=items&group="+tree_id+"&id="+item_id);
        } else {
            $("#items_list").html("<ul class=\'liste_items\' id=\'full_items_list\'></ul>");
            AfficherDetailsItem(item_id);
            if (tree_id != $("#hid_cat").val()) {
                ListerItems(tree_id);
            }
        }
    }

    function refreshListLastSeenItems()
    {
        // refresh list of last items seen
        if ("'.$_SESSION["key"].'" == "") return false;
        $.post(
            "sources/main.queries.php",
            {
                type    : "refresh_list_items_seen",
                key        : "'.$_SESSION["key"].'"
            },
            function(data) {
                data = $.parseJSON(data);
                //check if format error
                if (data.error == "") {
                    if (data.text == null) {
                        $("#last_seen_items_list").html("<li>'.$LANG['none'].'</li>");
                    } else {
                        $("#last_seen_items_list").html(data.text);
                    }
                    // rebuild menu
                    $("#menu_last_seen_items").menu("refresh");
                    // show notification
                    if (data.existing_suggestions != 0) {
                        blink("#menu_button_suggestion", -1, 500, "ui-state-error");
                    }
                } else {
                    $("#main_info_box_text").html(data.error);
                    setTimeout(function(){$("#main_info_box").effect( "fade", "slow" );}, 1000);
                }
            }
        );
    }

    // DUO box - identification
    function loadDuoDialog()
    {
        // show dialog
        $("#dialog_duo").dialog({
            width: 600,
            height: 500,
            title: "DUO Security",
            open: function(event, ui) {
                $("#div_duo").load(
                    "'.$_SESSION['settings']['cpassman_url'].'/duo.load.php", function(){}
                );
            }
        }).dialog("open");
    }

    // DUO box - wait
    function loadDuoDialogWait()
    {
        $("#div_duo").html("<center><i class=\"fa fa-cog fa-spin fa-2x\"></i><br /><br />'.$LANG['duo_wait'].'</center>");
        $("#dialog_duo").dialog({
            width: 400,
            height: 250,
            title: "DUO Security - please wait ..."
        }).dialog("open");
    }

    function ChangeMyPass()
    {
        if ($("#new_pw").val() != "" && $("#new_pw").val() == $("#new_pw2").val()) {
            if (parseInt($("#pw_strength_value").val()) >= parseInt($("#user_pw_complexity").val())) {
                var data = "{\"new_pw\":\""+sanitizeString($("#new_pw").val())+"\"}";
                $.post(
                    "sources/main.queries.php",
                    {
                        type                : "change_pw",
                        change_pw_origine   : "first_change",
                        complexity          : $("#user_pw_complexity").val(),
                        key                 : "'.$_SESSION['key'].'",
                        data                : prepareExchangedData(data, "encode", "'.$_SESSION['key'].'>")
                    },
                    function(data) {
                        if (data[0].error == "complexity_level_not_reached") {
                            $("#new_pw, #new_pw2").val("");
                            $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<span>'.$LANG['error_complex_not_enought'].'></span>");
                        } else {
                            location.reload(true);
                        }
                    },
                    "json"
                );
            } else {
                $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("'.addslashes($LANG['error_complex_not_enought']).'");
            }
        } else {
            $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("'.addslashes($LANG['index_pw_error_identical']).'");
        }
    }

    /*
    **
    */
    function prepareMsgToDisplay(type, msg) {
        var html;
        if (type === "error") {
            html = "<i class=\'fa fa-warning fa-lg mi-red\'></i>&nbsp;";

            if (msg === "not_allowed") {
                html += "'.addslashes($LANG['error_not_allowed_to']).'";
            } else if (msg === "key_not_conform") {
                html += "Key verification for Query is not correct!";
            }
        } else if (type === "info") {
            html = "<i class=\'fa fa-info-circle fa-lg\'></i>&nbsp;";
            if (msg === "done") {
                html += "'.addslashes($LANG['alert_message_done']).'";
            }
        }

        return html;
    }

    $(function() {
        // AGSES authentication
        if ($("#axs_canvas").length > 0) {
            // show the agsesflicker
            $("#login, #agses_cardid").blur(function() {
                // exclude if login is empty or Admin
                if ($("#login").val() === "" || $("#login").val() === "admin") return false;

                $("#pw").attr("disabled", true);

                // special check for agses_cardid
                // must contain 12 numbers
                if ($("#agses_cardid").val() !== "") {
                    var agses_carid_error = "";
                    if ($("#agses_cardid").val().length !== 12) {
                        agses_carid_error = "Card ID MUST contain 12 numbers";
                    } else if (isNaN($("#agses_cardid").val())) {
                        agses_carid_error = "Card ID contains only numbers";
                    }

                    if (agses_carid_error !== "") {
                        $("#agses_cardid_div").after("<div class=\"ui-state-error ui-corner-all\" id=\"tmp_agses_div\" style=\"padding:5px; text-align:center; width:454px;\">ERROR: "+agses_carid_error+"</div>");
                        $("#tmp_agses_div").show(1).delay(2000).fadeOut(500);
                        $("#agses_cardid_div").show();
                        return false;
                    }
                }

                // show a wait message
                $("#agses_cardid_div").after("<div class=\"ui-state-focus ui-corner-all\" id=\"tmp_agses_wait_div\" style=\"padding:5px; text-align:center; width:454px;\"><i class=\"fa fa-cog fa-spin fa-1x\"></i>&nbsp;'.addslashes($LANG['admin_agses_wait']).'</div>");

                // send query
                $.post(
                    "sources/identify.php",
                    {
                        type :    "identify_user_with_agses",
                        login:    sanitizeString($("#login").val()),
                        cardid:   sanitizeString($("#agses_cardid").val()),
                        key:      "'.$_SESSION['key'].'"
                    },
                    function(data) {
                        // init
                        $("#pw").attr("disabled", false);
                        $("#agses_flickercode_div").hide();
                        $("#user_pwd").text("'.addslashes($LANG['index_password']).'");

                        if (data[0].error !== "" && data[0].agses_message === "") {
                        // an error occured during query
                            if (data[0].error === "no_agses_info") {
                                data[0].error = "'.addslashes($LANG['agses_error_missing_api_data']).'";
                            }
                            $("#agses_cardid_div").after("<div class=\"ui-state-error ui-corner-all\" id=\"tmp_agses_div\" style=\"padding:5px; text-align:center; width:454px;\">ERROR: "+data[0].error+"</div>");
                            $("#tmp_agses_div").show(1).delay(3000).fadeOut(1000);

                        } else if (data[0].agses_message !== "" && (data[0].agses_message.indexOf("ERROR ") === 0 || data[0].agses_status === "no_user_card_id")) {
                        // Agses returned an error
                            $("#agses_cardid_div").show();
                            $("#agses_cardid").focus();

                            $("#agses_cardid_div").after("<div class=\"ui-state-error ui-corner-all\" id=\"tmp_agses_div\" style=\"padding:5px; text-align:center; width:454px;\">ERROR: "+data[0].agses_message+"</div>");
                            $("#tmp_agses_div").show(1).delay(3000).fadeOut(1000);

                        } else if (data[0].agses_message !== "") {
                        // show agses flicker
                            $("#agses_cardid_div").hide();
                            // check if already generated
                            if ($("#axs_canvas").data("agsesFlicker") !== undefined) {
                                $("#axs_canvas").agsesFlicker({
                                    "message": data[0].agses_message,
                                });
                            } else {
                                // generateflickercode
                                $("#axs_canvas").agsesInit({
                                    "message": data[0].agses_message,
                                });
                            }
                            $("#agses_flickercode_div").show();
                            $("#user_pwd").text("'.addslashes($LANG['index_agses_key']).'");

                        } else if (data[0].agses_message === "") {
                        // user needs to enter his user card id
                            $("#agses_cardid_div").show();
                            $("#user_pwd").text("'.addslashes($LANG['index_password']).'");
                            $("#agses_cardid").focus();

                        } else {
                        // something wrong
                        // typically the user login does not exist
                            $("#agses_flickercode_div, #agses_cardid_div").hide();
                            $("#user_pwd").text("'.addslashes($LANG['index_password']).'");
                            $("#agses_cardid_div").after("<div class=\"ui-state-error ui-corner-all\" id=\"tmp_agses_div\" style=\"padding:5px; text-align:center; width:454px;\">ERROR: "+data[0].error+"</div>");
                            $("#tmp_agses_div").show(1).delay(3000).fadeOut(1000);
                        }

                        // remove wait message
                        $("#tmp_agses_wait_div").remove();
                    },
                    "json"
                );
            })
        }

        // manage countdown for session expiration
        countdown();

        // load DUO login
        if ($("#duo_sig_response").val() != "") {
            $("#login").val($("#duo_login").val());

            // checking that response is corresponding to user credentials
            $.post(
                "sources/identify.php",
                {
                    type :             "identify_duo_user_check",
                    login:             sanitizeString($("#login").val()),
                    sig_response:     $("#duo_sig_response").val()
                },
                function(data) {
                    var ret = data[0].resp.split("|");
                    if (ret[0] === "ERR") {
                        $("#div_duo").html("ERROR " + ret[1]);
                    } else {
                        // finally launch identification process inside Teampass.
                        loadDuoDialogWait();

                        $.post(
                            "sources/identify.php",
                            {
                                type :     "identify_user",
                                data :     prepareExchangedData($("#duo_data").val(), "encode", "'.$_SESSION['key'].'")
                            },
                            function(data) {
                                $("#connection_error").hide();
                                //redirection for admin is specific
                                if (data[0].user_admin == "1") window.location.href="index.php?page=manage_main";
                                else $( "#duo_form" ).submit();
                            },
                            "json"
                        );
                    }
                },
                "json"
            );
        }

        $(".button, .btn").button();

        //TOOLTIPS
        $("#main *, #footer *, #icon_last_items *, #top *, button, .tip").tooltipster({
            maxWidth: 400,
            contentAsHTML: true,
            multiple: true
        });
        $("#user_session").val(sessionStorage.password);

        $(".menu").menu({
            icon: {},
            position: { my: "left top", at: "left bottom" },
            _closeOnDocumentClick: function( event ) {
                return true;
            }
        });

        //Display Tabs
        $("#item_edit_tabs, #item_tabs").tabs();

        //BUTTON
        $("#but_identify_user").hover(
            function() {
                $(this).addClass("ui-state-hover");
            },
            function() {
                $(this).removeClass("ui-state-hover");
            }
       ).mousedown(function() {
            $(this).addClass("ui-state-active");
        })
        .mouseup(function() {
                $(this).removeClass("ui-state-active");
        });

        //END SESSION DIALOG BOX
        $("#div_fin_session").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 400,
            height: 150,
            title: "'.$LANG['index_alarm'].'",
            buttons: {
                "'.$LANG['index_add_one_hour'].'": function() {
                    IncreaseSessionTime("'.$LANG['alert_message_done'].'", "'.$LANG['please_wait'].'");
                    $("#div_fin_session").hide();
                    $("#countdown").css("color","white");
                    $(this).dialog("close");
                }
            }
        });

        //WARNING FOR QUERY ERROR
        $("#div_mysql_error").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 700,
            height: 150,
            title: "'.$LANG['error_mysql'].'",
            buttons: {
                "'.$LANG['ok'].'": function() {
                    $(this).dialog("close");
                }
            }
        });

        //DIALOG FOR USER PROFILE
        $("#dialog_user_profil").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 500,
            height: 520,
            title: "'.$LANG['user_profile_dialogbox_menu'].'",
            buttons: {
                "'.$LANG['close'].'": function() {
                    $(this).dialog("close");
                }
            },
            close: function() {
                $("#dialog_user_profil").dialog("option", "height", 430);
                $("#div_user_profil").html("<i class=\'fa fa-cog fa-spin fa-2x\'></i>&nbsp;<b>'.$LANG['please_wait'].'</b>");
            }
        });

        //MESSAGE DIALOG
        $("#div_dialog_message").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 400,
            height: 150,
            title: "'.$LANG['div_dialog_message_title'].'",
            buttons: {
                "'.$LANG['ok'].'": function() {
                    $("#div_dialog_message").dialog("close");
                }
            },
            beforeClose: function(){
                $("#div_dialog_message_text").html("");
            },
            close: function() {
                $("#div_dialog_message").dialog("close");
            }
        });

        // DIALOG BOX FOR SETTING PERSONAL SALTKEY
        $("#div_set_personal_saltkey").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 500,
            height: 190,
            title: "'.$LANG['home_personal_saltkey_label'].'",
            open: function( event, ui ) {
                $("#input_personal_saltkey").val("'.addslashes(str_replace("&quot;", '"', $_SESSION['my_sk'])).'");
            },
            buttons: {
                "'.$LANG['save_button'].'": function() {
                    LoadingPage();
                    var data = "{\"psk\":\""+sanitizeString($("#input_personal_saltkey").val())+"\"}";
                    //Send query
                    $.post(
                        "sources/main.queries.php",
                        {
                           type    : "store_personal_saltkey",
                           data    : prepareExchangedData(data, "encode", "'.$_SESSION['key'].'")
                        },
                        function(data) {
                            data = prepareExchangedData(data , "decode", "'.$_SESSION['key'].'");
                            if (data.error !== "") {
                                // display error
                                $("#main_info_box_text").html(data.error);
                                $("#main_info_box").show().position({
                                    my: "center",
                                    at: "center top+75",
                                    of: "#top"
                                });
                                setTimeout(function(){$("#main_info_box").effect( "fade", "slow" );}, 5000);
                            } else {
                                $("#main_info_box_text").html("'.$LANG['alert_message_done'].' '.$txt['alert_page_will_reload'].'");
                                $("#main_info_box").show().position({
                                    my: "center",
                                    at: "center top+75",
                                    of: "#top"
                                });
                                setTimeout(function(){$("#main_info_box").effect( "fade", "slow" );}, 1000);
                                location.reload();
                            }
                            LoadingPage();
                            $("#input_personal_saltkey").val("");
                        }
                    );
                    $(this).dialog("close");
                },
                "'.$LANG['cancel_button'].'": function() {
                    $(this).dialog("close");
                }
            }
        });

        // DIALOG BOX FOR CHANGING PERSONAL SALTKEY
        $("#div_change_personal_saltkey").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 450,
            height: 310,
            title: "'.$LANG['menu_title_new_personal_saltkey'].'",
            open: function() {
                $("#new_personal_saltkey").val("");
                $("#old_personal_saltkey").val("'.addslashes(str_replace("&quot;", '"', $_SESSION['my_sk'])).'");
            },
            buttons: {
                "'.$LANG['ok'].'": function() {
                    $("#div_change_personal_saltkey_wait").show();
                    var data_to_share = "{\"sk\":\"" + sanitizeString($("#new_personal_saltkey").val()) + "\", \"old_sk\":\"" + sanitizeString($("#old_personal_saltkey").val()) + "\"}";

                    $("#div_change_personal_saltkey_wait_progress").html("  0%");

                    //Send query
                    $.post(
                        "sources/main.queries.php",
                        {
                            type            : "change_personal_saltkey",
                            data_to_share   : prepareExchangedData(data_to_share, "encode", "'.$_SESSION['key'].'"),
                            key             : "'.$_SESSION['key'].'"
                        },
                        function(data) {
                            data = prepareExchangedData(data , "decode", "'.$_SESSION['key'].'");
                            if (data.error == "no") {
                                changePersonalSaltKey(data_to_share, data.list, data.nb_total);
                            } else {

                            }
                            /*
                            $("#div_change_personal_saltkey_wait").hide();
                            $("#div_change_personal_saltkey").dialog("close");
                            */
                        }
                   );
                },
                "'.$LANG['cancel_button'].'": function() {
                    $(this).dialog("close");
                }
            },
            close: function() {
                $("#div_change_personal_saltkey_wait").hide();
            }
        });

        // DIALOG FOR PSK
        $("#psk_confirm").focusout(function() {
            if ($("#psk_confirm").val() != $("#psk").val()) {
                $("#but_identify_user").prop("disabled", true);
                $("#psk, #psk_confirm").addClass("ui-state-error");
            } else {
                $("#but_identify_user").prop("disabled", false);
                $("#psk, #psk_confirm").removeClass("ui-state-error");
            }
        });

        // DIALOG BOX FOR ASKING PASSWORD
        $("#div_forgot_pw").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 400,
            height: 250,
            title: "'.$LANG['forgot_my_pw'].'",
            buttons: {
                "'.$LANG['send'].'": function() {
                    $("#div_forgot_pw_alert").html("");
                    $("#div_forgot_pw_status").show();
                    $.post(
                        "sources/main.queries.php",
                        {
                            type    : "send_pw_by_email",
                            email    : $("#forgot_pw_email").val(),
                            login    : $("#forgot_pw_login").val()
                        },
                        function(data) {
                            $("#div_forgot_pw_status").hide();
                            if (data[0].error != "") {
                                $("#div_forgot_pw_alert").html(data[0].message).addClass("ui-state-error").show();
                            } else {
                                $("#div_forgot_pw_alert").html("");
                                $("#div_dialog_message_text").html(data[0].message);
                                $("#div_forgot_pw").dialog("close");
                                $("#div_dialog_message").dialog("open");
                            }
                        },
                        "json"
                    );
                },
                "'.$LANG['cancel_button'].'": function() {
                    $("#div_forgot_pw_alert").html("");
                    $("#forgot_pw_email").val("");
                    $(this).dialog("close");
                }
            }
        });

        //PREPARE MAIN MENU
        $("#main_menu button, #personal_menu_actions button").button();

        //PREPARE LANGUGAGE DROPDOWN
            $(".dropdown dt").click(function() {
                $(".dropdown dd ul").toggle();
            });

            $(".dropdown dd ul li a").click(function() {
                var text = $(this).html();
                $(".dropdown dt a span").html(text);
                $(".dropdown dd ul").hide();
                $("#result").html("Selected value is: " + getSelectedValue("sample"));
            });

            function getSelectedValue(id)
            {
                return $("#" + id).find("dt a span.value").html();
            }

            $(document).bind("click", function(e) {
                var $clicked = $(e.target);
                if (! $clicked.parents().hasClass("dropdown"))
                    $(".dropdown dd ul").hide();
            });
        //END

        $.ajaxSetup({
            error: function(jqXHR, exception) {
                if (jqXHR.status === 0) {
                    $("#div_dialog_message").show();
                    $("#div_dialog_message_text").html("Not connect. Verify Network.");
                } else if (jqXHR.status == 404) {
                    $("#div_dialog_message").show();
                    $("#div_dialog_message_text").html("Requested page not found. [404]");
                } else if (jqXHR.status == 500) {
                    $("#div_dialog_message").show();
                    $("#div_dialog_message_text").html("Internal Server Error [500].");
                } else if (exception === "parsererror") {
                    $("#div_dialog_message").show();
                    $("#div_dialog_message_text").html("Requested JSON parse failed.");
                } else if (exception === "timeout") {
                    $("#div_dialog_message").show();
                    $("#div_dialog_message_text").html("Time out error.");
                } else if (exception === "abort") {
                    $("#div_dialog_message").show();
                    $("#div_dialog_message_text").html("Ajax request aborted.");
                } else {
                    $("#div_dialog_message").show();
                    $("#div_dialog_message_text").html("Uncaught Error.<br />" + jqXHR.responseText);
                }
            }
        });


        //Password meter for item creation
        $("#new_pw").simplePassMeter({
            "requirements": {},
            "container": "#pw_strength",
            "defaultText" : "'.$LANG['index_pw_level_txt'].'",
            "ratings": [
                {"minScore": 0,
                    "className": "meterFail",
                    "text": "'.$LANG['complex_level0'].'"
                },
                {"minScore": 25,
                    "className": "meterWarn",
                    "text": "'.$LANG['complex_level1'].'"
                },
                {"minScore": 50,
                    "className": "meterWarn",
                    "text": "'.$LANG['complex_level2'].'"
                },
                {"minScore": 60,
                    "className": "meterGood",
                    "text": "'.$LANG['complex_level3'].'"
                },
                {"minScore": 70,
                    "className": "meterGood",
                    "text": "'.$LANG['complex_level4'].'"
                },
                {"minScore": 80,
                    "className": "meterExcel",
                    "text": "'.$LANG['complex_level5'].'"
                },
                {"minScore": 90,
                    "className": "meterExcel",
                    "text": "'.$LANG['complex_level6'].'"
                }
            ]
        });
        $("#new_pw").bind({
            "score.simplePassMeter" : function(jQEvent, score) {
                $("#pw_strength_value").val(score);
            }
        }).change({
            "score.simplePassMeter" : function(jQEvent, score) {
        $("#pw_strength_value").val(score);
    }
        });

        // get list of last items
        refreshListLastSeenItems();

        // prevent usage of symbols in Personal saltkey
        $(".text_without_symbols").bind("keydown", function (event) {
            switch (event.keyCode) {
                case 8:  // Backspace
                case 9:  // Tab
                case 13: // Enter
                case 37: // Left
                case 38: // Up
                case 39: // Right
                case 40: // Down
                break;
                default:
                var regex = new RegExp("^[a-zA-Z0-9.,/#&$@()%*]+$");
                var key = event.key;
                if (!regex.test(key)) {
                    $("#set_personal_saltkey_warning").html("'.addslashes($LANG['character_not_allowed']).'").stop(true,true).show().fadeOut(1000);
                    event.preventDefault();
                    return false;
                }
                if (key !== "Alt" && key !== "Control" && key !== "Shift") $("#set_personal_saltkey_last_letter").html(key).stop(true,true).show().fadeOut(1400);
                break;
            }
        }).bind("paste",function(e){
            $("#set_personal_saltkey_warning").html("'.addslashes($LANG['error_not_allowed_to']).'").stop(true,true).show().fadeOut(1000);
            e.preventDefault();
        });

        setTimeout(function() { NProgress.done(); $(".fade").removeClass("out"); }, 1000);
    });';

if (isset($_GET['page']) && $_GET['page'] == "find") {
    // JAVASCRIPT FOR FIND PAGE
    $htmlHeaders .= '
    ';
} else if (isset($_GET['page']) && $_GET['page'] == "manage_main") {
    // JAVASCRIPT FOR ADMIN PAGE
    $htmlHeaders .= '
    //Function loads informations from cpassman FTP
    function LoadCPMInfo()
    {
        //Lauchn ajax query
        $.post(
            "sources/admin.queries.php",
            {
               type    : "cpm_status"
            },
            function(data) {
                if (data[0].error == "connection") {
                    $("#CPM_infos").html("Server connection is impossible ... check your Internet/firewall configuration");
                } else if (data[0].error == "conf_block") {
                    $("#CPM_infos").html("No display available. Feature disabled in configuration.");
                } else {
                    $("#CPM_infos").html("<span style=\'font-weight:bold;\'>'.$LANG['admin_info'].'</span>"+data[0].output+"</ul>");
                }
            },
            "json"
       );
    }

    /*
    * get statistics values
    */
    function showStatsValues() {
        // send query
        $.post(
                "sources/admin.queries.php",
            {
                type   : "get_values_for_statistics",
                key    : "'.$_SESSION['key'].'"
            },
            function(data) {
                //decrypt data
                try {
                    data = prepareExchangedData(data , "decode", "'.$_SESSION['key'].'");
                } catch (e) {
                    // error
                    $("#message_box").html("An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />"+data).show().fadeOut(4000);

                    return;
                }
                if (data.error === "") {
                    $("#value_items").html(data.stat_items);
                    var ips = "";
                    $.each(data.stat_country, function( index, value ) {
                      if (value > 0) {
                        if (ips === "") ips = index+":"+value;
                        else ips += ";"+index+":"+value;
                      }
                    });
                    $("#value_country").html(ips);
                    $("#value_folders").html(data.stat_folders);
                    $("#value_items_shared").html(data.stat_items_shared);
                    $("#value_folders_shared").html(data.stat_folders_shared);
                    $("#value_php").html(data.stat_phpversion);
                    $("#value_users").html(data.stat_users);
                    $("#value_admin").html(data.stat_admins);
                    $("#value_manager").html(data.stat_managers);
                    $("#value_ro").html(data.stat_ro);
                    $("#value_teampassv").html(data.stat_teampassversion);
                    $("#value_duo").html(data.stat_duo);
                    $("#value_kb").html(data.stat_kb);
                    $("#value_pf").html(data.pf);
                    $("#value_ldap").html(data.stat_ldap);
                    $("#value_agses").html(data.stat_agses);
                    $("#value_suggestion").html(data.stat_suggestion);
                    $("#value_api").html(data.stat_api);
                    $("#value_customfields").html(data.stat_customfields);
                    $("#value_syslog").html(data.stat_syslog);
                    $("#value_2fa").html(data.stat_2fa);
                    $("#value_https").html(data.stat_stricthttps);
                    $("#value_mysql").html(data.stat_mysqlversion);
                    $("#value_pf").html(data.stat_pf);
                    $("#value_fav").html(data.stat_fav);
                    var langs = "";
                    $.each(data.stat_languages, function( index, value ) {
                      if (value > 0) {
                        if (langs === "") langs = index+":"+value;
                        else langs += ";"+index+":"+value;
                      }
                    });
                    $("#value_languages").html(langs);
                }
            }
        );
    }

    //Load function on page load
    $(function() {
        $("#but_save_send_stat").button();

        // calculate statistic values
        showStatsValues();

        if ($("#setting_send_stats").val() !== "1") {
            // show anonymous stats tab
            $("#tabs").tabs({active: 1});
        } else {
            // show communication mean tab
            $("#tabs").tabs({active: 0});
        }

        $(".toggle").toggles({
            drag: true, // allow dragging the toggle between positions
            click: true, // allow clicking on the toggle
            text: {
                on: "'.$LANG['yes'].'", // text for the ON position
                off: "'.$LANG['no'].'" // and off
            },
            on: true, // is the toggle ON on init
            easing: "swing", // animation transition easing function
            animate: 250, // animation time (ms)
            width: 50, // width used if not set in css
            height: 20, // height if not set in css
            type: "compact" // if this is set to select then the select style toggle will be used
        });
        $(".toggle").on("toggle", function(e, active) {
            if (active) {
                $("#send_stats_input").val(1);
            } else {
                $("#send_stats_input").val(0);
            }
        });

        $("#but_save_send_stat").click(function() {
            var list = "";
            $(".stat_option:checked").each(function() {
                list += $(this).attr("id")+";";
            });
            // store in DB
            $.post(
                "sources/admin.queries.php",
                {
                    type    : "save_sending_statistics",
                    list    : list,
                    status  : $("#send_stats_input").val(),
                    key     : "'.$_SESSION['key'].'"
                },
                function(data) {
                    if (data[0].error === "" && data[0].result === "Done") {
                        $("#but_save_send_stat").val("'.$LANG['alert_message_done'].'");
                        setTimeout(
                            function() {
                                $("#but_save_send_stat").val("'.$LANG['save_statistics_choice'].'");
                            },
                            2000
                        );

                        // if enabled, then send stats right now
                        if ($("#send_stats_input").val() === "1") {
                            // send statistics
                            $.post(
                                "sources/main.queries.php",
                                {
                                    type : "sending_statistics",
                                    key  : "'.$_SESSION['key'].'"
                                }
                            );
                        }
                    }
                },
                "json"
            );
        });

        // manage checkbox
        $(".stat_option").change(function(){
            var myid = $(this).attr("id").split("_");
            if (this.checked) {
                $("#value_"+myid[1]).show();
            } else {
                $("#value_"+myid[1]).hide();
            }
        });

        $("#cb_select_all").click(function() {
            if ($("#cb_select_all").prop("checked")) {
                $(".stat_option").prop("checked", true);
            } else {
                $(".stat_option").prop("checked", false);
            }
        });

        LoadCPMInfo();
    });';
} else if (isset($_GET['page']) && $_GET['page'] == "favourites") {
    // JAVASCRIPT FOR FAVOURITES PAGE
    $htmlHeaders .= '
    $(function() {
        // DIALOG BOX FOR DELETING FAVOURITE
        $("#div_delete_fav").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 300,
            height: 160,
            title: "'.$LANG['item_menu_del_from_fav'].'",
            buttons: {
                "'.$LANG['index_change_pw_confirmation'].'": function() {
                    //Lauchn ajax query
                    $.post(
                        "sources/favourites.queries.php",
                        {
                           type    : "del_fav",
                           id    : $("#detele_fav_id").val()
                        },
                        function(data) {
                            $("#row-" + $("#detele_fav_id").val()).remove();
                            $("#div_delete_fav").dialog("close");
                        }
                   );
                },
                "'.$LANG['cancel_button'].'": function() {
                    $(this).dialog("close");
                }
            }
        });
    })

    function prepare_delete_fav(id)
    {
        $("#detele_fav_id").val(id);
        OpenDialog("div_delete_fav");
    }';
} else if (isset($_GET['page']) && isset($_SESSION['user_id'])) {
    // simulate a CRON activity (only 4 secs after page loading)
    // check for existing suggestions / changes
    $htmlHeaders .= '
    setTimeout(
        function() {
            $.post(
                "sources/main.queries.php",
                {
                    type    : "is_existings_suggestions",
                    key     : "'.$_SESSION['key'].'"
                },
                function(data) {
                    //check if format error
                    if (data[0].error === "" && parseInt(data[0].count) > 0) {
                        // incase we need to show the menu button
                        if (data[0].show_sug_in_menu === "1") {
                            $("#menu_suggestion_position")
                                .append("<a class=\"btn btn-default\" href=\"#\"><i class=\"fa fa-lightbulb-o fa-2x tip\" id=\"menu_icon_suggestions\" title=\"'.$LANG['suggestion_menu'].'\"></i></a>")
                                .click (function() {
                                    MenuAction("suggestion");
                                });
                            $(".btn").button();
                            $(".tip").tooltipster({multiple: true});
                        }

                        $("#menu_icon_suggestions").addClass("mi-red");

                        setInterval(function(){blink()}, 700);
                        function blink() {
                            $("#menu_icon_suggestions").fadeTo(100, 0.1).fadeTo(200, 1.0);
                        }
                    }
                },
                "json"
            );
        },
        4000
    );';
}

$htmlHeaders .= '
// ]]>
</script>';
