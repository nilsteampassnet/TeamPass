<?php
/**
 * @file          export.queries.php
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
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

// No time limit
set_time_limit(0);

global $k, $settings;
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset=utf-8");
error_reporting(E_ERROR);
require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

// connect to DB
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
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

//User's language loading
$k['langage'] = @$_SESSION['user_language'];
require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';

//Manage type of action asked
switch ($_POST['type']) {
    case "initialize_export_table":
        DB::query("TRUNCATE TABLE ".prefix_table("export"));
        break;

    //CASE export to PDF format
    case "export_to_pdf_format":
        /*
        $ids = explode(',', $_POST['ids']);
        foreach ($ids as $id) {
        */
        $id = $_POST['id'];
        if (!in_array($id, $_SESSION['forbiden_pfs']) && in_array($id, $_SESSION['groupes_visibles'])) {
            // get path
            $tree->rebuild();
            $folders = $tree->getPath($id, true);
            $path = "";
            foreach ($folders as $val) {
                if ($path) {
                    $path .= " » ";
                }
                $path .= $val->title;
            }

            // send query
            $rows = DB::query(
                "SELECT i.id as id, i.restricted_to as restricted_to, i.perso as perso, i.label as label, i.description as description, i.pw as pw, i.login as login, i.url as url, i.email as email,
                    l.date as date, i.pw_iv as pw_iv,
                    n.renewal_period as renewal_period
                    FROM ".prefix_table("items")." as i
                    INNER JOIN ".prefix_table("nested_tree")." as n ON (i.id_tree = n.id)
                    INNER JOIN ".prefix_table("log_items")." as l ON (i.id = l.id_item)
                    WHERE i.inactif = %i
                    AND i.id_tree= %i
                    AND (l.action = %s OR (l.action = %s AND l.raison LIKE %s))
                    ORDER BY i.label ASC, l.date DESC",
                "0",
                intval($id),
                "at_creation",
                "at_modification",
                "at_pw :%"
            );

            $id_managed = '';
            $i = 0;
            $items_id_list = array();
            foreach ($rows as $record) {
                $restricted_users_array = explode(';', $record['restricted_to']);
                //exclude all results except the first one returned by query
                if (empty($id_managed) || $id_managed != $record['id']) {
                    if (
                        (in_array($id, $_SESSION['personal_visible_groups']) && !($record['perso'] == 1 && $_SESSION['user_id'] == $record['restricted_to']) && !empty($record['restricted_to']))
                        ||
                        (!empty($record['restricted_to']) && !in_array($_SESSION['user_id'], $restricted_users_array))
                    ) {
                        //exclude this case
                    } else {
                        //encrypt PW
                        if (!empty($_POST['salt_key']) && isset($_POST['salt_key'])) {
                            $pw = cryption(
                                $record['pw'],
                                mysqli_escape_string($link, stripslashes($_POST['salt_key'])),
                                "decrypt"
                            );
                        } else {
                            $pw = cryption(
                                $record['pw'],
                                "",
                                "decrypt"
                            );
                        }

                        // get KBs
                        $arr_kbs = "";
                        $rows_kb = DB::query(
                            "SELECT b.label, b.id
                            FROM ".prefix_table("kb_items")." AS a
                            INNER JOIN ".prefix_table("kb")." AS b ON (a.kb_id = b.id)
                            WHERE a.item_id = %i",
                            $record['id']
                        );
                         foreach ($rows_kb as $rec_kb) {
                            if (empty($arr_kbs)) {
                                $arr_kbs = $rec_kb['label'];
                            } else {
                                $arr_kbs .= " | ".$rec_kb['label'];
                            }
                         }

                        // get TAGS
                        $arr_tags = "";
                        $rows_tag = DB::query(
                            "SELECT tag
                            FROM ".prefix_table("tags")."
                            WHERE item_id = %i",
                            $record['id']
                        );
                         foreach ($rows_tag as $rec_tag) {
                            if (empty($arr_tags)) {
                                $arr_tags = $rec_tag['tag'];
                            } else {
                                $arr_tags .= " ".$rec_tag['tag'];
                            }
                         }

                        // store
                        DB::insert(
                            prefix_table("export"),
                            array(
                                'id' => $record['id'],
                                'description' => strip_tags(cleanString(html_entity_decode($record['description'], ENT_QUOTES | ENT_XHTML, UTF-8), true)),
                                'label' => cleanString(html_entity_decode($record['label'], ENT_QUOTES | ENT_XHTML, UTF-8), true),
                                'pw' => html_entity_decode($pw['string'], ENT_QUOTES | ENT_XHTML, UTF-8),
                                'login' => strip_tags(cleanString(html_entity_decode($record['login'], ENT_QUOTES | ENT_XHTML, UTF-8), true)),
                                'path' => $path,
                                'url' => strip_tags(cleanString(html_entity_decode($record['url'], ENT_QUOTES | ENT_XHTML, UTF-8), true)),
                                'email' => strip_tags(cleanString(html_entity_decode($record['email'], ENT_QUOTES | ENT_XHTML, UTF-8), true)),
                                'kbs' => $arr_kbs,
                                'tags' => $arr_tags
                            )
                        );

                        // log
                        logItems(
                            $record['id'],
                            $record['label'],
                            $_SESSION['user_id'],
                            'at_export',
                            $_SESSION['login'],
                            'pdf'
                        );
                    }
                }
                $id_managed = $record['id'];
                $folder_title = $record['folder_title'];
            }
        }
        //}
        echo '[{}]';
        break;

    case "finalize_export_pdf":
        // query
        $rows = DB::query("SELECT * FROM ".prefix_table("export"));
        $counter = DB::count();
        if ($counter > 0) {
            // print
            //Some variables
            $table_full_width = 300;
            $table_col_width = array(40, 30, 30, 60, 27, 40, 25, 25);
            $table = array('label', 'login', 'pw', 'description', 'email', 'url', 'kbs', 'tags');
            $prev_path = "";

            //Prepare the PDF file
            include $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Pdf/Tfpdf/fpdf.php';

            $pdf = new FPDF_Protection("P", "mm", "A4", "ma page");
            $pdf->SetProtection(array('print'), $_POST['pdf_password']);

            //Add font for regular text
            $pdf->AddFont('helvetica', '');
            //Add monospace font for passwords
            $pdf->AddFont('LiberationMono', '');

            $pdf->aliasNbPages();
            $pdf->addPage(L);

            $prev_path = "";
            foreach ($rows as $record) {
                // decode
                $record['label'] = utf8_decode($record['label']);
                $record['login'] = utf8_decode($record['login']);
                $record['pw'] = utf8_decode($record['pw']);
                $record['description'] = utf8_decode($record['description']);
                $record['email'] = utf8_decode($record['email']);
                $record['url'] = utf8_decode($record['url']);
                $record['kbs'] = utf8_decode($record['kbs']);
                $record['tags'] = utf8_decode($record['tags']);

                $printed_ids[] = $record['id'];
                if ($prev_path != $record['path']) {
                    $pdf->SetFont('helvetica', '', 10);
                    $pdf->SetFillColor(192, 192, 192);
                    error_log('key: '.$key.' - paths: '.$record['path']);
                    $pdf->cell(0, 6, utf8_decode($record['path']), 1, 1, "L", 1);
                    $pdf->SetFillColor(222, 222, 222);
                    $pdf->cell($table_col_width[0], 6, $LANG['label'], 1, 0, "C", 1);
                    $pdf->cell($table_col_width[1], 6, $LANG['login'], 1, 0, "C", 1);
                    $pdf->cell($table_col_width[2], 6, $LANG['pw'], 1, 0, "C", 1);
                    $pdf->cell($table_col_width[3], 6, $LANG['description'], 1, 0, "C", 1);
                    $pdf->cell($table_col_width[4], 6, $LANG['email'], 1, 0, "C", 1);
                    $pdf->cell($table_col_width[5], 6, $LANG['url'], 1, 0, "C", 1);
                    $pdf->cell($table_col_width[6], 6, $LANG['kbs'], 1, 0, "C", 1);
                    $pdf->cell($table_col_width[7], 6, $LANG['tags'], 1, 1, "C", 1);
                }
                $prev_path = $record['path'];
                if (!isutf8($record['pw'])) $record['pw'] = "";
                //row height calculation
                $nb = 0;
                $nb = max($nb, nbLines($table_col_width[0], $record['label']));
                $nb = max($nb, nbLines($table_col_width[1], $record['login']));
                $nb = max($nb, nbLines($table_col_width[3], $record['description']));
                $nb = max($nb, nbLines($table_col_width[2], $record['pw']));
                $nb = max($nb, nbLines($table_col_width[5], $record['url']));
                $nb = max($nb, nbLines($table_col_width[6], $record['kbs']));
                $nb = max($nb, nbLines($table_col_width[7], $record['tags']));

                $h=5*$nb;
                //Page break needed?
                checkPageBreak($h);
                //Draw cells
                $pdf->SetFont('helvetica', '', 8);
                for ($i=0; $i<count($table); $i++) {
                    $w=$table_col_width[$i];
                    $a='L';
                    //actual position
                    $x=$pdf->GetX();
                    $y=$pdf->GetY();
                    //Draw
                    $pdf->Rect($x, $y, $w, $h);
                    //Write
                    $pdf->MultiCell($w, 5, ($record[$table[$i]]), 0, $a);
                    //go to right
                    $pdf->SetXY($x+$w, $y);
                }
                //return to line
                $pdf->Ln($h);
            }

            $pdf_file = "print_out_pdf_".date("Y-m-d", mktime(0, 0, 0, date('m'), date('d'), date('y')))."_".generateKey().".pdf";

            //send the file
            $pdf->Output($_SESSION['settings']['path_to_files_folder']."/".$pdf_file);

            //log
            logEvents('pdf_export', "", $_SESSION['user_id'], $_SESSION['login']);

            //clean table
            DB::query("TRUNCATE TABLE ".prefix_table("export"));

            echo '[{"text":"<a href=\''.$_SESSION['settings']['url_to_files_folder'].'/'.$pdf_file.'\' target=\'_blank\'>'.$LANG['pdf_download'].'</a>"}]';
        }
        break;

    //CASE export in CSV format
    case "export_to_csv_format":
        $full_listing = array();
        $full_listing[0] = array(
            'id' => "id",
            'label' => "label",
            'description' => "description",
            'pw' => "pw",
            'login' => "login",
            'restricted_to' => "restricted_to",
            'perso' => "perso",
            'url' => "url",
            'email' => "email",
            'kbs' => "kb",
            'tags' => "tag"
        );

        $id_managed = '';
        $i = 1;
        $items_id_list = array();

        foreach (explode(';', $_POST['ids']) as $id) {
            if (!in_array($id, $_SESSION['forbiden_pfs']) && in_array($id, $_SESSION['groupes_visibles'])) {
                $rows = DB::query(
                    "SELECT i.id as id, i.restricted_to as restricted_to, i.perso as perso, i.label as label, i.description as description, i.pw as pw, i.login as login, i.url as url, i.email as email,
                       l.date as date, i.pw_iv as pw_iv,
                       n.renewal_period as renewal_period
                    FROM ".prefix_table("items")." as i
                    INNER JOIN ".prefix_table("nested_tree")." as n ON (i.id_tree = n.id)
                    INNER JOIN ".prefix_table("log_items")." as l ON (i.id = l.id_item)
                    WHERE i.inactif = %i
                    AND i.id_tree= %i
                    AND (l.action = %s OR (l.action = %s AND l.raison LIKE %s))
                    ORDER BY i.label ASC, l.date DESC",
                    "0",
                    intval($id),
                    "at_creation",
                    "at_modification",
                    "at_pw :%"
                );
                foreach ($rows as $record) {
                    $restricted_users_array = explode(';', $record['restricted_to']);
                    //exclude all results except the first one returned by query
                    if (empty($id_managed) || $id_managed != $record['id']) {
                        if (
                            (in_array($id, $_SESSION['personal_visible_groups']) && !($record['perso'] == 1 && $_SESSION['user_id'] == $record['restricted_to']) && !empty($record['restricted_to']))
                            ||
                            (!empty($record['restricted_to']) && !in_array($_SESSION['user_id'], $restricted_users_array))
                        ) {
                            //exclude this case
                        } else {
                            //encrypt PW
                            if (!empty($_POST['salt_key']) && isset($_POST['salt_key'])) {
                                $pw = cryption(
                                    $record['pw'],
                                    mysqli_escape_string($link, stripslashes($_POST['salt_key'])),
                                    "decrypt"
                                );
                            } else {
                                $pw = cryption(
                                    $record['pw'],
                                    "",
                                    "decrypt"
                                );
                            }

                            // get KBs
                            $arr_kbs = [];
                            $rows_kb = DB::query(
                                "SELECT b.label, b.id
                                FROM ".prefix_table("kb_items")." AS a
                                INNER JOIN ".prefix_table("kb")." AS b ON (a.kb_id = b.id)
                                WHERE a.item_id = %i",
                                $record['id']
                            );
                             foreach ($rows_kb as $rec_kb) {
                                array_push($arr_kbs, $rec_kb['label']);
                             }

                            // get TAGS
                            $arr_tags = [];
                            $rows_tag = DB::query(
                                "SELECT tag
                                FROM ".prefix_table("tags")."
                                WHERE item_id = %i",
                                $record['id']
                            );
                             foreach ($rows_tag as $rec_tag) {
                                array_push($arr_tags, $rec_tag['tag']);
                             }

                            $full_listing[$i] = array(
                                'id' => $record['id'],
                                'label' => strip_tags(cleanString(html_entity_decode($record['label'], ENT_QUOTES | ENT_XHTML, UTF-8), true)),
                                'description' => htmlspecialchars_decode(addslashes(str_replace(array(";", "<br />"), array("|", "\n\r"), mysqli_escape_string($link, stripslashes(utf8_decode($record['description'])))))),
                                'pw' => html_entity_decode($pw['string'], ENT_QUOTES | ENT_XHTML, UTF-8),
                                'login' => strip_tags(cleanString(html_entity_decode($record['login'], ENT_QUOTES | ENT_XHTML, UTF-8), true)),
                                'restricted_to' => $record['restricted_to'],
                                'perso' => $record['perso'] === "0" ? "False" : "True",
                                'url' => $record['url'] !== "none" ? htmlspecialchars_decode($record['url']) : "",
                                'email' => $record['email'] !== "none" ? htmlspecialchars_decode($record['email']) : "",
                                'kbs' => implode(" | ", $arr_kbs),
                                'tags' => implode(" ", $arr_tags)
                            );
                            $i++;

                            // log
                            logItems(
                                $record['id'],
                                $record['label'],
                                $_SESSION['user_id'],
                                'at_export',
                                $_SESSION['login'],
                                'csv'
                            );
                        }
                    }
                    $id_managed = $record['id'];
                }
            }
        }
        //save the file
        $csv_file = '/print_out_csv_'.time().'_'.generateKey().'.csv';
        //print_r($full_listing);
        $outstream = fopen($_SESSION['settings']['path_to_files_folder'].$csv_file, "w");
        function outPutCsv(&$vals, $key, $filehandler)
        {
            fputcsv($filehandler, $vals, ";"); // add parameters if you want
        }
        array_walk($full_listing, "outPutCsv", $outstream);
        fclose($outstream);

        echo '[{"text":"<a href=\''.$_SESSION['settings']['url_to_files_folder'].$csv_file.'\' target=\'_blank\'>'.$LANG['pdf_download'].'</a>"}]';
        break;

    //CASE export in HTML format
    case "export_to_html_format":
        // step 1:
        // - prepare export file
        // - get full list of objects id to export
        include $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
        require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Encryption/GibberishAES/GibberishAES.php';
        $idsList = array();
        $objNumber = 0;

        foreach (explode(';', $_POST['ids']) as $id) {
            if (!in_array($id, $_SESSION['forbiden_pfs']) && in_array($id, $_SESSION['groupes_visibles'])) {
                // count elements to display
                $result = DB::query(
                    "SELECT i.id AS id, i.label AS label, i.restricted_to AS restricted_to, i.perso AS perso
                    FROM ".prefix_table("items")." as i
                    INNER JOIN ".prefix_table("nested_tree")." as n ON (i.id_tree = n.id)
                    INNER JOIN ".prefix_table("log_items")." as l ON (i.id = l.id_item)
                    WHERE i.inactif = %i
                    AND i.id_tree= %i
                    AND (l.action = %s OR (l.action = %s AND l.raison LIKE %s))
                    ORDER BY i.label ASC, l.date DESC",
                    "0",
                    $id,
                    "at_creation",
                    "at_modification",
                    "at_pw :%"
                );
                foreach ($result as $record) {
                    $restricted_users_array = explode(';', $record['restricted_to']);
                    if (
                        (
                            (in_array($id, $_SESSION['personal_visible_groups']) && !($record['perso'] == 1 && $_SESSION['user_id'] == $record['restricted_to']) && !empty($record['restricted_to']))
                            ||
                            (!empty($record['restricted_to']) && !in_array($_SESSION['user_id'], $restricted_users_array))
                            ||
                            (in_array($id, $_SESSION['groupes_visibles']))
                        ) && (
                            !in_array($record['id'], $idsList)
                        )
                    ) {
                        array_push($idsList, $record['id']);
                        $objNumber++;

                        // log
                        logItems(
                            $record['id'],
                            $record['label'],
                            $_SESSION['user_id'],
                            'at_export',
                            $_SESSION['login'],
                            'html'
                        );
                    }
                }
            }
        }

          // prepare export file
          //save the file
          $html_file = '/teampass_export_'.time().'_'.generateKey().'.html';
          //print_r($full_listing);
          $outstream = fopen($_SESSION['settings']['path_to_files_folder'].$html_file, "w");
          fwrite(
              $outstream,
'<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<title>TeamPass Off-line mode</title>
<style>
body{font-family:sans-serif; font-size:11pt; background:#DCE0E8;}
thead th{font-size:13px; font-weight:bold; background:#344151; padding:4px 10px 4px 10px; font-family:arial; color:#FFFFFF;}
tr.line0 td {background-color:#FFFFFF; border-bottom:1px solid #CCCCCC; font-family:arial; font-size:11px;}
tr.line1 td {background-color:#F0F0F0; border-bottom:1px solid #CCCCCC; font-family:arial; font-size:11px;}
tr.path td {background-color:#C0C0C0; font-family:arial; font-size:11px; font-weight:bold;}
#footer{width: 980px; height: 20px; line-height: 16px; margin: 10px auto 0 auto; padding: 10px; font-family: sans-serif; font-size: 10px; color:#000000;}
#header{padding:10px; font-size:18px; background:#344151; color:#FFFFFF; border:2px solid #222E3D;}
#itemsTable{width:100%;}
#information{margin:10px 0 10px 0; background:#344151; color:#FFFFFF; border:2px solid #222E3D;}
</style>
</head>
<body>
<div id="header">
'.$k['tool_name'].' - Off Line mode
</div>
<div style="margin:10px; font-size:9px;">
<i>This page was generated by <b>'.$_SESSION['name'].' '.$_SESSION['lastname'].'</b>, the '.date("Y/m/d H:i:s").'.</i>
</div>
<div id="information"></div>
<div style="margin:10px;">
Enter the decryption key : <input type="password" id="saltkey" />
</div>
<div>
<table id="itemsTable">
    <thead><tr>
        <th style="width:15%;">'.$LANG['label'].'</th>
        <th style="width:10%;">'.$LANG['pw'].'</th>
        <th style="width:30%;">'.$LANG['description'].'</th>
        <th style="width:5%;">'.$LANG['user_login'].'</th>
        <th style="width:20%;">'.$LANG['url'].'</th>
    </tr></thead>'
          );

        fclose($outstream);

        // send back and continue
        echo '[{"loop":"true", "number":"'.$objNumber.'", "file":"'.$_SESSION['settings']['path_to_files_folder'].$html_file.'" , "file_link":"'.$_SESSION['settings']['url_to_files_folder'].$html_file.'"}]';
        break;

    //CASE export in HTML format - Iteration loop
    case "export_to_html_format_loop":
        // do checks ... if fails, return an error
        if (
            !isset($_POST['idTree']) || !isset($_POST['idsList'])
        ) {
            echo '[{"error":"true"}]';
            break;
        }

        $full_listing = array();
        $items_id_list = array();
        include $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
        require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Encryption/GibberishAES/GibberishAES.php';

        $rows = DB::query(
            "SELECT i.id as id, i.url as url, i.perso as perso, i.label as label, i.description as description, i.pw as pw, i.login as login, i.id_tree as id_tree,
               l.date as date, i.pw_iv as pw_iv,
               n.renewal_period as renewal_period
            FROM ".prefix_table("items")." as i
            INNER JOIN ".prefix_table("nested_tree")." as n ON (i.id_tree = n.id)
            INNER JOIN ".prefix_table("log_items")." as l ON (i.id = l.id_item)
            WHERE i.inactif = %i
            AND i.id_tree= %i
            AND (l.action = %s OR (l.action = %s AND l.raison LIKE %s))
            ORDER BY i.label ASC, l.date DESC",
            "0",
            intval($_POST['idTree']),
            "at_creation",
            "at_modification",
            "at_pw :%"
        );
        //AND i.id_tree IN (".implode(',', $list).")
        foreach ($rows as $record) {
            //exclude all results except the first one returned by query
            if (empty($id_managed) || $id_managed != $record['id']) {
                // decrypt PW
                if (!empty($_POST['salt_key']) && isset($_POST['salt_key'])) {
                    $pw = cryption(
                        $record['pw'],
                        mysqli_escape_string($link, stripslashes($_POST['salt_key'])),
                        "decrypt"
                    );
                } else {
                    $pw = cryption(
                        $record['pw'],
                        "",
                        "decrypt"
                    );
                }
                array_push($full_listing,array(
                    'id_tree' => $record['id_tree'],
                    'id' => $record['id'],
                    'label' => $record['label'],
                    'description' => addslashes(str_replace(array(";", "<br />"), array("|", "\n\r"), mysqli_escape_string($link, stripslashes(utf8_decode($record['description']))))),
                    'pw' => $pw['string'],
                    'login' => $record['login'],
                    'url' => $record['url'],
                    'perso' => $record['perso']
                ));
                $i++;
                array_push($items_id_list,$record['id']);
            }
            $id_managed = $record['id'];
        }

        //save in export file
        $outstream = fopen($_POST['file'], "a");

        $lineType = "line1";
        $idTree = "";
        foreach ($full_listing as $elem) {
            if ($lineType == "line0") {
                $lineType = "line1";
            } else {
                $lineType = "line0";
            }
            if (empty($elem['description'])) {
                $desc = '&nbsp;';
            } else {
                $desc = addslashes($elem['description']);
            }
            if (empty($elem['login'])) {
                $login = '&nbsp;';
            } else {
                $login = addslashes($elem['login']);
            }
            if (empty($elem['url'])) {
                $url = '&nbsp;';
            } else {
                $url = addslashes($elem['url']);
            }

            // Prepare tree
            if ($idTree != $elem['id_tree']) {
                $arbo = $tree->getPath($elem['id_tree'], true);
                foreach ($arbo as $folder) {
                    $arboHtml_tmp = htmlspecialchars(stripslashes($folder->title), ENT_QUOTES);
                    if (empty($arboHtml)) {
                        $arboHtml = $arboHtml_tmp;
                    } else {
                        $arboHtml .= ' » '.$arboHtml_tmp;
                    }
                }
                fputs($outstream, '
        <tr class="path"><td colspan="5">'.$arboHtml.'</td></tr>'
                );
                $idTree = $elem['id_tree'];
            }

            $encPw = GibberishAES::enc($elem['pw'], $_POST['pdf_password']);
            fputs($outstream, '
        <tr class="'.$lineType.'">
            <td>'.addslashes($elem['label']).'</td>
            <td align="center"><span class="span_pw" id="span_'.$elem['id'].'"><a href="#" onclick="decryptme('.$elem['id'].', \''.$encPw.'\');return false;">Decrypt </a></span><input type="hidden" id="hide_'.$elem['id'].'" value="'.$encPw.'" /></td>
            <td>'.$desc.'</td>
            <td align="center">'.$login.'</td>
            <td align="center">'.$url.'</td>
            </tr>'
            );
        }

        fclose($outstream);

        // send back and continue
        echo '[{"loop":"true", "number":"'.$_POST['number'].'", "cpt":"'.$_POST['cpt'].'", "file":"'.$_POST['file'].'", "idsList":"'.$_POST['idsList'].'" , "file_link":"'.$_POST['file_link'].'"}]';
    break;

        //CASE export in HTML format - Iteration loop
    case "export_to_html_format_finalize":
        include $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
        // open file
        $outstream = fopen($_POST['file'], "a");

        fputs(
        $outstream,
        '
    </table></div>
    <input type="button" value="Hide all" onclick="hideAll()" />
    <div id="footer" style="text-align:center;">
        <a href="http://teampass.net/about/" target="_blank" style="">'.$k['tool_name'].'&nbsp;'.$k['version'].'&nbsp;'.$k['copyright'].'</a>
    </div>
    </body>
</html>
<script type="text/javascript">
    function decryptme(id, string)
    {
        if (document.getElementById("saltkey").value != "") {
            var decryptedPw;

            try {
                decryptedPw = GibberishAES.dec(string, document.getElementById("saltkey").value)
            }
            catch(e) {
                alert (e);
                return decryptedPw;
            }

            document.getElementById("span_"+id).innerHTML = decryptedPw +
                "&nbsp;<a href=\"#\" onclick=\"encryptme("+id+")\"><span style=\"font-size:7px;\">[Hide]</span></a>";
        } else {
            alert("Decryption Key is empty!");
        }
    }
    function encryptme(id)
    {
        document.getElementById("span_"+id).innerHTML = "<a href=\"#\" onclick=\"decryptme("+id+", \'"+document.getElementById("hide_"+id).value+"\')\">Decrypt</a>";
    }
    function hideAll()
    {
        var elements = document.getElementsByClassName("span_pw");
        for (var i=0, im=elements.length; im>i; i++) {
            var dataPw = elements[i].id.split("_");
            elements[i].innerHTML = "<a href=\"#\" onclick=\"decryptme("+dataPw[1]+", \'"+document.getElementById("hide_"+dataPw[1]).value+"\')\">Decrypt</a>";
        }
    }
    (function(e,r){"object"==typeof exports?module.exports=r():"function"==typeof define&&define.amd?define(r):e.GibberishAES=r()})(this,function(){"use strict";var e=14,r=8,n=!1,f=function(e){try{return unescape(encodeURIComponent(e))}catch(r){throw"Error on UTF-8 encode"}},c=function(e){try{return decodeURIComponent(escape(e))}catch(r){throw"Bad Key"}},t=function(e){var r,n,f=[];for(16>e.length&&(r=16-e.length,f=[r,r,r,r,r,r,r,r,r,r,r,r,r,r,r,r]),n=0;e.length>n;n++)f[n]=e[n];return f},a=function(e,r){var n,f,c="";if(r){if(n=e[15],n>16)throw"Decryption error: Maybe bad key";if(16===n)return"";for(f=0;16-n>f;f++)c+=String.fromCharCode(e[f])}else for(f=0;16>f;f++)c+=String.fromCharCode(e[f]);return c},o=function(e){var r,n="";for(r=0;e.length>r;r++)n+=(16>e[r]?"0":"")+e[r].toString(16);return n},d=function(e){var r=[];return e.replace(/(..)/g,function(e){r.push(parseInt(e,16))}),r},u=function(e,r){var n,c=[];for(r||(e=f(e)),n=0;e.length>n;n++)c[n]=e.charCodeAt(n);return c},i=function(n){switch(n){case 128:e=10,r=4;break;case 192:e=12,r=6;break;case 256:e=14,r=8;break;default:throw"Invalid Key Size Specified:"+n}},b=function(e){var r,n=[];for(r=0;e>r;r++)n=n.concat(Math.floor(256*Math.random()));return n},h=function(n,f){var c,t=e>=12?3:2,a=[],o=[],d=[],u=[],i=n.concat(f);for(d[0]=L(i),u=d[0],c=1;t>c;c++)d[c]=L(d[c-1].concat(i)),u=u.concat(d[c]);return a=u.slice(0,4*r),o=u.slice(4*r,4*r+16),{key:a,iv:o}},l=function(e,r,n){r=S(r);var f,c=Math.ceil(e.length/16),a=[],o=[];for(f=0;c>f;f++)a[f]=t(e.slice(16*f,16*f+16));for(0===e.length%16&&(a.push([16,16,16,16,16,16,16,16,16,16,16,16,16,16,16,16]),c++),f=0;a.length>f;f++)a[f]=0===f?x(a[f],n):x(a[f],o[f-1]),o[f]=s(a[f],r);return o},v=function(e,r,n,f){r=S(r);var t,o=e.length/16,d=[],u=[],i="";for(t=0;o>t;t++)d.push(e.slice(16*t,16*(t+1)));for(t=d.length-1;t>=0;t--)u[t]=p(d[t],r),u[t]=0===t?x(u[t],n):x(u[t],d[t-1]);for(t=0;o-1>t;t++)i+=a(u[t]);return i+=a(u[t],!0),f?i:c(i)},s=function(r,f){n=!1;var c,t=M(r,f,0);for(c=1;e+1>c;c++)t=g(t),t=y(t),e>c&&(t=k(t)),t=M(t,f,c);return t},p=function(r,f){n=!0;var c,t=M(r,f,e);for(c=e-1;c>-1;c--)t=y(t),t=g(t),t=M(t,f,c),c>0&&(t=k(t));return t},g=function(e){var r,f=n?D:B,c=[];for(r=0;16>r;r++)c[r]=f[e[r]];return c},y=function(e){var r,f=[],c=n?[0,13,10,7,4,1,14,11,8,5,2,15,12,9,6,3]:[0,5,10,15,4,9,14,3,8,13,2,7,12,1,6,11];for(r=0;16>r;r++)f[r]=e[c[r]];return f},k=function(e){var r,f=[];if(n)for(r=0;4>r;r++)f[4*r]=F[e[4*r]]^R[e[1+4*r]]^j[e[2+4*r]]^z[e[3+4*r]],f[1+4*r]=z[e[4*r]]^F[e[1+4*r]]^R[e[2+4*r]]^j[e[3+4*r]],f[2+4*r]=j[e[4*r]]^z[e[1+4*r]]^F[e[2+4*r]]^R[e[3+4*r]],f[3+4*r]=R[e[4*r]]^j[e[1+4*r]]^z[e[2+4*r]]^F[e[3+4*r]];else for(r=0;4>r;r++)f[4*r]=E[e[4*r]]^U[e[1+4*r]]^e[2+4*r]^e[3+4*r],f[1+4*r]=e[4*r]^E[e[1+4*r]]^U[e[2+4*r]]^e[3+4*r],f[2+4*r]=e[4*r]^e[1+4*r]^E[e[2+4*r]]^U[e[3+4*r]],f[3+4*r]=U[e[4*r]]^e[1+4*r]^e[2+4*r]^E[e[3+4*r]];return f},M=function(e,r,n){var f,c=[];for(f=0;16>f;f++)c[f]=e[f]^r[n][f];return c},x=function(e,r){var n,f=[];for(n=0;16>n;n++)f[n]=e[n]^r[n];return f},S=function(n){var f,c,t,a,o=[],d=[],u=[];for(f=0;r>f;f++)c=[n[4*f],n[4*f+1],n[4*f+2],n[4*f+3]],o[f]=c;for(f=r;4*(e+1)>f;f++){for(o[f]=[],t=0;4>t;t++)d[t]=o[f-1][t];for(0===f%r?(d=m(w(d)),d[0]^=K[f/r-1]):r>6&&4===f%r&&(d=m(d)),t=0;4>t;t++)o[f][t]=o[f-r][t]^d[t]}for(f=0;e+1>f;f++)for(u[f]=[],a=0;4>a;a++)u[f].push(o[4*f+a][0],o[4*f+a][1],o[4*f+a][2],o[4*f+a][3]);return u},m=function(e){for(var r=0;4>r;r++)e[r]=B[e[r]];return e},w=function(e){var r,n=e[0];for(r=0;4>r;r++)e[r]=e[r+1];return e[3]=n,e},A=function(e,r){var n,f=[];for(n=0;e.length>n;n+=r)f[n/r]=parseInt(e.substr(n,r),16);return f},C=function(e){var r,n=[];for(r=0;e.length>r;r++)n[e[r]]=r;return n},I=function(e,r){var n,f;for(f=0,n=0;8>n;n++)f=1===(1&r)?f^e:f,e=e>127?283^e<<1:e<<1,r>>>=1;return f},O=function(e){var r,n=[];for(r=0;256>r;r++)n[r]=I(e,r);return n},B=A("637c777bf26b6fc53001672bfed7ab76ca82c97dfa5947f0add4a2af9ca472c0b7fd9326363ff7cc34a5e5f171d8311504c723c31896059a071280e2eb27b27509832c1a1b6e5aa0523bd6b329e32f8453d100ed20fcb15b6acbbe394a4c58cfd0efaafb434d338545f9027f503c9fa851a3408f929d38f5bcb6da2110fff3d2cd0c13ec5f974417c4a77e3d645d197360814fdc222a908846eeb814de5e0bdbe0323a0a4906245cc2d3ac629195e479e7c8376d8dd54ea96c56f4ea657aae08ba78252e1ca6b4c6e8dd741f4bbd8b8a703eb5664803f60e613557b986c11d9ee1f8981169d98e949b1e87e9ce5528df8ca1890dbfe6426841992d0fb054bb16",2),D=C(B),K=A("01020408102040801b366cd8ab4d9a2f5ebc63c697356ad4b37dfaefc591",2),E=O(2),U=O(3),z=O(9),R=O(11),j=O(13),F=O(14),G=function(e,r,n){var f,c=b(8),t=h(u(r,n),c),a=t.key,o=t.iv,d=[[83,97,108,116,101,100,95,95].concat(c)];return e=u(e,n),f=l(e,a,o),f=d.concat(f),T.encode(f)},H=function(e,r,n){var f=T.decode(e),c=f.slice(8,16),t=h(u(r,n),c),a=t.key,o=t.iv;return f=f.slice(16,f.length),e=v(f,a,o,n)},L=function(e){function r(e,r){return e<<r|e>>>32-r}function n(e,r){var n,f,c,t,a;return c=2147483648&e,t=2147483648&r,n=1073741824&e,f=1073741824&r,a=(1073741823&e)+(1073741823&r),n&f?2147483648^a^c^t:n|f?1073741824&a?3221225472^a^c^t:1073741824^a^c^t:a^c^t}function f(e,r,n){return e&r|~e&n}function c(e,r,n){return e&n|r&~n}function t(e,r,n){return e^r^n}function a(e,r,n){return r^(e|~n)}function o(e,c,t,a,o,d,u){return e=n(e,n(n(f(c,t,a),o),u)),n(r(e,d),c)}function d(e,f,t,a,o,d,u){return e=n(e,n(n(c(f,t,a),o),u)),n(r(e,d),f)}function u(e,f,c,a,o,d,u){return e=n(e,n(n(t(f,c,a),o),u)),n(r(e,d),f)}function i(e,f,c,t,o,d,u){return e=n(e,n(n(a(f,c,t),o),u)),n(r(e,d),f)}function b(e){for(var r,n=e.length,f=n+8,c=(f-f%64)/64,t=16*(c+1),a=[],o=0,d=0;n>d;)r=(d-d%4)/4,o=8*(d%4),a[r]=a[r]|e[d]<<o,d++;return r=(d-d%4)/4,o=8*(d%4),a[r]=a[r]|128<<o,a[t-2]=n<<3,a[t-1]=n>>>29,a}function h(e){var r,n,f=[];for(n=0;3>=n;n++)r=255&e>>>8*n,f=f.concat(r);return f}var l,v,s,p,g,y,k,M,x,S=[],m=A("67452301efcdab8998badcfe10325476d76aa478e8c7b756242070dbc1bdceeef57c0faf4787c62aa8304613fd469501698098d88b44f7afffff5bb1895cd7be6b901122fd987193a679438e49b40821f61e2562c040b340265e5a51e9b6c7aad62f105d02441453d8a1e681e7d3fbc821e1cde6c33707d6f4d50d87455a14eda9e3e905fcefa3f8676f02d98d2a4c8afffa39428771f6816d9d6122fde5380ca4beea444bdecfa9f6bb4b60bebfbc70289b7ec6eaa127fad4ef308504881d05d9d4d039e6db99e51fa27cf8c4ac5665f4292244432aff97ab9423a7fc93a039655b59c38f0ccc92ffeff47d85845dd16fa87e4ffe2ce6e0a30143144e0811a1f7537e82bd3af2352ad7d2bbeb86d391",8);for(S=b(e),y=m[0],k=m[1],M=m[2],x=m[3],l=0;S.length>l;l+=16)v=y,s=k,p=M,g=x,y=o(y,k,M,x,S[l+0],7,m[4]),x=o(x,y,k,M,S[l+1],12,m[5]),M=o(M,x,y,k,S[l+2],17,m[6]),k=o(k,M,x,y,S[l+3],22,m[7]),y=o(y,k,M,x,S[l+4],7,m[8]),x=o(x,y,k,M,S[l+5],12,m[9]),M=o(M,x,y,k,S[l+6],17,m[10]),k=o(k,M,x,y,S[l+7],22,m[11]),y=o(y,k,M,x,S[l+8],7,m[12]),x=o(x,y,k,M,S[l+9],12,m[13]),M=o(M,x,y,k,S[l+10],17,m[14]),k=o(k,M,x,y,S[l+11],22,m[15]),y=o(y,k,M,x,S[l+12],7,m[16]),x=o(x,y,k,M,S[l+13],12,m[17]),M=o(M,x,y,k,S[l+14],17,m[18]),k=o(k,M,x,y,S[l+15],22,m[19]),y=d(y,k,M,x,S[l+1],5,m[20]),x=d(x,y,k,M,S[l+6],9,m[21]),M=d(M,x,y,k,S[l+11],14,m[22]),k=d(k,M,x,y,S[l+0],20,m[23]),y=d(y,k,M,x,S[l+5],5,m[24]),x=d(x,y,k,M,S[l+10],9,m[25]),M=d(M,x,y,k,S[l+15],14,m[26]),k=d(k,M,x,y,S[l+4],20,m[27]),y=d(y,k,M,x,S[l+9],5,m[28]),x=d(x,y,k,M,S[l+14],9,m[29]),M=d(M,x,y,k,S[l+3],14,m[30]),k=d(k,M,x,y,S[l+8],20,m[31]),y=d(y,k,M,x,S[l+13],5,m[32]),x=d(x,y,k,M,S[l+2],9,m[33]),M=d(M,x,y,k,S[l+7],14,m[34]),k=d(k,M,x,y,S[l+12],20,m[35]),y=u(y,k,M,x,S[l+5],4,m[36]),x=u(x,y,k,M,S[l+8],11,m[37]),M=u(M,x,y,k,S[l+11],16,m[38]),k=u(k,M,x,y,S[l+14],23,m[39]),y=u(y,k,M,x,S[l+1],4,m[40]),x=u(x,y,k,M,S[l+4],11,m[41]),M=u(M,x,y,k,S[l+7],16,m[42]),k=u(k,M,x,y,S[l+10],23,m[43]),y=u(y,k,M,x,S[l+13],4,m[44]),x=u(x,y,k,M,S[l+0],11,m[45]),M=u(M,x,y,k,S[l+3],16,m[46]),k=u(k,M,x,y,S[l+6],23,m[47]),y=u(y,k,M,x,S[l+9],4,m[48]),x=u(x,y,k,M,S[l+12],11,m[49]),M=u(M,x,y,k,S[l+15],16,m[50]),k=u(k,M,x,y,S[l+2],23,m[51]),y=i(y,k,M,x,S[l+0],6,m[52]),x=i(x,y,k,M,S[l+7],10,m[53]),M=i(M,x,y,k,S[l+14],15,m[54]),k=i(k,M,x,y,S[l+5],21,m[55]),y=i(y,k,M,x,S[l+12],6,m[56]),x=i(x,y,k,M,S[l+3],10,m[57]),M=i(M,x,y,k,S[l+10],15,m[58]),k=i(k,M,x,y,S[l+1],21,m[59]),y=i(y,k,M,x,S[l+8],6,m[60]),x=i(x,y,k,M,S[l+15],10,m[61]),M=i(M,x,y,k,S[l+6],15,m[62]),k=i(k,M,x,y,S[l+13],21,m[63]),y=i(y,k,M,x,S[l+4],6,m[64]),x=i(x,y,k,M,S[l+11],10,m[65]),M=i(M,x,y,k,S[l+2],15,m[66]),k=i(k,M,x,y,S[l+9],21,m[67]),y=n(y,v),k=n(k,s),M=n(M,p),x=n(x,g);return h(y).concat(h(k),h(M),h(x))},T=function(){var e="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",r=e.split(""),n=function(e){var n,f,c=[],t="";for(Math.floor(16*e.length/3),n=0;16*e.length>n;n++)c.push(e[Math.floor(n/16)][n%16]);for(n=0;c.length>n;n+=3)t+=r[c[n]>>2],t+=r[(3&c[n])<<4|c[n+1]>>4],t+=void 0!==c[n+1]?r[(15&c[n+1])<<2|c[n+2]>>6]:"=",t+=void 0!==c[n+2]?r[63&c[n+2]]:"=";for(f=t.slice(0,64)+"\n",n=1;Math.ceil(t.length/64)>n;n++)f+=t.slice(64*n,64*n+64)+(Math.ceil(t.length/64)===n+1?"":"\n");return f},f=function(r){r=r.replace(/\n/g,"");var n,f=[],c=[],t=[];for(n=0;r.length>n;n+=4)c[0]=e.indexOf(r.charAt(n)),c[1]=e.indexOf(r.charAt(n+1)),c[2]=e.indexOf(r.charAt(n+2)),c[3]=e.indexOf(r.charAt(n+3)),t[0]=c[0]<<2|c[1]>>4,t[1]=(15&c[1])<<4|c[2]>>2,t[2]=(3&c[2])<<6|c[3],f.push(t[0],t[1],t[2]);return f=f.slice(0,f.length-f.length%16)};return"function"==typeof Array.indexOf&&(e=r),{encode:n,decode:f}}();return{size:i,h2a:d,expandKey:S,encryptBlock:s,decryptBlock:p,Decrypt:n,s2a:u,rawEncrypt:l,rawDecrypt:v,dec:H,openSSLKey:h,a2h:o,enc:G,Hash:{MD5:L},Base64:T}});
</script>');

        fclose($outstream);

        echo '[{"text":"<a href=\''.$_POST['file_link'].'\' target=\'_blank\'>'.$LANG['pdf_download'].'</a>"}]';
        break;
}

//SPECIFIC FUNCTIONS FOR FPDF
function checkPageBreak($h)
{
    global $pdf;
    //Continue on a new page if needed
    if ($pdf->GetY()+$h>$pdf->PageBreakTrigger) {
        $pdf->addPage($pdf->CurOrientation);
    }
}

function nbLines($w, $txt)
{
    global $pdf;
    //Calculate the number of lines needed by a Multicell with a width of w
    $cw=&$pdf->CurrentFont['cw'];
    if ($w==0) {
        $w=$pdf->w-$this->rMargin-$pdf->x;
    }
    $wmax=($w-2*$pdf->cMargin)*1000/$pdf->FontSize;
    $s=str_replace("\r", '', $txt);
    $nb=strlen($s);
    if ($nb>0 and $s[$nb-1]=="\n") {
        $nb--;
    }
    $sep=-1;
    $i=0;
    $j=0;
    $l=0;
    $nl=1;
    while ($i<$nb) {
        $c=$s[$i];
        if ($c=="\n") {
            $i++;
            $sep=-1;
            $j=$i;
            $l=0;
            $nl++;
            continue;
        }
        if ($c==' ') {
            $sep=$i;
        }
        $l+=550;//$cw[$c];
        //echo $cw[$c].";".$wmax.";".$l."|";
        if ($l>$wmax) {
            if ($sep==-1) {
                if ($i==$j) {
                    $i++;
                }
            } else {
                $i=$sep+1;
            }
            $sep=-1;
            $j=$i;
            $l=0;
            $nl++;
        } else {
            $i++;
        }
    }

    return $nl;
}

