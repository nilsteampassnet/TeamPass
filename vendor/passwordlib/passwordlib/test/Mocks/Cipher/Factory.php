<?php
/**
 * The Cipher Factory
 *
 * Use this factory to instantiate ciphers and modes based upon their names. You
 * can register new ciphers and modes by simply calling the appropriate methods.
 *
 * PHP version 5.3
 *
 * @category  PHPPasswordLib
 * @package   Cipher
 * @author    Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright 2011 The Authors
 * @license   http://opensource.org/licenses/bsd-license.php New BSD License
 * @license   http://www.gnu.org/licenses/lgpl-2.1.html LGPL v 2.1
 */

namespace PasswordLibTest\Mocks\Cipher;


/**
 * The Cipher Factory
 *
 * Use this factory to instantiate ciphers and modes based upon their names. You
 * can register new ciphers and modes by simply calling the appropriate methods.
 *
 * @category  PHPPasswordLib
 * @package   Cipher
 * @author    Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class Factory extends \PasswordLib\Cipher\Factory {

    protected $callbacks = array();

    /**
     * Instantiate the factory
     *
     * This automatically loads and registers the default cipher and mode
     * implementations.
     *
     * @return void
     */
    public function __construct(array $callbacks = array()) {
        $this->callbacks = $callbacks;
    }

    public function __call($name, $args) {
        if (isset($this->callbacks[$name])) {
            return call_user_func_array($this->callbacks[$name], $args);
        }
        return null;
    }

    /**
     * Get an instance of a cipher by name
     *
     * Note that this will return the passed argument if it is an instance of
     * the Block cipher interface.
     *
     * @param string|Block $cipher The cipher name or instance to load
     *
     * @return Block The loaded block cipher
     * @throws RuntimeException if the cipher is not supported
     */
    public function getBlockCipher($cipher) {
        return $this->__call('getBlockCipher', array($cipher));
    }

    /**
     * Get an instance of a mode by name
     *
     * Note that this will return the passed argument if it is an instance of
     * the Mode interface.
     *
     * @param string|Mode $mode The mode name or instance to load
     *
     * @return Mode The loaded mode instance
     * @throws RuntimeException if the mode is not supported
     */
    public function getMode(
        $mode,
        \PasswordLib\Cipher\Block\Cipher $cipher,
        $initv,
        array $options = array()
    ) {
        return $this->__call('getMode', array($mode, $cipher, $initv, $options));
    }

    /**
     * Register a new cipher implementation class for this factory
     *
     * This will iterate over each supported cipher for the class and load the
     * cipher into the list of supported ciphers
     *
     * @param string $name  The name of the cipher (ignored)
     * @param string $class The full class name of the cipher implementation
     *
     * @return Factory $this The current factory instance
     * @throws InvalidArgumentException If the class is not a block cipher
     */
    public function registerCipher($name, $class) {
        return $this->__call('registerCipher', array($name, $class));
    }

    /**
     * Register a new mode implementation class for this factory
     *
     * @param string $name  The name of the mode (ignored)
     * @param string $class The full class name of the mode implementation
     *
     * @return Factory $this The current factory instance
     * @throws InvalidArgumentException If the class is not a valid mode
     */
    public function registerMode($name, $class) {
        return $this->__call('registerMode', array($name, $class));
    }

}
