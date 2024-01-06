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
define("SALT", "");

if ($session->has('system-timezone') && null !== $session->get('system-timezone')) {
    date_default_timezone_set($session->get('system-timezone'));
}


// DUO
@define('COST', '13'); // Don't change this.
@define('AKEY', '');
@define('IKEY', '');
@define('SKEY', '');
@define('HOST', '');