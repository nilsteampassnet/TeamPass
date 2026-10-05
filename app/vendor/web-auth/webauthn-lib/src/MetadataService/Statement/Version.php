<?php

declare(strict_types=1);

namespace Webauthn\MetadataService\Statement;

use Webauthn\Exception\MetadataStatementLoadingException;

readonly class Version
{
    public function __construct(
        public ?int $major,
        public ?int $minor
    ) {
        if ($major === null && $minor === null) {
            throw MetadataStatementLoadingException::create('Invalid data. Must contain at least one item');
        }
        $major >= 0 || throw MetadataStatementLoadingException::create('Invalid argument "major"');
        $minor >= 0 || throw MetadataStatementLoadingException::create('Invalid argument "minor"');
    }

    public static function create(?int $major, ?int $minor): self
    {
        return new self($major, $minor);
    }
}
