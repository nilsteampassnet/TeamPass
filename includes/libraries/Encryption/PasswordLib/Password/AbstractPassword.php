<?php
/**
 * The base abstract password hashing implementation
 *
 * This class provides common functionality to all child implementations
 *
 * PHP version 5.3
 *
 * @category   PHPPasswordLib
 * @package    Password
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */

namespace PasswordLib\Password;

use PasswordLib\Random\Factory as RandomFactory;
use DomainException;

/**
 * The base abstract password hashing implementation
 *
 * This class provides common functionality to all child implementations
 *
 * @category   PHPPasswordLib
 * @package    Password
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
abstract class AbstractPassword implements \PasswordLib\Password\Password {

    /**
     * @var array Default options for this password instance
     */
    protected $defaultOptions = array();

    /**
     * @var Generator The random generator to use for seeds
     */
    protected $generator = null;

    /**
     * @var array Options for this password instance
     */
    protected $options = array();

    /**
     * @var string The prefix for the generated hash
     */
    protected static $prefix = false;

    /**
     * Determine if the hash was made with this method
     *
     * @param string $hash The hashed data to check
     *
     * @return boolean Was the hash created by this method
     */
    public static function detect($hash) {
        $prefix = static::getPrefix();
        return strncmp($hash, $prefix, strlen($prefix)) === 0;
    }

    /**
     * Return the prefix used by this hashing method
     *
     * @return string The prefix used
     */
    public static function getPrefix() {
        return static::$prefix;
    }

    /**
     * Build a new instance
     *
     * @param array     $options    An array of options for the password isntance
     * @param Generator $generator  The random generator to use for seeds
     *
     * @return void
     */
    public function __construct(
        array $options = array(),
        \PasswordLib\Random\Generator $generator = null
    ) {
        $this->setOptions($this->defaultOptions);
        $this->setOptions($options);
        if (is_null($generator)) {
            $random    = new RandomFactory();
            $generator = $random->getMediumStrengthGenerator();
        }
        $this->generator = $generator;
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
        $this->options[$option] = $value;
        return $this;
    }

    /**
     * Set the options for the instance
     *
     * @param array $options The options to set
     *
     * @return $this
     */
    public function setOptions(array $options) {
        foreach ($options as $name => $value) {
            $this->setOption($name, $value);
        }
        return $this;
    }

    /**
     * Set the random number generator to use
     *
     * @param Generator $generator  The random generator to use for seeds
     *
     * @return void     
     */
    public function setGenerator(
        \PasswordLib\Random\Generator $generator = null
    ) {
        $this->generator = $generator;
    }

    /**
     * Perform a constant time comparison between two hash strings
     * 
     * This is done to prevent remote timing attacks from giving an attacker
     * information about the hash remotely.  This provides a constant runtime
     * equality check between two strings of the same length. This should be used
     * any time sensitive information is compared, as === can leak information
     * about the position of the difference to an attacker.
     *
     * Additionally, for added protection we're hashing each hash again with the 
     * same random key, to further protect against any form of timing attacks if
     * the two hashes are of different length
     *
     * @param string $hash1 The first hash to compare
     * @param string $hash2 The second hash to compare
     * 
     * @see http://rdist.root.org/2010/07/19/exploiting-remote-timing-attacks/
     * @see http://rdist.root.org/2010/01/07/timing-independent-array-comparison/
     * @return boolean True if the strings are identical
     */
    protected function compareStrings($hash1, $hash2) {
        $key   = $this->generator->generate(1024);
        $hash1 = hash_hmac('sha512', $hash1, $key, true);
        $hash2 = hash_hmac('sha512', $hash2, $key, true);

        $len    = strlen($hash1);
        $result = 0;
        for ($i = 0; $i < $len; $i++) {
            $result |= ord($hash1[$i]) ^ ord($hash2[$i]);
        }
        return $result === 0;
    }

    /**
     * Validates the password for type constraints
     *
     * @param string $password The password to validate
     * 
     * @return string The validated password (casted if needed)
     */
    protected function checkPassword($password) {
        switch (gettype($password)) {
            case 'string':
            case 'integer':
            case 'double':
                return (string) $password;
            case 'object':
                if (method_exists($password, '__tostring')) {
                    return (string) $password;
                }
                // Fall through intentional
            default:
                throw new DomainException(
                    'Invalid password type provided is invalid'
                );
        }
    }
}
