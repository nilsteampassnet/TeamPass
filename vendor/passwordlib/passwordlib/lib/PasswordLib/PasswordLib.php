<?php
namespace PasswordLib;
/**
 * A core wrapper class to provide easy access to all of the cryptographic functions
 * contained within the library
 *
 * PHP version 5.3
 *
 * @category   PHPPasswordLib
 * @package    Core
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */


/**
 * The autoloader class will be autoloaded at this point even if another autoloader
 * is in use.  So if it does not exist at this point, we know we must bootstrap
 * the libraries.
 */
    /*
if (!class_exists('\\PasswordLib\Core\AutoLoader', true)) {
    require_once 'bootstrap.php';
}
*/

use PasswordLib\Password\Factory as PasswordFactory;
use PasswordLib\Random\Factory as RandomFactory;

require_once dirname(__FILE__)."/Password/Factory.php";
require_once dirname(__FILE__)."/Random/Factory.php";

/**
 * A core wrapper class to provide easy access to some of the cryptographic
 * functions contained within the library
 *
 * @category   PHPPasswordLib
 * @package    Core
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class PasswordLib {

    /**
     * Create a password hash from the supplied password and generator prefix
     *
     * @param string $password The password to hash
     * @param string $prefix   The prefix of the hashing function
     *
     * @return string The generated password hash
     */
    public function createPasswordHash(
        $password,
        $prefix = '$2a$',
        array $options = array()
    ) {
        // if we're in a later version of PHP, we need to change this
        if ($prefix == '$2a$' && version_compare(PHP_VERSION, '5.3.7') >= 0) {
            $prefix = '$2y$';
        }

        $factory = new PasswordFactory();
        return $factory->createHash($password, $prefix, $options);
    }

    /**
     * Verify a password against a supplied password hash
     *
     * @param string $password The supplied password to attempt to verify
     * @param string $hash     The valid hash to verify against
     *
     * @throws \DomainException If the hash is invalid or impossible to verify
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
        $array  = str_split($string);
        $result = $this->shuffleArray($array);
        return implode('', $result);
    }

}
