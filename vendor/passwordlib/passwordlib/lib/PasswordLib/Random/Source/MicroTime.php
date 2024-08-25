<?php
/**
 * The Microtime Random Number Source
 *
 * This uses the current micro-second (looped several times) for a **very** weak
 * random number source.  This is only useful when combined with several other
 * stronger sources
 *
 * PHP version 5.3
 *
 * @category   PHPPasswordLib
 * @package    Random
 * @subpackage Source
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */

namespace PasswordLib\Random\Source;

use PasswordLib\Core\Strength;

/**
 * The Microtime Random Number Source
 *
 * This uses the current micro-second (looped several times) for a **very** weak
 * random number source.  This is only useful when combined with several other
 * stronger sources
 *
 * @category   PHPPasswordLib
 * @package    Random
 * @subpackage Source
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @codeCoverageIgnore
 */
class MicroTime implements \PasswordLib\Random\Source {

    /**
     * Return an instance of Strength indicating the strength of the source
     *
     * @return Strength An instance of one of the strength classes
     */
    public static function getStrength() {
        return new Strength(Strength::VERYLOW);
    }

    /**
     * Generate a random string of the specified size
     *
     * @param int $size The size of the requested random string
     *
     * @return string A string of the requested size
     */
    public function generate($size) {
        $result = '';
        $seed   = microtime() . memory_get_usage() . getmypid();
        for ($i = 0; $i < $size; $i++) {
            $seed    = md5(microtime() . $seed, true);
            $result .= $seed[$i % 16];
        }
        return $result;
    }

}
