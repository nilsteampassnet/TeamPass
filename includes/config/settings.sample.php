<?php
// DATABASE connexion parameters
define("DB_HOST", "");
define("DB_USER", "");
define("DB_PASSWD", "");
define("DB_NAME", "");
define("DB_PREFIX", "");
define("DB_PORT", "");
define("DB_ENCODING", "utf8");
define("DB_SSL", false); // if DB over SSL then comment this line
// if DB over SSL then uncomment the following lines
//define("DB_SSL", array(
//    "key" => "",
//    "cert" => "",
//    "ca_cert" => "",
//    "ca_path" => "",
//    "cipher" => ""
//));
define("DB_CONNECT_OPTIONS", array(
    MYSQLI_OPT_CONNECT_TIMEOUT => 10
));
define("IKEY", "");
define("SKEY", "");
define("HOST", "");

if (isset($session) === true && $session->has('system-timezone') && null !== $session->get('system-timezone')) {
    date_default_timezone_set($session->get('system-timezone'));
}
