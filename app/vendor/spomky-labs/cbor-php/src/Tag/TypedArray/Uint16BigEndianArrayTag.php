<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

/**
 * Tag 65: an array of unsigned 16 bit integers, big endian.
 */
final class Uint16BigEndianArrayTag extends AbstractNumericTypedArrayTag
{
    use TypedArrayFactoryTrait;

    public static function getTagId(): int
    {
        return self::TAG_TYPED_ARRAY_UINT16_BE;
    }

    public static function getElementSize(): int
    {
        return 2;
    }

    protected static function decodeElement(string $chunk): int|string
    {
        return self::unsignedInteger('n', $chunk);
    }
}
