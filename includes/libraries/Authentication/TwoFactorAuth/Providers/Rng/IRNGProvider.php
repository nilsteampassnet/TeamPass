<?php

namespace Authentication\TwoFactorAuth\Providers\Rng;

interface IRNGProvider
{
    public function getRandomBytes($bytecount);
    public function isCryptographicallySecure();
}