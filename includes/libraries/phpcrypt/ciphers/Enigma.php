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
 * Implements a 1 rotor version of the Enigma machine encryption.
 * This is a historical cipher - different variations of Enigma were used
 * by the Germans during WW2. However, old UNIX "crypt" command also used
 * this cipher. This Cipher is outdated and should not be used for,
 * encryption unless it's needed for use with compatibility with the old
 * UNIX 'crypt' command.
 *
 * It seems that mcrypt's Enigma implementation which this is modeled after
 * does everything in 32 bits, even on 64 bit platform. To make this compatible
 * I am forcing ints to be 32 bits as well
 *
 * Resources used to implement this algorithm
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2013 Ryan Gilfether
 */
class Cipher_Enigma extends Cipher
{
	const MASK = 0377;

	/** @type integer ROTORSZ The size of $t1,$t2,$t3,$deck */
	const ROTORSZ = 256;

	/** @type array $t1 An array of chars, ROTORSZ size */
	private $t1 = array();

	/** @type array $t2 An array of chars, ROTORSZ size */
	private $t2 = array();

	/** @type array $t3 An array of chars, ROTORSZ size */
	private $t3 = array();

	/** @type array $deck An array of chars, ROTORSZ size */
	private $deck = array();

	private $n1 = 0;
	private $n2 = 0;
	private $nr1 = 0;
	private $nr2 = 0;

	/** @type string $xkey Stores the modified key */
	private $xkey = "";


	/**
	 * Constructor
	 *
	 * @param string $key The key used for Encryption/Decryption
	 * @return void
	 */
	public function __construct($key)
	{
		parent::__construct(PHP_Crypt::CIPHER_ENIGMA, $key, strlen($key));

		$this->createKey();
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
	 * Encrypt plain text data using Enigma
	 *
	 * @param string $str A plain text string of any length
	 * @return boolean Returns true
	 */
	public function encrypt(&$str)
	{
		$this->operation(parent::ENCRYPT);
		return $this->enigma($str);
	}


	/**
	 * Decrypt an Enigma encrypted string
	 *
	 * @param string $str A Enigma encrypted string
	 * @return boolean Returns true
	 */
	public function decrypt(&$str)
	{
		$this->operation(parent::DECRYPT);
		return $this->enigma($str);
	}


	/**
	 * Peforms the actual Enigma encryption/decryption. Since
	 * the algorithm is the same for both, we do it all in
	 * this one function
	 *
	 * @param string $str A string to encrypt or decrypt
	 * @return boolean Returns true
	 */
	private function enigma(&$str)
	{
		// zero out the counters
		$this->n1 = 0;
		$this->n2 = 0;
		$this->nr1 = 0;
		$this->nr2 = 0;

		$max = strlen($str);
		for($j = 0; $j < $max; ++$j)
		{
			$i = ord($str[$j]);
			$this->nr1 = $this->n1;

			$pos1 = ($i + $this->nr1) & self::MASK;
			$pos3 = ($this->t1[$pos1] + $this->nr2) & self::MASK;
			$pos2 = ($this->t3[$pos3] - $this->nr2) & self::MASK;
			$i = $this->t2[$pos2] - $this->nr1;

			$str[$j] = chr($i);
			$this->n1++;

			if($this->n1 == self::ROTORSZ)
			{
				$this->n1 = 0;
				$this->n2++;

				if($this->n2 == self::ROTORSZ)
					$this->n2 = 0;

				$this->nr2 = $this->n2;
			}
		}

		return true;
	}


	/**
	 * The code for this function was translated to PHP from mcrypt's enigma.c,
	 * I was not able to find sufficient documentation to create my own
	 * version of this function.
	 * Enigma requires a 13 byte key, this function will
	 * lengthen the key to get it to 13 bytes long if it's short. It also
	 * Sets $deck, and $t1, $t2, $t3 which I think are the Enigma rotors.
	 *
	 * @return void
	 */
	private function createKey()
	{
		$this->deck = array();
		$this->t1 = array();
		$this->t2 = array_fill(0, self::ROTORSZ, 0);
		$this->t3 = $this->t2;
		$this->xkey = $this->key();
		$klen = $this->keySize();

		// get the key to exactly 13 bytes if it's less than 13
		if($klen < 13)
			$this->xkey = str_pad($this->xkey, 13, chr(0), STR_PAD_RIGHT);

		$seed = 123;
		for($i = 0; $i < 13; ++$i)
			$seed = parent::sInt32($seed) * ord($this->xkey[$i]) + $i;

		// sets $t1 and $deck
		for($i = 0; $i < self::ROTORSZ; ++$i)
		{
			$this->t1[] = $i;
			$this->deck[] = $i;
		}

		// sets $t3
		for($i = 0; $i < self::ROTORSZ; ++$i)
		{
			// make sure the return values are 32 bit
			$seed = 5 * parent::sInt32($seed) + ord($this->xkey[$i % 13]);
			$seed = parent::sInt32($seed);

			// force the returned value to be an unsigned int
			//$random = parent::uint32($seed % 65521);
			$random = parent::uInt($seed % 65521);

			$k = self::ROTORSZ - 1 - $i;
			$ic = ($random & self::MASK) % ($k + 1);

			// make sure the returned value is an unsigned int
			$random = parent::uInt($random >> 8);

			$temp = $this->t1[$k];
			$this->t1[$k] = $this->t1[$ic];
			$this->t1[$ic] = $temp;
			if($this->t3[$k] != 0)
				continue;

			$ic = ($random & self::MASK) % $k;
			while($this->t3[$ic] != 0)
				$ic = ($ic + 1) % $k;

			$this->t3[$k] = $ic;
			$this->t3[$ic] = $k;
		}

		// sets $t2
		for($i = 0; $i < self::ROTORSZ; ++$i)
		{
			$pos = $this->t1[$i] & self::MASK;
			$this->t2[$pos] = $i;
		}

		// now convert $t1, $t2, $t3, $deck values to signed chars,
		// PHP's chr() function returns an unsigned char, so we have
		// to use use our own
		$this->t1   = array_map("parent::sChar", $this->t1);
		$this->t2   = array_map("parent::sChar", $this->t2);
		$this->t3   = array_map("parent::sChar", $this->t3);
		$this->deck = array_map("parent::sChar", $this->deck);
	}


	/**
	 * Indicates this is a stream cipher
	 *
	 * @return integer Returns Cipher::STREAM
	 */
	public function type()
	{
		return parent::STREAM;
	}
}
?>
