<?php
/**
 * @file          views.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2018 Nils Laumaillé
 * @licensing     GNU GPL-3.0
 * @link          http://www.teampass.net
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
    require_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    require_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_views")) {
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
$tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

//Constant used
$nbElements = 20;

// building queries
if (null !== filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING)) {
    switch (filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING)) {
        #CASE generating the log for passwords renewal
        case "log_generate":
            // Prepare POST variable
            $post_date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);

            //Prepare the PDF file
            include $SETTINGS['cpassman_dir'].'/includes/libraries/Pdf/Tfpdf/tfpdf.class.php';
            $pdf = new TFPDF();

            //Add font for utf-8
            $pdf->AddFont('helvetica', '');
            $pdf->aliasNbPages();
            $pdf->addPage();
            $pdf->SetFont('helvetica', '', 16);
            $pdf->Cell(0, 10, $LANG['pdf_del_title'], 0, 1, 'C', false);
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Cell(0, 10, $LANG['pdf_del_date'].date($SETTINGS['date_format']." ".$SETTINGS['time_format'], time()), 0, 1, 'C', false);
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetFillColor(15, 86, 145);
            $pdf->cell(80, 6, $LANG['label'], 1, 0, "C", true);
            $pdf->cell(75, 6, $LANG['group'], 1, 0, "C", true);
            $pdf->cell(21, 6, $LANG['date'], 1, 0, "C", true);
            $pdf->cell(15, 6, $LANG['author'], 1, 1, "C", true);
            $pdf->SetFont('helvetica', '', 10);

            $rows = DB::query(
                "SELECT u.login as login, i.label as label, i.id_tree as id_tree, l.date
                FROM ".prefix_table("log_items")." as l
                INNER JOIN ".prefix_table("users")." as u ON (u.id=l.id_user)
                INNER JOIN ".prefix_table("items")." as i ON (i.id=l.id_item)
                WHERE l.action = %s AND l.raison LIKE %s",
                "at_modification",
                "at_pw :%"
            );
            foreach ($rows as $record) {
                if (date($SETTINGS['date_format'], $record['date']) === $post_date) {
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
                    $pdf->cell(21, 6, $post_date, 1, 0, "C");
                    $pdf->cell(15, 6, $record['login'], 1, 1, "C");
                }
            }
            list($d, $m, $y) = explode('/', filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING));
            $nomFichier = "log_followup_passwords_".date("Y-m-d", mktime(0, 0, 0, $m, $d, $y)).".pdf";
            //send the file
            $pdf->Output($SETTINGS['path_to_files_folder'].'/'.$nomFichier);

            echo '[{"text":"<a href=\''.$SETTINGS['url_to_files_folder'].'/'.$nomFichier.'\' target=\'_blank\'>'.$LANG['pdf_download'].'</a>"}]';
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
                $texte .= '<tr><td><input type=\'checkbox\' class=\'cb_deleted_folder\' value=\''.$record['intitule'].'\' id=\'folder_deleted_'.$record['intitule'].'\' />&nbsp;<b><label for=\'folder_deleted_'.$record['intitule'].'\'>'.
                    $tmp[2].'</label></b></td><td><input type=\"hidden\" value=\"'.$record['valeur'].'\"></td></tr>';
                $arrFolders[substr($record['intitule'], 1)] = $tmp[2];
            }

            //ITEMS deleted
            $texte .= "<tr><td><span class='fa fa-key'></span>&nbsp;<u><b>".$LANG['email_altbody_1']."</b></u></td></tr>";
            $rows = DB::query(
                "SELECT u.login as login, i.id as id, i.label as label,
                i.id_tree as id_tree, l.date as date, n.title as folder_title
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
                        if (count($arrFolders[$record['id_tree']]) > 0) {
                            $thisFolder = '<td>'.$arrFolders[$record['id_tree']].'</td>';
                        } else {
                            $thisFolder = "";
                        }
                    } else {
                        $thisFolder = "";
                    }

                    $texte .= '<tr><td><input type=\'checkbox\' class=\'cb_deleted_item\' value=\''.$record['id'].'\' id=\'item_deleted_'.$record['id'].'\' />&nbsp;<b><label for=\'item_deleted_'.$record['id'].'\'>'.$record['label'].'</label></b></td><td width=\"100px\" align=\"center\"><span class=\"fa fa-calendar\"></span>&nbsp;'.date($SETTINGS['date_format'], $record['date']).'</td><td width=\"70px\" align=\"center\"><span class=\"fa fa-user\"></span>&nbsp;'.$record['login'].'</td><td><span class=\"fa fa-folder-o\"></span>&nbsp;'.$record['folder_title'].'</td>'.$thisFolder.'</tr>';
                }
                $prev_id = $record['id'];
            }

            echo '[{"text":"'.$texte.'</table><div style=\'margin:15px 0px 0px 5px;\'><input type=\'checkbox\' id=\'item_deleted_select_all\' />&nbsp;&nbsp;<a class=\"button\" onclick=\"$(\'#tab2_action\').val(\'restoration\');OpenDialog(\'tab2_dialog\');\"><i class=\"fa fa-undo fa-lg\"></i>&nbsp;'.$LANG['restore'].'</a>&nbsp;&nbsp;<a class=\"button\" onclick=\"$(\'#tab2_action\').val(\'deletion\');OpenDialog(\'tab2_dialog\')\"><i class=\"fa fa-trash-o fa-lg\"></i>&nbsp;'.$LANG['delete'].'</a></div>"}]';
            break;

        /**
         * CASE admin want to restaure a list of deleted items
         */
        case "restore_deleted__items":
            // Prepare POST variable
            $post_list_f = explode(';', filter_input(INPUT_POST, 'list_f', FILTER_SANITIZE_STRING));
            $post_list_i = explode(';', filter_input(INPUT_POST, 'list_i', FILTER_SANITIZE_STRING));

            //restore FOLDERS
            if (count($post_list_f) > 0) {
                foreach ($post_list_f as $id) {
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
            if (count($post_list_i) > 0) {
                foreach ($post_list_i as $id) {
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
            // Prepare POST variable
            $post_folders = explode(';', filter_input(INPUT_POST, 'folders', FILTER_SANITIZE_STRING));
            $post_items = explode(';', filter_input(INPUT_POST, 'items', FILTER_SANITIZE_STRING));

            if (count($post_folders) > 0) {
                //delete folders
                foreach ($post_folders as $fId) {
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
                            // delete attachments
                            $item_files = DB::query("SELECT id, file FROM ".prefix_table("files")." WHERE id_item=%i", $item['id']);
                            foreach ($item_files as $file) {
                                fileDelete($SETTINGS['path_to_upload_folder'].'/'.$file['file']);
                            }

                            //Delete item
                            DB::delete(prefix_table("items"), "id = %i", $item['id']);
                            DB::delete(prefix_table("log_items"), "id_item = %i", $item['id']);

                            //Update CACHE table
                            DB::delete(prefix_table("cache"), "id = %i", $item['id']);
                        }
                        //Actualize the variable
                        $_SESSION['nb_folders']--;
                    }
                    //delete folder
                    DB::delete(prefix_table("misc"), "intitule = %s AND type = %s", $fId, "folder_deleted");
                }
            }

            foreach ($post_items as $id) {
                //delete from ITEMS
                DB::delete(prefix_table("items"), "id=%i", $id);
                //delete from LOG_ITEMS
                DB::delete(prefix_table("log_items"), "id_item=%i", $id);
                // delete attachments
                $item_files = DB::query("SELECT file FROM ".prefix_table("files")." WHERE id_item=%i", $id);
                foreach ($item_files as $file) {
                    fileDelete($SETTINGS['path_to_upload_folder'].'/'.$file['file']);
                }
                //delete from FILES
                DB::delete(prefix_table("files"), "id_item=%i", $id);
                //delete from TAGS
                DB::delete(prefix_table("tags"), "item_id=%i", $id);
            }
            break;

        /**
         * CASE generating the pdf of items to rennew
         */
        case "generate_renewal_pdf":
            // Prepare POST variable
            $post_text = explode(';', filter_input(INPUT_POST, 'text', FILTER_SANITIZE_STRING));

            //Prepare the PDF file
            include $SETTINGS['cpassman_dir'].'/includes/libraries/Pdf/Tfpdf/tfpdf.class.php';
            $pdf = new tFPDF();

            //Add font for utf-8
            $pdf->AddFont('helvetica', '');

            $pdf->aliasNbPages();
            $pdf->addPage();
            $pdf->SetFont('helvetica', '', 16);
            $pdf->Cell(0, 10, $LANG['renewal_needed_pdf_title'], 0, 1, 'C', false);
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Cell(0, 10, $LANG['pdf_del_date'].date($SETTINGS['date_format']." ".$SETTINGS['time_format'], time()), 0, 1, 'C', false);
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetFillColor(192, 192, 192);
            $pdf->cell(70, 6, $LANG['label'], 1, 0, "C", true);
            $pdf->cell(25, 6, $LANG['creation_date'], 1, 0, "C", true);
            $pdf->cell(25, 6, $LANG['expiration_date'], 1, 0, "C", true);
            $pdf->cell(45, 6, $LANG['group'], 1, 0, "C", true);
            $pdf->cell(25, 6, $LANG['author'], 1, 1, "C", true);
            $pdf->SetFont('helvetica', '', 9);

            foreach (explode('@|@', addslashes($post_text)) as $line) {
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
            $pdf->Output($SETTINGS['path_to_files_folder']."/".$pdfFile);

            echo '[{"file" : "'.$SETTINGS['url_to_files_folder'].'/'.$pdfFile.'"}]';
            break;

        /**
         * CASE purging logs
         */
        case "purgeLogs":
            // Prepare POST variable
            $post_purgeFrom = filter_input(INPUT_POST, 'purgeFrom', FILTER_SANITIZE_STRING);
            $post_purgeTo = filter_input(INPUT_POST, 'purgeTo', FILTER_SANITIZE_STRING);
            $post_logType = filter_input(INPUT_POST, 'logType', FILTER_SANITIZE_STRING);
            $post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);

            $post_purgeFrom = strtotime(date_format(date_create_from_format($SETTINGS['date_format'], $post_purgeFrom), 'Y-m-d'));
            $post_purgeTo = strtotime(date_format(date_create_from_format($SETTINGS['date_format'], $post_purgeTo), 'Y-m-d'));

            // Check KEY and rights
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }

            // Check conditions
            if (empty($post_purgeFrom) === false && empty($post_purgeTo) === false && empty($post_logType) === false
                && isset($_SESSION['user_admin']) && $_SESSION['user_admin'] == 1
            ) {
                if ($post_logType === "items_logs") {
                    DB::query(
                        "SELECT * FROM ".prefix_table("log_items")." WHERE action=%s ".
                        "AND date BETWEEN %i AND %i",
                        "at_shown",
                        ($post_purgeFrom),
                        ($post_purgeTo)
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefix_table("log_items"),
                        "action=%s AND date BETWEEN %i AND %i",
                        "at_shown",
                        ($post_purgeFrom),
                        ($post_purgeTo)
                    );
                } elseif ($post_logType === "connections_logs") {
                    DB::query(
                        "SELECT * FROM ".prefix_table("log_system")." WHERE type=%s ".
                        "AND date BETWEEN %i AND %i",
                        "user_connection",
                        ($post_purgeFrom),
                        ($post_purgeTo)
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefix_table("log_system"),
                        "type=%s AND date BETWEEN %i AND %i",
                        "user_connection",
                        ($post_purgeFrom),
                        ($post_purgeTo)
                    );
                } elseif ($post_logType === "errors_logs") {
                    DB::query(
                        "SELECT * FROM ".prefix_table("log_system")." WHERE type=%s ".
                        "AND date BETWEEN %i AND %i",
                        "error",
                        ($post_purgeFrom),
                        ($post_purgeTo)
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefix_table("log_system"),
                        "type=%s AND date BETWEEN %i AND %i",
                        "error",
                        ($post_purgeFrom),
                        ($post_purgeTo)
                    );
                } elseif ($post_logType === "copy_logs") {
                    DB::query(
                        "SELECT * FROM ".prefix_table("log_items")." WHERE action=%s ".
                        "AND date BETWEEN %i AND %i",
                        "at_copy",
                        ($post_purgeFrom),
                        ($post_purgeTo)
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefix_table("log_items"),
                        "action=%s AND date BETWEEN %i AND %i",
                        "at_copy",
                        ($post_purgeFrom),
                        ($post_purgeTo)
                    );
                } elseif ($post_logType === "admin_logs") {
                    DB::query(
                        "SELECT * FROM ".prefix_table("log_system")." WHERE type=%s ".
                        "AND date BETWEEN %i AND %i",
                        "admin_action",
                        ($post_purgeFrom),
                        ($post_purgeTo)
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefix_table("log_system"),
                        "type=%s AND date BETWEEN %i AND %i",
                        "admin_action",
                        ($post_purgeFrom),
                        ($post_purgeTo)
                    );
                } elseif ($post_logType === "failed_auth_logs") {
                    DB::query(
                        "SELECT * FROM ".prefix_table("log_system")." WHERE type=%s ".
                        "AND date BETWEEN %i AND %i",
                        "failed_auth",
                        ($post_purgeFrom),
                        ($post_purgeTo)
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefix_table("log_system"),
                        "type=%s AND date BETWEEN %i AND %i",
                        "failed_auth",
                        ($post_purgeFrom),
                        ($post_purgeTo)
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
}
