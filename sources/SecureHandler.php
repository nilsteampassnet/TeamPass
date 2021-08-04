<?php

declare(strict_types=1);

/**
 * Encrypt PHP session data for the internal PHP save handlers
 *
 * The encryption is built using OpenSSL extension with AES-256-CBC and the
 * authentication is provided using HMAC with SHA256.
 *
 * @author    Enrico Zimuel (enrico@zimuel.it)
 * @copyright MIT License
 */


namespace PHPSecureSession;

use SessionHandler;

class SecureHandler extends SessionHandler
{
    /**
     * Encryption and authentication key
     * @var string
     */
    protected $key;

    /**
     * Constructor
     */
    public function __construct()
    {
        if (!extension_loaded('openssl')) {
            throw new \RuntimeException(sprintf(
                "You need the OpenSSL extension to use %s",
                __CLASS__
            ));
        }
        if (!extension_loaded('mbstring')) {
            throw new \RuntimeException(sprintf(
                "You need the Multibytes extension to use %s",
                __CLASS__
            ));
        }
    }

    /**
     * Open the session
     *
     * @param string $save_path
     * @param string $session_name
     * @return bool
     */
    public function open($save_path, $session_name)
    {
        $this->key = $this->getKey('KEY_' . $session_name);
        return parent::open($save_path, $session_name);
    }

    /**
     * Read from session and decrypt
     *
     * @param string $session_id
     */
    public function read($session_id)
    {
        $data = parent::read($session_id);
        if (is_null($data)) {
            $data = '';  //use empty string instead of null!
        }
        return empty($data) ? '' : $this->decrypt($data, $this->key);
    }

    /**
     * Encrypt the data and write into the session
     *
     * @param string $session_id
     * @param string $data
     */
    public function write($session_id, $data)
    {
        return parent::write($session_id, $this->encrypt($data, $this->key));
    }

    /**
     * Encrypt and authenticate
     *
     * @param string $data
     * @param string $key
     * @return string
     */
    protected function encrypt($data, $key)
    {
        $block_iv = random_bytes(16); // AES block size in CBC mode
        // Encryption
        $ciphertext = openssl_encrypt(
            $data,
            'AES-256-CBC',
            mb_substr($key, 0, 32, '8bit'),
            OPENSSL_RAW_DATA,
            $block_iv
        );
        // Authentication
        $hmac = hash_hmac(
            'SHA256',
            $block_iv . $ciphertext,
            mb_substr($key, 32, null, '8bit'),
            true
        );
        return $hmac . $block_iv . $ciphertext;
    }

    /**
     * Authenticate and decrypt
     *
     * @param string $data
     * @param string $key
     * @return string
     */
    protected function decrypt($data, $key)
    {
        $hmac       = mb_substr($data, 0, 32, '8bit');
        $block_iv   = mb_substr($data, 32, 16, '8bit');
        $ciphertext = mb_substr($data, 48, null, '8bit');
        // Authentication
        $hmacNew = hash_hmac(
            'SHA256',
            $block_iv . $ciphertext,
            mb_substr($key, 32, null, '8bit'),
            true
        );
        if (!$this->hash_equals($hmac, $hmacNew)) {
            throw new \RuntimeException('Authentication failed');
        }
        // Decrypt
        return openssl_decrypt(
            $ciphertext,
            'AES-256-CBC',
            mb_substr($key, 0, 32, '8bit'),
            OPENSSL_RAW_DATA,
            $block_iv
        );
    }

    /**
     * Get the encryption and authentication keys from cookie
     *
     * @param string $name
     * @return string
     */
    protected function getKey($name)
    {
        if (empty($_COOKIE[$name]) === true) {
            // 32 for encryption and 32 for authentication
            $key = random_bytes(64);
            $cookieParam = session_get_cookie_params();
            setcookie(
                $name,
                base64_encode($key),
                // if session cookie lifetime > 0 then add to current time
                // otherwise leave it as zero, honoring zero's special meaning
                // expire at browser close.
                ($cookieParam['lifetime'] > 0) ? time() + $cookieParam['lifetime'] : 0,
                $cookieParam['path'],
                $cookieParam['domain'],
                $cookieParam['secure'],
                $cookieParam['httponly']
            );
            return $key;
        }

        // If not returned before - cookie exists
        return base64_decode($_COOKIE[$name]);
    }

    /**
     * Hash equals function for PHP 5.5+
     *
     * @param string $expected
     * @param string $actual
     * @return bool
     */
    protected function hash_equals($expected, $actual)
    {
        $expected     = filter_var($expected, FILTER_SANITIZE_STRING);
        $actual       = filter_var($actual, FILTER_SANITIZE_STRING);
        if (function_exists('hash_equals')) {
            return hash_equals($expected, $actual);
        }
        $lenExpected  = mb_strlen($expected, '8bit');
        $lenActual    = mb_strlen($actual, '8bit');
        $len          = min($lenExpected, $lenActual);
        $result = 0;
        for ($i = 0; $i < $len; $i++) {
            $result |= ord($expected[$i]) ^ ord($actual[$i]);
        }
        $result |= $lenExpected ^ $lenActual;
        return ($result === 0);
    }
}

// random_bytes is a PHP7 new function required by SecureHandler.
// random_compat has been written to define it in PHP5
if (file_exists('./includes/libraries/misc/random_compat/random.php')) {
    require_once('./includes/libraries/misc/random_compat/random.php');
} else {
    if (file_exists('../includes/libraries/misc/random_compat/random.php')) {
        require_once('../includes/libraries/misc/random_compat/random.php');
    } else {
        require_once('../../includes/libraries/misc/random_compat/random.php');
    }
}

// load
session_set_save_handler(
    new SecureHandler(),
    true
);
