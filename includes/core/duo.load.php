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
 * @file      duo.load.php
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

require_once '../../sources/SecureHandler.php';
session_name('teampass_session');
session_start();
if (isset($_SESSION['CPM']) === false || (int) $_SESSION['CPM'] !== 1) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../../includes/config/tp.config.php') === true) {
    include_once '../../includes/config/tp.config.php';
} elseif (file_exists('../includes/config/tp.config.php') === true) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php') === true) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

require $SETTINGS['cpassman_dir'].'/includes/config/settings.php';

require $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/php-jwt/BeforeValidException.php';
require $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/php-jwt/ExpiredException.php';
require $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/php-jwt/SignatureInvalidException.php';
require $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/php-jwt/JWT.php';
require $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/DuoUniversal/DuoException.php';
require $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/DuoUniversal/Client.php';

// Load superGlobals
include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();



try {
    $duo_client = new Authentication\DuoUniversal\Client(
        $SETTINGS['duo_ikey'],
        $SETTINGS['duo_skey'],
        $SETTINGS['duo_host'],
        $SETTINGS['cpassman_url'].'/duo-callback'
    );
} catch (DuoException $e) {
    throw new ErrorException("*** Duo config error. Verify the values in duo.conf are correct ***\n" . $e->getMessage());
}

$state = $duo_client->generateState();
$superGlobal->put('state', $state, 'SESSION');
$superGlobal->put('username', 'nils', 'SESSION');
unset($superGlobal);

# Redirect to prompt URI which will redirect to the client's redirect URI after 2FA
$prompt_uri = $duo_client->createAuthUrl('nils', $state);
return $response
    ->withHeader('Location', $prompt_uri)
    ->withStatus(302);


/*
** This page contains the javascript call for DUOSecurity api
** It loads the expected iFrame where user gives his DUO credentials
** It sends the request to the DUO server
*/
?>
<!--
<script type="text/javascript">
//<![CDATA[
$(function() {
    $.getScript("./includes/libraries/Authentication/DuoSecurity/Duo-Web-v2.min.js");
    $.post(
        "sources/identify.php",
        {
            type : "identify_duo_user",
            login: sanitizeString($("#login").val()),
        },
        function(data) {
            var ret = data[0].sig_request.split('|');
            $("#div-2fa-duo-progress").removeClass('hidden');
            if (ret[0] === "ERR") {
                $("#div-2fa-duo-progress")
                    .addClass('alert alert-info ')
                    .html('<i class="fas fa-exclamation-triangle text-danger mr-2"></i>' + ret[1]);
            } else {
                // preparing the DUO iframe
                var cssLink = $("<link rel='stylesheet' type='text/css' href='./includes/libraries/Authentication/DuoSecurity/Duo-Frame.css'>");
                $("head").append(cssLink);
                $("#div-2fa-duo-progress")
                    .removeClass('alert alert-info ')
                    .html('<iframe id="duo_iframe" style="margin-left:10px;" frameborder="0" data-host="<?php echo $SETTINGS['duo_host']; ?>" data-sig-request="'+data[0].sig_request+'"></iframe>');

                // loading the DUO iframe
                Duo.init({
                    'host': "<?php echo $SETTINGS['duo_host']; ?>",
                    'sig_request': data[0].sig_request,
                    'post_action': "index.php?type=duo_check&"+data[0].csrfp_token+"="+data[0].csrfp_key+"&post_type=duo"
                });

                $("#duo_login").val($("#login").val());
                $("#duo_pwd").val($("#pw").val());
            }
        },
        "json"
    );
});
//]]>
</script>
-->