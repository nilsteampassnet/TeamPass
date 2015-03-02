<?php
/**
 * An implementation of the RFC 10833-2 KDF3 Standard key derivation function
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

namespace CryptLib\Key\Derivation\KDF;

use CryptLib\Hash\Hash;

/**
 * An implementation of the RFC 10833-2 KDF3 Standard key derivation function
 *
 * @category   PHPCryptLib
 * @package    Key
 * @subpackage Derivation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class KDF3
    extends \CryptLib\Key\Derivation\AbstractDerivation
    implements \CryptLib\Key\Derivation\KDF
{

    /**
     * @var array An array of options for the key derivation function
     */
    protected $options = array(
        'hash'        => 'sha512',
        'pAmt'        => 4,
    );

    /**
     * Derive a key of the specified length based on the inputted secret
     *
     * @param string $secret The secret to base the key on
     * @param int    $length The length of the key to derive
     * @param string $other  Additional data to append to the key
     *
     * @return string The generated key
     */
    public function derive($secret, $length, $other = '') {
        $size = Hash::getHashSize($this->hash);
        $len  = ceil($length / $size);
        $res  = '';
        $stub = str_repeat(chr(0), max($this->options['pAmt'], 0));
        for ($i = 0; $i < $len; $i++) {
            $tmp  = $stub . pack('N', $i);
            $res .= hash($this->hash, $tmp . $secret . $other, true);
        }
        return substr($res, 0, $length);
    }

}

