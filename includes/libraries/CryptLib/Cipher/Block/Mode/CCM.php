<?php
/**
 * The CCM (Counter CBC-MAC) mode implementation
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
 * @see        http://tools.ietf.org/html/rfc3610
 */

namespace CryptLib\Cipher\Block\Mode;

/**
 * The CCM (Counter CBC-MAC) mode implementation
 *
 * @category   PHPCryptLib
 * @package    Cipher
 * @subpackage Block
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @see        http://tools.ietf.org/html/rfc3610
 */
class CCM extends \CryptLib\Cipher\Block\AbstractMode {

    /**
     * Indicates which mode the cipher mode is in (encryption/decryption)
     */
    const MODE_DECRYPT = 1;

    /**
     * Indicates which mode the cipher mode is in (encryption/decryption)
     */
    const MODE_ENCRYPT = 2;

    /**
     * @var int The number of octets in the Authentication field
     */
    protected $authFieldSize = 8;

    /**
     * @var string The data buffer for this encryption instance
     */
    protected $data = '';

    /**
     * @var int The number of octets in the length field
     */
    protected $lSize = 4;

    /**
     * @var string The mode name for the current instance
     */
    protected $mode = 'ccm';

    /**
     * @var int The current encryption mode (enc/dec)
     */
    protected $encryptionMode = 0;

    /**
     * @var array Mode specific options
     */
    protected $options = array(
        'adata' => '',
        'lSize' => 4,
        'aSize' => 8,
    );

    /**
     * @var string The initialization vector to use for this instance
     */
    protected $usedIV = '';

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
        $this->options = $options + $this->options;
        $this->cipher  = $cipher;
        $this->initv   = $initv;
        $this->adata   = $this->options['adata'];
        $this->setLSize($this->options['lSize']);
        $this->setAuthFieldSize($this->options['aSize']);
        $this->reset();
    }

    /**
     * Finish the mode and append any additional data necessary
     *
     * @return string Any additional data
     */
    public function finish() {
        $mask = (static::MODE_DECRYPT | static::MODE_ENCRYPT);
        if (!($this->encryptionMode ^ $mask)) {
            throw new \LogicException('Cannot encrypt and decrypt in same state');
        }
        if ($this->encryptionMode & static::MODE_DECRYPT) {
            return $this->decryptBlockFinal();
        } else {
            return $this->encryptBlockFinal();
        }
    }

    /**
     * Set the auth field size to a different value.
     *
     * Valid values: 4, 6, 8, 10, 12, 14, 16
     *
     * Note that increasing this size will make it harder for an attacker to
     * modify the message payload
     *
     * @param int $new The new size of auth field to append
     *
     * @return void
     * @throws InvalidArgumentException If the number is outside of the range
     */
    public function setAuthFieldSize($new) {
        if (!in_array($new, array(4, 6, 8, 10, 12, 14, 16))) {
            throw new \InvalidArgumentException(
                'The Auth Field must be one of: 4, 6, 8, 10, 12, 14, 16'
            );
        }
        $this->authFieldSize = (int) $new;
        $this->reset();
    }

    /**
     * Set the size of the length field.  This is a tradeoff between the maximum
     * message size and the size of the initialization vector
     *
     * Valid values are 2, 3, 4, 5, 6, 7, 8
     *
     * @param int $new The new LSize to use
     *
     * @return void
     * @throws InvalidArgumentException If the number is outside of the range
     */
    public function setLSize($new) {
        if ($new < 2 || $new > 8) {
            throw new \InvalidArgumentException(
                'The LSize must be between 2 and 8 inclusive'
            );
        }
        $this->lSize = (int) $new;
        $this->reset();
    }

    /**
     * Reset the mode to start over (destroying any intermediate state)
     *
     * @return void
     */
    public function reset() {
        $this->usedIV         = $this->extractInitv(
            $this->initv,
            $this->cipher->getBlockSize()
        );
        $this->encryptionMode = 0;
        $this->data           = '';
    }

    /**
     * Decrypt the data using the supplied key, cipher
     *
     * @param string $data The data to decrypt
     *
     * @return string The decrypted data
     */
    protected function decryptBlock($data) {
        $this->data .= $data;
        $this->encryptionMode |= static::MODE_DECRYPT;
    }

    /**
     * Perform the decryption of the block data
     *
     * @return string The final data
     */
    protected function decryptBlockFinal() {
        $message    = substr($this->data, 0, -1 * $this->authFieldSize);
        $uValue     = substr($this->data, -1 * $this->authFieldSize);
        $data       = $this->encryptMessage($message, $uValue);
        $computedT  = substr($data, -1 * $this->authFieldSize);
        $data       = substr($data, 0, -1 * $this->authFieldSize);
        $authFieldT = $this->computeAuthField($data);
        if ($authFieldT != $computedT) {
            return false;
        }
        return rtrim($data, chr(0));
    }

    /**
     * Encrypt the data using the supplied key, cipher
     *
     * @param string $data The data to encrypt
     *
     * @return string The encrypted data
     */
    protected function encryptBlock($data) {
        $this->data .= $data;
        $this->encryptionMode |= static::MODE_ENCRYPT;
    }

    /**
     * Perform the encryption of the block data
     *
     * @return string The final data
     */
    protected function encryptBlockFinal() {
        $authFieldT = $this->computeAuthField($this->data);
        $data       = $this->encryptMessage($this->data, $authFieldT);
        return $data;
    }

    /**
     * Compute the authentication field
     *
     * @param string $data   The data to compute with
     *
     * @return string The computed MAC Authentication Code
     */
    protected function computeAuthField($data) {
        $blockSize = $this->cipher->getBlockSize();
        $flags     = pack(
            'C',
            64 * (empty($this->adata) ? 0 : 1)
                + 8 * (($this->authFieldSize - 2) / 2)
                + ($this->lSize - 1)
        );
        $blocks    = array(
            $flags . $this->usedIV . pack($this->getLPackString(), strlen($data))
        );
        if (strlen($data) % $blockSize != 0) {
            $data .= str_repeat(chr(0), $blockSize - (strlen($data) % $blockSize));
        }

        $blocks = array_merge(
            $blocks,
            $this->processAData($this->adata, $blockSize)
        );
        if (!empty($data)) {
            $blocks = array_merge($blocks, str_split($data, $blockSize));
        }
        $crypted = array(
            1 => $this->cipher->encryptBlock($blocks[0])
        );

        $blockLen = count($blocks);
        for ($i = 1; $i < $blockLen; $i++) {
            $crypted[$i + 1] = $this->cipher->encryptBlock(
                $crypted[$i] ^ $blocks[$i]
            );
        }
        return substr(end($crypted), 0, $this->authFieldSize);
    }

    /**
     * Encrypt the data using the supplied method
     *
     * @param string $data      The data to encrypt
     * @param string $authValue The auth value field
     *
     * @return string The encrypted data with authfield payload
     */
    protected function encryptMessage($data, $authValue) {
        $blockSize = $this->cipher->getBlockSize();
        $flags     = pack('C', ($this->lSize - 1));
        $blocks    = str_split($data, $blockSize);
        $sblocks   = array();
        $blockLen  = count($blocks);
        for ($i = 0; $i <= $blockLen; $i++) {
            $sblocks[] = $this->cipher->encryptBlock(
                $flags . $this->usedIV . pack($this->getLPackString(), $i)
            );
        }
        $encrypted = '';
        foreach ($blocks as $key => $value) {
            if (strlen($value) < $blockSize) {
                $sblocks[$key + 1] = substr($sblocks[$key + 1], 0, strlen($value));
            }
            $encrypted .= $sblocks[$key + 1] ^ $value;
        }
        $sValue = substr($sblocks[0], 0, $this->authFieldSize);
        $uValue = $authValue ^ $sValue;
        return $encrypted . $uValue;
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
        return true;
    }

    /**
     * Extract the nonce from the initialization vector
     *
     * @param string $initv     The initialization Vector to trim
     * @param int    $blockSize The size of the final nonce
     *
     * @return string The sized nonce
     * @throws InvalidArgumentException if the IV is too short
     */
    protected function extractInitv($initv, $blockSize) {
        $initSize = $blockSize - 1 - $this->lSize;
        if (strlen($initv) < $initSize) {
            throw new \InvalidArgumentException(sprintf(
                'Supplied Initialization Vector is too short, should be %d bytes',
                $initSize
            ));
        }
        return substr($initv, 0, $initSize);
    }

    /**
     * Get a packing string related to the instance lSize variable
     *
     * @return string The pack() string to use to pack the length variables
     * @see pack()
     */
    protected function getLPackString() {
        if ($this->lSize <= 3) {
            return str_repeat('x', $this->lSize - 2) . 'n';
        }
        return str_repeat('x', $this->lSize - 4) . 'N';
    }

    /**
     * Process the Authentication data for authenticating
     *
     * @param string $adata     The data to authenticate with
     * @param int    $blockSize The block size for the cipher
     *
     * @return array An array of strings bound by the supplied blocksize
     */
    protected function processAData($adata, $blockSize) {
        if (!empty($this->adata)) {
            if (strlen($this->adata) < ((1 << 16) - (1 << 8))) {
                $len = pack('n', strlen($this->adata));
            } else {
                $len = chr(0xff) . chr(0xfe) . pack('N', strlen($this->adata));
            }
            $temp = $len . $this->adata;
            if (strlen($temp) % $blockSize != 0) {
                //Pad the string to exactly mod16
                $temp .= str_repeat(
                    chr(0),
                    $blockSize - (strlen($temp) % $blockSize)
                );
            }
            return str_split($temp, $blockSize);
        }
        return array();
    }

}
