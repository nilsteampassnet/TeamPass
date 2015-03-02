<?php
/**
 * The interface that all block cipher modes must implement
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
 * The interface that all block cipher modes must implement
 *
 * @category   PHPCryptLib
 * @package    Cipher
 * @subpackage Block
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @codeCoverageIgnore
 */
interface Mode {

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
    );

    /**
     * Decrypt the data using the supplied key, cipher and initialization vector
     *
     * @param string $data The data to decrypt
     *
     * @return string The decrypted data
     */
    public function decrypt($data);

    /**
     * Encrypt the data using the supplied key, cipher and initialization vector
     *
     * @param string $data The data to encrypt
     *
     * @return string The encrypted data
     */
    public function encrypt($data);

    /**
     * Finish the mode and append any additional data necessary
     *
     * @return string Any additional data
     */
    public function finish();

    /**
     * Get the name of the current mode implementation
     *
     * @return string The current mode name
     */
    public function getMode();

    /**
     * Reset the mode to start over (destroying any intermediate state)
     *
     * @return void
     */
    public function reset();

}
