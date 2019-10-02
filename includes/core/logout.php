<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      logout.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2019 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */


require_once '../../sources/SecureHandler.php';
session_name('teampass_session');
session_start();

// Update table by deleting ID
if (isset($_SESSION['user_id']) === true && empty($_SESSION['user_id']) === false) {
    $user_id = $_SESSION['user_id'];
} elseif (isset($_GET['user_id']) === true && empty($_GET['user_id']) === false) {
    $user_id = $_GET['user_id'];
} else {
    $user_id = '';
}

if (empty($user_id) === false && isset($_SESSION['CPM']) === true) {
    // connect to the server
    include_once '../../sources/main.functions.php';
    include_once '../../includes/config/settings.php';
    include_once '../../includes/libraries/Database/Meekrodb/db.class.php';
    if (defined('DB_PASSWD_CLEAR') === false) {
        define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
    }
    DB::$host = DB_HOST;
    DB::$user = DB_USER;
    DB::$password = DB_PASSWD_CLEAR;
    DB::$dbName = DB_NAME;
    DB::$port = DB_PORT;
    DB::$encoding = DB_ENCODING;

    // clear in db
    DB::update(
        DB_PREFIX.'users',
        array(
            'key_tempo' => '',
            'timestamp' => '',
            'session_end' => '',
        ),
        'id=%i',
        $user_id
    );
    //Log into DB the user's disconnection
    if (isset($SETTINGS['log_connections']) === true
        && (int) $SETTINGS['log_connections'] === 1
    ) {
        include_once '../../sources/main.functions.php';
        logEvents($SETTINGS, 'user_connection', 'disconnection', $user_id, @$_SESSION['login']);
    }
}

// erase session table
session_destroy();
$_SESSION = array();

require_once '../../sources/SecureHandler.php';
session_name('teampass_session');
session_start();
$_SESSION['CPM'] = 1;

// Get CSRFP code
$csrfp_array = include '../../includes/libraries/csrfp/libs/csrfp.config.php';

echo '
    <script type="text/javascript" src="../../plugins/store.js/dist/store.everything.min.js"></script>
    <script language="javascript" type="text/javascript">
    <!--
        // Clear localstorage
        store.remove("teampassApplication");
        store.remove("teampassSettings");
        store.remove("teampassUser");
        store.remove("teampassItem");
        sessionStorage.clear();
        
        setTimeout(function() {
            document.location.href="../../index.php"
        }, 1);
    -->
    </script>';
