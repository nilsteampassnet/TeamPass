<?php
require_once('../sources/sessions.php');
session_start();
//Session teampass tag
$_SESSION['CPM'] = 1;

################
## Function permits to get the value from a line
################
function getSettingValue($val)
{
    $val = trim(strstr($val, "="));
    return trim(str_replace('"', '', substr($val, 1, strpos($val, ";")-1)));
}

//get infos from SETTINGS.PHP file
$filename = "../includes/settings.php";
$events = "";
if (file_exists($filename)) {    // && empty($_SESSION['server'])
    //copy some constants from this existing file
    $settings_file = file($filename);
    while (list($key,$val) = each($settings_file)) {
        if (substr_count($val,'charset')>0) {
            $_SESSION['charset'] = getSettingValue($val);
        } elseif (substr_count($val,'@define(')>0 && substr_count($val, 'SALT')>0) {
            $_SESSION['encrypt_key'] = substr($val,17,strpos($val,"')")-17);
        } elseif (substr_count($val,'$smtp_server = ')>0) {
            $_SESSION['smtp_server'] = getSettingValue($val);
        } elseif (substr_count($val,'$smtp_auth = ')>0) {
            $_SESSION['smtp_auth'] = getSettingValue($val);
        } elseif (substr_count($val,'$smtp_port = ')>0) {
            $_SESSION['smtp_port'] = getSettingValue($val);
        } elseif (substr_count($val,'$smtp_security = ')>0) {
            $_SESSION['smtp_security'] = getSettingValue($val);
        } elseif (substr_count($val,'$smtp_auth_username = ')>0) {
            $_SESSION['smtp_auth_username'] = getSettingValue($val);
        } elseif (substr_count($val,'$smtp_auth_password = ')>0) {
            $_SESSION['smtp_auth_password'] = getSettingValue($val);
        } elseif (substr_count($val,'$email_from = ')>0) {
            $_SESSION['email_from'] = getSettingValue($val);
        } elseif (substr_count($val,'$email_from_name = ')>0) {
            $_SESSION['email_from_name'] = getSettingValue($val);
        } elseif (substr_count($val,'$server = ')>0) {
            $_SESSION['server'] = getSettingValue($val);
        } elseif (substr_count($val,'$user = ')>0) {
            $_SESSION['user'] = getSettingValue($val);
        } elseif (substr_count($val,'$pass = ')>0) {
            $_SESSION['pass'] = getSettingValue($val);
        } elseif (substr_count($val,'$port = ')>0) {
            $_SESSION['port'] = getSettingValue($val);
        } elseif (substr_count($val,'$database = ')>0) {
            $_SESSION['database'] = getSettingValue($val);
        } elseif (substr_count($val,'$pre = ')>0) {
            $_SESSION['pre'] = getSettingValue($val);
        } elseif (substr_count($val,'require_once "')>0 && substr_count($val, 'sk.php')>0) {
            $_SESSION['sk_path'] = substr($val,14,strpos($val,'";')-14);
        }
    }
}
if (
    isset($_SESSION['sk_file']) && !empty($_SESSION['sk_file'])
    && file_exists($_SESSION['sk_file'])
) {
    //copy some constants from this existing file
    $skFile = file($_SESSION['sk_file']);
    while (list($key,$val) = each($skFile)) {
        if (substr_count($val, '@define(')>0) {
            $_SESSION['encrypt_key'] = substr($val, 17, strpos($val, "')")-17);
        }
    }
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
        <title>TeamPass Installation</title>
        <link rel="stylesheet" href="install.css" type="text/css" />
        <script type="text/javascript" src="../includes/js/functions.js"></script>
        <script type="text/javascript" src="upgrade.js"></script>
        <script type="text/javascript" src="js/jquery.min.js"></script>
        <script type="text/javascript" src="js/jquery-ui.min.js"></script>
        <script type="text/javascript" src="js/aes.min.js"></script>

        <script type="text/javascript">
        //if (typeof $=='undefined') {function $(v) {return(document.getElementById(v));}}
        $(function() {
            /*
            if (document.getElementById("progressbar")) {
                gauge.add($("progressbar"), { width:600, height:30, name: 'pbar', limit: true, gradient: true, scale: 10, colors:['#ff0000','#00ff00']});
                if (document.getElementById("step").value == "1") gauge.modify($('pbar'),{values:[0.20,1]});
                else if (document.getElementById("step").value == "2") gauge.modify($('pbar'),{values:[0.35,1]});
                else if (document.getElementById("step").value == "3") gauge.modify($('pbar'),{values:[0.55,1]});
                else if (document.getElementById("step").value == "4") gauge.modify($('pbar'),{values:[0.70,1]});
                else if (document.getElementById("step").value == "5") gauge.modify($('pbar'),{values:[0.85,1]});
            }
            */
        });

        function aes_encrypt(text)
        {
            return Aes.Ctr.encrypt(text, "cpm", 128);
        }

        function goto_next_page(page)
        {
            if (page == "3" && document.getElementById("cpm_isUTF8").value == 1) {
                page = "4";
            }
            document.getElementById("step").value=page;
            document.install.submit();
        }

        function Check(step)
        {
            if (step != "") {
                if (step == "step1") {
                    var data = "type="+step+
                    "&abspath="+escape(document.getElementById("root_path").value);
                    document.getElementById("loader").style.display = "";
                } else
                if (step == "step2") {
                    document.getElementById("loader").style.display = "";
                	var maintenance = 1;
                	if (document.getElementById("no_maintenance_mode").checked==true) {
                		maintenance = 0;
                	}
                    var data = "type="+step+
                    "&db_host="+document.getElementById("db_host").value+
                    "&db_login="+escape(document.getElementById("db_login").value)+
                    "&tbl_prefix="+escape(document.getElementById("tbl_prefix").value)+
                    "&db_password="+aes_encrypt(document.getElementById("db_pw").value)+
                    "&db_port="+(document.getElementById("db_port").value)+
	            	"&db_bdd="+document.getElementById("db_bdd").value+
	            	"&no_maintenance_mode="+maintenance;
                } else
                if (step == "step3") {
                    document.getElementById("res_step3").innerHTML = '<img src="images/ajax-loader.gif" alt="" />';
                    var data = "type="+step+
                    "&prefix_before_convert="+document.getElementById("prefix_before_convert").checked;
                    document.getElementById("loader").style.display = "";
                } else
                if (step == "step4") {
                    $("#loader").show();
                    var data = "type="+step;
                    document.getElementById("loader").style.display = "";
                } else
                if (step == "step5") {
                	document.getElementById("res_step5").innerHTML = "Please wait... <img src=\"images/ajax-loader.gif\" />";
                    if (document.getElementById("sk_path") == null)
                    	var data = "type="+step;
                    else
                    	var data = "type="+step+"&sk_path="+escape(document.getElementById("sk_path").value);
                }
                httpRequest("upgrade_ajax.php",data);
            }
        }

        function newEncryptPw(suggestion){
            var nb = 10;
            var start = 0;

            if ($("#change_pw_encryption_start").val() != "") {
                start = $("#change_pw_encryption_start").val();
            } else {
                $("#change_pw_encryption_progress").html("Progress: 0% <img src=\"../includes/images/76.gif\" />");
            }
            request = $.post("upgrade_ajax.php",
                {
                    type        : "new_encryption_of_pw",
                    start       : start,
                    total       : $("#change_pw_encryption_total").val(),
                    suggestion  : suggestion,
                    nb          : nb
                },
                function(data) {
                    if (data[0].finish != 1 && data[0].finish != "suggestion") {
                        // handle re-encryption of passwords in Items table
                    	$("#change_pw_encryption_start").val(data[0].next);
                    	$("#change_pw_encryption_progress").html("Progress: "+data[0].progress+"% <img src=\"../includes/images/76.gif\" />");
                    	if (parseInt(start) < parseInt($("#change_pw_encryption_total").val())) {
                    	    newEncryptPw("0");
                    	}
                    } else if (data[0].finish == "suggestion") {
                        // handle the re-encryption of passwords in suggestion table
                        newEncryptPw("1");
                    } else {
                        // handle finishing
                    	$("#change_pw_encryption_progress").html("Done");
                    	$("#but_encrypt_continu").hide();
                    	/* Unlock this step */
                        document.getElementById("but_next").disabled = "";
                        document.getElementById("but_launch").disabled = "disabled";
                        document.getElementById("res_step4").innerHTML = "dataBase has been populated";
                        document.getElementById("loader").style.display = "none";
                    }
                },
                "json"
            );

        }
        </script>
    </head>
    <body>
<?php
require_once '../includes/language/english.php';
require_once '../includes/include.php';

if (isset($_POST['db_host'])) {
    $_SESSION['db_host'] = $_POST['db_host'];
    $_SESSION['db_bdd'] = $_POST['db_bdd'];
    $_SESSION['db_login'] = $_POST['db_login'];
    $_SESSION['db_pw'] = $_POST['db_pw'];
    $_SESSION['db_port'] = $_POST['db_port'];
    $_SESSION['tbl_prefix'] = $_POST['tbl_prefix'];
	//$_SESSION['session_start'] = $_POST['session_start'];
    if (isset($_POST['send_stats'])) {
        $_SESSION['send_stats'] = $_POST['send_stats'];
    } else {
        $_SESSION['send_stats'] = "";
    }
}

// LOADER
echo '
    <div style="position:absolute;top:49%;left:49%;display:none;z-index:9999999;" id="loader">
        <img src="../includes/images/76.gif" />
    </div>';

// HEADER
echo '
        <div id="top">
            <div id="logo"><img src="../includes/images/canevas/logo.png" /></div>
        </div>
        <div id="content">
            <div id="center" class="ui-corner-bottom">
                <form name="install" method="post" action="">';

//HIDDEN THINGS
echo '
                    <input type="hidden" id="step" name="step" value="', isset($_POST['step']) ? $_POST['step']:'', '" />
                    <input type="hidden" id="actual_cpm_version" name="actual_cpm_version" value="', isset($_POST['actual_cpm_version']) ? $_POST['actual_cpm_version']:'', '" />
                    <input type="hidden" id="cpm_isUTF8" name="cpm_isUTF8" value="', isset($_POST['cpm_isUTF8']) ? $_POST['cpm_isUTF8']:'', '" />
                    <input type="hidden" name="menu_action" id="menu_action" value="" />
                    <input type="hidden" name="session_salt" id="session_salt" value="', (isset($_POST['session_salt']) && !empty($_POST['session_salt'])) ? $_POST['session_salt']:@$_SESSION['encrypt_key'], '" />';

if (!isset($_GET['step']) && !isset($_POST['step'])) {
    //ETAPE O
    echo '
                     <h2>This page will help you to upgrade the TeamPass\'s database</h2>

                     Before starting, be sure to:<br />
                     - upload the complete package on the server and overwrite existing files,<br />
                     - have the database connection informations,<br />
                     - get some CHMOD rights on the server.<br />
                     <br />
                     <div style="font-weight:bold; font-size:14px;color:#C60000;"><img src="../includes/images/error.png" />&nbsp;ALWAYS BE SURE TO CREATE A DUMP OF YOUR DATABASE BEFORE UPGRADING</div>
                     <div class="">
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
                                while (list($cle,$val) = each($tab)) {
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

} elseif (
    (isset($_POST['step']) && $_POST['step'] == 1)
    || (isset($_GET['step']) && $_GET['step'] == 1)
) {
//define root path
    $abs_path = "";
    if (strrpos($_SERVER['DOCUMENT_ROOT'],"/") == 1) {
        $abs_path = strlen($_SERVER['DOCUMENT_ROOT'])-1;
    } else {
        $abs_path = $_SERVER['DOCUMENT_ROOT'];
    }
    $abs_path .= substr($_SERVER['PHP_SELF'], 0, strlen($_SERVER['PHP_SELF'])-20);
    //ETAPE 1
    echo '
                     <h3>Step 1 - Check server</h3>

                     <fieldset><legend>Please give me</legend>
                     <label for="root_path" style="width:300px;">Absolute path to TeamPass folder :</label><input type="text" id="root_path" name="root_path" class="step" style="width:560px;" value="'.$abs_path.'" /><br />
                     </fieldset>

                     <h4>Next elements will be checked.</h4>
                     <div style="margin:15px;" id="res_step1">
                     <span style="padding-left:30px;font-size:13pt;">File "settings.php" is writable</span><br />
                     <span style="padding-left:30px;font-size:13pt;">Directory "/install/" is writable</span><br />
                     <span style="padding-left:30px;font-size:13pt;">Directory "/includes/" is writable</span><br />
                     <span style="padding-left:30px;font-size:13pt;">Directory "/includes/avatars/" is writable</span><br />
                     <span style="padding-left:30px;font-size:13pt;">Directory "/files/" is writable</span><br />
                     <span style="padding-left:30px;font-size:13pt;">Directory "/upload/" is writable</span><br />
                     <span style="padding-left:30px;font-size:13pt;">PHP extension "mcrypt" is loaded</span><br />
                     <span style="padding-left:30px;font-size:13pt;">PHP extension "openssl" is loaded</span><br />
                     <span style="padding-left:30px;font-size:13pt;">PHP version is greater or equal to 5.4.0</span><br />
                     </div>
                     <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step1"></div>
                     <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step1_error"></div>
                     <input type="hidden" id="step1" name="step1" value="" />';

} elseif (
    (isset($_POST['step']) && $_POST['step'] == 2)
    || (isset($_GET['step']) && $_GET['step'] == 2)
) {
    //ETAPE 2
    echo '
                     <h3>Step 2</h3>
                     <fieldset><legend>DataBase Informations</legend>
                     <label for="db_host">Host :</label><input type="text" id="db_host" name="db_host" class="step" value="'.$_SESSION['server'].'" /><br />
                     <label for="db_db">DataBase name :</label><input type="text" id="db_bdd" name="db_bdd" class="step" value="'.$_SESSION['database'].'" /><br />
                     <label for="db_login">Login :</label><input type="text" id="db_login" name="db_login" class="step" value="'.$_SESSION['user'].'" /><br />
                     <label for="db_pw">Password :</label><input type="text" id="db_pw" name="db_pw" class="step" value="'.$_SESSION['pass'].'" /><br />
                     <label for="db_port">Port :</label><input type="text" id="db_port" name="db_port" class="step" value="',isset($_SESSION['port']) ? $_SESSION['port'] : "3306",'" /><br />
                     <label for="tbl_prefix">Table prefix :</label><input type="text" id="tbl_prefix" name="tbl_prefix" class="step" value="'.$_SESSION['pre'].'" />
                     </fieldset>

                     <fieldset><legend>Maintenance Mode</legend>
                     <p>
                     	<input type="checkbox" name="no_maintenance_mode" id="no_maintenance_mode"  />&nbsp;Don\'t activate the Maintenance mode
					 </p>
					 <i>By default, the maintenance mode is enabled when an Update is performed. This prevents the use of TeamPass while the scripts are running.<br />
					 However, some administrators may prefer to warn the users in another way. Nevertheless, keep in mind that the update process may fail or even be corrupted due to parallel queries.</i>
					 </fieldset>

                     <fieldset><legend>Anonymous statistics</legend>
                     <input type="checkbox" name="send_stats" id="send_stats" />Send monthly anonymous statistics.<br />
                     <i>Please consider sending your statistics as a way to contribute to futur improvements of TeamPass. Indeed this will help the creator to evaluate how the tool is used and by this way how to improve the tool. When enabled, the tool will automatically send once by month a bunch of statistics without any action from you. Of course, those data are absolutely anonymous and no data is exported, just the next informations : number of users, number of folders, number of items, tool version, ldap enabled, and personal folders enabled.<br>
                     This option can be enabled or disabled through the administration panel.</i>
                     </fieldset>

                     <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step2"></div>
                     <input type="hidden" id="step2" name="step2" value="" />';
} elseif (
    (isset($_POST['step']) && $_POST['step'] == 3 || isset($_GET['step']) && $_GET['step'] == 3)
    && isset($_POST['actual_cpm_version'])
) {
    //ETAPE 3
    echo '
                     <h3>Step 3 - Converting database to UTF-8</h3>';

    if (version_compare($_POST['actual_cpm_version'], $k['version'], "<")) {
        echo '
            Notice that TeamPass is now only using UTF-8 charset.
            This step will convert the database to this charset.<br />
            <p>
                Save previous tables before converting (prefix "old_" will be used)&nbsp;&nbsp;<input type="checkbox" id="prefix_before_convert" />
            </p>
            Click on the button when ready.

            <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step3"></div>  ';
        $conversion_utf8 = true;
    } else {
        echo '
            The database seems already in UTF-8 charset';
        $conversion_utf8 = false;
    }
} elseif (
    (isset($_POST['step']) && $_POST['step'] == 4) || (isset($_GET['step'])
    && $_GET['step'] == 4)
) {
    //ETAPE 4

    echo '
                     <h3>Step 4</h3>

                     The upgrader will now update your database.
                     <table>
                         <tr><td>Misc table will be populated with new values</td><td><span id="tbl_1"></span></td></tr>
                         <tr><td>Users table will be altered with news fields</td><td><span id="tbl_2"></span></td></tr>
                         <tr><td>Nested_Tree table will be altered with news fields</td><td><span id="tbl_5"></span></td></tr>
                         <tr><td>Table "tags" will be created</td><td><span id="tbl_3"></span></td></tr>
                         <tr><td>Table "log_system" will be created</td><td><span id="tbl_4"></span></td></tr>
                         <tr><td>Table "files" will be created</td><td><span id="tbl_6"></span></td></tr>
                         <tr><td>Table "cache" will be created</td><td><span id="tbl_7"></span></td></tr>
                         <tr><td>Change table "functions" to "roles"</td><td><span id="tbl_9"></span></td></tr>
                         <tr><td>Add table "kb"</td><td><span id="tbl_10"></span></td></tr>
                         <tr><td>Add table "kb_categories"</td><td><span id="tbl_11"></span></td></tr>
                         <tr><td>Add table "kb_items"</td><td><span id="tbl_12"></span></td></tr>
                         <tr><td>Add table "restriction_to_roles"</td><td><span id="tbl_13"></span></td></tr>
                         <tr><td>Add table "keys"</td><td><span id="tbl_14"></span></td></tr>
                         <tr><td>Populate table "keys"</td><td><span id="tbl_15"></span></td></tr>
                         <tr><td>Add table "Languages"</td><td><span id="tbl_16"></span></td></tr>
                         <tr><td>Add table "Emails"</td><td><span id="tbl_17"></span></td></tr>
                         <tr><td>Add table "Automatic_del"</td><td><span id="tbl_18"></span></td></tr>
                         <tr><td>Add table "items_edition"</td><td><span id="tbl_19"></span></td></tr>
                         <tr><td>Add table "categories"</td><td><span id="tbl_20"></span></td></tr>
                         <tr><td>Add table "categories_items"</td><td><span id="tbl_21"></span></td></tr>
                         <tr><td>Add table "categories_folders"</td><td><span id="tbl_22"></span></td></tr>
                         <tr><td>Add table "api"</td><td><span id="tbl_23"></span></td></tr>
                         <tr><td>Add table "otv"</td><td><span id="tbl_24"></span></td></tr>
                         <tr><td>Add table "suggestion"</td><td><span id="tbl_25"></span></td></tr>
                     </table>
                     <div style="display:none;" id="change_pw_encryption">
                         <br />
                         <p><b>Encryption protocol of existing passwords now has to be started. It may take several minutes.</b></p>
                         <p>
                             <div style="display:none;" id="change_pw_encryption_progress"></div>
                         </p>
                         <input type="button" value="Click to continue" id="but_encrypt_continu" onclick="newEncryptPw(0);" />
                         <input type="hidden" id="change_pw_encryption_start" value="" />
                         <input type="hidden" id="change_pw_encryption_total" value="" />
                     </div>


                     <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step4"></div>
                     <input type="hidden" id="step4" name="step4" value="" />';
} elseif (
    (isset($_POST['step']) && $_POST['step'] == 5)
    || (isset($_GET['step']) && $_GET['step'] == 5)
) {
    //ETAPE 5
    echo '
                     <h3>Step 5 - Miscellaneous</h3>
                     This step will:<br />
                     - update setting.php file for your server configuration <span id="step5_settingFile"></span><br />
                     - update sk.php file for data encryption <span id="step5_skFile"></span><br />
                     Click on the button when ready.';

    if (!isset($_SESSION['sk_path']) || !file_exists($_SESSION['sk_path'])) {
        echo '
        <h3>IMPORTANT: Since version 2.1.13, saltkey is stored in an independent file.</h3>
        <label for="sk_path" style="width:300px;">Absolute path to SaltKey :
            <img src="../includes/images/information-white.png" alt="" title="The SaltKey is stored in a file called sk.php. But for security reasons, this file should be stored in a folder outside the www folder of your server. So please, indicate here the path to this folder.">
        </label><input type="text" id="sk_path" name="sk_path" value="'.$abs_path.'/includes" size="75" /><br />
        ';
    } else {
        echo '<br /><br />
        <label for="sk_path" style="width:300px;">Absolute path to SaltKey :
            <img src="../includes/images/information-white.png" alt="" title="The SaltKey is stored in a file called sk.php. But for security reasons, this file should be stored in a folder outside the www folder of your server. So please, indicate here the path to this folder.">
        </label><input type="text" id="sk_path" name="sk_path" value="'.substr($_SESSION['sk_path'], 0, strlen($_SESSION['sk_path'])-7).'" size="75" /><br />
        ';
    }
    echo '
        <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step5"></div>';
} elseif (
    (isset($_POST['step']) && $_POST['step'] == 6)
    || (isset($_GET['step']) && $_GET['step'] == 6)
) {
    //ETAPE 5
    echo '
        <h3>Step 6</h3>
        Upgrade is now completed!<br />
        You can delete the "Install" directory from your server for increased security.<br /><br />
        For news, help and information, visit the <a href="http://teampass.net" target="_blank">TeamPass website</a>.<br /><br />
        IMPORTANT: Due to encryption credentials changed during the update, you need to clean the cache of your Web Browser in order to log in successfully.';
}

//buttons
if (!isset($_POST['step'])) {
    echo '
                 <div id="buttons_bottom">
                     <input type="button" id="but_next" onclick="goto_next_page(\'1\')" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="NEXT" />
                 </div>';
} elseif ($_POST['step'] == 3 && $conversion_utf8 == false) {
    echo '
                    <div style="width:900px;margin:auto;margin-top:30px;">
                        <div id="progressbar" style="float:left;margin-top:9px;"></div>
                        <div id="buttons_bottom">
                            <input type="button" id="but_next" onclick="goto_next_page(\''. (intval($_POST['step'])+1).'\')" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="NEXT" />
                        </div>
                    </div>';
} elseif ($_POST['step'] == 3 && $conversion_utf8 == true) {
    echo '
                    <div style="width:900px;margin:auto;margin-top:30px;">
                        <div id="progressbar" style="float:left;margin-top:9px;"></div>
                        <div id="buttons_bottom">
                            <input type="button" id="but_launch" onclick="Check(\'step'.$_POST['step'] .'\')" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="LAUNCH" />
                            <input type="button" id="but_next" onclick="goto_next_page(\''. (intval($_POST['step'])+1).'\')" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="NEXT" disabled="disabled" />
                        </div>
                    </div>';
} elseif ($_POST['step'] == 6) {
    echo '
                 <div id="buttons_bottom">
                     <input type="button" id="but_next" onclick="javascript:window.location.href=\'', (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ? 'https' : 'http', '://'.$_SERVER['HTTP_HOST'].substr($_SERVER['PHP_SELF'],0,strrpos($_SERVER['PHP_SELF'],'/')-8).'\';" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="Open TeamPass" />
                 </div>';
} else {
    echo '
                     <div style="width:900px;margin:auto;margin-top:30px;">
                         <div id="progressbar" style="float:left;margin-top:9px;"></div>
                         <div id="buttons_bottom">
                             <input type="button" id="but_launch" onclick="Check(\'step'.$_POST['step'] .'\')" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="LAUNCH" />
                             <input type="button" id="but_next" onclick="goto_next_page(\''. (intval($_POST['step'])+1).'\')" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="NEXT" disabled="disabled" />
                         </div>
                     </div>';
}

echo '
                </form>
            </div>
            </div>';
//FOOTER
// DON'T MODIFY THE FOOTER
echo '
    <div id="footer">
        <div style="width:500px;">
            '.$k['tool_name'].' '.$k['version'].' &#169; copyright 2009-2013
        </div>
        <div style="float:right;margin-top:-15px;">
        </div>
    </div>';
?>
    </body>
</html>
