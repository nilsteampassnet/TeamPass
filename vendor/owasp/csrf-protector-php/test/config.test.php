<?php
/**
 * Configuration file for CSRF Protector
 * Necessary configurations are (library would throw exception otherwise)
 * ---- failedAuthAction
 * ---- jsUrl
 * ---- tokenLength
 */
return array(
	"CSRFP_TOKEN" => "CSRFP-Token",
	"failedAuthAction" => array(
		"GET" => 0,
		"POST" => 0),
	"errorRedirectionPage" => "",
	"customErrorMessage" => "",
	"jsUrl" => "http://localhost/csrfp/js/csrfprotector.js",
	"tokenLength" => 10,
	"cookieConfig" => array(
		"path" => '',
		"domain" => '',
		"secure" => false,
		"expire" => '',
	),
    "disabledJavascriptMessage" => "sample error message",
	"verifyGetFor" => array()
);
