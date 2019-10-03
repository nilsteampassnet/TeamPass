<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      tree.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2019 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
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
require_once $SETTINGS['cpassman_dir'] . '/sources/SplClassLoader.php';
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

// Superglobal load
require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();

$session_user_admin = $superGlobal->get('user_admin', 'GET');
$sessionTreeStructure = $superGlobal->get('user_tree_structure', 'GET');
$sessionLastTreeRefresh = $superGlobal->get('user_tree_last_refresh_timestamp', 'GET');


$lastFolderChange = DB::query(
    'SELECT * FROM ' . prefixTable('misc') . '
    WHERE type = %s AND intitule = %s',
    'timestamp',
    'last_folder_change'
);
if (
    empty($sessionTreeStructure) === true
    || strtotime($lastFolderChange) > strtotime($sessionLastTreeRefresh)
    || (isset($_GET['force_refresh']) === true && (int) $_GET['force_refresh'] === 1)
) {
    // Build tree
    $tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'] . '/includes/libraries');
    $tree->register();
    $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

    if (
        isset($_SESSION['list_folders_limited']) === true
        && count($_SESSION['list_folders_limited']) > 0
    ) {
        $listFoldersLimitedKeys = array_keys($_SESSION['list_folders_limited']);
    } else {
        $listFoldersLimitedKeys = array();
    }
    // list of items accessible but not in an allowed folder
    if (
        isset($_SESSION['list_restricted_folders_for_items']) === true
        && count($_SESSION['list_restricted_folders_for_items']) > 0
    ) {
        $listRestrictedFoldersForItemsKeys = @array_keys($_SESSION['list_restricted_folders_for_items']);
    } else {
        $listRestrictedFoldersForItemsKeys = array();
    }

    $ret_json = array();
    $last_visible_parent = '';
    $parent = '#';
    $last_visible_parent_level = 1;

    // build the tree to be displayed
    if (
        isset($_GET['id']) === true
        && is_numeric(intval($_GET['id'])) === true
        && isset($_SESSION['user']['treeloadstrategy']) === true
        && $_SESSION['user']['treeloadstrategy'] === 'sequential'
    ) {
        buildNodeTree(
            $_GET['id'],
            $ret_json,
            $listFoldersLimitedKeys,
            $listRestrictedFoldersForItemsKeys,
            $tree,
            $SETTINGS
        );
    } elseif (
        isset($_SESSION['user']['treeloadstrategy']) === true
        && $_SESSION['user']['treeloadstrategy'] === 'sequential'
    ) {
        buildNodeTree(
            0,
            $ret_json,
            $listFoldersLimitedKeys,
            $listRestrictedFoldersForItemsKeys,
            $tree,
            $SETTINGS
        );
    } else {
        $completTree = $tree->getTreeWithChildren();
        foreach ($completTree[0]->children as $child) {
            recursiveTree(
                $child,
                $completTree,
                $tree,
                $listFoldersLimitedKeys,
                $listRestrictedFoldersForItemsKeys,
                $last_visible_parent,
                $last_visible_parent_level,
                $SETTINGS
            );
        }
    }

    // Save in SESSION
    $superGlobal->put('user_tree_structure', $ret_json, 'SESSION');
    $superGlobal->put('user_tree_last_refresh_timestamp', time(), 'SESSION');

    // Send back
    echo json_encode($ret_json);
} else {
    //echo '['.$sessionTreeStructure.']';
    echo $sessionTreeStructure;
}

/**
 * Get through asked folders.
 *
 * @param int   $nodeId                            Id
 * @param array $ret_json                          Array
 * @param array $listFoldersLimitedKeys            Limited
 * @param array $listRestrictedFoldersForItemsKeys Restricted
 * @param array $tree                              The tree
 * @param array $SETTINGS                          Teampass settings
 */
function buildNodeTree(
    $nodeId,
    $ret_json,
    $listFoldersLimitedKeys,
    $listRestrictedFoldersForItemsKeys,
    $tree,
    $SETTINGS
) {
    // Load library
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
    $superGlobal = new protect\SuperGlobal\SuperGlobal();

    // Prepare superGlobal variables
    $session_forbiden_pfs = $superGlobal->get('forbiden_pfs', 'SESSION');
    $session_groupes_visibles = $superGlobal->get('groupes_visibles', 'SESSION');
    $session_list_restricted_folders_for_items = $superGlobal->get('list_restricted_folders_for_items', 'SESSION');
    $session_user_id = $superGlobal->get('user_id', 'SESSION');
    $session_login = $superGlobal->get('login', 'SESSION');
    $session_no_access_folders = $superGlobal->get('no_access_folders', 'SESSION');
    $session_list_folders_limited = $superGlobal->get('list_folders_limited', 'SESSION');
    $session_read_only_folders = $superGlobal->get('read_only_folders', 'SESSION');
    $session_personal_folders = $superGlobal->get('personal_folders', 'SESSION');
    $session_personal_visible_groups = $superGlobal->get('personal_visible_groups', 'SESSION');

    // Be sure that user can only see folders he/she is allowed to
    if (
        in_array($nodeId, $session_forbiden_pfs) === false
        || in_array($nodeId, $session_groupes_visibles) === true
        || in_array($nodeId, $listFoldersLimitedKeys) === true
        || in_array($nodeId, $listRestrictedFoldersForItemsKeys) === true
    ) {
        //$displayThisNode = false;
        //$hide_node = false;
        $nbChildrenItems = 0;

        // Check if any allowed folder is part of the descendants of this node
        $nodeDescendants = $tree->getDescendants($nodeId, false, true, false);
        foreach ($nodeDescendants as $node) {
            $displayThisNode = false;
            if ((in_array($node->id, $session_forbiden_pfs) === false
                    || in_array($node->id, $session_groupes_visibles) === true
                    || in_array($node->id, $listFoldersLimitedKeys) === true
                    || in_array($node->id, $listRestrictedFoldersForItemsKeys)) === true
                && (in_array(
                    $node->id,
                    array_merge($session_groupes_visibles, $session_list_restricted_folders_for_items)
                ) === true
                    || @in_array($node->id, $listFoldersLimitedKeys) === true
                    || @in_array($node->id, $listRestrictedFoldersForItemsKeys) === true
                    || in_array($node->id, $session_no_access_folders) === true)
            ) {
                $displayThisNode = true;
            }

            if ($displayThisNode === true) {
                $hide_node = $show_but_block = false;
                $text = '';
                $title = '';

                // get count of Items in this folder
                DB::query(
                    'SELECT * FROM ' . prefixTable('items') . '
                    WHERE inactif=%i AND id_tree = %i',
                    0,
                    $node->id
                );
                $itemsNb = DB::count();

                // get info about current folder
                DB::query(
                    'SELECT * FROM ' . prefixTable('nested_tree') . '
                    WHERE parent_id = %i',
                    $node->id
                );
                $childrenNb = DB::count();

                // If personal Folder, convert id into user name
                if ($node->title === $session_user_id && $node->nlevel === '1') {
                    $node->title = $session_login;
                }

                // Decode if needed
                $node->title = htmlspecialchars_decode($node->title, ENT_QUOTES);

                // prepare json return for current node
                if ($node->parent_id == 0) {
                    $parent = '#';
                } else {
                    $parent = 'li_' . $node->parent_id;
                }

                // special case for READ-ONLY folder
                if ($_SESSION['user_read_only'] === true && !in_array($node->id, $session_personal_folders)) {
                    $title = langHdl('read_only_account');
                }
                $text .= str_replace('&', '&amp;', $node->title);
                $restricted = '0';
                $folderClass = 'folder';

                if (in_array($node->id, $session_groupes_visibles)) {
                    if (in_array($node->id, $session_read_only_folders)) {
                        $text = "<i class='far fa-eye fa-xs mr-1'></i>" . $text;
                        $title = langHdl('read_only_account');
                        $restricted = 1;
                        $folderClass = 'folder_not_droppable';
                    } elseif ($_SESSION['user_read_only'] === true && !in_array($node->id, $session_personal_visible_groups)) {
                        $text = "<i class='far fa-eye fa-xs mr-1'></i>" . $text;
                    }
                    $text .= ' (<span class=\'items_count\' id=\'itcount_' . $node->id . '\'>' . $itemsNb . '</span>';
                    // display tree counters
                    if (isset($SETTINGS['tree_counters']) && $SETTINGS['tree_counters'] == 1) {
                        $text .= '|' . $nbChildrenItems . '|' . (count($nodeDescendants) - 1);
                    }
                    $text .= ')';
                } elseif (in_array($node->id, $listFoldersLimitedKeys)) {
                    $restricted = '1';
                    if ($_SESSION['user_read_only'] === true) {
                        $text = "<i class='far fa-eye fa-xs mr-1'></i>" . $text;
                    }
                    $text .= ' (<span class=\'items_count\' id=\'itcount_' . $node->id . '\'>' . count($session_list_folders_limited[$node->id]) . '</span>';
                } elseif (in_array($node->id, $listRestrictedFoldersForItemsKeys)) {
                    $restricted = '1';
                    if ($_SESSION['user_read_only'] === true) {
                        $text = "<i class='far fa-eye fa-xs mr-1'></i>" . $text;
                    }
                    $text .= ' (<span class=\'items_count\' id=\'itcount_' . $node->id . '\'>' . count($session_list_restricted_folders_for_items[$node->id]) . '</span>';
                } else {
                    $restricted = '1';
                    $folderClass = 'folder_not_droppable';
                    if (isset($SETTINGS['show_only_accessible_folders']) && $SETTINGS['show_only_accessible_folders'] === '1') {
                        // folder is not visible
                        $nodeDirectDescendants = $tree->getDescendants($nodeId, false, true, true);
                        if (count($nodeDirectDescendants) > 0) {
                            // show it but block it
                            $hide_node = false;
                            $show_but_block = true;
                        } else {
                            // hide it
                            $hide_node = true;
                        }
                    } else {
                        // folder is visible but not accessible by user
                        $show_but_block = true;
                    }
                }

                // if required, separate the json answer for each folder
                if (!empty($ret_json)) {
                    $ret_json .= ', ';
                }

                // json
                if ($hide_node === false && $show_but_block === false) {
                    $ret_json .= '{' .
                        '"id":"li_' . $node->id . '"' .
                        ', "parent":"' . $parent . '"' .
                        ', "label":"' . $parent . '"' .
                        ', "level":"' . $parent . '"' .
                        ', "children":' . ($childrenNb == 0 ? 'false' : 'true') .
                        ', "text":"' . str_replace('"', '&quot;', $text) . '"' .
                        ', "li_attr":{"class":"jstreeopen", "title":"ID [' . $node->id . '] ' . $title . '"}' .
                        ', "a_attr":{"id":"fld_' . $node->id . '", "class":"' . $folderClass . ' nodblclick" , "onclick":"ListerItems(\'' . $node->id . '\', \'' . $restricted . '\', 0, 1)"}' .
                        '}';
                } elseif ($show_but_block === true) {
                    $ret_json .= '{' .
                        '"id":"li_' . $node->id . '"' .
                        ', "parent":"' . $parent . '"' .
                        ', "children":' . ($childrenNb == 0 ? 'false' : 'true') .
                        ', "text":"<i class=\'fas fa-times fa-xs text-danger mr-1\'></i>' . $text . '"' .
                        ', "li_attr":{"class":"", "title":"ID [' . $node->id . '] ' . langHdl('no_access') . '"}' .
                        '}';
                }
            }
        }
    }
}

/*
* Get through complete tree
*/
function recursiveTree(
    $nodeId,
    $completTree,
    $tree,
    $listFoldersLimitedKeys,
    $listRestrictedFoldersForItemsKeys,
    $last_visible_parent,
    $last_visible_parent_level,
    $SETTINGS
) {
    global $ret_json;
    //, $listFoldersLimitedKeys, $listRestrictedFoldersForItemsKeys, $tree, $LANG, $last_visible_parent, $last_visible_parent_level, $SETTINGS;
    $text = '';
    $title = '';
    $show_but_block = false;

    // Load config
    if (file_exists('../includes/config/tp.config.php')) {
        include '../includes/config/tp.config.php';
    } elseif (file_exists('./includes/config/tp.config.php')) {
        include './includes/config/tp.config.php';
    } else {
        throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
    }

    // Load library
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
    $superGlobal = new protect\SuperGlobal\SuperGlobal();

    // Prepare superGlobal variables
    $session_forbiden_pfs = $superGlobal->get('forbiden_pfs', 'SESSION');
    $session_groupes_visibles = $superGlobal->get('groupes_visibles', 'SESSION');
    $session_list_restricted_folders_for_items = $superGlobal->get('list_restricted_folders_for_items', 'SESSION');
    $session_user_id = $superGlobal->get('user_id', 'SESSION');
    $session_login = $superGlobal->get('login', 'SESSION');
    $session_user_read_only = $superGlobal->get('user_read_only', 'SESSION');
    //$session_no_access_folders = $superGlobal->get('no_access_folders', 'SESSION');
    $session_list_folders_limited = $superGlobal->get('list_folders_limited', 'SESSION');
    $session_read_only_folders = $superGlobal->get('read_only_folders', 'SESSION');
    //$session_all_folders_including_no_access = $superGlobal->get('all_folders_including_no_access', 'SESSION');
    //print_r($session_groupes_visibles);
    // Be sure that user can only see folders he/she is allowed to
    if (
        in_array($completTree[$nodeId]->id, $session_forbiden_pfs) === false
        || in_array($completTree[$nodeId]->id, $session_groupes_visibles) === true
        //|| in_array($completTree[$nodeId]->id, $listFoldersLimitedKeys) === true
        //|| in_array($completTree[$nodeId]->id, $listRestrictedFoldersForItemsKeys) === true
    ) {
        $displayThisNode = false;
        $hide_node = false;
        $nbChildrenItems = 0;

        // Check if any allowed folder is part of the descendants of this node
        $nodeDescendants = $tree->getDescendants($completTree[$nodeId]->id, true, false, true);
        //echo $completTree[$nodeId]->id.' --> ';
        //print_r($nodeDescendants);
        foreach ($nodeDescendants as $node) {
            // manage tree counters
            if (
                isset($SETTINGS['tree_counters']) === true && (int) $SETTINGS['tree_counters'] === 1
                && in_array(
                    $node,
                    array_merge($session_groupes_visibles, $session_list_restricted_folders_for_items)
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
                in_array($node, $session_groupes_visibles) === true
                //|| @in_array($node, $listFoldersLimitedKeys) === true
                //|| @in_array($node, $listRestrictedFoldersForItemsKeys) === true
            ) {
                // Final check - is PF allowed?
                $nodeDetails = $tree->getNode($node);
                if (
                    (int) $nodeDetails->personal_folder === 1
                    && (int) $SETTINGS['enable_pf_feature'] === 1
                    && (int) $_SESSION['personal_folder'] === 0
                ) {
                    $displayThisNode = false;
                } else {
                    $displayThisNode = true;
                }
                $hide_node = $show_but_block = false;
                $text = $title = '';
            }
        }
        //echo ' --> '.$node." -- ".$displayThisNode.'<br>';
        if ($displayThisNode === true) {
            // get info about current folder
            DB::query(
                'SELECT * FROM ' . prefixTable('items') . '
                WHERE inactif=%i AND id_tree = %i',
                0,
                $completTree[$nodeId]->id
            );
            $itemsNb = DB::count();

            // If personal Folder, convert id into user name
            if ($completTree[$nodeId]->title == $session_user_id && $completTree[$nodeId]->nlevel == 1) {
                $completTree[$nodeId]->title = $session_login;
            }

            // Decode if needed
            $completTree[$nodeId]->title = htmlspecialchars_decode($completTree[$nodeId]->title, ENT_QUOTES);

            // special case for READ-ONLY folder
            if (
                $session_user_read_only === true
                && in_array($completTree[$nodeId]->id, $session_user_read_only) === false
            ) {
                $title = langHdl('read_only_account');
            }
            $text .= str_replace('&', '&amp;', $completTree[$nodeId]->title);
            $restricted = '0';
            $folderClass = 'folder';

            if (in_array($completTree[$nodeId]->id, $session_groupes_visibles) === true) {
                if (in_array($completTree[$nodeId]->id, $session_read_only_folders) === true) {
                    $text = "<i class='far fa-eye fa-xs mr-1'></i>" . $text;
                    $title = langHdl('read_only_account');
                    $restricted = 1;
                    $folderClass = 'folder_not_droppable';
                } elseif (
                    $session_user_read_only === true
                    && in_array($completTree[$nodeId]->id, $_SESSION['personal_visible_groups']) === false
                ) {
                    $text = "<i class='far fa-eye fa-xs mr-1'></i>" . $text;
                }
                $text .= ' (<span class=\'items_count\' id=\'itcount_' . $completTree[$nodeId]->id . '\'>' . $itemsNb . '</span>';
                // display tree counters
                if (
                    isset($SETTINGS['tree_counters']) === true
                    && $SETTINGS['tree_counters'] == 1
                ) {
                    $text .= '|' . $nbChildrenItems . '|' . (count($nodeDescendants) - 1);
                }
                $text .= ')';
            } elseif (in_array($completTree[$nodeId]->id, $listFoldersLimitedKeys) === true) {
                $restricted = '1';
                if ($session_user_read_only === true) {
                    $text = "<i class='far fa-eye fa-xs mr-1'></i>" . $text;
                }
                $text .= ' (<span class=\'items_count\' id=\'itcount_' . $completTree[$nodeId]->id . '\'>' . count($session_list_folders_limited[$completTree[$nodeId]->id]) . '</span>)';
            } elseif (in_array($completTree[$nodeId]->id, $listRestrictedFoldersForItemsKeys) === true) {
                $restricted = '1';
                if ($_SESSION['user_read_only'] === true) {
                    $text = "<i class='far fa-eye fa-xs mr-1'></i>" . $text;
                }
                $text .= ' (<span class=\'items_count\' id=\'itcount_' . $completTree[$nodeId]->id . '\'>' . count($_SESSION['list_restricted_folders_for_items'][$completTree[$nodeId]->id]) . '</span>)';
            } else {
                $restricted = '1';
                $folderClass = 'folder_not_droppable';
                if (
                    isset($SETTINGS['show_only_accessible_folders']) === true
                    && $SETTINGS['show_only_accessible_folders'] === '1'
                    && $nbChildrenItems === 0
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
                        $show_but_block = true;
                        $hide_node = false;
                    } else {
                        // hide it
                        $hide_node = true;
                    }
                } else {
                    // folder is visible but not accessible by user
                    $show_but_block = true;
                }
            }

            // prepare json return for current node
            if ($completTree[$nodeId]->parent_id === '0') {
                $parent = '#';
            } else {
                $parent = 'li_' . $completTree[$nodeId]->parent_id;
            }

            // handle displaying
            if (
                isset($SETTINGS['show_only_accessible_folders']) === true
                && $SETTINGS['show_only_accessible_folders'] === '1'
            ) {
                if ($hide_node === true) {
                    $last_visible_parent = $parent;
                    $last_visible_parent_level = $completTree[$nodeId]->nlevel--;
                } elseif ($completTree[$nodeId]->nlevel < $last_visible_parent_level) {
                    $last_visible_parent = '';
                }
            }

            // json
            if ($hide_node === false && $show_but_block === false) {
                array_push(
                    $ret_json,
                    array(
                        'id' => 'li_' . $completTree[$nodeId]->id,
                        'parent' => empty($last_visible_parent) === true ? $parent : $last_visible_parent,
                        'text' => $text,
                        'li_attr' => array(
                            'class' => 'jstreeopen',
                            'title' => 'ID [' . $completTree[$nodeId]->id . '] ' . $title,
                        ),
                        'a_attr' => array(
                            'id' => 'fld_' . $completTree[$nodeId]->id,
                            'class' => $folderClass,
                            'onclick' => 'ListerItems(' . $completTree[$nodeId]->id . ', ' . $restricted . ', 0, 1)',
                            //'ondblclick' => 'LoadTreeNode('.$completTree[$nodeId]->id.')',
                            'data-title' => $completTree[$nodeId]->title,
                        ),
                    )
                );
            } elseif ($show_but_block === true) {
                array_push(
                    $ret_json,
                    array(
                        'id' => 'li_' . $completTree[$nodeId]->id,
                        'parent' => empty($last_visible_parent) === true ? $parent : $last_visible_parent,
                        'text' => '<i class="fas fa-times fa-xs text-danger mr-1"></i>' . $text,
                        'li_attr' => array(
                            'class' => '',
                            'title' => 'ID [' . $completTree[$nodeId]->id . '] ' . langHdl('no_access'),
                        ),
                    )
                );
            }
            foreach ($completTree[$nodeId]->children as $child) {
                recursiveTree(
                    $child,
                    $completTree,
                    $tree,
                    $listFoldersLimitedKeys,
                    $listRestrictedFoldersForItemsKeys,
                    $last_visible_parent,
                    $last_visible_parent_level,
                    $SETTINGS
                );
            }
        }
    }
}
