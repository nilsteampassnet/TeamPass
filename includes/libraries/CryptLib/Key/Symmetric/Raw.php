<?php
/**
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 * @version    Build @@version@@
 */

namespace CryptLib\Key\Symmetric;

/**
 * Description of raw
 *
 * @author ircmaxell
 */
class Raw extends AbstractSymmetric {

    public function __construct($key) {
        $this->key = $key;
    }

}

