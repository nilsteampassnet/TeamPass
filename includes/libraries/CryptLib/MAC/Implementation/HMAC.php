<?php
/**
 * A Hash-Base MAC generator
 *
 * PHP version 5.3
 *
 * @category   PHPCryptLib
 * @package    MAC
 * @subpackage Implementation
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */
namespace CryptLib\MAC\Implementation;

use \CryptLib\Hash\Hash;

/**
 * A Hash-Base MAC generator
 *
 * @category   PHPCryptLib
 * @package    MAC
 * @subpackage Implementation
 */
class HMAC extends \CryptLib\MAC\AbstractMAC {

    /**
     * @var array The stored options for this instance
     */
    protected $options = array(
        'hash' => 'sha256',
    );

    /**
     * Generate the MAC using the supplied data
     *
     * @param string $data The data to use to generate the MAC with
     * @param string $key  The key to generate the MAC
     * @param int    $size The size of the output to return
     *
     * @return string The generated MAC of the appropriate size
     */
    public function generate($data, $key, $size = 0) {
        $hash       = $this->options['hash'];
        $outputSize = Hash::getHashSize($hash);
        if ($size == 0) {
            $size = $outputSize;
        }
        if ($size > $outputSize) {
            throw new \OutOfRangeException(
                sprintf(
                    'The size is too big for the hash primitive [%d:%d]',
                    $size,
                    $outputSize
                )
            );
        }
        $return = hash_hmac($hash, $data, $key, true);
        return substr($return, 0, $size);
    }

}