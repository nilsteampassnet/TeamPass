<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\CBORObject;
use CBOR\IndefiniteLengthListObject;
use CBOR\IndefiniteLengthTextStringObject;
use CBOR\ListObject;
use CBOR\Normalizable;
use CBOR\Tag;
use CBOR\TextStringObject;
use function count;
use InvalidArgumentException;

/**
 * Tag 26: a serialised Perl object, encoded as an array whose first item is the class name and whose remaining
 * items are the arguments its constructor was called with.
 *
 * Nothing is instantiated here, and nothing should be: rebuilding an object graph from an untrusted document is
 * how deserialization vulnerabilities happen. The array is returned as data.
 *
 * @see \CBOR\Test\Tag\RegistryTagsTest
 */
final class PerlObjectTag extends Tag implements Normalizable
{
    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        if (! $object instanceof ListObject && ! $object instanceof IndefiniteLengthListObject) {
            throw new InvalidArgumentException('This tag only accepts a List object.');
        }
        if (count($object) < 1) {
            throw new InvalidArgumentException('This tag only accepts a List object that contains at least 1 item.');
        }
        $name = $object->get(0);
        if (! $name instanceof TextStringObject && ! $name instanceof IndefiniteLengthTextStringObject) {
            throw new InvalidArgumentException('Invalid class name. Expected a Text String object.');
        }

        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_PERL_OBJECT;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_PERL_OBJECT);

        return new self($ai, $data, $object);
    }

    public function getClassName(): string
    {
        /** @var IndefiniteLengthListObject|ListObject $object */
        $object = $this->object;
        /** @var IndefiniteLengthTextStringObject|TextStringObject $name */
        $name = $object->get(0);

        return $name->normalize();
    }

    /**
     * @return array<int, mixed> the class name followed by the constructor arguments
     */
    public function normalize(): array
    {
        /** @var IndefiniteLengthListObject|ListObject $object */
        $object = $this->object;

        return $object->normalize();
    }
}
