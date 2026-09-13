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
 * Tag 258: a mathematical finite set, encoded as an array.
 *
 * The array is the encoding of a set, not a set in itself: nothing in CBOR keeps a duplicate out of it. The
 * items are therefore returned in the order they were written, and it is up to the application to decide what a
 * repeated item means -- rejecting the document, most often, since a set that carries one is not what it claims.
 *
 * @see \CBOR\Test\Tag\RegistryTagsTest
 */
final class SetTag extends Tag implements Normalizable
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
        return self::TAG_SET;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_SET);

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
