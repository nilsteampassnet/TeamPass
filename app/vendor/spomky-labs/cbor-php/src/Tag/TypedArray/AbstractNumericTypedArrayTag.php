<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

use function array_map;
use CBOR\Normalizable;
use CBOR\OtherObject\HalfPrecisionFloatObject;
use InvalidArgumentException;
use function is_float;
use function is_int;
use function sprintf;
use function unpack;

/**
 * A typed array whose elements PHP can represent, i.e. every one of RFC 8746 but the binary128 pair.
 */
abstract class AbstractNumericTypedArrayTag extends AbstractTypedArrayTag implements Normalizable
{
    /**
     * @return array<int, float|int|string> the elements, in the order they are stored
     */
    public function normalize(): array
    {
        return array_map(
            static fn (string $chunk) => static::decodeElement($chunk),
            $this->getChunks()
        );
    }

    abstract protected static function decodeElement(string $chunk): float|int|string;

    /**
     * @return int|string the value, as a decimal string when it is too large for a PHP integer -- which happens
     *                    for the upper half of a uint64, and for a uint32 on a 32 bit build
     */
    protected static function unsignedInteger(string $format, string $chunk): int|string
    {
        $value = (int) self::unpackOne($format, $chunk);
        // The bits are right, only their reading as a signed integer is not: %u prints them as the unsigned
        // number they stand for, at whatever width this build of PHP uses.
        return $value < 0 ? sprintf('%u', $value) : $value;
    }

    /**
     * Reads an element that unpack() already returns as the signed integer it stands for.
     */
    protected static function integer(string $format, string $chunk): int
    {
        return (int) self::unpackOne($format, $chunk);
    }

    /**
     * Reads an element that unpack() can only return unsigned, and that means a two's complement signed number
     * of $bits bits. Only widths narrower than a PHP integer go through here: at 64 bits the shifts below would
     * overflow, and unpack() needs no help anyway.
     */
    protected static function signedInteger(string $format, string $chunk, int $bits): int
    {
        $value = (int) self::unpackOne($format, $chunk);
        $limit = 1 << ($bits - 1);

        return $value >= $limit ? $value - (1 << $bits) : $value;
    }

    protected static function float(string $format, string $chunk): float
    {
        return (float) self::unpackOne($format, $chunk);
    }

    /**
     * PHP has no binary16 type and no unpack() format for it, so the conversion of the simple value that carries
     * one is reused: the two bytes are the same in both places.
     */
    protected static function halfPrecisionFloat(string $chunk): float
    {
        return HalfPrecisionFloatObject::create($chunk)
            ->normalize();
    }

    private static function unpackOne(string $format, string $chunk): float|int
    {
        $unpacked = unpack($format, $chunk);
        $value = $unpacked === false ? null : ($unpacked[1] ?? null);
        if (! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException('Invalid data. The element cannot be decoded.');
        }

        return $value;
    }
}
