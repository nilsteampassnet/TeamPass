<?php
//TURKISH
if (!isset($_SESSION['settings']['cpassman_url'])) {
	$TeamPass_url = '';
}else{
	$TeamPass_url = $_SESSION['settings']['cpassman_url'];
}


$LANG['category'] = "Kategori";
$LANG['kb'] = "Knowledge Base";
$LANG['kb_anyone_can_modify'] = "Anyone can modify it";
$LANG['kb_form'] = "Manage entries in KB";
$LANG['new_kb'] = "Add a new KB";
?>
