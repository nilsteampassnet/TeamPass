<?php

declare(strict_types=1);

namespace Webauthn\TrustPath;

final readonly class EmptyTrustPath implements TrustPath
{
    public static function create(): self
    {
        return new self();
    }
}
