/*
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      install.js
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2022 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */


$(function() {	
	$("#but_next").click(function(event) {
        $("#step").val($(this).attr("target_id"));
        document.upgrade.submit();
    });

    // no paste
    $("#admin_pwd").bind("paste",function(e) {
        alert("Paste option is disabled !!");
        e.preventDefault();
    });
	
	// Auto start
	if ($("#step").val() == 5) {
		$('#but_launch').trigger('click');
	}

});

function aesEncrypt(text)
{
    return Aes.Ctr.encrypt(text, "cpm", 128);
}

function checkPage()
{
    var step = $("#step").val();
    var data = "";
    var error = "";
    var index = "";
    var tasks = [];
    var multiple = "";
    var tsk = "";
    $("#step_error").addClass("hidden").html("");
    $("#res_"+step).html("");
	
	alertify
        .message('Working on it... <i class="fas fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();

    if (step === "2") {
    // STEP 2
        if ($("#url_path").val() === "" || $("#absolute_path").val() === "") {
            error = "Fields need to be filled in!";
        } else {
            const jsonValues = {"absolute_path":$("#absolute_path").val(), "url_path":$("#url_path").val()};
            data = JSON.stringify(jsonValues);
            tasks = ["folder*install", "folder*includes", "folder*includes/config", "folder*includes/avatars", "folder*includes/libraries/csrfp/libs", "folder*includes/libraries/csrfp/js", "folder*includes/libraries/csrfp/log",  "extension*mbstring", "extension*openssl", "extension*bcmath", "extension*iconv", "extension*gd", "extension*xml", "extension*curl", "version*php", "ini*max_execution_time"];
            multiple = true;
            $("#hid_absolute_path").val($("#absolute_path").val());
            $("#hid_url_path").val($("#url_path").val());
        }
    } else if (step === "3") {
    // STEP 3
        if ($("#db_host").val() === "" || $("#db_db").val() === "" || $("#db_login").val() === "" || $("#db_port").val() === "") {
            error = "Fields need to be filled in!";
        } else if ($("#db_pw").val().indexOf('"') > -1) {
            error = "Double quotes in password not allowed!";
        } else {
            const jsonValues = {"db_host":$("#db_host").val(), "db_bdd":$("#db_bdd").val(), "db_login":$("#db_login").val(), "db_pw":$("#db_pw").val(), "db_port":$("#db_port").val(), "absolute_path":$("#hid_absolute_path").val(), "url_path":$("#hid_url_path").val()};
            data = JSON.stringify(jsonValues);
            tasks = ["connection*test"];
            multiple = "";
            $("#hid_db_host").val($("#db_host").val());
            $("#hid_db_bdd").val($("#db_bdd").val());
            $("#hid_db_login").val($("#db_login").val());
            $("#hid_db_pwd").val($("#db_pw").val());
            $("#hid_db_port").val($("#db_port").val());
        }
    } else if (step === "4") {
    // STEP 4
        if ($("#admin_pwd").val() === "") {
			alertify
				.error('<i class="fas fa-ban mr-2"></i>You must define a password for Administrator account.', 10)
				.dismissOthers();
			return false;
		} else if ($("#admin_pwd_confirm").val() === "") {
            alertify
				.error('<i class="fas fa-ban mr-2"></i>You must confirm the password for Administrator account.', 10)
				.dismissOthers();
			return false;
		} else if ($("#admin_pwd_confirm").val() !== $("#admin_pwd").val()) {
            alertify
				.error('<i class="fas fa-ban mr-2"></i>Administrator passwords are not similar.', 10)
				.dismissOthers();
			return false;
        } else{
            $("#hid_db_pre").val($("#tbl_prefix").val());
            const jsonValues = {"tbl_prefix":sanitizeString($("#tbl_prefix").val()), "sk_path":sanitizeString($("#sk_path").val()), "admin_pwd":sanitizeString($("#admin_pwd").val()), "send_stats":""};
            data = JSON.stringify(jsonValues);
            tasks = ["misc*preparation"];
            multiple = "";
        }
    } else if (step === "5") {
    // STEP 5
        data = "";
        tasks = ["table*utf8", "table*api", "table*automatic_del", "table*cache", "table*categories", "table*categories_folders", "table*categories_items", "table*defuse_passwords", "table*emails", "table*export", "table*files", "table*items", "table*items_change", "table*items_edition", "table*kb", "table*kb_categories", "table*kb_items", "table*languages", "table*log_items", "table*log_system", "table*misc", "table*nested_tree", "table*notification", "table*otv", "table*sharekeys_fields", "table*sharekeys_files", "table*sharekeys_items", "table*sharekeys_logs", "table*sharekeys_suggestions", "table*restriction_to_roles", "table*rights", "table*roles_title", "table*roles_values", "table*suggestion", "table*tags", "table*templates", "table*tokens", "table*users"];
        multiple = true;
    } else if (step === "6") {
    // STEP 6
        const jsonValues = {"url_path":sanitizeString($("#hid_url_path").val())};
        data = JSON.stringify(jsonValues);
        tasks = ["file*security", "file*settings.php", "file*csrfp-token", "install*cleanup"];
        multiple = true;
    }

    // launch query
    if (error === "" && multiple === true) {
        var global_error_on_query = false,
            index = 0;

        const dbInfo = {"db_host" : $("#hid_db_host").val(), "db_bdd" : $("#hid_db_bdd").val(), "db_login" : $("#hid_db_login").val(), "db_pw" : $("#hid_db_pwd").val(), "db_port" : $("#hid_db_port").val(), "db_pre" : $("#hid_db_pre").val()};

        $("#step_res").val("true");
        $("#pop_db").html("");

        function doGetJson(task) {
            tsk = task.split("*");

            return $.ajax({
                url: "install.queries.php",
                type : "POST",
                dataType : "json",
                async: false,
                data : {
                    type:       "step_"+step,
                    data:       aesEncrypt(data), //
                    activity:   aesEncrypt(tsk[0]),
                    task:       aesEncrypt(tsk[1]),
                    db:         aesEncrypt(JSON.stringify(dbInfo)),
                    index:      index,
                    multiple:   multiple,
                    info:       tsk[0]+"-"+tsk[1]
                }
            })
            .complete(function(data) {console.log(data)
                if (data.responseText === "") {
                    alertify
                        .error('<i class="fas fa-ban mr-2">[ERROR] Answer from server is empty.', 10)
                        .dismissOthers();
                } else {
                    data = $.parseJSON(data.responseText);
                    //console.log(data)
                    if (data[0].error === "") {
                        if (step === "5") {
                            if (data[0].activity === "table") {
                                $("#pop_db").append("<li>Table <b>"+data[0].task+"</b> created</li>");
                            } else if (data[0].activity === "entry") {
                                $("#pop_db").append("<li>Entries <b>"+data[0].task+"</b> were added</li>");
                            }
                        } else {
                            $("#res"+step+"_check"+data[0].index).html('<i class="fas fa-check text-success"></i>');
                        }

                        if (data[0].result !== undefined && data[0].result !== "" ) {
                            alertify
                                .message(data[0].result, 10)
                                .dismissOthers();
                        }
                    } else {
                        $("#res"+step+"_check"+data[0].index).html('<span class="badge badge-danger"><i class="fas fa-ban text-warning mr-2"></i>'+data[0].error+"</i></span>");
                                                    
                        global_error_on_query = true;
                    }
                }
                index++;
            });
        };

        var promise = tasks.slice(1)
            .reduce(
                (a,b) => a.then(doGetJson.bind(null, b)),
                doGetJson(tasks[0])
            );

        promise.then(function(){
            // do something when all requests are ready
            // all requests are complete
            if ($("#step_res").val() === "false" || global_error_on_query === true) {
                alertify
                    .error('<i class="fas fa-ban mr-2"></i>At least one task has failed! Please correct and relaunch.', 0)
                    .dismissOthers();
                return false;
            } else {
                alertify
                    .success('<i class="fas fa-check text-success mr-2"></i><b>Done</b>.<br>Page is now refreshing ...', 1)
                    .dismissOthers();

                    $("#but_launch")
                    .prop("disabled", true)
                    .addClass("hidden");
                $("#but_next")
                    .prop("disabled", false)
                    .removeClass("hidden");
                // Hide restart button at end of step 6 if successful
                if (step === "6") {
                    $("#but_restart")
                        .prop("disabled", true)
                        .addClass("hidden");
                }
                
                // Go to next step
                if (step <= 6) {
                    setTimeout(
                        function(){
                            $('#but_next').trigger('click');
                        },
                        1000
                    );
                }
            }
        });
    } else if (error === "" && multiple === "") {
        tsk = tasks[0].split("*");

        const dbInfo = {"db_host" : $("#hid_db_host").val(), "db_bdd" : $("#hid_db_bdd").val(), "db_login" : $("#hid_db_login").val(), "db_pw" : $("#hid_db_pwd").val(), "db_port" : $("#hid_db_port").val()};

        $.ajax({
            url: "install.queries.php",
            type : 'POST',
            dataType : "json",
            data : {
                type:       "step_"+step,
                data:       aesEncrypt(data),
                activity:   aesEncrypt(tsk[0]),
                task:       aesEncrypt(tsk[1]),
                db:         aesEncrypt(JSON.stringify(dbInfo)),
                index:      index,
                multiple:   multiple,
                info:       tsk[0]+"-"+tsk[1]
            },
            complete : function(data){
                data = $.parseJSON(data.responseText);
				
                if (data[0].error !== "" ) {
					alertify
						.error('<i class="fas fa-ban mr-2"></i>Next ERROR occurred: <i>'+data[0].error+'</i><br />Please correct and relaunch.', 0)
						.dismissOthers();
					return false;
                } else {
                    if (data[0].result !== undefined && data[0].result !== "" ) {
                        alertify
							.success('<i class="fas fa-check text-success mr-2"></i>'+data[0].result+'.<br>Page is now refreshing ...', 0)
							.dismissOthers();
                    }
                    $("#but_launch")
						.prop("disabled", true)
						.addClass("hidden");
                    $("#but_next")
						.prop("disabled", false)
						.removeClass("hidden");
                }
				
				// Go to next step
				if (step <= 6) {
					setTimeout(
						function(){
							$('#but_next').trigger('click');
						},
						1000
					);
				}
            }
        });
    } else {
        $("#step_error").removeClass("hidden").html(error);
    }
}


function GotoNextStep()
{
    var step = $("#page_id").val();
    var nextStep = parseInt(step)+1;

    if (nextStep === 7) {
        $("#but_restart, #but_next, #but_launch").addClass("hidden");
        $("#but_start").addClass("hidden");
        $("#step_result").html("").addClass("hidden");
        $("#step_name").html($("#menu_step"+nextStep).html());
        $("#step_content").html($("#text_step"+nextStep).html());
        $("#menu_step"+step).switchClass("li_inprogress", "li_done");
        $("#menu_step"+nextStep).switchClass("", "li_inprogress");
        $("#res_"+step).html("<img src=\"images/tick.png\">");
    } else {
        $("#page_id").val(nextStep);
        $("#but_launch").removeClass("hidden").prop("disabled", false);
        $("#but_launch").removeClass("hidden");
        $("#but_restart").removeClass("hidden");
        $("#but_next").prop("disabled", true);
        $("#but_next").addClass("hidden");
        $("#menu_step"+step).switchClass("li_inprogress", "li_done");
        $("#menu_step"+nextStep).switchClass("", "li_inprogress");
        $("#res_"+step).html("<img src=\"images/tick.png\">");
        $("#step_result").html("");
        $("#step_name").html($("#menu_step"+nextStep).html());
        $("#step_content").html($("#text_step"+nextStep).html());
        $('#admin_pwd').live("paste",function(e) {
            alert("Paste option is disabled !!");
            e.preventDefault();
        });
        $("#admin_pwd").live("keypress", function(e){
            var key = e.charCode || e.keyCode || 0;
            // allow backspace, tab, delete, arrows, letters, numbers and keypad numbers ONLY
            return (
                key !== 39
            );
        });
        // Auto start as required
        if (nextStep === "5" || nextStep === "6" ) {
            checkPage();
        }
    }
}
