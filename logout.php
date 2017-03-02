<?php
/**
 *
 * @file          logout.php
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

// Update table by deleting ID
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    DB::update(
        prefix_table("users"),
        array(
            'key_tempo' => '',
            'timestamp' => '',
            'session_end' => ''
        ),
        "id=%i",
        $_SESSION['user_id']
    );
    //Log into DB the user's disconnection
    if (isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] == 1) {
        logEvents('user_connection', 'disconnection', @$_SESSION['user_id'], $_SESSION['login']);
    }
}

// erase session table
$_SESSION = array();

header("Location: index.php");