<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

/**
 * Tag 74: an array of signed 32 bit integers, big endian.
 */
final class Sint32BigEndianArrayTag extends AbstractNumericTypedArrayTag
{
    use TypedArrayFactoryTrait;

    public static function getTagId(): int
    {
        return self::TAG_TYPED_ARRAY_SINT32_BE;
    }

    public static function getElementSize(): int
    {
        return 4;
    }

    protected static function decodeElement(string $chunk): int
    {
        return self::signedInteger('N', $chunk, 32);
    }
}
