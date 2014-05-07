<?php
//DUTCH
if (!isset($_SESSION['settings']['cpassman_url'])) {
	$TeamPass_url = '';
}else{
	$TeamPass_url = $_SESSION['settings']['cpassman_url'];
}


$LANG['category'] = "Categorie";
$LANG['kb'] = "Knownledge Base";
$LANG['kb_anyone_can_modify'] = "Iedereen kan dit aanpassen";
$LANG['kb_form'] = "Beheer gegevens in KB";
$LANG['new_kb'] = "Voeg een nieuwe KB toe";
?>
