<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\CBORObject;
use CBOR\IndefiniteLengthListObject;
use CBOR\ListObject;
use CBOR\Normalizable;
use CBOR\Tag;
use InvalidArgumentException;

/**
 * Tag 1003: a period, i.e. a stretch of the timeline delimited by two of the time values RFC 9581 defines.
 *
 * The array holds a start and an end, a start and a duration, or one of the two and a null standing for an open
 * end. Which of those it is depends on the items themselves, and the items may be extended times (tag 1001) that
 * this library deliberately leaves unflattened, so the array is returned as it stands.
 *
 * @see \CBOR\Test\Tag\RegistryTagsTest
 * @see https://datatracker.ietf.org/doc/html/rfc9581#section-5
 */
final class PeriodTag extends Tag implements Normalizable
{
    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        if (! $object instanceof ListObject && ! $object instanceof IndefiniteLengthListObject) {
            throw new InvalidArgumentException('This tag only accepts a List object.');
        }

        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_PERIOD;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_PERIOD);

        return new self($ai, $data, $object);
    }

    /**
     * @return array<int, mixed>
     */
    public function normalize(): array
    {
        /** @var IndefiniteLengthListObject|ListObject $object */
        $object = $this->object;

        return $object->normalize();
    }
}
