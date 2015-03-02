<?php
/**
 * A packing mode implementation for no padding
 *
 * This is provided for completeness only.  Do not use this with actual ciphers
 *
 * PHP version 5.3
 *
 * @category   PHPCryptLib
 * @package    Encryption
 * @subpackage PackingMode
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */

namespace CryptLib\Encryption\PackingMode;

/**
 * A packing mode implementation for no padding
 *
 * This is provided for completeness only.  Do not use this with actual ciphers
 *
 * @category   PHPCryptLib
 * @package    Encryption
 * @subpackage PackingMode
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class None implements \CryptLib\Encryption\PackingMode {

    /**
     * Pad the string to the specified size
     *
     * @param string $string    The string to pad
     * @param int    $blockSize The size to pad to
     *
     * @return string The padded string
     */
    public function pad($string, $blockSize = 32) {
        return $string;
    }

    /**
     * Strip the padding from the supplied string
     *
     * @param string $string The string to trim
     *
     * @return string The unpadded string
     */
    public function strip($string) {
        return $string;
    }

}
