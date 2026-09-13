<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

/**
 * Tag 85: an array of IEEE 754 binary32 values, little endian.
 */
final class Float32LittleEndianArrayTag extends AbstractNumericTypedArrayTag
{
    use TypedArrayFactoryTrait;

    public static function getTagId(): int
    {
        return self::TAG_TYPED_ARRAY_FLOAT32_LE;
    }

    public static function getElementSize(): int
    {
        return 4;
    }

    protected static function decodeElement(string $chunk): float
    {
        return self::float('g', $chunk);
    }
}
