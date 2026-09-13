<?php

declare(strict_types=1);

namespace CBOR\Tag;

use Brick\Math\BigInteger;
use Brick\Math\Exception\MathException;
use CBOR\CBORObject;
use CBOR\IndefiniteLengthListObject;
use CBOR\ListObject;
use CBOR\NegativeIntegerObject;
use CBOR\Normalizable;
use CBOR\Tag;
use CBOR\UnsignedIntegerObject;
use function count;
use InvalidArgumentException;

/**
 * Tag 30: a rational number, encoded as the two element array [numerator, denominator].
 *
 * Either part may itself be a bignum (tags 2 and 3), which is the point of the tag: a ratio such as 1/3 has no
 * exact binary or decimal floating point form, so it is carried as the pair it is.
 *
 * @see \CBOR\Test\Tag\RegistryTagsTest
 * @see https://peteroupc.github.io/CBOR/rational.html
 */
final class RationalNumberTag extends Tag implements Normalizable
{
    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        if (! $object instanceof ListObject && ! $object instanceof IndefiniteLengthListObject) {
            throw new InvalidArgumentException('This tag only accepts a List object.');
        }
        if (count($object) !== 2) {
            throw new InvalidArgumentException('This tag only accepts a List object that contains 2 items.');
        }

        $numerator = $object->get(0);
        $denominator = $object->get(1);
        if (! $numerator instanceof UnsignedIntegerObject && ! $numerator instanceof NegativeIntegerObject && ! $numerator instanceof UnsignedBigIntegerTag && ! $numerator instanceof NegativeBigIntegerTag) {
            throw new InvalidArgumentException('Invalid numerator. Expected an integer or a big integer object.');
        }
        // The denominator is unsigned: RFC-registered rationals carry the sign on the numerator only.
        if (! $denominator instanceof UnsignedIntegerObject && ! $denominator instanceof UnsignedBigIntegerTag) {
            throw new InvalidArgumentException(
                'Invalid denominator. Expected an unsigned integer or an unsigned big integer object.'
            );
        }
        if ($denominator instanceof UnsignedIntegerObject && $denominator->normalize() === '0') {
            throw new InvalidArgumentException('Invalid denominator. The value shall not be zero.');
        }

        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_RATIONAL_NUMBER;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_RATIONAL_NUMBER);

        return new self($ai, $data, $object);
    }

    public static function createFromNumeratorAndDenominator(CBORObject $numerator, CBORObject $denominator): self
    {
        return self::create(ListObject::create([$numerator, $denominator]));
    }

    /**
     * @return string the value in lowest terms, as "numerator/denominator" -- or as the numerator alone when the
     *                denominator reduces to 1
     */
    public function normalize(): string
    {
        /** @var IndefiniteLengthListObject|ListObject $object */
        $object = $this->object;

        try {
            $numerator = BigInteger::of(self::valueOf($object->get(0)));
            $denominator = BigInteger::of(self::valueOf($object->get(1)));
            // A zero denominator carried by a bignum only shows up here: reading it means decoding the byte
            // string, which is too much work to do while the tag is merely being built.
            if ((string) $denominator === '0') {
                throw new InvalidArgumentException('Invalid denominator. The value shall not be zero.');
            }

            // The reduction is done by hand rather than with BigRational, whose API moved between the versions
            // of brick/math this library supports.
            $divisor = $numerator->gcd($denominator);
            $numerator = $numerator->dividedBy($divisor);
            $denominator = $denominator->dividedBy($divisor);
        } catch (MathException $throwable) {
            throw new InvalidArgumentException('Invalid data. Cannot be converted into a rational number', 0, $throwable);
        }

        return (string) $denominator === '1' ? (string) $numerator : $numerator . '/' . $denominator;
    }

    /**
     * @return string the decimal representation of an integer that may well not fit a PHP one
     */
    private static function valueOf(CBORObject $object): string
    {
        /** @var NegativeBigIntegerTag|NegativeIntegerObject|UnsignedBigIntegerTag|UnsignedIntegerObject $object */
        return $object->normalize();
    }
}
