<?php
/**
 * @file          items.logs.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2018 Nils Laumaillé
 * @licensing     GNU GPL-3.0
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

require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
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
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

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
        case "log_action_on_item":
            // Check KEY and rights
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                "decode"
            );

            logItems(
                filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT),
                filter_var(htmlspecialchars_decode($dataReceived['label']), FILTER_SANITIZE_STRING),
                filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT),
                filter_var(htmlspecialchars_decode($dataReceived['action']), FILTER_SANITIZE_STRING),
                filter_var(htmlspecialchars_decode($dataReceived['login']), FILTER_SANITIZE_STRING)
            );
            break;
    }
}
