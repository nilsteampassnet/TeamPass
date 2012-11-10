<?php
global $lang, $txt, $k, $chemin_passman, $url_passman, $pw_complexity, $mngPages;
global $server, $user, $pass, $database, $pre, $db;

@define('SALT', 'whateveryouwant'); //Define your encryption key => NeverChange it once it has been used !!!!!

### DATABASE connexion parameters ###
$server = "localhost";
$user = "teampass";
$pass = "LuLnj2BATQLRtGzD";
$database = "teampass";
$pre = "teampass_";

@date_default_timezone_set($_SESSION['settings']['timezone']);
?>