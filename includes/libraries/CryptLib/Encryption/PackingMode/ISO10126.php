<?php
/**
 * A Packing Mode implementation of the ISO 10126 standard
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
 * A Packing Mode implementation of the ISO 10126 standard
 *
 * @category   PHPCryptLib
 * @package    Encryption
 * @subpackage PackingMode
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class ISO10126 implements \CryptLib\Encryption\PackingMode {

    /**
     * Pad the string to the specified size
     *
     * @param string $string    The string to pad
     * @param int    $blockSize The size to pad to
     *
     * @return string The padded string
     */
    public function pad($string, $blockSize = 32) {
        $pad    = $blockSize - (strlen($string) % $blockSize);
        $padstr = '';
        for ($i = 1; $i < $pad; $i++) {
            $padstr .= chr(mt_rand(0, 255));
        }
        return $string . $padstr . chr($pad);
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
        return substr($string, 0, $len);
    }

}
