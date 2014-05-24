<?php
//ITALIAN
if (!isset($_SESSION['settings']['cpassman_url'])) {
	$TeamPass_url = '';
}else{
	$TeamPass_url = $_SESSION['settings']['cpassman_url'];
}


$LANG['category'] = "Categoria";
$LANG['kb'] = "Knowledge Base";
$LANG['kb_anyone_can_modify'] = "Chiunque può modificare";
$LANG['kb_form'] = "Gestisci voci in KB";
$LANG['new_kb'] = "Aggiungi una voce in KB";
?>
