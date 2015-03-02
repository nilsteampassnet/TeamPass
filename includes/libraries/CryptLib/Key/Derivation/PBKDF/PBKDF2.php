<?php
/**
 * An implementation of the RFC 2898 PBKDF2 Standard key derivation function
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
 * An implementation of the RFC 2898 PBKDF2 Standard key derivation function
 *
 * @see        http://www.ietf.org/rfc/rfc2898.txt
 * @category   PHPCryptLib
 * @package    Key
 * @subpackage Derivation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class PBKDF2
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
        $size   = Hash::getHashSize($this->hash);
        $len    = ceil($length / $size);
        $result = '';
        for ($i = 1; $i <= $len; $i++) {
            $tmp = hash_hmac(
                $this->hash,
                $salt . pack('N', $i),
                $password,
                true
            );
            $res = $tmp;
            for ($j = 1; $j < $iterations; $j++) {
                $tmp  = hash_hmac($this->hash, $tmp, $password, true);
                $res ^= $tmp;
            }
            $result .= $res;
        }
        return substr($result, 0, $length);
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
        return 'pbkdf2-' . $this->hash;
    }

}

