<?php
/**
 *
 * @file          checks.php
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

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    include_once './includes/config/tp.config.php';
} elseif (file_exists('../../includes/config/tp.config.php')) {
    include_once '../../includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';

$pagesRights = array(
    "user" => array(
        "home", "items", "find", "kb", "favourites", "suggestion", "folders", "profile"
    ),
    "manager" => array(
        "home", "items", "find", "kb", "favourites", "suggestion", "folders", "manage_roles", "manage_folders",
        "manage_views", "manage_users"
    ),
    "human_resources" => array(
        "home", "items", "find", "kb", "favourites", "suggestion", "folders", "manage_roles", "manage_folders",
        "manage_views", "manage_users"
    ),
    "admin" => array(
        "home", "items", "find", "kb", "favourites", "suggestion", "folders", "manage_roles", "manage_folders",
        "manage_views", "manage_users", "manage_settings", "manage_main"
    )
);

/*
Handle CASES
 */
switch (filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING)) {
case "checkSessionExists":
    // Case permit to check if SESSION is still valid
    session_start();
    if (isset($_SESSION['CPM']) === true) {
        echo "1";
    } else {
        // In case that no session is available
        // Force the page to be reloaded and attach the CSRFP info

        // Load CSRFP
        $csrfp_array = include '../includes/libraries/csrfp/libs/csrfp.config.php';

        // Send back CSRFP info
        echo $csrfp_array['CSRFP_TOKEN'].";".filter_input(INPUT_POST, $csrfp_array['CSRFP_TOKEN'], FILTER_SANITIZE_STRING);
    }

    break;
}

/**
 * Returns the page the user is visiting
 * @return string The page name
 */
function curPage()
{
    global $SETTINGS;

    // Load libraries
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
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

/**
 * Checks if user is allowed to open the page
 * @param  integer $userId      User's ID
 * @param  integer $userKey     User's temporary key
 * @param  String $pageVisited  Page visited
 * @return Boolean              False/True
 */
function checkUser($userId, $userKey, $pageVisited)
{
    global $pagesRights, $SETTINGS;
    global $server, $user, $pass, $database, $port, $encoding;

    // Load libraries
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
    $superGlobal = new protect\SuperGlobal\SuperGlobal();

    if (empty($userId) === true || empty($pageVisited) === true || empty($userKey) === true) {
        return false;
    }

    if (is_array($pageVisited) === false) {
        $pageVisited = array($pageVisited);
    }

    // Securize language
    if (null === $superGlobal->get("user_language", "SESSION")
        || empty($superGlobal->get("user_language", "SESSION")) === true
    ) {
        $superGlobal->put("user_language", "english", "SESSION");
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
        "SELECT login, key_tempo, admin, gestionnaire, can_manage_all_users FROM ".prefix_table("users")." WHERE id = %i",
        $userId
    );

    // check if user exists and tempo key is coherant
    if (empty($data['login']) === true || empty($data['key_tempo']) === true || $data['key_tempo'] !== $userKey) {
        return false;
    }

    // check if user is allowed to see this page
    if ($data['admin'] !== '1'
        && $data['gestionnaire'] !== '1'
        && $data['can_manage_all_users'] !== '1'
        && IsInArray($pageVisited, $pagesRights['user']) === true
    ) {
        return true;
    } elseif ($data['admin'] !== '1'
        && ($data['gestionnaire'] === '1' || $data['can_manage_all_users'] === '1')
        && IsInArray($pageVisited, $pagesRights['manager']) === true
    ) {
        return true;
    } elseif ($data['admin'] === '1'
        && IsInArray($pageVisited, $pagesRights['admin']) === true
    ) {
        return true;
    }

    return false;
}

/**
 * Permits to check if at least one input is in array
 * @param array $pages  Input
 * @param array $table  Checked against this array
 */
function IsInArray($pages, $table)
{
    foreach ($pages as $page) {
        if (in_array($page, $table) === true) {
            return true;
        }
    }
    return false;
}
