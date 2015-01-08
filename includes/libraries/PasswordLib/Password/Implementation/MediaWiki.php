<?php
/**
 * The MediaWiki password hashing implementation
 *
 * Use this class to generate and validate MediaWiki password hashes.
 *
 * PHP version 5.3
 *
 * @category   PHPPasswordLib
 * @package    Password
 * @subpackage Implementation
 * @author     Michael Braun <michael-dev@fami-braun.de>
 * @copyright  2013 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */

namespace PasswordLib\Password\Implementation;

use PasswordLib\Random\Factory as RandomFactory;

/**
 * The MediaWiki password hashing implementation
 *
 * Use this class to generate and validate MediaWiki password hashes.
 *
 * @category   PHPPasswordLib
 * @package    Password
 * @subpackage Implementation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class MediaWiki extends Crypt {

    protected static $prefix = 'mwB';

    public static function detect($hash) {
        $prefix = static::getPrefix();
        return strncmp($hash, $prefix, strlen($prefix)) === 0;
    }

    public function create($password) {
        $prefix   = static::getPrefix();
        $password = $this->checkPassword($password);
        $salt     = $this->generateSalt();
        $result   = $prefix.$salt.'.'.md5($salt.'-'.md5($password));
        return $result;
    }

    public function verify($password, $hash) {
        $prefix   = static::getPrefix();
        $password = $this->checkPassword($password);
        if (!static::detect($hash)) {
            throw new \InvalidArgumentException(
                'The hash was not created here, we cannot verify it'
            );
        }
        preg_match('/^' . $prefix . '(.+)\./', $hash, $match);
        $salt = null;
        if (isset($match[1])) {
            $salt = $match[1];
        }
        $test = $prefix.$salt.'.'.md5($salt.'-'.md5($password));
        return $this->compareStrings($test, $hash);
    }

}
