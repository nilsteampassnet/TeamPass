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
 * @file      favourites.queries.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2023 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */

Use voku\helper\AntiXSS;
Use TeampassClasses\NestedTree\NestedTree;
Use TeampassClasses\SuperGlobal\SuperGlobal;
Use EZimuel\PHPSecureSession;
Use TeampassClasses\PerformChecks\PerformChecks;


// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
session_name('teampass_session');
session_start();

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
    exit();
}

// Do checks
// Instantiate the class with posted data
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => isset($_POST['type']) === true ? $_POST['type'] : '',
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => isset($_SESSION['user_id']) === false ? null : $_SESSION['user_id'],
        'user_key' => isset($_SESSION['key']) === false ? null : $_SESSION['key'],
        'CPM' => isset($_SESSION['CPM']) === false ? null : $_SESSION['CPM'],
    ]
);
// Handle the case
$checkUserAccess->caseHandler();
if (
    $checkUserAccess->userAccessPage('favourites') === false ||
    $checkUserAccess->checkSession() === false
) {
    // Not allowed page
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Load language file
require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user']['user_language'].'.php';

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
error_reporting(E_ERROR);
set_time_limit(0);

// --------------------------------- //

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

// manage action required
if (null !== $post_type) {
    switch ($post_type) {
        //CASE adding a new function
        case 'del_fav':
            //Get actual favourites
            $data = DB::queryfirstrow('SELECT favourites FROM '.prefixTable('users').' WHERE id = %i', $_SESSION['user_id']);
            $tmp = explode(';', $data['favourites']);
            $favs = '';
            $tab_favs = array();
            //redefine new list of favourites
            foreach ($tmp as $favorite) {
                if (!empty($favorite) && $favorite != $post_id) {
                    if (empty($favs)) {
                        $favs = $favorite;
                    } else {
                        $favs = ';'.$favorite;
                    }
                    array_push($tab_favs, $favorite);
                }
            }
            //update user's account
            DB::update(
                prefixTable('users'),
                array(
                    'favourites' => $favs,
                ),
                'id = %i',
                $_SESSION['user_id']
            );
            //update session
            $_SESSION['favourites'] = $tab_favs;
            break;
    }
}
