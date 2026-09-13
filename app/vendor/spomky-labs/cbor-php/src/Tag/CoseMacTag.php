<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\ByteStringObject;
use CBOR\CBORObject;
use CBOR\IndefiniteLengthByteStringObject;
use CBOR\IndefiniteLengthListObject;
use CBOR\ListObject;
use CBOR\MapObject;
use CBOR\OtherObject\NullObject;
use CBOR\Tag;

/**
 * Tag 97: COSE_Mac, a MAC'd message with one or more recipients, each of which carries the MAC key in its own
 * way.
 *
 * Content: [protected header, unprotected header, payload, tag, recipients].
 */
final class CoseMacTag extends AbstractCoseTag
{
    private const STRUCTURE_NAME = 'CoseMac';

    private const ITEM_COUNT = 5;

    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        $structure = self::assertStructure($object, self::ITEM_COUNT, self::STRUCTURE_NAME);
        self::assertPayloadAt($structure, 2, self::STRUCTURE_NAME);
        self::assertByteStringAt($structure, 3, self::STRUCTURE_NAME);
        self::assertListAt($structure, 4, self::STRUCTURE_NAME);

        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_COSE_MAC;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_COSE_MAC);

        return new self($ai, $data, $object);
    }

    /**
     * Builds the structure from its parts, serializing the protected header into the byte string COSE signs.
     */
    public static function createFromComponents(
        MapObject $protectedHeader,
        MapObject $unprotectedHeader,
        ByteStringObject|NullObject $payload,
        ByteStringObject $tag,
        ListObject $recipients
    ): self {
        return self::create(ListObject::create([
            ByteStringObject::create((string) $protectedHeader),
            $unprotectedHeader,
            $payload,
            $tag,
            $recipients,
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

    public function getRecipients(): ListObject|IndefiniteLengthListObject
    {
        /** @var IndefiniteLengthListObject|ListObject $item */
        $item = $this->itemAt(4);

        return $item;
    }
}
