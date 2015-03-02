<?php
/**
 * An abstract class for simplifing creation of ciphers
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
 * An abstract class for simplifing creation of ciphers
 *
 * @category   PHPCryptLib
 * @package    Cipher
 * @subpackage Block
 */
abstract class AbstractCipher implements \CryptLib\Cipher\Block\Cipher {

    /**
     * @var int The block size for the cipher
     */
    protected $blockSize = 0;

    /**
     * @var string The cipher name for the current instance
     */
    protected $cipher = '';

    /**
     * @var boolean Is the cipher ready to encrypt/decrypt
     */
    protected $initialized = false;

    /**
     * @var string The key to use for encryption/decryption
     */
    protected $key = '';

    /**
     * @var int The size of the key to use
     */
    protected $keySize = 0;

    /**
     * Decrypt a block of data
     * 
     * @param string $data The ciphertext to decrypt
     * 
     * @return string The decrypted data
     */
    abstract protected function decryptBlockData($data);

    /**
     * Encrypt a block of data
     * 
     * @param string $data The plaintext to encrypt
     * 
     * @return string The encrypted cipher text
     */
    abstract protected function encryptBlockData($data);

    /**
     * Construct the instance for the supplied cipher name
     *
     * @param string $cipher The cipher to implement
     *
     * @return void
     * @throws InvalidArgumentException if the cipher is not supported
     */
    public function __construct($cipher) {
        $ciphers = static::getSupportedCiphers();
        if (in_array($cipher, $ciphers)) {
            $this->cipher = $cipher;
        } else {
            throw new \InvalidArgumentException('Unsupported Cipher Supplied');
        }
    }

    /**
     * Decrypt a block of data using the supplied string key.
     *
     * Note that the supplied data should be the same size as the block size of
     * the cipher being used.
     *
     * @param string $data The data to decrypt
     *
     * @return string The result decrypted data
     * @throws InvalidArgumentException If the data size is not the block size
     * @throws RuntimeException If the cipher is not initialized
     */
    public function decryptBlock($data) {
        $this->enforceInitializedCipher();
        $this->enforceProperBlockSize($data);
        return $this->decryptBlockData($data);
    }

    /**
     * Encrypt a block of data using the supplied string key.
     *
     * Note that the supplied data should be the same size as the block size of
     * the cipher being used.
     *
     * @param string $data The data to encrypt
     *
     * @return string The result encrypted data
     * @throws InvalidArgumentException If the data size is not the block size
     * @throws RuntimeException If the cipher is not initialized
     */
    public function encryptBlock($data) {
        $this->enforceInitializedCipher();
        $this->enforceProperBlockSize($data);
        return $this->encryptBlockData($data);
    }

    /**
     * Get the block size for the current initialized cipher
     *
     * @param string $key The key the data will be encrypted with
     *
     * @return int The block size for the current cipher
     */
    public function getBlockSize() {
        return $this->blockSize;
    }

    /**
     * Get the string name of the current cipher instance
     *
     * @return string The current instantiated cipher
     */
    public function getCipher() {
        return $this->cipher;
    }

    /**
     * Get the key size for the current initialized cipher
     * 
     * @return int The key size for the current cipher
     */
    public function getKeySize() {
        return $this->keySize;
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
        if (strlen($key) != $this->getKeySize()) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The supplied key block is not the correct size [%d:%d]',
                    strlen($key),
                    $this->getKeySize()
                )
            );
        }
        $this->key         = $key;
        $this->initialized = $this->initialize();
    }

    /**
     * Check to see if the cipher is initialized
     * 
     * @return void
     * @throws RuntimeException If the cipher is not initialized
     */
    protected function enforceInitializedCipher() {
        if (!$this->initialized) {
            throw new \RuntimeException(
                'The cipher has not been properly initialized'
            );
        }
    }

    /**
     * Check to see if the data is of the correct block size
     *
     * @param string $data The data block to check
     * 
     * @return void
     * @throws InvalidArgumentException if the data is not the correct size
     */
    protected function enforceProperBlockSize($data) {
        if (strlen($data) != $this->getBlockSize()) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The supplied data block is not the correct size [%d:%d]',
                    strlen($data),
                    $this->getBlockSize()
                )
            );
        }
    }

    /**
     * Initialize the function after the key is set
     *
     * @return boolean The status of the initialization
     */
    protected function initialize() {
        return true;
    }

}