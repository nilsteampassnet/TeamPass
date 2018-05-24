<?php
/**
 * @package       datatable.php
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

require_once('../SecureHandler.php');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../../includes/config/tp.config.php')) {
    require_once '../../includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

global $k, $settings;
include $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
header("Content-type: text/html; charset=utf-8");
require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';

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

//init SQL variables
$filterLetter = '';

// Filter
if (isset($_GET['letter']) === true
    && $_GET['letter'] !== ""
    && $_GET['letter'] !== "None"
) {
    $filterLetter = filter_var($_GET['letter'], FILTER_SANITIZE_STRING);
}


//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'].'/includes/libraries');
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
    if (in_array($t->id, $_SESSION['groupes_visibles']) === true
        && in_array($t->id, $_SESSION['personal_visible_groups']) === false
        && $t->personal_folder == 0
    ) {
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

        // If filter on letter
        if (empty($filterLetter) === true
            || strtoupper(substr($t->title, 0, 1)) === $filterLetter
            ) {

            // start the line
            $sOutput .= "[";

            //col1
            if (($t->parent_id == 0 && ($_SESSION['is_admin'] == 1 || $_SESSION['can_create_root_folder'] == 1))
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
            if (isset($SETTINGS_EXT['pwComplexity'][$node_data['valeur']][1]) === true) {
                $sOutput .= '"<span id=\"complexite_'.$t->id.'\">'.$SETTINGS_EXT['pwComplexity'][$node_data['valeur']][1].'</span>"';
            } else {
                $sOutput .= '""';
            }
            $sOutput .= ',';

            //col4 - PARENT
            $data4 = DB::queryFirstRow(
                "SELECT title
                FROM ".$pre."nested_tree
                WHERE id = %i",
                intval($t->parent_id)
            );
            $sOutput .= '"<span id=\"parent_'.$t->id.'\">'.$data4['title'].' ('.$t->parent_id.')</span>"';
            $sOutput .= ',';

            //col5
            $sOutput .= '"'.$t->nlevel.'"';
            $sOutput .= ',';

            //col6
            $sOutput .= '"<span id=\"renewal_'.$t->id.'\">'.$node_data['renewal_period'].'</span>"';
            $sOutput .= ',';

            //col7
            $data7 = DB::queryFirstRow(
                "SELECT bloquer_creation,bloquer_modification
                FROM ".$pre."nested_tree
                WHERE id = %i",
                intval($t->id)
            );
            if (isset($data7['bloquer_creation']) && $data7['bloquer_creation'] == 1) {
                        $sOutput .= '"<i class=\"fa fa-toggle-on mi-green\" style=\"cursor:pointer;\" tp=\"'.$t->id.'-modif_droit_autorisation_sans_complexite-0\"></i>"';
            } else {
                        $sOutput .= '"<i class=\"fa fa-toggle-off\" style=\"cursor:pointer;\" tp=\"'.$t->id.'-modif_droit_autorisation_sans_complexite-1\"></i>"';
            }
            $sOutput .= ',';

            //col8
            if (isset($data7['bloquer_modification']) && $data7['bloquer_modification'] == 1) {
                        $sOutput .= '"<i class=\"fa fa-toggle-on mi-green\" style=\"cursor:pointer;\" tp=\"'.$t->id.'-modif_droit_modification_sans_complexite-0\"></i>';
            } else {
                        $sOutput .= '"<i class=\"fa fa-toggle-off\" style=\"cursor:pointer;\" tp=\"'.$t->id.'-modif_droit_modification_sans_complexite-1\"></i>';
            }
            $sOutput .= '<input type=\"hidden\" id=\"parent_id_'.$t->id.'\" value=\"'.$t->parent_id.'\" /><input type=\"hidden\"  id=\"renewal_id_'.$t->id.'\" value=\"'.$node_data['valeur'].'\" /><input type=\"hidden\"  id=\"block_creation_'.$t->id.'\" value=\"'.$node_data['bloquer_creation'].'\" /><input type=\"hidden\"  id=\"block_modif_'.$t->id.'\" value=\"'.$node_data['bloquer_modification'].'\" />"';

            //Finish the line
            $sOutput .= '],';

            array_push($arr_ids, $t->id);
            $x++;
        }
    }
}

if (count($treeDesc) > 0) {
    if (strrchr($sOutput, "[") != '[') {
        $sOutput = substr_replace($sOutput, "", -1);
    }
    $sOutput .= ']}';
} else {
    $sOutput .= '[] }';
}

// finalize output
echo '{"recordsTotal": '.$x.', "recordsFiltered": '.$x.', "data": '.$sOutput;
