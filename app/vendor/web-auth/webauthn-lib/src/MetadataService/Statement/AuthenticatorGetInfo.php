<?php

declare(strict_types=1);

namespace Webauthn\MetadataService\Statement;

class AuthenticatorGetInfo
{
    /**
     * @param array<array-key, mixed> $info
     */
    public function __construct(
        public array $info = []
    ) {
    }

    /**
     * @param array<array-key, mixed> $info
     */
    public static function create(array $info = []): self
    {
        return new self($info);
    }
}
