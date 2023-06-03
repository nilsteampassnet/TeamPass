<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 * @file      logout.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

require_once '../../sources/SecureHandler.php';
session_name('teampass_session');
session_start();

// Load superglobal library
require_once '../../includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();
$get = [];
$get['user_id'] = $superGlobal->get('user_id', 'GET');

// Update table by deleting ID
if (isset($_SESSION['user_id']) === true && empty($_SESSION['user_id']) === false) {
    $user_id = $_SESSION['user_id'];
} elseif (isset($get['token']) === true && empty($get['token']) === false) {
    $user_token = $get['token'];
} else {
    $user_id = '';
    $user_token = '';
}

if (empty($user_id) === false && isset($_SESSION['CPM']) === true) {
    // connect to the server
    include_once '../../sources/main.functions.php';
    include_once '../../includes/config/settings.php';
    include_once '../../includes/libraries/Database/Meekrodb/db.class.php';
    DB::$host = DB_HOST;
    DB::$user = DB_USER;
    DB::$password = defuseReturnDecrypted(DB_PASSWD, $SETTINGS);
    DB::$dbName = DB_NAME;
    DB::$port = DB_PORT;
    DB::$encoding = DB_ENCODING;
    DB::$ssl = DB_SSL;
    DB::$connect_options = DB_CONNECT_OPTIONS;
    // clear in db
    DB::update(
        DB_PREFIX.'users',
        [
            'key_tempo' => '',
            'timestamp' => '',
            'session_end' => '',
        ],
        'id=%i || key_tempo=%s',
        $user_id,
        isset($user_token) === true ? $user_token : ''
    );
    //Log into DB the user's disconnection
    if (isset($SETTINGS['log_connections']) === true
        && (int) $SETTINGS['log_connections'] === 1
    ) {
        include_once '../../sources/main.functions.php';
        logEvents($SETTINGS, 'user_connection', 'disconnect', (string) $user_id, isset($_SESSION['login']) === true ? $_SESSION['login'] : '');
    }
}

// erase session table
session_destroy();
$_SESSION = [];
require_once '../../sources/SecureHandler.php';
session_name('teampass_session');
session_start();
$_SESSION['CPM'] = 1;
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
        localStorage.clear();
        
        setTimeout(function() {
            document.location.href="../../index.php"
        }, 1);
    -->
    </script>';
