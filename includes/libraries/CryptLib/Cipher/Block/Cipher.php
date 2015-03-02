<?php
/**
 * The interface that all block ciphers must implement
 *
 * PHP version 5.3
 *
 * @category   PHPCryptLib
 * @package    Cipher
 * @subpackage Block
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */

namespace CryptLib\Cipher\Block;

/**
 * The interface that all block ciphers must implement
 *
 * @category   PHPCryptLib
 * @package    Cipher
 * @subpackage Block
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @codeCoverageIgnore
 */
interface Cipher {

    /**
     * Get a list of supported ciphers by this cipher.
     *
     * @return array An array of supported cipher names (strings)
     */
    public static function getSupportedCiphers();

    /**
     * Decrypt a block of data using the supplied string key.
     *
     * Note that the supplied data should be the same size as the block size of
     * the cipher being used.
     *
     * @param string $data The data to decrypt
     *
     * @return string The result decrypted data
     */
    public function decryptBlock($data);

    /**
     * Encrypt a block of data using the supplied string key.
     *
     * Note that the supplied data should be the same size as the block size of
     * the cipher being used.
     *
     * @param string $data The data to encrypt
     *
     * @return string The result encrypted data
     */
    public function encryptBlock($data);

    /**
     * Get the block size for the current initialized cipher.
     *
     * @return int The block size for the current cipher
     */
    public function getBlockSize();

    /**
     * Get the key size for the current initialized cipher
     * 
     * @return int The key size for the current cipher
     */
    public function getKeySize();

    /**
     * Get the string name of the current cipher instance.
     *
     * @return string The current instantiated cipher
     */
    public function getCipher();

    /**
     * Set the key to be used in this instance
     * 
     * @param string $key The key the data will be encrypted with
     * 
     * @return void
     */
    public function setKey($key);

}
