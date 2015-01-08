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

    private $state = null;

    /**
     * Return an instance of Strength indicating the strength of the source
     *
     * @return Strength An instance of one of the strength classes
     */
    public static function getStrength() {
        return new Strength(Strength::VERYLOW);
    }

    public function __construct() {
        $state = '';
        if (function_exists('posix_times')) {
            $state .= serialize(posix_times());
        }
        $state      .= getmypid() . memory_get_usage();
        $state      .= serialize($_ENV);
        $this->state = hash('sha512', $state, true);
    }

    /**
     * Generate a random string of the specified size
     *
     * @param int $size The size of the requested random string
     *
     * @return string A string of the requested size
     */
    public function generate($size) {
        $result      = '';
        $seed        = microtime() . memory_get_usage();
        $this->state = hash('sha512', $this->state . $seed, true);
        /**
         * Make the generated randomness a bit better by forcing a GC run which
         * should complete in a indeterminate amount of time, hence improving
         * the strength of the randomness a bit. It's still not crypto-safe,
         * but at least it's more difficult to predict.
         */
        gc_collect_cycles();
        for ($i = 0; $i < $size; $i += 8) {
            $seed        = $this->state . microtime() . pack('N', $i);
            $this->state = hash('sha512', $seed, true);
            /**
             * We only use the first 8 bytes here to prevent exposing the state
             * in its entirety, which could potentially expose other random 
             * generations in the future (in the same process)...
             */
            $result .= substr($this->state, 0, 8);
        }
        return substr($result, 0, $size);
    }

}
