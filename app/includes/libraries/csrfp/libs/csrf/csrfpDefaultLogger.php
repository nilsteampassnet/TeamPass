<?php
/**
 * This file has implementation for csrfpDefaultLogger class.
 */
include __DIR__ ."/LoggerInterface.php";

if (!defined('__CSRF_PROTECTOR_DEFAULT_LOGGER_')) {
    // to avoid multiple declaration errors
    define('__CSRF_PROTECTOR_DEFAULT_LOGGER_', true);

    /**
     * Default logger class for CSRF Protector.
     * 
     * This implementation is based on PHP's default error_log implementation.
     */
    class csrfpDefaultLogger implements LoggerInterface {
        /**
         * Sends error message to the defined error_handling routines.
         * 
         * Based on PHP's default error_log method implementation.
         *
         * Parameters:
         * $message - the log message
         * $context - context array
         * 
         * Return:
         * void
         */
        public function log($message, $context = array()) {
            $context['timestamp'] = time();
            $context['message'] = $message;

            // Convert log array to JSON format to be logged
            $contextString = "OWASP CSRF Protector PHP " 
                .json_encode($context) 
                .PHP_EOL;
            error_log($contextString, /* message_type= */ 0);
        }
    }
}
