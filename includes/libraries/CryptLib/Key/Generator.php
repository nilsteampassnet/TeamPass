<?php
/**
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 * @version    Build @@version@@
 */

namespace CryptLib\Key;

/**
 * Description of generator
 *
 * @author ircmaxell
 * @codeCoverageIgnore
 */
interface Generator extends Key {

    public static function test();

    public function __construct(array $options = array());

    /**
     * Generate a key of the supplied size
     *
     * @param Strength $strength   The strength of the generated key
     * @param int      $size       The size of the generated key (in bytes)
     * @param string   $passPhrase The passphrase to encrypt the key with
     *
     * @return void
     */
    public function generate(
        \CryptLib\Core\Strength $strength,
        $size,
        $passPhrase = ''
    );

}
