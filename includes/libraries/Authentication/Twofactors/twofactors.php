<?php
//namespace Authentication\TwoFactors;

/**
 * @file          twofactors.php
 * @author        Nils Laumaillé
 * @version       2.1.25
 * @copyright     (c) 2009-2015 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

class Google2FA
{
    const KEYREGENERATION = 30;    // Interval between key regeneration
    const OTPLENGTH = 6;    // Length of the Token generated

    private static $lut = array(   // Lookup needed for Base32 encoding
        "A" => 0,    "B" => 1,
        "C" => 2,    "D" => 3,
        "E" => 4,    "F" => 5,
        "G" => 6,    "H" => 7,
        "I" => 8,    "J" => 9,
        "K" => 10,    "L" => 11,
        "M" => 12,    "N" => 13,
        "O" => 14,    "P" => 15,
        "Q" => 16,    "R" => 17,
        "S" => 18,    "T" => 19,
        "U" => 20,    "V" => 21,
        "W" => 22,    "X" => 23,
        "Y" => 24,    "Z" => 25,
        "2" => 26,    "3" => 27,
        "4" => 28,    "5" => 29,
        "6" => 30,    "7" => 31
    );

    /**
     * Generates a 16 digit secret key in base32 format
     * @return string
     **/
    public function generate_secret_key($length = 16)
    {
        $b32     = "234567QWERTYUIOPASDFGHJKLZXCVBNM";
        $s     = "";

        for ($i = 0; $i < $length; $i++) {
            $s .= $b32[rand(0, 31)];
        }

        return $s;
    }

    /**
     * Returns the current Unix Timestamp devided by the KEYREGENERATION
     * period.
     * @return integer
     **/
    public function get_timestamp()
    {
        return floor(microtime(true)/self::KEYREGENERATION);
    }

    /**
     * Decodes a base32 string into a binary string.
     **/
    public function base32_decode($b32)
    {
        $b32     = strtoupper($b32);

        if (!preg_match('/^[ABCDEFGHIJKLMNOPQRSTUVWXYZ234567]+$/', $b32, $match)) {
            throw new Exception('Invalid characters in the base32 string.');
        }

        $l     = strlen($b32);
        $n    = 0;
        $j    = 0;
        $binary = "";

        for ($i = 0; $i < $l; $i++) {

            $n = $n << 5;                 // Move buffer left by 5 to make room
            $n = $n + self::$lut[$b32[$i]];     // Add value into buffer
            $j = $j + 5;                // Keep track of number of bits in buffer

            if ($j >= 8) {
                $j = $j - 8;
                $binary .= chr(($n & (0xFF << $j)) >> $j);
            }
        }

        return $binary;
    }

    /**
     * Takes the secret key and the timestamp and returns the one time
     * password.
     *
     * @param binary $key - Secret key in binary form.
     * @param integer $counter - Timestamp as returned by get_timestamp.
     * @return string
     **/
    public function oath_hotp($key, $counter)
    {
        if (strlen($key) < 8)
        throw new Exception('Secret key is too short. Must be at least 16 base 32 characters');

        $bin_counter = pack('N*', 0) . pack('N*', $counter);        // Counter must be 64-bit int
        $hash      = hash_hmac('sha1', $bin_counter, $key, true);

        return str_pad(self::oath_truncate($hash), self::OTPLENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Verifys a user inputted key against the current timestamp. Checks $window
     * keys either side of the timestamp.
     *
     * @param string $b32seed
     * @param string $key - User specified key
     * @param integer $window
     * @param boolean $useTimeStamp
     * @return boolean
     **/
    public function verify_key($b32seed, $key, $window = 4, $useTimeStamp = true)
    {

        $timeStamp = self::get_timestamp();

        if ($useTimeStamp !== true) {
            $timeStamp = (int)$useTimeStamp;
        }

        $binarySeed = self::base32_decode($b32seed);

        for ($ts = $timeStamp - $window; $ts <= $timeStamp + $window; $ts++) {
            if (self::oath_hotp($binarySeed, $ts) == $key) {
                return true;
            }
        }

        return false;

    }

    /**
     * Extracts the OTP from the SHA1 hash.
     * @param binary $hash
     * @return integer
     **/
    public function oath_truncate($hash)
    {
        $offset = ord($hash[19]) & 0xf;

        return (
            ((ord($hash[$offset+0]) & 0x7f) << 24) |
            ((ord($hash[$offset+1]) & 0xff) << 16) |
            ((ord($hash[$offset+2]) & 0xff) << 8) |
            (ord($hash[$offset+3]) & 0xff)
        ) % pow(10, self::OTPLENGTH);
    }

    public function get_qr_code_url($username, $secret)
    {
        $h              =   100;
        return  'http://chart.apis.google.com/chart?chs=' . $h . 'x' . $h .
                '&chld=M|0&cht=qr&chl=' . $secret;
    }

    public function get_qr_code($username, $secret)
    {
        if (false === $secret) {
            return false;
        }
        $url        =   self::get_qr_code_url($username, $secret);
        $curl_handle    =   curl_init();
        $headers    =   array('Expect:');
        $options    =   array(
            CURLOPT_URL     =>  $url,
            CURLOPT_CONNECTTIMEOUT  =>  2,
            CURLOPT_RETURNTRANSFER  =>  1,
            CURLOPT_USERAGENT   =>  'My-Google-Auth',
            CURLOPT_HTTPHEADER  =>  $headers
        );
        curl_setopt_array($curl_handle, $options);

        $query  =   curl_exec($curl_handle);
        curl_close($curl_handle);

        $base_64=   chunk_split(base64_encode($query));

        return '<img class="google_qrcode" src="data:image/gif;base64,' . $base_64 . '" alt="QR Code" />';
    }
}
