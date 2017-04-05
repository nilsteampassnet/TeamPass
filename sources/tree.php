<?php
/**
 * @file          tree.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once 'SecureHandler.php';
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

// includes
require_once $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';

// header
header("Content-type: text/html; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

// Define Timezone
if (isset($_SESSION['settings']['timezone'])) {
    date_default_timezone_set($_SESSION['settings']['timezone']);
} else {
    date_default_timezone_set('UTC');
}

// Connect to mysql server
require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = 'db_error_handler';
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);

//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
$tree->rebuild();
$folders = $tree->getDescendants();

// define temporary sessions
if ($_SESSION['user_admin'] == 1 && (isset($k['admin_full_right'])
    && $k['admin_full_right'] == true) || !isset($k['admin_full_right'])) {
    $_SESSION['groupes_visibles'] = $_SESSION['personal_visible_groups'];
    $_SESSION['groupes_visibles_list'] = implode(',', $_SESSION['groupes_visibles']);
}
// prepare some variables
if (isset($_COOKIE['jstree_select']) && !empty($_COOKIE['jstree_select'])) {
    $firstGroup = str_replace("#li_", "", $_COOKIE['jstree_select']);
} else {
    $firstGroup = "";
}
if (isset($_SESSION['list_folders_limited']) && count($_SESSION['list_folders_limited']) > 0) {
    $listFoldersLimitedKeys = @array_keys($_SESSION['list_folders_limited']);
} else {
    $listFoldersLimitedKeys = array();
}
// list of items accessible but not in an allowed folder
if (isset($_SESSION['list_restricted_folders_for_items'])
    && count($_SESSION['list_restricted_folders_for_items']) > 0) {
    $listRestrictedFoldersForItemsKeys = @array_keys($_SESSION['list_restricted_folders_for_items']);
} else {
    $listRestrictedFoldersForItemsKeys = array();
}

$ret_json = $last_visible_parent = '';
$parent = "#";
$last_visible_parent_level = 1;

// build the tree to be displayed
if (isset($_GET['id']) && is_numeric(intval($_GET['id'])) && isset($_SESSION['user_settings']['treeloadstrategy']) && $_SESSION['user_settings']['treeloadstrategy'] == "sequential") {
    buildNodeTree($_GET['id']);
} else if (isset($_SESSION['user_settings']['treeloadstrategy']) && $_SESSION['user_settings']['treeloadstrategy'] == "sequential") {
    buildNodeTree(0);
} else {
    $completTree = $tree->getTreeWithChildren();
    foreach ($completTree[0]->children as $child) {
        $data = recursiveTree($child);
    }
}
echo '['.$ret_json.']';

/*
* Get through asked folders
*/
function buildNodeTree($nodeId)
{
    global $ret_json, $listFoldersLimitedKeys, $listRestrictedFoldersForItemsKeys, $tree, $LANG, $last_visible_parent, $last_visible_parent_level;

    // Be sure that user can only see folders he/she is allowed to
    if (
        !in_array($nodeId, $_SESSION['forbiden_pfs'])
        || in_array($nodeId, $_SESSION['groupes_visibles'])
        || in_array($nodeId, $listFoldersLimitedKeys)
        || in_array($nodeId, $listRestrictedFoldersForItemsKeys)
    ) {
        $displayThisNode = false;
        $hide_node = false;
        $nbChildrenItems = 0;

        // Check if any allowed folder is part of the descendants of this node
        $nodeDescendants = $tree->getDescendants($nodeId, false, true, false);
        foreach ($nodeDescendants as $node) {
            $displayThisNode = false;
            if (
                (!in_array($node->id, $_SESSION['forbiden_pfs'])
                || in_array($node->id, $_SESSION['groupes_visibles'])
                || in_array($node->id, $listFoldersLimitedKeys)
                || in_array($node->id, $listRestrictedFoldersForItemsKeys)) && (
                in_array(
                    $node->id,
                    array_merge($_SESSION['groupes_visibles'], $_SESSION['list_restricted_folders_for_items'])
                )
                || @in_array($node->id, $listFoldersLimitedKeys)
                || @in_array($node->id, $listRestrictedFoldersForItemsKeys)
                || in_array($node->id, $_SESSION['no_access_folders'])
                )
            ) {
                $displayThisNode = true;
            }

            if ($displayThisNode == true) {
                $hide_node = $show_but_block = $eye_icon = false;
                $text = $title = "";

                // get count of Items in this folder
                DB::query(
                    "SELECT * FROM ".prefix_table("items")."
                    WHERE inactif=%i AND id_tree = %i",
                    0,
                    $node->id
                );
                $itemsNb = DB::count();

                // get info about current folder
                DB::query(
                    "SELECT * FROM ".prefix_table("nested_tree")."
                    WHERE parent_id = %i",
                    $node->id
                );
                $childrenNb = DB::count();

                // If personal Folder, convert id into user name
                if ($node->title == $_SESSION['user_id'] && $node->nlevel == 1) {
                    $node->title = $_SESSION['login'];
                }

                // prepare json return for current node
                if ($node->parent_id==0) $parent = "#";
                else $parent = "li_".$node->parent_id;

                // special case for READ-ONLY folder
                if ($_SESSION['user_read_only'] === true && !in_array($node->id, $_SESSION['personal_folders'])) {
                    $eye_icon = true;
                    $title = $LANG['read_only_account'];
                }
                $text .= str_replace("&", "&amp;", $node->title);
                $restricted = "0";
                $folderClass = "folder";

                if (in_array($node->id, $_SESSION['groupes_visibles'])) {
                    if (in_array($node->id, $_SESSION['read_only_folders'])) {
                        $text = "<i class='fa fa-eye'></i>&nbsp;".$text;
                        $title = $LANG['read_only_account'];
                        $restricted = 1;
                        $folderClass = "folder_not_droppable";
                    } else if ($_SESSION['user_read_only'] == true && !in_array($node->id, $_SESSION['personal_visible_groups'])) {
                        $text = "<i class='fa fa-eye'></i>&nbsp;".$text;
                    }
                    $text .= ' (<span class=\'items_count\' id=\'itcount_'.$node->id.'\'>'.$itemsNb.'</span>';
                    // display tree counters
                    if (isset($_SESSION['settings']['tree_counters']) && $_SESSION['settings']['tree_counters'] == 1) {
                        $text .= '|'.$nbChildrenItems.'|'.(count($nodeDescendants)-1);
                    }
                    $text .= ')';
                } elseif (in_array($node->id, $listFoldersLimitedKeys)) {
                    $restricted = "1";
                    if ($_SESSION['user_read_only'] == true) {
                        $text = "<i class='fa fa-eye'></i>&nbsp;".$text;
                    }
                    $text .= ' (<span class=\'items_count\' id=\'itcount_'.$node->id.'\'>'.count($_SESSION['list_folders_limited'][$node->id]).'</span>';
                } elseif (in_array($node->id, $listRestrictedFoldersForItemsKeys)) {
                    $restricted = "1";
                    if ($_SESSION['user_read_only'] == true) {
                        $text = "<i class='fa fa-eye'></i>&nbsp;".$text;
                    }
                    $text .= ' (<span class=\'items_count\' id=\'itcount_'.$node->id.'\'>'.count($_SESSION['list_restricted_folders_for_items'][$node->id]).'</span>';
                } else {
                    $restricted = "1";
                    $folderClass = "folder_not_droppable";
                    if (isset($_SESSION['settings']['show_only_accessible_folders']) && $_SESSION['settings']['show_only_accessible_folders'] == 1) {
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
                if (!empty($ret_json)) $ret_json .= ", ";

                // json
                if ($hide_node == false && $show_but_block == false) {
                    $ret_json .= '{'.
                        '"id":"li_'.$node->id.'"'.
                        ', "parent":"'.$parent.'"'.
                        ', "children":'. ($childrenNb == 0 ? "false" : "true").
                        ', "text":"'.str_replace('"', '&quot;', $text).'"'.
                        ', "li_attr":{"class":"jstreeopen", "title":"ID ['.$node->id.'] '.$title.'"}'.
                        ', "a_attr":{"id":"fld_'.$node->id.'", "class":"'.$folderClass.' nodblclick" , "onclick":"ListerItems(\''.$node->id.'\', \''.$restricted.'\', 0, 1)"}'.
                    '}';
                } else if ($show_but_block == true) {
                    $ret_json .= '{'.
                        '"id":"li_'.$node->id.'"'.
                        ', "parent":"'.$parent.'"'.
                        ', "children":'. ($childrenNb == 0 ? "false" : "true").
                        ', "text":"<i class=\'fa fa-close mi-red\'></i>&nbsp;'.$text.'"'.
                        ', "li_attr":{"class":"", "title":"ID ['.$node->id.'] '.$LANG['no_access'].'"}'.
                    '}';
                }
            }
        }
    }
}

/*
* Get through complete tree
*/
function recursiveTree($nodeId)
{
    global $completTree, $ret_json, $listFoldersLimitedKeys, $listRestrictedFoldersForItemsKeys, $tree, $LANG, $last_visible_parent, $last_visible_parent_level;

    // Be sure that user can only see folders he/she is allowed to
    if (
        !in_array($completTree[$nodeId]->id, $_SESSION['forbiden_pfs'])
        || in_array($completTree[$nodeId]->id, $_SESSION['groupes_visibles'])
        || in_array($completTree[$nodeId]->id, $listFoldersLimitedKeys)
        || in_array($completTree[$nodeId]->id, $listRestrictedFoldersForItemsKeys)
    ) {
        $displayThisNode = false;
        $hide_node = false;
        $nbChildrenItems = 0;

        // Check if any allowed folder is part of the descendants of this node
        $nodeDescendants = $tree->getDescendants($completTree[$nodeId]->id, true, false, true);
        foreach ($nodeDescendants as $node) {
            // manage tree counters
            if (isset($_SESSION['settings']['tree_counters']) && $_SESSION['settings']['tree_counters'] == 1 && in_array($node,array_merge($_SESSION['groupes_visibles'], $_SESSION['list_restricted_folders_for_items']))) {
                DB::query(
                    "SELECT * FROM ".prefix_table("items")."
                    WHERE inactif=%i AND id_tree = %i",
                    0,
                    $node
                );
                $nbChildrenItems += DB::count();
            }
            if (
                in_array(
                    $node,
                    array_merge(
                        $_SESSION['groupes_visibles'],
                        $_SESSION['list_restricted_folders_for_items'],
                        $_SESSION['no_access_folders']
                    )
                )
                || @in_array($node, $listFoldersLimitedKeys)
                || @in_array($node, $listRestrictedFoldersForItemsKeys)
            ) {
                $displayThisNode = true;
                $hide_node = $show_but_block = $eye_icon = false;
                $text = $title = "";
                break;
            }
        }

        if ($displayThisNode == true) {
            // get info about current folder
            DB::query(
                "SELECT * FROM ".prefix_table("items")."
                WHERE inactif=%i AND id_tree = %i",
                0,
                $completTree[$nodeId]->id
            );
            $itemsNb = DB::count();

            // If personal Folder, convert id into user name
            if ($completTree[$nodeId]->title == $_SESSION['user_id'] && $completTree[$nodeId]->nlevel == 1) {
                $completTree[$nodeId]->title = $_SESSION['login'];
            }

            // special case for READ-ONLY folder
            if ($_SESSION['user_read_only'] === true && !in_array($completTree[$nodeId]->id, $user_pf)) {
                $eye_icon = true;
                $title = $LANG['read_only_account'];
            }
            $text .= str_replace("&", "&amp;", $completTree[$nodeId]->title);
            $restricted = "0";
            $folderClass = "folder";

            if (in_array($completTree[$nodeId]->id, $_SESSION['groupes_visibles'])) {
                if (in_array($completTree[$nodeId]->id, $_SESSION['read_only_folders'])) {
                    $text = "<i class='fa fa-eye'></i>&nbsp;".$text;
                    $title = $LANG['read_only_account'];
                    $restricted = 1;
                    $folderClass = "folder_not_droppable";
                } else if ($_SESSION['user_read_only'] == true && !in_array($completTree[$nodeId]->id, $_SESSION['personal_visible_groups'])) {
                    $text = "<i class='fa fa-eye'></i>&nbsp;".$text;
                }
                $text .= ' (<span class=\'items_count\' id=\'itcount_'.$completTree[$nodeId]->id.'\'>'.$itemsNb.'</span>';
                // display tree counters
                if (isset($_SESSION['settings']['tree_counters']) && $_SESSION['settings']['tree_counters'] == 1) {
                    $text .= '|'.$nbChildrenItems.'|'.(count($nodeDescendants)-1);
                }
                $text .= ')';
            } elseif (in_array($completTree[$nodeId]->id, $listFoldersLimitedKeys)) {
                $restricted = "1";
                if ($_SESSION['user_read_only'] == true) {
                    $text = "<i class='fa fa-eye'></i>&nbsp;".$text;
                }
                $text .= ' (<span class=\'items_count\' id=\'itcount_'.$completTree[$nodeId]->id.'\'>'.count($_SESSION['list_folders_limited'][$completTree[$nodeId]->id]).'</span>';
            } elseif (in_array($completTree[$nodeId]->id, $listRestrictedFoldersForItemsKeys)) {
                $restricted = "1";
                if ($_SESSION['user_read_only'] == true) {
                    $text = "<i class='fa fa-eye'></i>&nbsp;".$text;
                }
                $text .= ' (<span class=\'items_count\' id=\'itcount_'.$completTree[$nodeId]->id.'\'>'.count($_SESSION['list_restricted_folders_for_items'][$completTree[$nodeId]->id]).'</span>';
            } else {
                $restricted = "1";
                $folderClass = "folder_not_droppable";
                if (isset($_SESSION['settings']['show_only_accessible_folders']) && $_SESSION['settings']['show_only_accessible_folders'] === "1" && $nbChildrenItems === 0) {
                    // folder should not be visible
                    // only if it has no descendants
                    $nodeDirectDescendants = $tree->getDescendants($nodeId, false, false, true);
                    if (count(array_diff($nodeDirectDescendants, array_merge($_SESSION['groupes_visibles'], $_SESSION['list_restricted_folders_for_items']))) !== count($nodeDirectDescendants)) {
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
            if ($completTree[$nodeId]->parent_id === "0") {
                $parent = "#";
            } else {
                $parent = "li_".$completTree[$nodeId]->parent_id;
            }

            // handle displaying
            if (isset($_SESSION['settings']['show_only_accessible_folders']) && $_SESSION['settings']['show_only_accessible_folders'] === "1") {
                if ($hide_node === true) {
                    $last_visible_parent = $parent;
                    $last_visible_parent_level = $completTree[$nodeId]->nlevel --;
                } else if ($completTree[$nodeId]->nlevel < $last_visible_parent_level) {
                    $last_visible_parent = "";
                }
            }


            // json
            if ($hide_node === false  && $show_but_block === false) {
                $ret_json .= (!empty($ret_json) ? ", " : "") . '{'.
                    '"id":"li_'.$completTree[$nodeId]->id.'"'.
                    ', "parent":"'.(empty($last_visible_parent) ? $parent : $last_visible_parent).'"'.
                    ', "text":"'.str_replace('"', '&quot;', $text).'"'.
                    ', "li_attr":{"class":"jstreeopen", "title":"ID ['.$completTree[$nodeId]->id.'] '.$title.'"}'.
                    ', "a_attr":{"id":"fld_'.$completTree[$nodeId]->id.'", "class":"'.$folderClass.'" , "onclick":"ListerItems(\''.$completTree[$nodeId]->id.'\', \''.$restricted.'\', 0, 1)", "ondblclick":"LoadTreeNode(\''.$completTree[$nodeId]->id.'\')"}'.
                '}';
            } else if ($show_but_block === true) {
                $ret_json .= (!empty($ret_json) ? ", " : "") . '{'.
                    '"id":"li_'.$completTree[$nodeId]->id.'"'.
                    ', "parent":"'.(empty($last_visible_parent) ? $parent : $last_visible_parent).'"'.
                    ', "text":"<i class=\'fa fa-close mi-red\'></i>&nbsp;'.$text.'"'.
                    ', "li_attr":{"class":"", "title":"ID ['.$completTree[$nodeId]->id.'] '.$LANG['no_access'].'"}'.
                '}';
            }
            foreach ($completTree[$nodeId]->children as $child) {
                recursiveTree($child);
            }
        }
    }
}
