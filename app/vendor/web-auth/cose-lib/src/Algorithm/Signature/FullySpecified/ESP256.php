<?php

declare(strict_types=1);

namespace Cose\Algorithm\Signature\FullySpecified;

use Cose\Algorithm\Signature\ECDSA\ECDSA;
use Cose\Key\Ec2Key;
use const OPENSSL_ALGO_SHA256;

/**
 * ECDSA using the P-256 curve and SHA-256.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9864.html#section-2.1
 */
final class ESP256 extends ECDSA
{
    public const ID = -9;

    public static function create(): self
    {
        return new self();
    }

    public static function identifier(): int
    {
        return self::ID;
    }

    protected function getHashAlgorithm(): int
    {
        return OPENSSL_ALGO_SHA256;
    }

    protected function getCurve(): int
    {
        return Ec2Key::CURVE_P256;
    }

    protected function getSignaturePartLength(): int
    {
        return 64;
    }
}
