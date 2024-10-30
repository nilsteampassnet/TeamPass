<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      login.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\OAuth2Controller\OAuth2Controller;
// Automatic redirection
$nextUrl = '';
if (strpos($server['request_uri'], '?') > 0) {
    $nextUrl = filter_var(
        substr($server['request_uri'], strpos($server['request_uri'], '?')),
        FILTER_SANITIZE_URL
    );
}

$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');
$get = [];
$postType = $request->query->get('post_type', '');
$postType = filter_var($postType, FILTER_SANITIZE_SPECIAL_CHARS);
$get['post_type'] = $postType;
if (isset($SETTINGS['duo']) === true && (int) $SETTINGS['duo'] === 1 && $get['post_type'] === 'duo' ) {
    $get['duo_state'] = $request->query->get('state');
    $get['duo_code'] = $request->query->get('duo_code');
}

// Manage case of Oauth2 login
if (isset($_GET['code']) === true && isset($_GET['state']) === true && $get['post_type'] === 'oauth2') {
    $get['code'] = filter_var($_GET['code'], FILTER_SANITIZE_SPECIAL_CHARS);
    $get['state'] = filter_var($_GET['state'], FILTER_SANITIZE_SPECIAL_CHARS);
    $get['session_state'] = filter_var($_GET['session_state'], FILTER_SANITIZE_SPECIAL_CHARS);

    if (WIP === true) error_log('---- OAUTH2 START ----');

    // Création d'une instance du contrôleur
    $OAuth2 = new OAuth2Controller($SETTINGS);

    // Traitement de la réponse de callback Azure
    $userInfo = $OAuth2->callback();

    if ($userInfo['error'] === false) {
        // Si aucune erreur, stocker les informations utilisateur dans la session PHP

        // Stocker les informations de l'utilisateur dans la session
        $session->set('userOauth2Info', $userInfo['userOauth2Info']);

        // Rediriger l'utilisateur vers la page d'accueil ou une page d'authentification réussie
        header('Location: index.php');
        exit;
    } else {
        // Gérer les erreurs
        echo 'Erreur lors de la récupération des informations utilisateur : ' . htmlspecialchars($userInfo['message'], ENT_QUOTES, 'UTF-8');
    };
}

// Azure step is done
if (null !== $session->get('userOauth2Info') && empty($session->get('userOauth2Info')) === false && $session->get('userOauth2Info')['oauth2TokenUsed'] === false) {
    // Azure step is done
    // Check if user exists in Teampass
    if (WIP === true) {
        error_log('---- CALLBACK LOGIN ----');
    }

    $session->set('user-login', strstr($session->get('userOauth2Info')['userPrincipalName'], '@', true));

    // Encoder les valeurs de la session en JSON
    $userOauth2InfoJson = json_encode($session->get('userOauth2Info'));
}

echo '
<body class="hold-transition login-page '.$theme_body.'">
<div class="login-box">
    <div class="login-logo"><div style="margin:30px;">',
    isset($SETTINGS['custom_logo']) === true && empty($SETTINGS['custom_logo']) === false ?
        '<img src="' . (string) $SETTINGS['custom_logo'] . '" alt="" style="text-align:center; max-width:100px;" />' :
        '<img src="includes/images/teampass-logo2-login.png" alt="Teampass Logo">',
        '
        </div>
        <div style="font-weight:bold;">
            '.TP_TOOL_NAME.'
        </div>
    </div>

    <!-- /.login-logo -->
    <div class="card">
        <div class="card-header text-center">
            <h3>',
    isset($SETTINGS['custom_login_text']) === true
        && empty($SETTINGS['custom_login_text']) === false ? $SETTINGS['custom_login_text'] : $lang->get('index_get_identified'),
    '
            </h3>
        </div>

        <div class="card-body login-card-body1">
            <div class="input-group has-feedback mb-2">
                <div class="input-group-prepend infotip" title="' . $lang->get('login') . '">
                    <span class="input-group-text"><i class="fa-solid fa-user fa-fw"></i></span>
                </div>';
if (
    isset($SETTINGS['enable_http_request_login']) === true
    && (int) $SETTINGS['enable_http_request_login'] === 1
    && $request->getUser() !== null
    && ! (isset($SETTINGS['maintenance_mode']) === true
        && (int) $SETTINGS['maintenance_mode'] === 1)
) {
    if (strpos($request->getUser(), '@') !== false) {
        $username = explode('@', $request->getUser())[0];
    } elseif (strpos($request->getUser(), '\\') !== false) {
        $username = explode('\\', $request->getUser())[1];
    } else {
        $username = $request->getUser();
    }
    echo '
            <input type="text" id="login" class="form-control" placeholder="', filter_var($username, FILTER_SANITIZE_FULL_SPECIAL_CHARS), '" readonly>';
} else {
    echo '
            <input type="text" id="login" class="form-control" placeholder="' . $lang->get('index_login') . '" value="'.(null !== $session->get('user-login') && empty($session->get('user-login')) === false ? filter_var($session->get('user-login'), FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '').'">';
}

echo '
        </div>';
if (! (isset($SETTINGS['enable_http_request_login']) === true
    && (int) $SETTINGS['enable_http_request_login'] === 1
    && $request->getUser() !== null
    && ! (isset($SETTINGS['maintenance_mode']) === true
        && (int) $SETTINGS['maintenance_mode'] === 1))) {
    echo '
        <div class="input-group has-feedback mb-2">
            <div class="input-group-prepend infotip" title="' . $lang->get('password') . '">
                <span class="input-group-text"><i class="fa-solid fa-lock fa-fw"></i></span>
            </div>
            <input type="password" id="pw" class="form-control submit-button" placeholder="' . $lang->get('index_password') . '">
        </div>';
}

echo '
        <div class="input-group has-feedback mb-2">
            <div class="input-group-prepend infotip" title="' . $lang->get('session_expiration_in_minutes') . '">
                <span class="input-group-text"><i class="fa-solid fa-clock fa-fw"></i></span>
            </div>
            <input type="text" id="session_duration" class="form-control submit-button" 
            placeholder="' . $lang->get('index_session_duration') .'&nbsp;(' . $lang->get('minutes') . ')" 
            value="', isset($SETTINGS['default_session_expiration_time']) === true ? $SETTINGS['default_session_expiration_time'] : '', '">
        </div>';
// 2FA auth selector
$mfaHtmlPart = '
        <input type="hidden" id="2fa_user_selection" value="'.htmlspecialchars((isset($get['post_type']) === true && $get['post_type'] === 'duo') ? 'duo' : ''). '">
        <input type="hidden" id="duo_code" value="'.htmlspecialchars(isset($get['duo_code']) === true && is_null($get['duo_code']) === false ? $get['duo_code'] : ''). '">
        <input type="hidden" id="duo_state" value="'.htmlspecialchars(isset($get['duo_state']) === true && is_null($get['duo_state']) === false ? $get['duo_state'] : ''). '">
        <div class="row mb-3 hidden" id="2fa_methods_selector">
            <div class="col-12">
                <h8 class="login-box-msg">' . $lang->get('2fa_authentication_selector') . '</h8>
                <div class="2fa-methods text-center mt-2">'.
                (isset($SETTINGS['google_authentication']) === true && (int) $SETTINGS['google_authentication'] === 1 ?
        '
                    <label for="select2fa-otp">Google</label>
                    <input type="radio" class="2fa_selector_select" name="2fa_selector_select" id="select2fa-otp" data-mfa="google" data-button-color="lightblue">' : '').
                    (isset($SETTINGS['duo']) === true && (int) $SETTINGS['duo'] === 1 ?
        '
                    <label for="select2fa-duo">Duo Security</label>
                    <input type="radio" class="2fa_selector_select" name="2fa_selector_select" id="select2fa-duo" data-mfa="duo" data-button-color="lightblue">' : '').
                    (isset($SETTINGS['yubico_authentication']) === true && (int) $SETTINGS['yubico_authentication'] === 1 ?
        '
                    <label for="select2fa-yubico">Yubico</label>
                    <input type="radio" class="2fa_selector_select" name="2fa_selector_select" id="select2fa-yubico" data-mfa="yubico" data-button-color="lightblue">' : '').
    '
                </div>
            </div>
        </div>';
echo $mfaHtmlPart;

// DUO box
if (isset($SETTINGS['duo']) === true && (int) $SETTINGS['duo'] === 1) {
    echo '
        <div id="div-2fa-duo" class="row mb-3 div-2fa-method hidden">
            <div id="div-2fa-duo-progress" class="text-center hidden"></div>
        </div>';
}

// Google Authenticator code
if (isset($SETTINGS['google_authentication']) === true && (int) $SETTINGS['google_authentication'] === 1) {
    echo '
        <div id="div-2fa-google" class="mb-3 div-2fa-method hidden">
            <div class="input-group has-feedback mb-2">
                <div class="input-group-prepend infotip" title="' . $lang->get('mfa_unique_code') . '">
                    <span class="input-group-text"><i class="fa-solid fa-key fa-fw"></i></span>
                </div>
                <input type="text" id="ga_code" class="form-control submit-button" placeholder="' . $lang->get('ga_identification_code') . '" />
                <span class="input-group-append">
                    <button type="button" class="btn btn-info btn-flat" onclick="send_user_new_temporary_ga_code()">
                        <i class="fa-solid fa-envelope form-control-feedback pointer infotip" 
                    title="' . $lang->get('i_need_to_generate_new_ga_code') . '"></i>
                    </button>
                </span>
            </div>
            <div id="div-2fa-google-qr" class="row mt-2 "></div>
        </div>';
}

if (isset($SETTINGS['enable_http_request_login']) === true
    && (int) $SETTINGS['enable_http_request_login'] === 1
    && $request->getUser() !== null
    && (isset($SETTINGS['maintenance_mode']) === false
    && (int) $SETTINGS['maintenance_mode'] === 1)
) {
    echo '
<script>
var seconds = 1;
function updateLogonButton(timeToGo){
    document.getElementById("but_identify_user").value = "' . $lang->get('duration_login_attempt') . ' " + timeToGo;
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
    500
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
                        <input type="text" size="10" id="yubico_user_id" class="form-control" placeholder="' . $lang->get('yubico_user_id') . '">
                    </p>
                    <p>
                    <input type="text" size="10" id="yubico_user_key" class="form-control" placeholder="' . $lang->get('yubico_user_key') . '">
                    </p>
                </div>
                <input autocomplete="off" type="text" id="yubico_key" class="form-control submit-button" placeholder="' . $lang->get('press_your_yubico_key') . '">
                <div class="row">
                    <span class="ml-2 mt-1 font-weight-light small pointer" id="register-yubiko-key">' . $lang->get('register_new_yubiko_key') . '</span>
                </div>
            </div>
        </div>';
}

echo '
        <div class="row mt-5">
            <div class="col-12">
                <button id="but_identify_user" class="btn btn-primary btn-block">' . $lang->get('log_in') . '</button>
                
                <!-- In case of upgrade, the user has to provide his One Time Code -->
                <div class="card-body user-one-time-code-card-body hidden">
                    <h5 class="login-box-msg">' . $lang->get('provide_personal_one_time_code') . '</h5>

                    <div class="input-group has-feedback mb-2 mt-4">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa-solid fa-unlock-alt"></i></span>
                        </div>
                        <input type="password" id="user-one-time-code" class="form-control" placeholder="' . $lang->get('one_time_code') . '">
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <button id="but_confirm_otc" class="btn btn-primary btn-block">' . $lang->get('confirm') . '</button>
                        </div>
                    </div>
                </div>
                <!-- /end -->

            </div>
        </div>';
        
// OAUTH2 div
if (isKeyExistingAndEqual('oauth2_enabled', 1, $SETTINGS) === true) {
    echo '
        <hr class="mt-3 mb-3"/>
        <div class="row mb-2">
            <div class="col-12">
                <button id="but_login_with_oauth2" class="btn btn-primary btn-block">' . $SETTINGS['oauth2_client_appname'] . '</button>
            </div>
        </div>';
}

echo '
    </div>';

echo '
    <!-- /.login-card-body -->';
// In case of password change
echo '
    <div class="card-body confirm-password-card-body hidden">
        <h5 class="login-box-msg">' . $lang->get('new_password_required') . '</h5>

        <div class="alert alert-info">
            <div class="text-center"><i class="icon fa fa-info"></i>' . $lang->get('password_strength') . '
            <span id="confirm-password-level" class="ml-2 font-weight-bold"></span></div>
        </div>

        <div>
            <div id="current-user-password-div" class="hidden">
                <div class="input-group has-feedback mb-2">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-key"></i></span>
                    </div>
                    <input type="password" id="current-user-password" class="form-control" placeholder="' . $lang->get('current_password') . '">
                </div>
            </div>
            <div class="input-group has-feedback mb-2 mt-4">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                </div>
                <input type="password" id="new-user-password" class="form-control" placeholder="' . $lang->get('index_new_pw') . '">
            </div>
            <div class="input-group has-feedback mb-2">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                </div>
                <input type="password" id="new-user-password-confirm" class="form-control" placeholder="' . $lang->get('index_change_pw_confirmation') . '">
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
                    <button id="but_confirm_new_password" class="btn btn-primary btn-block">' . $lang->get('confirm') . '</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card-body hidden" id="card-user-treat-psk">
        <div class="alert alert-info">
            <div class="text-center"><i class="icon fa fa-info"></i>' . $lang->get('user_has_psk_info') . '</div>
        </div>
        <div class="input-group has-feedback mb-2">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-key"></i></span>
            </div>
            <input type="password" id="user-old-defuse-psk" class="form-control" placeholder="' . $lang->get('home_personal_saltkey') . '">
        </div>
        <div class="row mb-3 mt-4">
            <div class="col-12">
                <button id="but_confirm_defuse_psk" class="btn btn-primary btn-block">' . $lang->get('launch') . '</button>
            </div>
            <div class="col-12 mt-3">
                <button id="but_confirm_forgot_defuse_psk" class="btn btn-danger btn-block text-bold">' . $lang->get('i_cannot_remember') . '</button>
            </div>
        </div>
    </div>

</div>
<!-- /.login-box -->';
