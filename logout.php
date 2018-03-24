<?php
/**
 *
 * @file          logout.php
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


require_once 'sources/SecureHandler.php';
session_start();

// Update table by deleting ID
if (isset($_SESSION['user_id']) && empty($_SESSION['user_id']) === false) {
    $user_id = $_SESSION['user_id'];
} elseif (isset($_GET['user_id']) && empty($_GET['user_id']) === false) {
    $user_id = $_GET['user_id'];
} else {
    $user_id = "";
}

if (empty($user_id) === false && isset($_SESSION['CPM']) === true) {
    // connect to the server
    require_once './sources/main.functions.php';
    require_once './includes/config/settings.php';
    require_once './includes/libraries/Database/Meekrodb/db.class.php';
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

    // clear in db
    DB::update(
        $pre."users",
        array(
            'key_tempo' => '',
            'timestamp' => '',
            'session_end' => ''
        ),
        "id=%i",
        $user_id
    );
    //Log into DB the user's disconnection
    if (isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] == 1) {
        require_once 'sources/main.functions.php';
        logEvents('user_connection', 'disconnection', $user_id, @$_SESSION['login']);
    }
}

// erase session table
session_unset();
session_destroy();
$_SESSION = array();

echo '
    <script language="javascript" type="text/javascript">
    <!--
        sessionStorage.clear();
        setTimeout(function(){document.location.href="index.php"}, 1);
    -->
    </script>';
