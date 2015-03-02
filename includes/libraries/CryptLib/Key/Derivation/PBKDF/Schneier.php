<?php
/**
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 * @version    Build @@version@@
 */

namespace CryptLib\Key\Derivation\PBKDF;

use CryptLib\Hash\Hash;

/**
 * Description of pbkdf2
 *
 * @author ircmaxell
 */
class Schneier
    extends \CryptLib\Key\Derivation\AbstractDerivation
    implements \CryptLib\Key\Derivation\PBKDF
{

    public function derive($password, $salt, $iterations, $length) {
        $size = Hash::getHashSize($this->hash);
        if ($length > $size) {
            throw new \InvalidArgumentException('Length is too long for hash');
        }
        $tmp = hash($this->hash, $password . $salt, true);
        for ($i = 2; $i <= $iterations; $i++) {
            $tmp = hash($this->hash, $tmp . $password . $salt, true);
        }
        return substr($tmp, 0, $length);
    }

    /**
     * Get the signature for this implementation
     *
     * This should include all information needed to build the same instance
     * later.
     *
     * @return string The signature for this instance
     */
    public function getSignature() {
        return 'schneier-'.$this->hash;
    }

}
