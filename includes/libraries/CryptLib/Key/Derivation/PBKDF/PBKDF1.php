<?php
/**
 * An implementation of the RFC 2898 PBKDF1 Standard key derivation function
 *
 * PHP version 5.3
 *
 * @see        http://www.ietf.org/rfc/rfc2898.txt
 * @category   PHPCryptLib
 * @package    Key
 * @subpackage Derivation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */

namespace CryptLib\Key\Derivation\PBKDF;

use CryptLib\Hash\Hash;

/**
 * An implementation of the RFC 2989 PBKDF1 Standard key derivation function
 *
 * @see        http://www.ietf.org/rfc/rfc2898.txt
 * @category   PHPCryptLib
 * @package    Key
 * @subpackage Derivation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class PBKDF1
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
        $size = Hash::getHashSize($this->hash);
        if ($length > $size) {
            $message = 'Length is too long for hash';
            throw new \InvalidArgumentException($message);
        }
        $tmp = hash($this->hash, $password . $salt, true);
        for ($i = 2; $i <= $iterations; $i++) {
            $tmp = hash($this->hash, $tmp, true);
        }
        return substr($tmp, 0, $length);
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
        return 'pbkdf1-' . $this->hash;
    }

}

