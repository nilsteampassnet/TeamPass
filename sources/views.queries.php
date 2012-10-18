<?php
/**
 * @file 		views.queries.php
 * @author		Nils Laumaillé
 * @version 	2.1.8
 * @copyright 	(c) 2009-2011 Nils Laumaillé
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

session_start();
if (!isset($_SESSION['CPM'] ) || $_SESSION['CPM'] != 1)
    die('Hacking attempt...');

include '../includes/language/'.$_SESSION['user_language'].'.php';
include '../includes/settings.php';
include '../includes/include.php';
header("Content-type: text/html; charset=utf-8");
include 'main.functions.php';

// connect to the server
    require_once 'Database.class.php';
    $db = new Database($server, $user, $pass, $database, $pre);
    $db->connect();

//Constant used
$nb_elements = 20;

// Construction de la requ?te en fonction du type de valeur
switch ($_POST['type']) {
    #CASE generating the log for passwords renewal
    case "log_generate":
        require_once 'NestedTree.class.php';
        $tree = new NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

        //Prepare the PDF file
        include '../includes/libraries/tfpdf/tfpdf.php';
        $pdf=new tFPDF();

        //Add font for utf-8
        $pdf->AddFont('DejaVu','','DejaVuSansCondensed.ttf',true);

        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetFont('DejaVu','',16);
        $pdf->Cell(0,10,$txt['pdf_del_title'],0,1,'C',false);
        $pdf->SetFont('DejaVu','',12);
        $pdf->Cell(0,10,$txt['pdf_del_date'].date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'],mktime(date("H"),date("i"),date("s"),date("m"),date("d"),date("Y"))),0,1,'C',false);
        $pdf->SetFont('DejaVu','',10);
        $pdf->SetFillColor(15,86,145);
        $pdf->cell(80,6,$txt['label'],1,0,"C",1);
        $pdf->cell(75,6,$txt['group'],1,0,"C",1);
        $pdf->cell(21,6,$txt['date'],1,0,"C",1);
        $pdf->cell(15,6,$txt['author'],1,1,"C",1);
        $pdf->SetFont('DejaVu','',10);

        $rows = $db->fetch_all_array("
            SELECT u.login AS login, i.label AS label, i.id_tree AS id_tree
            FROM ".$pre."log_items AS l
            INNER JOIN ".$pre."users AS u ON (u.id=l.id_user)
            INNER JOIN ".$pre."items AS i ON (i.id=l.id_item)
            WHERE l.action = 'Modification'
            AND l.raison = 'Mot de passe chang?'
        ");
        foreach ($rows as $reccord) {
            if ( date($_SESSION['settings']['date_format'],$reccord['date']) == $_POST['date'] ) {
                //information about the pw creator
                $res_user = mysql_query("SELECT login FROM ".$pre."users WHERE id = '".$reccord['id_user']."'");
                $data_user = mysql_fetch_row($res_user);
                //information about the pw itself
                $res_item = mysql_query("SELECT label, id_tree FROM ".$pre."items WHERE id = '".$reccord['id_item']."'");
                $data_item = mysql_fetch_row($res_item);
                //get the tree grid
                $arbo = $tree->getPath($reccord['id_tree'], true);
                $arboTxt = "";
                foreach ($arbo as $elem) {
                    if ( empty($arboTxt) ) $arboTxt = $elem->title;
                    else $arboTxt .= " > ".$elem->title;
                }
                $pdf->cell(80,6,$reccord['label'],1,0,"L");
                $pdf->cell(75,6,$arboTxt,1,0,"L");
                $pdf->cell(21,6,$_POST['date'],1,0,"C");
                $pdf->cell(15,6,$reccord['login'],1,1,"C");
            }
        }
        list($d,$m,$y) = explode('/',$_POST['date']);
        $nomFichier = "log_followup_passwords_".date("Y-m-d",mktime(0,0,0,$m,$d,$y)).".pdf";
        //send the file
        $pdf->Output($_SESSION['settings']['path_to_files_folder'].'/'.$nomFichier);

        echo '[{"text":"<a href=\''.$_SESSION['settings']['url_to_files_folder'].'/'.$nomFichier.'\' target=\'_blank\'>'.$txt['pdf_download'].'</a>"}]';
    break;

    #----------------------------------
    #CASE display a full listing with all items deleted
    case "lister_suppression":
        //FOLDERS deleted
        $arr_folders = array();
        $texte = "<table cellpadding=3><tr><td><u><b>".$txt['group']."</b></u></td></tr>";
        $rows = $db->fetch_all_array("
            SELECT valeur, intitule
            FROM ".$pre."misc
            WHERE type  = 'folder_deleted'");
        foreach ($rows as $reccord) {
            $tmp = explode(',', $reccord['valeur']);
            $texte .= '<tr><td><input type=\'checkbox\' class=\'cb_deleted_folder\' value=\''.$reccord['intitule'].'\' id=\'folder_deleted_'.$reccord['intitule'].'\' />&nbsp;<b>'.
                $tmp[2].'</b></td><td><input type=\"hidden\" value=\"'.$reccord['valeur'].'\"></td></tr>';
            $arr_folders[substr($reccord['intitule'],1)] = $tmp[2];
        }

        //ITEMS deleted
        $texte .= "<tr><td><u><b>".$txt['email_altbody_1']."</b></u></td></tr>";
        $rows = $db->fetch_all_array("
            SELECT u.login AS login, i.id AS id, i.label AS label, i.id_tree AS id_tree, l.date AS date
            FROM ".$pre."log_items AS l
            INNER JOIN ".$pre."items AS i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users AS u ON (l.id_user=u.id)
            WHERE i.inactif = '1'
            AND l.action = 'at_delete'
            GROUP BY l.id_item");
        foreach ($rows as $reccord) {
            if (in_array($reccord['id_tree'], $arr_folders)) {
                if (count($arr_folders[$reccord['id_tree']])>0 ) {
                    $this_folder = '<td>'.$arr_folders[$reccord['id_tree']].'</td>';
                } else {
                    $this_folder = "";
                }
            } else {
                $this_folder = "";
            }

            $texte .= '<tr><td><input type=\'checkbox\' class=\'cb_deleted_item\' value=\''.$reccord['id'].'\' id=\'item_deleted_'.$reccord['id'].'\' />&nbsp;<b>'.$reccord['label'].'</b></td><td width=\"100px\" align=\"center\">'.date($_SESSION['settings']['date_format'],$reccord['date']).'</td><td width=\"70px\" align=\"center\">'.$reccord['login'].'</td>'.$this_folder.'</tr>';
        }

        echo '[{"text":"'.$texte.'</table><div style=\'margin-left:5px;\'><input type=\'checkbox\' id=\'item_deleted_select_all\' />&nbsp;<img src=\"includes/images/arrow-repeat.png\" title=\"'.$txt['restore'].'\" style=\"cursor:pointer;\" onclick=\"restoreDeletedItems()\">&nbsp;<img src=\"includes/images/bin_empty.png\" title=\"'.$txt['delete'].'\" style=\"cursor:pointer;\" onclick=\"reallyDeleteItems()\"></div>"}]';
    break;

    #----------------------------------
    #CASE admin want to restaure a list of deleted items
    case "restore_deleted__items":
        //restore FOLDERS
        if (count($_POST['list_f'])>0) {
            foreach ( explode(';',$_POST['list_f']) as $id ) {
                $data = $db->query_first("
                    SELECT valeur
                    FROM ".$pre."misc
                    WHERE type = 'folder_deleted'
                    AND intitule = '".$id."'"
                );
                if ($data['valeur'] != 0) {
                    $folder_data = explode(',', $data['valeur']);
                    //insert deleted folder
                    $db->query_insert(
                        'nested_tree',
                        array(
                            'id' => $folder_data[0],
                            'parent_id' => $folder_data[1],
                            'title' => $folder_data[2],
                            'nleft' => $folder_data[3],
                            'nright' => $folder_data[4],
                            'nlevel' => $folder_data[5],
                            'bloquer_creation' => $folder_data[6],
                            'bloquer_modification' => $folder_data[7],
                            'personal_folder' => $folder_data[8],
                            'renewal_period' => $folder_data[9]
                        )
                    );
                    //delete log
                    $db->query("DELETE FROM ".$pre."misc WHERE type = 'folder_deleted' AND intitule= '".$id."'");
                }
            }
        }
        //restore ITEMS
        if (count($_POST['list_i'])>0) {
            foreach ( explode(';',$_POST['list_i']) as $id ) {
                $db->query_update(
                "items",
                array(
                    'inactif' => '0'
                ),
                'id = '.$id
                );
                //log
                $db->query("INSERT INTO ".$pre."log_items VALUES ('".$id."','".mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y'))."','".$_SESSION['user_id']."','at_restored','')");
            }
        }

    break;

    #----------------------------------
    #CASE admin want to delete a list of deleted items
    case "really_delete_items":
        $folders = explode(';',$_POST['folders']);
        if (count($folders)>0) {
            //delete folders
            foreach ($folders as $f_id) {
                //get folder ID
                $id = substr($f_id, 1);

                //delete any subfolder
                $rows = $db->fetch_all_array(
                    "SELECT valeur
                    FROM ".$pre."misc
                    WHERE type='folder_deleted' AND intitule = '".$f_id."'"
                );
                foreach ($rows as $reccord) {
                    //get folder id
                    $val = explode(",", $reccord['valeur']);
                    //delete items & logs
                    $items = $db->fetch_all_array("SELECT id FROM ".$pre."items WHERE id_tree='".$val[0]."'");
                    foreach ($items as $item) {
                        //Delete item
                        $db->query("DELETE FROM ".$pre."items WHERE id = ".$item['id']);
                        $db->query("DELETE FROM ".$pre."log_items WHERE id_item = ".$item['id']);

                        //Update CACHE table
                        mysql_query("DELETE FROM ".$pre."cache WHERE id = ".$item['id']);
                    }
                    //Actualize the variable
                    $_SESSION['nb_folders'] --;
                }
                //delete folder
                $db->query("DELETE FROM ".$pre."misc WHERE intitule = '".$f_id."' AND type = 'folder_deleted'");
            }
        }

        foreach ( explode(';',$_POST['items']) as $id ) {
            //delete from ITEMS
            $db->query("DELETE FROM ".$pre."items WHERE id=".$id);
            //delete from LOG_ITEMS
            $db->query("DELETE FROM ".$pre."log_items WHERE id_item=".$id);
            //delete from FILES
            $db->query("DELETE FROM ".$pre."files WHERE id_item=".$id);
            //delete from TAGS
            $db->query("DELETE FROM ".$pre."tags WHERE item_id=".$id);
            //delete from KEYS
            $db->query("DELETE FROM `".$pre."keys` WHERE `id` ='".$id."' AND `table`='items'");
        }
    break;

    #----------------------------------
    #CASE admin want to see COONECTIONS logs
    case "connections_logs":
        $logs = "";
        $nb_pages = 1;
        $pages = '<table style=\'border-top:1px solid #969696;\'><tr><td>'.$txt['pages'].'&nbsp;:&nbsp;</td>';

        //get number of pages
        $data = $db->fetch_row("
            SELECT COUNT(*)
            FROM ".$pre."log_system AS l
            INNER JOIN ".$pre."users AS u ON (l.qui=u.id)
            WHERE l.type = 'user_connection'");
        if ($data[0] != 0) {
            $nb_pages = ceil($data[0]/$nb_elements);
            for ($i=1;$i<=$nb_pages;$i++) {
                $pages .= '<td onclick=\'displayLogs(\"connections_logs\",'.$i.',\"'.$_POST['order'].'\")\'><span style=\'cursor:pointer;' . ($_POST['page'] == $i ? 'font-weight:bold;font-size:18px;\'>'.$i:'\'>'.$i ) . '</span></td>';
            }
        }
        $pages .= '</tr></table>';

        //define query limits
        if ( isset($_POST['page']) && $_POST['page'] > 1 ) {
            $start = ($nb_elements*($_POST['page']-1)) + 1;
        } else {
            $start = 0;
        }

        //launch query
        $rows = $db->fetch_all_array("
            SELECT l.date AS date, l.label AS label, l.qui AS who, u.login AS login
            FROM ".$pre."log_system AS l
            INNER JOIN ".$pre."users AS u ON (l.qui=u.id)
            WHERE l.type = 'user_connection'
            ORDER BY ".$_POST['order']." ".$_POST['direction']."
            LIMIT $start, $nb_elements");

        foreach( $rows as $reccord)
            $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'],$reccord['date']).'</td><td align=\"center\">'.$txt[$reccord['label']].'</td><td align=\"center\">'.$reccord['login'].'</td></tr>';

        echo '[{"tbody_logs": "'.$logs.'" , "log_pages" : "'.$pages.'"}]';
    break;

    #----------------------------------
    #CASE admin want to see CONNECTIONS logs
    case "errors_logs":
        $logs = "";
        $nb_pages = 1;
        $pages = '<table style=\'border-top:1px solid #969696;\'><tr><td>'.$txt['pages'].'&nbsp;:&nbsp;</td>';

        //get number of pages
        $data = $db->fetch_row("
            SELECT COUNT(*)
            FROM ".$pre."log_system AS l
            INNER JOIN ".$pre."users AS u ON (l.qui=u.id)
            WHERE l.type = 'error'");
        if ($data[0] != 0) {
            $nb_pages = ceil($data[0]/$nb_elements);
            for ($i=1;$i<=$nb_pages;$i++) {
                $pages .= '<td onclick=\'displayLogs(\"errors_logs\",'.$i.',\"'.$_POST['order'].'\")\'><span style=\'cursor:pointer;' . ($_POST['page'] == $i ? 'font-weight:bold;font-size:18px;\'>'.$i:'\'>'.$i ) . '</span></td>';
            }
        }
        $pages .= '</tr></table>';

        //define query limits
        if ( isset($_POST['page']) && $_POST['page'] > 1 ) {
            $start = ($nb_elements*($_POST['page']-1)) + 1;
        } else {
            $start = 0;
        }

        //launch query
        $rows = $db->fetch_all_array("
            SELECT l.date AS date, l.label AS label, l.qui AS who, u.login AS login
            FROM ".$pre."log_system AS l
            INNER JOIN ".$pre."users AS u ON (l.qui=u.id)
            WHERE l.type = 'error'
            ORDER BY ".$_POST['order']." ".$_POST['direction']."
            LIMIT $start, $nb_elements");

        foreach ($rows as $reccord) {
            $label = explode('@',addslashes(CleanString($reccord['label'])));
            $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'],$reccord['date']).'</td><td align=\"center\">'.@$label[1].'</td><td align=\"left\">'.$label[0].'</td><td align=\"center\">'.$reccord['login'].'</td></tr>';
        }

        echo '[{"tbody_logs": "'.$logs.'" , "log_pages" : "'.$pages.'"}]';
    break;

    #----------------------------------
    #CASE admin want to see CONNECTIONS logs
    case "access_logs":
        $logs = $sql_filter = "";
        $nb_pages = 1;
        $pages = '<table style=\'border-top:1px solid #969696;\'><tr><td>'.$txt['pages'].'&nbsp;:&nbsp;</td>';

        if (isset($_POST['filter']) && !empty($_POST['filter'])) {
            $sql_filter = " AND i.label LIKE '%".$_POST['filter']."%'";
        }
        if (isset($_POST['filter_user']) && !empty($_POST['filter_user'])) {
            $sql_filter = " AND l.id_user LIKE '%".$_POST['filter_user']."%'";
        }

        //get number of pages
        $data = $db->fetch_row("
            SELECT COUNT(*)
            FROM ".$pre."log_items AS l
            INNER JOIN ".$pre."items AS i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users AS u ON (l.id_user=u.id)
            WHERE l.action = 'at_shown'".$sql_filter);
        if ($data[0] != 0) {
            $nb_pages = ceil($data[0]/$nb_elements);
            for ($i=1;$i<=$nb_pages;$i++) {
                $pages .= '<td onclick=\'displayLogs(\"access_logs\",'.$i.',\"'.$_POST['order'].'\")\'><span style=\'cursor:pointer;' . ($_POST['page'] == $i ? 'font-weight:bold;font-size:18px;\'>'.$i:'\'>'.$i ) . '</span></td>';
            }
        }
        $pages .= '</tr></table>';

        //define query limits
        if ( isset($_POST['page']) && $_POST['page'] > 1 ) {
            $start = ($nb_elements*($_POST['page']-1)) + 1;
        } else {
            $start = 0;
        }

        //launch query
        $rows = $db->fetch_all_array("
            SELECT l.date AS date, u.login AS login, i.label AS label
            FROM ".$pre."log_items AS l
            INNER JOIN ".$pre."items AS i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users AS u ON (l.id_user=u.id)
            WHERE l.action = 'at_shown'".$sql_filter."
            ORDER BY ".$_POST['order']." ".$_POST['direction']."
            LIMIT $start, $nb_elements");

        foreach ($rows as $reccord) {
            //$label = explode('@',addslashes(CleanString($reccord['label'])));
            $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'],$reccord['date']).'</td><td align=\"left\">'.str_replace('"', '\"', $reccord['label']).'</td><td align=\"center\">'.$reccord['login'].'</td></tr>';
        }

        echo '[{"tbody_logs": "'.$logs.'" , "log_pages" : "'.$pages.'"}]';
        break;

        #----------------------------------
        #CASE admin want to see COPIES logs
           case "copy_logs":
        $logs = $sql_filter = "";
        $nb_pages = 1;
        $pages = '<table style=\'border-top:1px solid #969696;\'><tr><td>'.$txt['pages'].'&nbsp;:&nbsp;</td>';

        if (isset($_POST['filter']) && !empty($_POST['filter'])) {
            $sql_filter = " AND i.label LIKE '%".$_POST['filter']."%'";
        }
        if (isset($_POST['filter_user']) && !empty($_POST['filter_user'])) {
            $sql_filter = " AND l.id_user LIKE '%".$_POST['filter_user']."%'";
        }

        //get number of pages
        $data = $db->fetch_row("
            SELECT COUNT(*)
            FROM ".$pre."log_items AS l
            INNER JOIN ".$pre."items AS i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users AS u ON (l.id_user=u.id)
            WHERE l.action = 'at_copy'".$sql_filter);
        if ($data[0] != 0) {
            $nb_pages = ceil($data[0]/$nb_elements);
            for ($i=1;$i<=$nb_pages;$i++) {
                $pages .= '<td onclick=\'displayLogs(\"copy_logs\",'.$i.', \'\')\'><span style=\'cursor:pointer;' . ($_POST['page'] == $i ? 'font-weight:bold;font-size:18px;\'>'.$i:'\'>'.$i ) . '</span></td>';
            }
        }
        $pages .= '</tr></table>';

        //define query limits
        if ( isset($_POST['page']) && $_POST['page'] > 1 ) {
            $start = ($nb_elements*($_POST['page']-1)) + 1;
        } else {
            $start = 0;
        }

        //launch query
        $rows = $db->fetch_all_array("
            SELECT l.date AS date, u.login AS login, i.label AS label
            FROM ".$pre."log_items AS l
            INNER JOIN ".$pre."items AS i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users AS u ON (l.id_user=u.id)
            WHERE l.action = 'at_copy'".$sql_filter."
            ORDER BY date DESC
            LIMIT $start, $nb_elements");

        foreach ($rows as $reccord) {
            $label = explode('@',addslashes(CleanString($reccord['label'])));
            $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'],$reccord['date']).'</td><td align=\"left\">'.$label[0].'</td><td align=\"center\">'.$reccord['login'].'</td></tr>';
        }

        echo '[{"tbody_logs": "'.$logs.'" , "log_pages" : "'.$pages.'"}]';
        break;

         #----------------------------------
         #CASE admin want to see ITEMS logs
           case "items_logs":
               $logs = $sql_filter = "";
               $nb_pages = 1;
               $pages = '<table style=\'border-top:1px solid #969696;\'><tr><td>'.$txt['pages'].'&nbsp;:&nbsp;</td>';

               if (isset($_POST['filter']) && !empty($_POST['filter'])) {
                   $sql_filter = " AND i.label LIKE '%".$_POST['filter']."%'";
               }
               if (isset($_POST['filter_user']) && !empty($_POST['filter_user'])) {
                   $sql_filter = " AND l.id_user LIKE '%".$_POST['filter_user']."%'";
               }

               //get number of pages
               $data = $db->fetch_row("
                   SELECT COUNT(*)
            FROM ".$pre."log_items AS l
            INNER JOIN ".$pre."items AS i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users AS u ON (l.id_user=u.id)
            WHERE i.label LIKE '%".$_POST['filter']."%'");
               if ($data[0] != 0) {
                   $nb_pages = ceil($data[0]/$nb_elements);
                   for ($i=1;$i<=$nb_pages;$i++) {
                       $pages .= '<td onclick=\'displayLogs(\"copy_logs\",'.$i.', \'\')\'><span style=\'cursor:pointer;' . ($_POST['page'] == $i ? 'font-weight:bold;font-size:18px;\'>'.$i:'\'>'.$i ) . '</span></td>';
                   }
               }
               $pages .= '</tr></table>';

               //define query limits
               if ( isset($_POST['page']) && $_POST['page'] > 1 ) {
                   $start = ($nb_elements*($_POST['page']-1)) + 1;
               } else {
                   $start = 0;
               }

               //launch query
               $rows = $db->fetch_all_array("
                   SELECT l.date AS date, u.login AS login, i.label AS label
            FROM ".$pre."log_items AS l
            INNER JOIN ".$pre."items AS i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users AS u ON (l.id_user=u.id)
            WHERE i.label LIKE '%".$_POST['filter']."%'
            ORDER BY date DESC
            LIMIT $start, $nb_elements");

               foreach ($rows as $reccord) {
                   $label = explode('@',addslashes(CleanString($reccord['label'])));
                   $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'],$reccord['date']).'</td><td align=\"left\">'.$label[0].'</td><td align=\"center\">'.$reccord['login'].'</td></tr>';
               }

               echo '[{"tbody_logs": "'.$logs.'" , "log_pages" : "'.$pages.'"}]';
           break;

        #----------------------------------
        #CASE admin want to see COPIES logs
    case "admin_logs":
        $logs = $sql_filter = "";
        $nb_pages = 1;
        $pages = '<table style=\'border-top:1px solid #969696;\'><tr><td>'.$txt['pages'].'&nbsp;:&nbsp;</td>';

        if (isset($_POST['filter']) && !empty($_POST['filter'])) {
            $sql_filter = " AND l.label LIKE '%".$_POST['filter']."%'";
        }
        if (isset($_POST['filter_user']) && !empty($_POST['filter_user'])) {
            $sql_filter = " AND l.qui LIKE '%".$_POST['filter_user']."%'";
        }

        //get number of pages
        $data = $db->fetch_row("
            SELECT COUNT(*)
            FROM ".$pre."log_system AS l
            INNER JOIN ".$pre."users AS u ON (l.qui=u.id)
            WHERE l.type = 'admin_action'".$sql_filter);
        if ($data[0] != 0) {
            $nb_pages = ceil($data[0]/$nb_elements);
            for ($i=1;$i<=$nb_pages;$i++) {
                $pages .= '<td onclick=\'displayLogs(\"copy_logs\",'.$i.', \'\')\'><span style=\'cursor:pointer;' . ($_POST['page'] == $i ? 'font-weight:bold;font-size:18px;\'>'.$i:'\'>'.$i ) . '</span></td>';
            }
        }
        $pages .= '</tr></table>';

        //define query limits
        if ( isset($_POST['page']) && $_POST['page'] > 1 ) {
            $start = ($nb_elements*($_POST['page']-1)) + 1;
        } else {
            $start = 0;
        }

        //launch query
        $rows = $db->fetch_all_array("
            SELECT l.date AS date, u.login AS login, l.label AS label
            FROM ".$pre."log_system AS l
            INNER JOIN ".$pre."users AS u ON (l.qui=u.id)
            WHERE l.type = 'admin_action'".$sql_filter."
            ORDER BY date DESC
            LIMIT $start, $nb_elements");

        foreach ($rows as $reccord) {
            $label = explode('@',addslashes(CleanString($reccord['label'])));
            $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'],$reccord['date']).'</td><td align=\"left\">'.$label[0].'</td><td align=\"center\">'.$reccord['login'].'</td></tr>';
        }

        echo '[{"tbody_logs": "'.$logs.'" , "log_pages" : "'.$pages.'"}]';
        break;

    #----------------------------------
    #CASE display a full listing with items EXPRIED
    case "generate_renewal_listing":

        if ( $_POST['period'] == "0" )
            $date = (mktime(date('h'),date('i'),date('s'),date('m'),date('d'),date('y')));
        else if ( $_POST['period'] == "1month" )
            $date = (mktime(date('h'),date('i'),date('s'),date('m')+1,date('d'),date('y')));
        else if ( $_POST['period'] == "6months" )
            $date = (mktime(date('h'),date('i'),date('s'),date('m')+6,date('d'),date('y')));
        else if ( $_POST['period'] == "1year" )
            $date = (mktime(date('h'),date('i'),date('s'),date('m'),date('d'),date('y')+1));

        $id_item = "";
        $texte = "<table cellpadding=3><thead><tr><th>".$txt['label']."</th><th>".$txt['creation_date']."</th><th>".$txt['expiration_date']."</th><th>".$txt['group']."</th><th>".$txt['auteur']."</th></tr></thead>";
        $text_pdf = "";
        $rows = $db->fetch_all_array("
            SELECT u.login AS login,
            i.id AS id, i.label AS label, i.id_tree AS id_tree,
            l.date AS date, l.id_item AS id_item, l.action AS action, l.raison AS raison,
            n.renewal_period AS renewal_period, n.title AS title
            FROM ".$pre."log_items AS l
            INNER JOIN ".$pre."items AS i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users AS u ON (l.id_user=u.id)
            INNER JOIN ".$pre."nested_tree AS n ON (n.id=i.id_tree)
            WHERE i.inactif = '0'
            AND (l.action = 'at_creation' OR (l.action = 'at_modification' AND l.raison LIKE 'at_pw :%') )
            AND n.renewal_period != '0'
            ORDER BY i.label ASC, l.date DESC");
        $id_managed = '';
        foreach ($rows as $reccord) {
            if ( empty($id_managed) || $id_managed != $reccord['id'] ) {
                //manage the date limit
                $item_date = $reccord['date'] + ($reccord['renewal_period'] * $k['one_month_seconds']);

                if ($item_date <= $date) {
                    //Save data found
                    $texte .= '<tr><td width=\"250px\"><span class=\"ui-icon ui-icon-link\" style=\"float: left; margin-right: .3em; cursor:pointer;\" onclick=\"javascript:window.location.href = \'index.php?page=items&amp;group='.$reccord['id_tree'].'&amp;id='.$reccord['id'].'\'\">&nbsp;</span>'.$reccord['label'].'</td><td width=\"100px\" align=\"center\">'.date($_SESSION['settings']['date_format'],$reccord['date']).'</td><td width=\"100px\" align=\"center\">'.date($_SESSION['settings']['date_format'],$item_date).'</td><td width=\"150px\" align=\"center\">'.$reccord['title'].'</td><td width=\"100px\" align=\"center\">'.$reccord['login'].'</td></tr>';

                    //save data for PDF
                    if (empty($text_pdf) )
                        $text_pdf = $reccord['label'].'@;@'.date($_SESSION['settings']['date_format'],$reccord['date']).'@;@'.date($_SESSION['settings']['date_format'],$item_date).'@;@'.$reccord['title'].'@;@'.$reccord['login'];
                    else
                        $text_pdf .= '@|@'.$reccord['label'].'@;@'.date($_SESSION['settings']['date_format'],$reccord['date']).'@;@'.date($_SESSION['settings']['date_format'],$item_date).'@;@'.$reccord['title'].'@;@'.$reccord['login'];
                }
            }
            $id_managed = $reccord['id'];
        }

        echo '[{"text" : "'.$texte.'</table>" , "pdf" : "'.$text_pdf.'"}]';
    break;

    #----------------------------------
    #CASE generating the pdf of items to rennew
    case "generate_renewal_pdf":
        require_once 'NestedTree.class.php';
        $tree = new NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

        //Prepare the PDF file
        include '../includes/libraries/tfpdf/tfpdf.php';
        $pdf=new tFPDF();

        //Add font for utf-8
        $pdf->AddFont('DejaVu','','DejaVuSansCondensed.ttf',true);

        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetFont('DejaVu','',16);
        $pdf->Cell(0,10,$txt['renewal_needed_pdf_title'],0,1,'C',false);
        $pdf->SetFont('DejaVu','',12);
        $pdf->Cell(0,10,$txt['pdf_del_date'].date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'],mktime(date("H"),date("i"),date("s"),date("m"),date("d"),date("Y"))),0,1,'C',false);
        $pdf->SetFont('DejaVu','',10);
        $pdf->SetFillColor(192,192,192);
        $pdf->cell(70,6,$txt['label'],1,0,"C",1);
        $pdf->cell(25,6,$txt['creation_date'],1,0,"C",1);
        $pdf->cell(25,6,$txt['expiration_date'],1,0,"C",1);
        $pdf->cell(45,6,$txt['group'],1,0,"C",1);
        $pdf->cell(25,6,$txt['author'],1,1,"C",1);
        $pdf->SetFont('DejaVu','',9);

        foreach ( explode('@|@',addslashes($_POST['text'])) as $line ) {
            $elem = explode('@;@',$line);
            if ( !empty($elem[0]) ) {
                $pdf->cell(70,6,$elem[0],1,0,"L");
                $pdf->cell(25,6,$elem[1],1,0,"C");
                $pdf->cell(25,6,$elem[2],1,0,"C");
                $pdf->cell(45,6,$elem[3],1,0,"C");
                $pdf->cell(25,6,$elem[4],1,1,"C");
            }
        }

        $pdf_file = "renewal_pdf_".date("Y-m-d",mktime(0,0,0,date('m'),date('d'),date('y'))).".pdf";
        //send the file
        $pdf->Output($_SESSION['settings']['path_to_files_folder']."/".$pdf_file);

        echo '[{"file" : "'.$_SESSION['settings']['url_to_files_folder'].'/'.$pdf_file.'"}]';
    break;
}
