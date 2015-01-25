<?php
/**
 * The core PBKDF interface (Password Based Key Derivation Function)
 *
 * This interface must be used to describe all derivation functions that take a
 * password as input and produce a key or hash as output 
 *
 * PHP version 5.3
 *
 * @category   PHPPasswordLib
 * @package    Key
 * @subpackage Derivation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */

namespace PasswordLib\Key\Derivation;

/**
 * The core PBKDF interface (Password Based Key Derivation Function)
 *
 * This interface must be used to describe all derivation functions that take a
 * password as input and produce a key or hash as output
 *
 * @category   PHPPasswordLib
 * @package    Key
 * @subpackage Derivation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @codeCoverageIgnore
 */
interface PBKDF {

    /**
     * Derive a key from the supplied arguments
     *
     * @param string $password   The password to derive from
     * @param string $salt       The salt string to use
     * @param int    $iterations The number of iterations to use
     * @param int    $length     The size of the string to generate
     *
     * @return string The derived key
     */
    public function derive($passkey, $salt, $iterations, $klen);

    /**
     * Get the signature for this implementation
     *
     * This should include all information needed to build the same isntance
     * later.
     *
     * @return string The signature for this instance
     */
    public function getSignature();

}
