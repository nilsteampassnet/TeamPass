<?php
/**
 * @file          users.queries.table.php
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
include $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
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
// Build FUNCTIONS list
$rolesList = array();
$rows = DB::query("SELECT id,title FROM ".$pre."roles_title"." ORDER BY title ASC");
foreach ($rows as $record) {
    $rolesList[$record['id']] = array('id' => $record['id'], 'title' => $record['title']);
}

$listAvailableUsers = $listAdmins = $html = "";
$listAlloFcts_position = false;

//Columns name
$aColumns = array('id', 'login', 'name', 'lastname', 'admin', 'read_only', 'gestionnaire', 'isAdministratedByRole', 'can_manage_all_users', 'can_create_root_folder', 'personal_folder', 'email', 'ga', 'fonction_id');
$aSortTypes = array('asc', 'desc');

//init SQL variables
$sWhere = $sOrder = $sLimit = "";

/* BUILD QUERY */
//Paging
$sLimit = "";
if (isset($_GET['length']) && $_GET['length'] != '-1') {
    $sLimit = "LIMIT ". filter_var($_GET['start'], FILTER_SANITIZE_NUMBER_INT) .", ". filter_var($_GET['length'], FILTER_SANITIZE_NUMBER_INT)."";
}

//Ordering
if (isset($_GET['order'][0]['dir']) && in_array($_GET['order'][0]['dir'], $aSortTypes)) {
    $sOrder = "ORDER BY  ";
    if (
        preg_match("#^(asc|desc)\$#i", $_GET['order'][0]['column'])
    ) {
        $sOrder .= "".$aColumns[ filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT) ]." "
        .mysqli_escape_string($link, $_GET['order'][0]['column']) .", ";
    }

    $sOrder = substr_replace($sOrder, "", -2);
    if ($sOrder == "ORDER BY") {
        $sOrder = "";
    }
}

/*
   * Filtering
   * NOTE this does not match the built-in DataTables filtering which does it
   * word by word on any field. It's possible to do here, but concerned about efficiency
   * on very large tables, and MySQL's regex functionality is very limited
*/
if (isset($_GET['letter']) && $_GET['letter'] != "" && $_GET['letter'] != "None") {
    if (empty($sWhere)) $sWhere = " WHERE ";
    $sWhere .= $aColumns[1]." LIKE '".filter_var($_GET['letter'], FILTER_SANITIZE_STRING)."%' OR ";
    $sWhere .= $aColumns[2]." LIKE '".filter_var($_GET['letter'], FILTER_SANITIZE_STRING)."%' OR ";
    $sWhere .= $aColumns[3]." LIKE '".filter_var($_GET['letter'], FILTER_SANITIZE_STRING)."%' ";
}
elseif (isset($_GET['search']['value']) && $_GET['search']['value'] != "") {
    if (empty($sWhere)) $sWhere = " WHERE ";
    $sWhere .= $aColumns[1]." LIKE '".filter_var($_GET['search']['value'], FILTER_SANITIZE_STRING)."%' OR ";
    $sWhere .= $aColumns[2]." LIKE '".filter_var($_GET['search']['value'], FILTER_SANITIZE_STRING)."%' OR ";
    $sWhere .= $aColumns[3]." LIKE '".filter_var($_GET['search']['value'], FILTER_SANITIZE_STRING)."%' ";
}

// enlarge the query in case of Manager
if (!$_SESSION['is_admin'] && !$_SESSION['user_can_manage_all_users']) {
    if (empty($sWhere)) $sWhere = " WHERE ";
    else $sWhere .= " AND ";
    $sWhere .= "isAdministratedByRole IN (".implode(",", array_filter($_SESSION['user_roles'])).")";
}

$rows = DB::query(
    "SELECT * FROM ".$pre."users
    $sWhere"
);
$iTotal = DB::count();

$rows = DB::query(
    "SELECT * FROM ".$pre."users
    $sWhere
    $sLimit"
);
$iFilteredTotal = DB::count();

// output
if (DB::count() > 0) {
    $sOutput = '[';
} else {
    $sOutput = '';
}

foreach ($rows as $record) {

    // Get list of allowed functions
    $listAlloFcts = "";
    if ($record['admin'] != 1) {
        if (count($rolesList) > 0) {
            foreach ($rolesList as $fonction) {
                if (in_array($fonction['id'], explode(";", $record['fonction_id']))) {
                    $listAlloFcts .= '<i class="fa fa-angle-right"></i>&nbsp;'.addslashes(mysqli_real_escape_string($link, filter_var($fonction['title'], FILTER_SANITIZE_STRING))).'<br />';
                }
            }
            $listAlloFcts_position = true;
        }
        if (empty($listAlloFcts)) {
            $listAlloFcts = '<i class="fa fa-exclamation mi-red tip" title="'.@htmlspecialchars($LANG['user_alarm_no_function'], ENT_QUOTES, "UTF-8").'"></i>';
            $listAlloFcts_position = false;
        }
    }
    // Get list of allowed groups
    $listAlloGrps = "";
    if ($record['admin'] != 1) {
        if (count($treeDesc) > 0) {
            foreach ($treeDesc as $t) {
                if (@!in_array($t->id, $_SESSION['groupes_interdits']) && in_array($t->id, $_SESSION['groupes_visibles'])) {
                    $ident = "";
                    if (in_array($t->id, explode(";", $record['groupes_visibles']))) {
                        $listAlloGrps .= '<i class="fa fa-angle-right"></i>&nbsp;'.addslashes(mysqli_real_escape_string($link, filter_var($ident.$t->title, FILTER_SANITIZE_STRING))).'<br />';
                    }
                    $prev_level = $t->nlevel;
                }
            }
        }
    }
    // Get list of forbidden groups
    $listForbGrps = "";
    if ($record['admin'] != 1) {
        if (count($treeDesc) > 0) {
            foreach ($treeDesc as $t) {
                $ident = "";
                if (in_array($t->id, explode(";", $record['groupes_interdits']))) {
                    $listForbGrps .= '<i class="fa fa-angle-right"></i>&nbsp;'.addslashes(mysqli_real_escape_string($link, filter_var($ident.$t->title, FILTER_SANITIZE_STRING))).'<br />';
                }
                $prev_level = $t->nlevel;
            }
        }
    }

    //Show user only if can be administrated by the adapted Roles manager
    if (
        $_SESSION['is_admin'] ||
        ($record['isAdministratedByRole'] > 0 &&
        in_array($record['isAdministratedByRole'], $_SESSION['user_roles'])) ||
        ($_SESSION['user_can_manage_all_users'] && $record['admin'] != 1)
    ) {
        $showUserFolders = true;
    } else {
        $showUserFolders = false;
    }

    // Build list of available users
    if ($record['admin'] != 1 && $record['disabled'] != 1) {
        $listAvailableUsers .= '<option value="'.$record['id'].'">'.$record['login'].'</option>';
    }

    // Display Grid
    if ($showUserFolders == true) {
        $sOutput .= "[";

        //col1
        if ($record['disabled'] == 1) {
            $sOutput .= '"<span class=\"fa fa-user-times tip mi-red\" title=\"'.$LANG['account_is_locked'].'\"></span>&nbsp;';
        } else {
            $sOutput .= '"';
        }
        if ($record['id'] != API_USER_ID && $record['id'] != OTV_USER_ID)
            $sOutput .= '<span class=\"fa fa-external-link tip\" style=\"cursor:pointer;\" onclick=\"user_edit(\''.$record['id'].'\')\" title=\"'.$LANG['edit'].' ['.$record['id'].']'.'\"></span>';

        // pwd change
        $sOutput .= '&nbsp;<span class=\"fa fa-key tip\" style=\"cursor:pointer;\" onclick=\"mdp_user(\''.$record['id'].'\')\" title=\"'.addcslashes($LANG['change_password'], '"\\/').'\"></span>';

        // user logs
        $sOutput .= '&nbsp;<span class=\"fa fa-newspaper-o tip\" onclick=\"user_action_log_items(\''.$record['id'].'\')\" style=\"cursor:pointer;\" title=\"'.addcslashes($LANG['see_logs'], '"\\/').'\"></span>';

        // user flashcode sending
        if (empty($record['ga'])) {
            $sOutput .= '&nbsp;<span class=\"fa fa-qrcode mi-yellow tip\" style=\"cursor:pointer;\" onclick=\"user_action_ga_code(\''.$record['id'].'\')\" title=\"'.addcslashes($LANG['user_ga_code'], '"\\/').'\"></span>';
        } else {
            $sOutput .= '&nbsp;<span class=\"fa fa-qrcode mi-green tip\" style=\"cursor:pointer;\" onclick=\"user_action_ga_code(\''.$record['id'].'\')\" title=\"'.addcslashes($LANG['user_ga_code'], '"\\/').'\"></span>';
        }

        if ($record['admin'] !== "1")
            $sOutput .= '&nbsp;<span class=\"fa fa-sitemap tip\" style=\"cursor:pointer;\" onclick=\"user_folders_rights(\''.$record['id'].'\')\" title=\"'.$LANG['user_folders_rights'].' ['.$record['id'].']'.'\"></span>';

        $sOutput .= '",';

        //col2
        $sOutput .= '"<input=\"hidden\" id=\"user_login_'.$record['id'].'\" value=\"'.$record['login'].'\">'.$record['login'].'"';
        $sOutput .= ',';

        //col3
        $sOutput .= '"'.$record['name'].'"';
        $sOutput .= ',';

        //col4
        $sOutput .= '"'.$record['lastname'].'"';
        $sOutput .= ',';

        //col5 - MANAGED BY
        $txt = "";
        $rows2 = DB::query("SELECT title FROM ".$pre."roles_title"." WHERE id = '".$record['isAdministratedByRole']."' ORDER BY title ASC");
        if (DB::count() > 0) {
            foreach ($rows2 as $record2) {
                $txt .= '<i class=\"fa fa-angle-right\"></i>&nbsp;'.addslashes($LANG['managers_of']).' '.addslashes(mysqli_real_escape_string($link, filter_var($record2['title'], FILTER_SANITIZE_STRING))).'<br />';
            }
        } else {
            $txt = '<i class=\"fa fa-angle-right\"></i>&nbsp;'.addslashes($LANG['god']);
        }
        $sOutput .= '"'.$txt.'"';
        $sOutput .= ',';

        //col6
        $sOutput .= '"'.addslashes($listAlloFcts).'"';
        $sOutput .= ',';

        //col9
        if ($_SESSION['user_can_manage_all_users'] === "1" || $_SESSION['is_admin'] === "1") {
            if ($record['admin'] === "1") $sOutput .= '"<i class=\"fa fa-toggle-on mi-green\" style=\"cursor:pointer;\" tp=\"'.$record['id'].'-admin-0\"></i>"';
            else $sOutput .= '"<i class=\"fa fa-toggle-off\" style=\"cursor:pointer;\" tp=\"'.$record['id'].'-admin-1\"></i>"';
        } else {
            $sOutput .= '""';
        }
        $sOutput .= ',';

        //col10
        if ($record['gestionnaire'] === "1") $sOutput .= '"<i class=\"fa fa-toggle-on mi-green\" style=\"cursor:pointer;\"  tp=\"'.$record['id'].'-gestionnaire-0\"></i>"';
        else $sOutput .= '"<i class=\"fa fa-toggle-off\" style=\"cursor:pointer;\" tp=\"'.$record['id'].'-gestionnaire-1\"></i>"';
        $sOutput .= ',';

        //col11
        if ($record['read_only'] === "1") $sOutput .= '"<i class=\"fa fa-toggle-on mi-green\" style=\"cursor:pointer;\" tp=\"'.$record['id'].'-read_only-0\"></i>"';
        else $sOutput .= '"<i class=\"fa fa-toggle-off\" style=\"cursor:pointer;\" tp=\"'.$record['id'].'-read_only-1\"></i>"';
        $sOutput .= ',';

        //col11
        if ($_SESSION['is_admin'] === "1" || $_SESSION['user_can_manage_all_users'] == 1) {
            if ($record['can_manage_all_users'] === "1") {
                $sOutput .= '"<i class=\"fa fa-toggle-on mi-green\" style=\"cursor:pointer;\" tp=\"' . $record['id'] . '-can_manage_all_users-0\"></i>"';
            } else {
                $sOutput .= '"<i class=\"fa fa-toggle-off\" style=\"cursor:pointer;\" tp=\"' . $record['id'] . '-can_manage_all_users-1\"></i>"';
            }
        } else {
            $sOutput .= '""';
        }
        $sOutput .= ',';

        //col12
        if ($record['can_create_root_folder'] === "1") $sOutput .= '"<i class=\"fa fa-toggle-on mi-green\" style=\"cursor:pointer;\" tp=\"'.$record['id'].'-can_create_root_folder-0\"></i>"';
        else $sOutput .= '"<i class=\"fa fa-toggle-off\" style=\"cursor:pointer;\" tp=\"'.$record['id'].'-can_create_root_folder-1\"></i>"';
        $sOutput .= ',';

        //col13
        if ($record['personal_folder'] === "1") $sOutput .= '"<i class=\"fa fa-toggle-on mi-green\" style=\"cursor:pointer;\" tp=\"'.$record['id'].'-personal_folder-0\"></i>"';
        else $sOutput .= '"<i class=\"fa fa-toggle-off\" style=\"cursor:pointer;\" tp=\"'.$record['id'].'-personal_folder-1\"></i>"';


        //Finish the line
        $sOutput .= '],';

        $iFilteredTotal ++;
    }
}

if (count($rows) > 0) {
    if (strrchr($sOutput, "[") != '[') $sOutput = substr_replace($sOutput, "", -1);
    $sOutput .= '] }';
} else {
    $sOutput .= '[] }';
}

// prepare complete output

echo '{"recordsTotal": '.$iTotal.', "recordsFiltered": '.$iFilteredTotal.', "data": '.$sOutput;
