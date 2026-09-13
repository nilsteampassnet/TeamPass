<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\ASN1\Type\Primitive;

use Brick\Math\BigInteger;

/**
 * Implements *ENUMERATED* type.
 */
final class Enumerated extends Integer
{
    public static function create(BigInteger|int|string $number): static
    {
        return new static($number, self::TYPE_ENUMERATED);
    }

    // decoding is inherited from Integer: it already binds create() late, and duplicating it here let a
    // zero-length ENUMERATED bypass the content octet check and surface as an InvalidArgumentException.
}
