<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\CBORObject;
use CBOR\IndefiniteLengthMapObject;
use CBOR\MapObject;
use CBOR\Normalizable;
use CBOR\Tag;
use InvalidArgumentException;

/**
 * Tag 1001: an extended time, i.e. an instant with more than the seconds tag 1 can carry -- a clock quality, a
 * time scale, a UTC offset, a leap second indicator.
 *
 * RFC 9581 builds the value out of a map whose keys select what is present, and the combinations do not all map
 * onto a PHP date object: a leap second, a time scale that is not UTC or a resolution below the microsecond all
 * describe instants DateTimeImmutable cannot hold. The map is therefore returned as it stands rather than
 * flattened into a date that would silently lose one of them.
 *
 * @see \CBOR\Test\Tag\RegistryTagsTest
 * @see https://datatracker.ietf.org/doc/html/rfc9581#section-3
 */
final class ExtendedTimeTag extends Tag implements Normalizable
{
    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        if (! $object instanceof MapObject && ! $object instanceof IndefiniteLengthMapObject) {
            throw new InvalidArgumentException('This tag only accepts a Map object.');
        }
        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_EXTENDED_TIME;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_EXTENDED_TIME);

        return new self($ai, $data, $object);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function normalize(): array
    {
        /** @var IndefiniteLengthMapObject|MapObject $object */
        $object = $this->object;

        return $object->normalize();
    }
}
