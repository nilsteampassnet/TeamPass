<?php
/**
 * Proxy wrapper — forwards requests to the private app layer.
 * The web root is public/; the actual source files live in app/sources/.
 *
 * Public, unauthenticated endpoint: it consumes the one-time unlock code emailed when an
 * account is locked by the anti-bruteforce protection. Possession of that code is the only
 * credential it accepts.
 */
if (!defined("TEAMPASS_ROOT")) {
    define("TEAMPASS_ROOT", realpath(__DIR__ . '/..'));
}
if (!defined("TEAMPASS_APP")) {
    define("TEAMPASS_APP", TEAMPASS_ROOT . '/app');
}
if (!defined("TEAMPASS_STORAGE")) {
    define("TEAMPASS_STORAGE", TEAMPASS_ROOT . '/storage');
}

require_once TEAMPASS_ROOT . "/app/sources/self-unlock.php";
