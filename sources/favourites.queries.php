<?php
/**
 * @file          favourites.queries.php
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

require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
include $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset==utf-8");

// connect to DB
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
$post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

// manage action required
if (null !== $post_type) {
    switch ($post_type) {
        #CASE adding a new function
        case "del_fav":
            //Get actual favourites
            $data = DB::queryfirstrow("SELECT favourites FROM ".prefix_table("users")." WHERE id = %i", $_SESSION['user_id']);
            $tmp = explode(";", $data['favourites']);
            $favs = "";
            $tab_favs = array();
            //redefine new list of favourites
            foreach ($tmp as $favorite) {
                if (!empty($favorite) && $favorite != $post_id) {
                    if (empty($favs)) {
                        $favs = $favorite;
                    } else {
                        $favs = ';'.$favorite;
                    }
                    array_push($tab_favs, $favorite);
                }
            }
            //update user's account
            DB::update(
                prefix_table("users"),
                array(
                    'favourites' => $favs
                ),
                "id = %i",
                $_SESSION['user_id']
            );
            //update session
            $_SESSION['favourites'] = $tab_favs;
            break;
    }
}
