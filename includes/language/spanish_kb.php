<?php
//SPANISH
if (!isset($_SESSION['settings']['cpassman_url'])) {
	$TeamPass_url = '';
}else{
	$TeamPass_url = $_SESSION['settings']['cpassman_url'];
}


$txt['category'] = "Categoria";
$txt['kb'] = "Base de Conocimiento";
$txt['kb_anyone_can_modify'] = "Cualquiera puede modificarlo";
$txt['kb_form'] = "Administrar entradas en la KB";
$txt['new_kb'] = "Agregar una nueva KB";
?>
