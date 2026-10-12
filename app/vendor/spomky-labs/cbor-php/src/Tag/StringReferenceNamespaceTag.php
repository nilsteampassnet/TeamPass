<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\CBORObject;
use CBOR\Normalizable;
use CBOR\Tag;

/**
 * Tag 256: opens a namespace for the string references (tag 25) that appear inside the item it wraps.
 *
 * The strings a reference may point at are the ones seen since the namespace was opened, which makes the table
 * a property of the enclosing document rather than of any single item. This library records the namespace and
 * leaves the resolution to the application.
 *
 * @see \CBOR\Test\Tag\RegistryTagsTest
 * @see https://cbor.schmorp.de/stringref
 */
final class StringReferenceNamespaceTag extends Tag implements Normalizable
{
    public static function getTagId(): int
    {
        return self::TAG_STRING_REFERENCE_NAMESPACE;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_STRING_REFERENCE_NAMESPACE);

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
