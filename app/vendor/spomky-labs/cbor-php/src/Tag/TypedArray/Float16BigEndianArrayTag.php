<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

/**
 * Tag 80: an array of IEEE 754 binary16 values, big endian.
 */
final class Float16BigEndianArrayTag extends AbstractNumericTypedArrayTag
{
    use TypedArrayFactoryTrait;

    public static function getTagId(): int
    {
        return self::TAG_TYPED_ARRAY_FLOAT16_BE;
    }

    public static function getElementSize(): int
    {
        return 2;
    }

    protected static function decodeElement(string $chunk): float
    {
        return self::halfPrecisionFloat($chunk);
    }
}
