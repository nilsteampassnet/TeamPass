<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

/**
 * Tag 73: an array of signed 16 bit integers, big endian.
 */
final class Sint16BigEndianArrayTag extends AbstractNumericTypedArrayTag
{
    use TypedArrayFactoryTrait;

    public static function getTagId(): int
    {
        return self::TAG_TYPED_ARRAY_SINT16_BE;
    }

    public static function getElementSize(): int
    {
        return 2;
    }

    protected static function decodeElement(string $chunk): int
    {
        return self::signedInteger('n', $chunk, 16);
    }
}
