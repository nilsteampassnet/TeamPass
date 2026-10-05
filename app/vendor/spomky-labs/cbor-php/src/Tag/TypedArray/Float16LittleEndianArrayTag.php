<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

use function strrev;

/**
 * Tag 84: an array of IEEE 754 binary16 values, little endian.
 */
final class Float16LittleEndianArrayTag extends AbstractNumericTypedArrayTag
{
    use TypedArrayFactoryTrait;

    public static function getTagId(): int
    {
        return self::TAG_TYPED_ARRAY_FLOAT16_LE;
    }

    public static function getElementSize(): int
    {
        return 2;
    }

    protected static function decodeElement(string $chunk): float
    {
        return self::halfPrecisionFloat(strrev($chunk));
    }
}
