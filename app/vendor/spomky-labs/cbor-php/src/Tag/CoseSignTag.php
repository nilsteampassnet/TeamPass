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
 * Tag 98: COSE_Sign, a message signed by one or more signers, each with its own headers and signature.
 *
 * Content: [protected header, unprotected header, payload, signatures].
 */
final class CoseSignTag extends AbstractCoseTag
{
    private const STRUCTURE_NAME = 'CoseSign';

    private const ITEM_COUNT = 4;

    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        $structure = self::assertStructure($object, self::ITEM_COUNT, self::STRUCTURE_NAME);
        self::assertPayloadAt($structure, 2, self::STRUCTURE_NAME);
        self::assertListAt($structure, 3, self::STRUCTURE_NAME);

        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_COSE_SIGN;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_COSE_SIGN);

        return new self($ai, $data, $object);
    }

    /**
     * Builds the structure from its parts, serializing the protected header into the byte string COSE signs.
     */
    public static function createFromComponents(
        MapObject $protectedHeader,
        MapObject $unprotectedHeader,
        ByteStringObject|NullObject $payload,
        ListObject $signatures
    ): self {
        return self::create(ListObject::create([
            ByteStringObject::create((string) $protectedHeader),
            $unprotectedHeader,
            $payload,
            $signatures,
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

    public function getSignatures(): ListObject|IndefiniteLengthListObject
    {
        /** @var IndefiniteLengthListObject|ListObject $item */
        $item = $this->itemAt(3);

        return $item;
    }
}
