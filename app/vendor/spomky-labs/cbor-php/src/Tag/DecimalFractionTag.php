<?php

declare(strict_types=1);

namespace CBOR\Tag;

use Brick\Math\BigInteger;
use CBOR\ByteStringObject;
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
use function str_starts_with;
use function strlen;

final class DecimalFractionTag extends Tag implements Normalizable
{
    /**
     * The maximum absolute value accepted for the exponent.
     *
     * 10^e needs about e decimal digits to write down, so the exponent alone decides how much memory normalizing
     * one item costs -- and the exponent is three bytes on the wire. At 8192 a six byte item expanded to more than
     * eight kilobytes, an amplification of over a thousand, and a document made of such items exhausted the memory
     * limit inside decode() with a fatal error no try/catch can intercept. The bound is now the range of an IEEE
     * 754 double, which is what a decimal fraction is meant to exceed in precision, not in magnitude.
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

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_DECIMAL_FRACTION);

        return new self($ai, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_DECIMAL_FRACTION;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): self
    {
        return new self($additionalInformation, $data, $object);
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
     * Create a DecimalFraction from a PHP float value.
     * DecimalFraction represents: mantissa × 10^exponent
     * This method converts a float to a decimal representation with minimal precision loss.
     *
     * @param float $value The float value to convert
     * @param int $precision Maximum number of decimal places (default: 10)
     * @return self The DecimalFraction tag object
     */
    public static function createFromFloat(float $value, int $precision = 10): self
    {
        if (! extension_loaded('bcmath')) {
            throw new RuntimeException('The extension "bcmath" is required to use this method');
        }

        if ($precision < 0) {
            throw new InvalidArgumentException('Precision must be non-negative');
        }

        // Handle special cases
        if (is_nan($value) || is_infinite($value)) {
            throw new InvalidArgumentException('DecimalFraction cannot represent NaN or Infinity');
        }

        if ($value === 0.0) {
            // 0 = 0 × 10^0
            return self::createFromExponentAndMantissa(
                UnsignedIntegerObject::create(0),
                UnsignedIntegerObject::create(0)
            );
        }

        // Convert float to string with appropriate precision
        // We use sprintf to get a decimal representation
        $str = sprintf("%.{$precision}F", $value);

        // Remove trailing zeros after decimal point
        if (str_contains($str, '.')) {
            $str = rtrim($str, '0');
            $str = rtrim($str, '.');
        }

        // Split into integer and fractional parts
        $parts = explode('.', $str);
        $integerPart = $parts[0];
        $fractionalPart = $parts[1] ?? '';

        // Calculate exponent (negative = decimal places)
        $exponent = -strlen($fractionalPart);

        // Keep the sign aside: it is not a digit and must not survive the normalisation below, which would
        // otherwise turn a value rounding to zero, such as -1e-12, into the unparsable mantissa "-".
        $isNegative = str_starts_with($integerPart, '-');

        // Combine to form mantissa (drop the sign and the decimal point, then the leading zeros)
        $mantissa = ltrim(ltrim($integerPart, '-') . $fractionalPart, '0');
        if ($mantissa === '') {
            $mantissa = '0';
        }

        // Normalize: remove trailing zeros from mantissa by adjusting exponent
        while ($mantissa !== '0' && str_ends_with($mantissa, '0')) {
            $mantissa = substr($mantissa, 0, -1);
            $exponent++;
        }

        // A value that rounds to zero at the requested precision is zero, whatever its sign was.
        if ($mantissa === '0') {
            $isNegative = false;
            $exponent = 0;
        }

        // Create exponent object
        if ($exponent >= 0) {
            $exponentObj = UnsignedIntegerObject::create($exponent);
        } else {
            $exponentObj = NegativeIntegerObject::create($exponent);
        }

        // Put the sign back on the mantissa now that the normalisation above is done with it.
        return self::createFromExponentAndMantissa(
            $exponentObj,
            self::mantissaObject($isNegative ? '-' . $mantissa : $mantissa)
        );
    }

    /**
     * The mantissa of a decimal fraction is only bounded by the requested precision, so it routinely outgrows the
     * 8-byte argument an integer head can carry. RFC 8949 section 3.4.3 answers that with the bignum tags the
     * constructor already accepts, and this picks whichever of the four representations fits.
     *
     * The sign is read off the string rather than from a PHP integer cast, which saturates at PHP_INT_MIN or
     * PHP_INT_MAX and would report the wrong sign for exactly the long mantissas this has to handle.
     */
    private static function mantissaObject(string $mantissa): CBORObject
    {
        $value = BigInteger::of($mantissa);
        if (str_starts_with($mantissa, '-')) {
            $argument = BigInteger::of(-1)->minus($value);

            return $argument->isLessThanOrEqualTo(self::maximumHeadArgument())
                ? NegativeIntegerObject::createFromString($mantissa)
                : NegativeBigIntegerTag::create(ByteStringObject::create(self::toBigEndianBytes($argument)));
        }

        return $value->isLessThanOrEqualTo(self::maximumHeadArgument())
            ? UnsignedIntegerObject::createFromString($mantissa)
            : UnsignedBigIntegerTag::create(ByteStringObject::create(self::toBigEndianBytes($value)));
    }

    /**
     * Largest argument an integer head can carry, 2^64 - 1. Beyond it a bignum tag is the only representation.
     */
    private static function maximumHeadArgument(): BigInteger
    {
        return BigInteger::fromBase('FFFFFFFFFFFFFFFF', 16);
    }

    /**
     * The network byte order, unsigned, no leading zero byte representation a bignum tag wraps.
     */
    private static function toBigEndianBytes(BigInteger $value): string
    {
        $hex = $value->toBase(16);
        if (strlen($hex) % 2 === 1) {
            $hex = '0' . $hex;
        }
        $bytes = hex2bin($hex);
        if ($bytes === false) {
            throw new InvalidArgumentException('Unable to convert the data');
        }

        return $bytes;
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
            // m x 10^e with e >= 0 is an integer: no scale is needed and none shall be printed.
            return bcmul($mantissa, bcpow('10', $exponent, 0), 0);
        }

        // m x 10^-s has exactly s decimal digits, so that scale makes the division exact. A fixed scale would
        // either truncate the result to zero or, worse, report a rounded value as if it were the decoded one.
        $scale = -$exponentValue;

        return self::stripTrailingZeros(bcdiv($mantissa, bcpow('10', (string) $scale, 0), $scale));
    }

    /**
     * bcdiv() pads the result up to the requested scale, so the exact value comes back with trailing zeros and,
     * once they are gone, a dangling decimal point. Both are stripped, but only from a value that has a decimal
     * point at all: trimming "500" would turn it into "5".
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
