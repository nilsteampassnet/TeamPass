<?php
/*
 * Author: Ryan Gilfether
 * URL: http://www.gilfether.com/phpCrypt
 * Date: April 23, 2013
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
 * Implements Skipjack Encryption
 * Resources used to implement this algorithm:
 * csrc.nist.gov/groups/ST/toolkit/documents/skipjack/skipjack.pdf
 * http://calccrypto.wikidot.com/algorithms:skipjack
 * http://www.quadibloc.com/crypto/co040303.htm
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2013 Ryan Gilfether
 */
class Cipher_Skipjack extends Cipher
{
	/** @type integer BYTES_BLOCK The size of the block, in bytes */
	const BYTES_BLOCK = 8; // 64 bits

	/** @type integer BYTES_KEY The size of the key, in bytes */
	const BYTES_KEY = 10; // 80 bits

	/** @type string $expanded_key The expanded key */
	private $expanded_key = "";

	/** @type array $_f The Skipjack F-Table, this is a constant */
	private static $_f = array();


	/**
	 * Constructor
	 *
	 * @param string $key The key used for Encryption/Decryption
	 * @return void
	 */
	public function __construct($key)
	{
		// set the Skipjack key
		parent::__construct(PHP_Crypt::CIPHER_SKIPJACK, $key, self::BYTES_KEY);

		// initialize variables
		$this->initTables();

		// set the block size used
		$this->blockSize(self::BYTES_BLOCK);

		// expand the key from 10 bytes to 128 bytes
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


	/**
	 * Encrypt plain text data using Skipjack
	 *
	 * @param string $data A plain text string, 8 bytes long
	 * @return boolean Returns true
	 */
	public function encrypt(&$text)
	{
		$this->operation(parent::ENCRYPT);

		for($i = 1; $i <= 32; ++$i)
		{
			$pos = (4 * $i) - 4;
			$subkey = substr($this->expanded_key, $pos, 4);

			if($i >= 1 && $i <= 8)
				$this->ruleA($text, $subkey, $i);

			if($i >= 9 && $i <= 16)
				$this->ruleB($text, $subkey, $i);

			if($i >= 17 && $i <= 24)
				$this->ruleA($text, $subkey, $i);

			if($i >= 25 && $i <= 32)
				$this->ruleB($text, $subkey, $i);
		}

		return true;
	}


	/**
	 * Decrypt a Skipjack encrypted string
	 *
	 * @param string $encrypted A Skipjack encrypted string, 8 bytes long
	 * @return boolean Returns true
	 */
	public function decrypt(&$text)
	{
		$this->operation(parent::DECRYPT);

		for($i = 32; $i >= 1; --$i)
		{
			$pos = ($i - 1) * 4;
			$subkey = substr($this->expanded_key, $pos, 4);

			if($i <= 32 && $i >= 25)
				$this->ruleB($text, $subkey, $i);

			if($i <= 24 && $i >= 17)
				$this->ruleA($text, $subkey, $i);

			if($i <= 16 && $i >= 9)
				$this->ruleB($text, $subkey, $i);

			if($i <= 8 && $i >= 1)
				$this->ruleA($text, $subkey, $i);
		}

		return true;
	}


	/**
	 * For the G Permutations, the input data is 2 Bytes The first byte is
	 * the left side and the second is the right side.The round key is 4 bytes
	 * long (Indices 8*i-8 to 8*i), which is split as 4 pieces: K0, K1, K2, K3
	 *
	 * @param string $bytes A 2 byte string
	 * @param string $key 4 bytes of $this->expanded_key
	 * @return string A 2 byte string, the G Permutation of $bytes
	 */
	private function gPermutation($bytes, $key)
	{
		$left = ord($bytes[0]);
		$right = ord($bytes[1]);

		if($this->operation() == parent::ENCRYPT)
		{
			for($i = 0; $i < 4; ++$i)
			{
				if($i == 0 || $i == 2)
				{
					$pos = $right ^ $this->str2Dec($key[$i]);
					$left = $left ^ self::$_f[$pos];
				}
				else
				{
					$pos = $left ^ $this->str2Dec($key[$i]);
					$right = $right ^ self::$_f[$pos];
				}
			}
		}
		else // parent::DECRYPT
		{
			// we do the same as in encryption, but apply the key backwards,
			// from key[3] to key[0]
			for($i = 3; $i >= 0; --$i)
			{
				if($i == 0 || $i == 2)
				{
					$pos = $right ^ $this->str2Dec($key[$i]);
					$left = $left ^ self::$_f[$pos];
				}
				else
				{
					$pos = $left ^ $this->str2Dec($key[$i]);
					$right = $right ^ self::$_f[$pos];
				}
			}
		}

		return $this->dec2Str($left).$this->dec2Str($right);
	}


	/**
	 * Perform SkipJacks RuleA function. Split the data into 4 parts,
	 * 2 bytes each: W0, W1, W2, W3.
	 *
	 * @param string $bytes An 8 byte string
	 * @param string $key 4 bytes of $this->expanded_key
	 * @param integer $i The round number
	 * @return void
	 */
	private function ruleA(&$bytes, $key, $i)
	{
		$w = str_split($bytes, 2);

		if($this->operation() == parent::ENCRYPT)
		{
			/*
			 * Set the W3 as the old W2
			 * Set the W2 as the old W1
			 * Set the W1 as the G(W0)
			 * Set the W0 as the W1 xor W4 xor i
			 */

			$w[4] = $w[3];
			$w[3] = $w[2];
			$w[2] = $w[1];
			$w[1] = $this->gPermutation($w[0], $key);

			$hex1 = $this->str2Hex($w[1]);
			$hex4 = $this->str2Hex($w[4]);
			$hexi = $this->dec2Hex($i);
			$w[0] = $this->xorHex($hex1, $hex4, $hexi);
			$w[0] = $this->hex2Str($w[0]);
		}
		else // parent::DECRYPT
		{
			/*
			 * Set W4 as W0 xor W1 xor i
			 * Set W0 as Inverse G(W1)
			 * Set W1 as the old W2
			 * Set W2 as the old W3
			 * Set W3 as W4
			 */

			$hex0 = $this->str2Hex($w[0]);
			$hex1 = $this->str2Hex($w[1]);
			$hexi = $this->dec2Hex($i);
			$w[4] = $this->xorHex($hex0, $hex1, $hexi);
			$w[4] = $this->hex2Str($w[4]);

			$w[0] = $this->gPermutation($w[1], $key);
			$w[1] = $w[2];
			$w[2] = $w[3];
			$w[3] = $w[4];
		}

		// glue all the pieces back together
		$bytes = $w[0].$w[1].$w[2].$w[3];
	}


	/**
	 * Perform SkipJacks RuleB function. Split the data into 4 parts,
	 * 2 bytes each: W0, W1, W2, W3.
	 *
	 * @param string $bytes An 8 bytes string
	 * @param string $key 4 bytes of $this->expanded_key
	 * @param integer $i The round number
	 * @return void
	 */
	private function ruleB(&$bytes, $key, $i)
	{
		$w = str_split($bytes, 2);

		if($this->operation() == parent::ENCRYPT)
		{
			/*
			 * Set the new W3 as the old W2
			 * Set the new W2 as the old W0 xor old W1 xor i
			 * Set the new W1 as G(old W0)
			 * Set the new W0 as the old W3
			 */

			$w[4] = $w[3];
			$w[3] = $w[2];

			$hex0 = $this->str2Hex($w[0]);
			$hex1 = $this->str2Hex($w[1]);
			$hexi = $this->dec2Hex($i);
			$w[2] = $this->xorHex($hex0, $hex1, $hexi);
			$w[2] = $this->hex2Str($w[2]);

			$w[1] = $this->gPermutation($w[0], $key);
			$w[0] = $w[4];
		}
		else // parent::DECRYPT
		{
			/*
			 * Set W4 as the old W0
			 * Set new W0 as Inverse G(old W1)
			 * Set new W1 as Inverse G(old W1) xor old W2 xor i
			 * Set new W2 as the old W3
			 * Set new W0 as the old W4
			 */

			$w[4] = $w[0];
			$w[0] = $this->gPermutation($w[1], $key);

			$hex0 = $this->str2Hex($w[0]);
			$hex2 = $this->str2Hex($w[2]);
			$hexi = $this->dec2Hex($i);
			$w[1] = $this->xorHex($hex0, $hex2, $hexi);
			$w[1] = $this->hex2Str($w[1]);

			$w[2] = $w[3];
			$w[3] = $w[4];
		}

		$bytes = $w[0].$w[1].$w[2].$w[3];
	}


	/**
	 * Expands the key from 10 bytes, to 128 bytes
	 * This is done by copying the key 1 byte at a time and
	 * appending it to $this->expanded_key, when we reach the
	 * end of the key, we start over at position 0 and continue
	 * until we reach 128 bytes
	 *
	 * @return void
	 */
	private function expandKey()
	{
		$this->expanded_key = "";
		$key_bytes = $this->keySize();
		$key = $this->key();
		$pos = 0;

		for($i = 0; $i < 128; ++$i)
		{
			if($pos == $key_bytes)
				$pos = 0;

			$this->expanded_key .= $key[$pos];
			++$pos;
		}
	}


	/**
	 * Initialize all the tables, this function is called inside the constructor
	 *
	 * @return void
	 */
	private function initTables()
	{
		self::$_f = array(
			0xa3, 0xd7, 0x09, 0x83, 0xf8, 0x48, 0xf6, 0xf4, 0xb3, 0x21, 0x15, 0x78, 0x99, 0xb1, 0xaf, 0xf9,
			0xe7, 0x2d, 0x4d, 0x8a, 0xce, 0x4c, 0xca, 0x2e, 0x52, 0x95, 0xd9, 0x1e, 0x4e, 0x38, 0x44, 0x28,
			0x0a, 0xdf, 0x02, 0xa0, 0x17, 0xf1, 0x60, 0x68, 0x12, 0xb7, 0x7a, 0xc3, 0xe9, 0xfa, 0x3d, 0x53,
			0x96, 0x84, 0x6b, 0xba, 0xf2, 0x63, 0x9a, 0x19, 0x7c, 0xae, 0xe5, 0xf5, 0xf7, 0x16, 0x6a, 0xa2,
			0x39, 0xb6, 0x7b, 0x0f, 0xc1, 0x93, 0x81, 0x1b, 0xee, 0xb4, 0x1a, 0xea, 0xd0, 0x91, 0x2f, 0xb8,
			0x55, 0xb9, 0xda, 0x85, 0x3f, 0x41, 0xbf, 0xe0, 0x5a, 0x58, 0x80, 0x5f, 0x66, 0x0b, 0xd8, 0x90,
			0x35, 0xd5, 0xc0, 0xa7, 0x33, 0x06, 0x65, 0x69, 0x45, 0x00, 0x94, 0x56, 0x6d, 0x98, 0x9b, 0x76,
			0x97, 0xfc, 0xb2, 0xc2, 0xb0, 0xfe, 0xdb, 0x20, 0xe1, 0xeb, 0xd6, 0xe4, 0xdd, 0x47, 0x4a, 0x1d,
			0x42, 0xed, 0x9e, 0x6e, 0x49, 0x3c, 0xcd, 0x43, 0x27, 0xd2, 0x07, 0xd4, 0xde, 0xc7, 0x67, 0x18,
			0x89, 0xcb, 0x30, 0x1f, 0x8d, 0xc6, 0x8f, 0xaa, 0xc8, 0x74, 0xdc, 0xc9, 0x5d, 0x5c, 0x31, 0xa4,
			0x70, 0x88, 0x61, 0x2c, 0x9f, 0x0d, 0x2b, 0x87, 0x50, 0x82, 0x54, 0x64, 0x26, 0x7d, 0x03, 0x40,
			0x34, 0x4b, 0x1c, 0x73, 0xd1, 0xc4, 0xfd, 0x3b, 0xcc, 0xfb, 0x7f, 0xab, 0xe6, 0x3e, 0x5b, 0xa5,
			0xad, 0x04, 0x23, 0x9c, 0x14, 0x51, 0x22, 0xf0, 0x29, 0x79, 0x71, 0x7e, 0xff, 0x8c, 0x0e, 0xe2,
			0x0c, 0xef, 0xbc, 0x72, 0x75, 0x6f, 0x37, 0xa1, 0xec, 0xd3, 0x8e, 0x62, 0x8b, 0x86, 0x10, 0xe8,
			0x08, 0x77, 0x11, 0xbe, 0x92, 0x4f, 0x24, 0xc5, 0x32, 0x36, 0x9d, 0xcf, 0xf3, 0xa6, 0xbb, 0xac,
			0x5e, 0x6c, 0xa9, 0x13, 0x57, 0x25, 0xb5, 0xe3, 0xbd, 0xa8, 0x3a, 0x01, 0x05, 0x59, 0x2a, 0x46
		);
	}


	/**
	 * Indicates that this is a block cipher
	 *
	 * @return integer Returns Cipher::BLOCK
	 */
	public function type()
	{
		return parent::BLOCK;
	}
}
?>
