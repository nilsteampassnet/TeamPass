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
class SHA512 extends Crypt {

    protected static $prefix = '$6$';

    protected $defaultOptions = array(
        'rounds' => 5000,
    );

    protected $saltLen = 16;

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
        if (!sscanf($hash, '$6$rounds=%d$', $rounds)) {
            $rounds = 5000;
        }
        return new static(array('rounds' => $rounds));
    }

    /**
     * Determine if the hash was made with this method
     *
     * @param string $hash The hashed data to check
     *
     * @return boolean Was the hash created by this method
     */
    public static function detect($hash) {
        $regex = '#^\$6\$(rounds=\d{4,9}\$)?[a-zA-Z0-9./]{16}\$[a-zA-Z0-9./]{86}$#';
        return 1 == preg_match($regex, $hash);
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
        if ($option == 'rounds') {
            if ($value < 1000 || $value > 999999999) {
                throw new \InvalidArgumentException(
                    'Invalid cost parameter specified, ' .
                    'must be between 1000 and 999999999'
                );
            }
        }
        $this->options[$option] = $value;
        return $this;
    }

    protected function generateSalt() {
        $salt = parent::generateSalt();
        return '$6$rounds=' . $this->options['rounds'] . '$' . $salt;
    }

}