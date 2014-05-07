<?php
//ITALIAN
if (!isset($_SESSION['settings']['cpassman_url'])) {
	$TeamPass_url = '';
}else{
	$TeamPass_url = $_SESSION['settings']['cpassman_url'];
}


$LANG['help_on_folders'] = "<div class='ui-state-highlight ui-corner-all' style='padding:5px;font-weight:bold;'>";
$LANG['help_on_roles'] = "<div class='ui-state-highlight ui-corner-all' style='padding:5px;font-weight:bold;'>";
$LANG['help_on_users'] = "<div class='ui-state-highlight ui-corner-all' style='padding:5px;font-weight:bold;'>";
?>
