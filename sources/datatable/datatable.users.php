<?php
/**
 * @file          users.queries.table.php
 * @author        Nils Laumaillé
 * @version       2.1.24
 * @copyright     (c) 2009-2015 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('../sessions.php');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

global $k, $settings;
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
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
$aColumns = array('id', 'login', 'name', 'lastname', 'admin', 'read_only', 'gestionnaire', 'isAdministratedByRole', 'can_create_root_folder', 'personal_folder', 'email', 'ga', 'fonction_id');
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
    /*
	for ($i=0; $i<intval($_GET['order[0][column]']); $i++) {
		if (
			$_GET[ 'bSortable_'.filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)] == "true" &&
			preg_match("#^(asc|desc)\$#i", $_GET['sSortDir_'.$i])
		) {
			$sOrder .= "".$aColumns[ filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT) ]." "
			.mysqli_escape_string($link, $_GET['sSortDir_'.$i]) .", ";
		}
	}
    */

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
$criteria = "";
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


db::debugMode(false);
DB::query("SELECT * FROM ".$pre."users");
$iTotal = DB::count();

$rows = DB::query(
    "SELECT * FROM ".$pre."users
    $sWhere
    $sLimit",
    $criteria
);
$iFilteredTotal = DB::count();

/*
   * Output
*/
$sOutput = '{';
$sOutput .= '"recordsTotal": '.$iTotal.', ';
$sOutput .= '"recordsFiltered": '.$iFilteredTotal.', ';
$sOutput .= '"data": ';

if ($iFilteredTotal > 0) {
    $sOutput .= '[';
}

foreach ($rows as $record) {

    // Get list of allowed functions
    $listAlloFcts = "";
    if ($record['admin'] != 1) {
        if (count($rolesList) > 0) {
            foreach ($rolesList as $fonction) {
                if (in_array($fonction['id'], explode(";", $record['fonction_id']))) {
                    $listAlloFcts .= '<i class="fa fa-angle-right"></i>&nbsp;'.@htmlspecialchars($fonction['title'], ENT_COMPAT, "UTF-8").'<br />';
                }
            }
            $listAlloFcts_position = true;
        }
        if (empty($listAlloFcts)) {
            $listAlloFcts = '<i class="fa fa-exclamation fa-lg mi-red tip" title="'.$LANG['user_alarm_no_function'].'"></i>';
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
                        $listAlloGrps .= '<i class="fa fa-angle-right"></i>&nbsp;'.@htmlspecialchars($ident.$t->title, ENT_COMPAT, "UTF-8").'<br />';
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
                    $listForbGrps .= '<i class="fa fa-angle-right"></i>&nbsp;'.@htmlspecialchars($ident.$t->title, ENT_COMPAT, "UTF-8").'<br />';
                }
                $prev_level = $t->nlevel;
            }
        }
    }
    // is user locked?
    if ($record['disabled'] == 1) {
    }

    //Show user only if can be administrated by the adapted Roles manager
    if (
        $_SESSION['is_admin'] ||
        ($record['isAdministratedByRole'] > 0 &&
        in_array($record['isAdministratedByRole'], $_SESSION['user_roles']))
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
        
        // <-- prepare all possible conditions
        // If user is active, then you could lock it
        // If user is locked, you could delete it
        if ($record['disabled'] == 1) {
            $userTxt = $LANG['user_del'];
            $actionOnUser = '<i class="fa fa-user-times fa-lg tip" style="cursor:pointer;" onclick="action_on_user(\''.$record['id'].'\',\'delete\')" title="'.$userTxt.'">';
            $userIcon = "user--minus";
            //$row_style = ' style="background-color:#FF8080;font-size:11px;"';
        } else {
            $userTxt = $LANG['user_lock'];
            $actionOnUser = '<i class="fa fa-lock fa-lg tip" style="cursor:pointer;" onclick="action_on_user(\''.$record['id'].'\',\'lock\')" title="'.$userTxt.'">';
            $userIcon = "user-locked";
            //$row_style = ' class="ligne'.($x % 2).' data-row"';     
        }
        
        if ($_SESSION['user_admin'] == 1 || ($_SESSION['user_manager'] == 1 && $record['admin'] == 0 && $record['gestionnaire'] == 0) && $showUserFolders == true) 
            $td_class = 'class="editable_textarea"';
        else $td_class = '';
        
        if ($record['admin'] == 1) {
            $is_admin = ' style="display:none;"';
            $admin_checked = ' checked';
            $admin_disabled = ' disabled="disabled"';
        } else {
            $is_admin = '';
            $admin_checked = '';
            $admin_disabled = '';
        }
        
        if ($_SESSION['user_manager'] == 1) $is_manager = 'disabled="disabled"';
        else $is_manager = '';
        
        if ($record['gestionnaire'] == 1) $is_gest = 'checked';
        else $is_gest = '';
        
        if ($showUserFolders == false) {
            $show_folders = 'display:none;';
            $show_folders_disabled = 'disabled="disabled"';
            $user_action = 'src="includes/images/user--minus_disabled.png"';
            $pw_change = 'src="includes/images/lock__pencil_disabled.png"';
            $log_report = 'src="includes/images/report.png" onclick="user_action_log_items(\''.$record['id'].'\')" class="button" style="padding:2px; cursor:pointer;" title="'.$LANG['see_logs'].'"';
        } else {
            $show_folders = '';
            $show_folders_disabled = '';
            $user_action = '<i class="fa fa-user fa-lg tip" style="cursor:pointer;" onclick="'.$actionOnUser.'" title="'.$userTxt.'">';
            $pw_change = '<i class="fa fa-key fa-lg tip" style="cursor:pointer;" onclick="mdp_user(\''.$record['id'].'\')" title="'.$LANG['change_password'].'"></i>';
            $log_report = '<i class=\"fa fa-newspaper-o fa-lg tip\" onclick=\"user_action_log_items(\''.$record['id'].'\')\" style=\"cursor:pointer;\" title=\"'.$LANG['see_logs'].'\"></i>';
        }
                            
        if ($_SESSION['user_manager'] == 1 || $record['admin'] == 1) $tmp1 = 'disabled="disabled"';
        else $tmp1 = '';
        
        if ($record['read_only'] == 1) $is_ro = 'checked';
        else $is_ro = '';            

        if ($record['can_create_root_folder'] == 1) $can_create_root_folder = 'checked';
        else $can_create_root_folder = '';
        
        if ($record['personal_folder'] == 1) $personal_folder = 'checked';
        else $personal_folder = '';
        
        if (empty($record['email'])) $email_change = 'mail--exclamation.png';
        else $email_change = 'mail--pencil.png';
        
        if (empty($record['ga'])) $ga_code = 'phone_add';
        else $ga_code = 'phone_sound' ;
        
        //col1
        $sOutput .= '"<i class=\"fa fa-external-link fa-lg tip\" style=\"cursor:pointer;\" onclick=\"edit_user(\''.$record['id'].'\')\" title=\"'.$LANG['change_password'].'\"></i>&nbsp;'.$record['id'].'"';
        $sOutput .= ',';
        
        //col2
        $sOutput .= '"'.$record['login'].'"';
        $sOutput .= ',';
        
        //col3
        $sOutput .= '"'.$record['name'].'"';
        $sOutput .= ',';
        
        //col4
        $sOutput .= '"'.$record['lastname'].'"';
        $sOutput .= ',';
        
        //col5
        $sOutput .= '""';
        $sOutput .= ',';
        
        //col6
        $sOutput .= '"'.addslashes($listAlloFcts).'"';
        $sOutput .= ',';
        /*
        //col7
        $sOutput .= '"'.addslashes($listAlloGrps).'"';
        $sOutput .= ',';
        
        //col8
        $sOutput .= '"'.addslashes($listForbGrps).'"';
        $sOutput .= ',';
        */
        //col9
        if ($record['admin'] == 1) $sOutput .= '"<i class=\"fa fa-toggle-on fa-lg mi-green\"></i>"';
        else $sOutput .= '"<i class=\"fa fa-toggle-off fa-lg\"></i>"';
        $sOutput .= ',';
        
        //col10
        if ($record['gestionnaire'] == 1) $sOutput .= '"<i class=\"fa fa-toggle-on fa-lg mi-green\" style=\"cursor:pointer;\" onclick=\"ChangeUserParm(\''.$record['id'].'\',\'gestionnaire\', \'0\')\"></i>"';
        else $sOutput .= '"<i class=\"fa fa-toggle-off fa-lg\" style=\"cursor:pointer;\" onclick=\"ChangeUserParm(\''.$record['id'].'\',\'gestionnaire\', \'1\')\"></i>"';
        $sOutput .= ',';
        
        //col11
        if ($record['read_only'] == 1) $sOutput .= '"<i class=\"fa fa-toggle-on fa-lg mi-green\" style=\"cursor:pointer;\" onclick=\"ChangeUserParm(\''.$record['id'].'\',\'read_only\', \'0\')\"></i>"';
        else $sOutput .= '"<i class=\"fa fa-toggle-off fa-lg\" style=\"cursor:pointer;\" onclick=\"ChangeUserParm(\''.$record['id'].'\',\'read_only\', \'1\')\"></i>"';
        $sOutput .= ',';
        
        //col12
        if ($record['can_create_root_folder'] == 1) $sOutput .= '"<i class=\"fa fa-toggle-on fa-lg mi-green\" style=\"cursor:pointer;\" onclick=\"ChangeUserParm(\''.$record['id'].'\',\'can_create_root_folder\', \'0\')\"></i>"';
        else $sOutput .= '"<i class=\"fa fa-toggle-off fa-lg\" style=\"cursor:pointer;\" onclick=\"ChangeUserParm(\''.$record['id'].'\',\'can_create_root_folder\', \'1\')\"></i>"';
        $sOutput .= ',';
        
        //col13
        if ($record['personal_folder'] == 1) $sOutput .= '"<i class=\"fa fa-toggle-on fa-lg mi-green\" style=\"cursor:pointer;\" onclick=\"ChangeUserParm(\''.$record['id'].'\',\'personal_folder\', \'0\')\"></i>"';
        else $sOutput .= '"<i class=\"fa fa-toggle-off fa-lg\" style=\"cursor:pointer;\" onclick=\"ChangeUserParm(\''.$record['id'].'\',\'personal_folder\', \'1\')\"></i>"';
        $sOutput .= ',';
        
        //col14
        if ($record['admin'] == 1) $sOutput .= '"<i class=\"fa fa-user-times fa-lg tip\" style=\"cursor:pointer;\" onclick=\"action_on_user(\''.$record['id'].'\',\'delete\')\" title=\"'.$LANG['user_del'].'\">"';
        else $sOutput .= '"<i class=\"fa fa-toggle-off fa-lg\"></i>"';
        $sOutput .= ',';
        
        //col15
        if ($record['email'] == "") $sOutput .= '"<i class=\"fa fa-exclamation fa-lg mi-yellow tip\"></i>&nbsp;<i class=\"fa fa-envelope fa-lg mi-yellow tip\" style=\"cursor:pointer;\" onclick=\"mail_user(\''.$record['id'].'\',\''.addslashes($record['email']).'\')\" title=\"'.$LANG['email'].'\"></i>"';
        else $sOutput .= '"<i class=\"fa fa-check fa-lg mi-green\"></i>"';
        $sOutput .= ',';
        
        //col16
        $sOutput .= '"'.($log_report).'"';
        //$sOutput .= ',';
    }
    
    
    
    
    

    
/*
    //col2
    $ret_cat = DB::queryfirstrow("SELECT category FROM ".$pre."kb_categories WHERE id = %i", $record['category_id']);
    $sOutput .= '"'.htmlspecialchars(stripslashes($ret_cat['category']), ENT_QUOTES).'",';

    //col3
    $sOutput .= '"'.htmlspecialchars(stripslashes($record['label']), ENT_QUOTES).'",';

    //col4
    $ret_author = DB::queryfirstrow("SELECT login FROM ".$pre."users WHERE id = %i", $record['author_id']);
    $sOutput .= '"'.html_entity_decode($ret_author['login'], ENT_NOQUOTES).'"';
*/
    //Finish the line
    $sOutput .= '],';
}

if (count($rows) > 0) {
    $sOutput = substr_replace($sOutput, "", -1);
    $sOutput .= '] }';
} else {
    $sOutput .= '[] }';
}

echo $sOutput;
