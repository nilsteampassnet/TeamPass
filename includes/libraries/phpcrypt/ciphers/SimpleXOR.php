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
require_once(dirname(__FILE__)."/../phpCrypt.php");


/**
 * An example class that implements a simple Exclusive OR (XOR) encryption
 * cipher using the user supplied key.
 *
 * A SimpleXOR cipher is the same as a One Time Pad when the key length
 * is the same as the data length, and the key is changed after every round
 * of encryption.
 *
 * XOR encryption is very difficult to break, however it can be susceptible
 * to patterns. Thus it is not recommended to use this Cipher for very
 * sensitive data, instead use one of the more secure ciphers (AES, 3DES,
 * Blowfish, etc)
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2005 Ryan Gilfether
 */
class Cipher_Simple_XOR extends Cipher
{
	/** @type integer BYTES_BLOCK The size of the block, in bytes */
	const BYTES_BLOCK = 1; // 8 bits


	/**
	 * Constructor
	 * Sets the key used for encryption. Also sets the requied block size
	 *
	 * @param string $key string containing the user supplied encryption key
	 * @return void
	 */
	public function __construct($key)
	{
		// SimpleXOR does not have a key size requirement
		parent::__construct(PHP_Crypt::CIPHER_SIMPLEXOR, $key);

		// required block size in bits
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
	 * Encrypts data using an XOR encryption scheme
	 *
	 * @param string $text A string to encrypt
	 * @return boolean Always returns true
	 */
	public function encrypt(&$text)
	{
		$this->operation(parent::ENCRYPT);
		return $this->simpleXOR($text);
	}


	/**
	 * Decrypts data encrypted with SimpleXOR::Encrypt()
	 *
	 * @param string $text An encrypted string to decrypt
	 * @return boolean Always returns true
	 */
	public function decrypt(&$text)
	{
		$this->operation(parent::DECRYPT);
		return $this->simpleXOR($text);
	}


	/**
	 * Because XOR Encryption uses the same algorithm to encrypt and decrypt,
	 * this function contains the code to do both. The SimpleXOR::Encrypt()
	 * and SimpleXOR::Decrypt() function above just call this function
	 *
	 * @param string $input
	 * @return boolean Always returns true
	 */
	private function simpleXOR(&$text)
	{
		$pos = 0;
		$max = strlen($text);
		$key = $this->key();

		for($i = 0; $i < $max; ++$i)
		{
			// if the current position in the key reaches the end of the key,
			// start over at position 0 of the key
			if($pos >= $this->keySize())
				$pos = 0;

			$text[$i] = $text[$i] ^ $key[$pos];
			++$pos;
		}

		return true;
	}


	/**
	 * Indicates that this is stream cipher
	 *
	 * @return integer Returns Cipher::STREAM
	 */
	public function type()
	{
		return parent::STREAM;
	}
}
?>
