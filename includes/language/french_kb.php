<?php
//FRENCH
if (!isset($_SESSION['settings']['cpassman_url'])) {
	$TeamPass_url = '';
}else{
	$TeamPass_url = $_SESSION['settings']['cpassman_url'];
}


$txt['category'] = "Catégorie";
$txt['kb'] = "Base de Connaissances";
$txt['kb_anyone_can_modify'] = "Modifiable par tout un chacun";
$txt['kb_form'] = "Gérer les entrées";
$txt['new_kb'] = "Ajouter une nouvelle entrée";
?>
