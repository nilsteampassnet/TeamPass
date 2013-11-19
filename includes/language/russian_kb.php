<?php
//RUSSIAN
if (!isset($_SESSION['settings']['cpassman_url'])) {
	$TeamPass_url = '';
}else{
	$TeamPass_url = $_SESSION['settings']['cpassman_url'];
}


$txt['category'] = "Категория";
$txt['kb'] = "База Знаний";
$txt['kb_anyone_can_modify'] = "Всем разрешено вносить изменения";
$txt['kb_form'] = "Manage entries in KB";
$txt['new_kb'] = "Add a new KB";
?>
