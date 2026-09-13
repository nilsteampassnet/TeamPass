<?php

declare(strict_types=1);

namespace Webauthn\Event;

use Webauthn\MetadataService\Statement\MetadataStatement;

final readonly class MetadataStatementFound implements WebauthnEvent
{
    public function __construct(
        public MetadataStatement $metadataStatement
    ) {
    }

    public static function create(MetadataStatement $metadataStatement): self
    {
        return new self($metadataStatement);
    }
}
