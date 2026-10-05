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
 * Tag 1002: a duration, i.e. an amount of time with no anchor on the timeline.
 *
 * As with tag 1001, RFC 9581 encodes it as a map of components, and the components it allows do not all fit a
 * PHP DateInterval, so the map is returned as it stands.
 *
 * @see \CBOR\Test\Tag\RegistryTagsTest
 * @see https://datatracker.ietf.org/doc/html/rfc9581#section-4
 */
final class DurationTag extends Tag implements Normalizable
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
        return self::TAG_DURATION;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_DURATION);

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
