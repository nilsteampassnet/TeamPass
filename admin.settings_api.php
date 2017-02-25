<?php
/**
 *
 * @file          admin.settings_api.php
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
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html><head><title>API Settings</title>
<style type="text/css">
table {
  width: 100%;
}
td {
vertical-align: top;
}
th:nth-child(1) {
  width: 25%;
}
th:nth-child(2) {
  width: 60%;
}
th:nth-child(3) {
  width: 10%;
  display: hidden;
}
.maintable-left {
  width: 40%;
}
.maintable-right {
  width: 60%;
}
.fa-chevron-right {
margin-right: .8em;
}
.tip {
cursor:pointer;
}
.keytable tr {
background-color: white;
padding: 0 5px 0 5px;
}
.keytable td:nth-child(3) {
 text-align: center;
}


</style>
</head><body>
<?php
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

echo '
<div id="tabs-9">
  <table>
    <tbody>
<!-- Enable API (toggle) -->
      <tr>
        <td class="maintable-left">
          <label for="api">
          <i class="fa fa-chevron-right mi-grey-1"></i>' .
            $LANG['settings_api'].'
            &nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_api_tip']), ENT_QUOTES).'"></i>
          </label>
        </td>
        <td class="maintable-right">
          <div class="toggle toggle-modern" id="api" data-toggle-on="', isset($_SESSION['settings']['api']) && $_SESSION['settings']['api'] == 1 ? 'true' : 'false', '">
          </div>
          <div><input type="hidden" id="api_input" name="api_input" value="', isset($_SESSION['settings']['api']) && $_SESSION['settings']['api'] == 1 ? '1' : '0', '" />
        </div>
        </td>
      </tr>

      <tr>
        <td colspan="2"><hr />
        </td>
      </tr>

<!-- API Keys (table) -->
      <tr class="hideable">
        <td>
          <i class="fa fa-chevron-right mi-grey-1"></i>
          <label for="api_keys_list">' .$LANG['settings_api_keys_list'].'
          &nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_api_keys_list_tip']), ENT_QUOTES).'"></i>
          </label>
        </td>
        <td>
          <div id="api_keys_list">
            <table id="tbl_keys">
              <thead>
                <tr>
                <th>'.$LANG['label'].'</th>
                <th>'.$LANG['settings_api_key'].'</th>
                <th></th>
                </tr>
              </thead>
              <tbody class="keytable">
                <tr style="display: none ! important;">
                  <td>HTML validation placeholder</td>
                </tr>';
                $rows = DB::query(
                    "SELECT id, label, value FROM ".prefix_table("api")."
                    WHERE type = %s
                    ORDER BY timestamp ASC",
                    'key'
                );
                foreach ($rows as $record) {
                echo '
                  <tr id="apiid'.$record['id'].'">
                    <td id="apiid'.$record['id'].'label">'.$record['label'].'</td>
                    <td id="apiid'.$record['id'].'value">'.$record['value'].'</td>
                    <td>
                      <i class="fa fa-pencil tip" onclick="key_update(\''.$record['id'].'\', $(\'#apiid'.$record['id'].'label\').text(), $(\'#apiid'.$record['id'].'value\').text())" title="'.htmlentities(strip_tags($LANG['edit']), ENT_QUOTES).'"></i>&nbsp;
                      <i class="fa fa-trash mi-red tip" onclick="deleteApiKey(\''.$record['id'].'\')" title="'.htmlentities(strip_tags($LANG['del_button']), ENT_QUOTES).'"></i></td>
                    </td>
                    
                </tr>';
                }
                echo '
              </tbody>
            </table>
          </div>
          <br /><input type="button" id="but_add_new_key" value="'.$LANG['settings_api_add_key'].'" onclick="newKeyDB()" class="ui-state-default ui-corner-all" />
        </td>
      </tr>

      <tr class="hideable">
        <td colspan="2"><hr />
        </td>
      </tr>

<!-- API IP Whitelist (table) -->
      <tr class="hideable">
        <td>
          <i class="fa fa-chevron-right mi-grey-1"></i>
          <label for="api">
            '.$LANG['settings_api_ip_whitelist'].'&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_api_ip_whitelist_tip']), ENT_QUOTES).'"></i>
          </label>
 	 	    </td>
      	<td>
        <div id="api_ips_list">';
          $data = DB::query(
            "SELECT id, label, value FROM ".prefix_table("api")."
            WHERE type = %s",
            'ip'
          );
          $counter = DB::count();
          if ($counter != 0) {
            echo '
            <table id="tbl_ips">
              <thead>
                <tr>
                  <th>'.$LANG['label'].'</th>
                  <th>'.$LANG['settings_api_ip'].'</th>
                  <th></th>
                </tr>
              </thead>
              <tbody class="keytable">';
                $rows = DB::query(
                    "SELECT id, label, value FROM ".prefix_table("api")."
                    WHERE type = %s
                    ORDER BY timestamp ASC",
                    'ip'
                );
                foreach ($rows as $record) {
                  echo '
                  <tr id="apiid'.$record['id'].'">
                    <td id="apiid'.$record['id'].'label">'.$record['label'].'</td>
                    <td id="apiid'.$record['id'].'value">'.$record['value'].'</td>
                    <td>
                      <i class="fa fa-pencil tip" onclick="ip_update(\''.$record['id'].'\', $(\'#apiid'.$record['id'].'label\').text(), $(\'#apiid'.$record['id'].'value\').text())" title="'.htmlentities(strip_tags($LANG['edit']), ENT_QUOTES).'"></i>&nbsp;
                      <i class="fa fa-trash mi-red tip" onclick="deleteApiKey(\''.$record['id'].'\')" title="'.htmlentities(strip_tags($LANG['del_button']), ENT_QUOTES).'"></i></td>
                  </tr>';
                }
              echo '
              </tbody>
            </table>
            ';
          }else {
            echo $LANG['settings_api_world_open'];
          }
        echo '
        </div>
        <br />
        <input type="button" id="but_add_new_ip" value="'.$LANG['settings_api_add_ip'].'" onclick="newIPDB()" class="ui-state-default ui-corner-all" />        
        </td>
      </tr>
    </tbody>
  </table>
</div>';

// dialog box
echo '
<div id="api_db" style="display:none;">
    <input type="hidden" id="api_db_type" />
    <input type="hidden" id="api_db_action" />
    <input type="hidden" id="api_db_id" />
    <div id="api_db_message" style="display:none;"></div>
    <div id="api_db_intro"></div>
    <div>
        <span id="api_db_label_span"></span>
        <input type="text" id="api_db_label_input" size="50" />
    </div>
    <div id="div_key">
        <span id="api_db_key_span" ></span>
        <input type="text" id="api_db_key_input" disabled="disabled" size="50" />
    </div>
</div>';

echo '
<script type="text/javascript">
//<![CDATA[
$(".tip").tooltipster({
    maxWidth: 400,
    contentAsHTML: true
});

/* FUNCTIONS FOR KEYS */
function newKeyDB()
{
    $("#api_db_type").val("admin_action_api_save_key");
    $("#api_db_action").val("add");
    $("#api_db_intro").html("'.$LANG['settings_api_db_intro'].'");
    $("#api_db_label_span").html("'.$LANG['label'].'");
    $("#api_db_key_span").html("'.$LANG['settings_api_key'].'");
    $("#api_db_label_input, #api_db_key_input").val("");
    $("#api_db_key_input").prop("disabled", true);
    $("#div_key").show();
    generateApiKey();
    $("#api_db").dialog("open");
}

function key_update(id, value, key)
{
    $("#api_db_type").val("admin_action_api_save_key");
    $("#api_db_id").val(id);
    $("#api_db_action").val("update");
    $("#api_db_intro").html("'.$LANG['settings_api_db_intro'].'");
    $("#api_db_label_span").html("'.$LANG['label'].'");
    $("#api_db_key_span").html("'.$LANG['settings_api_key'].'");
    $("#api_db_label_input").val(value);
    $("#div_key").show();
    $("#api_db_key_input").prop("disabled", true).val(key);
    $("#api_db").dialog("open");
}

function generateApiKey()
{
    $.post(
        "sources/main.queries.php",
        {
            type    : "generate_a_password",
            size    : "39",
            numerals    : "true",
            capitalize  : "true",
            symbols    : "false",
            secure    : "false"
        },
        function(data) {
            data = prepareExchangedData(data, "decode", "'.$_SESSION["key"].'");
            if (data.error == "true") {
                $("#api_db_message").html(data.error_msg);
            } else {
                $("#api_db_key_input").val(data.key).focus();
            }
        }
    );
}

function deleteApiKey(id)
{
    $.post(
        "sources/admin.queries.php",
        {
            type    : "admin_action_api_save_key",
            action  : "delete",
            label   : "",
            key     : "",
            id      : id
        },
        function(data) {
            var current_index = $("#tabs").tabs("option","active");
            $("#tabs").tabs("load",current_index);
            $("#div_loading").hide();
        },
        "json"
    );
}

/* FUNCTIONS FOR IPS */
function newIPDB()
{
    $("#api_db_type").val("admin_action_api_save_ip");
    $("#api_db_action").val("add");
    $("#api_db_intro").html("'.$LANG['settings_api_db_intro'].'");
    $("#api_db_label_span").html("'.$LANG['label'].'");
    $("#api_db_key_span").html("'.$LANG['settings_api_ip'].'");
    $("#api_db_label_input, #api_db_key_input").val("");
    $("#api_db_key_input").prop("disabled", false);
    $("#div_key").show();
    $("#api_db").dialog("open");
}

function ip_update(id, value, ip)
{
    $("#api_db_type").val("admin_action_api_save_ip");
    $("#api_db_id").val(id);
    $("#api_db_action").val("update");
    $("#div_key").show();
    $("#api_db_intro").html("'.$LANG['settings_api_db_intro'].'");
    $("#api_db_label_span").html("'.$LANG['label'].'");
    $("#api_db_key_span").html("'.$LANG['settings_api_ip'].'");
    $("#api_db_label_input").val(value);
    $("#api_db_key_input").prop("disabled", false);
    $("#api_db_key_input").val(ip);
    $("#api_db").dialog("open");
}

$(function() {
    $("#api_db").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 250,
        title: "'.$LANG['confirm'].'",
        buttons: {
            "'.$LANG['confirm'].'": function() {
                $("#api_db_message").html("");
                if ($("#api_db_label_input").val().length > 255 || $("#api_db_key_input").val().length > 255) {
                    $("#api_db_message").html("'.$LANG['error_too_long'].'");
                    exit;
                }

                $("#div_loading").show();
                var $this = $(this);
                // send query
                $.post(
                    "sources/admin.queries.php",
                    {
                        type    : $("#api_db_type").val(),
                        action  : $("#api_db_action").val(),
                        label   : sanitizeString($("#api_db_label_input").val()),
                        key     : sanitizeString($("#api_db_key_input").val()),
                        id      : $("#api_db_id").val()
                    },
                    function(data) {
                        // reload tab
                        if ($("#api_db_action").val() == "add") {
                            var current_index = $("#tabs").tabs("option","active");
                            $("#tabs").tabs("load",current_index);
                        } else if ($("#api_db_action").val() == "update") {
                            var current_index = $("#tabs").tabs("option","active");
                            $("#tabs").tabs("load",current_index);
                        }

                        $("#div_loading").hide();
                        $("#api_db").dialog("close");
                    },
                    "json"
               );
            },
            "'.$LANG['cancel_button'].'": function() {
                $("#div_loading").hide();
                $(this).dialog("close");
            }
        }
    });

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
            if(e.target.id == "api") $(".hideable").show();
        } else {
            $("#"+e.target.id+"_input").val(0);
            if(e.target.id == "api") $(".hideable").hide();
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
                    $("#message_box").html("Error: Server reply cannot be parsed!<br />Returned data:<br />"+data).show().fadeOut(4000);

                    return;
                }
                console.log(data);
                if (data.error == "") {
                    $("#"+e.target.id).before("<i class=\"fa fa-check fa-lg mi-green new_check\" style=\"float:right;\"></i>");
                    $(".new_check").fadeOut(4000);
                    setTimeout("$(\".new_check\").remove()", 4000);
                }
            }
        );
    });
});
//]]>
</script></body></html>';