<?php
/**
 * The basic interface for MAC (Message Authentication Code) generation
 *
 * PHP version 5.3
 *
 * @category   PHPCryptLib
 * @package    MAC
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */
namespace CryptLib\MAC;

/**
 * The basic interface for MAC (Message Authentication Code) generation
 *
 * @category   PHPCryptLib
 * @package    MAC
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
interface MAC {

    /**
     * Build the instance of the MAC generator
     *
     * @param array $options The options for the instance
     *
     * @return void
     */
    public function __construct(array $options = array());

    /**
     * Generate the MAC using the supplied data
     *
     * @param string $data The data to use to generate the MAC with
     * @param string $key  The key to generate the MAC
     * @param int    $size The size of the output to return
     *
     * @return string The generated MAC of the appropriate size
     */
    public function generate($data, $key, $size = 0);

}
