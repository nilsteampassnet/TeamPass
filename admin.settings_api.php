<?php
/**
 *
 * @file          admin.settings_api.php
 * @author        Nils Laumaillé
 * @version       2.1.25
 * @copyright     (c) 2009-2015 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link		  http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('sources/sessions.php');
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
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/include.php';
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
	<div style="margin-bottom:3px;">
        <label for="api" style="width:350px;">' .
            $LANG['settings_api'].'
            &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_api_tip'].'" />
        </label>
        <span class="div_radio">
            <input type="radio" id="api_radio1" name="api" onclick="saveApiStatus(1)" value="1"', isset($_SESSION['settings']['api']) && $_SESSION['settings']['api'] == 1 ? ' checked="checked"' : '', ' /><label for="api_radio1">'.$LANG['yes'].'</label>
            <input type="radio" id="api_radio2" name="api" onclick="saveApiStatus(0)" value="0"', isset($_SESSION['settings']['api']) && $_SESSION['settings']['api'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['api']) ? ' checked="checked"':''), ' /><label for="api_radio2">'.$LANG['no'].'</label>
        </span>
    </div>
    <hr>
    <div style="margin-bottom:3px;">
        <label for="api" style="width:350px;"><b>' .$LANG['settings_api_keys_list'].'</b>
            &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_api_keys_list_tip'].'" />
            &nbsp;<input type="button" id="but_add_new_key" value="'.$LANG['settings_api_add_key'].'" onclick="newKeyDB()" class="ui-state-default ui-corner-all" />
        </label>
        <div id="api_keys_list">
            <table id="tbl_keys">
                <thead>
                <th>'.$LANG['label'].'</th>
                <th>'.$LANG['settings_api_key'].'</th>
                </thead>';
                $rows = DB::query(
                    "SELECT id, label, value FROM ".prefix_table("api")."
                    WHERE type = %s
                    ORDER BY timestamp ASC",
                    'key'
                );
                foreach ($rows as $record) {
                    echo '
                    <tr id="'.$record['id'].'">
						<td onclick="key_update($(this).closest(\'tr\').attr(\'id\'), $(this).text(), $(this).next(\'td\').text())" style="cursor:pointer;">'.$record['label'].'</td>
						<td>'.$record['value'].'</td>
						<td><img src="./includes/images/cross.png" onclick="deleteApiKey($(this).closest(\'tr\').attr(\'id\'))" title="" style="cursor:pointer;" /></td>
					</tr>';
                }
                echo '
            </table>
        </div>
    </div>
    <hr>
    <div style="margin-bottom:3px;">
        <label for="api" style="width:350px;"><b>'.$LANG['settings_api_ip_whitelist'].'</b>
            &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_api_ip_whitelist_tip'].'" />
            &nbsp;<input type="button" id="but_add_new_ip" value="'.$LANG['settings_api_add_ip'].'" onclick="newIPDB()" class="ui-state-default ui-corner-all" />
        </label>
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
                <th>'.$LANG['label'].'</th>
                <th>'.$LANG['settings_api_ip'].'</th>
                </thead>';
					$rows = DB::query(
                        "SELECT id, label, value FROM ".prefix_table("api")."
                        WHERE type = %s
                        ORDER BY timestamp ASC",
                        'ip'
                    );
					foreach ($rows as $record) {
						echo '
						<tr id="'.$record['id'].'">
						<td onclick="ip_update($(this).closest(\'tr\').attr(\'id\'), $(this).text(), $(this).next(\'td\').text())" style="cursor:pointer;">'.$record['label'].'</td>
						<td>'.$record['value'].'</td>
						<td><img src="./includes/images/cross.png" onclick="deleteApiKey($(this).closest(\'tr\').attr(\'id\'))" title="" style="cursor:pointer;" /></td>
					</tr>';
}
echo '
            </table>';
		}else {
			echo $LANG['settings_api_world_open'];
		}
		echo '
        </div>
    </div>
</div>';

// dialog box
echo '
<div id="api_db" style="display:none;">
    <input type="hidden" id="api_db_type" />
    <input type="hidden" id="api_db_action" />
    <input type="hidden" id="api_db_id" />
    <div id="api_db_message" style="display:none;"></div>
    <div id="api_db_intro"></div>
    <div style="margin-top:5px;">
        <span id="api_db_label_span" style="width:200px;"></span>
        <input type="text" id="api_db_label_input" size="50" />
    </div>
    <div style="margin-top:5px;" id="div_key">
        <span id="api_db_key_span" style="width:200px;"></span>
        <input type="text" id="api_db_key_input" disabled="disabled" size="50" />
    </div>
</div>';

echo '
<script type="text/javascript">

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
	        id		: id
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
	// hide save button
	$("#save_button").hide();

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
                        id		: $("#api_db_id").val()
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
});

function saveApiStatus(status)
{
	$.post(
	    "sources/admin.queries.php",
	    {
	        type    : "save_api_status",
	        status  : status
	    },
	    function(data) {

	    }
	);
	}
	</script>
	';