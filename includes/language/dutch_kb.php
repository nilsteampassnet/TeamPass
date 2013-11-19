<?php
//DUTCH
if (!isset($_SESSION['settings']['cpassman_url'])) {
	$TeamPass_url = '';
}else{
	$TeamPass_url = $_SESSION['settings']['cpassman_url'];
}


$txt['category'] = "Categorie";
$txt['kb'] = "Knownledge Base";
$txt['kb_anyone_can_modify'] = "Iedereen kan dit aanpassen";
$txt['kb_form'] = "Beheer gegevens in KB";
$txt['new_kb'] = "Voeg een nieuwe KB toe";
?>
