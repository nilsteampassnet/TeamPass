/**
 * @file          install.js
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2011 Nils Laumaillé
 * @licensing     GNU GPL-3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

$(function() {
    $(".button").button();
    $("#but_launch, #step_error, #but_restart").addClass("hidden");
    $("#but_launch").prop("disabled", true);

    // no paste
    $("#admin_pwd").bind("paste",function(e) {
        alert("Paste option is disabled !!");
        e.preventDefault();
    });
});

function aesEncrypt(text)
{
    return Aes.Ctr.encrypt(text, "cpm", 128);
}

function checkPage()
{
    var step = $("#page_id").val();
    var data = "";
    var error = "";
    var index = "";
    var tasks = [];
    var multiple = "";
    var tsk = "";
    $("#step_error").addClass("hidden").html("");
    $("#res_"+step).html("");

    if (step === "2") {
    // STEP 2
        if ($("#url_path").val() === "" || $("#root_path").val() === "") {
            error = "Fields need to be filled in!";
        } else {
            const jsonValues = {"root_path":$("#root_path").val(), "url_path":$("#url_path").val()};
            data = JSON.stringify(jsonValues);
            tasks = ["folder*install", "folder*includes", "folder*includes/config", "folder*includes/avatars", "folder*includes/libraries/csrfp/libs", "folder*includes/libraries/csrfp/js", "folder*includes/libraries/csrfp/log", "folder*files", "folder*upload", "extension*mcrypt", "extension*mbstring", "extension*openssl", "extension*bcmath", "extension*iconv", "extension*gd", "extension*xml", "extension*curl", "version*php", "ini*max_execution_time"];
            multiple = true;
            $("#hid_abspath").val($("#root_path").val());
            $("#hid_url_path").val($("#url_path").val());
        }
    } else if (step === "3") {
    // STEP 3
        if ($("#db_host").val() === "" || $("#db_db").val() === "" || $("#db_login").val() === "" || $("#db_port").val() === "") {
            error = "Paths need to be filled in!";
        } else if ($("#db_pw").val().indexOf('"') > -1) {
            error = "Double quotes in password not allowed!";
        } else {
            const jsonValues = {"db_host":$("#db_host").val(), "db_bdd":$("#db_bdd").val(), "db_login":$("#db_login").val(), "db_pw":$("#db_pw").val(), "db_port":$("#db_port").val(), "abspath":$("#hid_abspath").val(), "url_path":$("#hid_url_path").val()};
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
            error = "You must define a password for Admin account!";
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
        tasks = ["table*utf8", "table*items", "table*log_items", "table*misc", "table*nested_tree", "table*rights", "table*users", "table*tags", "table*log_system", "table*files", "table*cache", "table*roles_title", "table*roles_values", "table*kb", "table*kb_categories", "table*kb_items", "table*restriction_to_roles", "table*languages", "table*emails", "table*automatic_del", "table*items_edition", "table*categories", "table*categories_items", "table*categories_folders", "table*api", "table*otv", "table*suggestion", "table*tokens", "table*items_change"];
        multiple = true;
    } else if (step === "6") {
    // STEP 6
        const jsonValues = {"url_path":sanitizeString($("#hid_url_path").val())};
        data = JSON.stringify(jsonValues);
        tasks = ["file*sk.php", "file*security", "install*cleanup", "file*settings.php", "file*csrfp-token"];
        multiple = true;
    }

    // launch query
    if (error === "" && multiple === true) {
        var ajaxReqs = [];

        const dbInfo = {"db_host" : $("#hid_db_host").val(), "db_bdd" : $("#hid_db_bdd").val(), "db_login" : $("#hid_db_login").val(), "db_pw" : $("#hid_db_pwd").val(), "db_port" : $("#hid_db_port").val(), "db_pre" : $("#hid_db_pre").val()};

        $("#step_result").html("Please wait <img src=\"images/ajax-loader.gif\">");
        $("#step_res").val("true");
        $("#pop_db").html("");

        for (index = 0; index < tasks.length; ++index) {
            tsk = tasks[index].split("*");
            ajaxReqs.push($.ajax({
                url: "install.queries.php",
                type : "POST",
                dataType : "json",
                data : {
                    type:       "step_"+step,
                    data:       aesEncrypt(data), //
                    activity:   aesEncrypt(tsk[0]),
                    task:       aesEncrypt(tsk[1]),
                    db:         aesEncrypt(JSON.stringify(dbInfo)),
                    index:      index,
                    multiple:   multiple,
                    info:       tsk[0]+"-"+tsk[1]
                },
                complete : function(data){
                    if (data.responseText === "") {
                        // stop error occured, PHP5.5 installed?
                        $("#step_result").html("[ERROR] Answer from server is empty.");
                    } else {
                        data = $.parseJSON(data.responseText);
                        if (data[0].error === "") {
                            if (step === "5") {
                                if (data[0].activity === "table") {
                                    $("#pop_db").append("<li>Table <b>"+data[0].task+"</b> created</li>");
                                } else if (data[0].activity === "entry") {
                                    $("#pop_db").append("<li>Entries <b>"+data[0].task+"</b> were added</li>");
                                }
                            } else {
                                $("#res"+step+"_check"+data[0].index).html("<img src=\"images/tick.png\">");
                            }

                            if (data[0].result !== undefined && data[0].result !== "" ) {
                                $("#step_result").html(data[0].result);
                            }
                        } else {
                            // ignore setting error if regarding setting permissions (step 6, index 2)
                            if (step+data[0].index !== "62") {
                                //$("#step_res").val("false");
                            }
                            $("#res"+step+"_check"+data[0].index).html("<img src=\"images/exclamation-red.png\">&nbsp;<i>"+data[0].error+"</i>");
                            $("#pop_db").append("<li><img src=\"images/exclamation-red.png\">&nbsp;Error on task `<b>"+data[0].activity+" > "+data[0].task+"`</b>. <i>"+data[0].error+"</i></li>");
                            if (data[0].result !== undefined && data[0].result !== "" ) {
                                $("#step_result").html(data[0].result);
                            }
                        }
                    }
                }
            }));
        }
        $.when.apply($, ajaxReqs).done(function(data) {
            setTimeout(function(){
                // all requests are complete
                if ($("#step_res").val() === "false") {
                    $("#step_error").removeClass("hidden").html("At least one task has failed! Please correct and relaunch. ");
                    $("#res_"+step).html("<img src=\"images/exclamation-red.png\">");
                } else {
                    $("#but_launch").prop("disabled", true);
                    $("#but_launch").addClass("hidden");
                    $("#but_next").prop("disabled", false);
                    $("#but_next").removeClass("hidden");
                    // Hide restart button at end of step 6 if successful
                    if (step === "6") {
                        $("#but_restart").prop("disabled", true);
                        $("#but_restart").addClass("hidden");
                    }
                }
                $("#step_result").html("");
            }, 1000);
        });
    } else if (error === "" && multiple === "") {
        $("#step_result").html("Please wait <img src=\"images/ajax-loader.gif\">");
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
                $("#step_result").html("");
                if (data[0].error !== "" ) {
                    $("#step_error").removeClass("hidden").html("The next ERROR occurred: <i>'"+data[0].error+"'</i><br />Please correct and relaunch.");
                    $("#res_"+step).html("<img src=\"images/exclamation-red.png\">");
                } else {
                    if (data[0].result !== undefined && data[0].result !== "" ) {
                        $("#step_result").html("<span style=\"font-weight:bold; margin-right:20px;\">"+data[0].result+"</span>");
                    }
                    $("#but_launch").prop("disabled", true);
                    $("#but_launch").addClass("hidden");
                    $("#but_next").prop("disabled", false);
                    $("#but_next").removeClass("hidden");
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
