<?php
/**
 * An abstract implementation of some standard key derivation needs
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
 * An abstract implementation of some standard key derivation needs
 *
 * @category   PHPPasswordLib
 * @package    Key
 * @subpackage Derivation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
abstract class AbstractDerivation {

    /**
     * @var Hash A hashing algorithm to use for the derivation
     */
    protected $hash = null;

    /**
     * @var array An array of options for the key derivation function
     */
    protected $options = array(
        'hash'        => 'sha512',
    );

    /**
     * Construct the derivation instance
     *
     * @param array $options An array of options to set for this instance
     *
     * @return void
     */
    public function __construct(array $options = array()) {
        $this->options = $options + $this->options;
        $this->hash    = $this->options['hash'];
    }

}
