<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

use CBOR\ByteStringObject;
use CBOR\CBORObject;
use CBOR\IndefiniteLengthByteStringObject;
use CBOR\Tag;
use function count;
use Countable;
use InvalidArgumentException;
use function sprintf;
use function str_split;

/**
 * Common behaviour of the typed array tags of RFC 8746, i.e. tags 64 to 87.
 *
 * A typed array is a byte string read as a sequence of numbers of one fixed width and one fixed byte order. It
 * is what an array of tag 2 bignums or of doubles would be, minus the head each of those items would carry: a
 * megabyte of samples travels as a megabyte, not as three.
 *
 * The tag number alone gives the element type, so the byte string only has to be a whole number of elements
 * long. That is the one thing checked here; the elements themselves are decoded on demand.
 *
 * @see \CBOR\Test\Tag\TypedArrayTagTest
 * @see https://datatracker.ietf.org/doc/html/rfc8746
 *
 * @phpstan-consistent-constructor
 */
abstract class AbstractTypedArrayTag extends Tag implements Countable
{
    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        if (! $object instanceof ByteStringObject && ! $object instanceof IndefiniteLengthByteStringObject) {
            throw new InvalidArgumentException('This tag only accepts a Byte String object.');
        }
        $size = static::getElementSize();
        if ($object->getLength() % $size !== 0) {
            throw new InvalidArgumentException(
                sprintf('This tag only accepts a Byte String object whose length is a multiple of %d.', $size)
            );
        }

        parent::__construct($additionalInformation, $data, $object);
    }

    /**
     * @return int<1, max> the width of one element, in bytes
     */
    abstract public static function getElementSize(): int;

    public function count(): int
    {
        return count($this->getChunks());
    }

    /**
     * @return array<int, string> the raw bytes of each element, in the order they are stored
     */
    public function getChunks(): array
    {
        /** @var ByteStringObject|IndefiniteLengthByteStringObject $object */
        $object = $this->object;
        $value = $object->getValue();
        if ($value === '') {
            // str_split() answers [''] rather than [] for an empty string before PHP 8.2.
            return [];
        }

        return str_split($value, static::getElementSize());
    }
}
