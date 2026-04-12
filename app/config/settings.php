<?php
// DATABASE connexion parameters
define("DB_HOST", "localhost");
define("DB_USER", "root");
define("DB_PASSWD", "def5020072e65aaa2e6f02a8f7e32a6e09670e2c26b01b5962ba88c32e7f8eb79442a27b64e01963fb6ba0d3224e16275e6a045de834144be903e052543f6a2e403039f12d6f04ae3525f964ba384610b4517e1b58786baf28");
define("DB_NAME", "teampass_3200");
define("DB_PREFIX", "teampass_");
define("DB_PORT", "3306");
define("DB_ENCODING", "utf8mb4");
define("DB_SSL", array(
    "key" => "",
    "cert" => "",
    "ca_cert" => "",
    "ca_path" => "",
    "cipher" => ""
));
define("DB_CONNECT_OPTIONS", array(
    MYSQLI_OPT_CONNECT_TIMEOUT => 10
));
// SECUREPATH is now a constant defined in app/config/include.php (TEAMPASS_ROOT/secrets)
define("SECUREFILE", "P2YLLfga5BDujvBtet5TTA6L48sWFcu8DXQs3fvG");
define("IKEY", "");
define("SKEY", "");
define("HOST", "");

if (isset($session) === true && $session->has('system-timezone') && null !== $session->get('system-timezone')) {
    date_default_timezone_set($_SESSION['settings']['timezone']);
}
