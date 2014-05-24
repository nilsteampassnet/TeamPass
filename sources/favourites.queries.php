<?php
/**
 * @file          favourites.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.19
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('sessions.php');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
header("Content-type: text/html; charset==utf-8");

// connect to DB
$db = new SplClassLoader('Database\Core', '../includes/libraries');
$db->register();
$db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
$db->connect();

// Construction de la requ?te en fonction du type de valeur
if (!empty($_POST['type'])) {
    switch ($_POST['type']) {
        #CASE adding a new function
        case "del_fav":
            //Get actual favourites
            //$data = $db->fetchRow("SELECT favourites FROM ".$pre."users WHERE id = '".$_SESSION['user_id']."'");
            $data = $db->queryGetRow(
                "users",
                array(
                    "favourites"
                ),
                array(
                    "id" => intval($_SESSION['user_id'])
                )
            );
            $tmp = explode(";", $data[0]);
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
            $db->queryUpdate(
                "users",
                array(
                    'favourites' => $favs
               ),
                "id = '".$_SESSION['user_id']."'"
            );
            //update session
            $_SESSION['favourites'] = $tab_favs;
            break;
    }
}
