<?php
/**
 * 
 */

// DATABASE connexion parameters
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWD', 'def502000fa7aee3b01f501f0249e46dec3563df9403aa3e800c09646187e1d630cc93fb4251c2192da379c12b4eefef9641ed35691a46f5c693bdbcde60221514b0011b8ce820fa08a77f1ad3c5fb85f0a8e232');
define('DB_NAME', 'test');
define('DB_PREFIX', 'teampass_');
define('DB_PORT', '3307');
define('DB_ENCODING', '');

@date_default_timezone_set($_SESSION['settings']['timezone']);
@define('SECUREPATH', 'D:/wamp64/tmp/test');
if (file_exists("D:/wamp64/tmp/test/sk.php")) {
    include_once "D:/wamp64/tmp/test/sk.php";
}
