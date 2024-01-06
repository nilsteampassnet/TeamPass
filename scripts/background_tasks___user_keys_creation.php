<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 * @version   
 * @file      background_tasks___user_keys_creation.php
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

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request;
use TeampassClasses\Language\Language;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$lang = new Language(); 

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
error_reporting(E_ERROR);
// increase the maximum amount of time a script is allowed to run
set_time_limit($SETTINGS['task_maximum_run_time']);

// --------------------------------- //

require_once __DIR__.'/background_tasks___functions.php';

// Get PHP binary
$phpBinaryPath = getPHPBinary();

// log start
$logID = doLog('start', 'user_keys', (isset($SETTINGS['enable_tasks_log']) === true ? (int) $SETTINGS['enable_tasks_log'] : 0));


// Manage the tasks in queue.
// Task to treat selection is:
// 1- take first is_in_progress === 1
// 2- take first is_in_progress === 0 and finished_at === null
DB::debugmode(false);
$process_to_perform = DB::queryfirstrow(
    'SELECT *
    FROM ' . prefixTable('processes') . '
    WHERE is_in_progress = %i AND process_type = %s
    ORDER BY increment_id ASC',
    1,
    'create_user_keys'
);

if (DB::count() > 0) {
    // handle tasks inside this process
    //echo "Handle task<br>";
    handleTask(
        $process_to_perform['increment_id'],
        json_decode($process_to_perform['arguments'], true),
        $SETTINGS,
    );

} else {
    // search for next process to handle
    $process_to_perform = DB::queryfirstrow(
        'SELECT *
        FROM ' . prefixTable('processes') . '
        WHERE is_in_progress = %i AND finished_at = "" AND process_type = %s
        ORDER BY increment_id ASC',
        0,
        'create_user_keys'
    );
    //print_r($process_to_perform);
    if (DB::count() > 0) {
        // update DB - started_at
        DB::update(
            prefixTable('processes'),
            array(
                'started_at' => time(),
            ),
            'increment_id = %i',
            $process_to_perform['increment_id']
        );

        provideLog('[PROCESS][#'. $process_to_perform['increment_id'].'][START]', $SETTINGS);
        handleTask(
            $process_to_perform['increment_id'],
            json_decode($process_to_perform['arguments'], true),
            $SETTINGS,
        );
    }
}

// log end
doLog('end', '', (isset($SETTINGS['enable_tasks_log']) === true ? (int) $SETTINGS['enable_tasks_log'] : 0), $logID);

// launch a new iterative process
$process_to_perform = DB::queryfirstrow(
    'SELECT *
    FROM ' . prefixTable('processes') . '
    WHERE is_in_progress = %i AND process_type = %s
    ORDER BY increment_id ASC',
    1,
    'create_user_keys'
);
if (DB::count() > 0) {
    $process = new Symfony\Component\Process\Process([$phpBinaryPath, __FILE__]);
    $process->start();
    $process->wait();
}



/**
 * Undocumented function
 *
 * @param integer $processId
 * @param array $ProcessArguments
 * @param array $SETTINGS
 * @return bool
 */
function handleTask(int $processId, array $ProcessArguments, array $SETTINGS): bool
{
    provideLog('[PROCESS][#'. $processId.'][START]', $SETTINGS);
    //DB::debugmode(false);
    $task_to_perform = DB::queryfirstrow(
        'SELECT *
        FROM ' . prefixTable('processes_tasks') . '
        WHERE process_id = %i AND finished_at IS NULL
        ORDER BY increment_id ASC',
        $processId
    );
/*
    print_r($ProcessArguments);
    echo " ;; ".cryption($ProcessArguments['new_user_pwd'], '','decrypt', $SETTINGS)['string']." ;; ".cryption($ProcessArguments['new_user_code'], '','decrypt', $SETTINGS)['string']." ;; ";
    return false;
*/
    
    if (DB::count() > 0) {
        // check if a linux process is not currently on going
        // if sub_task_in_progress === 1 then exit
        if ((int) $task_to_perform['sub_task_in_progress'] === 0) {
            provideLog('[TASK][#'. $task_to_perform['increment_id'].'][START]', $SETTINGS);

            // handle next task
            $args = json_decode($task_to_perform['task'], true);
            //print_r($args);return false;

            // flag as in progress
            DB::update(
                prefixTable('processes'),
                array(
                    'updated_at' => time(),
                    'is_in_progress' => 1,
                ),
                'increment_id = %i',
                $processId
            );

            // flag task as on going
            if ((int) $args['index'] === 0) {
                DB::update(
                    prefixTable('processes_tasks'),
                    array(
                        'is_in_progress' => 1,
                    ),
                    'increment_id = %i',
                    $task_to_perform['increment_id']
                );
            }

            // flag sub task in progress as on going
            DB::update(
                prefixTable('processes_tasks'),
                array(
                    'sub_task_in_progress' => 1,
                ),
                'increment_id = %i',
                $task_to_perform['increment_id']
            );

            $taskStatus = performUserCreationKeys(
                (int) $ProcessArguments['new_user_id'],
                (bool) false,
                (string) $args['step'],
                (int) $args['index'],
                (int) isset($SETTINGS['maximum_number_of_items_to_treat']) === true ? $SETTINGS['maximum_number_of_items_to_treat'] : $args['nb'],
                $SETTINGS,
                $ProcessArguments
            );

            // update the task status
            DB::update(
                prefixTable('processes_tasks'),
                array(
                    'sub_task_in_progress' => 0,    // flag sub task is no more in prgoress
                    'task' => $taskStatus['new_action'] !== $args['step'] ? 
                    json_encode(["status" => "Done"]) :
                    json_encode([
                        "step" => $taskStatus['new_action'],
                        "index" => $taskStatus['new_index'],
                        "nb" => isset($SETTINGS['maximum_number_of_items_to_treat']) === true ? $SETTINGS['maximum_number_of_items_to_treat'] : $args['nb'],
                    ]),
                    'is_in_progress' => $taskStatus['new_action'] !== $args['step'] ? -1 : 1,
                    'finished_at' => $taskStatus['new_action'] !== $args['step'] ? time() : NULL,
                    'updated_at' => time(),
                ),
                'increment_id = %i',
                $task_to_perform['increment_id']
            );

            provideLog('[TASK]['.$args['step'].'] starting at '.$args['index'].' is done.', $SETTINGS);

            if ($args['step'] === 'step60') {
                // all done
                provideLog('[PROCESS]['.$processId.'][FINISHED]', $SETTINGS);
                DB::debugmode(false);
                DB::update(
                    prefixTable('processes'),
                    array(
                        'finished_at' => time(),
                        'is_in_progress' => -1,
                        'arguments' => json_encode([
                            'new_user_id' => $ProcessArguments['new_user_id'],
                        ])
                    ),
                    'increment_id = %i',
                    $processId
                );
            }
            return false;

        } else {
            // Task is currently being in progress by another server process
            provideLog('[TASK][#'. $task_to_perform['increment_id'].'][WARNING] Similar task already being processes', $SETTINGS);
            return false;
        }
    }
    return true;
}

/**
 * Permits to encrypt user's keys
 *
 * @param integer $post_user_id
 * @param boolean $post_self_change
 * @param string $post_action
 * @param integer $post_start
 * @param integer $post_length
 * @param array $SETTINGS
 * @param array $extra_arguments
 * @return array
 */
function performUserCreationKeys(
    int     $post_user_id,
    bool    $post_self_change,
    string  $post_action,
    int     $post_start,
    int     $post_length,
    array   $SETTINGS,
    array   $extra_arguments
): array
{
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
                //echo '<br>Start STEP0<br>';
                // CLear old sharekeys
                if ($post_self_change === false) {
                    deleteUserObjetsKeys($post_user_id, $SETTINGS);
                }

                $return['new_action'] = 'step10';
                $return['new_index'] = 0;
                provideLog('[STEP][0][FINISHED]', $SETTINGS);
            }
            
            // STEP 10 - EMAIL
            elseif ($post_action === 'step10') {
                provideLog('[STEP][10][START][INDEX]['.$post_start.']', $SETTINGS);
                $return = cronContinueReEncryptingUserSharekeysStep10(
                    $post_user_id,
                    $SETTINGS,
                    $extra_arguments
                );
                provideLog('[STEP][10][FINISHED]', $SETTINGS);
            }
            
            // STEP 20 - ITEMS
            elseif ($post_action === 'step20') {
                provideLog('[STEP][20][START][INDEX]['.$post_start.']', $SETTINGS);
                $logID = doLog('start', 'user_keys-ITEMS', (isset($SETTINGS['enable_tasks_log']) === true ? (int) $SETTINGS['enable_tasks_log'] : 0));
                $return = cronContinueReEncryptingUserSharekeysStep20(
                    $post_user_id,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS,
                    $extra_arguments
                );
                doLog('end', '', (isset($SETTINGS['enable_tasks_log']) === true ? (int) $SETTINGS['enable_tasks_log'] : 0), $logID, $return['treated_items']);
                provideLog('[STEP][20][FINISHED]', $SETTINGS);
            }

            // STEP 30 - LOGS
            elseif ($post_action === 'step30') {
                provideLog('[STEP][30][START][INDEX]['.$post_start.']', $SETTINGS);
                $return = cronContinueReEncryptingUserSharekeysStep30(
                    $post_user_id,
                    $post_self_change,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS,
                    $extra_arguments
                );
                provideLog('[STEP][30][FINISHED]', $SETTINGS);
            }

            // STEP 40 - FIELDS
            elseif ($post_action === 'step40') {
                provideLog('[STEP][40][START][INDEX]['.$post_start.']', $SETTINGS);
                $return = cronContinueReEncryptingUserSharekeysStep40(
                    $post_user_id,
                    $post_self_change,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS,
                    $extra_arguments
                );
                provideLog('[STEP][40][FINISHED]', $SETTINGS);
            }
            
            // STEP 50 - SUGGESTIONS
            elseif ($post_action === 'step50') {
                provideLog('[STEP][50][START][INDEX]['.$post_start.']', $SETTINGS);
                $return = cronContinueReEncryptingUserSharekeysStep50(
                    $post_user_id,
                    $post_self_change,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS,
                    $extra_arguments
                );
                provideLog('[STEP][50][FINISHED]', $SETTINGS);
            }
            
            // STEP 60 - FILES
            elseif ($post_action === 'step60') {
                provideLog('[STEP][60][START][INDEX]['.$post_start.']', $SETTINGS);
                $return = cronContinueReEncryptingUserSharekeysStep60(
                    $post_user_id,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS,
                    $extra_arguments
                );
                provideLog('[STEP][60][FINISHED]', $SETTINGS);
            }
            
            // Continu with next step
            return $return;
        }
        
        // Nothing to do
        provideLog('[USER][ERROR] No user public key', $SETTINGS);
        return [
            'error' => true,
            'new_action' => 'finished',
        ];
    }
    provideLog('[USER][ERROR] No user found', $SETTINGS);
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
    provideLog('[USER][INFO] ID:'.$owner_id, $SETTINGS);
    //provideLog('[DEBUG] '.$pwd." -- ", $SETTINGS);
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
        /*if ($post_self_change === false) {
            DB::insert(
                prefixTable('sharekeys_files'),
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
                    FROM ' . prefixTable('sharekeys_files') . '
                    WHERE object_id = %i AND user_id = %i',
                    $record['id'],
                    $post_user_id
                );
            }

            // NOw update
            DB::update(
                prefixTable('sharekeys_files'),
                array(
                    'share_key' => $share_key_for_item,
                ),
                'increment_id = %i',
                $currentUserKey['increment_id']
            );
        }*/
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
    $lang = new Language();

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


/**
 * SEnd email to user
 *
 * @param string $post_receipt
 * @param string $post_body
 * @param string $post_subject
 * @param array $post_replace
 * @return void
 */
function sendMailToUser(
    string $post_receipt,
    string $post_body,
    string $post_subject,
    array $post_replace
): void
{
    if (count($post_replace) > 0 && is_null($post_replace) === false) {
        $post_body = str_replace(
            array_keys($post_replace),
            array_values($post_replace),
            $post_body
        );
    }

    prepareSendingEmail(
        $post_subject,
        $post_body,
        $post_receipt,
        ""
    );
}