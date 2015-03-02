<?php
/**
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 * @version    Build @@version@@
 */

namespace CryptLib\Key\Symmetric;

/**
 * Description of abstractsymetric
 *
 * @author ircmaxell
 */
abstract class AbstractSymmetric implements \CryptLib\Key\Symmetric {

    protected $key = '';

    public function __toString() {
        return $this->getKey();
    }

    public function getKey() {
        return $this->key;
    }

    public function getType() {
        return self::SYMMETRIC;
    }

    public function saveKey($filename) {
        file_put_contents($filename, $this->getKey());
    }

}
