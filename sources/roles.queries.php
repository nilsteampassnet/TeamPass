<?php
/**
 * @package       roles.queries.php
 * @author        Nils Laumaillé <nils@teampass.net>
 * @version       2.1.27
 * @copyright     2009-2018 Nils Laumaillé
 * @license       GNU GPL-3.0
 * @link          https://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once 'SecureHandler.php';
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 ||
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) ||
    !isset($_SESSION['key']) || empty($_SESSION['key'])
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
/* do checks */
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_roles")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

include $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset=utf-8");
require_once 'main.functions.php';

require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

//Connect to DB
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
$pass = defuse_return_decrypted($pass);
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = true;
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);

//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'].'/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

if (null !== filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING)) {
    switch (filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING)) {
        #CASE adding a new role
        case "add_new_role":
            // Prepare POST variables
            $post_name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);

            //Check if role already exist : No similar roles
            $tmp = DB::query(
                "SELECT * FROM ".prefix_table("roles_title")." WHERE title = %s",
                stripslashes($post_name)
            );
            $counter = DB::count();
            if ($counter == 0) {
                db::debugmode(false);
                DB::insert(
                    prefix_table("roles_title"),
                    array(
                        'title' => noHTML($post_name),
                        'complexity' => filter_input(INPUT_POST, 'complexity', FILTER_SANITIZE_NUMBER_INT),
                        'creator_id' => $_SESSION['user_id']
                    )
                );
                $role_id = DB::insertId();

                if ($role_id != 0) {
                    //Actualize the variable
                    $_SESSION['nb_roles']++;

                    // get some data
                    $data_tmp = DB::queryfirstrow(
                        "SELECT fonction_id FROM ".prefix_table("users")." WHERE id = %s",
                        $_SESSION['user_id']
                    );

                    // add new role to user
                    $tmp = str_replace(";;", ";", $data_tmp['fonction_id']);
                    if (substr($tmp, -1) == ";") {
                        $_SESSION['fonction_id'] = str_replace(";;", ";", $data_tmp['fonction_id'].$role_id);
                    } else {
                        $_SESSION['fonction_id'] = str_replace(";;", ";", $data_tmp['fonction_id'].";".$role_id);
                    }
                    // store in DB
                    DB::update(
                        prefix_table("users"),
                        array(
                            'fonction_id' => $_SESSION['fonction_id']
                            ),
                        "id = %i",
                        $_SESSION['user_id']
                    );
                    $_SESSION['user_roles'] = explode(";", $_SESSION['fonction_id']);


                    echo '[ { "error" : "no" } ]';
                } else {
                    echo '[ { "error" : "yes" , "message" : "Database error. Contact your administrator!" } ]';
                }
            } else {
                echo '[ { "error" : "yes" , "message" : "'.$LANG['error_role_exist'].'" } ]';
            }
            break;

        //-------------------------------------------
        #CASE delete a role
        case "delete_role":
            // Prepare POST variables
            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // Delete roles
            DB::delete(prefix_table("roles_title"), "id = %i", $post_id);
            DB::delete(prefix_table("roles_values"), "role_id = %i", $post_id);

            //Actualize the variable
            $_SESSION['nb_roles']--;

            // parse all users to remove this role
            $rows = DB::query(
                "SELECT id, fonction_id FROM ".prefix_table("users")."
                ORDER BY id ASC"
            );
            foreach ($rows as $record) {
                $tab = explode(";", $record['fonction_id']);
                if (($key = array_search($post_id, $tab)) !== false) {
                    // remove the deleted role id
                    unset($tab[$key]);

                    // store new list of functions
                    DB::update(
                        prefix_table("users"),
                        array(
                            'fonction_id' => rtrim(implode(";", $tab), ";")
                            ),
                        "id = %i",
                        $record['id']
                    );
                }
            }

            echo '[ { "error" : "no" } ]';
            break;

        //-------------------------------------------
        #CASE editing a role
        case "edit_role":
            // Prepare POST variables
            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
            $post_title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);

            //Check if role already exist : No similar roles
            DB::query(
                "SELECT * FROM ".prefix_table("roles_title")." WHERE title = %s AND id != %i",
                $post_title,
                $post_id
            );
            $counter = DB::count();
            if ($counter == 0) {
                DB::update(
                    prefix_table("roles_title"),
                    array(
                        'title' => noHTML(filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING)),
                        'complexity' => filter_input(INPUT_POST, 'complexity', FILTER_SANITIZE_NUMBER_INT),
                    ),
                    'id = %i',
                    filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                );
                echo '[ { "error" : "no" } ]';
            } else {
                echo '[ { "error" : "yes" , "message" : "'.$LANG['error_role_exist'].'" } ]';
            }
            break;

        /******************************************
        *CASE editing a role
        */
        case "allow_pw_change_for_role":
            DB::update(
                prefix_table("roles_title"),
                array(
                    'allow_pw_change' => filter_input(INPUT_POST, 'value', FILTER_SANITIZE_STRING)
                ),
                'id = %i',
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
            );
            break;

        //-------------------------------------------
        #CASE change right for a role on a folder via the TM
        case "change_role_via_tm":
            // Prepare POST variables
            $post_access = filter_input(INPUT_POST, 'access', FILTER_SANITIZE_STRING);
            $post_accessoption = filter_input(INPUT_POST, 'accessoption', FILTER_SANITIZE_STRING);

            // Loop on selection
            $arr = explode(",", filter_input(INPUT_POST, 'multiselection', FILTER_SANITIZE_STRING));
            foreach ($arr as $elem) {
                $tmp = explode('-', $elem);
                $folder_id = $tmp[0];
                $role_id = $tmp[1];

                //get full tree dependencies
                $tree_list = $tree->getDescendants($folder_id, true);

                if ($post_access === "read" || $post_access === "write" || $post_access === "nodelete") {
                    // define code to use
                    if ($post_access == "read") {
                        $access = "R";
                    } elseif ($post_access == "write") {
                        if (empty($post_accessoption) === true) {
                            $access = "W";
                        } elseif ($post_accessoption === "nodelete") {
                            $access = "ND";
                        } elseif ($post_accessoption === "noedit") {
                            $access = "NE";
                        } elseif ($post_accessoption === "nodelete_noedit") {
                            $access = "NDNE";
                        }
                    } else {
                        $access = "";
                    }

                    // loop
                    foreach ($tree_list as $node) {
                        // delete
                        DB::delete(prefix_table("roles_values"), "folder_id = %i AND role_id = %i", $node->id, $role_id);

                        //Store in DB
                        DB::insert(
                            prefix_table("roles_values"),
                            array(
                                'folder_id' => $node->id,
                                'role_id' => $role_id,
                                'type' => $access
                            )
                        );
                    }
                } else {
                    foreach ($tree_list as $node) {
                        // delete
                        DB::delete(prefix_table("roles_values"), "folder_id = %i AND role_id = %i", $node->id, $role_id);
                    }
                }
            }

            echo '[ { "error" : "no" } ]';
            break;

        //-------------------------------------------
        #CASE refresh the matrix
        case "refresh_roles_matrix":
            // Ensure Complexity levels are translated
            if (isset($SETTINGS_EXT['pwComplexity']) === false) {
                $SETTINGS_EXT['pwComplexity'] = array(
                    0=>array(0, $LANG['complex_level0']),
                    25=>array(25, $LANG['complex_level1']),
                    50=>array(50, $LANG['complex_level2']),
                    60=>array(60, $LANG['complex_level3']),
                    70=>array(70, $LANG['complex_level4']),
                    80=>array(80, $LANG['complex_level5']),
                    90=>array(90, $LANG['complex_level6'])
                );
            }

            // Prepare POST variables
            $post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);
            $post_filter = filter_input(INPUT_POST, 'filter', FILTER_SANITIZE_STRING);

            $tree = $tree->getDescendants();
            $texte = '<table><thead><tr><th>'.$LANG['groups'].'</th>';
            $gpes_ok = array();
            $gpes_nok = array();
            $arrRolesTitle = array();
            $arrRoles = array();
            $display_nb = 8;
            $sql_limit = "";
            $next = 1;
            $previous = 1;
            $where = "";

            // Should we filter on role names
            if (empty($post_filter) === false) {
                $where = " WHERE title LIKE '".$post_filter."%'";
            }

            //count nb of roles
            $arrUserRoles = array_filter($_SESSION['user_roles']);
            if (count($arrUserRoles) > 0 && $_SESSION['is_admin'] !== "1") {
                if (empty($where) === true) {
                    $where = " WHERE id IN (".implode(',', $arrUserRoles).")";
                } else {
                    $where .= " AND id IN (".implode(',', $arrUserRoles).")";
                }
            }
            DB::query("SELECT * FROM ".prefix_table("roles_title").$where);
            $roles_count = DB::count();
            if ($roles_count > $display_nb) {
                if (null === $post_start || intval($post_start) === 0) {
                    $start = 0;
                    $previous = 0;
                } else {
                    $start = $post_start;
                    if ($start - $display_nb >= 0) {
                        $previous = $start - $display_nb;
                    } else {
                        $previous = 0;
                    }
                }
                $sql_limit = " LIMIT ".mysqli_real_escape_string($link, filter_var($start, FILTER_SANITIZE_NUMBER_INT)).", ".mysqli_real_escape_string($link, filter_var($display_nb, FILTER_SANITIZE_NUMBER_INT));
                $next = $start + $display_nb;
            }

            //Display table header
            $rows = DB::query(
                "SELECT * FROM ".prefix_table("roles_title").
                $where."
                ORDER BY title ASC".$sql_limit
            );
            foreach ($rows as $record) {
                if ($_SESSION['is_admin'] === '1'
                    || (($_SESSION['user_manager'] === '1' || $_SESSION['user_can_manage_all_users'] === '1')
                        && (in_array($record['id'], $arrUserRoles) == true
                            || $record['creator_id'] == $_SESSION['user_id']
                        )
                    )
                ) {
                    if ($record['allow_pw_change'] == 1) {
                        $allow_pw_change = '&nbsp;<span class=\'fa mi-red fa-2x fa-magic tip\' id=\'img_apcfr_'.$record['id'].'\' onclick=\'allow_pw_change_for_role('.$record['id'].', 0)\' style=\'cursor:pointer;\' title=\''.$LANG['role_cannot_modify_all_seen_items'].'\'></span>';
                    } else {
                        $allow_pw_change = '&nbsp;<span class=\'fa fa-magic fa-2x mi-green tip\'  id=\'img_apcfr_'.$record['id'].'\' onclick=\'allow_pw_change_for_role('.$record['id'].', 1)\' style=\'cursor:pointer;\' title=\''.$LANG['role_can_modify_all_seen_items'].'\'></span>';
                    }
                    $title = filter_var(htmlspecialchars_decode($record['title'], ENT_QUOTES), FILTER_SANITIZE_STRING);

                    $texte .= '<th style=\'font-size:10px;min-width:60px;\' class=\'edit_role\'>'.
                        $title.
                        '<br>'.
                        '<span class=\'fa fa-pencil fa-2x mi-grey-1\' onclick=\'edit_this_role('.$record['id'].',"'.htmlentities(htmlspecialchars_decode($record['title'], ENT_QUOTES), ENT_QUOTES, "UTF-8").'",'.$record['complexity'].')\' style=\'cursor:pointer;\'></span>&nbsp;'.
                        '<span class=\'fa fa-trash fa-2x mi-grey-1\' style=\'cursor:pointer;\' onclick=\'delete_this_role('.$record['id'].',"'.htmlentities($record['title'], ENT_QUOTES, "UTF-8").'")\'></span>'.
                        $allow_pw_change.
                        '<div style=\'margin-top:-8px;\'>[&nbsp;'.$SETTINGS_EXT['pwComplexity'][$record['complexity']][1].'&nbsp;]</div></th>';

                    array_push($arrRoles, $record['id']);
                    array_push($arrRolesTitle, $record['title']);
                }
            }
            $texte .= '</tr></thead><tbody>';

            //Display each folder with associated rights by role
            $i = 0;
            foreach ($tree as $node) {
                if (in_array($node->id, $_SESSION['groupes_visibles']) && !in_array($node->id, $_SESSION['personal_visible_groups'])) {
                    $ident = "";
                    for ($a = 1; $a < $node->nlevel; $a++) {
                        $ident .= '<i class=\'fa fa-sm fa-caret-right mi-grey-1\'></i>&nbsp;';
                    }

                    //display 1st cell of the line
                    $texte .= '<tr><td style=\'font-size:10px; font-family:arial;\' title=\'ID='.$node->id.'\'>'.$ident." ".$node->title.'</td>';

                    foreach ($arrRoles as $role) {
                        //check if this role has access or not
                        // if not then color is red; if yes then color is green
                        $role_detail = DB::queryfirstrow("SELECT * FROM ".prefix_table("roles_values")." WHERE folder_id = %i AND role_id = %i", $node->id, $role);
                        if (DB::count() > 0) {
                            if ($role_detail['type'] == "W") {
                                $color = '#008000';
                                $allowed = "W";
                                $title = $LANG['write'];
                                $label = '<i class="fa fa-indent"></i>&nbsp;<i class="fa fa-edit"></i>&nbsp;<i class="fa fa-eraser"></i>';
                            } elseif ($role_detail['type'] == "ND") {
                                $color = '#4E45F7';
                                $allowed = "ND";
                                $title = $LANG['no_delete'];
                                $label = '<i class="fa fa-indent"></i>&nbsp;<i class="fa fa-edit"></i>';
                            } elseif ($role_detail['type'] == "NE") {
                                $color = '#4E45F7';
                                $allowed = "NE";
                                $title = $LANG['no_edit'];
                                $label = '<i class="fa fa-indent"></i>&nbsp;<i class="fa fa-eraser"></i>';
                            } elseif ($role_detail['type'] == "NDNE") {
                                $color = '#4E45F7';
                                $allowed = "NDNE";
                                $title = $LANG['no_edit_no_delete'];
                                $label = '<i class="fa fa-indent"></i>';
                            } else {
                                $color = '#FEBC11';
                                $allowed = "R";
                                $title = $LANG['read'];
                                $label = '<i class="fa fa-eye"></i>';
                            }
                        } else {
                            $color = '#FF0000';
                            $allowed = false;
                            $title = $LANG['no_access'];
                            $label = '<i class="fa fa-hand-stop-o"></i>';
                        }
                        if (in_array($node->id, $_SESSION['read_only_folders']) || !in_array($node->id, $_SESSION['groupes_visibles'])) {
                            $texte .= '<td align=\'center\' style=\'text-align:center;background-color:'.$color.'\' id=\'tm_cell_'.$i.'\' title=\''.$title.'\'>'.$label.'</td>';
                        } else {
                            $texte .= '<td align=\'center\' style=\'text-align:center;background-color:'.$color.'\' id=\'tm_cell_'.$i.'\' title=\''.$title.'\'><span onclick=\'openRightsDialog('.$role.','.$node->id.','.$i.',"'.$allowed.'")\'>'.$label.'</span><span style=\'float:right;\'><input type=\'checkbox\' title=\'\' class=\'multi_folders\' id=\'multisel_'.$node->id.'_'.$role.'\'></span></td>';
                        }


                        $i++;
                    }
                    $texte .= '</tr>';
                }
            }
            $texte .= '</tbody></table>';

            $return_values = array(
                "new_table" => $texte,
                "all" => $roles_count,
                "next" => $next,
                "previous" => $previous,
                "list_of_roles" => $arrRolesTitle
            );

            $return_values = json_encode($return_values, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

            //return data
            echo $return_values;

            break;
    }
}
