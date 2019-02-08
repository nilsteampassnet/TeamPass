<?php
/**
 * Teampass - a collaborative passwords manager.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 *
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2019 Nils Laumaillé
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 *
 * @version   GIT: <git_id>
 *
 * @see      http://www.teampass.net
 */
require_once '../../sources/SecureHandler.php';
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
    DB::$host = DB_HOST;
    DB::$user = DB_USER;
    DB::$password = defuseReturnDecrypted(DB_PASSWD, $SETTINGS);
    DB::$dbName = DB_NAME;
    DB::$port = DB_PORT;
    DB::$encoding = DB_ENCODING;
    $link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
    $link->set_charset(DB_ENCODING);

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
        setTimeout(function(){document.location.href="../../index.php"}, 1);
    -->
    </script>';
