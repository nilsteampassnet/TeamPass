<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

/**
 * Tag 67: an array of unsigned 64 bit integers, big endian.
 *
 * Half of that range is beyond PHP_INT_MAX, so an element above it comes back as a decimal string.
 */
final class Uint64BigEndianArrayTag extends AbstractNumericTypedArrayTag
{
    use TypedArrayFactoryTrait;

    public static function getTagId(): int
    {
        return self::TAG_TYPED_ARRAY_UINT64_BE;
    }

    public static function getElementSize(): int
    {
        return 8;
    }

    protected static function decodeElement(string $chunk): int|string
    {
        return self::unsignedInteger('J', $chunk);
    }
}
