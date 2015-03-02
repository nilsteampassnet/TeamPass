<?php
/**
 * A class for arbitrary precision math functions
 *
 * PHP version 5.3
 *
 * @category   PHPCryptLib
 * @package    Core
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */
namespace CryptLib\Core;

/**
 * A class for arbitrary precision math functions
 *
 * @category   PHPCryptLib
 * @package    Core
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
abstract class BigMath {

    /**
     * Get an instance of the big math class
     *
     * This is NOT a singleton.  It simply loads the proper strategy
     * given the current server configuration
     *
     * @return \CryptLib\Core\BigMath A big math instance
     */
    public static function createFromServerConfiguration() {
        //@codeCoverageIgnoreStart
        if (extension_loaded('bcmath')) {
            return new \CryptLib\Core\BigMath\BCMath();
        } elseif (extension_loaded('gmp')) {
            return new \CryptLib\Core\BigMath\GMP();
        } else {
            return new \CryptLib\Core\BigMath\PHPMath();
        }
        //@codeCoverageIgnoreEnd
    }

    /**
     * Add two numbers together
     *
     * @param string $left  The left argument
     * @param string $right The right argument
     *
     * @return A base-10 string of the sum of the two arguments
     */
    abstract public function add($left, $right);

    /**
     * Subtract two numbers
     *
     * @param string $left  The left argument
     * @param string $right The right argument
     *
     * @return A base-10 string of the difference of the two arguments
     */
    abstract public function subtract($left, $right);

}