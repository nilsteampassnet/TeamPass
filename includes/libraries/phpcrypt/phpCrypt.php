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
require_once(dirname(__FILE__)."/Includes.inc.php");

/**
 * The phpCrypt class, a front end to all the Ciphers and Modes phpCrypt supports
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2005 Ryan Gilfether
 */
class PHP_Crypt
{
	// Ciphers
	const CIPHER_3DES			= "3DES";
	const CIPHER_3WAY			= "3-Way";
	const CIPHER_AES_128		= "AES-128";
	const CIPHER_AES_192		= "AES-192";
	const CIPHER_AES_256		= "AES-256";
	const CIPHER_ARC4			= "ARC4"; // Alternative RC4
	const CIPHER_BLOWFISH		= "Blowfish";
	const CIPHER_CAST_128		= "CAST-128";
	const CIPHER_CAST_256		= "CAST-256";
	const CIPHER_DES			= "DES";
	const CIPHER_ENIGMA			= "Enigma";
	const CIPHER_GOST			= "GOST";
	const CIPHER_RC2			= "RC2";
	const CIPHER_RIJNDAEL_128	= "Rijndael-128";
	const CIPHER_RIJNDAEL_192	= "Rijndael-192";
	const CIPHER_RIJNDAEL_256	= "Rijndael-256";
	const CIPHER_SKIPJACK		= "Skipjack";
	const CIPHER_SIMPLEXOR		= "SimpleXOR";
	const CIPHER_VIGENERE		= "Vigenere"; // historical

	// Modes
	const MODE_CBC	= "CBC";
	const MODE_CFB	= "CFB";  // 8 bit cfb mode
	const MODE_CTR	= "CTR";
	const MODE_ECB	= "ECB";
	const MODE_NCFB	= "NCFB"; // blocksize cfb mode
	const MODE_NOFB	= "NOFB"; // blocksize ofb mode
	const MODE_OFB	= "OFB";  // 8 bit ofb mode
	const MODE_PCBC	= "PCBC";
	const MODE_RAW	= "Raw";  // raw encryption, with no mode
	const MODE_STREAM = "Stream"; // used only for stream ciphers

	// The source of random data used to create keys and IV's
	// Used for PHP_Crypt::createKey(), PHP_Crypt::createIV()
	const RAND			= "rand"; // uses mt_rand(), windows & unix
	const RAND_DEV_RAND	= "/dev/random"; // unix only
	const RAND_DEV_URAND= "/dev/urandom";// unix only
	const RAND_WIN_COM	= "wincom";		 // windows only, COM extension
	const RAND_DEFAULT_SZ = 32;			 // the default number of bytes returned

	// Padding types
	const PAD_ZERO			= 0;
	const PAD_ANSI_X923		= 1;
	const PAD_ISO_10126		= 2;
	const PAD_PKCS7			= 3;
	const PAD_ISO_7816_4	= 4;


	/** @type object $cipher An instance of the cipher object selected */
	private $cipher = null;

	/** @type object $mode An instance of the mode object selected */
	private $mode = null;


	/**
	 * Constructor
	 *
	 * @param string $key The key to use for the selected Cipher
	 * @param string $cipher The type of cipher to use
	 * @param string $mode The encrypt mode to use with the cipher
	 * @param string $padding The padding type to use. Defaults to PAD_ZERO
	 * @return void
	 */
	public function __construct($key, $cipher = self::CIPHER_AES_128, $mode = self::MODE_ECB, $padding = self::PAD_ZERO)
	{
		/*
		 * CIPHERS
		 */
		switch($cipher)
		{
		case self::CIPHER_3DES:
			$this->cipher = new Cipher_3DES($key);
			break;

		case self::CIPHER_3WAY:
			$this->cipher = new Cipher_3WAY($key);
			break;

		case self::CIPHER_AES_128:
			$this->cipher = new Cipher_AES_128($key);
			break;

		case self::CIPHER_AES_192:
			$this->cipher = new Cipher_AES_192($key);
			break;

		case self::CIPHER_AES_256:
			$this->cipher = new Cipher_AES_256($key);
			break;

		case self::CIPHER_ARC4: // an alternative to RC4
			$this->cipher = new Cipher_ARC4($key);
			break;

		case self::CIPHER_BLOWFISH:
			$this->cipher = new Cipher_Blowfish($key);
			break;

		case self::CIPHER_CAST_128:
			$this->cipher = new Cipher_CAST_128($key);
			break;

		case self::CIPHER_CAST_256:
			$this->cipher = new Cipher_CAST_256($key);
			break;

		case self::CIPHER_DES:
			$this->cipher = new Cipher_DES($key);
			break;

		case self::CIPHER_ENIGMA:
			$this->cipher = new Cipher_Enigma($key);
			break;

		case self::CIPHER_GOST:
			$this->cipher = new Cipher_GOST($key);
			break;

		case self::CIPHER_RC2:
			$this->cipher = new Cipher_RC2($key);
			break;

		case self::CIPHER_RIJNDAEL_128:
			$this->cipher = new Cipher_Rijndael_128($key);
			break;

		case self::CIPHER_RIJNDAEL_192:
			$this->cipher = new Cipher_Rijndael_192($key);
			break;

		case self::CIPHER_RIJNDAEL_256:
			$this->cipher = new Cipher_Rijndael_256($key);
			break;

		case self::CIPHER_SIMPLEXOR:
			$this->cipher = new Cipher_Simple_XOR($key);
			break;

		case self::CIPHER_SKIPJACK:
			$this->cipher = new Cipher_Skipjack($key);
			break;

		case self::CIPHER_VIGENERE:
			$this->cipher = new Cipher_Vigenere($key);
			break;

		default:
			trigger_error("$cipher is not a valid cipher", E_USER_WARNING);
		}


		/*
		 * MODES
		 */
		switch($mode)
		{
		case self::MODE_CBC:
			$this->mode = new Mode_CBC($this->cipher);
			break;

		case self::MODE_CFB:
			$this->mode = new Mode_CFB($this->cipher);
			break;

		case self::MODE_CTR:
			$this->mode = new Mode_CTR($this->cipher);
			break;

		case self::MODE_ECB:
			$this->mode = new Mode_ECB($this->cipher);
			break;

		case self::MODE_NCFB:
			$this->mode = new Mode_NCFB($this->cipher);
			break;

		case self::MODE_NOFB:
			$this->mode = new Mode_NOFB($this->cipher);
			break;

		case self::MODE_OFB:
			$this->mode = new Mode_OFB($this->cipher);
			break;

		case self::MODE_PCBC:
			$this->mode = new Mode_PCBC($this->cipher);
			break;

		case self::MODE_RAW:
			$this->mode = new Mode_RAW($this->cipher);
			break;

		case self::MODE_STREAM:
			$this->mode = new Mode_Stream($this->cipher);
			break;

		default:
			trigger_error("$mode is not a valid mode", E_USER_WARNING);
		}

		// set the default padding
		$this->padding($padding);
	}


	/**
	 * Destructor
	 *
	 * @return void
	 */
	public function __destruct()
	{

	}


	/**
	 * Encrypt a plain text message using the Mode and Cipher selected.
	 * Some stream modes require this function to be called in a loop
	 * which requires the use of $result parameter to retrieve
	 * the decrypted data.
	 *
	 * @param string $text The plain text string
	 * @return string The encrypted string
	 */
	public function encrypt($text)
	{
		// check that an iv is set, if required by the mode
		$this->mode->checkIV();

		// the encryption is done inside the mode
		$this->mode->encrypt($text);
		return $text;
	}


	/**
	 * Decrypt an encrypted message using the Mode and Cipher selected.
	 * Some stream modes require this function to be called in a loop
	 * which requires the use of $result parameter to retrieve
	 * the decrypted data.
	 *
	 * @param string $text The encrypted string
	 * @return string The decrypted string
	 */
	public function decrypt($text)
	{
		// check that an iv is set, if required by the mode
		$this->mode->checkIV();

		// the decryption is done inside the mode
		$this->mode->decrypt($text);
		return $text;
	}


	/**
	 * Return the cipher object being used
	 *
	 * @return object The Cipher object
	 */
	public function cipher()
	{
		return $this->cipher;
	}


	/**
	 * Return the mode object being used
	 *
	 * @return object The Mode object
	 */
	public function mode()
	{
		return $this->mode;
	}


	/**
	 * Returns the name of the cipher being used
	 *
	 * @return string The name of the cipher currently in use,
	 *	it will be one of the predefined phpCrypt cipher constants
	 */
	public function cipherName()
	{
		return $this->cipher->name();
	}


	/**
	 * Return the name of the mode being used
	 *
	 * @return string The name of the mode in use, it will
	 * be one of the predefined phpCrypt mode constants
	 */
	public function modeName()
	{
		return $this->mode->name();
	}


	/**
	 * Returns Ciphers required block size in bytes
	 *
	 * @return integer The cipher data block size, in bytes
	 */
	public function cipherBlockSize()
	{
		return $this->cipher->blockSize();
	}


	/**
	 * Returns the cipher's required key size, in bytes
	 *
	 * @return integer The cipher's key size requirement, in bytes
	 */
	public function cipherKeySize()
	{
		return $this->cipher->keySize();
	}


	/**
	 * Sets and/or returns the key to be used. Normally you set
	 * the key in the phpCrypt constructor. This can be usefully
	 * if you need to change the key on the fly and don't want
	 * to create a new instance of phpCrypt.
	 *
	 * If the $key parameter is not given, this function will simply
	 * return the key currently in use.
	 *
	 * @param string $key Optional, The key to set
	 * @return string The key being used
	 */
	public function cipherKey($key = "")
	{
		return $this->cipher->key($key);
	}


	/**
	 * A helper function which will create a random key. Calls
	 * Core::randBytes(). By default it will use PHP_Crypt::RAND for
	 * the random source of bytes, and return a PHP_Crypt::RAND_DEFAULT_SZ
	 * byte string. There are 4 ways to create a random byte string by
	 * setting the $src parameter:
	 * PHP_Crypt::RAND - Default, uses mt_rand()
	 * PHP_Crypt::RAND_DEV_RAND - Unix only, uses /dev/random
	 * PHP_Crypt::RAND_DEV_URAND - Unix only, uses /dev/urandom
	 * PHP_Crypt::RAND_WIN_COM - Windows only, uses Microsoft's CAPICOM SDK
	 *
	 * @param string $src Optional, The source to use to create random bytes
	 * @param integer $len Optional, The number of random bytes to return
	 * @return string A random string of bytes
	 */
	public static function createKey($src = self::RAND, $len = self::RAND_DEFAULT_SZ)
	{
		return Core::randBytes($src, $len);
	}


	/**
	 * Sets the IV to use. Note that you do not need to call
	 * this function if creating an IV using createIV(). This
	 * function is used when an IV has already been created
	 * outside of phpCrypt and needs to be set. Alternatively
	 * you can just pass the $iv parameter to the encrypt()
	 * or decrypt() functions
	 *
	 * When the $iv parameter is not given, the function will
	 * return the current IV being used. See createIV() if you
	 * need to create an IV.
	 *
	 * @param string $iv Optional, The IV to use during Encryption/Decryption
	 * @return void
	 */
	public function IV($iv = "")
	{
		return $this->mode->IV($iv);
	}


	/**
	 * Creates an IV for the the Cipher selected, if one is required.
	 * If you already have an IV to use, this function does not need
	 * to be called, instead set it with setIV(). If you create an
	 * IV with createIV(), you do not need to set it with setIV(),
	 * as it is automatically set in this function
	 *
	 * $src values are:
	 * PHP_Crypt::RAND - Default, uses mt_rand()
	 * PHP_Crypt::RAND_DEV_RAND - Unix only, uses /dev/random
	 * PHP_Crypt::RAND_DEV_URAND - Unix only, uses /dev/urandom
	 * PHP_Crypt::RAND_WIN_COM - Windows only, uses Microsoft's CAPICOM SDK
	 *
	 * @param string $src Optional, how the IV is generated
	 * @return string The IV that was created, and set for the mode
	 */
	public function createIV($src = self::RAND)
	{
		return $this->mode->createIV($src);
	}


	/**
	 * Sets the type of padding to be used within the specified Mode
	 *
	 * @param string $type One of the predefined padding types
	 * @return void
	 */
	public function padding($type = "")
	{
		return $this->mode->padding($type);
	}
}
?>
