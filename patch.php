<?php
session_start();

include './includes/settings.php';
include './sources/main.functions.php';

require_once './sources/SplClassLoader.php';

// connect to the server
$db = new SplClassLoader('Database\Core', './includes/libraries');
$db->register();
$db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
$db->connect();

$rows = $db->fetchAllArray("SELECT id, pw FROM ".$pre."items WHERE perso = '0'");
foreach ($rows as $reccord) {
	$pw = decrypt($reccord['pw']);
	if (!empty($pw) && strlen($pw) > 30 && isutf8($pw)) {

		$key = substr($pw, 0, 15);
		$pw = substr($pw,30);

		$pw = $key . $pw;

		$pw = encrypt($pw);

		$db->queryUpdate(
			'items',
			array(
			    'pw' => $pw
			),
			"id='".$reccord['id']."'"
		);
	}
}

?>