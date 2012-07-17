<?php
/**
 * @file 		export.queries.php
 * @author		Nils Laumaillé
 * @version 	2.1
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

global $k, $settings;
include('../includes/settings.php');
header("Content-type: text/html; charset=utf-8");
error_reporting (E_ERROR);
require_once('main.functions.php');

// connect to the server
    require_once("class.database.php");
    $db = new Database($server, $user, $pass, $database, $pre);
    $db->connect();

//User's language loading
$k['langage'] = @$_SESSION['user_language'];
require_once('../includes/language/'.$_SESSION['user_language'].'.php');

//Manage type of action asked
switch($_POST['type'])
{
	//CASE export to PDF format
    case "export_to_pdf_format":
    	$full_listing = array();

    	foreach (explode(';', $_POST['ids']) as $id){
    		if (!in_array($id, $_SESSION['forbiden_pfs']) && in_array($id, $_SESSION['groupes_visibles'])) {

	   			$rows = $db->fetch_all_array("
	                   SELECT i.id AS id, i.restricted_to AS restricted_to, i.perso AS perso, i.label AS label, i.description AS description, i.pw AS pw, i.login AS login,
	                       l.date AS date,
	                       n.renewal_period AS renewal_period,
	                       k.rand_key
	                   FROM ".$pre."items AS i
	                   INNER JOIN ".$pre."nested_tree AS n ON (i.id_tree = n.id)
	                   INNER JOIN ".$pre."log_items AS l ON (i.id = l.id_item)
	                   INNER JOIN ".$pre."keys AS k ON (i.id = k.id)
	                   WHERE i.inactif = 0
	                   AND i.id_tree=".$id."
	                   AND (l.action = 'at_creation' OR (l.action = 'at_modification' AND l.raison LIKE 'at_pw :%'))
	                   ORDER BY i.label ASC, l.date DESC
                ");

	   			$id_managed = '';
	   			$i = 0;
	   			$items_id_list = array();
	   			foreach( $rows as $reccord ) {
                    $restricted_users_array = explode(';',$reccord['restricted_to']);
	   				//exclude all results except the first one returned by query
	   				if ( empty($id_managed) || $id_managed != $reccord['id'] ){
	   					if (
                            (in_array($id, $_SESSION['personal_visible_groups']) && !($reccord['perso'] == 1 && $_SESSION['user_id'] == $reccord['restricted_to']) && !empty($reccord['restricted_to']))
                            ||
                            (!empty($reccord['restricted_to']) && !in_array($_SESSION['user_id'],$restricted_users_array))
                        ){
	   						//exclude this case
	   					}else {
	   						//encrypt PW
	   						if ( !empty($_POST['salt_key']) && isset($_POST['salt_key']) ){
	   							$pw = decrypt($reccord['pw'], mysql_real_escape_string(stripslashes($_POST['salt_key'])));
	   						}else
	   							$pw = decrypt($reccord['pw']);

	   						$full_listing[$reccord['id']] = array(
		   						'id' => $reccord['id'],
		   						'label' => $reccord['label'],
		   						'pw' => substr(addslashes($pw), strlen($reccord['rand_key'])),
		   						'login' => $reccord['login']
							);
	   					}
	    			}
	   				$id_managed = $reccord['id'];
	   			}
   			}
    	}

    	//Build PDF
    	if (!empty($full_listing)) {
    		//Prepare the PDF file
    		include('../includes/libraries/tfpdf/tfpdf.php');
    		$pdf=new tFPDF();

    		//Add font for utf-8
    		$pdf->AddFont('DejaVu','','DejaVuSansCondensed.ttf',true);

    		$pdf->AliasNbPages();
    		$pdf->AddPage();
    		$pdf->SetFont('DejaVu','',16);
    		$pdf->Cell(0,10,$txt['print_out_pdf_title'],0,1,'C',false);
    		$pdf->SetFont('DejaVu','',12);
    		$pdf->Cell(0,10,$txt['pdf_del_date']." ".date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'],mktime(date("H"),date("i"),date("s"),date("m"),date("d"),date("Y"))).' '.$txt['by'].' '.$_SESSION['login'],0,1,'C',false);
    		$pdf->SetFont('DejaVu','',10);
    		$pdf->SetFillColor(192,192,192);
    		$pdf->cell(65,6,$txt['label'],1,0,"C",1);
    		$pdf->cell(55,6,$txt['login'],1,0,"C",1);
    		$pdf->cell(70,6,$txt['pw'],1,1,"C",1);
    		$pdf->SetFont('DejaVu','',9);

    		foreach( $full_listing as $item ){
   				$pdf->cell(65,6,stripslashes($item['label']),1,0,"L");
   				$pdf->cell(55,6,stripslashes($item['login']),1,0,"C");
   				$pdf->cell(70,6,stripslashes($item['pw']),1,1,"C");
    		}

    		$pdf_file = "print_out_pdf_".date("Y-m-d",mktime(0,0,0,date('m'),date('d'),date('y'))).".pdf";
    		//send the file
    		$pdf->Output($_SESSION['settings']['cpassman_dir']."/files/".$pdf_file);

    		echo '[{"text":"<a href=\''.$_SESSION['settings']['cpassman_url'].'/files/'.$pdf_file.'\' target=\'_blank\'>'.$txt['pdf_download'].'</a>"}]';
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

    	foreach (explode(';', $_POST['ids']) as $id){
    		if (!in_array($id, $_SESSION['forbiden_pfs']) && in_array($id, $_SESSION['groupes_visibles'])) {

	   			$rows = $db->fetch_all_array("
	                   SELECT i.id AS id, i.restricted_to AS restricted_to, i.perso AS perso, i.label AS label, i.description AS description, i.pw AS pw, i.login AS login,
	                       l.date AS date,
	                       n.renewal_period AS renewal_period,
	                       k.rand_key
	                   FROM ".$pre."items AS i
	                   INNER JOIN ".$pre."nested_tree AS n ON (i.id_tree = n.id)
	                   INNER JOIN ".$pre."log_items AS l ON (i.id = l.id_item)
	                   INNER JOIN ".$pre."keys AS k ON (i.id = k.id)
	                   WHERE i.inactif = 0
	                   AND i.id_tree=".$id."
	                   AND (l.action = 'at_creation' OR (l.action = 'at_modification' AND l.raison LIKE 'at_pw :%'))
	                   ORDER BY i.label ASC, l.date DESC
                ");
	   			foreach( $rows as $reccord ) {
                    $restricted_users_array = explode(';',$reccord['restricted_to']);
	   				//exclude all results except the first one returned by query
	   				if ( empty($id_managed) || $id_managed != $reccord['id'] ){
	   					if (
                            (in_array($id, $_SESSION['personal_visible_groups']) && !($reccord['perso'] == 1 && $_SESSION['user_id'] == $reccord['restricted_to']) && !empty($reccord['restricted_to']))
                            ||
                            (!empty($reccord['restricted_to']) && !in_array($_SESSION['user_id'],$restricted_users_array))
                        ){
	   						//exclude this case
	   					}else {
	   						//encrypt PW
	   						if ( !empty($_POST['salt_key']) && isset($_POST['salt_key']) ){
	   							$pw = decrypt($reccord['pw'], mysql_real_escape_string(stripslashes($_POST['salt_key'])));
	   						}else
	   							$pw = decrypt($reccord['pw']);

	   						$full_listing[$i] = array(
		   						'id' => $reccord['id'],
		   						'label' => $reccord['label'],
		   						'description' => addslashes(str_replace(array(";","<br />"),array("|",""),stripslashes(html_entity_decode($reccord['description'],ENT_QUOTES)))),
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
    	$csv_file = $_SESSION['settings']['cpassman_dir'].'/files/print_out_csv_'.time().'.csv';
print_r($full_listing);
    	$outstream = fopen($csv_file, "w");
    	function __outputCSV(&$vals, $key, $filehandler) {
    		fputcsv($filehandler, $vals,";"); // add parameters if you want
    	}
    	array_walk($full_listing, "__outputCSV", $outstream);
    	fclose($outstream);

		echo '[{"text":"<a href=\''.$csv_file.'\' target=\'_blank\'>'.$txt['pdf_download'].'</a>"}]';
	break;
}

?>