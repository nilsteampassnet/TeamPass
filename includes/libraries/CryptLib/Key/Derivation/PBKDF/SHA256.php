<?php
/**
 * An implementation of the crypt library's SHA512 hash method
 *
 * PHP version 5.3
 *
 * @category   PHPCryptLib
 * @package    Key
 * @subpackage Derivation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */

namespace CryptLib\Key\Derivation\PBKDF;

/**
 * An implementation of the crypt library's SHA512 hash method
 *
 * PHP version 5.3
 *
 * @category   PHPCryptLib
 * @package    Key
 * @subpackage Derivation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class SHA256
    extends \CryptLib\Key\Derivation\AbstractDerivation
    implements \CryptLib\Key\Derivation\PBKDF
{

    /**
     * Derive a key from the supplied arguments
     *
     * @param string $password   The password to derive from
     * @param string $salt       The salt string to use
     * @param int    $iterations The number of iterations to use
     * @param int    $length     The size of the string to generate
     *
     * @return string The derived key
     */
    public function derive($password, $salt, $iterations, $length) {
        $salt = substr(str_pad($salt, 16, chr(0)), 0, 16);
        $salt = '$5$rounds='.$iterations.'$'.$salt;
        return crypt($password, $salt);
    }

    /**
     * Get the signature for this implementation
     *
     * This should include all information needed to build the same isntance
     * later.
     *
     * @return string The signature for this instance
     */
    public function getSignature() {
        return 'sha256';
    }

}

