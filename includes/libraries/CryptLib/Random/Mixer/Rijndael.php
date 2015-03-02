<?php
/**
 * The Rijndael-128 based high strength mixer class
 *
 * This class implements a mixer based upon the recommendations in RFC 4086
 * section 5.2
 *
 * PHP version 5.3
 *
 * @see        http://tools.ietf.org/html/rfc4086#section-5.2
 * @category   PHPCryptLib
 * @package    Random
 * @subpackage Mixer
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */

namespace CryptLib\Random\Mixer;

use \CryptLib\Cipher\Factory as CipherFactory;
use \CryptLib\Core\Strength;

/**
 * The Rijndael-128 based high strength mixer class
 *
 * This class implements a mixer based upon the recommendations in RFC 4086
 * section 5.2
 *
 * @see        http://tools.ietf.org/html/rfc4086#section-5.2
 * @category   PHPCryptLib
 * @package    Random
 * @subpackage Mixer
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class Rijndael extends DES {

    /**
     * An instance of a Rijndael symmetric encryption cipher
     *
     * @var Cipher The Rijndael cipher instance
     */
    protected $cipher = 'rijndael-128';

    /**
     * Return an instance of Strength indicating the strength of the source
     *
     * @return Strength An instance of one of the strength classes
     */
    public static function getStrength() {
        return new Strength(Strength::HIGH);
    }

}
