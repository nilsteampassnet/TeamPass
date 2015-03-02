<?php
/**
 * The CBC (Cipher Block Chaining) mode implementation
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
 * The CBC (Cipher Block Chaining) mode implementation
 *
 * @category   PHPCryptLib
 * @package    Cipher
 * @subpackage Block
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */

class CBC extends \CryptLib\Cipher\Block\AbstractMode {

    /**
     * Decrypt the data using the supplied key, cipher
     *
     * @param string $data The data to decrypt
     *
     * @return string The decrypted data
     */
    protected function decryptBlock($data) {
        $stub        = $this->cipher->decryptBlock($data);
        $result      = $stub ^ $this->state;
        $this->state = $data;
        return $result;
    }

    /**
     * Encrypt the data using the supplied key, cipher
     *
     * @param string $data The data to encrypt
     *
     * @return string The encrypted data
     */
    protected function encryptBlock($data) {
        $stub        = $this->cipher->encryptBlock($data ^ $this->state);
        $this->state = $stub;
        return $stub;
    }

}
