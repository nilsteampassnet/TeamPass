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
 * @file      tools.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\NestedTree\NestedTree;
use Duo\DuoUniversal\Client;
use Duo\DuoUniversal\DuoException;

// Load functions
require_once 'main.functions.php';
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
loadClasses('DB');
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
// Instantiate the class with posted data
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => $request->request->get('type', '') !== '' ? htmlspecialchars($request->request->get('type')) : '',
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('tools') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);

switch ($post_type) {
//##########################################################
//CASE for creating a DB backup
case 'perform_fix_pf_items-step1':
    // Check KEY
    if ($post_key !== $session->get('key')) {
        echo prepareExchangedData(
            array(
                'error' => true,
                'message' => $lang->get('key_is_not_correct'),
            ),
            'encode'
        );
        break;
    }
    // Is admin?
    if ($session->get('user-admin') !== 1) {
        echo prepareExchangedData(
            array(
                'error' => true,
                'message' => $lang->get('error_not_allowed_to'),
            ),
            'encode'
        );
        break;
    }

    // decrypt and retrieve data in JSON format
    $dataReceived = prepareExchangedData(
        $post_data,
        'decode'
    );

    $userId = filter_var($dataReceived['userId'], FILTER_SANITIZE_NUMBER_INT);

    // Get user info
    $userInfo = DB::queryFirstRow(
        'SELECT private_key, public_key, psk, encrypted_psk
        FROM teampass_users
        WHERE id = %i',
        $userId
    );

    // Get user's private folders
    $userPFRoot = DB::queryFirstRow(
        'SELECT id
        FROM teampass_nested_tree
        WHERE title = %i',
        $userId
    );
    if (DB::count() === 0) {
        echo prepareExchangedData(
            array(
                'error' => true,
                'message' => 'User has no personal folders',
            ),
            'encode'
        );
        break;
    }
    $personalFolders = [];
    $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
    $tree->rebuild();
    $folders = $tree->getDescendants($userPFRoot['id'], true);
    foreach ($folders as $folder) {
        array_push($personalFolders, $folder->id);
    }

    //Show done
    echo prepareExchangedData(
        array(
            'error' => false,
            'message' => 'Personal Folders found: ',
            'personalFolders' => json_encode($personalFolders),
        ),
        'encode'
    );
    break;

case 'perform_fix_pf_items-step2':
    // Check KEY
    if ($post_key !== $session->get('key')) {
        echo prepareExchangedData(
            array(
                'error' => true,
                'message' => $lang->get('key_is_not_correct'),
            ),
            'encode'
        );
        break;
    }
    // Is admin?
    if ($session->get('user-admin') !== 1) {
        echo prepareExchangedData(
            array(
                'error' => true,
                'message' => $lang->get('error_not_allowed_to'),
            ),
            'encode'
        );
        break;
    }

    // decrypt and retrieve data in JSON format
    $dataReceived = prepareExchangedData(
        $post_data,
        'decode'
    );

    $userId = filter_var($dataReceived['userId'], FILTER_SANITIZE_NUMBER_INT);
    $personalFolders = filter_var($dataReceived['personalFolders'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Delete all private items with sharekeys
    $pfiSharekeys = DB::queryFirstColumn(
        'select s.increment_id
        from teampass_sharekeys_items as s
        INNER JOIN teampass_items AS i ON (i.id = s.object_id)
        WHERE s.user_id = %i AND i.perso = 1 AND i.id_tree IN %ls',
        $userId,
        $personalFolders
    );
    $pfiSharekeysCount = DB::count();
    if ($pfiSharekeysCount > 0) {
        DB::delete(
            "teampass_sharekeys_items",
            "increment_id IN %ls",
            $pfiSharekeys
        );
    }

    
    //Show done
    echo prepareExchangedData(
        array(
            'error' => false,
            'message' => '<br>Number of Sharekeys for private items DELETED: ',
            'nbDeleted' => $pfiSharekeysCount,
            'personalFolders' => json_encode($personalFolders),
        ),
        'encode'
    );
    break;

case 'perform_fix_pf_items-step3':
    // Check KEY
    if ($post_key !== $session->get('key')) {
        echo prepareExchangedData(
            array(
                'error' => true,
                'message' => $lang->get('key_is_not_correct'),
            ),
            'encode'
        );
        break;
    }
    // Is admin?
    if ($session->get('user-admin') !== 1) {
        echo prepareExchangedData(
            array(
                'error' => true,
                'message' => $lang->get('error_not_allowed_to'),
            ),
            'encode'
        );
        break;
    }

    // decrypt and retrieve data in JSON format
    $dataReceived = prepareExchangedData(
        $post_data,
        'decode'
    );

    $userId = filter_var($dataReceived['userId'], FILTER_SANITIZE_NUMBER_INT);
    $personalFolders = filter_var($dataReceived['personalFolders'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Update from items_old to items all the private itemsitems that have been converted to teampass_aes
    // Get all key back
    $items = DB::query(
        "SELECT id
        FROM teampass_items
        WHERE id_tree IN %ls AND encryption_type = %s",
        $personalFolders,
        "teampass_aes"
    );
    //DB::debugMode(false);
    $nbItems = DB::count();
    foreach ($items as $item) {
        $defusePwd = DB::queryFirstField("SELECT pw FROM teampass_items_old WHERE id = %i", $item['id']);
        DB::update(
            "teampass_items",
            ['pw' => $defusePwd, "encryption_type" => "defuse"],
            "id = %i",
            $item['id']
        );
    }

    
    //Show done
    echo prepareExchangedData(
        array(
            'error' => false,
            'message' => '<br>Number of items reseted to Defuse: ',
            'nbItems' => $nbItems,
            'personalFolders' => json_encode($personalFolders),
        ),
        'encode'
    );
    break;
}