<?php
/**
 * The Cipher Factory
 *
 * Use this factory to instantiate ciphers and modes based upon their names. You
 * can register new ciphers and modes by simply calling the appropriate methods.
 *
 * PHP version 5.3
 *
 * @category  PHPCryptLib
 * @package   Cipher
 * @author    Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright 2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */

namespace CryptLib\Cipher;

/**
 * Some used classes, aliased appropriately
 */
use CryptLib\Cipher\Block\Cipher as Cipher;
use CryptLib\Cipher\Block\Mode   as Mode;
use CryptLib\Cipher\Block\Cipher\MCrypt;


/**
 * The Cipher Factory
 *
 * Use this factory to instantiate ciphers and modes based upon their names. You
 * can register new ciphers and modes by simply calling the appropriate methods.
 *
 * @category  PHPCryptLib
 * @package   Cipher
 * @author    Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class Factory extends \CryptLib\Core\AbstractFactory {

    /**
     * @var array A list of available cipher implementations by name of cipher
     */
    protected $ciphers = array();

    /**
     * @var array A list of available mode implementations by name of mode
     */
    protected $modes = array();

    /**
     * Instantiate the factory
     *
     * This automatically loads and registers the default cipher and mode
     * implementations.
     *
     * @return void
     */
    public function __construct() {
        $this->loadModes();
        $this->loadCiphers();
    }

    /**
     * Get an instance of a cipher by name
     *
     * Note that this will return the passed argument if it is an instance of
     * the Block cipher interface.
     *
     * @param string|Block $cipher The cipher name or instance to load
     *
     * @return Cipher The loaded block cipher
     * @throws RuntimeException if the cipher is not supported
     */
    public function getBlockCipher($cipher) {
        if (is_object($cipher) && $cipher instanceof Cipher) {
            return $cipher;
        }
        $cipher = strtolower($cipher);
        if (in_array($cipher, MCrypt::getSupportedCiphers())) {
            //Use the built in MCrypt library if it's available
            return new MCrypt($cipher);
        } elseif (isset($this->ciphers[$cipher])) {
            $class = $this->ciphers[$cipher];
            return new $class($cipher);
        }
        $message = sprintf('Unsupported Cipher %s', $cipher);
        throw new \RuntimeException($message);
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
        \CryptLib\Cipher\Block\Cipher $cipher,
        $initv,
        array $options = array()
    ) {
        if (is_object($mode) && $mode instanceof Mode) {
            return $mode;
        }
        $mode = strtolower($mode);
        if (isset($this->modes[$mode])) {
            $class = $this->modes[$mode];
            return new $class($cipher, $initv, $options);
        }
        $message = sprintf('Unsupported Mode %s', $mode);
        throw new \RuntimeException($message);
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
        $refl      = new \ReflectionClass($class);
        $interface = '\\'. __NAMESPACE__ . '\\Block\\Cipher';
        if (!$refl->implementsInterface($interface)) {
            $message = sprintf('Class must implement %s', $interface);
            throw new \InvalidArgumentException($message);
        }
        foreach ($class::getSupportedCiphers() as $cipher){
            $this->ciphers[$cipher] = $class;
        }
        return $this;
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
        $refl      = new \ReflectionClass($class);
        $interface = '\\'. __NAMESPACE__ . '\\Block\\Mode';
        if (!$refl->implementsInterface($interface)) {
            throw new \InvalidArgumentException('Class must implement Mode');
        }
        $this->modes[strtolower($name)] = $class;
        return $this;
    }

    /**
     * Load all core cipher implementations
     *
     * @return void
     */
    protected function loadCiphers() {
        $this->loadFiles(
            __DIR__ . '/Block/Cipher',
            __NAMESPACE__ . '\\Block\\Cipher\\',
            array($this, 'registerCipher')
        );
    }

    /**
     * Load all core mode implementations
     *
     * @return void
     */
    protected function loadModes() {
        $this->loadFiles(
            __DIR__ . '/Block/Mode',
            __NAMESPACE__ . '\\Block\\Mode\\',
            array($this, 'registerMode')
        );
    }

}
