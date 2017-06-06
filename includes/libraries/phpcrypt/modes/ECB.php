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
require_once(dirname(__FILE__)."/../Mode.php");
require_once(dirname(__FILE__)."/../phpCrypt.php");


/**
 * Implements Electronic Code Book (ECB) block cipher mode
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2005 Ryan Gilfether
 */
class Mode_ECB extends Mode
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
        parent::__construct(PHP_Crypt::MODE_ECB, $cipher);

        // this works with only block Ciphers
        if ($cipher->type() != Cipher::BLOCK) {
                    trigger_error("ECB mode requires a block cipher", E_USER_WARNING);
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
     * to the constructor in ECB mode
     *
     * @param string $text The string to be encrypted in ECB mode
     * @return boolean Returns true
     */
    public function encrypt(&$text)
    {
        $this->pad($text);
        $blocksz = $this->cipher->blockSize();

        $max = strlen($text) / $blocksz;
        for ($i = 0; $i < $max; ++$i)
        {
            // get the current position within $text
            $pos = $i * $blocksz;

            // grab a block of text
            $block = substr($text, $pos, $blocksz);

            // encrypt the block
            $this->cipher->encrypt($block);

            // replace the plain text with the cipher text
            $text = substr_replace($text, $block, $pos, $blocksz);
        }

        return true;
    }


    /**
     * Decrypts an the entire string $plain_text using the cipher passed
     * to the constructor in ECB mode
     *
     * @param string $text The string to be decrypted in ECB mode
     * @return boolean Returns true
     */
    public function decrypt(&$text)
    {
        $blocksz = $this->cipher->blockSize();

        $max = strlen($text) / $blocksz;
        for ($i = 0; $i < $max; ++$i)
        {
            // get the current position within $text
            $pos = $i * $blocksz;

            // get a block of cipher text
            $block = substr($text, $pos, $blocksz);

            // decrypt the block
            $this->cipher->decrypt($block);

            // replace the block of cipher text with plain text
            $text = substr_replace($text, $block, $pos, $blocksz);
        }

        $this->strip($text);
        return true;
    }


    /**
     * This mode does not require an IV
     *
     * @return boolean Returns false
     */
    public function requiresIV()
    {
        return false;
    }
}
?>
