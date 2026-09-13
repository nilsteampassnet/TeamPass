<?php

declare(strict_types=1);

namespace CBOR\Tag;

use function array_values;
use CBOR\CBORObject;
use CBOR\IndefiniteLengthListObject;
use CBOR\ListObject;
use CBOR\Normalizable;
use CBOR\Tag;
use CBOR\UnsignedIntegerObject;
use function count;
use InvalidArgumentException;
use function is_array;

/**
 * Common behaviour of the two multi-dimensional array tags of RFC 8746: 40 (row-major) and 1040 (column-major).
 *
 * The content is the two element array [dimensions, values], where the values are a flat array -- a plain CBOR
 * array or one of the typed array tags -- and the dimensions say how to fold it. Only the order in which the
 * flat positions are walked separates the two tags, which is what getStrides() describes.
 */
abstract class AbstractMultiDimensionalArrayTag extends Tag implements Normalizable
{
    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        if (! $object instanceof ListObject && ! $object instanceof IndefiniteLengthListObject) {
            throw new InvalidArgumentException('This tag only accepts a List object.');
        }
        if (count($object) !== 2) {
            throw new InvalidArgumentException('This tag only accepts a List object that contains 2 items.');
        }

        $dimensions = $object->get(0);
        if (! $dimensions instanceof ListObject && ! $dimensions instanceof IndefiniteLengthListObject) {
            throw new InvalidArgumentException('Invalid dimensions. Expected a List object.');
        }
        if (count($dimensions) === 0) {
            throw new InvalidArgumentException('Invalid dimensions. Expected at least one dimension.');
        }
        foreach ($dimensions as $dimension) {
            if (! $dimension instanceof UnsignedIntegerObject) {
                throw new InvalidArgumentException('Invalid dimensions. Expected Unsigned Integer objects.');
            }
        }

        $values = $object->get(1);
        // A typed array tag is the other half of RFC 8746 and the reason the values are not always a plain array.
        if (! $values instanceof ListObject && ! $values instanceof IndefiniteLengthListObject && ! ($values instanceof TagInterface && $values instanceof Normalizable)) {
            throw new InvalidArgumentException('Invalid values. Expected a List object or a typed array tag.');
        }

        parent::__construct($additionalInformation, $data, $object);
    }

    /**
     * @return array<int, int> the size of each dimension, outermost first
     */
    public function getDimensions(): array
    {
        /** @var IndefiniteLengthListObject|ListObject $object */
        $object = $this->object;
        /** @var IndefiniteLengthListObject|ListObject $dimensions */
        $dimensions = $object->get(0);

        $result = [];
        foreach ($dimensions as $dimension) {
            /** @var UnsignedIntegerObject $dimension */
            $value = $dimension->normalize();
            $asInteger = (int) $value;
            // A CBOR unsigned integer goes up to 2^64-1; such a dimension could not be built anyway, and
            // silently wrapping it would produce a shape that has nothing to do with the document.
            if ((string) $asInteger !== $value) {
                throw new InvalidArgumentException('Invalid dimensions. The value is too large.');
            }
            $result[] = $asInteger;
        }

        return $result;
    }

    /**
     * @return array<int, mixed> the values as they are laid out in the document, before folding
     */
    public function getFlatValues(): array
    {
        /** @var IndefiniteLengthListObject|ListObject $object */
        $object = $this->object;
        /** @var CBORObject&Normalizable $values */
        $values = $object->get(1);
        $normalized = $values->normalize();
        if (! is_array($normalized)) {
            throw new InvalidArgumentException('Invalid values. Expected a List object or a typed array tag.');
        }

        return array_values($normalized);
    }

    /**
     * @return array<int, mixed> the values folded into as many nested arrays as there are dimensions
     */
    public function normalize(): array
    {
        $dimensions = $this->getDimensions();
        $values = $this->getFlatValues();

        $expected = 1;
        foreach ($dimensions as $dimension) {
            $expected *= $dimension;
            if ($expected > count($values)) {
                throw new InvalidArgumentException('Invalid data. The dimensions do not match the number of values.');
            }
        }
        if ($expected !== count($values)) {
            throw new InvalidArgumentException('Invalid data. The dimensions do not match the number of values.');
        }

        return self::fold($values, $dimensions, static::getStrides($dimensions), 0, 0);
    }

    /**
     * The distance between two consecutive positions along each dimension.
     *
     * @param array<int, int> $dimensions
     *
     * @return array<int, int>
     */
    abstract protected static function getStrides(array $dimensions): array;

    /**
     * @param array<int, mixed> $values
     * @param array<int, int>   $dimensions
     * @param array<int, int>   $strides
     *
     * @return array<int, mixed>
     */
    private static function fold(array $values, array $dimensions, array $strides, int $level, int $offset): array
    {
        $isLast = $level === count($dimensions) - 1;
        $result = [];
        for ($index = 0; $index < $dimensions[$level]; ++$index) {
            $position = $offset + $index * $strides[$level];
            $result[] = $isLast ? $values[$position] : self::fold($values, $dimensions, $strides, $level + 1, $position);
        }

        return $result;
    }
}
