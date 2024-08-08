<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      logout.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request;
use TeampassClasses\Language\Language;
use TeampassClasses\NestedTree\NestedTree;

// Load functions
require_once __DIR__.'/../../sources/main.functions.php';

// init
loadClasses('DB');

$request = Request::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');
$session = SessionManager::getSession();

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
$get = [];
$get['user_id'] = $request->query->get('user_id');

// Update table by deleting ID
if ($session->has('user-id') && null !== $session->get('user-id') && empty($session->get('user-id')) === false) {
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
$session->set('key', SessionManager::getCookieValue('PHPSESSID'));

?>
<script type="text/javascript" src="../../plugins/store.js/dist/store.everything.min.js"></script>
<script language="javascript" type="text/javascript">
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
</script>
