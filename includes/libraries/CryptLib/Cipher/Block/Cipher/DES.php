<?php
/**
 * An implementation of the DES cipher, using the phpseclib implementation
 * 
 * This was forked from phpseclib and modified to use CryptLib conventions
 *
 * PHP version 5.3
 *
 * @category   PHPCryptLib
 * @package    Cipher
 * @subpackage Block
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @author     Jim Wigginton <terrafrost@php.net>
 * @copyright  2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */

namespace CryptLib\Cipher\Block\Cipher;

/**
 * An implementation of the DES cipher, using the phpseclib implementation
 *
 * @category   PHPCryptLib
 * @package    Cipher
 * @subpackage Block
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class DES extends \CryptLib\Cipher\Block\AbstractCipher {

    /**
     * @var int The block size for the cipher
     */
    protected $blockSize = 8;

    /**
     * @var int The key size for the cipher
     */
    protected $keySize = 8;

    /**
     * @var array The prepared key schedule
     */
    protected $preparedKeys = array();

    /**
     * In the official DES docs, they're described as being matrices that one 
     * accesses by using the first and last bits to determine the row and the 
     * middle four bits to determine the column.  in this implementation, 
     * they've been converted to vectors
     * 
     * @var array The S-boxes
     */
    protected static $sbox = array(
        array(
            14,  0,  4, 15, 13,  7,  1,  4,  2, 14, 15,  2, 11, 13,  8,  1,
             3, 10 ,10,  6,  6, 12, 12, 11,  5,  9,  9,  5,  0,  3,  7,  8,
             4, 15,  1, 12, 14,  8,  8,  2, 13,  4,  6,  9,  2,  1, 11,  7,
            15,  5, 12, 11,  9,  3,  7, 14,  3, 10, 10,  0,  5,  6,  0, 13
        ),
        array(
            15,  3,  1, 13,  8,  4, 14,  7,  6, 15, 11,  2,  3,  8,  4, 14,
             9, 12,  7,  0,  2,  1, 13, 10, 12,  6,  0,  9,  5, 11, 10,  5,
             0, 13, 14,  8,  7, 10, 11,  1, 10,  3,  4, 15, 13,  4,  1,  2,
             5, 11,  8,  6, 12,  7,  6, 12,  9,  0,  3,  5,  2, 14, 15,  9
        ),
        array(
            10, 13,  0,  7,  9,  0, 14,  9,  6,  3,  3,  4, 15,  6,  5, 10,
             1,  2, 13,  8, 12,  5,  7, 14, 11, 12,  4, 11,  2, 15,  8,  1,
            13,  1,  6, 10,  4, 13,  9,  0,  8,  6, 15,  9,  3,  8,  0,  7,
            11,  4,  1, 15,  2, 14, 12,  3,  5, 11, 10,  5, 14,  2,  7, 12
        ),
        array(
             7, 13, 13,  8, 14, 11,  3,  5,  0,  6,  6, 15,  9,  0, 10,  3,
             1,  4,  2,  7,  8,  2,  5, 12, 11,  1, 12, 10,  4, 14, 15,  9,
            10,  3,  6, 15,  9,  0,  0,  6, 12, 10, 11,  1,  7, 13, 13,  8,
            15,  9,  1,  4,  3,  5, 14, 11,  5, 12,  2,  7,  8,  2,  4, 14
        ),
        array(
             2, 14, 12, 11,  4,  2,  1, 12,  7,  4, 10,  7, 11, 13,  6,  1,
             8,  5,  5,  0,  3, 15, 15, 10, 13,  3,  0,  9, 14,  8,  9,  6,
             4, 11,  2,  8,  1, 12, 11,  7, 10,  1, 13, 14,  7,  2,  8, 13,
            15,  6,  9, 15, 12,  0,  5,  9,  6, 10,  3,  4,  0,  5, 14,  3
        ),
        array(
            12, 10,  1, 15, 10,  4, 15,  2,  9,  7,  2, 12,  6,  9,  8,  5,
             0,  6, 13,  1,  3, 13,  4, 14, 14,  0,  7, 11,  5,  3, 11,  8,
             9,  4, 14,  3, 15,  2,  5, 12,  2,  9,  8,  5, 12, 15,  3, 10,
             7, 11,  0, 14,  4,  1, 10,  7,  1,  6, 13,  0, 11,  8,  6, 13
        ),
        array(
             4, 13, 11,  0,  2, 11, 14,  7, 15,  4,  0,  9,  8,  1, 13, 10,
             3, 14, 12,  3,  9,  5,  7, 12,  5,  2, 10, 15,  6,  8,  1,  6,
             1,  6,  4, 11, 11, 13, 13,  8, 12,  1,  3,  4,  7, 10, 14,  7,
            10,  9, 15,  5,  6,  0,  8, 15,  0, 14,  5,  2,  9,  3,  2, 12
        ),
        array(
            13,  1,  2, 15,  8, 13,  4,  8,  6, 10, 15,  3, 11,  7,  1,  4,
            10, 12,  9,  5,  3,  6, 14, 11,  5,  0,  0, 14, 12,  9,  7,  2,
             7,  2, 11,  1,  4, 14,  1,  7,  9,  4, 12, 10, 14,  8,  2, 13,
             0, 15,  6, 12, 10,  9, 13,  0, 15,  3,  3,  5,  5,  6,  8, 11
        )
    );

    /**
     * @var array The key shift sequence for shifts per round of DES block
     */
    protected static $keyShifts = array(
        1, 1, 2, 2, 2, 2, 2, 2, 1, 2, 2, 2, 2, 2, 2, 1
    );

    /**
     * Get a list of supported ciphers for this class implementation
     *
     * @return array A list of supported ciphers
     */
    public static function getSupportedCiphers() {
        return array('des');
    }

    /**
     * Decrypt a block of data using the supplied string key
     *
     * Note that the supplied data should be the same size as the block size of
     * the cipher being used.
     *
     * @param string $data The data to decrypt
     *
     * @return string The result decrypted data
     */
    protected function decryptBlockData($data) {
        $keys = array_reverse($this->preparedKeys);
        return $this->processBlock($data, $keys);
    }

    /**
     * Encrypt a block of data using the supplied string key
     *
     * Note that the supplied data should be the same size as the block size of
     * the cipher being used.
     *
     * @param string $data The data to encrypt
     *
     * @return string The result encrypted data
     */
    protected function encryptBlockData($data) {
        return $this->processBlock($data, $this->preparedKeys);
    }

    /**
     * Initialize the cipher by preparing the key
     *
     * @return boolean The status of the initialization
     */
    protected function initialize() {
        $this->preparedKeys = $this->prepareKey($this->key);
        return true;
    }

    /**
     * Compute the keys necessary to execute the block cipher
     *
     * @param string $key The key to prepare
     * 
     * @return array The prepared keys
     */
    private function prepareKey($key) {
        // pad the key and remove extra characters as appropriate.
        $key = str_pad(substr($key, 0, 8), 8, chr(0));

        $temp    = unpack('Na/Nb', $key);
        $key     = array($temp['a'], $temp['b']);
        $msb     = array(
            ($key[0] >> 31) & 1,
            ($key[1] >> 31) & 1
        );
        $key[0] &= 0x7FFFFFFF;
        $key[1] &= 0x7FFFFFFF;

        $key = array(
            (($key[1] & 0x00000002) << 26) | (($key[1] & 0x00000204) << 17) |
            (($key[1] & 0x00020408) <<  8) | (($key[1] & 0x02040800) >>  1) |
            (($key[0] & 0x00000002) << 22) | (($key[0] & 0x00000204) << 13) |
            (($key[0] & 0x00020408) <<  4) | (($key[0] & 0x02040800) >>  5) |
            (($key[1] & 0x04080000) >> 10) | (($key[0] & 0x04080000) >> 14) |
            (($key[1] & 0x08000000) >> 19) | (($key[0] & 0x08000000) >> 23) |
            (($key[0] & 0x00000010) >>  1) | (($key[0] & 0x00001000) >> 10) |
            (($key[0] & 0x00100000) >> 19) | (($key[0] & 0x10000000) >> 28),
            (($key[1] & 0x00000080) << 20) | (($key[1] & 0x00008000) << 11) |
            (($key[1] & 0x00800000) <<  2) | (($key[0] & 0x00000080) << 16) |
            (($key[0] & 0x00008000) <<  7) | (($key[0] & 0x00800000) >>  2) |
            (($key[1] & 0x00000040) << 13) | (($key[1] & 0x00004000) <<  4) |
            (($key[1] & 0x00400000) >>  5) | (($key[1] & 0x40000000) >> 14) |
            (($key[0] & 0x00000040) <<  9) | ( $key[0] & 0x00004000       ) |
            (($key[0] & 0x00400000) >>  9) | (($key[0] & 0x40000000) >> 18) |
            (($key[1] & 0x00000020) <<  6) | (($key[1] & 0x00002000) >>  3) |
            (($key[1] & 0x00200000) >> 12) | (($key[1] & 0x20000000) >> 21) |
            (($key[0] & 0x00000020) <<  2) | (($key[0] & 0x00002000) >>  7) |
            (($key[0] & 0x00200000) >> 16) | (($key[0] & 0x20000000) >> 25) |
            (($key[1] & 0x00000010) >>  1) | (($key[1] & 0x00001000) >> 10) |
            (($key[1] & 0x00100000) >> 19) | (($key[1] & 0x10000000) >> 28) |
            ($msb[1] << 24) | ($msb[0] << 20)
        );

        $keys = array();
        for ($i = 0; $i < 16; $i++) {
            $key[0] <<= static::$keyShifts[$i];
            $temp   = ($key[0] & 0xF0000000) >> 28;
            $key[0] = ($key[0] | $temp) & 0x0FFFFFFF;

            $key[1] <<= static::$keyShifts[$i];
            $temp   = ($key[1] & 0xF0000000) >> 28;
            $key[1] = ($key[1] | $temp) & 0x0FFFFFFF;

            $temp = array(
                (($key[1] & 0x00004000) >>  9) | (($key[1] & 0x00000800) >>  7) |
                (($key[1] & 0x00020000) >> 14) | (($key[1] & 0x00000010) >>  2) |
                (($key[1] & 0x08000000) >> 26) | (($key[1] & 0x00800000) >> 23),
                (($key[1] & 0x02400000) >> 20) | (($key[1] & 0x00000001) <<  4) |
                (($key[1] & 0x00002000) >> 10) | (($key[1] & 0x00040000) >> 18) |
                (($key[1] & 0x00000080) >>  6),
                ( $key[1] & 0x00000020       ) | (($key[1] & 0x00000200) >>  5) |
                (($key[1] & 0x00010000) >> 13) | (($key[1] & 0x01000000) >> 22) |
                (($key[1] & 0x00000004) >>  1) | (($key[1] & 0x00100000) >> 20),
                (($key[1] & 0x00001000) >>  7) | (($key[1] & 0x00200000) >> 17) |
                (($key[1] & 0x00000002) <<  2) | (($key[1] & 0x00000100) >>  6) |
                (($key[1] & 0x00008000) >> 14) | (($key[1] & 0x04000000) >> 26),
                (($key[0] & 0x00008000) >> 10) | ( $key[0] & 0x00000010       ) |
                (($key[0] & 0x02000000) >> 22) | (($key[0] & 0x00080000) >> 17) |
                (($key[0] & 0x00000200) >>  8) | (($key[0] & 0x00000002) >>  1),
                (($key[0] & 0x04000000) >> 21) | (($key[0] & 0x00010000) >> 12) |
                (($key[0] & 0x00000020) >>  2) | (($key[0] & 0x00000800) >>  9) |
                (($key[0] & 0x00800000) >> 22) | (($key[0] & 0x00000100) >>  8),
                (($key[0] & 0x00001000) >>  7) | (($key[0] & 0x00000088) >>  3) |
                (($key[0] & 0x00020000) >> 14) | (($key[0] & 0x00000001) <<  2) |
                (($key[0] & 0x00400000) >> 21),
                (($key[0] & 0x00000400) >>  5) | (($key[0] & 0x00004000) >> 10) |
                (($key[0] & 0x00000040) >>  3) | (($key[0] & 0x00100000) >> 18) |
                (($key[0] & 0x08000000) >> 26) | (($key[0] & 0x01000000) >> 24)
            );

            $keys[] = $temp;
        }

        return $keys;
    }

    /**
     * Process a block of data and encrypt (or decrypt depending upon key sequence)
     *
     * @param string $block The block of data to process
     * @param array  $keys  The array of prepared keys to use
     * 
     * @return string The processed block data
     */
    protected function processBlock($block, array $keys) {
        $temp  = unpack('Na/Nb', $block);
        $block = array($temp['a'], $temp['b']);

        /**
         * Because php does arithmetic right shifts, if the most significant bits
         * are set, right shifting those into the correct position will add 1's -
         * not 0's.  this will intefere with the | operation unless a second & is
         * done.  so we isolate these bits and left shift them into place.  we
         * then & each block with 0x7FFFFFFF to prevennt 1's from being added for
         * any other shifts.
         */
        $msb       = array(
            ($block[0] >> 31) & 1,
            ($block[1] >> 31) & 1
        );
        $block[0] &= 0x7FFFFFFF;
        $block[1] &= 0x7FFFFFFF;

        /**
         * We isolate the appropriate bit in the appropriate integer and shift as
         * appropriate. In some cases, there are going to be multiple bits in the
         * same integer that need to be shifted in the same way.  we combine those
         * into one shift operation.
         */
        $block = array(
            (($block[1] & 0x00000040) << 25) | (($block[1] & 0x00004000) << 16) |
            (($block[1] & 0x00400001) <<  7) | (($block[1] & 0x40000100) >>  2) |
            (($block[0] & 0x00000040) << 21) | (($block[0] & 0x00004000) << 12) |
            (($block[0] & 0x00400001) <<  3) | (($block[0] & 0x40000100) >>  6) |
            (($block[1] & 0x00000010) << 19) | (($block[1] & 0x00001000) << 10) |
            (($block[1] & 0x00100000) <<  1) | (($block[1] & 0x10000000) >>  8) |
            (($block[0] & 0x00000010) << 15) | (($block[0] & 0x00001000) <<  6) |
            (($block[0] & 0x00100000) >>  3) | (($block[0] & 0x10000000) >> 12) |
            (($block[1] & 0x00000004) << 13) | (($block[1] & 0x00000400) <<  4) |
            (($block[1] & 0x00040000) >>  5) | (($block[1] & 0x04000000) >> 14) |
            (($block[0] & 0x00000004) <<  9) | ( $block[0] & 0x00000400       ) |
            (($block[0] & 0x00040000) >>  9) | (($block[0] & 0x04000000) >> 18) |
            (($block[1] & 0x00010000) >> 11) | (($block[1] & 0x01000000) >> 20) |
            (($block[0] & 0x00010000) >> 15) | (($block[0] & 0x01000000) >> 24),
            (($block[1] & 0x00000080) << 24) | (($block[1] & 0x00008000) << 15) |
            (($block[1] & 0x00800002) <<  6) | (($block[0] & 0x00000080) << 20) |
            (($block[0] & 0x00008000) << 11) | (($block[0] & 0x00800002) <<  2) |
            (($block[1] & 0x00000020) << 18) | (($block[1] & 0x00002000) <<  9) |
            ( $block[1] & 0x00200000       ) | (($block[1] & 0x20000000) >>  9) |
            (($block[0] & 0x00000020) << 14) | (($block[0] & 0x00002000) <<  5) |
            (($block[0] & 0x00200000) >>  4) | (($block[0] & 0x20000000) >> 13) |
            (($block[1] & 0x00000008) << 12) | (($block[1] & 0x00000800) <<  3) |
            (($block[1] & 0x00080000) >>  6) | (($block[1] & 0x08000000) >> 15) |
            (($block[0] & 0x00000008) <<  8) | (($block[0] & 0x00000800) >>  1) |
            (($block[0] & 0x00080000) >> 10) | (($block[0] & 0x08000000) >> 19) |
            (($block[1] & 0x00000200) >>  3) | (($block[0] & 0x00000200) >>  7) |
            (($block[1] & 0x00020000) >> 12) | (($block[1] & 0x02000000) >> 21) |
            (($block[0] & 0x00020000) >> 16) | (($block[0] & 0x02000000) >> 25) |
            ($msb[1] << 28) | ($msb[0] << 24)
        );

        for ($i = 0; $i < 16; $i++) {
            // start of "the Feistel (F) function"
            $key  = ((($block[1] >> 27) & 0x1F)
                    | (($block[1] & 1) << 5)) ^ $keys[$i][0];
            $temp = ((static::$sbox[0][$key]) << 28);
            $key  = (($block[1] & 0x1F800000) >> 23) ^ $keys[$i][1];
            $temp |= ((static::$sbox[1][$key]) << 24);
            $key = (($block[1] & 0x01F80000) >> 19) ^ $keys[$i][2];
            $temp |= ((static::$sbox[2][$key]) << 20);
            $key = (($block[1] & 0x001F8000) >> 15) ^ $keys[$i][3];
            $temp |= ((static::$sbox[3][$key]) << 16);
            $key = (($block[1] & 0x0001F800) >> 11) ^ $keys[$i][4];
            $temp |= ((static::$sbox[4][$key]) << 12);
            $key = (($block[1] & 0x00001F80) >>  7) ^ $keys[$i][5];
            $temp |= ((static::$sbox[5][$key]) <<  8);
            $key = (($block[1] & 0x000001F8) >>  3) ^ $keys[$i][6];
            $temp |= ((static::$sbox[6][$key]) <<  4);
            $key = ((($block[1] & 0x1F) << 1)
                    | (($block[1] >> 31) & 1)) ^ $keys[$i][7];
            $temp |= ( static::$sbox[7][$key]);

            $msb   = ($temp >> 31) & 1;
            $temp &= 0x7FFFFFFF;
            $nwB   = (($temp & 0x00010000) << 15) | (($temp & 0x02020120) <<  5);
            $nwB |= (($temp & 0x00001800) << 17) | (($temp & 0x01000000) >> 10);
            $nwB |= (($temp & 0x00000008) << 24) | (($temp & 0x00100000) <<  6);
            $nwB |= (($temp & 0x00000010) << 21) | (($temp & 0x00008000) <<  9);
            $nwB |= (($temp & 0x00000200) << 12) | (($temp & 0x10000000) >> 27);
            $nwB |= (($temp & 0x00000040) << 14) | (($temp & 0x08000000) >>  8);
            $nwB |= (($temp & 0x00004000) <<  4) | (($temp & 0x00000002) << 16);
            $nwB |= (($temp & 0x00442000) >>  6) | (($temp & 0x40800000) >> 15);
            $nwB |= (($temp & 0x00000001) << 11) | (($temp & 0x20000000) >> 20);
            $nwB |= (($temp & 0x00080000) >> 13) | (($temp & 0x00000004) <<  3);
            $nwB |= (($temp & 0x04000000) >> 22) | (($temp & 0x00000480) >>  7);
            $nwB |= (($temp & 0x00200000) >> 19) | ($msb << 23);
            // end of "the Feistel (F) function" - $newBlock is F's output

            $temp     = $block[1];
            $block[1] = $block[0] ^ $nwB;
            $block[0] = $temp;
        }

        $msb       = array(
            ($block[0] >> 31) & 1,
            ($block[1] >> 31) & 1
        );
        $block[0] &= 0x7FFFFFFF;
        $block[1] &= 0x7FFFFFFF;

        $block = array(
            (($block[0] & 0x01000004) <<  7) | (($block[1] & 0x01000004) <<  6) |
            (($block[0] & 0x00010000) << 13) | (($block[1] & 0x00010000) << 12) |
            (($block[0] & 0x00000100) << 19) | (($block[1] & 0x00000100) << 18) |
            (($block[0] & 0x00000001) << 25) | (($block[1] & 0x00000001) << 24) |
            (($block[0] & 0x02000008) >>  2) | (($block[1] & 0x02000008) >>  3) |
            (($block[0] & 0x00020000) <<  4) | (($block[1] & 0x00020000) <<  3) |
            (($block[0] & 0x00000200) << 10) | (($block[1] & 0x00000200) <<  9) |
            (($block[0] & 0x00000002) << 16) | (($block[1] & 0x00000002) << 15) |
            (($block[0] & 0x04000000) >> 11) | (($block[1] & 0x04000000) >> 12) |
            (($block[0] & 0x00040000) >>  5) | (($block[1] & 0x00040000) >>  6) |
            (($block[0] & 0x00000400) <<  1) | ( $block[1] & 0x00000400       ) |
            (($block[0] & 0x08000000) >> 20) | (($block[1] & 0x08000000) >> 21) |
            (($block[0] & 0x00080000) >> 14) | (($block[1] & 0x00080000) >> 15) |
            (($block[0] & 0x00000800) >>  8) | (($block[1] & 0x00000800) >>  9),
            (($block[0] & 0x10000040) <<  3) | (($block[1] & 0x10000040) <<  2) |
            (($block[0] & 0x00100000) <<  9) | (($block[1] & 0x00100000) <<  8) |
            (($block[0] & 0x00001000) << 15) | (($block[1] & 0x00001000) << 14) |
            (($block[0] & 0x00000010) << 21) | (($block[1] & 0x00000010) << 20) |
            (($block[0] & 0x20000080) >>  6) | (($block[1] & 0x20000080) >>  7) |
            ( $block[0] & 0x00200000       ) | (($block[1] & 0x00200000) >>  1) |
            (($block[0] & 0x00002000) <<  6) | (($block[1] & 0x00002000) <<  5) |
            (($block[0] & 0x00000020) << 12) | (($block[1] & 0x00000020) << 11) |
            (($block[0] & 0x40000000) >> 15) | (($block[1] & 0x40000000) >> 16) |
            (($block[0] & 0x00400000) >>  9) | (($block[1] & 0x00400000) >> 10) |
            (($block[0] & 0x00004000) >>  3) | (($block[1] & 0x00004000) >>  4) |
            (($block[0] & 0x00800000) >> 18) | (($block[1] & 0x00800000) >> 19) |
            (($block[0] & 0x00008000) >> 12) | (($block[1] & 0x00008000) >> 13) |
            ($msb[0] <<  7) | ($msb[1] <<  6)
        );

        return pack('NN', $block[0], $block[1]);
    }

}
