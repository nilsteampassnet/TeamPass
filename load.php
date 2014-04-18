<?php
/**
 *
 * @file          load.php
 * @author        Nils Laumaillé
 * @version       2.1.19
 * @copyright     (c) 2009-2014 Nils Laumaillé
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

// Common elements
$htmlHeaders = '
        <link rel="stylesheet" href="includes/css/passman.css" type="text/css" />
        <link rel="stylesheet" href="includes/js/jquery-ui/css/'.$k['jquery-ui-theme'].'/jquery-ui-'.$k['jquery-ui-version'].'.custom.min.css" type="text/css" />

        <script type="text/javascript" src="includes/js/functions.js"></script>

        <script type="text/javascript" src="includes/js/jquery-ui/js/jquery-'.$k['jquery-version'].'.js"></script>
        <script type="text/javascript" src="includes/js/jquery-ui/js/jquery-ui-'.$k['jquery-ui-version'].'.custom.min.js"></script>

        <script language="JavaScript" type="text/javascript" src="includes/js/simplePassMeter/simplePassMeter.js"></script>

        <script type="text/javascript" src="includes/libraries/Encryption/Crypt/aes.min.js"></script>';
// For ITEMS page, load specific CSS files for treeview
if (isset($_GET['page']) && $_GET['page'] == "items") {
    $htmlHeaders .= '
        <link rel="stylesheet" type="text/css" href="includes/css/items.css" />
        <link rel="stylesheet" href="includes/js/jstree/themes/default/style.css" type="text/css" />
        <script type="text/javascript" src="includes/js/jstree/jstree.min.js"></script>
        <script type="text/javascript" src="includes/js/jstree/jquery.cookie.js"></script>

        <script type="text/javascript" src="includes/js/bgiframe/jquery.bgiframe.min.js"></script>

        <script type="text/javascript" src="includes/libraries/Plupload/plupload.full.js"></script>

        <script type="text/javascript" src="includes/js/ckeditor/ckeditor.js"></script>
        <script type="text/javascript" src="includes/js/ckeditor/dialog-patch.js"></script>
        <script type="text/javascript" src="includes/js/ckeditor/adapters/jquery.js"></script>

        <link rel="stylesheet" type="text/css" href="includes/js/multiselect/jquery.multiselect.css" />
        <script type="text/javascript" src="includes/js/multiselect/jquery.multiselect.min.js"></script>

        <script type="text/javascript" src="includes/js/tinysort/jquery.tinysort.min.js"></script>
        <script type="text/javascript" src="includes/js/zeroclipboard/ZeroClipboard.js"></script>';
} else if (isset($_GET['page']) && $_GET['page'] == "manage_settings") {
    $htmlHeaders .= '
        <script type="text/javascript" src="includes/libraries/Plupload/plupload.full.js"></script>';
} else if (isset($_GET['page']) && ($_GET['page'] == "manage_users" || $_GET['page'] == "manage_folders")) {
    $htmlHeaders .= '
        <script src="includes/js/jeditable/jquery.jeditable.js" type="text/javascript"></script>';
} else if (isset($_GET['page']) && $_GET['page'] == "manage_views") {
    $htmlHeaders .= '
        <link rel="stylesheet" type="text/css" href="includes/js/datatable/jquery.dataTablesUI.css" />
        <script type="text/javascript" src="includes/js/datatable/jquery.dataTables.min.js"></script>';
} else if (isset($_GET['page']) && ($_GET['page'] == "find" || $_GET['page'] == "kb")) {
    $htmlHeaders .= '
        <link rel="stylesheet" type="text/css" href="includes/css/kb.css" />

        <script type="text/javascript" src="includes/js/ckeditor/ckeditor.js"></script>
        <script type="text/javascript" src="includes/js/ckeditor/dialog-patch.js"></script>
        <script type="text/javascript" src="includes/js/ckeditor/adapters/jquery.js"></script>

        <link rel="stylesheet" type="text/css" href="includes/js/datatable/jquery.dataTablesUI.css" />
        <script type="text/javascript" src="includes/js/datatable/jquery.dataTables.min.js"></script>

        <link rel="stylesheet" type="text/css" href="includes/js/ui-multiselect/css/ui.multiselect.css" />
        <script type="text/javascript" src="includes/js/ui-multiselect/js/ui.multiselect.min.js"></script>';
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
    //Menu actions
    function MenuAction(val)
    {
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

    //Identify user
    function identifyUser(redirect, psk)
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

        data = \'{"login":"\'+sanitizeString($("#login").val())+\'" , "pw":"\'+sanitizeString($("#pw").val())+\'" , "duree_session":"\'+$("#duree_session").val()+\'" , "screenHeight":"\'+$("body").innerHeight()+\'" , "randomstring":"\'+randomstring+\'"\'+data+\'}\';

        //send query
        $.post(
            "sources/main.queries.php",
            {
                type : "identify_user",
                data : prepareExchangedData(data, "encode")
            },
            function(data) {
                if (data[0].value == randomstring) {
                    $("#connection_error").hide();
                    //redirection for admin is specific
                    if (data[0].user_admin == "1") window.location.href="index.php?page=manage_main";
                    else if (data[0].initial_url != "") window.location.href=data[0].initial_url;
                    else window.location.href="index.php";
                } else if (data[0].value == "user_is_locked") {
                    $("#connection_error").html("'.$txt['account_is_locked'].'").show();
                } else if (data[0].value == "bad_psk") {
                    $("#ajax_loader_connexion").hide();
                    $("#connection_error").html("'.$txt['bad_psk'].'").show();
                } else if (data[0].value == "bad_psk_confirmation") {
                    $("#ajax_loader_connexion").hide();
                    $("#connection_error").html("'.$txt['bad_psk_confirmation'].'").show();
                } else if (data[0].value == "psk_required") {
                    $("#ajax_loader_connexion").hide();
                    $("#connection_error").html("'.$txt['psk_required'].'");
                    $("#connection_error, #connect_psk_confirm").show();
                } else if (!isNaN(parseFloat(data[0].value)) && isFinite(data[0].value)) {
                    $("#connection_error").html(data + "'.$txt['login_attempts_on'].(@$_SESSION['settings']['nb_bad_authentication'] + 1).'").show();
                } else if (data[0].value == "error") {
                    $("#mysql_error_warning").html(data[0].text);
                    $("#div_mysql_error").show().dialog("open");
                } else if (data[0].value == "false_onetimepw") {
                    $("#connection_error").html("'.$txt['bad_onetime_password'].'").show();
                } else if (data[0].error == "bad_credentials") {
                	$("#connection_error").html("'.$txt['index_bas_pw'].'").show();
                } else if (data[0].error == "ga_code_wrong") {
                	$("#connection_error").html("'.$txt['ga_bad_code'].'").show();
                } else {
                    $("#connection_error").html("'.$txt['index_bas_pw'].'").show();
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
	                data : prepareExchangedData(data, "encode")
	            },
	            function(data) {
	            	if (data[0].error == "0") {
						$("#ga_qr").attr("src", data[0].ga_url);
                	    $("#div_ga_url").show();
	            	} else {
						$("#connection_error").html("'.$txt['index_bas_pw'].'").show();
                	    $("#div_ga_url").hide();
	            	}
                    $("#ajax_loader_connexion").hide();
	            },
	            "json"
	        );
    	} else {
    		$("#connection_error").html("'.$txt['ga_enter_credentials'].'").show();
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
    		    data : prepareExchangedData(data, "encode")
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
                data : prepareExchangedData(data, "encode")
            },
            function(data) {
            	if (data == "done") {
	                $("#language").val(lang);
	                document.location.href="index.php";
	            }
            }
       );
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

    $(function() {
        //TOOLTIPS
        $("#main *, #footer *, #icon_last_items *, #top *, button, .tip").tooltip();
        $("#user_session").val(sessionStorage.password);

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
            title: "'.$txt['index_alarm'].'",
            buttons: {
                "'.$txt['index_add_one_hour'].'": function() {
                    IncreaseSessionTime();
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
            title: "'.$txt['error_mysql'].'",
            buttons: {
                "'.$txt['ok'].'": function() {
                    $(this).dialog("close");
                }
            }
        });

        //MESSAGE DIALOG
        $("#div_dialog_message").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 300,
            height: 150,
            title: "'.$txt['div_dialog_message_title'].'",
            buttons: {
                "'.$txt['ok'].'": function() {
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
    });';

if (!isset($_GET['page'])) {
    $htmlHeaders .= '
    $(function() {
/*
        $("#login").focusout(function() {
            if ($("#login").val() != "" && $("#login").val() != "admin") {
                $("#login_check_wait").show();
                // check if login exists
                $.post(
                    "sources/main.queries.php",
                    {
                        type    : "check_login_exists",
                        userId    : $("#login").val()
                    },
                    function(data) {
                        $("#login_check_wait").hide();
                        if (data[0].login == "") {
                            $("#login").addClass("ui-state-error");
                        } else {
                            $("#login").removeClass("ui-state-error");
                        }
                        if (data[0].psk == "") {
                            $("#connect_psk_confirm").show();
                        }
                    },
                    "json"
                );
            }
        });
*/
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
            width: 300,
            height: 250,
            title: "'.$txt['forgot_my_pw'].'",
            buttons: {
                "'.$txt['send'].'": function() {
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
                "'.$txt['cancel_button'].'": function() {
                    $("#div_forgot_pw_alert").html("");
                    $("#forgot_pw_email").val("");
                    $(this).dialog("close");
                }
            }
        });
    });';
}

if (!isset($_GET['page']) && isset($_SESSION['key']) && $zim == 1) {
    $htmlHeaders .= '
    $(function() {
        //build nice buttonset
        $("#radio_import_type, #connect_ldap_mode").buttonset();
        $("#personal_sk, #change_personal_sk, #reset_personal_sk, #pickfiles_csv, #pickfiles_kp").button();

        if ($("#personal_saltkey_set").val() != 1) {
            $("#change_personal_sk").button("disable");
        }

        //Clear text when clicking on buttonset
        $(".import_radio").click(function() {
            $("#import_status").html("");
        });

        // DIALOG BOX FOR CHANGING PASSWORD
        $("#div_changer_mdp").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 300,
            height: 250,
            title: "'.$txt['index_change_pw'].'",
            open: function( event, ui ) {
                $("#change_pwd_complexPw").html("'.
                    $txt['complex_asked'].' : '.$pwComplexity[$_SESSION['user_pw_complexity']][1]
                .'");
                $("#change_pwd_error").hide();
            },
            buttons: {
                "'.$txt['index_change_pw_button'].'": function() {
                    if ($("#new_pw").val() != "" && $("#new_pw").val() == $("#new_pw2").val()) {
                        if ($("#pw_strength_value").val() >= $("#user_pw_complexity").val()) {
                            var data = "{\"new_pw\":\""+sanitizeString($("#new_pw").val())+"\"}";
                            $.post(
                                "sources/main.queries.php",
                                {
                                    type    : "change_pw",
                                    change_pw_origine    : "user_change",
                                    complexity:    $("#pw_strength_value").val(),
                                    data :    aes_encrypt(data)
                                },
                                function(data) {
                                    if (data[0].error == "already_used") {
                                        $("#new_pw, #new_pw2").val("");
                                        $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<span>'.$txt['pw_used'].'</span>");
                                    } else {
                                        document.main_form.submit();
                                    }
                                },
                                "json"
                           );
                        } else {
                            $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("'.$txt['error_complex_not_enought'].'");
                        }
                    } else {
                        $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("'.$txt['index_pw_error_identical'].'");
                    }
                },
                "'.$txt['cancel_button'].'": function() {
                    $("#change_pwd_error").removeClass("ui-state-error ui-corner-all").html("");
                    $("#new_pw, #new_pw2").val("");
                    $(this).dialog("close");
                }
            }
        });

        // DIALOG BOX FOR CHANGING PERSONAL SALTKEY
        $("#div_change_personal_saltkey").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 400,
            height: 230,
            title: "'.$txt['menu_title_new_personal_saltkey'].'",
            buttons: {
                "'.$txt['ok'].'": function() {
                    $("#div_change_personal_saltkey_wait").show();
                    //Send query
                    $.post(
                        "sources/main.queries.php",
                        {
                           type    : "change_personal_saltkey",
                           sk    : encodeURIComponent($("#new_personal_saltkey").val())
                        },
                        function(data) {
                            $("#div_change_personal_saltkey_wait").hide();
                            $("#div_change_personal_saltkey").dialog("close");
                        }
                   );
                },
                "'.$txt['cancel_button'].'": function() {
                    $(this).dialog("close");
                }
            }
        });

        // DIALOG BOX FOR DELETING PERSONAL SALTKEY
        $("#div_reset_personal_sk").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 400,
            height: 200,
            title: "'.$txt['menu_title_new_personal_saltkey'].'",
            buttons: {
                "'.$txt['ok'].'": function() {
                    $("#div_loading").show();

                    //Send query
                    $.post(
                        "sources/main.queries.php",
                        {
                           type    : "reset_personal_saltkey",
                           sk    : encodeURIComponent($("#reset_personal_saltkey").val())
                        },
                        function(data) {
                            $("#div_loading").hide();
                            $("#div_reset_personal_sk").dialog("close");
                        }
                   );
                },
                "'.$txt['cancel_button'].'": function() {
                    $(this).dialog("close");
                }
            }
        });

        // DIALOG BOX FOR CSV IMPORT
        $("#div_import_from_csv").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 600,
            height: 500,
            title: "'.$txt['import_csv_menu_title'].'",
            buttons: {
                "'.$txt['import_button'].'": function() {
                    if ($(\'#radio1\').attr(\'checked\')) ImportItemsFromCSV();
                    else $(this).dialog("close");
                },
                "'.$txt['cancel_button'].'": function() {
                    $("#import_status").html("");
                    $(this).dialog("close");
                }
            },
            close: function() {
                $("#csv_import_options").hide();
            }
        });

        // DIALOG BOX FOR PRINT OUT ITEMS
        $("#div_print_out").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 400,
            height: 450,
            title: "'.$txt['print_out_menu_title'].'",
            buttons: {
                "'.$txt['print'].'": function() {
                    //Get list of selected folders
                    var ids = "";
                    $("#selected_folders :selected").each(function(i, selected) {
                        if (ids == "") ids = $(selected).val();
                        else ids = ids + ";" + $(selected).val();
                    });
                    $("#div_print_out_wait").show();
                    $("#print_out_error").hide();
                    $("#download_link").html("");

                    // Get PDF encryption password and make sure it is set
                    if (($("#pdf_password").val() == "") && ($("input[name=\"export_format\"]:checked").val() == "pdf")) {
                        $("#print_out_error").show().html("'.$txt['pdf_password_warning'].'").attr("class","ui-state-error");
                        $("#div_print_out_wait").hide();
                        return;
                    }

                    // export format?
                    var export_format = "";
                    if ($("input[name=\"export_format\"]:checked").val() == "pdf") export_format = "export_to_pdf_format";
                    else if ($("input[name=\"export_format\"]:checked").val() == "csv") export_format = "export_to_csv_format";
                    else if ($("input[name=\"export_format\"]:checked").val() == "html") export_format = "export_to_html_format";

                    if (export_format == "export_to_html_format" && $("#pdf_password").val() == "") {
                    	$("#print_out_error").show().html("'.$txt['pdf_password_warning'].'").attr("class","ui-state-error");
                        $("#div_print_out_wait").hide();
                        return;
                    }

                    //Send query
                    $.post(
                        "sources/export.queries.php",
                        {
                            type    : export_format,
                            ids        : ids,
                            pdf_password : $("#pdf_password").val()
                        },
                        function(data) {
                            $("#download_link").html(data[0].text);
                            $("#div_print_out_wait").hide();
                        },
                        "json"
                   );
                },
                "'.$txt['cancel_button'].'": function() {
                    $(this).dialog("close");
                }
            }
        });

        //Password meter
        if ($("#new_pw").length) {
            $("#new_pw").simplePassMeter({
                "requirements": {},
                  "container": "#pw_strength",
                  "defaultText" : "'.$txt['index_pw_level_txt'].'",
                "ratings": [
                {"minScore": 0,
                    "className": "meterFail",
                    "text": "'.$txt['complex_level0'].'"
                },
                {"minScore": 25,
                    "className": "meterWarn",
                    "text": "'.$txt['complex_level1'].'"
                },
                {"minScore": 50,
                    "className": "meterWarn",
                    "text": "'.$txt['complex_level2'].'"
                },
                {"minScore": 60,
                    "className": "meterGood",
                    "text": "'.$txt['complex_level3'].'"
                },
                {"minScore": 70,
                    "className": "meterGood",
                    "text": "'.$txt['complex_level4'].'"
                },
                {"minScore": 80,
                    "className": "meterExcel",
                    "text": "'.$txt['complex_level5'].'"
                },
                {"minScore": 90,
                    "className": "meterExcel",
                    "text": "'.$txt['complex_level6'].'"
                }
                ]
            });
        }
        $("#new_pw").bind({
            "score.simplePassMeter" : function(jQEvent, score) {
                $("#pw_strength_value").val(score);
            }
        });


        if ($("#offline_password").length) {
            $("#offline_password").simplePassMeter({
                "requirements": {},
                  "container": "#offline_pw_strength",
                  "defaultText" : "'.$txt['index_pw_level_txt'].'",
                "ratings": [
                {"minScore": 0,
                    "className": "meterFail",
                    "text": "'.$txt['complex_level0'].'"
                },
                {"minScore": 25,
                    "className": "meterWarn",
                    "text": "'.$txt['complex_level1'].'"
                },
                {"minScore": 50,
                    "className": "meterWarn",
                    "text": "'.$txt['complex_level2'].'"
                },
                {"minScore": 60,
                    "className": "meterGood",
                    "text": "'.$txt['complex_level3'].'"
                },
                {"minScore": 70,
                    "className": "meterGood",
                    "text": "'.$txt['complex_level4'].'"
                },
                {"minScore": 80,
                    "className": "meterExcel",
                    "text": "'.$txt['complex_level5'].'"
                },
                {"minScore": 90,
                    "className": "meterExcel",
                    "text": "'.$txt['complex_level6'].'"
                }
                ]
            });
        }
        $("#offline_password").bind({
            "score.simplePassMeter" : function(jQEvent, score) {
                $("#offline_pw_strength_value").val(score);
            }
        });


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
       );';

    if (!empty($_SESSION['user_id']) && isset($_SESSION['user_id'])) {
        $htmlHeaders .= '
        // CSV IMPORT
        var uploader_csv = new plupload.Uploader({
    		runtimes : "gears,html5,flash,silverlight,browserplus",
    		browse_button : "pickfiles_csv",
    		container : "upload_container_csv",
    		max_file_size : "10mb",
            chunk_size : "1mb",
    		unique_names : true,
            dragdrop : true,
            multiple_queues : false,
            multi_selection : false,
            max_file_count : 1,
    		url : "sources/upload/upload.files.php",
    		flash_swf_url : "includes/libraries/Plupload/plupload.flash.swf",
    		silverlight_xap_url : "includes/libraries/Plupload/plupload.silverlight.xap",
    		filters : [
    			{title : "CSV files", extensions : "csv"}
    		],
    		init: {
    		    FilesAdded: function(up, files) {
                    up.start();
                },
                BeforeUpload: function (up, file) {
                    $("#import_status_ajax_loader").show();
                    up.settings.multipart_params = {
                        "PHPSESSID":"'.$_SESSION['user_id'].'",
                        "csvFile":file.name,
                        "type_upload":"import_items_from_csv"
                    };
                },
                UploadComplete: function(up, files) {
                    $.each(files, function(i, file) {
                        ImportCSV(file.name);
                        $("#csv_import_options").show();
                    });
                    $("#import_status_ajax_loader").hide();
                }
    		}
    	});

    	// Show runtime status
        uploader_csv.bind("Init", function(up, params) {
    		$("#plupload_runtime").html("Upload feature: runtime " + params.runtime).removeClass(\'ui-state-error\');
    		$("#upload_enabled").val("1");
    	});

        // Uploader options
    	uploader_csv.bind("UploadProgress", function(up, file) {
    		$("#" + file.id + " b").html(file.percent + "%");
    	});
    	uploader_csv.bind("Error", function(up, err) {
    		$("#filelist_csv").html("<div class=\'ui-state-error ui-corner-all\'>Error: " + err.code +
    			", Message: " + err.message +
    			(err.file ? ", File: " + err.file.name : "") +
    			"</div>"
    		);
    		up.refresh(); // Reposition Flash/Silverlight
    	});
    	uploader_csv.bind("+", function(up, file) {
    		$("#" + file.id + " b").html("100%");
    	});

    	// Load CSV click
    	$("#uploadfiles_csv").click(function(e) {
    		uploader_csv.start();
    		e.preventDefault();
    	});
    	uploader_csv.init();

        // KEYPASS IMPORT
        var uploader_kp = new plupload.Uploader({
    		runtimes : "gears,html5,flash,silverlight,browserplus",
    		browse_button : "pickfiles_kp",
    		container : "upload_container_kp",
    		max_file_size : "10mb",
            chunk_size : "1mb",
    		unique_names : true,
            dragdrop : true,
            multiple_queues : false,
            multi_selection : false,
            max_file_count : 1,
    		url : "sources/upload/upload.files.php",
    		flash_swf_url : "includes/libraries/Plupload/plupload.flash.swf",
    		silverlight_xap_url : "includes/libraries/Plupload/plupload.silverlight.xap",
    		filters : [
    			{title : "Keypass files", extensions : "xml"}
    		],
    		init: {
    		    FilesAdded: function(up, files) {
                    up.start();
                },
                BeforeUpload: function (up, file) {
                    $("#import_status_ajax_loader").show();
                    up.settings.multipart_params = {
                        "PHPSESSID":"'.$_SESSION['user_id'].'",
                        "xmlFile":file.name,
                        "type_upload":"import_items_from_keypass"
                    };
                },
                UploadComplete: function(up, files) {
                    ImportKEEPASS(files[0].name);
                }
    		}
    	});
        // Uploader options
    	uploader_kp.bind("UploadProgress", function(up, file) {
    		$("#" + file.id + " b").html(file.percent + "%");
    	});
    	uploader_kp.bind("Error", function(up, err) {
    		$("#filelist_kp").html("<div class=\'ui-state-error ui-corner-all\'>Error: " + err.code +
    			", Message: " + err.message +
    			(err.file ? ", File: " + err.file.name : "") +
    			"</div>"
    		);
    		up.refresh(); // Reposition Flash/Silverlight
    	});
    	uploader_kp.bind("+", function(up, file) {
    		$("#" + file.id + " b").html("100%");
    	});

    	// Load CSV click
    	$("#uploadfiles_kp").click(function(e) {
    		uploader_kp.start();
    		e.preventDefault();
    	});
    	uploader_kp.init();';
    }

    $htmlHeaders .= '
    })

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
		                complexity            :    "",
		                data                 :    aes_encrypt(data)
		            },
		            function(data) {
		                document.main_form.submit();
		            },
		            "json"
				);
			} else {
				$("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("'.$txt['error_complex_not_enought'].'");
			}
		} else {
			$("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("'.$txt['index_pw_error_identical'].'");
		}
    }

    //Permits to upload passwords from KEEPASS file
    function ImportKEEPASS(file)
    {
        //clean divs
        $("#import_status").html("");
        $("#import_from_file_info").html("").hide();

        $("#div_loading").show();

        //check if file has good format
        $.post(
            "sources/import.queries.php",
            {
               type        : "import_file_format_keepass",
               file        : file,
               destination        : $("#import_keepass_items_to").val()
            },
            function(data) {
                $("#div_loading").hide();
                $("#import_status").html(data[0].message);
                $("#import_status_ajax_loader").hide();
            },
            "json"
       );
    }

    //Permits to upload passwords from CSV file
    function ImportCSV(file)
    {
        $("#import_status").html("");
        $("#import_from_file_info").html("").hide();
        $.post(
            "sources/import.queries.php",
            {
               type        : "import_file_format_csv",
               file        : file
            },
            function(data) {
                if (data[0].error == "bad_structure") {
                    $("#import_from_file_info").html("'.$txt['import_error_no_read_possible'].'").show();
                } else {
                    $("#import_status").html(data[0].output);
                    $("#item_all_selection").click(function() {
                        if ($("#item_all_selection").prop("checked")) {
                            $("input[class=\'item_checkbox\']:not([disabled=\'disabled\'])").attr("checked", true);
                        } else {
                            $("input[class=\'item_checkbox\']:not([disabled=\'disabled\'])").removeAttr("checked");
                        }
                    });
                }
                $("#import_status_ajax_loader").hide();
            },
            "json"
       );
    }

    //get list of items checked by user
    function ImportItemsFromCSV()
    {
        var items = "";

        //Get data checked
        $("input[class=item_checkbox]:checked").each(function() {
            var elem = $(this).attr("id").split("-");
            if (items == "") items = $("#item_to_import_values-"+elem[1]).val();
            else items = items + "@_#sep#_@" + $("#item_to_import_values-"+elem[1]).val();

        });
        $("#import_status_ajax_loader").show();

        //Lauchn ajax query that will insert items into DB
        $.post(
            "sources/import.queries.php",
            {
               type        : "import_items",
               folder    : $("#import_items_to").val(),
               data        : aes_encrypt(items),
               import_csv_anyone_can_modify    : $("#import_csv_anyone_can_modify").prop("checked"),
               import_csv_anyone_can_modify_in_role    : $("#import_csv_anyone_can_modify_in_role").prop("checked")
            },
            function(data) {
                //after inserted, disable the checkbox in order to prevent against new insert
                var elem = data[0].items.split(";");
                for (var i=0; i<elem.length; i++) {
                    $("#item_to_import-"+elem[i]).attr("disabled", true);
                    $("#item_text-"+elem[i]).css("textDecoration", "line-through");
                }

                $("#import_status_ajax_loader").hide();
            },
            "json"
       );
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
                    $("#selected_folders").append("<option value=\'"+reccord+"\'>"+data[reccord]+"</option>");
                }
            }
       );

        //Open dialogbox
        $("#div_print_out").dialog("open");
    }

    // OFF-LINE mode -> select folder and key
    function offlineModeLaunch()
    {
    	$("#offline_mode_selected_folders").empty();

        //Lauchn ajax query that will build the select list
        $.post(
            "sources/main.queries.php",
            {
               type        : "get_folders_list",
               div_id    : "offline_mode_selected_folders"
            },
            function(data) {
                data = $.parseJSON(data);
                for (reccord in data) {
                    $("#offline_mode_selected_folders").append("<option value=\'"+reccord+"\'>"+data[reccord]+"</option>");
                }
            }
       );

        //Open dialogbox
        $("#div_offline_mode").dialog("open");
    }

    //Store PSK
    function StorePersonalSK()
    {
        //Lauchn ajax query
        $.post(
            "sources/main.queries.php",
            {
               type    : "store_personal_saltkey",
               sk    : aes_encrypt("{\"psk\":\""+sanitizeString($("#input_personal_saltkey").val())+"\"}")
            },
            function(data) {
                if ($("#input_personal_saltkey").val() != "") {
                    $("#div_dialog_message_text").html("<div style=\'font-size:16px;\'><span class=\'ui-icon ui-icon-info\' style=\'float: left; margin-right: .3em;\'></span>'.$txt['alert_message_done'].'</div>");
                    $("#change_personal_sk").button("enable");
                    $("#div_dialog_message").dialog("open");
                }
            }
       );
    }';
} else if (isset($_GET['page']) && $_GET['page'] == "find") {
    // JAVASCRIPT FOR FIND PAGE
    $htmlHeaders .= '
    $(function() {
        //Launch the datatables pluggin
        oTable = $("#t_items").dataTable({
            "aaSorting": [[ 1, "asc" ]],
            "sPaginationType": "full_numbers",
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": "sources/find.queries.php",
            "bJQueryUI": true,
            "oLanguage": {
                "sUrl": "includes/language/datatables.'.$_SESSION['user_language'].'.txt"
            },
            "fnInitComplete": function() {
                $("#find_page input").focus();
            }
        });
    });';
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
                    $("#CPM_infos").html("<span style=\'font-weight:bold;\'>'.$txt['admin_info'].'</span>"+data[0].output+"</ul>");
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
            title: "'.$txt['item_menu_del_from_fav'].'",
            buttons: {
                "'.$txt['index_change_pw_confirmation'].'": function() {
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
                "'.$txt['cancel_button'].'": function() {
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
