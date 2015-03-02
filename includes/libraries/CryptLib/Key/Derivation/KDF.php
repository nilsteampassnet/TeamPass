<?php
/**
 * The standard Key Derivation Function interface
 *
 * PHP version 5.3
 *
 * @category   PHPCryptLib
 * @package    Key
 * @subpackage Derivation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT Licenses
 * @version    Build @@version@@
 */

namespace CryptLib\Key\Derivation;

/**
 * The standard Key Derivation Function interface
 *
 * @category   PHPCryptLib
 * @package    Key
 * @subpackage Derivation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @codeCoverageIgnore
 */
interface KDF {

    /**
     * Derive a key of the specified length based on the inputted secret
     *
     * @param string $secret The secret to base the key on
     * @param int    $length The length of the key to derive
     * @param string $other  Additional data to append to the key
     *
     * @return string The generated key
     */
    public function derive($secret, $length, $other = '');

}
