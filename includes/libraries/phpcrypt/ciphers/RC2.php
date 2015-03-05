<?php
/*
 * Author: Ryan Gilfether
 * URL: http://www.gilfether.com/phpCrypt
 * Date: May 5, 2013
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
 * Implements RC2 Encryption
 * The key size must be a multiple of 8 bits, between 8-128 bits
 * Most of this was implemented by reading the original RC2
 * C source code, and recreating it in PHP
 *
 * Resources used to implement this algorithm
 * https://groups.google.com/forum/?fromgroups=#!msg/sci.crypt/m1UFiFMC89Y/cLyO5tr1g_MJ
 * http://tools.ietf.org/html/rfc2268
 * http://www.ipa.go.jp/security/rfc/RFC2268EN.html
 * http://www.umich.edu/~x509/ssleay/rrc2.html
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2013 Ryan Gilfether
 */
class Cipher_RC2 extends Cipher
{
	/** @type integer BYTES_BLOCK The size of the block, in bytes */
	const BYTES_BLOCK = 8; // 64 bits

	// rc2 has a variable length key, between 1 - 128 bytes (8-1024 bits)
	//const BYTES_KEY = 0;

	/** @type array $_sbox RC2's sBox, initialized in initTables() */
	private static $_sbox = array();

	/** @type string $xkey The expanded key */
	private $xkey = "";


	/**
	 * Constructor
	 *
	 * @param string $key The key used for Encryption/Decryption
	 * @return void
	 */
	public function __construct($key)
	{
		// the key must be between 1 and 128 bytes, keys larger than
		// 128 bytes are truncated in expandedKey()
		$keylen = strlen($key);
		if($keylen < 1)
		{
			$err = "Key size is $keylen bits, Key size must be between 1 and 128 bytes";
			trigger_error($err, E_USER_WARNING);
		}

		// set the key
		parent::__construct(PHP_Crypt::CIPHER_RC2, $key, $keylen);

		// set the block size
		$this->blockSize(self::BYTES_BLOCK);

		// initialize the tables
		$this->initTables();

		// expand the key to 128 bytes
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
	 * Encrypt plain text data using RC2
	 *
	 * @param string $text A 64 bit (8 byte) plain text string
	 * @return boolean Returns true
	 */
	public function encrypt(&$text)
	{
		$this->operation(parent::ENCRYPT);

		// split up the message and key into 4 16 bit parts (2 byte words),
		// then convert each array element to an integer
		$w = self::splitBytes($text);
		$k = self::splitBytes($this->xkey);
		$j = 0; // the key index

		for($i = 0; $i < 16; ++$i)
		{
			$j = $i * 4;

			/* This is where it gets ugly, RC2 relies on unsigned ints. PHP does
			 * not have a nice way to handle unsigned ints, so we have to rely on sprintf.
			 * To make RC2 compatible with mCrypt, I also forced everything to 32 bit
			 * When I test against mcrypt and the original rc2 C source, I get 32 bit
			 * results, even on a 64 bit platform
			 */

			// 16 rounds of RC2's Mixing algorithm for each 2 byte 'word' as
			// required by RC2
			$w[0] += parent::uInt($w[1] & ~$w[3]) + parent::uInt($w[2] & $w[3]) + $k[$j + 0];
			$w[0]  = sprintf("%u", $w[0] << 1) + sprintf("%u", $w[0] >> 15 & 1);
			$w[0]  = parent::uInt32($w[0]);

			$w[1] += parent::uInt($w[2] & ~$w[0]) + parent::uInt($w[3] & $w[0]) + $k[$j + 1];
			$w[1]  = parent::uInt($w[1] << 2) + parent::uInt($w[1] >> 14 & 3);
			$w[1]  = parent::uInt32($w[1]);

			$w[2] += parent::uInt($w[3] & ~$w[1]) + parent::uInt($w[0] & $w[1]) + $k[$j + 2];
			$w[2]  = parent::uInt($w[2] << 3) + parent::uInt($w[2] >> 13 & 7);
			$w[2]  = parent::uInt32($w[2]);

			$w[3] += parent::uInt($w[0] & ~$w[2]) + parent::uInt($w[1] & $w[2]) + $k[$j + 3];
			$w[3]  = parent::uInt($w[3] << 5) + parent::uInt($w[3] >> 11 & 31);
			$w[3]  = parent::uInt32($w[3]);

			// rounds 5 and 11 get rc2's Mash
			if($i == 4 || $i == 10)
			{
				$w[0] += $k[$w[3] & 63];
				$w[1] += $k[$w[0] & 63];
				$w[2] += $k[$w[1] & 63];
				$w[3] += $k[$w[2] & 63];
			}
		}

		/* truthfully, I am not clear why this is required. It was not mentioned
		 * in any of the documentation I read, however reading the original RC2 C
		 * source code, this was done and in order for me to get the
		 * correct results in PHP I needed to do this as well
		 */
		$max = count($w);
		for($i = 0; $i < $max; ++$i)
		{
			$pos = $i * 2;
			$text[$pos]   = chr($w[$i]);
			$text[$pos+1] = chr($w[$i] >> 8);
		}

		return true;
	}


	/**
	 * Decrypt a RC2 encrypted string
	 *
	 * @param string $text A RC2 encrypted string
	 * @return boolean Returns true
	 */
	public function decrypt(&$text)
	{
		$this->operation(parent::DECRYPT);

		// first split up the message into four 16 bit parts (2 bytes),
		// then convert each array element to an integer
		$w = self::splitBytes($text);
		$k = self::splitBytes($this->xkey);
		$j = 0; // the key index

		for($i = 15; $i >= 0; --$i)
		{
			$j = $i * 4;

			/* This is where it gets ugly, RC2 relies on unsigned ints. PHP does
			 * not have a nice way to handle unsigned ints, so we have to rely on sprintf.
			 * To make RC2 compatible with mCrypt, I also forced everything to 32 bit
			 * When I test against mcrypt and the original rc2 C source, I get 32 bit
			 * results, even on a 64 bit platform
			 */

			$w[3] &= 65535;
			$w[3]  = parent::uInt($w[3] << 11) + parent::uInt($w[3] >> 5);
			$w[3] -= parent::uInt($w[0] & ~$w[2]) + parent::uInt($w[1] & $w[2]) + $k[$j + 3];
			$w[3]  = parent::uInt32($w[3]);

			$w[2] &= 65535;
			$w[2]  = parent::uInt($w[2] << 13) + parent::uInt($w[2] >> 3);
			$w[2] -= parent::uInt($w[3] & ~$w[1]) + parent::uInt($w[0] & $w[1]) + $k[$j + 2];
			$w[2]  = parent::uInt32($w[2]);

			$w[1] &= 65535;
			$w[1]  = parent::uInt($w[1] << 14) + parent::uInt($w[1] >> 2);
			$w[1] -= parent::uInt($w[2] & ~$w[0]) + parent::uInt($w[3] & $w[0]) + $k[$j + 1];
			$w[1]  = parent::uInt32($w[1]);

			$w[0] &= 65535;
			$w[0]  = parent::uInt($w[0] << 15) + parent::uInt($w[0] >> 1);
			$w[0] -= parent::uInt($w[1] & ~$w[3]) + parent::uInt($w[2] & $w[3]) + $k[$j + 0];
			$w[0]  = parent::uInt32($w[0]);

			if($i == 5 || $i == 11)
			{
				$w[3] -= $k[$w[2] & 63];
				$w[2] -= $k[$w[1] & 63];
				$w[1] -= $k[$w[0] & 63];
				$w[0] -= $k[$w[3] & 63];
			}
		}


		/* I am not clear why this is required. It was not mentioned
		 * in any of the documentation I read, however reading the original RC2 C
		 * source code, this was done and in order for me to get the
		 * correct results in PHP I needed to do this as well
		 */
		$max = count($w);
		for($i = 0; $i < $max; ++$i)
		{
			$pos = $i * 2;
			$text[$pos]   = chr($w[$i]);
			$text[$pos+1] = chr($w[$i] >> 8);
		}

		return true;
	}


	/**
	 * Splits an 8 byte string into four array elements of 2 bytes each, swaps the bytes
	 * of each array element, and converts the result into an integer
	 *
	 * @param string $str The 8 byte string to split and convert
	 * @return array An array of 4 elements, each with 2 bytes
	 */
	private static function splitBytes($str)
	{
		$arr = str_split($str, 2);

		return array_map(function($b){
			return Core::str2Dec($b[1].$b[0]);
		}, $arr);
	}


	/**
	 * Expands the key to 128 bits. RC2 uses an initial key size between
	 * 8 - 128 bits, then takes the initial key and expands it out to 128
	 * bits, which is used for encryption
	 *
	 * @return void
	 */
	private function expandKey()
	{
		// start by copying the key to the xkey variable
		$this->xkey = $this->key();
		$len = $this->keySize();

		// the max length of the key is 128 bytes
		if($len > 128)
			$this->xkey = substr($this->xkey, 0, 128);

		// now expanded the rest of the key to 128 bytes, using the sbox
		for($i = $len; $i < 128; ++$i)
		{
			$byte1 = ord($this->xkey[$i - $len]);
			$byte2 = ord($this->xkey[$i - 1]);
			$pos = ($byte1 + $byte2);

			// the sbox is only 255 bytes, so if we extend past that
			// we need to modulo 256 so we have a valid position
			if($pos > 255)
				$pos -= 256;

			$this->xkey .= chr(self::$_sbox[$pos]);
		}

		// now replace the first byte of the key with it's position in the sbox
		$pos = ord($this->xkey[0]);
		$this->xkey[0] = chr(self::$_sbox[$pos]);
	}


	/**
	 * Initialize tables
	 *
	 * @return void
	 */
	private function initTables()
	{
		self::$_sbox = array(
			0xd9, 0x78, 0xf9, 0xc4, 0x19, 0xdd, 0xb5, 0xed, 0x28, 0xe9, 0xfd, 0x79, 0x4a, 0xa0, 0xd8, 0x9d,
			0xc6, 0x7e, 0x37, 0x83, 0x2b, 0x76, 0x53, 0x8e, 0x62, 0x4c, 0x64, 0x88, 0x44, 0x8b, 0xfb, 0xa2,
			0x17, 0x9a, 0x59, 0xf5, 0x87, 0xb3, 0x4f, 0x13, 0x61, 0x45, 0x6d, 0x8d, 0x09, 0x81, 0x7d, 0x32,
			0xbd, 0x8f, 0x40, 0xeb, 0x86, 0xb7, 0x7b, 0x0b, 0xf0, 0x95, 0x21, 0x22, 0x5c, 0x6b, 0x4e, 0x82,
			0x54, 0xd6, 0x65, 0x93, 0xce, 0x60, 0xb2, 0x1c, 0x73, 0x56, 0xc0, 0x14, 0xa7, 0x8c, 0xf1, 0xdc,
			0x12, 0x75, 0xca, 0x1f, 0x3b, 0xbe, 0xe4, 0xd1, 0x42, 0x3d, 0xd4, 0x30, 0xa3, 0x3c, 0xb6, 0x26,
			0x6f, 0xbf, 0x0e, 0xda, 0x46, 0x69, 0x07, 0x57, 0x27, 0xf2, 0x1d, 0x9b, 0xbc, 0x94, 0x43, 0x03,
			0xf8, 0x11, 0xc7, 0xf6, 0x90, 0xef, 0x3e, 0xe7, 0x06, 0xc3, 0xd5, 0x2f, 0xc8, 0x66, 0x1e, 0xd7,
			0x08, 0xe8, 0xea, 0xde, 0x80, 0x52, 0xee, 0xf7, 0x84, 0xaa, 0x72, 0xac, 0x35, 0x4d, 0x6a, 0x2a,
			0x96, 0x1a, 0xd2, 0x71, 0x5a, 0x15, 0x49, 0x74, 0x4b, 0x9f, 0xd0, 0x5e, 0x04, 0x18, 0xa4, 0xec,
			0xc2, 0xe0, 0x41, 0x6e, 0x0f, 0x51, 0xcb, 0xcc, 0x24, 0x91, 0xaf, 0x50, 0xa1, 0xf4, 0x70, 0x39,
			0x99, 0x7c, 0x3a, 0x85, 0x23, 0xb8, 0xb4, 0x7a, 0xfc, 0x02, 0x36, 0x5b, 0x25, 0x55, 0x97, 0x31,
			0x2d, 0x5d, 0xfa, 0x98, 0xe3, 0x8a, 0x92, 0xae, 0x05, 0xdf, 0x29, 0x10, 0x67, 0x6c, 0xba, 0xc9,
			0xd3, 0x00, 0xe6, 0xcf, 0xe1, 0x9e, 0xa8, 0x2c, 0x63, 0x16, 0x01, 0x3f, 0x58, 0xe2, 0x89, 0xa9,
			0x0d, 0x38, 0x34, 0x1b, 0xab, 0x33, 0xff, 0xb0, 0xbb, 0x48, 0x0c, 0x5f, 0xb9, 0xb1, 0xcd, 0x2e,
			0xc5, 0xf3, 0xdb, 0x47, 0xe5, 0xa5, 0x9c, 0x77, 0x0a, 0xa6, 0x20, 0x68, 0xfe, 0x7f, 0xc1, 0xad
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
