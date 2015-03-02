<?php
/**
 * The NOFB (Nbit Output FeedBack) mode implementation
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
 * The NOFB (Nbit Output FeedBack) mode implementation
 *
 * @category   PHPCryptLib
 * @package    Cipher
 * @subpackage Block
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class NOFB extends \CryptLib\Cipher\Block\AbstractMode {

    /**
     * Decrypt the data using the supplied key, cipher and initialization vector
     *
     * @param string $data The data to decrypt
     *
     * @return string The decrypted data
     */
    protected function decryptBlock($data) {
        return $this->encryptBlock($data);
    }

    /**
     * Encrypt the data using the supplied key, cipher and initialization vector
     *
     * @param string $data The data to encrypt
     *
     * @return string The encrypted data
     */
    protected function encryptBlock($data) {
        $this->state = $this->cipher->encryptBlock($this->state);
        return $this->state ^ $data;
    }

}
