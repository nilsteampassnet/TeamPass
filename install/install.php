<?php
require_once('../sources/sessions.php');
session_start();
// Session teampass tag
$_SESSION['CPM'] = 1;

if ( file_exists('../includes/settings.php')){
   echo '
	<head>
	<title>TeamPass Installation</title>
	<link rel="stylesheet" href="install.css" type="text/css" />
	</head>
<div style="position:absolute;top:49%;left:49%;display:none;z-index:9999999;" id="loader"><img src="../includes/images/76.gif" /></div>
        <div id="top">
            <div id="logo"><img src="../includes/images/canevas/logo.png" /></div>
        </div>
        <div id="content">
            <div id="center" class="ui-corner-bottom">
                <form name="install" method="post" action="">
	<h2>Teampass installation complete<h2>';

 exit;
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
        <title>TeamPass Installation</title>
        <link rel="stylesheet" href="install.css" type="text/css" />
        <script type="text/javascript" src="../includes/js/functions.js"></script>
        <script type="text/javascript" src="install.js"></script>
        <script type="text/javascript" src="gauge/gauge.js"></script>
        <script type="text/javascript" src="js/jquery.min.js"></script>
        <script type="text/javascript" src="js/jquery-ui.min.js"></script>
        <script type="text/javascript" src="js/aes.min.js"></script>

        <script type="text/javascript">
        <!-- // --><![CDATA[
        //if (typeof $=='undefined') {function $(v) {return(document.getElementById(v));}}
        $(function() {
            if ($("#progressbar")) {
                gauge.add($("progressbar"), { width:600, height:30, name: 'pbar', limit: true, gradient: true, scale: 10, colors:['#ff0000','#00ff00']});
                if ($("#step").val() == "1") gauge.modify($('pbar'),{values:[0.10,1]});
                else if ($("#step").val() == "2") gauge.modify($('pbar'),{values:[0.30,1]});
                else if ($("#step").val() == "3") gauge.modify($('pbar'),{values:[0.50,1]});
                else if ($("#step").val() == "4") gauge.modify($('pbar'),{values:[0.70,1]});
                else if ($("#step").val() == "5") gauge.modify($('pbar'),{values:[0.90,1]});
                else if ($("#step").val() == "6") gauge.modify($('pbar'),{values:[1,1]});
            }

            //DB PW non accepted characters management
            $("#db_pw").keypress(function (e) {
                var key = e.charCode || e.keyCode || 0;
                if (key == 32) alert('No space character is allowed for password');
                // allow backspace, tab, delete, arrows, letters, numbers and keypad numbers ONLY
                return (
                    (key >= 8 && key <= 31) ||
                    (key >= 33 && key <= 222)
               );
            });

            //SALT KEY non accepted characters management
            $("#encrypt_key").keypress(function (e) {
                var key = e.charCode || e.keyCode || 0;
                if ($("#encrypt_key").val().length < 15)
                    $("#encrypt_key_res").html("<img src='../includes/images/cross.png' />");
                else
                    $("#encrypt_key_res").html("<img src='../includes/images/tick.png' />");
                // allow backspace, tab, delete, arrows, letters, numbers and keypad numbers ONLY
                return (
                    key != 33 && key != 34 && key != 39 && key != 92 && key != 32  && key != 96
                    && key != 44 && key != 38 && key != 94 && (key < 122)
                    && $("#encrypt_key").val().length <= 32
               );
            });

        	// no paste
        	$('#encrypt_key').bind("paste",function(e) {
        		alert('Paste option is disabled !!');
        		e.preventDefault();
        	});
        });

        function aes_encrypt(text)
        {
            return Aes.Ctr.encrypt(text, "cpm", 128);
        }

        function goto_next_page(page)
        {
            document.getElementById("step").value=page;
            document.install.submit();
        }

        function Check(step)
        {
            if (step != "") {
                var data;
                var error = "";

                if (step == "step1") {
                    document.getElementById("loader").style.display = "";
                    document.getElementById("url_path_res").innerHTML = "";
                    //Check if last slash exists. If yes, then warn
                    if (document.getElementById("url_path").value.lastIndexOf("/") == (document.getElementById("url_path").value.length-1)) {
                        document.getElementById("url_path_res").innerHTML = "<img src='images/exclamation-red.png' /> No end slash!";
                    } else {
                        data = "type="+step+
                        "&abspath="+escape(document.getElementById("root_path").value);
                    }
                } else
                if (step == "step2") {
                        $("#error_db").hide();
                    document.getElementById("loader").style.display = "none";
                    data = "type="+step+
                    "&db_host="+document.getElementById("db_host").value+
                    "&db_login="+escape(document.getElementById("db_login").value)+
                    "&db_password="+aes_encrypt(document.getElementById("db_pw").value)+
                    "&db_bdd="+document.getElementById("db_bdd").value;

                    if (document.getElementById("db_pw").value.indexOf('"') != -1) error = "DB Password should not contain a double quotes!<br>";
                    if (document.getElementById("db_login").value.indexOf('"') != -1) error += "DB Login should not contain a double quotes!<br>";
                    if (document.getElementById("db_bdd").value.indexOf('"') != -1) error += "DB should not contain a double quotes!";
                } else
                if (step == "step3") {
                    document.getElementById("loader").style.display = "";
                    var status = true;
                    if (document.getElementById("tbl_prefix").value != "")
                        document.getElementById("tbl_prefix_res").innerHTML = "<img src='images/tick.png'>";
                    else{
                        document.getElementById("tbl_prefix_res").innerHTML = "<img src='images/exclamation-red.png'>";
                        status = false;
                    }

                    //Check if saltkey is okay
                    var key_val = false;
                    var key_length = false;
                    var key_char = false;
                    if (document.getElementById("encrypt_key").value != "")key_val = true;
                    else{
                        document.getElementById("encrypt_key_res").innerHTML = "<img src='images/exclamation-red.png'> No value!";
                        status = false;
                    }
                    if (document.getElementById("encrypt_key").value.length >= 15 && document.getElementById("encrypt_key").value.length <= 32)
                        key_length = true;
                    else{
                        document.getElementById("encrypt_key_res").innerHTML = "<img src='images/exclamation-red.png'> 15 to 32 characters!";
                        status = false;
                    }
                    if (key_val == true && key_length == true && key_char == true)
                        document.getElementById("encrypt_key_res").innerHTML = "<img src='images/tick.png'>";

                    //check if sk path is okay
                    if (document.getElementById("sk_path").value != "") {
                    	if (document.getElementById("sk_path").value.lastIndexOf("/") == document.getElementById("sk_path").value.length-1) {
                            document.getElementById("sk_path_res").innerHTML = "<img src='images/exclamation-red.png' /> No end slash!";
                        } else {
                            data = "type="+step+
                            "&skPath="+document.getElementById("sk_path").value;
                        }
                    } else{
                        document.getElementById("sk_path_res").innerHTML = "<img src='images/exclamation-red.png'>";
                        status = false;
                    }

                    /*if (status == true) {
                        gauge.modify($('pbar'),{values:[0.60,1]});
                        document.getElementById("but_next").disabled = "";
                    }*/
                } else
                if (step == "step4") {
                    document.getElementById("loader").style.display = "";
                    data = "type="+step;
                } else
                if (step == "step5") {
                	document.getElementById("res_step5").innerHTML = "Please wait... <img src=\"install/images/ajax-loader.gif\" />";
                    document.getElementById("loader").style.display = "";
                    data = "type="+step;
                }

                if (data && error == "") httpRequest("install_ajax.php",data);

                if (error != "") {
                    $("#error_db").html(error);
                    $("#error_db").show();
                }
            }
        }

        /**
         *  * Generate a new password and copy it to the password input areas
         *   *
         *    * @param   object   the form that holds the password fields
         *     *
         *      * @return  boolean  always true
         *       */
        function suggestKey(passwd_form) {
            // restrict the password to just letters and numbers to avoid problems:
            // "editors and viewers regard the password as multiple words and
            // things like double click no longer work"
                 var pwchars = "abcdefhjmnpqrstuvwxyz23456789ABCDEFGHJKLMNPQRSTUVWYXZ";
                 var passwordlength = 28;    // length of the salt
                 var passwd = passwd_form.encrypt_key;
                 passwd.value = '';

                 for ( i = 0; i < passwordlength; i++ ) {
                    passwd.value += pwchars.charAt( Math.floor( Math.random() * pwchars.length ) )
                 }
                 passwd_form.encrypt_key.value = passwd.value;
                 return true;
        }
        // ]]>
        </script>
    </head>
    <body>
<?php
require_once '../includes/language/english.php';
require_once '../includes/include.php';
// # LOADER
echo '
    <div style="position:absolute;top:49%;left:49%;display:none;z-index:9999999;" id="loader"><img src="../includes/images/76.gif" /></div>';
// # HEADER ##
echo '
        <div id="top">
            <div id="logo"><img src="../includes/images/canevas/logo.png" /></div>
        </div>
        <div id="content">
            <div id="center" class="ui-corner-bottom">
                <form name="install" method="post" action="">';
// Hidden things
echo '
                    <input type="hidden" id="step" name="step" value="', isset($_POST['step']) ? $_POST['step']:'', '" />
                    <input type="hidden" name="menu_action" id="menu_action" value="" />';

if (!isset($_GET['step']) && !isset($_POST['step'])) {
    // ETAPE O
    echo '
                    <h2>This page will help you through the installation process of TeamPass</h2>

                    Before starting, be sure to:<br />
                    - upload the complete package on the server,<br />
                    - have the database connection informations (*),<br />
                    - get some CHMOD rights on the server.<br />
                    <br />
                    <br />
                    <i>* Mysql database suggestions:<br />
                    - create a new database (for example teampass),<br />
                    - create a new mysql user (for example teampass_root),<br />
                    - set full admin rights for this user on teampass table,<br />
                    - allow access from localhost to the database<br /></i>';

    echo '
                <div style="" class="ui-widget ui-state-highlight">
                     <h4>TeamPass is distributed under GNU AFFERO GPL licence.</h4>';
    // Display the license file
    $Fnm = "../license.txt";
    if (file_exists($Fnm)) {
        $tab = file($Fnm);
        echo '
                    <div style="float:left;width:100%;height:250px;overflow:auto;">
                        <div style="float:left;font-style:italic;">';
        $show = false;
        $cnt = 0;
        while (list($cle, $val) = each($tab)) {
            echo $val."<br />";
        }
        echo '
                        </div>
                    </div>';
    }
    echo '
                 </div>
                 &nbsp;
                 ';
} elseif ((isset($_POST['step']) && $_POST['step'] == 1) || (isset($_GET['step']) && $_GET['step'] == 1)) {
    // define root path
    $abs_path = "";
    if (strrpos($_SERVER['DOCUMENT_ROOT'], "/") == 1) {
        $abs_path = strlen($_SERVER['DOCUMENT_ROOT']) - 1;
    } else {
        $abs_path = $_SERVER['DOCUMENT_ROOT'];
    }
    $abs_path .= substr($_SERVER['PHP_SELF'], 0, strlen($_SERVER['PHP_SELF']) - 20);
    // ETAPE 1
    echo '
                    <h3>Step 1 - Check server</h3>

                    <fieldset><legend>Please give me</legend>
                    <label for="root_path" style="width:300px;">Absolute path to teampass folder :</label><input type="text" id="root_path" name="root_path" class="step" style="width:560px;" value="'.$abs_path.'" /><br />
                    <label for="url_path" style="width:300px;">Full URL to teampass :</label><input type="text" id="url_path" name="url_path" class="step" style="width:560px;" value="http://'.$_SERVER['HTTP_HOST'].substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/') - 8).'" /><span style="padding-left:10px;" id="url_path_res"></span><br />
                    </fieldset>

                    <h4>Next elements will be checked.</h4>
                    <div style="margin:15px;" id="res_step1">
                    <span style="padding-left:30px;font-size:13pt;">File "settings.php" is writable</span><br />
                    <span style="padding-left:30px;font-size:13pt;">Directory "/install/" is writable</span><br />
                    <span style="padding-left:30px;font-size:13pt;">Directory "/includes/" is writable</span><br />
                    <span style="padding-left:30px;font-size:13pt;">Directory "/files/" is writable</span><br />
                    <span style="padding-left:30px;font-size:13pt;">Directory "/upload/" is writable</span><br />
                    <span style="padding-left:30px;font-size:13pt;">PHP extension "mcrypt" is loaded</span><br />
                    <span style="padding-left:30px;font-size:13pt;">PHP extension "openssl" is loaded</span><br />
                    <span style="padding-left:30px;font-size:13pt;">PHP extension "gmp" is loaded</span><br />
                    <span style="padding-left:30px;font-size:13pt;">PHP extension "bcmath" is loaded</span><br />
                    <span style="padding-left:30px;font-size:13pt;">PHP extension "iconv" is loaded</span><br />
                    <span style="padding-left:30px;font-size:13pt;">PHP version is gretter or equal to 5.3.0</span><br />
                    </div>
                    <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="status_step1"></div>';
} elseif ((isset($_POST['step']) && $_POST['step'] == 2) || (isset($_GET['step']) && $_GET['step'] == 2)) {
    $_SESSION['root_path'] = $_POST['root_path'];
    $_SESSION['url_path'] = $_POST['url_path'];
    // ETAPE 2
    echo '
                    <h3>Step 2</h3>
                    <fieldset><legend>dataBase Informations</legend>
                    <label for="db_host">Host :</label><input type="text" id="db_host" name="db_host" class="step" /><br />
                    <label for="db_db">dataBase name :</label><input type="text" id="db_bdd" name="db_bdd" class="step" /><br />
                    <label for="db_login">Login :</label><input type="text" id="db_login" name="db_login" class="step" /><br />
                    <label for="db_pw">Password :</label><input type="text" id="db_pw" name="db_pw" class="step" tilte="Double quotes not allowed!" />
                                        <br />
                                        <div id="error_db" style="display:none;font-size:18px;color:red;margin:10px 0 10px 0;"></div>
                    </fieldset>

                    <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step2"></div>
                    <input type="hidden" id="step2" name="step2" value="" />';
} elseif ((isset($_POST['step']) && $_POST['step'] == 3) || (isset($_GET['step']) && $_GET['step'] == 3)) {
    $_SESSION['db_host'] = $_POST['db_host'];
    $_SESSION['db_bdd'] = $_POST['db_bdd'];
    $_SESSION['db_login'] = $_POST['db_login'];
    $_SESSION['db_pw'] = $_POST['db_pw'];
    // ETAPE 3
    echo '
                    <h3>Step 3</h3>
                    <fieldset><legend>Give me some informations</legend>
                    <label for="tbl_prefix" style="width:300px;">Table prefix :</label><input type="text" id="tbl_prefix" name="tbl_prefix" class="step" value="teampass_" onblur /><span style="padding-left:10px;" id="tbl_prefix_res"></span><br />

                    <label for="encrypt_key" style="width:300px;">Encryption key (SaltKey): <img src="../includes/images/information-white.png" alt="" title="For security reasons, salt key must be more than 15 characters and less than 32, should contains upper and lower case letters, special characters and numbers, and SHALL NOT CONTAINS single quotes!!!">
                        <span style="font-size:9pt;font-weight:normal;"><br />for passwords encryption in database</span>
                    </label>
                    <input type="text" id="encrypt_key" name="encrypt_key" class="step"  /><input type="button" id="button_generate_key" value="Generate" onclick="suggestKey(this.form)" /><span style="padding-left:10px;" id="encrypt_key_res"></span><br /><br />

                    <label for="sk_path" style="width:300px;">Absolute path to SaltKey :
                        <img src="../includes/images/information-white.png" alt="" title="The SaltKey is stored in a file called sk.php. But for security reasons, this file should be stored in a folder outside the www folder of your server. So please, indicate here the path to this folder. <br> If this field remains empty, this file will be stored in folder \"/includes\".">
                    </label><input type="text" id="sk_path" name="sk_path" class="step" value="" /><span style="padding-left:10px;" id="sk_path_res"></span><br /><br />

                    <label for="smtp_server" style="width:300px;">SMTP server :<span style="font-size:9pt;font-weight:normal;"><br />Email server configuration</span></label><input type="text" id="smtp_server" name="smtp_server" class="step" value="smtp.my_domain.com" /><br /><br />

                    <label for="smtp_auth" style="width:300px;">SMTP authorization:<span style="font-size:9pt;font-weight:normal;"><br />false or true</span></label><input type="text" id="smtp_auth" name="smtp_auth" class="step" value="false" /><br /><br />

                    <label for="smtp_auth_username" style="width:300px;">SMTP authorization username :</label><input type="text" id="smtp_auth_username" name="smtp_auth_username" class="step" value="" /><br />

                    <label for="smtp_auth_password" style="width:300px;">SMTP authorization password :</label><input type="text" id="smtp_auth_password" name="smtp_auth_password" class="step" value="" /><br />

                    <label for="smtp_port" style="width:300px;">SMTP Port :</label><input type="text" id="smtp_port" name="smtp_port" class="step" value="25" /><br />

                    <label for="email_from" style="width:300px;">Email from :</label><input type="text" id="email_from" name="email_from" class="step" value=""  /><br />

                    <label for="email_from_name" style="width:300px;">Email from name :</label><input type="text" id="email_from_name" name="email_from_name" class="step" value="" />
                    </fieldset>

                    <fieldset><legend>Anonymous statistics</legend>
                    <input type="checkbox" name="send_stats" id="send_stats" />Send monthly anonymous statistics.<br />
                    Please considere sending your statistics as a way to contribute to futur improvments of teampass. Indeed this will help the creator to evaluate how the tool is used and by this way how to improve the tool. When enabled, the tool will automatically send once by month a bunch of statistics without any action from you. Of course, those data are absolutely anonymous and no data is exported, just the next informations : number of users, number of folders, number of items, tool version, ldap enabled, and personal folders enabled.<br>
                    This option can be enabled or disabled through the administration panel.
                    </fieldset>

                    <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step3"></div>  ';
} elseif ((isset($_POST['step']) && $_POST['step'] == 4) || (isset($_GET['step']) && $_GET['step'] == 4)) {
    if (!isset($_POST['tbl_prefix']) || (isset($_POST['tbl_prefix']) && empty($_POST['tbl_prefix']))) {
        $_SESSION['tbl_prefix'] = "";
    } else {
        $_SESSION['tbl_prefix'] = $_POST['tbl_prefix'];
    }
    $_SESSION['encrypt_key'] = $_POST['encrypt_key'];
    $_SESSION['sk_path'] = $_POST['sk_path'];
    $_SESSION['smtp_server'] = $_POST['smtp_server'];
    $_SESSION['smtp_auth'] = $_POST['smtp_auth'];
    $_SESSION['smtp_auth_username'] = $_POST['smtp_auth_username'];
    $_SESSION['smtp_auth_password'] = $_POST['smtp_auth_password'];
    $_SESSION['smtp_port'] = $_POST['smtp_port'];
    $_SESSION['email_from'] = $_POST['email_from'];
    $_SESSION['email_from_name'] = $_POST['email_from_name'];
    if (isset($_POST['send_stats'])) {
        $_SESSION['send_stats'] = $_POST['send_stats'];
    } else {
        $_SESSION['send_stats'] = "";
    }
    // ETAPE 4
    echo '
                    <h3>Step 4</h3>
                    <fieldset><legend>Populate the dataBase</legend>
                    The installer will now update your database.
                    <table>
                        <tr><td>Add table "items"</td><td><span id="tbl_2"></span></td></tr>
                        <tr><td>Add table "log_items"</td><td><span id="tbl_3"></span></td></tr>
                        <tr><td>Add table "misc"</td><td><span id="tbl_4"></span></td></tr>
                        <tr><td>Add table "nested_tree"</td><td><span id="tbl_5"></span></td></tr>
                        <tr><td>Add table "rights"</td><td><span id="tbl_6"></span></td></tr>
                        <tr><td>Add table "users"</td><td><span id="tbl_7"></span></td></tr>
                        <tr><td>Add Admin account</td><td><span id="tbl_8"></span></td></tr>
                        <tr><td>Add table "tags"</td><td><span id="tbl_9"></span></td></tr>
                        <tr><td>Add table "log_system"</td><td><span id="tbl_10"></span></td></tr>
                        <tr><td>Add table "files"</td><td><span id="tbl_11"></span></td></tr>
                        <tr><td>Add table "cache"</td><td><span id="tbl_12"></span></td></tr>
                        <tr><td>Add table "roles_title"</td><td><span id="tbl_13"></span></td></tr>
                        <tr><td>Add table "roles_values"</td><td><span id="tbl_14"></span></td></tr>
                        <tr><td>Add table "kb"</td><td><span id="tbl_15"></span></td></tr>
                        <tr><td>Add table "kb_categories"</td><td><span id="tbl_16"></span></td></tr>
                        <tr><td>Add table "kb_items"</td><td><span id="tbl_17"></span></td></tr>
                        <tr><td>Add table "restriction_to_roles"</td><td><span id="tbl_18"></span></td></tr>
                        <tr><td>Add table "keys"</td><td><span id="tbl_19"></span></td></tr>
                        <tr><td>Add table "languages"</td><td><span id="tbl_20"></span></td></tr>
                        <tr><td>Add table "emails"</td><td><span id="tbl_21"></span></td></tr>
                        <tr><td>Add table "automatic_del"</td><td><span id="tbl_22"></span></td></tr>
                        <tr><td>Add table "items_edition"</td><td><span id="tbl_23"></span></td></tr>
                        <tr><td>Add table "categories"</td><td><span id="tbl_24"></span></td></tr>
                        <tr><td>Add table "categories_items"</td><td><span id="tbl_25"></span></td></tr>
                        <tr><td>Add table "categories_folders"</td><td><span id="tbl_26"></span></td></tr>
                    </table>
                    </fieldset>

                    <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step4"></div>  ';
} elseif ((isset($_POST['step']) && $_POST['step'] == 5) || (isset($_GET['step']) && $_GET['step'] == 5)) {
    // ETAPE 5
    echo '
                    <h3>Step 5 - Miscellaneous</h3>
                    This step will:<br />
                    - write the new setting.php file for your server configuration <span id="step5_settingFile"></span><br />
                    - write the new sk.php file for data encryption <span id="step5_skFile"></span><br />
                    Click on the button when ready.

                    <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step5"></div>  ';
} elseif ((isset($_POST['step']) && $_POST['step'] == 6) || (isset($_GET['step']) && $_GET['step'] == 6)) {
    // ETAPE 6
    echo '
                    <h3>Step 6</h3>
                    Installation is now finished!<br />
                    You can log as an Administrator by using login <b>admin</b> and password <b>admin</b>.<br />
                    You can delete "Install" directory from your server for more security, and change the CHMOD on the "/includes" directory.<br /><br />
                    For news, help and information, visit the <a href="http://teampass.net" target="_blank">TeamPass website</a>.';
}
// buttons
if (!isset($_POST['step'])) {
    echo '
                    <div id="buttons_bottom">
                        <input type="button" id="but_next" onclick="goto_next_page(\'1\')" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="NEXT" />
                    </div>';
} elseif ($_POST['step'] == 6) {
    echo '
                    <div id="buttons_bottom">
                        <input type="button" id="but_next" onclick="javascript:window.location.href=\'', (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ? 'https' : 'http', '://'.$_SERVER['HTTP_HOST'].substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/') - 8).'\';" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="Open TeamPass" />
                    </div>';
} else {
    echo '
                    <div style="width:900px;margin:auto;margin-top:30px;">
                        <div id="progressbar" style="float:left;margin-top:9px;"></div>
                        <div id="buttons_bottom">
                            <input type="button" id="but_launch" onclick="Check(\'step'.$_POST['step'].'\')" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="LAUNCH" />
                            <input type="button" id="but_next" onclick="goto_next_page(\''.(intval($_POST['step']) + 1).'\')" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="NEXT" disabled="disabled" />
                        </div>
                    </div>';
}

echo '
                </form>
            </div>
            </div>';
// FOOTER
// # DON'T MODIFY THE FOOTER ###
echo '
    <div id="footer">
        <div style="width:500px;">
            '.$k['tool_name'].' '.$k['version'].' &copy; copyright 2010-2012
        </div>
        <div style="float:right;margin-top:-15px;">

        </div>
    </div>';

?>
    </body>
</html>
