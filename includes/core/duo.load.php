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
 * @copyright 2009-2019 Nils Laumaillé
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 *
 * @version   GIT: <git_id>
 *
 * @see      http://www.teampass.net
 */

require_once '../../sources/SecureHandler.php';
session_name('teampass_session');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
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


/*
** This page contains the javascript call for DUOSecurity api
** It loads the expected iFrame where user gives his DUO credentials
** It sends the request to the DUO server
*/
?>
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
                    .html('<iframe id="duo_iframe" style="margin-left:10px;" frameborder="0" data-host="<?php echo $_GLOBALS['HOST']; ?>" data-sig-request="'+data[0].sig_request+'"></iframe>');

                // loading the DUO iframe
                Duo.init({
                    'host': "<?php echo $_GLOBALS['HOST']; ?>",
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