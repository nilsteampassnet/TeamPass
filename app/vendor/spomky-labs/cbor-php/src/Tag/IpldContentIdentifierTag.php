<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\ByteStringObject;
use CBOR\CBORObject;
use CBOR\IndefiniteLengthByteStringObject;
use CBOR\Normalizable;
use CBOR\Tag;
use InvalidArgumentException;

/**
 * Tag 42: an IPLD content identifier (CID), as used by IPFS and other content addressed systems.
 *
 * The byte string is a multibase prefixed CID: the leading 0x00 identity byte followed by the binary CID itself.
 * Neither the prefix nor the multihash inside it is interpreted here -- decoding them belongs to a CID library --
 * so the value is returned as the raw bytes it holds.
 *
 * @see \CBOR\Test\Tag\RegistryTagsTest
 */
final class IpldContentIdentifierTag extends Tag implements Normalizable
{
    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        if (! $object instanceof ByteStringObject && ! $object instanceof IndefiniteLengthByteStringObject) {
            throw new InvalidArgumentException('This tag only accepts a Byte String object.');
        }

        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_IPLD_CONTENT_IDENTIFIER;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_IPLD_CONTENT_IDENTIFIER);

        return new self($ai, $data, $object);
    }

    public function normalize(): string
    {
        /** @var ByteStringObject|IndefiniteLengthByteStringObject $object */
        $object = $this->object;

        return $object->normalize();
    }
}
