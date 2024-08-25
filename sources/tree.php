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
 * @copyright 2009-2024 Teampass.net
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

// Load config if $SETTINGS not defined
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
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

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
    'limitedFolders' => null !== $session->get('user-list_folders_limited') ? json_encode($session->get('user-list_folders_limited')) : '{}',
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
    $filters
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
    (null === $session->get('user-tree_structure') || empty($session->get('user-tree_structure')) === true) ? [] : $session->get('user-tree_structure'),
    (int) $inputData['userId'],
    (int) $inputData['forceRefresh']
);
// We don't use the cache if an ID of folder is provided
if ($goTreeRefresh['state'] === true || empty($inputData['nodeId']) === false || $inputData['userTreeLoadStrategy'] === 'sequential') {
    // Build tree
    $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

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
    $session->set('user-tree_structure', $ret_json);
    $session->set('user-tree_last_refresh_timestamp', time());

    $ret_json = json_encode($ret_json);

    // Save user folders tree
    cacheTreeUserHandler(
        (int) $inputData['userId'],
        $ret_json,
        $SETTINGS
    );

    // Add new process
    DB::insert(
        prefixTable('background_tasks'),
        array(
            'created_at' => time(),
            'process_type' => 'user_build_cache_tree',
            'arguments' => json_encode([
                'user_id' => (int) $inputData['userId'],
            ], JSON_HEX_QUOT | JSON_HEX_TAG),
            'updated_at' => '',
            'finished_at' => '',
            'output' => '',
        )
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
    $big_array = array_diff(
        array_unique(
            array_merge(
                $session_groupes_visibles, 
                $listFoldersLimitedKeys, 
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
 * @param NestedTree   $tree                              The tree
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
    NestedTree $tree,
    array $listFoldersLimitedKeys,
    array $listRestrictedFoldersForItemsKeys,
    int $last_visible_parent,
    int $last_visible_parent_level,
    array $SETTINGS,
    array $inputData,
    array &$ret_json = array()
) {
    $text = '';
    
    // Load config
    include __DIR__.'/../includes/config/tp.config.php';

    $displayThisNode = false;
    $nbItemsInSubfolders = $nbSubfolders = $nbItemsInFolder = 0;
    $nodeDescendants = $tree->getDescendants($nodeId, true, false, false);
    
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
 * @param NestedTree $tree
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
 * @param array $ret_json
 * @return void
 */
function handleNode(
    int $nodeId,
    stdClass $currentNode,
    NestedTree $tree,
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
        $tree
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
 * @param array $session_list_folders_limited
 * @param integer $show_only_accessible_folders
 * @param integer $tree_counters
 * @param bool $session_user_read_only
 * @param array $listFoldersLimitedKeys
 * @param array $listRestrictedFoldersForItemsKeys
 * @param array $session_list_restricted_folders_for_items
 * @param array $session_personal_folder
 * @param NestedTree $tree
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
    NestedTree $tree
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

    } elseif ((int) $nbSubfolders === 0
        //&& (int) $show_only_accessible_folders === 1
    ) {
        // folder should not be visible
        // only if it has no descendants
        $nodeDirectDescendants = $tree->getDescendants($nodeId, false, false, true);
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