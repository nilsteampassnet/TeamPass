<?php
/**
 * @file          views.queries.php
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
if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 ||
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) ||
    !isset($_SESSION['key']) || empty($_SESSION['key']))
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_views")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset=utf-8");
require_once 'main.functions.php';

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

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
$tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

//Constant used
$nbElements = 20;

// Construction de la requ?te en fonction du type de valeur
switch ($_POST['type']) {
    #CASE generating the log for passwords renewal
    case "log_generate":
        //Prepare the PDF file
        include $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Pdf/Tfpdf/tfpdf.class.php';
        $pdf=new TFPDF();

        //Add font for utf-8
        $pdf->AddFont('helvetica', '');
        $pdf->aliasNbPages();
        $pdf->addPage();
        $pdf->SetFont('helvetica', '', 16);
        $pdf->Cell(0, 10, $LANG['pdf_del_title'], 0, 1, 'C', false);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, $LANG['pdf_del_date'].date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], time()), 0, 1, 'C', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetFillColor(15, 86, 145);
        $pdf->cell(80, 6, $LANG['label'], 1, 0, "C", 1);
        $pdf->cell(75, 6, $LANG['group'], 1, 0, "C", 1);
        $pdf->cell(21, 6, $LANG['date'], 1, 0, "C", 1);
        $pdf->cell(15, 6, $LANG['author'], 1, 1, "C", 1);
        $pdf->SetFont('helvetica', '', 10);

        $rows = DB::query(
            "SELECT u.login as login, i.label as label, i.id_tree as id_tree
            FROM ".prefix_table("log_items")." as l
            INNER JOIN ".prefix_table("users")." as u ON (u.id=l.id_user)
            INNER JOIN ".prefix_table("items")." as i ON (i.id=l.id_item)
            WHERE l.action = %s AND l.raison = %s",
            "Modification",
            "Mot de passe changé"
        );
        foreach ($rows as $record) {
            if (date($_SESSION['settings']['date_format'], $record['date']) == $_POST['date']) {
                //get the tree grid
                $arbo = $tree->getPath($record['id_tree'], true);
                $arboTxt = "";
                foreach ($arbo as $elem) {
                    if (empty($arboTxt)) {
                        $arboTxt = $elem->title;
                    } else {
                        $arboTxt .= " > ".$elem->title;
                    }
                }
                $pdf->cell(80, 6, $record['label'], 1, 0, "L");
                $pdf->cell(75, 6, $arboTxt, 1, 0, "L");
                $pdf->cell(21, 6, $_POST['date'], 1, 0, "C");
                $pdf->cell(15, 6, $record['login'], 1, 1, "C");
            }
        }
        list($d, $m, $y) = explode('/', $_POST['date']);
        $nomFichier = "log_followup_passwords_".date("Y-m-d", mktime(0, 0, 0, $m, $d, $y)).".pdf";
        //send the file
        $pdf->Output($_SESSION['settings']['path_to_files_folder'].'/'.$nomFichier);

        echo '[{"text":"<a href=\''.$_SESSION['settings']['url_to_files_folder'].'/'.$nomFichier.'\' target=\'_blank\'>'.$LANG['pdf_download'].'</a>"}]';
        break;

    /**
     * CASE display a full listing with all items deleted
     */
    case "lister_suppression":
        //FOLDERS deleted
        $arrFolders = array();
        $texte = "<table cellpadding=3><tr><td><span class='fa fa-folder-open'></span>&nbsp;<u><b>".$LANG['group']."</b></u></td></tr>";
        $rows = DB::query(
            "SELECT valeur, intitule
            FROM ".prefix_table("misc")."
            WHERE type  = %s",
            "folder_deleted"
        );
        foreach ($rows as $record) {
            $tmp = explode(', ', $record['valeur']);
            $texte .= '<tr><td><input type=\'checkbox\' class=\'cb_deleted_folder\' value=\''.$record['intitule'].'\' id=\'folder_deleted_'.$record['intitule'].'\' />&nbsp;<b>'.
                $tmp[2].'</b></td><td><input type=\"hidden\" value=\"'.$record['valeur'].'\"></td></tr>';
            $arrFolders[substr($record['intitule'], 1)] = $tmp[2];
        }

        //ITEMS deleted
        $texte .= "<tr><td><span class='fa fa-key'></span>&nbsp;<u><b>".$LANG['email_altbody_1']."</b></u></td></tr>";
        $rows = DB::query(
            "SELECT u.login as login, i.id as id, i.label as label, i.id_tree as id_tree, l.date as date, n.title as folder_title
            FROM ".prefix_table("log_items")." as l
            INNER JOIN ".prefix_table("items")." as i ON (l.id_item=i.id)
            INNER JOIN ".prefix_table("users")." as u ON (l.id_user=u.id)
            INNER JOIN ".prefix_table("nested_tree")." as n ON (i.id_tree=n.id)
            WHERE i.inactif = %i
            AND l.action = %s",
            1,
            "at_delete"
        );
        $prev_id = "";
        foreach ($rows as $record) {
            if ($record['id'] !== $prev_id) {
                if (in_array($record['id_tree'], $arrFolders)) {
                    if (count($arrFolders[$record['id_tree']])>0) {
                        $thisFolder = '<td>'.$arrFolders[$record['id_tree']].'</td>';
                    } else {
                        $thisFolder = "";
                    }
                } else {
                    $thisFolder = "";
                }

                $texte .= '<tr><td><input type=\'checkbox\' class=\'cb_deleted_item\' value=\''.$record['id'].'\' id=\'item_deleted_'.$record['id'].'\' />&nbsp;<b>'.$record['label'].'</b></td><td width=\"100px\" align=\"center\"><span class=\"fa fa-calendar\"></span>&nbsp;'.date($_SESSION['settings']['date_format'], $record['date']).'</td><td width=\"70px\" align=\"center\"><span class=\"fa fa-user\"></span>&nbsp;'.$record['login'].'</td><td><span class=\"fa fa-folder-o\"></span>&nbsp;'.$record['folder_title'].'</td>'.$thisFolder.'</tr>';
            }
            $prev_id = $record['id'];
        }

        echo '[{"text":"'.$texte.'</table><div style=\'margin:15px 0px 0px 5px;\'><input type=\'checkbox\' id=\'item_deleted_select_all\' />&nbsp;&nbsp;<a class=\"button\" onclick=\"$(\'#tab2_action\').val(\'restoration\');OpenDialog(\'tab2_dialog\');\"><i class=\"fa fa-undo fa-lg\"></i>&nbsp;'.$LANG['restore'].'</a>&nbsp;&nbsp;<a class=\"button\" onclick=\"$(\'#tab2_action\').val(\'deletion\');OpenDialog(\'tab2_dialog\')\"><i class=\"fa fa-trash-o fa-lg\"></i>&nbsp;'.$LANG['delete'].'</a></div>"}]';
        break;

    /**
     * CASE admin want to restaure a list of deleted items
     */
    case "restore_deleted__items":
        //restore FOLDERS
        if (count($_POST['list_f'])>0) {
            foreach (explode(';', $_POST['list_f']) as $id) {
                $data = DB::queryfirstrow(
                    "SELECT valeur
                    FROM ".prefix_table("misc")."
                    WHERE type = 'folder_deleted'
                    AND intitule = %s",
                    $id
                );
                if ($data['valeur'] != 0) {
                    $folderData = explode(', ', $data['valeur']);
                    //insert deleted folder
                    DB::insert(
                        prefix_table("nested_tree"),
                        array(
                            'id' => $folderData[0],
                            'parent_id' => $folderData[1],
                            'title' => $folderData[2],
                            'nleft' => $folderData[3],
                            'nright' => $folderData[4],
                            'nlevel' => $folderData[5],
                            'bloquer_creation' => $folderData[6],
                            'bloquer_modification' => $folderData[7],
                            'personal_folder' => $folderData[8],
                            'renewal_period' => $folderData[9]
                       )
                    );
                    //delete log
                    DB::delete(prefix_table("misc"), "type = %s AND intitule = %s", "folder_deleted", $id);
                }
            }
        }
        //restore ITEMS
        if (count($_POST['list_i'])>0) {
            foreach (explode(';', $_POST['list_i']) as $id) {
                DB::update(
                    prefix_table("items"),
                    array(
                        'inactif' => '0'
                    ),
                    'id = %i',
                    $id
                );
                //log
                DB::insert(
                    prefix_table("log_items"),
                    array(
                        "id_item" => $id,
                        "date" => time(),
                        "id_user" => $_SESSION['user_id'],
                        "action" => "at_restored"
                    )
                );
            }
        }

        updateCacheTable("reload", "");
        
        break;

    /**
     * CASE admin want to delete a list of deleted items
     */
    case "really_delete_items":
        $folders = explode(';', $_POST['folders']);
        if (count($folders)>0) {
            //delete folders
            foreach ($folders as $fId) {
                //get folder ID
                $id = substr($fId, 1);

                //delete any subfolder
                $rows = DB::query(
                    "SELECT valeur FROM ".prefix_table("misc")." WHERE type=%s AND intitule = %s",
                    "folder_deleted",
                    $fId
                );
                foreach ($rows as $record) {
                    //get folder id
                    $val = explode(", ", $record['valeur']);
                    //delete items & logs
                    $items = DB::query("SELECT id FROM ".prefix_table("items")." WHERE id_tree=%i", $val[0]);
                    foreach ($items as $item) {
                        //Delete item
                        DB::delete(prefix_table("items"), "id = %i", $item['id']);
                        DB::delete(prefix_table("log_items"), "id_item = %i", $item['id']);

                        //Update CACHE table
                        DB::delete(prefix_table("cache"), "id = %i", $item['id']);
                    }
                    //Actualize the variable
                    $_SESSION['nb_folders'] --;
                }
                //delete folder
                DB::delete(prefix_table("misc"), "intitule = %s AND type = %s", $fId, "folder_deleted");
            }
        }

        foreach (explode(';', $_POST['items']) as $id) {
            //delete from ITEMS
            DB::delete(prefix_table("items"), "id=%i", $id);
            //delete from LOG_ITEMS
            DB::delete(prefix_table("log_items"), "id_item=%i", $id);
            //delete from FILES
            DB::delete(prefix_table("files"), "id_item=%i", $id);
            //delete from TAGS
            DB::delete(prefix_table("tags"), "item_id=%i", $id);
            //delete from KEYS
            //DB::delete(prefix_table("keys"), "`id` =%i AND `sql_table`=%s", $id, "items");
        }
        break;

    #----------------------------------
    #CASE admin want to see COONECTIONS logs
    case "connections_logs":
        $logs = "";
        $nbPages = 1;
        $pages = '<table style=\'border-top:1px solid #969696;\'><tr><td>'.$LANG['pages'].'&nbsp;:&nbsp;</td>';

        //get number of pages
        DB::query(
            "SELECT * FROM ".prefix_table("log_system")." as l INNER JOIN ".prefix_table("users")." as u ON (l.qui=u.id) WHERE l.type = %s",
            "user_connection"
        );
        if (DB::count() != 0) {
            $nbPages = ceil($data[0]/$nbElements);
            for ($i=1; $i<=$nbPages; $i++) {
                $pages .= '<td onclick=\'displayLogs(\"connections_logs\", '.
                $i.', \"'.$_POST['order'].'\")\'><span style=\'cursor:pointer;'.
                ($_POST['page'] == $i ? 'font-weight:bold;font-size:18px;\'>'.$i:
                '\'>'.$i).'</span></td>';
            }
        }
        $pages .= '</tr></table>';

        //define query limits
        if (isset($_POST['page']) && $_POST['page'] > 1) {
            $start = ($nbElements*($_POST['page']-1)) + 1;
        } else {
            $start = 0;
        }

        //launch query
        $rows = DB::query(
            "SELECT l.date as date, l.label as label, l.qui as who, u.login as login
            FROM ".prefix_table("log_system")." as l
            INNER JOIN ".prefix_table("users")." as u ON (l.qui=u.id)
            WHERE l.type = %s
            ORDER BY %s %s
			LIMIT ".mysqli_real_escape_string($link, filter_var($start, FILTER_SANITIZE_NUMBER_INT)) .", ". mysqli_real_escape_string($link, filter_var($nbElements, FILTER_SANITIZE_NUMBER_INT)),
            "user_connection",
			$_POST['order'],
			$POST['direction']
        );

        foreach ($rows as $record) {
            $logs .= '<tr><td>'.date(
                $_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date']
            ).'</td><td align=\"center\">'.$LANG[$record['label']].'</td><td align=\"center\">'.
            $record['login'].'</td></tr>';
        }

        echo '[{"tbody_logs": "'.$logs.'" , "log_pages" : "'.$pages.'"}]';
        break;

    /**
     * CASE admin want to see CONNECTIONS logs
     */
    case "errors_logs":
        $logs = "";
        $nbPages = 1;
        $pages = '<table style=\'border-top:1px solid #969696;\'><tr><td>'.$LANG['pages'].'&nbsp;:&nbsp;</td>';

        //get number of pages
        DB::query(
            "SELECT *
            FROM ".prefix_table("log_system")." as l
            INNER JOIN ".prefix_table("users")." as u ON (l.qui=u.id)
            WHERE l.type = %s",
            "error"
        );
        if (DB::count() != 0) {
            $nbPages = ceil($data[0]/$nbElements);
            for ($i=1; $i<=$nbPages; $i++) {
                $pages .= '<td onclick=\'displayLogs(\"errors_logs\", '.$i.', \"'.$_POST['order'].
                '\")\'><span style=\'cursor:pointer;'.($_POST['page'] == $i ?
                'font-weight:bold;font-size:18px;\'>'.$i:'\'>'.$i).'</span></td>';
            }
        }
        $pages .= '</tr></table>';

        //define query limits
        if (isset($_POST['page']) && $_POST['page'] > 1) {
            $start = ($nbElements*($_POST['page']-1)) + 1;
        } else {
            $start = 0;
        }

        //launch query
        $rows = DB::query(
            "SELECT l.date as date, l.label as label, l.qui as who, u.login as login
            FROM ".prefix_table("log_system")." as l
            INNER JOIN ".prefix_table("users")." as u ON (l.qui=u.id)
            WHERE l.type = %s
            ORDER BY %s %s
            LIMIT ".mysqli_real_escape_string($link, filter_var($start, FILTER_SANITIZE_NUMBER_INT)) .", ". mysqli_real_escape_string($link, filter_var($nbElements, FILTER_SANITIZE_NUMBER_INT)),
            "error",
			$_POST['order'],
			$_POST['direction']
        );
        foreach ($rows as $record) {
            $label = explode('@', addslashes(cleanString($record['label'])));
            $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date']).'</td><td align=\"center\">'.@$label[1].'</td><td align=\"left\">'.$label[0].'</td><td align=\"center\">'.$record['login'].'</td></tr>';
        }

        echo '[{"tbody_logs": "'.$logs.'" , "log_pages" : "'.$pages.'"}]';
        break;

    /**
     * CASE admin want to see CONNECTIONS logs
     */
    case "access_logs":
        $logs = $sqlFilter = "";
        $nbPages = 1;
        $pages = '<table style=\'border-top:1px solid #969696;\'><tr><td>'.$LANG['pages'].'&nbsp;:&nbsp;</td>';

        $where = new WhereClause('and');
        $where->add('l.action=%s', "at_shown");

        if (isset($_POST['filter']) && !empty($_POST['filter'])) {
            $where->add('i.label=%ss', $_POST['filter']);
        }
        if (isset($_POST['filter_user']) && !empty($_POST['filter_user'])) {
            $where->add('i.id_user=%ss', $_POST['filter_user']);
        }

        //get number of pages
        $data = DB::query(
            "SELECT * FROM ".prefix_table("log_items")." as l INNER JOIN ".prefix_table("items")." as i ON (l.id_item=i.id)
            INNER JOIN ".prefix_table("users")." as u ON (l.id_user=u.id)
            WHERE %l",
            $where
        );
        if ($data[0] != 0) {
            $nbPages = ceil($data[0]/$nbElements);
            for ($i=1; $i<=$nbPages; $i++) {
                $pages .= '<td onclick=\'displayLogs(\"access_logs\", '.$i.', \"'.$_POST['order'].'\")\'><span style=\'cursor:pointer;'.($_POST['page'] == $i ? 'font-weight:bold;font-size:18px;\'>'.$i:'\'>'.$i).'</span></td>';
            }
        }
        $pages .= '</tr></table>';

        //define query limits
        if (isset($_POST['page']) && $_POST['page'] > 1) {
            $start = ($nbElements*($_POST['page']-1)) + 1;
        } else {
            $start = 0;
        }

        //launch query
        $rows = DB::query(
            "SELECT l.date as date, u.login as login, i.label as label
            FROM ".prefix_table("log_items")." as l
            INNER JOIN ".prefix_table("items")." as i ON (l.id_item=i.id)
            INNER JOIN ".prefix_table("users")." as u ON (l.id_user=u.id)
            WHERE %l
            ORDER BY ".$_POST['order']." ".$_POST['direction']."
            LIMIT ".mysqli_real_escape_string($link, filter_var($start, FILTER_SANITIZE_NUMBER_INT)) .", ". mysqli_real_escape_string($link, filter_var($nbElements, FILTER_SANITIZE_NUMBER_INT)),
            $where
        );
        foreach ($rows as $record) {
            $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date']).'</td><td align=\"left\">'.str_replace('"', '\"', $record['label']).'</td><td align=\"center\">'.$record['login'].'</td></tr>';
        }

        echo '[{"tbody_logs": "'.$logs.'" , "log_pages" : "'.$pages.'"}]';
        break;

    /**
     * CASE admin want to see COPIES logs
     */
    case "copy_logs":
        $logs = $sqlFilter = "";
        $nbPages = 1;
        $pages = '<table style=\'border-top:1px solid #969696;\'><tr><td>'.$LANG['pages'].'&nbsp;:&nbsp;</td>';

        $where = new WhereClause('and');
        $where->add('l.action=%s', "at_copy");

        if (isset($_POST['filter']) && !empty($_POST['filter'])) {
            $where->add('i.label=%ss', $_POST['filter']);
        }
        if (isset($_POST['filter_user']) && !empty($_POST['filter_user'])) {
            $where->add('i.id_user=%ss', $_POST['filter_user']);
        }

        //get number of pages
        $data = DB::query(
            "SELECT * FROM ".prefix_table("log_items")." as l
            INNER JOIN ".prefix_table("items")." as i ON (l.id_item=i.id)
            INNER JOIN ".prefix_table("users")." as u ON (l.id_user=u.id)
            WHERE %l",
            $where
        );
        if ($data[0] != 0) {
            $nbPages = ceil($data[0]/$nbElements);
            for ($i=1; $i<=$nbPages; $i++) {
                $pages .= '<td onclick=\'displayLogs(\"copy_logs\", '.$i.', \'\')\'><span style=\'cursor:pointer;'.($_POST['page'] == $i ? 'font-weight:bold;font-size:18px;\'>'.$i:'\'>'.$i).'</span></td>';
            }
        }
        $pages .= '</tr></table>';

        //define query limits
        if (isset($_POST['page']) && $_POST['page'] > 1) {
            $start = ($nbElements*($_POST['page']-1)) + 1;
        } else {
            $start = 0;
        }

        //launch query
        $rows = DB::query(
            "SELECT l.date as date, u.login as login, i.label as label
            FROM ".prefix_table("log_items")." as l
            INNER JOIN ".prefix_table("items")." as i ON (l.id_item=i.id)
            INNER JOIN ".prefix_table("users")." as u ON (l.id_user=u.id)
            WHERE %l
            ORDER BY date DESC
            LIMIT ".mysqli_real_escape_string($link, filter_var($start, FILTER_SANITIZE_NUMBER_INT)) .", ". mysqli_real_escape_string($link, filter_var($nbElements, FILTER_SANITIZE_NUMBER_INT)),
            $where
        );
        foreach ($rows as $record) {
            $label = explode('@', addslashes(cleanString($record['label'])));
            $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date']).'</td><td align=\"left\">'.$label[0].'</td><td align=\"center\">'.$record['login'].'</td></tr>';
        }

        echo '[{"tbody_logs": "'.$logs.'" , "log_pages" : "'.$pages.'"}]';
        break;

    /**
     * CASE admin want to see ITEMS logs
     */
    case "items_logs":
        $logs = $sqlFilter = "";
        $nbPages = 1;
        $pages = '<table style=\'border-top:1px solid #969696;\'><tr><td>'.$LANG['pages'].'&nbsp;:&nbsp;</td>';

        $where = new WhereClause('and');
        $where->add('i.label=%s', $_POST['filter']);

        if (isset($_POST['filter']) && !empty($_POST['filter'])) {
            $where->add('i.label=%ss', $_POST['filter']);
        }
        if (isset($_POST['filter_user']) && !empty($_POST['filter_user'])) {
            $where->add('i.id_user=%ss', $_POST['filter_user']);
        }

        //get number of pages
        DB::query(
            "SELECT * FROM ".prefix_table("log_items")." as l
            INNER JOIN ".prefix_table("items")." as i ON (l.id_item=i.id)
            INNER JOIN ".prefix_table("users")." as u ON (l.id_user=u.id)
            WHERE %l",
            $where
        );
        if (DB::count() != 0) {
            $nbPages = ceil($data[0]/$nbElements);
            for ($i=1; $i<=$nbPages; $i++) {
                $pages .= '<td onclick=\'displayLogs(\"items_logs\", '.$i.', \"\")\'><span style=\'cursor:pointer;'.($_POST['page'] == $i ? 'font-weight:bold;font-size:18px;\'>'.$i:'\'>'.$i).'</span></td>';
            }
        }
        $pages .= '</tr></table>';

        //define query limits
        if (isset($_POST['page']) && $_POST['page'] > 1) {
            $start = ($nbElements*($_POST['page']-1)) + 1;
        } else {
            $start = 0;
        }

        //launch query
        $rows = DB::query(
            "SELECT l.date as date, u.login as login, i.label as label,
            i.perso as perso
            FROM ".prefix_table("log_items")." as l
            INNER JOIN ".prefix_table("items")." as i ON (l.id_item=i.id)
            INNER JOIN ".prefix_table("users")." as u ON (l.id_user=u.id)
            WHERE %l
            ORDER BY date DESC
            LIMIT ".mysqli_real_escape_string($link, filter_var($start, FILTER_SANITIZE_NUMBER_INT)) .", ". mysqli_real_escape_string($link, filter_var($nbElements, FILTER_SANITIZE_NUMBER_INT)),
            $where
        );

        foreach ($rows as $record) {
            if ($record['perso'] == 1) {
                $label[0] = "** ".$LANG['at_personnel']." **";
            } else {
                $label = explode('@', addslashes(cleanString($record['label'])));
            }
            $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date']).'</td><td align=\"left\">'.$label[0].'</td><td align=\"center\">'.$record['login'].'</td></tr>';
        }

        echo '[{"tbody_logs": "'.$logs.'" , "log_pages" : "'.$pages.'"}]';
        break;

    /**
     * CASE admin want to see COPIES logs
     */
    case "admin_logs":
        $logs = $sqlFilter = "";
        $nbPages = 1;
        $pages = '<table style=\'border-top:1px solid #969696;\'><tr><td>'.$LANG['pages'].'&nbsp;:&nbsp;</td>';

        $where = new WhereClause('and');
        $where->add('l.type=%s', "admin_action");

        if (isset($_POST['filter']) && !empty($_POST['filter'])) {
            $where->add('i.label=%ss', $_POST['filter']);
        }
        if (isset($_POST['filter_user']) && !empty($_POST['filter_user'])) {
            $where->add('i.id_user=%ss', $_POST['filter_user']);
        }

        //get number of pages
        DB::query(
            "SELECT * FROM ".prefix_table("log_system")." as l
            INNER JOIN ".prefix_table("users")." as u ON (l.qui=u.id)
            WHERE %l",
            $where
        );
        if ($data[0] != 0) {
            $nbPages = ceil($data[0]/$nbElements);
            for ($i=1; $i<=$nbPages; $i++) {
                $pages .= '<td onclick=\'displayLogs(\"copy_logs\", '.$i.', \'\')\'><span style=\'cursor:pointer;'.($_POST['page'] == $i ? 'font-weight:bold;font-size:18px;\'>'.$i:'\'>'.$i).'</span></td>';
            }
        }
        $pages .= '</tr></table>';

        //define query limits
        if (isset($_POST['page']) && $_POST['page'] > 1) {
            $start = ($nbElements*($_POST['page']-1)) + 1;
        } else {
            $start = 0;
        }

        //launch query
        $rows = DB::query(
            "SELECT l.date as date, u.login as login, l.label as label
            FROM ".prefix_table("log_system")." as l
            INNER JOIN ".prefix_table("users")." as u ON (l.qui=u.id)
            WHERE %l
            ORDER BY date DESC
            LIMIT ".mysqli_real_escape_string($link, filter_var($start, FILTER_SANITIZE_NUMBER_INT)) .", ". mysqli_real_escape_string($link, filter_var($nbElements, FILTER_SANITIZE_NUMBER_INT)),
            $where
        );

        foreach ($rows as $record) {
            $label = explode('@', addslashes(cleanString($record['label'])));
            $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date']).'</td><td align=\"left\">'.$label[0].'</td><td align=\"center\">'.$record['login'].'</td></tr>';
        }

        echo '[{"tbody_logs": "'.$logs.'" , "log_pages" : "'.$pages.'"}]';
        break;

    /**
     * CASE display a full listing with items EXPRIED
     */
    case "generate_renewal_listing":
        if ($_POST['period'] == "0") {
            $date = (time());
        } elseif ($_POST['period'] == "1month") {
            $date = (mktime(date('h'), date('i'), date('s'), date('m')+1, date('d'), date('y')));
        } elseif ($_POST['period'] == "6months") {
            $date = (mktime(date('h'), date('i'), date('s'), date('m')+6, date('d'), date('y')));
        } elseif ($_POST['period'] == "1year") {
            $date = (mktime(date('h'), date('i'), date('s'), date('m'), date('d'), date('y')+1));
        }
        $idItem = "";
        $texte = "<table cellpadding=3><thead><tr><th>".$LANG['label']."</th><th>".$LANG['creation_date']."</th><th>".$LANG['expiration_date']."</th><th>".$LANG['group']."</th><th>".$LANG['auteur']."</th></tr></thead>";
        $textPdf = "";
        $rows = DB::query(
            "SELECT u.login as login,
            i.id as id, i.label as label, i.id_tree as id_tree,
            l.date as date, l.id_item as id_item, l.action as action, l.raison as raison,
            n.renewal_period as renewal_period, n.title as title
            FROM ".prefix_table("log_items")." as l
            INNER JOIN ".prefix_table("items")." as i ON (l.id_item=i.id)
            INNER JOIN ".prefix_table("users")." as u ON (l.id_user=u.id)
            INNER JOIN ".prefix_table("nested_tree")." as n ON (n.id=i.id_tree)
            WHERE i.inactif = %s
            AND (l.action = %s OR (l.action = %s AND l.raison LIKE %ss))
            AND n.renewal_period != %s
            ORDER BY i.label ASC, l.date DESC",
            0,
            "at_creation",
            "at_modification",
            "at_pw :",
            0
        );
        $idManaged = '';
        foreach ($rows as $record) {
            if (empty($idManaged) || $idManaged != $record['id']) {
                //manage the date limit
                $itemDate = $record['date'] + ($record['renewal_period'] * $k['one_month_seconds']);

                if ($itemDate <= $date) {
                    //Save data found
                    $texte .= '<tr><td width=\"250px\"><span class=\"ui-icon ui-icon-link\" style=\"float: left; margin-right: .3em; cursor:pointer;\" onclick=\"window.location.href = \'index.php?page=items&amp;group='.$record['id_tree'].'&amp;id='.$record['id'].'\'\">&nbsp;</span>'.$record['label'].'</td><td width=\"100px\" align=\"center\">'.date($_SESSION['settings']['date_format'], $record['date']).'</td><td width=\"100px\" align=\"center\">'.date($_SESSION['settings']['date_format'], $itemDate).'</td><td width=\"150px\" align=\"center\">'.$record['title'].'</td><td width=\"100px\" align=\"center\">'.$record['login'].'</td></tr>';

                    //save data for PDF
                    if (empty($textPdf)) {
                        $textPdf = $record['label'].'@;@'.date($_SESSION['settings']['date_format'], $record['date']).'@;@'.date($_SESSION['settings']['date_format'], $itemDate).'@;@'.$record['title'].'@;@'.$record['login'];
                    } else {
                        $textPdf .= '@|@'.$record['label'].'@;@'.date($_SESSION['settings']['date_format'], $record['date']).'@;@'.date($_SESSION['settings']['date_format'], $itemDate).'@;@'.$record['title'].'@;@'.$record['login'];
                    }
                }
            }
            $idManaged = $record['id'];
        }

        echo '[{"text" : "'.$texte.'</table>" , "pdf" : "'.$textPdf.'"}]';
        break;

    /**
     * CASE generating the pdf of items to rennew
     */
    case "generate_renewal_pdf":
        //Prepare the PDF file
        include $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Pdf/Tfpdf/tfpdf.class.php';
        $pdf=new tFPDF();

        //Add font for utf-8
        $pdf->AddFont('helvetica', '');

        $pdf->aliasNbPages();
        $pdf->addPage();
        $pdf->SetFont('helvetica', '', 16);
        $pdf->Cell(0, 10, $LANG['renewal_needed_pdf_title'], 0, 1, 'C', false);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, $LANG['pdf_del_date'].date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], time()), 0, 1, 'C', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetFillColor(192, 192, 192);
        $pdf->cell(70, 6, $LANG['label'], 1, 0, "C", 1);
        $pdf->cell(25, 6, $LANG['creation_date'], 1, 0, "C", 1);
        $pdf->cell(25, 6, $LANG['expiration_date'], 1, 0, "C", 1);
        $pdf->cell(45, 6, $LANG['group'], 1, 0, "C", 1);
        $pdf->cell(25, 6, $LANG['author'], 1, 1, "C", 1);
        $pdf->SetFont('helvetica', '', 9);

        foreach (explode('@|@', addslashes($_POST['text'])) as $line) {
            $elem = explode('@;@', $line);
            if (!empty($elem[0])) {
                $pdf->cell(70, 6, $elem[0], 1, 0, "L");
                $pdf->cell(25, 6, $elem[1], 1, 0, "C");
                $pdf->cell(25, 6, $elem[2], 1, 0, "C");
                $pdf->cell(45, 6, $elem[3], 1, 0, "C");
                $pdf->cell(25, 6, $elem[4], 1, 1, "C");
            }
        }

        $pdfFile = "renewal_pdf_".date("Y-m-d", mktime(0, 0, 0, date('m'), date('d'), date('y'))).".pdf";
        //send the file
        $pdf->Output($_SESSION['settings']['path_to_files_folder']."/".$pdfFile);

        echo '[{"file" : "'.$_SESSION['settings']['url_to_files_folder'].'/'.$pdfFile.'"}]';
        break;

    /**
     * CASE purging logs
     */
    case "purgeLogs":
        if (!empty($_POST['purgeFrom']) && !empty($_POST['purgeTo']) && !empty($_POST['logType'])
            && isset($_SESSION['user_admin']) && $_SESSION['user_admin'] == 1) {
            if ($_POST['logType'] == "items_logs") {
                DB::query(
                    "SELECT * FROM ".prefix_table("log_items")." WHERE action=%s ".
                    "AND date BETWEEN %i AND %i",
                    "at_shown",
                    intval(strtotime($_POST['purgeFrom'])),
                    intval(strtotime($_POST['purgeTo']))
                );
                $counter = DB::count();
                    // Delete
                 DB::delete(prefix_table("log_items"), "action=%s AND date BETWEEN %i AND %i",
                    "at_shown",
                    intval(strtotime($_POST['purgeFrom'])),
                    intval(strtotime($_POST['purgeTo']))
                 );
            } elseif ($_POST['logType'] == "connections_logs") {
                DB::query(
                    "SELECT * FROM ".prefix_table("log_system")." WHERE type=%s ".
                    "AND date BETWEEN %i AND %i",
                    "user_connection",
                    intval(strtotime($_POST['purgeFrom'])),
                    intval(strtotime($_POST['purgeTo']))
                );
                $counter = DB::count();
                // Delete
                DB::delete(prefix_table("log_system"), "type=%s AND date BETWEEN %i AND %i",
                    "user_connection",
                    intval(strtotime($_POST['purgeFrom'])),
                    intval(strtotime($_POST['purgeTo']))
                );
            } elseif ($_POST['logType'] == "errors_logs") {
                DB::query(
                    "SELECT * FROM ".prefix_table("log_system")." WHERE type=%s ".
                    "AND date BETWEEN %i AND %i",
                    "error",
                    intval(strtotime($_POST['purgeFrom'])),
                    intval(strtotime($_POST['purgeTo']))
                );
                $counter = DB::count();
                // Delete
                DB::delete(prefix_table("log_system"), "type=%s AND date BETWEEN %i AND %i",
                    "error",
                    intval(strtotime($_POST['purgeFrom'])),
                    intval(strtotime($_POST['purgeTo']))
                );
            } elseif ($_POST['logType'] == "copy_logs") {
                DB::query(
                    "SELECT * FROM ".prefix_table("log_items")." WHERE action=%s ".
                    "AND date BETWEEN %i AND %i",
                    "at_copy",
                    intval(strtotime($_POST['purgeFrom'])),
                    intval(strtotime($_POST['purgeTo']))
                );
                $counter = DB::count();
                // Delete
                DB::delete(prefix_table("log_items"), "action=%s AND date BETWEEN %i AND %i",
                    "at_copy",
                    intval(strtotime($_POST['purgeFrom'])),
                    intval(strtotime($_POST['purgeTo']))
                );
            } elseif ($_POST['logType'] == "admin_logs") {
                DB::query(
                    "SELECT * FROM ".prefix_table("log_system")." WHERE type=%s ".
                    "AND date BETWEEN %i AND %i",
                    "admin_action",
                    intval(strtotime($_POST['purgeFrom'])),
                    intval(strtotime($_POST['purgeTo']))
                );
                $counter = DB::count();
                // Delete
                DB::delete(prefix_table("log_system"), "type=%s AND date BETWEEN %i AND %i",
                    "admin_action",
                    intval(strtotime($_POST['purgeFrom'])),
                    intval(strtotime($_POST['purgeTo']))
                );
            } elseif ($_POST['logType'] == "failed_auth_logs") {
                DB::query(
                    "SELECT * FROM ".prefix_table("log_system")." WHERE type=%s ".
                    "AND date BETWEEN %i AND %i",
                    "failed_auth",
                    intval(strtotime($_POST['purgeFrom'])),
                    intval(strtotime($_POST['purgeTo']))
                );
                $counter = DB::count();
                // Delete
                DB::delete(prefix_table("log_system"), "type=%s AND date BETWEEN %i AND %i",
                    "failed_auth",
                    intval(strtotime($_POST['purgeFrom'])),
                    intval(strtotime($_POST['purgeTo']))
                );
            } else {
                $counter = 0;
            }

            echo '[{"status" : "ok", "nb":"'.$counter.'"}]';
        } else {
            echo '[{"status" : "nok"}]';
        }
        break;
}
