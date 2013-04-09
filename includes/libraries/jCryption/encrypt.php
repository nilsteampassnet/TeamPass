<?php
/**
 *
 * @file          encrypt.php
 * @author        Nils Laumaillé
 * @version       2.1.17
 * @copyright     (c) 2009-2013 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link
 */

// Start the session so we can use PHP sessions
session_start();
// Include the jCryption library
require_once("jcryption.php");
require_once("../../settings.php");
require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
// Set the RSA key length
$keyLength = 1024;
// Create a jCrytion object
$jCryption = new jCryption();

// If the GET parameter "generateKeypair" is set
if (isset($_GET["generateKeypair"])) {
    // Do some tests on server
    if (!file_exists(SECUREPATH."/100_1024_keys.inc.php")) {
        echo '{"e":"encryption_cfg_error","msg":"'.
            htmlspecialchars(strip_tags($txt['channel_encryption_no_file']), ENT_QUOTES).
            '"}';
    } elseif (!extension_loaded('openssl')) {
        echo '{"e":"encryption_cfg_error","msg":"'.
            htmlspecialchars(strip_tags($txt['channel_encryption_no_openssl']), ENT_QUOTES).
            '"}';
    } elseif (!extension_loaded('gmp')) {
        echo '{"e":"encryption_cfg_error","msg":"'.
            htmlspecialchars(strip_tags($txt['channel_encryption_no_gmp']), ENT_QUOTES).
            '"}';
    } elseif (!extension_loaded('bcmath')) {
        echo '{"e":"encryption_cfg_error","msg":"'.
            htmlspecialchars(strip_tags($txt['channel_encryption_no_bcmath']), ENT_QUOTES).
            '"}';
    } elseif (!extension_loaded('iconv')) {
        echo '{"e":"encryption_cfg_error","msg":"'.
            htmlspecialchars(strip_tags($txt['channel_encryption_no_iconv']), ENT_QUOTES).
            '"}';
    } else {
    	// Include some RSA keys
    	require_once(SECUREPATH."/100_1024_keys.inc.php");
    	// Pick a random RSA key from the array
    	$keys = $arrKeys[mt_rand(0, 100)];
    	// Save the RSA keypair into the session
    	$_SESSION["e"] = array("int" => $keys["e"], "hex" => $jCryption->dec2string($keys["e"], 16));
    	$_SESSION["d"] = array("int" => $keys["d"], "hex" => $jCryption->dec2string($keys["d"], 16));
    	$_SESSION["n"] = array("int" => $keys["n"], "hex" => $jCryption->dec2string($keys["n"], 16));
    	// Create an array containing the RSA keypair
    	$arrOutput = array(
    		"e" => $_SESSION["e"]["hex"],
    		"n" => $_SESSION["n"]["hex"],
    		"maxdigits" => intval($keyLength*2/16+3)
    	);
    	// JSON encode the RSA keypair
    	echo json_encode($arrOutput);
    }
} elseif (isset($_GET["handshake"])) {
    // If the GET parameter "handshake" is set
	// Decrypt the AES key with the RSA key
	$key = $jCryption->decrypt($_POST['key'], $_SESSION["d"]["int"], $_SESSION["n"]["int"]);
	// Removed the RSA key from the session
	unset($_SESSION["e"]);
	unset($_SESSION["d"]);
	unset($_SESSION["n"]);
	// Save the AES key into the session
	$_SESSION["encKey"] = $key;
	// JSON encohe the challenge
	echo json_encode(array("challenge" => AesCtr::encrypt($key, $key, 256)));
}