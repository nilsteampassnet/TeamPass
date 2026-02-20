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
    $checkUserAccess->userAccessPage('utilities') === false ||
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
        //CASE system health report
        case 'get_health_report':
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

            $now = time();
            $from = $now - 86400;

            // Users exclusions (system accounts, API/OTV/TP, etc.)
            $excludedUserIds = tpGetExcludedUserIds();
            $excludedSql = '';
            if (empty($excludedUserIds) === false) {
                $excludedSql = ' AND id NOT IN (' . implode(',', array_map('intval', $excludedUserIds)) . ')';
            }


            // ------------------------
            // Overview (same base as admin dashboard system health)
            // ------------------------

            // Encryption check
            $encryptionStatus = 'success';
            $encryptionText = $lang->get('health_status_ok');

            if (
                isset($SETTINGS['securepath'], $SETTINGS['securefile']) === true
                && empty($SETTINGS['securepath']) === false
                && empty($SETTINGS['securefile']) === false
                && file_exists($SETTINGS['securepath'] . DIRECTORY_SEPARATOR . $SETTINGS['securefile']) === false
            ) {
                $encryptionStatus = 'danger';
                $encryptionText = $lang->get('health_secure_file_missing');
            }

            // Active sessions count (exclude soft-deleted + system accounts)
            $sessionsCount = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable('users') . ' WHERE session_end > %i AND disabled = 0 AND deleted_at IS NULL' . $excludedSql,
                $now
            );

            // Cron check
            DB::query(
                'SELECT valeur
                FROM ' . prefixTable('misc') . '
                WHERE type = %s AND intitule = %s and valeur >= %d',
                'admin',
                'last_cron_exec',
                $now - 600
            );

            if (DB::count() === 0) {
                $cronStatus = 'danger';
                $cronText = $lang->get('error');
            } else {
                $lastCron = DB::queryFirstField(
                    'SELECT created_at FROM ' . prefixTable('background_tasks_logs') . '
                    ORDER BY created_at DESC
                    LIMIT 1'
                );

                $cronStatus = 'success';
                $cronText = $lang->get('health_status_ok');

                if (!$lastCron || ($now - (int) $lastCron) > 120) {
                    $cronStatus = 'warning';
                    $cronText = $lang->get('health_cron_delayed');
                }
            }

            // Unknown files count
            $unknownFilesData = DB::queryFirstField(
                'SELECT valeur FROM ' . prefixTable('misc') . '
                WHERE type = %s AND intitule = %s',
                'admin',
                'unknown_files'
            );

            $unknownFilesCount = 0;
            if (!empty($unknownFilesData)) {
                $unknownFiles = json_decode((string) $unknownFilesData, true);
                if (is_array($unknownFiles)) {
                    $unknownFilesCount = count($unknownFiles);
                }
            }

            // ------------------------
            // System info (CPU / RAM / Disk / PHP)
            // ------------------------
            $cpuCores = tpGetCpuCores();
            $load = sys_getloadavg();
            $load1 = isset($load[0]) ? round((float) $load[0], 2) : 0.0;
            $load5 = isset($load[1]) ? round((float) $load[1], 2) : 0.0;
            $load15 = isset($load[2]) ? round((float) $load[2], 2) : 0.0;

            $mem = tpGetMemInfo();
            $disk = tpGetDiskInfo(
                array_unique(
                    array_filter(
                        array(
                            $SETTINGS['cpassman_dir'] ?? '',
                            $SETTINGS['path_to_upload_folder'] ?? '',
                            '/',
                        )
                    )
                )
            );

            $phpIni = tpGetPhpIniInfo();
            $opcache = tpGetOpcacheInfo();

            // ------------------------
            // Database health
            // ------------------------
            $dbVersion = (string) DB::queryFirstField('SELECT VERSION()');
            $latStart = microtime(true);
            DB::query('SELECT 1');
            $latencyMs = round((microtime(true) - $latStart) * 1000, 2);

            $dbName = (string) ($SETTINGS['db_name'] ?? '');
            if ($dbName === '') {
                $dbName = (string) DB::queryFirstField('SELECT DATABASE()');
            }

            $dbSizeBytes = (float) DB::queryFirstField(
                'SELECT SUM(data_length + index_length)
                FROM information_schema.TABLES
                WHERE table_schema = %s',
                $dbName
            );
            if ($dbSizeBytes <= 0) {
                try {
                    $st = DB::query('SHOW TABLE STATUS');
                    $sum = 0;
                    foreach ($st as $r) {
                        $sum += (float) ($r['Data_length'] ?? 0) + (float) ($r['Index_length'] ?? 0);
                    }
                    $dbSizeBytes = (float) $sum;
                } catch (Exception $e) {
                    // ignore
                }
            }
            $dbSizeMb = round($dbSizeBytes / 1024 / 1024, 2);

            $tablesOverview = array(
                prefixTable('users'),
                prefixTable('items'),
                prefixTable('sharekeys_items'),
                prefixTable('log_system'),
                prefixTable('log_items'),
                prefixTable('background_tasks_logs'),
            );

            $dbTables = array();
            foreach ($tablesOverview as $tableName) {
                if (tpTableExists($tableName) === false) {
                    continue;
                }

                $tableInfo = tpGetTableInfo($tableName, $dbName);
                $dbTables[] = $tableInfo;
            }

            // Table sizes (top 20)
            $dbTablesTop = tpGetTopTablesInfo(20, $dbName);

            // ------------------------
            // Crypto / Sharekeys health
            // ------------------------
            $sharekeysTables = array(
                'sharekeys_items',
                'sharekeys_files',
                'sharekeys_fields',
                'sharekeys_logs',
                'sharekeys_suggestions',
            );

            $sharekeysStats = array();
            $sharekeysOrphans = array();
            $sharekeysOrphansTotal = 0;

            foreach ($sharekeysTables as $t) {
                $tableName = prefixTable($t);
                if (tpTableExists($tableName) === false) {
                    continue;
                }

                $stats = tpGetSharekeysTableStats($tableName);
                if (empty($stats) === false) {
                    $sharekeysStats[] = $stats;
                }

                // Orphans (objects + users)
                $orphans = tpGetSharekeysOrphans($t);
                if (empty($orphans) === false) {
                    $sharekeysOrphans[$t] = $orphans;
                    $sharekeysOrphansTotal += (int) ($orphans['orphans_total'] ?? 0);
                }
            }

            // Sharekeys items progress (table sharekeys_items)
            $sharekeysItemsProgress = array(
                'total' => 0,
                'v1' => 0,
                'v3' => 0,
                'percent_v3' => 0,
            );
            foreach ($sharekeysStats as $st) {
                if (($st['table'] ?? '') === prefixTable('sharekeys_items')) {
                    $sharekeysItemsProgress['total'] = (int) ($st['total'] ?? 0);
                    $sharekeysItemsProgress['v1'] = (int) ($st['v1'] ?? 0);
                    $sharekeysItemsProgress['v3'] = (int) ($st['v3'] ?? 0);
                    $sharekeysItemsProgress['percent_v3'] = $sharekeysItemsProgress['total'] > 0
                        ? round(($sharekeysItemsProgress['v3'] / $sharekeysItemsProgress['total']) * 100, 2)
                        : 0;
                    break;
                }
            }


            // Sharekeys items distribution: personal vs shared
            $sharekeysItemsPerso = array();
            if (tpTableExists(prefixTable('sharekeys_items')) === true && tpTableExists(prefixTable('items')) === true) {
                $raw = DB::query(
                    'SELECT i.perso, s.encryption_version, COUNT(*) AS cnt
                    FROM ' . prefixTable('sharekeys_items') . ' s
                    INNER JOIN ' . prefixTable('items') . ' i ON i.id = s.object_id
                    GROUP BY i.perso, s.encryption_version'
                );
                foreach ($raw as $r) {
                    $sharekeysItemsPerso[] = array(
                        'perso' => (int) ($r['perso'] ?? 0),
                        'encryption_version' => (int) ($r['encryption_version'] ?? 0),
                        'count' => (int) ($r['cnt'] ?? 0),
                    );
                }
            }

            // Migration stats table (if exists)
            $migrationStats = array();
            $migrationOverall = array(
                'total_records' => 0,
                'v1_records' => 0,
                'v3_records' => 0,
                'percent_v3' => 0,
            );

            if (tpTableExists(prefixTable('encryption_migration_stats')) === true) {
                $rows = DB::query(
                    'SELECT table_name, total_records, v1_records, v3_records, last_update
                    FROM ' . prefixTable('encryption_migration_stats') . '
                    ORDER BY table_name ASC'
                );
                foreach ($rows as $r) {
                    $total = (int) ($r['total_records'] ?? 0);
                    $v3 = (int) ($r['v3_records'] ?? 0);
                    $percent = $total > 0 ? round(($v3 / $total) * 100, 2) : 0;

                    $migrationStats[] = array(
                        'table_name' => (string) ($r['table_name'] ?? ''),
                        'total_records' => $total,
                        'v1_records' => (int) ($r['v1_records'] ?? 0),
                        'v3_records' => $v3,
                        'percent_v3' => $percent,
                        'last_update' => (string) ($r['last_update'] ?? ''),
                    );

                    $migrationOverall['total_records'] += $total;
                    $migrationOverall['v1_records'] += (int) ($r['v1_records'] ?? 0);
                    $migrationOverall['v3_records'] += $v3;
                }

                if ($migrationOverall['total_records'] > 0) {
                    $migrationOverall['percent_v3'] = round(($migrationOverall['v3_records'] / $migrationOverall['total_records']) * 100, 2);
                }
            }

            // Users migration progress (computed)
            $usersTotal = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable('users') . ' WHERE disabled = 0 AND deleted_at IS NULL' . $excludedSql
            );
            // Users with migrated keys (phpseclib v3) - same reference as scripts/repair_phpseclib_migration.php
            $usersV3Keys = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable('users') . ' WHERE encryption_version = 3 AND disabled = 0 AND deleted_at IS NULL' . $excludedSql
            );
            // Users flagged as fully migrated (may be out-of-sync depending on migration history)
            $usersV3Flag = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable('users') . ' WHERE phpseclibv3_migration_completed = 1 AND disabled = 0 AND deleted_at IS NULL' . $excludedSql
            );

            // Keep the main progress aligned on keys version (v3)
            $usersV3 = $usersV3Keys;
$usersNotMigrated = $usersTotal - $usersV3;
            $usersPercent = $usersTotal > 0 ? round(($usersV3 / $usersTotal) * 100, 2) : 0;

            // Personal items migration progress
            $usersPersonalMigrated = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable('users') . ' WHERE personal_items_migrated = 1 AND disabled = 0 AND deleted_at IS NULL' . $excludedSql
            );
            $usersPersonalPercent = $usersTotal > 0 ? round(($usersPersonalMigrated / $usersTotal) * 100, 2) : 0;

            // Excluded users counts (for UI coherence with main dashboard)
            $excludedCounts = array(
                'system' => 0,
                'deleted' => 0,
                'disabled' => 0,
            );

            if (empty($excludedUserIds) === false) {
                $excludedCounts['system'] = (int) DB::queryFirstField(
                    'SELECT COUNT(*) FROM ' . prefixTable('users') . ' WHERE id IN %li',
                    $excludedUserIds
                );
            }

            // Soft-deleted users (excluding system accounts)
            $excludedCounts['deleted'] = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable('users') . ' WHERE deleted_at IS NOT NULL' . $excludedSql
            );

            // Disabled users (excluding system accounts and excluding soft-deleted)
            $excludedCounts['disabled'] = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable('users') . ' WHERE disabled = 1 AND deleted_at IS NULL' . $excludedSql
            );


            // Integrity hash check (same algorithm as scripts/fix_integrity_hash.php dry-run)
            $integrity = tpGetIntegrityHashStatus($SETTINGS, $excludedUserIds);

            // Inconsistent users with v1 sharekeys while marked migrated
            $inconsistentUsers = tpGetUsersWithV1SharekeysButMigrated($excludedUserIds);
            $inconsistentUsersCount = count($inconsistentUsers);

            // Users with sharekeys version mismatch
            $usersSharekeysMismatch = tpGetUsersSharekeysVersionMismatch($excludedUserIds);

            // ------------------------
            // Backups status
            // ------------------------
            $backupInfo = tpGetBackupsStatus($SETTINGS);

            // Translate backup summary text keys for UI (keep keys for export)
            if (isset($backupInfo['summary']['text_key']) === true) {
                $backupInfo['summary']['text'] = $lang->get((string) $backupInfo['summary']['text_key']);
            }
            if (isset($backupInfo['directories']) === true && is_array($backupInfo['directories']) === true) {
                foreach (array('scheduled', 'onthefly') as $dirKey) {
                    if (isset($backupInfo['directories'][$dirKey]['summary']['text_key']) === true) {
                        $backupInfo['directories'][$dirKey]['summary']['text'] = $lang->get(
                            (string) $backupInfo['directories'][$dirKey]['summary']['text_key']
                        );
                    }
                }
            }

            // ------------------------
            // Compare PHP vs Teampass settings
            // ------------------------
            $teampassSettings = tpGetTeampassSettingsForHealth($SETTINGS);
            $systemChecks = tpGetSystemChecks($phpIni, $teampassSettings, $lang);

            // ------------------------
            // Logs (last 24h) - teampass_log_system
            // ------------------------
            $totalLogs = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable('log_system') . '
                WHERE (date+0) BETWEEN %i AND %i',
                $from,
                $now
            );

            $errorLogs = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable('log_system') . '
                WHERE type = %s AND (date+0) BETWEEN %i AND %i',
                'error',
                $from,
                $now
            );

            $connectionLogs = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable('log_system') . '
                WHERE type = %s AND (date+0) BETWEEN %i AND %i',
                'user_connection',
                $from,
                $now
            );

            $topLabelsRaw = DB::query(
                'SELECT label, COUNT(*) AS c
                FROM ' . prefixTable('log_system') . '
                WHERE (date+0) BETWEEN %i AND %i
                GROUP BY label
                ORDER BY c DESC
                LIMIT 10',
                $from,
                $now
            );

            $topUsersRaw = DB::query(
                'SELECT qui, COUNT(*) AS c
                FROM ' . prefixTable('log_system') . '
                WHERE (date+0) BETWEEN %i AND %i
                GROUP BY qui
                ORDER BY c DESC
                LIMIT 10',
                $from,
                $now
            );

            
            // Enrich top users with identity (name + lastname) when "qui" is a numeric user id
            $topUserIds = array();
            foreach ($topUsersRaw as $r) {
                $quiStr = (string) ($r['qui'] ?? '');
                if ($quiStr !== '' && ctype_digit($quiStr)) {
                    $topUserIds[] = (int) $quiStr;
                }
            }
            $topUserIds = array_values(array_unique($topUserIds));

            $topUsersIdentity = array();
            if (empty($topUserIds) === false) {
                $usersRaw = DB::query(
                    'SELECT id, name, lastname
                    FROM ' . prefixTable('users') . '
                    WHERE id IN %li',
                    $topUserIds
                );
                foreach ($usersRaw as $u) {
                    $uid = (int) ($u['id'] ?? 0);
                    if ($uid > 0) {
                        $topUsersIdentity[$uid] = array(
                            'name' => (string) ($u['name'] ?? ''),
                            'lastname' => (string) ($u['lastname'] ?? ''),
                        );
                    }
                }
            }
$recentRaw = DB::query(
                'SELECT type, date, label, qui
                FROM ' . prefixTable('log_system') . '
                WHERE (date+0) BETWEEN %i AND %i
                ORDER BY (date+0) DESC
                LIMIT 50',
                $from,
                $now
            );

            $topLabels = array();
            foreach ($topLabelsRaw as $r) {
                $topLabels[] = array(
                    'label' => (string) ($r['label'] ?? ''),
                    'count' => (int) ($r['c'] ?? 0),
                );
            }

            
            $topUsers = array();
            foreach ($topUsersRaw as $r) {
                $quiStr = (string) ($r['qui'] ?? '');
                $userId = ($quiStr !== '' && ctype_digit($quiStr)) ? (int) $quiStr : 0;

                $identity = $userId > 0 && isset($topUsersIdentity[$userId]) ? $topUsersIdentity[$userId] : array();

                $topUsers[] = array(
                    'qui' => $quiStr,
                    'user_id' => $userId,
                    'user_name' => (string) ($identity['name'] ?? ''),
                    'user_lastname' => (string) ($identity['lastname'] ?? ''),
                    'count' => (int) ($r['c'] ?? 0),
                );
            }

$recent = array();
            foreach ($recentRaw as $r) {
                $ts = (int) ($r['date'] ?? 0);
                $recent[] = array(
                    'type' => (string) ($r['type'] ?? ''),
                    'date' => $ts,
                    'date_human' => $ts > 0 ? date('Y-m-d H:i:s', $ts) : '',
                    'label' => (string) ($r['label'] ?? ''),
                    'qui' => (string) ($r['qui'] ?? ''),
                );
            }

            $report = array(
                'generated_at' => $now,
                'generated_at_human' => date('Y-m-d H:i:s', $now),
                'export_filename' => $lang->get('health_export_filename'),
                'overview' => array(
                    'encryption' => array(
                        'status' => $encryptionStatus,
                        'text' => $encryptionText,
                    ),
                    'sessions' => array(
                        'count' => $sessionsCount,
                    ),
                    'cron' => array(
                        'status' => $cronStatus,
                        'text' => $cronText,
                    ),
                    'unknown_files' => array(
                        'count' => $unknownFilesCount,
                    ),
                    'migration' => array(
                        'overall' => $migrationOverall,
                        'sharekeys_items' => $sharekeysItemsProgress,
                        'users' => array(
                            'total' => $usersTotal,
                            // Main health indicator: migrated keys version (v3)
                            'migrated' => $usersV3,
                            'not_migrated' => $usersNotMigrated,
                            'percent_migrated' => $usersPercent,
                            // Diagnostics (not displayed unless UI uses it)
                            'migrated_keys' => $usersV3Keys,
                            'migrated_flag' => $usersV3Flag,
                            'percent_migrated_flag' => $usersTotal > 0 ? round(($usersV3Flag / $usersTotal) * 100, 2) : 0,
                            'excluded' => $excludedCounts,
                        ),
                        'personal_items' => array(
                            'total' => $usersTotal,
                            'migrated' => $usersPersonalMigrated,
                            'percent_migrated' => $usersPersonalPercent,
                        ),
                    ),
                    'findings' => array(
                        'sharekeys_orphans_total' => $sharekeysOrphansTotal,
                        'integrity_missing' => (int) ($integrity['missing'] ?? 0),
                        'integrity_mismatch' => (int) ($integrity['mismatch'] ?? 0),
                        'inconsistent_users' => $inconsistentUsersCount,
                        'backup_status' => (string) ($backupInfo['summary']['status'] ?? 'info'),
                    ),
                ),
                'system' => array(
                    'cpu' => array(
                        'cores' => $cpuCores,
                        'load_1' => $load1,
                        'load_5' => $load5,
                        'load_15' => $load15,
                    ),
                    'memory' => $mem,
                    'disk' => $disk,
                    'php' => $phpIni,
                    'opcache' => $opcache,
                    'teampass_settings' => $teampassSettings,
                    'checks' => $systemChecks,
                ),
                'database' => array(
                    'version' => $dbVersion,
                    'latency_ms' => $latencyMs,
                    'size_mb' => $dbSizeMb,
                    'tables_overview' => $dbTables,
                    'tables_top' => $dbTablesTop,
                ),
                'crypto' => array(
                    'sharekeys' => array(
                        'tables' => $sharekeysStats,
                        'items_perso' => $sharekeysItemsPerso,
                        'orphans' => $sharekeysOrphans,
                        'orphans_total' => $sharekeysOrphansTotal,
                    ),
                    'migration_stats' => $migrationStats,
                    'integrity_hash' => $integrity,
                    'inconsistent_users' => $inconsistentUsers,
                    'users_sharekeys_mismatch' => $usersSharekeysMismatch,
                ),
                'backups' => $backupInfo,
                'logs' => array(
                    'from' => $from,
                    'to' => $now,
                    'total' => $totalLogs,
                    'errors' => $errorLogs,
                    'connections' => $connectionLogs,
                    'top_labels' => $topLabels,
                    'top_users' => $topUsers,
                    'recent' => $recent,
                ),
            );

            echo prepareExchangedData(
                array(
                    'error' => false,
                    'report' => $report,
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

/**
 * ------------------------------
 * Utilities / Health helpers
 * ------------------------------
 */

function tpGetCpuCores(): int
{
    $cpuinfo = '/proc/cpuinfo';
    if (is_readable($cpuinfo) === true) {
        $content = @file_get_contents($cpuinfo);
        if ($content !== false) {
            preg_match_all('/^processor\s*:/m', $content, $m);
            $count = isset($m[0]) ? count($m[0]) : 0;
            if ($count > 0) {
                return $count;
            }
        }
    }

    $env = getenv('NUMBER_OF_PROCESSORS');
    if ($env !== false && (int) $env > 0) {
        return (int) $env;
    }

    return 1;
}

function tpGetMemInfo(): array
{
    $meminfo = '/proc/meminfo';
    $result = array(
        'total_mb' => 0,
        'available_mb' => 0,
        'used_mb' => 0,
        'used_percent' => 0,
        'swap_total_mb' => 0,
        'swap_free_mb' => 0,
    );

    if (is_readable($meminfo) === false) {
        return $result;
    }

    $lines = @file($meminfo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return $result;
    }

    $vals = array();
    foreach ($lines as $line) {
        if (preg_match('/^(\w+):\s+(\d+)\s+kB$/', $line, $m) === 1) {
            $vals[$m[1]] = (int) $m[2];
        }
    }

    $totalKb = (int) ($vals['MemTotal'] ?? 0);
    $availKb = (int) ($vals['MemAvailable'] ?? 0);
    $swapTotalKb = (int) ($vals['SwapTotal'] ?? 0);
    $swapFreeKb = (int) ($vals['SwapFree'] ?? 0);

    $totalMb = (int) round($totalKb / 1024);
    $availMb = (int) round($availKb / 1024);
    $usedMb = max(0, $totalMb - $availMb);
    $usedPercent = $totalMb > 0 ? round(($usedMb / $totalMb) * 100, 2) : 0;

    $result['total_mb'] = $totalMb;
    $result['available_mb'] = $availMb;
    $result['used_mb'] = $usedMb;
    $result['used_percent'] = $usedPercent;
    $result['swap_total_mb'] = (int) round($swapTotalKb / 1024);
    $result['swap_free_mb'] = (int) round($swapFreeKb / 1024);

    return $result;
}

function tpGetDiskInfo(array $paths): array
{
    $out = array();
    $seen = array();

    foreach ($paths as $p) {
        $p = trim((string) $p);
        if ($p === '') {
            continue;
        }

        $real = realpath($p);
        if ($real === false) {
            // fallback to root directory of the path
            $real = realpath(dirname($p));
        }
        if ($real === false) {
            $real = '/';
        }

        if (isset($seen[$real]) === true) {
            continue;
        }
        $seen[$real] = true;

        $total = @disk_total_space($real);
        $free = @disk_free_space($real);

        if ($total === false || $free === false || $total <= 0) {
            $out[] = array(
                'path' => $real,
                'total_gb' => 0,
                'free_gb' => 0,
                'used_percent' => 0,
            );
            continue;
        }

        $used = $total - $free;
        $usedPercent = round(($used / $total) * 100, 2);

        $out[] = array(
            'path' => $real,
            'total_gb' => round($total / 1024 / 1024 / 1024, 2),
            'free_gb' => round($free / 1024 / 1024 / 1024, 2),
            'used_percent' => $usedPercent,
        );
    }

    return $out;
}

function tpGetPhpIniInfo(): array
{
    $keys = array(
        'memory_limit',
        'max_execution_time',
        'max_input_time',
        'post_max_size',
        'upload_max_filesize',
        'max_file_uploads',
        'max_input_vars',
        'default_socket_timeout',
        'date.timezone',
    );

    $ini = array();
    foreach ($keys as $k) {
        $ini[$k] = (string) ini_get($k);
    }

    return array(
        'version' => PHP_VERSION,
        'sapi' => PHP_SAPI,
        'ini' => $ini,
    );
}

function tpGetOpcacheInfo(): array
{
    $enabled = extension_loaded('Zend OPcache') === true;

    $out = array(
        'enabled' => $enabled,
        'configuration' => array(),
        'status' => array(),
    );

    if ($enabled === false) {
        return $out;
    }

    if (function_exists('opcache_get_configuration') === true) {
        $cfg = @opcache_get_configuration();
        if (is_array($cfg) === true) {
            $directives = $cfg['directives'] ?? array();
            if (is_array($directives) === true) {
                // Keep only a small subset
                $wanted = array(
                    'opcache.enable',
                    'opcache.enable_cli',
                    'opcache.memory_consumption',
                    'opcache.interned_strings_buffer',
                    'opcache.max_accelerated_files',
                    'opcache.revalidate_freq',
                    'opcache.validate_timestamps',
                    'opcache.save_comments',
                    'opcache.jit',
                    'opcache.jit_buffer_size',
                );
                foreach ($wanted as $w) {
                    if (isset($directives[$w]) === true) {
                        $out['configuration'][$w] = $directives[$w];
                    }
                }
            }
        }
    }

    if (function_exists('opcache_get_status') === true) {
        $st = @opcache_get_status(false);
        if (is_array($st) === true) {
            $mem = $st['memory_usage'] ?? array();
            $stats = $st['opcache_statistics'] ?? array();

            $out['status'] = array(
                'opcache_enabled' => (bool) ($st['opcache_enabled'] ?? true),
                'cache_full' => (bool) ($st['cache_full'] ?? false),
                'used_memory_mb' => isset($mem['used_memory']) ? round(((float) $mem['used_memory']) / 1024 / 1024, 2) : 0,
                'free_memory_mb' => isset($mem['free_memory']) ? round(((float) $mem['free_memory']) / 1024 / 1024, 2) : 0,
                'wasted_memory_mb' => isset($mem['wasted_memory']) ? round(((float) $mem['wasted_memory']) / 1024 / 1024, 2) : 0,
                'num_cached_scripts' => (int) ($stats['num_cached_scripts'] ?? 0),
                'hits' => (int) ($stats['hits'] ?? 0),
                'misses' => (int) ($stats['misses'] ?? 0),
                'blacklist_misses' => (int) ($stats['blacklist_misses'] ?? 0),
                'oom_restarts' => (int) ($stats['oom_restarts'] ?? 0),
                'hash_restarts' => (int) ($stats['hash_restarts'] ?? 0),
                'manual_restarts' => (int) ($stats['manual_restarts'] ?? 0),
            );
        }
    }

    return $out;
}

function tpIniSizeToBytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $value = strtolower($value);

    // Accept "10mb" (Teampass) or "10M" (php.ini)
    if (preg_match('/^(\d+(?:\.\d+)?)\s*(b|kb|k|mb|m|gb|g|tb|t)?$/', $value, $m) !== 1) {
        return 0;
    }

    $num = (float) ($m[1] ?? 0);
    $unit = (string) ($m[2] ?? 'b');

    switch ($unit) {
        case 't':
        case 'tb':
            return (int) round($num * 1024 * 1024 * 1024 * 1024);
        case 'g':
        case 'gb':
            return (int) round($num * 1024 * 1024 * 1024);
        case 'm':
        case 'mb':
            return (int) round($num * 1024 * 1024);
        case 'k':
        case 'kb':
            return (int) round($num * 1024);
        case 'b':
        default:
            return (int) round($num);
    }
}

function tpTableExists(string $tableName): bool
{
    $count = (int) DB::queryFirstField(
        'SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE table_schema = DATABASE() AND table_name = %s',
        $tableName
    );

    return $count > 0;
}

function tpColumnExists(string $tableName, string $columnName): bool
{
    $count = (int) DB::queryFirstField(
        'SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE table_schema = DATABASE() AND table_name = %s AND column_name = %s',
        $tableName,
        $columnName
    );

    return $count > 0;
}

function tpGetTableInfo(string $tableName, string $dbName = ''): array
{
    $tableName = trim($tableName);
    if ($tableName === '') {
        return array(
            'name' => '',
            'engine' => '',
            'rows' => 0,
            'size_mb' => 0,
            'free_mb' => 0,
            'fragmentation_ratio' => 0,
            'status' => 'info',
        );
    }

    if ($dbName === '') {
        $dbName = (string) DB::queryFirstField('SELECT DATABASE()');
    }

    $row = array();

    // Preferred: information_schema (more consistent across engines)
    try {
        $row = (array) DB::queryFirstRow(
            'SELECT table_name, engine, table_rows, data_length, index_length, data_free
            FROM information_schema.TABLES
            WHERE table_schema = %s AND table_name = %s',
            $dbName,
            $tableName
        );
    } catch (Exception $e) {
        $row = array();
    }

    // Fallback: SHOW TABLE STATUS (works when information_schema is filtered)
    if (empty($row) === true) {
        try {
            $st = (array) DB::queryFirstRow('SHOW TABLE STATUS LIKE %s', $tableName);
            if (empty($st) === false) {
                $row = array(
                    'table_name' => (string) ($st['Name'] ?? $tableName),
                    'engine' => (string) ($st['Engine'] ?? ''),
                    'table_rows' => (int) ($st['Rows'] ?? 0),
                    'data_length' => (float) ($st['Data_length'] ?? 0),
                    'index_length' => (float) ($st['Index_length'] ?? 0),
                    'data_free' => (float) ($st['Data_free'] ?? 0),
                );
            }
        } catch (Exception $e) {
            // ignore
        }
    }

    $sizeBytes = (float) (($row['data_length'] ?? 0) + ($row['index_length'] ?? 0));
    $sizeMb = round($sizeBytes / 1024 / 1024, 2);
    $freeMb = round(((float) ($row['data_free'] ?? 0)) / 1024 / 1024, 2);

    $ratio = 0;
    if ($sizeBytes > 0) {
        $ratio = round((((float) ($row['data_free'] ?? 0)) / $sizeBytes), 4);
    }

    $status = 'success';
    if ($freeMb >= 50 && $ratio >= 0.2) {
        $status = 'warning';
    }

    return array(
        'name' => (string) ($row['table_name'] ?? $tableName),
        'engine' => (string) ($row['engine'] ?? ''),
        'rows' => (int) ($row['table_rows'] ?? 0),
        'size_mb' => $sizeMb,
        'free_mb' => $freeMb,
        'fragmentation_ratio' => $ratio,
        'status' => $status,
    );
}


function tpGetTopTablesInfo(int $limit = 20, string $dbName = ''): array
{
    if ($dbName === '') {
        $dbName = (string) DB::queryFirstField('SELECT DATABASE()');
    }

    $prefix = prefixTable('users');
    $prefix = str_replace('`', '', $prefix);
    $prefix = substr($prefix, 0, -strlen('users'));

    $rows = array();

    // Preferred: information_schema
    try {
        $rows = DB::query(
            'SELECT table_name, engine, table_rows, data_length, index_length, data_free
            FROM information_schema.TABLES
            WHERE table_schema = %s AND table_name LIKE %ss
            ORDER BY (data_length + index_length) DESC
            LIMIT %i',
            $dbName,
            $prefix . '%',
            $limit
        );
    } catch (Exception $e) {
        $rows = array();
    }

    // Fallback: SHOW TABLE STATUS
    if (empty($rows) === true) {
        try {
            $st = DB::query('SHOW TABLE STATUS LIKE %s', $prefix . '%');
            $rows = array();
            foreach ($st as $r) {
                $rows[] = array(
                    'table_name' => (string) ($r['Name'] ?? ''),
                    'engine' => (string) ($r['Engine'] ?? ''),
                    'table_rows' => (int) ($r['Rows'] ?? 0),
                    'data_length' => (float) ($r['Data_length'] ?? 0),
                    'index_length' => (float) ($r['Index_length'] ?? 0),
                    'data_free' => (float) ($r['Data_free'] ?? 0),
                );
            }
        } catch (Exception $e) {
            $rows = array();
        }
    }

    // Last resort: no prefix filter
    if (empty($rows) === true) {
        try {
            $rows = DB::query(
                'SELECT table_name, engine, table_rows, data_length, index_length, data_free
                FROM information_schema.TABLES
                WHERE table_schema = %s
                ORDER BY (data_length + index_length) DESC
                LIMIT %i',
                $dbName,
                $limit
            );
        } catch (Exception $e) {
            $rows = array();
        }
    }


// Final fallback: SHOW TABLE STATUS without filter (works even without information_schema privileges)
if (empty($rows) === true) {
    try {
        $st = DB::query('SHOW TABLE STATUS');
        $rows = array();
        foreach ($st as $r) {
            $rows[] = array(
                'table_name' => (string) ($r['Name'] ?? ''),
                'engine' => (string) ($r['Engine'] ?? ''),
                'table_rows' => (int) ($r['Rows'] ?? 0),
                'data_length' => (float) ($r['Data_length'] ?? 0),
                'index_length' => (float) ($r['Index_length'] ?? 0),
                'data_free' => (float) ($r['Data_free'] ?? 0),
            );
        }

        usort($rows, static function ($a, $b) {
            $sa = (float) (($a['data_length'] ?? 0) + ($a['index_length'] ?? 0));
            $sb = (float) (($b['data_length'] ?? 0) + ($b['index_length'] ?? 0));
            return $sb <=> $sa;
        });

        $rows = array_slice($rows, 0, $limit);
    } catch (Exception $e) {
        $rows = array();
    }
}
    $out = array();
    foreach ($rows as $r) {
        $sizeBytes = (float) (($r['data_length'] ?? 0) + ($r['index_length'] ?? 0));
        $sizeMb = round($sizeBytes / 1024 / 1024, 2);
        $freeMb = round(((float) ($r['data_free'] ?? 0)) / 1024 / 1024, 2);
        $ratio = $sizeBytes > 0 ? round((((float) ($r['data_free'] ?? 0)) / $sizeBytes), 4) : 0;

        $status = 'success';
        if ($freeMb >= 50 && $ratio >= 0.2) {
            $status = 'warning';
        }

        $out[] = array(
            'name' => (string) ($r['table_name'] ?? ''),
            'engine' => (string) ($r['engine'] ?? ''),
            'rows' => (int) ($r['table_rows'] ?? 0),
            'size_mb' => $sizeMb,
            'free_mb' => $freeMb,
            'fragmentation_ratio' => $ratio,
            'status' => $status,
        );
    }

    return $out;
}


function tpGetSharekeysTableStats(string $tableName): array
{
    if (tpColumnExists($tableName, 'encryption_version') === false) {
        return array();
    }

    $row = DB::queryFirstRow(
        'SELECT
            COUNT(*) AS total,
            SUM(encryption_version = 1) AS v1,
            SUM(encryption_version = 3) AS v3,
            SUM(encryption_version IS NULL) AS nulls
        FROM ' . $tableName
    );

    return array(
        'table' => $tableName,
        'total' => (int) ($row['total'] ?? 0),
        'v1' => (int) ($row['v1'] ?? 0),
        'v3' => (int) ($row['v3'] ?? 0),
        'nulls' => (int) ($row['nulls'] ?? 0),
    );
}

/**
 * Identifies and counts orphaned records in sharekeys tables.
 * Orphans are records linked to non-existent objects, non-existent users, 
 * or inactive/deleted users.
 * * @param string $shortTableName The short name of the sharekey table (e.g., 'sharekeys_items').
 * @return array{missing_object: ?int, missing_user: int, inactive_user: int, orphans_total: int}
 */
function tpGetSharekeysOrphans(string $shortTableName): array
{
    $shortTableName = trim($shortTableName);
    $tableName = prefixTable($shortTableName);
    
    if (tpTableExists($tableName) === false) {
        return array();
    }

    // Identify missing users (records with user_id not found in users table)
    $missingUser = (int) DB::queryFirstField(
        'SELECT COUNT(*)
        FROM ' . $tableName . ' s
        LEFT JOIN ' . prefixTable('users') . ' u ON u.id = s.user_id
        WHERE u.id IS NULL'
    );

    // Identify inactive or deleted users
    $inactiveUser = (int) DB::queryFirstField(
        'SELECT COUNT(*)
        FROM ' . $tableName . ' s
        INNER JOIN ' . prefixTable('users') . ' u ON u.id = s.user_id
        WHERE u.disabled = 1 OR u.deleted_at IS NOT NULL'
    );

    $missingObject = null;
    $targetTable = '';

    // Determine target object table based on sharekey context
    switch ($shortTableName) {
        case 'sharekeys_items':
            $targetTable = 'items';
            break;
        case 'sharekeys_files':
            $targetTable = 'files';
            break;
        case 'sharekeys_suggestions':
            $targetTable = 'suggestion';
            break;
        case 'sharekeys_fields':
            $targetTable = 'categories_items';
            break;
    }

    // Identify missing objects if the target table exists
    if (!empty($targetTable) && tpTableExists(prefixTable($targetTable))) {
        $missingObject = (int) DB::queryFirstField(
            'SELECT COUNT(*)
            FROM ' . $tableName . ' s
            LEFT JOIN ' . prefixTable($targetTable) . ' o ON o.id = s.object_id
            WHERE o.id IS NULL'
        );
    }

    // Calculate total orphans
    $orphansTotal = $missingUser + $inactiveUser + (int) $missingObject;

    return array(
        'missing_object' => $missingObject,
        'missing_user' => $missingUser,
        'inactive_user' => $inactiveUser,
        'orphans_total' => $orphansTotal,
    );
}

function tpGetExcludedUserIds(): array
{
    $ids = array();

    $constants = array(
        'OTV_USER_ID',
        'SSH_USER_ID',
        'API_USER_ID',
        'ADMIN_USER_ID',
        'SUPERVISOR_USER_ID',
    );

    foreach ($constants as $c) {
        if (defined($c) === true) {
            $ids[] = (int) constant($c);
        }
    }

    
    // Fallback: system accounts by login (TP / OTV / API)
    // This ensures exclusions even if constants are not defined in some contexts.
    try {
        $rows = DB::query(
            'SELECT id FROM ' . prefixTable('users') . ' WHERE UPPER(login) IN ("TP","OTV","API")'
        );
        foreach ($rows as $r) {
            $ids[] = (int) ($r['id'] ?? 0);
        }
    } catch (Exception $e) {
        // ignore
    }

$ids = array_values(array_unique(array_filter($ids, static function ($v): bool {
        return is_int($v) && $v > 0;
    })));

    return $ids;
}

function tpGetIntegrityHashStatus(array $SETTINGS, array $excludedUserIds = array()): array
{
    $out = array(
        'available' => false,
        'missing' => 0,
        'mismatch' => 0,
        'ok' => 0,
        'details' => array(
            'missing_seed' => 0,
            'missing_public_key' => 0,
            'missing_stored_hash' => 0,
            'mismatch_raw' => 0,
            'mismatch_even_trim' => 0,
        ),
        'users' => array(),
        'error' => '',
    );

    // Resolve secure file path
    $securePath = '';
    $secureFile = '';

    if (defined('SECUREPATH') === true) {
        $securePath = (string) constant('SECUREPATH');
    } else {
        $securePath = (string) ($SETTINGS['securepath'] ?? '');
    }

    if (defined('SECUREFILE') === true) {
        $secureFile = (string) constant('SECUREFILE');
    } else {
        $secureFile = (string) ($SETTINGS['securefile'] ?? '');
    }

    $fullPath = rtrim($securePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $secureFile;

    if ($securePath === '' || $secureFile === '' || is_readable($fullPath) === false) {
        $out['error'] = 'secure_file_not_readable';
        return $out;
    }

    if (class_exists('Defuse\\Crypto\\Key') === false) {
        $out['error'] = 'defuse_key_class_missing';
        return $out;
    }

    $serverSecret = '';
    try {
        $key = \Defuse\Crypto\Key::loadFromAsciiSafeString((string) file_get_contents($fullPath));
        $serverSecret = $key->saveToAsciiSafeString();
    } catch (Throwable $e) {
        $out['error'] = 'secure_file_invalid';
        return $out;
    }

    $out['available'] = true;

    $excludedSql = '';
    if (empty($excludedUserIds) === false) {
        $excludedSql = ' AND id NOT IN (' . implode(',', array_map('intval', $excludedUserIds)) . ')';
    }

    $users = DB::query(
        'SELECT id, login, user_derivation_seed, public_key, key_integrity_hash
        FROM ' . prefixTable('users') . '
        WHERE disabled = 0 AND deleted_at IS NULL' . $excludedSql
    );

    $maxListed = 50;
    foreach ($users as $u) {
        $id = (int) ($u['id'] ?? 0);
        $login = (string) ($u['login'] ?? '');

        $seed = (string) ($u['user_derivation_seed'] ?? '');
        $pub = (string) ($u['public_key'] ?? '');
        $stored = (string) ($u['key_integrity_hash'] ?? '');

        $seedTrim = trim($seed);
        $pubTrim = trim($pub);

        if ($seedTrim === '') {
            $out['details']['missing_seed']++;
            $out['missing']++;
            if (count($out['users']) < $maxListed) {
                $out['users'][] = array('id' => $id, 'login' => $login, 'reason' => 'missing_seed');
            }
            continue;
        }
        if ($pubTrim === '') {
            $out['details']['missing_public_key']++;
            $out['missing']++;
            if (count($out['users']) < $maxListed) {
                $out['users'][] = array('id' => $id, 'login' => $login, 'reason' => 'missing_public_key');
            }
            continue;
        }
        if (trim($stored) === '') {
            $out['details']['missing_stored_hash']++;
            $out['missing']++;
            if (count($out['users']) < $maxListed) {
                $out['users'][] = array('id' => $id, 'login' => $login, 'reason' => 'missing_hash');
            }
            continue;
        }

        $expectedRaw = hash_hmac('sha256', $seed . $pub, $serverSecret);
        $expectedTrim = hash_hmac('sha256', $seedTrim . $pubTrim, $serverSecret);

        if (hash_equals($expectedRaw, trim($stored)) === true) {
            $out['ok']++;
            continue;
        }

        $out['details']['mismatch_raw']++;

        // Some instances have stray spaces (seen in some migrations)
        if (hash_equals($expectedTrim, trim($stored)) === true) {
            $out['details']['mismatch_even_trim']++;
            // Still considered mismatch as it requires clean-up
        }

        $out['mismatch']++;
        if (count($out['users']) < $maxListed) {
            $out['users'][] = array('id' => $id, 'login' => $login, 'reason' => 'mismatch');
        }
    }

    return $out;
}

function tpGetUsersWithV1SharekeysButMigrated(array $excludedUserIds = array()): array
{
    $sharekeysSources = tpGetSharekeysUserUnionSources(1);
    if ($sharekeysSources === '') {
        return array();
    }

    $excludedSql = '';
    if (empty($excludedUserIds) === false) {
        $excludedSql = ' AND u.id NOT IN (' . implode(',', array_map('intval', $excludedUserIds)) . ')';
    }

    $sql = '
        SELECT u.id, u.login, SUM(t.cnt) AS v1_sharekeys
        FROM ' . prefixTable('users') . ' u
        INNER JOIN (
            ' . $sharekeysSources . '
        ) t ON t.user_id = u.id
        WHERE (u.encryption_version = 3 OR u.phpseclibv3_migration_completed = 1)
        AND u.disabled = 0 AND u.deleted_at IS NULL
        ' . $excludedSql . '
        GROUP BY u.id, u.login
        HAVING v1_sharekeys > 0
        ORDER BY v1_sharekeys DESC
        LIMIT 100
    ';

    $rows = DB::query($sql);

    $out = array();
    foreach ($rows as $r) {
        $out[] = array(
            'id' => (int) ($r['id'] ?? 0),
            'login' => (string) ($r['login'] ?? ''),
            'v1_sharekeys' => (int) ($r['v1_sharekeys'] ?? 0),
        );
    }

    return $out;
}

/**
 * Returns unioned sources to count v1/v3 sharekeys by user.
 * $targetVersion: 1 or 3
 */
function tpGetSharekeysUserUnionSources(int $targetVersion): string
{
    $tables = array(
        'sharekeys_items',
        'sharekeys_files',
        'sharekeys_fields',
        'sharekeys_logs',
        'sharekeys_suggestions',
    );

    $parts = array();
    foreach ($tables as $t) {
        $tableName = prefixTable($t);
        if (tpTableExists($tableName) === false) {
            continue;
        }
        if (tpColumnExists($tableName, 'encryption_version') === false) {
            continue;
        }

        // safe: table names come from a fixed list + prefixTable
        $parts[] = 'SELECT user_id, COUNT(*) AS cnt FROM ' . $tableName . ' WHERE encryption_version = ' . (int) $targetVersion . ' GROUP BY user_id';
    }

    return implode("\nUNION ALL\n", $parts);
}

function tpGetUsersSharekeysVersionMismatch(array $excludedUserIds = array()): array
{
    $out = array(
        'v3_users_with_v1_sharekeys' => array(),
        'v1_users_with_v3_sharekeys' => array(),
    );

    $excludedSql = '';
    if (empty($excludedUserIds) === false) {
        $excludedSql = ' AND u.id NOT IN (' . implode(',', array_map('intval', $excludedUserIds)) . ')';
    }

    $v1Sources = tpGetSharekeysUserUnionSources(1);
    if ($v1Sources !== '') {
        $sql = '
            SELECT u.id, u.login, SUM(t.cnt) AS sharekeys
            FROM ' . prefixTable('users') . ' u
            INNER JOIN (
                ' . $v1Sources . '
            ) t ON t.user_id = u.id
            WHERE u.encryption_version = 3
            AND u.disabled = 0 AND u.deleted_at IS NULL
            ' . $excludedSql . '
            GROUP BY u.id, u.login
            HAVING sharekeys > 0
            ORDER BY sharekeys DESC
            LIMIT 50
        ';
        $rows = DB::query($sql);
        foreach ($rows as $r) {
            $out['v3_users_with_v1_sharekeys'][] = array(
                'id' => (int) ($r['id'] ?? 0),
                'login' => (string) ($r['login'] ?? ''),
                'sharekeys' => (int) ($r['sharekeys'] ?? 0),
            );
        }
    }

    $v3Sources = tpGetSharekeysUserUnionSources(3);
    if ($v3Sources !== '') {
        $sql = '
            SELECT u.id, u.login, SUM(t.cnt) AS sharekeys
            FROM ' . prefixTable('users') . ' u
            INNER JOIN (
                ' . $v3Sources . '
            ) t ON t.user_id = u.id
            WHERE u.encryption_version = 1
            AND u.disabled = 0 AND u.deleted_at IS NULL
            ' . $excludedSql . '
            GROUP BY u.id, u.login
            HAVING sharekeys > 0
            ORDER BY sharekeys DESC
            LIMIT 50
        ';
        $rows = DB::query($sql);
        foreach ($rows as $r) {
            $out['v1_users_with_v3_sharekeys'][] = array(
                'id' => (int) ($r['id'] ?? 0),
                'login' => (string) ($r['login'] ?? ''),
                'sharekeys' => (int) ($r['sharekeys'] ?? 0),
            );
        }
    }

    return $out;
}

/**
 * Health module helper: retrieve a Teampass "settings" value.
 * - If tpGetSettingsValue() exists (backups module loaded), use it.
 * - Else fallback to reading teampass_misc (type='settings').
 */
function tpHealthGetSettingsValue(string $key, string $default = ''): string
{
    $key = trim($key);
    if ($key === '') {
        return $default;
    }

    if (function_exists('tpGetSettingsValue') === true) {
        return (string) tpGetSettingsValue($key, $default);
    }

    // Fallback: read settings from misc table (no dependency on backups module)
    if (function_exists('tpTableExists') === false || function_exists('prefixTable') === false) {
        return $default;
    }
    if (tpTableExists(prefixTable('misc')) === false) {
        return $default;
    }

    $val = DB::queryFirstField(
        'SELECT valeur
        FROM ' . prefixTable('misc') . '
        WHERE type = %s AND intitule = %s
        LIMIT 1',
        'settings',
        $key
    );

    if ($val === null) {
        return $default;
    }

    $s = (string) $val;
    return $s === '' ? $default : $s;
}

function tpGetBackupsStatus(array $SETTINGS): array
{
    $now = time();

    // Ensure backup metadata helpers are available (used by backups module)
    $backupFunctionsPath = __DIR__ . '/backup.functions.php';
    if (is_file($backupFunctionsPath) === true) {
        require_once $backupFunctionsPath;
    }

    // Base files directory (same logic as backups.queries.php)
    $baseFilesDir = (string) ($SETTINGS['path_to_files_folder'] ?? '');
    if ($baseFilesDir === '' && isset($SETTINGS['cpassman_dir']) === true) {
        $baseFilesDir = rtrim((string) $SETTINGS['cpassman_dir'], '/') . '/files';
    }
    if ($baseFilesDir === '') {
        $baseFilesDir = (string) (__DIR__ . '/../files');
    }
    $baseFilesDir = rtrim($baseFilesDir, '/');

    // Scheduled output dir is configurable; default is <files>/backups
    $scheduledDir = $baseFilesDir . '/backups';
    $scheduledDir = (string) tpHealthGetSettingsValue('bck_scheduled_output_dir', $scheduledDir);
    if ($scheduledDir === '') {
        $scheduledDir = $baseFilesDir . '/backups';
    }
    $scheduledDir = rtrim((string) $scheduledDir, '/');

    // Expected values (current instance)
    $expectedSchemaLevel = function_exists('tpGetSchemaLevel') === true ? (string) tpGetSchemaLevel() : '';
    $expectedTpFilesVersion = function_exists('tpGetTpFilesVersion') === true ? (string) tpGetTpFilesVersion() : '';

    // ------------------------
    // List files (all + latest)
    // ------------------------
    $scheduledAll = tpListBackupSqlFiles($scheduledDir, true, 0);
    $ontheflyAll = tpListBackupSqlFiles($baseFilesDir, false, 0);

    $scheduledLatest = tpListBackupSqlFiles($scheduledDir, true, 10);
    $ontheflyLatest = tpListBackupSqlFiles($baseFilesDir, false, 10);

    // ------------------------
    // Orphan metadata (.meta.json) monitoring
    // ------------------------
    $scheduledMetaOrphans = function_exists('tpListOrphanBackupMetaFiles') ? count(tpListOrphanBackupMetaFiles($scheduledDir)) : 0;
    $ontheflyMetaOrphans = function_exists('tpListOrphanBackupMetaFiles') ? count(tpListOrphanBackupMetaFiles($baseFilesDir)) : 0;

    // ------------------------
    // Build directory health (stats are computed on ALL files)
    // ------------------------
    $scheduledDirHealth = tpBuildBackupDirHealth(
        $scheduledDir,
        'health_backup_scheduled',
        $scheduledLatest,
        $scheduledMetaOrphans,
        array(
            'all_files' => $scheduledAll,
            'expected_schema_level' => $expectedSchemaLevel,
            'expected_tp_files_version' => $expectedTpFilesVersion,
        )
    );

    $ontheflyDirHealth = tpBuildBackupDirHealth(
        $baseFilesDir,
        'health_backup_onthefly',
        $ontheflyLatest,
        $ontheflyMetaOrphans,
        array(
            'all_files' => $ontheflyAll,
            'expected_schema_level' => $expectedSchemaLevel,
            'expected_tp_files_version' => $expectedTpFilesVersion,
        )
    );

    // ------------------------
    // Summary: last backup age (best effort across BOTH dirs)
    // ------------------------
    $lastMtime = 0;
    foreach (array($scheduledAll, $ontheflyAll) as $list) {
        if (isset($list['error']) === true || empty($list) === true) {
            continue;
        }
        foreach ($list as $f) {
            $mt = (int) ($f['mtime'] ?? 0);
            if ($mt > $lastMtime) {
                $lastMtime = $mt;
            }
        }
    }
    $lastAgeHours = $lastMtime > 0 ? round(($now - $lastMtime) / 3600, 2) : null;

    // ------------------------
    // Scheduler health (settings)
    // ------------------------
    $scheduler = array(
        'output_dir' => $scheduledDir,
        'retention_days' => 0,
        'next_run_at' => 0,
        'last_run_at' => 0,
        'last_status' => '',
        'last_message' => '',
        'last_completed_at' => 0,
    );

    $scheduler['output_dir'] = (string) tpHealthGetSettingsValue('bck_scheduled_output_dir', $scheduledDir);
    if ($scheduler['output_dir'] === '') {
        $scheduler['output_dir'] = $scheduledDir;
    }
    $scheduler['retention_days'] = (int) tpHealthGetSettingsValue('bck_scheduled_retention_days', '0');
    $scheduler['next_run_at'] = (int) tpHealthGetSettingsValue('bck_scheduled_next_run_at', '0');
    $scheduler['last_run_at'] = (int) tpHealthGetSettingsValue('bck_scheduled_last_run_at', '0');
    $scheduler['last_status'] = (string) tpHealthGetSettingsValue('bck_scheduled_last_status', '');
    $scheduler['last_message'] = (string) tpHealthGetSettingsValue('bck_scheduled_last_message', '');
    $scheduler['last_completed_at'] = (int) tpHealthGetSettingsValue('bck_scheduled_last_completed_at', '0');

    $scheduler['next_run_at_human'] = $scheduler['next_run_at'] > 0 ? date('Y-m-d H:i:s', (int) $scheduler['next_run_at']) : '';
    $scheduler['last_run_at_human'] = $scheduler['last_run_at'] > 0 ? date('Y-m-d H:i:s', (int) $scheduler['last_run_at']) : '';
    $scheduler['last_completed_at_human'] = $scheduler['last_completed_at'] > 0 ? date('Y-m-d H:i:s', (int) $scheduler['last_completed_at']) : '';

    // ------------------------
    // Latest backups (merged, for quick visibility)
    // ------------------------
    $latest = array();

    if (isset($scheduledLatest['error']) === false && empty($scheduledLatest) === false) {
        foreach ($scheduledLatest as $f) {
            if (is_array($f) === false) {
                continue;
            }
            $f['type'] = 'scheduled';
            $latest[] = $f;
        }
    }

    if (isset($ontheflyLatest['error']) === false && empty($ontheflyLatest) === false) {
        foreach ($ontheflyLatest as $f) {
            if (is_array($f) === false) {
                continue;
            }
            $f['type'] = 'onthefly';
            $latest[] = $f;
        }
    }

    usort($latest, static function (array $a, array $b): int {
        return ((int) ($b['mtime'] ?? 0)) <=> ((int) ($a['mtime'] ?? 0));
    });
    $latest = array_slice($latest, 0, 10);

    foreach ($latest as $k => $f) {
        $schema = isset($f['schema_level']) && is_scalar($f['schema_level']) ? (string) $f['schema_level'] : '';
        if ($schema === '' || $expectedSchemaLevel === '') {
            $latest[$k]['compatibility'] = 'unknown';
        } elseif ($schema === $expectedSchemaLevel) {
            $latest[$k]['compatibility'] = 'compatible';
        } else {
            $latest[$k]['compatibility'] = 'incompatible';
        }
    }


    // ------------------------
    // Backup history (merged, last 20 backups across scheduled + on-the-fly)
    // ------------------------
    $backupHistory = array();

    $sources = array(
        array('type' => 'scheduled', 'files' => $scheduledAll),
        array('type' => 'onthefly', 'files' => $ontheflyAll),
    );

    foreach ($sources as $src) {
        if (is_array($src) === false || isset($src['files']) === false) {
            continue;
        }
        $type = (string) ($src['type'] ?? 'unknown');
        $files = $src['files'];
        if (is_array($files) === false || isset($files['error']) === true || empty($files) === true) {
            continue;
        }

        foreach ($files as $f) {
            if (is_array($f) === false) {
                continue;
            }

            $schemaLevel = isset($f['schema_level']) && $f['schema_level'] !== null ? (string) $f['schema_level'] : '';
            $tpFilesVersion = isset($f['tp_files_version']) && $f['tp_files_version'] !== null ? (string) $f['tp_files_version'] : '';

            $compatibility = 'unknown';
            if ($expectedSchemaLevel !== '' && $schemaLevel !== '') {
                $compatibility = ($schemaLevel === $expectedSchemaLevel) ? 'compatible' : 'incompatible';
                if ($compatibility === 'compatible' && $expectedTpFilesVersion !== '' && $tpFilesVersion !== '' && $tpFilesVersion !== $expectedTpFilesVersion) {
                    $compatibility = 'incompatible';
                }
            }

            $f['type'] = $type;
            $f['compatibility'] = $compatibility;

            $backupHistory[] = $f;
        }
    }

    usort($backupHistory, static function (array $a, array $b): int {
        return ((int) ($b['mtime'] ?? 0)) <=> ((int) ($a['mtime'] ?? 0));
    });
    $backupHistory = array_slice($backupHistory, 0, 20);

    // ------------------------
    // Tasks related to backups (background_tasks)
    // ------------------------
    // Note: do not rely on information_schema checks here (some DB accounts may not have access),
    // just try querying and gracefully fallback to empty arrays on error.
    $tasks = array();
    try {
        $rows = DB::query(
            'SELECT created_at, started_at, finished_at, process_type, status, error_message
            FROM ' . prefixTable('background_tasks') . '
            WHERE process_type LIKE %ss OR arguments LIKE %ss
            ORDER BY created_at DESC
            LIMIT 10',
            '%backup%',
            '%backup%'
        );
        foreach ($rows as $r) {
            $tasks[] = array(
                'process_type' => (string) ($r['process_type'] ?? ''),
                'status' => (string) ($r['status'] ?? ''),
                'created_at' => (int) ($r['created_at'] ?? 0),
                'created_at_human' => isset($r['created_at']) ? date('Y-m-d H:i:s', (int) $r['created_at']) : '',
                'finished_at' => (int) ($r['finished_at'] ?? 0),
                'finished_at_human' => isset($r['finished_at']) && (int) $r['finished_at'] > 0 ? date('Y-m-d H:i:s', (int) $r['finished_at']) : '',
                'error_message' => (string) ($r['error_message'] ?? ''),
            );
        }
    } catch (Throwable $e) {
        $tasks = array();
    }

    // ------------------------
    // Task logs related to backups (background_tasks_logs)
    // ------------------------
    $taskLogs = array();
    try {
        $rows = DB::query(
            'SELECT created_at, job, status, updated_at, finished_at, treated_objects
            FROM ' . prefixTable('background_tasks_logs') . '
            WHERE job LIKE %ss OR job LIKE %ss
            ORDER BY created_at DESC
            LIMIT 20',
            '%backup%',
            '%bck%'
        );
        foreach ($rows as $r) {
            $taskLogs[] = array(
                'created_at' => (int) ($r['created_at'] ?? 0),
                'created_at_human' => isset($r['created_at']) && (int) $r['created_at'] > 0 ? date('Y-m-d H:i:s', (int) $r['created_at']) : '',
                'job' => (string) ($r['job'] ?? ''),
                'status' => (string) ($r['status'] ?? ''),
                'updated_at' => (int) ($r['updated_at'] ?? 0),
                'updated_at_human' => isset($r['updated_at']) && (int) $r['updated_at'] > 0 ? date('Y-m-d H:i:s', (int) $r['updated_at']) : '',
                'finished_at' => (int) ($r['finished_at'] ?? 0),
                'finished_at_human' => isset($r['finished_at']) && (int) $r['finished_at'] > 0 ? date('Y-m-d H:i:s', (int) $r['finished_at']) : '',
                'treated_objects' => isset($r['treated_objects']) ? (string) $r['treated_objects'] : '',
            );
        }
    } catch (Throwable $e) {
        $taskLogs = array();
    }

    // Fallback: if backup executions are not written in background_tasks_logs,
    // reuse background_tasks entries as "logs" to avoid showing an empty block.
    if (empty($taskLogs) === true && empty($tasks) === false) {
        foreach ($tasks as $t) {
            $taskLogs[] = array(
                'created_at' => (int) ($t['created_at'] ?? 0),
                'created_at_human' => (string) ($t['created_at_human'] ?? ''),
                'job' => (string) ($t['process_type'] ?? ''),
                'status' => (string) ($t['status'] ?? ''),
                'updated_at' => (int) ($t['created_at'] ?? 0),
                'updated_at_human' => (string) ($t['created_at_human'] ?? ''),
                'finished_at' => (int) ($t['finished_at'] ?? 0),
                'finished_at_human' => (string) ($t['finished_at_human'] ?? ''),
                'treated_objects' => '',
            );
        }
    }

$scheduledStats = isset($scheduledDirHealth['stats']) && is_array($scheduledDirHealth['stats']) ? $scheduledDirHealth['stats'] : array();
    $ontheflyStats = isset($ontheflyDirHealth['stats']) && is_array($ontheflyDirHealth['stats']) ? $ontheflyDirHealth['stats'] : array();

    $totalFiles = (int) ($scheduledStats['total_files'] ?? 0) + (int) ($ontheflyStats['total_files'] ?? 0);
    $totalCompatible = (int) ($scheduledStats['compatible'] ?? 0) + (int) ($ontheflyStats['compatible'] ?? 0);
    $anomaliesTotal = (int) ($scheduledStats['anomalies_total'] ?? 0) + (int) ($ontheflyStats['anomalies_total'] ?? 0);

    $summaryStatus = 'info';
    $summaryTextKey = 'health_backup_no_data';

    if ($totalFiles === 0) {
        $summaryStatus = 'danger';
        $summaryTextKey = 'health_backup_no_files';
    } elseif ($expectedSchemaLevel !== '' && $totalCompatible === 0) {
        $summaryStatus = 'warning';
        $summaryTextKey = 'health_backup_no_compatible_backups';
    } elseif ($anomaliesTotal > 0) {
        $summaryStatus = 'warning';
        $summaryTextKey = 'health_backup_anomalies_found';
    } else {
        $summaryStatus = 'success';
        $summaryTextKey = 'health_status_ok';
    }

    return array(
        'expected_schema_level' => $expectedSchemaLevel,
        'expected_tp_files_version' => $expectedTpFilesVersion,
        'directories' => array(
            'scheduled' => $scheduledDirHealth,
            'onthefly' => $ontheflyDirHealth,
        ),
        'scheduler' => $scheduler,
        'latest_files' => $latest,
        'backup_history' => $backupHistory,
        'tasks' => $tasks,
        'task_logs' => $taskLogs,
        'summary' => array(
            'status' => $summaryStatus,
            'text_key' => $summaryTextKey,
            'last_backup_age_hours' => $lastAgeHours,
            'anomalies_total' => $anomaliesTotal,
            'total_files' => $totalFiles,
            'total_compatible' => $totalCompatible,
        ),
    );
}


function tpGetBackupDirStatus(string $dir, string $prefix, int $limit = 10, array $options = array()): array
{
    $now = time();

    $labelKey = (string) ($options['label_key'] ?? '');
    $nameRegex = $options['name_regex'] ?? null;
    $allowedSuffixes = $options['allowed_suffixes'] ?? null;

    $files = tpListBackupFiles($dir, $prefix, $limit, array(
        'name_regex' => $nameRegex,
        'allowed_suffixes' => $allowedSuffixes,
    ));

    $summaryStatus = 'info';
    $summaryTextKey = 'health_backup_no_data';
    $lastAgeHours = null;

    if (isset($files['error']) === true) {
        $summaryStatus = 'warning';
        $summaryTextKey = 'health_backup_path_not_readable';
    } elseif (empty($files) === true) {
        $summaryStatus = 'danger';
        $summaryTextKey = 'health_backup_no_files';
    } else {
        $lastMtime = (int) ($files[0]['mtime'] ?? 0);
        if ($lastMtime > 0) {
            $lastAgeHours = round(($now - $lastMtime) / 3600, 2);
            if ($lastAgeHours <= 24) {
                $summaryStatus = 'success';
                $summaryTextKey = 'health_status_ok';
            } else {
                $summaryStatus = 'warning';
                $summaryTextKey = 'health_backup_old_fmt';
            }
        } else {
            $summaryStatus = 'warning';
            $summaryTextKey = 'health_backup_no_data';
        }
    }

    return array(
        'label_key' => $labelKey,
        'path' => $dir,
        'filename_prefix' => $prefix,
        'files' => $files,
        'summary' => array(
            'status' => $summaryStatus,
            'text_key' => $summaryTextKey,
            'last_backup_age_hours' => $lastAgeHours,
        ),
    );
}

function tpListBackupFiles(string $dir, string $prefix, int $limit = 10, array $options = array()): array
{
    $dir = trim($dir);
    if ($dir === '' || is_dir($dir) === false) {
        return array('error' => 'dir_missing');
    }
    if (is_readable($dir) === false) {
        return array('error' => 'dir_not_readable');
    }

    $nameRegex = $options['name_regex'] ?? null;
    $allowedSuffixes = $options['allowed_suffixes'] ?? null;

    $items = array();
    $it = new DirectoryIterator($dir);
    foreach ($it as $f) {
        if ($f->isDot() === true || $f->isFile() === false) {
            continue;
        }

        $name = $f->getFilename();

        if ($prefix !== '' && strpos($name, $prefix) !== 0) {
            continue;
        }

        if (is_string($nameRegex) === true && $nameRegex !== '' && @preg_match($nameRegex, $name) !== 1) {
            continue;
        }

        if (is_array($allowedSuffixes) === true && empty($allowedSuffixes) === false) {
            $match = false;
            foreach ($allowedSuffixes as $suf) {
                $suf = (string) $suf;
                if ($suf !== '' && strcasecmp(substr($name, -strlen($suf)), $suf) === 0) {
                    $match = true;
                    break;
                }
            }
            if ($match === false) {
                continue;
            }
        }

        $items[] = array(
            'name' => $name,
            'mtime' => (int) $f->getMTime(),
            'size_mb' => round(((float) $f->getSize()) / 1024 / 1024, 2),
        );
    }

    usort($items, static function (array $a, array $b): int {
        return ((int) ($b['mtime'] ?? 0)) <=> ((int) ($a['mtime'] ?? 0));
    });

    $items = array_slice($items, 0, $limit);

    foreach ($items as $k => $v) {
        $items[$k]['mtime_human'] = isset($v['mtime']) ? date('Y-m-d H:i:s', (int) $v['mtime']) : '';
    }

    return $items;
}


function tpListBackupSqlFiles(string $dir, bool $scheduledOnly, int $limit = 10): array
{
    $dir = trim($dir);
    if ($dir === '' || is_dir($dir) === false) {
        return array('error' => 'dir_missing');
    }
    if (is_readable($dir) === false) {
        return array('error' => 'dir_not_readable');
    }

    $dir = rtrim($dir, '/');
    $pattern = $scheduledOnly ? ($dir . '/scheduled-*.sql') : ($dir . '/*.sql');

    $paths = glob($pattern);
    if ($paths === false) {
        $paths = array();
    }

    $items = array();
    foreach ($paths as $fp) {
        if (is_string($fp) === false || $fp === '' || is_file($fp) === false) {
            continue;
        }

        $bn = basename($fp);

        if ($scheduledOnly === false) {
            // Skip scheduled backups and temporary files (same filters as backups.queries.php)
            if (strpos($bn, 'scheduled-') === 0) {
                continue;
            }
            if (strpos($bn, 'defuse_temp_') === 0 || strpos($bn, 'defuse_temp_restore_') === 0) {
                continue;
            }
        }

        $comment = null;
        if (function_exists('tpGetBackupCommentFromMeta') === true) {
            $c = (string) tpGetBackupCommentFromMeta($fp);
            $comment = $c !== '' ? $c : null;
        }

        $tpFilesVersion = null;
        if (function_exists('tpGetBackupTpFilesVersionFromMeta') === true) {
            $v = (string) tpGetBackupTpFilesVersionFromMeta($fp);
            $tpFilesVersion = $v !== '' ? $v : null;
        }

        $schemaLevel = null;
        if (function_exists('tpGetBackupSchemaLevelFromMetaOrFilename') === true) {
            $sl = (string) tpGetBackupSchemaLevelFromMetaOrFilename($fp);
            $schemaLevel = $sl !== '' ? $sl : null;
        } else {
            if (preg_match('/-sl(\d+)\.sql$/', $bn, $m) === 1) {
                $schemaLevel = (string) $m[1];
            }
        }

        $mtime = (int) @filemtime($fp);
        $items[] = array(
            'name' => $bn,
            'mtime' => $mtime,
            'mtime_human' => $mtime > 0 ? date('Y-m-d H:i:s', $mtime) : '',
            'size_mb' => round(((float) @filesize($fp)) / 1024 / 1024, 2),
            'schema_level' => $schemaLevel,
            'tp_files_version' => $tpFilesVersion,
            'comment' => $comment,
        );
    }

    usort($items, static function (array $a, array $b): int {
        return ((int) ($b['mtime'] ?? 0)) <=> ((int) ($a['mtime'] ?? 0));
    });

    if ($limit <= 0) {
        return $items;
    }

    return array_slice($items, 0, $limit);
}

function tpBuildBackupDirHealth(string $dir, string $labelKey, array $files, int $metaOrphans = 0, array $options = array()): array
{
    $now = time();

    $expectedSchemaLevel = '';
    if (isset($options['expected_schema_level']) === true && is_scalar($options['expected_schema_level'])) {
        $expectedSchemaLevel = (string) $options['expected_schema_level'];
    }

    $expectedTpFilesVersion = '';
    if (isset($options['expected_tp_files_version']) === true && is_scalar($options['expected_tp_files_version'])) {
        $expectedTpFilesVersion = (string) $options['expected_tp_files_version'];
    }

    $allFiles = $files;
    if (isset($options['all_files']) === true && is_array($options['all_files']) === true) {
        $allFiles = $options['all_files'];
    }

    $summaryStatus = 'info';
    $summaryTextKey = 'health_backup_no_data';
    $lastAgeHours = null;

    $stats = array(
        'total_files' => 0,
        'compatible' => 0,
        'incompatible' => 0,
        'unknown_schema' => 0,
        'missing_meta' => 0,
        'meta_orphans' => $metaOrphans,
        'tp_version_mismatch' => 0,
        'last_backup_at' => 0,
        'last_backup_at_human' => '',
        'last_backup_file' => '',
        'last_backup_size_mb' => null,
        'last_backup_comment' => null,
        'last_backup_compatibility' => 'unknown',
        'last_compatible_backup_at' => 0,
        'last_compatible_backup_at_human' => '',
        'last_compatible_backup_file' => '',
        'last_compatible_backup_size_mb' => null,
        'last_compatible_backup_comment' => null,
        'expected_schema_level' => $expectedSchemaLevel,
        'expected_tp_files_version' => $expectedTpFilesVersion,
        'anomalies_total' => 0,
    );

    if (isset($allFiles['error']) === true) {
        $summaryStatus = 'warning';
        $summaryTextKey = 'health_backup_path_not_readable';
    } elseif (empty($allFiles) === true) {
        $summaryStatus = 'danger';
        $summaryTextKey = 'health_backup_no_files';
    } else {
        foreach ($allFiles as $f) {
            if (is_array($f) === false) {
                continue;
            }
            $name = isset($f['name']) && is_scalar($f['name']) ? (string) $f['name'] : '';
            if ($name === '') {
                continue;
            }

            $stats['total_files']++;

            $mtime = (int) ($f['mtime'] ?? 0);
            $sizeMb = isset($f['size_mb']) ? (float) $f['size_mb'] : null;
            $comment = isset($f['comment']) && is_scalar($f['comment']) ? (string) $f['comment'] : null;
            $comment = $comment !== null ? trim($comment) : null;
            if ($comment === '') {
                $comment = null;
            }

            $schemaLevel = isset($f['schema_level']) && is_scalar($f['schema_level']) ? (string) $f['schema_level'] : '';
            $tpFilesVersion = isset($f['tp_files_version']) && is_scalar($f['tp_files_version']) ? (string) $f['tp_files_version'] : '';

            $compatibility = 'unknown';
            if ($schemaLevel !== '' && $expectedSchemaLevel !== '') {
                $compatibility = ($schemaLevel === $expectedSchemaLevel) ? 'compatible' : 'incompatible';
            }

            if ($mtime > $stats['last_backup_at']) {
                $stats['last_backup_at'] = $mtime;
                $stats['last_backup_at_human'] = date('Y-m-d H:i:s', $mtime);
                $stats['last_backup_file'] = $name;
                $stats['last_backup_size_mb'] = $sizeMb;
                $stats['last_backup_comment'] = $comment;
                $stats['last_backup_compatibility'] = $compatibility;
            }

            if ($schemaLevel === '') {
                $stats['unknown_schema']++;
            } elseif ($expectedSchemaLevel !== '') {
                if ($schemaLevel === $expectedSchemaLevel) {
                    $stats['compatible']++;
                    if ($mtime > $stats['last_compatible_backup_at']) {
                        $stats['last_compatible_backup_at'] = $mtime;
                        $stats['last_compatible_backup_at_human'] = date('Y-m-d H:i:s', $mtime);
                        $stats['last_compatible_backup_file'] = $name;
                        $stats['last_compatible_backup_size_mb'] = $sizeMb;
                        $stats['last_compatible_backup_comment'] = $comment;
                    }
                } else {
                    $stats['incompatible']++;
                }
            }

            if ($expectedTpFilesVersion !== '' && $tpFilesVersion !== '' && $tpFilesVersion !== $expectedTpFilesVersion) {
                $stats['tp_version_mismatch']++;
            }

            // Check missing meta (.meta.json)
            $fullPath = rtrim($dir, '/') . '/' . $name;
            $metaPath = $fullPath . '.meta.json';
            if (function_exists('tpGetBackupMetadataPath') === true) {
                $metaPath = (string) tpGetBackupMetadataPath($fullPath);
            }
            if (is_file($metaPath) === false) {
                $stats['missing_meta']++;
            }
        }

        // Best effort age calculation based on latest backup
        if ($stats['last_backup_at'] > 0) {
            $lastAgeHours = round(($now - $stats['last_backup_at']) / 3600, 2);
        }

        $stats['anomalies_total'] =
            (int) $metaOrphans
            + (int) $stats['missing_meta']
            + (int) $stats['unknown_schema']
            + (int) $stats['tp_version_mismatch']
            + (int) ($expectedSchemaLevel !== '' ? $stats['incompatible'] : 0);

        if ($expectedSchemaLevel !== '' && $stats['compatible'] === 0) {
            $summaryStatus = 'warning';
            $summaryTextKey = 'health_backup_no_compatible_backups';
        } elseif ($stats['anomalies_total'] > 0) {
            $summaryStatus = 'warning';
            $summaryTextKey = 'health_backup_anomalies_found';
        } else {
            $summaryStatus = 'success';
            $summaryTextKey = 'health_status_ok';
        }
    }

    return array(
        'label_key' => $labelKey,
        'path' => $dir,
        'files' => $files,
        'meta_orphans' => $metaOrphans,
        'stats' => $stats,
        'summary' => array(
            'status' => $summaryStatus,
            'text_key' => $summaryTextKey,
            'last_backup_age_hours' => $lastAgeHours,
        ),
    );
}


function tpGetTeampassSettingsForHealth(array $SETTINGS): array
{
    $wanted = array(
        'timezone',
        'path_to_upload_folder',
        'upload_maxfilesize',
        'task_maximum_run_time',
        'enable_tasks_manager',
        'tasks_manager_refreshing_period',
        'enable_tasks_log',
        'tasks_log_retention_delay',
        'bck_script_path',
        'bck_script_filename',
    );

    $out = array();
    foreach ($wanted as $k) {
        if (isset($SETTINGS[$k]) === true) {
            $out[$k] = $SETTINGS[$k];
        }
    }

    return $out;
}

function tpGetSystemChecks(array $phpIni, array $tpSettings, Language $lang): array
{
    $checks = array();

    // Upload size consistency
    $tpUpload = isset($tpSettings['upload_maxfilesize']) ? tpIniSizeToBytes((string) $tpSettings['upload_maxfilesize']) : 0;
    $phpUpload = isset($phpIni['ini']['upload_max_filesize']) ? tpIniSizeToBytes((string) $phpIni['ini']['upload_max_filesize']) : 0;
    $phpPost = isset($phpIni['ini']['post_max_size']) ? tpIniSizeToBytes((string) $phpIni['ini']['post_max_size']) : 0;

    $limit = 0;
    if ($phpUpload > 0 && $phpPost > 0) {
        $limit = min($phpUpload, $phpPost);
    } elseif ($phpUpload > 0) {
        $limit = $phpUpload;
    } elseif ($phpPost > 0) {
        $limit = $phpPost;
    }

    if ($tpUpload > 0 && $limit > 0 && $tpUpload > $limit) {
        $checks[] = array(
            'status' => 'warning',
            'title' => $lang->get('health_check_upload_limits'),
            'text' => sprintf(
                $lang->get('health_check_upload_limits_mismatch'),
                (string) $tpSettings['upload_maxfilesize'],
                (string) ($phpIni['ini']['upload_max_filesize'] ?? ''),
                (string) ($phpIni['ini']['post_max_size'] ?? '')
            ),
        );
    } else {
        $checks[] = array(
            'status' => 'success',
            'title' => $lang->get('health_check_upload_limits'),
            'text' => $lang->get('health_status_ok'),
        );
    }

    // Execution time consistency
    $tpTaskMax = isset($tpSettings['task_maximum_run_time']) ? (int) $tpSettings['task_maximum_run_time'] : 0;
    $phpExec = isset($phpIni['ini']['max_execution_time']) ? (int) $phpIni['ini']['max_execution_time'] : 0;

    if ($tpTaskMax > 0 && $phpExec > 0 && $phpExec < $tpTaskMax) {
        $checks[] = array(
            'status' => 'warning',
            'title' => $lang->get('health_check_execution_time'),
            'text' => sprintf(
                $lang->get('health_check_execution_time_mismatch'),
                (string) $tpTaskMax,
                (string) $phpExec
            ),
        );
    } else {
        $checks[] = array(
            'status' => 'success',
            'title' => $lang->get('health_check_execution_time'),
            'text' => $lang->get('health_status_ok'),
        );
    }

    return $checks;
}
