<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

/**
 * Tag 64: an array of unsigned 8 bit integers.
 */
final class Uint8ArrayTag extends AbstractNumericTypedArrayTag
{
    use TypedArrayFactoryTrait;

    public static function getTagId(): int
    {
        return self::TAG_TYPED_ARRAY_UINT8;
    }

    public static function getElementSize(): int
    {
        return 1;
    }

    protected static function decodeElement(string $chunk): int|string
    {
        return self::unsignedInteger('C', $chunk);
    }
}
