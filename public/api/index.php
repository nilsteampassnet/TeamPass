<?php
/**
 * Proxy wrapper — forwards API requests to the private app layer.
 * The web root is public/; the actual API entry point lives in app/api/.
 */
if (!defined('TEAMPASS_ROOT')) {
    define('TEAMPASS_ROOT', realpath(__DIR__ . '/../..'));
}

require_once TEAMPASS_ROOT . '/app/api/index.php';
