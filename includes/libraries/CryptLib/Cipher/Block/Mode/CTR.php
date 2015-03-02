<?php
/**
 * The CTR (Counter) mode implementation
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

use CryptLib\Core\BaseConverter;
use CryptLib\Core\BigMath;

/**
 * The CTR (Counter) mode implementation
 *
 * @category   PHPCryptLib
 * @package    Cipher
 * @subpackage Block
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */

class CTR extends \CryptLib\Cipher\Block\AbstractMode {

    /**
     * @var BigMath An instance of the BigMath library
     */
    protected $bigMath = null;

    /**
     * Build the instance of the cipher mode
     *
     * @param Cipher $cipher  The cipher to use for encryption/decryption
     * @param string $initv   The initialization vector (empty if not needed)
     * @param array  $options An array of mode-specific options
     */
    public function __construct(
        \CryptLib\Cipher\Block\Cipher $cipher,
        $initv,
        array $options = array()
    ) {
        parent::__construct($cipher, $initv, $options);
        $this->bigMath = BigMath::createFromServerConfiguration();
    }

    /**
     * Reset the mode to start over (destroying any intermediate state)
     *
     * @return void
     */
    public function reset() {
        $this->state = BaseConverter::ConvertFromBinary($this->initv, '0123456789');
        $this->state = ltrim($this->state, '0');
    }

    /**
     * Decrypt the data using the supplied key, cipher
     *
     * @param string $data The data to decrypt
     *
     * @return string The decrypted data
     */
    protected function decryptBlock($data) {
        return $this->encryptBlock($data);
    }

    /**
     * Encrypt the data using the supplied key, cipher
     *
     * @param string $data The data to encrypt
     *
     * @return string The encrypted data
     */
    protected function encryptBlock($data) {
        $size        = $this->cipher->getBlockSize();
        $state       = str_pad(
            BaseConverter::convertToBinary($this->state, '0123456789'),
            $size,
            chr(0),
            STR_PAD_LEFT
        );
        $stub        = $this->cipher->encryptBlock($state);
        $this->state = $this->bigMath->add($this->state, 1);
        return $stub ^ $data;
    }

}
