<?php
global $lang, $txt, $k, $pathTeampas, $urlTeampass, $pwComplexity, $mngPages;
global $server, $user, $pass, $database, $pre, $db;

### DATABASE connexion parameters ###
$server = "localhost";
$user = "teampass";
$pass = "rhUx9EwLNC7TvZHb";
$database = "teampass";
$pre = "teampass_";

@date_default_timezone_set($_SESSION['settings']['timezone']);
@define('SECUREPATH', '/opt/teampass/secret/');
require_once "/opt/teampass/secret//sk.php";
?>