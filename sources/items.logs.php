<?php
/**
 * @file          items.logs.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once 'SecureHandler.php';
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

require_once $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
require_once 'main.functions.php';

//Class loader
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

// Connect to mysql server
require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = 'db_error_handler';
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);

// Check KEY and rights
if (!isset($_POST['key']) || $_POST['key'] != $_SESSION['key']) {
    echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
    exit();
}

// Do asked action
if (isset($_POST['type'])) {
    switch ($_POST['type']) {
        /*
        * CASE
        * log if item's password is shown
        */
        case "item_password_shown":
            if (isset($_SESSION['settings']['log_accessed']) && $_SESSION['settings']['log_accessed'] == 1) {
                DB::insert(
                    prefix_table("log_items"),
                    array(
                        'id_item' => $_POST['id_item'],
                        'date' => time(),
                        'id_user' => $_SESSION['user_id'],
                        'action' => 'at_password_shown'
                   )
                );

                // SysLog
                if (isset($_SESSION['settings']['syslog_enable']) && $_SESSION['settings']['syslog_enable'] == 1) {
                    send_syslog("The password of Item #".$_POST['id_item']." was shown to ".$_SESSION['login'].".","teampass","php",$_SESSION['settings']['syslog_host'],$_SESSION['settings']['syslog_port']);
                }
            }

            break;
        /*
        * CASE
        * log if item's password is copied
        */
        case "item_password_copied":
            if (isset($_SESSION['settings']['log_accessed']) && $_SESSION['settings']['log_accessed'] == 1) {
                DB::insert(
                    prefix_table("log_items"),
                    array(
                        'id_item' => $_POST['id_item'],
                        'date' => time(),
                        'id_user' => $_SESSION['user_id'],
                        'action' => 'at_password_copied'
                   )
                );

                // SysLog
                if (isset($_SESSION['settings']['syslog_enable']) && $_SESSION['settings']['syslog_enable'] == 1) {
                    send_syslog("The password of Item #".$_POST['id_item']." was copied to clipboard by ".$_SESSION['login'].".","teampass","php",$_SESSION['settings']['syslog_host'],$_SESSION['settings']['syslog_port']);
                }
            }

            break;
    }
}