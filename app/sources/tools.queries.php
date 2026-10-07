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
// Read raw, like an encrypted payload: each field is sanitized after decoding.
// FILTER_SANITIZE_FULL_SPECIAL_CHARS acts as htmlentities() and stored "é" as "&eacute;".
$post_data = filter_input(INPUT_POST, 'data', FILTER_UNSAFE_RAW);

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
        FROM ' . prefixTable('nested_tree') . '
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
        from ' . prefixTable('sharekeys_items') . ' as s
        INNER JOIN ' . prefixTable('items') . ' AS i ON (i.id = s.object_id)
        WHERE s.user_id = %i AND i.perso = 1 AND i.id_tree IN %ls',
        $userId,
        $personalFolders
    );
    $pfiSharekeysCount = DB::count();
    if ($pfiSharekeysCount > 0) {
        DB::delete(
            prefixTable('sharekeys_items'),
            "increment_id IN %ls",
            $pfiSharekeys
        );
    }

    
    //Show done
    echo prepareExchangedData(
        array(
            'error' => false,
            // No markup here - the caller encodes this message and owns the line break.
            'message' => 'Number of Sharekeys for private items DELETED: ',
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
        FROM " . prefixTable('items') . "
        WHERE id_tree IN %ls AND encryption_type = %s",
        $personalFolders,
        "teampass_aes"
    );
    //DB::debugMode(false);
    $nbItems = DB::count();
    foreach ($items as $item) {
        $defusePwd = DB::queryFirstField("SELECT pw FROM " . prefixTable('items_old') . " WHERE id = %i", $item['id']);
        DB::update(
            prefixTable('items'),
            ['pw' => $defusePwd, "encryption_type" => "defuse"],
            "id = %i",
            $item['id']
        );
    }

    
    //Show done
    echo prepareExchangedData(
        array(
            'error' => false,
            // No markup here - the caller encodes this message and owns the line break.
            'message' => 'Number of items reseted to Defuse: ',
            'nbItems' => $nbItems,
            'personalFolders' => json_encode($personalFolders),
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

        // Whose keys the repair will open the objects with: yours by default, or the reference
        // user chosen on the page. Counting needs no password, only the user's sharekeys.
        $sourceUserId = (int) $session->get('user-id');
        if (is_string($post_data) === true && $post_data !== '') {
            $dataReceived = prepareExchangedData($post_data, 'decode');
            $requestedSourceId = is_array($dataReceived) === true ? (int) ($dataReceived['sourceUserId'] ?? 0) : 0;
            if ($requestedSourceId > 0 && restoreSharekeysSourceUser($requestedSourceId) !== null) {
                $sourceUserId = $requestedSourceId;
            }
        }

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
                $sourceUserId
            );

            $analysis[$scopeName] = [
                'objects' => $objectsCount,
                'missing_pairs' => max(0, $objectsCount * $eligibleUsersCount - $existingKeys),
                'tp_missing' => $tpMissing,
                'admin_seedable' => $adminSeedable,
                'unrecoverable' => max(0, $tpMissing - $adminSeedable),
            ];
        }

        // Personal objects are a different problem, so they get their own table. Only the owner and
        // the internal account may hold a key (SEC-8), which splits them into three very different
        // situations: the repair can rebuild the owner's key from the internal one, only the owner
        // can rebuild the internal one from theirs, and when both are gone nobody can do either.
        $nonOwnerIds = array_merge($specialUserIds, [TP_USER_ID]);
        $personalAnalysis = [];
        foreach (restoreSharekeysScopeDefs(true) as $scopeName => $def) {
            $personalObjects = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . $def['from'] . ' WHERE ' . $def['where']
            );
            // No user other than the internal accounts holds a usable key: the owner cannot read it.
            $ownerMissing = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . $def['from'] . '
                LEFT JOIN ' . prefixTable($def['table']) . ' AS sk ON (sk.object_id = o.id AND sk.user_id NOT IN %li AND sk.share_key != "" AND sk.encryption_version = 3)
                WHERE ' . $def['where'] . ' AND sk.increment_id IS NULL',
                $nonOwnerIds
            );
            // No internal reference key: nothing server side can open the object.
            $tpMissing = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . $def['from'] . '
                LEFT JOIN ' . prefixTable($def['table']) . ' AS sktp ON (sktp.object_id = o.id AND sktp.user_id = ' . TP_USER_ID . ' AND sktp.share_key != "")
                WHERE ' . $def['where'] . ' AND sktp.increment_id IS NULL'
            );
            // Neither: no key left at all.
            $noKeyAtAll = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . $def['from'] . '
                LEFT JOIN ' . prefixTable($def['table']) . ' AS sk ON (sk.object_id = o.id AND sk.user_id NOT IN %li AND sk.share_key != "" AND sk.encryption_version = 3)
                LEFT JOIN ' . prefixTable($def['table']) . ' AS sktp ON (sktp.object_id = o.id AND sktp.user_id = ' . TP_USER_ID . ' AND sktp.share_key != "")
                WHERE ' . $def['where'] . ' AND sk.increment_id IS NULL AND sktp.increment_id IS NULL',
                $nonOwnerIds
            );

            $personalAnalysis[$scopeName] = [
                'objects' => $personalObjects,
                'owner_missing' => $ownerMissing,
                // Owner key gone, reference key still there: this repair rebuilds it.
                'repairable' => max(0, $ownerMissing - $noKeyAtAll),
                // Reference key gone, owner can still read: only the owner can rebuild it.
                'needs_owner' => max(0, $tpMissing - $noKeyAtAll),
                'unrecoverable' => $noKeyAtAll,
            ];
        }

        echo prepareExchangedData(
            array(
                'error' => false,
                'eligible_users' => $eligibleUsersCount,
                'analysis' => $analysis,
                'personal_analysis' => $personalAnalysis,
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

        // Personal objects with no internal reference key - the ones this repair cannot touch. Naming
        // the owner is the whole point of the list: when the owner can still read the object they
        // rebuild the reference key themselves from their profile, and when they cannot, nothing
        // automatic is left. The two need different words, so the row says which case it is.
        $nonOwnerIds = array_merge($specialUserIds, [TP_USER_ID]);
        $personalDetails = [];
        foreach (restoreSharekeysScopeDefs(true) as $scopeName => $def) {
            $ownerJoins = ' LEFT JOIN ' . prefixTable('nested_tree') . ' AS pfolder ON pfolder.id = ' . $def['itemAlias'] . '.id_tree
                LEFT JOIN ' . prefixTable('nested_tree') . ' AS proot ON (proot.personal_folder = 1 AND proot.parent_id = 0
                    AND pfolder.nleft >= proot.nleft AND pfolder.nright <= proot.nright)
                LEFT JOIN ' . prefixTable('users') . ' AS ow ON ow.id = proot.title';
            $missingBoth = ' LEFT JOIN ' . prefixTable($def['table']) . ' AS sk ON (sk.object_id = o.id AND sk.user_id NOT IN %li AND sk.share_key != "" AND sk.encryption_version = 3)
                LEFT JOIN ' . prefixTable($def['table']) . ' AS sktp ON (sktp.object_id = o.id AND sktp.user_id = ' . TP_USER_ID . ' AND sktp.share_key != "")';

            $total = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . $def['from'] . $missingBoth . '
                WHERE ' . $def['where'] . ' AND sktp.increment_id IS NULL',
                $nonOwnerIds
            );

            $rows = [];
            if ($total > 0) {
                $rows = DB::query(
                    'SELECT o.id AS object_id, ' . $detailDefs[$scopeName]['label'] . ', ' . $detailDefs[$scopeName]['extra'] . ',
                        IFNULL(ow.login, "") AS owner_login,
                        IF(sk.increment_id IS NULL, 0, 1) AS owner_can_read
                    FROM ' . $def['from'] . $detailDefs[$scopeName]['extraJoin'] . $ownerJoins . $missingBoth . '
                    WHERE ' . $def['where'] . ' AND sktp.increment_id IS NULL
                    ORDER BY o.id ASC
                    LIMIT %i',
                    $nonOwnerIds,
                    $detailsLimit
                );
            }

            $personalDetails[$scopeName] = [
                'total' => $total,
                'objects' => array_map(
                    static function (array $row): array {
                        return [
                            'id' => (int) $row['object_id'],
                            'label' => (string) $row['label'],
                            'extra' => (string) $row['extra'],
                            'owner_login' => (string) $row['owner_login'],
                            'owner_can_read' => (int) $row['owner_can_read'],
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
                'personal_details' => $personalDetails,
            ),
            'encode'
        );
        break;

    /*
    * RESTORE MISSING SHAREKEYS - STEP 2 - Choose whose keys open the objects
    * Your own keys by default. A reference user's private key is opened here, with that user's
    * password, and kept in the server session for the duration of the repair only: no private
    * key and no password ever goes back to the browser.
    */
    case 'restore_missing_sharekeys-prepare':
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
        $sourceUserId = (int) ($dataReceived['sourceUserId'] ?? 0);
        // IMPORTANT: Passwords should NOT be sanitized (fix 3.1.5.10)
        $sourcePassword = (string) ($dataReceived['sourcePassword'] ?? '');

        // Every check opens objects through the internal account: without its key nothing can be
        // verified, and the background task would stop on its first object.
        $tpKeys = getTpUserKeyPair($SETTINGS);
        if ($tpKeys['private_key'] === '') {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('restore_missing_sharekeys_tp_key_unavailable'),
                ),
                'encode'
            );
            break;
        }

        $state = [
            'source_user_id' => (int) $session->get('user-id'),
            'private_key' => '',
            'public_key' => '',
            'expires_at' => time() + 3600,
        ];
        if ($sourceUserId > 0 && $sourceUserId !== (int) $session->get('user-id')) {
            $sourceUser = restoreSharekeysSourceUser($sourceUserId);
            $sourcePrivateKey = $sourceUser !== null && $sourcePassword !== ''
                ? decryptPrivateKey($sourcePassword, $sourceUser['private_key'])
                : '';
            if ($sourceUser === null || empty($sourcePrivateKey) === true) {
                logEvents($SETTINGS, 'admin_action', 'restore_missing_sharekeys_reference_refused', (string) $session->get('user-id'), $session->get('user-login'), (string) $sourceUserId);
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('restore_missing_sharekeys_source_refused'),
                    ),
                    'encode'
                );
                break;
            }
            $state['source_user_id'] = $sourceUser['id'];
            $state['private_key'] = (string) $sourcePrivateKey;
            $state['public_key'] = $sourceUser['public_key'];
            logEvents($SETTINGS, 'admin_action', 'restore_missing_sharekeys_reference_user', (string) $session->get('user-id'), $session->get('user-login'), (string) $sourceUserId);
        }
        $session->set('restore_missing_sharekeys-state', $state);

        echo prepareExchangedData(
            array(
                'error' => false,
            ),
            'encode'
        );
        break;

    /*
    * RESTORE MISSING SHAREKEYS - STEP 3 - Check and rebuild the TP_USER reference keys
    * Walks the objects the source user can open (JS-batched, one scope at a time) and makes the
    * internal TP account key of each one a key that really opens it: a sharekey only proves an
    * object key was encrypted for someone, not that the object is still encrypted with it.
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

        $state = $session->get('restore_missing_sharekeys-state');
        if (is_array($state) === false || (int) ($state['expires_at'] ?? 0) < time()) {
            $session->remove('restore_missing_sharekeys-state');
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('restore_missing_sharekeys_state_expired'),
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

        $tpKeys = getTpUserKeyPair($SETTINGS);
        if ($tpKeys['private_key'] === '') {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('restore_missing_sharekeys_tp_key_unavailable'),
                ),
                'encode'
            );
            break;
        }

        // The reference user's keys when one was chosen, your own otherwise
        $sourceUserId = (int) $state['source_user_id'];
        $sourcePrivateKey = (string) $state['private_key'] !== '' ? (string) $state['private_key'] : (string) $session->get('user-private_key');
        $sourcePublicKey = (string) $state['public_key'] !== '' ? (string) $state['public_key'] : (string) $session->get('user-public_key');
        $checkable = $def['ciphertext'] !== '';

        $rows = DB::query(
            'SELECT o.id AS object_id' . restoreSharekeysCipherColumns($def) . ',
                src.share_key AS source_share_key, src.increment_id AS source_key_id,
                tp.share_key AS tp_share_key, tp.increment_id AS tp_key_id, tp.encryption_version AS tp_version
            FROM ' . $def['from'] . '
            INNER JOIN ' . prefixTable($def['table']) . ' AS src ON (src.object_id = o.id AND src.user_id = %i AND src.share_key != "")
            LEFT JOIN ' . prefixTable($def['table']) . ' AS tp ON (tp.object_id = o.id AND tp.user_id = ' . TP_USER_ID . ' AND tp.share_key != "")
            WHERE ' . $def['where'] . ' AND o.id > %i
            ORDER BY o.id ASC
            LIMIT %i',
            $sourceUserId,
            $lastId,
            $batchSize
        );

        $checked = 0;
        $seeded = 0;
        $replaced = 0;
        $failed = 0;
        foreach ($rows as $record) {
            $lastId = (int) $record['object_id'];
            ++$checked;
            $ciphertext = (string) $record['ciphertext'];
            $meta = (string) $record['meta'];

            try {
                // The reference key in place, when it is current: does it open the object?
                $tpHasCurrentKey = $record['tp_share_key'] !== null && (int) $record['tp_version'] === 3;
                $tpObjectKey = $tpHasCurrentKey === true
                    ? decryptUserObjectKeyWithMigration(
                        (string) $record['tp_share_key'],
                        $tpKeys['private_key'],
                        $tpKeys['public_key'],
                        (int) $record['tp_key_id'],
                        $def['table']
                    )
                    : '';
                $tpCheck = restoreSharekeysCheckObjectKey($tpObjectKey, $ciphertext, $meta, $checkable);
                if ($tpHasCurrentKey === true && $tpCheck['opens'] === true) {
                    continue;
                }

                $sourceObjectKey = decryptUserObjectKeyWithMigration(
                    (string) $record['source_share_key'],
                    $sourcePrivateKey,
                    $sourcePublicKey,
                    (int) $record['source_key_id'],
                    $def['table']
                );
                $sourceCheck = restoreSharekeysCheckObjectKey($sourceObjectKey, $ciphertext, $meta, $checkable);
                $action = sharekeyRepairReferenceAction(
                    $tpHasCurrentKey,
                    $tpObjectKey !== '',
                    $tpCheck['opens'],
                    $sourceCheck['opens'],
                    $sourceCheck['proves'],
                    $tpObjectKey !== '' && hash_equals($tpObjectKey, $sourceObjectKey)
                );
                if ($action === 'source_unusable') {
                    ++$failed;
                    continue;
                }

                $written = insertOrUpdateSharekey(
                    prefixTable($def['table']),
                    $lastId,
                    (int) TP_USER_ID,
                    encryptUserObjectKey($sourceObjectKey, $tpKeys['public_key'])
                );
                if ($written === false) {
                    ++$failed;
                    continue;
                }
                if ($action === 'replace') {
                    // The other users received their key from the same incomplete distribution, so
                    // they hold the same stale key. Removing it makes them missing keys, which the
                    // background task recreates from the reference key just checked; the internal
                    // accounts are left alone, the distribution never covers them.
                    DB::delete(
                        prefixTable($def['table']),
                        'object_id = %i AND user_id NOT IN %li',
                        $lastId,
                        [(int) TP_USER_ID, $sourceUserId, (int) OTV_USER_ID, (int) SSH_USER_ID, (int) API_USER_ID]
                    );
                    ++$replaced;
                } else {
                    ++$seeded;
                }
            } catch (Throwable $e) {
                ++$failed;
                error_log('TEAMPASS Error - restore_missing_sharekeys-seed - object #' . $lastId . ' (' . $def['table'] . '): ' . $e->getMessage());
            }
        }

        echo prepareExchangedData(
            array(
                'error' => false,
                'scope' => $scopeName,
                'checked' => $checked,
                'seeded' => $seeded,
                'replaced' => $replaced,
                'failed' => $failed,
                'lastId' => $lastId,
                'finished' => count($rows) < $batchSize,
            ),
            'encode'
        );
        break;

    /*
    * RESTORE MISSING SHAREKEYS - STEP 4 - Launch the background repair task
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

        // The reference user's private key is not needed past the previous step
        $session->remove('restore_missing_sharekeys-state');

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
