<?php
/**
 * An abstract class for MessageAuthenticationCode generation
 *
 * PHP version 5.3
 *
 * @category   PHPCryptLib
 * @package    MAC
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */
namespace CryptLib\MAC;

/**
 * An abstract class for MessageAuthenticationCode generation
 *
 * @category   PHPCryptLib
 * @package    MAC
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
abstract class AbstractMAC implements MAC {

    /**
     * @var array The stored options for this instance
     */
    protected $options = array();

    /**
     * Build the instance of the MAC generator
     *
     * @param array $options The options for the instance
     *
     * @return void
     */
    public function __construct(array $options = array()) {
        $this->options = $options + $this->options;
    }

}
