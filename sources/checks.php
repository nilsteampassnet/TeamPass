<?php
/**
 *
 * @file          checks.php
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

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    require_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    require_once './includes/config/tp.config.php';
} elseif (file_exists('../../includes/config/tp.config.php')) {
    require_once '../../includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';

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
    global $SETTINGS;

    // Load libraries
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
    $superGlobal = new protect\SuperGlobal\SuperGlobal();

    // Parse the url
    parse_str(
        substr(
            (string) $superGlobal->get("REQUEST_URI", "SERVER"),
            strpos((string) $superGlobal->get("REQUEST_URI", "SERVER"), "?") + 1
        ),
        $result
    );
    return $result['page'];
}

function checkUser($userId, $userKey, $pageVisited)
{
    global $pagesRights, $SETTINGS;
    global $server, $user, $pass, $database, $pre, $port, $encoding;

    // Load libraries
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
    $superGlobal = new protect\SuperGlobal\SuperGlobal();

    if (empty($userId) || empty($pageVisited) || empty($userKey)) {
        return false;
    }

    if (!is_array($pageVisited)) {
        $pageVisited = array($pageVisited);
    }

    require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$superGlobal->get("user_language", "SESSION").'.php';
    require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';
    require_once 'main.functions.php';

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
    if (empty($data['admin']) === true
        && empty($data['gestionnaire'] === true)
        && !IsInArray($pageVisited, $pagesRights['user'])
    ) {
        return false;
    } elseif (empty($data['admin']) === true
        && empty($data['gestionnaire']) === false
        && !IsInArray($pageVisited, $pagesRights['manager'])
    ) {
        return false;
    } elseif (empty($data['admin']) === false
        && !IsInArray($pageVisited, $pagesRights['admin'])
    ) {
        return false;
    }

    return true;
}

function IsInArray($pages, $table)
{
    foreach ($pages as $page) {
        if (in_array($page, $table)) {
            return true;
        }
    }
    return false;
}
