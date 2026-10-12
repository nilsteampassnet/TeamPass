<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

/**
 * Tag 75: an array of signed 64 bit integers, big endian.
 */
final class Sint64BigEndianArrayTag extends AbstractNumericTypedArrayTag
{
    use TypedArrayFactoryTrait;

    public static function getTagId(): int
    {
        return self::TAG_TYPED_ARRAY_SINT64_BE;
    }

    public static function getElementSize(): int
    {
        return 8;
    }

    /**
     * A 64 bit two's complement element is exactly what unpack() answers on a 64 bit build: the range of the
     * element is the range of a PHP integer, so nothing has to be corrected.
     */
    protected static function decodeElement(string $chunk): int
    {
        return self::integer('J', $chunk);
    }
}
