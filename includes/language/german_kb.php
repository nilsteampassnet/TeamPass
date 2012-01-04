<?php
//GERMAN
if (!isset($_SESSION['settings']['cpassman_url'])) {
	$TeamPass_url = '';
}else{
	$TeamPass_url = $_SESSION['settings']['cpassman_url'];
}


$txt['category'] = "Kategorie";
$txt['kb'] = "Wissensdatenbank";
$txt['kb_anyone_can_modify'] = "Jeder darf ändern";
$txt['kb_form'] = "Einträge in Wissensdatenbak verwalten";
$txt['new_kb'] = "Neue Wissensdatenbank hinzufügen";
?>
