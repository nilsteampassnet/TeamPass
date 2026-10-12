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
 * Tag 16: COSE_Encrypt0, a single recipient encrypted message -- the recipient is known out of band, so the
 * structure carries no recipient list.
 *
 * Content: [protected header, unprotected header, ciphertext].
 */
final class CoseEncrypt0Tag extends AbstractCoseTag
{
    private const STRUCTURE_NAME = 'CoseEncrypt0';

    private const ITEM_COUNT = 3;

    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        $structure = self::assertStructure($object, self::ITEM_COUNT, self::STRUCTURE_NAME);
        self::assertPayloadAt($structure, 2, self::STRUCTURE_NAME);

        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_COSE_ENCRYPT0;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_COSE_ENCRYPT0);

        return new self($ai, $data, $object);
    }

    /**
     * Builds the structure from its parts, serializing the protected header into the byte string COSE signs.
     */
    public static function createFromComponents(MapObject $protectedHeader, MapObject $unprotectedHeader, ByteStringObject|NullObject $ciphertext): self
    {
        return self::create(ListObject::create([
            ByteStringObject::create((string) $protectedHeader),
            $unprotectedHeader,
            $ciphertext,
        ]));
    }

    /**
     * The ciphertext, or null when it is detached and carried out of band.
     */
    public function getCiphertext(): ByteStringObject|IndefiniteLengthByteStringObject|NullObject
    {
        /** @var ByteStringObject|IndefiniteLengthByteStringObject|NullObject $item */
        $item = $this->itemAt(2);

        return $item;
    }
}
