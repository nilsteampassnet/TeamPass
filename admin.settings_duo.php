<?php
/**
 *
 * @file          admin.settings_duo.php
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

//get infos from SETTINGS.PHP file
$filename = $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
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
<div id="tabs-9">
	<div style="margin-bottom:3px;">
		<table>
		 <!-- 2factors_code -->
		<tr style="margin-bottom:3px">
			<td>
				<i class="fa fa-sm fa-wrench"></i>&nbsp;
				<label>'.$LANG['admin_2factors_authentication_setting'].'
				&nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['admin_2factors_authentication_setting_tip'].'" />
				</label>
	        </td>
	        <td>
	            <div class="div_radio">
	                <input type="radio" id="2factors_authentication_radio1" name="2factors_authentication" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', (isset($_SESSION['settings']['2factors_authentication']) && $_SESSION['settings']['2factors_authentication'] == 1) ? ' checked="checked"' : '', ' /><label for="2factors_authentication_radio1">'.$LANG['yes'].'</label>
	                <input type="radio" id="2factors_authentication_radio2" name="2factors_authentication" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['2factors_authentication']) && $_SESSION['settings']['2factors_authentication'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['2factors_authentication']) ? ' checked="checked"':''), ' /><label for="2factors_authentication_radio2">'.$LANG['no'].'</label>
	            </div>
			<td>
		</tr>
	<!-- // Google Authenticator website name -->
		<tr style="margin-bottom:3px">
			<td>
				<i class="fa fa-sm fa-wrench"></i>&nbsp;
				<label for="ga_website_name">'.$LANG['admin_ga_website_name'].'</label>
				&nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['admin_ga_website_name_tip'].'" />
				</td>
				<td>
				<input type="text" size="30" id="ga_website_name" name="ga_website_name" value="', isset($_SESSION['settings']['ga_website_name']) ? $_SESSION['settings']['ga_website_name'] : 'TeamPass for ChangeMe', '" class="text ui-widget-content" />
			<td>
		</tr>
		</table>
		<div style="margin:5px 0 5px 20px;">
			<input type="button" onclick="SaveFA()" value="'.$LANG['save_button'].'" class="ui-state-default ui-corner-all" style="cursor:pointer; padding:4px; width:75px;" />
			&nbsp;<span id="savefa_wait" style="display: none;"><i class="fa fa-cog fa-spin"></i></span>
		</div>
	</div>
	<hr style="margin:10px 0 10px 0;">
	<div style="margin-bottom:3px;">
		<i class="fa fa-sm fa-wrench"></i>&nbsp;
        <label for="api" style="width:350px;">' .
        $LANG['settings_duo'].'
            &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_duo_tip'].'" />
        </label>
        <span class="div_radio">
            <input type="radio" id="api_radio1" name="ldap_mode" onclick="saveDuoStatus(1)" value="1"', isset($_SESSION['settings']['duo']) && $_SESSION['settings']['duo'] == 1 ? ' checked="checked"' : '', ' /><label for="api_radio1">'.$LANG['yes'].'</label>
            <input type="radio" id="api_radio2" name="ldap_mode" onclick="saveDuoStatus(0)" value="0"', isset($_SESSION['settings']['duo']) && $_SESSION['settings']['duo'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['duo']) ? ' checked="checked"':''), ' /><label for="api_radio2">'.$LANG['no'].'</label>
        </span>
        &nbsp;<span id="save_status_wait" style="display: none;"><i class="fa fa-cog fa-spin"></i></span>
    </div>
    <div id="duo_enabled" style="', isset($_SESSION['settings']['duo']) && $_SESSION['settings']['duo'] == 1 ? '' : 'display:none;', '">
    <div style="margin-bottom:3px;">
    	<h3>'.$LANG['admin_duo_intro'].'</h3>
    </div>
    <div style="margin-bottom:3px;">
    	<table border="0">';
// AKEY
echo '
           <tr style="margin-bottom:3px">
				<td width="100px">
				<span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
				<label for="duo_akey">'.$LANG['admin_duo_akey'].'</label>
				</td>
				<td>
				<input type="text" size="60" id="duo_akey" name="duo_akey" value="'.$tmp_akey.'" class="text ui-widget-content" />
				&nbsp;<input type="button" onclick="GenerateCryptKey(40)" value="'.$LANG['generate_random_key'].'" class="ui-state-default ui-corner-all" style="cursor:pointer;" />&nbsp;<span id="generate_wait" style="display: none;"><i class="fa fa-cog fa-spin"></i></span>
				<td>
			</tr>';
// IKEY
echo '
           <tr style="margin-bottom:3px">
				<td>
				<span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
				<label for="duo_ikey">'.$LANG['admin_duo_ikey'].'</label>
				</td>
				<td>
				<input type="text" size="60" id="duo_ikey" name="duo_ikey" value="'.$tmp_ikey.'" class="text ui-widget-content" />
				<td>
			</tr>';
// SKEY
echo '
           <tr style="margin-bottom:3px">
				<td>
				<span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
				<label for="duo_skey">'.$LANG['admin_duo_skey'].'</label>
				</td>
				<td>
				<input type="text" size="60" id="duo_skey" name="duo_skey" value="'.$tmp_skey.'" class="text ui-widget-content" />
				<td>
			</tr>';
// HOST
echo '
           <tr style="margin-bottom:3px">
				<td>
				<span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
				<label for="duo_host">'.$LANG['admin_duo_host'].'</label>
				</td>
				<td>
				<input type="text" size="60" id="duo_host" name="duo_host" value="'.$tmp_host.'" class="text ui-widget-content" />
				<td>
			</tr>';

echo '
		</table>
	<div style="margin:15px 0 0 0;">' .
        $LANG['settings_duo_explanation'].'
    </div>
	<div style="margin-bottom:20px; margin-top:20px;">
		<input type="button" onclick="SaveKeys()" value="'.$LANG['duo_save_sk_file'].'" class="ui-state-default ui-corner-all" style="cursor:pointer;" />
		&nbsp;<span id="save_wait" style="display: none;"><i class="fa fa-cog fa-spin"></i></span>
	</div>
	</div>
</div>';

echo '
<script type="text/javascript">
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

	var data = "{\"2factors_authentication\":\""+$("input[name=\"2factors_authentication\"]").prop("checked")+"\", \"ga_website_name\":\""+sanitizeString($("#ga_website_name").val())+"\"}";
	$.post(
		"sources/admin.queries.php",
		{
			type : "save_fa_options",
			data : prepareExchangedData(data, "encode", "'.$_SESSION['key'].'"),
            key  : "'.$_SESSION['key'].'"
		},
		function(data) {
            if (data[0].error == "") {
				$("#main_info_box_text").html(data[0].result);
				console.log($("input[name=\"2factors_authentication\"]").prop("checked"));
				if ($("input[name=\"2factors_authentication\"]").prop("checked") == "true") {
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

</script>
';