<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\CBORObject;
use CBOR\IndefiniteLengthTextStringObject;
use CBOR\Normalizable;
use CBOR\Tag;
use CBOR\TextStringObject;
use const DATE_RFC3339;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use function preg_match;
use function str_contains;

/**
 * @see \CBOR\Test\Tag\DatetimeTagTest
 */
final class DatetimeTag extends Tag implements Normalizable
{
    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        if (! $object instanceof TextStringObject && ! $object instanceof IndefiniteLengthTextStringObject) {
            throw new InvalidArgumentException('This tag only accepts a Byte String object.');
        }
        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_STANDARD_DATETIME;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): Tag
    {
        [$ai, $data] = self::determineComponents(self::TAG_STANDARD_DATETIME);

        return new self($ai, $data, $object);
    }

    public function normalize(): DateTimeInterface
    {
        /** @var TextStringObject|IndefiniteLengthTextStringObject $object */
        $object = $this->object;
        $value = $object->normalize();

        // Since PHP 8, createFromFormat() rejects an argument holding a NUL byte with a ValueError -- an Error, so
        // neither the "=== false" guard below nor a caller catching InvalidArgumentException ever sees it. The byte
        // cannot appear in an RFC 3339 date-time anyway, so it is turned down here, within the error contract.
        if (str_contains($value, "\0")) {
            throw new InvalidArgumentException('Invalid data. Cannot be converted into a datetime object');
        }

        // RFC 3339 allows a leap second, which no PHP date can hold. It is parsed as the second before it and
        // shifted forward again, which lands on the next minute: the closest instant this library can return.
        $isLeapSecond = preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:)60(.*)$/', $value, $matches) === 1;
        if ($isLeapSecond) {
            $value = $matches[1] . '59' . $matches[2];
        }

        $result = DateTimeImmutable::createFromFormat(DATE_RFC3339, $value);
        if ($result === false || ! self::wasParsedExactly()) {
            $result = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', $value);
            if ($result === false || ! self::wasParsedExactly()) {
                throw new InvalidArgumentException('Invalid data. Cannot be converted into a datetime object');
            }
        }

        return $isLeapSecond ? $result->add(new DateInterval('PT1S')) : $result;
    }

    /**
     * Tells whether the last parse consumed the whole string and described an instant that exists.
     *
     * createFromFormat() does not reject a date-time that cannot be: it rolls it over and merely reports a warning,
     * so "2013-02-30" comes back as the 2nd of March and hour 25 as the next day. A document then decodes to an
     * instant it does not carry, which is worse than a rejection.
     */
    private static function wasParsedExactly(): bool
    {
        $errors = DateTimeImmutable::getLastErrors();

        // As of PHP 8.2 a parse with nothing to report returns false instead of an array of empty counters.
        return $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
    }
}
