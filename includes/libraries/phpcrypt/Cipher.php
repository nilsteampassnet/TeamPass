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


namespace PHP_Crypt;
require_once(dirname(__FILE__)."/Core.php");
require_once(dirname(__FILE__)."/phpCrypt.php");

/**
 * Generic parent class for ciphers. Should not be used directly,
 * instead a class should extend from this class
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2005 Ryan Gilfether
 */
abstract class Cipher extends Core
{
	/** @type integer ENCRYPT Indicates when we are in encryption mode */
	const ENCRYPT = 1;

	/** @type integer DECRYPT Indicates when we are in decryption mode */
	const DECRYPT = 2;

	/** @type integer BLOCK Indicates that a cipher is a block cipher */
	const BLOCK = 1;

	/** @type integer STREAM Indicates that a cipher is a stream cipher */
	const STREAM = 2;

	/** @type string $cipher_name Stores the name of the cipher */
	protected $cipher_name = "";

	/** @type integer $block_size The block size of the cipher in bytes */
	protected $block_size = 0;

	/**
	 * @type integer $operation Indicates if a cipher is Encrypting or Decrypting
	 * this can be set to either Cipher::ENCRYPT or Cipher::DECRYPT
	 */
	protected $operation = self::ENCRYPT; // can be either Cipher::ENCRYPT | Cipher::DECRYPT;

	/** @type string $key Stores the key for the Cipher */
	private $key = "";

	/** @type integer $key_len Keep track of the key length, so we don't
	have to make repeated calls to strlen() to find the length */
	private $key_len = 0;


	/**
	 * Constructor
	 *
	 * @param string $name one of the predefined ciphers
	 * @param string $key The key used for encryption
	 * @param int Optional, the required size of a key for the cipher
	 * @return void
	 */
	protected function __construct($name, $key = "", $required_key_sz = 0)
	{
		$this->name($name);
		$this->key($key, $required_key_sz);
	}


	/**
	 * Destructor
	 *
	 * @return void
	 */
	protected function __destruct()
	{

	}



	/**********************************************************************
	 * ABSTRACT METHODS
	 *
	 * The abstract methods required by inheriting classes to implement
	 **********************************************************************/

	/**
	 * The cipher's encryption function. Must be defined
	 * by the class inheriting this Cipher object. This function
	 * will most often be called from within the Mode object
	 *
	 * @param string $text The text to be encrypted
	 * @return boolean Always returns false
	 */
	abstract public function encrypt(&$text);


	/**
	 * The cipher's decryption function. Must be defined
	 * by the class inheriting this Cipher object. This function
	 * will most often be called from within the Mode object
	 *
	 * @param string $text The text to decrypt
	 * @return boolean Always returns false
	 */
	abstract public function decrypt(&$text);


	/**
	 * Indiciates whether the cipher is a block or stream cipher
	 *
	 * @return integer Returns either Cipher::BLOCK or Cipher::STREAM
	 */
	abstract public function type();




	/**********************************************************************
	 * PUBLIC METHODS
	 *
	 **********************************************************************/

	/**
	 * Determine if we are Encrypting or Decrypting
	 * Since some ciphers use the same algorithm to Encrypt or Decrypt but with only
	 * slight differences, we need a way to check if we are Encrypting or Decrypting
	 * An example is DES, which uses the same algorithm except that when Decrypting
	 * the sub_keys are reversed
	 *
	 * @param integer $op Sets the operation to Cipher::ENCRYPT or Cipher::DECRYPT
	 * @return integer The current operation, either Cipher::ENCRYPT or Cipher::DECRYPT
	 */
	public function operation($op = 0)
	{
		if($op == self::ENCRYPT || $op == self::DECRYPT)
			$this->operation = $op;

		return $this->operation;
	}


	/**
	 * Return the name of cipher that is currently being used
	 *
	 * @return string The cipher name
	 */
	public function name($name = "")
	{
		if($name != "")
			$this->cipher_name = $name;

		return $this->cipher_name;
	}


	/**
	 * Size of the data in Bits that get used during encryption
	 *
	 * @param integer $bytes Number of bytes each block of data is required by the cipher
	 * @return integer The number of bytes each block of data required by the cipher
	 */
	public function blockSize($bytes = 0)
	{
		if($bytes > 0)
			$this->block_size = $bytes;

		// in some cases a blockSize is not set, such as stream ciphers.
		// so just return 0 for the block size
		if(!isset($this->block_size))
			return 0;

		return $this->block_size;
	}


	/**
	 * Returns the size (in bytes) required by the cipher.
	 *
	 * @return integer The number of bytes the cipher requires the key to be
	 */
	public function keySize()
	{
		return $this->key_len;
	}


	/**
	 * Set the cipher key used for encryption/decryption. This function
	 * may lengthen or shorten the key to meet the size requirements of
	 * the cipher.
	 *
	 * If the $key parameter is not given, this function simply returns the
	 * current key being used.
	 *
	 * @param string $key Optional, A key for the cipher
	 * @param integer $req_sz The byte size required for the key
	 * @return string They key, which may have been modified to fit size
	 *	requirements
	 */
	public function key($key = "", $req_sz = 0)
	{
		if($key != "" && $key != null)
		{
			// in the case where the key is changed changed after
			// creating a new Cipher object and the $req_sz was not
			// given, we need to make sure the new key meets the size
			// requirements. This can be determined from the $this->key_len
			// member set from the previous key
			if($this->key_len > 0 && $req_sz == 0)
				$req_sz = $this->key_len;
			else
				$this->key_len = strlen($key);

			if($req_sz > 0)
			{
				if($this->key_len > $req_sz)
				{
					// shorten the key length
					$key = substr($key, 0, $req_sz);
					$this->key_len = $req_sz;
				}
				else if($this->key_len < $req_sz)
				{
					// send a notice that the key was too small
					// NEVER PAD THE KEY, THIS WOULD BE INSECURE!!!!!
					$msg = strtoupper($this->name())." requires a $req_sz byte key, {$this->key_len} bytes received";
					trigger_error($msg, E_USER_WARNING);
					
					return false;
				}
			}

			$this->key = $key;
		}

		return $this->key;
	}
}
?>
