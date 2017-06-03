<?php
/*
 * Author: Ryan Gilfether
 * URL: http://www.gilfether.com/phpCrypt
 * Date: March 30, 2013
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
 * Implements Counter (CTR) block cipher mode
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2013 Ryan Gilfether
 */
class Mode_CTR extends Mode
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
        parent::__construct(PHP_CRYPT::MODE_CTR, $cipher);

        // this works with only block Ciphers
        if ($cipher->type() != Cipher::BLOCK) {
                    trigger_error("CTR mode requires a block cipher", E_USER_WARNING);
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
     * Encrypts an encrypted string
     *
     * @param string $text the string to be encrypted
     * @return boolean Returns true
     */
    public function encrypt(&$text)
    {
        $len = strlen($text);
        $blocksz = $this->cipher->blockSize();

        $max = $len / $blocksz;
        for ($i = 0; $i < $max; ++$i)
        {
            // get the current position in $text
            $pos = $i * $blocksz;

            // make sure we don't extend past the length of $text
            $byte_len = $blocksz;
            if (($pos + $byte_len) > $len) {
                            $byte_len -= ($pos + $byte_len) - $len;
            }

            // encrypt the register
            $this->enc_register = $this->register;
            $this->cipher->encrypt($this->enc_register);

            // grab a block of plain text
            $block = substr($text, $pos, $byte_len);

            // xor the block
            for ($j = 0; $j < $byte_len; ++$j) {
                            $block[$j] = $block[$j] ^ $this->enc_register[$j];
            }

            // replace the plain text block with the encrypted block
            $text = substr_replace($text, $block, $pos, $byte_len);

            // increment the counter
            $this->counter();
        }

        return true;
    }


    /**
     * Decrypt an encrypted string
     *
     * @param string $text The string to be decrypted
     * @return boolean Returns true
     */
    public function decrypt(&$text)
    {
        $len = strlen($text);
        $blocksz = $this->cipher->blockSize();

        $max = $len / $blocksz;
        for ($i = 0; $i < $max; ++$i)
        {
            // get the current position in $text
            $pos = $i * $blocksz;

            // make sure we don't extend past the length of $text
            $byte_len = $blocksz;
            if (($pos + $byte_len) > $len) {
                            $byte_len -= ($pos + $byte_len) - $len;
            }

            // encrypt the register
            $this->enc_register = $this->register;
            $this->cipher->encrypt($this->enc_register);

            // grab a block of plain text
            $block = substr($text, $pos, $byte_len);

            // xor the block with the register (which contains the IV)
            for ($j = 0; $j < $byte_len; ++$j) {
                            $block[$j] = $block[$j] ^ $this->enc_register[$j];
            }

            // replace the encrypted block with the plain text
            $text = substr_replace($text, $block, $pos, $byte_len);

            // increment the counter
            $this->counter();
        }

        return true;
    }


    /**
     * This mode requires an IV
     *
     * @return boolean True
     */
    public function requiresIV()
    {
        return true;
    }


    /**
     * Increments the counter (which is initially the IV) by one byte starting at the
     * last byte. Once that byte has reached 0xff, it is then set to 0x00, and the
     * next byte is then incremented. On the following incrementation, the last byte
     * is incremented again. An example:
     * PASS 1:   2037e9ae63f73dfe
     * PASS 2:   2037e9ae63f73dff
     * PASS 3:   2037e9ae63f73e00
     * PASS 4:   2037e9ae63f73e01
     * ...
     * PASS N:   2100000000000001
     * PASS N+1: 2100000000000002
     *
     * @return void
     */
    private function counter()
    {
        $pos = $this->cipher->blockSize() - 1;

        // starting at the last byte, loop through each byte until
        // we find one that can be incremented
        for ($i = $pos; $i >= 0; --$i)
        {
            // if we reached the last byte, set it to 0x00, then
            // loop one more time to increment the next byte
            if (ord($this->register[$i]) == 0xff) {
                            $this->register[$i] = chr(0x00);
            } else
            {
                // now increment the byte by 1
                $this->register[$i] = chr(ord($this->register[$i]) + 1);
                break;
            }
        }
    }
}
?>
