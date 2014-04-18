<?php
/**
 *
 * @file          checks.php
 * @author        Nils Laumaillé
 * @version       2.1.20
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link		  http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

$pagesRights = array(
    "user" => array(
        "home", "items", "find", "kb", "favourites"
    ),
    "manager" => array(
        "home", "items", "find", "kb", "favourites", "manage_roles", "manage_folders", "manage_views", "manage_users"
    ),
    "admin" => array(
        "home", "items", "find", "kb", "favourites", "manage_roles", "manage_folders", "manage_views", "manage_users", "manage_settings", "manage_main"
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

    include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
    require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

    // Connect to mysql server
    $db = new SplClassLoader('Database\Core', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
    $db->register();
    $db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
    $db->connect();

    // load user's data
    /*$sql = "SELECT * FROM ".$pre."users WHERE id = '$userId'";
    $row = $db->query($sql);
    $data = $db->fetchArray($row);*/
    $data = $db->queryGetArray(
        "users",
        array(
            "login",
            "key_tempo",
            "admin",
            "gestionnaire"
        ),
        array(
            "id" => intval($userId)
        )
    );

    // check if user exists and tempo key is coherant
    if (empty($data['login']) || empty($data['key_tempo']) || $data['key_tempo'] != $userKey) {
        return false;
    }

    // check if user is allowed to see this page
    if (empty($data['admin']) && empty($data['gestionnaire']) && !in_array($pageVisited, $pagesRights['user'])) {
        return false;
    } else if (empty($data['admin']) && !empty($data['gestionnaire']) && !in_array($pageVisited, $pagesRights['manager'])) {
        return false;
    } else if (!empty($data['admin']) && !in_array($pageVisited, $pagesRights['admin'])) {
        return false;
    }

    return true;
}