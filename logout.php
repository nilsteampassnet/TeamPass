<?php
/**
 * Created by PhpStorm.
 * User: Nous
 * Date: 05/01/2016
 * Time: 20:12
 */

session_start();

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

// Kill session
session_destroy();

header("Location: index.php");