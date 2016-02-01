<?php

//name of HTTP POST variable for authentication
define("CSRFP_TOKEN","csrfp_token");

/**
 * child exception classes
 */
class configFileNotFoundException extends \exception {};
class logDirectoryNotFoundException extends \exception {};
class jsFileNotFoundException extends \exception {};
class logFileWriteError extends \exception {};
class baseJSFileNotFoundExceptio extends \exception {};

class csrfProtector
{
	/**
	 * expiry time for cookie
	 * @var int
	 */
	public static $cookieExpiryTime = 1800;	//30 minutes

	/**
	 * flag for cross origin/same origin request
	 * @var bool
	 */
	private static $isSameOrigin = true;

	/**
	 * flag to check if output file is a valid HTML or not
	 * @var bool
	 */
	private static $isValidHTML = false;

	/**
	 * Varaible to store weather request type is post or get
	 * @var string
	 */
	protected static $requestType = "GET";

	/**
	 * config file for CSRFProtector
	 * @var int Array, length = 6
	 * @property #1: failedAuthAction (int) => action to be taken in case autherisation fails
	 * @property #2: logDirectory (string) => directory in which log will be saved
	 * @property #3: customErrorMessage (string) => custom error message to be sent in case
	 *						of failed authentication
	 * @property #4: jsFile (string) => location of the CSRFProtector js file
	 * @property #5: tokenLength (int) => default length of hash
	 * @property #6: disabledJavascriptMessage (string) => error message if client's js is disabled
	 */
	public static $config = array();

	/**
	 * function to initialise the csrfProtector work flow
	 * @parameters: variables to override default configuration loaded from file
	 *
	 * @param $length - length of CSRF_AUTH_TOKEN to be generated
	 * @param $action - int array, for different actions to be taken in case of failed validation
	 *
	 * @return void
	 *
	 * @throw configFileNotFoundException			
	 */
	public static function init($length = null, $action = null)
	{
		/**
		 * if mod_csrfp already enabled, no verification, no filtering
		 * Already done by mod_csrfp
		 */
		if (getenv('mod_csrfp_enabled')) {
			return;			
		}

		//start session in case its not
		if (session_id() == '') {
			require_once('sources/sessions.php');
		    session_start();
		}

		if (!file_exists(__DIR__ ."/../csrfp.config.php")) {
			throw new configFileNotFoundException("configuration file not found for CSRFProtector!");	
		}

		//load configuration file and properties
		self::$config = include(__DIR__ ."/../csrfp.config.php");

		//overriding length property if passed in parameters
		if ($length !== null) {
			self::$config['tokenLength'] = intval($length);
		}
		
		//action that is needed to be taken in case of failed authorisation
		if ($action !== null) {
			self::$config['failedAuthAction'] = $action;
		}

		if (self::$config['CSRFP_TOKEN'] == '')
			self::$config['CSRFP_TOKEN'] = CSRFP_TOKEN;	

		//authorise the incoming request
		self::authorisePost();

		// Initialize output buffering handler
		ob_start('csrfProtector::ob_handler');

		if (!isset($_COOKIE[self::$config['CSRFP_TOKEN']])
			|| !isset($_SESSION[self::$config['CSRFP_TOKEN']]))
			self::refreshToken();
	}

	/**
	 * Function to check weather to use cached version of js
	 * 		file or not
	 *
	 * @param void
	 *
	 * @return, bool -- true if cacheversion can be used
	 *					-- false otherwise
	 */
	public static function useCachedVersion()
	{
		$configLastModified = filemtime(__DIR__ ."/../csrfp.config.php");
		if (file_exists(__DIR__ ."/../" .self::$config['jsPath'])) {
			$jsFileLastModified = filemtime(__DIR__ ."/../" 
				.self::$config['jsPath']);
			if ($jsFileLastModified < $configLastModified) {
				// -- config is more recent than js file
				return false;
			}
			return true;
		} else {
			return false;
		}
		
	}

	/**
	 * Function to create new cache version of js
	 *
	 * @param void
	 *
	 * @return void
	 *
	 * @throw baseJSFileNotFoundExceptio
	 */
	public static function createNewJsCache()
	{
		if (!file_exists(__DIR__ ."/csrfpJsFileBase.php")) {
			throw new baseJSFileNotFoundExceptio("base js file needed to create js file not found at " .__DIR__);
			return;;
		}

		$jsFile = file_get_contents(__DIR__ ."/csrfpJsFileBase.php");
		$arrayStr = '';
		if (self::$config['verifyGetFor']) {
			foreach (self::$config['verifyGetFor'] as $key => $value) {
				if ($key !== 0) $arrayStr .= ',';
				$arrayStr .= "'". $value ."'";
			}
		}
		$jsFile = str_replace('$$tokenName$$', self::$config['CSRFP_TOKEN'], $jsFile);
		$jsFile = str_replace('$$getAllowedUrls$$', $arrayStr, $jsFile);
		file_put_contents(__DIR__ ."/../" .self::$config['jsPath'], $jsFile);
	}

	/**
	 * function to authorise incoming post requests
	 * @param void
	 * @return void
	 * @throw logDirectoryNotFoundException
	 */
	public static function authorisePost()
	{
		//#todo this method is valid for same origin request only, 
		//enable it for cross origin also sometime
		//for cross origin the functionality is different
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {

			//set request type to POST
			self::$requestType = "POST";

			//currently for same origin only
			if (!(isset($_POST[self::$config['CSRFP_TOKEN']]) 
				&& isset($_SESSION[self::$config['CSRFP_TOKEN']])
				&& ($_POST[self::$config['CSRFP_TOKEN']] === $_SESSION[self::$config['CSRFP_TOKEN']])
				)) {

				//action in case of failed validation
				self::failedValidationAction();			
			} else {
				self::refreshToken();	//refresh token for successfull validation
			}
		} else if (!static::isURLallowed()) {
			
			//currently for same origin only
			if (!(isset($_GET[self::$config['CSRFP_TOKEN']]) 
				&& isset($_SESSION[self::$config['CSRFP_TOKEN']])
				&& ($_GET[self::$config['CSRFP_TOKEN']] === $_SESSION[self::$config['CSRFP_TOKEN']])
				)) {

				//action in case of failed validation
				self::failedValidationAction();			
			} else {
				self::refreshToken();	//refresh token for successfull validation
			}
		}	
	}

	/**
	 * function to be called in case of failed validation
	 * performs logging and take appropriate action
	 * @param: void
	 * @return: void
	 */
	private static function failedValidationAction()
	{
		if (!file_exists(__DIR__ ."/../" .self::$config['logDirectory'])) {
			throw new logDirectoryNotFoundException("Log Directory Not Found!");		
		}
	
		//call the logging function
		static::logCSRFattack();

		//#todo: ask mentors if $failedAuthAction is better as an int or string
		//default case is case 0
		switch (self::$config['failedAuthAction'][self::$requestType]) {
			case 0:
				//send 403 header
				header('HTTP/1.0 403 Forbidden');
				exit("<h2>403 Access Forbidden by CSRFProtector!</h2>");
				break;
			case 1:
				//unset the query parameters and forward
				if (self::$requestType === 'GET') {
					$_GET = array();
				} else {
					$_POST = array();
				}
				break;
			case 2:
				//redirect to custom error page
				$location  = self::$config['errorRedirectionPage'];
				header("location: $location");
			case 3:
				//send custom error message
				exit(self::$config['customErrorMessage']);
				break;
			case 4:
				//send 500 header -- internal server error
				header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
				exit("<h2>500 Internal Server Error!</h2>");
				break;
			default:
				//unset the query parameters and forward
				if (self::$requestType === 'GET') {
					$_GET = array();
				} else {
					$_POST = array();
				}
				break;
		}		
	}

	/**
	 * function to set auth cookie 
	 * @param: void
	 * @return void
	 */
	public static function refreshToken()
	{
		$token = self::generateAuthToken();

		//set token to session for server side validation
		$_SESSION[self::$config['CSRFP_TOKEN']] = $token;

		//set token to cookie for client side processing
		setcookie(self::$config['CSRFP_TOKEN'], 
			$token, 
			time() + self::$cookieExpiryTime);
	}

	/**
	 * function to generate random hash of length as given in parameter
	 * max length = 128
	 * @param: length to hash required, int
	 * @return string
	 */
	public static function generateAuthToken()
	{
		//if config tokenLength value is 0 or some non int
		if (intval(self::$config['tokenLength']) === 0) {
			self::$config['tokenLength'] = 32;	//set as default
		}

		//if $length > 128 throw exception #todo 

		if (function_exists("hash_algos") && in_array("sha512", hash_algos())) {
			$token = hash("sha512", mt_rand(0, mt_getrandmax()));
		} else {
			$token = '';
			for ($i = 0; $i < 128; ++$i) {
				$r = mt_rand(0, 35);
				if ($r < 26) {
					$c = chr(ord('a') + $r);
				} else { 
					$c = chr(ord('0') + $r - 26);
				}
				$token .= $c;
			}
		}
		return substr($token, 0, self::$config['tokenLength']);
	}

	/**
	 * Rewrites <form> on the fly to add CSRF tokens to them. This can also
	 * inject our JavaScript library.
	 * @param: $buffer, output buffer to which all output are stored
	 * @param: flag
	 * @return string, complete output buffer
	 */
	public static function ob_handler($buffer, $flags)
	{
		// Even though the user told us to rewrite, we should do a quick heuristic
	    // to check if the page is *actually* HTML. We don't begin rewriting until
	    // we hit the first <html tag.
	    if (!self::$isValidHTML) {
	        // not HTML until proven otherwise
	        if (stripos($buffer, '<html') !== false) {
	            self::$isValidHTML = true;
	        } else {
	            return $buffer;
	        }
	    }
	    
	    //add a <noscript> message to outgoing HTML output,
	    //informing the user to enable js for CSRFProtector to work
	    //best section to add, after <body> tag
	    $buffer = preg_replace("/<body[^>]*>/", "$0 <noscript>" .self::$config['disabledJavascriptMessage'] .
	    	"</noscript>", $buffer);

	    $arrayStr = '';
	    if (!self::useCachedVersion()) {
	    	try {
	    		self::createNewJsCache();
	    	} catch (exception $ex) {
	    		if (self::$config['verifyGetFor']) {
					foreach (self::$config['verifyGetFor'] as $key => $value) {
						if ($key !== 0) $arrayStr .= ',';
						$arrayStr .= "'". $value ."'";
					}
				}
	    	}
	    }

	    $script = '<script type="text/javascript" src="' .self::$config['jsUrl']
	    	.'"></script>' .PHP_EOL;

	    $script .= '<script type="text/javascript">' .PHP_EOL;
	    if ($arrayStr !== '') {
	    	$script .= 'CSRFP.checkForUrls = [' .$arrayStr .'];' .PHP_EOL;
	    }
	    $script .= 'window.onload = function() {' .PHP_EOL;
	    $script .= '	csrfprotector_init();' .PHP_EOL;
	    $script .= '};' .PHP_EOL;
	    $script .= '</script>' .PHP_EOL;

	    //implant the CSRFGuard js file to outgoing script
	    $buffer = str_ireplace('</body>', $script . '</body>', $buffer, $count);
	    if (!$count) {
	        $buffer .= $script;
	    }

	    return $buffer;
	}

	/**
	 * Functio to log CSRF Attack
	 * @param: void
	 * @retrun: void
	 * @throw: logFileWriteError
	 */
	private static function logCSRFattack()
	{
		//if file doesnot exist for, create it
		$logFile = fopen(__DIR__ ."/../" .self::$config['logDirectory']
		."/" .date("m-20y") .".log", "a+");
		
		//throw exception if above fopen fails
		if (!$logFile) {
			throw new logFileWriteError("Unable to write to the log file");	
		}

		//miniature version of the log
		$log = array();
		$log['timestamp'] = time();
		$log['HOST'] = $_SERVER['HTTP_HOST'];
		$log['REQUEST_URI'] = $_SERVER['REQUEST_URI'];
		$log['requestType'] = self::$requestType;

		if (self::$requestType === "GET") {
			$log['query'] = $_GET;
		} else {
			$log['query'] = $_POST;
		}

		$log['cookie'] = $_COOKIE;

		//convert log array to JSON format to be logged
		$log = json_encode($log) .PHP_EOL;

		//append log to the file
		fwrite($logFile, $log);

		//close the file handler
		fclose($logFile);
	}

	/**
	 * Function to return current url of executing page
	 * @param: void
	 * @return: string, current url
	 */
	private static function getCurrentUrl()
	{
		return $_SERVER['REQUEST_SCHEME'] .'://'
			.$_SERVER['HTTP_HOST'] .$_SERVER['PHP_SELF'];
	}

	/**
	 * Function to check if current url mataches for any urls
	 * Listed in config file
	 * @param: void
	 * @return: boolean, true is url need no validation, false if validation needed
	 */ 
	public static function isURLallowed() {
		foreach (self::$config['verifyGetFor'] as $key => $value) {
			$value = str_replace(array('/','*'), array('\/','(.*)'), $value);
			preg_match('/' .$value .'/', self::getCurrentUrl(), $output);
			if (count($output) > 0) {
				return false;
			}
		}
		return true;
	}
};
