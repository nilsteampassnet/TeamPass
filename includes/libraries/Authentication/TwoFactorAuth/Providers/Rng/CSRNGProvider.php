<?php

namespace Authentication\TwoFactorAuth\Providers\Rng;
require_once(dirname(__FILE__)."/IRNGProvider.php");

class CSRNGProvider implements IRNGProvider
{
    public function getRandomBytes($bytecount) {
        return random_bytes($bytecount);    // PHP7+
    }

    public function isCryptographicallySecure() {
        return true;
    }
}