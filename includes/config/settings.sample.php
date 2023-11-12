<?php
// DATABASE connexion parameters
define("DB_HOST", "");
define("DB_USER", "");
define("DB_PASSWD", "");
define("DB_NAME", "");
define("DB_PREFIX", "");
define("DB_PORT", "");
define("DB_ENCODING", "utf8");
define("DB_SSL", false);
define("DB_CONNECT_OPTIONS", array(
    MYSQLI_OPT_CONNECT_TIMEOUT => 10
));
define("SECUREPATH", "");
define("SECUREFILE", "");
define("IKEY", "");
define("SKEY", "");
define("HOST", "");

if (isset($_SESSION['settings']['timezone']) === true) {
    date_default_timezone_set($_SESSION['settings']['timezone']);
}
