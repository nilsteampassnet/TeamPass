<?php
// DATABASE connexion parameters
define("DB_HOST", "");
define("DB_USER", "");
define("DB_PASSWD", "");
define("DB_NAME", "");
define("DB_PREFIX", "");
define("DB_PORT", "");
define("DB_ENCODING", "");
define("SECUREPATH", "");

if (isset($_SESSION['settings']['timezone']) === true) {
    date_default_timezone_set($_SESSION['settings']['timezone']);
}
