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
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1)
{
    die('Hacking attempt...');
}
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
?>


<script type="text/javascript">
$(function() {
	$.post(
		"sources/identify.php",
		{
			type : "identify_duo_user",
			login: sanitizeString($("#login").val())
		},
		function(data) {
			var cssLink = $("<link rel='stylesheet' type='text/css' href='./includes/libraries/Authentication/DuoSecurity/Duo-Frame.css'>");
	    	$("head").append(cssLink);

	$.getScript("./includes/libraries/Authentication/DuoSecurity/js/Duo-Web-v2.js");

		    //$('#duo_iframe').attr("data-host", "<?php echo HOST; ?>");
		    //$('#duo_iframe').attr("data-sig-request", data[0].sig_request);
		    //console.log('<iframe id="duo_iframe" frameborder="0" data-host="<?php echo HOST; ?>" data-sig-request="'+data[0].sig_request+'"></iframe>');
		    $("#div_duo").html('<iframe id="duo_iframe" frameborder="0" data-host="<?php echo HOST; ?>" data-sig-request="'+data[0].sig_request+'"></iframe>');

		    Duo.init({
                'host': '<?php echo HOST; ?>',
                'sig_request': data[0].sig_request,
                'post_action' : 'https://localhost/teampass/index.php?duo_status=valid&sig_response='
              });

		    /*var ifr=$('<iframe/>', {
	            id: 'duo_iframe',
	            load:function(){
	                $(this).show();
	                Duo.init({
	                    'host': '<?php echo HOST; ?>',
	                    'sig_request': data[0].sig_request
	                  });
	            }
	        });
	        $('#div_duo').html(ifr);*/


			/*if (data[0].value != "") {

				$.post(
						"sources/identify.php",
						{
							type : "identify_duo_user_check",
							sig_response : data[0].value,
							login: sanitizeString($("#login").val())
						},
						function(data) {
							console.log(data[0].value);
						},
						"json"
								);

			}*/
		},
		"json"
	);
});
</script>