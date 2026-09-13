<?php

declare(strict_types=1);

namespace Webauthn\MetadataService\Service;

class MetadataBLOBPayload
{
    /**
     * @param MetadataBLOBPayloadEntry[] $entries
     */
    public function __construct(
        public readonly int $no,
        public readonly string $nextUpdate,
        public readonly ?string $legalHeader = null,
        public array $entries = [],
    ) {
    }
}
