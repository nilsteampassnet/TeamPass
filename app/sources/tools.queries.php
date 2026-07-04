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
 * @copyright 2009-2026 Teampass.net
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
            'type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
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
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

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
    if (!hash_equals((string) $session->get('key'), (string) $post_key)) {
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
    if ((int) $session->get('user-admin') !== 1) {
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
        'SELECT pk.private_key, u.public_key, u.psk, u.encrypted_psk
        FROM ' . prefixTable('users') . ' AS u
        LEFT JOIN ' . prefixTable('user_private_keys') . ' AS pk ON (u.id = pk.user_id AND pk.is_current = 1)
        WHERE u.id = %i',
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
    if (!hash_equals((string) $session->get('key'), (string) $post_key)) {
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
    if ((int) $session->get('user-admin') !== 1) {
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
    if (!hash_equals((string) $session->get('key'), (string) $post_key)) {
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
    if ((int) $session->get('user-admin') !== 1) {
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

    /* TOOL #2 - Fixing items master keys */
    /*
    * STEP 1 - Check if we have the correct pwd for TP_USER
    */
    case 'perform_fix_items_master_keys-step1':
        // Check KEY
        if (!hash_equals((string) $session->get('key'), (string) $post_key)) {
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
        if ((int) $session->get('user-admin') !== 1) {
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

        // Get TP_USER info
        $userInfo = DB::queryFirstRow(
            'SELECT u.pw, u.public_key, pk.private_key, u.login, u.name
            FROM ' . prefixTable('users') . ' AS u
            LEFT JOIN ' . prefixTable('user_private_keys') . ' AS pk ON (u.id = pk.user_id AND pk.is_current = 1)
            WHERE u.id = %i',
            TP_USER_ID
        );

        // decrypt owner password
        $decryptedData = cryption($userInfo['pw'], '', 'decrypt', $SETTINGS);
        $pwd = $decryptedData['string'] ?? '';

        $privateKey = decryptPrivateKey($pwd, $userInfo['private_key']);

        if (empty($privateKey)) {
            // Generate new keys for TP user
            $userKeys = generateUserKeys($pwd, $SETTINGS ?? null);

            // Update user keys
            $updateData = array(
                'public_key' => $userKeys['public_key'],
                'private_key' => $userKeys['private_key'],
            );

            // Include transparent recovery data if available
            if (isset($userKeys['user_seed'])) {
                $updateData['user_derivation_seed'] = $userKeys['user_seed'];
                $updateData['private_key_backup'] = $userKeys['private_key_backup'];
                $updateData['key_integrity_hash'] = $userKeys['key_integrity_hash'];
                $updateData['last_pw_change'] = time();
            }

            DB::update(
                prefixTable('users'),
                $updateData,
                'id = %i',
                TP_USER_ID
            );

            // Store private key in dedicated table
            insertPrivateKeyWithCurrentFlag((int) TP_USER_ID, $userKeys['private_key']);

            $privateKey = decryptPrivateKey($pwd, $userKeys['private_key']);

            if (empty($privateKey)) {
                error_log("Teampass - Error: impossible to decrypt the private key for master user");
            }
        }

        // Checking that this pwd cannot decrypt an item sharekey        
        // Get one itemKey from current user
        $currentUserKey = DB::queryFirstRow(
            'SELECT ski.share_key, ski.increment_id AS increment_id
            FROM ' . prefixTable('sharekeys_items') . ' AS ski
            INNER JOIN ' . prefixTable('items') . ' AS i ON i.id = ski.object_id
            WHERE ski.user_id = %i AND ski.share_key != ""
            ORDER BY RAND()
            LIMIT 1',
            TP_USER_ID
        );
        /*
        // CAN BE COMMENTED OUT FOR DEV PURPOSE ; THIS SHOULD BE KEPT IN NORMAL CASE
        if (!empty($currentUserKey)) {
            // Decrypt itemkey with user key
            // use old password to decrypt private_key
            $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $privateKey);
            
            if (!empty(@base64_decode($itemKey))) {
                // GOOD password
                // THings all good
                echo  prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => 'No issue found, normal process should work. This process is now finished. (item id : ' . $currentUserKey['increment_id'] . ')',
                    ),
                    'encode'
                );
                break;
            }
        }
        */
        
        //Show done
        echo prepareExchangedData(
            array(
                'error' => empty($pwd) === false ? false : true,
                'message' => empty($pwd) === false ? 
                    'Master password decrypted.'
                    : 'Master password not decrypted, We have a problem.', 
                'tp_user_publicKey' => $userInfo['public_key'],
                'tp_user_pwd' => $pwd,
            ),
            'encode'
        );
        break;

    /*
    * STEP 2 - Check if we have the correct pwd for selected user
    */
    case 'perform_fix_items_master_keys-step2':
        // Check KEY
        if (!hash_equals((string) $session->get('key'), (string) $post_key)) {
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
        if ((int) $session->get('user-admin') !== 1) {
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
        // IMPORTANT: Passwords should NOT be sanitized (fix 3.1.5.10)
        $userPwd = $dataReceived['userPassword'];

        // Get user info
        $userInfo = DB::queryFirstRow(
            'SELECT u.public_key, pk.private_key
            FROM ' . prefixTable('users') . ' AS u
            LEFT JOIN ' . prefixTable('user_private_keys') . ' AS pk ON (u.id = pk.user_id AND pk.is_current = 1)
            WHERE u.id = %i',
            $userId
        );

        // decrypt user provate key
        $privateKey = decryptPrivateKey($userPwd, $userInfo['private_key']);

        if (empty($privateKey)) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => 'User private key not decrypted, ',
                ),
                'encode'
            );
            break;
        }

        // Checking that this pwd cannot decrypt an item sharekey        
        // Get one itemKey from current user
        $currentUserKey = DB::queryFirstRow(
            'SELECT ski.share_key, ski.increment_id AS increment_id
            FROM ' . prefixTable('sharekeys_items') . ' AS ski
            INNER JOIN ' . prefixTable('items') . ' AS i ON i.id = ski.object_id
            WHERE ski.user_id = %i AND ski.share_key != ""
            ORDER BY RAND()
            LIMIT 1',
            $userId
        );

        if (!empty($currentUserKey)) {
            // Decrypt itemkey with user key
            // use old password to decrypt private_key
            $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $privateKey);
            
            if (!empty(@base64_decode($itemKey))) {
                // GOOD password
                // THings all good

                // Check and prepare backup table
                $tableExists = DB::queryFirstField("SHOW TABLES LIKE %s", prefixTable('sharekeys_backup'));
                if (empty($tableExists)) {
                    // Table doesn't exist, create it
                    DB::query(
                        'CREATE TABLE `'.prefixTable('sharekeys_backup').'` (
                        `increment_id` INT(12) NOT NULL AUTO_INCREMENT,
                        `object_type` VARCHAR(50) NOT NULL,
                        `object_id` INT(12) NOT NULL,
                        `user_id` INT(12) NOT NULL,
                        `share_key` MEDIUMTEXT NOT NULL,
                        `increment_id_value` INT(12) NOT NULL,
                        `operation_code` VARCHAR(50) NOT NULL,
                        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`increment_id`)
                        ) CHARSET=utf8;'
                    );
                }

                // Get number of users to treat
                DB::query(
                    'SELECT i.id 
                    FROM ' . prefixTable('items') . ' AS i
                    INNER JOIN ' . prefixTable('sharekeys_items') . ' AS si ON i.id = si.object_id
                    WHERE i.perso = %i AND si.user_id = %i;',
                    0,
                    $userId
                );
                
                echo prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => 'Could decrypt the privatekey. We can proceed with '.DB::count().' items.',
                        'nb_items_to_proceed' => DB::count(),
                        'selected_user_privateKey' => $privateKey,
                    ),
                    'encode'
                );
                break;
            }
        }
        
        //Show done
        echo prepareExchangedData(
            array(
                'error' => true,
                'message' => 'We could not decrypt the item share key with user provided, We have a problem.', 
            ),
            'encode'
        );
        break;

    /*
    * STEP 3 - Start the process of changing the keys
    */
    case 'perform_fix_items_master_keys-step3':
        // Check KEY
        if (!hash_equals((string) $session->get('key'), (string) $post_key)) {
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
        if ((int) $session->get('user-admin') !== 1) {
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
        $userId = intval($dataReceived['userId']);
        $tpUserPublicKey = htmlspecialchars($dataReceived['tp_user_publicKey'], ENT_QUOTES, 'UTF-8');
        $selectedUserPrivateKey = htmlspecialchars($dataReceived['selected_user_privateKey'], ENT_QUOTES, 'UTF-8');
        $nbItems = intval($dataReceived['nbItems']);
        $startIndex = intval($dataReceived['startIndex']);
        $limit = intval($dataReceived['limit']);
        $operationCode = htmlspecialchars($dataReceived['operationCode'], ENT_QUOTES, 'UTF-8');


        // Start transaction for better performance
        try {
            DB::startTransaction();

            // If we are starting then generate a code
            if (empty($operationCode)) {
                $operationCode = uniqidReal(32);
            }

            // Loop on items
            $rows = DB::query(
                'SELECT si.object_id AS object_id, si.share_key AS share_key, i.pw AS pw, si.increment_id as increment_id
                FROM ' . prefixTable('sharekeys_items') . ' AS si
                INNER JOIN ' . prefixTable('items') . ' AS i ON (i.id = si.object_id)
                WHERE si.user_id = %i
                ORDER BY si.increment_id ASC
                LIMIT ' . $startIndex . ', ' . $limit,
                $userId
            );        
            
            if (!empty($rows)) {
                foreach ($rows as $record) {
                    // Decrypt itemkey with admin key
                    $itemKey = decryptUserObjectKey(
                        $record['share_key'],
                        $selectedUserPrivateKey
                    );
                    
                    // Prevent to change key if its key is empty
                    if (empty($itemKey) === true) {
                        continue;
                    }

                    // Encrypt Item key for TP USER
                    $share_key_for_item = encryptUserObjectKey($itemKey, $tpUserPublicKey);
                    
                    // Get object for TP USER
                    // It will be updated if already exists
                    $currentTPUserKey = DB::queryFirstRow(
                        'SELECT increment_id, user_id, share_key
                        FROM ' . prefixTable('sharekeys_items') . '
                        WHERE object_id = %i AND user_id = %i',
                        $record['object_id'],
                        TP_USER_ID
                    );
                    
                    if (!empty($currentTPUserKey)) {
                        // Backup current value
                        DB::insert(
                            prefixTable('sharekeys_backup'),
                            array(
                                'object_id' => (int) $record['object_id'],
                                'user_id' => (int) $currentTPUserKey['user_id'],
                                'share_key' => $currentTPUserKey['share_key'],
                                'object_type' => 'item',
                                'increment_id_value' => (int) $currentTPUserKey['increment_id'],
                                'operation_code' => $operationCode,
                            )
                        );

                        // NOw update
                        DB::update(
                            prefixTable('sharekeys_items'),
                            array(
                                'share_key' => $share_key_for_item,
                            ),
                            'increment_id = %i',
                            $currentTPUserKey['increment_id']
                        );
                    }
                }
            }
            // Commit transaction
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            error_log("Teampass - Error: Keys treatment: " . $e->getMessage());
        }

        $nextIndex = (int) $startIndex + (int) $limit;
        
        //Show done
        echo prepareExchangedData(
            array(
                'error' => false,
                'message' => '', 
                'nextIndex' => $nextIndex,
                'status' => (int) $nextIndex >= (int) $nbItems ? 'done' : 'continue',
                'operationCode' => $operationCode,
            ),
            'encode'
        );
        break;

        /*
        * RESTORE a backup
        */
        case 'restore_items_master_keys_from_backup':
            // Check KEY
            if (!hash_equals((string) $session->get('key'), (string) $post_key)) {
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
            if ((int) $session->get('user-admin') !== 1) {
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
            
            $operationCode = filter_var($dataReceived['operationCode'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
            // Get PT_USER info
            DB::queryFirstRow(
                'SELECT operation_code
                FROM ' . prefixTable('sharekeys_backup') . '
                WHERE operation_code = %s',
                $operationCode
            );

            if (DB::count() > 0) {
                // Copy all sharekeys from backup to original table
                // using increment_id_value in order to update the correct record
                $rows = DB::query(
                    'SELECT *
                    FROM ' . prefixTable('sharekeys_backup') . '
                    WHERE operation_code = %s',
                    $operationCode
                );
                foreach ($rows as $backup) {
                    // Get object for TP USER
                    // It will be updated if already exists
                    DB::update(
                        prefixTable('sharekeys_items'),
                        array(
                            'share_key' => $backup['share_key'],
                        ),
                        'increment_id = %i',
                        $backup['increment_id_value']
                    );
                }

                // Delete all sharekeys for this operation
                DB::query(
                    'DELETE FROM ' . prefixTable('sharekeys_backup') . '
                    WHERE operation_code = %i',
                    $operationCode
                );

                // DOne
                echo prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => 'This backup has been restored. Original keys are back in production',
                    ),
                    'encode'
                );
                break;
            }

            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => 'This operation code does not exists.',
                ),
                'encode'
            );
            break;

        /*
        * DELETE a backup
        */
        case 'perform_delete_restore_backup':
            // Check KEY
            if (!hash_equals((string) $session->get('key'), (string) $post_key)) {
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
            if ((int) $session->get('user-admin') !== 1) {
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
            
            $operationCode = filter_var($dataReceived['operationCode'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            error_log('Deleted backup with code: '.$operationCode);
            // Get operation info
            DB::query(
                'SELECT operation_code
                FROM ' . prefixTable('sharekeys_backup') . '
                WHERE operation_code = %s',
                $operationCode
            );
            $nbKeys = DB::count();

            if ($nbKeys > 0) {
                // Delete all sharekeys for this operation
                DB::query(
                    'DELETE FROM ' . prefixTable('sharekeys_backup') . '
                    WHERE operation_code = %s',
                    $operationCode
                );

                // DOne
                echo prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => 'This backup with '.$nbKeys.' keys has been deleted.',
                    ),
                    'encode'
                );
                break;
            }

            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => 'This operation code does not exists.',
                ),
                'encode'
            );
            break;

    /*
    * RESTORE MISSING SHAREKEYS - STEP 1 - Analyze (dry-run, no write)
    * Counts missing sharekeys for eligible users on non-personal objects.
    */
    case 'restore_missing_sharekeys-analyze':
        // Check KEY
        if (!hash_equals((string) $session->get('key'), (string) $post_key)) {
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
        if ((int) $session->get('user-admin') !== 1) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }

        $specialUserIds = [OTV_USER_ID, SSH_USER_ID, API_USER_ID];

        // Number of users expected to own a sharekey for every shared object
        // (same eligibility rule as storeUsersShareKey)
        $eligibleUsersCount = (int) DB::queryFirstField(
            'SELECT COUNT(*) FROM ' . prefixTable('users') . ' WHERE id NOT IN %li AND public_key != ""',
            $specialUserIds
        );

        $analysis = [];
        foreach (restoreSharekeysScopeDefs() as $scopeName => $def) {
            $objectsCount = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . $def['from'] . ' WHERE ' . $def['where']
            );
            $existingKeys = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . $def['from'] . '
                INNER JOIN ' . prefixTable($def['table']) . ' AS sk ON sk.object_id = o.id
                INNER JOIN ' . prefixTable('users') . ' AS u ON u.id = sk.user_id
                WHERE ' . $def['where'] . ' AND sk.share_key != "" AND u.public_key != "" AND u.id NOT IN %li',
                $specialUserIds
            );
            $tpMissing = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . $def['from'] . '
                LEFT JOIN ' . prefixTable($def['table']) . ' AS sk ON (sk.object_id = o.id AND sk.user_id = ' . TP_USER_ID . ' AND sk.share_key != "")
                WHERE ' . $def['where'] . ' AND sk.increment_id IS NULL'
            );
            $adminSeedable = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . $def['from'] . '
                INNER JOIN ' . prefixTable($def['table']) . ' AS ska ON (ska.object_id = o.id AND ska.user_id = %i AND ska.share_key != "")
                LEFT JOIN ' . prefixTable($def['table']) . ' AS sk ON (sk.object_id = o.id AND sk.user_id = ' . TP_USER_ID . ' AND sk.share_key != "")
                WHERE ' . $def['where'] . ' AND sk.increment_id IS NULL',
                (int) $session->get('user-id')
            );

            $analysis[$scopeName] = [
                'objects' => $objectsCount,
                'missing_pairs' => max(0, $objectsCount * $eligibleUsersCount - $existingKeys),
                'tp_missing' => $tpMissing,
                'admin_seedable' => $adminSeedable,
                'unrecoverable' => max(0, $tpMissing - $adminSeedable),
            ];
        }

        echo prepareExchangedData(
            array(
                'error' => false,
                'eligible_users' => $eligibleUsersCount,
                'analysis' => $analysis,
            ),
            'encode'
        );
        break;

    /*
    * RESTORE MISSING SHAREKEYS - Details of objects without TP_USER reference key
    * For each object, lists the users still holding a valid sharekey so the
    * admin knows who can re-save an unrecoverable object.
    */
    case 'restore_missing_sharekeys-details':
        // Check KEY
        if (!hash_equals((string) $session->get('key'), (string) $post_key)) {
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
        if ((int) $session->get('user-admin') !== 1) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }

        $specialUserIds = [OTV_USER_ID, SSH_USER_ID, API_USER_ID];
        $detailsLimit = 100;

        // Per-scope label columns (object aliased "o", parent item aliased "i" when joined)
        $detailDefs = [
            'items' => [
                'label' => 'o.label AS label',
                'extra' => '"" AS extra',
                'extraJoin' => '',
            ],
            'fields' => [
                'label' => 'i.label AS label',
                'extra' => 'IFNULL(c.title, "") AS extra',
                'extraJoin' => ' LEFT JOIN ' . prefixTable('categories') . ' AS c ON c.id = o.field_id',
            ],
            'files' => [
                'label' => 'i.label AS label',
                'extra' => 'o.name AS extra',
                'extraJoin' => '',
            ],
        ];

        $details = [];
        foreach (restoreSharekeysScopeDefs() as $scopeName => $def) {
            $total = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . $def['from'] . '
                LEFT JOIN ' . prefixTable($def['table']) . ' AS sk ON (sk.object_id = o.id AND sk.user_id = ' . TP_USER_ID . ' AND sk.share_key != "")
                WHERE ' . $def['where'] . ' AND sk.increment_id IS NULL'
            );

            $rows = [];
            if ($total > 0) {
                $rows = DB::query(
                    'SELECT o.id AS object_id, ' . $detailDefs[$scopeName]['label'] . ', ' . $detailDefs[$scopeName]['extra'] . ',
                        (SELECT GROUP_CONCAT(DISTINCT u.login SEPARATOR ", ")
                        FROM ' . prefixTable($def['table']) . ' AS skh
                        INNER JOIN ' . prefixTable('users') . ' AS u ON u.id = skh.user_id
                        WHERE skh.object_id = o.id AND skh.share_key != "" AND u.id NOT IN %li) AS key_holders,
                        (SELECT COUNT(DISTINCT skh2.user_id)
                        FROM ' . prefixTable($def['table']) . ' AS skh2
                        INNER JOIN ' . prefixTable('users') . ' AS u2 ON u2.id = skh2.user_id
                        WHERE skh2.object_id = o.id AND skh2.share_key != "" AND u2.id NOT IN %li) AS key_holders_count
                    FROM ' . $def['from'] . $detailDefs[$scopeName]['extraJoin'] . '
                    LEFT JOIN ' . prefixTable($def['table']) . ' AS sk ON (sk.object_id = o.id AND sk.user_id = ' . TP_USER_ID . ' AND sk.share_key != "")
                    WHERE ' . $def['where'] . ' AND sk.increment_id IS NULL
                    ORDER BY o.id ASC
                    LIMIT %i',
                    $specialUserIds,
                    $specialUserIds,
                    $detailsLimit
                );
            }

            $details[$scopeName] = [
                'total' => $total,
                'objects' => array_map(
                    static function (array $row): array {
                        return [
                            'id' => (int) $row['object_id'],
                            'label' => (string) $row['label'],
                            'extra' => (string) $row['extra'],
                            'key_holders' => (string) ($row['key_holders'] ?? ''),
                            'key_holders_count' => (int) $row['key_holders_count'],
                        ];
                    },
                    $rows
                ),
            ];
        }

        echo prepareExchangedData(
            array(
                'error' => false,
                'limit' => $detailsLimit,
                'details' => $details,
            ),
            'encode'
        );
        break;

    /*
    * RESTORE MISSING SHAREKEYS - STEP 2 - Seed TP_USER reference keys
    * Recreates missing TP_USER sharekeys using the executing admin's own keys
    * (the admin private key only exists in the web session, hence this
    * synchronous, JS-batched step). Called in a loop per scope until finished.
    */
    case 'restore_missing_sharekeys-seed':
        // Check KEY
        if (!hash_equals((string) $session->get('key'), (string) $post_key)) {
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
        if ((int) $session->get('user-admin') !== 1) {
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
        $scopeName = filter_var($dataReceived['scope'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $lastId = (int) filter_var($dataReceived['lastId'] ?? 0, FILTER_SANITIZE_NUMBER_INT);

        $scopeDefs = restoreSharekeysScopeDefs();
        if (isset($scopeDefs[$scopeName]) === false) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => 'Unknown scope',
                ),
                'encode'
            );
            break;
        }
        $def = $scopeDefs[$scopeName];
        $batchSize = 25;

        // Objects the admin can decrypt but for which TP_USER has no valid v3 key.
        // A leftover legacy v1 key on TP_USER is treated as "not good enough"
        // (it may be broken and fail to decrypt server-side): the admin's
        // in-session key re-seeds TP_USER as v3, so the object stays recoverable
        // by the background task even when TP_USER's own key was broken (#5252).
        $rows = DB::query(
            'SELECT o.id AS object_id, ska.share_key AS admin_share_key, ska.increment_id AS admin_key_id
            FROM ' . $def['from'] . '
            INNER JOIN ' . prefixTable($def['table']) . ' AS ska ON (ska.object_id = o.id AND ska.user_id = %i AND ska.share_key != "")
            LEFT JOIN ' . prefixTable($def['table']) . ' AS sk ON (sk.object_id = o.id AND sk.user_id = ' . TP_USER_ID . ' AND sk.share_key != "" AND sk.encryption_version = 3)
            WHERE ' . $def['where'] . ' AND sk.increment_id IS NULL AND o.id > %i
            ORDER BY o.id ASC
            LIMIT %i',
            (int) $session->get('user-id'),
            $lastId,
            $batchSize
        );

        $tpUserPublicKey = (string) DB::queryFirstField(
            'SELECT public_key FROM ' . prefixTable('users') . ' WHERE id = %i',
            TP_USER_ID
        );

        $seeded = 0;
        $failed = 0;
        foreach ($rows as $record) {
            $lastId = (int) $record['object_id'];
            $objectKey = decryptUserObjectKeyWithMigration(
                (string) $record['admin_share_key'],
                $session->get('user-private_key'),
                $session->get('user-public_key'),
                (int) $record['admin_key_id'],
                $def['table']
            );
            if (empty($objectKey)) {
                $failed++;
                continue;
            }
            try {
                insertOrUpdateSharekey(
                    prefixTable($def['table']),
                    (int) $record['object_id'],
                    (int) TP_USER_ID,
                    encryptUserObjectKey($objectKey, $tpUserPublicKey)
                );
                $seeded++;
            } catch (Exception $e) {
                $failed++;
                error_log('TEAMPASS Error - restore_missing_sharekeys-seed - object #' . $record['object_id'] . ' (' . $def['table'] . '): ' . $e->getMessage());
            }
        }

        echo prepareExchangedData(
            array(
                'error' => false,
                'scope' => $scopeName,
                'seeded' => $seeded,
                'failed' => $failed,
                'lastId' => $lastId,
                'finished' => count($rows) < $batchSize,
            ),
            'encode'
        );
        break;

    /*
    * RESTORE MISSING SHAREKEYS - STEP 3 - Launch the background repair task
    * The task distributes the missing sharekeys to all eligible users using
    * TP_USER as reference (server-side decryptable).
    */
    case 'restore_missing_sharekeys-launch':
        // Check KEY
        if (!hash_equals((string) $session->get('key'), (string) $post_key)) {
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
        if ((int) $session->get('user-admin') !== 1) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }

        // Refuse concurrent repair tasks
        DB::query(
            'SELECT increment_id FROM ' . prefixTable('background_tasks') . '
            WHERE process_type = %s AND (finished_at IS NULL OR finished_at = "")',
            'restore_missing_sharekeys'
        );
        if (DB::count() > 0) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('restore_missing_sharekeys_task_exists'),
                ),
                'encode'
            );
            break;
        }

        DB::insert(
            prefixTable('background_tasks'),
            array(
                'created_at' => time(),
                'process_type' => 'restore_missing_sharekeys',
                'arguments' => json_encode([
                    'author' => (int) $session->get('user-id'),
                ]),
            )
        );
        $taskId = DB::insertId();

        logEvents($SETTINGS, 'admin_action', 'restore_missing_sharekeys_started', (string) $session->get('user-id'), $session->get('user-login'));

        // Trigger immediate execution of the background handler
        triggerBackgroundHandler();

        echo prepareExchangedData(
            array(
                'error' => false,
                'message' => $lang->get('restore_missing_sharekeys_task_launched'),
                'taskId' => $taskId,
            ),
            'encode'
        );
        break;

}

/**
 * Definition of the object scopes handled by the "Restore missing sharekeys"
 * tool. Each scope maps an object source (aliased "o", personal items excluded)
 * to its sharekeys table.
 *
 * @return array<string, array{table: string, from: string, where: string}>
 */
function restoreSharekeysScopeDefs(): array
{
    return [
        'items' => [
            'table' => 'sharekeys_items',
            'from' => prefixTable('items') . ' AS o',
            'where' => 'o.perso = 0',
        ],
        'fields' => [
            'table' => 'sharekeys_fields',
            'from' => prefixTable('categories_items') . ' AS o INNER JOIN ' . prefixTable('items') . ' AS i ON (i.id = o.item_id AND i.perso = 0)',
            'where' => 'o.encryption_type = "' . TP_ENCRYPTION_NAME . '"',
        ],
        'files' => [
            'table' => 'sharekeys_files',
            'from' => prefixTable('files') . ' AS o INNER JOIN ' . prefixTable('items') . ' AS i ON (i.id = o.id_item AND i.perso = 0)',
            'where' => 'o.status = "' . TP_ENCRYPTION_NAME . '"',
        ],
    ];
}
