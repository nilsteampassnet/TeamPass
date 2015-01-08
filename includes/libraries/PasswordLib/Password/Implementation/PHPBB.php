<?php
/**
 * The PHPBB password hashing implementation
 *
 * Use this class to generate and validate PHPBB password hashes.
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
 * The PHPBB password hashing implementation
 *
 * Use this class to generate and validate PHPBB password hashes.
 *
 * @see        http://www.openwall.com/phpass/
 * @category   PHPPasswordLib
 * @package    Password
 * @subpackage Implementation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class PHPBB extends PHPASS {

    /**
     * @var string The prefix for the generated hash
     */
    protected static $prefix = '$H$';

}