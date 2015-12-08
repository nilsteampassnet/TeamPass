<?php
/**
 *
 * @file          load.php
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

// Common elements
$htmlHeaders = '
        <link rel="stylesheet" href="includes/css/passman.css" type="text/css" />
        <link rel="stylesheet" href="includes/js/jquery-ui/jquery-ui.min.css" type="text/css" />
        <link rel="stylesheet" href="includes/js/jquery-ui/jquery-ui.structure.min.css" type="text/css" />
        <link rel="stylesheet" href="includes/js/jquery-ui/jquery-ui.theme.min.css" type="text/css" />
        <link rel="stylesheet" href="includes/font-awesome/css/font-awesome.min.css">

        <script type="text/javascript" src="includes/js/functions.js"></script>

        <script type="text/javascript" src="includes/js/jquery-ui/external/jquery/jquery.js"></script>
        <script type="text/javascript" src="includes/js/jquery-ui/jquery-ui.min.js"></script>

        <link rel="stylesheet" href="includes/js/tooltipster/css/tooltipster.css">
        <script type="text/javascript" src="includes/js/tooltipster/js/jquery.tooltipster.min.js"></script>

        <script language="JavaScript" type="text/javascript" src="includes/js/simplePassMeter/simplePassMeter.js"></script>
        <script src="includes/js/jeditable/jquery.jeditable.js" type="text/javascript"></script>
        <script type="text/javascript" src="includes/libraries/Encryption/Crypt/aes.min.js"></script>

        <script type="text/javascript" src="includes/libraries/Plupload/plupload.full.js"></script>

        <link rel="stylesheet" href="includes/js/nprogress/nprogress.css">
        <script type="text/javascript" src="includes/js/nprogress/nprogress.js"></script>';

// For ITEMS page, load specific CSS files for treeview
if (isset($_GET['page']) && $_GET['page'] == "items") {
    $htmlHeaders .= '
        <link rel="stylesheet" type="text/css" href="includes/css/items.css" />
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
        <link rel="stylesheet" href="includes/bootstrap/css/bootstrap.min.css">
        <script src="includes/bootstrap/js/bootstrap.min.js"></script>
        -->';
} else if (isset($_GET['page']) && $_GET['page'] == "manage_settings") {
    $htmlHeaders .= '
        <script type="text/javascript" src="includes/libraries/Plupload/plupload.full.js"></script>';
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
        <link rel="stylesheet" type="text/css" href="includes/css/kb.css" />

        <script type="text/javascript" src="includes/js/ckeditor/ckeditor.js"></script>
        <script type="text/javascript" src="includes/js/ckeditor/adapters/jquery.js"></script>

        <link rel="stylesheet" type="text/css" href="includes/js/datatable/css/jquery.dataTables.min.css" />
        <link rel="stylesheet" type="text/css" href="includes/js/datatable/css/dataTables.jqueryui.min.css" />
        <script type="text/javascript" src="includes/js/datatable/js/jquery.dataTables.min.js"></script>
        <script type="text/javascript" src="includes/js/datatable/js/dataTables.jqueryui.min.js"></script>

        <link rel="stylesheet" type="text/css" href="includes/js/ui-multiselect/css/ui.multiselect.css" />
        <script type="text/javascript" src="includes/js/ui-multiselect/js/ui.multiselect.min.js"></script>';
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
        <script type="text/javascript" src="includes/libraries/Plupload/plupload.full.js"></script>';
    }
}
// Get Favicon
$htmlHeaders .= isset($_SESSION['settings']['favicon']) ? '
        <link rel="icon" href="'.$_SESSION['settings']['favicon'].'" type="image/vnd.microsoft.ico" />' : '';

$htmlHeaders .= '
<script type="text/javascript">
<!-- // --><![CDATA[
    // ShowHide
    function showHideDiv (divId)
    {
        if ($("#"+divId).is(":visible")) {
            $("#"+divId).hide();
        } else {
            $("#"+divId).show();
        }
    }


    //Menu actions
    function MenuAction(val)
    {
        NProgress.start();
        if (val == "deconnexion") {
            sessionStorage.clear();
            $("#menu_action").val(val);
            document.main_form.submit();
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
        var randomstring = "";
        var chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz".split("");
        for (var i = 0; i < 10; i++) {
            randomstring += chars[Math.floor(Math.random() * chars.length)];
        }

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
                    else window.location.href="index.php";
                } else if (data[0].value == "user_is_locked") {
                    $("#connection_error").html("'.$LANG['account_is_locked'].'").show();
                } else if (data[0].value == "bad_psk") {
                    $("#ajax_loader_connexion").hide();
                    $("#connection_error").html("'.$LANG['bad_psk'].'").show();
                } else if (data[0].value == "bad_psk_confirmation") {
                    $("#ajax_loader_connexion").hide();
                    $("#connection_error").html("'.$LANG['bad_psk_confirmation'].'").show();
                } else if (data[0].value == "psk_required") {
                    $("#ajax_loader_connexion").hide();
                    $("#connection_error").html("' . $LANG['psk_required'] . '");
                    $("#connection_error, #connect_psk_confirm").show();
                } else if (data[0].value == "user_not_exists") {
                    $("#connection_error").html("'.$LANG['user_not_exists'].'").show();
                    console.log("'.$LANG['user_not_exists'].'");
                } else if (!isNaN(parseFloat(data[0].value)) && isFinite(data[0].value)) {
                    $("#connection_error").html(data + "'.$LANG['login_attempts_on'].(@$_SESSION['settings']['nb_bad_authentication'] + 1).'").show();
                } else if (data[0].value == "error") {
                    $("#mysql_error_warning").html(data[0].text).show();
                    $("#div_mysql_error").show().dialog("open");
                } else if (data[0].value == "false_onetimepw") {
                    $("#connection_error").html("'.$LANG['bad_onetime_password'].'").show();
                } else if (data[0].error == "bad_credentials") {
                    $("#connection_error").html("'.$LANG['index_bas_pw'].'").show();
                } else if (data[0].error == "ga_code_wrong") {
                    $("#connection_error").html("'.$LANG['ga_bad_code'].'").show();
                } else {
                    $("#connection_error").html("'.$LANG['index_bas_pw'].'").show();
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
                    if (data[0].error == "0") {
                        //$("#ga_qr").attr("src", data[0].ga_url);
                        $("#div_ga_url").show();
                    } else {
                        $("#connection_error").html("'.$LANG['index_bas_pw'].'").show();
                        $("#div_ga_url").hide();
                    }
                    $("#ajax_loader_connexion").hide();
                },
                "json"
            );
        } else {
            $("#connection_error").html("'.$LANG['ga_enter_credentials'].'").show();
        }
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
                type :    "generate_new_password",
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



    /**
    * Creates a random string
    * @returns {string} A random string
    */
    function randomString() {
        var chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz";
        var string_length = 128;
        var randomstring = "";
        for (var i=0; i<string_length; i++) {
            var rnum = Math.floor(Math.random() * chars.length);
            randomstring += chars.substring(rnum,rnum+1);
        }
        //randomstring += cursor.x;
        //randomstring += cursor.y;
        return randomstring;
    }

    function OpenDiv(div)
    {
        $("#"+div).slideToggle("slow");
    }

    function OpenDialogBox(id)
    {
        $("#"+id).dialog("open");
    }

    //Change language using icon flags
    function ChangeLanguage(lang)
    {
        $("#language").val(lang);
        data = \'{"lang":"\'+sanitizeString(lang)+\'"}\';
        $.post(
            "sources/main.queries.php",
            {
                type : "change_user_language",
                data : prepareExchangedData(data, "encode", "'.$_SESSION["key"].'")
            },
            function(data) {
                if (data == "done") {
                    document.location.href="index.php?language="+lang;
                }
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
                //check if format error
                if (data[0].error == "") {
                    $("#last_seen_items_list").html(data[0].text);
                    // rebuild menu
                    $("#menu_last_seen_items").menu("refresh");
					// show notification
					if (data[0].existing_suggestions != 0) {
						blink("#menu_button_suggestion", -1, 500, "ui-state-error");
					}
                } else {
                    $("#main_info_box_text").html(data[0].error);
                    setTimeout(function(){$("#main_info_box").effect( "fade", "slow" );}, 1000);
                }
            },
            "json"
        );
    }

    // DUO box - identification
    function loadDuoDialog()
    {
		/*
		// save data connection
		$.post(
            "sources/identify.php",
            {
                type   : "store_data_in_cookie",
                data   : prepareExchangedData($("#duo_data").val(), "encode", "'.$_SESSION['key'].'>"),
                key    : "'.$_SESSION['key'].'"
            },
			function(data) {
				if (data[0].error == "something_wrong") {
					
				}
			},
			"json"
        );
		*/
		
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
                        change_pw_origine    : "first_change",
                        complexity            :    $("#user_pw_complexity").val(),
                        data                 :    prepareExchangedData(data, "encode", "'.$_SESSION['key'].'>")
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
                $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("'.$LANG['error_complex_not_enought'].'");
            }
        } else {
            $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("'.$LANG['index_pw_error_identical'].'");
        }
    }

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
                    data                 :    prepareExchangedData(data, "encode", "'.$_SESSION['key'].'>")
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
            $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("'.$LANG['error_complex_not_enought'].'");
        }
    } else {
        $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("'.$LANG['index_pw_error_identical'].'");
    }
}

    $(function() {
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

        $(".button").button();
        
        //TOOLTIPS
        $("#main *, #footer *, #icon_last_items *, #top *, button, .tip").tooltipster({
            maxWidth: 400,
            contentAsHTML: true
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
            height: 400,
            title: "'.$LANG['user_profile_dialogbox_menu'].'",
            buttons: {
                "'.$LANG['close'].'": function() {
                    $(this).dialog("close");
                }
            },
            close: function() {
                $("#dialog_user_profil").dialog("option", "height", 400);
                $("#div_user_profil").html("<i class=\'fa fa-cog fa-spin fa-2x\'></i>&nbsp;<b>'.$LANG['please_wait'].'</b>");
            }
        });

        //MESSAGE DIALOG
        $("#div_dialog_message").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 300,
            height: 150,
            title: "'.$LANG['div_dialog_message_title'].'",
            buttons: {
                "'.$LANG['ok'].'": function() {
                    $(this).dialog("close");
                }
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
				console.log("'.addslashes(str_replace("&quot;", '"', $_SESSION['my_sk'])).'");
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
                            LoadingPage();
                            if ($("#input_personal_saltkey").val() != "") {
                                $("#main_info_box_text").html("'.$LANG['alert_message_done'].' '.$txt['alert_page_will_reload'].'");
                                $("#main_info_box").show().position({
                                    my: "center",
                                    at: "center top+75",
                                    of: "#top"
                                });
                                setTimeout(function(){$("#main_info_box").effect( "fade", "slow" );}, 1000);
                                location.reload();
                            }
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
    //Load function on page load
    $(function() {
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
            height: 100,
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
                            document.form_favourites.submit();
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
        OpenDialogBox("div_delete_fav");
    }';
}

$htmlHeaders .= '
// ]]>
</script>';
