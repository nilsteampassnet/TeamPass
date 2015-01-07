<?php
/**
 * The PHPASS password hashing implementation
 *
 * Use this class to generate and validate PHPASS password hashes.
 *
 * PHP version 5.3
 *
 * @see        http://www.openwall.com/phpass/
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
 * The PHPASS password hashing implementation
 *
 * Use this class to generate and validate PHPASS password hashes.
 *
 * @see        http://www.openwall.com/phpass/
 * @category   PHPPasswordLib
 * @package    Password
 * @subpackage Implementation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class PHPASS extends \PasswordLib\Password\AbstractPassword {

    /**
     * @var string The ITOA string to be used for base64 conversion
     */
    protected static $itoa = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ
                                abcdefghijklmnopqrstuvwxyz';

    protected $defaultOptions = array(
        'cost' => 8,
    );

    /**
     * This is the hash function to use.  To be overriden by child classes
     *
     * @var string The hash function to use for this instance
     */
    protected $hashFunction = 'md5';

    /**
     * @var string The prefix for the generated hash
     */
    protected static $prefix = '$P$';

    /**
     * Determine if the hash was made with this method
     *
     * @param string $hash The hashed data to check
     *
     * @return boolean Was the hash created by this method
     */
    public static function detect($hash) {
        $prefix = preg_quote(static::$prefix, '/');
        return 1 == preg_match('/^'.$prefix.'[a-zA-Z0-9.\/]{31}$/', $hash);
    }

    /**
     * Initialize the password hasher by replacing away spaces in the itoa var
     *
     * @return void
     */
    public static function init() {
        static::$itoa = preg_replace('/\s/', '', static::$itoa);
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
        $iterations = static::decodeIterations($hash[3]);
        return new static(array('cost' => $iterations));
    }

    /**
     * Decode an ITOA encoded iteration count
     *
     * @param string $byte The character to decode
     *
     * @return int The decoded iteration count (base2)
     */
    protected static function decodeIterations($byte) {
        return strpos(static::$itoa, $byte);
    }

    /**
     * Encode a base2 iteration count to a base64 character
     *
     * @param int $number
     *
     * @return string The encoded character
     */
    protected static function encodeIterations($number) {
        return static::$itoa[$number];
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
            if ($value > 30 || $value < 7) {
                throw new \InvalidArgumentException(
                    'Invalid Cost Supplied'
                );
            }
        }
        $this->options[$option] = $value;
        return $this;
    }

    /**
     * Create a password hash for a given plain text password
     *
     * @param string $password The password to hash
     *
     * @return string The formatted password hash
     */
    public function create($password) {
        $password = $this->checkPassword($password);
        $salt     = $this->to64($this->generator->generate(6));
        $prefix   = static::encodeIterations($this->options['cost']) . $salt;
        return static::$prefix . $prefix . $this->hash($password, $salt);
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
        if (!static::detect($hash)) {
            throw new \InvalidArgumentException(
                'The hash was not created here, we cannot verify it'
            );
        }
        $iterations = static::decodeIterations($hash[3]);
        if ($iterations != $this->options['cost']) {
            throw new \InvalidArgumentException(
                'Iteration Count Mismatch, Bailing'
            );
        }
        $salt = substr($hash, 4, 8);
        $hash = substr($hash, 12);
        $test = $this->hash($password, $salt);
        return $this->compareStrings($test, $hash);
    }

    /**
     * Execute the hash function with proper iterations
     *
     * @param string $password The password to hash
     * @param string $salt     The salt to use to hash
     *
     * @return string The base64 encoded generated hash
     */
    protected function hash($password, $salt) {
        $count = 1 << $this->options['cost'];
        $hash  = hash($this->hashFunction, $salt . $password, true);
        do {
            $hash = hash($this->hashFunction, $hash . $password, true);
        } while (--$count);
        return $this->to64($hash);
    }

    /**
     * Convert the input number to a base64 number of the specified size
     *
     * @param int $input The number to convert
     *
     * @return string The converted representation
     */
    protected function to64($input) {
        $output = '';
        $count  = strlen($input);
        $ictr   = 0;
        do {
            $value   = ord($input[$ictr++]);
            $output .= static::$itoa[$value & 0x3f];
            if ($ictr < $count) {
                $value |= ord($input[$ictr]) << 8;
            }
            $output .= static::$itoa[($value >> 6) & 0x3f];
            if ($ictr++ >= $count) {
                break;
            }
            if ($ictr < $count) {
                $value |= ord($input[$ictr]) << 16;
            }
            $output .= static::$itoa[($value >> 12) & 0x3f];
            if ($ictr++ < $count) {
                $output .= static::$itoa[($value >> 18) & 0x3f];
            }
        } while ($ictr < $count);
        return $output;
    }

}

PHPASS::init();