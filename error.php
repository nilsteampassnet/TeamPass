<?php
/**
 * @file          error.php
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


if (file_exists('../sources/SecureHandler.php')) {
    require_once '../sources/SecureHandler.php';
} elseif (file_exists('./sources/SecureHandler.php')) {
    require_once './sources/SecureHandler.php';
} else {
    throw new Exception("Error file '/sources/SecureHandler.php' not exists", 1);
}
if (!isset($_SESSION)) {
    session_start();
}
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
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

if (null !== filter_input(INPUT_POST, 'session', FILTER_SANITIZE_STRING)
    && filter_input(INPUT_POST, 'session', FILTER_SANITIZE_STRING) === "expired"
) {
    //Include files
    require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
    require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
    require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

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
    if (isset($SETTINGS['log_connections']) && $SETTINGS['log_connections'] == 1) {
        logEvents('user_connection', 'disconnection', $_SESSION['user_id'], $_SESSION['login']);
    }
} else {
    require_once $SETTINGS['cpassman_dir'].'/includes/language/english.php';
    echo '
    <div style="width:800px;margin:auto;">
        <div class="ui-state-error ui-corner-all error" style="margin-top:60px; padding:15px; text-align:center; font-size:16px;" >
            <i class="fa fa-warning fa-2x"></i><br /><br />';

    if (@$_SESSION['error']['code'] === ERR_NOT_ALLOWED) {
        echo $LANG['error_not_authorized'];
    } elseif (@$_SESSION['error']['code'] === ERR_NOT_EXIST) {
        echo $LANG['error_not_exists'];
    } elseif (@$_SESSION['error']['code'] === ERR_SESS_EXPIRED) {
        echo $LANG['index_session_expired'];
    } elseif (@$_SESSION['error']['code'] === ERR_VALID_SESSION) {
        echo $LANG['error_not_authorized'];
    }
    echo '
            <br /><br /><a href="index.php" />'.$LANG['home'].'</a>
        </div>';
}

// erase session table
$_SESSION = array();

// Kill session
session_destroy();

echo '
</div>';
