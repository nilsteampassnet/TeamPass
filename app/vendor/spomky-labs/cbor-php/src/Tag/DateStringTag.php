<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\CBORObject;
use CBOR\IndefiniteLengthTextStringObject;
use CBOR\Normalizable;
use CBOR\Tag;
use CBOR\TextStringObject;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Tag 1004: a date in the RFC 3339 "full-date" form, i.e. YYYY-MM-DD, with no time and no time zone.
 *
 * It is the text counterpart of tag 100. As there, PHP has no date-only type, so normalize() returns midnight
 * UTC. A day that does not exist -- 2013-02-30, say -- is rejected rather than rolled over into the next month.
 *
 * @see \CBOR\Test\Tag\DateTagTest
 * @see https://datatracker.ietf.org/doc/html/rfc8943
 */
final class DateStringTag extends Tag implements Normalizable
{
    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        if (! $object instanceof TextStringObject && ! $object instanceof IndefiniteLengthTextStringObject) {
            throw new InvalidArgumentException('This tag only accepts a Text String object.');
        }

        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_DATE_STRING;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_DATE_STRING);

        return new self($ai, $data, $object);
    }

    /**
     * @return DateTimeInterface midnight UTC on the day the tag denotes
     */
    public function normalize(): DateTimeInterface
    {
        /** @var IndefiniteLengthTextStringObject|TextStringObject $object */
        $object = $this->object;

        // The leading "!" resets every field the format does not carry, so the time is midnight rather than the
        // current one -- otherwise the same document would normalize to a different instant every second.
        $result = DateTimeImmutable::createFromFormat('!Y-m-d', $object->normalize(), new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        // As of PHP 8.2 a parse with nothing to report returns false instead of an array of empty counters.
        $exact = $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
        if ($result === false || ! $exact) {
            throw new InvalidArgumentException('Invalid data. Cannot be converted into a datetime object');
        }

        return $result;
    }
}
