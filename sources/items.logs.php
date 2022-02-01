<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      items.logs.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2022 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */


require_once 'SecureHandler.php';
session_name('teampass_session');
session_start();
if (isset($_SESSION['CPM']) === false
    || $_SESSION['CPM'] != 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id'])
    || isset($_SESSION['key']) === false || empty($_SESSION['key'])
) {
    die('Hacking attempt...');
}

// Load config if $SETTINGS not defined
if (isset($SETTINGS['cpassman_dir']) === false || empty($SETTINGS['cpassman_dir'])) {
    if (file_exists('../includes/config/tp.config.php')) {
        include_once '../includes/config/tp.config.php';
    } elseif (file_exists('./includes/config/tp.config.php')) {
        include_once './includes/config/tp.config.php';
    } elseif (file_exists('../../includes/config/tp.config.php')) {
        include_once '../../includes/config/tp.config.php';
    } else {
        throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
    }
}

/* do checks */
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], 'items', $SETTINGS)) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/tp.config.php';

header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

// connect to the server
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

// Check KEY and rights
if (null === $post_key
    || $post_key != $_SESSION['key']
) {
    echo prepareExchangedData(
        $SETTINGS['cpassman_dir'],
        array('error' => 'ERR_KEY_NOT_CORRECT'),
        'encode'
    );
    exit();
}

// Do asked action
if (null !== $post_type) {
    switch ($post_type) {
        case 'log_action_on_item':
            // Check KEY and rights
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    $SETTINGS['cpassman_dir'],
                    array('error' => 'ERR_KEY_NOT_CORRECT'),
                    'encode'
                );
                break;
            }

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                $post_data,
                'decode'
            );

            logItems(
                $SETTINGS,
                (int) filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT),
                filter_var(htmlspecialchars_decode($dataReceived['label']), FILTER_SANITIZE_STRING),
                (int) filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT),
                filter_var(htmlspecialchars_decode($dataReceived['action']), FILTER_SANITIZE_STRING),
                filter_var(htmlspecialchars_decode($dataReceived['login']), FILTER_SANITIZE_STRING)
            );
            break;
    }
}
