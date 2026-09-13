<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

/**
 * Tag 87: an array of IEEE 754 binary128 values, little endian.
 *
 * PHP has no quadruple precision float and no way to build one out of the bytes without losing what makes it
 * one, so this tag carries the elements rather than converting them: getChunks() hands back the 16 bytes of
 * each, for a caller that knows what to do with them. That is also why it is not Normalizable -- returning a
 * binary64 here would quietly drop 15 digits of precision.
 */
final class Float128LittleEndianArrayTag extends AbstractTypedArrayTag
{
    use TypedArrayFactoryTrait;

    public static function getTagId(): int
    {
        return self::TAG_TYPED_ARRAY_FLOAT128_LE;
    }

    public static function getElementSize(): int
    {
        return 16;
    }
}
