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
 * Tag 259: a map meant as a key-value dictionary, as opposed to an object with named fields.
 *
 * The distinction matters when CBOR is converted to a format where the two are not spelled the same way. The
 * value is a plain map here, and normalize() returns it as one.
 *
 * @see \CBOR\Test\Tag\RegistryTagsTest
 */
final class ExplicitMapTag extends Tag implements Normalizable
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
        return self::TAG_EXPLICIT_MAP;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_EXPLICIT_MAP);

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
