<?php
/*
 * Author: Ryan Gilfether
 * URL: http://www.gilfether.com/phpCrypt
 * Date: May 4, 2013
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
 * Implements the Vigenere Cipher
 * The Vigenere Cipher encrypts alphabetic text using a series of
 * different Caesar ciphers based on letters of a keyword.
 * This cipher was first described in 1553 in the book
 * "La cifra del. Sig. Giovan Battista Bellaso". This cipher
 * is intended only for use for fun or for historical purposes and
 * is not secure by modern standards. Only works with ASCII.
 *
 * Resources used to implement this algorithm:
 * http://en.wikipedia.org/wiki/Vigen%C3%A8re_cipher
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2013 Ryan Gilfether
 */
class Cipher_Vigenere extends Cipher
{
    /** @type array $_vtable The Vigenere table */
    private static $_vtable = null;


    /**
     * Constructor
     *
     * @param string $key The key used for Encryption/Decryption
     * @return void
     */
    public function __construct($key)
    {
        // set the key
        parent::__construct(PHP_Crypt::CIPHER_VIGENERE, $key);

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
     * Encrypt plain text data using Vigenere cipher
     *
     * @return boolean Returns true
     */
    public function encrypt(&$text)
    {
        $this->operation(parent::ENCRYPT);

        // convert to uppercase, and remove any non alphabetic characters
        $text = strtoupper($text);
        $text = preg_replace("/[^A-Z]/", "", $text);
        $len = strlen($text);

        // prepare the key for the cipher
        $this->keyPrep($len);

        // loop through each letter of the message
        for ($i = 0; $i < $len; ++$i)
        {
            // get the Cipher letter from the Vigenere table, using the
            // current letter from the key as the row, and the current letter
            // from the text, as the column, subtract 65 because ascii upper case
            // letters start at 65
            $row = ord($this->expanded_key[$i]) - 65;
            $col = ord($text[$i]) - 65;
            $pos = ($row * 26) + $col;

            // convert the plain text to cipher text
            $text[$i] = self::$_vtable[$pos];
        }

        return true;
    }


    /**
     * Decrypt a Vigenere encrypted string
     *
     * @return boolean Returns true
     */
    public function decrypt(&$text)
    {
        $this->operation(parent::DECRYPT);

        // ensure the cipher text is all uppercase
        $text = strtoupper($text);
        $len = strlen($text);

        // prepare the key for the cipher
        $this->keyPrep($len);

        // go to the row corresponding to the letter from the key
        for ($i = 0; $i < $len; ++$i)
        {
            // find the row from the current character of the key, we subtract 65
            // because uppercase letters start at ASCII 65
            $row = (ord($this->expanded_key[$i]) - 65) * 26;

            // loop throw the entire row in the table until we find the letter
            // that matches the letter from the encrypted text
            for ($j = 0; $j < 26; ++$j)
            {
                // save the position we are in in the row
                $pos = $row + $j;

                // compare the letter from the table to the letter in the cipher text
                if (self::$_vtable[$pos] == $text[$i])
                {
                    // bingo, we found it. The plain text is the letter associated with
                    // with the column position, again add 65 because ascii capital
                    // letters start at 65
                    $text[$i] = chr($j + 65);
                    break;
                }
            }
        }

        return true;
    }


    /**
     * Prepare the key. The key can only contain uppercase letters.
     * All other characters are stripped out. The key length must match
     * The length of the message
     *
     * @param integer $len The length of message
     * @return void
     */
    private function keyPrep($len)
    {
        // we never modify the actual key, so we save it into another variable
        $this->expanded_key = $this->key();
        $this->expanded_key = strtoupper($this->expanded_key);
        $this->expanded_key = preg_replace("/[^A-Z]/", "", $this->expanded_key);
        $keylen = strlen($this->expanded_key);

        // The key must be prepared so that it is the same length as the
        // message. If it is longer or shorter we need to modify it
        // to make it the correct length
        if ($keylen > $len) {
                    $this->expanded_key = substr($this->expanded_key, 0, $len);
        } else if ($len > $keylen)
        {
            // if the key is shorter than the message, then we need pad the key
            // by repeating it until it is the correct length
            $diff = $len - $keylen;
            $pos = 0;

            for ($i = 0; $i < $diff; ++$i)
            {
                if ($pos >= $keylen) {
                                    $pos = 0;
                }

                $this->expanded_key .= $this->expanded_key[$pos];
                ++$pos;
            }
        }
    }


    /**
     * Initialize the Vigenere table used for encryption & decryption
     *
     * @return void
     */
    private function initTables()
    {
        self::$_vtable = array(
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
            'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'A',
            'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'A', 'B',
            'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'A', 'B', 'C',
            'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'A', 'B', 'C', 'D',
            'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'A', 'B', 'C', 'D', 'E',
            'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'A', 'B', 'C', 'D', 'E', 'F',
            'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'A', 'B', 'C', 'D', 'E', 'F', 'G',
            'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H',
            'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I',
            'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J',
            'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K',
            'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L',
            'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
            'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N',
            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
            'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
            'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q',
            'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R',
            'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S',
            'U', 'V', 'W', 'X', 'Y', 'Z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T',
            'V', 'W', 'X', 'Y', 'Z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U',
            'W', 'X', 'Y', 'Z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V',
            'X', 'Y', 'Z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W',
            'Y', 'Z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X',
            'Z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y'
        );
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
