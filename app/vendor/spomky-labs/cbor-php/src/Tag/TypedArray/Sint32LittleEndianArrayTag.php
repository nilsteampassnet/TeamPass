<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

/**
 * Tag 78: an array of signed 32 bit integers, little endian.
 */
final class Sint32LittleEndianArrayTag extends AbstractNumericTypedArrayTag
{
    use TypedArrayFactoryTrait;

    public static function getTagId(): int
    {
        return self::TAG_TYPED_ARRAY_SINT32_LE;
    }

    public static function getElementSize(): int
    {
        return 4;
    }

    protected static function decodeElement(string $chunk): int
    {
        return self::signedInteger('V', $chunk, 32);
    }
}
