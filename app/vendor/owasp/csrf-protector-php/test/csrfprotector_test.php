<?php
use PHPUnit\Framework\TestCase;

date_default_timezone_set('UTC');
require_once __DIR__ .'/../libs/csrf/csrfprotector.php';
require_once __DIR__ .'/../libs/csrf/csrfpDefaultLogger.php';
require_once __DIR__ .'/testHelpers.php';
require_once __DIR__ .'/fakeLogger.php';

if (intval(phpversion('tidy')) >= 7 
    && !class_exists('\PHPUnit_Framework_TestCase', true)) {
    class_alias('\PHPUnit\Framework\TestCase', '\PHPUnit_Framework_TestCase');
}

/**
 * main test class
 */
class csrfp_test extends PHPUnit_Framework_TestCase {
    /**
     * @var array to hold current configurations
     */
    protected $config = array();

    /**
     * Function to be run before every test*() functions.
     */
    public function setUp() {
        csrfprotector::$config['CSRFP_TOKEN'] = 'CSRFP-Token';
        csrfprotector::$config['cookieConfig'] = array('secure' => false);

        $_SERVER['REQUEST_URI'] = 'temp';       // For logging
        $_SERVER['REQUEST_SCHEME'] = 'http';    // For authorizePost
        $_SERVER['HTTP_HOST'] = 'test';         // For isUrlAllowed
        $_SERVER['PHP_SELF'] = '/index.php';     // For authorizePost
        $_POST[csrfprotector::$config['CSRFP_TOKEN']]
          = $_GET[csrfprotector::$config['CSRFP_TOKEN']] = '123';

        //token mismatch - leading to failed validation
        $_SESSION[csrfprotector::$config['CSRFP_TOKEN']] = array('abc');
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['HTTPS'] = null;

        $this->config = include(__DIR__ .'/config.test.php');

        // Create an instance of config file -- for testing
        $data = file_get_contents(__DIR__ .'/config.test.php');
        file_put_contents(__DIR__ .'/../libs/config.php', $data);

        if (!defined('__CSRFP_UNIT_TEST__')) define('__CSRFP_UNIT_TEST__', true);
    }

    /**
     * tearDown()
     */
    public function tearDown() {
        unlink(__DIR__ .'/../libs/config.php');
    }

    /**
     * Function to check refreshToken() functionality
     */
    public function testRefreshToken() {
        $val = $_COOKIE[csrfprotector::$config['CSRFP_TOKEN']] = '123abcd';
        $_SESSION[csrfprotector::$config['CSRFP_TOKEN']] = array('123abcd');
        csrfProtector::$config['tokenLength'] = 20;
        csrfProtector::refreshToken();

        $this->assertTrue(
            strcmp($val, $_SESSION[csrfprotector::$config['CSRFP_TOKEN']][1]) != 0);

        $this->assertTrue(csrfp_wrapper::checkHeader('Set-Cookie'));
        $this->assertTrue(csrfp_wrapper::checkHeader('CSRFP-Token'));
        $this->assertTrue(
            csrfp_wrapper::checkHeader(
                $_SESSION[csrfprotector::$config['CSRFP_TOKEN']][1]));
    }

    /**
     * Function to check cookieConfig class
     */
    public function testCookieConfigClass() {
        $cfg = array(
            "path" => "abcd",
            "secure" => true,
            "domain" => "abcd",
            "expire" => 600,
        );

        // simple test
        $cookieConfig = new csrfpCookieConfig($cfg);
        $this->assertEquals("abcd", $cookieConfig->path);
        $this->assertEquals("abcd", $cookieConfig->domain);
        $this->assertEquals(true, $cookieConfig->secure);
        $this->assertEquals(600, $cookieConfig->expire);

        // default value test
        $cookieConfig = new csrfpCookieConfig(array());
        $this->assertEquals('', $cookieConfig->path);
        $this->assertEquals('', $cookieConfig->domain);
        $this->assertEquals(false, $cookieConfig->secure);
        $this->assertEquals(1800, $cookieConfig->expire);

        // secure as string
        $cookieConfig = new csrfpCookieConfig(array('secure' => 'true'));
        $this->assertEquals(true, $cookieConfig->secure);
        $cookieConfig = new csrfpCookieConfig(array('secure' => 'false'));
        $this->assertEquals(true, $cookieConfig->secure);

        // expire as string
        $cookieConfig = new csrfpCookieConfig(array('expire' => '600'));
        $this->assertEquals(600, $cookieConfig->expire);
        $cookieConfig = new csrfpCookieConfig(array('expire' => ''));
        $this->assertEquals(1800, $cookieConfig->expire);
    }

    /**
     * test secure flag is set in the token cookie when requested
     */
    public function testSecureCookie() {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION[csrfprotector::$config['CSRFP_TOKEN']] = array('123abcd');
        csrfProtector::$config['tokenLength'] = 20;

        // this one would generally fails, as init was already called and now private static
        // property is set with secure as false;
        $csrfp = new csrfProtector;
        $reflection = new \ReflectionClass(get_class($csrfp));
        $property = $reflection->getProperty('cookieConfig');
        $property->setAccessible(true);

        // change value to false
        $property->setValue($csrfp, new csrfpCookieConfig(array('secure' => false)));
        csrfprotector::refreshToken();
        $this->assertNotRegExp('/; secure/', csrfp_wrapper::getHeaderValue('Set-Cookie'));

        // change value to true
        $property->setValue($csrfp, new csrfpCookieConfig(array('secure' => true)));
        csrfprotector::refreshToken();
        $this->assertRegExp('/; secure/', csrfp_wrapper::getHeaderValue('Set-Cookie'));
    }

    /**
     * test secure flag is set in the token cookie when requested
     */
    public function testCookieExpireTime() {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION[csrfprotector::$config['CSRFP_TOKEN']] = array('123abcd');
        csrfProtector::$config['tokenLength'] = 20;

        // this one would generally fails, as init was already called and now private static
        // property is already set;
        $csrfp = new csrfProtector;
        $reflection = new \ReflectionClass(get_class($csrfp));
        $property = $reflection->getProperty('cookieConfig');
        $property->setAccessible(true);

        // change value to 600
        $property->setValue($csrfp, new csrfpCookieConfig(array('expire' => 600)));
        csrfprotector::refreshToken();
        // Check the expire date to the nearest minute in case the seconds does not match during test execution
        $this->assertRegExp('/; expires=' . date('D, d-M-Y H:i', time() + 600) . ':\d\d GMT;?/', csrfp_wrapper::getHeaderValue('Set-Cookie'));
        if(version_compare(phpversion(), '5.5', '>=')) {
            $this->assertRegExp('/; Max-Age=600/', csrfp_wrapper::getHeaderValue('Set-Cookie'));
        }
    }

    /**
     * test authorise post -> action = 403, forbidden
     */
    public function testAuthorisePost_failedAction_1() {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        csrfprotector::$config['verifyGetFor'] = array('http://test/index*');
        csrfprotector::$config['failedAuthAction']['POST'] = 0;
        csrfprotector::$config['failedAuthAction']['GET'] = 0;

        //csrfprotector::authorizePost();
        $this->markTestSkipped('Cannot add tests as code exit here');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        csrfp_wrapper::changeRequestType('GET');
        //csrfprotector::authorizePost();

        $this->markTestSkipped('Cannot add tests as code exit here');
    }

    /**
     * test authorise post -> strip $_GET, $_POST
     */
    public function testAuthorisePost_failedAction_2() {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $csrfp = new csrfProtector;
        $fakeLogger = $this->setFakeLogger($csrfp);

        csrfprotector::$config['verifyGetFor'] = array('http://test/index*');
        csrfprotector::$config['failedAuthAction']['POST'] = 1;
        csrfprotector::$config['failedAuthAction']['GET'] = 1;

        $_POST = array('param1' => 1, 'param2' => 2);
        csrfprotector::authorizePost();
        $this->assertEmpty($_POST);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        csrfp_wrapper::changeRequestType('GET');
        $_GET = array('param1' => 1, 'param2' => 2);

        csrfprotector::authorizePost();
        $this->assertEmpty($_GET);
    }

    /**
     * test authorise post -> redirect
     */
    public function testAuthorisePost_failedAction_3() {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        csrfprotector::$config['verifyGetFor'] = array('http://test/index*');
        csrfprotector::$config['errorRedirectionPage'] = 'http://test';
        csrfprotector::$config['failedAuthAction']['POST'] = 2;
        csrfprotector::$config['failedAuthAction']['GET'] = 2;

        //csrfprotector::authorizePost();
        $this->markTestSkipped('Cannot add tests as code exit here');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        csrfp_wrapper::changeRequestType('GET');
        //csrfprotector::authorizePost();
        $this->markTestSkipped('Cannot add tests as code exit here');
    }

    /**
     * test authorise post -> error message & exit
     */
    public function testAuthorisePost_failedAction_4() {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        csrfprotector::$config['verifyGetFor'] = array('http://test/index*');
        csrfprotector::$config['customErrorMessage'] = 'custom error message';
        csrfprotector::$config['failedAuthAction']['POST'] = 3;
        csrfprotector::$config['failedAuthAction']['POST'] = 3;

        //csrfprotector::authorizePost();
        $this->markTestSkipped('Cannot add tests as code exit here');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        csrfp_wrapper::changeRequestType('GET');
        //csrfprotector::authorizePost();
        $this->markTestSkipped('Cannot add tests as code exit here');
    }

    /**
     * test authorise post -> 500 internal server error
     */
    public function testAuthorisePost_failedAction_5() {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        csrfprotector::$config['verifyGetFor'] = array('http://test/index*');
        csrfprotector::$config['failedAuthAction']['POST'] = 4;
        csrfprotector::$config['failedAuthAction']['GET'] = 4;

        //csrfprotector::authorizePost();
        //$this->markTestSkipped('Cannot add tests as code exit here');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        csrfp_wrapper::changeRequestType('GET');
        //csrfprotector::authorizePost();
        //csrfp_wrapper::checkHeader('500');
        //$this->markTestSkipped('Cannot add tests as code exit here');
    }

    /**
     * test authorise post -> default action: strip $_GET, $_POST
     */
    public function testAuthorisePost_failedAction_6() {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $csrfp = new csrfProtector;
        $fakeLogger = $this->setFakeLogger($csrfp);

        csrfprotector::$config['verifyGetFor'] = array('http://test/index*');
        csrfprotector::$config['failedAuthAction']['POST'] = 10;
        csrfprotector::$config['failedAuthAction']['GET'] = 10;

        $_POST = array('param1' => 1, 'param2' => 2);
        csrfprotector::authorizePost();
        $this->assertEmpty($_POST);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        csrfp_wrapper::changeRequestType('GET');
        $_GET = array('param1' => 1, 'param2' => 2);

        csrfprotector::authorizePost();
        $this->assertEmpty($_GET);
    }

    /**
     * test authorise success with token in $_POST
     */
    public function testAuthorisePost_success() {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST[csrfprotector::$config['CSRFP_TOKEN']]
            = $_GET[csrfprotector::$config['CSRFP_TOKEN']]
            = $_SESSION[csrfprotector::$config['CSRFP_TOKEN']][0];
        $temp = $_SESSION[csrfprotector::$config['CSRFP_TOKEN']];

        csrfprotector::authorizePost(); //will create new session and cookies
        $this->assertFalse($temp == $_SESSION[csrfprotector::$config['CSRFP_TOKEN']][0]);
        $this->assertTrue(csrfp_wrapper::checkHeader('Set-Cookie'));
        $this->assertTrue(csrfp_wrapper::checkHeader('CSRFP-Token'));
        // $this->assertTrue(csrfp_wrapper::checkHeader($_SESSION[csrfprotector::$config['CSRFP_TOKEN']][0]));  // Combine these 3 later

        // For get method
        $_SERVER['REQUEST_METHOD'] = 'GET';
        csrfp_wrapper::changeRequestType('GET');
        $_POST[csrfprotector::$config['CSRFP_TOKEN']]
            = $_GET[csrfprotector::$config['CSRFP_TOKEN']]
            = $_SESSION[csrfprotector::$config['CSRFP_TOKEN']][0];
        $temp = $_SESSION[csrfprotector::$config['CSRFP_TOKEN']];

        csrfprotector::authorizePost(); //will create new session and cookies
        $this->assertFalse($temp == $_SESSION[csrfprotector::$config['CSRFP_TOKEN']]);
        $this->assertTrue(csrfp_wrapper::checkHeader('Set-Cookie'));
        $this->assertTrue(csrfp_wrapper::checkHeader('CSRFP-Token'));
        // $this->assertTrue(csrfp_wrapper::checkHeader($_SESSION[csrfprotector::$config['CSRFP_TOKEN']][0]));  // Combine these 3 later
    }

    /**
     * test authorise success with token in header
     */
    public function testAuthorisePost_success_2() {
        unset($_POST[csrfprotector::$config['CSRFP_TOKEN']]);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $serverKey = 'HTTP_' .strtoupper(csrfprotector::$config['CSRFP_TOKEN']);
        $serverKey = str_replace('-', '_', $serverKey);

        $csrfp = new csrfProtector;
        $reflection = new \ReflectionClass(get_class($csrfp));
        $property = $reflection->getProperty('tokenHeaderKey');
        $property->setAccessible(true);
        // change value to false
        $property->setValue($csrfp, $serverKey);

        $_SERVER[$serverKey] = $_SESSION[csrfprotector::$config['CSRFP_TOKEN']][0];
        $temp = $_SESSION[csrfprotector::$config['CSRFP_TOKEN']];

        csrfprotector::authorizePost(); //will create new session and cookies
        $this->assertFalse($temp == $_SESSION[csrfprotector::$config['CSRFP_TOKEN']][0]);
        $this->assertTrue(csrfp_wrapper::checkHeader('Set-Cookie'));
        $this->assertTrue(csrfp_wrapper::checkHeader('CSRFP-Token'));
        // $this->assertTrue(csrfp_wrapper::checkHeader($_SESSION[csrfprotector::$config['CSRFP_TOKEN']][0]));  // Combine these 3 later
 
    }

    /**
     * test for generateAuthToken()
     */
    public function testGenerateAuthToken() {
        csrfprotector::$config['tokenLength'] = 20;
        $token1 = csrfprotector::generateAuthToken();
        $token2 = csrfprotector::generateAuthToken();

        $this->assertFalse($token1 == $token2);
        $this->assertEquals(20, strlen($token1));
        $this->assertRegExp('/^[a-z0-9]{20}$/', $token1);

        csrfprotector::$config['tokenLength'] = 128;
        $token = csrfprotector::generateAuthToken();
        $this->assertEquals(128, strlen($token));
        $this->assertRegExp('/^[a-z0-9]{128}$/', $token);
    }

    /**
     * test ob_handler_function
     */
    public function testob_handler() {
        csrfprotector::$config['verifyGetFor'] = array();
        csrfprotector::$config['disabledJavascriptMessage'] = 'test message';
        csrfprotector::$config['jsUrl'] = 'http://localhost/test/csrf/js/csrfprotector.js';

        $testHTML = '<html>';
        $testHTML .= '<head><title>1</title>';
        $testHTML .= '<body onload="test()">';
        $testHTML .= '-- some static content --';
        $testHTML .= '-- some static content --';
        $testHTML .= '</body>';
        $testHTML .= '</head></html>';

        $modifiedHTML = csrfprotector::ob_handler($testHTML, 0);
        $inpLength = strlen($testHTML);
        $outLength = strlen($modifiedHTML);

        //Check if file has been modified
        $this->assertNotEquals($inpLength, $outLength);
        $this->assertContains('<noscript>', $modifiedHTML);
        $this->assertContains('<input type="hidden" id="' . CSRFP_FIELD_TOKEN_NAME . '"', $modifiedHTML);
        $this->assertContains('<input type="hidden" id="' . CSRFP_FIELD_URLS . '"', $modifiedHTML);
        $this->assertContains('<script', $modifiedHTML);
    }

    /**
     * test ob_handler_function
     */
    public function testob_handler_withoutClosedBodyTag() {
        csrfprotector::$config['verifyGetFor'] = array();
        csrfprotector::$config['disabledJavascriptMessage'] = 'test message';
        csrfprotector::$config['jsUrl'] = 'http://localhost/test/csrf/js/csrfprotector.js';

        $testHTML = '<html>';
        $testHTML .= '<head><title>1</title>';
        $testHTML .= '<body onload="test()">';
        $testHTML .= '-- some static content --';
        $testHTML .= '-- some static content --';
        $testHTML .= '</head></html>';

        $modifiedHTML = csrfprotector::ob_handler($testHTML, 0);

        //Check if file has been modified
        $this->assertStringEndsWith('</script>', $modifiedHTML);
    }

    /**
     * test ob_handler_function for output filter
     */
    public function testob_handler_positioning() {
        csrfprotector::$config['verifyGetFor'] = array();
        csrfprotector::$config['disabledJavascriptMessage'] = 'test message';
        csrfprotector::$config['jsUrl'] = 'http://localhost/test/csrf/js/csrfprotector.js';

        $testHTML = '<html>';
        $testHTML .= '<head><title>1</title>';
        $testHTML .= '<body onload="test()">';
        $testHTML .= '-- some static content --';
        $testHTML .= '-- some static content --';
        $testHTML .= '</body>';
        $testHTML .= '</head></html>';

        $modifiedHTML = csrfprotector::ob_handler($testHTML, 0);

        $this->assertEquals(strpos($modifiedHTML, '<body') + 23, strpos($modifiedHTML, '<noscript'));
        $this->assertContains('</script>' . PHP_EOL . '</body>', $modifiedHTML);
    }

    /**
     * test ob_handler_function for output filter
     */
    public function testob_handler_withoutInjectedCSRFGuardScript() {
        csrfprotector::$config['verifyGetFor'] = array();
        csrfprotector::$config['disabledJavascriptMessage'] = 'test message';
        csrfprotector::$config['jsUrl'] = false;

        $testHTML = '<html>';
        $testHTML .= '<head><title>1</title>';
        $testHTML .= '<body onload="test()">';
        $testHTML .= '-- some static content --';
        $testHTML .= '-- some static content --';
        $testHTML .= '</body>';
        $testHTML .= '</head></html>';

        $modifiedHTML = csrfprotector::ob_handler($testHTML, 0);

        $this->assertContains('<input type="hidden" id="' . CSRFP_FIELD_TOKEN_NAME . '"', $modifiedHTML);
        $this->assertContains('<input type="hidden" id="' . CSRFP_FIELD_URLS . '"', $modifiedHTML);

        $this->assertContains('<noscript', $modifiedHTML);
        $this->assertNotContains('</script>' . PHP_EOL . '</body>', $modifiedHTML);
    }

    /**
     * testing exception in logging function
     */
    public function testgetCurrentUrl() {
        $stub = new ReflectionClass('csrfprotector');
        $method = $stub->getMethod('getCurrentUrl');
        $method->setAccessible(true);
        $this->assertEquals("http://test/index.php", $method->invoke(null, array()));

        $tmp_request_scheme = $_SERVER['REQUEST_SCHEME'];
        unset($_SERVER['REQUEST_SCHEME']);

        // server-https is not set
        $this->assertEquals("http://test/index.php", $method->invoke(null, array()));

        $_SERVER['HTTPS'] = 'on';
        $this->assertEquals("https://test/index.php", $method->invoke(null, array()));
        unset($_SERVER['HTTPS']);

        $_SERVER['REQUEST_SCHEME'] = "https";
        $this->assertEquals("https://test/index.php", $method->invoke(null, array()));

        $_SERVER['REQUEST_SCHEME'] = $tmp_request_scheme;
    }

    /**
     * testing logging function
     */
    public function testlogCSRFattack() {
        $csrfp = new csrfProtector;
        $fakeLogger = $this->setFakeLogger($csrfp);

        $stub = new ReflectionClass('csrfprotector');
        $method = $stub->getMethod('logCSRFattack');
        $method->setAccessible(true);

        $this->assertNull($fakeLogger->getLastMessageLogged());
        $method->invoke(null);
        $this->assertNotNull($fakeLogger->getLastMessageLogged());
    }

    /**
     * Tests isUrlAllowed() function for various urls and configuration
     */
    public function testisURLallowed() {
        csrfprotector::$config['verifyGetFor']
            = array('http://test/delete*', 'https://test/*');

        $_SERVER['PHP_SELF'] = '/nodelete.php';
        $this->assertTrue(csrfprotector::isURLallowed());

        // Test 'http://test/index.php'
        $_SERVER['PHP_SELF'] = '/index.php';
        $this->assertTrue(csrfprotector::isURLallowed());

        // Test 'http://test/delete.php'
        $_SERVER['PHP_SELF'] = '/delete.php';
        $this->assertFalse(csrfprotector::isURLallowed());

        // Test 'http://test/delete_users.php'
        $_SERVER['PHP_SELF'] = '/delete_user.php';
        $this->assertFalse(csrfprotector::isURLallowed());

        // Test 'https://test/index.php'
        $_SERVER['REQUEST_SCHEME'] = 'https';
        $_SERVER['PHP_SELF'] = '/index.php';
        $this->assertFalse(csrfprotector::isURLallowed());

        // 'https://test/delete_users.php'
        $_SERVER['PHP_SELF'] = '/delete_user.php';
        $this->assertFalse(csrfprotector::isURLallowed());
    }

    /**
     * Test for exception thrown when env variable is set by mod_csrfprotector
     */
    public function testModCSRFPEnabledException() {
        putenv('mod_csrfp_enabled=true');
        $_COOKIE[csrfprotector::$config['CSRFP_TOKEN']] = 'abc';
        $_SESSION[csrfprotector::$config['CSRFP_TOKEN']] = array('abc');

        csrfProtector::$config = array();
        csrfProtector::init();

        // Assuming no config was added
        $this->assertTrue(count(csrfProtector::$config) == 0);
        
        // unset the env variable
        putenv('mod_csrfp_enabled');
    }

    /**
     * Test for exception thrown when init() method is called multiple times
     */
    public function testMultipleInitializeException() {
        csrfProtector::$config = array();
        $this->assertTrue(count(csrfProtector::$config) == 0);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        csrfProtector::init();

        $this->assertTrue(count(csrfProtector::$config) == 9);
        try {
            csrfProtector::init();
            $this->fail("alreadyInitializedException not raised");
        }  catch (alreadyInitializedException $ex) {
            // pass
            $this->assertTrue(true);
        } catch (Exception $ex) {
            $this->fail("exception other than alreadyInitializedException failed");
        }

        // cleanup
        ob_end_clean();
    }

    /**
     * Test for exception thrown when init() method is called with missing config items
     * @expectedException incompleteConfigurationException
     * @expectedExceptionMessage OWASP CSRFProtector: Incomplete configuration file: missing failedAuthAction, jsUrl, tokenLength value(s)
     */
    public function testInit_incompleteConfigurationException() {
        // Create an instance of config file -- for testing
        $data = file_get_contents(__DIR__ .'/config.testInit_incompleteConfigurationException.php');
        file_put_contents(__DIR__ .'/../libs/config.php', $data);

        csrfProtector::$config = array();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        csrfProtector::init();
    }

    /**
     * Test for exception thrown when init() method is called multiple times
     */
    public function testInit_withoutInjectedCSRFGuardScript() {
        // Create an instance of config file -- for testing
        $data = file_get_contents(__DIR__ .'/config.testInit_withoutInjectedCSRFGuardScript.php');
        file_put_contents(__DIR__ .'/../libs/config.php', $data);

        csrfProtector::$config = array();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        csrfProtector::init();

        // cleanup
        ob_end_clean();
    }

    private function setFakeLogger($csrfp) {
        $fakeLogger = new fakeLogger();

        $reflection = new \ReflectionClass(get_class($csrfp));
        $property = $reflection->getProperty('logger');
        $property->setAccessible(true);
        $property->setValue($csrfp, $fakeLogger);

        return $fakeLogger;
    }
}
