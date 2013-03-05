<?php

require_once("jCryption.php");

$keyLength = 1024;
$jCryption = new jCryption();

$numberOfPairs = 100;
$arrKeyPairs = array();

for ($i=0; $i < $numberOfPairs; $i++) {
	$arrKeyPairs[] = $jCryption->generateKeypair($keyLength);
}

$file = array();
$file[] = '<?php';
$file[] = '$arrKeys = ';
$file[] = var_export($arrKeyPairs, true);
$file[] = ';';

file_put_contents($numberOfPairs . "_". $keyLength . "_keys.inc.php", implode("\n", $file));