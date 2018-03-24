<?php
/**
 *
 * @file          index.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2018 Nils Laumaillé
 * @licensing     GNU GPL-3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once './sources/SecureHandler.php';
session_start();
if (isset($_SESSION['CPM']) === false || $_SESSION['CPM'] != 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id']) === true
    || isset($_SESSION['key']) === false || empty($_SESSION['key']) === true
) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], "home") === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

require $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
require $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
header("Content-type: text/html; charset=utf-8");
header("Cache-Control: no-cache, no-store, must-revalidate");

// reload user avatar
$userData = DB::queryFirstRow(
    "SELECT avatar, avatar_thumb
    FROM ".prefix_table("users")."
    WHERE id=%i",
    $_SESSION['user_id']
);
$_SESSION['user_avatar'] = $userData['avatar'];
$_SESSION['user_avatar_thumb'] = $userData['avatar_thumb'];

// prepare avatar
if (isset($userData['avatar']) && !empty($userData['avatar'])) {
    if (file_exists('includes/avatars/'.$userData['avatar'])) {
        $avatar = $SETTINGS['cpassman_url'].'/includes/avatars/'.$userData['avatar'];
    } else {
        $avatar = $SETTINGS['cpassman_url'].'/includes/images/photo.jpg';
    }
} else {
    $avatar = $SETTINGS['cpassman_url'].'/includes/images/photo.jpg';
}

// user type
if (isset($LANG) === true) {
    if ($_SESSION['user_admin'] === '1') {
        $_SESSION['user_privilege'] = $LANG['god'];
    } elseif ($_SESSION['user_manager'] === '1') {
        $_SESSION['user_privilege'] = $LANG['gestionnaire'];
    } elseif ($_SESSION['user_read_only'] === '1') {
        $_SESSION['user_privilege'] = $LANG['read_only_account'];
    } elseif ($_SESSION['user_can_manage_all_users'] === '1') {
        $_SESSION['user_privilege'] = $LANG['human_resources'];
    } else {
        $_SESSION['user_privilege'] = $LANG['user'];
    }
}

// prepare list of timezones
foreach (timezone_identifiers_list() as $zone) {
    $arrayTimezones[$zone] = $zone;
}

// prepare lsit of flags
$rows = DB::query("SELECT label FROM ".prefix_table("languages")." ORDER BY label ASC");
foreach ($rows as $record) {
    $arraFlags[$record['label']] = $record['label'];
}

header("access-control-allow-origin: *");
echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
    <head>
        <title>User Profile</title>
    </head>
<body>';

echo '
<input type="hidden" id="profile_user_token" value="" />';

// Get info about personal_saltkey_security_level
if (isset($SETTINGS['personal_saltkey_security_level']) === true && empty($SETTINGS['personal_saltkey_security_level']) === false) {
    echo '
<input type="hidden" id="input_personal_saltkey_security_level" value="'.$SETTINGS['personal_saltkey_security_level'].'" />';
} else {
    echo '
<input type="hidden" id="input_personal_saltkey_security_level" value="" />';
}

echo '
<table style="margin-left:7px;">
    <tr>
        <td rowspan="4" style="width:94px">
            <div id="profile_photo" class="ui-widget ui-state-highlight tip" style="padding:2px; text-align:center; cursor:pointer;" title="'.$LANG['upload_new_avatar'].'"><img src="'.$avatar.'" /></div>
        </td>
        <td style="width:70px;">&nbsp;'.$LANG['name'].':</td>
        <td><b>', isset($_SESSION['name']) && !empty($_SESSION['name']) ? $_SESSION['name'].' '.$_SESSION['lastname'] : $_SESSION['login'], '</b></td>
    </tr>
    <tr>
        <td style="width:70px;">&nbsp;'.$LANG['user_login'].':</td>
        <td><span style="">'.$_SESSION['login'].'</span></td>
    </tr>
    <tr>
        <td style="width:70px;">&nbsp;'.$LANG['email'].':</td>
        <td title="'.$LANG['click_to_change'].'"><span style="cursor:pointer;" class="editable_textarea" id="email_'.$_SESSION['user_id'].'">'.$_SESSION['user_email'].'</span>&nbsp;<i class="fa fa-pencil fa-fw jeditable-activate" style="cursor:pointer;"></i></td>
    </tr>
    <tr>
        <td style="width:70px;">&nbsp;'.$LANG['role'].':</td>
        <td>'.$_SESSION['user_privilege'].'</td>
    </tr>
</table>

<div style="float:left; margin-left:10px;">
   <ul class="menu" style="">
      <li class="menu_150" style="padding:4px; text-align:left;"><i class="fa fa-bars fa-fw"></i>&nbsp;'.$LANG['admin_actions_title'].'
         <ul class="menu_250" style="text-align:left;">';
if (!isset($SETTINGS['duo']) || $SETTINGS['duo'] == 0) {
    echo '
            <li id="but_change_password"><i class="fa fa-key fa-fw"></i> &nbsp;'.$LANG['index_change_pw'].'</li>';
}
echo '
            <li id="but_change_psk"><i class="fa fa-lock fa-fw"></i> &nbsp;'.$LANG['menu_title_new_personal_saltkey'].'</li>
            <li id="but_reset_psk"><i class="fa fa-eraser fa-fw"></i> &nbsp;'.$LANG['personal_saltkey_lost'].'</li>
         </ul>
      </li>
   </ul>
</div>

<div style="float:left;width:95%;margin:10px 0 5px 10px;">
    <hr>
    <div style="margin-bottom:6px;">
        <i class="fa fa-child fa-fw fa-lg"></i>&nbsp;
        '.$LANG['index_last_seen'].' ', isset($SETTINGS['date_format']) ? date($SETTINGS['date_format'], $_SESSION['derniere_connexion']) : date("d/m/Y", $_SESSION['derniere_connexion']), ' '.$LANG['at'].' ', isset($SETTINGS['time_format']) ? date($SETTINGS['time_format'], $_SESSION['derniere_connexion']) : date("H:i:s", $_SESSION['derniere_connexion']), '
    </div>';
if (isset($_SESSION['last_pw_change']) && !empty($_SESSION['last_pw_change'])) {
    // Handle last password change string
    if (isset($_SESSION['last_pw_change']) === true) {
        if (isset($SETTINGS['date_format']) === true) {
            $last_pw_change = date($SETTINGS['date_format'], $_SESSION['last_pw_change']);
        } else {
            $last_pw_change = date("d/m/Y", $_SESSION['last_pw_change']);
        }
    } else {
        $last_pw_change = "-";
    }

    // Handle expiration for pw
    if (isset($_SESSION['numDaysBeforePwExpiration']) === false ||
        $_SESSION['numDaysBeforePwExpiration'] === '' ||
        $_SESSION['numDaysBeforePwExpiration'] === 'infinite'
    ) {
        $numDaysBeforePwExpiration = '';
    } else {
        $numDaysBeforePwExpiration = $LANG['index_pw_expiration'].' '.$_SESSION['numDaysBeforePwExpiration'].' '.$LANG['days'].'.';
    }
    echo '
    <div style="margin-bottom:6px;">
        <i class="fa fa-calendar fa-fw fa-lg"></i>&nbsp;&nbsp;'.$LANG['index_last_pw_change'].' '.$last_pw_change.'. '.$numDaysBeforePwExpiration.'
    </div>';
}
echo '
    <div style="margin-bottom:6px;margin-top:6px;">
        <i class="fa fa-cloud-upload fa-fw fa-lg"></i>&nbsp;
        <span id="plupload_runtime2" class="ui-state-error ui-corner-all" style="width:350px;">'.$LANG['error_upload_runtime_not_found'].'</span>
        <input type="hidden" id="upload_enabled2" value="" />
    </div>
    <hr>
    <div style="margin-bottom:6px;">
        <i class="fa fa-code-fork fa-fw fa-lg"></i>&nbsp;'. $LANG['tree_load_strategy'].':&nbsp;<span style="cursor:pointer; font-weight:bold;" class="editable_select" id="treeloadstrategy_'.$_SESSION['user_id'].'" title="'.$LANG['click_to_change'].'">'.$_SESSION['user_settings']['treeloadstrategy'].'</span>&nbsp;<i class="fa fa-pencil fa-fw jeditable-activate" style="cursor:pointer;"></i>
    </div>';

if ((isset($_SESSION['user_settings']['usertimezone']) === true && $_SESSION['user_settings']['usertimezone'] !== "not_defined") || isset($SETTINGS['timezone']) === true) {
    echo '
    <div style="margin-bottom:6px;">
        <i class="fa fa-clock-o fa-fw fa-lg"></i>&nbsp;'. $LANG['timezone_selection'].':&nbsp;<span style="cursor:pointer; font-weight:bold;" class="editable_timezone" id="usertimezone_'.$_SESSION['user_id'].'" title="'.$LANG['click_to_change'].'">', (isset($_SESSION['user_settings']['usertimezone']) && $_SESSION['user_settings']['usertimezone'] !== "not_defined") ? $_SESSION['user_settings']['usertimezone'] : $SETTINGS['timezone'], '</span>&nbsp;<i class="fa fa-pencil fa-fw jeditable-activate" style="cursor:pointer;"></i>
    </div>';
}

echo '
    <div style="margin-bottom:6px;">
        <i class="fa fa-language fa-fw fa-lg"></i>&nbsp;'. $LANG['user_language'].':&nbsp;<span style="cursor:pointer; font-weight:bold;" class="editable_language" id="userlanguage_'.$_SESSION['user_id'].'" title="'.$LANG['click_to_change'].'">', isset($_SESSION['user_language']) ? $_SESSION['user_language'] : $SETTINGS['default_language'], '</span>&nbsp;<i class="fa fa-pencil fa-fw jeditable-activate" style="cursor:pointer;"></i>
    </div>';


if (isset($SETTINGS['api']) && $SETTINGS['api'] === '1') {
    echo '
    <div style="margin-bottom:6px;">
        <i class="fa fa-paper-plane fa-lg"></i>&nbsp;&nbsp;'. $LANG['user_profile_api_key'].':&nbsp;<span style="font-weight:bold;" id="user_api_key" title="">', isset($_SESSION['user_settings']['api-key']) === true ? $_SESSION['user_settings']['api-key'] : '', '</span>&nbsp;<i class="fa fa-refresh fa-fw" style="cursor:pointer;" id="but_new_api"></i>
    </div>';
}

if (isset($SETTINGS['agses_authentication_enabled']) && $SETTINGS['agses_authentication_enabled'] == 1) {
    echo '
    <hr>

    <div style="margin-bottom:6px;">
        <i class="fa fa-id-card-o fa-lg"></i>&nbsp;'. $LANG['user_profile_agses_card_id'].':&nbsp;<span style="cursor:pointer; font-weight:bold;" class="editable_textarea" id="agses-usercardid_'.$_SESSION['user_id'].'" title="'.$LANG['click_to_change'].'">', isset($_SESSION['user_settings']['agses-usercardid']) ? $_SESSION['user_settings']['agses-usercardid'] : '', '</span>&nbsp;<i class="fa fa-pencil fa-fw jeditable-activate" style="cursor:pointer;"></i>
    </div>';
}

echo '
</div>

<hr>

<div style="display:none;margin:3px 0 10px 0;text-align:center;padding:4px;" id="field_warning" class="ui-widget-content ui-state-error ui-corner-all"></div>

<div style="float:left;width:100%;margin-top:3px;">
    <div style="text-align:center;margin:5px;padding:3px;display:none;" id="profile_info_box" class="ui-widget ui-state-highlight ui-corner-all"></div>
    <div style="height:20px;text-align:center;margin:2px;" id="change_pwd_error" class=""></div>
    <div id="upload_container_photo" style="display:none;"></div>
    <div id="filelist_photo" style="display:none;"></div>';

// if DUOSecurity enabled then changing PWD is not allowed
if (isset($SETTINGS['duo']) === false || $SETTINGS['duo'] == 0) {
    echo '
    <div id="div_change_password" style="display:none; padding:5px;" class="ui-widget ui-state-default">
        <div style="text-align:center;margin:5px;padding:3px;" id="change_pwd_complexPw" class="ui-widget ui-state-active ui-corner-all"></div>
        <label for="new_pw" class="form_label">'.$LANG['index_new_pw'].' :</label>
        <input type="password" size="15" name="new_pw" id="new_pw" />
        <br />
        <label for="new_pw2" class="form_label">'.$LANG['index_change_pw_confirmation'].' :</label>
        <input type="password" size="15" name="new_pw2" id="new_pw2" />

        <div id="pw_strength" style="margin:10px 0 10px 120px;text-align:center;"></div>
        <input type="hidden" id="pw_strength_value" />

        <span class="button" id="button_change_pw">'.$LANG['index_change_pw_button'].'</span>&nbsp;
        <span id="password_change_wait" style="display:none;"><i class="fa fa-cog fa-spin"></i>&nbsp;'.$LANG['please_wait'].'</span>
    </div>';
}

//change the saltkey dialogbox
echo '
    <div id="div_change_psk" style="display:none;padding:5px;" class="ui-widget ui-state-default">
        <div style="text-align:center;margin:5px;padding:3px;" id="change_psk_complexPw" class="ui-widget ui-state-active ui-corner-all hidden"></div>
        <div style="margin-bottom:4px; padding:6px;" class="ui-state-highlight">
            <i class="fa fa-exclamation-triangle fa-fw mi-red"></i>&nbsp;'.$LANG['new_saltkey_warning'].'
        </div>
        <table border="0">
            <tr>
                <td>
                    <label for="new_personal_saltkey" class="form_label">'.$LANG['new_saltkey'].' :</label>
                </td>
                <td>
                    <input type="password" size="30" id="new_personal_saltkey" class="text_without_symbols tip" title="'.$LANG['text_without_symbols'].'" />
                </td>
            </tr>
            <tr>
                <td>
                    <label for="new_personal_saltkey_confirm" class="form_label">'.$LANG['confirm'].' :</label>
                </td>
                <td>
                    <input type="password" size="30" id="new_personal_saltkey_confirm" value="" class="text_without_symbols" />
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div id="new_psk_strength" style="margin:3px 0 3px"></div>
                    <input type="hidden" id="new_psk_strength_value" />
                </td>
            </tr>
            <tr>
                <td>
                    <label for="old_personal_saltkey" class="form_label" style="margin-top:5px;">'.$LANG['old_saltkey'].' :</label>
                </td>
                <td>
                    <input type="text" size="30" name="old_personal_saltkey" id="old_personal_saltkey" value="" class="text_without_symbols" />
                </td>
            </tr>
        </table>
        <div style="margin-top:4px;">
            <span class="button" id="button_change_psk">'.$LANG['index_change_pw_button'].'</span>&nbsp;
            <span id="psk_change_wait" style="display:none;"><i class="fa fa-cog fa-spin"></i>&nbsp;<span id="psk_change_wait_info">'.$LANG['please_wait'].'</span></span>
        </div>
   </div>';


//saltkey LOST dialogbox
echo '
    <div id="div_reset_psk" style="display:none;padding:5px;" class="ui-widget ui-state-default">
        <div style="margin-bottom:4px; padding:6px;" class="ui-state-highlight">
            <i class="fa fa-exclamation-triangle fa-fw mi-red"></i>&nbsp;'.$LANG['new_saltkey_warning_lost'].'
        </div>

        <div style="margin-top:4px;">
            <input type="checkbox" id="reset_psk_confirm" />&nbsp;<label for="reset_psk_confirm">'.$LANG['please_confirm_operation'].'</label>
        </div>

        <div style="margin-top:4px;">
            <span class="button" id="button_reset_psk">'.$LANG['continue'].'</span>&nbsp;
            <span id="psk_reset_wait" style="display:none;"><i class="fa fa-cog fa-spin"></i>&nbsp;<span id="psk_reset_wait_info">'.$LANG['please_wait'].'</span></span>
        </div>
   </div>';
echo '
</div>';

// Pw complexity levels
if (isset($_SESSION['user_language']) && $_SESSION['user_language'] !== "0") {
    require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
    $SETTINGS_EXT['pwComplexity'] = array(
        0=>array(0, $LANG['complex_level0']),
        25=>array(25, $LANG['complex_level1']),
        50=>array(50, $LANG['complex_level2']),
        60=>array(60, $LANG['complex_level3']),
        70=>array(70, $LANG['complex_level4']),
        80=>array(80, $LANG['complex_level5']),
        90=>array(90, $LANG['complex_level6'])
    );
}
?>
<script type="text/javascript" src="includes/js/functions.js"></script>
<script type="text/javascript">
$(function() {
    $(".tip").tooltipster({multiple: true});
    // password
    $("#but_change_password").click(function() {
        $("#change_pwd_complexPw").html("<?php echo $LANG['complex_asked']; ?> : <?php echo $SETTINGS_EXT['pwComplexity'][$_SESSION['user_pw_complexity']][1]; ?>");
        $("#change_pwd_error").hide();
      $("#div_change_psk, #div_reset_psk").hide();

      if ($("#div_change_password").not(":visible")) {
         $("#div_change_password").show();
         $("#dialog_user_profil").dialog("option", "height", 580);
      }
    });

    //Password meter
    $("#new_pw").simplePassMeter({
        "requirements": {},
        "container": "#pw_strength",
        "defaultText" : "<?php echo $LANG['index_pw_level_txt']; ?>",
        "ratings": [
            {"minScore": 0,
                "className": "meterFail",
                "text": "<?php echo $LANG['complex_level0']; ?>"
            },
            {"minScore": 25,
                "className": "meterWarn",
                "text": "<?php echo $LANG['complex_level1']; ?>"
            },
            {"minScore": 50,
                "className": "meterWarn",
                "text": "<?php echo $LANG['complex_level2']; ?>"
            },
            {"minScore": 60,
                "className": "meterGood",
                "text": "<?php echo $LANG['complex_level3']; ?>"
            },
            {"minScore": 70,
                "className": "meterGood",
                "text": "<?php echo $LANG['complex_level4']; ?>"
            },
            {"minScore": 80,
                "className": "meterExcel",
                "text": "<?php echo $LANG['complex_level5']; ?>"
            },
            {"minScore": 90,
                "className": "meterExcel",
                "text": "<?php echo $LANG['complex_level6']; ?>"
            }
        ]
    });
    $("#new_pw").bind({
        "score.simplePassMeter": function(jQEvent, score) {
            $("#pw_strength_value").val(score);
        }
    });

    // For Personal Saltkey
    $("#new_personal_saltkey").simplePassMeter({
        "requirements": {},
        "container": "#new_psk_strength",
        "defaultText" : "<?php echo $LANG['index_pw_level_txt']; ?>",
        "ratings": [
            {"minScore": 0,
                "className": "meterFail",
                "text": "<?php echo $LANG['complex_level0']; ?>"
            },
            {"minScore": 25,
                "className": "meterWarn",
                "text": "<?php echo $LANG['complex_level1']; ?>"
            },
            {"minScore": 50,
                "className": "meterWarn",
                "text": "<?php echo $LANG['complex_level2']; ?>"
            },
            {"minScore": 60,
                "className": "meterGood",
                "text": "<?php echo $LANG['complex_level3']; ?>"
            },
            {"minScore": 70,
                "className": "meterGood",
                "text": "<?php echo $LANG['complex_level4']; ?>"
            },
            {"minScore": 80,
                "className": "meterExcel",
                "text": "<?php echo $LANG['complex_level5']; ?>"
            },
            {"minScore": 90,
                "className": "meterExcel",
                "text": "<?php echo $LANG['complex_level6']; ?>"
            }
        ]
    });
    $("#new_personal_saltkey").bind({
        "score.simplePassMeter": function(jQEvent, score) {
            $("#new_psk_strength_value").val(score);
        }
    });

    // launch password change
    $("#button_change_pw").click(function() {
        $("#change_pwd_error").addClass("ui-state-error ui-corner-all").hide();
        if ($("#new_pw").val() != "" && $("#new_pw").val() == $("#new_pw2").val()) {
            if (parseInt($("#pw_strength_value").val()) >= parseInt($("#user_pw_complexity").val())) {
                $("#password_change_wait").show();
                var data = '{"new_pw":"'+sanitizeString($("#new_pw").val())+'"}';
                $.post(
                    "sources/main.queries.php",
                    {
                        type                : "change_pw",
                        change_pw_origine   : "user_change",
                        complexity          : $("#pw_strength_value").val(),
                        data                : prepareExchangedData(data, "encode", "<?php echo $_SESSION['key']; ?>")
                    },
                    function(data) {
                        if (data[0].error == "already_used") {
                            $("#new_pw, #new_pw2").val("");
                            $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<span><?php echo $LANG['pw_used']; ?></span>");
                        } else if (data[0].error == "complexity_level_not_reached") {
                            $("#new_pw, #new_pw2").val("");
                            $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<span><?php echo $LANG['error_complex_not_enought']; ?></span>");
                        } else if (data[0].error == "pwd_hash_not_correct") {
                            $("#new_pw, #new_pw2").val("");
                            $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<span><?php echo $LANG['error_not_allowed_to']; ?></span>");
                        } else {
                            $("#div_change_password").hide();
                            $("#dialog_user_profil").dialog("option", "height", 450);
                            $("#new_pw, #new_pw2").val("");
                        }
                        $("#password_change_wait").hide();
                        $("#profile_info_box").html("<?php echo $LANG['alert_message_done']; ?>").show();

                        $(this).delay(2000).queue(function() {
                            $("#profile_info_box").effect( "fade", "slow" );
                            $(this).dequeue();
                        });
                    },
                    "json"
                );
            } else {
                $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<?php echo $LANG['error_complex_not_enought']; ?>");
                $(this).delay(1000).queue(function() {
                    $("#change_pwd_error").effect( "fade", "slow" );
                    $(this).dequeue();
                });
            }
        } else {
            $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<?php echo $LANG['index_pw_error_identical']; ?>");
            $(this).delay(1000).queue(function() {
                $("#change_pwd_error").effect( "fade", "slow" );
                $(this).dequeue();
            });
        }
    });

    // AVATAR IMPORT
    var uploader_photo = new plupload.Uploader({
        runtimes : "gears,html5,flash,silverlight,browserplus",
        browse_button : "profile_photo",
        container : "upload_container_photo",
        max_file_size : "2mb",
        chunk_size : "1mb",
        unique_names : true,
        dragdrop : true,
        multiple_queues : false,
        multi_selection : false,
        max_file_count : 1,
        filters : [
            {title : "PNG files", extensions : "png"}
        ],
        resize : {
            width : "90",
            height : "90",
            quality : "90"
        },
        url : "sources/upload/upload.files.php",
        flash_swf_url : "includes/libraries/Plupload/plupload.flash.swf",
        silverlight_xap_url : "includes/libraries/Plupload/plupload.silverlight.xap",
        init: {
            FilesAdded: function(up, files) {
                // generate and save token
                $.post(
                    "sources/main.queries.php",
                    {
                        type : "save_token",
                        size : 25,
                        capital: true,
                        numeric: true,
                        ambiguous: true,
                        reason: "avatar_profile_upload",
                        duration: 10
                    },
                    function(data) {
                        $("#profile_user_token").val(data[0].token);
                        up.start();
                    },
                    "json"
                );
            },
            BeforeUpload: function (up, file) {
                var tmp = Math.random().toString(36).substring(7);

                up.settings.multipart_params = {
                    "PHPSESSID":"<?php echo $_SESSION['user_id']; ?>",
                    "type_upload":"upload_profile_photo",
                    "user_token": $("#profile_user_token").val()
                };
            }
        }
    });

    // Show runtime status
    uploader_photo.bind("Init", function(up, params) {
        $("#plupload_runtime2").html("<?php echo $LANG['runtime_upload']; ?> " + params.runtime).removeClass('ui-state-error');
        $("#upload_enabled2").val("1");
    });

    // get error
    uploader_photo.bind("Error", function(up, err) {
        $("#filelist_photo").html("<div class='ui-state-error ui-corner-all'>Error: " + err.code +
            ", Message: " + err.message +
            (err.file ? ", File: " + err.file.name : "") +
            "</div>"
        );
        up.refresh(); // Reposition Flash/Silverlight
    });

     // get response
    uploader_photo.bind("FileUploaded", function(up, file, object) {
        // Decode returned data
        var myData = prepareExchangedData(object.response, "decode", "<?php echo $_SESSION['key']; ?>");

        // update form
        $("#profile_photo").html('<img src="includes/avatars/'+myData.filename+'" />');
        $("#user_avatar_thumb").attr('src', 'includes/avatars/'+myData.filename_thumb);
        $("#filelist_photo").html('').hide();
    });

    uploader_photo.init();

   $("#profile_photo").click(function() {
      $("#div_change_psk, #div_reset_psk, #div_change_password").hide();
      $("#dialog_user_profil").dialog("option", "height", 450);
   });

    //inline editing
    $(".editable_textarea").editable("sources/users.queries.php", {
        onsubmit: function(settings, value) {
            console.log(value);
        },
        indicator : "<img src=\'includes/images/loading.gif\' />",
        type   : "text",
        submit : "<i class=\'fa fa-check mi-green\'></i>&nbsp;",
        cancel : "<i class=\'fa fa-remove mi-red\'></i>&nbsp;",
        name   : "newValue",
        width  : 220
    });
    $(".editable_select").editable("sources/users.queries.php", {
        indicator : "<img src=\'includes/images/loading.gif\' />",
        data   : " {'full':'<?php echo $LANG['full']; ?>','sequential':'<?php echo $LANG['sequential']; ?>', 'selected':'<?php echo $_SESSION['user_settings']['treeloadstrategy']; ?>'}",
        type   : 'select',
        select : true,
        onblur : "cancel",
        submit : "<i class=\'fa fa-check mi-green\'></i>&nbsp;",
        cancel : "<i class=\'fa fa-remove mi-red\'></i>&nbsp;",
        name : "newValue"
    });
    $(".editable_language").editable("sources/users.queries.php", {
        indicator : "<img src=\'includes/images/loading.gif\' />",
        data   : '<?php print json_encode($arraFlags); ?>',
        type   : 'select',
        select : true,
        onblur : "cancel",
        submit : "<i class=\'fa fa-check mi-green\'></i>&nbsp;",
        cancel : "<i class=\'fa fa-remove mi-red\'></i>&nbsp;",
        name : "newValue"
    });
    $(".editable_timezone").editable("sources/users.queries.php", {
        indicator : "<img src=\'includes/images/loading.gif\' />",
        data : '<?php print json_encode($arrayTimezones); ?>',
        type   : 'select',
        select : true,
        onblur : "cancel",
        submit : "<i class=\'fa fa-check mi-green\'></i>&nbsp;",
        cancel : "<i class=\'fa fa-remove mi-red\'></i>&nbsp;",
        name : "newValue"
    });
    $(".editable_yesno").editable("sources/users.queries.php", {
        indicator : "<img src=\'includes/images/loading.gif\' />",
        data : '{"O":"<?php echo $LANG['no']; ?>","1":"<?php echo $LANG['yes']; ?>"}',
        type   : 'select',
        select : true,
        onblur : "cancel",
        submit : "<i class=\'fa fa-check mi-green\'></i>&nbsp;",
        cancel : "<i class=\'fa fa-remove mi-red\'></i>&nbsp;",
        name : "newValue"
    });

    $('.jeditable-activate').click(function() {
        $(this).prev().click();
    });


    // PSK
    $("#but_change_psk").click(function() {
      // hide other divs
      $("#div_change_password, #div_reset_psk").hide();

      // prepare fields
      $("#new_personal_saltkey").val("");
      $("#old_personal_saltkey").val("<?php echo addslashes(str_replace("&quot;", '"', @$_SESSION['user_settings']['clear_psk'])); ?>");

      // Get personal_saltkey_security_level
      if ($("#input_personal_saltkey_security_level").val() !== "") {
        $("#change_psk_complexPw")
            .html("<?php echo $LANG['complex_asked']; ?> : <?php echo $SETTINGS_EXT['pwComplexity'][$SETTINGS['personal_saltkey_security_level']][1]; ?>")
            .removeClass("hidden");
      } else {
        $("#change_psk_complexPw").addClass("hidden");
      }

      $("#div_change_psk").show();
      $("#dialog_user_profil").dialog("option", "height", 690);
    });

    // manage CHANGE OF PERSONAL SALTKEY
    $("#button_change_psk").click(function() {
        // Check if all fields are filled in
        if ($("#new_personal_saltkey").val() === "" || $("#new_personal_saltkey_confirm").val() === "" || $("#old_personal_saltkey").val() === "") {
            $("#psk_change_wait").hide();
            $("#div_change_psk").before('<div id="tmp_msg" class="ui-widget ui-state-error ui-corner-all" style="margin-bottom:3px; padding:3px;"><?php echo addslashes($LANG['home_personal_saltkey_label']); ?></div>');

            $(this).delay(1000).queue(function() {
                $("#tmp_msg").effect( "fade", "slow" );
                $("#tmp_msg").remove();
                $(this).dequeue();
            });
            return false;
        }

        // Check if psk are similar
        if ($("#new_personal_saltkey").val() !== $("#new_personal_saltkey_confirm").val()) {
            $("#psk_change_wait").hide();
            $("#div_change_psk").before('<div id="tmp_msg" class="ui-widget ui-state-error ui-corner-all" style="margin-bottom:3px; padding:3px;"><?php echo addslashes($LANG['bad_psk_confirmation']); ?></div>');

            $(this).delay(1000).queue(function() {
                $("#tmp_msg").effect( "fade", "slow" );
                $("#tmp_msg").remove();
                $(this).dequeue();
            });
            return false;
        }

        // Check if minimum security level is reched
        if ($("#input_personal_saltkey_security_level").val() !== "") {
            if (parseInt($("#new_psk_strength_value").val()) < parseInt($("#input_personal_saltkey_security_level").val())) {
                $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<?php echo $LANG['error_complex_not_enought']; ?>");
                $(this).delay(1000).queue(function() {
                    $("#change_pwd_error").effect( "fade", "slow" );
                    $(this).dequeue();
                });
                return false;
            }
        }

        // Show pspinner to user
        $("#psk_change_wait").show();

        var data_to_share = "{\"sk\":\"" + sanitizeString($("#new_personal_saltkey").val()) + "\", \"old_sk\":\"" + sanitizeString($("#old_personal_saltkey").val()) + "\"}";

        $("#psk_change_wait_info").html("... 0%");

        //Send query
        $.post(
            "sources/main.queries.php",
            {
                type            : "change_personal_saltkey",
                data_to_share   : prepareExchangedData(data_to_share, "encode", "<?php echo $_SESSION['key']; ?>"),
                key             : "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
                if (data.error === "no") {
                    changePersonalSaltKey(data_to_share, data.list, data.nb_total);
                } else {
                    $("#psk_change_wait").hide();
                    $("#div_change_psk").before('<div id="tmp_msg" class="ui-widget ui-state-error ui-corner-all" style="margin-bottom:3px; padding:3px;">' + data.error + '</div>');

                    $(this).delay(3000).queue(function() {
                        $("#tmp_msg").effect( "fade", "slow" );
                        $("#tmp_msg").remove();
                        $(this).dequeue();
                    });
                    return false;
                }
            }
        );
    });


    // RESET PSK
    $("#but_reset_psk").click(function() {
        // hide other divs
        $("#div_change_password, #div_change_psk").hide();

        // prepare fields
        $("#new_reset_psk").val("");

        $("#div_reset_psk").show();
        $("#dialog_user_profil").dialog("option", "height", 600);
    });
    $("#button_reset_psk").click(function() {
        if ($("#reset_psk_confirm").is(":checked")) {
            $("#psk_reset_wait").show();

            $.post(
                "sources/main.queries.php",
                {
                type    : "reset_personal_saltkey",
                key             : "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    $("#psk_reset_wait").hide();
                    $("#button_reset_psk").after('<div id="reset_temp"><?php echo $LANG['alert_message_done']; ?></div>');

                    $(this).delay(1500).queue(function() {
                        $("#div_reset_psk").effect( "fade", "slow" );
                        $("#reset_temp").remove();
                        $(this).dequeue();
                    });

                    $("#psk_change_wait_info").html("<?php echo $LANG['alert_message_done']; ?>");
                    location.reload();
                }
            );
        }
    });

    $( ".button" ).button();

   $(".menu").menu({
      icon: {},
      position: { my: "left top", at: "right top" }
   });

   // prevent usage of symbols in Personal saltkey
   $(".text_without_symbols").bind("keydown", function (event) {
      switch (event.keyCode) {
         case 8:  // Backspace
         case 9:  // Tab
         case 13: // Enter
         case 37: // Left
         case 38: // Up
         case 39: // Right
         case 40: // Down
         break;
         default:
         var regex = new RegExp("^[a-zA-Z0-9.,/#&$@()%*]+$");
         var key = event.key;
         if (!regex.test(key)) {
            $("#field_warning").html("<?php echo addslashes($LANG['character_not_allowed']); ?>").stop(true,true).show().fadeOut(1000);
            event.preventDefault();
            return false;
         }
         break;
      }
   }).bind("paste",function(e){
      $("#field_warning").html("<?php echo addslashes($LANG['error_not_allowed_to']); ?>").stop(true,true).show().fadeOut(1000);
      e.preventDefault();
   });

   // If user api is empty then generate one
   if ($("#user_api_key").text() === "none") {
     generateNewUserApiKey();
   }

   $("#but_new_api").click(function() {
     generateNewUserApiKey();
   });
});


function changePersonalSaltKey(credentials, ids, nb_total)
{
   // extract current id and adapt list
   var aIds = ids.split(",");
   var currentID = aIds[0];
   aIds.shift();
   var nb = aIds.length;
   aIds = aIds.toString();

   if (nb == 0)
      $("#psk_change_wait_info").html("&nbsp;...&nbsp;"+"100%");
   else
      $("#psk_change_wait_info").html("&nbsp;...&nbsp;"+Math.floor(((nb_total-nb) / nb_total) * 100)+"%");

    var data = "{\"psk\":\""+sanitizeString($("#new_personal_saltkey").val())+"\"}";
    $.post(
      "sources/main.queries.php",
        {
            type    : "store_personal_saltkey",
            data    : prepareExchangedData(data, "encode", "<?php echo $_SESSION['key']; ?>"),
            debug   : true,
            key     : "<?php echo $_SESSION['key']; ?>"
        },
        function(data){
            if (data[0].error !== "") {
                // display error
                $("#psk_change_wait_info").html(data[0].error);
                $(this).delay(4000).queue(function() {
                    $("#main_info_box").effect( "fade", "slow" );
                    $(this).dequeue();
                });
            } else {
                $.post(
                    "sources/utils.queries.php",
                    {
                        type            : "reencrypt_personal_pwd",
                        data_to_share   : prepareExchangedData(credentials, "encode", "<?php echo $_SESSION['key']; ?>"),
                        currentId       : currentID,
                        key             : "<?php echo $_SESSION['key']; ?>"
                    },
                    function(data){
                        if (currentID === "") {
                            $("#psk_change_wait_info").html("<?php echo $LANG['alert_message_done']; ?>");
                            location.reload();
                        } else {
                            if (data[0].error === "") {
                            changePersonalSaltKey(credentials, aIds, nb_total);
                            } else {
                                $("#psk_change_wait_info").html(data[0].error);
                            }
                        }
                    },
                    "json"
                );
            }
        },
        "json"
    );
}

/*
**
 */
function generateNewUserApiKey() {
    var newApiKey = "";

    // Generate key
    $.post(
        "sources/main.queries.php",
        {
            type        : "generate_a_password",
            size        : "39",
            lowercase   : "true",
            numerals    : "true",
            capitalize  : "true",
            symbols     : "false",
            secure      : "false"
        },
        function(data) {
            data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
            if (data.key !== "") {
                newApiKey = data.key;

                // Save key in session and database
                var data = "{\"field\":\"user_api_key\" ,\"new_value\":\""+newApiKey+"\" ,\"user_id\":\"<?php echo $_SESSION['user_id']; ?>\"}";

                $.post(
                  "sources/main.queries.php",
                    {
                        type    : "update_user_field",
                        data    : prepareExchangedData(data, "encode", "<?php echo $_SESSION['key']; ?>"),
                        key     : "<?php echo $_SESSION['key']; ?>"
                    },
                    function(data){
                        $("#user_api_key").text(newApiKey);
                    }
                );
            }
        }
    );
}
</script>
</body>
</html>
