<?php
/**
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 * @version    Build @@version@@
 */

namespace CryptLib\Key;

/**
 *
 * @author ircmaxell
 * @codeCoverageIgnore
 */
interface Key {

    const SYMMETRIC = 'symmetric';

    const PUBLICKEY = 'publickey';

    public function __toString();

    public function getType();

}
