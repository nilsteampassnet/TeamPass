<?php
/**
 * The Joomla based hash implementation based off of the md5-hex hash method
 *
 * It's worth noting, since there's no prefix, you cannot create a hash using
 * the factory method.
 *
 * PHP version 5.3
 *
 * @category   PHPCryptLib
 * @package    Password
 * @subpackage Implementation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */

namespace CryptLib\Password\Implementation;

use CryptLib\Random\Factory as RandomFactory;

/**
 * The Joomla based hash implementation based off of the md5-hex hash method
 *
 * It's worth noting, since there's no prefix, you cannot create a hash using
 * the factory method.
 *
 * @category   PHPCryptLib
 * @package    Password
 * @subpackage Implementation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class Joomla implements \CryptLib\Password\Password {

    /**
     * @var Generator The random generator to use for seeds
     */
    protected $generator = null;

    /**
     * Determine if the hash was made with this method
     *
     * @param string $hash The hashed data to check
     *
     * @return boolean Was the hash created by this method
     */
    public static function detect($hash) {
        return (boolean) preg_match('/^[a-fA-F0-9]{32}:[a-zA-z0-9]{32}$/', $hash);
    }

    /**
     * Return the prefix used by this hashing method
     *
     * @return string The prefix used
     */
    public static function getPrefix() {
        return false;
    }

    /**
     * Load an instance of the class based upon the supplied hash
     *
     * @param string $hash The hash to load from
     *
     * @return Password the created instance
     * @throws InvalidArgumentException if the hash wasn't created here
     */
    public static function loadFromHash($hash) {
        if (!static::detect($hash)) {
            throw new \InvalidArgumentException('Hash Not Created Here');
        }
        return new static();
    }

    /**
     * Build a new instance
     *
     * @param Generator $generator  The random generator to use for seeds
     * @param Factory   $factory    The hash factory to use for this instance
     *
     * @return void
     */
    public function __construct(
        \CryptLib\Random\Generator $generator = null
    ) {
        if (is_null($generator)) {
            $random    = new RandomFactory();
            $generator = $random->getMediumStrengthGenerator();
        }
        $this->generator = $generator;
    }

    /**
     * Create a password hash for a given plain text password
     *
     * @param string $password The password to hash
     *
     * @return string The formatted password hash
     */
    public function create($password) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $salt  = $this->generator->generateString(32, $chars);
        $hash  = md5($password . $salt);
        return $hash . ':' . $salt;
    }

    /**
     * Verify a password hash against a given plain text password
     *
     * @param string $password The password to hash
     * @param string $hash     The supplied ahsh to validate
     *
     * @return boolean Does the password validate against the hash
     */
    public function verify($password, $hash) {
        if (!static::detect($hash)) {
            throw new \InvalidArgumentException(
                'The hash was not created here, we cannot verify it'
            );
        }
        list ($hash, $salt) = explode(':', $hash, 2);
        $test               = md5($password . $salt);
        return $test == $hash;
    }

}
