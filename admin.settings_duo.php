<?php
/**
 *
 * @file          admin.settings_duo.php
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
require_once('sources/SecureHandler.php');
session_start();
if (
        !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 ||
        !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) ||
        !isset($_SESSION['key']) || empty($_SESSION['key']))
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_settings")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
header("Content-type: text/html; charset=utf-8");
require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

// connect to the server
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

//get infos from SETTINGS.PHP file
$filename = $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
$events = "";
if (file_exists($filename)) {
    //copy some constants from this existing file
    $settingsFile = file($filename);
    while (list($key,$val) = each($settingsFile)) {
        if (substr_count($val, 'require_once "')>0 && substr_count($val, 'sk.php')>0) {
            $tmp_skfile = substr($val, 14, strpos($val, '";')-14);
        }
    }
}

// read SK.PHP file
$tmp_akey = $tmp_ikey = $tmp_skey = $tmp_host = "";
$skFile = file($tmp_skfile);
while (list($key,$val) = each($skFile)) {
    if (substr_count($val, "@define('AKEY'")>0) {
        $tmp_akey = substr($val, 17, strpos($val, '")')-17);
    } else
    if (substr_count($val, "@define('IKEY'")>0) {
        $tmp_ikey = substr($val, 17, strpos($val, '")')-17);
    } else
    if (substr_count($val, "@define('SKEY'")>0) {
        $tmp_skey = substr($val, 17, strpos($val, '")')-17);
    } else
    if (substr_count($val, "@define('HOST'")>0) {
        $tmp_host = substr($val, 17, strpos($val, '")')-17);
    }
}
echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html><head><title>Two-factor Settings</title>
<style type="text/css">
table {
  width: 100%;
}
td {
  vertical-align: top;
}
.fa-chevron-right {
  margin-right: .6em;
}
.fa-question-circle {
  margin-left: .6em;
}
.tip {
  cursor:pointer;
}
.googleauth td:nth-child(1) {
  width: 40%;
}
.googleauth td:nth-child(2) {
  width: 60%;
}
.google_enabled { ', isset($_SESSION['settings']['google_authentication']) && $_SESSION['settings']['google_authentication'] == 1 ? '' : 'display:none;', ' }
.duo_enabled { ', isset($_SESSION['settings']['duo']) && $_SESSION['settings']['duo'] == 1 ? '' : 'display:none;', ' }
.agses_enabled { ', isset($_SESSION['settings']['agses_authentication_enabled']) && $_SESSION['settings']['agses_authentication_enabled'] == 1 ? '' : 'display:none;', ' }
</style>
</head><body>';



echo '
<div id="tabs-9">
  <table><tbody>
<!-- Google Authentication toggle -->
    <tr class="googleauth">
      <td>
        <label>
          <i class="fa fa-chevron-right mi-grey-1"></i>
          '.$LANG['admin_2factors_authentication_setting'].'
          <i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_2factors_authentication_setting_tip']), ENT_QUOTES).'"></i>
        </label>
      </td>
      <td>
        <div class="toggle toggle-modern" id="google_authentication" data-toggle-on="', isset($_SESSION['settings']['google_authentication']) && $_SESSION['settings']['google_authentication'] == 1 ? 'true' : 'false', '">
        </div>
        <input type="hidden" id="google_authentication_input" name="google_authentication_input" value="', isset($_SESSION['settings']['google_authentication']) && $_SESSION['settings']['google_authentication'] == 1 ? '1' : '0', '" />
      </td>
    </tr>

<!-- // Google Authentication name -->
    <tr class="googleauth google_enabled">
      <td>
        <label for="ga_website_name">
          <i class="fa fa-chevron-right mi-grey-1"></i>
          '.$LANG['admin_ga_website_name'].'<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_ga_website_name_tip']), ENT_QUOTES).'"></i>
        </label>
      </td>
      <td>
        <input type="text" size="30" id="ga_website_name" name="ga_website_name" value="', isset($_SESSION['settings']['ga_website_name']) ? $_SESSION['settings']['ga_website_name'] : 'not set', '" class="text ui-widget-content" />
        <input type="button" onclick="SaveFA()" value="'.$LANG['save_button'].'" class="ui-state-default ui-corner-all" />
        <span id="save_wait" style="display: none;"><i class="fa fa-cog fa-spin"></i></span>
      </td>
    </tr>

<!-- // ga_reset_by_user -->
    <tr class="googleauth google_enabled">
      <td>
        <label for="ga_reset_by_user">
          <i class="fa fa-chevron-right mi-grey-1"></i>
          '.$LANG['ga_reset_by_user'].'<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['ga_reset_by_user_tip']), ENT_QUOTES).'"></i>
        </label>
      </td>
      <td>
        <div class="toggle toggle-modern" id="ga_reset_by_user" data-toggle-on="', isset($_SESSION['settings']['ga_reset_by_user']) && $_SESSION['settings']['ga_reset_by_user'] == 1 ? 'true' : 'false', '">
        </div>
        <input type="hidden" id="ga_reset_by_user_input" name="ga_reset_by_user_input" value="', isset($_SESSION['settings']['ga_reset_by_user']) && $_SESSION['settings']['ga_reset_by_user'] == 1 ? '1' : '0', '" />
      </td>
    </tr>

    <tr>
      <td colspan="2"><hr />
      </td>
    </tr>

<!-- // Duo Security toggle -->
    <tr>
      <td>
        <label for="duo">
          <i class="fa fa-chevron-right mi-grey-1"></i>
          '.$LANG['settings_duo'].'
          <i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_duo_tip']), ENT_QUOTES).'"></i>
        </label>
      </td>
      <td>
        <div class="toggle toggle-modern" id="duo" data-toggle-on="', isset($_SESSION['settings']['duo']) && $_SESSION['settings']['duo'] == 1 ? 'true' : 'false', '">
        </div>
        <input type="hidden" id="duo_input" name="duo_input" value="', isset($_SESSION['settings']['duo']) && $_SESSION['settings']['duo'] == 1 ? '1' : '0', '" />
      </td>
    </tr>

    <!-- // Duo Security keys -->
    <tr class="duo_enabled">
      <td>
        <i class="fa fa-chevron-right mi-grey-1"></i>
        '.$LANG['admin_duo_intro'].'
        <i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_duo_explanation']), ENT_QUOTES).'"></i>
      </td>
      <td>
        <input type="button" onclick="GenerateCryptKey(40)" value="'.$LANG['generate_random_key'].'" class="ui-state-default ui-corner-all" />
        <span id="generate_wait" style="display: none;"><i class="fa fa-cog fa-spin"></i></span>
        <table>
          <tr>
            <td>
              <label for="duo_akey">
                '.$LANG['admin_duo_akey'].'
              </label>
            </td>
            <td>
              <input type="text" size="60" id="duo_akey" name="duo_akey" value="'.$tmp_akey.'" class="text ui-widget-content" />
            </td>
          </tr>
          <tr>
            <td>
              <label for="duo_ikey">'.$LANG['admin_duo_ikey'].'</label>
            </td>
            <td>
              <input type="text" size="60" id="duo_ikey" name="duo_ikey" value="'.$tmp_ikey.'" class="text ui-widget-content" />
            </td>
          </tr>
          <tr>
            <td>
              <label for="duo_skey">'.$LANG['admin_duo_skey'].'</label>
            </td>
            <td>
              <input type="text" size="60" id="duo_skey" name="duo_skey" value="'.$tmp_skey.'" class="text ui-widget-content" />
            </td>
          </tr>
          <tr>
            <td>
              <label for="duo_host">'.$LANG['admin_duo_host'].'</label>
            </td>
            <td>
              <input type="text" size="60" id="duo_host" name="duo_host" value="'.$tmp_host.'" class="text ui-widget-content" />
            </td>
          </tr>
        </table>
      </td>
    </tr>

<!-- // Duo Security save button -->
    <tr class="duo_enabled">
      <td>
        <i class="fa fa-chevron-right mi-grey-1"></i>
        '.$LANG['duo_save_sk_file'].'
      </td>
      <td>
        <div>
              <input type="button" onclick="SaveKeys()" value="'.$LANG['save_button'].'" class="ui-state-default ui-corner-all" />
              <span id="save_wait" style="display: none;">
                <i class="fa fa-cog fa-spin"></i>
              </span>
        </div>
      </td>
    </tr>

    <tr>
      <td colspan="2"><hr />
      </td>
    </tr>

<!-- // AgSES -->
    <tr class="agses">
      <td>
        <label>
          <i class="fa fa-chevron-right mi-grey-1"></i>
          '.$LANG['admin_agses_authentication_setting'].'
          <i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_agses_authentication_setting_tip']), ENT_QUOTES).'"></i>
        </label>
      </td>
      <td>
        <div class="toggle toggle-modern" id="agses_authentication_enabled" data-toggle-on="', isset($_SESSION['settings']['agses_authentication_enabled']) && $_SESSION['settings']['agses_authentication_enabled'] == 1 ? 'true' : 'false', '">
        </div>
        <input type="hidden" id="agses_authentication_enabled_input" name="agses_authentication_enabled_input" value="', isset($_SESSION['settings']['agses_authentication_enabled']) && $_SESSION['settings']['agses_authentication_enabled'] == 1 ? '1' : '0', '" />
      </td>
    </tr>

<!-- // AgSES API -->
    <tr class="agses agses_enabled">
      <td>
        <label for="agses_api_key">
          <i class="fa fa-chevron-right mi-grey-1"></i>
          '.$LANG['admin_agses_hosted'].'<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_agses_hosted_tip']), ENT_QUOTES).'"></i>
        </label>
      </td>
      <td>
        <table>
          <tr>
            <td>
              <label for="duo_akey">
                '.$LANG['admin_agses_hosted_url'].'
              </label>
            </td>
            <td>
              <input type="text" size="60" id="agses_hosted_url" value="', isset($_SESSION['settings']['agses_hosted_url']) ? $_SESSION['settings']['agses_hosted_url'] : '', '" class="text ui-widget-content" />
            </td>
          </tr>
          <tr>
            <td>
               '.$LANG['admin_agses_hosted_id'].'
              </label>
            </td>
            <td>
              <input type="text" size="60" id="agses_hosted_id" value="', isset($_SESSION['settings']['agses_hosted_id']) ? $_SESSION['settings']['agses_hosted_id'] : '', '" class="text ui-widget-content" />
            </td>
          </tr>
          <tr>
            <td>
               '.$LANG['admin_agses_hosted_apikey'].'
              </label>
            </td>
            <td>
              <input type="text" size="60" id="agses_hosted_apikey" value="', isset($_SESSION['settings']['agses_hosted_apikey']) ? $_SESSION['settings']['agses_hosted_apikey'] : '', '" class="text ui-widget-content" />
            </td>
          </tr>
        </table>
        </td>
      </tr>

      <tr class="agses agses_enabled">
        <td>
        <label>
          <i class="fa fa-chevron-right mi-grey-1"></i>
          '.$LANG['admin_agses_save'].'
        </label>
      </td>
        <td>
        <input type="button" onclick="SaveAgses()" value="'.$LANG['save_button'].'" class="ui-state-default ui-corner-all" />
        <span id="save_agses_wait" style="display: none;">
        <i class="fa fa-cog fa-spin"></i>
        </span>
      </td>
    </tr>

  </tbody></table>
</div>';

echo '
<script type="text/javascript">
//<![CDATA[
function saveDuoStatus(status)
{
    $("#save_status_wait").show();
    $.post(
            "sources/admin.queries.php",
            {
                type    : "save_duo_status",
                status  : status
            },
            function(data) {
                $("#main_info_box_text").html("'.$LANG['alert_message_done'].'");
                $("#main_info_box").show().position({
                    my: "center",
                    at: "center top+75",
                    of: "#top"
                });
                setTimeout(function(){$("#main_info_box").effect( "fade", "slow" );}, 1000);
                if (status == 0) $("#duo_enabled").hide();
                else $("#duo_enabled").show();
                $("#save_status_wait").hide();
            }
    );
}

function GenerateCryptKey(size)
{
    $("#generate_wait").show();
    $.post(
        "sources/main.queries.php",
        {
            type : "generate_new_key",
            size : size
        },
        function(data) {
            $("#duo_akey").val(data[0].key);
            $("#generate_wait").hide();
        },
        "json"
    );
}

function SaveKeys()
{
    $("#save_wait").show();

    var data = "{\"akey\":\""+sanitizeString($("#duo_akey").val())+"\", \"ikey\":\""+sanitizeString($("#duo_ikey").val())+"\", \"skey\":\""+sanitizeString($("#duo_skey").val())+"\", \"host\":\""+sanitizeString($("#duo_host").val())+"\"}";
    $.post(
        "sources/admin.queries.php",
        {
            type : "save_duo_in_sk_file",
            data : prepareExchangedData(data, "encode", "'.$_SESSION['key'].'"),
            key  : "'.$_SESSION['key'].'"
        },
        function(data) {
            if (data[0].error == "") {
                $("#main_info_box_text").html(data[0].result);
            } else {
                $("#main_info_box_text").html(data[0].error);
            }
            $("#main_info_box").show().position({
                my: "center",
                at: "center top+75",
                of: "#top"
            });
            setTimeout(function(){$("#main_info_box").effect( "fade", "slow" );}, 2000);
            $("#save_wait").hide();
        },
        "json"
    );
}

$(".tip").tooltipster({
    maxWidth: 400,
    contentAsHTML: true
});

function SaveFA()
{
    $("#savefa_wait").show();

    var data = "{\"google_authentication\":\""+$("input[name=\"google_authentication\"]").prop("checked")+"\", \"ga_website_name\":\""+sanitizeString($("#ga_website_name").val())+"\"}";
    $.post(
        "sources/admin.queries.php",
        {
            type : "save_google_options",
            data : prepareExchangedData(data, "encode", "'.$_SESSION['key'].'"),
            key  : "'.$_SESSION['key'].'"
        },
        function(data) {
            if (data[0].error == "") {
                $("#main_info_box_text").html(data[0].result);
                console.log($("input[name=\"google_authentication\"]").prop("checked"));
                if ($("input[name=\"google_authentication\"]").prop("checked") == "true") {
                    $("#temp_session_fa_status").val("1");
                } else {
                    $("#temp_session_fa_status").val("0");
                }
                $("#temp_session_fa_website").val($("#ga_website_name").val());
            } else {
                $("#main_info_box_text").html(data[0].error);
            }
            $("#main_info_box").show().position({
                my: "center",
                at: "center top+75",
                of: "#top"
            });
            setTimeout(function(){$("#main_info_box").effect( "fade", "slow" );}, 2000);
            $("#savefa_wait").hide();
        },
        "json"
    );
}

function SaveAgses()
{
    $("#save_agses_wait").show();

    var data = "{\"agses_hosted_url\":\""+sanitizeString($("#agses_hosted_url").val())+"\" , \"agses_hosted_id\":\""+sanitizeString($("#agses_hosted_id").val())+"\" , \"agses_hosted_apikey\":\""+sanitizeString($("#agses_hosted_apikey").val())+"\"}";
    $.post(
        "sources/admin.queries.php",
        {
            type : "save_agses_options",
            data : prepareExchangedData(data, "encode", "'.$_SESSION['key'].'"),
            key  : "'.$_SESSION['key'].'"
        },
        function(data) {
            if (data[0].error == "") {
                $("#main_info_box_text").html(data[0].result);
            } else {
                $("#main_info_box_text").html(data[0].error);
            }
            $("#main_info_box").show().position({
                my: "center",
                at: "center top+75",
                of: "#top"
            });
            setTimeout(function(){$("#main_info_box").effect( "fade", "slow" );}, 2000);
            $("#save_agses_wait").hide();
        },
        "json"
    );
}

$(function() {
    $(".toggle").toggles({
        drag: true, // allow dragging the toggle between positions
        click: true, // allow clicking on the toggle
        text: {
            on: "'.$LANG['yes'].'", // text for the ON position
            off: "'.$LANG['no'].'" // and off
        },
        on: true, // is the toggle ON on init
        animate: 250, // animation time (ms)
        easing: "swing", // animation transition easing function
        width: 50, // width used if not set in css
        height: 20, // height if not set in css
        type: "compact" // if this is set to "select" then the select style toggle will be used
    });
    $(".toggle").on("toggle", function(e, active) {
        if (active) {
            $("#"+e.target.id+"_input").val(1);
            if(e.target.id == "duo") $(".duo_enabled").show();
            if(e.target.id == "google_authentication") $(".google_enabled").show();
            if(e.target.id == "agses_authentication_enabled") $(".agses_enabled").show();
        } else {
            $("#"+e.target.id+"_input").val(0);
            if(e.target.id == "duo") $(".duo_enabled").hide();
            if(e.target.id == "google_authentication") $(".google_enabled").hide();
            if(e.target.id == "agses_authentication_enabled") $(".agses_enabled").hide();
        }
        // store in DB
        var data = "{\"field\":\""+e.target.id+"\", \"value\":\""+$("#"+e.target.id+"_input").val()+"\"}";
        $.post(
            "sources/admin.queries.php",
            {
                type    : "save_option_change",
                data     : prepareExchangedData(data, "encode", "'.$_SESSION['key'].'"),
                key     : "'.$_SESSION['key'].'"
            },
            function(data) {
                //decrypt data
                try {
                    data = prepareExchangedData(data , "decode", "'.$_SESSION['key'].'");
                } catch (e) {
                    // error
                    $("#message_box").html("An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />"+data).show().fadeOut(4000);

                    return;
                }
                console.log(data);
                if (data.error == "") {
                    $("#"+e.target.id).before("<i class=\"fa fa-check fa-lg mi-green new_check\" style=\"float:right;\"></i>");
                    $(".new_check").fadeOut(2000);
                    setTimeout("$(\".new_check\").remove()", 2100);
                }
            }
        );
    });
});
//]]>
</script>

</body></html>';