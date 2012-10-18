<?php
/**
 * @file 		favourites.queries.php
 * @author		Nils Laumaillé
 * @version 	2.1.8
 * @copyright 	(c) 2009-2011 Nils Laumaillé
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

session_start();
if (!isset($_SESSION['CPM'] ) || $_SESSION['CPM'] != 1)
    die('Hacking attempt...');

include '../includes/language/'.$_SESSION['user_language'].'.php');
include '../includes/settings.php';
header("Content-type: text/html; charset==utf-8");

// connect to the server
require_once 'class.database.php';
$db = new Database($server, $user, $pass, $database, $pre);
$db->connect();

// Construction de la requ?te en fonction du type de valeur
if ( !empty($_POST['type']) ) {
    switch ($_POST['type']) {
        #CASE adding a new function
        case "del_fav":
            //Get actual favourites
            $data = $db->fetch_row("SELECT favourites FROM ".$pre."users WHERE id = '".$_SESSION['user_id']."'");
            $tmp = explode(";",$data[0]);
            $favs = "";
            $tab_favs = array();
            //redefine new list of favourites
            foreach ($tmp as $f) {
                if (!empty($f) && $f != $_POST['id']) {
                    if ( empty($favs) ) $favs = $f;
                    else $favs = ';'.$f;
                    array_push($tab_favs,$f);
                }
            }
            //update user's account
            $db->query_update(
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
