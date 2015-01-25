<?php
/**
 *
 * @file          index.php
 * @author        Nils Laumaillé
 * @version       2.1.23
 * @copyright     (c) 2009-2015 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('./sources/sessions.php');
session_start();
if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 ||
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) ||
    !isset($_SESSION['key']) || empty($_SESSION['key']))
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/includes/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "home")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
header("Content-type: text/html; charset=utf-8");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");

// reload user avatar
$userData = DB::queryFirstRow("SELECT avatar, avatar_thumb FROM ".prefix_table("users")." WHERE id=%i", $_SESSION['user_id']);
@$_SESSION['user']['avatar'] = $userData['avatar'];
@$_SESSION['user']['avatar_thumb'] = $userData['avatar_thumb'];

echo '
<table>
    <tr>
        <td rowspan="4" style="width:94px">
            <div id="profile_photo" class="ui-widget ui-state-highlight" style="padding:2px;"><img src="', isset($userData['avatar']) && !empty($userData['avatar']) ? 'includes/avatars/'.$userData['avatar'] : './includes/images/photo.jpg', '" /></div>
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
        <td title="'.$LANG['click_to_change'].'" class="tip"><span style="" class="editable_textarea" id="email_'.$_SESSION['user_id'].'">'.$_SESSION['user_email'].'</span></td>
    </tr>
    <tr>
        <td style="width:70px;">&nbsp;'.$LANG['role'].':</td>
        <td>'.$_SESSION['user_privilege'].'</td>
    </tr>
</table>

<div style="float:left;width:95%;margin:10px;">
    <i class="fa fa-child fa-fw"></i>&nbsp;
    '.$LANG['index_last_seen'].' ', isset($_SESSION['settings']['date_format']) ? date($_SESSION['settings']['date_format'], $_SESSION['derniere_connexion']) : date("d/m/Y", $_SESSION['derniere_connexion']), ' '.$LANG['at'].' ', isset($_SESSION['settings']['time_format']) ? date($_SESSION['settings']['time_format'], $_SESSION['derniere_connexion']) : date("H:i:s", $_SESSION['derniere_connexion']), '
    <br />';
if (isset($_SESSION['last_pw_change']) && !empty($_SESSION['last_pw_change'])) {
    echo '
    <i class="fa fa-calendar fa-fw"></i>&nbsp;'. $LANG['index_last_pw_change'].' ', isset($_SESSION['settings']['date_format']) ? date($_SESSION['settings']['date_format'], $_SESSION['last_pw_change']) : (isset($_SESSION['last_pw_change']) ? date("d/m/Y", $_SESSION['last_pw_change']) : "-"). '. ', $_SESSION['numDaysBeforePwExpiration'] == "infinite" ? '' : $LANG['index_pw_expiration'].' '.$_SESSION['numDaysBeforePwExpiration'].' '.$LANG['days'];
}
echo '
    <br />
    <i class="fa fa-cloud-upload fa-fw"></i>&nbsp;
    <span id="plupload_runtime2" class="ui-state-error ui-corner-all" style="width:350px;">Upload feature: No runtime found.</span>
    <input type="hidden" id="upload_enabled2" value="" />
</div>
<hr>
<div style="float:left;width:95%;margin:10px;">
    <span class="button" id="pickfiles_photo">'.$LANG['upload_new_avatar'].'</span>
    <span class="button" id="change_password">'.$LANG['index_change_pw'].'</span>
    <div style="text-align:center;margin:5px;padding:3px;display:none;" id="profile_info_box" class="ui-widget ui-state-highlight ui-corner-all"></div>
    <div style="height:20px;text-align:center;margin:2px;" id="change_pwd_error" class=""></div>
    <div id="upload_container_photo" style="display:none;"></div>
    <div id="filelist_photo" style="display:none;"></div>

    <div id="div_change_password" style="display:none;">
        <div style="text-align:center;margin:5px;padding:3px;" id="change_pwd_complexPw" class="ui-widget ui-state-active ui-corner-all"></div>
        <label for="new_pw" class="form_label">'.$LANG['index_new_pw'].' :</label>
        <input type="password" size="15" name="new_pw" id="new_pw" />
        <br />
        <label for="new_pw2" class="form_label">'.$LANG['index_change_pw_confirmation'].' :</label>
        <input type="password" size="15" name="new_pw2" id="new_pw2" />
        <div id="pw_strength" style="margin:10px 0 10px 120px;text-align:center;"></div>
        <input type="hidden" id="pw_strength_value" />
        <span class="button" id="button_change_pw">'.$LANG['index_change_pw_button'].'</span>&nbsp;
        <i class="fa fa-cog fa-spin" id="password_change_wait" style="display:none;"></i>
    </div>
</div>';
?>
<script type="text/javascript">
$(function() {
    $(".tip").tooltipster();
    // password
    $("#change_password").click(function() {
        $("#change_pwd_complexPw").html("<?php echo $LANG['complex_asked'];?> : <?php echo $_SESSION['settings']['pwComplexity'][$_SESSION['user_pw_complexity']][1];?>");
        $("#change_pwd_error").hide();
        $("#div_change_password").toggle();
        if ($("#div_change_password").is(":visible")) {
            $("#dialog_user_profil").dialog("option", "height", 470);
            $("#new_pw").focus();
        } else {
            $("#dialog_user_profil").dialog("option", "height", 400);
        }
    });

    //Password meter
    if ($("#new_pw").length) {
        $("#new_pw").simplePassMeter({
            "requirements": {},
            "container": "#pw_strength",
            "defaultText" : "<?php echo $LANG['index_pw_level_txt'];?>",
            "ratings": [
                {"minScore": 0,
                    "className": "meterFail",
                    "text": "<?php echo $LANG['complex_level0'];?>"
                },
                {"minScore": 25,
                    "className": "meterWarn",
                    "text": "<?php echo $LANG['complex_level1'];?>"
                },
                {"minScore": 50,
                    "className": "meterWarn",
                    "text": "<?php echo $LANG['complex_level2'];?>"
                },
                {"minScore": 60,
                    "className": "meterGood",
                    "text": "<?php echo $LANG['complex_level3'];?>"
                },
                {"minScore": 70,
                    "className": "meterGood",
                    "text": "<?php echo $LANG['complex_level4'];?>"
                },
                {"minScore": 80,
                    "className": "meterExcel",
                    "text": "<?php echo $LANG['complex_level5'];?>"
                },
                {"minScore": 90,
                    "className": "meterExcel",
                    "text": "<?php echo $LANG['complex_level6'];?>"
                }
            ]
        });
    }
    $("#new_pw").bind({
        "score.simplePassMeter" : function(jQEvent, score) {
            $("#pw_strength_value").val(score);
        }
    });

    // launche password change
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
                        data                : prepareExchangedData(data, "encode", "<?php echo $_SESSION['key'];?>")
                    },
                    function(data) {
                        if (data[0].error == "already_used") {
                            $("#new_pw, #new_pw2").val("");
                            $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<span><?php echo $LANG['pw_used'];?></span>");
                        } else if (data[0].error == "complexity_level_not_reached") {
                            $("#new_pw, #new_pw2").val("");
                            $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<span><?php echo $LANG['error_complex_not_enought'];?></span>");
                        } else {
                            $("#div_change_password").hide();
                            $("#dialog_user_profil").dialog("option", "height", 400);
                            $("#new_pw, #new_pw2").val("");
                        }
                        $("#password_change_wait").hide();
                        $("#profile_info_box").html("<?php echo $LANG['alert_message_done'];?>").show();
                        setTimeout(function(){$("#profile_info_box").effect( "fade", "slow" );}, 1000);
                    },
                    "json"
                );
            } else {
                $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<?php echo $LANG['error_complex_not_enought'];?>");
                setTimeout(function(){$("#change_pwd_error").effect( "fade", "slow" );}, 1000);
            }
        } else {
            $("#change_pwd_error").addClass("ui-state-error ui-corner-all").show().html("<?php echo $LANG['index_pw_error_identical'];?>");
            setTimeout(function(){$("#change_pwd_error").effect( "fade", "slow" );}, 1000);
        }
    });

    // AVATAR IMPORT
    var uploader_photo = new plupload.Uploader({
        runtimes : "gears,html5,flash,silverlight,browserplus",
        browse_button : "pickfiles_photo",
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
                up.start();
            },
            BeforeUpload: function (up, file) {
                var tmp = Math.random().toString(36).substring(7);

                up.settings.multipart_params = {
                    "PHPSESSID":"<?php echo $_SESSION['user_id'];?>",
                    "fileName":file.name,
                    "type_upload":"upload_profile_photo",
                    "newFileName":"user<?php echo $_SESSION['user_id'];?>"+tmp
                };
            }
        }
    });

    // Show runtime status
    uploader_photo.bind("Init", function(up, params) {
        $("#plupload_runtime2").html("Upload feature: runtime " + params.runtime).removeClass('ui-state-error');
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
        var myData;
        try {
            myData = eval(object.response);
        } catch(err) {
            myData = eval('(' + object.response + ')');
        }
        $("#profile_photo").html('<img src="includes/avatars/'+myData.filename+'" />');
        $("#filelist_photo").html('').hide();
    });

    // Load CSV click
    $("#uploadfiles_photo").click(function(e) {
        uploader_photo.start();
        e.preventDefault();
    });
    uploader_photo.init();
    
    //inline editing
    $(".editable_textarea").editable("sources/users.queries.php", {
          indicator : "<img src=\'includes/images/loading.gif\' />",
          type   : "text",
          select : true,
          submit : "<img src=\'includes/images/disk_black.png\' />",
          cancel : "<img src=\'includes/images/cross.png\' />",
          name : "newValue"
    });

    $( ".button" ).button();
});
 </script>