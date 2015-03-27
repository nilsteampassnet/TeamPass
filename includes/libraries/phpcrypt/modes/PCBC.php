<?php
/*
 * Author: Ryan Gilfether
 * URL: http://www.gilfether.com/phpCrypt
 * Date: March 25, 2013
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
 * Implements Propagating Cipher-Block Chaining (PCBC) block cipher mode
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2013 Ryan Gilfether
 */
class Mode_PCBC extends Mode
{
	/**
	 * Constructor
	 * Sets the cipher object that will be used for encryption
	 *
	 * @param object $cipher one of the phpCrypt encryption cipher objects
	 * @return void
	 */
	function __construct($cipher)
	{
		parent::__construct(PHP_Crypt::MODE_PCBC, $cipher);

		// this works with only block Ciphers
		if($cipher->type() != Cipher::BLOCK)
			trigger_error("PCBC mode requires a block cipher", E_USER_WARNING);
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
	 * Encrypts an the entire string $plain_text using the cipher
	 *
	 * @param string $text the string to be encrypted
	 * @return boolean Returns true
	 */
	public function encrypt(&$text)
	{
		$this->pad($text);
		$blocksz = $this->cipher->blockSize();

		$max = strlen($text) / $blocksz;
		for($i = 0; $i < $max; ++$i)
		{
			// current position in the text
			$pos = $i * $blocksz;

			// get a block of plain text, and make a copy
			$block = substr($text, $pos, $blocksz);
			$plain_block = $block;

			// xor the register with plain text
			for($j = 0; $j < $blocksz; ++$j)
				$block[$j] = $this->register[$j] ^ $block[$j];

			// encrypt the block creating the cipher text
			$this->cipher->encrypt($block);

			// xor the encrypted block with the plain text block to create
			// the register in the next round
			for($j = 0; $j < $blocksz; ++$j)
				$this->register[$j] = $block[$j] ^ $plain_block[$j];

			// copy the encrypted block back to $text
			$text = substr_replace($text, $block, $pos, $blocksz);
		}

		return true;
	}


	/**
	 * Decrypts an the entire string $plain_text using the cipher
	 *
	 * @param string $text the string to be decrypted
	 * @return boolean Returns true
	 */
	public function decrypt(&$text)
	{
		$blocksz = $this->cipher->blockSize();

		$max = strlen($text) / $blocksz;
		for($i = 0; $i < $max; ++$i)
		{
			// current position in the text
			$pos = $i * $blocksz;

			// get a block of encrypted text, and make a copy
			$block = substr($text, $pos, $blocksz);
			$enc_block = $block;

			// decrypt the block
			$this->cipher->decrypt($block);

			// xor the decrypted block with the register, to create
			// the plain text block
			for($j = 0; $j < $blocksz; ++$j)
				$block[$j] = $this->register[$j] ^ $block[$j];

			// now xor the $enc_block with the unencrypted $block
			for($j = 0; $j < $blocksz; ++$j)
				$this->register[$j] = $block[$j] ^ $enc_block[$j];

			// copy the plain text block back to $text
			$text = substr_replace($text, $block, $pos, $blocksz);
		}

		$this->strip($text);
		return true;
	}


	/**
	 * This mode requires an IV
	 *
	 * @return boolean Returns true
	 */
	public function requiresIV()
	{
		return true;
	}
}
?>
