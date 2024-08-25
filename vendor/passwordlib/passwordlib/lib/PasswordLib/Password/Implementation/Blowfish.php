<?php
/**
 * The Blowfish password hashing implementation
 *
 * Use this class to generate and validate Blowfish password hashes.
 *
 * PHP version 5.3
 *
 * @category   PHPPasswordLib
 * @package    Password
 * @subpackage Implementation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */

namespace PasswordLib\Password\Implementation;

use PasswordLib\Random\Factory as RandomFactory;

/**
 * The Blowfish password hashing implementation
 *
 * Use this class to generate and validate Blowfish password hashes.
 *
 * @category   PHPPasswordLib
 * @package    Password
 * @subpackage Implementation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class Blowfish extends Crypt {

    protected $saltLen = 22;

    /**
     * Determine if the hash was made with this method
     *
     * @param string $hash The hashed data to check
     *
     * @return boolean Was the hash created by this method
     */
    public static function detect($hash) {
        static $regex = '/^\$2a\$(0[4-9]|[1-2][0-9]|3[0-1])\$[a-zA-Z0-9.\/]{53}/';
        return 1 == preg_match($regex, $hash);
    }

    /**
     * Return the prefix used by this hashing method
     *
     * @return string The prefix used
     */
    public static function getPrefix() {
        return '$2a$';
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
        list(, , $iterations) = explode('$', $hash, 4);
        return new static((int) $iterations);
    }

    protected function generateSalt() {
        $salt   = parent::generateSalt();
        $prefix = '$2a$' . str_pad($this->iterations, 2, '0', STR_PAD_LEFT);
        return $prefix . '$' . $salt;
    }
}