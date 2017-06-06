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
require_once(dirname(__FILE__)."/../phpCrypt.php");
require_once(dirname(__FILE__)."/Rijndael128.php");


/**
 * Implement Advanced Encryption Standard (AES)
 * Both AES-256 and Rijndael-128 operate on 128 bit blocks.
 * The only difference is AES-256 uses a 256 bit key, where
 * as Rijndael-128 can uses a variable length key.
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2013 Ryan Gilfether
 */
class Cipher_AES_256 extends Cipher_Rijndael_128
{
    /** @type integer BYTES_BLOCK The size of the block, in bytes */
    const BYTES_BLOCK = 16; // 128 bits;

    /** @type integer BITS_KEY The size of the key, in bytes */
    const BYTES_KEY = 32; // 256 bits;

    /**
     * Constructor
     * Sets the key used for encryption.
     *
     * @param string $key string containing the user supplied encryption key
     * @return void
     */
    public function __construct($key)
    {
        // Setup AES by calling the second constructor in Rijndael_128
        // The block size is set here too, since all AES implementations use 128 bit blocks
        parent::__construct1(PHP_Crypt::CIPHER_AES_256, $key, self::BYTES_KEY);
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
}
?>
