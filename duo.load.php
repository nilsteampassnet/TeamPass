<?php
/**
 *
 * @package       duo.load.php
 * @author        Nils Laumaillé <nils@teampass.net>
 * @version       2.1.27
 * @copyright     2009-2018 Nils Laumaillé
 * @license       GNU GPL-3.0
 * @link          https://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('./sources/SecureHandler.php');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
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

include $SETTINGS['cpassman_dir'].'/includes/config/settings.php';


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
            login: sanitizeString($("#login").val())
        },
        function(data) {
            var ret = data[0].sig_request.split('|');
            if (ret[0] === "ERR") {
                $("#div_duo").html("ERROR " + ret[1]);
            } else {
                // preparing the DUO iframe
                var cssLink = $("<link rel='stylesheet' type='text/css' href='./includes/libraries/Authentication/DuoSecurity/Duo-Frame.css'>");
                $("head").append(cssLink);
                $("#div_duo").html('<iframe id="duo_iframe" frameborder="0" data-host="<?php echo HOST; ?>" data-sig-request="'+data[0].sig_request+'"></iframe>');

                // loading the DUO iframe
                Duo.init({
                    'host': '<?php echo HOST; ?>',
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