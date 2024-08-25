<?php
require_once __DIR__ .'/../libs/csrf/LoggerInterface.php';

if (!defined('__CSRF_PROTECTOR_TEST_FAKE_LOGGER__')) {
    // to avoid multiple declaration errors.
    define('__CSRF_PROTECTOR_TEST_FAKE_LOGGER__', true);

    /** 
     * Fake logger class.
     */
    class fakeLogger implements LoggerInterface {

        private $lastMessage = null;
        private $lastContext = null;

        public function getLastMessageLogged() {
            return $this->lastMessage;
        }

        public function getLastContextLogged() {
            return $this->lastContext;
        }

        public function log($message, $context = array()) {
            $this->lastMessage = $message;
            $this->lastContext = $context;
        }
    }
}
