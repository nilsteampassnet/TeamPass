<?php
/**
* jCryption
*
* PHP version 5.3
*
* LICENSE: This source file is subject to version 3.0 of the PHP license
* that is available through the world-wide-web at the following URI:
* http://www.php.net/license/3_0.txt.  If you did not receive a copy of
* the PHP License and are unable to obtain it through the web, please
* send a note to license@php.net so we can mail you a copy immediately.
*
* Many of the functions in this class are from the PEAR Crypt_RSA package ...
* So most of the credits goes to the original creator of this package Alexander Valyalkin
* you can get the package under http://pear.php.net/package/Crypt_RSA
*
* I just changed, added, removed and improved some functions to fit the needs of jCryption
*
* @author     Daniel Griesser <daniel.griesser@jcryption.org>
* @copyright  2011 Daniel Griesser
* @license    http://www.php.net/license/3_0.txt PHP License 3.0
* @version    1.2
* @link       http://jcryption.org/
*/
class jCryption {

	private $_key_len;
	private $_e;

	public function __construct($e="\x01\x00\x01") {
		$this->_e = $e;
	}

	/**
	* Generates the Keypair with the given keyLength the encryption key e ist set staticlly
	* set to 65537 for faster encryption.
	*
	* @param int $keyLength
	* @return array
	*/
	public function generateKeypair($keyLength) {
		$this->_key_len = intval($keyLength);
		if ($this->_key_len < 8) {
			$this->_key_len = 8;
		}
		// set [e] to 0x10001 (65537)
		$e = $this->_bin2int($this->_e);
		// generate [p], [q] and [n]
		$p_len = intval(($this->_key_len + 1) / 2);
		$q_len = $this->_key_len - $p_len;
		$p1 = $q1 = 0;
		do {
			// generate prime number [$p] with length [$p_len] with the following condition:
			// GCD($e, $p - 1) = 1
			do {
				$p = $this->getPrime($p_len);
				$p1 = bcsub($p, '1');
				$tmp = $this->_gcd($e, $p1);
			} while (bccomp($tmp, '1'));
			// generate prime number [$q] with length [$q_len] with the following conditions:
			// GCD($e, $q - 1) = 1
			// $q != $p
			do {
				$q = $this->getPrime($q_len);
				$q1 = bcsub($q, '1');
				$tmp = $this->_gcd($e, $q1);
			} while (bccomp($tmp, '1') && !bccomp($q, $p));

			// if (p < q), then exchange them
			if (bccomp($p, $q) < 0) {
				$tmp = $p;
				$p = $q;
				$q = $tmp;
				$tmp = $p1;
				$p1 = $q1;
				$q1 = $tmp;
			}
			// calculate n = p * q
			$n = bcmul($p, $q);

		} while ($this->_bitLen($n) != $this->_key_len);

		// calculate d = 1/e mod (p - 1) * (q - 1)
		$pq = bcmul($p1, $q1);
		$d = $this->_invmod($e, $pq);

		// store RSA keypair attributes
		return array('n' => $n, 'e' => $e, 'd' => $d, 'p' => $p, 'q' => $q);
	}

	/**
	* Finds greatest common divider (GCD) of $num1 and $num2
	*
	* @param string $num1
	* @param string $num2
	* @return string
	*/
	private function _gcd($num1, $num2) {
		do {
			$tmp = bcmod($num1, $num2);
			$num1 = $num2;
			$num2 = $tmp;
		} while (bccomp($num2, '0'));
		return $num1;
	}

	/**
	* Transforms binary representation of large integer into its native form.
	*
	* Example of transformation:
	*    $str = "\x12\x34\x56\x78\x90";
	*    $num = 0x9078563412;
	*
	* @param string $str
	* @return string
	* @access public
	*/
	private function _bin2int($str) {
		$result = '0';
		$n = strlen($str);
		do {
			$result = bcadd(bcmul($result, '256'), ord($str {--$n} ));
		} while ($n > 0);
		return $result;
	}

	/**
	* Transforms large integer into binary representation.
	*
	* Example of transformation:
	*    $num = 0x9078563412;
	*    $str = "\x12\x34\x56\x78\x90";
	*
	* @param string $num
	* @return string
	* @access public
	*/
	private function _int2bin($num) {
		$result = '';
		do {
			$result .= chr(bcmod($num, '256'));
			$num = bcdiv($num, '256');
		} while (bccomp($num, '0'));
		return $result;
	}

	/**
	* Generates prime number with length $bits_cnt
	*
	* @param int $bits_cnt
	*/
	public function getPrime($bits_cnt) {
		$bytes_n = intval($bits_cnt / 8);
		do {
			$str = '';
			$str = openssl_random_pseudo_bytes($bytes_n);
			$num = $this->_bin2int($str);
			$num = gmp_strval(gmp_nextprime($num));
		} while ($this->_bitLen($num) != $bits_cnt);
		return $num;
	}

	/**
	* Finds inverse number $inv for $num by modulus $mod, such as:
	*     $inv * $num = 1 (mod $mod)
	*
	* @param string $num
	* @param string $mod
	* @return string
	*/
	private function _invmod($num, $mod) {
		$x = '1';
		$y = '0';
		$num1 = $mod;
		do {
			$tmp = bcmod($num, $num1);
			$q = bcdiv($num, $num1);
			$num = $num1;
			$num1 = $tmp;
			$tmp = bcsub($x, bcmul($y, $q));
			$x = $y;
			$y = $tmp;
		} while (bccomp($num1, '0'));
		if (bccomp($x, '0') < 0) {
			$x = bcadd($x, $mod);
		}
		return $x;
	}

	/**
	* Returns bit length of number $num
	*
	* @param string $num
	* @return int
	*/
	private function _bitLen($num) {
		$tmp = $this->_int2bin($num);
		$bit_len = strlen($tmp) * 8;
		$tmp = ord($tmp {strlen($tmp) - 1} );
		if (!$tmp) {
			$bit_len -= 8;
		} else {
			while (!($tmp & 0x80)) {
				$bit_len--;
				$tmp <<= 1;
			}
		}
		return $bit_len;
	}

	/**
	* Converts a hex string to bigint string
	*
	* @param string $hex
	* @return string
	*/
	private function _hex2bint($hex) {
		$result = '0';
		for ($i=0; $i < strlen($hex); $i++) {
			$result = bcmul($result, '16');
			if ($hex[$i] >= '0' && $hex[$i] <= '9') {
				$result = bcadd($result, $hex[$i]);
			} else if ($hex[$i] >= 'a' && $hex[$i] <= 'f') {
				$result = bcadd($result, '1' . ('0' + (ord($hex[$i]) - ord('a'))));
			} else if ($hex[$i] >= 'A' && $hex[$i] <= 'F') {
				$result = bcadd($result, '1' . ('0' + (ord($hex[$i]) - ord('A'))));
			}
		}
		return $result;
	}

	/**
	* Converts a hex string to int
	*
	* @param string $hex
	* @return int
	* @access public
	*/
	private function _hex2int($hex) {
		$result = 0;
		for ($i=0; $i < strlen($hex); $i++) {
			$result *= 16;
			if ($hex[$i] >= '0' && $hex[$i] <= '9') {
				$result += ord($hex[$i]) - ord('0');
			} else if ($hex[$i] >= 'a' && $hex[$i] <= 'f') {
				$result += 10 + (ord($hex[$i]) - ord('a'));
			} else if ($hex[$i] >= 'A' && $hex[$i] <= 'F') {
				$result += 10 + (ord($hex[$i]) - ord('A'));
			}
		}
		return $result;
	}

	/**
	* Converts a bigint string to the ascii code
	*
	* @param string $bigint
	* @return string
	*/
	private function _bint2char($bigint) {
		$message = '';
		while (bccomp($bigint, '0') != 0) {
			$ascii = bcmod($bigint, '256');
			$bigint = bcdiv($bigint, '256', 0);
			$message .= chr($ascii);
		}
		return $message;
	}

	/**
	* Removes the redundacy in den encrypted string
	*
	* @param string $string
	* @return mixed
	*/
	private function _redundacyCheck($string) {
		$r1 = substr($string, 0, 2);
		$r2 = substr($string, 2);
		$check = $this->_hex2int($r1);
		$value = $r2;
		$sum = 0;
		for ($i=0; $i < strlen($value); $i++) {
			$sum += ord($value[$i]);
		}
		if ($check == ($sum & 0xFF)) {
			return $value;
		} else {
			return NULL;
		}
	}

	/**
	* Decrypts a given string with the $dec_key and the $enc_mod
	*
	* @param string $encrypted
	* @param int $dec_key
	* @param int $enc_mod
	* @return string
	*/
	public function decrypt($encrypted, $dec_key, $enc_mod) {
		//replaced split with explode
		$blocks = explode(' ', $encrypted);
		$result = "";
		$max = count($blocks);
		for ($i=0; $i < $max; $i++) {
			$dec = $this->_hex2bint($blocks[$i]);
			if (function_exists('gmp_strval') && function_exists('gmp_powm')){
				$dec = gmp_strval(gmp_powm($dec, $dec_key, $enc_mod));
			} else {
				$dec = bcpowmod($dec, $dec_key, $enc_mod);
			}
			$ascii = $this->_bint2char($dec);
			$result .= $ascii;
		}
		return $this->_redundacyCheck($result);
	}

	/**
	* Converts a given decimal string to any base between 2 and 36
	*
	* @param string $decimal
	* @param int $base
	* @return string
	*/
	public function dec2string($decimal, $base) {
		$string = null;
		$base = (int) $base;
		if ($base < 2 | $base > 36 | $base == 10) {
			echo 'BASE must be in the range 2-9 or 11-36';
			exit;
		}

		$charset = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charset = substr($charset, 0, $base);

		do {
			$remainder = bcmod($decimal, $base);
			$char = substr($charset, $remainder, 1);
			$string = $char . $string;
			$decimal = bcdiv(bcsub($decimal, $remainder), $base);
		} while ($decimal > 0);

		return strtolower($string);
	}
}

/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */
/*  AES implementation in PHP (c) Chris Veness 2005-2011. Right of free use is granted for all    */
/*    commercial or non-commercial use under CC-BY licence. No warranty of any form is offered.   */
/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */
  
class Aes {

  /**
   * AES Cipher function: encrypt 'input' with Rijndael algorithm
   *
   * @param input message as byte-array (16 bytes)
   * @param w     key schedule as 2D byte-array (Nr+1 x Nb bytes) - 
   *              generated from the cipher key by keyExpansion()
   * @return      ciphertext as byte-array (16 bytes)
   */
  public static function cipher($input, $w) {    // main cipher function [§5.1]
    $Nb = 4;                 // block size (in words): no of columns in state (fixed at 4 for AES)
    $Nr = count($w)/$Nb - 1; // no of rounds: 10/12/14 for 128/192/256-bit keys
  
    $state = array();  // initialise 4xNb byte-array 'state' with input [§3.4]
    for ($i=0; $i<4*$Nb; $i++) $state[$i%4][floor($i/4)] = $input[$i];
  
    $state = self::addRoundKey($state, $w, 0, $Nb);
  
    for ($round=1; $round<$Nr; $round++) {  // apply Nr rounds
      $state = self::subBytes($state, $Nb);
      $state = self::shiftRows($state, $Nb);
      $state = self::mixColumns($state, $Nb);
      $state = self::addRoundKey($state, $w, $round, $Nb);
    }
  
    $state = self::subBytes($state, $Nb);
    $state = self::shiftRows($state, $Nb);
    $state = self::addRoundKey($state, $w, $Nr, $Nb);
  
    $output = array(4*$Nb);  // convert state to 1-d array before returning [§3.4]
    for ($i=0; $i<4*$Nb; $i++) $output[$i] = $state[$i%4][floor($i/4)];
    return $output;
  }
  
  
  private static function addRoundKey($state, $w, $rnd, $Nb) {  // xor Round Key into state S [§5.1.4]
    for ($r=0; $r<4; $r++) {
      for ($c=0; $c<$Nb; $c++) $state[$r][$c] ^= $w[$rnd*4+$c][$r];
    }
    return $state;
  }
  
  private static function subBytes($s, $Nb) {    // apply SBox to state S [§5.1.1]
    for ($r=0; $r<4; $r++) {
      for ($c=0; $c<$Nb; $c++) $s[$r][$c] = self::$sBox[$s[$r][$c]];
    }
    return $s;
  }
  
  private static function shiftRows($s, $Nb) {    // shift row r of state S left by r bytes [§5.1.2]
    $t = array(4);
    for ($r=1; $r<4; $r++) {
      for ($c=0; $c<4; $c++) $t[$c] = $s[$r][($c+$r)%$Nb];  // shift into temp copy
      for ($c=0; $c<4; $c++) $s[$r][$c] = $t[$c];           // and copy back
    }          // note that this will work for Nb=4,5,6, but not 7,8 (always 4 for AES):
    return $s;  // see fp.gladman.plus.com/cryptography_technology/rijndael/aes.spec.311.pdf 
  }
  
  private static function mixColumns($s, $Nb) {   // combine bytes of each col of state S [§5.1.3]
    for ($c=0; $c<4; $c++) {
      $a = array(4);  // 'a' is a copy of the current column from 's'
      $b = array(4);  // 'b' is a•{02} in GF(2^8)
      for ($i=0; $i<4; $i++) {
        $a[$i] = $s[$i][$c];
        $b[$i] = $s[$i][$c]&0x80 ? $s[$i][$c]<<1 ^ 0x011b : $s[$i][$c]<<1;
      }
      // a[n] ^ b[n] is a•{03} in GF(2^8)
      $s[0][$c] = $b[0] ^ $a[1] ^ $b[1] ^ $a[2] ^ $a[3]; // 2*a0 + 3*a1 + a2 + a3
      $s[1][$c] = $a[0] ^ $b[1] ^ $a[2] ^ $b[2] ^ $a[3]; // a0 * 2*a1 + 3*a2 + a3
      $s[2][$c] = $a[0] ^ $a[1] ^ $b[2] ^ $a[3] ^ $b[3]; // a0 + a1 + 2*a2 + 3*a3
      $s[3][$c] = $a[0] ^ $b[0] ^ $a[1] ^ $a[2] ^ $b[3]; // 3*a0 + a1 + a2 + 2*a3
    }
    return $s;
  }
  
  /**
   * Key expansion for Rijndael cipher(): performs key expansion on cipher key
   * to generate a key schedule
   *
   * @param key cipher key byte-array (16 bytes)
   * @return    key schedule as 2D byte-array (Nr+1 x Nb bytes)
   */
  public static function keyExpansion($key) {  // generate Key Schedule from Cipher Key [§5.2]
    $Nb = 4;              // block size (in words): no of columns in state (fixed at 4 for AES)
    $Nk = count($key)/4;  // key length (in words): 4/6/8 for 128/192/256-bit keys
    $Nr = $Nk + 6;        // no of rounds: 10/12/14 for 128/192/256-bit keys
  
    $w = array();
    $temp = array();
  
    for ($i=0; $i<$Nk; $i++) {
      $r = array($key[4*$i], $key[4*$i+1], $key[4*$i+2], $key[4*$i+3]);
      $w[$i] = $r;
    }
  
    for ($i=$Nk; $i<($Nb*($Nr+1)); $i++) {
      $w[$i] = array();
      for ($t=0; $t<4; $t++) $temp[$t] = $w[$i-1][$t];
      if ($i % $Nk == 0) {
        $temp = self::subWord(self::rotWord($temp));
        for ($t=0; $t<4; $t++) $temp[$t] ^= self::$rCon[$i/$Nk][$t];
      } else if ($Nk > 6 && $i%$Nk == 4) {
        $temp = self::subWord($temp);
      }
      for ($t=0; $t<4; $t++) $w[$i][$t] = $w[$i-$Nk][$t] ^ $temp[$t];
    }
    return $w;
  }
  
  private static function subWord($w) {    // apply SBox to 4-byte word w
    for ($i=0; $i<4; $i++) $w[$i] = self::$sBox[$w[$i]];
    return $w;
  }
  
  private static function rotWord($w) {    // rotate 4-byte word w left by one byte
    $tmp = $w[0];
    for ($i=0; $i<3; $i++) $w[$i] = $w[$i+1];
    $w[3] = $tmp;
    return $w;
  }
  
  // sBox is pre-computed multiplicative inverse in GF(2^8) used in subBytes and keyExpansion [§5.1.1]
  private static $sBox = array(
    0x63,0x7c,0x77,0x7b,0xf2,0x6b,0x6f,0xc5,0x30,0x01,0x67,0x2b,0xfe,0xd7,0xab,0x76,
    0xca,0x82,0xc9,0x7d,0xfa,0x59,0x47,0xf0,0xad,0xd4,0xa2,0xaf,0x9c,0xa4,0x72,0xc0,
    0xb7,0xfd,0x93,0x26,0x36,0x3f,0xf7,0xcc,0x34,0xa5,0xe5,0xf1,0x71,0xd8,0x31,0x15,
    0x04,0xc7,0x23,0xc3,0x18,0x96,0x05,0x9a,0x07,0x12,0x80,0xe2,0xeb,0x27,0xb2,0x75,
    0x09,0x83,0x2c,0x1a,0x1b,0x6e,0x5a,0xa0,0x52,0x3b,0xd6,0xb3,0x29,0xe3,0x2f,0x84,
    0x53,0xd1,0x00,0xed,0x20,0xfc,0xb1,0x5b,0x6a,0xcb,0xbe,0x39,0x4a,0x4c,0x58,0xcf,
    0xd0,0xef,0xaa,0xfb,0x43,0x4d,0x33,0x85,0x45,0xf9,0x02,0x7f,0x50,0x3c,0x9f,0xa8,
    0x51,0xa3,0x40,0x8f,0x92,0x9d,0x38,0xf5,0xbc,0xb6,0xda,0x21,0x10,0xff,0xf3,0xd2,
    0xcd,0x0c,0x13,0xec,0x5f,0x97,0x44,0x17,0xc4,0xa7,0x7e,0x3d,0x64,0x5d,0x19,0x73,
    0x60,0x81,0x4f,0xdc,0x22,0x2a,0x90,0x88,0x46,0xee,0xb8,0x14,0xde,0x5e,0x0b,0xdb,
    0xe0,0x32,0x3a,0x0a,0x49,0x06,0x24,0x5c,0xc2,0xd3,0xac,0x62,0x91,0x95,0xe4,0x79,
    0xe7,0xc8,0x37,0x6d,0x8d,0xd5,0x4e,0xa9,0x6c,0x56,0xf4,0xea,0x65,0x7a,0xae,0x08,
    0xba,0x78,0x25,0x2e,0x1c,0xa6,0xb4,0xc6,0xe8,0xdd,0x74,0x1f,0x4b,0xbd,0x8b,0x8a,
    0x70,0x3e,0xb5,0x66,0x48,0x03,0xf6,0x0e,0x61,0x35,0x57,0xb9,0x86,0xc1,0x1d,0x9e,
    0xe1,0xf8,0x98,0x11,0x69,0xd9,0x8e,0x94,0x9b,0x1e,0x87,0xe9,0xce,0x55,0x28,0xdf,
    0x8c,0xa1,0x89,0x0d,0xbf,0xe6,0x42,0x68,0x41,0x99,0x2d,0x0f,0xb0,0x54,0xbb,0x16);
  
  // rCon is Round Constant used for the Key Expansion [1st col is 2^(r-1) in GF(2^8)] [§5.2]
  private static $rCon = array( 
    array(0x00, 0x00, 0x00, 0x00),
    array(0x01, 0x00, 0x00, 0x00),
    array(0x02, 0x00, 0x00, 0x00),
    array(0x04, 0x00, 0x00, 0x00),
    array(0x08, 0x00, 0x00, 0x00),
    array(0x10, 0x00, 0x00, 0x00),
    array(0x20, 0x00, 0x00, 0x00),
    array(0x40, 0x00, 0x00, 0x00),
    array(0x80, 0x00, 0x00, 0x00),
    array(0x1b, 0x00, 0x00, 0x00),
    array(0x36, 0x00, 0x00, 0x00) ); 

} 
 
/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */
/*  AES counter (CTR) mode implementation in PHP (c) Chris Veness 2005-2011. Right of free use is */
/*    granted for all commercial or non-commercial use under CC-BY licence. No warranty of any    */
/*    form is offered.                                                                            */
/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */
  
class AesCtr extends Aes {
  
  /** 
   * Encrypt a text using AES encryption in Counter mode of operation
   *  - see http://csrc.nist.gov/publications/nistpubs/800-38a/sp800-38a.pdf
   *
   * Unicode multi-byte character safe
   *
   * @param plaintext source text to be encrypted
   * @param password  the password to use to generate a key
   * @param nBits     number of bits to be used in the key (128, 192, or 256)
   * @return          encrypted text
   */
  public static function encrypt($plaintext, $password, $nBits) {
    $blockSize = 16;  // block size fixed at 16 bytes / 128 bits (Nb=4) for AES
    if (!($nBits==128 || $nBits==192 || $nBits==256)) return '';  // standard allows 128/192/256 bit keys
    // note PHP (5) gives us plaintext and password in UTF8 encoding!
    
    // use AES itself to encrypt password to get cipher key (using plain password as source for  
    // key expansion) - gives us well encrypted key
    $nBytes = $nBits/8;  // no bytes in key
    $pwBytes = array();
    for ($i=0; $i<$nBytes; $i++) $pwBytes[$i] = ord(substr($password,$i,1)) & 0xff;
    $key = Aes::cipher($pwBytes, Aes::keyExpansion($pwBytes));
    $key = array_merge($key, array_slice($key, 0, $nBytes-16));  // expand key to 16/24/32 bytes long 
  
    // initialise 1st 8 bytes of counter block with nonce (NIST SP800-38A §B.2): [0-1] = millisec, 
    // [2-3] = random, [4-7] = seconds, giving guaranteed sub-ms uniqueness up to Feb 2106
    $counterBlock = array();
    $nonce = floor(microtime(true)*1000);   // timestamp: milliseconds since 1-Jan-1970
    $nonceMs = $nonce%1000;
    $nonceSec = floor($nonce/1000);
    $nonceRnd = floor(rand(0, 0xffff));
    
    for ($i=0; $i<2; $i++) $counterBlock[$i]   = self::urs($nonceMs,  $i*8) & 0xff;
    for ($i=0; $i<2; $i++) $counterBlock[$i+2] = self::urs($nonceRnd, $i*8) & 0xff;
    for ($i=0; $i<4; $i++) $counterBlock[$i+4] = self::urs($nonceSec, $i*8) & 0xff;
    
    // and convert it to a string to go on the front of the ciphertext
    $ctrTxt = '';
    for ($i=0; $i<8; $i++) $ctrTxt .= chr($counterBlock[$i]);
  
    // generate key schedule - an expansion of the key into distinct Key Rounds for each round
    $keySchedule = Aes::keyExpansion($key);
    //print_r($keySchedule);
    
    $blockCount = ceil(strlen($plaintext)/$blockSize);
    $ciphertxt = array();  // ciphertext as array of strings
    
    for ($b=0; $b<$blockCount; $b++) {
      // set counter (block #) in last 8 bytes of counter block (leaving nonce in 1st 8 bytes)
      // done in two stages for 32-bit ops: using two words allows us to go past 2^32 blocks (68GB)
      for ($c=0; $c<4; $c++) $counterBlock[15-$c] = self::urs($b, $c*8) & 0xff;
      for ($c=0; $c<4; $c++) $counterBlock[15-$c-4] = self::urs($b/0x100000000, $c*8);
  
      $cipherCntr = Aes::cipher($counterBlock, $keySchedule);  // -- encrypt counter block --
  
      // block size is reduced on final block
      $blockLength = $b<$blockCount-1 ? $blockSize : (strlen($plaintext)-1)%$blockSize+1;
      $cipherByte = array();
      
      for ($i=0; $i<$blockLength; $i++) {  // -- xor plaintext with ciphered counter byte-by-byte --
        $cipherByte[$i] = $cipherCntr[$i] ^ ord(substr($plaintext, $b*$blockSize+$i, 1));
        $cipherByte[$i] = chr($cipherByte[$i]);
      }
      $ciphertxt[$b] = implode('', $cipherByte);  // escape troublesome characters in ciphertext
    }
  
    // implode is more efficient than repeated string concatenation
    $ciphertext = $ctrTxt . implode('', $ciphertxt);
    $ciphertext = base64_encode($ciphertext);
    return $ciphertext;
  }
  
  
  /** 
   * Decrypt a text encrypted by AES in counter mode of operation
   *
   * @param ciphertext source text to be decrypted
   * @param password   the password to use to generate a key
   * @param nBits      number of bits to be used in the key (128, 192, or 256)
   * @return           decrypted text
   */
  public static function decrypt($ciphertext, $password, $nBits) {
    $blockSize = 16;  // block size fixed at 16 bytes / 128 bits (Nb=4) for AES
    if (!($nBits==128 || $nBits==192 || $nBits==256)) return '';  // standard allows 128/192/256 bit keys
    $ciphertext = base64_decode($ciphertext);
  
    // use AES to encrypt password (mirroring encrypt routine)
    $nBytes = $nBits/8;  // no bytes in key
    $pwBytes = array();
    for ($i=0; $i<$nBytes; $i++) $pwBytes[$i] = ord(substr($password,$i,1)) & 0xff;
    $key = Aes::cipher($pwBytes, Aes::keyExpansion($pwBytes));
    $key = array_merge($key, array_slice($key, 0, $nBytes-16));  // expand key to 16/24/32 bytes long
    
    // recover nonce from 1st element of ciphertext
    $counterBlock = array();
    $ctrTxt = substr($ciphertext, 0, 8);
    for ($i=0; $i<8; $i++) $counterBlock[$i] = ord(substr($ctrTxt,$i,1));
    
    // generate key schedule
    $keySchedule = Aes::keyExpansion($key);
  
    // separate ciphertext into blocks (skipping past initial 8 bytes)
    $nBlocks = ceil((strlen($ciphertext)-8) / $blockSize);
    $ct = array();
    for ($b=0; $b<$nBlocks; $b++) $ct[$b] = substr($ciphertext, 8+$b*$blockSize, 16);
    $ciphertext = $ct;  // ciphertext is now array of block-length strings
  
    // plaintext will get generated block-by-block into array of block-length strings
    $plaintxt = array();
    
    for ($b=0; $b<$nBlocks; $b++) {
      // set counter (block #) in last 8 bytes of counter block (leaving nonce in 1st 8 bytes)
      for ($c=0; $c<4; $c++) $counterBlock[15-$c] = self::urs($b, $c*8) & 0xff;
      for ($c=0; $c<4; $c++) $counterBlock[15-$c-4] = self::urs(($b+1)/0x100000000-1, $c*8) & 0xff;
  
      $cipherCntr = Aes::cipher($counterBlock, $keySchedule);  // encrypt counter block
  
      $plaintxtByte = array();
      for ($i=0; $i<strlen($ciphertext[$b]); $i++) {
        // -- xor plaintext with ciphered counter byte-by-byte --
        $plaintxtByte[$i] = $cipherCntr[$i] ^ ord(substr($ciphertext[$b],$i,1));
        $plaintxtByte[$i] = chr($plaintxtByte[$i]);
      
      }
      $plaintxt[$b] = implode('', $plaintxtByte); 
    }
  
    // join array of blocks into single plaintext string
    $plaintext = implode('',$plaintxt);
    
    return $plaintext;
  }
  
  
  /*
   * Unsigned right shift function, since PHP has neither >>> operator nor unsigned ints
   *
   * @param a  number to be shifted (32-bit integer)
   * @param b  number of bits to shift a to the right (0..31)
   * @return   a right-shifted and zero-filled by b bits
   */
  private static function urs($a, $b) {
    $a &= 0xffffffff; $b &= 0x1f;  // (bounds check)
    if ($a&0x80000000 && $b>0) {   // if left-most bit set
      $a = ($a>>1) & 0x7fffffff;   //   right-shift one bit & clear left-most bit
      $a = $a >> ($b-1);           //   remaining right-shifts
    } else {                       // otherwise
      $a = ($a>>$b);               //   use normal right-shift
    } 
    return $a; 
  }

}  
