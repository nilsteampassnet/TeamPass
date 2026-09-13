<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

/**
 * Tag 86: an array of IEEE 754 binary64 values, little endian.
 */
final class Float64LittleEndianArrayTag extends AbstractNumericTypedArrayTag
{
    use TypedArrayFactoryTrait;

    public static function getTagId(): int
    {
        return self::TAG_TYPED_ARRAY_FLOAT64_LE;
    }

    public static function getElementSize(): int
    {
        return 8;
    }

    protected static function decodeElement(string $chunk): float
    {
        return self::float('e', $chunk);
    }
}
