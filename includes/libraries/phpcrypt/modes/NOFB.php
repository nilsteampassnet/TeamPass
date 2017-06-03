<?php
/*
 * Author: Ryan Gilfether
 * URL: http://www.gilfether.com/phpCrypt
 * Date: March 29, 2013
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
 * Implements N Output Feedback (NOFB) block cipher mode, same as OFB
 * but N = the block size requirement of the cipher
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2013 Ryan Gilfether
 */
class Mode_NOFB extends Mode
{
    /**
     * Constructor
     * Sets the cipher object that will be used for encryption
     *
     * @param object $cipher one of the phpCrypt encryption cipher objects
     * @return void
     */
    public function __construct($cipher)
    {
        parent::__construct(PHP_Crypt::MODE_NOFB, $cipher);

        // this works with only block Ciphers
        if ($cipher->type() != Cipher::BLOCK) {
                    trigger_error("NOFB mode requires a block cipher", E_USER_WARNING);
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
            // current position in the text
            $pos = $i * $blocksz;

            // make sure we don't extend past the length of $text
            $byte_len = $blocksz;
            if (($pos + $blocksz) > $len) {
                            $byte_len -= ($pos + $blocksz) - $len;
            }

            // encrypt the register
            $this->enc_register = $this->register;
            $this->cipher->encrypt($this->enc_register);

            // now grab a block of text and a block of from the register, and XOR them
            $block = substr($text, $pos, $byte_len);
            for ($j = 0; $j < $byte_len; ++$j) {
                            $block[$j] = $block[$j] ^ $this->enc_register[$j];
            }

            // replace the plain text block with encrypted text
            $text = substr_replace($text, $block, $pos, $byte_len);

            // shift the register left n-bytes, append n-bytes of encrypted register
            $this->register = substr($this->register, $byte_len);
            $this->register .= substr($this->enc_register, 0, $byte_len);
        }

        return true;
    }


    /**
     * Decrypts an the entire string $plain_text using the cipher passed
     *
     * @param string $text the string to be decrypted
     * @return boolean Returns true
     */
    public function decrypt(&$text)
    {
        $len = strlen($text);
        $blocksz = $this->cipher->blockSize();

        $max = $len / $blocksz;
        for ($i = 0; $i < $max; ++$i)
        {
            // current position within $text
            $pos = $i * $blocksz;

            // make sure we don't extend past the length of $text
            $byte_len = $blocksz;
            if (($pos + $byte_len) > $len) {
                            $byte_len -= ($pos + $byte_len) - $len;
            }

            // encrypt the register
            $this->enc_register = $this->register;
            $this->cipher->encrypt($this->enc_register);

            // shift the register left n-bytes, append n-bytes of encrypted register
            $this->register = substr($this->register, $byte_len);
            $this->register .= substr($this->enc_register, 0, $byte_len);

            // now grab a block of text and xor with the register
            $block = substr($text, $pos, $byte_len);
            for ($j = 0; $j < $byte_len; ++$j) {
                            $block[$j] = $block[$j] ^ $this->enc_register[$j];
            }

            // replace the encrypted block with plain text
            $text = substr_replace($text, $block, $pos, $byte_len);
        }

        return true;
    }


    /**
     * This mode requires an IV
     *
     * @return boolean true
     */
    public function requiresIV()
    {
        return true;
    }
}
?>
