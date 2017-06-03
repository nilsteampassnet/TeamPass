<?php
/*
 * Author: Ryan Gilfether
 * URL: http://www.gilfether.com/phpCrypt
 * Date: March 25, 2013
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

namespace PHP_Crypt;
require_once(dirname(__FILE__)."/../Cipher.php");
require_once(dirname(__FILE__)."/../Mode.php");
require_once(dirname(__FILE__)."/../phpCrypt.php");


/**
 * Implements Cipher-Block Chaining (CBC) block cipher mode
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2013 Ryan Gilfether
 */
class Mode_CBC extends Mode
{
    /**
     * Constructor
     * Sets the cipher object that will be used for encryption
     *
     * @param object $cipher one of the phpCrypt encryption cipher objects
     * @return void
     */
    function __construct($cipher)
    {
        parent::__construct(PHP_Crypt::MODE_CBC, $cipher);

        // this works with only block Ciphers
        if ($cipher->type() != Cipher::BLOCK) {
                    trigger_error("CBC mode requires a block cipher", E_USER_WARNING);
        }
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
     * Encrypts an the entire string $plain_text using the cipher passed
     * to the constructor in CBC mode
     * The steps to encrypt using CBC are as follows
     * 1) Get a block of plain text data to use in the Cipher
     * 2) XOR the block with IV (if it's the first round) or the previous
     *	round's encrypted result
     * 3) Encrypt the block using the cipher
     * 4) Save the encrypted block to use in the next round
     *
     * @param string $text the string to be encrypted in CBC mode
     * @return boolean Returns true
     */
    public function encrypt(&$text)
    {
        $this->pad($text);
        $blocksz = $this->cipher->blockSize();

        $max = strlen($text) / $blocksz;
        for ($i = 0; $i < $max; ++$i)
        {
            // get the current position in $text
            $pos = $i * $blocksz;

            // grab a block of plain text
            $block = substr($text, $pos, $blocksz);

            // xor the block with the register
            for ($j = 0; $j < $blocksz; ++$j) {
                            $block[$j] = $block[$j] ^ $this->register[$j];
            }

            // encrypt the block, and save it back to the register
            $this->cipher->encrypt($block);
            $this->register = $block;

            // replace the plain text block with the cipher text
            $text = substr_replace($text, $this->register, $pos, $blocksz);
        }

        return true;
    }


    /**
     * Decrypts an the entire string $plain_text using the cipher passed
     * to the constructor in CBC mode
     * The decryption algorithm requires the following steps
     * 1) Get the first block of encrypted text, save it
     * 2) Decrypt the block
     * 3) XOR the decrypted block (if it's the first pass use the IV,
     *	else use the encrypted block from step 1
     * 4) the result from the XOR will be a plain text block, save it
     * 5) assign the encrypted block from step 1 to use in the next round
     *
     * @param string $text the string to be decrypted in CBC mode
     * @return boolean Returns true
     */
    public function decrypt(&$text)
    {
        $blocksz = $this->cipher->blockSize();

        $max = strlen($text) / $blocksz;
        for ($i = 0; $i < $max; ++$i)
        {
            // get the current position in $text
            $pos = $i * $blocksz;

            // grab a block of cipher text, and save it for use later
            $block = substr($text, $pos, $blocksz);
            $tmp_block = $block;

            // decrypt the block of cipher text
            $this->cipher->decrypt($block);

            // xor the block with the register
            for ($j = 0; $j < $blocksz; ++$j) {
                            $block[$j] = $block[$j] ^ $this->register[$j];
            }

            // replace the block of cipher text with plain text
            $text = substr_replace($text, $block, $pos, $blocksz);

            // save the cipher text block to the register
            $this->register = $tmp_block;
        }

        $this->strip($text);
        return true;
    }


    /**
     * This mode requires an IV
     *
     * @return boolean Returns True
     */
    public function requiresIV()
    {
        return true;
    }
}
?>
