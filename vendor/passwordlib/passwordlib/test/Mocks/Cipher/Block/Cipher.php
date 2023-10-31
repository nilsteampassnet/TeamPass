<?php
/**
 * The interface that all block ciphers must implement
 *
 * PHP version 5.3
 *
 * @category   PHPPasswordLib
 * @package    Cipher
 * @subpackage Block
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @license    http://www.gnu.org/licenses/lgpl-2.1.html LGPL v 2.1
 */

namespace PasswordLibTest\Mocks\Cipher\Block;

/**
 * The interface that all block ciphers must implement
 *
 * @category   PHPPasswordLib
 * @package    Cipher
 * @subpackage Block
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class Cipher extends \PasswordLibTest\Mocks\AbstractMock implements \PasswordLib\Cipher\Block\Cipher {

    public static $ciphers = array();

    public static function init() {
        static::$ciphers = array();
    }


    /**
     * Get a list of supported ciphers by this cipher
     *
     * @return array An array of supported cipher names (strings)
     */
    public static function getSupportedCiphers() {
        return static::$ciphers;
    }

    /**
     * Decrypt a block of data using the supplied string key
     *
     * Note that the supplied data should be the same size as the block size of
     * the cipher being used.
     *
     * @param string $data The data to decrypt
     * @param string $key  The key to decrypt with
     *
     * @return string The result decrypted data
     */
    public function decryptBlock($data) {
        return $this->__call('decryptBlock', array($data));
    }

    /**
     * Encrypt a block of data using the supplied string key
     *
     * Note that the supplied data should be the same size as the block size of
     * the cipher being used.
     *
     * @param string $data The data to encrypt
     * @param string $key  The key to encrypt with
     *
     * @return string The result encrypted data
     */
    public function encryptBlock($data) {
        return $this->__call('encryptBlock', array($data));
    }

    
    public function setKey($key) {
        return $this->__call('setKey', array($key));
    }
    
    /**
     * Get the block size for the current initialized cipher
     *
     * @param string $key The key the data will be encrypted with
     *
     * @return int The block size for the current cipher
     */
    public function getBlockSize() {
        return $this->__call('getBlockSize', array());
    }

    /**
     * Get the string name of the current cipher instance
     *
     * @return string The current instantiated cipher
     */
    public function getCipher() {
        return $this->__call('getCipher', array());
    }

    /**
     * Get the block size for the current initialized cipher
     *
     * @return int The block size for the current cipher
     */
    public function getKeySize() {
        return $this->__call('getKeySize', array());
    }
}
