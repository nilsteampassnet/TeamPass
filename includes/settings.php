<?php
global $lang, $txt, $k, $pathTeampas, $urlTeampass, $pwComplexity, $mngPages;
global $server, $user, $pass, $database, $pre, $db;

### DATABASE connexion parameters ###
$server = "localhost";
$user = "root";
$pass = "";
$database = "tp";
$pre = "teampass_";

@date_default_timezone_set($_SESSION['settings']['timezone']);
@define('SECUREPATH', 'E:/xampp/security');
require_once "E:/xampp/security/sk.php";
@define('COST', '13'); // Don't change this.