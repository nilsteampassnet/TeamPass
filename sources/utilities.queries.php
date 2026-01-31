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
 * @file      utilities.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */
use TiBeN\CrontabManager\CrontabJob;
use TiBeN\CrontabManager\CrontabAdapter;
use TiBeN\CrontabManager\CrontabRepository;
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
if (
    $checkUserAccess->userAccessPage('utilities.database') === false ||
    $checkUserAccess->checkSession() === false
) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //

// Load tree
$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Construction de la requ?te en fonction du type de valeur
if (null !== $post_type) {
    switch ($post_type) {
            //CASE list of recycled elements
        case 'recycled_bin_elements':
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
    } elseif ($session->get('user-read_only') === 1) {
        echo prepareExchangedData(
            array(
                'error' => true,
                'message' => $lang->get('error_not_allowed_to'),
            ),
            'encode'
        );
        break;
    }

    // Deleted folders (recycled bin) - stored in misc (type folder_deleted)
    $deletedFolders = tpGetDeletedFoldersFromMisc();
    $deletedFolderIds = array_keys($deletedFolders);

    // Build full paths for deleted folders (mix of deleted ancestors + existing parents)
    $deletedFolderParentIds = array();
    foreach ($deletedFolders as $df) {
        $deletedFolderParentIds[] = (int) ($df['parent_id'] ?? 0);
    }
    $pathIdsToFetch = array_values(array_unique(array_filter($deletedFolderParentIds, static function ($v) {
        return (int) $v > 0;
    })));
    $existingPaths = tpGetNestedTreeFolderPaths($pathIdsToFetch);

    $deletedPathsMemo = array();

    // Precompute deleted items count per deleted folder (direct count, then compute subtree count in PHP)
    // IMPORTANT: for items belonging to a deleted folder, older instances may have inactif=1 without deleted_at set.
    // We count deleted items based on inactif only.
    $directDeletedItemsCount = array();
    if (empty($deletedFolderIds) === false) {
        $rowsCounts = DB::query(
            'SELECT id_tree, COUNT(*) AS cnt
            FROM ' . prefixTable('items') . '
            WHERE id_tree IN %li
              AND inactif = %i
            GROUP BY id_tree',
            $deletedFolderIds,
            1
        );
        foreach ($rowsCounts as $rowCount) {
            $directDeletedItemsCount[(int) $rowCount['id_tree']] = (int) $rowCount['cnt'];
        }
    }

    $deletedChildren = tpBuildDeletedFoldersChildrenMap($deletedFolders);
    $subtreeCountMemo = array();

    // Build folders array with full path + count of deleted items in this folder subtree
    $arrFolders = array();
    foreach ($deletedFolders as $folderId => $folderInfo) {
        $path = tpBuildDeletedFolderPath((int) $folderId, $deletedFolders, $existingPaths, $deletedPathsMemo);
        if ($path === '') {
            $path = (string) ($folderInfo['title'] ?? '');
        }

        $itemsCount = tpComputeDeletedFolderSubtreeItemsCount((int) $folderId, $deletedChildren, $directDeletedItemsCount, $subtreeCountMemo);

        $arrFolders[] = array(
            'id' => (int) $folderId,
            'label' => (string) ($folderInfo['title'] ?? ''),
            'path' => $path,
            'items_count' => (int) $itemsCount,
        );
    }

    // Sort folders by path for readability
    usort($arrFolders, static function ($a, $b) {
        return strcmp((string) $a['path'], (string) $b['path']);
    });

    // Deleted items (only if the parent folder still exists).
    // If the folder was deleted too, the item must be restored by restoring the folder (to avoid restoring an "invisible" item).
    $arrItems = array();
    $rows = DB::query(
        'SELECT u.login as login, u.name as name, u.lastname as lastname,
        i.id as id, i.label as label,
        i.id_tree as id_tree, l.date as date, n.title as folder_title,
        a.del_enabled as del_enabled, a.del_value as del_value, a.del_type as del_type
        FROM ' . prefixTable('log_items') . ' as l
        INNER JOIN ' . prefixTable('items') . ' as i ON (l.id_item=i.id)
        LEFT JOIN ' . prefixTable('users') . ' as u ON (l.id_user=u.id)
        INNER JOIN ' . prefixTable('nested_tree') . ' as n ON (i.id_tree=n.id)
        LEFT JOIN ' . prefixTable('automatic_del') . ' as a ON (l.id_item = a.item_id)
        WHERE l.action = %s
        AND TRIM(COALESCE(i.deleted_at, "")) <> ""
        ORDER BY l.id_item ASC, CAST(l.date AS UNSIGNED) DESC',
        'at_delete'
    );

    // Prepare paths for existing folders
    $treeIds = array();
    foreach ($rows as $record) {
        if (isset($record['id_tree']) === true && $record['id_tree'] !== null) {
            $treeIds[] = (int) $record['id_tree'];
        }
    }
    $treeIds = array_values(array_unique($treeIds));
    $existingFolderPaths = tpGetNestedTreeFolderPaths($treeIds);

    // Build items array (one row per item)
    $prev_id = '';
    foreach ($rows as $record) {
        if ($record['id'] !== $prev_id) {
            $folderId = (int) $record['id_tree'];

            $folderTitle = (string) ($record['folder_title'] ?? '');
            $folderPath = $existingFolderPaths[$folderId] ?? $folderTitle;

            $itemLabel = (string) ($record['label'] ?? '');
            $itemPath = ($folderPath !== '' ? $folderPath . ' / ' : '') . $itemLabel;

            $arrItems[] = array(
                'id' => (int) $record['id'],
                'label' => $itemLabel,
                'path' => $itemPath,
                'date' => date($SETTINGS['date_format'], (int) $record['date']),
                'login' => (string) ($record['login'] ?? ''),
                'name' => trim((string) ($record['name'] ?? '') . ' ' . (string) ($record['lastname'] ?? '')),
                'folder_label' => $folderTitle,
                'folder_path' => $folderPath,
                'del_enabled' => (bool) $record['del_enabled'],
                'del_value' => (int) $record['del_type'] === 1 ? (int) $record['del_value'] : $record['del_value'],
                'del_type' => (int) $record['del_type'],
            );
        }
        $prev_id = $record['id'];
    }

    // send data
    echo prepareExchangedData(
        array(
            'error' => false,
            'message' => '',
            'folders' => $arrFolders,
            'items' => $arrItems,
        ),
        'encode'
    );
    break;
        //CASE recycle selected recycled elements
        case 'restore_selected_objects':
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
    } elseif ($session->get('user-read_only') === 1) {
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

    // Prepare variables
    $post_folders = filter_var_array($dataReceived['folders'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $post_items = filter_var_array($dataReceived['items'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Deleted folders index (from misc)
    $deletedFolders = tpGetDeletedFoldersFromMisc();
    $deletedChildren = tpBuildDeletedFoldersChildrenMap($deletedFolders);

    // Restore folders
    $foldersSelectedSubtree = array();
    foreach ($post_folders as $folderIdRaw) {
        $folderId = (int) $folderIdRaw;
        if ($folderId <= 0 || isset($deletedFolders[$folderId]) === false) {
            continue;
        }

        $subtree = tpCollectDeletedFolderSubtree($folderId, $deletedChildren);
        foreach ($subtree as $fid) {
            $foldersSelectedSubtree[$fid] = true;
        }
    }

    // Ensure parent chain exists for each restored folder (restore ancestors only if needed)
    $foldersToInsert = $foldersSelectedSubtree;
    foreach (array_keys($foldersSelectedSubtree) as $fid) {
        $parent = (int) ($deletedFolders[$fid]['parent_id'] ?? 0);
        while ($parent > 0) {
            // If parent exists in tree, stop climbing
            $parentExists = DB::queryFirstField(
                'SELECT id FROM ' . prefixTable('nested_tree') . ' WHERE id = %i',
                $parent
            );
            if (empty($parentExists) === false) {
                break;
            }

            // If parent is not in deleted folders, stop (cannot restore further)
            if (isset($deletedFolders[$parent]) === false) {
                break;
            }

            // Add the parent folder to insertion set
            if (isset($foldersToInsert[$parent]) === false) {
                $foldersToInsert[$parent] = true;
            }

            $parent = (int) ($deletedFolders[$parent]['parent_id'] ?? 0);
        }
    }

    $folderIdsToInsert = array_map('intval', array_keys($foldersToInsert));

    // Sort folders to insert: parents first
    $depthMemo = array();
    usort($folderIdsToInsert, static function ($a, $b) use ($deletedFolders, &$depthMemo) {
        $da = tpGetDeletedFolderDepth((int) $a, $deletedFolders, $depthMemo);
        $db = tpGetDeletedFolderDepth((int) $b, $deletedFolders, $depthMemo);
        if ($da === $db) {
            return $a <=> $b;
        }
        return $da <=> $db;
    });

    // Default folder complexity (low)
    $defaultComplexity = defined('TP_PW_STRENGTH_1') ? (int) TP_PW_STRENGTH_1 : 1;

    foreach ($folderIdsToInsert as $fid) {
        if (isset($deletedFolders[$fid]) === false) {
            continue;
        }

        // Skip if folder already exists (was restored by another selection)
        $exists = DB::queryFirstField(
            'SELECT id FROM ' . prefixTable('nested_tree') . ' WHERE id = %i',
            $fid
        );
        if (empty($exists) === false) {
            // Ensure complexity exists (some instances may have lost it)
            tpEnsureFolderComplexity($fid, $defaultComplexity);
            continue;
        }

        $fd = $deletedFolders[$fid];
        $parentId = (int) ($fd['parent_id'] ?? 0);

        // If parent doesn't exist (and was not restored), attach to root to avoid a broken tree
        if ($parentId > 0) {
            $parentExists = DB::queryFirstField(
                'SELECT id FROM ' . prefixTable('nested_tree') . ' WHERE id = %i',
                $parentId
            );
            if (empty($parentExists) === true && isset($foldersToInsert[$parentId]) === false) {
                $parentId = 0;
            }
        }

        $insertData = array(
            'id' => (int) $fid,
            'parent_id' => (int) $parentId,
            'title' => (string) ($fd['title'] ?? ''),
            'nleft' => 0,
            'nright' => 0,
            'nlevel' => 0,
            'bloquer_creation' => (int) ($fd['bloquer_creation'] ?? 0),
            'bloquer_modification' => (int) ($fd['bloquer_modification'] ?? 0),
            'personal_folder' => (int) ($fd['personal_folder'] ?? 0),
            'renewal_period' => (int) ($fd['renewal_period'] ?? 0),
            'categories' => (string) ($fd['categories'] ?? ''),
        );

        // Optional fields (available in newer folder_deleted format)
        if (isset($fd['fa_icon']) === true) {
            $insertData['fa_icon'] = (string) $fd['fa_icon'];
        }
        if (isset($fd['fa_icon_selected']) === true) {
            $insertData['fa_icon_selected'] = (string) $fd['fa_icon_selected'];
        }
        if (isset($fd['is_template']) === true) {
            $insertData['is_template'] = (int) $fd['is_template'];
        }

        DB::insert(prefixTable('nested_tree'), $insertData);

        // Ensure folder complexity exists (required for item editability)
        tpEnsureFolderComplexity($fid, $defaultComplexity);
    }

    // Remove restored folders from recycled bin (misc folder_deleted entries)
    foreach ($folderIdsToInsert as $fid) {
        DB::delete(
            prefixTable('misc'),
            'type = %s AND intitule = %s',
            'folder_deleted',
            'f' . (int) $fid
        );
    }

    // Restore items that belong to restored folders that were explicitly selected (folder subtree)
    $folderIdsToRestoreItems = array_map('intval', array_keys($foldersSelectedSubtree));
    if (empty($folderIdsToRestoreItems) === false) {
        DB::update(
            prefixTable('items'),
            array(
                'inactif' => 0,
                'deleted_at' => null,
            ),
            'id_tree IN %li',
            $folderIdsToRestoreItems
        );

        // Log restored items
$items = DB::query(
    'SELECT id, label
    FROM ' . prefixTable('items') . '
    WHERE id_tree IN %li',
    $folderIdsToRestoreItems
);
foreach ($items as $item) {
    logItems(
        $SETTINGS,
        (int) $item['id'],
        (string) $item['label'],
        (int) $session->get('user-id'),
        'at_restored',
        (string) $session->get('user-login')
    );
}
}

    // Restore selected items
    foreach ($post_items as $itemIdRaw) {
        $itemId = (int) $itemIdRaw;
        if ($itemId <= 0) {
            continue;
        }

        DB::update(
            prefixTable('items'),
            array(
                'inactif' => 0,
                'deleted_at' => null,
            ),
            'id = %i',
            $itemId
        );$itemLabel = DB::queryFirstField(
    'SELECT label
    FROM ' . prefixTable('items') . '
    WHERE id = %i',
    $itemId
);
logItems(
    $SETTINGS,
    $itemId,
    (string) ($itemLabel ?? ''),
    (int) $session->get('user-id'),
    'at_restored',
    (string) $session->get('user-login')
);
}

    // Rebuild tree indexes and update cache
    $tree->rebuild();
    updateCacheTable('reload', null);

    // send data
    echo prepareExchangedData(
        array(
            'error' => false,
            'message' => '',
        ),
        'encode'
    );
    break;
        //CASE delete selected recycled elements
        case 'delete_selected_objects':
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
    } elseif ($session->get('user-read_only') === 1) {
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

    // Prepare variables
    $post_folders = filter_var_array($dataReceived['folders'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $post_items = filter_var_array($dataReceived['items'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Deleted folders index (from misc) - used to delete a full subtree from the recycled bin
    $deletedFolders = tpGetDeletedFoldersFromMisc();
    $deletedChildren = tpBuildDeletedFoldersChildrenMap($deletedFolders);

    // Permanently delete FOLDERS (and all deleted subfolders + items they contain)
    $folderIdsToDelete = array();
    foreach ($post_folders as $folderIdRaw) {
        $folderId = (int) $folderIdRaw;
        if ($folderId <= 0 || isset($deletedFolders[$folderId]) === false) {
            continue;
        }

        $subtree = tpCollectDeletedFolderSubtree($folderId, $deletedChildren);
        foreach ($subtree as $fid) {
            $folderIdsToDelete[(int) $fid] = true;
        }
    }
    $folderIdsToDelete = array_map('intval', array_keys($folderIdsToDelete));

    foreach ($folderIdsToDelete as $folderId) {
        // Permanently delete all (soft-deleted) items in this folder
        $items = DB::query(
            'SELECT id
            FROM ' . prefixTable('items') . '
            WHERE id_tree = %i AND inactif = %i',
            $folderId,
            1
        );
        foreach ($items as $item) {
            tpHardDeleteItem((int) $item['id']);
        }

        // Delete the folder recycled-bin entry
        DB::delete(
            prefixTable('misc'),
            'type = %s AND intitule = %s',
            'folder_deleted',
            'f' . $folderId
        );
    }

    // Permanently delete ITEMS
    foreach ($post_items as $itemIdRaw) {
        tpHardDeleteItem((int) $itemIdRaw);
    }

    // Rebuild tree indexes and update cache
    $tree->rebuild();
    updateCacheTable('reload', null);

    // send data
    echo prepareExchangedData(
        array(
            'error' => false,
            'message' => '',
        ),
        'encode'
    );
    break;
/*
        * CASE purging logs
        */
        case 'purge_logs':
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
            } elseif ($session->get('user-read_only') === 1) {
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

            // Prepare variables
            $post_log_type = filter_var($dataReceived['dataType'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_date_from = strtotime(filter_var($dataReceived['dateStart'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            $post_date_to = strtotime(filter_var($dataReceived['dateEnd'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            $post_filter_user = filter_var($dataReceived['filter_user'], FILTER_SANITIZE_NUMBER_INT);
            $post_filter_action = filter_var($dataReceived['filter_action'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            // Check conditions
            if (
                empty($post_date_from) === false
                && empty($post_date_to) === false
                && empty($post_log_type) === false
                && ($session->has('user-admin') && (int) $session->get('user-admin') && null !== $session->get('user-admin') && (int) $session->get('user-admin') === 1)
            ) {
                if ($post_log_type === 'items') {
                    DB::query(
                        'SELECT * FROM ' . prefixTable('log_items') . '
                        WHERE (date BETWEEN %i AND %i)'
                        . ($post_filter_action === 'all' ? '' : ' AND action = "'.$post_filter_action.'"')
                        . ((int) $post_filter_user === -1 ? '' : ' AND id_user = '.(int) $post_filter_user),
                        $post_date_from,
                        $post_date_to
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefixTable('log_items'),
                        '(date BETWEEN %i AND %i)'
                        . ($post_filter_action === 'all' ? '' : ' AND action = "'.$post_filter_action.'"')
                        . ((int) $post_filter_user === -1 ? '' : ' AND id_user = '.(int) $post_filter_user),
                        $post_date_from,
                        $post_date_to
                    );
                } elseif ($post_log_type === 'connections') {
                    //db::debugmode(true);
                    DB::query(
                        'SELECT * FROM ' . prefixTable('log_system') . '
                        WHERE type=%s '
                        . 'AND (date BETWEEN %i AND %i)'
                        . ($post_filter_action === 'all' ? '' : ' AND label = "'.$post_filter_action.'"')
                        . ((int) $post_filter_user === -1 ? '' : ' AND qui = '.(int) $post_filter_user),
                        'user_connection',
                        $post_date_from,
                        $post_date_to
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefixTable('log_system'),
                        'type=%s '
                        . 'AND (date BETWEEN %i AND %i)'
                        . ($post_filter_action === 'all' ? '' : ' AND label = "'.$post_filter_action.'"')
                        . ((int) $post_filter_user === -1 ? '' : ' AND qui = '.(int) $post_filter_user),
                        'user_connection',
                        $post_date_from,
                        $post_date_to
                    );
                } elseif ($post_log_type === 'errors') {
                    DB::query(
                        'SELECT * FROM ' . prefixTable('log_system') . ' WHERE type=%s ' .
                            'AND (date BETWEEN %i AND %i)',
                        'error',
                        $post_date_from,
                        $post_date_to
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefixTable('log_system'),
                        'type=%s AND (date BETWEEN %i AND %i)',
                        'error',
                        $post_date_from,
                        $post_date_to
                    );
                } elseif ($post_log_type === 'copy') {
                    DB::query(
                        'SELECT * FROM ' . prefixTable('log_items') . ' WHERE action=%s ' .
                            'AND (date BETWEEN %i AND %i)',
                        'at_copy',
                        $post_date_from,
                        $post_date_to
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefixTable('log_items'),
                        'action=%s AND (date BETWEEN %i AND %i)',
                        'at_copy',
                        $post_date_from,
                        $post_date_to
                    );
                } elseif ($post_log_type === 'admin') {
                    DB::query(
                        'SELECT * FROM ' . prefixTable('log_system') . ' WHERE type=%s ' .
                            'AND (date BETWEEN %i AND %i)',
                        'admin_action',
                        $post_date_from,
                        $post_date_to
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefixTable('log_system'),
                        'type=%s AND (date BETWEEN %i AND %i)',
                        'admin_action',
                        $post_date_from,
                        $post_date_to
                    );
                } elseif ($post_log_type === 'failed') {
                    DB::query(
                        'SELECT * FROM ' . prefixTable('log_system') . ' WHERE type=%s ' .
                            'AND (date BETWEEN %i AND %i)',
                        'failed_auth',
                        $post_date_from,
                        $post_date_to
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefixTable('log_system'),
                        'type=%s AND (date BETWEEN %i AND %i)',
                        'failed_auth',
                        $post_date_from,
                        $post_date_to
                    );
                } else {
                    $counter = 0;
                }

                // send data
                echo prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => '',
                        'nb_deleted' => $counter,
                    ),
                    'encode'
                );

                break;
            }

            // send data
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );

            break;

        //CASE delete a task
        case 'task_delete':
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
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // Get info about task
            $taskInfo = DB::queryFirstRow(
                'SELECT p.process_type as process_type
                FROM ' . prefixTable('background_tasks') . ' as p
                WHERE p.increment_id = %i',
                $post_id
            );
            if ($taskInfo !== null) {
                // delete task
                DB::query(
                    'DELETE FROM ' . prefixTable('background_subtasks') . '
                    WHERE task_id = %i',
                    $post_id
                );
                DB::query(
                    'DELETE FROM ' . prefixTable('background_tasks') . '
                    WHERE increment_id = %i',
                    $post_id
                );

                // if user creation then update user status
                if ($taskInfo['process_type'] === 'create_user_keys') {
                    DB::update(
                        prefixTable('users'),
                        array(
                            'ongoing_process_id' => NULL,
                        ),
                        'ongoing_process_id = %i',
                        (int) $post_id
                    );
                }     
            }

            // send data
            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );
            break;

        //CASE handle crontab job
        case 'handle_crontab_job':
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
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // get PHP binary path
            $phpBinaryPath = getPHPBinary();

            // Instantiate the adapter and repository
            try {
                $crontabRepository = new CrontabRepository(new CrontabAdapter());
                $results = $crontabRepository->findJobByRegex('/Teampass\ scheduler/');
                if (count($results) === 0) {
                    // Add the job
                    $crontabJob = new CrontabJob();
                    $crontabJob
                        ->setMinutes('*')
                        ->setHours('*')
                        ->setDayOfMonth('*')
                        ->setMonths('*')
                        ->setDayOfWeek('*')
                        ->setTaskCommandLine($phpBinaryPath . ' ' . $SETTINGS['cpassman_dir'] . '/sources/scheduler.php')
                        ->setComments('Teampass scheduler');
                    
                    $crontabRepository->addJob($crontabJob);
                    $crontabRepository->persist();
                }
            } catch (Exception $e) {
                // do nothing
                print_r($e);
            }

            // send data
            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );
            break;
    }
}


/**
 * Parse "folder_deleted" misc value (legacy CSV or JSON).
 *
 * Legacy format (comma-separated): id, parent_id, title, nleft, nright, nlevel, bloquer_creation, bloquer_modification, personal_folder, renewal_period
 */
function tpParseFolderDeletedValeur(string $valeur): array
{
    $valeur = trim($valeur);
    if ($valeur === '') {
        return array();
    }

    // JSON format (preferred)
    if (substr($valeur, 0, 1) === '{') {
        $decoded = json_decode($valeur, true);
        if (is_array($decoded) === true && json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
    }

    // Legacy CSV format
    $parts = array_map('trim', explode(',', $valeur));

    return array(
        'id' => (int) ($parts[0] ?? 0),
        'parent_id' => (int) ($parts[1] ?? 0),
        'title' => (string) ($parts[2] ?? ''),
        'nleft' => (int) ($parts[3] ?? 0),
        'nright' => (int) ($parts[4] ?? 0),
        'nlevel' => (int) ($parts[5] ?? 0),
        'bloquer_creation' => (int) ($parts[6] ?? 0),
        'bloquer_modification' => (int) ($parts[7] ?? 0),
        'personal_folder' => (int) ($parts[8] ?? 0),
        'renewal_period' => (int) ($parts[9] ?? 0),
    );
}

/**
 * Fetch full paths for existing folders from nested_tree (id => "A / B / C").
 */
function tpGetNestedTreeFolderPaths(array $folderIds): array
{
    if (empty($folderIds) === true) {
        return array();
    }

    $folderIds = array_values(array_unique(array_map('intval', $folderIds)));

    $rows = DB::query(
        'SELECT node.id,
           GROUP_CONCAT(parent.title ORDER BY parent.nlevel SEPARATOR " / ") AS path
        FROM ' . prefixTable('nested_tree') . ' AS node
        JOIN ' . prefixTable('nested_tree') . ' AS parent
          ON parent.nleft <= node.nleft AND parent.nright >= node.nright
        WHERE node.id IN %li
        GROUP BY node.id',
        $folderIds
    );

    $paths = array();
    foreach ($rows as $row) {
        $paths[(int) $row['id']] = (string) $row['path'];
    }

    return $paths;
}

/**
 * Build the full path for a deleted folder by walking through misc folder_deleted and existing nested_tree.
 */
function tpBuildDeletedFolderPath(int $folderId, array $deletedFolders, array $existingPaths, array &$memo): string
{
    if (isset($memo[$folderId]) === true) {
        return (string) $memo[$folderId];
    }

    // If the folder exists in nested_tree (should not happen for deleted folders, but safe)
    if (isset($existingPaths[$folderId]) === true) {
        $memo[$folderId] = (string) $existingPaths[$folderId];
        return (string) $memo[$folderId];
    }

    if (isset($deletedFolders[$folderId]) === false) {
        $memo[$folderId] = '';
        return '';
    }

    $title = (string) $deletedFolders[$folderId]['title'];
    $parentId = (int) $deletedFolders[$folderId]['parent_id'];

    $parentPath = '';
    if ($parentId > 0) {
        if (isset($existingPaths[$parentId]) === true) {
            $parentPath = (string) $existingPaths[$parentId];
        } elseif (isset($deletedFolders[$parentId]) === true) {
            $parentPath = tpBuildDeletedFolderPath($parentId, $deletedFolders, $existingPaths, $memo);
        }
    }

    if ($parentPath !== '') {
        $memo[$folderId] = $parentPath . ' / ' . $title;
    } else {
        $memo[$folderId] = $title;
    }

    return (string) $memo[$folderId];
}

/**
 * Read deleted folders from misc table (type folder_deleted) and return a normalized map:
 *   [folderId => ['id'=>..,'parent_id'=>..,'title'=>.., ...]]
 *
 * Supports both legacy "csv" format and JSON format.
 */
function tpGetDeletedFoldersFromMisc(): array
{
    $deletedFolders = array();

    $rows = DB::query(
        'SELECT valeur
        FROM ' . prefixTable('misc') . '
        WHERE type = %s',
        'folder_deleted'
    );

    foreach ($rows as $record) {
        $folderData = tpParseFolderDeletedValeur((string) $record['valeur']);
        if (empty($folderData) === true || empty($folderData['id']) === true) {
            continue;
        }

        $folderId = (int) $folderData['id'];
        $deletedFolders[$folderId] = $folderData;
        $deletedFolders[$folderId]['id'] = $folderId;
        $deletedFolders[$folderId]['parent_id'] = (int) ($folderData['parent_id'] ?? 0);
        $deletedFolders[$folderId]['title'] = (string) ($folderData['title'] ?? '');
    }

    return $deletedFolders;
}

/**
 * Build a parent => children index from deleted folders.
 *
 * @return array<int, array<int>>
 */
function tpBuildDeletedFoldersChildrenMap(array $deletedFolders): array
{
    $children = array();

    foreach ($deletedFolders as $folderId => $folderData) {
        $parentId = (int) ($folderData['parent_id'] ?? 0);
        if ($parentId <= 0) {
            continue;
        }

        if (isset($children[$parentId]) === false) {
            $children[$parentId] = array();
        }
        $children[$parentId][] = (int) $folderId;
    }

    return $children;
}

/**
 * Collect the full subtree of a deleted folder (folder itself + deleted descendants).
 *
 * @return array<int>
 */
function tpCollectDeletedFolderSubtree(int $rootFolderId, array $childrenMap): array
{
    if ($rootFolderId <= 0) {
        return array();
    }

    $seen = array();
    $stack = array((int) $rootFolderId);

    while (empty($stack) === false) {
        $id = (int) array_pop($stack);
        if ($id <= 0 || isset($seen[$id]) === true) {
            continue;
        }

        $seen[$id] = true;

        if (isset($childrenMap[$id]) === true) {
            foreach ($childrenMap[$id] as $childId) {
                $stack[] = (int) $childId;
            }
        }
    }

    return array_map('intval', array_keys($seen));
}

/**
 * Compute deleted items count for a deleted folder subtree.
 */
function tpComputeDeletedFolderSubtreeItemsCount(int $folderId, array $childrenMap, array $directCounts, array &$memo): int
{
    if (isset($memo[$folderId]) === true) {
        return (int) $memo[$folderId];
    }

    $count = (int) ($directCounts[$folderId] ?? 0);

    if (isset($childrenMap[$folderId]) === true) {
        foreach ($childrenMap[$folderId] as $childId) {
            $count += tpComputeDeletedFolderSubtreeItemsCount((int) $childId, $childrenMap, $directCounts, $memo);
        }
    }

    $memo[$folderId] = $count;

    return $count;
}

/**
 * Compute a folder depth in deleted folders graph (used to restore parents first).
 */
function tpGetDeletedFolderDepth(int $folderId, array $deletedFolders, array &$memo): int
{
    if (isset($memo[$folderId]) === true) {
        return (int) $memo[$folderId];
    }

    if (isset($deletedFolders[$folderId]) === false) {
        $memo[$folderId] = 0;
        return 0;
    }

    $parentId = (int) ($deletedFolders[$folderId]['parent_id'] ?? 0);
    if ($parentId <= 0 || isset($deletedFolders[$parentId]) === false) {
        $memo[$folderId] = 0;
        return 0;
    }

    $memo[$folderId] = 1 + tpGetDeletedFolderDepth($parentId, $deletedFolders, $memo);
    return (int) $memo[$folderId];
}

/**
 * Ensure a folder has a password complexity policy (misc: type=complex).
 * Required for correct item editability after folder restore.
 */
function tpEnsureFolderComplexity(int $folderId, int $defaultComplexity): void
{
    if ($folderId <= 0) {
        return;
    }

    $row = DB::queryFirstRow(
        'SELECT valeur
        FROM ' . prefixTable('misc') . '
        WHERE type = %s AND intitule = %i',
        'complex',
        $folderId
    );

    if (isset($row['valeur']) === false) {
        DB::insert(
            prefixTable('misc'),
            array(
                'type' => 'complex',
                'intitule' => (int) $folderId,
                'valeur' => (int) $defaultComplexity,
            )
        );
        return;
    }

    if (trim((string) $row['valeur']) === '') {
        DB::update(
            prefixTable('misc'),
            array(
                'valeur' => (int) $defaultComplexity,
            ),
            'type = %s AND intitule = %i',
            'complex',
            $folderId
        );
    }
}

/**
 * Permanently delete an item and its dependent records.
 */
function tpHardDeleteItem(int $itemId): void
{
    if ($itemId <= 0) {
        return;
    }

    // Delete item logs
    DB::delete(prefixTable('log_items'), 'id_item = %i', $itemId);

    // Delete deletion policy (if any)
    DB::delete(prefixTable('automatic_del'), 'item_id = %i', $itemId);

    // Delete OTP data
    DB::delete(prefixTable('items_otp'), 'item_id = %i', $itemId);

    // Delete edition / change history
    DB::delete(prefixTable('items_change'), 'item_id = %i', $itemId);
    DB::delete(prefixTable('items_edition'), 'item_id = %i', $itemId);

    // Delete KB link
    DB::delete(prefixTable('kb_items'), 'item_id = %i', $itemId);

    // Delete sharekeys for the item itself
    DB::delete(prefixTable('sharekeys_items'), 'object_id = %i', $itemId);

    // Delete custom fields and their sharekeys
    $fields = DB::query(
        'SELECT id
        FROM ' . prefixTable('categories_items') . '
        WHERE item_id = %i',
        $itemId
    );
    foreach ($fields as $field) {
        DB::delete(prefixTable('sharekeys_fields'), 'object_id = %i', (int) $field['id']);
    }
    DB::delete(prefixTable('categories_items'), 'item_id = %i', $itemId);

    // Delete files and their sharekeys
    $files = DB::query(
        'SELECT id
        FROM ' . prefixTable('files') . '
        WHERE id_item = %i',
        $itemId
    );
    foreach ($files as $file) {
        DB::delete(prefixTable('sharekeys_files'), 'object_id = %i', (int) $file['id']);
    }
    DB::delete(prefixTable('files'), 'id_item = %i', $itemId);

    // Finally delete the item itself
    DB::delete(prefixTable('items'), 'id = %i', $itemId);
}
