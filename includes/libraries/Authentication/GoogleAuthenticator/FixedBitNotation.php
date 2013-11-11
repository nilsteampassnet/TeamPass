<?php
namespace Authentication\GoogleAuthenticator;
/**
* FixedBitNotation
*
* @author Andre DeMarre
* @package FixedBitNotation
*
* The FixedBitNotation class is for binary to text conversion. It
* can handle many encoding schemes, formally defined or not, that
* use a fixed number of bits to encode each character.
*
* @package FixedBitNotation
*/
class FixedBitNotation
{
    protected $_chars;
    protected $_bitsPerCharacter;
    protected $_radix;
    protected $_rightPadFinalBits;
    protected $_padFinalGroup;
    protected $_padCharacter;
    protected $_charmap;

    /**
    * Constructor
    *
    * @param integer $bitsPerCharacter Bits to use for each encoded
    *                character
    * @param string  $chars Base character alphabet
    * @param boolean $rightPadFinalBits How to encode last character
    * @param boolean $padFinalGroup Add padding to end of encoded
    *                output
    * @param string  $padCharacter Character to use for padding
    */
    public function __construct($bitsPerCharacter, $chars = NULL, $rightPadFinalBits = FALSE, $padFinalGroup = FALSE, $padCharacter = '=')
    {
        // Ensure validity of $chars
        if (!is_string($chars) || ($charLength = strlen($chars)) < 2) {
            $chars =
            '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-,';
            $charLength = 64;
        }

        // Ensure validity of $bitsPerCharacter
        if ($bitsPerCharacter < 1) {
            // $bitsPerCharacter must be at least 1
            $bitsPerCharacter = 1;
            $radix = 2;
        } elseif ($charLength < 1 << $bitsPerCharacter) {
            // Character length of $chars is too small for $bitsPerCharacter
            // Set $bitsPerCharacter to greatest acceptable value
            $bitsPerCharacter = 1;
            $radix = 2;

            while ($charLength >= ($radix <<= 1) && $bitsPerCharacter < 8) {
                $bitsPerCharacter++;
            }

            $radix >>= 1;

        } elseif ($bitsPerCharacter > 8) {
            // $bitsPerCharacter must not be greater than 8
            $bitsPerCharacter = 8;
            $radix = 256;

        } else {
            $radix = 1 << $bitsPerCharacter;
        }

        $this->_chars = $chars;
        $this->_bitsPerCharacter = $bitsPerCharacter;
        $this->_radix = $radix;
        $this->_rightPadFinalBits = $rightPadFinalBits;
        $this->_padFinalGroup = $padFinalGroup;
        $this->_padCharacter = $padCharacter[0];
    }

    /**
    * Encode a string
    *
    * @param  string $rawString Binary data to encode
    * @return string
    */
    public function encode($rawString)
    {
        // Unpack string into an array of bytes
        $bytes = unpack('C*', $rawString);
        $byteCount = count($bytes);

        $encodedString = '';
        $byte = array_shift($bytes);
        $bitsRead = 0;

        $chars = $this->_chars;
        $bitsPerCharacter = $this->_bitsPerCharacter;
        $rightPadFinalBits = $this->_rightPadFinalBits;
        $padFinalGroup = $this->_padFinalGroup;
        $padCharacter = $this->_padCharacter;

        // Generate encoded output;
        // each loop produces one encoded character
        for ($c = 0; $c < $byteCount * 8 / $bitsPerCharacter; $c++) {

            // Get the bits needed for this encoded character
            if ($bitsRead + $bitsPerCharacter > 8) {
                // Not enough bits remain in this byte for the current
                // character
                // Save the remaining bits before getting the next byte
                $oldBitCount = 8 - $bitsRead;
                $oldBits = $byte ^ ($byte >> $oldBitCount << $oldBitCount);
                $newBitCount = $bitsPerCharacter - $oldBitCount;

                if (!$bytes) {
                    // Last bits; match final character and exit loop
                    if ($rightPadFinalBits) $oldBits <<= $newBitCount;
                    $encodedString .= $chars[$oldBits];

                    if ($padFinalGroup) {
                        // Array of the lowest common multiples of
                        // $bitsPerCharacter and 8, divided by 8
                        $lcmMap = array(1 => 1, 2 => 1, 3 => 3, 4 => 1,
                        5 => 5, 6 => 3, 7 => 7, 8 => 1);
                        $bytesPerGroup = $lcmMap[$bitsPerCharacter];
                        $pads = $bytesPerGroup * 8 / $bitsPerCharacter
                        - ceil((strlen($rawString) % $bytesPerGroup)
                        * 8 / $bitsPerCharacter);
                        $encodedString .= str_repeat($padCharacter[0], $pads);
                    }

                    break;
                }

                // Get next byte
                $byte = array_shift($bytes);
                $bitsRead = 0;

            } else {
                $oldBitCount = 0;
                $newBitCount = $bitsPerCharacter;
            }

            // Read only the needed bits from this byte
            $bits = $byte >> 8 - ($bitsRead + ($newBitCount));
            $bits ^= $bits >> $newBitCount << $newBitCount;
            $bitsRead += $newBitCount;

            if ($oldBitCount) {
                // Bits come from seperate bytes, add $oldBits to $bits
                $bits = ($oldBits << $newBitCount) | $bits;
            }

            $encodedString .= $chars[$bits];
        }

        return $encodedString;
    }

    /**
    * Decode a string
    *
    * @param  string  $encodedString Data to decode
    * @param  boolean $caseSensitive
    * @param  boolean $strict Returns NULL if $encodedString contains
    *                 an undecodable character
    * @return string|NULL
    */
    public function decode($encodedString, $caseSensitive = TRUE, $strict = FALSE)
    {
        if (!$encodedString || !is_string($encodedString)) {
            // Empty string, nothing to decode
            return '';
        }

        $chars = $this->_chars;
        $bitsPerCharacter = $this->_bitsPerCharacter;
        $radix = $this->_radix;
        $rightPadFinalBits = $this->_rightPadFinalBits;
        $padFinalGroup = $this->_padFinalGroup;
        $padCharacter = $this->_padCharacter;

        // Get index of encoded characters
        if ($this->_charmap) {
            $charmap = $this->_charmap;

        } else {
            $charmap = array();

            for ($i = 0; $i < $radix; $i++) {
                $charmap[$chars[$i]] = $i;
            }

            $this->_charmap = $charmap;
        }

        // The last encoded character is $encodedString[$lastNotatedIndex]
        $lastNotatedIndex = strlen($encodedString) - 1;

        // Remove trailing padding characters
        while ($encodedString[$lastNotatedIndex] == $padCharacter[0]) {
            $encodedString = substr($encodedString, 0, $lastNotatedIndex);
            $lastNotatedIndex--;
        }

        $rawString = '';
        $byte = 0;
        $bitsWritten = 0;

        // Convert each encoded character to a series of unencoded bits
        for ($c = 0; $c <= $lastNotatedIndex; $c++) {

            if (!isset($charmap[$encodedString[$c]]) && !$caseSensitive) {
                // Encoded character was not found; try other case
                if (isset($charmap[$cUpper
                = strtoupper($encodedString[$c])])) {
                    $charmap[$encodedString[$c]] = $charmap[$cUpper];

                } elseif (isset($charmap[$cLower
                = strtolower($encodedString[$c])])) {
                    $charmap[$encodedString[$c]] = $charmap[$cLower];
                }
            }

            if (isset($charmap[$encodedString[$c]])) {
                $bitsNeeded = 8 - $bitsWritten;
                $unusedBitCount = $bitsPerCharacter - $bitsNeeded;

                // Get the new bits ready
                if ($bitsNeeded > $bitsPerCharacter) {
                    // New bits aren't enough to complete a byte; shift them
                    // left into position
                    $newBits = $charmap[$encodedString[$c]] << $bitsNeeded
                    - $bitsPerCharacter;
                    $bitsWritten += $bitsPerCharacter;

                } elseif ($c != $lastNotatedIndex || $rightPadFinalBits) {
                    // Zero or more too many bits to complete a byte;
                    // shift right
                    $newBits = $charmap[$encodedString[$c]] >> $unusedBitCount;
                    $bitsWritten = 8; //$bitsWritten += $bitsNeeded;

                } else {
                    // Final bits don't need to be shifted
                    $newBits = $charmap[$encodedString[$c]];
                    $bitsWritten = 8;
                }

                $byte |= $newBits;

                if ($bitsWritten == 8 || $c == $lastNotatedIndex) {
                    // Byte is ready to be written
                    $rawString .= pack('C', $byte);

                    if ($c != $lastNotatedIndex) {
                        // Start the next byte
                        $bitsWritten = $unusedBitCount;
                        $byte = ($charmap[$encodedString[$c]]
                        ^ ($newBits << $unusedBitCount)) << 8 - $bitsWritten;
                    }
                }

            } elseif ($strict) {
                // Unable to decode character; abort
                return NULL;
            }
        }

        return $rawString;
    }
}
