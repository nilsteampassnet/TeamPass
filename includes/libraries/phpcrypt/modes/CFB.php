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
 * Implements Cipher Feedback (CFB) block cipher mode
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2013 Ryan Gilfether
 */
class Mode_CFB extends Mode
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
        parent::__construct(PHP_Crypt::MODE_CFB, $cipher);

        // this works with only block Ciphers
        if ($cipher->type() != Cipher::BLOCK) {
                    trigger_error("CFB mode requires a block cipher", E_USER_WARNING);
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
     * @param string $text The string to be encrypted in CFB mode
     * @return boolean Returns true
     */
    public function encrypt(&$text)
    {
        $max = strlen($text);
        for ($i = 0; $i < $max; ++$i)
        {
            // copy the register, and then encrypt it
            $this->enc_register = $this->register;
            $this->cipher->encrypt($this->enc_register);

            // encrypt the byte
            $text[$i] = $text[$i] ^ $this->enc_register[0];

            // left shift the register and push the encrypted byte onto register
            $this->register = substr($this->register, 1);
            $this->register .= $text[$i];
        }

        return true;
    }


    /**
     * Decrypts an the entire string $plain_text using the cipher passed
     *
     * @param string $text the string to be decrypted in CFB mode
     * @return boolean Returns true
     */
    public function decrypt(&$text)
    {
        $max = strlen($text);
        for ($i = 0; $i < $max; ++$i)
        {
            $this->enc_register = $this->register;
            $this->cipher->encrypt($this->enc_register);

            // left shift the register and push the encrypted byte onto register
            $this->register = substr($this->register, 1);
            $this->register .= $text[$i];

            // decrypt the byte
            $text[$i] = $text[$i] ^ $this->enc_register[0];
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
}
?>
