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
 * @file      tree.php
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2022 Teampass.net
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
if (file_exists('../includes/config/tp.config.php')) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// includes
require_once $SETTINGS['cpassman_dir'] . '/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
require_once $SETTINGS['cpassman_dir'] . '/includes/language/' . $_SESSION['user_language'] . '.php';
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
    'userTreeLoadStrategy' => isset($_SESSION['user_treeloadstrategy']) === true ? $_SESSION['user_treeloadstrategy'] : '',
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
    (array) (is_null($superGlobal->get('user_tree_structure', 'SESSION')) === true || empty($superGlobal->get('user_tree_structure', 'SESSION')) === true) ? [] : $superGlobal->get('user_tree_structure', 'SESSION'),
    (int) $inputData['userId'],
    (int) $inputData['forceRefresh']
);

if ($goTreeRefresh['state'] === true) {
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
        $inputData['userTreeLoadStrategy'] = 'sequential';
        if ($inputData['userTreeLoadStrategy'] === 'sequential') {
            // SEQUENTIAL MODE
            $completTree = $tree->getDescendants(empty($nodeId) === true ? '' : $nodeId, false, true, false);
            
            foreach ($completTree as $child) {
                recursiveTree(
                    $child->id,
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
                //echo " - start ".$child." - ";
                recursiveTree(
                    $child,
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
    if (
        in_array($nodeId, $big_array) === true) {
        return true;
    }
    return false;
}

/**
 * Get through asked folders
 *
 * @param int $nodeId
 * @param array $listFoldersLimitedKeys
 * @param array $listRestrictedFoldersForItemsKeys
 * @param array $tree
 * @param array $SETTINGS
 * @param array $session_forbiden_pfs
 * @param array $session_groupes_visibles
 * @param array $session_list_restricted_folders_for_items
 * @param int $session_user_id
 * @param string $session_login
 * @param array $session_no_access_folders
 * @param array $session_list_folders_limited
 * @param array $session_read_only_folders
 * @param array $session_personal_folders
 * @param array $session_personal_visible_groups
 * @param int $session_user_read_only
 * @return array
 */
function buildNodeTree(
    $nodeId,
    $listFoldersLimitedKeys,
    $listRestrictedFoldersForItemsKeys,
    $tree,
    $SETTINGS,
    $session_forbiden_pfs,
    $session_groupes_visibles,
    $session_list_restricted_folders_for_items,
    $session_user_id,
    $session_login,
    $session_no_access_folders,
    $session_list_folders_limited,
    $session_read_only_folders,
    $session_personal_folders,
    $session_personal_visible_groups,
    $session_user_read_only
) {
    // Prepare variables
    $ret_json = array();
    $nbChildrenItems = 0;


    // Check if any allowed folder is part of the descendants of this node
    $nodeDescendants = $tree->getDescendants($nodeId, false, true, false);
    foreach ($nodeDescendants as $node) {
        if (showFolderToUser(
                (int) $node->id,
                $session_forbiden_pfs,
                $session_groupes_visibles,
                $listFoldersLimitedKeys,
                $listRestrictedFoldersForItemsKeys
            ) === true
            && (in_array(
                $node->id,
                array_merge($session_groupes_visibles, $session_list_restricted_folders_for_items)
            ) === true
                || (is_array($listFoldersLimitedKeys) === true && in_array($node->id, $listFoldersLimitedKeys) === true)
                || (is_array($listRestrictedFoldersForItemsKeys) === true && in_array($node->id, $listRestrictedFoldersForItemsKeys) === true)
                || in_array($node->id, $session_no_access_folders) === true)
        ) {
            //echo 
            $nodeElements= buildNodeTreeElements(
                $node,
                $session_user_id,
                $session_login,
                $session_user_read_only,
                $session_personal_folders,
                $session_groupes_visibles,
                $session_read_only_folders,
                $session_personal_visible_groups,
                $nbChildrenItems,
                $nodeDescendants,
                $listFoldersLimitedKeys,
                $session_list_folders_limited,
                $listRestrictedFoldersForItemsKeys,
                $session_list_restricted_folders_for_items,
                $SETTINGS,
                $tree->numDescendants($node->id)
            );

            // prepare json return for current node
            $parent = $nodeElements['parent'] === 'li_0' ? '#' : $nodeElements['parent'];

            // json    
            array_push(
                $ret_json,
                array(
                    'id' => 'li_' . $node->id,
                    'parent' => $parent,
                    'text' => '<i class="fas fa-folder mr-2"></i>'.($nodeElements['show_but_block'] === true ? '<i class="fas fa-times fa-xs text-danger mr-1"></i>' : '') . $nodeElements['text'],
                    'children' => ($nodeElements['childrenNb'] === 0 ? false : true),
                    'fa_icon' => 'folder',
                    'li_attr' => array(
                        'class' => ($nodeElements['show_but_block'] === true ? '' : 'jstreeopen'),
                        'title' => 'ID [' . $node->id . '] ' . ($nodeElements['show_but_block'] === true ? langHdl('no_access') : $nodeElements['title']),
                    ),
                    'a_attr' => $nodeElements['show_but_block'] === true ? (array(
                        'id' => 'fld_' . $node->id,
                        'class' => $nodeElements['folderClass'],
                        'onclick' => 'ListerItems(' . $node->id . ', ' . $nodeElements['restricted'] . ', 0, 1)',
                        'data-title' => $node->title,
                    )) : '',
                    'is_pf' => in_array($node->id, $session_personal_folders) === true ? 1 : 0,
                )
            );
        }
    }

    return $ret_json;
}

/**
 * Get some infos for this node
 *
 * @param integer   $nodeId
 * @param integer   $nodeLevel
 * @param string    $nodeTitle
 * @param integer   $userId
 * @param string    $userLogin
 * @param bool      $userIsRO
 * @param array     $userPF
 * @return array
 */
function getNodeInfos(
    int $nodeId,
    int $nodeLevel,
    string $nodeTitle,
    int $userId,
    string $userLogin,
    bool $userIsRO,
    array $userPF
) : array
{
    $ret = [];
    // get count of Items in this folder
    DB::query(
        'SELECT *
        FROM ' . prefixTable('items') . '
        WHERE inactif=%i AND id_tree = %i',
        0,
        $nodeId
    );
    $ret['itemsNb'] = DB::count();

    // get info about current folder
    DB::query(
        'SELECT *
        FROM ' . prefixTable('nested_tree') . '
        WHERE parent_id = %i',
        $nodeId
    );
    $ret['childrenNb'] = DB::count();

    // Manage node title
    if ($userIsRO === true && in_array($nodeId, $userPF) === false) {
        // special case for READ-ONLY folder
        $ret['title'] = langHdl('read_only_account');
    } else {
        // If personal Folder, convert id into user name
        $ret['title'] = (string) $nodeTitle === (string) $userId && (int) $nodeLevel === 1 ?
        $userLogin :
        htmlspecialchars_decode($nodeTitle, ENT_QUOTES);
    }

    $ret['text'] = str_replace(
        '&',
        '&amp;',
        $ret['title']
    );

    return $ret;
}

function buildNodeTreeElements(
    $node,
    $session_user_id,
    $session_login,
    $session_user_read_only,
    $session_personal_folders,
    $session_groupes_visibles,
    $session_read_only_folders,
    $session_personal_visible_groups,
    $nbChildrenItems,
    $nodeDescendants,
    $listFoldersLimitedKeys,
    $session_list_folders_limited,
    $listRestrictedFoldersForItemsKeys,
    $session_list_restricted_folders_for_items,
    $SETTINGS,
    $numDescendants
)
{
    // Get info for this node
    $nodeInfos = getNodeInfos(
        (int) $node->id,
        (int) $node->nlevel,
        (string) $node->title,
        (int) $session_user_id,
        (string) $session_login,
        (bool) $session_user_read_only,
        (array) $session_personal_folders
    );
    $itemsNb = $nodeInfos['itemsNb'];
    $childrenNb = $nodeInfos['childrenNb'];
    $title = $nodeInfos['title'];
    $text = $nodeInfos['text'];

    // prepare json return for current node
    $parent = $node->parent_id === 0 ? '#' : 'li_' . $node->parent_id;

    if (in_array($node->id, $session_groupes_visibles) === true) {
        if (in_array($node->id, $session_read_only_folders) === true) {
            return array(
                'text' => '<i class="far fa-eye fa-xs mr-1 ml-1"></i>' . $text .
                    ' <span class=\'badge badge-danger ml-2 items_count\' id=\'itcount_' . $node->id . '\'>' . $itemsNb . '</span>' .
                    (isKeyExistingAndEqual('tree_counters', 1, $SETTINGS) === true ?
                        '/'.$nbChildrenItems .'/'.(count($nodeDescendants) - 1)
                        : '')
                    .'</span>',
                'title' => langHdl('read_only_account'),
                'restricted' => 1,
                'folderClass' => 'folder_not_droppable',
                'show_but_block' => false,
                'parent' => $parent,
                'childrenNb' => $childrenNb,
                'itemsNb' => $itemsNb,
            );
        }
        
        return array(
            'text' => ($session_user_read_only === true && in_array($node->id, $session_personal_visible_groups) === false) ?
                ('<i class="far fa-eye fa-xs mr-1 ml-1"></i>' . $text .
                ' <span class=\'badge badge-danger ml-2 items_count\' id=\'itcount_' . $node->id . '\'>' . $itemsNb . '</span>' .
                (isKeyExistingAndEqual('tree_counters', 1, $SETTINGS) === true ?
                    '/'.$nbChildrenItems .'/'.(count($nodeDescendants) - 1)  :
                    '') .
                '</span>') :
                $text .(' <span class=\'badge badge-danger ml-2 items_count\' id=\'itcount_' . $node->id . '\'>' . $itemsNb . '</span>' .
                (isKeyExistingAndEqual('tree_counters', 1, $SETTINGS) === true ?
                    '/'.$nbChildrenItems .'/'.(count($nodeDescendants) - 1)  :
                    '') .
                '</span>'),
            'title' => langHdl('read_only_account'),
            'restricted' => 1,
            'folderClass' => 'folder_not_droppable',
            'show_but_block' => false,
            'parent' => $parent,
            'childrenNb' => $childrenNb,
            'itemsNb' => $itemsNb,
        );
    }
    
    if (in_array($node->id, $listFoldersLimitedKeys) === true) {
        return array(
            'text' => $text . ($session_user_read_only === true ?
                '<i class="far fa-eye fa-xs mr-1 ml-1"></i>' :
                '<span class="badge badge-danger ml-2 items_count" id="itcount_' . $node->id . '">' . count($session_list_folders_limited[$node->id]) . '</span>'
            ),
            'title' => $title,
            'restricted' => 1,
            'folderClass' => 'folder',
            'show_but_block' => false,
            'parent' => $parent,
            'childrenNb' => $childrenNb,
            'itemsNb' => $itemsNb,
        );
    }
    
    if (in_array($node->id, $listRestrictedFoldersForItemsKeys) === true) {        
        return array(
            'text' => $text . ($session_user_read_only === true ? 
                '<i class="far fa-eye fa-xs mr-1 ml-1"></i>' :
                '<span class="badge badge-danger ml-2 items_count" id="itcount_' . $node->id . '">' . count($session_list_restricted_folders_for_items[$node->id]) . '</span>'
            ),
            'title' => $title,
            'restricted' => 1,
            'folderClass' => 'folder',
            'show_but_block' => false,
            'parent' => $parent,
            'childrenNb' => $childrenNb,
            'itemsNb' => $itemsNb,
        );
    }

    // default case
    if (isKeyExistingAndEqual('show_only_accessible_folders', 1, $SETTINGS) === true
        && (int) $numDescendants === 0)
    {
        return array();
    }

    return array(
        'text' => $text,
        'title' => $title,
        'restricted' => 1,
        'folderClass' => 'folder_not_droppable',
        'show_but_block' => true,   // folder is visible but not accessible by user
        'parent' => $parent,
        'childrenNb' => $childrenNb,
        'itemsNb' => $itemsNb,
    );
}

/**
 * Get through complete tree
 *
 * @param int     $nodeId                            Id
 * @param array   $completTree                       Tree info
 * @param array   $tree                              The tree
 * @param array   $listFoldersLimitedKeys            Limited
 * @param array   $listRestrictedFoldersForItemsKeys Restricted
 * @param int     $last_visible_parent               Visible parent
 * @param int     $last_visible_parent_level         Parent level
 * @param array   $SETTINGS                          Teampass settings
 * @param string  $session_forbiden_pfs,
 * @param string  $session_groupes_visibles,
 * @param string  $session_list_restricted_folders_for_items,
 * @param int     $session_user_id,
 * @param string  $session_login,
 * @param string  $session_user_read_only,
 * @param array   $session_personal_folder,
 * @param string  $session_list_folders_limited,
 * @param string  $session_read_only_folders,
 * @param string  $session_personal_visible_groups
 * @param int     $can_create_root_folder
 * @param array   $ret_json
 *
 * @return array
 */
function recursiveTree(
    $nodeId,
    $currentNode,
    $tree,
    $listFoldersLimitedKeys,
    $listRestrictedFoldersForItemsKeys,
    $last_visible_parent,
    $last_visible_parent_level,
    $SETTINGS,
    $inputData,
    &$ret_json = array()
) {
    $text = '';
    $title = '';
    $show_but_block = false;
    
    // Load config
    include __DIR__.'/../includes/config/tp.config.php';


    // Be sure that user can only see folders he/she is allowed to
    if (
        //in_array($nodeId, $session_forbiden_pfs) === false
        //|| in_array($nodeId, $session_groupes_visibles) === true
        in_array($nodeId, array_merge($inputData['personalFolders'], $inputData['visibleFolders'])) === true
    ) {
        $displayThisNode = false;
        $nbChildrenItems = 0;
        $nodeDirectDescendants = $tree->getDescendants($nodeId, false, false, true);
        
        // Check if any allowed folder is part of the descendants of this node
        $nodeDescendants = $tree->getDescendants($nodeId, true, false, true);
        foreach ($nodeDescendants as $node) {
            //echo $node." , ";
            // manage tree counters
            if (
                isKeyExistingAndEqual('tree_counters', 1, $SETTINGS) === true
                && in_array(
                    $node,
                    array_merge(
                        $inputData['visibleFolders'],
                        is_null($inputData['restrictedFoldersForItems']) === true ? [] : $inputData['restrictedFoldersForItems']
                    )
                ) === true
            ) {
                DB::query(
                    'SELECT * FROM ' . prefixTable('items') . '
                    WHERE inactif=%i AND id_tree = %i',
                    0,
                    $node
                );
                $nbChildrenItems += DB::count();
            }

            if (
                in_array($node, array_merge($inputData['personalFolders'], $inputData['visibleFolders'])) === true
            ) {
                // Final check - is PF allowed?
                $nodeDetails = $tree->getNode($node);
                if (
                    (int) $nodeDetails->personal_folder === 1
                    && (int) $SETTINGS['enable_pf_feature'] === 1
                    && in_array($node, $inputData['personalFolders']) === false
                ) {
                    $displayThisNode = false;
                } else {
                    $displayThisNode = true;
                    // not adding a break in order to permit a correct count of items
                }
                $text = '';
            }
        }
        
        if ($displayThisNode === true) {
            //echo "on affiche ".$nodeId.' ; ';
            //print_r($currentNode);
            handleNode(
                $nodeId,
                $currentNode,
                $tree,
                $listFoldersLimitedKeys,
                $listRestrictedFoldersForItemsKeys,
                $last_visible_parent,
                $last_visible_parent_level,
                $SETTINGS,
                $inputData,
                $text,
                $nbChildrenItems,
                $nodeDescendants,
                $nodeDirectDescendants,
                $ret_json
            );
        }
    }
    return $ret_json;
}


function handleNode(
    $nodeId,
    $currentNode,
    $tree,
    $listFoldersLimitedKeys,
    $listRestrictedFoldersForItemsKeys,
    $last_visible_parent,
    $last_visible_parent_level,
    $SETTINGS,
    $inputData,
    $text,
    $nbChildrenItems,
    $nodeDescendants,
    $nodeDirectDescendants,
    &$ret_json = array()
)
{
    // get info about current folder
    DB::query(
        'SELECT * FROM ' . prefixTable('items') . '
        WHERE inactif=%i AND id_tree = %i',
        0,
        $nodeId
    );
    $itemsNb = DB::count();

    // If personal Folder, convert id into user name
    if ((int) $currentNode->title === (int) $inputData['userId'] && (int) $currentNode->nlevel === 1) {
        $currentNode->title = $inputData['userLogin'];
    }

    // Decode if needed
    $currentNode->title = htmlspecialchars_decode($currentNode->title, ENT_QUOTES);

    $nodeData = prepareNodeData(
        $currentNode,
        (int) $nodeId,
        $inputData['visibleFolders'],
        $inputData['readOnlyFolders'],
        $inputData['personalVisibleFolders'],
        (int) $nbChildrenItems,
        $nodeDescendants,
        (int) $itemsNb,
        $inputData['limitedFolders'],
        (int) $SETTINGS['show_only_accessible_folders'],
        $nodeDirectDescendants,
        isset($SETTINGS['tree_counters']) === true ? (int) $SETTINGS['tree_counters'] : '',
        (int) $inputData['userReadOnly'],
        $listFoldersLimitedKeys,
        $listRestrictedFoldersForItemsKeys,
        $inputData['restrictedFoldersForItems'],
        $inputData['personalFolders']
    );

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
                'can_edit' => (int) $inputData['userCanCreateRootFolder']
                //,'children' => count($nodeDirectDescendants) > 0 ? true : false,
            )
        );
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

    // ensure we get the children of the folder
    if (isset($currentNode->children) === false) {
        $currentNode->children = $tree->getDescendants($nodeId, false, true, true);
    }
    if ($inputData['userCanCreateRootFolder'] !== 'sequential' && isset($currentNode->children) === true) {
        foreach ($currentNode->children as $child) {
            //print_r($tree->getNode($child));
            recursiveTree(
                $child,
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


function prepareNodeData(
    $completTree,
    $nodeId,
    $session_groupes_visibles,
    $session_read_only_folders,
    $session_personal_visible_groups,
    $nbChildrenItems,
    $nodeDescendants,
    $itemsNb,
    $session_list_folders_limited,
    $show_only_accessible_folders,
    $nodeDirectDescendants,
    $tree_counters,
    $session_user_read_only,
    $listFoldersLimitedKeys,
    $listRestrictedFoldersForItemsKeys,
    $session_list_restricted_folders_for_items,
    $session_personal_folder
): array
{
    // special case for READ-ONLY folder
    $title = '';
    if (
        $session_user_read_only === true
        && in_array($completTree[$nodeId]->id, $session_user_read_only) === false
    ) {
        $title = langHdl('read_only_account');
    }

    if (in_array($nodeId, $session_groupes_visibles) === true) {
        if (in_array($nodeId, $session_read_only_folders) === true) {
            return [
                'html' => '<i class="far fa-eye fa-xs mr-1 ml-1"></i><span class="badge badge-pill badge-light ml-2 items_count" id="itcount_' . $nodeId . '">' . $itemsNb .
                    ($tree_counters === 1 ? '/'.$nbChildrenItems .'/'.(count($nodeDescendants) - 1)  : '') . '</span>',
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
                'html' => '<i class="far fa-eye fa-xs mr-1"></i><span class="badge badge-pill badge-light ml-2 items_count" id="itcount_' . $nodeId . '">' . $itemsNb .
                    ($tree_counters === 1 ? '/'.$nbChildrenItems .'/'.(count($nodeDescendants) - 1)  : '') . '</span>',
                'title' => $title,
                'restricted' => 0,
                'folderClass' => 'folder',
                'show_but_block' => false,
                'hide_node' => false,
                'is_pf' => in_array($nodeId, $session_personal_folder) === true ? 1 : 0,
            ];
        }
        
        return [
            'html' => '<span class="badge badge-pill badge-light ml-2 items_count" id="itcount_' . $nodeId . '">' . $itemsNb .
                ($tree_counters === 1 ? '/'.$nbChildrenItems .'/'.(count($nodeDescendants) - 1)  : '') . '</span>',
            'title' => $title,
            'restricted' => 0,
            'folderClass' => 'folder',
            'show_but_block' => false,
            'hide_node' => false,
            'is_pf' => in_array($nodeId, $session_personal_folder) === true ? 1 : 0,
        ];

    } elseif (in_array($nodeId, $listFoldersLimitedKeys) === true) {
        return [
            'html' => ($session_user_read_only === true ? '<i class="far fa-eye fa-xs mr-1"></i>' : '') .
                '<span class="badge badge-pill badge-light ml-2 items_count" id="itcount_' . $nodeId . '">' . count($session_list_folders_limited[$nodeId]) . '</span>',
            'title' => $title,
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
            'title' => $title,
            'restricted' => 1,
            'folderClass' => 'folder',
            'show_but_block' => false,
            'hide_node' => false,
            'is_pf' => in_array($nodeId, $session_personal_folder) === true ? 1 : 0,
        ];

    } elseif ((int) $show_only_accessible_folders === 1
        && (int) $nbChildrenItems === 0
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
                'title' => $title,
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
            'title' => $title,
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
    if (empty($userCacheTree['data']) === false) {
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