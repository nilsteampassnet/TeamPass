<?php
/**
 * A core wrapper class to provide easy access to all of the cryptographic functions
 * contained within the library
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

namespace CryptLib;

/**
 * The autoloader class will be autoloaded at this point even if another autoloader
 * is in use.  So if it does not exist at this point, we know we must bootstrap
 * the libraries.
 */
if (!class_exists('\\CryptLib\Core\AutoLoader', true)) {
    require_once 'bootstrap.php';
}

use CryptLib\Password\Factory as PasswordFactory;
use CryptLib\Random\Factory as RandomFactory;

/**
 * A core wrapper class to provide easy access to some of the cryptographic
 * functions contained within the library
 *
 * @category   PHPCryptLib
 * @package    Core
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class CryptLib {

    /**
     * Create a password hash from the supplied password and generator prefix
     *
     * @param string $password The password to hash
     * @param string $prefix   The prefix of the hashing function
     *
     * @return string The generated password hash
     */
    public function createPasswordHash($password, $prefix = '$2a$') {
        $factory = new PasswordFactory();
        return $factory->createHash($password, $prefix);
    }

    /**
     * Verify a password against a supplied password hash
     *
     * @param string $password The supplied password to attempt to verify
     * @param string $hash     The valid hash to verify against
     *
     * @return boolean Is the password valid
     */
    public function verifyPasswordHash($password, $hash) {
        $factory = new PasswordFactory();
        return $factory->verifyHash($password, $hash);
    }

    /**
     * Get a random element from the array
     *
     * @param array $sourceArray The source array to fetch from
     *
     * @return mixed A random element from the source array
     */
    public function getRandomArrayElement(array $sourceArray) {
        $keys       = array_keys($sourceArray);
        $upperBound = count($keys);
        $factory    = new RandomFactory;
        $generator  = $factory->getMediumStrengthGenerator();
        $key        = $generator->generateInt(0, $upperBound - 1);
        return $sourceArray[$keys[$key]];
    }

    /**
     * Generate a random full-byte string (characters 0 - 255)
     *
     * @param int $size The length of the generated string
     *
     * @return string The generated string
     */
    public function getRandomBytes($size) {
        $factory   = new RandomFactory;
        $generator = $factory->getMediumStrengthGenerator();
        return $generator->generate($size);
    }

    /**
     * Get a random number between the supplied boundaries
     *
     * @param int $min The smallest bound the generated number can be
     * @param int $max The upper bound on the generated number
     *
     * @return int The generated random number
     */
    public function getRandomNumber($min = 0, $max = PHP_INT_MAX) {
        $factory   = new RandomFactory;
        $generator = $factory->getMediumStrengthGenerator();
        return $generator->generateInt($min, $max);
    }

    /**
     * Generate a random token using base64 characters (a-zA-Z0-9./)
     *
     * @param int $size The number of characters in the generated output
     *
     * @return string The generated token string
     */
    public function getRandomToken($size) {
        $factory   = new RandomFactory;
        $generator = $factory->getMediumStrengthGenerator();
        return $generator->generateString($size);
    }

    /**
     * Shuffle an array.  This will preserve key => value relationships, and return
     * a new array that has been randomized in order.
     *
     * To get keys randomized, simply pass the result through array_values()...
     *
     * @param array $array The input array to randomize
     *
     * @return array The suffled array
     */
    public function shuffleArray(array $array) {
        $factory   = new RandomFactory;
        $generator = $factory->getMediumStrengthGenerator();
        $result    = array();
        $values    = array_values($array);
        $keys      = array_keys($array);
        $max       = count($array);
        for ($i = $max - 1; $i >= 0; $i--) {
            $int                 = $generator->generateInt(0, $i);
            $result[$keys[$int]] = $values[$int];
            unset($keys[$int], $values[$int]);
            $keys   = array_values($keys);
            $values = array_values($values);
        }
        return $result;
    }

    /**
     * Shuffle a string and return the randomized string
     *
     * @param string $string The string to randomize
     *
     * @return string The shuffled string
     */
    public function shuffleString($string) {
        $factory   = new RandomFactory;
        $generator = $factory->getMediumStrengthGenerator();
        $array     = str_split($string);
        $result    = $this->shuffleArray($array);
        return implode('', $result);
    }

}