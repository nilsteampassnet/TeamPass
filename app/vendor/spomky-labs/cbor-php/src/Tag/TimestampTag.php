<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\CBORObject;
use CBOR\NegativeIntegerObject;
use CBOR\Normalizable;
use CBOR\OtherObject\DoublePrecisionFloatObject;
use CBOR\OtherObject\HalfPrecisionFloatObject;
use CBOR\OtherObject\SinglePrecisionFloatObject;
use CBOR\Tag;
use CBOR\UnsignedIntegerObject;
use DateTimeImmutable;
use DateTimeInterface;
use function floor;
use InvalidArgumentException;
use function is_infinite;
use function is_nan;
use function round;
use function sprintf;

final class TimestampTag extends Tag implements Normalizable
{
    /**
     * The largest magnitude accepted for a floating point timestamp.
     *
     * Beyond that, the instant cannot be represented: the seconds no longer fit a PHP integer, and a double has
     * long since lost the resolution needed to tell one second from the next anyway. The bound is some 3 x 10^10
     * years, far outside the range DateTimeImmutable itself supports.
     */
    private const MAXIMUM_TIMESTAMP_MAGNITUDE = 1.0e18;

    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        if (! $object instanceof UnsignedIntegerObject && ! $object instanceof NegativeIntegerObject && ! $object instanceof HalfPrecisionFloatObject && ! $object instanceof SinglePrecisionFloatObject && ! $object instanceof DoublePrecisionFloatObject) {
            throw new InvalidArgumentException('This tag only accepts integer-based or float-based objects.');
        }
        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_EPOCH_DATETIME;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): Tag
    {
        [$ai, $data] = self::determineComponents(self::TAG_EPOCH_DATETIME);

        return new self($ai, $data, $object);
    }

    public function normalize(): DateTimeInterface
    {
        $object = $this->object;

        $formatted = match (true) {
            $object instanceof UnsignedIntegerObject, $object instanceof NegativeIntegerObject => DateTimeImmutable::createFromFormat('U', $object->normalize()),
            $object instanceof HalfPrecisionFloatObject, $object instanceof SinglePrecisionFloatObject, $object instanceof DoublePrecisionFloatObject => DateTimeImmutable::createFromFormat('U.u', self::formatFloat($object->normalize())),
            default => throw new InvalidArgumentException('Unable to normalize the object'),
        };

        if ($formatted === false) {
            throw new InvalidArgumentException('Invalid data. Cannot be converted into a datetime object');
        }

        return $formatted;
    }

    /**
     * Splits a floating point timestamp into the seconds and microseconds that "U.u" expects.
     *
     * The value is decomposed arithmetically rather than printed and cut apart: the decimal representation of a
     * double depends on the "precision" ini setting, it drops the fractional part of an integral value entirely
     * -- "U.u" then rejects the result -- and, being the representation of a signed magnitude, it truncates
     * towards zero where "U.u" needs the seconds floored.
     */
    private static function formatFloat(float $timestamp): string
    {
        if (is_nan($timestamp) || is_infinite($timestamp)) {
            throw new InvalidArgumentException('Invalid data. Cannot be converted into a datetime object');
        }
        if ($timestamp < -self::MAXIMUM_TIMESTAMP_MAGNITUDE || $timestamp > self::MAXIMUM_TIMESTAMP_MAGNITUDE) {
            throw new InvalidArgumentException('Invalid data. Cannot be converted into a datetime object');
        }

        $seconds = (int) floor($timestamp);
        $microseconds = (int) round(($timestamp - $seconds) * 1000000);
        // Rounding up from something like x.9999996 carries into the next second.
        if ($microseconds === 1000000) {
            ++$seconds;
            $microseconds = 0;
        }

        return sprintf('%d.%06d', $seconds, $microseconds);
    }
}
