<?php
/**
 * The core Key Factory
 *
 * PHP version 5.3
 *
 * @category   PHPPasswordLib
 * @package    Key
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */

namespace PasswordLib\Key;

/**
 * The core Key Factory
 *
 * @category   PHPPasswordLib
 * @package    Key
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class Factory extends \PasswordLib\Core\AbstractFactory {

    /**
     * @var array An array of KDF class implementations
     */
    protected $kdf = array();

    /**
     * @var array An array of PBKDF class implementations
     */
    protected $pbkdf = array();

    /**
     * @var array An array of symmetric key generator implementations
     */
    protected $symmetricGenerators = array();

    /**
     * Construct the instance, loading the core implementations
     *
     * @return void
     */
    public function __construct() {
        $this->loadPBKDF();
    }

    public function getKDF($name = 'kdf3', array $options = array()) {
        if (isset($this->kdf[$name])) {
            $class = $this->kdf[$name];
            return new $class($options);
        }
        throw new \InvalidArgumentException('Unsupported KDF');
    }

    public function getPBKDF($name = 'pbkdf2', array $options = array()) {
        if (isset($this->pbkdf[$name])) {
            $class = $this->pbkdf[$name];
            return new $class($options);
        }
        throw new \InvalidArgumentException('Unsupported PBKDF');
    }

    public function getPBKDFFromSignature($signature) {
        list ($name, $hash) = explode('-', $signature, 2);
        return $this->getPBKDF($name, array('hash' => $hash));
    }

    public function getSymmetricKeyGenerator() {
    }

    public function registerKDF($name, $class) {
        $this->registerType(
            'kdf',
            __NAMESPACE__ . '\\Derivation\\KDF',
            $name,
            $class
        );
    }

    public function registerPBKDF($name, $class) {
        $this->registerType(
            'pbkdf',
            __NAMESPACE__ . '\\Derivation\\PBKDF',
            $name,
            $class
        );
    }

    protected function loadKDF() {
        $this->loadFiles(
            __DIR__ . '/Derivation/KDF',
            __NAMESPACE__ . '\\Derivation\\KDF\\',
            array($this, 'registerKDF')
        );
    }

    protected function loadPBKDF() {
        $this->loadFiles(
            __DIR__ . '/Derivation/PBKDF',
            __NAMESPACE__ . '\\Derivation\\PBKDF\\',
            array($this, 'registerPBKDF')
        );
    }

}
