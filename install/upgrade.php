<?php
require_once('../sources/SecureHandler.php');
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
$filename = "../includes/config/settings.php";
$events = "";
if (file_exists($filename)) {
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
        <link rel="stylesheet" href="../includes/font-awesome/css/font-awesome.min.css">
        <script type="text/javascript" src="../includes/js/functions.js"></script>
        <script type="text/javascript" src="upgrade.js"></script>
        <script type="text/javascript" src="js/jquery.min.js"></script>
        <script type="text/javascript" src="js/jquery-ui.min.js"></script>
        <script type="text/javascript" src="js/aes.min.js"></script>

        <script type="text/javascript">
        $(function(){
            $("#but_next").click(function(event) {
                $("#step").val($(this).attr("target_id"));
                document.install.submit();
            });

            $("#dump_done").click(function(event) {
                if($("#dump_done").is(':checked')) {
                    $("#but_next").prop("disabled", false);
                } else {
                    $("#but_next").prop("disabled", true);
                }
            });
        });

        function aes_encrypt(text)
        {
            return Aes.Ctr.encrypt(text, "cpm", 128);
        }

        function Check(step)
        {
            if (step != "") {
                var upgrade_file = "upgrade_ajax.php";
                if (step === "step0" && document.getElementById("user_login").value !== "" && document.getElementById("user_pwd").value !== "") {
                    var data = "type="+step+
                    "&login="+escape(document.getElementById("user_login").value)+
                    "&pwd="+window.btoa(aes_encrypt(document.getElementById("user_pwd").value));
                    document.getElementById("loader").style.display = "";
                } else if (step == "step1") {
                    var data = "type="+step+
                    "&abspath="+escape(document.getElementById("root_path").value)+
                        "&fullurl="+escape(document.getElementById("root_url").value);
                    document.getElementById("loader").style.display = "";
                } else
                if (step == "step2") {
                    document.getElementById("loader").style.display = "";
                    var maintenance = 1;
                    if (document.getElementById("no_maintenance_mode").checked==true) {
                        maintenance = 0;
                    }
                    var data = "type="+step+
                    "&no_maintenance_mode="+maintenance+
                    "&session_salt="+escape(document.getElementById("session_salt").value)
                    "&previous_sk="+escape(document.getElementById("previous_sk").value);
                } else
                if (step == "step3") {
                    document.getElementById("res_step3").innerHTML = '<img src="images/ajax-loader.gif" alt="" />';
                    var data = "type="+step+
                    "&prefix_before_convert="+document.getElementById("prefix_before_convert").checked;
                    document.getElementById("loader").style.display = "";
                } else
                if (step == "step4") {
                    upgrade_file = "";
                    var data = "type="+step;
                    manageUpgradeScripts("0");

                } else
                if (step == "step5") {
                    document.getElementById("res_step5").innerHTML = "Please wait... <img src=\"images/ajax-loader.gif\" />";
                    if (document.getElementById("sk_path") == null)
                        var data = "type="+step;
                    else
                        var data = "type="+step+"&sk_path="+escape(document.getElementById("sk_path").value);
                }
                if (upgrade_file != "") httpRequest(upgrade_file, data);
            }
        }

        function manageUpgradeScripts(file_number)
        {
            var start_at = 0;
            var noitems_by_loop = 20;
            var loop_number = 0;

            if (file_number == 0) $("#step4_progress").html("");

            request = $.post("upgrade_scripts_manager.php",
                {
                    file_number : parseInt(file_number)
                },
                function(data) {
                    // work not finished
                    if (data[0].finish != 1) {
                        // loop
                        runUpdate(data[0].scriptname, data[0].parameter, start_at, noitems_by_loop, loop_number, file_number);
                    }
                    // work finished
                    else {
                        $("#step4_progress").html("<div>All done.</div>"+ $("#step4_progress").html());
                        /* Unlock this step */
                        document.getElementById("but_next").disabled = "";
                        document.getElementById("but_launch").disabled = "disabled";
                        document.getElementById("loader").style.display = "none";
                    }
                },
                "json"
            );
        }

        function runUpdate (script_file, type_parameter, start_at, noitems_by_loop, loop_number, file_number)
        {
            var d = new Date();
            loop_number ++;
            var rand_number = CreateRandomString(5);

            $("#step4_progress").html("<div>"+("0" + d.getHours()).slice(-2)+":"+("0" + d.getMinutes()).slice(-2)+":"+("0" + d.getSeconds()).slice(-2)+" - <i>"+script_file+"</i> - Loop #"+loop_number+" <span id='span_"+rand_number+"'>is now running ... <i class=\"fa fa-cog fa-spin\" style=\"color:orange\"></i></span></div>"+ $("#step4_progress").html());

            request = $.post(script_file,
                {
                    type        : type_parameter,
                    start       : start_at,
                    total       : start_at,
                    nb          : noitems_by_loop,
                    session_salt: $("#session_salt").val()
                },
                function(data) {
                    // work not finished
                    if (data[0].finish != 1) {
                        $("#span_"+rand_number).html("<i class=\"fa fa-thumbs-up\" style=\"color:green\"></i>")
                        // loop
                        runUpdate(script_file, type_parameter, data[0].next, noitems_by_loop, loop_number, file_number);
                    }
                    // is there an error
                    else if (data[0].finish == 1 && data[0].error != "") {
                        $("#span_"+rand_number).html("<i class=\"fa fa-thumbs-down\" style=\"color:red\"></i>");
                        $("#step4_progress").html("<div style=\"margin:15px 0 15px 0; font-style:italic;\">"+d.getHours()+":"+d.getMinutes()+":"+d.getSeconds()+" - <b>ERROR</b>: "+data[0].error+"</div>"+ $("#step4_progress").html());
                        $("#step4_progress").html("<div>An error occurred. Please check and relaunch.</div>"+ $("#step4_progress").html());
                    }
                    // work finished
                    else {
                        $("#span_"+rand_number).html("<i class=\"fa fa-thumbs-up\" style=\"color:green\"></i>")
                        // continue with next script file
                        file_number ++;
                        manageUpgradeScripts(file_number);
                    }
                },
                "json"
            );
        }

        function newEncryptPw(suggestion){
            var nb = 20;
            var start = 0;

            if ($("#change_pw_encryption_start").val() != "") {
                start = $("#change_pw_encryption_start").val();
            } else {
                $("#change_pw_encryption_progress").html("Progress: 0% <img src=\"images/76.gif\" />");
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

        function launch_database_dump() {
            $("#dump_result").html("<img src=\"images/76.gif\" />");
            request = $.post("upgrade_ajax.php",
                {
                    type      : "perform_database_dump"
                },
                function(data) {
                    var obj = $.parseJSON(data);
                    if (obj[0].error !== "") {
                        // ERROR
                        $("#dump_result").html(obj[0].error);
                    } else {
                        // DONE
                        $("#dump_result").html("Dump is successfull. File stored in folder " + obj[0].file);
                    }
                }
            );
        }
        </script>
    </head>
    <body>
<?php
require_once '../includes/language/english.php';
require_once '../includes/config/include.php';


if (isset($_POST['root_url'])) {
    $_SESSION['fullurl'] = $_POST['root_url'];
}


//define root path
$abs_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . substr($_SERVER['PHP_SELF'], 0, strlen($_SERVER['PHP_SELF'])-20);
if( isset($_SERVER['HTTPS'] ) ) {
    $protocol = 'https://';
} else {
    $protocol = 'http://';
}

// LOADER
echo '
    <div style="position:absolute;top:49%;left:49%;display:none;z-index:9999999;" id="loader">
        <img src="images/76.gif" />
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
                    <input type="hidden" name="user_granted" id="user_granted" value="" />
                    <input type="hidden" name="session_salt" id="session_salt" value="', (isset($_POST['session_salt']) && !empty($_POST['session_salt'])) ? $_POST['session_salt']:@$_SESSION['encrypt_key'], '" />';

if (!isset($_GET['step']) && !isset($_POST['step'])) {
    //ETAPE O
    echo '
                    <div>
                    <fieldset>
                        <legend>Teampass upgrade</legend>
                        Before starting, be sure to:<ul>
                        <li>upload the complete package on the server and overwrite existing files,</li>
                        <li>have the database connection informations,</li>
                        <li>get some CHMOD rights on the server.</li>
                        </ul>

                        <h5>TeamPass is distributed under GNU AFFERO GPL licence.</h5>

                        <div style="font-weight:bold; color:#C60000; margin-bottom:10px;">
                        <img src="images/error.png" />&nbsp;ALWAYS BE SURE TO CREATE A DUMP OF YOUR DATABASE BEFORE UPGRADING.
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Authentication</legend>
                        <div class="ui-state-default ui-corner-all" style="float:left; width:100%; padding:10px; margin:20px 0 20 px;">
                             <div style="float:left; height:50px; width:100%;">
                                <label for="user_login" style="width:200px;">Administrator Login:</label>&nbsp;
                                <input type="text" id="user_login" />
                             </div>
                             <div style="float:left;">
                                <label for="user_pwd" style="width:200px;">Administrator Password:</label>&nbsp;
                                <input type="password" id="user_pwd" />
                             </div>
                        </div>
                    </fieldset>

                    <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step0"></div>
                    <input type="hidden" id="step0" name="step0" value="" />

                     </div>';

} elseif (
    (isset($_POST['step']) && $_POST['step'] == 1)
    || (isset($_GET['step']) && $_GET['step'] == 1)
    && $_POST['user_granted'] === "1"
) {
    //ETAPE 1
    $_SESSION['user_granted'] = $_POST['user_granted'];
    echo '
                     <h3>Step 1 - Check server</h3>

                     <fieldset><legend>Please give me</legend>
                     <label for="root_path" style="width:300px;">Absolute path to TeamPass folder:</label><input type="text" id="root_path" name="root_path" class="step" style="width:560px;" value="'.$abs_path.'" /><br />
                     <label for="root_url" style="width:300px;">Full URL to TeamPass:</label><input type="text" id="root_url" name="root_url" class="step" style="width:560px;" value="'.$protocol.$_SERVER['HTTP_HOST'].substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/') - 8).'" /><br />
                     </fieldset>

                     <h4>Next elements will be checked.</h4>
                     <div style="margin:15px;" id="res_step1">
                     <span style="padding-left:30px;font-size:13pt;">File "settings.php" is writable</span><br />
                     <span style="padding-left:30px;font-size:13pt;">Directory "/install/" is writable</span><br />
                     <span style="padding-left:30px;font-size:13pt;">Directory "/includes/" is writable</span><br />
                     <span style="padding-left:30px;font-size:13pt;">Directory "/includes/config/" is writable</span><br />
                     <span style="padding-left:30px;font-size:13pt;">Directory "/includes/avatars/" is writable</span><br />
                     <span style="padding-left:30px;font-size:13pt;">Directory "/files/" is writable</span><br />
                     <span style="padding-left:30px;font-size:13pt;">Directory "/upload/" is writable</span><br />
                     <span style="padding-left:30px;font-size:13pt;">PHP extension "mcrypt" is loaded</span><br />
                     <span style="padding-left:30px;font-size:13pt;">PHP extension "openssl" is loaded</span><br />
                     <span style="padding-left:30px;font-size:13pt;">PHP extension "gd" is loaded</span><br />
                     <span style="padding-left:30px;font-size:13pt;">PHP extension "curl" is loaded</span><br />
                     <span style="padding-left:30px;font-size:13pt;">PHP version is greater or equal to 5.5.0</span><br />
                     </div>
                     <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step1"></div>
                     <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step1_error"></div>
                     <input type="hidden" id="step1" name="step1" value="" />';

} elseif (
    (isset($_POST['step']) && $_POST['step'] == 2)
    || (isset($_GET['step']) && $_GET['step'] == 2)
    && $_SESSION['user_granted'] === "1"
) {
    //ETAPE 2
    echo '
                     <h3>Step 2</h3>
                     <fieldset><legend>DataBase Informations</legend>';

                     // check if all database  info are available
                     if (
                        isset($_SESSION['server']) && !empty($_SESSION['server'])
                        && isset($_SESSION['database']) && !empty($_SESSION['database'])
                        && isset($_SESSION['user']) && !empty($_SESSION['user'])
                        && isset($_SESSION['pass'])
                        && isset($_SESSION['port']) && !empty($_SESSION['port'])
                        && isset($_SESSION['pre'])
                    ) {
                        echo '
                        <div style="">
                        The database information has been retreived from the settings file.<br>
                        If you need to change them, please edit file `/includes/config/settings.php` and relaunch the upgrade process.
                        </div>';
                     } else {
                        echo '
                        <div style="">
                        The database information has not been retreived from the settings file.<br>
                        You need to adapt the file `/includes/config/settings.php` and relaunch the upgrade process.
                        </div>';
                     }

                     echo '
                     <a href="'.$protocol.$_SERVER['HTTP_HOST'].substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/') - 8).'/install/upgrade.php">Restart upgrade process</a>
                     </fieldset>

                     <fieldset><legend>Maintenance Mode</legend>
                     <p>
                        <input type="checkbox" name="no_maintenance_mode" id="no_maintenance_mode"  />&nbsp;Don\'t activate the Maintenance mode
                     </p>
                     <i>By default, the maintenance mode is enabled when an Update is performed. This prevents the use of TeamPass while the scripts are running.<br />
                     However, some administrators may prefer to warn the users in another way. Nevertheless, keep in mind that the update process may fail or even be corrupted due to parallel queries.</i>
                     </fieldset>

                     <!--
                     <fieldset><legend>Anonymous statistics</legend>
                     <input type="checkbox" name="send_stats" id="send_stats" />Send monthly anonymous statistics.<br />
                     <i>Please consider sending your statistics as a way to contribute to futur improvements of TeamPass. Indeed this will help the creator to evaluate how the tool is used and by this way how to improve the tool. When enabled, the tool will automatically send once by month a bunch of statistics without any action from you. Of course, those data are absolutely anonymous and no data is exported, just the next informations : number of users, number of folders, number of items, tool version, ldap enabled, and personal folders enabled.<br>
                     This option can be enabled or disabled through the administration panel.</i>
                     </fieldset>
                     -->

                     <div id="dump">
                     <fieldset><legend>Database dump</legend>
                     <i>If you have NOT performed a dump of your database, please considere to create one now.</i>
                     <br>
                     <a href="#" onclick="launch_database_dump(); return false;">Launch a new database dump</a>
                     <br><span id="dump_result" style="margin-top:4px;"></span>
                     </fieldset>
                     </div>';

                    // teampass_version = 2.1.27 and no encrypt_key in db
                     echo '
                     <div id="no_encrypt_key" style="display:none;">
                     <fieldset><legend>Previous SALTKEY</legend>
                        <p>It seems that the old saltkey has not been stored inside the database. <br>Please use the next field to enter the saltkey you used in previous version of Teampass. It can be retrieved by editing sk.php file (in case you are upgrading from a version older than 2.1.27) or a sk.php backup file (in case you are upgrading from 2.1.27).<br>
                        </p>
                        <label for="previous_sk">Previous SaltKey:&nbsp</label>
                        <input type="text" id="previous_sk" size="100px" value="'.@$_SESSION['encrypt_key'].'" />
                     </fieldset>
                     </div>';

                    echo '
                     <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step2"></div>
                     <input type="hidden" id="step2" name="step2" value="" />';
} elseif (
    (isset($_POST['step']) && $_POST['step'] == 3 || isset($_GET['step']) && $_GET['step'] == 3)
    && isset($_POST['actual_cpm_version'])
    && $_SESSION['user_granted'] === "1"
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
    && $_SESSION['user_granted'] === "1"
) {
    //ETAPE 4

    echo '
                     <h3>Step 4</h3>

                     The upgrader will now update the database by running several upgrade scripts.
                     <div id="step4_progress" style="margin-top:20px;"></div>
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
    && $_SESSION['user_granted'] === "1"
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
            <img src="images/information-white.png" alt="" title="The SaltKey is stored in a file called sk.php. But for security reasons, this file should be stored in a folder outside the www folder of your server. So please, indicate here the path to this folder.">
        </label><input type="text" id="sk_path" name="sk_path" value="'.$abs_path.'/includes" size="75" /><br />
        ';
    } else {
        echo '<br /><br />
        <label for="sk_path" style="width:300px;">Absolute path to SaltKey :
            <img src="images/information-white.png" alt="" title="The SaltKey is stored in a file called sk.php. But for security reasons, this file should be stored in a folder outside the www folder of your server. So please, indicate here the path to this folder.">
        </label><input type="text" id="sk_path" name="sk_path" value="'.substr($_SESSION['sk_path'], 0, strlen($_SESSION['sk_path'])-7).'" size="75" /><br />
        ';
    }
    echo '
        <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step5"></div>';
} elseif (
    (isset($_POST['step']) && $_POST['step'] == 6)
    || (isset($_GET['step']) && $_GET['step'] == 6)
    && $_SESSION['user_granted'] === "1"
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
                     <input type="button" id="but_launch" onclick="Check(\'step0\')" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="LAUNCH" />
                    <input type="button" id="but_next" target_id="1" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="NEXT" disabled="disabled" />
                 </div>';
} elseif ($_POST['step'] == 3 && $conversion_utf8 == false && $_SESSION['user_granted'] === "1") {
    echo '
                    <div style="width:900px;margin:auto;margin-top:30px;">
                        <div id="progressbar" style="float:left;margin-top:9px;"></div>
                        <div id="buttons_bottom">
                            <input type="button" id="but_next" target_id="'. (intval($_POST['step'])+1).'" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="NEXT" />
                        </div>
                    </div>';
} elseif ($_POST['step'] == 3 && $conversion_utf8 == true && $_SESSION['user_granted'] === "1") {
    echo '
                    <div style="width:900px;margin:auto;margin-top:30px;">
                        <div id="progressbar" style="float:left;margin-top:9px;"></div>
                        <div id="buttons_bottom">
                            <input type="button" id="but_launch" onclick="Check(\'step'.$_POST['step'] .'\')" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="LAUNCH" />
                            <input type="button" id="but_next" target_id="'. (intval($_POST['step'])+1).'" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="NEXT" disabled="disabled" />
                        </div>
                    </div>';
} elseif ($_POST['step'] == 6 && $_SESSION['user_granted'] === "1") {
    echo '
                 <div style="margin-top:30px; text-align:center; width:100%; font-size:24px;">
                     <a href="#" onclick="javascript:window.location.href=\'', (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ? 'https' : 'http', '://'.$_SERVER['HTTP_HOST'].substr($_SERVER['PHP_SELF'],0,strrpos($_SERVER['PHP_SELF'],'/')-8).'\';"><b>Open TeamPass</b></a>
                 </div>';
} else {
    echo '
                     <div style="width:900px;margin:auto;margin-top:30px;">
                         <div id="progressbar" style="float:left;margin-top:9px;"></div>
                         <div id="buttons_bottom">
                             <input type="button" id="but_launch" onclick="Check(\'step'.$_POST['step'] .'\')" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="LAUNCH" />
                             <input type="button" id="but_next" target_id="'. (intval($_POST['step'])+1).'" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="NEXT" disabled="disabled" />
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
            '.$k['tool_name'].' '.$k['version'].' &#169; copyright 2009-2016
        </div>
        <div style="float:right;margin-top:-15px;">
        </div>
    </div>';
?>
    </body>
</html>
