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
 * @file      items.logs.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\NestedTree\NestedTree;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
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
if (
    $checkUserAccess->userAccessPage('items') === false ||
    $checkUserAccess->checkSession() === false
) {
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
// Load tree
$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);

// Check KEY and rights
if (null === $post_key
    || $post_key != $session->get('key')
) {
    echo prepareExchangedData(
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
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array('error' => 'ERR_KEY_NOT_CORRECT'),
                    'encode'
                );
                break;
            }

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // Check if the data is correct
            // Required keys: id, label, user_id, action, login
            $requiredKeys = ['id', 'label', 'user_id', 'action', 'login'];
            
            if (
                is_array($dataReceived) && // check if the data is an array
                array_diff_key(array_flip($requiredKeys), $dataReceived) === [] &&  // check if all required keys have a valuekeys are present
                count(array_filter($dataReceived)) === count($requiredKeys) && // check if all required 
                in_array($dataReceived['action'], ['at_password_shown', 'at_password_copied'], true) && // only log these actions
                $session->get('user-id') === (int) filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT) // only log actions of the current user
            ) {
                // Log the action
                logItems(
                    $SETTINGS,
                    (int) filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT),
                    filter_var(htmlspecialchars_decode($dataReceived['label']), FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                    (int) filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT),
                    filter_var(htmlspecialchars_decode($dataReceived['action']), FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                    filter_var(htmlspecialchars_decode($dataReceived['login']), FILTER_SANITIZE_FULL_SPECIAL_CHARS)
                );
            }
            break;
    }
}
