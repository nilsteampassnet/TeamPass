<?php
/*
 * Author: Ryan Gilfether
 * URL: http://www.gilfether.com/phpCrypt
 * Date: April 20, 2013
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

include_once(dirname(__FILE__)."/phpCrypt.php");

/**
 * Implement the following byte padding standards:
 * ANSI X.923
 * ISO 10126
 * PKCS7
 * ISO/IEC 7816-4
 * Zero (NULL Byte) Padding
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2013 Ryan Gilfether
 */
class Padding
{
	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() { }


	/**
	 * Destructor
	 *
	 * @return void
	 */
	public function __destruct() { }


	/**
	 * Returns a string padded using the specified padding scheme
	 *
	 * @param string $text The string to pad
	 * @param integer $bytes The number of bytes to pad
	 * @param integer $type One of the predefined padding types
	 * @return boolean True on success, false on error
	 */
	public static function pad(&$text, $bytes, $type = PHP_Crypt::ZERO)
	{
		// if the size of padding is not greater than 1
		// just return true, no padding will be done
		if(!($bytes > 0))
			return true;

		switch($type)
		{
			case PHP_Crypt::PAD_ZERO:
				return self::zeroPad($text, $bytes);
				break;
			case PHP_Crypt::PAD_ANSI_X923:
				return self::ansiX923Pad($text, $bytes);
				break;
			case PHP_Crypt::PAD_ISO_10126:
				return self::iso10126Pad($text, $bytes);
				break;
			case PHP_Crypt::PAD_PKCS7:
				return self::pkcs7Pad($text, $bytes);
				break;
			case PHP_Crypt::PAD_ISO_7816_4:
				return self::iso7816Pad($text, $bytes);
				break;
			default:
				trigger_error("$type is not a valid padding type.", E_USER_NOTICE);
				return self::zeroPad($text, $bytes);
		}

		return true;
	}


	/**
	 * Strips padding from a string
	 *
	 * @param string $text The string strip padding from
	 * @param integer $type One of the predefined padding types
	 * @return boolean True on success, false on error
	 */
	public static function strip(&$text, $type = PHP_Crypt::ZERO)
	{
		switch($type)
		{
			case PHP_Crypt::PAD_ZERO:
				return self::zeroStrip($text);
				break;
			case PHP_Crypt::PAD_ANSI_X923:
				return self::ansiX923Strip($text);
				break;
			case PHP_Crypt::PAD_ISO_10126:
				return self::iso10126Strip($text);
				break;
			case PHP_Crypt::PAD_PKCS7:
				return self::pkcs7Strip($text);
				break;
			case PHP_Crypt::PAD_ISO_7816_4:
				return self::iso7816Strip($text);
				break;
			default:
				trigger_error("$type is not a valid padding type.", E_USER_NOTICE);
				return self::zeroStrip($text);
		}

		return true;
	}


	/**
	 * Pads a string with null bytes
	 *
	 * @param string $text The string to be padded
	 * @param integer $bytes The number of bytes to pad
	 * @return boolean Returns true
	 */
	private static function zeroPad(&$text, $bytes)
	{
		$len = $bytes + strlen($text);
		$text = str_pad($text, $len, chr(0), STR_PAD_RIGHT);
		return true;
	}


	/**
	 * Strips null padding off a string
	 * NOTE: This is generally a bad idea as there is no way
	 * to distinguish a null byte that is not padding from
	 * a null byte that is padding. Stripping null padding should
	 *  be handled by the developer at the application level.
	 *
	 * @param string $text The string with null padding to strip
	 * @return boolean Returns true
	 */
	private static function zeroStrip(&$text)
	{
		// with NULL byte padding, we should not strip off the
		// null bytes, instead leave this to the developer at the
		// application level
		//$text = preg_replace('/\0+$/', '', $text);
		return true;
	}


	/**
	 * Pads a string using ANSI X.923
	 * Adds null padding to the string, except for the last byte
	 * which indicates the number of bytes padded
	 *
	 * @param string $text The string to pad
	 * @param integer The number of bytes to pad
	 * @return boolean Returns true
	 */
	private static function ansiX923Pad(&$text, $bytes)
	{
		$len = $bytes + strlen($text);
		$text  = str_pad($text, ($len-1), "\0", STR_PAD_RIGHT);
		$text .= chr($bytes);
		return true;
	}


	/**
	 * Strips ANSI X.923 padding from a string
	 *
	 * @param string $text The string to strip padding from
	 * @return boolean Returns true
	 */
	private static function ansiX923Strip(&$text)
	{
		$pos = strlen($text) - 1;
		$c = ord($text[$pos]);

		if($c == 0)
			return true;
		else if($c == 1)
			$text = substr($text, 0, -1);
		else
		{
			// the total null bytes are 1 less than the value of the final byte
			$nc = $c - 1;
			$text = preg_replace('/\0{'.$nc.'}'.preg_quote(chr($c)).'$/', "", $text);
		}

		return true;
	}


	/**
	 * Pads a string using ISO 10126
	 * Adds random bytes to the end of the string, except for the last
	 * byte which indicates the number of padded bytes
	 *
	 * @param string $text The string to pad
	 * @param integer The number of bytes to pad
	 * @return boolean Returns true
	 */
	private static function iso10126Pad(&$text, $bytes)
	{
		// create the random pad bytes, we do one less than
		// needed because the last byte is reserved for the
		// number of padded bytes
		for($i = 0; $i < ($bytes - 1); ++$i)
			$text .= chr(mt_rand(0, 255));

		// add the byte to indicate the padding length
		$text .= chr($bytes);
		return true;
	}


	/**
	 * Strips ISO 10126 padding from a string
	 *
	 * @param string $text The string to strip padding from
	 * @return boolean Returns true
	 */
	private static function iso10126Strip(&$text)
	{
		$pos = strlen($text) - 1;
		$c = ord($text[$pos]) * -1;

		// if we got a null byte at the end of the string,
		// just return
		if($c == 0)
			return true;

		$text = substr($text, 0, $c);
		return true;
	}


	/**
	 * Pads a string using PKCS7
	 * Adds padding using the bytes with the value of the
	 * number of bytes need for padding
	 *
	 * @param string $text The string to pad
	 * @param integer The number of bytes to pad
	 * @return boolean Returns true
	 */
	private static function pkcs7Pad(&$text, $bytes)
	{
		$len = $bytes + strlen($text);
		$text  = str_pad($text, $len, chr($bytes), STR_PAD_RIGHT);
		return true;
	}


	/**
	 * Strips PKCS7 padding from a string
	 *
	 * @param string $text The string to strip padding from
	 * @return boolean Returns true
	 */
	private static function pkcs7Strip(&$text)
	{
		$pos = strlen($text) - 1;
		$c = ord($text[$pos]);

		if($c == 0)
			return true;

		$text = preg_replace('/'.preg_quote(chr($c)).'{'.$c.'}$/', "", $text);
		return true;
	}


	/**
	 * Pads a string using ISO/IEC 7816-4
	 * Adds byte 0x80 followed by null bytes to pad a string
	 *
	 * @param string $text The string to pad
	 * @param integer The number of bytes to pad
	 * @return boolean Returns true
	 */
	private static function iso7816Pad(&$text, $bytes)
	{
		$text .= chr(0x80);
		$len = $bytes + strlen($text);

		// if we are only padding one byte, then 0x80 is all we need
		// else we follow up with null bytes
		if($bytes > 1)
			$text  = str_pad($text, ($len - 1), chr(0), STR_PAD_RIGHT);

		return true;
	}


	/**
	 * Strips ISO/IEC 7816-4 padding from a string
	 *
	 * @param string $text The string to strip padding from
	 * @return boolean Returns true
	 */
	private static function iso7816Strip(&$text)
	{
		$c = chr(0x80);
		$text = preg_replace('/'.preg_quote($c).'\0*$/', '', $text);
		return true;
	}
}
?>
