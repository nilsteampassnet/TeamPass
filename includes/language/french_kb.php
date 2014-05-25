<?php
//FRENCH
if (!isset($_SESSION['settings']['cpassman_url'])) {
	$TeamPass_url = '';
}else{
	$TeamPass_url = $_SESSION['settings']['cpassman_url'];
}


$LANG['category'] = "Catégorie";
$LANG['kb'] = "Base de Connaissances";
$LANG['kb_anyone_can_modify'] = "Modifiable par tout un chacun";
$LANG['kb_form'] = "Gérer les entrées";
$LANG['new_kb'] = "Ajouter une nouvelle entrée";
?>
