<?php
namespace Authentication\GoogleAuthenticator;
/**
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


class GoogleAuthenticator
{
    protected $passCodeLength;
    protected $secretLength;
    protected $pinModulo;
    protected $fixBitNotation;

    /**
     * @param int $passCodeLength
     * @param int $secretLength
     */
    public function __construct($passCodeLength = 6, $secretLength = 10)
    {
        $this->passCodeLength = $passCodeLength;
        $this->secretLength   = $secretLength;
        $this->pinModulo      = pow(10, $this->passCodeLength);
    }

    /**
     * @param $secret
     * @param $code
     * @return bool
     */
    public function checkCode($secret, $code)
    {
        $time = floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) {
            if ($this->getCode($secret, $time + $i) == $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $secret
     * @param  null   $time
     * @return string
     */
    public function getCode($secret, $time = null)
    {
        if (!$time) {
            $time = floor(time() / 30);
        }

        $base32 = new FixedBitNotation(5, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', TRUE, TRUE);
        $secret = $base32->decode($secret);

        $time = pack("N", $time);
        $time = str_pad($time, 8, chr(0), STR_PAD_LEFT);

        $hash = hash_hmac('sha1', $time, $secret, true);
        $offset = ord(substr($hash, -1));
        $offset = $offset & 0xF;

        $truncatedHash = self::hashToInt($hash, $offset) & 0x7FFFFFFF;
        $pinValue = str_pad($truncatedHash % $this->pinModulo, 6, "0", STR_PAD_LEFT);

        return $pinValue;
    }

    /**
     * @param $bytes
     * @param $start
     * @return integer
     */
    protected static function hashToInt($bytes, $start)
    {
        $input = substr($bytes, $start, strlen($bytes) - $start);
        $val2 = unpack("N", substr($input, 0, 4));

        return $val2[1];
    }

    /**
     * @param  string $user
     * @param  string $hostname
     * @param  string $secret
     * @return string
     */
    public function getUrl($user, $hostname, $secret)
    {
        $encoder = "http://www.google.com/chart?chs=200x200&chld=M|0&cht=qr&chl=";
        $encoderURL = sprintf("%sotpauth://totp/%s@%s%%3Fsecret%%3D%s", $encoder, $user, $hostname, $secret);

        return $encoderURL;
    }

    /**
     * @return string
     */
    public function generateSecret()
    {
        $secret = "";
        for ($i = 1; $i <= $this->secretLength; $i++) {
            $c = rand(0, 255);
            $secret .= pack("c", $c);
        }

        $base32 = new FixedBitNotation(5, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', TRUE, TRUE);

        return $base32->encode($secret);
    }
}
