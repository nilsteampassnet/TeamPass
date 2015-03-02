<?php
/**
 * The ECB (Electronic CodeBook) mode implementation
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

namespace CryptLib\Cipher\Block\Mode;

/**
 * The ECB (Electronic CodeBook) mode implementation
 *
 * @category   PHPCryptLib
 * @package    Cipher
 * @subpackage Block
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class ECB extends \CryptLib\Cipher\Block\AbstractMode {

    /**
     * Decrypt the data using the supplied key, cipher and initialization vector
     *
     * @param string $data The data to decrypt
     *
     * @return string The decrypted data
     */
    protected function decryptBlock($data) {
        return $this->cipher->decryptBlock($data);
    }

    /**
     * Encrypt the data using the supplied key, cipher and initialization vector
     *
     * @param string $data The data to encrypt
     *
     * @return string The encrypted data
     */
    protected function encryptBlock($data) {
        return $this->cipher->encryptBlock($data);
    }

}
