<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      upgrade.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2022 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */


header('X-XSS-Protection: 1; mode=block');
header('X-Frame-Options: SameOrigin');

// **PREVENTING SESSION HIJACKING**
// Prevents javascript XSS attacks aimed to steal the session ID
ini_set('session.cookie_httponly', 1);

// **PREVENTING SESSION FIXATION**
// Session ID cannot be passed through URLs
ini_set('session.use_only_cookies', 1);

// Uses a secure connection (HTTPS) if possible
ini_set('session.cookie_secure', 0);

require_once '../sources/SecureHandler.php';
session_name('teampass_session');
session_start();
//Session teampass tag
$_SESSION['CPM'] = 1;
define('MIN_PHP_VERSION', 7.4);
define('MIN_MYSQL_VERSION', '8.0.13');
define('MIN_MARIADB_VERSION', '10.2.1');

// Prepare POST variables
$post_root_url = filter_input(INPUT_POST, 'root_url', FILTER_SANITIZE_STRING);
$post_step = filter_input(INPUT_POST, 'step', FILTER_SANITIZE_NUMBER_INT);
$post_actual_cpm_version = filter_input(INPUT_POST, 'actual_cpm_version', FILTER_SANITIZE_STRING);
$post_cpm_isUTF8 = filter_input(INPUT_POST, 'cpm_isUTF8', FILTER_SANITIZE_STRING);
$post_user_granted = filter_input(INPUT_POST, 'user_granted', FILTER_SANITIZE_STRING);
$post_session_salt = filter_input(INPUT_POST, 'session_salt', FILTER_SANITIZE_STRING);
$post_url_path = filter_input(INPUT_POST, 'url_path', FILTER_SANITIZE_STRING);
$post_infotmp = filter_input(INPUT_POST, 'infotmp', FILTER_SANITIZE_STRING);

//###############
//# Function permits to get the value from a line
//###############
/**
 * @param string $val
 */
function getSettingValue($val)
{
    $val = trim(strstr($val, '='));

    return trim(str_replace('"', '', substr($val, 1, strpos($val, ';') - 1)));
}

//get infos from SETTINGS.PHP file
$filename = '../includes/config/settings.php';
$events = '';
if (file_exists($filename)) {
    include_once $filename;
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
        <title>TeamPass Upgrade</title>
        
        <meta http-equiv='Content-Type' content='text/html;charset=utf-8' />
        <meta name="viewport" content="width=device-width, initial-scale=1"/>
        <meta http-equiv="x-ua-compatible" content="ie=edge"/>

        <link rel="stylesheet" href="css/install.css" type="text/css" />
        <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.css">
        
        <!-- Theme style -->
        <link rel="stylesheet" href="../plugins/adminlte/css/adminlte.min.css">
        <link rel="stylesheet" href="../plugins/alertifyjs/css/alertify.min.css"/>
        <link rel="stylesheet" href="../plugins/alertifyjs/css/themes/bootstrap.min.css"/>
        
        
    </head>
    
    <body>
<?php
require_once '../includes/language/english.php';
require_once '../includes/config/include.php';

if (empty($post_root_url) === false) {
    $_SESSION['fullurl'] = $post_root_url;
}

//define root path
$abs_path = rtrim(
    filter_var($_SERVER['DOCUMENT_ROOT'], FILTER_SANITIZE_STRING),
    '/'
).substr(
    filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_STRING),
    0,
    strlen(filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_STRING)) - 20
);
if (isset($_SERVER['HTTPS'])) {
    $protocol = 'https://';
} else {
    $protocol = 'http://';
}


// HEADER
echo '
    <div id="top" class="center-screen">
        <div id="logo" class="lcol"><img src="../includes/images/teampass-logo2-home.png" /></div>
        <div class="lcol">
            <span class="header-title">'.strtoupper(TP_TOOL_NAME).'</span>
            <!--<span class="header-title-small"> v'.TP_VERSION_FULL.'</span>-->
        </div>
    <div id="content">
        <form name="install" method="post" action="">
        <div class="card card-default color-palette-box">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-people-carry mr-2"></i>Teampass upgrade
                </h3>
            </div>
            <div class="card-body">';

//HIDDEN THINGS
echo '
                <input type="hidden" id="step" name="step" value="', isset($post_step) ? $post_step : '', '" />
                <input type="hidden" id="actual_cpm_version" name="actual_cpm_version" value="', isset($post_actual_cpm_version) ? $post_actual_cpm_version : '', '" />
                <input type="hidden" id="cpm_isUTF8" name="cpm_isUTF8" value="', isset($post_cpm_isUTF8) ? $post_cpm_isUTF8 : '', '" />
                <input type="hidden" name="menu_action" id="menu_action" value="" />
                <input type="hidden" name="user_granted" id="user_granted" value="" />
                <input type="hidden" name="infotmp" id="infotmp" value="', isset($post_infotmp) ? $post_infotmp : '', '" />
                <input type="hidden" name="url_path" id="url_path" value="', (isset($post_root_url) === true && empty($post_root_url) === false) ? $post_root_url : $post_url_path, '" />
                <input type="hidden" name="session_salt" id="session_salt" value="', (isset($post_session_salt) && !empty($post_session_salt)) ? $post_session_salt : @$_SESSION['encrypt_key'], '" />';

if (!isset($_GET['step']) && !isset($post_step)) {
    //ETAPE O
    echo '
                <div class="row">
                    <div class="callout callout-warning col-12">
                        <h5>Information</h5>
    
                        <p>Upgrade process is about to start. This will upgrade Teampass database to version '.TP_VERSION_FULL.'.</p>
                        <p>Version 3 comes with a new secured encryption strategy getting rid of any Saltkey. It relies on public and private keys generated for each user. As an impact, this upgrade will automatically generate a One-Time-Code for each user and send by email. It will be requested on first login. Please ensure your users have filled in their email with a valid value.</p>
                    </div>

                    <div class="callout callout-info col-12">
                        <h5>Before starting, take a couple of minutes to perform backup of current Teampass instance:</h5>
    
                        <p>
                        <ul>
                            <li><i class="fas fa-exclamation-circle mr-2 text-danger"></i>Ensure to clear your browser cache (keyboard: <i>CTRL + F5</i>)</li>
                            <li><i class="fas fa-exclamation-triangle mr-2 text-warning"></i>Create a dump of your database</li>
                            <li><i class="fas fa-exclamation-triangle mr-2 text-warning"></i>Perform a zip of the current Teampass folder</li>
                            <li><i class="fas fa-info-circle mr-2 text-success"></i>Refer to <a href="https://teampass.readthedocs.io/en/latest/install/upgrade/" target="_blank" class="text-info">upgrade documentation</a>.</li>
                        </ul>
                        </p>
                    </div>
                </div>

                <div class="row card card-primary">
                    <div class="card-body col-12">
                        <div class="form-group">
                            <label>Administrator Login</label>
                            <input type="text" class="form-control" id="user_login" placeholder="Enter admin login">
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" class="form-control" id="user_pwd" placeholder="Enter admin password">
                        </div>
                        <div class="alert alert-warning hidden" id="res_step0"></div>
                    </div>
                </div>


                <input type="hidden" id="step0" name="step0" value="" />

            </div>';
// STEP1
} elseif ((isset($post_step) && $post_step == 1)
    || (isset($_GET['step']) && $_GET['step'] == 1)
    && $post_user_granted === '1'
) {
    //ETAPE 1
    $_SESSION['user_granted'] = $post_user_granted;
    echo '
            <div class="row">
                <div class="col-12">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h5>Server information</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Absolute path to TeamPass folder</label>
                                <input type="text" class="form-control" id="root_path" value="'.$abs_path.'">
                            </div>
                            <div class="form-group">
                                <label>Full URL to TeamPass</label>
                                <input type="text" class="form-control" id="root_url" value="'.$protocol.$_SERVER['HTTP_HOST'].substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/') - 8).'">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h5>Next elements will be checked</h5>
                        </div>
                        <div class="card-body">

                            <div id="res_step1">
                            <span>File "settings.php" is writable</span><br />
                            <span>Directory "/install/" is writable</span><br />
                            <span>Directory "/includes/" is writable</span><br />
                            <span>Directory "/includes/config/" is writable</span><br />
                            <span>Directory "/includes/avatars/" is writable</span><br />
                            <span>Directory "/files/" is writable</span><br />
                            <span>Directory "/upload/" is writable</span><br />
                            <span>PHP extension "openssl" is loaded</span><br />
                            <span>PHP extension "gd" is loaded</span><br />
                            <span>PHP extension "curl" is loaded</span><br />
                            <span>PHP version is greater or equal to '.MIN_PHP_VERSION.'</span><br />
                            <span>SQL version is greater or equal to MySQL '.MIN_MYSQL_VERSION.' or MariaDB '.MIN_MARIADB_VERSION.'</span><br />
                            </div>
                        
                        </div>
                    </div>
                </div>
            </div>
            <input type="hidden" id="step1" name="step1" value="" />';
// STEP2
} elseif ((isset($post_step) && $post_step == 2)
    || (isset($_GET['step']) && $_GET['step'] == 2)
    && $_SESSION['user_granted'] === '1'
) {
    // Do we have all database settings
    if (null !== DB_HOST
        && null !== DB_USER
        && null !== DB_PASSWD
        && null !== DB_NAME
        && null !== DB_PREFIX
        && null !== DB_PORT
    ) {
        $dbSettings = true;
    } else {
        $dbSettings = false;
    }
    //ETAPE 2
    echo '
        <div class="row">
            <div class="col-12">
                <div class="card card-',$dbSettings === true ? 'primary' : 'warning','">
                    <div class="card-header">
                        <h5>DataBase Informations</h5>
                    </div>
                    <div class="card-body">';

    // check if all database  info are available
    if ($dbSettings === true) {
        echo '
                        <div>
                        Database settings has been retreived.<br>
                        If you need to change them, please edit file `/includes/config/settings.php` and relaunch the upgrade process.
                        </div>';
    } else {
        echo '
                        <div>
                        The database information has not been retreived from the settings file.<br>
                        You need to adapt the file `/includes/config/settings.php` and relaunch the upgrade process.
                        </div>';
    }

    echo '
                        <a href="'.$protocol.$_SERVER['HTTP_HOST'].substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/') - 8).'/install/upgrade.php">Restart upgrade process</a>
                    </div>
                </div>

                <div class="card card-primary">
                    <div class="card-header">
                        <h5>Maintenance Mode</h5>
                    </div>
                    <div class="card-body">
                        <div>
                            <input type="checkbox" class="mr-2" id="no_maintenance_mode">
                            <label for="no_maintenance_mode">Don\'t activate the Maintenance mode</label>
                        </div>
                        <small class="form-text text-muted">
                            By default, the maintenance mode is enabled when an Update is performed. This prevents the use of TeamPass while the scripts are running.<br />
                            However, some administrators may prefer to warn the users in another way. Nevertheless, keep in mind that the update process may fail or even be corrupted due to parallel queries.
                        </small>
                    </div>
                </div>

                <div class="card card-primary">
                    <div class="card-header">
                        <h5>Database dump</h5>
                    </div>
                    <div class="card-body">
                        <div>
                            If you have NOT performed a dump of your database, please considere to create one now.
                        </div>
                        <div>
                            <a href="#" onclick="launch_database_dump(); return false;">Launch a new database dump</a>
                        </div>
                        <div>
                            <span id="dump_result" style="margin-top:4px;" class="card card-info"></span>
                        </div>
                    </div>
                </div>

                <!-- teampass_version = 2.1.27 and no encrypt_key in db -->
                <div class="callout card-warning hidden" id="no_encrypt_key">
                    <div class="card-header">
                        <h5>Database Origine</h5>
                    </div>
                    <div class="card-body">
                        <div>
                            Please select:&nbsp;<select id="no_key_selection">
                            <option value="false">-- select --</option>
                            <option value="no_previous_sk_sel">We have never used Teampass in an older version than 2.1.27(.x)</option>
                            <option value="previous_sk_sel">We have used Teampass in an older version (example: 2.1.26)</option>
                        </select>
                        <div id="previous_sk_div" style="display:none;">
                            <p>Please use the next field to enter the saltkey you used in previous version of Teampass. It can be retrieved by editing sk.php file (in case you are upgrading from a version older than 2.1.27) or a sk.php backup file (in case you are upgrading from 2.1.27).<br>
                            </p>
                            <label for="previous_sk">Previous SaltKey:&nbsp</label>
                            <input type="text" id="previous_sk" size="100px" value="'.@$_SESSION['encrypt_key'].'" />
                        </div>
                        </div>
                    </div>
                </div>

                <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step2"></div>
                <input type="hidden" id="step2" name="step2" value="">

            </div>
        </div>';


// STEP3
} elseif ((isset($post_step) && $post_step == 3 || isset($_GET['step']) && $_GET['step'] == 3)
    && isset($post_actual_cpm_version)
    && intVal($_SESSION['user_granted']) === 1
) {
    if (version_compare($post_actual_cpm_version, '2.1.26', '<')) {
        $conversion_utf8 = true;
    } else {
        $conversion_utf8 = false;
    }
    echo '
        <div class="card card-primary">
            <div class="card-header">
                <h5>Converting database to UTF-8</h5>
            </div>
            <div class="card-body">
                <div>
                ', $conversion_utf8 === true ?
                'Notice that TeamPass is now only using UTF-8 charset.
                This step will convert the database to this charset.
                <div>
                <input type="checkbox" id="prefix_before_convert" class="mr-2"><label for="prefix_before_convert">Save previous tables before converting (prefix "old_" will be used)</label>
                </div>' :
                'The database is currently using UTF-8 charset <i class="fas fa-check-circle fa-lg text-success ml-3"></i>', '
                </div>
            </div>
        </div>';
// STEP4
} elseif ((isset($post_step) && $post_step == 4) || (isset($_GET['step'])
    && $_GET['step'] == 4)
    && $_SESSION['user_granted'] === '1'
) {
    echo '
        <div class="card card-primary">
            <div class="card-header">
                <h5>Database updates</h5>
            </div>
            <div class="card-body">
                <small class="form-text text-muted">
                    The database needs to be adapted. This step can take a very long time depending on the data volume and server performance.
                </small>
                <div class="row card card-primary mt-2">
                    <div class="card-body col-12 font-weight-light" id="step4_progress" style="overflow-y: scroll; height:400px;">
                        Please click the button START.
                    </div>
                </div>

                <div class="hidden" id="change_pw_encryption">
                    <br />
                    <p><b>Encryption protocol of existing passwords now has to be started. It may take a very long time depending on the data volume and server performance.</b></p>
                    <p>
                        <div style="display:none;" id="change_pw_encryption_progress"></div>
                    </p>
                    <input type="button" value="Click to continue" id="but_encrypt_continu" onclick="newEncryptPw(0);" />
                    <input type="hidden" id="change_pw_encryption_start" value="" />
                    <input type="hidden" id="change_pw_encryption_total" value="" />
                </div>

                <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step4"></div>
                <input type="hidden" id="step4" name="step4" value="">
            </div>
        </div>';
// STEP5
} elseif ((isset($post_step) && $post_step == 5)
    || (isset($_GET['step']) && $_GET['step'] == 5)
    && $_SESSION['user_granted'] === '1'
) {
    //ETAPE 5
    echo '
        <h4>Finalization</h4>
        <div>
            <ul>
            <li>Regenerate settings.php file to remove any dependency to saltkey file if needed <span id="step5_settingFile"></span></li>
            <li>Anonymize saltkey file if needed <span id="step5_saltkeyFile"></span></li>
            <li>Generate config file if needed <span id="step5_configFile"></span></li>
            <li>Generate CSRFP config file if needed <span id="step5_csrfpFile"></span></li>
            </ul>
        </div>';

    echo '
        <div class="card card-primary">
            <div class="card-header">
                <h5>Absolute path to SaltKey</h5>
            </div>
            <div class="card-body">
                <small class="form-text text-muted">
                The SaltKey is stored in a file stored in a folder outside the www folder of your server.
                </small>
                <div class="mt-4">
                    <input type="text" class="form-control" id="sk_path" value="', defined('SECUREPATH') === true ? SECUREPATH : '', '" placeholder="Path to folder">
                </div>
            </div>
        </div>';

    echo '
        <div class="alert alert-info mt-4 hidden" id="res_step5"></div>';
} elseif ((isset($post_step) && $post_step == 6)
    || (isset($_GET['step']) && $_GET['step'] == 6)
    && $_SESSION['user_granted'] === '1'
) {
    //ETAPE 5
    echo '
        <h4>Upgrade is now completed</h4>
        <div class="callout callout-info mt-4">
            <div>
                For news, help and information, visit <a href="https://teampass.net" target="_blank" class="text-info">TeamPass website</a>
            </div>
        </div>
        <div class="alert alert-primary mt-4">
            <i class="far fa-lightbulb text-warning mr-2 fa-lg"></i>It is recommended to clean the cache of your Web Browser before trying to log in.
        </div>';

    if (version_compare($post_actual_cpm_version, '2.1.27', '<=')) {
        echo '
        <div class="alert alert-warning mt-4">
            <i class="fa fa-exclamation-circle text-danger mr-2 fa-lg"></i>This upgrade was a heavy one. Indeed we have changed the encryption of your data to make them safer now as they don\'t rely anymore on a key.<br>
            This forced us to encode your users data with a One-Time-Code that they did receive by email. For any reason, they did not received it, you as an admin, can change it from the users management page.
        </div>';
    }

    echo '
        <div class="mt-5">
        <a href="#" class="btn btn-primary" onclick="javascript:window.location.href=\'', (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ? 'https' : 'http', '://'.$_SERVER['HTTP_HOST'].substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/') - 8).'\';"><b>Open TeamPass</b></a>
        </div>';
}

echo '
            <div class="card-footer">';
//buttons
if (!isset($post_step)) {
    echo '
            <input type="button" id="but_launch" data-step="step0" class="btn btn-primary" value="START">
            <input type="button" id="but_next" data-target="1" style="" class="btn btn-primary" value="NEXT" disabled="disabled">';
} elseif (intVal($post_step) === 3 && $conversion_utf8 === false && $_SESSION['user_granted'] === '1') {
    echo '
            <input type="button" id="but_next" target_id="'.(intval($post_step) + 1).'" class="btn btn-primary" value="NEXT">';
} elseif (intVal($post_step) === 3 && $conversion_utf8 === true && $_SESSION['user_granted'] === '1') {
    echo '
            <input type="button" id="but_launch" data-step="step'.$post_step.'" class="btn btn-primary" value="START">
            <input type="button" id="but_next" data-target="'.(intval($post_step) + 1).'" class="btn btn-primary" value="NEXT" disabled="disabled">';
} elseif (intVal($post_step) === 6 && $_SESSION['user_granted'] === '1') {
    // Nothong to do
} else {
    echo '
            <input type="button" id="but_launch" data-step="step'.$post_step.'" class="btn btn-primary" value="START" />
            <input type="button" id="but_next" data-target="'.(intval($post_step) + 1).'" class="btn btn-primary" value="NEXT" disabled="disabled">';
}

echo '
            </div>
        </div>
        </form>
    </div>';
//FOOTER
echo '
    <div id="footer">
        <div style="width:500px; font-size:16px;">
            '.TP_TOOL_NAME.' '.TP_VERSION_FULL.' &#169; copyright 2009-'.date("Y").'
        </div>
        <div style="float:right;margin-top:-15px;">
        </div>
    </div>';
?>
    </div>

</body>
</html>


<script type="text/javascript" src="js/aes.min.js"></script>
<!-- jQuery -->
<script src="../plugins/jquery/jquery.min.js"></script>
<!-- jQuery -->
<script src="../plugins/jqueryUI/jquery-ui.min.js"></script>
<!-- Popper -->
<script src="../plugins/popper/umd/popper.min.js"></script>
<!-- Bootstrap -->
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE -->
<script src="../plugins/adminlte/js/adminlte.min.js"></script>
<!-- Altertify -->
<script type="text/javascript" src="../plugins/alertifyjs/alertify.min.js"></script>




<script type="text/javascript">
var timeTaken = '';


$(function(){
    // click on button NEXT
    $("#but_next").click(function(event) {
        $("#step").val($(this).data("target"));
        document.install.submit();
    });

    $("#dump_done").click(function(event) {
        if($("#dump_done").is(':checked')) {
            $("#but_next").prop("disabled", false);
        } else {
            $("#but_next").prop("disabled", true);
        }
    });

    $("#no_key_selection").change(function() {
        if ($("#no_key_selection").val() === "no_previous_sk_sel") {
            $("#previous_sk_div").hide();
            $("#previous_sk").val("");
        } else if ($("#no_key_selection").val() === "previous_sk_sel") {
            $("#previous_sk_div").show();
        }
    });

    // click on button START
    $('#but_launch').click(function(event) {
        var currentStep = $(this).data('step'),
            postData = '';
        // STEP 0
        if (currentStep === 'step0') {
            if ($("#user_login").val() === "" || $("#user_pwd").val() === "") {
                alertify
                    .error('<i class="fas fa-ban mr-2">[ERROR] You must provide credentials</i>', 10)
                    .dismissOthers();
                return false;
            }

            postData = {
                type : currentStep,
                login : $("#user_login").val(),
                pwd : window.btoa(aesEncrypt($("#user_pwd").val()))
            }
            
        } else if (currentStep === 'step1') {
            postData = {
                type : currentStep,
                abspath : $("#root_path").val(),
                fullurl : $("#root_url").val()
            }
        } else if (currentStep === 'step2') {
            postData = {
                type : currentStep,
                abspath : $("#root_path").val(),
                fullurl : $("#root_url").val()
            }
        } else if (currentStep === 'step3') {
            postData = {
                type : currentStep,
                abspath : $("#root_path").val(),
                fullurl : $("#root_url").val(),
                prefix_before_convert : $('#prefix_before_convert').prop("checked")
            }

        } else if (currentStep === "step4") {
            upgrade_file = "";
            timeTaken = getTime();
            manageUpgradeScripts("0");
            return false;

        } else if (currentStep === "step5") {
            postData = {
                type : currentStep,
                url_path : $("#url_path").val()
            }
        }

        alertify
            .message('<i class="fas fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();
        $("#res_"+currentStep).html("").addClass("hidden");

        // EXECUTE AJAX REQUEST
        return $.ajax({
            url: "upgrade_ajax.php",
            type : "POST",
            dataType : "json",
            async: false,
            data : postData,
            complete : function(result, status){
                //console.log(result.responseText)
                data = $.parseJSON(result.responseText)[0];
                console.log(data)
                // manage error
                if (data.error !== "") {
                    $("#user_granted").val("0");
                    $('#but_next').attr('disabled');

                    if (data.info !== "") {
                        $('#res'+currentStep).html(data.info).removeClass("hidden");
                    }
                    alertify
                        .error('<i class="fas fa-exclamation-triangle mr-2"></i>  '+data.error+'</i>', 5)
                        .dismissOthers();
                } else {
                    $("#step").val(data.index);
                    $("#user_granted").val("1");
                    $('#but_next').removeAttr('disabled');

                    // Special
                    if (currentStep === 'step0') {
                        $("#infotmp").val(data.info);
                    } else if (currentStep === 'step1') {
                        $('#res_step1').html(data.info).removeClass('hidden');
                    } else if (currentStep === 'step2') {
                        
                        $('#res_step2').html(data.info).removeClass('hidden');
                        $('#cpm_isUTF8').val(data.isUtf8);
                        if (parseInt(data.isUtf8) === 1) {
                            $('#step').val(4);
                            $('#but_next').data('target', "4");
                        }
                    } else if (currentStep === 'step5') {
                        $("#res_step5").html("Operations are successfully completed.").removeClass("hidden");
                        var res = $.parseJSON(atob(data.info));
                        
                        $.each(res, function(index, value) {
                            $('#'+value.id).html(value.html);
                        });
                    }

                    // Display
                    alertify
                        .success('<i class="fas fa-thumbs-up mr-2">  Done</i>', 5)
                        .dismissOthers();
                }
            }
        });
    });

});

function aesEncrypt(text)
{
    return Aes.Ctr.encrypt(text, "cpm", 128);
}


function manageUpgradeScripts(file_number)
{
    var start_at = 0;
    var noitems_by_loop = 100;
    var loop_number = 0;

    if (file_number == 0) {
        $("#step4_progress").html("");
        alertify
            .success('Done', 1)
            .dismissOthers();
    }

    request = $.post("upgrade_scripts_manager.php",
        {
            file_number : parseInt(file_number)
        },
        function(data) {
            console.log(data[0])
            // work not finished
            if (data[0].finish !== "1") {
                // loop
                runUpdate(data[0].scriptname, data[0].parameter, start_at, noitems_by_loop, loop_number, file_number);
            }
            // work finished
            else {
                alertify
                    .success('Done with initialization phase', 3)
                    .dismissOthers();

                migrateUsersToV3('step1', '', 'init', createRandomId(), 0, false, false);
            }
        },
        "json"
    );
}

var usersList = [],
    previousStep = '',
    usersRestList = [];
/**
    * We need to migrate all users
    * This will change the users encryption and generate a OTC
    *
    * @return void
    */
function migrateUsersToV3(step, data, number, rand_number, loop_start, loop_finished)
{
    //console.log("> "+step+" - Number: "+number + " - Previous step: "+previousStep);
    //console.log(usersList);
    var d = new Date(),
        count_in_loop = <?php echo (int) NUMBER_ITEMS_IN_BATCH;?>,
        userSteps = {};


    // Decode data
    var newData = '';
    if (data !== '' && step === 'step1') {
        newData = JSON.parse(window.atob(data));
    }
    //console.log("TO DO : "+step+" -- "+number+" -- "+loop_start+" -- "+loop_finished+" -- "+newData.id)

    if (step === 'step1') {
        userInfo = '';
        // Show progress to user
        $("#user_"+rand_number).html("User id " + newData.id + " done <i class=\"fas fa-thumbs-up\" style=\"color:green\"></i>");

        if (newData !== '') {
            usersList.push(newData);
        }
        //console.log('List of users');
        //console.log(JSON.stringify(usersList))
        
        if (usersRestList.length > 0) {
            number = usersRestList[0];
            usersRestList.shift();
        } else if (number !== 'init') {
            number = 'end';
        }
        //console.log('List of next users');
        //console.log(JSON.stringify(usersRestList) + " ; Number = "+number)
        
        rand_number = createRandomId();
        $("#step4_progress").html("<div>" + getTime() + " - <span id='user_"+rand_number+"'>Treating User account ... <i class=\"fas fa-cog fa-spin\" style=\"color:orange\"></i></span></div>"+ $("#step4_progress").html());
        // --
        // --
    } else {
        // Prepare array                
        if (usersList[number] !== undefined) {
            userSteps = {
                'step1' : {
                    'text' : 'Users public / private keys were generated if needed',
                    'number' : 0,
                    'start' : 0
                },
                'step2' : {
                    'text' : 'User '+usersList[number].id+' - All ITEMS keys generated',
                    'number' : 0,
                    'start' : 0
                },
                'step3' : {
                    'text' : 'User '+usersList[number].id+' - All LOGS keys generated',
                    'number' : number,
                    'start' : 0
                },
                'step4' : {
                    'text' : 'User '+usersList[number].id+' - All FIELDS keys generated',
                    'number' : number,
                    'start' : 0
                },
                'step5' : {
                    'text' : 'User '+usersList[number].id+' - All SUGGESTIONS keys generated',
                    'number' : number,
                    'start' : 0
                },
                'step6' : {
                    'text' : 'User '+usersList[number].id+' - All FILES keys generated',
                    'number' : number,
                    'start' : 0
                },
                'nextUser' : {
                    'number' : parseInt(number) + 1,
                    'start' : 0
                }
            };
        }

        // What strategy to have for next treatment
        if (previousStep === 'step1') {
            $("#user_"+rand_number).html("Users public / private keys were generated if needed <i class=\"fas fa-thumbs-up\" style=\"color:green\"></i>");

            number = 0;
            loop_start = 0;
            // --
        } else if (previousStep === step) {
            // IF we are in the same step and we continue
            $("#user_"+rand_number).html(userSteps[previousStep].text + " until " + (loop_start + count_in_loop) + " done <i class=\"fas fa-thumbs-up\" style=\"color:green\"></i>");

            loop_start += count_in_loop;
            // --
        } else if (step !== 'nextUser' && loop_finished === "true") {
            // The loop on current topic is finished but still with same user
            $("#user_"+rand_number).html(userSteps[previousStep].text + " <i class=\"fas fa-thumbs-up\" style=\"color:green\"></i>");

            number = userSteps[step].number;
            loop_start = userSteps[step].start;
            // --
        } else if (step === 'nextUser') {
            // The treatment of current user is finished
            // Select next user
            $("#user_"+rand_number)
                .html(userSteps[previousStep].text + " <i class=\"fas fa-thumbs-up\" style=\"color:green\"></i>");

            $("#user_"+rand_number).parent()
                .prepend('<div>' + getTime() +' - User ' + usersList[number].id + ' fully treated <i class="fas fa-thumbs-up" style="color:green"></i></div>');                    

            // Have we handled all users
            // If not then restart at step2 for next user
            if ((parseInt(number)+1) < usersList.length) {
                number = userSteps[step].number;
                loop_start = userSteps[step].start;
                step = 'step2';
            } else {
                // Prepare list of users to be displayed
                var htmlUsersList = '<i class="fas fa-info-circle mr-2"></i>You could provide those unique codes to users by your own.<br>'+
                    '<ul>';
                $.each(usersList, function(index, user) {
                    htmlUsersList += '<li>User: '+user.name+' '+user.lastname+' (login: '+user.login+') ; OneTime code: '+user.otp+'</li>'
                });
                htmlUsersList += '</ul>';

                // Done
                $("#user_"+rand_number).parent()
					.prepend(
						'<div>' + getTime() +' - All keys have been generated for users <i class="fas fa-thumbs-up" style="color:green"></i>'+
						'<br>'+
						'<div type="button" class="btn btn-primary btn-sm" id="buttonListOfUsers">Show/Hide list of users</div>'+
						'<div class="hidden alert alert-secondary mt-2" id="htmlListOfUsers" role="alert">'+htmlUsersList+'</div>'+
						'</div>'
					);

				// Act on button click
				$(document).on('click', '#buttonListOfUsers', function() { 
					if ($('#htmlListOfUsers').hasClass('hidden') === true) {
						$('#htmlListOfUsers').removeClass('hidden');
					} else {
						$('#htmlListOfUsers').addClass('hidden');
					}
				});

                console.log(usersList);
                // Now send passwords
                console.log("Send emails to users from now");
                sendPwdToUsers(usersList, true, 0, usersList.length);

                return;
            }
        }
        
        if (usersList.length === 0 && step === 'step2' && loop_finished === 'true' && number === 0 && loop_start === 0) {
            /* Unlock this step */
            document.getElementById("but_next").disabled = "";
            document.getElementById("but_launch").disabled = "disabled";
            
            $("#user_"+rand_number).parent()
                .prepend('<div>' + getTime() +' - All steps have been successfully performed <i class="fas fa-thumbs-up" style="color:green"></i></div>'); 
            return;
        } else {
            // Show progress
            rand_number = createRandomId();
            $("#step4_progress").html("<div>" + getTime() +" - <span id='user_"+rand_number+"'>User "+usersList[number].id+" - Creating keys until "+(loop_start + count_in_loop)+" ... <i class=\"fas fa-cog fa-spin\" style=\"color:orange\"></i></span></div>"+ $("#step4_progress").html());

            // Prepare user information                
            userInfo = {
                'id' : usersList[number].id,
                'otp' : usersList[number].otp,
                'public_key' : usersList[number].public_key,
                'private_key' : usersList[number].private_key,
                'login' : usersList[number].login,
                'name' : encode_utf8(usersList[number].name),
                'lastname' : encode_utf8(usersList[number].lastname),
            };
        }
    }
    // Migrate if needed all account to new AES encryption
    //console.log('Posting number = '+number);
    $.post(
        "upgrade_run_3.0.0_users.php",
        {
            step : step,
            number : number === 'init' ? '' : number,
            userInfo : window.btoa(JSON.stringify(userInfo)),
            start : loop_start,
            count_in_loop : count_in_loop,
            info : $('#infotmp').val(),
            extra : number === 'end' ? 'all_users_created' : '',
        },
        function(data) {
            //console.log(data[0]);
            //console.log(JSON.parse(window.atob(data[0].rest)));
            //console.log(JSON.parse(window.atob(data[0].data)));
            previousStep = step;

            if (data[0].finish !== "1") {
                // Manage list of users that is provide on number = 0
                if ((number) === 'init' && step === 'step1') {
                    if (data[0].rest !== '') {
                        usersRestList = JSON.parse(window.atob(data[0].rest));
                    }else {
                        usersRestList = '';
                    }
                    console.log("USERLIST = "+usersRestList);
                }

                // loop
                migrateUsersToV3(
                    data[0].next,
                    data[0].data,
                    data[0].number,
                    rand_number,
                    loop_start,
                    data[0].loop_finished
                );
            } else {
                // Done
                /* Unlock this step */
                document.getElementById("but_next").disabled = "";
                document.getElementById("but_launch").disabled = "disabled";
            }
        },
        "json"
    );
}

/**
* 
 */
function sendPwdToUsers(usersList, init, cpt, total)
{
    var d = new Date();

    // Inform
    if (init === true) {
        rand_number = createRandomId();
        $("#step4_progress").html("<div>" + getTime() +" - <span id='user_"+rand_number+"'>Now sending new users password by email ... " +
            "<i class=\"fas fa-cog fa-spin\" style=\"color:orange\"></i><span id='sending_emails_pct' class='ml-3'>0%</span></div>"+ $("#step4_progress").html());
    }
    
    // Prepare user data to be sent
    userInfo = {
        'id' : usersList[0].id,
        'otp' : usersList[0].otp,
    };
    //console.log('Envoi email pour : ');
    //console.log(userInfo);

    // Remove user from list
    usersList.shift();

    // Send email for each user
    $.post(
        "upgrade_run_3.0.0_users.php",
        {
            step : 'send_pwd_by_email',
            userInfo : window.btoa(JSON.stringify(userInfo))
        },
        function(data) {
            //console.log(data);
            //console.log('----');
            // Done with sending the user email
            if (usersList.length === 0) {
                // all users have received their email
                $("#user_"+rand_number)
                    .html("Emails were sent <i class=\"fas fa-thumbs-up\" style=\"color:green\"></i>");

                /* Unlock this step */
                document.getElementById("but_next").disabled = "";
                document.getElementById("but_launch").disabled = "disabled";
                
                $("#user_"+rand_number).parent()
                .prepend('<div>' + getTime() +' - All steps have been successfully performed <i class="fas fa-thumbs-up" style="color:green"></i></div>'); 
                
            } else {
                // current user received his email
                // Send to the next one
                $("#sending_emails_pct").text(Math.round((cpt / total) * 100) + "%");

                sendPwdToUsers(usersList, false, cpt++, total);
            }
        },
        "json"
    );
}

function runUpdate (script_file, type_parameter, start_at, noitems_by_loop, loop_number, file_number)
{
    var d = new Date(),
        info = '';
    loop_number ++;
    var rand_number = createRandomId();

    $("#step4_progress").html("<div>" + getTime() +" - <i>"+script_file+"</i> - Loop #"+loop_number+" <span id='span_"+rand_number+"'>is now running ... <i class=\"fas fa-cog fa-spin\" style=\"color:orange\"></i></span></div>"+ $("#step4_progress").html());

    if (type_parameter === 'user_id') {
        info = $("#infotmp").val();
    }

    request = $.post(
        script_file,
        {
            type        : type_parameter,
            start       : start_at,
            total       : start_at,
            nb          : noitems_by_loop,
            session_salt: $("#session_salt").val(),
            info        : info,
        },
        function(data) {
            console.info(type_parameter)
            console.log(data)
            // work not finished
            if (data[0].finish !== "1") {
                $("#span_"+rand_number).html("<i class=\"fas fa-thumbs-up\" style=\"color:green\"></i>")
                // loop
                runUpdate(script_file, type_parameter, data[0].next, noitems_by_loop, loop_number, file_number);
            // is there an error
            } else if (data[0].finish === "1" && data[0].error !== "") {
                $("#span_"+rand_number).html("<i class=\"fas fa-thumbs-down\" style=\"color:red\"></i>");
                $("#step4_progress").html("<div style=\"margin:15px 0 15px 0; font-style:italic;\">"+getTime() +" - <b>ERROR</b>: "+data[0].error+"</div>"+ $("#step4_progress").html());
                $("#step4_progress").html("<div>An error occurred. Please check and relaunch.</div>"+ $("#step4_progress").html());
            // work finished
            } else {
                $("#span_"+rand_number).html("<i class=\"fas fa-thumbs-up\" style=\"color:green\"></i>")
                // continue with next script file
                file_number ++;
                manageUpgradeScripts(file_number);
            }
        },
        "json"
    );
}

function newEncryptPw(suggestion)
{
    var nb = 20;
    var start = 0;

    if ($("#change_pw_encryption_start").val() != "") {
        start = $("#change_pw_encryption_start").val();
    } else {
        $("#change_pw_encryption_progress").html('Progress: 0% <i class="fas fa-cog fa-spin fa-2x"></i>');
    }
    var request = $.post("upgrade_ajax.php",
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
                $("#change_pw_encryption_progress").html('Progress: '+data[0].progress+'% <i class="fas fa-cog fa-spin fa-2x"></i>');
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
    alertify
        .message('<i class="fas fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();

    request = $.post(
        "upgrade_ajax.php",
        {
            type      : "perform_database_dump"
        },
        function(data) {
            console.log(data)
            if (data[0].error !== "") {
                // ERROR
                $("#dump_result").html(data[0].error);

                alertify
                    .Error('Error', 1)
                    .dismissOthers();
            } else {
                // DONE
                $("#dump_result").html('<div class="alert alert-info mt-2">Dump is successfull. File stored in file <b>' + data[0].filename + '</b></div>');
                $('#but_next').attr("disabled", false);
                alertify
                    .success('Success', 1)
                    .dismissOthers();
            }
        },
        "json"
    );
}

function createRandomId()
{
    var randLetter = String.fromCharCode(65 + Math.floor(Math.random() * 26));
    return randLetter + Date.now();
}

function getTime()
{
    var d = new Date();
    return ("0" +d.getHours()).slice(-2)+":"+("0" + d.getMinutes()).slice(-2)+":"+("0" + d.getSeconds()).slice(-2)
}

function encode_utf8( s )
{
  return unescape( encodeURIComponent( s ) );
}

</script>

