<?php
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
 * @file      background_tasks___userKeysCreation_subtaskHdl.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use Symfony\Component\HttpFoundation\Request;
use TeampassClasses\Language\Language;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$request = Request::createFromGlobals();

// Load config if $SETTINGS not defined
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
error_reporting(E_ERROR);
// increase the maximum amount of time a script is allowed to run
set_time_limit($SETTINGS['task_maximum_run_time']);

// --------------------------------- //

// This subtask is used to create the user keys
// It is called by the main task
// Once all items are processed, the subtask is marked as FINISHED

$arguments = $_SERVER['argv'];
array_shift($arguments);


$inputData = [
    'subTaskId' => $_SERVER['argv'][1],
    'index' => $_SERVER['argv'][2],
    'nb' => $_SERVER['argv'][3],
    'step' => $_SERVER['argv'][4],
    'taskArguments' => $_SERVER['argv'][5],
    'taskId' => $_SERVER['argv'][6],
];

$filters = [
    'subTaskId' => 'trim|escape',
    'index' => 'trim|escape',
    'nb' => 'trim|escape',
    'step' => 'trim|escape',
    'taskArguments' => 'trim|strip_tags',
    'taskId' => 'trim|escape',
];

$taskArgs = json_decode($inputData['taskArguments'], true);
/*
$fichier = fopen(__DIR__.'/log.txt', 'a');
fwrite($fichier, 'taskArgs: '.print_r($inputData, true)."\n");
fclose($fichier);
*/

// update DB - started_at
if ((int) $inputData['index'] === 0) {
    DB::update(
        prefixTable('background_tasks'),
        array(
            'started_at' => time(),
        ),
        'increment_id = %i',
        $inputData['taskId']
    );
}

$taskStatus = performUserCreationKeys(
    (bool) false,
    (string) $inputData['step'],
    (int) $inputData['index'],
    (int) $inputData['nb'],
    $SETTINGS,
    $taskArgs
);

// update the subtask status
DB::update(
    prefixTable('background_subtasks'),
    array(
        'sub_task_in_progress' => 0,    // flag sub task is no more in prgoress
        'is_in_progress' => 0,
        'finished_at' => time(),
        'updated_at' => time(),
    ),
    'increment_id = %i',
    $inputData['subTaskId']
);


/**
 * Permits to encrypt user's keys
 *
 * @param boolean $post_self_change
 * @param string $post_action
 * @param integer $post_start
 * @param integer $post_length
 * @param array $SETTINGS
 * @param array $extra_arguments
 * @return array
 */
function performUserCreationKeys(
    bool    $post_self_change,
    string  $post_action,
    int     $post_start,
    int     $post_length,
    array   $SETTINGS,
    array   $extra_arguments
): array
{
    $post_user_id = $extra_arguments['new_user_id'];

    if (isUserIdValid($post_user_id) === true) {
        // Check if user exists
        $userInfo = DB::queryFirstRow(
            'SELECT public_key, private_key
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );
        
        if (isset($userInfo['public_key']) === true) {
            $return = [];

            // WHAT STEP TO PERFORM?
            if ($post_action === 'step0') {
                // CLear old sharekeys
                if ($post_self_change === false) {
                    deleteUserObjetsKeys($post_user_id, $SETTINGS);
                }

                $return['new_action'] = 'step10';
                $return['new_index'] = 0;
            }
            
            // STEP 10 - EMAIL
            elseif ($post_action === 'step10') {
                $return = cronContinueReEncryptingUserSharekeysStep10(
                    $post_user_id,
                    $SETTINGS,
                    $extra_arguments
                );
            }
            
            // STEP 20 - ITEMS
            elseif ($post_action === 'step20') {
                $return = cronContinueReEncryptingUserSharekeysStep20(
                    $post_user_id,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS,
                    $extra_arguments
                );
            }

            // STEP 30 - LOGS
            elseif ($post_action === 'step30') {
                $return = cronContinueReEncryptingUserSharekeysStep30(
                    $post_user_id,
                    $post_self_change,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS,
                    $extra_arguments
                );
            }

            // STEP 40 - FIELDS
            elseif ($post_action === 'step40') {
                $return = cronContinueReEncryptingUserSharekeysStep40(
                    $post_user_id,
                    $post_self_change,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS,
                    $extra_arguments
                );
            }
            
            // STEP 50 - SUGGESTIONS
            elseif ($post_action === 'step50') {
                $return = cronContinueReEncryptingUserSharekeysStep50(
                    $post_user_id,
                    $post_self_change,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS,
                    $extra_arguments
                );
            }
            
            // STEP 60 - FILES
            elseif ($post_action === 'step60') {
                $return = cronContinueReEncryptingUserSharekeysStep60(
                    $post_user_id,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS,
                    $extra_arguments
                );
            }
            
            // Continu with next step
            return $return;
        }
        
        // Nothing to do
        //provideLog('[USER][ERROR] No user public key', $SETTINGS);
        return [
            'error' => true,
            'new_action' => 'finished',
        ];
    }

    return [
        'error' => true,
        'new_action' => 'finished',
    ];
}

function getOwnerInfo(int $owner_id, string $owner_pwd, array $SETTINGS): array
{
    $userInfo = DB::queryFirstRow(
        'SELECT pw, public_key, private_key, login, name
        FROM ' . prefixTable('users') . '
        WHERE id = %i',
        $owner_id
    );
    
    // decrypt owner password
    $pwd = cryption($owner_pwd, '','decrypt', $SETTINGS)['string'];
    // decrypt private key and send back
    return [
        'private_key' => decryptPrivateKey($pwd, $userInfo['private_key']),
        'login' => $userInfo['login'],
        'name' => $userInfo['name'],
    ];
}


/**
 * Handle step 1
 *
 * @param integer $post_user_id
 * @param integer $post_start
 * @param integer $post_length
 * @param string $user_public_key
 * @param array $SETTINGS
 * @param array $extra_arguments
 * @return array
 */
function cronContinueReEncryptingUserSharekeysStep20(
    int $post_user_id,
    int $post_start,
    int $post_length,
    string $user_public_key,
    array $SETTINGS,
    array $extra_arguments
): array 
{    
    // get user private key
    $ownerInfo = getOwnerInfo($extra_arguments['owner_id'], $extra_arguments['creator_pwd'], $SETTINGS);
    $userInfo = getOwnerInfo($extra_arguments['new_user_id'], $extra_arguments['new_user_pwd'], $SETTINGS);

    // Start transaction for better performance
    DB::startTransaction();

    // Loop on items
    $rows = DB::query(
        'SELECT id, pw, perso
        FROM ' . prefixTable('items') . '
        '.(isset($extra_arguments['only_personal_items']) === true && $extra_arguments['only_personal_items'] === 1 ? 'WHERE perso = 1' : '').'
        ORDER BY id ASC
        LIMIT ' . $post_start . ', ' . $post_length
    );
    //    WHERE perso = 0
    foreach ($rows as $record) {
        // Get itemKey from current user
        $currentUserKey = DB::queryFirstRow(
            'SELECT share_key, increment_id
            FROM ' . prefixTable('sharekeys_items') . '
            WHERE object_id = %i AND user_id = %i',
            $record['id'],
            //$extra_arguments['owner_id']
            (int) $record['perso'] === 0 ? $extra_arguments['owner_id'] : $extra_arguments['new_user_id']
        );

        // do we have any input? (#3481)
        if ($currentUserKey === null || count($currentUserKey) === 0) {
            continue;
        }

        // Decrypt itemkey with admin key
        $itemKey = decryptUserObjectKey(
            $currentUserKey['share_key'],
            //$ownerInfo['private_key']
            (int) $record['perso'] === 0 ? $ownerInfo['private_key'] : $userInfo['private_key']
        );
        
        // Prevent to change key if its key is empty
        if (empty($itemKey) === true) {
            continue;
        }

        // Encrypt Item key
        $share_key_for_item = encryptUserObjectKey($itemKey, $user_public_key);
        
        $currentUserKey = DB::queryFirstRow(
            'SELECT increment_id
            FROM ' . prefixTable('sharekeys_items') . '
            WHERE object_id = %i AND user_id = %i',
            $record['id'],
            $post_user_id
        );

        if (DB::count() > 0) {
            // NOw update
            DB::update(
                prefixTable('sharekeys_items'),
                array(
                    'share_key' => $share_key_for_item,
                ),
                'increment_id = %i',
                $currentUserKey['increment_id']
            );
        } else {
            DB::insert(
                prefixTable('sharekeys_items'),
                array(
                    'object_id' => (int) $record['id'],
                    'user_id' => (int) $post_user_id,
                    'share_key' => $share_key_for_item,
                )
            );
        }
    }

    // SHould we change step?
    DB::query(
        'SELECT *
        FROM ' . prefixTable('items')
    );

    // Commit transaction
    DB::commit();

    $next_start = (int) $post_start + (int) $post_length;
    return [
        'new_index' => $next_start > DB::count() ? 0 : $next_start,
        'new_action' => $next_start > DB::count() ? 'step30' : 'step20',
        'treated_items' => $next_start > DB::count() ? (DB::count() - (int) $post_start) : $post_length,
    ];
}


/**
 * Handle step 2
 *
 * @param integer $post_user_id
 * @param boolean $post_self_change
 * @param integer $post_start
 * @param integer $post_length
 * @param string $user_public_key
 * @param array $SETTINGS
 * @param array $extra_arguments
 * @return array
 */
function cronContinueReEncryptingUserSharekeysStep30(
    int $post_user_id,
    bool $post_self_change,
    int $post_start,
    int $post_length,
    string $user_public_key,
    array $SETTINGS,
    array $extra_arguments
): array
{
    // get user private key
    $ownerInfo = getOwnerInfo($extra_arguments['owner_id'], $extra_arguments['creator_pwd'], $SETTINGS);

    // Loop on logs
    $rows = DB::query(
        'SELECT increment_id
        FROM ' . prefixTable('log_items') . '
        WHERE raison LIKE "at_pw :%" AND encryption_type = "teampass_aes"
        LIMIT ' . $post_start . ', ' . $post_length
    );
    foreach ($rows as $record) {
        // Get itemKey from current user
        $currentUserKey = DB::queryFirstRow(
            'SELECT share_key
            FROM ' . prefixTable('sharekeys_logs') . '
            WHERE object_id = %i AND user_id = %i',
            $record['increment_id'],
            $extra_arguments['owner_id']
        );

        // do we have any input? (#3481)
        if ($currentUserKey === null || count($currentUserKey) === 0) {
            continue;
        }

        // Decrypt itemkey with admin key
        $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $ownerInfo['private_key']);

        // Encrypt Item key
        $share_key_for_item = encryptUserObjectKey($itemKey, $user_public_key);

        // Save the key in DB
        if ($post_self_change === false) {
            DB::insert(
                prefixTable('sharekeys_logs'),
                array(
                    'object_id' => (int) $record['increment_id'],
                    'user_id' => (int) $post_user_id,
                    'share_key' => $share_key_for_item,
                )
            );
        } else {
            // Get itemIncrement from selected user
            if ((int) $post_user_id !== (int) $extra_arguments['owner_id']) {
                $currentUserKey = DB::queryFirstRow(
                    'SELECT increment_id
                    FROM ' . prefixTable('sharekeys_items') . '
                    WHERE object_id = %i AND user_id = %i',
                    $record['id'],
                    $post_user_id
                );
            }

            // NOw update
            DB::update(
                prefixTable('sharekeys_logs'),
                array(
                    'share_key' => $share_key_for_item,
                ),
                'increment_id = %i',
                $currentUserKey['increment_id']
            );
        }
    }

    // SHould we change step?
    DB::query(
        'SELECT increment_id
        FROM ' . prefixTable('log_items') . '
        WHERE raison LIKE "at_pw :%" AND encryption_type = "teampass_aes"'
    );

    $next_start = (int) $post_start + (int) $post_length;
    return [
        'new_index' => $next_start > DB::count() ? 0 : $next_start,
        'new_action' => $next_start > DB::count() ? 'step40' : 'step30',
    ];
}


/**
 * Handle step 3 of re-encrypting user sharekeys
 *
 * @param integer $post_user_id
 * @param boolean $post_self_change
 * @param integer $post_start
 * @param integer $post_length
 * @param string $user_public_key
 * @param array $SETTINGS
 * @param array $extra_arguments
 * @return array
 */
function cronContinueReEncryptingUserSharekeysStep40(
    int $post_user_id,
    bool $post_self_change,
    int $post_start,
    int $post_length,
    string $user_public_key,
    array $SETTINGS,
    array $extra_arguments
): array
{
    // get user private key
    $ownerInfo = getOwnerInfo($extra_arguments['owner_id'], $extra_arguments['creator_pwd'], $SETTINGS);

    // Loop on fields
    $rows = DB::query(
        'SELECT id
        FROM ' . prefixTable('categories_items') . '
        WHERE encryption_type = "teampass_aes"
        LIMIT ' . $post_start . ', ' . $post_length
    );
    foreach ($rows as $record) {
        // Get itemKey from current user
        $currentUserKey = DB::queryFirstRow(
            'SELECT share_key
            FROM ' . prefixTable('sharekeys_fields') . '
            WHERE object_id = %i AND user_id = %i',
            $record['id'],
            $extra_arguments['owner_id']
        );

        if (isset($currentUserKey['share_key']) === true) {
            // Decrypt itemkey with admin key
            $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $ownerInfo['private_key']);

            // Encrypt Item key
            $share_key_for_item = encryptUserObjectKey($itemKey, $user_public_key);

            // Save the key in DB
            if ($post_self_change === false) {
                DB::insert(
                    prefixTable('sharekeys_fields'),
                    array(
                        'object_id' => (int) $record['id'],
                        'user_id' => (int) $post_user_id,
                        'share_key' => $share_key_for_item,
                    )
                );
            } else {
                // Get itemIncrement from selected user
                if ((int) $post_user_id !== (int) $extra_arguments['owner_id']) {
                    $currentUserKey = DB::queryFirstRow(
                        'SELECT increment_id
                        FROM ' . prefixTable('sharekeys_items') . '
                        WHERE object_id = %i AND user_id = %i',
                        $record['id'],
                        $post_user_id
                    );
                }

                // NOw update
                DB::update(
                    prefixTable('sharekeys_fields'),
                    array(
                        'share_key' => $share_key_for_item,
                    ),
                    'increment_id = %i',
                    $currentUserKey['increment_id']
                );
            }
        }
    }

    // SHould we change step?
    DB::query(
        'SELECT *
        FROM ' . prefixTable('categories_items') . '
        WHERE encryption_type = "teampass_aes"'
    );

    $next_start = (int) $post_start + (int) $post_length;
    return [
        'new_index' => $next_start > DB::count() ? 0 : $next_start,
        'new_action' => $next_start > DB::count() ? 'step50' : 'step40',
    ];
}


/**
 * Handle step 4
 *
 * @param integer $post_user_id
 * @param boolean $post_self_change
 * @param integer $post_start
 * @param integer $post_length
 * @param string $user_public_key
 * @param array $SETTINGS
 * @param array $extra_arguments
 * @return array
 */
function cronContinueReEncryptingUserSharekeysStep50(
    int $post_user_id,
    bool $post_self_change,
    int $post_start,
    int $post_length,
    string $user_public_key,
    array $SETTINGS,
    array $extra_arguments
): array
{
    // get user private key
    $ownerInfo = getOwnerInfo($extra_arguments['owner_id'], $extra_arguments['creator_pwd'], $SETTINGS);

    // Loop on suggestions
    $rows = DB::query(
        'SELECT id
        FROM ' . prefixTable('suggestion') . '
        LIMIT ' . $post_start . ', ' . $post_length
    );
    foreach ($rows as $record) {
        // Get itemKey from current user
        $currentUserKey = DB::queryFirstRow(
            'SELECT share_key
            FROM ' . prefixTable('sharekeys_suggestions') . '
            WHERE object_id = %i AND user_id = %i',
            $record['id'],
            $extra_arguments['owner_id']
        );

        // do we have any input? (#3481)
        if ($currentUserKey === null || count($currentUserKey) === 0) {
            continue;
        }

        // Decrypt itemkey with admin key
        $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $ownerInfo['private_key']);

        // Encrypt Item key
        $share_key_for_item = encryptUserObjectKey($itemKey, $user_public_key);

        // Save the key in DB
        if ($post_self_change === false) {
            DB::insert(
                prefixTable('sharekeys_suggestions'),
                array(
                    'object_id' => (int) $record['id'],
                    'user_id' => (int) $post_user_id,
                    'share_key' => $share_key_for_item,
                )
            );
        } else {
            // Get itemIncrement from selected user
            if ((int) $post_user_id !== (int) $extra_arguments['owner_id']) {
                $currentUserKey = DB::queryFirstRow(
                    'SELECT increment_id
                    FROM ' . prefixTable('sharekeys_items') . '
                    WHERE object_id = %i AND user_id = %i',
                    $record['id'],
                    $post_user_id
                );
            }

            // NOw update
            DB::update(
                prefixTable('sharekeys_suggestions'),
                array(
                    'share_key' => $share_key_for_item,
                ),
                'increment_id = %i',
                $currentUserKey['increment_id']
            );
        }
    }

    // SHould we change step?
    DB::query(
        'SELECT *
        FROM ' . prefixTable('suggestion')
    );

    $next_start = (int) $post_start + (int) $post_length;
    return [
        'new_index' => $next_start > DB::count() ? 0 : $next_start,
        'new_action' => $next_start > DB::count() ? 'step60' : 'step50',
    ];
}


/**
 * Handle step 5
 *
 * @param integer $post_user_id
 * @param integer $post_start
 * @param integer $post_length
 * @param string $user_public_key
 * @param array $SETTINGS
 * @param array $extra_arguments
 * @return array
 */
function cronContinueReEncryptingUserSharekeysStep60(
    int $post_user_id,
    int $post_start,
    int $post_length,
    string $user_public_key,
    array $SETTINGS,
    array $extra_arguments
): array
{
    // get user private key
    $ownerInfo = getOwnerInfo($extra_arguments['owner_id'], $extra_arguments['creator_pwd'], $SETTINGS);
    $userInfo = getOwnerInfo($extra_arguments['new_user_id'], $extra_arguments['new_user_pwd'], $SETTINGS);

    // Loop on files
    $rows = DB::query(
        'SELECT f.id AS id, i.perso AS perso
        FROM ' . prefixTable('files') . ' AS f
        INNER JOIN ' . prefixTable('items') . ' AS i ON i.id = f.id_item
        WHERE f.status = "' . TP_ENCRYPTION_NAME . '"
        LIMIT ' . $post_start . ', ' . $post_length
    ); //aes_encryption
    foreach ($rows as $record) {
        // Get itemKey from current user
        $currentUserKey = DB::queryFirstRow(
            'SELECT share_key, increment_id
            FROM ' . prefixTable('sharekeys_files') . '
            WHERE object_id = %i AND user_id = %i',
            $record['id'],
            (int) $record['perso'] === 0 ? $extra_arguments['owner_id'] : $extra_arguments['new_user_id']
        );

        // do we have any input? (#3481)
        if ($currentUserKey === null || count($currentUserKey) === 0) {
            continue;
        }

        // Decrypt itemkey with user key
        $itemKey = decryptUserObjectKey(
            $currentUserKey['share_key'],
            //$ownerInfo['private_key']
            (int) $record['perso'] === 0 ? $ownerInfo['private_key'] : $userInfo['private_key']
        );
        
        // Prevent to change key if its key is empty
        if (empty($itemKey) === true) {
            continue;
        }

        // Encrypt Item key
        $share_key_for_item = encryptUserObjectKey($itemKey, $user_public_key);

        $currentUserKey = DB::queryFirstRow(
            'SELECT increment_id
            FROM ' . prefixTable('sharekeys_files') . '
            WHERE object_id = %i AND user_id = %i',
            $record['id'],
            $post_user_id
        );
        // Save the key in DB
        if (DB::count() > 0) {
            // NOw update
            DB::update(
                prefixTable('sharekeys_files'),
                array(
                    'share_key' => $share_key_for_item,
                ),
                'increment_id = %i',
                $currentUserKey['increment_id']
            );
        } else {
            DB::insert(
                prefixTable('sharekeys_files'),
                array(
                    'object_id' => (int) $record['id'],
                    'user_id' => (int) $post_user_id,
                    'share_key' => $share_key_for_item,
                )
            );
        }
    }

    // SHould we change step? Finished ?
    DB::query(
        'SELECT *
        FROM ' . prefixTable('files') . '
        WHERE status = "' . TP_ENCRYPTION_NAME . '"'
    );
    $counter = DB::count();
    $next_start = (int) $post_start + (int) $post_length;

    if ($next_start > $counter) {
        // Set user as ready for usage
        DB::update(
            prefixTable('users'),
            array(
                'ongoing_process_id' => NULL,
                'special' => 'none',
            ),
            'id = %i',
            $extra_arguments['new_user_id']
        );
    }

    return [
        'new_index' => $next_start > $counter ? 0 : $next_start,
        'new_action' => $next_start > $counter ? 'finished' : 'step60',
    ];
}


/**
 * Handle step 6
 *
 * @param integer $post_user_id
 * @param integer $post_start
 * @param integer $post_length
 * @param string $user_public_key
 * @param array $SETTINGS
 * @param array $extra_arguments
 * @return array
 */
function cronContinueReEncryptingUserSharekeysStep10(
    int $post_user_id,
    array $SETTINGS,
    array $extra_arguments
): array
{
    $lang = new Language('english');

    // IF USER IS NOT THE SAME
    if ((int) $post_user_id === (int) $extra_arguments['owner_id']) {
        return [
            'new_index' => 0,
            'new_action' => 'finished',
        ];
    }
    
    // update LOG
    logEvents(
        $SETTINGS,
        'user_mngt',
        'at_user_new_keys',
        TP_USER_ID,
        "",
        (string) $extra_arguments['new_user_id']
    );

    // if done then send email to new user
    // get user info
    $userInfo = DB::queryFirstRow(
        'SELECT email, login, auth_type, special, lastname, name
        FROM ' . prefixTable('users') . '
        WHERE id = %i',
        $extra_arguments['new_user_id']
    );

    // SEND EMAIL TO USER depending on context
    // Config 1: new user is a local user
    // Config 2: new user is an LDAP user
    // Config 3: send new password
    // COnfig 4: send new encryption code
    if (isset($extra_arguments['send_email']) === true && (int) $extra_arguments['send_email'] === 1) {
        sendMailToUser(
            filter_var($userInfo['email'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            empty($extra_arguments['email_body']) === false ? $extra_arguments['email_body'] : $lang->get('email_body_user_config_1'),
            'TEAMPASS - ' . $lang->get('login_credentials'),
            (array) filter_var_array(
                [
                    '#code#' => cryption($extra_arguments['new_user_code'], '','decrypt', $SETTINGS)['string'],
                    '#lastname#' => isset($userInfo['name']) === true ? $userInfo['name'] : '',
                    '#login#' => isset($userInfo['login']) === true ? $userInfo['login'] : '',
                    '#password#' => cryption($extra_arguments['new_user_pwd'], '','decrypt', $SETTINGS)['string'],
                ],
                FILTER_SANITIZE_FULL_SPECIAL_CHARS
            )
        );
    }
        
    // Set user as ready for usage
    DB::update(
        prefixTable('users'),
        array(
            'is_ready_for_usage' => 1,
            'otp_provided' => isset($extra_arguments['otp_provided_new_value']) === true && (int) $extra_arguments['otp_provided_new_value'] === 1 ? $extra_arguments['otp_provided_new_value'] : 0
        ),
        'id = %i',
        $extra_arguments['new_user_id']
    );

    return [
        'new_index' => 0,
        'new_action' => 'step20',
    ];
}
