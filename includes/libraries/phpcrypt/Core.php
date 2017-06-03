<?php
/*
 * Author: Ryan Gilfether
 * URL: http://www.gilfether.com/phpCrypt
 * Date: March 26, 2013
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

/**
 * A base class that should not be used directly. It is intended as a base
 * object that should be extended and provides tools that child objects may use.
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2013 Ryan Gilfether
 */
class Core
{
    /** @type integer HASH_LEN The length of md5() hash string */
    const HASH_LEN = 16;


    /**
     * Constructor
     *
     */
    protected function __construct()
    {

    }


    /**
     * Destructor
     *
     */
    protected function __destruct()
    {

    }


    /**
     * Convert hexidecimal to a binary string (ex: "00110110")
     *
     * @param string $hex A string containing a hexidecimal number
     * @return string A string representation of a binary
     */
    public static function hex2Bin($hex)
    {
        // if we do not have an even number of hex characters
        // append a 0 to the beginning to make it even
        if (strlen($hex) % 2) {
                    $hex = "0$hex";
        }

        $parts = str_split($hex, 2);
        $parts = array_map(function($v) {
                $v = base_convert($v, 16, 2);
                return str_pad($v, 8, "0", STR_PAD_LEFT);
        }, $parts);

        return implode("", $parts);
    }


    /**
     * Convert hex to a string
     *
     * @param string $hex A string representation of Hex (IE: "1a2b3c" not 0x1a2b3c)
     * @return string a string
     */
    public static function hex2Str($hex)
    {
        // php version >= 5.4 have a hex2bin function, use it
        // if it exists
        if (function_exists("hex2bin")) {
                    return hex2bin($hex);
        }

        $parts = str_split($hex, 2);
        $parts = array_map(function($v) {
                return chr(Core::hex2Dec($v));
        }, $parts);

        return implode("", $parts);
    }


    /**
     * Converts Hex to Decimal
     * This function just calls php's hexdec() function,  but I
     * encapsulated it in this function to keep things uniform
     * and have all possible conversion function available in
     * the Cipher class
     *
     * @param string $hex A hex number to convert to decimal
     * @return integer A decimal number
     */
    public static function hex2Dec($hex)
    {
        return hexdec($hex);
    }


    /**
     * Convert binary string (ie 00110110) to hex
     *
     * @param string $bin A binary string
     * @return string A string representation of hexidecimal number
     */
    public static function bin2Hex($bin)
    {
        $parts = str_split($bin, 8);

        $parts = array_map(function($v) {
            $v = str_pad($v, 8, "0", STR_PAD_LEFT);
            $v = dechex(bindec($v));
            return str_pad($v, 2, "0", STR_PAD_LEFT);
        }, $parts);

        return implode("", $parts);
    }


    /**
     * Converts a binary representation (ie 01101011)  back to a string
     *
     * @param string $bin a binary representation string
     * @return string A string of characters representing the binary
     */
    public static function bin2Str($bin)
    {
        $hex = self::bin2Hex($bin);
        return self::hex2Str($hex);
    }


    /**
     * Convert a binary string (ie: 01101011) to a decimal number
     *
     * @param string A string representation of a binary number
     * @param string $bin
     * @return integer The number converted from the binary string
     */
    public static function bin2Dec($bin)
    {
        return bindec($bin);
    }


    /**
     * Convert a string to hex
     * This function calls the PHP bin2hex(), and is here
     * for consistency with the other string functions
     *
     * @param string $str A string
     * @return string A string representation of hexidecimal number
     */
    public static function str2Hex($str)
    {
        return bin2hex($str);
    }


    /**
     * Convert a string of characters to a decimal number
     *
     * @param string $str The string to convert to decimal
     * @return integer The integer converted from the string
     */
    public static function str2Dec($str)
    {
        $hex = self::str2Hex($str);
        return self::hex2Dec($hex);
    }


    /**
     * Converts a string to binary representation (ie 01101011)
     *
     * @param string $str A string
     * @return string A binary representation of the the string
     */
    public static function str2Bin($str)
    {
        $hex = self::str2Hex($str);
        $parts = str_split($hex, 2);

        $parts = array_map(function($v) {
            return Core::hex2Bin($v);
        }, $parts);

        return implode("", $parts);
    }


    /**
     * Converts Decimal to Hex
     * This function just calls php's dechex() function,  but I
     * encapsulated it in this function to keep things uniform
     * and have all possible conversion function available in
     * the Cipher class
     *
     * The parameter $req_bytes will pad the return hex with NULL (00)
     * until the hex represents the number of bytes given to $req_bytes
     * This is because dechex() drops null bytes from the Hex, which may
     * be needed in some cases
     *
     * @param integer $dec A decimal number to convert
     * @param integer $req_bytes Optional, forces the string to be at least
     *	$req_bytes in size, this is needed because on occasion left most null bytes
     *	are dropped in dechex(), causing the string to have a shorter byte
     *	size than the initial integer.
     * @return string A hexidecimal representation of the decimal number
     */
    public static function dec2Hex($dec, $req_bytes = 0)
    {
        $hex = dechex($dec);

        // if we do not have an even number of hex characters
        // append a 0 to the beginning. dechex() drops leading 0's
        if (strlen($hex) % 2) {
                    $hex = "0$hex";
        }

        // if the number of bytes in the hex is less than
        // what we need it to be, add null bytes to the
        // front of the hex to padd it to the required size
        if (($req_bytes * 2) > strlen($hex)) {
                    $hex = str_pad($hex, ($req_bytes * 2), "0", STR_PAD_LEFT);
        }

        return $hex;
    }


    /**
     * Converts Decimal to Binary
     * This function just calls php's decbin() function,  but I
     * encapsulated it in this function to keep things uniform
     * and have all possible conversion function available in
     * the Cipher class
     *
     * @param integer $dec A decimal number to convert
     * @param integer $req_bytes Optional, forces the string to be at least
     *	$req_bytes in size, this is needed because on occasion left most null bytes
     *	are dropped in dechex(), causing the string to have a shorter byte
     *	size than the initial integer.
     * @return string A binary representation of the decimal number
     */
    public static function dec2Bin($dec, $req_bytes = 0)
    {
        $hex = self::dec2Hex($dec, $req_bytes);
        return self::hex2Bin($hex);
    }


    /**
     * Convert a decimal to a string of bytes
     *
     * @param integer $dec A decimal number
     * @param integer $req_bytes Optional, forces the string to be at least
     *	$req_bytes in size, this is needed because on occasion left most null bytes
     *	are dropped in dechex(), causing the string to have a shorter byte
     *	size than the initial integer.
     * @return string A string with the number of bytes equal to $dec
     */
    public static function dec2Str($dec, $req_bytes = 0)
    {
        $hex = self::dec2Hex($dec, $req_bytes);
        return self::hex2Str($hex);
    }


    /**
     * XORs two binary strings (representation of binary, ie 01101011),
     * assumed to be equal length
     *
     * @param string $a A string that represents binary
     * @param string $b A string that represents binary
     * @return string A representation of binary
     */
    public static function xorBin($a, $b)
    {
        $len_a = strlen($a);
        $len_b = strlen($b);
        $width = $len_a;

        // first determine if the two binary strings are the same length,
        // and if not get them to the same length
        if ($len_a > $len_b)
        {
            $width = $len_a;
            $b = str_pad($b, $width, "0", STR_PAD_LEFT);
        } else if ($len_a < $len_b)
        {
            $width = $len_b;
            $a = str_pad($a, $width, "0", STR_PAD_LEFT);
        }

        // fortunately PHP knows how to XOR each byte in a string
        // so we don't have to loop to do it
        $bin = self::bin2Str($a) ^ self::bin2Str($b);
        return self::str2Bin($bin);
    }


    /**
     * ExclusiveOR hex values. Supports an unlimited number of parameters.
     * The values are string representations of hex values
     * IE: "0a1b2c3d" not 0x0a1b2c3d
     *
     * @param string Unlimited number parameters, each a string representation of hex
     * @return string A string representation of the result in Hex
     */
    public static function xorHex()
    {
        $hex   = func_get_args();
        $count = func_num_args();

        // we need a minimum of 2 values
        if ($count < 2) {
                    return false;
        }

        // first get all hex values to an even number
        array_walk($hex, function(&$val, $i) {
            if (strlen($val) % 2) {
                            $val = "0".$val;
            }
        });

        $res = 0;
        for ($i = 0; $i < $count; ++$i)
        {
            // if this is the first loop, set the 'result' to the first
            // hex value
            if ($i == 0) {
                            $res = $hex[0];
            } else
            {
                // to make the code easier to follow
                $h1 = $res;
                $h2 = $hex[$i];

                // get lengths
                $len1 = strlen($h1);
                $len2 = strlen($h2);

                // now check that both hex values are the same length,
                // if not pad them with 0's until they are
                if ($len1 > $len2) {
                                    $h2 = str_pad($h2, $len1, "0", STR_PAD_LEFT);
                } else if ($len1 < $len2) {
                                    $h1 = str_pad($h1, $len2, "0", STR_PAD_LEFT);
                }

                // PHP knows how to XOR each byte in a string, so convert the
                // hex to a string, XOR, and convert back
                $res = self::hex2Str($h1) ^ self::hex2Str($h2);
                $res = self::str2Hex($res);
            }
        }

        return $res;
    }


    /**
     * Forces an integer to be signed
     *
     * @param integer $int An integer
     * @return integer An signed integer
     */
    public static function sInt($int)
    {
        $arr = unpack("i", pack("i", $int));
        return $arr[1];
    }


    /**
     * Forces an integer to be unsigned
     *
     * @param integer $int A signed integer
     * @return integer An unsigned integer
     */
    public static function uInt($int)
    {
        $arr = unpack("I", pack("I", $int));
        $ret = $arr[1];

        // On 32 bit platforms unpack() and pack() do not convert
        // from signed to unsigned properly all the time, it will return
        // the same negative number given to it, the work around is
        // to use sprintf().
        // Tested with php 5.3.x on Windows XP & Linux 32bit
        if ($ret < 0) {
                    $ret = sprintf("%u", $ret) + 0;
        }
        // convert from string to int

        return $ret;
    }


    /**
     * Forces an integer to be a 32 bit signed integer
     *
     * @param integer $int An integer
     * @return integer An signed 32 bit integer
     */
    public static function sInt32($int)
    {
        if (PHP_INT_SIZE === 4) {
            // 32 bit
            return self::sInt($int);
        } else // PHP_INT_SIZE === 8 // 64 bit
        {
            $arr = unpack("l", pack("l", $int));
            return $arr[1];
        }
    }


    /**
     * Force an integer to be a 32 bit unsigned integer
     *
     * @param integer $int An integer
     * @return integer An unsigned 32 bit integer
     */
    public static function uInt32($int)
    {
        if (PHP_INT_SIZE === 4) {
            // 32 bit
            return self::uInt($int);
        } else // PHP_INT_SIZE === 8  // 64 bit
        {
            $arr = unpack("L", pack("L", $int));
            return $arr[1];
        }
    }


    /**
     * Converts an integer to the value for an signed char
     *
     * @param integer $int The integer to convert to a signed char
     * @return integer A signed integer, representing a signed char
     */
    public static function sChar($int)
    {
        $arr = unpack("c", pack("c", $int));
        return $arr[1];
    }


    /**
     * Converts an integer to the value for an unsigned char
     *
     * @param integer $int The integer to convert to a unsigned char
     * @return integer An unsigned integer, representing a unsigned char
     */
    public static function uChar($int)
    {
        $arr = unpack("C", pack("C", $int));
        return $arr[1];
    }


    /**
     * Rotates bits Left, appending the bits pushed off the left onto the right
     *
     * @param integer $shifts The number of shifts left to make
     * @return integer The resulting value from the rotation
     */
    public static function rotBitsLeft32($i, $shifts)
    {
        if ($shifts <= 0) {
                    return $i;
        }

        $shifts &= 0x1f; /* higher rotates would not bring anything */

        // this is causing problems on 32 bit platform
        //return self::uInt32(($i << $shifts) | ($i >> (32 - $shifts)));

        // so lets cheat: convert to binary string, rotate left, and
        // convert back to decimal
        $i = self::dec2Bin(self::uInt32($i), 4);
        $i = substr($i, $shifts).substr($i, 0, $shifts);
        return self::bin2Dec($i);

    }


    /**
     * Rotates bits right, appending the bits pushed off the right onto the left
     *
     * @param integer $shifts The number of shifts right to make
     * @return integer The resulting value from the rotation
     */
    public static function rotBitsRight32($i, $shifts)
    {
        if ($shifts <= 0) {
                    return $i;
        }

        $shifts &= 0x1f; /* higher rotates would not bring anything */

        // this might cause problems on 32 bit platforms since rotBitsLeft32 was
        // having a problem with some bit shifts on 32 bits
        // return self::uInt32(($i >> $shifts) | ($i << (32 - $shifts)));

        // so lets cheat: convert to binary string, rotate right,
        // and convert back to decimal
        $i = self::dec2Bin($i, 4);
        $i = substr($i, (-1 * $shifts)).substr($i, 0, (-1 * $shifts));
        return self::bin2Dec($i);
    }


    /**
     * Create a string of random bytes, used for creating an IV
     * and a random key. See PHP_Crypt::createKey() and PHP_Crypt::createIV()
     * There are 4 ways to auto generate random bytes by setting $src parameter
     * PHP_Crypt::RAND - Default, uses mt_rand()
     * PHP_Crypt::RAND_DEV_RAND - Unix only, uses /dev/random
     * PHP_Crypt::RAND_DEV_URAND - Unix only, uses /dev/urandom
     * PHP_Crypt::RAND_WIN_COM - Windows only, uses Microsoft's CAPICOM SDK
     *
     * @param string $src Optional, Use the $src to create the random bytes
     * 	by default PHP_Crypt::RAND is used when $src is not specified
     * @param integer $byte_len The length of the byte string to create
     * @return string A random string of bytes
     */
    public static function randBytes($src = PHP_Crypt::RAND, $byte_len = PHP_Crypt::RAND_DEFAULT_SZ)
    {
        $bytes = "";
        $err_msg = "";

        if ($src == PHP_Crypt::RAND_DEV_RAND)
        {
            if (file_exists(PHP_Crypt::RAND_DEV_RAND)) {
                            $bytes = file_get_contents(PHP_CRYPT::RAND_DEV_RAND, false, null, 0, $byte_len);
            } else {
                            $err_msg = PHP_Crypt::RAND_DEV_RAND." not found";
            }
        } else if ($src == PHP_Crypt::RAND_DEV_URAND)
        {
            if (file_exists(PHP_Crypt::RAND_DEV_URAND)) {
                            $bytes = file_get_contents(PHP_CRYPT::RAND_DEV_URAND, false, null, 0, $byte_len);
            } else {
                            $err_msg = PHP_Crypt::RAND_DEV_URAND." not found";
            }
        } else if ($src == PHP_Crypt::RAND_WIN_COM)
        {
            if (extension_loaded('com_dotnet'))
            {
                // http://msdn.microsoft.com/en-us/library/aa388176(VS.85).aspx
                try
                {
                    // request a random number in $byte_len bytes, returned
                    // as base_64 encoded string. This is because PHP munges the
                    // binary data on Windows
                    $com = @new \COM("CAPICOM.Utilities.1");
                    $bytes = $com->GetRandom($byte_len, 0);
                } catch (Exception $e)
                {
                    $err_msg = "Windows COM exception: ".$e->getMessage();
                }

                if (!$bytes) {
                                    $err_msg = "Windows COM failed to create random string of bytes";
                }
            } else {
                            $err_msg = "The COM_DOTNET extension is not loaded";
            }
        }

        // trigger a warning if something went wrong
        if ($err_msg != "") {
                    trigger_error("$err_msg. Defaulting to PHP_Crypt::RAND", E_USER_WARNING);
        }

        // if the random bytes where not created properly or PHP_Crypt::RAND was
        // passed as the $src param, create the bytes using mt_rand(). It's not
        // the most secure option but we have no other choice
        if (strlen($bytes) < $byte_len)
        {
            $bytes = "";

            // md5() hash a random number to get a 16 byte string, keep looping
            // until we have a string as long or longer than the ciphers block size
            for ($i = 0; ($i * self::HASH_LEN) < $byte_len; ++$i) {
                            $bytes .= md5(mt_rand(), true);
            }
        }

        // because $bytes may have come from mt_rand() or /dev/urandom which are not
        // cryptographically secure, lets add another layer of 'randomness' before
        // the final md5() below
        $bytes = str_shuffle($bytes);

        // md5() the $bytes to add extra randomness. Since md5() only returns
        // 16 bytes, we may need to loop to generate a string of $bytes big enough for
        // some ciphers which have a block size larger than 16 bytes
        $tmp = "";
        $loop = ceil(strlen($bytes) / self::HASH_LEN);
        for ($i = 0; $i < $loop; ++$i) {
                    $tmp .= md5(substr($bytes, ($i * self::HASH_LEN), self::HASH_LEN), true);
        }

        // grab the number of bytes equal to the requested $byte_len
        return substr($tmp, 0, $byte_len);
    }
}
?>
