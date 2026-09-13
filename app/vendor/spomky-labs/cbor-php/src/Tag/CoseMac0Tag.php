<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\ByteStringObject;
use CBOR\CBORObject;
use CBOR\IndefiniteLengthByteStringObject;
use CBOR\ListObject;
use CBOR\MapObject;
use CBOR\OtherObject\NullObject;
use CBOR\Tag;

/**
 * Tag 17: COSE_Mac0, a MAC'd message with a single recipient -- the key is known out of band, so the structure
 * carries no recipient list.
 *
 * Content: [protected header, unprotected header, payload, tag].
 */
final class CoseMac0Tag extends AbstractCoseTag
{
    private const STRUCTURE_NAME = 'CoseMac0';

    private const ITEM_COUNT = 4;

    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        $structure = self::assertStructure($object, self::ITEM_COUNT, self::STRUCTURE_NAME);
        self::assertPayloadAt($structure, 2, self::STRUCTURE_NAME);
        self::assertByteStringAt($structure, 3, self::STRUCTURE_NAME);

        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_COSE_MAC0;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_COSE_MAC0);

        return new self($ai, $data, $object);
    }

    /**
     * Builds the structure from its parts, serializing the protected header into the byte string COSE signs.
     */
    public static function createFromComponents(
        MapObject $protectedHeader,
        MapObject $unprotectedHeader,
        ByteStringObject|NullObject $payload,
        ByteStringObject $tag
    ): self {
        return self::create(ListObject::create([
            ByteStringObject::create((string) $protectedHeader),
            $unprotectedHeader,
            $payload,
            $tag,
        ]));
    }

    /**
     * The payload, or null when it is detached and carried out of band.
     */
    public function getPayload(): ByteStringObject|IndefiniteLengthByteStringObject|NullObject
    {
        /** @var ByteStringObject|IndefiniteLengthByteStringObject|NullObject $item */
        $item = $this->itemAt(2);

        return $item;
    }

    public function getTag(): ByteStringObject|IndefiniteLengthByteStringObject
    {
        /** @var ByteStringObject|IndefiniteLengthByteStringObject $item */
        $item = $this->itemAt(3);

        return $item;
    }
}
