<?php
/**
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 * @version    Build @@version@@
 */

namespace CryptLib\Key\Symmetric\Generator;

use CryptLib\Random\Factory    as RandomFactory;
use CryptLib\Key\Factory       as KeyFactory;
use CryptLib\Key\Symmetric\Raw as Raw;

/**
 * Description of mtrand
 *
 * @author ircmaxell
 */
class Internal implements \CryptLib\Key\Generator {

    protected $kdf = null;

    protected $random = null;

    public static function test() {
        return true;
    }

    public function __construct(array $options = array()) {
        $options += array('kdf' => null, 'random' => null);
        if (is_null($options['kdf'])) {
            $factory        = new KeyFactory();
            $options['kdf'] = $factory->getKdf('kdf3');
        }
        $this->kdf = $options['kdf'];
        if (is_null($options['random'])) {
            $options['random'] = new RandomFactory();
        }
        $this->random = $options['random'];
    }

    public function __toString() {
    }

    public function generate(
        \CryptLib\Core\Strength $strength,
        $size,
        $passphrase = ''
    ) {
        $generator = $this->random->getGenerator($strength);
        $seed      = $generator->generate($size);
        $key       = $this->kdf->derive($seed, $size, $passphrase);
        return new Raw(substr($key, 0, $size));
    }

    public function getType() {
        return static::TYPE_SYMMETRIC;
    }
}
