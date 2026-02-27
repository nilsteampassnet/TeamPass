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
 * @copyright 2009-2026 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 *
 * @see       https://www.teampass.net
 */


use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
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
            'type' => null !== $request->request->get('type') ? htmlspecialchars($request->request->get('type')) : '',
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
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //

// Load tree
$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// Prepare sanitization
$data = [
    'forbidenPfs' => null !== $session->get('user-forbiden_personal_folders') ? json_encode($session->get('user-forbiden_personal_folders')) : '{}',
    'visibleFolders' => null !== $session->get('user-accessible_folders') ? json_encode($session->get('user-accessible_folders')) : '{}',
    'userId' => null !== $session->get('user-id') ? $session->get('user-id') : '',
    'userLogin' => null !== $session->get('user-login') ? $session->get('user-login') : '',
    'userReadOnly' => null !== $session->get('user-read_only') ? $session->get('user-read_only') : '',
    'readOnlyFolders' => null !== $session->get('user-read_only_folders') ? json_encode($session->get('user-read_only_folders')) : '{}',
    'personalVisibleFolders' => null !== $session->get('user-personal_visible_folders') ? json_encode($session->get('user-personal_visible_folders')) : '{}',
    'userTreeLastRefresh' => null !== $session->get('user-tree_last_refresh_timestamp') ? $session->get('user-tree_last_refresh_timestamp') : '',
    'forceRefresh' => null !== $request->query->get('force_refresh') ? $request->query->get('force_refresh') : '',
    'nodeId' => null !== $request->query->get('id') ? $request->query->get('id') : '',
    'restrictedFoldersForItems' => null !== $request->query->get('list_restricted_folders_for_items') ? json_encode($request->query->get('list_restricted_folders_for_items')) : '{}',
    'noAccessFolders' => null !== $session->get('user-no_access_folders') ? json_encode($session->get('user-no_access_folders')) : '{}',
    'personalFolders' => null !== $session->get('user-personal_folders') ? json_encode($session->get('user-personal_folders')) : '{}',
    'userCanCreateRootFolder' => null !== $session->get('user-can_create_root_folder') ? json_encode($session->get('user-can_create_root_folder')) : '{}',
    'userTreeLoadStrategy' => null !== $session->get('user-tree_load_strategy') ? $session->get('user-tree_load_strategy') : '',
    'treeVersion' => null !== $request->query->get('tree_version') ? $request->query->get('tree_version') : '',
];

$filters = [
    'forbidenPfs' => 'cast:array',
    'visibleFolders' => 'cast:array',
    'userId' => 'cast:integer',
    'userLogin' => 'trim|escape',
    'userReadOnly' => 'cast:boolean',
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
    'treeVersion' => 'trim|escape',
];

$inputData = dataSanitizer(
    $data,
    $filters
);

$lastFolderChange = DB::queryFirstRow(
    'SELECT valeur FROM ' . prefixTable('misc') . '
    WHERE type = %s AND intitule = %s',
    'timestamp',
    'last_folder_change'
);
if (DB::count() === 0) {
    $lastFolderChange['valeur'] = 0;
}
/** @var int|string $lastFolderChangeValeur */
$lastFolderChangeValeur = $lastFolderChange['valeur'] ?? 0;

// Should we use a cache or refresh the tree
$goTreeRefresh = loadTreeStrategy(
    (int) $lastFolderChangeValeur,
    (int) $inputData['userTreeLastRefresh'],
    (null === $session->get('user-tree_structure') || empty($session->get('user-tree_structure')) === true) ? [] : $session->get('user-tree_structure'),
    (int) $inputData['userId'],
    (int) $inputData['forceRefresh']
);
// We don't use the cache if an ID of folder is provided
if ($goTreeRefresh['state'] === true || empty($inputData['nodeId']) === false) {
    // Build tree
    $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
    
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
    $completTree = [];
    $last_visible_parent = -1;
    $last_visible_parent_level = 1;
    $nodeId = isset($inputData['nodeId']) === true && is_int($inputData['nodeId']) === true ? $inputData['nodeId'] : 0;

    // build the tree to be displayed
    if (showFolderToUser(
        $nodeId,
        $inputData['forbidenPfs'],
        $inputData['visibleFolders'],
        $listRestrictedFoldersForItemsKeys
    ) === true)
    {
        // Note: Condition with sequential/full option has been removed (see #4900)
        $completTree = $tree->getTreeWithChildren();
        foreach ($completTree[0]->children as $child) {
            recursiveTree(
                (int) $child,
                $completTree[$child],
                $completTree,
                $listRestrictedFoldersForItemsKeys,
                $last_visible_parent,
                $last_visible_parent_level,
                $SETTINGS,
                $inputData,
                $ret_json
            );
        }
    }

    // Save in SESSION
    $session->set('user-tree_structure', $ret_json);
    $session->set('user-tree_last_refresh_timestamp', time());

    // Build visible_folders synchronously from the same tree data
    $visibleFoldersArray = buildVisibleFoldersFromTree($ret_json, $completTree, $inputData);
    $visibleFoldersJson = json_encode($visibleFoldersArray);

    $ret_json = json_encode($ret_json);

    // Save user folders tree (data + visible_folders in one call)
    cacheTreeUserHandler(
        (int) $inputData['userId'],
        $ret_json,
        $SETTINGS,
        '',
        $visibleFoldersJson
    );

    // Send back with version for client-side caching
    $treeVersion = md5($ret_json);
    if (!empty($inputData['treeVersion']) && $inputData['treeVersion'] === $treeVersion) {
        echo json_encode(['unchanged' => true, 'version' => $treeVersion]);
    } else {
        echo json_encode(['unchanged' => false, 'version' => $treeVersion, 'tree' => json_decode($ret_json)]);
    }
} else {
    // Cached data path — also version it
    $cachedData = $goTreeRefresh['data'];
    $treeVersion = md5($cachedData);
    if (!empty($inputData['treeVersion']) && $inputData['treeVersion'] === $treeVersion) {
        echo json_encode(['unchanged' => true, 'version' => $treeVersion]);
    } else {
        echo json_encode(['unchanged' => false, 'version' => $treeVersion, 'tree' => json_decode($cachedData)]);
    }
}


/**
 * Get all descendant nodes from the in-memory tree (no SQL query).
 * Replaces $tree->getDescendants($id, true/false, false, false) calls.
 *
 * @param array $completTree Complete tree from getTreeWithChildren()
 * @param int $nodeId Node ID to get descendants for
 * @param bool $includeSelf Whether to include the node itself
 * @return array Associative array of node objects keyed by ID
 */
function getDescendantNodesFromTree(array $completTree, int $nodeId, bool $includeSelf = false): array
{
    $nodes = [];
    if ($includeSelf && isset($completTree[$nodeId])) {
        $nodes[$nodeId] = $completTree[$nodeId];
    }
    if (isset($completTree[$nodeId]) && !empty($completTree[$nodeId]->children)) {
        foreach ($completTree[$nodeId]->children as $childId) {
            if (isset($completTree[(int) $childId])) {
                $nodes[(int) $childId] = $completTree[(int) $childId];
                $subNodes = getDescendantNodesFromTree($completTree, (int) $childId, false);
                foreach ($subNodes as $k => $v) {
                    $nodes[$k] = $v;
                }
            }
        }
    }
    return $nodes;
}

/**
 * Get all descendant IDs from the in-memory tree (no SQL query).
 * Replaces $tree->getDescendants($id, false, false, true) calls.
 *
 * @param array $completTree Complete tree from getTreeWithChildren()
 * @param int $nodeId Node ID to get descendants for
 * @param bool $includeSelf Whether to include the node itself
 * @return array Flat array of descendant IDs
 */
function getDescendantIdsFromTree(array $completTree, int $nodeId, bool $includeSelf = false): array
{
    $ids = [];
    if ($includeSelf) {
        $ids[] = $nodeId;
    }
    if (isset($completTree[$nodeId]) && !empty($completTree[$nodeId]->children)) {
        foreach ($completTree[$nodeId]->children as $childId) {
            $ids[] = (int) $childId;
            $ids = array_merge($ids, getDescendantIdsFromTree($completTree, (int) $childId, false));
        }
    }
    return $ids;
}

/**
 * Check if user can see this folder based upon rights
 *
 * @param integer $nodeId
 * @param array $session_forbiden_pfs
 * @param array $session_groupes_visibles
 * @param array $listRestrictedFoldersForItemsKeys
 * @return boolean
 */
function showFolderToUser(
    int $nodeId,
    array $session_forbiden_pfs,
    array $session_groupes_visibles,
    array $listRestrictedFoldersForItemsKeys
): bool
{
    $big_array = array_diff(
        array_unique(
            array_merge(
                $session_groupes_visibles, 
                $listRestrictedFoldersForItemsKeys
            ), 
            SORT_NUMERIC
        ), 
        $session_forbiden_pfs
    );
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
 * @param array      $completTree                       In-memory tree from getTreeWithChildren()
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
    array $completTree,
    array $listRestrictedFoldersForItemsKeys,
    int $last_visible_parent,
    int $last_visible_parent_level,
    array $SETTINGS,
    array $inputData,
    array &$ret_json = array()
) {
    // Initialize variables
    $text = '';
    $displayThisNode = false;
    $nbItemsInSubfolders = $nbSubfolders = $nbItemsInFolder = 0;
    $nodeDescendants = getDescendantNodesFromTree($completTree, $nodeId, true);
    // On combine les tableaux une seule fois pour optimiser
    $allowedFolders = array_merge($inputData['personalFolders'], $inputData['visibleFolders']);
    
    foreach ($nodeDescendants as $node) {
        if (!in_array($node->id, $allowedFolders)) {
            continue;
        }

        // If it is a personal folder, we check the specific conditions
        if (
            (int) $node->personal_folder === 1 
            && (int) $SETTINGS['enable_pf_feature'] === 1 
            && !in_array($node->id, $inputData['personalFolders'])
        ) {
            continue;
        }
        
        // If we are here, the node must be displayed
        $displayThisNode = true;
        $nbItemsInSubfolders = (int) $node->nb_items_in_subfolders;
        $nbItemsInFolder = (int) $node->nb_items_in_folder;
        $nbSubfolders = (int) $node->nb_subfolders;
        break;  // Get out as soon as we find a valid node.
    }
    
    if ($displayThisNode === true) {
        handleNode(
            (int) $nodeId,
            $currentNode,
            $completTree,
            $listRestrictedFoldersForItemsKeys,
            $last_visible_parent,
            $last_visible_parent_level,
            $SETTINGS,
            $inputData,
            $text,
            $nbItemsInSubfolders,
            $nbSubfolders,
            $nbItemsInFolder,
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
 * @param array $completTree In-memory tree from getTreeWithChildren()
 * @param array $listRestrictedFoldersForItemsKeys
 * @param integer $last_visible_parent
 * @param integer $last_visible_parent_level
 * @param array $SETTINGS
 * @param array $inputData
 * @param string $text
 * @param integer $nbItemsInSubfolders
 * @param integer $nbSubfolders
 * @param integer $nbItemsInFolder
 * @param array $ret_json
 * @return void
 */
function handleNode(
    int $nodeId,
    stdClass $currentNode,
    array $completTree,
    array $listRestrictedFoldersForItemsKeys,
    int $last_visible_parent,
    int $last_visible_parent_level,
    array $SETTINGS,
    array $inputData,
    string $text,
    int $nbItemsInSubfolders,
    int $nbSubfolders,
    int $nbItemsInFolder,
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
        isset($SETTINGS['tree_counters']) === true && isset($SETTINGS['enable_tasks_manager']) === true && (int) $SETTINGS['enable_tasks_manager'] === 1 && (int) $SETTINGS['tree_counters'] === 1 ? 1 : 0,
        (bool) $inputData['userReadOnly'],
        $listRestrictedFoldersForItemsKeys,
        $inputData['restrictedFoldersForItems'],
        $inputData['personalFolders'],
        $completTree
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
        $currentNode->children = isset($completTree[$nodeId]) ? $completTree[$nodeId]->children : [];
    }
    if ($inputData['userTreeLoadStrategy'] === 'full' && isset($currentNode->children) === true) {
        foreach ($currentNode->children as $child) {
            if (!isset($completTree[$child])) {
                continue;
            }
            recursiveTree(
                (int) $child,
                $completTree[$child],
                $completTree,
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
    $session = SessionManager::getSession();
    // Load user's language
    $lang = new Language($session->get('user-language') ?? 'english');

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
                'text' => '<i class="'.$currentNode->fa_icon.' tree-folder mr-2" data-folder="'.$currentNode->fa_icon.'"  data-folder-selected="'.$currentNode->fa_icon_selected.'"></i>'.$text.htmlspecialchars($currentNode->title).$nodeData['html'],
                'li_attr' => array(
                    'class' => 'jstreeopen',
                    'title' => 'ID [' . $nodeId . '] ' . $nodeData['title'],
                ),
                'a_attr' => array(
                    'id' => 'fld_' . $nodeId,
                    'class' => $nodeData['folderClass'],
                    'onclick' => 'ListerItems(' . $nodeId . ', ' . $nodeData['restricted'] . ', 0, 1)',
                    'data-title' => htmlspecialchars($currentNode->title),
                ),
                'is_pf' => in_array($nodeId, $inputData['personalFolders']) === true ? 1 : 0,
                'can_edit' => (int) $inputData['userCanCreateRootFolder'],
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
                'text' => '<i class="'.$currentNode->fa_icon.' tree-folder mr-2" data-folder="'.$currentNode->fa_icon.'"  data-folder-selected="'.$currentNode->fa_icon_selected.'"></i>'.'<i class="fas fa-times fa-xs text-danger mr-1 ml-1"></i>'.$text.htmlspecialchars($currentNode->title).$nodeData['html'],
                'li_attr' => array(
                    'class' => '',
                    'title' => 'ID [' . $nodeId . '] ' . $lang->get('no_access'),
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
 * @param integer $tree_counters
 * @param bool $session_user_read_only
 * @param array $listRestrictedFoldersForItemsKeys
 * @param array $session_list_restricted_folders_for_items
 * @param array $session_personal_folder
 * @param array $completTree In-memory tree from getTreeWithChildren()
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
    int $tree_counters,
    bool $session_user_read_only,
    array $listRestrictedFoldersForItemsKeys,
    array $session_list_restricted_folders_for_items,
    array $session_personal_folder,
    array $completTree
): array
{
    $session = SessionManager::getSession();
    // Load user's language
    $lang = new Language($session->get('user-language') ?? 'english');

    if (in_array($nodeId, $session_groupes_visibles) === true) {
        // special case for READ-ONLY folder
        if (in_array($nodeId, $session_read_only_folders) === true) {
            return [
                'html' => '<i class="far fa-eye fa-xs mr-1 ml-1"></i>'.
                    ($tree_counters === 1 ? '<span class="badge badge-pill badge-light ml-2 items_count" id="itcount_' . $nodeId . '">' . $nbItemsInFolder .'/'.$nbItemsInSubfolders .'/'.$nbSubfolders. '</span>'  : ''),
                'title' => $lang->get('read_only_account'),
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
                'title' => $lang->get('read_only_account'),
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

    } elseif ((int) $nbSubfolders === 0
        //&& (int) $show_only_accessible_folders === 1
    ) {
        // folder should not be visible
        // only if it has no descendants
        $nodeDirectDescendants = getDescendantIdsFromTree($completTree, $nodeId, false);
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
        'title' => '',
        'restricted' => 1,
        'folderClass' => 'folder_not_droppable',
        'show_but_block' => true,
        'hide_node' => false,
        'is_pf' => in_array($nodeId, $session_personal_folder) === true ? 1 : 0,
    ];
}


/**
 * Build visible_folders array from the jstree data and the complete tree.
 * Synchronizes visible_folders with the jstree data in a single pass,
 * eliminating the desync between data and visible_folders columns.
 *
 * @param array $retJsonArray The jstree JSON array built by recursiveTree()
 * @param array $completTree Complete tree from getTreeWithChildren()
 * @param array $inputData Sanitized input data with session info
 * @return array The visible_folders array
 */
function buildVisibleFoldersFromTree(
    array $retJsonArray,
    array $completTree,
    array $inputData
): array {
    $visibleFolders = [];
    $readOnlyFoldersSet = array_flip($inputData['readOnlyFolders'] ?? []);
    $accessibleFoldersSet = array_flip($inputData['visibleFolders'] ?? []);

    foreach ($retJsonArray as $node) {
        // Extract folder ID from jstree "li_123" format
        $nodeIdStr = $node['id'] ?? '';
        if (strpos($nodeIdStr, 'li_') !== 0) {
            continue;
        }
        $folderId = (int) substr($nodeIdStr, 3);

        if (!isset($completTree[$folderId])) {
            continue;
        }
        $treeNode = $completTree[$folderId];

        // Build path by walking parent chain in memory
        $pathParts = [];
        $currentId = (int) $treeNode->parent_id;
        while (isset($completTree[$currentId]) && $currentId > 0) {
            $pathParts[] = htmlspecialchars(
                stripslashes(htmlspecialchars_decode($completTree[$currentId]->title, ENT_QUOTES)),
                ENT_QUOTES
            );
            $currentId = (int) $completTree[$currentId]->parent_id;
        }
        $path = implode(' / ', array_reverse($pathParts));

        // Determine title (personal folder uses login name)
        $title = $treeNode->title;
        if ((int) $title === (int) $inputData['userId'] && (int) $treeNode->nlevel === 1) {
            $title = $inputData['userLogin'];
        }

        $visibleFolders[] = [
            'id' => $folderId,
            'level' => (int) $treeNode->nlevel,
            'title' => $title,
            'disabled' => (
                !isset($accessibleFoldersSet[$folderId])
                || isset($readOnlyFoldersSet[$folderId])
            ) ? 1 : 0,
            'parent_id' => (int) $treeNode->parent_id,
            'perso' => (int) $treeNode->personal_folder,
            'path' => $path,
            'is_visible_active' => isset($readOnlyFoldersSet[$folderId]) ? 1 : 0,
        ];
    }

    return $visibleFolders;
}

/**
 * Permits to check if we can user a cache instead of loading from DB
 *
 * @param integer $lastTreeChange
 * @param integer $userTreeLastRefresh
 * @param array $userSessionTreeStructure
 * @param integer $userId
 * @param integer $forceRefresh
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
    $userCacheTree = DB::queryFirstRow(
        'SELECT data, timestamp, IFNULL(invalidated_at, 0) as invalidated_at
        FROM ' . prefixTable('cache_tree') . '
        WHERE user_id = %i',
        $userId
    );
    if (empty($userCacheTree['data']) === false && $userCacheTree['data'] !== '[]') {
        // Check per-user invalidation
        /** @var int|string $invalidatedAt */
        $invalidatedAt = $userCacheTree['invalidated_at'];
        /** @var int|string $cacheTimestamp */
        $cacheTimestamp = $userCacheTree['timestamp'];
        if ((int) $invalidatedAt > (int) $cacheTimestamp) {
            return [
                'state' => true,
                'data' => [],
            ];
        }
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