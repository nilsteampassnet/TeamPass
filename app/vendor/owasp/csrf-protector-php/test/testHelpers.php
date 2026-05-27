<?php
if (!defined('__CSRF_PROTECTOR_TEST_HELPERS__')) {
    // to avoid multiple declaration errors.
    define('__CSRF_PROTECTOR_TEST_HELPERS__', true);

    /**
     * Wrapper class that extends CSRF Protector for testing purpose.
     */
    class csrfp_wrapper extends csrfprotector {
        /**
         * Function to provide wrapper method to set the protected var, requestType
         * @param string $type
         */
        public static function changeRequestType($type) {
            self::$requestType = $type;
        }

        /**
         * Function to check for a string value anywhere within HTTP response headers
         * Returns true on first match of $needle in header names or values
         * @param string $needle
         * @return bool
         */
        public static function checkHeader($needle) {
            $haystack = xdebug_get_headers();
            foreach ($haystack as $key => $value) {
                if (strpos($value, $needle) !== false)
                    return true;
            }
            return false;
        }

        /**
         * Function to return the string value of the last response header
         * identified by name $needle
         * @param string $needle
         * @return string
         */
        public static function getHeaderValue($needle) {
            $haystack = xdebug_get_headers();
            foreach ($haystack as $key => $value) {
                if (strpos($value, $needle) === 0) {
                    // Deliberately overwrite to accept the last rather than first match
                    // as xdebug_get_headers() will accumulate all set headers
                    list(,$hvalue) = explode(':', $value, 2);
                }
            }
            return $hvalue;
        } 
    }
}
?>
