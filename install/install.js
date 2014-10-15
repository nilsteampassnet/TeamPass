/**
 * Created by nlaumail on 23/07/14.
 */

$(function() {
    $(".button").button();
    $("#but_launch, #step_error").hide();

    //SALT KEY non accepted characters management
    $("#encrypt_key").keypress(function (e) {
        var key = e.charCode || e.keyCode || 0;
        if ($("#encrypt_key").val().length < 15)
            $("#res4_check1").html("<img src='../includes/images/cross.png' />");
        else
            $("#res4_check1").html("<img src='../includes/images/tick.png' />");
        // allow backspace, tab, delete, arrows, letters, numbers and keypad numbers ONLY
        return (
            key != 33 && key != 34 && key != 39 && key != 92 && key != 32  && key != 96
                && key != 44 && key != 38 && key != 94 && (key < 122)
                && $("#encrypt_key").val().length <= 32
        );
    });

    // no paste
    $('#encrypt_key, #admin_pwd').bind("paste",function(e) {
        alert('Paste option is disabled !!');
        e.preventDefault();
    });
});

function CheckPage()
{
    var step = $("#page_id").val();
    var data;
    var error = "";
    var index;
    var tasks = [];
    var multiple = "";
    var error_msg = "";
    $("#step_error").hide().html("");
    $("#res_"+step).html("");

    // STEP 2
    if (step == "2") {
        if ($("#url_path").val() == "" || $("#root_path").val() == "") {
            error = "Fields need to be filled in!";
        } else {
            data = '{"root_path":"'+$("#root_path").val()+'", "url_path":"'+$("#url_path").val()+'"}';
            tasks = ["folder*install", "folder*includes", "folder*files", "folder*upload", "extension*mcrypt", "extension*mbstring", "extension*openssl", "extension*gmp", "extension*bcmath", "extension*iconv", "version*php", "ini*max_execution_time"];
            multiple = true;
        }
    }

    // STEP 3
    if (step == "3") {
        if ($("#db_host").val() == "" || $("#db_db").val() == "" || $("#db_login").val() == "" || $("#db_port").val() == "") {
            error = "Paths need to be filled in!";
        } else {
            data = '{"db_host":"'+$("#db_host").val()+'", "db_bdd":"'+$("#db_bdd").val()+'", "db_login":"'+$("#db_login").val()+'", "db_pw":"'+$("#db_pw").val()+'", "db_port":"'+$("#db_port").val()+'"}';
            tasks = ["connection*test"];
            multiple = "";
        }
    }

    // STEP 4
    if (step == "4") {
        if ($("#encrypt_key").val() == "") {
            error = "You must define a SALTkey!";
        } else if ($("#admin_pwd").val() == "") {
            error = "You must define a password for Admin account!";
        } else{
            data = '{"tbl_prefix":"'+sanitizeString($("#tbl_prefix").val())+'", "encrypt_key":"'+sanitizeString($("#encrypt_key").val())+'", "sk_path":"'+sanitizeString($("#sk_path").val())+'", "smtp_server":"'+sanitizeString($("#smtp_server").val())+'", "smtp_auth":"'+sanitizeString($("#smtp_auth").val())+'", "smtp_auth_username":"'+sanitizeString($("#smtp_auth_username").val())+'", "smtp_auth_password":"'+sanitizeString($("#smtp_auth_password").val())+'", "smtp_port":"'+sanitizeString($("#smtp_port").val())+'", "email_from":"'+sanitizeString($("#email_from").val())+'", "email_from_name":"'+sanitizeString($("#email_from_name").val())+'", "admin_pwd":"'+sanitizeString($("#admin_pwd").val())+'", "send_stats":"'+$("#send_stats").prop("checked")+'"}';
            tasks = ["misc*preparation"];
            multiple = "";
        }
    }

    // STEP 5
    if (step == "5") {
        data = '';
        tasks = ["table*items", "table*log_items", "table*misc", "table*nested_tree", "table*rights", "table*users", "entry*admin", "table*tags", "table*log_system", "table*files", "table*cache", "table*roles_title", "table*roles_values", "table*kb", "table*kb_categories", "table*kb_items", "table*restriction_to_roles", "table*keys", "table*languages", "table*emails", "table*automatic_del", "table*items_edition", "table*categories", "table*categories_items", "table*categories_folders", "table*api", "table*otv", "table*suggestion"];
        multiple = true;
    }

    // STEP 6
    if (step == "6") {
        data = '';
        tasks = ["file*settings.php", "file*sk.php"];
        multiple = true;
    }

    // launch query
    if (error == "" && multiple == true) {
        $("#step_result").html("Please wait <img src=\"images/ajax-loader.gif\">");
        var globalResult = true;
        $("#step_res").val("true");
        var ajaxReqs = [];
        for (index = 0; index < tasks.length; ++index) {
            var tsk = tasks[index].split("*");
            ajaxReqs.push($.ajax({
                url: "install.queries.php",
                type : 'POST',
                dataType : "json",
                data : {
                    type:       "step_"+step,
                    data:       aes_encrypt(data), //
                    activity:   aes_encrypt(tsk[0]),
                    task:       aes_encrypt(tsk[1]),
                    index:      index,
                    multiple:   multiple
                },
                complete : function(data, statut){
                    data = $.parseJSON(data.responseText);
                    if (data[0].error == "" ) {
                        $("#res"+step+"_check"+data[0].index).html("<img src=\"images/tick.png\">");
                        if (data[0].result != undefined && data[0].result != "" ) {
                            $("#step_result").html(data[0].result);
                        }
                    } else {
                        $("#step_res").val("false");
                        $("#res"+step+"_check"+data[0].index).html("<img src=\"images/exclamation-red.png\">&nbsp;<i>"+data[0].error+"</i>");
                        if (data[0].result != undefined && data[0].result != "" ) {
                            $("#step_result").html(data[0].result);
                        }
                    }
                }
            }));
        }
        $.when.apply($, ajaxReqs).done(function() {
            setTimeout(function(){
                // all requests are complete
                if ($("#step_res").val() == "false") {
                    $("#step_error").show().html("At least one task has failed! Please correct and relaunch.");
                    $("#res_"+step).html("<img src=\"images/exclamation-red.png\">");
                } else {
                    $("#but_launch").prop("disabled", true);
                    $("#but_next").prop("disabled", false);
                }
                $("#step_result").html("");
            }, 1000);
        });
    } else if (error == "" && multiple == "") {
        $("#step_result").html("Please wait <img src=\"images/ajax-loader.gif\">");
        var tsk = tasks[0].split("*");
        $.ajax({
            url: "install.queries.php",
            type : 'POST',
            dataType : "json",
            data : {
                type:       "step_"+step,
                data:       aes_encrypt(data),
                activity:   aes_encrypt(tsk[0]),
                task:       aes_encrypt(tsk[1]),
                index:      index,
                multiple:   multiple
            },
            complete : function(data, statut){
                data = $.parseJSON(data.responseText);
                $("#step_result").html("");
                if (data[0].error != "" ) {
                    $("#step_error").show().html("The next ERROR occurred: <i>'"+data[0].error+"'</i><br />Please correct and relaunch.");
                    $("#res_"+step).html("<img src=\"images/exclamation-red.png\">");
                } else {
                    if (data[0].result != undefined && data[0].result != "" ) {
                        $("#step_result").html("<span style=\"font-weight:bold; margin-right:20px;\">"+data[0].result+"</span>");
                    }
                    $("#but_launch").prop("disabled", true);
                    $("#but_next").prop("disabled", false);
                }
            },
            error : function(resultat, statut, erreur){

            }
        });
    } else {
        $("#step_error").show().html(error);
    }
}


function GotoNextStep()
{
    var step = $("#page_id").val();
    var nextStep = parseInt(step)+1;

    if (nextStep == 7) {
        $("#but_launch, #but_next").hide();
        $("#step_result").html("Installation finished.");
        $("#step_name").html($("#menu_step"+nextStep).html());
        $("#step_content").html($("#text_step"+nextStep).html());
        $("#menu_step"+step).switchClass("li_inprogress", "li_done");
        $("#menu_step"+nextStep).switchClass("", "li_inprogress");
        $("#res_"+step).html("<img src=\"images/tick.png\">");
    } else {
        $("#page_id").val(nextStep);
        $("#but_launch").show().prop("disabled", false);
        $("#but_next").prop("disabled", true);
        $("#menu_step"+step).switchClass("li_inprogress", "li_done");
        $("#menu_step"+nextStep).switchClass("", "li_inprogress");
        $("#res_"+step).html("<img src=\"images/tick.png\">");
        $("#step_result").html("");
        $("#step_name").html($("#menu_step"+nextStep).html());
        $("#step_content").html($("#text_step"+nextStep).html());
        $("#encrypt_key").live('keypress', function(e){
            var key = e.charCode || e.keyCode || 0;
            if ($("#encrypt_key").val().length < 15)
                $("#res4_check1").html("<img src='../includes/images/cross.png' />");
            else
                $("#res4_check1").html("<img src='../includes/images/tick.png' />");
            // allow backspace, tab, delete, arrows, letters, numbers and keypad numbers ONLY
            return (
                key != 33 && key != 34 && key != 39 && key != 92 && key != 32  && key != 96
                    && key != 44 && key != 38 && key != 94 && (key < 122)
                    && $("#encrypt_key").val().length <= 32
            );
        });
        $('#encrypt_key, #admin_pwd').live("paste",function(e) {
            alert('Paste option is disabled !!');
            e.preventDefault();
        });
        $("#admin_pwd").live('keypress', function(e){
            var key = e.charCode || e.keyCode || 0;
            // allow backspace, tab, delete, arrows, letters, numbers and keypad numbers ONLY
            return (
                key != 39
            );
        });
    }
}

function suggestKey() {
    // restrict the password to just letters and numbers to avoid problems:
    // "editors and viewers regard the password as multiple words and
    // things like double click no longer work"
    var pwchars = "abcdefhjmnpqrstuvwxyz23456789ABCDEFGHJKLMNPQRSTUVWYXZ";
    var passwordlength = 28;    // length of the salt
    var passwd = "";

    for ( i = 0; i < passwordlength; i++ ) {
        passwd += pwchars.charAt( Math.floor( Math.random() * pwchars.length ) )
    }
    $("#encrypt_key").val(passwd);
    $("#res4_check1").html("<img src='../includes/images/tick.png' />");
    return true;
}

function aes_encrypt(text)
{
    return Aes.Ctr.encrypt(text, "cpm", 128);
}

function httpRequest(file,data,type) {
    var xhr_object = null;
    var is_chrome = navigator.userAgent.toLowerCase().indexOf('chrome') > -1;

	if (document.getElementById("menu_action") != null) {
		document.getElementById("menu_action").value = "action";
	}

    if(window.XMLHttpRequest) { // Firefox
        xhr_object = new XMLHttpRequest();
    } else if(window.ActiveXObject) { // Internet Explorer
        xhr_object = new ActiveXObject("Microsoft.XMLHTTP");  //Info IE8 now supports =>  xhr_object = new XMLHttpRequest()
    } else { // XMLHttpRequest non support? par le navigateur
        alert("Your browser does not support XMLHTTPRequest objects ...");
        return;
    }

    if (type == "GET") {
        xhr_object.open("GET", file+"?"+data, true);
        xhr_object.send(null);
    } else {
        xhr_object.open("POST", file, true);
        xhr_object.onreadystatechange = function() {
          if(xhr_object.readyState == 4) {
              eval(xhr_object.responseText);
              //Check if query is for user identification. If yes, then reload page.
              if (data != "" && data.indexOf('ype=identify_user') > 0 ) {
                  if (is_chrome == true ) PauseInExecution(100);  //Needed pause for Chrome
                  if (type == "") {
                      if (document.getElementById('erreur_connexion').style.display == "") {
                          //rise an error in url. This in order to display the eror after refreshing
                          window.location.href="index.php?error=rised";
                      } else {
                        window.location.href="index.php";
                      }
                  } else {
                      if (type = "?error=rised") {
                            if (document.getElementById('erreur_connexion').style.display == "none") type = "";   //clean error in url
                            else type = "?error=rised"; //Maintain the ERROR
                      }
                      window.location.href="index.php"+type;
                  }
              }
          }
        }
        xhr_object.setRequestHeader("Content-type", "application/x-www-form-urlencoded; charset=utf-8");
        xhr_object.send(data);
    }
}
