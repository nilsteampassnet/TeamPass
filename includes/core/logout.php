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

use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;
use TeampassClasses\NestedTree\NestedTree;

// Load functions
require_once __DIR__.'/../../sources/main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();
$lang = new Language(); 
$session = SessionManager::getSession();

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
$get = [];
$get['user_id'] = $superGlobal->get('user_id', 'GET');

// Update table by deleting ID
if (null !== $session->get('user-id') && empty($session->get('user-id')) === false) {
    $user_id = $session->get('user-id');
} elseif (isset($get['token']) === true && empty($get['token']) === false) {
    $user_token = $get['token'];
} else {
    $user_id = '';
    $user_token = '';
}

if (empty($user_id) === false) {
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
        logEvents($SETTINGS, 'user_connection', 'disconnect', (string) $user_id, null !== $session->get('user-login') ? $session->get('user-login') : '');
    }
}

// erase session table
$session->invalidate();

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
