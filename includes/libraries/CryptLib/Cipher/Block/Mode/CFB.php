<?php
/**
 * The CFB (Cipher FeedBack) mode implementation
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
 * The CFB (Cipher FeedBack) mode implementation
 *
 * @category   PHPCryptLib
 * @package    Cipher
 * @subpackage Block
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class CFB extends \CryptLib\Cipher\Block\AbstractMode {

    /**
     * Decrypt the data using the supplied key, cipher
     *
     * @param string $data The data to decrypt
     *
     * @return string The decrypted data
     */
    protected function decryptBlock($data) {
        $stub        = $this->cipher->encryptBlock($this->state);
        $rawData     = $stub ^ $data;
        $this->state = $rawData;
        return $rawData;
    }

    /**
     * Encrypt the data using the supplied key, cipher
     *
     * @param string $data The data to encrypt
     *
     * @return string The encrypted data
     */
    protected function encryptBlock($data) {
        $stub        = $this->cipher->encryptBlock($this->state);
        $this->state = $data;
        return $stub ^ $data;
    }

}
