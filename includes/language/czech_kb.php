<?php
//CZECH
if (!isset($_SESSION['settings']['cpassman_url'])) {
	$TeamPass_url = '';
}else{
	$TeamPass_url = $_SESSION['settings']['cpassman_url'];
}


$txt['category'] = "Kategorie";
$txt['kb'] = "Databáze znalostí";
$txt['kb_anyone_can_modify'] = "Může být upravováno kýmkoliv";
$txt['kb_form'] = "Správa položek v databázi znalostí";
$txt['new_kb'] = "Přidat novou databázi znalostí";
?>
