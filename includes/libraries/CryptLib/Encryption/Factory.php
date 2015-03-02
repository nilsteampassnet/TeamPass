<?php
/**
 * The core Encryption Factory
 *
 * PHP version 5.3
 *
 * @category   PHPCryptLib
 * @package    Encryption
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */

namespace CryptLib\Encryption;

use CryptLib\Cipher\Factory as CipherFactory;

/**
 * The core Encryption Factory
 *
 * PHP version 5.3
 *
 * @category   PHPCryptLib
 * @package    Encryption
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class Factory extends \CryptLib\Core\AbstractFactory {

    /**
     * @var Factory The Cipher Factory to use for this instance
     */
    protected $cipherFactory = null;

    /**
     * @var array An array of PackingModes available for use
     */
    protected $packingModes = array();

    /**
     * Build the instance
     *
     * @param Factory $factory The Cipher Factory to use for this instance
     *
     * @return void
     */
    public function __construct(CryptoLib\Cipher\Factory $factory = null) {
        if (is_null($factory)) {
            $factory = new CipherFactory();
        }
        $this->cipherFactory = $factory;
        $this->loadPackingModes();
    }

    /**
     * Get a packing mode by name
     *
     * @param string|PackingMode $name The name of the packing mode or instance
     *
     * @return PackingMode The instantiated PackingMode class
     * @throws RuntimeException If the mode does not exist
     */
    public function getPackingMode($name) {
        if (is_object($name) && $name instanceof PackingMode) {
            return $name;
        }
        $name = strtolower($name);
        if (isset($this->packingModes[$name])) {
            $class = $this->packingModes[$name];
            return new $class;
        }
        $message = sprintf('Invalid Mode Supplied: %s', $name);
        throw new \RuntimeException($message);
    }

    /**
     * Register a new packing mode by name
     *
     * @param string $name  The name of the packing mode
     * @param string $class The class to instantiate for the mode
     *
     * @return Factory $this The current factory instance
     */
    public function registerPackingMode($name, $class) {
        $this->registerType(
            'packingModes',
            __NAMESPACE__ . '\\PackingMode',
            $name,
            $class
        );
        return $this;
    }

    /**
     * Load the core packing modes for this instance
     *
     * @return void
     */
    protected function loadPackingModes() {
        $this->loadFiles(
            __DIR__ . '/PackingMode',
            __NAMESPACE__ . '\\PackingMode\\',
            array($this, 'registerPackingMode')
        );
    }

}
