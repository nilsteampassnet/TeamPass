#!/usr/bin/php
<?php
class SimpleTest {
    public function assert($boolean) {
        if (! $boolean)
            $this->fail ();
    }
    protected function fail($msg = '') {
        echo "FAILURE! $msg\n";
        debug_print_backtrace ();
        die ();
    }
}
function microtime_float() {
    list ( $usec, $sec ) = explode ( " ", microtime () );
    return (( float ) $usec + ( float ) $sec);
}

if (phpversion () >= '5.3')
    $is_php_53 = true;
else
    $is_php_53 = false;

ini_set ( 'date.timezone', 'America/Los_Angeles' );

error_reporting ( E_ALL | E_STRICT );
require_once '../db.class.php';
include 'test_setup.php'; // test config values go here
DB::$user = $set_db_user;
DB::$password = $set_password;
DB::$dbName = $set_db;
DB::$host = $set_host;
DB::get (); // connect to mysql

require_once 'BasicTest.php';
require_once 'CallTest.php';
require_once 'ObjectTest.php';
require_once 'WhereClauseTest.php';
require_once 'ErrorTest.php';
require_once 'TransactionTest.php';
require_once 'HelperTest.php';

$classes_to_test = array (
        'BasicTest',
        'CallTest',
        'WhereClauseTest',
        'ObjectTest',
        'ErrorTest',
        'TransactionTest',
        'HelperTest'
);

if ($is_php_53) {
    require_once 'ErrorTest_53.php';
    $classes_to_test [] = 'ErrorTest_53';
} else {
    echo "PHP 5.3 not detected, skipping 5.3 tests..\n";
}

$mysql_version = DB::serverVersion ();
if ($mysql_version >= '5.5') {
    require_once 'TransactionTest_55.php';
    $classes_to_test [] = 'TransactionTest_55';
} else {
    echo "MySQL 5.5 not available (version is $mysql_version) -- skipping MySQL 5.5 tests\n";
}

$time_start = microtime_float ();
foreach ( $classes_to_test as $class ) {
    $object = new $class ();

    foreach ( get_class_methods ( $object ) as $method ) {
        if (substr ( $method, 0, 4 ) != 'test')
            continue;
        echo "Running $class::$method..\n";
        $object->$method ();
    }
}
$time_end = microtime_float ();
$time = round ( $time_end - $time_start, 2 );

echo "Completed in $time seconds\n";

?>
