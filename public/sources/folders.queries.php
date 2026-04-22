<?php
/**
 * Proxy wrapper — forwards requests to the private app layer.
 * The web root is public/; the actual source files live in app/sources/.
 */
if (!defined("TEAMPASS_ROOT")) {
    define("TEAMPASS_ROOT", realpath(__DIR__ . '/../..'));
}

require_once TEAMPASS_ROOT . "/app/sources/folders.queries.php";
