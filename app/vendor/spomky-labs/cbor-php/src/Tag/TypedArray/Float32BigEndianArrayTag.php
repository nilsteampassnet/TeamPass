<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

/**
 * Tag 81: an array of IEEE 754 binary32 values, big endian.
 */
final class Float32BigEndianArrayTag extends AbstractNumericTypedArrayTag
{
    use TypedArrayFactoryTrait;

    public static function getTagId(): int
    {
        return self::TAG_TYPED_ARRAY_FLOAT32_BE;
    }

    public static function getElementSize(): int
    {
        return 4;
    }

    protected static function decodeElement(string $chunk): float
    {
        return self::float('G', $chunk);
    }
}
