<?php
/**
 * @file          export.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.13
 * @copyright     (c) 2009-2012 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

global $k, $settings;
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
header("Content-type: text/html; charset=utf-8");
error_reporting(E_ERROR);
require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

// connect to DB
$db = new SplClassLoader('Database\Core', '../includes/libraries');
$db->register();
$db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
$db->connect();

//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

//User's language loading
$k['langage'] = @$_SESSION['user_language'];
require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';

//Manage type of action asked
switch ($_POST['type']) {
    //CASE export to PDF format
    case "export_to_pdf_format":
        $full_listing = array();

        foreach (explode(';', $_POST['ids']) as $id) {
            if (!in_array($id, $_SESSION['forbiden_pfs']) && in_array($id, $_SESSION['groupes_visibles'])) {
                $rows = $db->fetchAllArray(
                    "SELECT i.id as id, i.restricted_to as restricted_to, i.perso as perso, i.label as label, i.description as description, i.pw as pw, i.login as login,
                    l.date as date,
                    n.renewal_period as renewal_period,
                    k.rand_key
                    FROM ".$pre."items as i
                    INNER JOIN ".$pre."nested_tree as n ON (i.id_tree = n.id)
                    INNER JOIN ".$pre."log_items as l ON (i.id = l.id_item)
                    INNER JOIN ".$pre."keys as k ON (i.id = k.id)
                    WHERE i.inactif = 0
                    AND i.id_tree=".$id."
                    AND (l.action = 'at_creation' OR (l.action = 'at_modification' AND l.raison LIKE 'at_pw :%'))
                    ORDER BY i.label ASC, l.date DESC"
                );

                $id_managed = '';
                $i = 0;
                $items_id_list = array();
                foreach ($rows as $reccord) {
                    $restricted_users_array = explode(';', $reccord['restricted_to']);
                    //exclude all results except the first one returned by query
                    if (empty($id_managed) || $id_managed != $reccord['id']) {
                        if (
                        (in_array($id, $_SESSION['personal_visible_groups']) && !($reccord['perso'] == 1 && $_SESSION['user_id'] == $reccord['restricted_to']) && !empty($reccord['restricted_to']))
                        ||
                        (!empty($reccord['restricted_to']) && !in_array($_SESSION['user_id'], $restricted_users_array))
                        ) {
                            //exclude this case
                        } else {
                            //encrypt PW
                            if (!empty($_POST['salt_key']) && isset($_POST['salt_key'])) {
                                $pw = decrypt($reccord['pw'], mysql_real_escape_string(stripslashes($_POST['salt_key'])));
                            } else {
                                $pw = decrypt($reccord['pw']);
                            }
                            /*$full_listing[$reccord['id']] = array(
                               'id' => $reccord['id'],
                               'label' => $reccord['label'],
                               'pw' => substr(addslashes($pw), strlen($reccord['rand_key'])),
                               'login' => $reccord['login']
                            );*/
                            $full_listing[$id][$reccord['id']] = array($reccord['label'], $reccord['login'], substr(addslashes($pw), strlen($reccord['rand_key'])), $reccord['description']);
                        }
                    }
                    $id_managed = $reccord['id'];
                }
            }
        }

        $tree->rebuild();
        // get node paths for table headers
        foreach ($full_listing as $key => $val) {
            $folders = $tree->getPath($key, true);
            $path = "";
            foreach ($folders as $val) {
                if ($path) {
                    $path .= " » ";
                }
                $path .= $val->title;
            }
            $paths[$key] = $path;
        }

        //Build PDF
        if (!empty($full_listing)) {
            //Some variables
            $table_full_width = 190;
            $table_col_width = array(45, 40, 45, 60);

            //Prepare the PDF file
            include $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Pdf/Tfpdf/fpdf.php';
            $pdf=new FPDF_Protection();
            $pdf->SetProtection(array('print'), $_POST['pdf_password']);

            //Add font for regular text
            $pdf->AddFont('DejaVu', '', 'DejaVuSansCondensed.ttf', true);
            //Add monospace font for passwords
            $pdf->AddFont('LiberationMono', '');

            $pdf->aliasNbPages();
            $pdf->addPage();
            $pdf->SetFont('DejaVu', '', 16);
            $pdf->Cell(0, 10, $txt['print_out_pdf_title'], 0, 1, 'C', false);
            $pdf->SetFont('DejaVu', '', 12);
            $pdf->Cell(0, 10, $txt['pdf_del_date']." ".date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], time()).' '.$txt['by'].' '.$_SESSION['login'], 0, 1, 'C', false);

            foreach ($full_listing as $key => $val) {
                $printed_ids[] = $key;
                $pdf->SetFont('DejaVu', '', 10);
                $pdf->SetFillColor(192, 192, 192);
                error_log('key: '.$key.' - paths: '.$paths[$key]);
                $pdf->cell(0, 6, $paths[$key], 1, 1, "L", 1);
                $pdf->SetFillColor(222, 222, 222);
                $pdf->cell(45, 6, $txt['label'], 1, 0, "C", 1);
                $pdf->cell(40, 6, $txt['login'], 1, 0, "C", 1);
                $pdf->cell(45, 6, $txt['pw'], 1, 0, "C", 1);
                $pdf->cell(60, 6, $txt['description'], 1, 1, "C", 1);
                foreach ($val as $item) {
                    //row height calculus
                    $nb = 0;
                    for ($i=0; $i<count($item); $i++) {
                        if ($i==3) {
                            $item[$i] = html_entity_decode(htmlspecialchars_decode(str_replace("<br />", "\n", $item[$i]), ENT_QUOTES));
                        }
                        $nb=max($nb, nbLines($table_col_width[$i], $item[$i]));
                    }
                    $h=5*$nb;
                    //Page break needed?
                    checkPageBreak($h);
                    //Draw cells
                    $pdf->SetFont('DejaVu', '', 9);
                    for ($i=0; $i<count($item); $i++) {
                        $w=$table_col_width[$i];
                        $a='L';
                        //actual position
                        $x=$pdf->GetX();
                        $y=$pdf->GetY();
                        //Draw
                        $pdf->Rect($x, $y, $w, $h);
                        //Write
                        /*if ($i == 2) {
                            // change font for password
                            $pdf->SetFont('LiberationMono', '', 9);
                        } else {
                            $pdf->SetFont('DejaVu', '', 9);
                        }*/
                        if ($i==3) {
                            $item[$i] = html_entity_decode(htmlspecialchars_decode(str_replace("<br />", "\n", $item[$i]), ENT_QUOTES));
                        }
                        $pdf->MultiCell($w, 5, $item[$i], 0, $a);
                        //go to right
                        $pdf->SetXY($x+$w, $y);
                    }
                    //return to line
                    $pdf->Ln($h);
                }
            }

            $pdf_file = "print_out_pdf_".date("Y-m-d", mktime(0, 0, 0, date('m'), date('d'), date('y')))."_".generateKey().".pdf";
            //send the file
            $pdf->Output($_SESSION['settings']['path_to_files_folder']."/".$pdf_file);

            //log
            logEvents('pdf_export', implode(';', $printed_ids), $_SESSION['user_id']);

            echo '[{"text":"<a href=\''.$_SESSION['settings']['url_to_files_folder'].'/'.$pdf_file.'\' target=\'_blank\'>'.$txt['pdf_download'].'</a>"}]';
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
            'perso' => "perso"
        );

        $id_managed = '';
        $i = 1;
        $items_id_list = array();

        foreach (explode(';', $_POST['ids']) as $id) {
            if (!in_array($id, $_SESSION['forbiden_pfs']) && in_array($id, $_SESSION['groupes_visibles'])) {
                $rows = $db->fetchAllArray(
                    "SELECT i.id as id, i.restricted_to as restricted_to, i.perso as perso, i.label as label, i.description as description, i.pw as pw, i.login as login,
                       l.date as date,
                       n.renewal_period as renewal_period,
                       k.rand_key
                    FROM ".$pre."items as i
                    INNER JOIN ".$pre."nested_tree as n ON (i.id_tree = n.id)
                    INNER JOIN ".$pre."log_items as l ON (i.id = l.id_item)
                    INNER JOIN ".$pre."keys as k ON (i.id = k.id)
                    WHERE i.inactif = 0
                    AND i.id_tree=".$id."
                    AND (l.action = 'at_creation' OR (l.action = 'at_modification' AND l.raison LIKE 'at_pw :%'))
                    ORDER BY i.label ASC, l.date DESC"
                );
                foreach ($rows as $reccord) {
                    $restricted_users_array = explode(';', $reccord['restricted_to']);
                    //exclude all results except the first one returned by query
                    if (empty($id_managed) || $id_managed != $reccord['id']) {
                        if (
                            (in_array($id, $_SESSION['personal_visible_groups']) && !($reccord['perso'] == 1 && $_SESSION['user_id'] == $reccord['restricted_to']) && !empty($reccord['restricted_to']))
                            ||
                            (!empty($reccord['restricted_to']) && !in_array($_SESSION['user_id'], $restricted_users_array))
                        ) {
                            //exclude this case
                        } else {
                            //encrypt PW
                            if (!empty($_POST['salt_key']) && isset($_POST['salt_key'])) {
                                $pw = decrypt($reccord['pw'], mysql_real_escape_string(stripslashes($_POST['salt_key'])));
                            } else {
                                $pw = decrypt($reccord['pw']);
                            }
                            $full_listing[$i] = array(
                                'id' => $reccord['id'],
                                'label' => $reccord['label'],
                                'description' => addslashes(str_replace(array(";", "<br />"), array("|", "\n\r"), mysql_real_escape_string(stripslashes(utf8_decode($reccord['description']))))),
                                'pw' => substr(addslashes($pw), strlen($reccord['rand_key'])),
                                'login' => $reccord['login'],
                                'restricted_to' => $reccord['restricted_to'],
                                'perso' => $reccord['perso']
                            );
                            $i++;
                        }
                    }
                    $id_managed = $reccord['id'];
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

        echo '[{"text":"<a href=\''.$_SESSION['settings']['url_to_files_folder'].$csv_file.'\' target=\'_blank\'>'.$txt['pdf_download'].'</a>"}]';
        break;
}

//SPECIFIC FUNCTIONS FOR FPDF
function checkPageBreak($h)
{
    global $pdf;
    //Continu on a new page if needed
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
