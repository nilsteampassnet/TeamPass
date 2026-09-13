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
 * Tag 41: an array whose items are all of the same type.
 *
 * The tag is a hint for the consumer -- it lets a statically typed decoder allocate one kind of slot -- and adds
 * nothing to the value, so the array normalizes exactly as it would untagged. Homogeneity is not checked: what
 * counts as "the same type" is the application's data model, not CBOR's major types.
 *
 * @see \CBOR\Test\Tag\RegistryTagsTest
 * @see https://datatracker.ietf.org/doc/html/rfc8746#section-3
 */
final class HomogeneousArrayTag extends Tag implements Normalizable
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
        return self::TAG_HOMOGENEOUS_ARRAY;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_HOMOGENEOUS_ARRAY);

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
