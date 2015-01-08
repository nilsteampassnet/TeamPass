<?php
/**
 * The APR1 password hashing implementation
 *
 * Use this class to generate and validate APR1 password hashes.  APR1 hashes
 * are used primarrily by Apache for .htaccess password storage.
 *
 * PHP version 5.3
 *
 * @see        http://httpd.apache.org/docs/2.2/misc/password_encryptions.html
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
 * The APR1 password hashing implementation
 *
 * Use this class to generate and validate APR1 password hashes.  APR1 hashes
 * are used primarrily by Apache for .htaccess password storage.
 *
 * @see        http://httpd.apache.org/docs/2.2/misc/password_encryptions.html
 * @category   PHPPasswordLib
 * @package    Password
 * @subpackage Implementation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class APR1 extends \PasswordLib\Password\AbstractPassword {

    /**
     * @var Generator The random generator to use for seeds
     */
    protected $generator = null;

    /**
     * @var Hash The hash function to use (MD5)
     */
    protected $hash = null;

    /**
     * @var int The number of iterations to perform (1000 for APR1)
     */
    protected $iterations = 1000;

    protected static $prefix = '$apr1$';

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
        return new static;
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
        $salt     = $this->to64(
            $this->generator->generateInt(0, PHP_INT_MAX),
            8
        );
        return $this->hash($password, $salt, $this->iterations);
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
        $bits     = explode('$', $hash);
        if (!isset($bits[3]) || $bits[1] != 'apr1') {
            return false;
        }
        $test = $this->hash($password, $bits[2], $this->iterations);
        return $this->compareStrings($test, $hash);
    }

    /**
     * Perform the hashing of the password
     *
     * @param string $password   The plain text password to hash
     * @param string $salt       The 8 byte salt to use
     * @param int    $iterations The number of iterations to use
     *
     * @return string The hashed password
     */
    protected function hash($password, $salt, $iterations) {
        $len  = strlen($password);
        $text = $password . '$apr1$' . $salt;
        $bin  = md5($password.$salt.$password, true);
        for ($i = $len; $i > 0; $i -= 16) {
            $text .= substr($bin, 0, min(16, $i));
        }
        for ($i = $len; $i > 0; $i >>= 1) {
            $text .= ($i & 1) ? chr(0) : $password[0];
        }
        $bin = $this->iterate($text, $iterations, $salt, $password);
        return $this->convertToHash($bin, $salt);
    }

    protected function iterate($text, $iterations, $salt, $password) {
        $bin = md5($text, true);
        for ($i = 0; $i < $iterations; $i++) {
            $new = ($i & 1) ? $password : $bin;
            if ($i % 3) {
                $new .= $salt;
            }
            if ($i % 7) {
                $new .= $password;
            }
            $new .= ($i & 1) ? $bin : $password;
            $bin  = md5($new, true);
        }
        return $bin;
    }

    protected function convertToHash($bin, $salt) {
        $tmp  = '$apr1$'.$salt.'$';
        $tmp .= $this->to64(
            (ord($bin[0])<<16) | (ord($bin[6])<<8) | ord($bin[12]),
            4
        );
        $tmp .= $this->to64(
            (ord($bin[1])<<16) | (ord($bin[7])<<8) | ord($bin[13]),
            4
        );
        $tmp .= $this->to64(
            (ord($bin[2])<<16) | (ord($bin[8])<<8) | ord($bin[14]),
            4
        );
        $tmp .= $this->to64(
            (ord($bin[3])<<16) | (ord($bin[9])<<8) | ord($bin[15]),
            4
        );
        $tmp .= $this->to64(
            (ord($bin[4])<<16) | (ord($bin[10])<<8) | ord($bin[5]),
            4
        );
        $tmp .= $this->to64(
            ord($bin[11]),
            2
        );
        return $tmp;
    }

    /**
     * Convert the input number to a base64 number of the specified size
     *
     * @param int $num  The number to convert
     * @param int $size The size of the result string
     *
     * @return string The converted representation
     */
    protected function to64($num, $size) {
        static $seed = '';
        if (empty($seed)) {
            $seed = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'.
                    'abcdefghijklmnopqrstuvwxyz';
        }
        $result = '';
        while (--$size >= 0) {
            $result .= $seed[$num & 0x3f];
            $num   >>= 6;
        }
        return $result;
    }

}
