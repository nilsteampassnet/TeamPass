<?php
/**
 * @file          datatable.php
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

require_once('../SecureHandler.php');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

global $k, $settings;
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';
header("Content-type: text/html; charset=utf-8");
require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';

//Connect to DB
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
$tree = new Tree\NestedTree\NestedTree($pre."nested_tree", 'id', 'parent_id', 'title');

$treeDesc = $tree->getDescendants();

/*
   * Output
*/

if (count($treeDesc) > 0) {
    $sOutput = '[';
} else {
    $sOutput = "";
}

$x = 0;
$arr_ids = array();
foreach ($treeDesc as $t) {
    if (in_array($t->id, $_SESSION['groupes_visibles']) && !in_array($t->id, $_SESSION['personal_visible_groups']) && $t->personal_folder == 0) {
        // get $t->parent_id
        $data = DB::queryFirstRow("SELECT title FROM ".$pre."nested_tree WHERE id = %i", $t->parent_id);
        if ($t->nlevel == 1) {
            $data['title'] = $LANG['root'];
        }

        // get rights on this folder
        $tab_droits = array();
        $rows = DB::query("SELECT fonction_id  FROM ".$pre."rights WHERE authorized=%i AND tree_id = %i", 1, $t->id);
        foreach ($rows as $record) {
            array_push($tab_droits, $record['fonction_id']);
        }

        // identation to simulate depth
        $ident = "";
        for ($l = 1; $l < $t->nlevel; $l++) {
            $ident .= '<i class=\"fa fa-sm fa-caret-right mi-grey-1\"></i>&nbsp;';
        }
        // Get some elements from DB concerning this node
        $node_data = DB::queryFirstRow(
            "SELECT m.valeur AS valeur, n.renewal_period AS renewal_period,
            n.bloquer_creation AS bloquer_creation, n.bloquer_modification AS bloquer_modification
            FROM ".$pre."misc AS m,
            ".$pre."nested_tree AS n
            WHERE m.type=%s AND m.intitule = n.id AND m.intitule = %i",
            "complex",
            $t->id
        );

        // start the line
        $sOutput .= "[";

        //col1
        if (
            ($t->parent_id == 0 && ($_SESSION['is_admin'] == 1 || $_SESSION['can_create_root_folder'] == 1))
            ||
            $t->parent_id != 0
        ) {
            $sOutput .= '"<i class=\"fa fa-external-link tip\" style=\"cursor:pointer;\" onclick=\"open_edit_folder_dialog(\''.$t->id.'\')\" title=\"'.$LANG['edit'].' ['.$t->id.']'.'\"></i>&nbsp;<input type=\"checkbox\" class=\"cb_selected_folder\" id=\"cb_selected-'.$t->id.'\" />"';
        } else {
            $sOutput .= '""';
        }
        $sOutput .= ',';

        //col5
        $sOutput .= '"'.$t->id.'"';
        $sOutput .= ',';

        //col2
        $sOutput .= '"'.$ident.'<span id=\"title_'.$t->id.'\">'.addslashes(str_replace("'", "&lsquo;", $t->title)).'</span>"';
        $sOutput .= ',';

        // col3 - get number of items in folder
        $data_items = DB::query(
            "SELECT id
            FROM ".$pre."items
            WHERE id_tree = %i",
            $t->id
        );
        $sOutput .= '"'.DB::count().'"';
        $sOutput .= ',';

        //col3
        $sOutput .= '"<span id=\"complexite_'.$t->id.'\">'.@$_SESSION['settings']['pwComplexity'][$node_data['valeur']][1].'</span>"';
        $sOutput .= ',';

        //col4
        $sOutput .= '"<span id=\"parent_'.$t->id.'\">'.$t->parent_id.'</span>"';
        $sOutput .= ',';

        //col5
        $sOutput .= '"'.$t->nlevel.'"';
        $sOutput .= ',';

        //col6
        $sOutput .= '"<span id=\"renewal_'.$t->id.'\">'.$node_data['renewal_period'].'</span>"';
        $sOutput .= ',';

        $data3 = DB::queryFirstRow(
            "SELECT bloquer_creation,bloquer_modification
            FROM ".$pre."nested_tree
            WHERE id = %i",
            intval($t->id)
        );

        //col7
        if (isset($data3['bloquer_creation']) && $data3['bloquer_creation'] == 1)
            $sOutput .= '"<i class=\"fa fa-toggle-on mi-green\" style=\"cursor:pointer;\" tp=\"'.$t->id.'-modif_droit_autorisation_sans_complexite-0\"></i>"';
        else
            $sOutput .= '"<i class=\"fa fa-toggle-off\" style=\"cursor:pointer;\" tp=\"'.$t->id.'-modif_droit_autorisation_sans_complexite-1\"></i>"';
        $sOutput .= ',';

        //col8
        if (isset($data3['bloquer_modification']) && $data3['bloquer_modification'] == 1)
            $sOutput .= '"<i class=\"fa fa-toggle-on mi-green\" style=\"cursor:pointer;\" tp=\"'.$t->id.'-modif_droit_modification_sans_complexite-0\"></i>';
        else
            $sOutput .= '"<i class=\"fa fa-toggle-off\" style=\"cursor:pointer;\" tp=\"'.$t->id.'-modif_droit_modification_sans_complexite-1\"></i>';
        $sOutput .= '<input type=\"hidden\" id=\"parent_id_'.$t->id.'\" value=\"'.$t->parent_id.'\" /><input type=\"hidden\"  id=\"renewal_id_'.$t->id.'\" value=\"'.$node_data['valeur'].'\" /><input type=\"hidden\"  id=\"block_creation_'.$t->id.'\" value=\"'.$node_data['bloquer_creation'].'\" /><input type=\"hidden\"  id=\"block_modif_'.$t->id.'\" value=\"'.$node_data['bloquer_modification'].'\" />"';

        //Finish the line
        $sOutput .= '],';

        array_push($arr_ids, $t->id);
        $x++;
    }
}

if (count($treeDesc) > 0) {
    if (strrchr($sOutput, "[") != '[') $sOutput = substr_replace($sOutput, "", -1);
    $sOutput .= ']}';
} else {
    $sOutput .= '[] }';
}

// finalize output
echo '{"recordsTotal": '.$x.', "recordsFiltered": '.$x.', "data": '.$sOutput;
