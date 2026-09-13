<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

/**
 * Tag 70: an array of unsigned 32 bit integers, little endian.
 */
final class Uint32LittleEndianArrayTag extends AbstractNumericTypedArrayTag
{
    use TypedArrayFactoryTrait;

    public static function getTagId(): int
    {
        return self::TAG_TYPED_ARRAY_UINT32_LE;
    }

    public static function getElementSize(): int
    {
        return 4;
    }

    protected static function decodeElement(string $chunk): int|string
    {
        return self::unsignedInteger('V', $chunk);
    }
}
