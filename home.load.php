<?php
/**
 *
 * @file          home.load.php
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
?>

<script type="text/javascript">
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
	
    // DIALOG BOX FOR OFF-LINE MODE
    $("#div_offline_mode").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 450,
        title: "<?php echo $txt['offline_menu_title'];?>",
        buttons: {
            "<?php echo $txt['pw_generate'];?>": function() {
                //Get list of selected folders
                var ids = "";
                $("#offline_mode_selected_folders :selected").each(function(i, selected) {
                    if (ids == "") ids = $(selected).val();
                    else ids = ids + ";" + $(selected).val();
                });
                $("#div_offline_mode_wait").show();
                $("#offline_mode_error").hide();
                $("#offline_download_link").html("");

                if ($("#offline_password").val() == "") {
                    $("#offline_mode_error").show().html("<?php echo $txt['pdf_password_warning'];?>").attr("class","ui-state-error");
                    $("#offline_download_link, #div_offline_mode_wait").hide();
                    return;
                }

                if ($("#offline_pw_strength_value").val() < $("#min_offline_pw_strength_value").val()) {
                    $("#offline_mode_error").addClass("ui-state-error ui-corner-all").show().html("<?php echo $txt['error_complex_not_enought'];?>");
                    $("#offline_download_link, #div_offline_mode_wait").hide();
                    return;
                }

                pdf_password = sanitizeString($("#offline_password").val());

                //Send query
                $.post(
                    "sources/export.queries.php",
                    {
                        type    : "export_to_html_format",
                        ids        : ids,
                        pdf_password : sanitizeString($("#offline_password").val())
                    },
                    function(data) {
                        if (data[0].loop != null && data[0].loop == "true") {
                    		exportHTMLLoop(ids, data[0].file,  ids.split(';').length, 1, pdf_password);
                    	}
                        $("#offline_download_link").html(data[0].text);
                        $("#div_offline_mode_wait").hide();
                    },
                    "json"
               );
            },
            "<?php echo $txt['cancel_button'];?>": function() {
                $(this).dialog("close");
            }
        }
    });

    //Password meter
    if ($("#new_pw").length) {
        $("#new_pw").simplePassMeter({
            "requirements": {},
              "container": "#pw_strength",
              "defaultText" : "<?php echo $txt['index_pw_level_txt'];?>",
            "ratings": [
            {"minScore": 0,
                "className": "meterFail",
                "text": "<?php echo $txt['complex_level0'];?>"
            },
            {"minScore": 25,
                "className": "meterWarn",
                "text": "<?php echo $txt['complex_level1'];?>"
            },
            {"minScore": 50,
                "className": "meterWarn",
                "text": "<?php echo $txt['complex_level2'];?>"
            },
            {"minScore": 60,
                "className": "meterGood",
                "text": "<?php echo $txt['complex_level3'];?>"
            },
            {"minScore": 70,
                "className": "meterGood",
                "text": "<?php echo $txt['complex_level4'];?>"
            },
            {"minScore": 80,
                "className": "meterExcel",
                "text": "<?php echo $txt['complex_level5'];?>"
            },
            {"minScore": 90,
                "className": "meterExcel",
                "text": "<?php echo $txt['complex_level6'];?>"
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
              "defaultText" : "<?php echo $txt['index_pw_level_txt'];?>",
            "ratings": [
            {"minScore": 0,
                "className": "meterFail",
                "text": "<?php echo $txt['complex_level0'];?>"
            },
            {"minScore": 25,
                "className": "meterWarn",
                "text": "<?php echo $txt['complex_level1'];?>"
            },
            {"minScore": 50,
                "className": "meterWarn",
                "text": "<?php echo $txt['complex_level2'];?>"
            },
            {"minScore": 60,
                "className": "meterGood",
                "text": "<?php echo $txt['complex_level3'];?>"
            },
            {"minScore": 70,
                "className": "meterGood",
                "text": "<?php echo $txt['complex_level4'];?>"
            },
            {"minScore": 80,
                "className": "meterExcel",
                "text": "<?php echo $txt['complex_level5'];?>"
            },
            {"minScore": 90,
                "className": "meterExcel",
                "text": "<?php echo $txt['complex_level6'];?>"
            }
            ]
        });
    }
    $("#offline_password").bind({
        "score.simplePassMeter" : function(jQEvent, score) {
            $("#offline_pw_strength_value").val(score);
        }
    });

    // DIALOG BOX FOR CHANGING PASSWORD
    $("#div_changer_mdp").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 300,
        height: 250,
        title: "<?php echo $txt['index_change_pw'];?>",
        open: function( event, ui ) {
            $("#change_pwd_complexPw").html("<?php echo $txt['complex_asked'];?> : <?php echo $pwComplexity[$_SESSION['user_pw_complexity']][1];?>");
            $("#change_pwd_error").hide();
        },
        buttons: {
            "<?php echo $txt['index_change_pw_button'];?>": function() {
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
                                    $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<span><?php echo $txt['pw_used'];?></span>");
                                } else {
                                    document.main_form.submit();
                                }
                            },
                            "json"
                       );
                    } else {
                        $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<?php echo $txt['error_complex_not_enought'];?>");
                    }
                } else {
                    $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<?php echo $txt['index_pw_error_identical'];?>");
                }
            },
            "<?php echo $txt['cancel_button'];?>": function() {
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
        title: "<?php echo $txt['menu_title_new_personal_saltkey'];?>",
        buttons: {
            "<?php echo $txt['ok'];?>": function() {
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
            "<?php echo $txt['cancel_button'];?>": function() {
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
        title: "<?php echo $txt['menu_title_new_personal_saltkey'];?>",
        buttons: {
            "<?php echo $txt['ok'];?>": function() {
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
            "<?php echo $txt['cancel_button'];?>": function() {
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
        title: "<?php echo $txt['import_csv_menu_title'];?>",
        buttons: {
            "<?php echo $txt['import_button'];?>": function() {
                if ($('#radio1').attr('checked')) ImportItemsFromCSV();
                else $(this).dialog("close");
            },
            "<?php echo $txt['cancel_button'];?>": function() {
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
        title: "<?php echo $txt['print_out_menu_title'];?>",
        buttons: {
            "<?php echo $txt['print'];?>": function() {
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
                    $("#print_out_error").show().html("<?php echo $txt['pdf_password_warning'];?>").attr("class","ui-state-error");
                    $("#div_print_out_wait").hide();
                    return;
                }

                // export format?
                var export_format = "";
                if ($("input[name=\"export_format\"]:checked").val() == "pdf") export_format = "export_to_pdf_format";
                else if ($("input[name=\"export_format\"]:checked").val() == "csv") export_format = "export_to_csv_format";
                else if ($("input[name=\"export_format\"]:checked").val() == "html") export_format = "export_to_html_format";

                if (export_format == "export_to_html_format" && $("#pdf_password").val() == "") {
                    $("#print_out_error").show().html("<?php echo $txt['pdf_password_warning'];?>").attr("class","ui-state-error");
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
            "<?php echo $txt['cancel_button'];?>": function() {
                $(this).dialog("close");
            }
        }
    });

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
                    "PHPSESSID":"'.$_SESSION['user_id'];?>",
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
        $("#plupload_runtime").html("Upload feature: runtime " + params.runtime).removeClass('ui-state-error');
        $("#upload_enabled").val("1");
    });

    // Uploader options
    uploader_csv.bind("UploadProgress", function(up, file) {
        $("#" + file.id + " b").html(file.percent + "%");
    });
    uploader_csv.bind("Error", function(up, err) {
        $("#filelist_csv").html("<div class='ui-state-error ui-corner-all'>Error: " + err.code +
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
                    "PHPSESSID":"'.$_SESSION['user_id'];?>",
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
        $("#filelist_kp").html("<div class='ui-state-error ui-corner-all'>Error: " + err.code +
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
    uploader_kp.init();

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


/*
* Loading Item details step 2
*/
function exportHTMLLoop(idsList, file, number, cpt, pdf_password)
{
	// prpare list of ids to treat during this run
	if (idsList != "") {
		$("#offline_download_link").html('<img src="includes/images/ajax-loader.gif" /> ' + Math.round((parseInt(cpt)*100)/parseInt(number)) + "%");

		tab = idsList.split(';');
		idTree = tab[0];
		tab = tab.slice(1, tab.length);
		idsList = tab.join(';');
		cpt = parseInt(cpt) + 1;
		
		$.post(
			"sources/export.queries.php",
			{
				type 	: "export_to_html_format_loop",
				idsList	: idsList,
				idTree 	: idTree,
				file    : file,
				cpt     : cpt,
				number  : number,
				pdf_password : pdf_password
			},
			function(data) {
				// relaunch for next run
				exportHTMLLoop (
					data[0].idsList,
					data[0].file,
					number,
					cpt,
					pdf_password
				);
			},
			"json"
		);
	} else {
		$.post(
			"sources/export.queries.php",
			{
				type 	: "export_to_html_format_finalize",
				file    : file
			},
			function(data) {
				$("#offline_download_link").html(data[0].text);
				$("#div_print_out_wait").hide();
				$("#div_loading").hide();
			},
			"json"
		);
	}
};

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
            $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<?php echo $txt['error_complex_not_enought'];?>");
        }
    } else {
        $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<?php echo $txt['index_pw_error_identical'];?>");
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
                $("#import_from_file_info").html("<?php echo $txt['import_error_no_read_possible'];?>").show();
            } else {
                $("#import_status").html(data[0].output);
                $("#item_all_selection").click(function() {
                    if ($("#item_all_selection").prop("checked")) {
                        $("input[class='item_checkbox']:not([disabled='disabled'])").attr("checked", true);
                    } else {
                        $("input[class='item_checkbox']:not([disabled='disabled'])").removeAttr("checked");
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
                $("#selected_folders").append("<option value='"+reccord+"'>"+data[reccord]+"</option>");
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
                $("#offline_mode_selected_folders").append("<option value='"+reccord+"'>"+data[reccord]+"</option>");
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
                $("#div_dialog_message_text").html("<div style='font-size:16px;'><span class='ui-icon ui-icon-info' style='float: left; margin-right: .3em;'></span><?php echo $txt['alert_message_done'];?></div>");
                $("#change_personal_sk").button("enable");
                $("#div_dialog_message").dialog("open");
            }
        }
   );
}
</script>