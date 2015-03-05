<?php
/*
 * Author: Ryan Gilfether
 * URL: http://www.gilfether.com/phpCrypt
 * Date: July 14, 2013
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
 * Implements The 3Way Cipher
 * There may be a bug with this implementation of 3-Way. Due
 * to lack of information on this cipher, the mCrypt
 * implementation of 3-Way was used to help develop phpCrypt's
 * version. The gamma() function in mCrypt's 3-way.c does
 * not modifiy the values passed into it. I am not sure
 * if this is a bug, or if it is by design though I suspect
 * it may not be intended to work that way. For compatibility
 * with mCrypt's 3-Way implementation, phpCrypts 3-Way gamma()
 * function does nothing, and leaves the value passed into it
 * unmodified as well. If this is found to be incorrect I
 * will make the necessary changes to correct it. Please view
 * the gamma() function in this class for more information.
 *
 * Resources used to implement this algorithm:
 * mCrypt's 3-way.c
 * http://www.quadibloc.com/crypto/co040307.htm
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2013 Ryan Gilfether
 */
class Cipher_3Way extends Cipher
{
	/** @type integer BYTES_BLOCK The size of the block, in bytes */
	const BYTES_BLOCK = 12; // 96 bits;

	/** @type integer BYTES_KEY The size of the key, in bytes */
	const BYTES_KEY = 12; // 96 bits;

	/** @type integer ROUNDS The number of rounds to implement */
	const ROUNDS = 11;

	/** @type array $_rconst_enc The round constants for encryption */
	private static $_rcon_enc = array();

	/** @type array $_rconst_enc The round constants for decryption */
	private static $_rcon_dec = array();


	/**
	 * Constructor
	 *
	 * @param string $key The key used for Encryption/Decryption
	 * @return void
	 */
	public function __construct($key)
	{
		// set the key, make sure the required length is set in bytes
		parent::__construct(PHP_Crypt::CIPHER_3WAY, $key, self::BYTES_KEY);

		// set the block size
		$this->blockSize(self::BYTES_BLOCK);

		// initialize the round constants
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
	 * Encrypt plain text data
	 *
	 * @param string $data A 96 bit plain data
	 * @return boolean Returns true
	 */
	public function encrypt(&$data)
	{
		$this->operation(parent::ENCRYPT);
		return $this->threeway($data);
	}


	/**
	 * Decrypt an encrypted string
	 *
	 * @param string $data A 96 bit block encrypted data
	 * @return boolean Returns true
	 */
	public function decrypt(&$data)
	{
		$this->operation(parent::DECRYPT);
		return $this->threeway($data);
	}


	/**
	 * The same alorigthm is used for both Encryption, and Decryption
	 *
	 * @param string $data A 96 bit block of data
	 * @return boolean Returns true
	 */
	private function threeway(&$data)
	{
		// first split $data into three 32 bit parts
		$data = str_split($data, 4);
		$data = array_map("parent::str2Dec", $data);

		// split the key into three 32 bit parts
		$key = str_split($this->key(), 4);
		$key = array_map("parent::str2Dec", $key);

		// determine which round constant to use
		if($this->operation() == parent::ENCRYPT)
			$rcon = self::$_rcon_enc;
		else
			$rcon = self::$_rcon_dec;

		if($this->operation() == parent::DECRYPT)
		{
			$this->theta($key);
			$this->invertBits($key);
			$this->invertBits($data);
		}

		// 3Way uses 11 rounds
		for($i = 0; $i < self::ROUNDS; ++$i)
		{
			$data[0] = parent::uInt32($data[0] ^ $key[0] ^ ($rcon[$i] << 16));
			$data[1] = parent::uInt32($data[1] ^ $key[1]);
			$data[2] = parent::uInt32($data[2] ^ $key[2] ^ $rcon[$i]);

			$this->rho($data);
		}

		$data[0] = parent::uInt32($data[0] ^ $key[0] ^ ($rcon[self::ROUNDS] << 16));
		$data[1] = parent::uInt32($data[1] ^ $key[1]);
		$data[2] = parent::uInt32($data[2] ^ $key[2] ^ $rcon[self::ROUNDS]);

		$this->theta($data);

		if($this->operation() == parent::DECRYPT)
			$this->invertBits($data);

		// assemble the three 32 bit parts back to a 96 bit string
		$data = parent::dec2Str($data[0], 4).parent::dec2Str($data[1], 4).
				parent::dec2Str($data[2], 4);

		return true;
	}


	/**
	 * 3-Way's Theta function
	 * This was translated from mcrypt's 3-Way theta() function
	 *
	 * @param array $d A 3 element array of 32 bit integers
	 * @return void
	 */
	private function theta(&$d)
	{
		$tmp = array();

		$tmp[0] = parent::uInt32(
					$d[0] ^ ($d[0] >> 16) ^ ($d[1] << 16) ^ ($d[1] >> 16) ^ ($d[2] << 16) ^
					($d[1] >> 24) ^ ($d[2] << 8) ^ ($d[2] >> 8) ^ ($d[0] << 24) ^ ($d[2] >> 16) ^
					($d[0] << 16) ^ ($d[2] >> 24) ^ ($d[0] << 8)
				);

		$tmp[1] = parent::uInt32(
					$d[1] ^ ($d[1] >> 16) ^ ($d[2] << 16) ^ ($d[2] >> 16) ^ ($d[0] << 16) ^
					($d[2] >> 24) ^ ($d[0] << 8) ^ ($d[0] >> 8) ^ ($d[1] << 24) ^ ($d[0] >> 16) ^
					($d[1] << 16) ^ ($d[0] >> 24) ^ ($d[1] << 8)
				);

		$tmp[2] = parent::uInt32(
					$d[2] ^ ($d[2] >> 16) ^ ($d[0] << 16) ^ ($d[0] >> 16) ^ ($d[1] << 16) ^
					($d[0] >> 24) ^ ($d[1] << 8) ^ ($d[1] >> 8) ^ ($d[2] << 24) ^ ($d[1] >> 16) ^
					($d[2] << 16) ^ ($d[1] >> 24) ^ ($d[2] << 8)
				);

		$d = $tmp;
	}


	/**
	 * 3-Ways gamma() function
	 * NOTE: After extensive testing against mcrypt's 3way, it appears
	 * mcrypt's 3way gamma() function does not modify the array passed into
	 * it. During testing mcrypt's 3way gamma() function returned the exact
	 * same values sent into it. I'm confused as to why this is, and
	 * can't find enough information about 3-Way to know if this is correct
	 * though I suspect it's not. For compatibility, I am going to make
	 * phpCrypt's 3way gamma() function leave the values unmodified. I will change
	 * this at a later date if I find this is incorrect and mcrypt has a bug
	 *
	 * This was translated from mcrypt's 3-Way gamma() function
	 *
	 * @param array $d A 3 element array of 32 bit integers
	 * @return void
	 */
	private function gamma(&$d)
	{
		/*
		$tmp = array();

		$tmp[0] = parent::uInt32($d[0] ^ ($d[1] | (~$d[2])));
		$tmp[1] = parent::uInt32($d[1] ^ ($d[2] | (~$d[0])));
		$tmp[2] = parent::uInt32($d[2] ^ ($d[0] | (~$d[1])));

		$d = $tmp;
		*/
	}


	/**
	 * Applies several of 3Way's functions used for encryption and decryption
	 * NOTE: Please read the comments in the $this->gamma() function. This
	 * function calls the $this->gamma() function which does not do anything.
	 *
	 * @param array $d A 3 element 32 bit integer array
	 * @return void
	 */
	private function rho(&$d)
	{
		$this->theta($d);
		$this->pi1($d);
		$this->gamma($d);
		$this->pi2($d);
	}


	/**
	 * 3Way's PI_1 function
	 * This was taken from mcrypt's 3-way pi_1() function
	 *
	 * @param array $d A 3 element 32 bit integer array
	 * @return void
	 */
	private function pi1(&$d)
	{
		$d[0] = parent::uInt32(($d[0] >> 10) ^ ($d[0] << 22));
		$d[2] = parent::uInt32(($d[2] << 1) ^ ($d[2] >> 31));
	}


	/**
	 * 3Way's PI_2 function
	 * This was taken from mcrypt's 3-way pi_2() function
	 *
	 * @param array $d A 3 element 32 bit integer array
	 * @return void
	 */
	private function pi2(&$d)
	{
		$d[0] = parent::uInt32(($d[0] << 1) ^ ($d[0] >> 31));
		$d[2] = parent::uInt32(($d[2] >> 10) ^ ($d[2] << 22));
	}


	/**
	 * Reverse the bits of each element of array $d, and
	 * reverses the order of array $d, used only during
	 * decryption
	 *
	 * @param array $d A 3 element array of a 32 bit integers
	 * @return void
	 */
	private function invertBits(&$d)
	{
		$d = array_map("parent::dec2Bin", $d);
		$d = array_map("strrev", $d);
		$d = array_map("parent::bin2Dec", $d);
		$d = array_reverse($d);
	}


	/**
	 * Initialize the tables used in 3Way Encryption.
	 *
	 * @return void
	 */
	private function initTables()
	{
		// round constants for encryption
		self::$_rcon_enc = array(
			0x0B0B, 0x1616, 0x2C2C, 0x5858,
			0xB0B0, 0x7171, 0xE2E2, 0xD5D5,
			0xBBBB, 0x6767, 0xCECE, 0x8D8D
		);

		// round constants for decryption
		self::$_rcon_dec = array(
			0xB1B1, 0x7373, 0xE6E6, 0xDDDD,
			0xABAB, 0x4747, 0x8E8E, 0x0D0D,
			0x1A1A, 0x3434, 0x6868, 0xD0D0
		);
	}


	/**
	 * Indicates this is a block cipher
	 *
	 * @return integer Returns Cipher::BLOCK
	 */
	public function type()
	{
		return parent::BLOCK;
	}
}
?>
