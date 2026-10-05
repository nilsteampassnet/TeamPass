<?php

declare(strict_types=1);

namespace CBOR;

use function strlen;

/**
 * @see \CBOR\Test\ByteStringObjectTest
 */
final class ByteStringObject extends AbstractCBORObject implements Normalizable
{
    private const MAJOR_TYPE = self::MAJOR_TYPE_BYTE_STRING;

    private ?string $length = null;

    private bool $lengthComputed = false;

    public function __construct(
        private string $value
    ) {
        parent::__construct(self::MAJOR_TYPE, 0);
    }

    public function __toString(): string
    {
        $this->computeLength();
        $result = parent::__toString();
        $result .= $this->length ?? '';

        return $result . $this->value;
    }

    public function getAdditionalInformation(): int
    {
        $this->computeLength();

        return parent::getAdditionalInformation();
    }

    public static function create(string $data): self
    {
        return new self($data);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getLength(): int
    {
        return strlen($this->value);
    }

    public function normalize(): string
    {
        return $this->value;
    }

    /**
     * A string object is immutable, so its head never changes -- but decoding never asks for it: the object is built
     * from the payload and read back through normalize(), and only re-encoding calls __toString(). Computing it up
     * front made every decoded string pay for a head nobody reads.
     */
    private function computeLength(): void
    {
        if ($this->lengthComputed) {
            return;
        }

        [$this->additionalInformation, $this->length] = LengthCalculator::getLengthOfString($this->value);
        $this->lengthComputed = true;
    }
}
