<?php
/*
 * Author: Ryan Gilfether
 * URL: http://www.gilfether.com/phpCrypt
 * Date: March 5, 2013
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
require_once(dirname(__FILE__)."/../Mode.php");
require_once(dirname(__FILE__)."/../phpCrypt.php");


/**
 * Allows Raw encryption of block or stream cipher, this does not use any
 * mode, rather is simply calls the Encryption/Decryption method of the
 * Cipher selected. The data encrypted/decrypted must be the same length
 * as required by the Cipher. No padding is used.
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2013 Ryan Gilfether
 */
class Mode_Raw extends Mode
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
		parent::__construct(PHP_Crypt::MODE_RAW, $cipher);
	}


	/**
	 * Constructor used by classes that extend this class
	 * Used by Mode_Stream, which extends this class
	 *
	 * @param object $cipher One of phpCrypts cipher objects
	 * @param integer $mode The mode constant identifier
	 * @return void
	 */
	protected function __construct1($mode, $cipher)
	{
		parent::__construct($mode, $cipher);
	}


	/**
	 * Destructor
	 */
	public function __destruct()
	{
		parent::__destruct();
	}


	/**
	 * Encrypts an the string using the Cipher with no Mode
	 * NOTE: The data in $text must be the exact length required by the Cipher
	 *
	 * @param string $str the string to be encrypted
	 * @return boolean Always returns false
	 */
	public function encrypt(&$text)
	{
		$this->cipher->encrypt($text);
		return true;
	}


	/**
	 * Decrypts one block of cipher text, not using any mode.
	 * NOTE: The data in $text must be the exact length required by the Cipher
	 *
	 * @param string $str the string to be decrypted
	 * @return boolean Always returns false
	 */
	public function decrypt(&$text)
	{
		$this->cipher->decrypt($text);
		return true;
	}


	/**
	 * This mode does not require an IV
	 *
	 * @return boolean false
	 */
	public function requiresIV()
	{
		return false;
	}
}
?>
