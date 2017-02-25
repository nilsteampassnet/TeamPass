<?php

namespace Authentication\TwoFactorAuth;

require_once(dirname(__FILE__)."/Providers/Qr/GoogleQRCodeProvider.php");
require_once(dirname(__FILE__)."/Providers/Qr/IQRCodeProvider.php");
require_once(dirname(__FILE__)."/Providers/Rng/CSRNGProvider.php");
require_once(dirname(__FILE__)."/Providers/Rng/IRNGProvider.php");

// Based on / inspired by: https://github.com/PHPGangsta/GoogleAuthenticator
// Algorithms, digits, period etc. explained: https://github.com/google/google-authenticator/wiki/Key-Uri-Format
class TwoFactorAuth
{
    private $algorithm;
    private $period;
    private $digits;
    private $issuer;
    private $qrcodeprovider;
    private $rngprovider;
    private static $_base32dict = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567=';
    private static $_base32;
    private static $_base32lookup = array();
    private static $_supportedalgos = array('sha1', 'sha256', 'sha512', 'md5');

    function __construct($issuer = null, $digits = 6, $period = 30, $algorithm = 'sha1', $qrcodeprovider = null, $rngprovider = null)
    {
        $this->issuer = $issuer;

        if (!is_int($digits) || $digits <= 0)
            throw new TwoFactorAuthException('Digits must be int > 0');
        $this->digits = $digits;

        if (!is_int($period) || $period <= 0)
            throw new TwoFactorAuthException('Period must be int > 0');
        $this->period = $period;

        $algorithm = strtolower(trim($algorithm));
        if (!in_array($algorithm, self::$_supportedalgos))
            throw new TwoFactorAuthException('Unsupported algorithm: ' . $algorithm);
        $this->algorithm = $algorithm;

        // Set default QR Code provider if none was specified
        if ($qrcodeprovider==null)
            $qrcodeprovider = new Providers\Qr\GoogleQRCodeProvider();

        if (!($qrcodeprovider instanceof Providers\Qr\IQRCodeProvider))
            throw new TwoFactorAuthException('QRCodeProvider must implement IQRCodeProvider');

        $this->qrcodeprovider = $qrcodeprovider;

        // Try to find best available RNG provider if none was specified
        if ($rngprovider==null) {
            if (function_exists('random_bytes')) {
                $rngprovider = new Providers\Rng\CSRNGProvider();
            } elseif (function_exists('mcrypt_create_iv')) {
                $rngprovider = new Providers\Rng\MCryptRNGProvider();
            } elseif (function_exists('openssl_random_pseudo_bytes')) {
                $rngprovider = new Providers\Rng\OpenSSLRNGProvider();
            } elseif (function_exists('hash')) {
                $rngprovider = new Providers\Rng\HashRNGProvider();
            } else {
                throw new TwoFactorAuthException('Unable to find a suited RNGProvider');
            }
        }

        if (!($rngprovider instanceof Providers\Rng\IRNGProvider))
            throw new TwoFactorAuthException('RNGProvider must implement IRNGProvider');

        $this->rngprovider = $rngprovider;

        self::$_base32 = str_split(self::$_base32dict);
        self::$_base32lookup = array_flip(self::$_base32);
    }

    /**
     * Create a new secret
     */
    public function createSecret($bits = 80, $requirecryptosecure = true)
    {
        $secret = '';
        $bytes = ceil($bits / 5);   //We use 5 bits of each byte (since we have a 32-character 'alphabet' / BASE32)
        if ($requirecryptosecure && !$this->rngprovider->isCryptographicallySecure())
            throw new TwoFactorAuthException('RNG provider is not cryptographically secure');
        $rnd = $this->rngprovider->getRandomBytes($bytes);
        for ($i = 0; $i < $bytes; $i++)
            $secret .= self::$_base32[ord($rnd[$i]) & 31];  //Mask out left 3 bits for 0-31 values
        return $secret;
    }

    /**
     * Calculate the code with given secret and point in time
     */
    public function getCode($secret, $time = null)
    {
        $secretkey = $this->base32Decode($secret);

        $timestamp = "\0\0\0\0" . pack('N*', $this->getTimeSlice($this->getTime($time)));  // Pack time into binary string
        $hashhmac = hash_hmac($this->algorithm, $timestamp, $secretkey, true);             // Hash it with users secret key
        $hashpart = substr($hashhmac, ord(substr($hashhmac, -1)) & 0x0F, 4);               // Use last nibble of result as index/offset and grab 4 bytes of the result
        $value = unpack('N', $hashpart);                                                   // Unpack binary value
        $value = $value[1] & 0x7FFFFFFF;                                                   // Drop MSB, keep only 31 bits

        return str_pad($value % pow(10, $this->digits), $this->digits, '0', STR_PAD_LEFT);
    }

    /**
     * Check if the code is correct. This will accept codes starting from ($discrepancy * $period) sec ago to ($discrepancy * period) sec from now
     */
    public function verifyCode($secret, $code, $discrepancy = 1, $time = null)
    {
        $result = false;
        $timetamp = $this->getTime($time);

        // To keep safe from timing-attachs we iterate *all* possible codes even though we already may have verified a code is correct
        for ($i = -$discrepancy; $i <= $discrepancy; $i++)
            $result |= $this->codeEquals($this->getCode($secret, $timetamp + ($i * $this->period)), $code);

        return (bool)$result;
    }

    /**
     * Timing-attack safe comparison of 2 codes (see http://blog.ircmaxell.com/2014/11/its-all-about-time.html)
     */
    private function codeEquals($safe, $user) {
        if (function_exists('hash_equals')) {
            return hash_equals($safe, $user);
        } else {
            // In general, it's not possible to prevent length leaks. So it's OK to leak the length. The important part is that
            // we don't leak information about the difference of the two strings.
            if (strlen($safe)===strlen($user)) {
                $result = 0;
                for ($i = 0; $i < strlen($safe); $i++)
                    $result |= (ord($safe[$i]) ^ ord($user[$i]));
                return $result === 0;
            }
        }
        return false;
    }

    /**
     * Get data-uri of QRCode
     */
    public function getQRCodeImageAsDataUri($label, $secret, $size = 200)
    {
        if (!is_int($size) || $size <= 0)
            throw new TwoFactorAuthException('Size must be int > 0');

        return 'data:'
            . $this->qrcodeprovider->getMimeType()
            . ';base64,'
            . base64_encode($this->qrcodeprovider->getQRCodeImage($this->getQRText($label, $secret), $size));
    }

    private function getTime($time)
    {
        return ($time === null) ? time() : $time;
    }

    private function getTimeSlice($time = null, $offset = 0)
    {
        return (int)floor($time / $this->period) + ($offset * $this->period);
    }

    /**
     * Builds a string to be encoded in a QR code
     */
    public function getQRText($label, $secret)
    {
        return 'otpauth://totp/' . rawurlencode($label)
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($this->issuer)
            . '&period=' . intval($this->period)
            . '&algorithm=' . rawurlencode(strtoupper($this->algorithm))
            . '&digits=' . intval($this->digits);
    }

    private function base32Decode($value)
    {
        if (strlen($value)==0) return '';

        if (preg_match('/[^'.preg_quote(self::$_base32dict).']/', $value) !== 0)
            throw new TwoFactorAuthException('Invalid base32 string');

        $buffer = '';
        foreach (str_split($value) as $char)
        {
            if ($char !== '=')
                $buffer .= str_pad(decbin(self::$_base32lookup[$char]), 5, 0, STR_PAD_LEFT);
        }
        $length = strlen($buffer);
        $blocks = trim(chunk_split(substr($buffer, 0, $length - ($length % 8)), 8, ' '));

        $output = '';
        foreach (explode(' ', $blocks) as $block)
            $output .= chr(bindec(str_pad($block, 8, 0, STR_PAD_RIGHT)));

        return $output;
    }
}
