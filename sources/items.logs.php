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

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    require_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    require_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
include $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
require_once 'main.functions.php';

//Class loader
require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

// Connect to mysql server
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
$pass = defuse_return_decrypted($pass);
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = true;
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
$post_id_item = filter_input(INPUT_POST, 'id_item', FILTER_SANITIZE_NUMBER_INT);

// Check KEY and rights
if (null === $post_key
    || $post_key != $_SESSION['key']
) {
    echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
    exit();
}

// Do asked action
if (null !== $post_type) {
    switch ($post_type) {
        /*
        * CASE
        * log if item's password is shown
        */
        case "item_password_shown":
            if (isset($SETTINGS['log_accessed']) && $SETTINGS['log_accessed'] == 1) {
                DB::insert(
                    prefix_table("log_items"),
                    array(
                        'id_item' => $post_id_item,
                        'date' => time(),
                        'id_user' => $_SESSION['user_id'],
                        'action' => 'at_password_shown'
                    )
                );

                // SysLog
                if (isset($SETTINGS['syslog_enable']) && $SETTINGS['syslog_enable'] == 1) {
                    send_syslog(
                        "The password of Item #".$post_id_item." was shown to ".$_SESSION['login'].".",
                        $SETTINGS['syslog_host'],
                        $SETTINGS['syslog_port'],
                        "teampass"
                    );
                }
            }

            break;
        /*
        * CASE
        * log if item's password is copied
        */
        case "item_password_copied":
            if (isset($SETTINGS['log_accessed']) && $SETTINGS['log_accessed'] == 1) {
                DB::insert(
                    prefix_table("log_items"),
                    array(
                        'id_item' => $post_id_item,
                        'date' => time(),
                        'id_user' => $_SESSION['user_id'],
                        'action' => 'at_password_copied'
                    )
                );

                // SysLog
                if (isset($SETTINGS['syslog_enable']) && $SETTINGS['syslog_enable'] == 1) {
                    send_syslog(
                        "The password of Item #".$post_id_item." was copied to clipboard by ".$_SESSION['login'].".",
                        $SETTINGS['syslog_host'],
                        $SETTINGS['syslog_port'],
                        "teampass"
                    );
                }
            }

            break;
    }
}
