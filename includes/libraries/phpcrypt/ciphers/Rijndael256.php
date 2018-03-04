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
require_once(dirname(__FILE__)."/Rijndael.php");
require_once(dirname(__FILE__)."/../phpCrypt.php");


/**
 * Implement Rijndael with a 256 bit data block
 * Key sizes can be 128, 192, or 256
 * References used to implement this cipher:
 * http://www.net-security.org/dl/articles/AESbyExample.pdf
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2013 Ryan Gilfether
 */
class Cipher_Rijndael_256 extends Cipher_Rijndael
{
    /** @type integer BYTES_BLOCK The size of the block, in bytes */
    const BYTES_BLOCK = 32; // 256 bits

    //const BITS_KEY = 0;


    /**
     * Constructor
     * Sets the key used for encryption. Also sets the requied block size
     *
     * @param string $key string containing the user supplied encryption key
     * @return void
     */
    public function __construct($key)
    {
        // Set up the key
        parent::__construct(PHP_Crypt::CIPHER_RIJNDAEL_256, $key);

        // required block size in bits
        $this->blockSize(self::BYTES_BLOCK);

        // expand the key now that we know the key size, and the bit size
        $this->expandKey();
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
