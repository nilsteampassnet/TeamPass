<?php

declare(strict_types=1);

namespace CBOR;

use CBOR\Tag\TagInterface;
use function chr;
use InvalidArgumentException;

/**
 * @phpstan-consistent-constructor
 */
abstract class Tag extends AbstractCBORObject implements TagInterface
{
    private const MAJOR_TYPE = self::MAJOR_TYPE_TAG;

    public function __construct(
        int $additionalInformation,
        protected ?string $data,
        protected CBORObject $object
    ) {
        parent::__construct(self::MAJOR_TYPE, $additionalInformation);
    }

    public function __toString(): string
    {
        $result = parent::__toString();
        if ($this->data !== null) {
            $result .= $this->data;
        }

        return $result . $this->object;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function getValue(): CBORObject
    {
        return $this->object;
    }

    /**
     * A tag number is the argument of a major type 6 head, so RFC 8949 section 3 gives it the same five encodings as
     * any other argument and section 4.2 requires the shortest one that holds it. The bounds are therefore inclusive
     * and each payload is packed to the exact width its additional information announces: 255 is the largest
     * one-byte tag number, not the first two-byte one, and tag 256 is "d9 0100", never a single byte.
     *
     * @return array{int, null|string}
     */
    protected static function determineComponents(int $tag): array
    {
        if ($tag < 0) {
            throw new InvalidArgumentException('The value must be a positive integer.');
        }

        return match (true) {
            $tag <= 23 => [$tag, null],
            $tag <= 0xFF => [self::LENGTH_1_BYTE, chr($tag)],
            $tag <= 0xFFFF => [self::LENGTH_2_BYTES, pack('n', $tag)],
            $tag <= 0xFFFFFFFF => [self::LENGTH_4_BYTES, pack('N', $tag)],
            default => [self::LENGTH_8_BYTES, pack('J', $tag)],
        };
    }
}
