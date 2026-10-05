<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\CBORObject;
use CBOR\Normalizable;
use CBOR\Tag;

/**
 * Tag 39: marks the item it wraps as an identifier -- a name to be compared, not a value to be interpreted.
 *
 * @see \CBOR\Test\Tag\RegistryTagsTest
 */
final class IdentifierTag extends Tag implements Normalizable
{
    public static function getTagId(): int
    {
        return self::TAG_IDENTIFIER;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_IDENTIFIER);

        return new self($ai, $data, $object);
    }

    /**
     * The tag says something about the item it wraps rather than about its value, so the item is normalized on
     * its own. Use getValue() when the tag itself matters.
     */
    public function normalize(): mixed
    {
        $object = $this->object;

        return $object instanceof Normalizable ? $object->normalize() : $object;
    }
}
