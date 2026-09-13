<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

/**
 * Tag 68: an array of unsigned 8 bit integers with clamped arithmetic, i.e. the JavaScript Uint8ClampedArray.
 *
 * Clamping describes how a producer turns a wider value into a byte -- 300 becomes 255 rather than 44 -- so it
 * shapes what was written, not how it is read. The elements decode exactly as tag 64.
 */
final class Uint8ClampedArrayTag extends AbstractNumericTypedArrayTag
{
    use TypedArrayFactoryTrait;

    public static function getTagId(): int
    {
        return self::TAG_TYPED_ARRAY_UINT8_CLAMPED;
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
