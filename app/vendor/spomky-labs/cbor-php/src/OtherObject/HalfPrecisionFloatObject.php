<?php

declare(strict_types=1);

namespace CBOR\OtherObject;

use CBOR\Normalizable;
use CBOR\OtherObject as Base;
use const INF;
use InvalidArgumentException;
use const NAN;
use function ord;
use function strlen;

final class HalfPrecisionFloatObject extends Base implements Normalizable
{
    use FloatBitsTrait;

    public static function supportedAdditionalInformation(): array
    {
        return [self::OBJECT_HALF_PRECISION_FLOAT];
    }

    /**
     * Encode a PHP float as an IEEE 754 binary16 value: 1 sign bit, 5 exponent bits (bias 15) and 10 mantissa bits.
     *
     * The conversion reads the binary64 representation of the argument directly and rounds to nearest, ties to even,
     * as IEEE 754 mandates. Going through binary32 first would round twice and would therefore be off by one unit in
     * the last place for the values that sit exactly halfway between two binary16 neighbours.
     */
    public static function createFromFloat(float $number): self
    {
        if (is_nan($number)) {
            // RFC 8949: canonical NaN is 0xf97e00 (quiet NaN with zero payload)
            return self::createFromBits(0x7E00);
        }

        // abs() and comparisons cannot tell -0.0 from 0.0, so every bit is read from the packed representation.
        $packed = pack('E', $number);
        $signBit = (ord($packed[0]) & 0x80) === 0 ? 0 : 1 << 15;

        if (is_infinite($number)) {
            return self::createFromBits($signBit | 0x7C00);
        }

        // Magnitude of the binary64 value: 11 exponent bits (bias 1023) followed by 52 mantissa bits.
        $bits = ord($packed[0]) & 0x7F;
        for ($index = 1; $index < 8; $index++) {
            $bits = ($bits << 8) | ord($packed[$index]);
        }
        $doubleExponent = $bits >> 52;
        $doubleMantissa = $bits & 0x000FFFFFFFFFFFFF;

        // Zero, or a subnormal binary64 below 2^-1022, which is far under half of the smallest binary16 subnormal.
        if ($doubleExponent === 0) {
            return self::createFromBits($signBit);
        }

        // Significand with its implicit leading bit: abs($number) === $significand * 2 ** ($doubleExponent - 1075).
        $significand = $doubleMantissa | (1 << 52);

        // Exponent field the value would carry as a normal binary16 number.
        $halfExponent = $doubleExponent - 1023 + 15;

        // Too large for binary16, before rounding is even considered.
        if ($halfExponent >= 0x1F) {
            return self::createFromBits($signBit | 0x7C00);
        }

        if ($halfExponent > 0) {
            // Normal: keep 11 significant bits (the implicit one plus 10) out of the 53 the significand holds.
            $halfSignificand = self::roundToNearestEven($significand, 42);
            if ($halfSignificand === 0x800) {
                // Rounding carried out of the mantissa and into the exponent.
                $halfSignificand >>= 1;
                $halfExponent++;
                if ($halfExponent >= 0x1F) {
                    return self::createFromBits($signBit | 0x7C00);
                }
            }

            return self::createFromBits($signBit | ($halfExponent << 10) | ($halfSignificand & 0x3FF));
        }

        // Subnormal: express the value as a multiple of 2^-24, the smallest binary16 step.
        $shift = 43 - $halfExponent;
        if ($shift > 53) {
            // Strictly below half of the smallest subnormal, so it rounds to zero.
            return self::createFromBits($signBit);
        }

        // A carry out of the 10 mantissa bits spills into the exponent field, which yields the smallest normal.
        return self::createFromBits($signBit | self::roundToNearestEven($significand, $shift));
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data): Base
    {
        return new self($additionalInformation, $data);
    }

    public static function create(string $value): self
    {
        if (strlen($value) !== 2) {
            throw new InvalidArgumentException('The value is not a valid half precision floating point');
        }

        return new self(self::OBJECT_HALF_PRECISION_FLOAT, $value);
    }

    public function normalize(): float
    {
        // PHP has no native binary16, so the value is still assembled by hand -- but from the raw bits rather than
        // from three brick/math round trips.
        $bits = $this->bits('n');
        $exponent = $bits >> 10 & 0b11111;
        $mantissa = $bits & 0b1111111111;
        $sign = ($bits >> 15 & 1) === 1 ? -1 : 1;

        if ($exponent === 0) {
            $val = $mantissa * 2 ** (-24);
        } elseif ($exponent !== 0b11111) {
            $val = ($mantissa + (1 << 10)) * 2 ** ($exponent - 25);
        } else {
            $val = $mantissa === 0 ? INF : NAN;
        }

        return (float) ($sign * $val);
    }

    public function getExponent(): int
    {
        return $this->bits('n') >> 10 & 0b11111;
    }

    public function getMantissa(): int
    {
        return $this->bits('n') & 0b1111111111;
    }

    public function getSign(): int
    {
        return ($this->bits('n') >> 15 & 1) === 1 ? -1 : 1;
    }

    private static function createFromBits(int $bits): self
    {
        return new self(self::OBJECT_HALF_PRECISION_FLOAT, pack('n', $bits));
    }

    /**
     * Drop the $shift lowest bits of $significand, rounding to nearest and breaking ties towards the even value.
     */
    private static function roundToNearestEven(int $significand, int $shift): int
    {
        $kept = $significand >> $shift;
        $dropped = $significand & ((1 << $shift) - 1);
        $halfway = 1 << ($shift - 1);

        if ($dropped > $halfway || ($dropped === $halfway && ($kept & 1) === 1)) {
            $kept++;
        }

        return $kept;
    }
}
