<?php
/**
 * The Drupal password hashing implementation
 *
 * Use this class to generate and validate Drupal password hashes.
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
class Drupal extends PHPASS {

    /**
     * @var string The prefix for the generated hash
     */
    protected static $prefix = '$S$';

    /**
     * @var string The hash function to use for this instance
     */
    protected $hashFunction = 'sha512';

    /**
     * Determine if the hash was made with this method
     *
     * @param string $hash The hashed data to check
     *
     * @return boolean Was the hash created by this method
     */
    public static function detect($hash) {
        $prefix = preg_quote(static::$prefix, '/');
        return 1 == preg_match('/^'.$prefix.'[a-zA-Z0-9.\/]{95}$/', $hash);
    }

}