<?php
date_default_timezone_set('UTC');
require_once __DIR__ .'/../libs/csrf/csrfprotector.php';
require_once __DIR__ .'/../libs/csrf/LoggerInterface.php';
require_once __DIR__ .'/fakeLogger.php';

if (intval(phpversion('tidy')) >= 7 && !class_exists('\PHPUnit_Framework_TestCase', true)) {
    class_alias('\PHPUnit\Framework\TestCase', '\PHPUnit_Framework_TestCase');
}

class csrfp_test_customLogger extends PHPUnit_Framework_TestCase
{
    /**
     * @var array to hold current configurations
     */
    protected $config = array();

    public function setUp() {
        csrfprotector::$config['CSRFP_TOKEN'] = 'CSRFP-Token';
        csrfprotector::$config['cookieConfig'] = array('secure' => false);
        csrfprotector::$config['logDirectory'] = '../test/logs';
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
        ob_end_flush();
    }

    public function testCustomLogger_doesntThrowException() {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $tmp = csrfProtector::$config;
        csrfProtector::$config = array();
        csrfProtector::init(null, array('POST' => 1), new fakeLogger());

        $this->assertTrue(true);
    }

    public function testCustomLogger_onLogAttack_loggerIsCalled() {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $fakeLogger = new fakeLogger();
        $this->assertNull($fakeLogger->getLastMessageLogged());

        $tmp = csrfProtector::$config;
        csrfProtector::$config = array();
        csrfProtector::init(null, array('POST' => 1), $fakeLogger);

        $this->assertNotNull($fakeLogger->getLastMessageLogged());
    }
}
