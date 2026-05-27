<?php

/**
 * Gibberish AES, a PHP Implementation
 *
 * See Gibberish AES javascript encryption library, @link https://github.com/mdp/gibberish-aes
 *
 * An important note: The complementary JavaScript project Gibberish AES has been
 * deprecated, see https://github.com/mdp/gibberish-aes/issues/25
 * Consider finding alternative PHP and JavaScript solutions.
 *
 * This implementation is based on initial code proposed by nbari at dalmp dot com
 * @link http://www.php.net/manual/en/function.openssl-decrypt.php#107210
 *
 * Requirements:
 *
 * php7.1
 *
 * modules: openssl, mbstring
 *
 *
 * Usage:
 *
 * // This is a secret pass-phrase, keep it in a safe place and don't loose it.
 * $pass = 'my secret pass-phrase, it should be long';
 *
 * // The string to be encrypted.
 * $string = 'my secret message';
 *
 * // This is the result after encryption of the given string.
 * $encrypted_string = GibberishAES::enc($string, $pass);
 *
 * // This is the result after decryption of the previously encrypted string.
 * // $decrypted_string == $string (should be).
 * $decrypted_string = GibberishAES::dec($encrypted_string, $pass);
 * echo $decrypted_string;
 *
 * // The default key-size is 256 bits. 128 and 192 bits are also allowed.
 * // Example:
 * $old_key_size = GibberishAES::size();
 * GibberishAES::size(192);
 * // The short way: $old_key_size = GibberishAES::size(192);
 * $encrypted_string = GibberishAES::enc($string, $pass);
 * $decrypted_string = GibberishAES::dec($encrypted_string, $pass);
 * GibberishAES::size($old_key_size);
 * echo $decrypted_string;
 *
 * @author Ivan Tcholakov <ivantcholakov@gmail.com>, 2012-2016.
 * @author Philipp Dittert <philipp.dittert@gmail.com>, 2019-2020
 *
 * Code repository: @link https://github.com/dittertp/gibberish-aes-php
 *
 * @version 2.0.0
 *
 * @license The MIT License (MIT)
 * @link http://opensource.org/licenses/MIT
 */

namespace GibberishAES;

class GibberishAES
{
    // The default key size in bits.
    protected static $key_size = 256;

    // The allowed key sizes in bits.
    protected static $valid_key_sizes = array(128, 192, 256);

    protected static $random_bytes_exists = null;
    protected static $openssl_encrypt_exists = null;
    protected static $openssl_decrypt_exists = null;
    protected static $mcrypt_exists = null;
    protected static $mbstring_func_overload = null;

    final private function __construct()
    {
    }

    private function __clone()
    {
    }

    /**
     * Crypt AES (256, 192, 128)
     *
     * @param   string  $string     The input message to be encrypted.
     * @param   string  $pass       The secret pass-phrase, choose a long string
     *                              (64 characters for example) for keeping high entropy.
     *                              The pass-phrase is converted internaly into
     *                              a binary key that is to be used for encryption.
     * @return  mixed               base64 encrypted string, FALSE on failure.
     * @throws \Exception
     */
    public static function enc(string $string, string $pass): string
    {
        $key_size = self::$key_size;

        // Set a random salt.
        $salt = self::randomBytes(8);

        $salted = '';
        $dx = '';

        // Lengths in bytes:
        $key_length = (int) ($key_size / 8);
        $block_length = 16; // 128 bits, iv has the same length.
        // $salted_length = $key_length (32, 24, 16) + $block_length (16) = (48, 40, 32)
        $salted_length = $key_length + $block_length;

        while (self::strlen($salted) < $salted_length) {
            $dx = md5($dx . $pass . $salt, true);
            $salted .= $dx;
        }

        $key = self::substr($salted, 0, $key_length);
        $iv = self::substr($salted, $key_length, $block_length);

        $encrypted = self::aesCbcEncrypt($string, $key, $iv);

        return $encrypted !== false ? base64_encode('Salted__' . $salt . $encrypted) : false;
    }

    /**
     * Decrypt AES (256, 192, 128)
     *
     * @param   string  $string     The input message to be decrypted.
     * @param   string  $pass       The secret pass-phrase that has been used for encryption.
     * @return  mixed               base64 decrypted string, FALSE on failure.
     */
    public static function dec(string $string, string $pass): string
    {

        $key_size = self::$key_size;

        // Lengths in bytes:
        $key_length = (int) ($key_size / 8);
        $block_length = 16;

        $data = base64_decode($string);
        $salt = self::substr($data, 8, 8);
        $encrypted = self::substr($data, 16);

        $rounds = 3;
        if ($key_size == 128) {
            $rounds = 2;
        }

        $data00 = $pass . $salt;
        $md5_hash = array();
        $md5_hash[0] = md5($data00, true);
        $result = $md5_hash[0];

        for ($i = 1; $i < $rounds; $i++) {
            $md5_hash[$i] = md5($md5_hash[$i - 1] . $data00, true);
            $result .= $md5_hash[$i];
        }

        $key = self::substr($result, 0, $key_length);
        $iv = self::substr($result, $key_length, $block_length);

        return self::aesCbcDecrypt($encrypted, $key, $iv);
    }

    /**
     * Sets the key-size for encryption/decryption in number of bits
     *
     * @param   int|null $newSize The new key size. The valid integer values are: 128, 192, 256 (default)
     *                            $newsize may be NULL or may be omited - in this case
     *                            this method is just a getter of the current key size value.
     *
     * @return int Returns the old key size value.
     *
     * @throws \Exception
     */
    public static function size(?int $newSize = null): int
    {
        $result = self::$key_size;

        if (is_null($newSize)) {
            return $result;
        }

        if (!in_array($newSize, self::$valid_key_sizes)) {
            throw new \Exception(
                'GibberishAES: Invalid key size value was to be set. Allowed values: '
                . implode(', ', self::$valid_key_sizes)
            );
        }

        self::$key_size = $newSize;

        return $newSize;
    }

    /**
     * returns the multibyte string length
     *
     * @param string $str
     *
     * @return int
     */
    protected static function strlen(string $str): int
    {
        return mb_strlen($str, '8bit');
    }

    /**
     * returns a substring of given string
     *
     * @param string $str
     * @param int $start
     * @param int|null $length
     *
     * @return string
     */
    protected static function substr(string $str, int $start, ?int $length = null): string
    {
        isset($length) or $length = ($start >= 0 ? self::strlen($str) - $start : -$start);

        return mb_substr($str, $start, $length, '8bit');
    }

    /**
     * return random bytes
     *
     * @param int $length
     *
     * @return string
     * @throws \Exception
     */
    protected static function randomBytes(int $length): string
    {
        return random_bytes($length);
    }

    /**
     * encrypt given string with given key
     *
     * @param string $string
     * @param string $key
     * @param string $iv
     *
     * @return string
     */
    protected static function aesCbcEncrypt(string $string, string $key, string $iv): string
    {
        $key_size = self::$key_size;

        return openssl_encrypt($string, "aes-$key_size-cbc", $key, true, $iv);
    }

    /**
     * decrypts given string with given key
     *
     * @param string $crypted
     * @param string $key
     * @param string $iv
     *
     * @return string
     */
    protected static function aesCbcDecrypt(string $crypted, string $key, string $iv): string
    {
        $key_size = self::$key_size;

        return openssl_decrypt($crypted, "aes-$key_size-cbc", $key, 1, $iv);
    }
}
