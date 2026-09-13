<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

/**
 * Tag 82: an array of IEEE 754 binary64 values, big endian.
 */
final class Float64BigEndianArrayTag extends AbstractNumericTypedArrayTag
{
    use TypedArrayFactoryTrait;

    public static function getTagId(): int
    {
        return self::TAG_TYPED_ARRAY_FLOAT64_BE;
    }

    public static function getElementSize(): int
    {
        return 8;
    }

    protected static function decodeElement(string $chunk): float
    {
        return self::float('E', $chunk);
    }
}
