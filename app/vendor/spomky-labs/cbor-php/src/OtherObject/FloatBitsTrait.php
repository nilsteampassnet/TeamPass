<?php

declare(strict_types=1);

namespace CBOR\OtherObject;

use InvalidArgumentException;
use function is_float;
use function is_int;

/**
 * Reads the IEEE 754 payload of a float object as native machine values.
 *
 * CBOR stores floats big-endian, which is exactly what unpack() consumes: "E" and "G" hand back the binary64 and
 * binary32 values themselves, and "J", "N" and "n" the raw bit pattern the sign, exponent and mantissa accessors sit
 * on. Both used to go through brick/math, which cost six BigInteger allocations for every float normalized.
 *
 * @internal
 */
trait FloatBitsTrait
{
    /**
     * The payload read as the floating point value it encodes.
     */
    private function value(string $format): float
    {
        return (float) $this->unpackPayload($format);
    }

    /**
     * The payload read as its raw bit pattern, big-endian.
     */
    private function bits(string $format): int
    {
        return (int) $this->unpackPayload($format);
    }

    private function unpackPayload(string $format): int|float
    {
        $data = $this->data;
        if ($data === null) {
            throw new InvalidArgumentException('Invalid data');
        }

        $unpacked = unpack($format, $data);
        if ($unpacked === false) {
            throw new InvalidArgumentException('Invalid data');
        }

        $value = $unpacked[1];
        if (! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException('Invalid data');
        }

        return $value;
    }
}
