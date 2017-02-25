<?php
/**
 *
 * @file          duo.load.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('./sources/SecureHandler.php');
session_start();
if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1)
{
    die('Hacking attempt...');
}
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';


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
                    'post_action': "index.php?page=items&type=duo_check&"+data[0].csrfp_token+"="+data[0].csrfp_key
                });

                $("#duo_login").val($("#login").val());
            }
        },
        "json"
    );
});
//]]>
</script>