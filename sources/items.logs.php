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
 * @copyright 2009-2023 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */


use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\Language\Language;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\NestedTree\NestedTree;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();
$lang = new Language(); 
session_name('teampass_session');
session_start();

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Do checks
// Instantiate the class with posted data
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => returnIfSet($superGlobal->get('type', 'POST')),
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($superGlobal->get('user_id', 'SESSION'), null),
        'user_key' => returnIfSet($superGlobal->get('key', 'SESSION'), null),
        'CPM' => returnIfSet($superGlobal->get('CPM', 'SESSION'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if (
    $checkUserAccess->userAccessPage('items') === false ||
    $checkUserAccess->checkSession() === false
) {
    // Not allowed page
    $superGlobal->put('code', ERR_NOT_ALLOWED, 'SESSION', 'error');
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
    || $post_key != $superGlobal->get('key', 'SESSION')
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
            if ($post_key !== $superGlobal->get('key', 'SESSION')) {
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

            if (is_array($dataReceived) === true && array_key_exists('id', $dataReceived) === true && null !== filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT)) {
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
