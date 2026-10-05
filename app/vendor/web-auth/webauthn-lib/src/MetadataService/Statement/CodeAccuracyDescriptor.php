<?php

declare(strict_types=1);

namespace Webauthn\MetadataService\Statement;

use Webauthn\Exception\MetadataStatementLoadingException;

class CodeAccuracyDescriptor extends AbstractDescriptor
{
    public function __construct(
        public readonly int $base,
        public readonly int $minLength,
        ?int $maxRetries = null,
        ?int $blockSlowdown = null
    ) {
        $base >= 0 || throw MetadataStatementLoadingException::create(
            'Invalid data. The value of "base" must be a positive integer'
        );
        $minLength >= 0 || throw MetadataStatementLoadingException::create(
            'Invalid data. The value of "minLength" must be a positive integer'
        );
        parent::__construct($maxRetries, $blockSlowdown);
    }

    public static function create(int $base, int $minLength, ?int $maxRetries = null, ?int $blockSlowdown = null): self
    {
        return new self($base, $minLength, $maxRetries, $blockSlowdown);
    }
}
