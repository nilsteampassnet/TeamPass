<?php
/*
 * Author: Ryan Gilfether
 * URL: http://www.gilfether.com/phpCrypt
 * Date: May 3, 2013
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
require_once(dirname(__FILE__)."/../Cipher.php");
require_once(dirname(__FILE__)."/../phpCrypt.php");


/**
 * Implements ARC4 Encryption
 * ARC4 is an alternative name for RC4, RC4 is trademarked
 * Resources used to implement this algorithm:
 * http://calccrypto.wikidot.com/algorithms:rc4
 * http://en.wikipedia.org/wiki/RC4
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2013 Ryan Gilfether
 */
class Cipher_ARC4 extends Cipher
{
    /** @type string $_s The S List */
    private $_s = "";

    /** @type string $_key_stream The Key Stream */
    private $_key_stream = "";


    /**
     * Constructor
     *
     * @param string $key The key used for Encryption/Decryption
     * @return void
     */
    public function __construct($key)
    {
        // set the ARC4 key
        parent::__construct(PHP_Crypt::CIPHER_ARC4, $key);
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
     * Encrypt plain text data using ARC4
     *
     * @return boolean Returns true
     */
    public function encrypt(&$text)
    {
        $this->operation(parent::ENCRYPT);
        return $this->arc4($text);
    }


    /**
     * Decrypt a ARC4 encrypted string
     *
     * @return boolean Returns true
     */
    public function decrypt(&$text)
    {
        $this->operation(parent::DECRYPT);
        return $this->arc4($text);
    }


    /**
     * Performs the ARC4 algorithm, since encryption and decryption
     * are the same, all the work is done here
     *
     * @param string $text The string to encrypt/decrypt
     * @return boolean Returns true
     */
    private function arc4(&$text)
    {
        $len = strlen($text);
        $this->prga($len);

        for ($i = 0; $i < $len; ++$i) {
                    $text[$i] = $text[$i] ^ $this->_key_stream[$i];
        }

        return true;
    }


    /**
     * The key scheduling algorithm (KSA)
     *
     * @return void
     */
    private function ksa()
    {
        $j = 0;
        $this->_s = array();
        $keylen = $this->keySize();
        $key = $this->key();

        // fill $this->_s with all the values from 0-255
        for ($i = 0; $i < 256; ++$i) {
                    $this->_s[$i] = $i;
        }

        // the changing S List
        for ($i = 0; $i < 256; ++$i)
        {
            $k = $key[$i % $keylen];
            $j = ($j + $this->_s[$i] + ord($k)) % 256;

            // swap bytes
            self::swapBytes($this->_s[$i], $this->_s[$j]);
        }
    }


    /**
     * The Pseudo-Random Generation Algorithm (PGRA)
     * Creates the key stream used for encryption / decryption
     *
     * @param integer $data_len The length of the data we are encrypting/decrypting
     * @return void
     */
    private function prga($data_len)
    {
        $i = 0;
        $j = 0;
        $this->_key_stream = "";

        // set up the key schedule
        $this->ksa();

        for ($c = 0; $c < $data_len; ++$c)
        {
            $i = ($i + 1) % 256;
            $j = ($j + $this->_s[$i]) % 256;

            // swap bytes
            self::swapBytes($this->_s[$i], $this->_s[$j]);

            $pos = ($this->_s[$i] + $this->_s[$j]) % 256;
            $this->_key_stream .= chr($this->_s[$pos]);
        }
    }


    /**
     * Swap two individual bytes
     *
     * @param string $a A single byte
     * @param string $b A single byte
     * @return void
     */
    private static function swapBytes(&$a, &$b)
    {
        $tmp = $a;
        $a = $b;
        $b = $tmp;
    }


    /**
     * Indicates that this is a stream cipher
     *
     * @return integer Returns Cipher::STREAM
     */
    public function type()
    {
        return parent::STREAM;
    }
}
?>
