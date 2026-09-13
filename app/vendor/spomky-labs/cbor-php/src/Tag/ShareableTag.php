<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\CBORObject;
use CBOR\Normalizable;
use CBOR\Tag;

/**
 * Tag 28: marks the item it wraps as shareable, so that later occurrences may refer back to it with tag 29.
 *
 * Sharing is what lets a document hold a cyclic or repeated structure without repeating its encoding. Resolving
 * a reference needs the table of the values marked so far, which is per document rather than per item, so this
 * library records the mark and leaves the resolution to the application.
 *
 * @see \CBOR\Test\Tag\RegistryTagsTest
 * @see https://cbor.schmorp.de/value-sharing
 */
final class ShareableTag extends Tag implements Normalizable
{
    public static function getTagId(): int
    {
        return self::TAG_SHAREABLE;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_SHAREABLE);

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
