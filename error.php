<?php
/**
 * @file          error.php
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

//require_once('sources/SecureHandler.php');
@session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

if (isset($_POST['session']) && $_POST['session'] == "expired") {
    //Include files
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
    require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

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

    // Include main functions used by TeamPass
    require_once 'sources/main.functions.php';

    // Update table by deleting ID
    if (isset($_SESSION['user_id'])) {
        DB::update(
            $pre."users",
            array(
                'key_tempo' => ''
            ),
            "id=%i",
            $_SESSION['user_id']
        );
    }

    //Log into DB the user's disconnection
    if (isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] == 1) {
        logEvents('user_connection', 'disconnection', $_SESSION['user_id'], $_SESSION['login']);
    }
} else {
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/english.php';
    echo '
    <div style="width:800px;margin:auto;">';
    if (@$_SESSION['error']['code'] == ERR_NOT_ALLOWED) {
        echo '
        <div class="ui-state-error ui-corner-all error" >'.$LANG['error_not_authorized'].'</div>';
    } elseif (@$_SESSION['error']['code'] == ERR_NOT_EXIST) {
        echo '
        <div class="ui-state-error ui-corner-all error" >'.$LANG['error_not_exists'].'</div>';
    } elseif (@$_SESSION['error']['code'] == ERR_SESS_EXPIRED) {
        echo '
        <div class="ui-state-error ui-corner-all error" style="text-align:center;" >'.$LANG['index_session_expired'].'<br /><br /><a href="index.php" />'.$LANG['home'] .'</a></div>';
    } elseif (@$_SESSION['error']['code'] == ERR_NO_MCRYPT) {
        echo '
        <div class="ui-state-error ui-corner-all error" style="text-align:center;" >'.$LANG['error_mcrypt_not_loaded'].'<br /><br /><a href="index.php" />'.$LANG['home'] .'</a></div>';
    } elseif (@$_SESSION['error']['code'] == ERR_VALID_SESSION) {
        echo '
        <div class="ui-state-error ui-corner-all error" style="text-align:center;" >'.$LANG['error_not_authorized'].'<br /><br /><a href="index.php" />'.$LANG['home'] .'</a></div>';
    }
}

// erase session table
$_SESSION = array();

// Kill session
session_destroy();

echo '
</div>';
