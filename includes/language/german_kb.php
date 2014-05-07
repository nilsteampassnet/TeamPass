<?php
//GERMAN
if (!isset($_SESSION['settings']['cpassman_url'])) {
	$TeamPass_url = '';
}else{
	$TeamPass_url = $_SESSION['settings']['cpassman_url'];
}


$LANG['category'] = "Kategorie";
$LANG['kb'] = "Wissensdatenbank";
$LANG['kb_anyone_can_modify'] = "Jeder darf ändern";
$LANG['kb_form'] = "Einträge in Wissensdatenbak verwalten";
$LANG['new_kb'] = "Neue Wissensdatenbank hinzufügen";
?>
