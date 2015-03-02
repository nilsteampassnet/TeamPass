<?php
/**
 * An abstract class for simplifing creation of cipher modes
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
 * An abstract class for simplifing creation of cipher modes
 *
 * @category   PHPCryptLib
 * @package    Cipher
 * @subpackage Block
 */
abstract class AbstractMode implements \CryptLib\Cipher\Block\Mode {

    /**
     * @var string Additional data to authenticate with
     */
    protected $adata = '';

    /**
     * @var Cipher The cipher to use for this mode instance
     */
    protected $cipher = null;

    /**
     * @var string The initialization Vector to use for this mode
     */
    protected $initv = '';

    /**
     * @var string The mode name for the current instance
     */
    protected $mode = '';

    /**
     * @var array Mode specific options
     */
    protected $options = array();

    /**
     * @var string The internal state of the mode
     */
    protected $state = '';

    /**
     * Perform the decryption of the current block
     *
     * @param string $data The data to decrypt
     *
     * @return string The decrypted data
     */
    abstract protected function decryptBlock($data);

    /**
     * Perform the encryption of the current block
     *
     * @param string $data The data to encrypt
     *
     * @return string The encrypted data
     */
    abstract protected function encryptBlock($data);

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
        $class         = strtolower(get_class($this));
        $class         = substr($class, strrpos($class, '\\') + 1);
        $this->mode    = $class;
        $this->options = $options + $this->options;
        $this->cipher  = $cipher;
        $this->initv   = $initv;
        $this->reset();
    }

    /**
     * Decrypt the data using the supplied key, cipher and initialization vector
     *
     * @param string $data The data to decrypt
     *
     * @return string The decrypted data
     */
    public function decrypt($data) {
        $this->enforceBlockSize($data);
        return $this->decryptBlock($data);
    }

    /**
     * Encrypt the data using the supplied key, cipher and initialization vector
     *
     * @param string $data The data to encrypt
     *
     * @return string The encrypted data
     */
    public function encrypt($data) {
        $this->enforceBlockSize($data);
        return $this->encryptBlock($data);
    }

    /**
     * Finish the mode and append any additional data necessary
     *
     * @return string Any additional data
     */
    public function finish() {
        return '';
    }

    /**
     * Get the name of the current mode implementation
     *
     * @return string The current mode name
     */
    public function getMode() {
        return $this->mode;
    }

    /**
     * Reset the mode to start over (destroying any intermediate state)
     *
     * @return void
     */
    public function reset() {
        $this->state = $this->initv;
    }

    /**
     * Enforce the data block is the correct size for the cipher
     *
     * @param string $data The data to check
     *
     * @return void
     * @throws InvalidArgumentException if the block size is not correct
     */
    protected function enforceBlockSize($data) {
        if (strlen($data) != $this->cipher->getBlockSize()) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The data block must match the block size [%d:%d]',
                    strlen($data),
                    $this->cipher->getBlockSize()
                )
            );
        }
    }
}