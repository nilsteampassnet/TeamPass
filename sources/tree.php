<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @project   Teampass
 * @version   3.0.0.22
 * @file      tree.php
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2023 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 *
 * @see       https://www.teampass.net
 */


require_once 'SecureHandler.php';
session_name('teampass_session');
session_start();
if (
    isset($_SESSION['CPM']) === false
    || $_SESSION['CPM'] !== 1
    || isset($_SESSION['key']) === false
    || empty($_SESSION['key']) === true
) {
    die('Hacking attempt...');
}

// Load config
include __DIR__.'/../includes/config/tp.config.php';

// includes
require_once $SETTINGS['cpassman_dir'] . '/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
require_once $SETTINGS['cpassman_dir'] . '/includes/language/' . $_SESSION['user']['user_language'] . '.php';
require_once $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';

// header
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Define Timezone
if (isset($SETTINGS['timezone'])) {
    date_default_timezone_set($SETTINGS['timezone']);
} else {
    date_default_timezone_set('UTC');
}

// Connect to mysql server
require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = DB_PASSWD_CLEAR;
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;
DB::$ssl = DB_SSL;
DB::$connect_options = DB_CONNECT_OPTIONS;

// Superglobal load
require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();

// Prepare sanitization
$data = [
    'forbidenPfs' => isset($_SESSION['forbiden_pfs']) === true ? json_encode($_SESSION['forbiden_pfs']) : '{}',
    'visibleFolders' => isset($_SESSION['groupes_visibles']) === true ? json_encode($_SESSION['groupes_visibles']) : '{}',
    'userId' => isset($_SESSION['user_id']) === true ? $_SESSION['user_id'] : '',
    'userLogin' => isset($_SESSION['login']) === true ? $_SESSION['login'] : '',
    'userReadOnly' => isset($_SESSION['user_read_only']) === true ? $_SESSION['user_read_only'] : '',
    'limitedFolders' => isset($_SESSION['list_folders_limited']) === true ? json_encode($_SESSION['list_folders_limited']) : '{}',
    'readOnlyFolders' => isset($_SESSION['read_only_folders']) === true ? json_encode($_SESSION['read_only_folders']) : '{}',
    'personalVisibleFolders' => isset($_SESSION['personal_visible_groups']) === true ? json_encode($_SESSION['personal_visible_groups']) : '{}',
    'userTreeLastRefresh' => isset($_SESSION['user_tree_last_refresh_timestamp']) === true ? $_SESSION['user_tree_last_refresh_timestamp'] : '',
    'forceRefresh' => isset($_GET['force_refresh']) === true ? $_GET['force_refresh'] : '',
    'nodeId' => isset($_GET['id']) === true ? $_GET['id'] : '',
    'restrictedFoldersForItems' => isset($_GET['list_restricted_folders_for_items']) === true ? json_encode($_GET['list_restricted_folders_for_items']) : '{}',
    'noAccessFolders' => isset($_SESSION['no_access_folders']) === true ? json_encode($_SESSION['no_access_folders']) : '{}',
    'personalFolders' => isset($_SESSION['personal_folders']) === true ? json_encode($_SESSION['personal_folders']) : '{}',
    'userCanCreateRootFolder' => isset($_SESSION['can_create_root_folder']) === true ? json_encode($_SESSION['can_create_root_folder']) : '{}',
    'userTreeLoadStrategy' => isset($_SESSION['user']['user_treeloadstrategy']) === true ? $_SESSION['user']['user_treeloadstrategy'] : '',
];

$filters = [
    'forbidenPfs' => 'cast:array',
    'visibleFolders' => 'cast:array',
    'userId' => 'cast:integer',
    'userLogin' => 'trim|escape',
    'userReadOnly' => 'cast:boolean',
    'limitedFolders' => 'cast:array',
    'readOnlyFolders' => 'cast:array',
    'personalVisibleFolders' => 'cast:array',
    'userTreeLastRefresh' => 'cast:integer',
    'forceRefresh' => 'cast:integer',
    'nodeId' => 'cast:integer',
    'restrictedFoldersForItems' => 'cast:array',
    'noAccessFolders' => 'cast:array',
    'personalFolders' => 'cast:array',
    'userCanCreateRootFolder' => 'cast:array',
    'userTreeLoadStrategy' => 'trim|escape',
];

$inputData = dataSanitizer(
    $data,
    $filters,
    $SETTINGS['cpassman_dir']
);

$lastFolderChange = DB::queryfirstrow(
    'SELECT valeur FROM ' . prefixTable('misc') . '
    WHERE type = %s AND intitule = %s',
    'timestamp',
    'last_folder_change'
);
if (DB::count() === 0) {
    $lastFolderChange['valeur'] = 0;
}

// Should we use a cache or refresh the tree
$goTreeRefresh = loadTreeStrategy(
    (int) $lastFolderChange['valeur'],
    (int) $inputData['userTreeLastRefresh'],
    /** @scrutinizer ignore-type */(array) (is_null($superGlobal->get('user_tree_structure', 'SESSION')) === true || empty($superGlobal->get('user_tree_structure', 'SESSION')) === true) ? [] : $superGlobal->get('user_tree_structure', 'SESSION'),
    (int) $inputData['userId'],
    (int) $inputData['forceRefresh']
);

// We don't use the cache if an ID of folder is provided
if ($goTreeRefresh['state'] === true || empty($inputData['nodeId']) === false || $inputData['userTreeLoadStrategy'] === 'sequential') {
    // Build tree
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Tree/NestedTree/NestedTree.php';
    $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

    if (
        isset($inputData['limitedFolders']) === true
        && is_array($inputData['limitedFolders']) === true
        && count($inputData['limitedFolders']) > 0
    ) {
        $listFoldersLimitedKeys = array_keys($inputData['limitedFolders']);
    } else {
        $listFoldersLimitedKeys = array();
    }
    
    // list of items accessible but not in an allowed folder
    if (
        isset($inputData['restrictedFoldersForItems']) === true
        && count($inputData['restrictedFoldersForItems']) > 0
    ) {
        $listRestrictedFoldersForItemsKeys = @array_keys($inputData['restrictedFoldersForItems']);
    } else {
        $listRestrictedFoldersForItemsKeys = array();
    }
    
    $ret_json = array();
    $last_visible_parent = -1;
    $last_visible_parent_level = 1;
    $nodeId = isset($inputData['nodeId']) === true && is_int($inputData['nodeId']) === true ? $inputData['nodeId'] : 0;

    // build the tree to be displayed
    if (showFolderToUser(
        $nodeId,
        $inputData['forbidenPfs'],
        $inputData['visibleFolders'],
        $listFoldersLimitedKeys,
        $listRestrictedFoldersForItemsKeys
    ) === true)
    {
        if ($inputData['userTreeLoadStrategy'] === 'sequential') {
            // SEQUENTIAL MODE
            $completTree = $tree->getDescendants(empty($nodeId) === true ? 0 : (int) $nodeId, false, true, false);
            foreach ($completTree as $child) {
                recursiveTree(
                    (int) $child->id,
                    $child,
                    /** @scrutinizer ignore-type */ $tree,
                    $listFoldersLimitedKeys,
                    $listRestrictedFoldersForItemsKeys,
                    $last_visible_parent,
                    $last_visible_parent_level,
                    $SETTINGS,
                    $inputData,
                    $ret_json
                );
            }

        } else {
            // FULL MODE
            $completTree = $tree->getTreeWithChildren();
            foreach ($completTree[0]->children as $child) {
                recursiveTree(
                    (int) $child,
                    $completTree[$child],
                    /** @scrutinizer ignore-type */ $tree,
                    $listFoldersLimitedKeys,
                    $listRestrictedFoldersForItemsKeys,
                    $last_visible_parent,
                    $last_visible_parent_level,
                    $SETTINGS,
                    $inputData,
                    $ret_json
                );
            }
        }
    }

    // Save in SESSION
    $superGlobal->put('user_tree_structure', $ret_json, 'SESSION');
    $superGlobal->put('user_tree_last_refresh_timestamp', time(), 'SESSION');

    $ret_json = json_encode($ret_json);

    // Save user folders tree
    cacheTreeUserHandler(
        (int) $inputData['userId'],
        $ret_json,
        $SETTINGS
    );

    // Send back
    echo $ret_json;
} else {
    echo $goTreeRefresh['data'];
}


/**
 * Check if user can see this folder based upon rights
 *
 * @param integer $nodeId
 * @param array $session_forbiden_pfs
 * @param array $session_groupes_visibles
 * @param array $listFoldersLimitedKeys
 * @param array $listRestrictedFoldersForItemsKeys
 * @return boolean
 */
function showFolderToUser(
    int $nodeId,
    array $session_forbiden_pfs,
    array $session_groupes_visibles,
    array $listFoldersLimitedKeys,
    array $listRestrictedFoldersForItemsKeys
): bool
{
    $big_array = array_diff(array_unique(array_merge($session_groupes_visibles, $listFoldersLimitedKeys, $listRestrictedFoldersForItemsKeys), SORT_NUMERIC), $session_forbiden_pfs);
    //print_r($session_groupes_visibles);
    if ($nodeId === 0 || in_array($nodeId, $big_array) === true) {
        return true;
    }
    return false;
}



/**
 * Get through complete tree
 *
 * @param int     $nodeId                            Id
 * @param stdClass   $currentNode                       Tree info
 * @param Tree\NestedTree\NestedTree   $tree                              The tree
 * @param array   $listFoldersLimitedKeys            Limited
 * @param array   $listRestrictedFoldersForItemsKeys Restricted
 * @param int     $last_visible_parent               Visible parent
 * @param int     $last_visible_parent_level         Parent level
 * @param array   $SETTINGS                          Teampass settings
 * @param array   $inputData,
 * @param array   $ret_json
 *
 * @return array
 */
function recursiveTree(
    int $nodeId,
    stdClass $currentNode,
    Tree\NestedTree\NestedTree $tree,
    array $listFoldersLimitedKeys,
    array $listRestrictedFoldersForItemsKeys,
    int $last_visible_parent,
    int $last_visible_parent_level,
    array $SETTINGS,
    array $inputData,
    array &$ret_json = array()
) {
    $text = '';
    $title = '';
    $show_but_block = false;
    
    // Load config
    include __DIR__.'/../includes/config/tp.config.php';

    $displayThisNode = false;
    $nbItemsInSubfolders = $nbSubfolders = $nbItemsInFolder = 0;
    $nodeDescendants = $nodeDirectDescendants = $tree->getDescendants($nodeId, true, false, false);
    array_shift($nodeDirectDescendants); // get only the children

    foreach ($nodeDescendants as $node) {
        if (
            in_array($node->id, array_merge($inputData['personalFolders'], $inputData['visibleFolders'])) === true
        ) {
            // Final check - is PF allowed?
            if (
                (int) $node->personal_folder === 1
                && (int) $SETTINGS['enable_pf_feature'] === 1
                && in_array($node->id, $inputData['personalFolders']) === false
            ) {
                $displayThisNode = false;
            } else {
                $displayThisNode = true;
                $nbItemsInSubfolders = (int) $node->nb_items_in_subfolders;
                $nbItemsInFolder = (int) $node->nb_items_in_folder;
                $nbSubfolders = (int) $node->nb_subfolders;
                break;
            }
        }
    }
    
    if ($displayThisNode === true) {
        handleNode(
            (int) $nodeId,
            $currentNode,
            $tree,
            $listFoldersLimitedKeys,
            $listRestrictedFoldersForItemsKeys,
            $last_visible_parent,
            $last_visible_parent_level,
            $SETTINGS,
            $inputData,
            $text,
            $nbItemsInSubfolders,
            $nbSubfolders,
            $nbItemsInFolder,
            $nodeDirectDescendants,
            $ret_json
        );
    }
    
    return $ret_json;
}

/**
 * Permits to get the Node definition
 *
 * @param integer $nodeId
 * @param stdClass $currentNode
 * @param Tree\NestedTree\NestedTree $tree
 * @param array $listFoldersLimitedKeys
 * @param array $listRestrictedFoldersForItemsKeys
 * @param integer $last_visible_parent
 * @param integer $last_visible_parent_level
 * @param array $SETTINGS
 * @param array $inputData
 * @param string $text
 * @param integer $nbItemsInSubfolders
 * @param integer $nbSubfolders
 * @param integer $nbItemsInFolder
 * @param array $nodeDirectDescendants
 * @param array $ret_json
 * @return void
 */
function handleNode(
    int $nodeId,
    stdClass $currentNode,
    Tree\NestedTree\NestedTree $tree,
    array $listFoldersLimitedKeys,
    array $listRestrictedFoldersForItemsKeys,
    int $last_visible_parent,
    int $last_visible_parent_level,
    array $SETTINGS,
    array $inputData,
    string $text,
    int $nbItemsInSubfolders,
    int $nbSubfolders,
    int $nbItemsInFolder,
    array $nodeDirectDescendants,
    array &$ret_json = array()
)
{
    // If personal Folder, convert id into user name
    if ((int) $currentNode->title === (int) $inputData['userId'] && (int) $currentNode->nlevel === 1) {
        $currentNode->title = $inputData['userLogin'];
    }

    // Decode if needed
    $currentNode->title = htmlspecialchars_decode($currentNode->title, ENT_QUOTES);

    $nodeData = prepareNodeData(
        (int) $nodeId,
        $inputData['visibleFolders'],
        $inputData['readOnlyFolders'],
        $inputData['personalVisibleFolders'],
        (int) $nbItemsInFolder,
        (int) $nbItemsInSubfolders,
        (int) $nbSubfolders,
        $inputData['limitedFolders'],
        (int) $SETTINGS['show_only_accessible_folders'],
        isset($SETTINGS['tree_counters']) === true && isset($SETTINGS['enable_tasks_manager']) === true && (int) $SETTINGS['enable_tasks_manager'] === 1 && (int) $SETTINGS['tree_counters'] === 1 ? 1 : 0,
        (bool) $inputData['userReadOnly'],
        $listFoldersLimitedKeys,
        $listRestrictedFoldersForItemsKeys,
        $inputData['restrictedFoldersForItems'],
        $inputData['personalFolders'],
        $nodeDirectDescendants
    );

    // Prepare JSON 
    $tmpRetArray = prepareNodeJson(
        $currentNode,
        $nodeData,
        $inputData,
        $ret_json,
        $last_visible_parent,
        $last_visible_parent_level,
        $nodeId,
        $text,
        $nbSubfolders,
        $SETTINGS
    );    
    $last_visible_parent = $tmpRetArray['last_visible_parent'];
    $last_visible_parent_level = $tmpRetArray['last_visible_parent_level'];
    $ret_json = $tmpRetArray['ret_json'];

    // ensure we get the children of the folder
    if (isset($currentNode->children) === false) {
        $currentNode->children = $tree->getDescendants($nodeId, false, true, true);
    }
    if ($inputData['userTreeLoadStrategy'] === 'full' && isset($currentNode->children) === true) {
        foreach ($currentNode->children as $child) {
            recursiveTree(
                (int) $child,
                $tree->getNode($child),// get node info for this child
                /** @scrutinizer ignore-type */ $tree,
                $listFoldersLimitedKeys,
                $listRestrictedFoldersForItemsKeys,
                $last_visible_parent,
                $last_visible_parent_level,
                $SETTINGS,
                $inputData,
                $ret_json
            );
        }
    }
}

/**
 * Permits to prepare the node data
 *
 * @param stdClass $currentNode
 * @param array $nodeData
 * @param array $inputData
 * @param array $ret_json
 * @param integer $last_visible_parent
 * @param integer $last_visible_parent_level
 * @param integer $nodeId
 * @param string $text
 * @param integer $nbSubfolders
 * @param array $SETTINGS
 * @return array
 */
function prepareNodeJson(
    stdClass $currentNode,
    array $nodeData,
    array $inputData,
    array &$ret_json,
    int &$last_visible_parent,
    int &$last_visible_parent_level,
    int $nodeId,
    string $text,
    int $nbSubfolders,
    array $SETTINGS
): array
{
    // prepare json return for current node
    $parent = $currentNode->parent_id === '0' ? '#' : 'li_' . $currentNode->parent_id;

    // handle displaying
    if (isKeyExistingAndEqual('show_only_accessible_folders', 1, $SETTINGS) === true) {
        if ($nodeData['hide_node'] === true) {
            $last_visible_parent = (int) $parent;
            $last_visible_parent_level = $currentNode->nlevel--;
        } elseif ($currentNode->nlevel < $last_visible_parent_level) {
            $last_visible_parent = -1;
        }
    }

    // json
    if ($nodeData['hide_node'] === false && $nodeData['show_but_block'] === false) {
        array_push(
            $ret_json,
            array(
                'id' => 'li_' . $nodeId,
                'parent' => $last_visible_parent === -1 ? $parent : $last_visible_parent,
                'text' => '<i class="'.$currentNode->fa_icon.' tree-folder mr-2" data-folder="'.$currentNode->fa_icon.'"  data-folder-selected="'.$currentNode->fa_icon_selected.'"></i>'.$text.$currentNode->title.$nodeData['html'],
                'li_attr' => array(
                    'class' => 'jstreeopen',
                    'title' => 'ID [' . $nodeId . '] ' . $nodeData['title'],
                ),
                'a_attr' => array(
                    'id' => 'fld_' . $nodeId,
                    'class' => $nodeData['folderClass'],
                    'onclick' => 'ListerItems(' . $nodeId . ', ' . $nodeData['restricted'] . ', 0, 1)',
                    'data-title' => $currentNode->title,
                ),
                'is_pf' => in_array($nodeId, $inputData['personalFolders']) === true ? 1 : 0,
                'can_edit' => (int) $inputData['userCanCreateRootFolder'],
                //'children' => count($nodeDirectDescendants) > 0 ? true : false,
            )
        );
        
        if ($inputData['userTreeLoadStrategy'] === 'sequential') {
            $ret_json[count($ret_json) - 1]['children'] = $nbSubfolders > 0 ? true : false;
        }

    } elseif ($nodeData['show_but_block'] === true) {
        array_push(
            $ret_json,
            array(
                'id' => 'li_' . $nodeId,
                'parent' => $last_visible_parent === -1 ? $parent : $last_visible_parent,
                'text' => '<i class="'.$currentNode->fa_icon.' tree-folder mr-2" data-folder="'.$currentNode->fa_icon.'"  data-folder-selected="'.$currentNode->fa_icon_selected.'"></i>'.'<i class="fas fa-times fa-xs text-danger mr-1 ml-1"></i>'.$text.$currentNode->title.$nodeData['html'],
                'li_attr' => array(
                    'class' => '',
                    'title' => 'ID [' . $nodeId . '] ' . langHdl('no_access'),
                ),
            )
        );
    }

    return [
        'last_visible_parent' => (int) $last_visible_parent,
        'last_visible_parent_level' => (int) $last_visible_parent_level,
        'ret_json' => $ret_json
    ];
}

/**
 * Get the context of the folder
 *
 * @param integer $nodeId
 * @param array $session_groupes_visibles
 * @param array $session_read_only_folders
 * @param array $session_personal_visible_groups
 * @param integer $nbItemsInFolder
 * @param integer $nbItemsInSubfolders
 * @param integer $nbSubfolders
 * @param integer $session_list_folders_limited
 * @param integer $show_only_accessible_folders
 * @param integer $tree_counters
 * @param bool $session_user_read_only
 * @param array $listFoldersLimitedKeys
 * @param array $listRestrictedFoldersForItemsKeys
 * @param array $session_list_restricted_folders_for_items
 * @param array $session_personal_folder
 * @param array $nodeDirectDescendants
 * @return array
 */
function prepareNodeData(
    int $nodeId,
    array $session_groupes_visibles,
    array $session_read_only_folders,
    array $session_personal_visible_groups,
    int $nbItemsInFolder,
    int $nbItemsInSubfolders,
    int $nbSubfolders,
    array $session_list_folders_limited,
    int $show_only_accessible_folders,
    int $tree_counters,
    bool $session_user_read_only,
    array $listFoldersLimitedKeys,
    array $listRestrictedFoldersForItemsKeys,
    array $session_list_restricted_folders_for_items,
    array $session_personal_folder,
    array $nodeDirectDescendants
): array
{
    if (in_array($nodeId, $session_groupes_visibles) === true) {
        // special case for READ-ONLY folder
        if (in_array($nodeId, $session_read_only_folders) === true) {
            return [
                'html' => '<i class="far fa-eye fa-xs mr-1 ml-1"></i>'.
                    ($tree_counters === 1 ? '<span class="badge badge-pill badge-light ml-2 items_count" id="itcount_' . $nodeId . '">' . $nbItemsInFolder .'/'.$nbItemsInSubfolders .'/'.$nbSubfolders. '</span>'  : ''),
                'title' => langHdl('read_only_account'),
                'restricted' => 1,
                'folderClass' => 'folder_not_droppable',
                'show_but_block' => false,
                'hide_node' => false,
                'is_pf' => in_array($nodeId, $session_personal_folder) === true ? 1 : 0,
            ];

        } elseif (
            $session_user_read_only === true
            && in_array($nodeId, $session_personal_visible_groups) === false
        ) {
            return [
                'html' => '<i class="far fa-eye fa-xs mr-1"></i>'.
                    ($tree_counters === 1 ? '<span class="badge badge-pill badge-light ml-2 items_count" id="itcount_' . $nodeId . '">' . $nbItemsInFolder .'/'.$nbItemsInSubfolders .'/'.$nbSubfolders. '</span>'  : ''),
                'title' => langHdl('read_only_account'),
                'restricted' => 0,
                'folderClass' => 'folder',
                'show_but_block' => false,
                'hide_node' => false,
                'is_pf' => in_array($nodeId, $session_personal_folder) === true ? 1 : 0,
            ];
        }
        
        return [
            'html' => ($tree_counters === 1 ? '<span class="badge badge-pill badge-light ml-2 items_count" id="itcount_' . $nodeId . '">' . $nbItemsInFolder .'/'.$nbItemsInSubfolders .'/'.$nbSubfolders. '</span>'  : ''),
            'title' => '',
            'restricted' => 0,
            'folderClass' => 'folder',
            'show_but_block' => false,
            'hide_node' => false,
            'is_pf' => in_array($nodeId, $session_personal_folder) === true ? 1 : 0,
        ];

    } elseif (in_array($nodeId, $listFoldersLimitedKeys) === true) {
        return [
            'html' => ($session_user_read_only === true ? '<i class="far fa-eye fa-xs mr-1"></i>' : '') .
                ($tree_counters === 1 ? '<span class="badge badge-pill badge-light ml-2 items_count" id="itcount_' . $nodeId . '">' . count($session_list_folders_limited[$nodeId]) . '</span>' : ''),
            'title' => '',
            'restricted' => 1,
            'folderClass' => 'folder',
            'show_but_block' => false,
            'hide_node' => false,
            'is_pf' => in_array($nodeId, $session_personal_folder) === true ? 1 : 0,
        ];

    } elseif (in_array($nodeId, $listRestrictedFoldersForItemsKeys) === true) {
        return [
            'html' => $session_user_read_only === true ? '<i class="far fa-eye fa-xs mr-1"></i>' : '' .
                '<span class="badge badge-pill badge-light ml-2 items_count" id="itcount_' . $nodeId . '">' . count($session_list_restricted_folders_for_items[$nodeId]) . '</span>',
            'title' => '',
            'restricted' => 1,
            'folderClass' => 'folder',
            'show_but_block' => false,
            'hide_node' => false,
            'is_pf' => in_array($nodeId, $session_personal_folder) === true ? 1 : 0,
        ];

    } elseif ((int) $show_only_accessible_folders === 1
        && (int) $nbSubfolders === 0
    ) {
        // folder should not be visible
        // only if it has no descendants
        if (
            count(
                array_diff(
                    $nodeDirectDescendants,
                    array_merge(
                        $session_groupes_visibles,
                        array_keys($session_list_restricted_folders_for_items)
                    )
                )
            ) !== count($nodeDirectDescendants)
        ) {
            // show it but block it
            return [
                'html' => '',
                'title' => '',
                'restricted' => 1,
                'folderClass' => 'folder_not_droppable',
                'show_but_block' => true,
                'hide_node' => false,
                'is_pf' => in_array($nodeId, $session_personal_folder) === true ? 1 : 0,
            ];
        }
        
        // hide it
        return [
            'html' => '',
            'title' => '',
            'restricted' => 1,
            'folderClass' => 'folder_not_droppable',
            'show_but_block' => false,
            'hide_node' => true,
            'is_pf' => in_array($nodeId, $session_personal_folder) === true ? 1 : 0,
        ];
    }

    return [
        'html' => '',
        'title' => isset($title) === true ? $title : '',
        'restricted' => 1,
        'folderClass' => 'folder_not_droppable',
        'show_but_block' => true,
        'hide_node' => false,
        'is_pf' => in_array($nodeId, $session_personal_folder) === true ? 1 : 0,
    ];
}


/**
 * Permits to check if we can user a cache instead of loading from DB
 *
 * @param integer $lastTreeChange
 * @param integer $userTreeLastRefresh
 * @param array $userSessionTreeStructure
 * @param integer $userId
 * @param integer $forceRefresh
 * @param array $SETTINGS
 * @return array
 */
function loadTreeStrategy(
    int $lastTreeChange,
    int $userTreeLastRefresh,
    array $userSessionTreeStructure,
    int $userId,
    int $forceRefresh
): array
{
    // Case when refresh is EXPECTED / MANDATORY
    if ((int) $forceRefresh === 1) {
        return [
            'state' => true,
            'data' => [],
        ];
    }

    // Case when an update in the tree has been done
    // Refresh is then mandatory
    if ((int) $lastTreeChange > (int) $userTreeLastRefresh) {
        return [
            'state' => true,
            'data' => [],
        ];
    }

    // Does this user has the tree structure in session?
    // If yes then use it
    if (count($userSessionTreeStructure) > 0) {
        return [
            'state' => false,
            'data' => json_encode($userSessionTreeStructure),
        ];
    }

    // Does this user has a tree cache
    $userCacheTree = DB::queryfirstrow(
        'SELECT data
        FROM ' . prefixTable('cache_tree') . '
        WHERE user_id = %i',
        $userId
    );
    if (empty($userCacheTree['data']) === false && $userCacheTree['data'] !== '[]') {
        return [
            'state' => false,
            'data' => $userCacheTree['data'],
        ];
    }

    return [
        'state' => true,
        'data' => [],
    ];
}