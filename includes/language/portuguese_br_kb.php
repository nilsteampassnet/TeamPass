<?php
//PORTUGUESE
if (!isset($_SESSION['settings']['cpassman_url'])) {
	$TeamPass_url = '';
}else{
	$TeamPass_url = $_SESSION['settings']['cpassman_url'];
}


$LANG['category'] = "Categoria";
$LANG['kb'] = "Base de conhecimento";
$LANG['kb_anyone_can_modify'] = "Qualquer um pode modificar";
$LANG['kb_form'] = "Edita entradas no KB";
$LANG['new_kb'] = "Adiciona novo KB";
?>
