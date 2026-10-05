<?php

declare(strict_types=1);

namespace Webauthn\MetadataService\Statement;

use Webauthn\Exception\MetadataStatementLoadingException;

readonly class RgbPaletteEntry
{
    public function __construct(
        public int $r,
        public int $g,
        public int $b,
    ) {
        ($r >= 0 && $r <= 255) || throw MetadataStatementLoadingException::create('The key "r" is invalid');
        ($g >= 0 && $g <= 255) || throw MetadataStatementLoadingException::create('The key "g" is invalid');
        ($b >= 0 && $b <= 255) || throw MetadataStatementLoadingException::create('The key "b" is invalid');
    }

    public static function create(int $r, int $g, int $b): self
    {
        return new self($r, $g, $b);
    }
}
