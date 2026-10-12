<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\CBORObject;
use CBOR\Tag;
use function count;

/**
 * Tag 40: a multi-dimensional array in row-major order, i.e. the last index varies fastest -- the layout C and
 * PHP nested arrays use.
 *
 * @see \CBOR\Test\Tag\TypedArrayTagTest
 * @see https://datatracker.ietf.org/doc/html/rfc8746#section-3.1
 */
final class RowMajorMultiDimensionalArrayTag extends AbstractMultiDimensionalArrayTag
{
    public static function getTagId(): int
    {
        return self::TAG_ROW_MAJOR_MULTI_DIMENSIONAL_ARRAY;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_ROW_MAJOR_MULTI_DIMENSIONAL_ARRAY);

        return new self($ai, $data, $object);
    }

    protected static function getStrides(array $dimensions): array
    {
        $strides = [];
        $stride = 1;
        for ($index = count($dimensions) - 1; $index >= 0; --$index) {
            $strides[$index] = $stride;
            $stride *= $dimensions[$index];
        }

        return $strides;
    }
}
