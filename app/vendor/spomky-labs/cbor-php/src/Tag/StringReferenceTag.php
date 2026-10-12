<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\CBORObject;
use CBOR\Normalizable;
use CBOR\Tag;
use CBOR\UnsignedIntegerObject;
use InvalidArgumentException;

/**
 * Tag 25: a reference to a string already seen in the enclosing tag 256 namespace, given as its index.
 *
 * The index is returned as it stands: the table it points into is built while the document is read, which is
 * outside the scope of a single tag.
 *
 * @see \CBOR\Test\Tag\RegistryTagsTest
 * @see https://cbor.schmorp.de/stringref
 */
final class StringReferenceTag extends Tag implements Normalizable
{
    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        if (! $object instanceof UnsignedIntegerObject) {
            throw new InvalidArgumentException('This tag only accepts an Unsigned Integer object.');
        }
        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_STRING_REFERENCE;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_STRING_REFERENCE);

        return new self($ai, $data, $object);
    }

    /**
     * @return string the index, as a decimal string: a CBOR unsigned integer may exceed PHP_INT_MAX
     */
    public function normalize(): string
    {
        /** @var UnsignedIntegerObject $object */
        $object = $this->object;

        return $object->normalize();
    }
}
