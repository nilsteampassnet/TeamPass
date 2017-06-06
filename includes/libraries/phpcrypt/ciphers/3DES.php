<?php
/*
 * Author: Ryan Gilfether
 * URL: http://www.gilfether.com/phpCrypt
 * Date: April 3, 2013
 * Copyright (C) 2013 Ryan Gilfether
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
require_once(dirname(__FILE__)."/DES.php");
require_once(dirname(__FILE__)."/../phpCrypt.php");


/**
 * Implements Triple DES Encryption (3DES)
 * Extends the Cipher_DES class. Triple DES does 3 DES rounds for
 * both encryption and decryption. It requires an 8, 16, or 24 byte
 * key. If the key is 8 or 16 bytes, it is expanded to 24 bytes
 * before it is used
 *
 * Resources used to implement this algorithm(in addition to those
 * listed in DES.php):
 * http://en.wikipedia.org/wiki/Triple_DES
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2013 Ryan Gilfether
 */
class Cipher_3DES extends Cipher_DES
{
    /** @type integer BYTES_BLOCK The size of the block, in bytes */
    const BYTES_BLOCK = 8; // 64 bits

    /** @type integer BYTES_KEY The size of the key. The key can be
	8, 16, or 24 bytes but gets expanded to 24 bytes before used */
    const BYTES_KEY = 24;


    /**
     * Constructor
     *
     * @param string $key The key used for Encryption/Decryption
     * @return void
     */
    public function __construct($key)
    {
        $key_len = strlen($key);

        if ($key_len == 8 || $key_len == 16) {
                    $key = self::expandKey($key, $key_len);
        } else if ($key_len < self::BYTES_KEY)
        {
            $msg  = PHP_Crypt::CIPHER_3DES." requires an 8, 16, or 24 byte key. ";
            $msg .= "$key_len bytes received.";
            trigger_error($msg, E_USER_WARNING);
        }
        // else if the key is longer than 24 bytes, phpCrypt will shorten it

        // set the 3DES key, note that we call __construct1() not __construct()
        // this is a second contructor we created for classes that extend the
        // DES class
        parent::__construct1(PHP_Crypt::CIPHER_3DES, $key, self::BYTES_KEY);

        // 3DES requires that data is 64 bits
        $this->blockSize(self::BYTES_BLOCK);
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
     * Encrypt plain text data using TripleDES
     * For encryption 3DES takes a 192 bit key, splits
     * it into 3 64 bit parts, and then does the following
     * DES ENCRYPT(key1) -> DES DECRYPT(key2) -> DES ENCRYPT(key3)
     *
     * @return boolean Returns true
     */
    public function encrypt(&$text)
    {
        $blocksz = $this->blockSize();

        for ($i = 0; $i < 3; ++$i)
        {
            $key = substr($this->key(), ($i * 8), $blocksz);
            $this->keyPermutation($key);

            if ($i % 2) {
                // round 1
                $this->operation(parent::DECRYPT);
            } else {
                // rounds 0 and 2
                $this->operation(parent::ENCRYPT);
            }

            $this->des($text);
        }

        return true;
    }


    /**
     * Decrypt a TripleDES encrypted string
     * For decryption 3DES takes a 192 bit key, splits
     * it into 3 64 bit parts, and then does the following
     * DES DECRYPT(key1) -> DES ENCRYPT(key2) -> DES DECRYPT(key3)
     *
     * @return boolean Returns true
     */
    public function decrypt(&$text)
    {
        $blocksz = $this->blockSize();

        for ($i = 2; $i >= 0; --$i)
        {
            $key = substr($this->key(), ($i * 8), $blocksz);
            $this->keyPermutation($key);

            if ($i % 2) {
                // round 1
                $this->operation(parent::ENCRYPT);
            } else {
                // round 0 and 2
                $this->operation(parent::DECRYPT);
            }

            $this->des($text);
        }

        return true;
    }


    /**
     * We overload the DES::keyPermutation() function so it takes
     * a 64 bit key as a parameter. This is because 3DES uses a 192 bit
     * key which is split into 3 64 bit parts, each of which must be
     * passed through the keyPermutation() function
     * Key permutation, based on tables $_pc1 and $_pc2
     * Create 16 subkeys, each of which is 48-bits long.
     * NOTE: The $key parameter is required, however because PHP expects
     * an overloaded method to contain the same number of parameters
     * (when E_STRICT is set) as the parent class, we make it optional
     * to shut the 'error' up
     *
     * @param string $key A 64 bit key
     * @return void
     */
    private function keyPermutation($key = "")
    {
        $this->sub_keys = array();
        $pc1m = array();
        $pcr = array();
        $c = array();
        $d = array();

        // convert the key to binary
        $binkey = parent::str2Bin($key);

        // reduce the key down to 56bits based on table $_pc1
        for ($i = 0; $i < 56; ++$i)
        {
            $pos = parent::$_pc1[$i] - 1;
            $pc1m[$i] = $binkey[$pos];
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

            for ($j = 0; $j < parent::$_key_sched[$i - 1]; ++$j)
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
                            $this->sub_keys[$i - 1] .= $CnDn[parent::$_pc2[$j] - 1];
            }
        }

        // the sub_keys are created, we are done with the key permutation
    }


    /**
     * Triple DES accepts an 8, 16, or 24 bytes key. If the key is
     * 8 or 16 bytes, it is expanded to 24 bytes
     * An 8 byte key is expanded by repeating it 3 times to make a 24
     * byte key
     * A 16 byte key add the first 8 bytes to the end of the string
     * to make a 24 byte key
     *
     * @param string $key The 8 or 16 byte key to expand
     * @param integer $len
     * @return string If the key given is 8 or 16 bytes it returns the
     *	expanded 24 byte key, else it returns the original key unexpanded
     */
    private static function expandKey($key, $len)
    {
        // if we were given an 8 byte key, repeat it
        // 3 times to produce a 24 byte key
        if ($len == 8) {
                    $key = str_repeat($key, 3);
        }

        // if we were given a 16 byte key, add the first
        // 8 bytes to the end of the key to produce 24 bytes
        if ($len == 16) {
                    $key .= substr($key, 0, 8);
        }

        // return the key
        return $key;
    }
}
?>
