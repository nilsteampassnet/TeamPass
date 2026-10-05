<?php

declare(strict_types=1);

namespace Cose\Algorithm\Signature\RSA;

use Cose\Key\RsaKeyValidator;
use const OPENSSL_ALGO_SHA256;

final class RS256 extends RSA
{
    public const ID = -257;

    public static function create(?RsaKeyValidator $keyValidator = null): self
    {
        return new self($keyValidator);
    }

    public static function identifier(): int
    {
        return self::ID;
    }

    protected function getHashAlgorithm(): int
    {
        return OPENSSL_ALGO_SHA256;
    }
}
