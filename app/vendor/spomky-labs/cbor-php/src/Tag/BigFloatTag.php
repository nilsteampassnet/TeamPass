<?php

declare(strict_types=1);

namespace CBOR\Tag;

use Brick\Math\BigInteger;
use CBOR\CBORObject;
use CBOR\IndefiniteLengthListObject;
use CBOR\ListObject;
use CBOR\NegativeIntegerObject;
use CBOR\Normalizable;
use CBOR\Tag;
use CBOR\UnsignedIntegerObject;
use function count;
use function extension_loaded;
use InvalidArgumentException;
use RuntimeException;
use function sprintf;
use function str_contains;

final class BigFloatTag extends Tag implements Normalizable
{
    /**
     * The maximum absolute value accepted for the exponent.
     *
     * 2^e needs about e/3 decimal digits to write down and 2^-e exactly e of them, so the exponent alone decides how
     * much memory normalizing one item costs -- and the exponent is three bytes on the wire. At 8192 a six byte item
     * expanded to more than eight kilobytes, an amplification of over a thousand, and a document made of such items
     * exhausted the memory limit inside decode() with a fatal error no try/catch can intercept. The bound is now the
     * range of an IEEE 754 double, which is what a big float is meant to exceed in precision, not in magnitude.
     */
    public const MAX_ABSOLUTE_EXPONENT = 1024;

    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        if (! extension_loaded('bcmath')) {
            throw new RuntimeException('The extension "bcmath" is required to use this tag');
        }

        $isList = $object instanceof ListObject || $object instanceof IndefiniteLengthListObject;
        if (! $isList || count($object) !== 2) {
            throw new InvalidArgumentException(
                'This tag only accepts a ListObject object that contains an exponent and a mantissa.'
            );
        }
        $e = $object->get(0);
        if (! $e instanceof UnsignedIntegerObject && ! $e instanceof NegativeIntegerObject) {
            throw new InvalidArgumentException('The exponent must be a Signed Integer or an Unsigned Integer object.');
        }
        $m = $object->get(1);
        if (! $m instanceof UnsignedIntegerObject && ! $m instanceof NegativeIntegerObject && ! $m instanceof NegativeBigIntegerTag && ! $m instanceof UnsignedBigIntegerTag) {
            throw new InvalidArgumentException(
                'The mantissa must be a Positive or Negative Signed Integer or an Unsigned Integer object.'
            );
        }

        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_BIG_FLOAT;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): self
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_BIG_FLOAT);

        return new self($ai, $data, $object);
    }

    public static function createFromExponentAndMantissa(CBORObject $e, CBORObject $m): self
    {
        $object = ListObject::create()
            ->add($e)
            ->add($m)
        ;

        return self::create($object);
    }

    /**
     * Create a BigFloat from a PHP float value.
     * BigFloat represents: mantissa × 2^exponent
     * This method converts a float to the most compact BigFloat representation.
     *
     * @param float $value The float value to convert
     * @return self The BigFloat tag object
     */
    public static function createFromFloat(float $value): self
    {
        if (! extension_loaded('bcmath')) {
            throw new RuntimeException('The extension "bcmath" is required to use this method');
        }

        // Handle special cases
        if (is_nan($value) || is_infinite($value)) {
            throw new InvalidArgumentException('BigFloat cannot represent NaN or Infinity');
        }

        if ($value === 0.0) {
            // 0 = 0 × 2^0
            return self::createFromExponentAndMantissa(
                UnsignedIntegerObject::create(0),
                UnsignedIntegerObject::create(0)
            );
        }

        // Get the binary representation
        $negative = $value < 0;
        $absValue = abs($value);

        // Convert to binary string representation
        // We'll use the fact that PHP floats are IEEE 754 double precision
        // Extract mantissa and exponent from the float
        $packed = pack('d', $absValue);
        if (unpack('S', "\x01\x00")[1] === 1) {
            $packed = strrev($packed); // Little-endian system
        }
        $bits = unpack('J', $packed)[1];

        $biasedExponent = ($bits >> 52) & 0x7FF;
        $fraction = $bits & 0xFFFFFFFFFFFFF;

        if ($biasedExponent === 0) {
            // Subnormal number
            $exponent = -1022 - 52;
            $mantissa = $fraction;
        } else {
            // Normal number
            $exponent = $biasedExponent - 1023 - 52;
            $mantissa = $fraction | (1 << 52); // Add implicit leading 1
        }

        // Apply sign
        if ($negative) {
            $mantissa = -$mantissa;
        }

        // Normalize: remove trailing zeros from mantissa by adjusting exponent
        while ($mantissa !== 0 && ($mantissa & 1) === 0) {
            $mantissa >>= 1;
            $exponent++;
        }

        // Create exponent object
        if ($exponent >= 0) {
            $exponentObj = UnsignedIntegerObject::create($exponent);
        } else {
            $exponentObj = NegativeIntegerObject::create($exponent);
        }

        // Create mantissa object
        if ($mantissa >= 0) {
            $mantissaObj = UnsignedIntegerObject::createFromString((string) $mantissa);
        } else {
            $mantissaObj = NegativeIntegerObject::createFromString((string) $mantissa);
        }

        return self::createFromExponentAndMantissa($exponentObj, $mantissaObj);
    }

    public function normalize()
    {
        /** @var ListObject|IndefiniteLengthListObject $object */
        $object = $this->object;
        /** @var UnsignedIntegerObject|NegativeIntegerObject $e */
        $e = $object->get(0);
        /** @var UnsignedIntegerObject|NegativeIntegerObject|NegativeBigIntegerTag|UnsignedBigIntegerTag $m */
        $m = $object->get(1);

        $exponent = (string) $e->normalize();
        self::assertExponentIsWithinBounds($exponent);
        $mantissa = (string) $m->normalize();

        // The exponent is bounded, so it fits a PHP integer and the scale below is finite.
        $exponentValue = (int) $exponent;
        if ($exponentValue >= 0) {
            // m x 2^e with e >= 0 is an integer: no scale is needed and none shall be printed.
            return bcmul($mantissa, bcpow('2', $exponent, 0), 0);
        }

        // 2^-s is 5^s x 10^-s, so the exact quotient has s decimal digits and that scale loses nothing. A fixed
        // scale would either truncate the result to zero or, worse, report a rounded value as the decoded one.
        $scale = -$exponentValue;

        return self::stripTrailingZeros(bcdiv($mantissa, bcpow('2', (string) $scale, 0), $scale));
    }

    /**
     * bcdiv() pads the result up to the requested scale, so the exact value comes back with trailing zeros and,
     * once they are gone, a dangling decimal point. Both are stripped, but only from a value that has a decimal
     * point at all: trimming "512" would turn it into "51".
     */
    private static function stripTrailingZeros(string $value): string
    {
        if (! str_contains($value, '.')) {
            return $value;
        }

        return rtrim(rtrim($value, '0'), '.');
    }

    private static function assertExponentIsWithinBounds(string $exponent): void
    {
        if (BigInteger::of($exponent)->abs()->isGreaterThan(BigInteger::of(self::MAX_ABSOLUTE_EXPONENT))) {
            throw new InvalidArgumentException(sprintf(
                'The exponent is out of range. Its absolute value shall not exceed %d, got "%s".',
                self::MAX_ABSOLUTE_EXPONENT,
                $exponent
            ));
        }
    }
}
