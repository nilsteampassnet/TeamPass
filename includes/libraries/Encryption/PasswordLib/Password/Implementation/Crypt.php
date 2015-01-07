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
class Crypt extends \PasswordLib\Password\AbstractPassword {

    /**
     * @var Generator The random generator to use for seeds
     */
    protected $generator = null;

    protected $saltLen = 2;

    /**
     * Determine if the hash was made with this method
     *
     * @param string $hash The hashed data to check
     *
     * @return boolean Was the hash created by this method
     */
    public static function detect($hash) {
        static $regex = '/^[.\/0-9A-Za-z]{13}$/';
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
        return new static();
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
        $salt     = $this->generateSalt();
        $result   = crypt($password, $salt);
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
        $password = $this->checkPassword($password);
        if (!static::detect($hash)) {
            throw new \InvalidArgumentException(
                'The hash was not created here, we cannot verify it'
            );
        }
        $test = crypt($password, $hash);
        return $this->compareStrings($test, $hash);
    }

    protected function generateSalt() {
        $salt  = $this->generator->generate($this->saltLen);
        $chars = $this->to64($salt);
        return substr($chars, 0, $this->saltLen);
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
            if ($ictr >= $size) {
                $output .= $itoa[$cval1];
                break;
            }
            $cval2 = ord($input[$ictr++]);
            $cval1 |= $cval2 >> 6;
            $output .= $itoa[$cval1];
            $output .= $itoa[$cval2 & 0x3f];
        } while (true);
        return $output;
    }
}