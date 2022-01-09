<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 *
 * @file      login.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2022 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

// Automatic redirection
$nextUrl = '';
if (strpos($server['request_uri'], '?') > 0) {
    $nextUrl = filter_var(
        substr($server['request_uri'], strpos($server['request_uri'], '?')),
        FILTER_SANITIZE_URL
    );
}

require_once './includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();
$get = [];
$get['post_type'] = $superGlobal->get('post_type', 'GET');
$post_sig_response = filter_input(INPUT_POST, 'sig_response', FILTER_SANITIZE_STRING);
$post_duo_login = filter_input(INPUT_POST, 'duo_login', FILTER_SANITIZE_STRING);
$post_duo_pwd = filter_input(INPUT_POST, 'duo_pwd', FILTER_SANITIZE_STRING);
$post_duo_data = filter_input(INPUT_POST, 'duo_data', FILTER_SANITIZE_STRING);
echo '
<body class="hold-transition login-page">
<div class="login-box">
    <div class="login-logo">',
    isset($SETTINGS['custom_logo']) === true
        && empty($SETTINGS['custom_logo']) === false ?
        '<img src="' . (string) $SETTINGS['custom_logo'] . '" alt="" style="text-align:center;" />' : '',
    '
        <div style="margin-top:20px;">
            <img src="includes/images/teampass-logo2-login.png" alt="Teampass Logo">
        </div>
        <div style="font-weight:bold;">
            ' . TP_TOOL_NAME . '
        </div>
    </div>

    <!-- /.login-logo -->
    <div class="card">
        <div class="card-header text-center">
            <h3>',
    isset($SETTINGS['custom_login_text']) === true
        && empty($SETTINGS['custom_login_text']) === false ? $SETTINGS['custom_login_text'] : langHdl('index_get_identified'),
    '
            </h3>
        </div>

        <div class="card-body login-card-body1">
            <div class="input-group has-feedback mb-2">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-user fa-fw"></i></span>
                </div>';
if (
    isset($SETTINGS['enable_http_request_login']) === true
    && (int) $SETTINGS['enable_http_request_login'] === 1
    && $superGlobal('PHP_AUTH_USER', 'SERVER') !== null
    && ! (isset($SETTINGS['maintenance_mode']) === true
        && (int) $SETTINGS['maintenance_mode'] === 1)
) {
    if (strpos($superGlobal('PHP_AUTH_USER', 'SERVER'), '@') !== false) {
        $username = explode('@', $superGlobal('PHP_AUTH_USER', 'SERVER'))[0];
    } elseif (strpos($superGlobal('PHP_AUTH_USER', 'SERVER'), '\\') !== false) {
        $username = explode('\\', $superGlobal('PHP_AUTH_USER', 'SERVER'))[1];
    } else {
        $username = $superGlobal('PHP_AUTH_USER', 'SERVER');
    }
    echo '
            <input type="text" id="login" class="form-control" placeholder="', filter_var($username, FILTER_SANITIZE_STRING), '" readonly>';
} else {
    echo '
            <input type="text" id="login" class="form-control" placeholder="' . langHdl('index_login') . '">';
}

echo '
        </div>';
if (! (isset($SETTINGS['enable_http_request_login']) === true
    && (int) $SETTINGS['enable_http_request_login'] === 1
    && $superGlobal('PHP_AUTH_USER', 'SERVER') !== null
    && ! (isset($SETTINGS['maintenance_mode']) === true
        && (int) $SETTINGS['maintenance_mode'] === 1))) {
    echo '
        <div class="input-group has-feedback mb-2">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-lock fa-fw"></i></span>
            </div>
            <input type="password" id="pw" class="form-control submit-button" placeholder="' . langHdl('index_password') . '">
        </div>';
}

echo '
        <div class="input-group has-feedback mb-2">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-clock fa-fw"></i></span>
            </div>
            <input type="text" id="session_duration" class="form-control submit-button" 
            placeholder="' . langHdl('index_session_duration') .'&nbsp;(' . langHdl('minutes') . ')" 
            value="', isset($SETTINGS['default_session_expiration_time']) === true ? $SETTINGS['default_session_expiration_time'] : '', '">
        </div>';
// 2FA auth selector
echo '
        <input type="hidden" id="2fa_user_selection" value="',
    (isset($get['post_type']) === true && $get['post_type'] === 'duo' ? 'duo' : ''),
    '" />
        <input type="hidden" id="duo_sig_response" value="',
    $post_sig_response !== null ? $post_sig_response : '',
    '" />
        <div class="row mb-3 hidden" id="2fa_methods_selector">
            <div class="col-12">
                <h8 class="login-box-msg">' . langHdl('2fa_authentication_selector') . '</h8>
                <div class="2fa-methods text-center mt-2">',
    isset($SETTINGS['google_authentication']) === true && (int) $SETTINGS['google_authentication'] === 1 ?
        '
                    <label for="select2fa-otp">Google</label>
                    <input type="radio" class="2fa_selector_select" name="2fa_selector_select" id="select2fa-otp" data-mfa="google" data-button-color="lightblue">' : '',
    '',
    isset($SETTINGS['duo']) === true && (int) $SETTINGS['duo'] === 1 ?
        '
                    <label for="select2fa-duo">Duo Security</label>
                    <input type="radio" class="2fa_selector_select" name="2fa_selector_select" id="select2fa-duo" data-mfa="duo" data-button-color="lightblue">' : '',
    '',
    isset($SETTINGS['yubico_authentication']) === true && (int) $SETTINGS['yubico_authentication'] === 1 ?
        '
                    <label for="select2fa-yubico">Yubico</label>
                    <input type="radio" class="2fa_selector_select" name="2fa_selector_select" id="select2fa-yubico" data-mfa="yubico" data-button-color="lightblue">' : '',
    '
                </div>
            </div>
        </div>';
// DUO box
if (isset($SETTINGS['duo']) === true && (int) $SETTINGS['duo'] === 1) {
    echo '
        <div id="div-2fa-duo" class="row mb-3 div-2fa-method hidden">
            <div id="div-2fa-duo-progress" class="text-center hidden"></div>
            <form method="post" id="duo_form" action="">
                <input type="hidden" id="duo_login" name="duo_login" value="', $post_duo_login !== null ? $post_duo_login : '', '" />
                <input type="hidden" id="duo_pwd" name="duo_pwd" value="', $post_duo_pwd !== null ? $post_duo_pwd : '', '" />
                <input type="hidden" id="duo_data" name="duo_data" value="', $post_duo_data !== null ? $post_duo_data : '', '" />
            </form>
        </div>';
}

// Google Authenticator code
if (isset($SETTINGS['google_authentication']) === true && (int) $SETTINGS['google_authentication'] === 1) {
    echo '
        <div id="div-2fa-google" class="mb-3 div-2fa-method hidden">
            <div class="row">
                <div class="col-1">
                </div>
                <div class="col-8">
                    <img src="includes/images/otp.png">
                </div>
            </div>
            <div class="row">
                <div class="col-1">
                </div>
                <div class="col-8">
                    <input type="text" id="ga_code" class="form-control submit-button" placeholder="' . langHdl('ga_identification_code') . '" />
                </div>
                <div class="col-1">
                    <i class="fas fa-envelope form-control-feedback pointer infotip text-info" 
                    title="' . langHdl('i_need_to_generate_new_ga_code') . '" onclick="send_user_new_temporary_ga_code()"></i>
                </div>
            </div>
            <div id="div-2fa-google-qr" class="row mt-2 "></div>
        </div>';
}

if (isset($SETTINGS['enable_http_request_login']) === true
    && (int) $SETTINGS['enable_http_request_login'] === 1
    && $superGlobal('PHP_AUTH_USER', 'SERVER') !== null
    && (isset($SETTINGS['maintenance_mode']) === false
    && (int) $SETTINGS['maintenance_mode'] === 1)
) {
    echo '
<script>
var seconds = 1;
function updateLogonButton(timeToGo){
    document.getElementById("but_identify_user").value = "' . langHdl('duration_login_attempt') . ' " + timeToGo;
}
$( window ).on( "load", function() {
    updateLogonButton(seconds);
    setInterval(function() {
        seconds--;
        if (seconds >= 0) {
            updateLogonButton(seconds);
        } else if(seconds === 0) {
            launchIdentify("", "' . $nextUrl . '");
        }
        updateLogonButton(seconds);
    },
    1000
    );
});
</script>';
}

// Yubico authentication
if (isset($SETTINGS['yubico_authentication']) === true && (int) $SETTINGS['yubico_authentication'] === 1) {
    echo '
        <div id="div-2fa-yubico" class="row mb-3 div-2fa-method hidden">
            <div class="col-3">
                <img src="includes/images/yubico.png">
            </div>

            <div class="col-8">
                <div id="yubiko-new-key" class="alert alert-info hidden">
                    <p>
                        <input type="text" size="10" id="yubico_user_id" class="form-control" placeholder="' . langHdl('yubico_user_id') . '">
                    </p>
                    <p>
                    <input type="text" size="10" id="yubico_user_key" class="form-control" placeholder="' . langHdl('yubico_user_key') . '">
                    </p>
                </div>
                <input autocomplete="off" type="text" id="yubico_key" class="form-control submit-button" placeholder="' . langHdl('press_your_yubico_key') . '">
                <div class="row">
                    <span class="ml-2 mt-1 font-weight-light small pointer" id="register-yubiko-key">' . langHdl('register_new_yubiko_key') . '</span>
                </div>
            </div>
        </div>';
}

echo '
        <div class="row mb-3 mt-5">
            <div class="col-12">
                <button id="but_identify_user" class="btn btn-primary btn-block">' . langHdl('log_in') . '</button>
                
                <!-- In case of upgrade, the user has to provide his One Time Code -->
                <div class="card-body user-one-time-code-card-body hidden">
                    <h5 class="login-box-msg">' . langHdl('provide_personal_one_time_code') . '</h5>

                    <div class="input-group has-feedback mb-2 mt-4">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-unlock-alt"></i></span>
                        </div>
                        <input type="password" id="user-one-time-code" class="form-control" placeholder="' . langHdl('one_time_code') . '">
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <button id="but_confirm_otc" class="btn btn-primary btn-block">' . langHdl('confirm') . '</button>
                        </div>
                    </div>
                </div>
                <!-- /end -->

            </div>
        </div>
    </div>';

echo '
    <!-- /.login-card-body -->';
// In case of password change
echo '
    <div class="card-body confirm-password-card-body hidden">
        <h5 class="login-box-msg">' . langHdl('new_password_required') . '</h5>

        <div class="alert alert-info">
            <div class="text-center"><i class="icon fa fa-info"></i>' . langHdl('password_strength') . '
            <span id="confirm-password-level" class="ml-2 font-weight-bold"></span></div>
        </div>

        <div>
            <div id="current-user-password-div" class="hidden">
                <div class="input-group has-feedback mb-2">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-key"></i></span>
                    </div>
                    <input type="password" id="current-user-password" class="form-control" placeholder="' . langHdl('current_password') . '">
                </div>
            </div>
            <div class="input-group has-feedback mb-2 mt-4">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-key"></i></span>
                </div>
                <input type="password" id="new-user-password" class="form-control" placeholder="' . langHdl('index_new_pw') . '">
            </div>
            <div class="input-group has-feedback mb-2">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-key"></i></span>
                </div>
                <input type="password" id="new-user-password-confirm" class="form-control" placeholder="' . langHdl('index_change_pw_confirmation') . '">
            </div>
            <div class="row mb-3">
                <div class="col-md-12 offset-sm-4 text-center">
                    <input type="hidden" id="new-user-password-complexity-level" />
                    <div id="new-user-password-strength"></div>
                </div>
                <!-- /.col -->
            </div>
            <div class="row mb-3">
                <div class="col-12">
                    <button id="but_confirm_new_password" class="btn btn-primary btn-block">' . langHdl('confirm') . '</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card-body hidden" id="card-user-treat-psk">
        <div class="alert alert-info">
            <div class="text-center"><i class="icon fa fa-info"></i>' . langHdl('user_has_psk_info') . '</div>
        </div>
        <div class="input-group has-feedback mb-2">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-key"></i></span>
            </div>
            <input type="password" id="user-old-defuse-psk" class="form-control" placeholder="' . langHdl('home_personal_saltkey') . '">
        </div>
        <div class="row mb-3 mt-4">
            <div class="col-12">
                <button id="but_confirm_defuse_psk" class="btn btn-primary btn-block">' . langHdl('launch') . '</button>
            </div>
            <div class="col-12 mt-3">
                <button id="but_confirm_forgot_defuse_psk" class="btn btn-danger btn-block text-bold">' . langHdl('i_cannot_remember') . '</button>
            </div>
        </div>
    </div>

</div>
<!-- /.login-box -->';
