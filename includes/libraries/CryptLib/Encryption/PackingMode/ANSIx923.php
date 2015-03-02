<?php
/**
 * A packing mode implementation for ASNIx923 padding
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
 * A packing mode implementation for ASNIx923 padding
 *
 * @category   PHPCryptLib
 * @package    Encryption
 * @subpackage PackingMode
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class ANSIx923 implements \CryptLib\Encryption\PackingMode {

    /**
     * Pad the string to the specified size
     *
     * @param string $string    The string to pad
     * @param int    $blockSize The size to pad to
     *
     * @return string The padded string
     */
    public function pad($string, $blockSize = 32) {
        $pad = $blockSize - (strlen($string) % $blockSize);
        return $string . str_repeat(chr(0), $pad - 1) . chr($pad);
    }

    /**
     * Strip the padding from the supplied string
     *
     * @param string $string The string to trim
     *
     * @return string The unpadded string
     */
    public function strip($string) {
        $end = ord(substr($string, -1));
        $len = strlen($string) - $end;
        $tmp = str_repeat(chr(0), $end - 1) . chr($end);
        if (substr($string, $len) == $tmp) {
            return substr($string, 0, $len);
        }
        return false;
    }

}
