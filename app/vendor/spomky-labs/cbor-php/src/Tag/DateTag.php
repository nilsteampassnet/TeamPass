<?php

declare(strict_types=1);

namespace CBOR\Tag;

use Brick\Math\BigInteger;
use Brick\Math\Exception\MathException;
use CBOR\CBORObject;
use CBOR\NegativeIntegerObject;
use CBOR\Normalizable;
use CBOR\Tag;
use CBOR\UnsignedIntegerObject;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Tag 100: a date, as the number of days since 1970-01-01, with no time and no time zone.
 *
 * It is what tag 1 is to an instant: a birthday or an expiry date is a calendar day everywhere at once, and
 * pinning it to a moment -- which is what tag 0 or tag 1 would do -- moves it across a day boundary depending on
 * where it is read. PHP has no date-only type, so normalize() returns midnight UTC, the reading that keeps the
 * day intact as long as the time zone is left alone.
 *
 * @see \CBOR\Test\Tag\DateTagTest
 * @see https://datatracker.ietf.org/doc/html/rfc8943
 */
final class DateTag extends Tag implements Normalizable
{
    private const SECONDS_PER_DAY = 86400;

    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        if (! $object instanceof UnsignedIntegerObject && ! $object instanceof NegativeIntegerObject) {
            throw new InvalidArgumentException('This tag only accepts an Integer object.');
        }

        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_DATE;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_DATE);

        return new self($ai, $data, $object);
    }

    /**
     * @return DateTimeInterface midnight UTC on the day the tag denotes
     */
    public function normalize(): DateTimeInterface
    {
        /** @var NegativeIntegerObject|UnsignedIntegerObject $object */
        $object = $this->object;

        try {
            $seconds = BigInteger::of($object->normalize())
                ->multipliedBy(self::SECONDS_PER_DAY)
                ->toInt();
        } catch (MathException $throwable) {
            throw new InvalidArgumentException('Invalid data. Cannot be converted into a datetime object', 0, $throwable);
        }

        $result = DateTimeImmutable::createFromFormat('U', (string) $seconds);
        if ($result === false) {
            throw new InvalidArgumentException('Invalid data. Cannot be converted into a datetime object');
        }

        return $result;
    }
}
