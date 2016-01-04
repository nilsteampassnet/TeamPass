<?php
/**
 * Configuration file for CSRF Protector z
 */
 
if (!isset($_SESSION['settings']['cpassman_url']) || $_SESSION['settings']['cpassman_url'] == "") {
	$tp_url = $_SERVER['REQUEST_URI'];
} else {
	$tp_url = $_SESSION['settings']['cpassman_url'];
}
return array(
	"CSRFP_TOKEN" => "",
	"logDirectory" => "../log",
	"failedAuthAction" => array(
		"GET" => 0,
		"POST" => 0),
	"errorRedirectionPage" => "",
	"customErrorMessage" => "",
	"jsPath" => "../js/csrfprotector.js",
	"jsUrl" => $tp_url."/includes/libraries/csrfp/js/csrfprotector.js",
	"tokenLength" => 25,
	"disabledJavascriptMessage" => "This site attempts to protect users against <a href=\"https://www.owasp.org/index.php/Cross-Site_Request_Forgery_%28CSRF%29\">
	Cross-Site Request Forgeries </a> attacks. In order to do so, you must have JavaScript enabled in your web browser otherwise this site will fail to work correctly for you.
	 See details of your web browser for how to enable JavaScript.",
	 "verifyGetFor" => array()
);
