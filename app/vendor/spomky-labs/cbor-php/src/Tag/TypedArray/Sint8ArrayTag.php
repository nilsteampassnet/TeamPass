<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

/**
 * Tag 72: an array of signed 8 bit integers.
 */
final class Sint8ArrayTag extends AbstractNumericTypedArrayTag
{
    use TypedArrayFactoryTrait;

    public static function getTagId(): int
    {
        return self::TAG_TYPED_ARRAY_SINT8;
    }

    public static function getElementSize(): int
    {
        return 1;
    }

    protected static function decodeElement(string $chunk): int
    {
        return self::integer('c', $chunk);
    }
}
