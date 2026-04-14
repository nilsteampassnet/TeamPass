<?php
// DATABASE connexion parameters
define("DB_HOST", "localhost");
define("DB_USER", "root");
//define("DB_PASSWD", "test1");
define("DB_PASSWD", "def50200ed7e9507e18624d61baada64faab0195b709b195640d8f34cd5d94144b0e55a54c1568396369f9f0b0f4b8626cfc73f0cf5273bb4946ad272f40f27b69ae3a4052f934887f5085768989d3a85304fa6212593a7234");
define("DB_NAME", "teampass_3200");
define("DB_PREFIX", "teampass_");
define("DB_PORT", "");
define("DB_ENCODING", "utf8mb4");
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

// SECUREPATH is now defined in include.php as TEAMPASS_ROOT/secrets
define("SECUREFILE", "P2YLLfga5BDujvBtet5TTA6L48sWFcu8DXQs3fvG");

if (isset($session) === true && $session->has('system-timezone') && null !== $session->get('system-timezone')) {
    date_default_timezone_set($session->get('system-timezone'));
}
