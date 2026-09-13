<?php

declare(strict_types=1);

namespace Cose\Algorithm\Signature\FullySpecified;

use Cose\Algorithm\Signature\ECDSA\ECDSA;
use Cose\Key\Ec2Key;
use const OPENSSL_ALGO_SHA384;

/**
 * ECDSA using the brainpoolP320r1 curve and SHA-384.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9864.html#section-2.1
 */
final class ESB320 extends ECDSA
{
    public const ID = -266;

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
        return OPENSSL_ALGO_SHA384;
    }

    protected function getCurve(): int
    {
        return Ec2Key::CURVE_BP320;
    }

    protected function getSignaturePartLength(): int
    {
        return 80;
    }
}
