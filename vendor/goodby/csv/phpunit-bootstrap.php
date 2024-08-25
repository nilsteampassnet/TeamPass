<?php
// For composer
require_once 'vendor/autoload.php';

/**
 * You can overwrite GOODBY_CSV_TEST_DB_* by `export` command.
 * @see http://manpages.ubuntu.com/manpages/hardy/man5/exports.5.html
 *
 * Example:
 *
 * ```
 * export GOODBY_CSV_TEST_DB_USER=alice
 * export GOODBY_CSV_TEST_DB_PASS=passwd
 * phpunit
 * ```
 */
if ( isset($_SERVER['GOODBY_CSV_TEST_DB_HOST']) === false ) {
    $_SERVER['GOODBY_CSV_TEST_DB_HOST'] = $_SERVER['GOODBY_CSV_TEST_DB_HOST_DEFAULT'];
}
if ( isset($_SERVER['GOODBY_CSV_TEST_DB_NAME']) === false ) {
    $_SERVER['GOODBY_CSV_TEST_DB_NAME'] = $_SERVER['GOODBY_CSV_TEST_DB_NAME_DEFAULT'];
}
if ( isset($_SERVER['GOODBY_CSV_TEST_DB_USER']) === false ) {
    $_SERVER['GOODBY_CSV_TEST_DB_USER'] = $_SERVER['GOODBY_CSV_TEST_DB_USER_DEFAULT'];
}
if ( isset($_SERVER['GOODBY_CSV_TEST_DB_PASS']) === false ) {
    $_SERVER['GOODBY_CSV_TEST_DB_PASS'] = $_SERVER['GOODBY_CSV_TEST_DB_PASS_DEFAULT'];
}
