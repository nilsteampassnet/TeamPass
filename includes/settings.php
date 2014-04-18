<?php
global $lang, $txt, $k, $pathTeampas, $urlTeampass, $pwComplexity, $mngPages;
global $server, $user, $pass, $database, $pre, $db;

### DATABASE connexion parameters ###
$server = "localhost";
$user = "root";
$pass = "";
$database = "tpssl";
$pre = "teampass_";

@date_default_timezone_set($_SESSION['settings']['timezone']);
@define('SECUREPATH', 'C:/nils.laumaille/utils/xamppssl/security');
require_once "C:/nils.laumaille/utils/xamppssl/security/sk.php";
@define('COST', '13'); // Don't change this.