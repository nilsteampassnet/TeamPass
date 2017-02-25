<?php
/**
 * @file          favourites.queries.php
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

require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset==utf-8");

// connect to DB
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

// manage action required
if (!empty($_POST['type'])) {
    switch ($_POST['type']) {
        #CASE adding a new function
        case "del_fav":
            //Get actual favourites
            $data = DB::queryfirstrow("SELECT favourites FROM ".prefix_table("users")." WHERE id = %i", $_SESSION['user_id']);
            $tmp = explode(";", $data['favourites']);
            $favs = "";
            $tab_favs = array();
            //redefine new list of favourites
            foreach ($tmp as $f) {
                if (!empty($f) && $f != $_POST['id']) {
                    if (empty($favs)) {
                        $favs = $f;
                    } else {
                        $favs = ';'.$f;
                    }
                    array_push($tab_favs, $f);
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
