<?php
/**
 * The UniqID Random Number Source
 *
 * This uses the internal `uniqid()` function to generate low strength random
 * numbers.
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
 * The UniqID Random Number Source
 *
 * This uses the internal `uniqid()` function to generate low strength random
 * numbers.
 *
 * @category   PHPPasswordLib
 * @package    Random
 * @subpackage Source
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @codeCoverageIgnore
 */
class UniqID implements \PasswordLib\Random\Source {

    /**
     * Return an instance of Strength indicating the strength of the source
     *
     * @return Strength An instance of one of the strength classes
     */
    public static function getStrength() {
        return new Strength(Strength::LOW);
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
        while (strlen($result) < $size) {
            $result = uniqid($result, true);
        }
        return substr($result, 0, $size);
    }

}
