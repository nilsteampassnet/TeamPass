<?php
/**
 * The Blowfish password hashing implementation
 *
 * Use this class to generate and validate Blowfish password hashes.
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
 * The Blowfish password hashing implementation
 *
 * Use this class to generate and validate Blowfish password hashes.
 *
 * @category   PHPCryptLib
 * @package    Password
 * @subpackage Implementation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class Blowfish implements \CryptLib\Password\Password {

    /**
     * @var Generator The random generator to use for seeds
     */
    protected $generator = null;

    /**
     * @var int The number of iterations to perform (base 2)
     */
    protected $iterations = 10;

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

    /**
     * Build a new instance
     *
     * @param int       $iterations The number of times to iterate the hash
     * @param Generator $generator  The random generator to use for seeds
     *
     * @return void
     */
    public function __construct(
        $iterations = 8,
        \CryptLib\Random\Generator $generator = null
    ) {
        if ($iterations > 31 || $iterations < 4) {
            throw new \InvalidArgumentException('Invalid Iteration Count Supplied');
        }
        $this->iterations = $iterations;
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
        /**
         * Check for security flaw in the bcrypt implementation used by crypt()
         * @see http://php.net/security/crypt_blowfish.php
         */
        $match = preg_match('/[\x80-\xFF]/', $password);
        if (version_compare(PHP_VERSION, '5.3.7', '<') && $match) {
            throw new \RuntimeException(
                'The bcrypt implementation used by PHP contains a security flaw ' .
                'for password with 8-bit character. We suggest to upgrade to ' .
                'PHP 5.3.7+ or use passwords with only 7-bit characters'
            );
        }
        $salt       = $this->to64($this->generator->generate(16));
        $prefix     = static::getPrefix();
        $prefix    .= str_pad($this->iterations, 2, '0', STR_PAD_LEFT);
        $saltstring = $prefix . '$' . $salt;
        $result     = crypt($password, $saltstring);
        if ($result[0] == '*') {
            //@codeCoverageIgnoreStart
            throw new \RuntimeException('Password Could Not Be Created');
            //@codeCoverageIgnoreEnd
        }
        return $result;
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
        $test = crypt($password, $hash);
        return $test == $hash;
    }

    /**
     * Convert the input number to a base64 number of the specified size
     *
     * @param int $input The number to convert
     *
     * @return string The converted representation
     */
    protected function to64($input) {
        static $itoa = null;
        if (empty($itoa)) {
            $itoa = './ABCDEFGHIJKLMNOPQRSTUVWXYZ'
                    . 'abcdefghijklmnopqrstuvwxyz0123456789';
        }
        $output = '';
        $size   = strlen($input);
        $ictr   = 0;
        do {
            $cval1   = ord($input[$ictr++]);
            $output .= $itoa[$cval1 >> 2];
            $cval1   = ($cval1 & 0x03) << 4;
            if ($ictr >= $size) {
                $output .= $itoa[$cval1];
                break;
            }
            $cval2 = ord($input[$ictr++]);
            $cval1 |= $cval2 >> 4;
            $output .= $itoa[$cval1];
            $cval1   = ($cval2 & 0x0f) << 2;
            $cval2   = ord($input[$ictr++]);
            $cval1 |= $cval2 >> 6;
            $output .= $itoa[$cval1];
            $output .= $itoa[$cval2 & 0x3f];
        } while (true);
        return $output;
    }

}