<?php
/*
 * Author: Ryan Gilfether
 * URL: http://www.gilfether.com/phpCrypt
 * Date: Sep 4, 2005
 * Copyright (C) 2005 Ryan Gilfether
 *
 * This file is part of phpCrypt
 *
 * phpCrypt is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see <http://www.gnu.org/licenses/>.
 */

namespace PHP_CRYPT;
require_once(dirname(__FILE__)."/../Cipher.php");
require_once(dirname(__FILE__)."/../phpCrypt.php");


/**
 * Implements DES Encryption
 * Resources used to implement this algorithm:
 * J. Orlin Grabbe, The Des Algorithm Illustrated, http://orlingrabbe.com/des.htm
 * Bruce Schneier, Applied Cryptography (2nd edition), 1996, pp. 265-300
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2005 Ryan Gilfether
 */
class Cipher_DES extends Cipher
{
    /** @type integer BYTES_BLOCK The block size, in bytes */
    const BYTES_BLOCK = 8; // 64 bits

    /** @type integer BYTES_KEY The key size, in bytes */
    const BYTES_KEY = 8; // 64 bits

    /** @type array $sub_keys The permutated subkeys */
    protected $sub_keys = array();

    /*
	 * Tables initialized in the initTables()
	 */

    /**
     * @type array $_pc1 Permutated choice 1 (PC1),
     * This should be considered a constant
     */
    protected static $_pc1 = array();

    /**
     * @type array $_pc2 Permutated choice 2 (PC2),
     * This should be considered a constant
     */
    protected static $_pc2 = array();

    /**
     * @type array $_key_sched The key schedule,
     * This should be considered a constant
     */
    protected static $_key_sched = array();

    /**
     * @type array $_ip The Initial Permutation (IP),
     * This should be considered a constant
     */
    private static $_ip = array();

    /**
     * @type array $_e The Expansion table (E),
     * This should be considered a constant
     */
    private static $_e = array();

    /**
     * @type array $_s The Substitution Box (S),
     * This should be considered a constant
     */
    private static $_s = array();

    /**
     * @type array $_p The Permutation table (P),
     * This should be considered a constant
     */
    private static $_p = array();

    /**
     * @type array $_ip The The Final Permutation table (FP),
     * This should be considered a constant
     */
    private static $_fp = array();


    /**
     * Constructor, used only when calling this class directly
     * for classes that extend this class, call __construct1()
     *
     * @param string $key The key used for Encryption/Decryption
     * @return void
     */
    public function __construct($key)
    {
        // set the DES key
        parent::__construct(PHP_Crypt::CIPHER_DES, $key, self::BYTES_KEY);

        // initialize variables
        $this->initTables();

        // DES requires that data is 64 bits
        $this->blockSize(self::BYTES_BLOCK);

        // create the 16 rounds of 56 bit keys
        $this->keyPermutation();
    }


    /**
     * Second Constructor, used only by child classes that extend this class
     *
     * @param string $cipher The name of the cipher extending this class
     * @param string $key The key used for Encryption/Decryption
     * @param integer $key_byte_sz The required byte size of the extending cipher
     * @return void
     */
    protected function __construct1($cipher, $key, $key_byte_sz)
    {
        // set the key and key size
        parent::__construct($cipher, $key, $key_byte_sz);

        // initialize variables
        $this->initTables();
    }


    /**
     * Destructor
     *
     * @return void
     */
    public function __destruct()
    {
        parent::__destruct();
    }


    /**
     * Encrypt plain text data using DES
     *
     * @return boolean Returns true
     */
    public function encrypt(&$text)
    {
        $this->operation(parent::ENCRYPT);
        return $this->des($text);
    }


    /**
     * Decrypt a DES encrypted string
     *
     * @return boolean Returns true
     */
    public function decrypt(&$text)
    {
        $this->operation(parent::DECRYPT);
        return $this->des($text);
    }


    /**
     * This is where the actual encrypt/decryption takes place. Since
     * encryption and decryption are the same algorithm in DES, we only
     * need one function to do both.
     *
     * @param string $data The string to be encrypted or decrypted
     * @return boolean Returns true
     */
    protected function des(&$data)
    {
        $l = array();
        $r = array();

        // step two: Initial Permutation (IP) of plaintext
        $data = $this->ip($data);

        // divide the permuted block IP into a left half L0 of 32 bits,
        // and a right half R0 of 32 bits
        $l[0] = substr($data, 0, 32);
        $r[0] = substr($data, 32, 32);

        for ($n = 1; $n <= 16; ++$n)
        {
            $l[$n] = $r[$n - 1];

            if ($this->operation() == parent::DECRYPT) {
                            $f = $this->F($r[$n - 1], $this->sub_keys[16 - $n]);
            } else {
                            $f = $this->F($r[$n - 1], $this->sub_keys[$n - 1]);
            }

            // XOR F with Ln
            $r[$n] = $this->xorBin($l[$n - 1], $f);
        }

        // now we combine L[16] and R[16] back into a 64-bit string, but we reverse
        // L[16] and R[16] so that it becomes R[16]L[16]
        $data = $r[16].$l[16];

        // now do the final permutation
        $data = $this->fp($data);
        $data = parent::bin2Str($data);

        return true;
    }


    /**
     * The Key permutation, based on tables $_pc1 and $_pc2
     * Create 16 subkeys, each of which is 48-bits long.
     *
     * @return void
     */
    private function keyPermutation()
    {
        $this->sub_keys = array();
        $pc1m = array();
        $pcr = array();
        $c = array();
        $d = array();

        // convert the key to binary
        $binkey = parent::str2Bin($this->key());

        // reduce the key down to 56bits based on table $_pc1
        for ($i = 0; $i < 56; ++$i) {
                    $pc1m[$i] = $binkey[self::$_pc1[$i] - 1];
        }

        // split $pc1m in half (C0 and D0)
        $c[0] = array_slice($pc1m, 0, 28);
        $d[0] = array_slice($pc1m, 28, 28);

        // now that $c[0] and $d[0] are defined, create 16 blocks for Cn and Dn
        // where 1 <= n <= 16
        for ($i = 1; $i <= 16; ++$i)
        {
            // now set the next Cn and Dn as the previous Cn and Dn
            $c[$i] = $c[$i - 1];
            $d[$i] = $d[$i - 1];

            for ($j = 0; $j < self::$_key_sched[$i - 1]; ++$j)
            {
                // do a left shift, move each bit one place to the left,
                // except for the first bit, which is cycled to the end
                // of the block.
                $c[$i][] = array_shift($c[$i]);
                $d[$i][] = array_shift($d[$i]);
            }

            // We now form the sub_keys (Kn), for 1<=n<=16, by applying the
            // following permutation table to each of the concatenated
            // pairs CnDn. Each pair has 56 bits, but PC-2 only uses 48
            // of these.
            $CnDn = array_merge($c[$i], $d[$i]);
            $this->sub_keys[$i - 1] = "";
            for ($j = 0; $j < 48; ++$j) {
                            $this->sub_keys[$i - 1] .= $CnDn[self::$_pc2[$j] - 1];
            }
        }

        // the sub_keys are created, we are done with the key permutation
    }


    /**
     * Initial Permutation (IP)
     * Now we encode each 64-bit block of data. There is an initial permutation IP of
     * the 64 bits of the message data M. This rearranges the bits according to the
     * following table, where the entries in the table show the new arrangement of the
     * bits from their initial order. The 58th bit of M becomes the first bit of IP.
     * The 50th bit of M becomes the second bit of IP. The 7th bit of M is the last
     * bit of IP.
     *
     * According to the book Applied Cryptography (Bruce Schneier, 2nd edition, pg. 271):
     * The initial permution was used to make it easier to load plain text and cipher text
     * data into a DES chip in byte-sized pieces when doing DES in hardware. The IP and FP
     * are not necessary in software implementations and do not affect the security. However,
     * the IP and FP are part of the DES standard and not implementing it would deviate from
     * the standard, so we will do it here in phpCrypt.
     *
     * @param string $text
     * @return string the Initial Permutation (IP)
     */
    private function ip($text)
    {
        $text = parent::str2Bin($text);
        $ip = "";

        // loop through the 64 bit block, ordering it occording to $_ip
        for ($i = 0; $i < 64; ++$i) {
                    $ip .= $text[self::$_ip[$i] - 1];
        }

        return $ip;
    }


    /**
     * Function F - To calculate f, we first expand each block Rn-1 from 32 bits to 48 bits.
     * This is done by using a selection table that repeats some of the bits in Rn-1. We'll
     * call the use of this selection table the function E. Thus E(Rn-1) has a 32 bit input
     * block, and a 48 bit output block.
     *
     * @param string $r  32 bit binary, each bit in an array element
     * @param string $k 48 bit binary string
     * @return string 48 bit binary string
     */
    private function f($r, $k)
    {
        $bin = parent::xorBin($k, $this->E($r));

        // create a 32-bit string from $bits by passing it through the S-Boxes
        $bin = $this->s($bin);

        // now send permute $bin as defined by table self::$_p
        $bin = $this->p($bin);

        return $bin;
    }


    /**
     * Function E - Let E be such that the 48 bits of its output, written as 8 blocks of
     * 6 bits each, are obtained by selecting the bits in its inputs in order according
     * to the self::$_e[] table.
     * This is only used in the F() function
     *
     * @param string $r 32 bit binary, each bit in an array element
     * @return string 48 bit binary string
     */
    private function e($r)
    {
        $e = "";
        for ($i = 0; $i < 48; ++$i) {
                    $e .= $r[self::$_e[$i] - 1];
        }

        return $e;
    }


    /**
     * S-Box
     * Take a 48-bit string from F() and run it through the S-Boxes, this requires
     * us to break up the 48-bit string into 8 groups of 6 bits before sending it
     * through the S-Boxes
     *
     * @param string $bits The 48-bit string from F() to be processed
     * @return string A 32-bit string from created from the 48-bit string after passing through S-Boxes
     */
    private function s($bits)
    {
        $s = "";

        for ($i = 0; $i <= 42; $i += 6)
        {
            $sbits = substr($bits, $i, 6);

            // we need to determine the S-Box column number and row number
            // from the 6 bit string passed in, this is done using the following method:
            // The First & Last bits represent a number between 0-3, used to determine which row
            // The middle 4 bits represent a number between 0-15, used to determine the column
            $row = bindec("{$sbits[0]}{$sbits[5]}");
            $col = bindec("{$sbits[1]}{$sbits[2]}{$sbits[3]}{$sbits[4]}");

            // determine the position in the S-BOX, S-Box table is in self::$_s[]
            $pos = ($row * 16) + $col;

            // get the integer from the S-Box and convert it to binary
            $bin = decbin(self::$_s[($i / 6)][$pos]);
            $s .= str_pad($bin, 4, "0", STR_PAD_LEFT);
        }

        return $s;
    }


    /**
     * Permutation P
     * The permutation P is defined in self::$_p. P() returns a 32-bit output
     * from a 32-bit input from a binary string from the S-BOX by permuting
     * the bits of the input block.
     * This is only used inside of F() function
     *
     * @param string $s A 32-bit string originating from being passed through S-Box
     * @return string A 32-bit string, which is $s permuted through table self::$_p
     */
    private function p($s)
    {
        $p = "";
        for ($i = 0; $i < 32; ++$i) {
                    $p .= $s[self::$_p[$i] - 1];
        }

        return $p;
    }


    /**
     * Final Permutation (FP)
     * Read the comment about IP and FP being unecessary in software implmented DES (though
     * we will do it to follow the DES standard).
     *
     * @param string $bin A 64-bit binary string
     * @return string A 64-bit binary string that has been run through self::$_fp[] table
     */
    private function fp($bin)
    {
        $fp = "";
        for ($i = 0; $i < 64; ++$i) {
                    $fp .= $bin[self::$_fp[$i] - 1];
        }

        return $fp;
    }


    /**
     * Initialize all the tables, this function is called inside the constructor
     *
     * @return void
     */
    private function initTables()
    {
        // permuted choice 1 (PC1)
        // these values are chars and should be run through chr() when used
        self::$_pc1 = array(
            57, 49, 41, 33, 25, 17, 9,
                1, 58, 50, 42, 34, 26, 18,
            10, 2, 59, 51, 43, 35, 27,
            19, 11, 3, 60, 52, 44, 36,
            63, 55, 47, 39, 31, 23, 15,
                7, 62, 54, 46, 38, 30, 22,
            14, 6, 61, 53, 45, 37, 29,
            21, 13, 5, 28, 20, 12, 4
        );

        // permuted choice 2 (PC2)
        // these values are chars and should be run through chr() when used
        self::$_pc2 = array(
            14, 17, 11, 24, 1, 5,
                3, 28, 15, 6, 21, 10,
            23, 19, 12, 4, 26, 8,
            16, 7, 27, 20, 13, 2,
            41, 52, 31, 37, 47, 55,
            30, 40, 51, 45, 33, 48,
            44, 49, 39, 56, 34, 53,
            46, 42, 50, 36, 29, 32
        );

        // initial permutation (IP)
        self::$_ip = array(
            58, 50, 42, 34, 26, 18, 10, 2,
            60, 52, 44, 36, 28, 20, 12, 4,
            62, 54, 46, 38, 30, 22, 14, 6,
            64, 56, 48, 40, 32, 24, 16, 8,
            57, 49, 41, 33, 25, 17, 9, 1,
            59, 51, 43, 35, 27, 19, 11, 3,
            61, 53, 45, 37, 29, 21, 13, 5,
            63, 55, 47, 39, 31, 23, 15, 7
        );

        // expansion (E)
        self::$_e = array(
            32, 1, 2, 3, 4, 5,
                4, 5, 6, 7, 8, 9,
                8, 9, 10, 11, 12, 13,
            12, 13, 14, 15, 16, 17,
            16, 17, 18, 19, 20, 21,
            20, 21, 22, 23, 24, 25,
            24, 25, 26, 27, 28, 29,
            28, 29, 30, 31, 32, 1
        );

        // substition box (S)
        self::$_s = array(
            /* S1 */
            array(
                14, 4, 13, 1, 2, 15, 11, 8, 3, 10, 6, 12, 5, 9, 0, 7,
                    0, 15, 7, 4, 14, 2, 13, 1, 10, 6, 12, 11, 9, 5, 3, 8,
                    4, 1, 14, 8, 13, 6, 2, 11, 15, 12, 9, 7, 3, 10, 5, 0,
                15, 12, 8, 2, 4, 9, 1, 7, 5, 11, 3, 14, 10, 0, 6, 13
            ),

            /* S2 */
            array(
                15, 1, 8, 14, 6, 11, 3, 4, 9, 7, 2, 13, 12, 0, 5, 10,
                    3, 13, 4, 7, 15, 2, 8, 14, 12, 0, 1, 10, 6, 9, 11, 5,
                    0, 14, 7, 11, 10, 4, 13, 1, 5, 8, 12, 6, 9, 3, 2, 15,
                13, 8, 10, 1, 3, 15, 4, 2, 11, 6, 7, 12, 0, 5, 14, 9
            ),

            /* S3 */
            array(
                10, 0, 9, 14, 6, 3, 15, 5, 1, 13, 12, 7, 11, 4, 2, 8,
                13, 7, 0, 9, 3, 4, 6, 10, 2, 8, 5, 14, 12, 11, 15, 1,
                13, 6, 4, 9, 8, 15, 3, 0, 11, 1, 2, 12, 5, 10, 14, 7,
                    1, 10, 13, 0, 6, 9, 8, 7, 4, 15, 14, 3, 11, 5, 2, 12
            ),

            /* S4 */
            array(
                    7, 13, 14, 3, 0, 6, 9, 10, 1, 2, 8, 5, 11, 12, 4, 15,
                13, 8, 11, 5, 6, 15, 0, 3, 4, 7, 2, 12, 1, 10, 14, 9,
                10, 6, 9, 0, 12, 11, 7, 13, 15, 1, 3, 14, 5, 2, 8, 4,
                    3, 15, 0, 6, 10, 1, 13, 8, 9, 4, 5, 11, 12, 7, 2, 14
            ),

            /* S5 */
            array(
                    2, 12, 4, 1, 7, 10, 11, 6, 8, 5, 3, 15, 13, 0, 14, 9,
                14, 11, 2, 12, 4, 7, 13, 1, 5, 0, 15, 10, 3, 9, 8, 6,
                    4, 2, 1, 11, 10, 13, 7, 8, 15, 9, 12, 5, 6, 3, 0, 14,
                11, 8, 12, 7, 1, 14, 2, 13, 6, 15, 0, 9, 10, 4, 5, 3
            ),

            /* S6 */
            array(
                12, 1, 10, 15, 9, 2, 6, 8, 0, 13, 3, 4, 14, 7, 5, 11,
                10, 15, 4, 2, 7, 12, 9, 5, 6, 1, 13, 14, 0, 11, 3, 8,
                    9, 14, 15, 5, 2, 8, 12, 3, 7, 0, 4, 10, 1, 13, 11, 6,
                    4, 3, 2, 12, 9, 5, 15, 10, 11, 14, 1, 7, 6, 0, 8, 13
            ),

            /* S7 */
            array(
                    4, 11, 2, 14, 15, 0, 8, 13, 3, 12, 9, 7, 5, 10, 6, 1,
                13, 0, 11, 7, 4, 9, 1, 10, 14, 3, 5, 12, 2, 15, 8, 6,
                    1, 4, 11, 13, 12, 3, 7, 14, 10, 15, 6, 8, 0, 5, 9, 2,
                    6, 11, 13, 8, 1, 4, 10, 7, 9, 5, 0, 15, 14, 2, 3, 12
            ),

            /* S8 */
            array(
                13, 2, 8, 4, 6, 15, 11, 1, 10, 9, 3, 14, 5, 0, 12, 7,
                    1, 15, 13, 8, 10, 3, 7, 4, 12, 5, 6, 11, 0, 14, 9, 2,
                    7, 11, 4, 1, 9, 12, 14, 2, 0, 6, 10, 13, 15, 3, 5, 8,
                    2, 1, 14, 7, 4, 10, 8, 13, 15, 12, 9, 0, 3, 5, 6, 11
            )
        );

        // permutation (P)
        self::$_p = array(
            16, 7, 20, 21,
            29, 12, 28, 17,
                1, 15, 23, 26,
                5, 18, 31, 10,
                2, 8, 24, 14,
            32, 27, 3, 9,
            19, 13, 30, 6,
            22, 11, 4, 25
        );

        // final permutation (FP)
        self::$_fp = array(
            40, 8, 48, 16, 56, 24, 64, 32,
            39, 7, 47, 15, 55, 23, 63, 31,
            38, 6, 46, 14, 54, 22, 62, 30,
            37, 5, 45, 13, 53, 21, 61, 29,
            36, 4, 44, 12, 52, 20, 60, 28,
            35, 3, 43, 11, 51, 19, 59, 27,
            34, 2, 42, 10, 50, 18, 58, 26,
            33, 1, 41, 9, 49, 17, 57, 25
        );

        // key schedule used in KeyPermutation()
        self::$_key_sched = array(1, 1, 2, 2, 2, 2, 2, 2, 1, 2, 2, 2, 2, 2, 2, 1);
    }


    /**
     * Indicates this is a block cipher
     *
     * @return integer Returns Cipher::BLOCK
     */
    public function type()
    {
        return parent::BLOCK;
    }
}
?>
