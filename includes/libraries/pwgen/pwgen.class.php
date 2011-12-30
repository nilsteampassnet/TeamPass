<?php
	/**
	 * Port of the famous GNU/Linux Password Generator ("pwgen") to PHP.
	 * This file may be distributed under the terms of the GNU Public License.
	 * Copyright (C) 2001, 2002 by Theodore Ts'o <tytso@alum.mit.edu>
	 * Copyright (C) 2009 by Superwayne <superwayne@superwayne.org>
	 */
	class PWGen {
		// Flags for the pwgen function
		const PW_DIGITS = 0x0001;
		const PW_UPPERS = 0x0002; // At least one upper letter
		const PW_SYMBOLS = 0x0004;
		const PW_AMBIGUOUS = 0x0008;

		// Flags for the pwgen element
		const CONSONANT = 0x0001;
		const VOWEL = 0x0002;
		const DIPHTHONG = 0x0004;
		const NOT_FIRST = 0x0008;
		const NO_VOWELS = 0x0010;

		private $pwgen;
		private $pwgen_flags;
		private $password;
		private $pw_length;

		private static $initialized = false; // static block alread called?
		private static $elements;
		private static $pw_ambiguous;
		private static $pw_symbols;
		private static $pw_digits;
		private static $pw_uppers;
		private static $pw_lowers;
		private static $pw_vowels;

		/**
		 * @param length	Length of the generated password. Default: 8
		 * @param secure	Generate completely random, hard-to-memorize passwords. These should only
		 * 			be used for machine passwords, since otherwise it's almost guaranteed that
		 * 			users will simply write the password on a piece of paper taped to the monitor...
		 * @param numerals	Include at least one number in the password. This is the default.
		 * @param capitalize	Include at least one capital letter in the password. This is the default.
		 * @param ambiguous	Don't use characters that could be confused by the user when printed,
		 * 			such as 'l' and '1', or '0' or 'O'. This reduces the number of possible
		 *			passwords significantly, and as such reduces the quality of the passwords.
		 *			It may be useful for users who have bad vision, but in general use of this
		 *			option is not recommended.
		 * @param no-vowels	Generate random passwords that do not contain vowels or numbers that might be
		 * 			mistaken for vowels. It provides less secure passwords to allow system
		 * 			administrators to not have to worry with random passwords accidentally contain
		 * 			offensive substrings.
		 * @param symbols	Include at least one special character in the password.
		 */
		public function __construct($length=8, $secure=false, $numerals=true, $capitalize=true,
			$ambiguous=false, $no_vovels=false, $symbols=false) {
			self::__static();

			$this->pwgen = 'pw_phonemes';

			$this->setLength($length);
			$this->setSecure($secure);
			$this->setNumerals($numerals);
			$this->setCapitalize($capitalize);
			$this->setAmbiguous($ambiguous);
			$this->setNoVovels($no_vovels);
			$this->setSymbols($symbols);
		}

		/**
		 * Length of the generated password. Default: 8
		 */
		public function setLength($length) {
			if (is_numeric($length) && $length > 0) {
				$this->pw_length = $length;
				if ($this->pw_length < 5) {
					$this->pwgen = 'pw_rand';
				}
				if ($this->pw_length <= 2) {
					$this->setCapitalize(false);
				}
				if ($this->pw_length <= 1) {
					$this->setNumerals(false);
				}
			} else {
				$this->pw_length = 8;
			}
			return $this;
		}

		/**
		 * Generate completely random, hard-to-memorize passwords. These should only used for machine passwords,
		 * since otherwise it's almost guaranteed that users will simply write the password on a piece of paper
		 * taped to the monitor...
		 * Please note that this function implies that you want passwords which include symbols, numerals and
		 * capital letters.
		 */
		public function setSecure($secure) {
			if($secure) {
				$this->pwgen = 'pw_rand';
				$this->setNumerals(true);
				$this->setCapitalize(true);
			} else {
				$this->pwgen = 'pw_phonemes';
			}
			return $this;
		}

		/**
		 * Include at least one number in the password. This is the default.
		 */
		public function setNumerals($numerals) {
			if($numerals) {
				$this->pwgen_flags |= self::PW_DIGITS;
			} else {
				$this->pwgen_flags &= ~self::PW_DIGITS;
			}
			return $this;
		}

		/**
		 * Include at least one capital letter in the password. This is the default.
		 */
		public function setCapitalize($capitalize) {
			if($capitalize) {
				$this->pwgen_flags |= self::PW_UPPERS;
			} else {
				$this->pwgen_flags &= ~self::PW_UPPERS;
			}
			return $this;
		}

		/**
		 * Don't use characters that could be confused by the user when printed, such as 'l' and '1', or '0' or
		 * 'O'. This reduces the number of possible passwords significantly, and as such reduces the quality of
		 * the passwords. It may be useful for users who have bad vision, but in general use of this option is
		 * not recommended.
		 */
		public function setAmbiguous($ambiguous) {
			if($ambiguous) {
				$this->pwgen_flags |= self::PW_AMBIGUOUS;
			} else {
				$this->pwgen_flags &= ~self::PW_AMBIGUOUS;
			}
			return $this;
		}

		/**
		 * Generate random passwords that do not contain vowels or numbers that might be mistaken for vowels. It
		 * provides less secure passwords to allow system administrators to not have to worry with random
		 * passwords accidentally contain offensive substrings.
		 */
		public function setNoVovels($no_vovels) {
			if($no_vovels) {
				$this->pwgen = 'pw_rand';
				$this->pwgen_flags |= self::NO_VOWELS | self::PW_DIGITS | self::PW_UPPERS;
			} else {
				$this->pwgen = 'pw_phonemes';
				$this->pwgen_flags &= ~self::NO_VOWELS;
			}
			return $this;
		}

		public function setSymbols($symbols) {
			if($symbols) {
				$this->pwgen_flags |= self::PW_SYMBOLS;
			} else {
				$this->pwgen_flags &= ~self::PW_SYMBOLS;
			}
			return $this;
		}

		public function generate() {
			if($this->pwgen == 'pw_phonemes') {
				$this->pw_phonemes();
			} else { // $this->pwgen == 'pw_rand'
				$this->pw_rand();
			}
			return $this->password;
		}

		private function pw_phonemes() {
			$this->password = array();

			do {
				$feature_flags = $this->pwgen_flags;
				$c = 0;
				$prev = 0;
				$should_be = self::my_rand(0, 1) ? self::VOWEL : self::CONSONANT;
				$first = 1;

				while ($c < $this->pw_length) {
					$i = self::my_rand(0, count(self::$elements)-1);
					$str = self::$elements[$i]->str;
					$len = strlen($str);
					$flags = self::$elements[$i]->flags;
	
					// Filter on the basic type of the next element
					if (($flags & $should_be) == 0)
						continue;
					// Handle the NOT_FIRST flag
					if ($first && ($flags & self::NOT_FIRST))
						continue;
					// Don't allow VOWEL followed a Vowel/Dipthong pair
					if (($prev & self::VOWEL) && ($flags & self::VOWEL) &&
						($flags & self::DIPHTHONG))
						continue;
					// Don't allow us to overflow the buffer
					if ($len > $this->pw_length-$c)
						continue;
	
					// Handle the AMBIGUOUS flag
					if ($this->pwgen_flags & self::PW_AMBIGUOUS) {
						if (strpbrk($str, self::$pw_ambiguous) !== false)
							continue;
					}
	
					/*
					 * OK, we found an element which matches our criteria,
					 * let's do it!
					 */
					for($j=0; $j < $len; $j++)
						$this->password[$c+$j] = $str[$j];

					// Handle PW_UPPERS
					if ($this->pwgen_flags & self::PW_UPPERS) {
						if (($first || $flags & self::CONSONANT) && (self::my_rand(0, 9) < 2)) {
							$this->password[$c] = strtoupper($this->password[$c]);
							$feature_flags &= ~self::PW_UPPERS;
						}
					}
			
					$c += $len;
			
					// Time to stop?
					if ($c >= $this->pw_length)
						break;
			
					// Handle PW_DIGITS
					if ($this->pwgen_flags & self::PW_DIGITS) {
						if (!$first && (self::my_rand(0, 9) < 3)) {
							do {
								$ch = strval(self::my_rand(0, 9));
							} while (($this->pwgen_flags & self::PW_AMBIGUOUS) &&
								strpos(self::$pw_ambiguous, $ch) !== false);
							$this->password[$c++] = $ch;
							$feature_flags &= ~self::PW_DIGITS;
					
							$first = 1;
							$prev = 0;
							$should_be = self::my_rand(0, 1) ? self::VOWEL : self::CONSONANT;
							continue;
						}
					}
					
					// Handle PW_SYMBOLS
					if ($this->pwgen_flags & self::PW_SYMBOLS) {
						if (!$first && (self::my_rand(0, 9) < 2)) {
							do {
								$ch = self::$pw_symbols[self::my_rand(0,
									strlen(self::$pw_symbols)-1)];
							} while (($this->pwgen_flags & self::PW_AMBIGUOUS) &&
								strpos(self::$pw_ambiguous, $ch) !== false);
							$this->password[$c++] = $ch;
							$feature_flags &= ~self::PW_SYMBOLS;
						}
					}
	
					// OK, figure out what the next element should be
					if ($should_be == self::CONSONANT) {
						$should_be = self::VOWEL;
					} else { // should_be == VOWEL
						if (($prev & self::VOWEL) || ($flags & self::DIPHTHONG) ||
							(self::my_rand(0, 9) > 3))
							$should_be = self::CONSONANT;
						else
							$should_be = self::VOWEL;
					}
					$prev = $flags;
					$first = 0;
				}
			} while ($feature_flags & (self::PW_UPPERS | self::PW_DIGITS | self::PW_SYMBOLS));

			$this->password = implode('', $this->password);
		}

		private function pw_rand() {
			$this->password = array();

        		$chars = '';
			if ($this->pwgen_flags & self::PW_DIGITS) {
				$chars .= self::$pw_digits;
			}
			if ($this->pwgen_flags & self::PW_UPPERS) {
				$chars .= self::$pw_uppers;
			}
			$chars .= self::$pw_lowers;
			if ($this->pwgen_flags & self::PW_SYMBOLS) {
				$chars .= self::$pw_symbols;
			}

			do {
				$len = strlen($chars);
				$feature_flags = $this->pwgen_flags;
				$i = 0;

				while ($i < $this->pw_length) {
					$ch = $chars[self::my_rand(0, $len-1)];
					if (($this->pwgen_flags & self::PW_AMBIGUOUS) &&
						strpos(self::$pw_ambiguous, $ch) !== false)
						continue;
					if (($this->pwgen_flags & self::NO_VOWELS) &&
						strpos(self::$pw_vowels, $ch) !== false)
						continue;
					$this->password[$i++] = $ch;
					if (strpos(self::$pw_digits, $ch) !== false)
						$feature_flags &= ~self::PW_DIGITS;
					if (strpos(self::$pw_uppers, $ch) !== false)
						$feature_flags &= ~self::PW_UPPERS;
					if (strchr(self::$pw_symbols, $ch) !== false)
						$feature_flags &= ~self::PW_SYMBOLS;
				}
			} while ($feature_flags & (self::PW_UPPERS | self::PW_DIGITS | self::PW_SYMBOLS));

			$this->password = implode('', $this->password);
		}

		/**
		 * Generate a random number n, where $min <= n < $max
		 * Mersenne Twister is used as an algorithm
		 */
		public static function my_rand($min=0, $max=0) {
			return mt_rand($min, $max);
		}

		/**
		 * This method initializes all static vars which contain complex datatypes.
		 * It acts somewhat like a static block in Java. Since PHP does not support this principle, the method
		 * is called from the constructor. Because of that you can not access the static vars unless there
		 * exists at least one object of the class.
		 */
		private static function __static() {
			if(!self::$initialized) {
				self::$initialized = true;
				self::$elements = array(
					new PWElement('a', self::VOWEL),
					new PWElement('ae', self::VOWEL | self::DIPHTHONG),
					new PWElement('ah', self::VOWEL | self::DIPHTHONG),
					new PWElement('ai', self::VOWEL | self::DIPHTHONG),
					new PWElement('b', self::CONSONANT),
					new PWElement('c', self::CONSONANT),
					new PWElement('ch', self::CONSONANT | self::DIPHTHONG),
					new PWElement('d', self::CONSONANT),
					new PWElement('e', self::VOWEL),
					new PWElement('ee', self::VOWEL | self::DIPHTHONG),
					new PWElement('ei', self::VOWEL | self::DIPHTHONG),
					new PWElement('f', self::CONSONANT),
					new PWElement('g', self::CONSONANT),
					new PWElement('gh', self::CONSONANT | self::DIPHTHONG | self::NOT_FIRST),
					new PWElement('h', self::CONSONANT),
					new PWElement('i', self::VOWEL),
					new PWElement('ie', self::VOWEL | self::DIPHTHONG),
					new PWElement('j', self::CONSONANT),
					new PWElement('k', self::CONSONANT),
					new PWElement('l', self::CONSONANT),
					new PWElement('m', self::CONSONANT),
					new PWElement('n', self::CONSONANT),
					new PWElement('ng', self::CONSONANT | self::DIPHTHONG | self::NOT_FIRST),
					new PWElement('o', self::VOWEL),
					new PWElement('oh', self::VOWEL | self::DIPHTHONG),
					new PWElement('oo', self::VOWEL | self::DIPHTHONG),
					new PWElement('p', self::CONSONANT),
					new PWElement('ph', self::CONSONANT | self::DIPHTHONG),
					new PWElement('qu', self::CONSONANT | self::DIPHTHONG),
					new PWElement('r', self::CONSONANT),
					new PWElement('s', self::CONSONANT),
					new PWElement('sh', self::CONSONANT | self::DIPHTHONG),
					new PWElement('t', self::CONSONANT),
					new PWElement('th', self::CONSONANT | self::DIPHTHONG),
					new PWElement('u', self::VOWEL),
					new PWElement('v', self::CONSONANT),
					new PWElement('w', self::CONSONANT),
					new PWElement('x', self::CONSONANT),
					new PWElement('y', self::CONSONANT),
					new PWElement('z', self::CONSONANT)
				);
				self::$pw_ambiguous = 'B8G6I1l0OQDS5Z2';
				self::$pw_symbols = "!\"#$%&'()*+,-./:;<=>?@[\\]^_`{|}~";
				self::$pw_digits = '0123456789';
				self::$pw_uppers = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
				self::$pw_lowers = 'abcdefghijklmnopqrstuvwxyz';
				self::$pw_vowels = '01aeiouyAEIOUY';
			}
		}

		/**
		 * Returns the last generated password. If there is none, a new one will be generated.
		 */
		public function __toString() {
			return (empty($this->password) ? $this->generate() : $this->password);
		}

	}

	class PWElement {
		public $str;
		public $flags;	
		
		public function __construct($str, $flags) {
			$this->str = $str;
			$this->flags = $flags;
		}
	}
?>
