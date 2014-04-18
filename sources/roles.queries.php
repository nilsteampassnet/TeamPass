<?php
/**
 * @file          roles.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.19
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('sessions.php');
session_start();
if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || 
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || 
    !isset($_SESSION['key']) || empty($_SESSION['key'])) 
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_roles")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include 'error.php';
    exit();
}

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
header("Content-type: text/html; charset=utf-8");
include 'main.functions.php';

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

//Connect to DB
$db = new SplClassLoader('Database\Core', '../includes/libraries');
$db->register();
$db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
$db->connect();

//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

if (!empty($_POST['type'])) {
    switch ($_POST['type']) {
        #CASE adding a new role
        case "add_new_role":
            //Check if role already exist : No similar roles
            //$tmp = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."roles_title WHERE title = '".mysql_real_escape_string(stripslashes($_POST['name']))."'");
            $tmp = $db->queryCount(
                "roles_title",
                array(
                    "title" => stripslashes($_POST['name'])
                )
            );
            if ($tmp[0] == 0) {
                $role_id = $db->queryInsert(
                        'roles_title',
                        array(
                                'title' => mysql_real_escape_string(stripslashes($_POST['name'])),
                                'complexity' => $_POST['complexity'],
                                'creator_id' => $_SESSION['user_id']
                        )
                );

                if ($role_id != 0) {
                    //Actualize the variable
                    $_SESSION['nb_roles'] ++;
                    echo '[ { "error" : "no" } ]';
                } else {
                    echo '[ { "error" : "yes" , "message" : "Database error. Contact your administrator!" } ]';
                }
            } else {
                echo '[ { "error" : "yes" , "message" : "'.$txt['error_role_exist'].'" } ]';
            }
            break;

        //-------------------------------------------
        #CASE delete a role
        case "delete_role":
            $db->query("DELETE FROM ".$pre."roles_title WHERE id = ".$_POST['id']);
            $db->query("DELETE FROM ".$pre."roles_values WHERE role_id = ".$_POST['id']);
            //Actualize the variable
            $_SESSION['nb_roles'] --;

            echo '[ { "error" : "no" } ]';
            break;

        //-------------------------------------------
        #CASE editing a role
        case "edit_role":
            //Check if role already exist : No similar roles
            //$tmp = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."roles_title WHERE id != '".$_POST['id']."' AND title = '".mysql_real_escape_string(stripslashes($_POST['title']))."'");
            $tmp = $db->queryCount(
                "roles_title",
                array(
                    "title" => stripslashes($_POST['title']),
                    "id" => intval($_POST['id'])
                )
            );
            if ($tmp[0] == 0) {
                $db->queryUpdate(
                    "roles_title",
                    array(
                        'title' => $_POST['title'],
                        'complexity' => $_POST['complexity']
                   ),
                    'id = '.$_POST['id']
                );
                echo '[ { "error" : "no" } ]';
            } else {
                echo '[ { "error" : "yes" , "message" : "'.$txt['error_role_exist'].'" } ]';
            }
            break;

        /******************************************
        *CASE editing a role
        */
        case "allow_pw_change_for_role":
            $db->queryUpdate(
                "roles_title",
                array(
                    'allow_pw_change' => $_POST['value']
               ),
                'id = '.$_POST['id']
            );
            break;

        //-------------------------------------------
        #CASE change right for a role on a folder via the TM
        case "change_role_via_tm":
            //get full tree dependencies
            $tree = $tree->getDescendants($_POST['folder'], true);

            if (isset($_POST['allowed']) AND $_POST['allowed'] == 1) {
                //case where folder was allowed but not any more
                foreach ($tree as $node) {
                    //Store in DB
                    $db->queryDelete(
                        'roles_values',
                        array(
                            'folder_id' => $node->id,
                            'role_id' => $_POST['role']
                       )
                    );
                }
            } elseif ($_POST['allowed'] == 0) {
                //case where folder was not allowed but allowed now
                foreach ($tree as $node) {
                    //Store in DB
                    $db->queryInsert(
                        'roles_values',
                        array(
                            'folder_id' => $node->id,
                            'role_id' => $_POST['role']
                       )
                    );
                }
            }
            break;

        //-------------------------------------------
        #CASE refresh the matrix
        case "refresh_roles_matrix":
            //pw complexity levels
            $pwComplexity = array(
                0=>array(0,$txt['complex_level0']),
                25=>array(25,$txt['complex_level1']),
                50=>array(50,$txt['complex_level2']),
                60=>array(60,$txt['complex_level3']),
                70=>array(70,$txt['complex_level4']),
                80=>array(80,$txt['complex_level5']),
                90=>array(90,$txt['complex_level6'])
            );

            $tree = $tree->getDescendants();
            $texte = '<table><thead><tr><th>'.$txt['group'].'s</th>';
            $gpes_ok = array();
            $gpes_nok = array();
            $tab_fonctions = array();
            $arrRoles = array();
            $display_nb = 8;

            //count nb of roles
            $roles_count = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."roles_title");
            if ($roles_count > $display_nb) {
                if (!isset($_POST['start']) || $_POST['start'] == 0) {
                    $start = 0;
                    $previous = 0;
                } else {
                    $start = intval($_POST['start']);
                    $previous = $start-$display_nb;
                }
                $sql_limit = " LIMIT $start, $display_nb";
                $next = $start+$display_nb;
            }

            // array of roles for actual user
            $my_functions = explode(';', $_SESSION['fonction_id']);

            //Display table header
            $rows = $db->fetchAllArray(
                "SELECT *
                FROM ".$pre."roles_title
                ORDER BY title ASC".
                $sql_limit
            );
            foreach ($rows as $reccord) {
                if ($_SESSION['is_admin'] == 1  || ($_SESSION['user_manager'] == 1 && (in_array($reccord['id'], $my_functions) || $reccord['creator_id'] == $_SESSION['user_id']))) {
                    if ($reccord['allow_pw_change'] == 1) {
                        $allow_pw_change = '&nbsp;<img id=\'img_apcfr_'.$reccord['id'].'\' src=\'includes/images/ui-text-field-password-green.png\' onclick=\'allow_pw_change_for_role('.$reccord['id'].', 0)\' style=\'cursor:pointer;\' title=\''.$txt['role_cannot_modify_all_seen_items'].'\'>';
                    } else {
                        $allow_pw_change = '&nbsp;<img id=\'img_apcfr_'.$reccord['id'].'\' src=\'includes/images/ui-text-field-password-red.png\' onclick=\'allow_pw_change_for_role('.$reccord['id'].', 1)\' style=\'cursor:pointer;\' title=\''.$txt['role_can_modify_all_seen_items'].'\'>';
                    }

                    $texte .= '<th style=\'font-size:10px;min-width:60px;\' class=\'edit_role\'>'.$reccord['title'].
                        '<br><img src=\'includes/images/ui-tab--pencil.png\' onclick=\'edit_this_role('.$reccord['id'].',"'.htmlentities($reccord['title'], ENT_QUOTES, "UTF-8").'",'.$reccord['complexity'].')\' style=\'cursor:pointer;\'>&nbsp;'.
                        '<img src=\'includes/images/ui-tab--minus.png\' style=\'cursor:pointer;\' onclick=\'delete_this_role('.$reccord['id'].',"'.htmlentities($reccord['title'], ENT_QUOTES, "UTF-8").'")\'>'.
                        $allow_pw_change.
                        '<div style=\'margin-top:-8px;\'>[&nbsp;'.$pwComplexity[$reccord['complexity']][1].'&nbsp;]</div></th>';

                    array_push($arrRoles, $reccord['id']);
                }
            }
            $texte .= '</tr></thead><tbody>';

            //Display each folder with associated rights by role
            $i=0;
            foreach ($tree as $node) {
                if (in_array($node->id, $_SESSION['groupes_visibles']) && !in_array($node->id, $_SESSION['personal_visible_groups'])) {
                    $ident="";
                    for ($a=1; $a<$node->nlevel; $a++) {
                        $ident .= "&nbsp;&nbsp;";
                    }

                    //display 1st cell of the line
                    $texte .= '<tr><td style=\'font-size:10px; font-family:arial;\'>'.$ident.$node->title.'</td>';

                    foreach ($arrRoles as $role) {
                        //check if this role has access or not
                        // if not then color is red; if yes then color is green
                        //$count = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."roles_values WHERE folder_id = ".$node->id." AND role_id = ".$role);
                        $count = $db->queryCount(
                            "roles_values",
                            array(
                                "folder_id" => intval($node->id),
                                "role_id" => intval($role)
                            )
                        );
                        if ($count[0] > 0) {
                            $couleur = '#008000';
                            $allowed = 1;
                        } else {
                            $couleur = '#FF0000';
                            $allowed = 0;
                        }
                        $texte .= '<td align=\'center\' style=\'background-color:'.$couleur.'\' onclick=\'tm_change_role('.$role.','.$node->id.','.$i.','.$allowed.')\' id=\'tm_cell_'.$i.'\'></td>';
                        $i++;
                    }
                    $texte .= '</tr>';
                }
            }
            $texte .= '</tbody></table>';

            $return_values = array(
                "new_table" => $texte,
                "all" => $roles_count[0],
                "next" => $next,
                "previous" => $previous
            );

            //Check if is UTF8. IF not send Error
            /*if (!isUTF8($texte)) {
                $return_values = array("error" => $txt['error_string_not_utf8']);
            }*/

            $return_values = json_encode($return_values, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);

            //return data
            echo $return_values;

            break;
    }
} elseif (!empty($_POST['edit_fonction'])) {
    $id = explode('_', $_POST['id']);
    //Update DB
    $db->queryUpdate(
        'roles_title',
        array(
            'title' => mysql_real_escape_string(stripslashes(utf8_decode($_POST['edit_fonction'])))
       ),
        "id = ".$id[1]
    );
    //Show value
    echo $_POST['edit_fonction'];
}
