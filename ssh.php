<?php
/**
 *
 * @file          ssh.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('./sources/SecureHandler.php');
session_start();
if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 ||
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) ||
    !isset($_SESSION['key']) || empty($_SESSION['key']) || !isset($_GET['id'])
     || empty($_GET['key']) || $_GET['key'] != $_SESSION['key']
) {
    die('Hacking attempt...');
}
$_SESSION['settings']['enable_server_password_change']  = 1;
/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "home") || !isset($_SESSION['settings']['enable_server_password_change']) || $_SESSION['settings']['enable_server_password_change'] != 1) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
header("Content-type: text/html; charset=utf-8");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");

// connect to DB
require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = 'db_error_handler';
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);

// check user's token
$dataUser = DB::queryfirstrow(
    "SELECT key_tempo
    FROM ".prefix_table("users")."
    WHERE id=%i",
    $_SESSION['user_id']
);
if ($dataUser['key_tempo'] !== $_GET['key']) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

// get data about item
$dataItem = DB::queryfirstrow(
    "SELECT label, login, pw, pw_iv, url, auto_update_pwd_frequency
    FROM ".prefix_table("items")."
    WHERE id=%i",
    $_GET['id']
);
// decrypt password
$oldPwClear = cryption(
    $dataItem['pw'],
    "",
    "decrypt"
);

echo '
<div id="tabs">
    <ul>
        <li><a href="#tabs-1">'.$LANG['ssh_one_shot_change'].'</a></li>
        <li><a href="#tabs-2">'.$LANG['ssh_scheduled_change'].'</a></li>
    </ul>
    <div id="tabs-1">
        <div>
            <label for="ausp_ssh_root">'.$LANG['ssh_user'].':</label>&nbsp;
            <input type="text" id="ausp_ssh_root" class="menu_250 text ui-widget-content ui-corner-all" style="padding:3px;" value="'.$dataItem['login'].'" />
        </div>
        <div>
            <label for="ausp_ssh_pwd">'.$LANG['ssh_pwd'].':</label>&nbsp;
            <input type="password" id="ausp_ssh_pwd" class="menu_250 text ui-widget-content ui-corner-all" style="padding:3px;" value="'.$oldPwClear['string'].'" />
        </div>
        <div>
            <label for="ausp_pwd">'.$LANG['index_new_pw'].':</label>&nbsp;
            <input type="text" id="ausp_pwd" class="menu_250 text ui-widget-content ui-corner-all" style="padding:3px;" />
            &nbsp;<i id="ausp_but_generate" class="fa fa-refresh fa-border fa-sm tip" style="cursor:pointer;padding:3px;" title="'.htmlentities(strip_tags($LANG['click_to_generate']), ENT_QUOTES).'"></i>
            &nbsp;<i id="ausp_pwd_loader" style="display:none;margin-left:5px;" class="fa fa-cog fa-spin"></i>&nbsp;
        </div>
        <hr>
        <div id="dialog_auto_update_server_pwd_status" style="margin:15px 0 15px 0;">'.$LANG['auto_update_server_password_info'].'</div>
        <div id="dialog_auto_update_server_pwd_info" style="text-align:center;padding:5px;display:none;margin-top:10px;" class="ui-state-error ui-corner-all"></div>
        <hr>
        <a href="#" id="but_one_shot" class="button" onclick="start_one_shot_change()">'.$LANG['admin_action_db_backup_start_tip'].'</a>
    </div>
    <div id="tabs-2">
        <div style="margin-bottom:10px;">'.$LANG['ssh_password_frequency_change_info'].'</div>
        <label for="ausp_cron_freq">'.$LANG['ssh_password_frequency_change'].':</label>&nbsp;
        <select id="ssh_freq">
            <option value="0">0</option>
            <option value="1"', isset($dataItem['auto_update_pwd_frequency']) && $dataItem['auto_update_pwd_frequency'] == 1 ? "selected" : "", '>1</option>
            <option value="2"', isset($dataItem['auto_update_pwd_frequency']) && $dataItem['auto_update_pwd_frequency'] == 2 ? "selected" : "", '>2</option>
            <option value="3"', isset($dataItem['auto_update_pwd_frequency']) && $dataItem['auto_update_pwd_frequency'] == 3 ? "selected" : "", '>3</option>
            <option value="4"', isset($dataItem['auto_update_pwd_frequency']) && $dataItem['auto_update_pwd_frequency'] == 4 ? "selected" : "", '>4</option>
        </select>
        <div id="cronned_task_error" style="text-align:center;padding:5px;display:none;margin-top:10px;" class="ui-corner-all"></div>
        <hr>
        <a href="#" id="but_cronned_task" class="button" onclick="save_cronned_task()">'.$LANG['save_button'].'</a>
    </div>
</div>
';


?>
<script type="text/javascript">

function save_cronned_task()
{
    $("#cronned_task_error").hide();
    $.post(
        "sources/utils.queries.php",
        {
            type    : "server_auto_update_password_frequency",
            id      : $('#selected_items').val(),
            freq    : $('#ssh_freq').val(),
            key     : "<?php echo $_SESSION['key'];?>"
        },
        function(data) {
            if (data[0].error != "") {
                $("#cronned_task_error")
                    .html("Error: "+data[0].error)
                    .show()
                    .removeClass( "ui-state-focus" )
                    .addClass( "ui-state-error" );
            } else {
                $("#cronned_task_error")
                    .html("<?php echo $LANG['alert_message_done'];?>")
                    .show()
                    .removeClass( "ui-state-error" )
                    .addClass( "ui-state-focus" );
            }
        },
        "json"
    );
}

function start_one_shot_change()
{
    // check if new password is set
    if($("#ausp_pwd").val() == "") {
        $("#dialog_auto_update_server_pwd_info").html('<i class="fa fa-warning"></i>&nbsp;<?php echo $LANG['error_new_pwd_missing'];?>').show();
        return false;
    }
    // check if new password is set
    if($("#ausp_ssh_root").val() == "" || $("#ausp_ssh_pwd").val() == "") {
        $("#dialog_auto_update_server_pwd_info").html('<i class="fa fa-warning"></i>&nbsp;<?php echo $LANG['error_ssh_credentials_missing'];?>').show();
        return false;
    }
    // show progress
    $("#dialog_auto_update_server_pwd_status").html('<i class="fa fa-cog fa-spin"></i>&nbsp;<?php echo $LANG['please_wait'];?>&nbsp;...&nbsp;').attr("class","").show();
    $("#dialog_auto_update_server_pwd_info").html("").hide();
    //prepare data
        var data = '{"currentId":"'+$('#selected_items').val() + '", '+
        '"new_pwd":"'+$('#ausp_pwd').val()+'", '+
        '"ssh_root":"'+$('#ausp_ssh_root').val()+'", '+
        '"ssh_pwd":"'+$('#ausp_ssh_pwd').val()+'", '+
        '"user_id":"<?php echo $_SESSION['user_id'];?>"}';

    $.post(
        "sources/utils.queries.php",
        {
            type        : "server_auto_update_password",
            data        : prepareExchangedData(data, "encode", "<?php echo $_SESSION['key'];?>"),
            key         : "<?php echo $_SESSION['key'];?>"
        },
        function(data) {
            data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key'];?>");
            //check if format error
            if (data.error != "") {
                $("#dialog_auto_update_server_pwd_info").html("Error: "+data.error).show();
                $("#dialog_auto_update_server_pwd_status").html("<?php echo $LANG['auto_update_server_password_info'];?>");
            } else {
                // tbc
                $("#dialog_auto_update_server_pwd_status").html("done "+data.text);
                // change password in item form
                $('#edit_pw1').val($('#ausp_pwd').val());
                $("#hid_pw").val($('#ausp_pwd').val());
                // change quick password
                new Clipboard("#menu_button_copy_pw, #button_quick_pw_copy", {
                    text: function() {
                        return unsanitizeString($('#edit_pw1').val());
                    }
                });

                $("#button_quick_pw_copy").show();
            }
        }
    );
}

function generate_pw()
{
    $("#ausp_pwd_loader").show();
    $.post(
        "sources/main.queries.php",
        {
            type       : "generate_a_password",
            size       : 12,
            secure     : false,
            symbols    : true,
            capitalize : true,
            numerals   : true
        },
        function(data) {
            data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key'];?>");
            if (data.error == "true") {
                $("#dialog_auto_update_server_pwd_info").html(data.error_msg).show();
            } else {
                $("#ausp_pwd").val(data.key);
            }
            $("#ausp_pwd_loader").hide();
        }
    );
}

$(function() {
    $("#tabs").tabs();

    $(".button")
    .button()
    .click(function(event) {
        event.preventDefault();
    });

    // generate new pw at opening
    generate_pw();

    // button to generate
    $("#ausp_but_generate").click(function() {
        generate_pw();
    });
});



</script>