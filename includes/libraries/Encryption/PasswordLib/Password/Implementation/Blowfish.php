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

    protected static $prefix = '$2a$';

    protected $saltLen = 22;

    protected $defaultOptions = array(
        'cost' => 10,
    );

    /**
     * Return the prefix used by this hashing method
     *
     * @return string The prefix used
     */
    public static function getPrefix() {
        if (version_compare(PHP_VERSION, '5.3.7') >= 0) {
            return '$2y$';
        } else {
            return '$2a$';
        }
    }

    /**
     * Determine if the hash was made with this method
     *
     * @param string $hash The hashed data to check
     *
     * @return boolean Was the hash created by this method
     */
    public static function detect($hash) {
        static $regex = '/^\$2[ay]\$(0[4-9]|[1-2][0-9]|3[0-1])\$[a-zA-Z0-9.\/]{53}/';
        return 1 == preg_match($regex, $hash);
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
        return new static(array('cost' => $iterations));
    }

    /**
     * Set an option for the instance
     *
     * @param string $option The option to set
     * @param mixed  $value  The value to set the option to
     *
     * @return $this
     */
    public function setOption($option, $value) {
        if ($option == 'cost') {
            if ($value < 4 || $value > 31) {
                throw new \InvalidArgumentException(
                    'Invalid cost parameter specified, must be between 4 and 31'
                );
            }
        }
        $this->options[$option] = $value;
        return $this;
    }

    protected function generateSalt() {
        $salt    = parent::generateSalt();
        $prefix  = static::getPrefix();
        $prefix .= str_pad($this->options['cost'], 2, '0', STR_PAD_LEFT);
        return $prefix . '$' . $salt;
    }
}