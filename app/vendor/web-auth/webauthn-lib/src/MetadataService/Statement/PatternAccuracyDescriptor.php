<?php

declare(strict_types=1);

namespace Webauthn\MetadataService\Statement;

use Webauthn\Exception\MetadataStatementLoadingException;

class PatternAccuracyDescriptor extends AbstractDescriptor
{
    public function __construct(
        public readonly int $minComplexity,
        ?int $maxRetries = null,
        ?int $blockSlowdown = null
    ) {
        $minComplexity >= 0 || throw MetadataStatementLoadingException::create(
            'Invalid data. The value of "minComplexity" must be a positive integer'
        );
        parent::__construct($maxRetries, $blockSlowdown);
    }

    public static function create(int $minComplexity, ?int $maxRetries = null, ?int $blockSlowdown = null): self
    {
        return new self($minComplexity, $maxRetries, $blockSlowdown);
    }
}
