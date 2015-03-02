<?php
/**
 * An implementation of the crypt library's Blowfish hash method
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
 * An implementation of the crypt library's Blowfish hash method
 *
 * @category   PHPCryptLib
 * @package    Key
 * @subpackage Derivation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class BCrypt
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
        $salt = $this->encode64($salt);
        if (strlen($salt) > 22) {
            $salt = substr($salt, 0, 22);
        } elseif (strlen($salt) < 22) {
            $salt = str_pad($salt, '0');
        }
        $expense = ceil(log($iterations, 2));
        $expense = $expense < 4 ? 4 : $expense;
        $expense = $expense > 31 ? 31 : $expense;
        $salt    = '$2a$'.str_pad($expense, 2, '0', STR_PAD_LEFT).'$'.$salt;
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
        return 'bcrypt';
    }

    /**
     * Encode a string for a blowfish salt
     *
     * @param string $string The string to encode
     *
     * @return The encoded string
     */
    protected function encode64($string) {
        return str_replace(
            array('+', '='),
            array('.', '/'),
            base64_encode($string)
        );
    }
}

