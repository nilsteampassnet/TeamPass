<?php

include_once 'axs/AXSILPortal_V1_Auth.php';

if (!session_id())
	session_start();



$GIFUrl = "axs/axs/code2gif.php?code=";

//initialize api client
$agses = new AXSILPortal_V1_Auth();
$agses->setUrl('https://portal.icsl.at/agses/');
$agses->setAAId('sm0agses');
//for release there will be another api-key - this is temporary only
$agses->setApiKey('1390e9ac6ae2c26ca20677c382079b91');

$agses->create();

//create random salt and store it into session
if (!isset($_SESSION['hedgeId']) || $_SESSION['hedgeId'] == "") {
	$_SESSION['hedgeId'] = md5(time());
}

//sample agses card serial number (here you have to add your card number)
$agses_apn = "132000460342";	


//create auth message for given serial number
$_SESSION['flickercode'] = $agses->createAuthenticationMessage($agses_apn, true, 1, 2, $_SESSION['hedgeId']);			

$flickering = $_SESSION['flickercode'];

//display flickering and form to submit response code
echo "<pre>".$flickering."</pre>";

echo '<img id="agses_flickering" src="'.$GIFUrl.$flickering.'" width="460" height="120" alt="AGSES Flicker Code" title="Animated GIF Flicker Code"><br/>';

echo '<form method="get">'; 

echo '<input type="text" name="response_code">';

echo '<input type="submit" name="submit">';

echo '</form>';


if (isset($_GET['response_code'])) {

	//verifying response code
	$responseCode = htmlspecialchars_decode($_GET['response_code']);

	if ($responseCode != "" && strlen($responseCode) >= 4) {					
	
		// Verify response code, store result in session
		$result = $agses->verifyResponse($agses_apn, $responseCode, $_SESSION['hedgeId']);
		
		if ($result == 1) {	
			//succesfully logged in			
			$return = "";
			$logError = "";
			unset($_SESSION['hedgeId']);
			unset($_SESSION['flickercode']);
		} else {
			//error loggin in
			//more details to error_codes 'axs/AXSErrorcodes.inc.php'
			if ($result < -10) { 
				$logError =  "ERROR: ".$result;
			} else if ($result == -4) { 
				$logError =  "Wrong response code, no more tries left.";
			} else if ($result == -3) { 
				$logError =  "Wrong response code, try to reenter.";
			} else if ($result == -2) { 
				$logError =  "Timeout. The response code is not valid anymore.";
			} else if ($result == -1) { 
				$logError =  "Security Error. Did you try to verify the response from a different computer?";
			} else if ($result == 1) { 
				$logError =  "Authentication successful, response code correct. 
					  <br /><br />Authentification Method for SecureBrowser updated!";
				// Add necessary code here for accessing your Business Application
			}
			$return = "agses_error";
			echo '[{"value" : "'.$return.'", "user_admin":"',
			isset($_SESSION['user_admin']) ? $_SESSION['user_admin'] : "",
			'", "initial_url" : "'.@$_SESSION['initial_url'].'",
			"error" : "'.$logError.'"}]';
		
			die();	
		}			

	} else {
		
		$return = "agses_error";
		$logError = "No response code given";		
		
		echo '[{"value" : "'.$return.'", "user_admin":"',
		isset($_SESSION['user_admin']) ? $_SESSION['user_admin'] : "",
		'", "initial_url" : "'.@$_SESSION['initial_url'].'",
		"error" : "'.$logError.'"}]';
	
		die();			

	}
	

}

?>