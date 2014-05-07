<?php
//CZECH
if (!isset($_SESSION['settings']['cpassman_url'])) {
	$TeamPass_url = '';
}else{
	$TeamPass_url = $_SESSION['settings']['cpassman_url'];
}


$LANG['category'] = "Kategorie";
$LANG['kb'] = "Databáze znalostí";
$LANG['kb_anyone_can_modify'] = "Může být upravováno kýmkoliv";
$LANG['kb_form'] = "Správa položek v databázi znalostí";
$LANG['new_kb'] = "Přidat novou databázi znalostí";
?>
