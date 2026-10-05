<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

/**
 * Tag 77: an array of signed 16 bit integers, little endian.
 */
final class Sint16LittleEndianArrayTag extends AbstractNumericTypedArrayTag
{
    use TypedArrayFactoryTrait;

    public static function getTagId(): int
    {
        return self::TAG_TYPED_ARRAY_SINT16_LE;
    }

    public static function getElementSize(): int
    {
        return 2;
    }

    protected static function decodeElement(string $chunk): int
    {
        return self::signedInteger('v', $chunk, 16);
    }
}
