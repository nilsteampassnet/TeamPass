<?php
//SPANISH
if (!isset($_SESSION['settings']['cpassman_url'])) {
	$TeamPass_url = '';
}else{
	$TeamPass_url = $_SESSION['settings']['cpassman_url'];
}


$LANG['category'] = "Categoría";
$LANG['kb'] = "Base de Conocimiento";
$LANG['kb_anyone_can_modify'] = "Cualquiera puede modificarlo";
$LANG['kb_form'] = "Administrar entradas en la KB";
$LANG['new_kb'] = "Agregar una nueva KB";
?>
