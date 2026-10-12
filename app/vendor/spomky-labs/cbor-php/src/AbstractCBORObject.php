<?php

declare(strict_types=1);

namespace CBOR;

use function chr;

abstract class AbstractCBORObject implements CBORObject
{
    public function __construct(
        private int $majorType,
        protected int $additionalInformation
    ) {
    }

    public function __toString(): string
    {
        // A CBOR head is a single byte: three bits of major type followed by five of additional information. The
        // mask is what chr() already applies to an out-of-range codepoint, and it lets the byte be typed as one.
        return chr(($this->majorType << 5 | $this->additionalInformation) & 0xFF);
    }

    public function getMajorType(): int
    {
        return $this->majorType;
    }

    public function getAdditionalInformation(): int
    {
        return $this->additionalInformation;
    }
}
