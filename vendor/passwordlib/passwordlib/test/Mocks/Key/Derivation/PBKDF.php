<?php
/**
 * An implementation of the RFC 2898 PBKDF2 Standard key derivation function
 *
 * PHP version 5.3
 *
 * @see        http://www.ietf.org/rfc/rfc2898.txt
 * @category   PHPPasswordLib
 * @package    Key
 * @subpackage Derivation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @license    http://www.gnu.org/licenses/lgpl-2.1.html LGPL v 2.1
 * @version    Build @@version@@
 */

namespace PasswordLibTest\Mocks\Key\Derivation;

/**
 * An implementation of the RFC 2898 PBKDF2 Standard key derivation function
 *
 * @see        http://www.ietf.org/rfc/rfc2898.txt
 * @category   PHPPasswordLib
 * @package    Key
 * @subpackage Derivation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class PBKDF
    extends \PasswordLibTest\Mocks\AbstractMock
    implements \PasswordLib\Key\Derivation\PBKDF
{

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
    public function derive($password, $salt, $iterations, $length) {
        return $this->__call('derive', func_get_args());
    }

    /**
     * Get the signature for this implementation
     *
     * This should include all information needed to build the same isntance
     * later.
     *
     * @return string The signature for this instance
     */
    public function getSignature() {
        return $this->__call('getSignature', array());
    }

}

