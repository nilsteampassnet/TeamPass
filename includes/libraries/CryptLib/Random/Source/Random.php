<?php
/**
 * The Random Random Number Source
 *
 * This uses the *nix /dev/random device to generate high strength numbers
 *
 * PHP version 5.3
 *
 * @category   PHPCryptLib
 * @package    Random
 * @subpackage Source
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */

namespace CryptLib\Random\Source;

use CryptLib\Core\Strength;

/**
 * The Random Random Number Source
 *
 * This uses the *nix /dev/random device to generate high strength numbers
 *
 * @category   PHPCryptLib
 * @package    Random
 * @subpackage Source
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @codeCoverageIgnore
 */
class Random extends URandom {

    /**
     * @var string The file to read from
     */
    protected $file = '/dev/random';

    /**
     * Return an instance of Strength indicating the strength of the source
     *
     * @return Strength An instance of one of the strength classes
     */
    public static function getStrength() {
        return new Strength(Strength::HIGH);
    }

}
