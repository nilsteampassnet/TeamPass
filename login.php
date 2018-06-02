<?php
/**
 * Teampass - a collaborative passwords manager
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 * @package   Login.php
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2018 Nils Laumaillé
* @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * @version   GIT: <git_id>
 * @link      http://www.teampass.net
 */

 
// Automatic redirection
$nextUrl = '';
if (strpos($server_request_uri, "?") > 0) {
    $nextUrl = filter_var(
        substr($server_request_uri, strpos($server_request_uri, "?")),
        FILTER_SANITIZE_URL
    );
}

echo '
<body class="hold-transition login-page">
<div class="login-box">
  <div class="login-logo">',
  (isset($SETTINGS['custom_logo']) === true
  && empty($SETTINGS['custom_logo']) === false) ?
  '
    <img src="'.(string) $SETTINGS['custom_logo'].'" alt="" style="margin-bottom:40px;" />' :
  '',
  '
    <a href="../../index2.html"><b>'.TP_TOOL_NAME.'</b></a>
  </div>
  <!-- /.login-logo -->
  <div class="card">
    <div class="card-body login-card-body">
      <p class="login-box-msg">'.langHdl('index_get_identified').'</p>

      <div>
        <div class="input-group has-feedback mb-2">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-user"></i></span>
            </div>';

if (isset($SETTINGS['enable_http_request_login']) === true
    && $SETTINGS['enable_http_request_login'] === '1'
    && isset($_SERVER['PHP_AUTH_USER']) === true
    && !(isset($SETTINGS['maintenance_mode']) === true
    && $SETTINGS['maintenance_mode'] === '1')
) {
    if (strpos($_SERVER['PHP_AUTH_USER'], '@') !== false) {
        $username = explode("@", $_SERVER['PHP_AUTH_USER'])[0];
    } elseif (strpos($_SERVER['PHP_AUTH_USER'], '\\') !== false) {
        $username = explode("\\", $_SERVER['PHP_AUTH_USER'])[1];
    } else {
        $username = $_SERVER['PHP_AUTH_USER'];
    }
    echo '
          <input type="text" id="login" class="form-control" placeholder="', filter_var($username, FILTER_SANITIZE_STRING), '" readonly>';
} else {
    echo '
          <input type="text" id="login" class="form-control" placeholder="'.langHdl('index_login').'">';
}

echo '
        </div>';
if (!(isset($SETTINGS['enable_http_request_login']) === true
    && $SETTINGS['enable_http_request_login'] === '1'
    && isset($_SERVER['PHP_AUTH_USER']) === true
    && !(isset($SETTINGS['maintenance_mode']) === true
    && $SETTINGS['maintenance_mode'] === '1'))
) {
    echo '
        <div class="input-group has-feedback mb-2">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-lock"></i></span>
            </div>
          <input type="password" id="pw" class="form-control" placeholder="'.langHdl('index_password').'">
        </div>';
}

echo '
        <div class="input-group has-feedback mb-2">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-clock-o"></i></span>
            </div>
          <input type="text" id="session_duration" class="form-control" placeholder="'.langHdl('index_session_duration').'&nbsp;('.langHdl('minutes').')" value="', isset($SETTINGS['default_session_expiration_time']) === true ? $SETTINGS['default_session_expiration_time'] : '', '">
        </div>';

    // 2FA auth selector
    echo '
        <input type="hidden" id="2fa_agses" value="', isset($SETTINGS['agses_authentication_enabled']) === true && $SETTINGS['agses_authentication_enabled'] === '1' ? '1' : '0', '" />
        <input type="hidden" id="2fa_duo" value="', isset($SETTINGS['duo']) === true && $SETTINGS['duo'] === '1' ? '1' : '0', '" />
        <input type="hidden" id="2fa_google" value="', isset($SETTINGS['google_authentication']) === true && $SETTINGS['google_authentication'] === '1' ? '1' : '0', '" />
        <input type="hidden" id="2fa_yubico" value="', isset($SETTINGS['yubico_authentication']) === true && $SETTINGS['yubico_authentication'] === '1' ? '1' : '0', '" />
        <input type="hidden" id="2fa_user_selection" value="',
            (isset($_GET['post_type']) === true && $_GET['post_type'] === 'duo' ? 'duo' : '')
        , '" />
        <div class="row mb-3" id="2fa_methods_selector">
          <div class="col-12">
            <h8 class="login-box-msg">'.langHdl('2fa_authentication_selector').'</h8>
            <div class="2fa-methods" style="padding:3px; text-align:center;">
            ', isset($SETTINGS['google_authentication']) === true && $SETTINGS['google_authentication'] === '1' ?
                '<label for="select2fa-google">Google</label>
                <input type="radio" class="2fa_selector_select" name="2fa_selector_select" id="select2fa-google">' : '', '
                ', isset($SETTINGS['agses_authentication_enabled']) === true && $SETTINGS['agses_authentication_enabled'] === '1' ?
                '<label for="select2fa-agses">Agses</label>
                <input type="radio" class="2fa_selector_select" name="2fa_selector_select" id="select2fa-agses">' : '', '
                ', isset($SETTINGS['duo']) === true && $SETTINGS['duo'] === '1' ?
                '<label for="select2fa-duo">Duo Security</label>
                <input type="radio" class="2fa_selector_select" name="2fa_selector_select" id="select2fa-duo">' : '', '
                ', isset($SETTINGS['yubico_authentication']) === true && $SETTINGS['yubico_authentication'] === '1' ?
                '<label for="select2fa-yubico">Yubico</label>
                <input type="radio" class="2fa_selector_select" name="2fa_selector_select" id="select2fa-yubico">' : '', '
            </div>
          </div>
        </div>';

// AGSES
if (isset($SETTINGS['agses_authentication_enabled']) === true && $SETTINGS['agses_authentication_enabled'] === '1') {
    echo '
        <div id="div-2fa-agses" class="row mb-3 div-2fa-method ', isset($_SESSION['2famethod-agses']) === true && $_SESSION['2famethod-agses'] === '1' ? '' : 'hidden', '">
            <div id="agses_cardid_div" style="text-align:center; padding:5px; width:454px; margin:5px 0 5px;" class="ui-state-active ui-corner-all">
                ' . $LANG['user_profile_agses_card_id'].': &nbsp;
                <input type="text" size="12" id="agses_cardid">
            </div>
            <div id="agses_flickercode_div" style="text-align:center; display:none;">
                <canvas id="axs_canvas"></canvas>
            </div>
            <input type="text" id="agses_code" name="agses_code" style="margin-top:15px;" class="input_text text ui-widget-content ui-corner-all hidden submit-button" placeholder="' . addslashes($LANG['index_agses_key']).'" />
        </div>';
}

// Google Authenticator code
if (isset($SETTINGS['google_authentication']) === true && $SETTINGS['google_authentication'] === "1") {
    echo '
        <div id="div-2fa-google" class="row mb-3 div-2fa-method ', isset($_SESSION['2famethod-google']) === true && $_SESSION['2famethod-google'] === '1' ? '' : 'hidden', '">
          <div class="col-3">
            <img src="includes/images/2fa_google_auth.png">
          </div>
          <div class="col-8">
            <input type="text" id="ga_code" class="form-control submit-button" placeholder="'.langHdl('ga_identification_code').'" />
        </div>
        <div class="col-1">
            <span class="fa fa-envelope-o form-control-feedback hand infotip" title="'.langHdl('i_need_to_generate_new_ga_code').'" onclick="send_user_new_temporary_ga_code()"></span>
          </div>
        </div>';
}

if (isset($SETTINGS['enable_http_request_login']) === true
    && $SETTINGS['enable_http_request_login'] === '1'
    && isset($_SERVER['PHP_AUTH_USER']) === true
    && (isset($SETTINGS['maintenance_mode']) === false
    && $SETTINGS['maintenance_mode'] === '1')
) {
    echo '
<script>
var seconds = 1;
function updateLogonButton(timeToGo){
document.getElementById("but_identify_user").value = "'.langHdl('duration_login_attempt').' " + timeToGo;
}
$( window ).on( "load", function() {
updateLogonButton(seconds);
setInterval(function() {
  seconds--;
  if (seconds >= 0) {
      updateLogonButton(seconds);
  } else if(seconds === 0) {
      launchIdentify("", "'.$nextUrl.'");
  }
  updateLogonButton(seconds);
},
1000
);
});
</script>';
}

// Yubico authentication
if (isset($SETTINGS['yubico_authentication']) === true && $SETTINGS['yubico_authentication'] === "1") {
    echo '
        <div id="div-2fa-yubico" class="row mb-3 div-2fa-method ', isset($_SESSION['2famethod-yubico']) === true && $_SESSION['2famethod-yubico'] === '1' ? '' : 'hidden', '">
            <div class="col-3">
                <img src="includes/images/yubico.png">
            </div>

            <div class="col-8">
                <div id="yubico_credentials_div" class="hidden">
                    <h4>'.langHdl('provide_yubico_identifiers').'</h4>
                    <label for="yubico_user_id">'.langHdl('yubico_user_id').'</label>
                    <input type="text" size="10" id="yubico_user_id" class="form-control" />

                    <label for="yubico_user_key">'.langHdl('yubico_user_key').'</label>
                    <input type="text" size="10" id="yubico_user_key" class="form-control" />
                </div>
                <input autocomplete="off" type="text" id="yubiko_key" class="form-control submit-button" placeholder="'.langHdl('press_your_yubico_key').'">
                <div id="show_yubico_credentials" class="hidden"><a href="#" id="yubico_link">'.langHdl('show_yubico_info_form').'</a></div>
            </div>
        </div>';
}

echo '
          <div class="row mb-3">
            <div class="col-12">
                <button id="but_identify_user" class="btn btn-primary btn-block">'.langHdl('log_in').'</button>
            </div>
            <!-- /.col -->
          </div>
        </div>';

// Forgot link
if (isset($SETTINGS['disable_show_forgot_pwd_link']) === true) {
    echo '
        <div class="row mb-1">
            <a href="#" id="link_forgot_user_pwd">'.langHdl('forgot_my_pw').'</a>
        </div>';
}

      
echo '
    </div>
    <!-- /.login-card-body -->
  </div>
</div>
<!-- /.login-box -->';
?>

<script src="plugins/jquery/jquery.min.js"></script>
<script type="text/javascript" src="plugins/radioforbuttons/jquery.radiosforbuttons.min.js"></script>
<link rel="stylesheet" href="plugins/radioforbuttons/bootstrap-buttons.min.css" type="text/css" />
<script src="includes/js/functions.js"></script>
<script type="text/javascript">


// On page load
$(function() {
    // Set focus on login input
    $('#login').focus();

    // Click on log in button
    $('#but_identify_user').click(function() {
        launchIdentify('', '<?php isset($nextUrl) === true ? $nextUrl : '';?>');
    });

    // Click on forgot password button
    $('#link_forgot_user_pwd').click(function() {
        alertify.prompt(
            '<?php echo langHdl('forgot_my_pw');?>',
            '<?php echo langHdl('forgot_my_pw_text');?>',
            '<?php echo langHdl('email');?>'
            , function(evt, value) {
                alertify
                    .message(
                        '<?php echo '<span class="fa fa-cog fa-spin fa-lg"></span>&nbsp;'.langHdl('please_wait');?>',
                        0
                    )
                    .dismissOthers();
                $.post(
                    "sources/main.queries.php",
                    {
                        type  : "send_pw_by_email",
                        email : value,
                        login : $("#login").val()
                    },
                    function(data) {
                        if (data[0].error !== '') {
                            alertify.error(data[0].message, 10).dismissOthers(); 
                        } else {
                            alertify.success(data[0].message).dismissOthers(); 
                        }
                    },
                    "json"
                );
            }
            , function() {
                alertify.error('Cancel');
            }
        );
    });

    // Show tooltips
    $('.infotip').tooltip();
});

var twoFaMethods = parseInt($("#2fa_google").val())
  + parseInt($("#2fa_agses").val())
  + parseInt($("#2fa_duo").val())
  + parseInt($("#2fa_yubico").val()
);
if (twoFaMethods > 1) {
    // At least 2 2FA methods have to be shown
    var loginButMethods = ['google', 'agses', 'duo'];

    // Show methods
    $("#2fa_selector").removeClass("hidden");

    // Hide login button
    $('#div-login-button').addClass('hidden');

    // Unselect any method
    $(".2fa_selector_select").prop('checked', false);

    // Prepare buttons
    $('.2fa-methods').radiosforbuttons({
        margin: 20,
        vertical: false,
        group: false,
        autowidth: true
    });

    // Handle click
    $('.radiosforbuttons-2fa_selector_select')
    .click(function() {
        $('.div-2fa-method').addClass('hidden');
        
        var twofaMethod = $(this).text().toLowerCase();

        // Save user choice
        $('#2fa_user_selection').val(twofaMethod);

        // Show 2fa method div
        $('#div-2fa-'+twofaMethod).removeClass('hidden');

        // Show login button if required
        if ($.inArray(twofaMethod, loginButMethods) !== -1) {
            $('#div-login-button').removeClass('hidden');
        } else {
            $('#div-login-button').addClass('hidden');
        }

        // Make focus
        if (twofaMethod === 'google') {
            $('#ga_code').focus();
        } else if (twofaMethod === 'yubico') {
            $('#yubiko_key').focus();
        } else if (twofaMethod === 'agses') {
            startAgsesAuth();
        }
    });
} else if (twoFaMethods === 1) {
    // One 2FA method is expected
    if ($('#2fa_google').val() === '1') {
        $('#div-2fa-google').removeClass('hidden');
    } else if ($('#2fa_yubico').val() === '1') {
        $('#div-2fa-yubico').removeClass('hidden');
    } else if ($('#2fa_agses').val() === '1') {
        $('#div-2fa-agses').removeClass('hidden');
    }
    $('#login').focus();
} else {
    // No 2FA methods is expected
    $('#2fa_methods_selector').addClass('hidden');
}

$('.submit-button').keypress(function(event){
    if (event.keyCode === 10 || event.keyCode === 13) {
        launchIdentify('', '<?php echo $nextUrl; ?>', '');
        event.preventDefault();
    }
});

$('#yubiko_key').change(function(event) {
    launchIdentify('', '<?php echo $nextUrl; ?>', '');
    event.preventDefault();
});



/**
 * 
 */
function launchIdentify(isDuo, redirect, psk)
{ 
    if (redirect == undefined) {
        redirect = ""; //Check if redirection
    }
    
    // Check credentials are set
    if ($("#pw").val() === "" || $("#login").val() === "") {
            // Show warning
            if ($("#pw").val() === "") $("#pw").addClass("ui-state-error");
            if ($("#login").val() === "") $("#login").addClass("ui-state-error");

            // Clear 2fa code
            if ($("#yubiko_key").length > 0) {
                $("#yubiko_key").val("");
            }
            if ($("#ga_code").length > 0) {
                $("#ga_code").val("");
            }

            return false;
        }

        // 2FA method
        var user2FaMethod = $("#2fa_user_selection").val();

        if (user2FaMethod !== "") {
            if ((user2FaMethod === "yubico" && $("#yubiko_key").val() === "")
                || (user2FaMethod === "google" && $("#ga_code").val() === "")
            ) {
                return false;
            }
        } else {

        }

    // launch identification
    showAlertify('<span class="fa fa-cog fa-spin fa-2x"></span>', 0, 'top-center', 'notify');

    //create random string
    var randomstring = CreateRandomString(10);

    // get timezone
    var d = new Date();
    var TimezoneOffset = d.getTimezoneOffset()*60;

    // get some info
    var client_info = "";
    $.getJSON('https://ipapi.co/json',
        null,
        function (answered_data) { 
            if (answered_data.ip !== "") {
                client_info = answered_data.country+"-"+answered_data.city+"-"+answered_data.timezone;
            }
            
            // Get 2fa
            $.post(
                "sources/identify.php",
                {
                    type : "get2FAMethods"
                },
                function(fa_methods) {
                    var data = "";
                    if (user2FaMethod === "" && fa_methods[0].nb === "1") {
                        user2FaMethod = fa_methods[0].method;
                    }

                    // Google 2FA
                    if (user2FaMethod === "agses" && $("#agses_code").val() !== undefined) {
                        data = ', "agses_code":"' + $("#agses_code").val() + '"';
                    }
            
                    // Google 2FA
                    if (user2FaMethod === "google" && $("#ga_code").val() !== undefined) {
                        data = ', "GACode":"' + $("#ga_code").val() + '"';
                    }
                    
                    // Yubico
                    if (user2FaMethod === "yubico" && $("#yubiko_key").val() !== undefined) {
                        data = ', "yubico_key":"' + $("#yubiko_key").val()+ '"'+
                            ', "yubico_user_id":"' + ($("#yubico_user_id").val()) + '"'+
                            ', "yubico_user_key":"' + ($("#yubico_user_key").val()) + '"';
                    }

                    data = '{"login":"'+sanitizeString($("#login").val())+'" , "pw":"'+sanitizeString($("#pw").val())+'" , "duree_session":"'+$("#session_duration").val()+'" , "screenHeight":"'+$("body").innerHeight()+'" , "randomstring":"'+randomstring+'" , "TimezoneOffset":"'+TimezoneOffset+'"'+data+' , "client":"'+client_info+'" , "user_2fa_selection":"'+user2FaMethod+'"}';

                    // Handle if DUOSecurity is enabled
                    if (user2FaMethod === "agses" && $("#agses_code").val() === "") {
                        startAgsesAuth();
                    } else if (user2FaMethod !== "duo" || $("#login").val() === "admin") {
                        identifyUser(redirect, psk, data, randomstring);
                    } else {
                        // Handle if DUOSecurity is enabled
                        $("#duo_data").val(window.btoa(data));
                        loadDuoDialog();
                    }
                },
                "json"
            );
        }
    );
}

//Identify user
function identifyUser(redirect, psk, data, randomstring)
{
    // Check if session is still existing
    $.post(
        "sources/checks.php",
        {
            type : "checkSessionExists"
        },
        function(check_data) {console.log(data);
            if (check_data === "1") {
                //send query
                $.post(
                    "sources/identify.php",
                    {
                        type : "identify_user",
                        data : prepareExchangedData(data, 'encode', '<?php echo $_SESSION["key"];?>')
                    },
                    function(data) {
                        if (data[0].value === randomstring) {
                            $("#connection_error").hide();
                            //redirection for admin is specific
                            if (data[0].user_admin === "1") {
                                window.location.href="index.php?page=admin";
                            } else if (data[0].initial_url !== "") {
                                window.location.href=data[0].initial_url;
                            } else {
                                window.location.href = "index.php?page=items";
                            }
                        } else if (data[0].value === "user_is_locked") {
                            showAlertify('<?php echo langHdl('account_is_locked');?>', 5, 'top-right', 'warning');
                        } else if (data[0].value === "bad_psk") {
                            showAlertify('<?php echo langHdl('bad_psk');?>', 5, 'top-right', 'warning');
                        } else if (data[0].value === "bad_psk_confirmation") {
                            showAlertify('<?php echo langHdl('bad_psk_confirmation');?>', 5, 'top-right', 'warning');
                        } else if (data[0].value === "psk_required") {
                            $("#connect_psk_confirm").show();
                            showAlertify('<?php echo langHdl('psk_required');?>', 5, 'top-right', 'warning');
                        } else if (data[0].value === "user_not_exists") {
                            showAlertify('<?php echo langHdl('error_bad_credentials');?>', 5, 'top-right', 'warning');
                        } else if (!isNaN(parseFloat(data[0].value)) && isFinite(data[0].value)) {
                            showAlertify('<?php echo langHdl('login_attempts_on')."&nbsp;".(@$SETTINGS['nb_bad_authentication'] + 1);?>', 5, 'top-right', 'warning');
                        } else if (data[0].value === "error") {
                            $("#mysql_error_warning").html(data[0].text).show();
                            $("#div_mysql_error").show().dialog("open");
                            showAlertify('<?php echo langHdl('account_is_locked');?>', 5, 'top-right', 'warning');
                        } else if (data[0].value === "false_onetimepw") {
                            showAlertify('<?php echo langHdl('bad_onetime_password');?>', 5, 'top-right', 'warning');
                        } else if (data[0].pwd_attempts >= 3 || data[0].error === "bruteforce_wait") {
                            // now user needs to wait 10 secs before new passwd
                            showAlertify('<?php echo langHdl('error_bad_credentials_more_than_3_times');?>', 5, 'top-right', 'warning');
                        } else if (data[0].error === "bad_credentials") {
                            showAlertify('<?php echo langHdl('error_bad_credentials');?>', 5, 'top-right', 'warning');
                        } else if (data[0].error === "ga_code_wrong") {
                            showAlertify('<?php echo langHdl('ga_bad_code');?>', 5, 'top-right', 'warning');
                        } else if (data[0].value === "agses_error") {
                            showAlertify(data[0].error, 5, 'top-right', 'warning');
                        } else if (data[0].error === "ga_temporary_code_wrong") {
                            showAlertify('<?php echo langHdl('ga_bad_code');?>', 5, 'top-right', 'warning');
                        } else if (data[0].error === "ga_temporary_code_correct") {
                            $("#ga_code").val("").focus();
                            showAlertify(
                                data[0].value + '<br /><?php echo langHdl('ga_flash_qr_and_login');?>',
                                5,
                                'top-right',
                                'warning'
                            );
                        } else if (data[0].value === "install_error") {
                            showAlertify(data[0].error, 5, 'top-right', 'warning');
                        } else if (data[0].value === "no_user_yubico_credentials") {
                            $("#yubico_credentials_div").removeClass("hidden");
                            $("#yubico_user_id").focus();
                        } else if (data[0].value === "bad_user_yubico_credentials") {
                            showAlertify('<?php echo langHdl('account_is_loyubico_bad_codecked');?>', 5, 'top-right', 'warning');
                            if ($("#yubico_credentials_div").hasClass("hidden")) {
                                $("#show_yubico_credentials").removeClass("hidden");
                                $("#yubico_link").click(function() {
                                    $("#yubico_credentials_div").removeClass("hidden");
                                    $("#show_yubico_credentials").addClass("hidden");
                                });
                            }
                        } else {
                            showAlertify('<?php echo langHdl('error_bad_credentials');?>', 5, 'top-right');
                        }

                        // Clear Yubico
                        if ($("#yubiko_key").length > 0) {
                            $("#yubiko_key").val("");
                        }

                        $("#ajax_loader_connexion").hide();
                    },
                    "json"
                );
            } else {
                // No session was found, warn user
                // Attach the CSRFP tokenn to the form to prevent against error 403
                var csrfp = check_data.split(";");
                $("#form_identify").append(
                    "<input type='hidden' name='"+csrfp[0]+"' value='"+csrfp[1]+"' />" +
                    "<input type='hidden' name='auto_log' value='1' />"
                );

                // Warn user
                $("#main_info_box_text").html("<span ='fa fa-warning fa-lg'></span>&nbsp;Browser session is now expired. The page will be automatically reloaded in 2 seconds.");
                $("#main_info_box").show().position({
                    my: "center",
                    at: "center top+75",
                    of: "#top"
                });

                // Delay page submit
                $(this).delay(2000).queue(function() {
                    $("#form_identify").submit();
                    $(this).dequeue();
                });
            }
        }
    );
}

function getGASynchronization()
{
    if ($("#login").val() != "" && $("#pw").val() != "") {
        $("#ajax_loader_connexion").show();
        $("#connection_error").hide();
        $("#div_ga_url").hide();
        data = '{"login":"'+sanitizeString($("#login").val())+'" ,'+
                '"pw":"'+sanitizeString($("#pw").val())+'"}';
        //send query
        $.post(
            "sources/main.queries.php",
            {
                type : "ga_generate_qr",
                data : prepareExchangedData(data, "encode", "<?php echo $_SESSION["key"];?>"),
                send_email : "1"
            },
            function(data) {
                if (data[0].error === "0") {
                    $("#div_ga_url").show();
                } else if (data[0].error === "not_allowed") {
                    $("#connection_error").html("<?php echo langHdl('2FA_new_code_by_user_not_allowed');?>").show();
                    $("#div_ga_url").hide();
                } else if (data[0].error === "no_user") {
                    $("#connection_error").html("<?php echo langHdl('error_bad_credentials');?>").show();
                    $("#div_ga_url").hide();
                } else if (data[0].error === "no_email") {
                    $("#connection_error").html("<?php echo langHdl('error_no_email');?>").show();
                    $("#div_ga_url").hide();
                } else {
                    $("#connection_error").html("<?php echo langHdl('index_bas_pw');?>").show();
                    $("#div_ga_url").hide();
                }
                $("#ajax_loader_connexion").hide();
            },
            "json"
        );
    } else {
        $("#connection_error").html("<?php echo langHdl('ga_enter_credentials');?>").show();
    }
}

function send_user_new_temporary_ga_code() {
    // Check login and password
    if ($("#login").val() === "" || $("#pw").val() === "") {
        $("#connection_error").html("<?php echo langHdl('ga_enter_credentials');?>").show();
        return false;
    }
    $("#div_loading").show();
    $("#connection_error").html("").hide();

    data = '{"login":"'+sanitizeString($("#login").val())+'" ,'+
                '"pwd":"'+sanitizeString($("#pw").val())+'"}';

    $.post(
        "sources/main.queries.php",
        {
            type : "ga_generate_qr",
            data : prepareExchangedData(data, "encode", "<?php echo $_SESSION["key"];?>"),
            send_email : "1"
        },
        function(data) {
            if (data[0].error === "0") {
                $("#div_dialog_message").html(data[0].msg).dialog("open");
            } else if (data[0].error === "no_user") {
                $("#connection_error").html("<?php echo langHdl('error_bad_credentials');?>")
                    .show().delay(3000).fadeOut(500);
            } else if (data[0].error === "not_allowed") {
                $("#connection_error").html("<?php echo langHdl('setting_disabled_by_admin');?>")
                    .show().delay(3000).fadeOut(500);
            } else if (data[0].error === "no_email") {
                $("#connection_error").html("<?php echo langHdl('error_no_email');?>").show();
                $("#div_ga_url").hide();
            } else {

            }
            $("#div_loading").hide();
        },
        "json"
    );
}
</script>