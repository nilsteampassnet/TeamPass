<?php
/**
 * Configuration file for CSRF Protector z
 */

return array(
   "CSRFP_TOKEN" => "c42fba0fc254a656bedec4aa883f713a0403c1d0dcbf4dc4ee",
   "logDirectory" => "../log",
   "failedAuthAction" => array(
      "GET" => 0,
      "POST" => 0),
   "errorRedirectionPage" => "",
   "customErrorMessage" => "",
   "jsPath" => "../js/csrfprotector.js",
   "jsUrl" => "http://localhost:8000/includes/libraries/csrfp/js/csrfprotector.js",
   "tokenLength" => 50,
   "disabledJavascriptMessage" => "This site attempts to protect users against <a href=\"https://www.owasp.org/index.php/Cross-Site_Request_Forgery_%28CSRF%29\">
   Cross-Site Request Forgeries </a> attacks. In order to do so, you must have JavaScript enabled in your web browser otherwise this site will fail to work correctly for you.
    See details of your web browser for how to enable JavaScript.",
    "verifyGetFor" => array("*page=items&type=duo_check*")
);
