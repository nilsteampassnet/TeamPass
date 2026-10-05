<?php

declare(strict_types=1);

namespace Cose\Algorithm\Signature\FullySpecified;

use Cose\Algorithm\Signature\EdDSA\EdDSA;

/**
 * EdDSA using the Ed25519 parameter set of RFC 8032, section 5.1.
 *
 * This is the fully-specified counterpart of the polymorphic EdDSA identifier (-8). The cryptography is identical;
 * only the algorithm identifier differs.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9864.html#section-2.2
 */
final class Ed25519 extends EdDSA
{
    public const ID = -19;

    public static function create(): self
    {
        return new self();
    }

    public static function identifier(): int
    {
        return self::ID;
    }
}
