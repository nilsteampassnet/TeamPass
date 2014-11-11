<?php
/**
 * @file          roles.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.22
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
require_once $_SESSION['settings']['cpassman_dir'].'/includes/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_roles")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
header("Content-type: text/html; charset=utf-8");
include 'main.functions.php';

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

//Connect to DB
require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$error_handler = 'db_error_handler';
$link = mysqli_connect($server, $user, $pass, $database, $port);

//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

if (!empty($_POST['type'])) {
    switch ($_POST['type']) {
        #CASE adding a new role
        case "add_new_role":
            //Check if role already exist : No similar roles
            $tmp = DB::query("SELECT * FROM ".$pre."roles_title WHERE title = %s", stripslashes($_POST['name']));
            $counter = DB::count();
            if ($counter == 0) {
                DB::insert(
                    $pre.'roles_title',
                    array(
                        'title' => stripslashes($_POST['name']),
                        'complexity' => $_POST['complexity'],
                        'creator_id' => $_SESSION['user_id']
                    )
                );
                $role_id = DB::insertId();

                if ($role_id != 0) {
                    //Actualize the variable
                    $_SESSION['nb_roles'] ++;
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
            DB::delete($pre."roles_title", "id = %i", $_POST['id']);
            DB::delete($pre."roles_values", "role_id = %i", $_POST['id']);
            //Actualize the variable
            $_SESSION['nb_roles'] --;

            echo '[ { "error" : "no" } ]';
            break;

        //-------------------------------------------
        #CASE editing a role
        case "edit_role":
            //Check if role already exist : No similar roles
            //$tmp = DB::fetchRow("SELECT COUNT(*) FROM ".$pre."roles_title WHERE id != '".$_POST['id']."' AND title = '".mysqli_escape_string($link, stripslashes($_POST['title']))."'");
            DB::queryt("SELECT * FROM ".$pre."roles_title WHERE title = %s AND id = %i", $_POST['title'], $_POST['id']);
            $counter = DB::count();
            if ($counter == 0) {
                DB::update(
                    $pre."roles_title",
                    array(
                        'title' => $_POST['title'],
                        'complexity' => $_POST['complexity']
                   ),
                    'id = %i',
                    $_POST['id']
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
                $pre."roles_title",
                array(
                    'allow_pw_change' => $_POST['value']
               ),
                'id = %i',
                $_POST['id']
            );
            break;

        //-------------------------------------------
        #CASE change right for a role on a folder via the TM
        case "change_role_via_tm_old":
            //get full tree dependencies
            $tree = $tree->getDescendants($_POST['folder'], true);

            if (isset($_POST['allowed']) && $_POST['allowed'] == 1) {
                //case where folder was allowed but not any more
                foreach ($tree as $node) {
                    //Store in DB
                    DB::delete($pre."roles_values", "folder_id = %i AND role_id = %i", $node->id, $_POST['role']);
                }
            } elseif ($_POST['allowed'] == 0) {
                //case where folder was not allowed but allowed now
                foreach ($tree as $node) {
                    //Store in DB
                    DB::insert(
                        $pre.'roles_values',
                        array(
                            'folder_id' => $node->id,
                            'role_id' => $_POST['role']
                       )
                    );
                }
            }
            break;

        //-------------------------------------------
        #CASE change right for a role on a folder via the TM
        case "change_role_via_tm":
            //get full tree dependencies
            $tree = $tree->getDescendants($_POST['folder'], true);

            if ($_POST['access'] == "read" ||$_POST['access'] == "write") {
                foreach ($tree as $node) {
                    // delete
                    DB::delete($pre."roles_values", "folder_id = %i AND role_id = %i", $node->id, $_POST['role']);
                    
                    //Store in DB
                    DB::insert(
                        $pre.'roles_values',
                        array(
                            'folder_id' => $node->id,
                            'role_id' => $_POST['role'],
                            'type' => $_POST['access'] == "write" ? "W" : "R"
                       )
                    );
                }
            } else {
                foreach ($tree as $node) {
                    // delete
                    DB::delete($pre."roles_values", "folder_id = %i AND role_id = %i", $node->id, $_POST['role']);
                }
            }
            echo '[ { "error" : "no" } ]';
            break;

        //-------------------------------------------
        #CASE refresh the matrix
        case "refresh_roles_matrix":
            //pw complexity levels
            $pwComplexity = array(
                0=>array(0,$LANG['complex_level0']),
                25=>array(25,$LANG['complex_level1']),
                50=>array(50,$LANG['complex_level2']),
                60=>array(60,$LANG['complex_level3']),
                70=>array(70,$LANG['complex_level4']),
                80=>array(80,$LANG['complex_level5']),
                90=>array(90,$LANG['complex_level6'])
            );

            $tree = $tree->getDescendants();
            $texte = '<table><thead><tr><th>'.$LANG['groups'].'</th>';
            $gpes_ok = array();
            $gpes_nok = array();
            $tab_fonctions = array();
            $arrRoles = array();
            $display_nb = 8;
            $sql_limit = "";
            $next = 1;
            $previous = 1;

            //count nb of roles
            DB::query("SELECT * FROM ".$pre."roles_title");
            $roles_count =  DB::count();
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
            $rows = DB::query("SELECT * FROM ".$pre."roles_title ORDER BY title ASC".$sql_limit);
            foreach ($rows as $record) {
                if ($_SESSION['is_admin'] == 1  || ($_SESSION['user_manager'] == 1 && (in_array($record['id'], $my_functions) || $record['creator_id'] == $_SESSION['user_id']))) {
                    if ($record['allow_pw_change'] == 1) {
                        $allow_pw_change = '&nbsp;<img id=\'img_apcfr_'.$record['id'].'\' src=\'includes/images/ui-text-field-password-green.png\' onclick=\'allow_pw_change_for_role('.$record['id'].', 0)\' style=\'cursor:pointer;\' title=\''.$LANG['role_cannot_modify_all_seen_items'].'\'>';
                    } else {
                        $allow_pw_change = '&nbsp;<img id=\'img_apcfr_'.$record['id'].'\' src=\'includes/images/ui-text-field-password-red.png\' onclick=\'allow_pw_change_for_role('.$record['id'].', 1)\' style=\'cursor:pointer;\' title=\''.$LANG['role_can_modify_all_seen_items'].'\'>';
                    }

                    $texte .= '<th style=\'font-size:10px;min-width:60px;\' class=\'edit_role\'>'.$record['title'].
                        '<br><img src=\'includes/images/ui-tab--pencil.png\' onclick=\'edit_this_role('.$record['id'].',"'.htmlentities($record['title'], ENT_QUOTES, "UTF-8").'",'.$record['complexity'].')\' style=\'cursor:pointer;\'>&nbsp;'.
                        '<img src=\'includes/images/ui-tab--minus.png\' style=\'cursor:pointer;\' onclick=\'delete_this_role('.$record['id'].',"'.htmlentities($record['title'], ENT_QUOTES, "UTF-8").'")\'>'.
                        $allow_pw_change.
                        '<div style=\'margin-top:-8px;\'>[&nbsp;'.$pwComplexity[$record['complexity']][1].'&nbsp;]</div></th>';

                    array_push($arrRoles, $record['id']);
                }
            }
            $texte .= '</tr></thead><tbody>';

            //Display each folder with associated rights by role
            $i=0;
            foreach ($tree as $node) {
                if (in_array($node->id, $_SESSION['groupes_visibles']) && !in_array($node->id, $_SESSION['personal_visible_groups'])) {
                    $ident="";
                    for ($a=1; $a<$node->nlevel; $a++) {
                        $ident .= "&#8212;";
                    }

                    //display 1st cell of the line
                    $texte .= '<tr><td style=\'font-size:10px; font-family:arial;\' title=\'ID='.$node->id.'\'>'.$ident." ".$node->title.'</td>';

                    foreach ($arrRoles as $role) {
                        //check if this role has access or not
                        // if not then color is red; if yes then color is green
                        $role_detail = DB::queryfirstrow("SELECT * FROM ".$pre."roles_values WHERE folder_id = %i AND role_id = %i", $node->id, $role);
                        if (DB::count() > 0) {
                            if ($role_detail['type'] == "W") {
                                $couleur = '#008000';
                                $allowed = "W";
                                $title = $LANG['write'];
                            } else {
                                $couleur = '#FEBC11';
                                $allowed = "R";
                                $title = $LANG['read'];
                            }
                        } else {
                            $couleur = '#FF0000';
                            $allowed = false;
                            $title = $LANG['no_access'];
                        }
                        //$texte .= '<td align=\'center\' style=\'background-color:'.$couleur.'\' onclick=\'tm_change_role('.$role.','.$node->id.','.$i.','.$allowed.')\' id=\'tm_cell_'.$i.'\'></td>';
                        $texte .= '<td align=\'center\' style=\'text-align:center;background-color:'.$couleur.'\' onclick=\'openRightsDialog('.$role.','.$node->id.','.$i.',"'.$allowed.'")\' id=\'tm_cell_'.$i.'\' title=\''.$title.'\'></td>';
                        
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
                "previous" => $previous
            );

            //Check if is UTF8. IF not send Error
            /*if (!isUTF8($texte)) {
                $return_values = array("error" => $LANG['error_string_not_utf8']);
            }*/

            $return_values = json_encode($return_values, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);

            //return data
            echo $return_values;

            break;
    }
} elseif (!empty($_POST['edit_fonction'])) {
    $id = explode('_', $_POST['id']);
    //Update DB
    DB::update(
        $pre.'roles_title',
        array(
            'title' => mysqli_escape_string($link, stripslashes(utf8_decode($_POST['edit_fonction'])))
       ),
        "id = %i",
        $id[1]
    );
    //Show value
    echo $_POST['edit_fonction'];
}
