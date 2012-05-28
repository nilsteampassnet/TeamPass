<?php
/**
 * @file 		load.php
 * @author		Nils Laumaillé
 * @version 	2.1
 * @copyright 	(c) 2009-2011 Nils Laumaillé
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

if (!isset($_SESSION['CPM'] ) || $_SESSION['CPM'] != 1)
	die('Hacking attempt...');


//Common elements
$htmlHeaders = '
        <link rel="stylesheet" href="includes/css/passman.css" type="text/css" />
        <link rel="stylesheet" href="includes/jquery-ui/css/'.$k['jquery-ui-theme'].'/jquery-ui-'.$k['jquery-ui-version'].'.custom.css" type="text/css" />

        <script type="text/javascript" src="includes/js/functions.js"></script>

        <script type="text/javascript" src="includes/jquery-ui/js/jquery-'.$k['jquery-version'].'.min.js"></script>
        <script type="text/javascript" src="includes/jquery-ui/js/jquery-ui-'.$k['jquery-ui-version'].'.custom.min.js"></script>

        <script language="JavaScript" type="text/javascript" src="includes/js/jquery.tooltip.js"></script>

		<script language="JavaScript" type="text/javascript" src="includes/libraries/simplePassMeter/simplePassMeter.js"></script>

        <script type="text/javascript" src="includes/libraries/crypt/aes.min.js"></script>';




//For ITEMS page, load specific CSS files for treeview
if ( isset($_GET['page']) && $_GET['page'] == "items")
    $htmlHeaders .= '
		<link rel="stylesheet" type="text/css" href="includes/css/items.css" />
        <script type="text/javascript" src="includes/libraries/jstree/jquery.jstree.min.js"></script>
        <script type="text/javascript" src="includes/libraries/jstree/jquery.cookie.js"></script>

        <script type="text/javascript" src="includes/js/jquery.bgiframe.min.js"></script>
        <script type="text/javascript" src="includes/js/jquery.autocomplete.pack.js"></script>

        <link rel="stylesheet" type="text/css" href="includes/libraries/uploadify/uploadify.css" />
        <script type="text/javascript" src="includes/libraries/uploadify/jquery.uploadify.v2.1.4.min.js"></script>
        <script type="text/javascript" src="includes/libraries/uploadify/swfobject.js"></script>

		<script type="text/javascript" src="includes/libraries/ckeditor/ckeditor.js"></script>
		<script type="text/javascript" src="includes/libraries/ckeditor/dialog-patch.js"></script>
		<script type="text/javascript" src="includes/libraries/ckeditor/adapters/jquery.js"></script>

		<link rel="stylesheet" type="text/css" href="includes/libraries/multiselect/jquery.multiselect.css" />
        <script type="text/javascript" src="includes/libraries/multiselect/jquery.multiselect.min.js"></script>

        <script type="text/javascript" src="includes/libraries/tinysort/jquery.tinysort.min.js"></script>
        <script type="text/javascript" src="includes/libraries/zeroclipboard/ZeroClipboard.js"></script>';

else
if ( isset($_GET['page']) && $_GET['page'] == "manage_settings")
    $htmlHeaders .= '
        <link rel="stylesheet" type="text/css" href="includes/libraries/uploadify/uploadify.css" />
        <script type="text/javascript" src="includes/libraries/uploadify/jquery.uploadify.v2.1.4.min.js"></script>
        <script type="text/javascript" src="includes/libraries/uploadify/swfobject.js"></script>';

else
if ( isset($_GET['page']) && ( $_GET['page'] == "manage_users" ||$_GET['page'] == "manage_folders") )
    $htmlHeaders .= '
        <script src="includes/js/jquery.jeditable.js" type="text/javascript"></script>';

else
if ( isset($_GET['page']) && $_GET['page'] == "manage_views" )
    $htmlHeaders .= '
        <link rel="stylesheet" type="text/css" href="includes/libraries/datatable/jquery.dataTablesUI.css" />
		<script type="text/javascript" src="includes/libraries/datatable/jquery.dataTables.min.js"></script>';

else
if ( isset($_GET['page']) && ($_GET['page'] == "find" || $_GET['page'] == "kb"))
	$htmlHeaders .= '
	    <link rel="stylesheet" type="text/css" href="includes/css/kb.css" />

	    <script type="text/javascript" src="includes/libraries/ckeditor/ckeditor.js"></script>
		<script type="text/javascript" src="includes/libraries/ckeditor/dialog-patch.js"></script>
		<script type="text/javascript" src="includes/libraries/ckeditor/adapters/jquery.js"></script>

        <link rel="stylesheet" type="text/css" href="includes/libraries/datatable/jquery.dataTablesUI.css" />
        <script type="text/javascript" src="includes/libraries/datatable/jquery.dataTables.min.js"></script>

        <link rel="stylesheet" type="text/css" href="includes/libraries/ui-multiselect/css/ui.multiselect.css" />
        <script type="text/javascript" src="includes/libraries/ui-multiselect/js/ui.multiselect.min.js"></script>';

else
if ( !isset($_GET['page']) )
	$htmlHeaders .= '
        <link rel="stylesheet" type="text/css" href="includes/libraries/uploadify/uploadify.css" />
        <script type="text/javascript" src="includes/libraries/uploadify/jquery.uploadify.v2.1.4.min.js"></script>
        <script type="text/javascript" src="includes/libraries/uploadify/swfobject.js"></script>
        <script type="text/javascript" src="includes/libraries/numeric/jquery.numeric.js"></script>';


//Get Favicon
$htmlHeaders .= isset($_SESSION['settings']['favicon']) ? '
        <link rel="icon" href="'. $_SESSION['settings']['favicon'] . '" type="image/vnd.microsoft.ico" />' : '';

$htmlHeaders .= '
<script type="text/javascript">
<!-- // --><![CDATA[
    //deconnexion
    function MenuAction(val){
        if ( val == "deconnexion" ) {
            $("#menu_action").val(val);
            document.main_form.submit();
        }
        else {
        	$("#menu_action").val("action");
            if ( val == "") document.location.href="index.php";
            else document.location.href="index.php?page="+val;
        }
    }

	function aes_encrypt(text) {
		    return Aes.Ctr.encrypt(text, "'.SALT.'", 256);
		}

    //Identify user
    function identifyUser(redirect){
        $("#erreur_connexion").hide();
        if ( redirect == undefined ) redirect = ""; //Check if redirection
        if ( $("#login").val() != "" && $("#pw").val() != "" ){
            $("#pw").removeClass( "ui-state-error" );
            $("#ajax_loader_connexion").show();

            //create random string
            var randomstring = "";
            var chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz".split("");
            for (var i = 0; i < 10; i++) {
                randomstring += chars[Math.floor(Math.random() * chars.length)];
            }
            var data = \'{"login":"\'+sanitizeString($("#login").val())+\'" , "pw":"\'+sanitizeString($("#pw").val())+\'" , "duree_session":"\'+$("#duree_session").val()+\'" , "screenHeight":"\'+$("body").innerHeight()+\'" , "randomstring":"\'+randomstring+\'"}\';

            //send query
            $.post("sources/main.queries.php", {
                    type : "identify_user",
                    data : aes_encrypt(data)
                },
                function(data){
                    if (data == randomstring){
                        $("#ajax_loader_connexion").hide();
                        $("#erreur_connexion").hide();
                        window.location.href="index.php";
                    }else if (data == "user_is_locked"){
                        $("#ajax_loader_connexion").hide();
                        $("#erreur_connexion").html("'.$txt['account_is_locked'].'");
                        $("#erreur_connexion").show();
                    }else if (!isNaN(parseFloat(data)) && isFinite(data)){
                        $("#ajax_loader_connexion").hide();
                        $("#erreur_connexion").html(data + "'.$txt['login_attempts_on'] . (@$_SESSION['settings']['nb_bad_authentication']+1) .'");
                        $("#erreur_connexion").show();
                    }else{
                        $("#erreur_connexion").show();
                        $("#ajax_loader_connexion").hide();
                    }
                }
            );
        }else{
            $("#pw").addClass( "ui-state-error" );
        }
    }

	/*
	* Manage generation of new password
	*/
    function GenerateNewPassword(key, login){
    	$("#ajax_loader_send_mail").show();
		//send query
		$.post("sources/main.queries.php", {
				type :	"generate_new_password",
				login:	login,
				key :	key
			},
			function(data){
				if (data == "done"){
					window.location.href="index.php";
				}else{
					$("#generate_new_pw_error").show().html(data);
				}
				$("#ajax_loader_send_mail").hide();
			}
		);
	}

    function OpenDiv(div){
        $("#"+div).slideToggle("slow");
    }

    function OpenDialogBox(id){
        $("#"+id).dialog("open");
    }

	//Change language using icon flags
	function ChangeLanguage(lang){
		$("#language").val(lang);
		$.post(
			"sources/main.queries.php",
			{
				type    : "change_user_language",
				lang	: lang
			},
		    function(data){
		    	$("#language").val(lang);
		    	document.temp_form.submit();
		    },
		    "json"
		);
	}

    /*
    * Clean disconnection of user for security reasons.
    *
   	$(window).bind("beforeunload", function(){
		if ( $("#menu_action").val() == ""){
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
        $("#main *, #footer *, #icon_last_items *, #top *, button, .tip").tooltip({
            delay: 0,
            showURL: false
        });

        //Display Tabs
        $("#item_edit_tabs, #item_tabs").tabs();

        //BUTTON
        $("#but_identify_user").hover(
            function(){
                $(this).addClass("ui-state-hover");
            },
            function(){
                $(this).removeClass("ui-state-hover");
            }
        ).mousedown(function(){
            $(this).addClass("ui-state-active");
        })
        .mouseup(function(){
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

            function getSelectedValue(id) {
                return $("#" + id).find("dt a span.value").html();
            }

            $(document).bind("click", function(e) {
                var $clicked = $(e.target);
                if (! $clicked.parents().hasClass("dropdown"))
                    $(".dropdown dd ul").hide();
            });
        //END
    });';


if (!isset($_GET['page']) && isset($_SESSION['key'])) {
    $htmlHeaders .= '
	function aes_encrypt(text) {
		return Aes.Ctr.encrypt(text, "'.$_SESSION['key'].'", 256);
	}

    $(function() {
        //build nice buttonset
        $("#radio_import_type, #connect_ldap_mode").buttonset();
        $("#personal_sk, #change_personal_sk, #reset_personal_sk").button();

        if($("#personal_saltkey_set").val() != 1){
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
            buttons: {
                "'.$txt['index_change_pw_button'].'": function() {
                    if ( $("#new_pw").val() != "" && $("#new_pw").val() == $("#new_pw2").val() ){
                    	if($("#pw_strength_value").val() >= $("#user_pw_complexity").val()){
				            var data = "{\"new_pw\":\""+sanitizeString($("#new_pw").val())+"\"}";
				            $.post(
				                "sources/main.queries.php",
				                {
				                    type    : "change_pw",
				                    change_pw_origine    : "user_change",
				                    complexity:	$("#pw_strength_value").val(),
									data :	aes_encrypt(data)
				                },
				                function(data){
				                	if (data[0].error == "already_used") {
				                		$("#new_pw, #new_pw2").val("");
				                		$("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<span>'.$txt['pw_used'].'</span>");
				                	}else{
				                		document.main_form.submit();
				                	}
				                },
				                "json"
				            );
				        }else{
				        	$("#change_pwd_error").addClass("ui-state-error ui-corner-all").html("'.$txt['error_complex_not_enought'].'");
				        }
			        }else{
			            $("#change_pwd_error").addClass("ui-state-error ui-corner-all").html("'.$txt['index_pw_error_identical'].'");
			        }
                },
                "'.$txt['cancel_button'].'": function() {
					$("#change_pwd_error").removeClass("ui-state-error ui-corner-all").html("");
					 $("#new_pw, #new_pw2").val("");
                    $(this).dialog("close");
                }
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
                    $.post(
		                "sources/main.queries.php",
		                {
		                    type    : "send_pw_by_email",
		                    email	: $("#forgot_pw_email").val(),
							login	: $("#forgot_pw_login").val()
		                },
		                function(data){
		                	if (data[0].error != "no") {
		                		$("#div_forgot_pw_alert").html(data[0].message).addClass("ui-state-error").show();
		                	}else{
		                		$("#div_forgot_pw_alert").html(data[0].message);
		                		$("#div_forgot_pw").dialog("close");
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

		// DIALOG BOX FOR CHANGING PERSONAL SALTKEY
	    $("#div_change_personal_saltkey").dialog({
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
						   type	: "change_personal_saltkey",
						   sk	: encodeURIComponent($("#new_personal_saltkey").val())
						},
						function(data){
							$("#div_loading").hide();
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
						   type	: "reset_personal_saltkey",
						   sk	: encodeURIComponent($("#reset_personal_saltkey").val())
						},
						function(data){
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
                    if ( $(\'#radio1\').attr(\'checked\') ) ImportItemsFromCSV();
                    else $(this).dialog("close");
                },
                "'.$txt['cancel_button'].'": function() {
                    $("#import_status").html("");
                    $(this).dialog("close");
                }
            }
        });

        //CALL TO UPLOADIFY FOR CSV IMPORT
        $("#fileInput_csv").uploadify({
            "uploader"  : "includes/libraries/uploadify/uploadify.swf",
            "scriptData": {"type_upload":"import_items_from_csv"},
            "script"    : "includes/libraries/uploadify/uploadify.php?PHPSESSID='.$_SESSION['user_id'].'",
            "cancelImg" : "includes/libraries/uploadify/cancel.png",
            "auto"      : true,
            "fileDesc"  : "csv",
            "fileExt"   : "*.csv",
            "onComplete": function(event, queueID, fileObj, reponse, data){$("#import_status_ajax_loader").show();ImportCSV(fileObj.name);},
            "buttonText": \''.$txt['csv_import_button_text'].'\'
        });

        //CALL TO UPLOADIFY FOR KEEPASS IMPORT
        $("#fileInput_keepass").uploadify({
            "uploader"  : "includes/libraries/uploadify/uploadify.swf",
            "scriptData": {"type_upload":"import_items_from_file"},
            "script"    : "includes/libraries/uploadify/uploadify.php?PHPSESSID='.$_SESSION['user_id'].'",
            "cancelImg" : "includes/libraries/uploadify/cancel.png",
            "auto"      : true,
            "fileDesc"  : "xml",
            "fileExt"   : "*.xml",
            "onComplete": function(event, queueID, fileObj, reponse, data){$("#import_status_ajax_loader").show();ImportKEEPASS(fileObj.name);},//
            "buttonText": \''.$txt['keepass_import_button_text'].'\'
        });

        // DIALOG BOX FOR PRINT OUT ITEMS
        $("#div_print_out").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 400,
            height: 400,
            title: "'.$txt['print_out_menu_title'].'",
            buttons: {
                "'.$txt['print'].'": function() {
					//Get list of selected folders
					var ids = "";
					$("#selected_folders :selected").each(function(i, selected){
						if (ids == "" ) ids = $(selected).val();
						else ids = ids + ";" + $(selected).val();
					});
					$("#div_loading").show();

                	//Send query
                    $.post(
		                "sources/export.queries.php",
		                {
		                    type    : $("input[name=\"export_format\"]:checked").val() == "pdf" ? "export_to_pdf_format" : "export_to_csv_format",
		                    ids		: ids
		                },
		                function(data){
		                	$("#download_link").html(data[0].text);
		                	$("#div_loading").hide();
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

		//only numerics
		$(".numeric_only").numeric();

       //Simulate a CRON activity
		$.post(
			"sources/main.queries.php",
			{
				type    : "send_wainting_emails"
			},
			function(data){
				//
			}
		);
    })

    function ChangeMyPass(){
    	var data = "{\"new_pw\":\""+sanitizeString($("#new_pw").val())+"\"}";
        $.post(
            "sources/main.queries.php",
            {
                type    			: "change_pw",
                change_pw_origine	: "first_change",
                complexity			:	"",
				data 				:	aes_encrypt(data)
            },
            function(data){
            	document.main_form.submit();
            },
            "json"
        );
    }

    //Permits to upload passwords from KEEPASS file
    function ImportKEEPASS(file){
    	//clean divs
    	$("#import_status").html("");
    	$("#import_from_file_info").html("").hide();

    	$("#div_loading").show();

        //check if file has good format
		$.post(
			"sources/import.queries.php",
			{
			   type		: "import_file_format_keepass",
			   file		: file,
			   destination		: $("#import_keepass_items_to").val()
			},
			function(data){
				if(data[0].error == "not_kp_file"){
					$("#import_status").html(data[0].message);
					$("#import_status_ajax_loader").hide();
				}else{
					$("#import_status").html(data[0].message);
					$("#import_status_ajax_loader").hide();
				}
				$("#div_loading").hide();
			},
			"json"
		);
    }

    //Permits to upload passwords from CSV file
    function ImportCSV(file){
    	$("#import_status").html("");
    	$("#import_from_file_info").html("").hide();
        $.post(
			"sources/import.queries.php",
			{
			   type		: "import_file_format_csv",
			   file		: file
			},
			function(data){
				if(data[0].error == "bad_structure"){
					$("#import_from_file_info").html("'.$txt['import_error_no_read_possible'].'").show();
				}else{
					$("#import_status").html(data[0].output);
					$("#item_all_selection").click(function(){
						if($("#item_all_selection").prop("checked")){
							$("input[class=\'item_checkbox\']:not([disabled=\'disabled\'])").attr("checked", true);
						}else{
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
    function ImportItemsFromCSV(){
        var items = "";

        //Get data checked
        $("input[class=item_checkbox]:checked").each(function() {
            var elem = $(this).attr("id").split("-");
            if ( items == "") items = $("#item_to_import_values-"+elem[1]).val();
            else items = items + "@_#sep#_@" + $("#item_to_import_values-"+elem[1]).val();

        });
        $("#import_status_ajax_loader").show();

        //Lauchn ajax query that will insert items into DB
        $.post(
			"sources/import.queries.php",
			{
			   type		: "import_items",
			   folder	: $("#import_items_to").val(),
			   data		: aes_encrypt(items),
			   import_csv_anyone_can_modify	: $("#import_csv_anyone_can_modify").prop("checked"),
			   import_csv_anyone_can_modify_in_role	: $("#import_csv_anyone_can_modify_in_role").prop("checked")
			},
			function(data){
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
    function toggle_importing_details() {
        $("#div_importing_kp_details").toggle();
    }

    //PRINT OUT: select folders
    function print_out_items() {
    	$("#selected_folders").empty();

    	//Lauchn ajax query that will build the select list
        $.post(
			"sources/main.queries.php",
			{
			   type		: "get_folders_list",
			   div_id	: "selected_folders"
			},
			function(data){
				data = $.parseJSON(data);
				for(reccord in data){
					$("#selected_folders").append("<option value=\'"+reccord+"\'>"+data[reccord]+"</option>");
				}
			}
		);

    	//Open dialogbox
        $("#div_print_out").dialog("open");
    }

	//Store PSK
	function StorePersonalSK(){
        //Lauchn ajax query
        $.post(
			"sources/main.queries.php",
			{
			   type	: "store_personal_saltkey",
			   sk	: encodeURIComponent($("#input_personal_saltkey").val())
			},
			function(data){
				if($("#input_personal_saltkey").val() != ""){
					$("#div_dialog_message_text").html("<div style=\'font-size:16px;\'><span class=\'ui-icon ui-icon-info\' style=\'float: left; margin-right: .3em;\'></span>'.$txt['alert_message_done'].'</div>");
					$("#change_personal_sk").button("enable");
					$("#div_dialog_message").dialog("open");
				}
			}
		);
	}';
}

else
//JAVASCRIPT FOR FIND PAGE
if ( isset($_GET['page']) && $_GET['page'] == "find"){
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
            }
        });
    });';
}

else
//JAVASCRIPT FOR ADMIN PAGE
if ( isset($_GET['page']) && $_GET['page'] == "manage_main" ){
    $htmlHeaders .= '
            //Function loads informations from cpassman FTP
            function LoadCPMInfo(){
                //Lauchn ajax query
		        $.post(
					"sources/admin.queries.php",
					{
					   type	: "cpm_status"
					},
					function(data){
						if(data[0].error == "connection"){
							$("#CPM_infos").html("Server connection is impossible ... check your Internet/firewall configuration");
						}else{
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

}

else
//JAVASCRIPT FOR FAVOURITES PAGE
if ( isset($_GET['page']) && $_GET['page'] == "favourites" ){
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
						   type	: "del_fav",
						   id	: $("#detele_fav_id").val()
						},
						function(data){
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

    function prepare_delete_fav(id){
        $("#detele_fav_id").val(id);
        OpenDialogBox("div_delete_fav");
    }';
}

else
//JAVASCRIPT FOR ADMIN_SETTIGNS PAGE
if ( isset($_GET['page']) && $_GET['page'] == "manage_settings" ){
    $htmlHeaders .= '
    $(function() {
    	$("#restore_bck_encryption_key_dialog").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width:100,
            height:140,
            title: "'.$txt['admin_action_db_restore_key'].'",
            buttons: {
                "'.$txt['ok'].'": function() {
                    LaunchAdminActions("admin_action_db_restore", $("#restore_bck_fileObj").val()+"&"+$("#restore_bck_encryption_key").val());
                },
                "'.$txt['cancel_button'].'": function() {
                    $(this).dialog("close");
                }
            }
        });

        //CALL TO UPLOADIFY FOR RESTORE SQL FILE
        $("#fileInput_restore_sql").uploadify({
            "uploader"  : "includes/libraries/uploadify/uploadify.swf",
            "script"    : "includes/libraries/uploadify/uploadify.php?user_id='.$_SESSION['user_id'].'&key_tempo='.$_SESSION['key'].'",
            "cancelImg" : "includes/libraries/uploadify/cancel.png",
            "scriptData": {"type_upload":"restore_db"},
            "auto"      : true,
            "fileDesc"  : "sql",
            "fileExt"   : "*.sql",
            "wmode"     : "transparent",
            "buttonImg" : "includes/images/inbox--plus.png",
            "onComplete": function(event, queueID, fileObj, reponse, data){
            	$("#restore_bck_fileObj").val(fileObj.name);
            	$("#restore_bck_encryption_key_dialog").dialog("open");
            }
        });

        // Build Tabs
        $("#tabs").tabs({
        	//MASK SAVE BUTTON IF tab 3 selected
        	select: function(event, ui) {
        		if (ui.index == 2) {
					$("#save_button").hide();
		        }else{
		        	$("#save_button").show();
        		}
        		return true;
			}
		});

        //BUILD BUTTONS
        $("#save_button").button();

        //BUILD BUTTONSET
        $(".div_radio").buttonset();

        //check NEW SALT KEY
        $("#new_salt_key").keypress(function (e) {
	        var key = e.charCode || e.keyCode || 0;
			if($("#new_salt_key").val().length <= 15 || $("#new_salt_key").val().length >= 32){
				$("#change_salt_key_image").attr("src", "includes/images/cross.png");
				$("#change_salt_key_but").hide();
			}else{
				$("#change_salt_key_image").attr("src", "includes/images/tick.png");
				$("#change_salt_key_but").show();
			}
			// allow backspace, tab, delete, arrows, letters, numbers and keypad numbers ONLY
			return (
				key != 33 && key != 34 && key != 39 && key != 92 && key != 32  && key != 96 && (key < 165)
				&& $("#new_salt_key").val().length <= 32
			);
	    });
    });

    //###########
    //## FUNCTION : Launch the action the admin wants
    //###########
    function LaunchAdminActions(action,option){
        $("#div_loading").show();
        $("#result_admin_action_db_backup").html("");
        if ( action == "admin_action_db_backup" ) option = $("#result_admin_action_db_backup_key").val();
        else if ( action == "admin_action_backup_decrypt" ) option = $("#bck_script_decrypt_file").val();
        else if ( action == "admin_action_change_salt_key" ) option = aes_encrypt(sanitizeString($("#new_salt_key").val()));
        //Lauchn ajax query
        $.post(
			"sources/admin.queries.php",
			{
			   type		: action,
			   option	: option
			},
			function(data){
				$("#div_loading").hide();
				if(data[0].result == "db_backup"){
					$("#result_admin_action_db_backup").html("<img src=\'includes/images/document-code.png\' alt=\'\' />&nbsp;<a href=\'"+data[0].href+"\'>'.$txt['pdf_download'].'</a>");
				}else if(data[0].result == "pf_done"){
					$("#result_admin_action_check_pf").show();
				}else if(data[0].result == "db_restore"){
					$("#restore_bck_encryption_key_dialog").dialog("close");
					$("#result_admin_action_db_restore").html("<img src=\"includes/images/tick.png\" alt=\"\" />");
					$("#result_admin_action_db_restore_get_file").hide();
					//deconnect user
		            $("#menu_action").val("deconnexion");
		            document.main_form.submit();
				}else if(data[0].result == "db_optimize"){
					$("#result_admin_action_db_optimize").html("<img src=\'includes/images/tick.png\' alt=\'\' />");
				}else if(data[0].result == "purge_old_files"){
					$("#result_admin_action_purge_old_files").html("<img src=\'includes/images/tick.png\' alt=\'\' />&nbsp;"+data[0].nb_files_deleted+"&nbsp;'.$txt['admin_action_purge_old_files_result'].'");
				}else if(data[0].result == "db_clean_items"){
					$("#result_admin_action_db_clean_items").html("<img src=\"includes/images/tick.png\" alt=\"\" />&nbsp;"+data[0].nb_items_deleted+"&nbsp;'.$txt['admin_action_db_clean_items_result'].'");
				}else if(data[0].result == "changed_salt_key"){
					//deconnect user
		            $("#menu_action").val("deconnexion");
		            document.main_form.submit();
				}
			},
			"json"
		);
    }
    ';
}


$htmlHeaders .= '
// ]]>
</script>';
?>