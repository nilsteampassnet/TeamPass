<?php

declare(strict_types=1);

namespace Webauthn\MetadataService\Statement;

readonly class RogueListEntry
{
    public function __construct(
        public string $sk,
        public string $date
    ) {
    }

    public static function create(string $sk, string $date): self
    {
        return new self($sk, $date);
    }
}
