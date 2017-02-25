<?php
/**
 *
 * @file          checks.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link		  http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
require_once $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';

$pagesRights = array(
    "user" => array(
        "home", "items", "find", "kb", "favourites", "suggestion", "folders"
    ),
    "manager" => array(
        "home", "items", "find", "kb", "favourites", "suggestion", "folders", "manage_roles", "manage_folders", "manage_views", "manage_users"
    ),
    "admin" => array(
        "home", "items", "find", "kb", "favourites", "suggestion", "folders", "manage_roles", "manage_folders", "manage_views", "manage_users", "manage_settings", "manage_main"
    )
);

function curPage()
{
    parse_str(substr($_SERVER["REQUEST_URI"], strpos($_SERVER["REQUEST_URI"], "?")+1), $result);
    return $result['page'];
}

function checkUser($userId, $userKey, $pageVisited)
{
    global $pagesRights;

    if (empty($userId) || empty($pageVisited) || empty($userKey)) {
        return false;
    }

    if (!is_array($pageVisited)) {
        $pageVisited = array($pageVisited);
    }

    include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
    require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';
    require_once 'main.functions.php';

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

    // load user's data
    $data = DB::queryfirstrow(
        "SELECT login, key_tempo, admin, gestionnaire FROM ".prefix_table("users")." WHERE id = %i",
        $userId
    );

    // check if user exists and tempo key is coherant
    if (empty($data['login']) || empty($data['key_tempo']) || $data['key_tempo'] != $userKey) {
        return false;
    }

    // check if user is allowed to see this page
    if (empty($data['admin']) && empty($data['gestionnaire']) && !IsInArray($pageVisited, $pagesRights['user'])) {
        return false;
    } else if (empty($data['admin']) && !empty($data['gestionnaire']) && !IsInArray($pageVisited, $pagesRights['manager'])) {
        return false;
    } else if (!empty($data['admin']) && !IsInArray($pageVisited, $pagesRights['admin'])) {
        return false;
    }

    return true;
}

function IsInArray($pages, $table)
{
    foreach($pages as $page) {
        if (in_array($page, $table)) {
            return true;
        }
    }
    return false;
}
