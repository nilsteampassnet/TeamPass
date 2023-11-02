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
	"errorRedirectionPage" => "",
	"customErrorMessage" => "",
	"cookieConfig" => array(
		"path" => '',
		"domain" => '',
		"secure" => false,
		"expire" => '',
	),
    "disabledJavascriptMessage" => "sample error message",
	"verifyGetFor" => array()
);
