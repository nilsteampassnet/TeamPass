<?php
/**
 * Proxy wrapper — forwards requests to the private app layer.
 * login.oauth2.php must be HTTP-accessible (browser redirect to the OAuth2
 * provider), so a proxy is required.
 */
if (!defined('TEAMPASS_ROOT')) {
    define('TEAMPASS_ROOT', dirname(__DIR__, 3));
}
require_once TEAMPASS_ROOT . '/app/core/login.oauth2.php';