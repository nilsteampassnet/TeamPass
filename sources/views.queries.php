<?php
/**
 * @file          views.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.20
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
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_views")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include 'error.php';
    exit();
}

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/include.php';
header("Content-type: text/html; charset=utf-8");
include 'main.functions.php';

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

//Connect to DB
require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$error_handler = 'db_error_handler';

//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

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
        $pdf->AddFont('DejaVu', '', 'DejaVuSansCondensed.ttf', true);
        $pdf->aliasNbPages();
        $pdf->addPage();
        $pdf->SetFont('DejaVu', '', 16);
        $pdf->Cell(0, 10, $LANG['pdf_del_title'], 0, 1, 'C', false);
        $pdf->SetFont('DejaVu', '', 12);
        $pdf->Cell(0, 10, $LANG['pdf_del_date'].date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], time()), 0, 1, 'C', false);
        $pdf->SetFont('DejaVu', '', 10);
        $pdf->SetFillColor(15, 86, 145);
        $pdf->cell(80, 6, $LANG['label'], 1, 0, "C", 1);
        $pdf->cell(75, 6, $LANG['group'], 1, 0, "C", 1);
        $pdf->cell(21, 6, $LANG['date'], 1, 0, "C", 1);
        $pdf->cell(15, 6, $LANG['author'], 1, 1, "C", 1);
        $pdf->SetFont('DejaVu', '', 10);

        $rows = DB::query(
            "SELECT u.login as login, i.label as label, i.id_tree as id_tree
            FROM ".$pre."log_items as l
            INNER JOIN ".$pre."users as u ON (u.id=l.id_user)
            INNER JOIN ".$pre."items as i ON (i.id=l.id_item)
            WHERE l.action = %s AND l.raison = %s",
            "Modification",
            "Mot de passe changé"
        );
        foreach ($rows as $reccord) {
            if (date($_SESSION['settings']['date_format'], $reccord['date']) == $_POST['date']) {
                //get the tree grid
                $arbo = $tree->getPath($reccord['id_tree'], true);
                $arboTxt = "";
                foreach ($arbo as $elem) {
                    if (empty($arboTxt)) {
                        $arboTxt = $elem->title;
                    } else {
                        $arboTxt .= " > ".$elem->title;
                    }
                }
                $pdf->cell(80, 6, $reccord['label'], 1, 0, "L");
                $pdf->cell(75, 6, $arboTxt, 1, 0, "L");
                $pdf->cell(21, 6, $_POST['date'], 1, 0, "C");
                $pdf->cell(15, 6, $reccord['login'], 1, 1, "C");
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
        $texte = "<table cellpadding=3><tr><td><u><b>".$LANG['group']."</b></u></td></tr>";
        $rows = DB::query(
            "SELECT valeur, intitule
            FROM ".$pre."misc
            WHERE type  = %s",
            "folder_deleted"
        );
        foreach ($rows as $reccord) {
            $tmp = explode(', ', $reccord['valeur']);
            $texte .= '<tr><td><input type=\'checkbox\' class=\'cb_deleted_folder\' value=\''.$reccord['intitule'].'\' id=\'folder_deleted_'.$reccord['intitule'].'\' />&nbsp;<b>'.
                $tmp[2].'</b></td><td><input type=\"hidden\" value=\"'.$reccord['valeur'].'\"></td></tr>';
            $arrFolders[substr($reccord['intitule'], 1)] = $tmp[2];
        }

        //ITEMS deleted
        $texte .= "<tr><td><u><b>".$LANG['email_altbody_1']."</b></u></td></tr>";
        $rows = DB::query(
            "SELECT u.login as login, i.id as id, i.label as label, i.id_tree as id_tree, l.date as date
            FROM ".$pre."log_items as l
            INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
            WHERE i.inactif = %i
            AND l.action = %s
            GROUP BY l.id_item",
            1,
            "at_delete"
        );
        foreach ($rows as $reccord) {
            if (in_array($reccord['id_tree'], $arrFolders)) {
                if (count($arrFolders[$reccord['id_tree']])>0) {
                    $thisFolder = '<td>'.$arrFolders[$reccord['id_tree']].'</td>';
                } else {
                    $thisFolder = "";
                }
            } else {
                $thisFolder = "";
            }

            $texte .= '<tr><td><input type=\'checkbox\' class=\'cb_deleted_item\' value=\''.$reccord['id'].'\' id=\'item_deleted_'.$reccord['id'].'\' />&nbsp;<b>'.$reccord['label'].'</b></td><td width=\"100px\" align=\"center\">'.date($_SESSION['settings']['date_format'], $reccord['date']).'</td><td width=\"70px\" align=\"center\">'.$reccord['login'].'</td>'.$thisFolder.'</tr>';
        }

        echo '[{"text":"'.$texte.'</table><div style=\'margin-left:5px;\'><input type=\'checkbox\' id=\'item_deleted_select_all\' />&nbsp;<img src=\"includes/images/arrow-repeat.png\" title=\"'.$LANG['restore'].'\" style=\"cursor:pointer;\" onclick=\"restoreDeletedItems()\">&nbsp;<img src=\"includes/images/bin_empty.png\" title=\"'.$LANG['delete'].'\" style=\"cursor:pointer;\" onclick=\"reallyDeleteItems()\"></div>"}]';
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
                    FROM ".$pre."misc
                    WHERE type = 'folder_deleted'
                    AND intitule = %s",
                    $id
                );
                if ($data['valeur'] != 0) {
                    $folderData = explode(', ', $data['valeur']);
                    //insert deleted folder
                    DB::insert(
                        $pre.'nested_tree',
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
                    DB::delete($pre."misc", "type = %s AND intitule = %s", "folder_deleted", $id);
                }
            }
        }
        //restore ITEMS
        if (count($_POST['list_i'])>0) {
            foreach (explode(';', $_POST['list_i']) as $id) {
                DB::update(
                    $pre."items",
                    array(
                        'inactif' => '0'
                    ),
                    'id = %i',
                    $id
                );
                //log
                DB::insert(
                    $pre."log_items",
                    array(
                        "id_item" => $id,
                        "date" => time(),
                        "id_user" => $_SESSION['user_id'],
                        "action" => "at_restored"
                    )
                );
            }
        }
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
                    "SELECT valeur FROM ".$pre."misc WHERE type=%s AND intitule = %s",
                    folder_deleted,
                    $fId
                );
                foreach ($rows as $reccord) {
                    //get folder id
                    $val = explode(", ", $reccord['valeur']);
                    //delete items & logs
                    $items = DB::query("SELECT id FROM ".$pre."items WHERE id_tree=%i", $val[0]);
                    foreach ($items as $item) {
                        //Delete item
                        DB::delete($pre."items", "id = %i", $item['id']);
                        DB::delete($pre."log_items", "id_item = %i", $item['id']);

                        //Update CACHE table
                        DB::delete($pre."cache", "id = %i", $item['id']);
                    }
                    //Actualize the variable
                    $_SESSION['nb_folders'] --;
                }
                //delete folder
                DB::delete($pre."misc", "intitule = %s AND type = %s", $fId, "folder_deleted");
            }
        }

        foreach (explode(';', $_POST['items']) as $id) {
            //delete from ITEMS
            DB::delete($pre."items", "id=%i", $id);
            //delete from LOG_ITEMS
            DB::delete($pre."log_items", "id_item=%i", $id);
            //delete from FILES
            DB::delete($pre."files", "id_item=%i", $id);
            //delete from TAGS
            DB::delete($pre."tags", "item_id=%i", $id);
            //delete from KEYS
            DB::delete($pre."keys", "`id` =%i AND `table`=%s", $id, "items");
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
            "SELECT * FROM ".$pre."log_system as l INNER JOIN ".$pre."users as u ON (l.qui=u.id) WHERE l.type = %s",
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
            FROM ".$pre."log_system as l
            INNER JOIN ".$pre."users as u ON (l.qui=u.id)
            WHERE l.type = %s
            ORDER BY ".$_POST['order']." ".$_POST['direction']."
            LIMIT $start, $nbElements",
            "user_connection"
        );

        foreach ($rows as $reccord) {
            $logs .= '<tr><td>'.date(
                $_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date']
            ).'</td><td align=\"center\">'.$LANG[$reccord['label']].'</td><td align=\"center\">'.
            $reccord['login'].'</td></tr>';
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
            "SELECT * FROM ".$pre."log_system as l INNER JOIN ".$pre."users as u ON (l.qui=u.id) WHERE l.type = %s",
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
            FROM ".$pre."log_system as l
            INNER JOIN ".$pre."users as u ON (l.qui=u.id)
            WHERE l.type = %s
            ORDER BY ".$_POST['order']." ".$_POST['direction']."
            LIMIT $start, $nbElements",
            "error"
        );
        foreach ($rows as $reccord) {
            $label = explode('@', addslashes(cleanString($reccord['label'])));
            $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date']).'</td><td align=\"center\">'.@$label[1].'</td><td align=\"left\">'.$label[0].'</td><td align=\"center\">'.$reccord['login'].'</td></tr>';
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
            "SELECT * FROM ".$pre."log_items as l INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
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
            FROM ".$pre."log_items as l
            INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
            WHERE %l
            ORDER BY ".$_POST['order']." ".$_POST['direction']."
            LIMIT $start, $nbElements",
            $where
        );
        foreach ($rows as $reccord) {
            $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date']).'</td><td align=\"left\">'.str_replace('"', '\"', $reccord['label']).'</td><td align=\"center\">'.$reccord['login'].'</td></tr>';
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
            "SELECT * FROM ".$pre."log_items as l
            INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
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
            FROM ".$pre."log_items as l
            INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
            WHERE %l
            ORDER BY date DESC
            LIMIT $start, $nbElements",
            $where
        );
        foreach ($rows as $reccord) {
            $label = explode('@', addslashes(cleanString($reccord['label'])));
            $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date']).'</td><td align=\"left\">'.$label[0].'</td><td align=\"center\">'.$reccord['login'].'</td></tr>';
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
            "SELECT * FROM ".$pre."log_items as l
            INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
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
            FROM ".$pre."log_items as l
            INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
            WHERE %l
            ORDER BY date DESC
            LIMIT $start, $nbElements",
            $where
        );

        foreach ($rows as $reccord) {
            if ($reccord['perso'] == 1) {
                $label[0] = "** ".$LANG['at_personnel']." **";
            } else {
                $label = explode('@', addslashes(cleanString($reccord['label'])));
            }
            $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date']).'</td><td align=\"left\">'.$label[0].'</td><td align=\"center\">'.$reccord['login'].'</td></tr>';
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
            "SELECT * FROM ".$pre."log_system as l
            INNER JOIN ".$pre."users as u ON (l.qui=u.id)
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
            FROM ".$pre."log_system as l
            INNER JOIN ".$pre."users as u ON (l.qui=u.id)
            WHERE %l
            ORDER BY date DESC
            LIMIT $start, $nbElements",
            $where
        );

        foreach ($rows as $reccord) {
            $label = explode('@', addslashes(cleanString($reccord['label'])));
            $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date']).'</td><td align=\"left\">'.$label[0].'</td><td align=\"center\">'.$reccord['login'].'</td></tr>';
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
            FROM ".$pre."log_items as l
            INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
            INNER JOIN ".$pre."nested_tree as n ON (n.id=i.id_tree)
            WHERE i.inactif = %s
            AND (l.action = %s OR (l.action = %s AND l.raison = %ss))
            AND n.renewal_period != %s
            ORDER BY i.label ASC, l.date DESC",
            0,
            "at_creation",
            "at_modification",
            "at_pw :",
            0
        );
        $idManaged = '';
        foreach ($rows as $reccord) {
            if (empty($idManaged) || $idManaged != $reccord['id']) {
                //manage the date limit
                $itemDate = $reccord['date'] + ($reccord['renewal_period'] * $k['one_month_seconds']);

                if ($itemDate <= $date) {
                    //Save data found
                    $texte .= '<tr><td width=\"250px\"><span class=\"ui-icon ui-icon-link\" style=\"float: left; margin-right: .3em; cursor:pointer;\" onclick=\"javascript:window.location.href = \'index.php?page=items&amp;group='.$reccord['id_tree'].'&amp;id='.$reccord['id'].'\'\">&nbsp;</span>'.$reccord['label'].'</td><td width=\"100px\" align=\"center\">'.date($_SESSION['settings']['date_format'], $reccord['date']).'</td><td width=\"100px\" align=\"center\">'.date($_SESSION['settings']['date_format'], $itemDate).'</td><td width=\"150px\" align=\"center\">'.$reccord['title'].'</td><td width=\"100px\" align=\"center\">'.$reccord['login'].'</td></tr>';

                    //save data for PDF
                    if (empty($textPdf)) {
                        $textPdf = $reccord['label'].'@;@'.date($_SESSION['settings']['date_format'], $reccord['date']).'@;@'.date($_SESSION['settings']['date_format'], $itemDate).'@;@'.$reccord['title'].'@;@'.$reccord['login'];
                    } else {
                        $textPdf .= '@|@'.$reccord['label'].'@;@'.date($_SESSION['settings']['date_format'], $reccord['date']).'@;@'.date($_SESSION['settings']['date_format'], $itemDate).'@;@'.$reccord['title'].'@;@'.$reccord['login'];
                    }
                }
            }
            $idManaged = $reccord['id'];
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
        $pdf->AddFont('DejaVu', '', 'DejaVuSansCondensed.ttf', true);

        $pdf->aliasNbPages();
        $pdf->addPage();
        $pdf->SetFont('DejaVu', '', 16);
        $pdf->Cell(0, 10, $LANG['renewal_needed_pdf_title'], 0, 1, 'C', false);
        $pdf->SetFont('DejaVu', '', 12);
        $pdf->Cell(0, 10, $LANG['pdf_del_date'].date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], time()), 0, 1, 'C', false);
        $pdf->SetFont('DejaVu', '', 10);
        $pdf->SetFillColor(192, 192, 192);
        $pdf->cell(70, 6, $LANG['label'], 1, 0, "C", 1);
        $pdf->cell(25, 6, $LANG['creation_date'], 1, 0, "C", 1);
        $pdf->cell(25, 6, $LANG['expiration_date'], 1, 0, "C", 1);
        $pdf->cell(45, 6, $LANG['group'], 1, 0, "C", 1);
        $pdf->cell(25, 6, $LANG['author'], 1, 1, "C", 1);
        $pdf->SetFont('DejaVu', '', 9);

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
                    "SELECT * FROM ".$pre."log_items WHERE action=%s ".
                    "AND date BETWEEN %i AND %i'",
                    "at_shown",
                    intval(strtotime($_POST['purgeFrom'])),
                    intval(strtotime($_POST['purgeTo']))
                );
                $nbElements = DB::count();
                    // Delete
                DB::delete($pre."log_items", "action=%s AND date BETWEEN %i AND %i",
                    "at_shown",
                    intval(strtotime($_POST['purgeFrom'])),
                    intval(strtotime($_POST['purgeTo']))
                );
            } elseif ($_POST['logType'] == "connections_logs") {
                DB::query(
                    "SELECT * FROM ".$pre."log_items WHERE action=%s ".
                    "AND date BETWEEN %i AND %i'",
                    "user_connection",
                    intval(strtotime($_POST['purgeFrom'])),
                    intval(strtotime($_POST['purgeTo']))
                );
                $nbElements = DB::count();
                // Delete
                DB::delete($pre."log_items", "action=%s AND date BETWEEN %i AND %i",
                    "user_connection",
                    intval(strtotime($_POST['purgeFrom'])),
                    intval(strtotime($_POST['purgeTo']))
                );
            } elseif ($_POST['logType'] == "errors_logs") {
                DB::query(
                    "SELECT * FROM ".$pre."log_items WHERE action=%s ".
                    "AND date BETWEEN %i AND %i'",
                    "error",
                    intval(strtotime($_POST['purgeFrom'])),
                    intval(strtotime($_POST['purgeTo']))
                );
                $nbElements = DB::count();
                // Delete
                DB::delete($pre."log_items", "action=%s AND date BETWEEN %i AND %i",
                    "error",
                    intval(strtotime($_POST['purgeFrom'])),
                    intval(strtotime($_POST['purgeTo']))
                );
            } elseif ($_POST['logType'] == "copy_logs") {
                DB::query(
                    "SELECT * FROM ".$pre."log_items WHERE action=%s ".
                    "AND date BETWEEN %i AND %i'",
                    "at_copy",
                    intval(strtotime($_POST['purgeFrom'])),
                    intval(strtotime($_POST['purgeTo']))
                );
                $nbElements = DB::count();
                // Delete
                DB::delete($pre."log_items", "action=%s AND date BETWEEN %i AND %i",
                    "at_copy",
                    intval(strtotime($_POST['purgeFrom'])),
                    intval(strtotime($_POST['purgeTo']))
                );
            }

            echo '[{"status" : "ok", "nb":"'.$nbElements[0].'"}]';
        } else {
            echo '[{"status" : "nok"}]';
        }
        break;
}
