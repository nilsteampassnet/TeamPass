<?php
//RUSSIAN
if (!isset($_SESSION['settings']['cpassman_url'])) {
	$TeamPass_url = '';
}else{
	$TeamPass_url = $_SESSION['settings']['cpassman_url'];
}


$LANG['category'] = "Категория";
$LANG['kb'] = "База Знаний";
$LANG['kb_anyone_can_modify'] = "Всем разрешено вносить изменения";
$LANG['kb_form'] = "Manage entries in KB";
$LANG['new_kb'] = "Add a new KB";
?>
