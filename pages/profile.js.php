<?php
/**
 * Teampass - a collaborative passwords manager.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 *
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2018 Nils Laumaillé
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 *
 * @version   GIT: <git_id>
 *
 * @see      http://www.teampass.net
 */
if (isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id']) === true
    || isset($_SESSION['key']) === false || empty($_SESSION['key']) === true
) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php') === true) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php') === true) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception('Error file "/includes/config/tp.config.php" not exists', 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'profile', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}
?>


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
        data   : '<?php echo json_encode($arraFlags); ?>',
        type   : 'select',
        select : true,
        onblur : "cancel",
        submit : "<i class=\'fa fa-check mi-green\'></i>&nbsp;",
        cancel : "<i class=\'fa fa-remove mi-red\'></i>&nbsp;",
        name : "newValue"
    });
    $(".editable_timezone").editable("sources/users.queries.php", {
        indicator : "<img src=\'includes/images/loading.gif\' />",
        data : '<?php echo json_encode($arrayTimezones); ?>',
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
      $("#old_personal_saltkey").val("<?php echo addslashes(str_replace('&quot;', '"', @$_SESSION['user_settings']['clear_psk'])); ?>");

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