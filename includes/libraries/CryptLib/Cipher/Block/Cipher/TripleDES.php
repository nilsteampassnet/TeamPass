<?php
/**
 * An implementation of the TripleDES cipher
 * 
 * This was forked from phpseclib and modified to use CryptLib conventions
 *
 * PHP version 5.3
 *
 * @category   PHPCryptLib
 * @package    Cipher
 * @subpackage Block
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @author     Jim Wigginton <terrafrost@php.net>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */

namespace CryptLib\Cipher\Block\Cipher;

/**
 * An implementation of the TripleDES Cipher
 *
 * @category   PHPCryptLib
 * @package    Cipher
 * @subpackage Block
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class TripleDES extends DES {

    /**
     * @var int The key size for the cipher
     */
    protected $keySize = 24;

    /**
     * Get a list of supported ciphers for this class implementation
     *
     * @return array A list of supported ciphers
     */
    public static function getSupportedCiphers() {
        return array('tripledes');
    }

    /**
     * Set the key to use for the cipher
     *
     * @param string $key The key to use
     * 
     * @throws InvalidArgumentException If the key is not the correct size
     * @return void
     */
    public function setKey($key) {
        $len = strlen($key);
        if ($len == 16) {
            $key .= substr($key, 0, 8);
        } elseif ($len != 24) {
            throw new \InvalidArgumentException(
                'The supplied key block is not the correct size'
            );
        }
        $this->key         = $key;
        $this->initialized = true;
    }

    /**
     * Decrypt a block of data using the supplied string key
     *
     * Note that the supplied data should be the same size as the block size of
     * the cipher being used.
     *
     * @param string $data The data to decrypt
     *
     * @return string The result decrypted data
     */
    protected function decryptBlockData($data) {
        $key       = $this->key;
        $this->key = substr($key, 16, 8);
        $this->initialize();
        $data      = parent::decryptBlockData($data);
        $this->key = substr($key, 8, 8);
        $this->initialize();
        $data      = parent::encryptBlockData($data);
        $this->key = substr($key, 0, 8);
        $this->initialize();
        $data      = parent::decryptBlockData($data);
        $this->key = $key;
        return $data;
    }

    /**
     * Encrypt a block of data using the supplied string key
     *
     * Note that the supplied data should be the same size as the block size of
     * the cipher being used.
     *
     * @param string $data The data to encrypt
     *
     * @return string The result encrypted data
     */
    protected function encryptBlockData($data) {
        $key       = $this->key;
        $this->key = substr($key, 0, 8);
        $this->initialize();
        $data      = parent::encryptBlockData($data);
        $this->key = substr($key, 8, 8);
        $this->initialize();
        $data      = parent::decryptBlockData($data);
        $this->key = substr($key, 16, 8);
        $this->initialize();
        $data      = parent::encryptBlockData($data);
        $this->key = $key;
        return $data;
    }

}
