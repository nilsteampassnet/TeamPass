<?php
/**
 * The basic Hash implementation.
 *
 * It's worth noting, since there's no prefix, you cannot create a hash using
 * the factory method.
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
 * The basic Hash implementation.
 *
 * It's worth noting, since there's no prefix, you cannot create a hash using
 * the factory method.
 *
 * @category   PHPPasswordLib
 * @package    Password
 * @subpackage Implementation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class Hash extends \PasswordLib\Password\AbstractPassword {

    /**
     * @var Hash The hash function to use (MD5)
     */
    protected $defaultOptions = array(
        'hash' => 'sha512',
    );

    /**
     * Determine if the hash was made with this method
     *
     * @param string $hash The hashed data to check
     *
     * @return boolean Was the hash created by this method
     */
    public static function detect($hash) {
        $res  = preg_match('/^[a-fA-F0-9]+$/', $hash);
        $res &= (int) in_array(strlen($hash), array(32, 40, 64, 128));
        return (boolean) $res;
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
        $hashMethod = '';
        switch (strlen($hash)) {
            case 32:
                $hashMethod = 'md5';
                break;
            case 40:
                $hashMethod = 'sha1';
                break;
            case 64:
                $hashMethod = 'sha256';
                break;
            case 128:
                $hashMethod = 'sha512';
                break;
        }
        return new static(array('hash' => $hashMethod));
    }

    /**
     * Create a password hash for a given plain text password
     *
     * @param string $password The password to hash
     *
     * @return string The formatted password hash
     */
    public function create($password) {
        throw new \BadMethodCallException(
            'Unsalted Passwords are only implemented for verification'
        );
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
        $password = $this->checkPassword($password);
        $test     = hash($this->options['hash'], $password);
        return $this->compareStrings($test, $hash);
    }

}
