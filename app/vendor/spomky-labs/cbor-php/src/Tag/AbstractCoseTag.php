<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\ByteStringObject;
use CBOR\CBORObject;
use CBOR\Decoder;
use CBOR\DecoderInterface;
use CBOR\IndefiniteLengthByteStringObject;
use CBOR\IndefiniteLengthListObject;
use CBOR\IndefiniteLengthMapObject;
use CBOR\ListObject;
use CBOR\MapObject;
use CBOR\Normalizable;
use CBOR\OtherObject\NullObject;
use CBOR\StringStream;
use CBOR\Tag;
use function count;
use InvalidArgumentException;
use function sprintf;

/**
 * Common behaviour of the six COSE structures of RFC 9052: tags 16, 17, 18, 96, 97 and 98.
 *
 * Every one of them is an array that opens with the same two items -- the protected header, wrapped in a byte
 * string so that it is signed exactly as it was written, and the unprotected header as a plain map -- and then
 * differs in what follows. The tag only describes that shape: verifying a signature or a MAC needs keys and
 * algorithms this library knows nothing about, and belongs to a COSE implementation such as web-auth/cose-lib.
 *
 * @see \CBOR\Test\Tag\CoseTagTest
 * @see https://datatracker.ietf.org/doc/html/rfc9052
 */
abstract class AbstractCoseTag extends Tag implements Normalizable
{
    /**
     * The protected header as it is carried: a byte string whose content is the encoded header map.
     */
    public function getProtectedHeader(): ByteStringObject|IndefiniteLengthByteStringObject
    {
        /** @var ByteStringObject|IndefiniteLengthByteStringObject $item */
        $item = $this->itemAt(0);

        return $item;
    }

    /**
     * The protected header, decoded.
     *
     * An empty byte string is the COSE way of saying "no protected header", so it decodes to an empty map rather
     * than to a parse error.
     */
    public function getProtectedHeaderAsMap(
        ?DecoderInterface $decoder = null
    ): MapObject|IndefiniteLengthMapObject {
        $value = $this->getProtectedHeader()
            ->getValue();
        if ($value === '') {
            return MapObject::create();
        }

        $decoder ??= Decoder::create();
        $decoded = $decoder->decode(StringStream::create($value));
        if (! $decoded instanceof MapObject && ! $decoded instanceof IndefiniteLengthMapObject) {
            throw new InvalidArgumentException('Protected header is not a valid Map object.');
        }

        return $decoded;
    }

    public function getUnprotectedHeader(): MapObject|IndefiniteLengthMapObject
    {
        /** @var IndefiniteLengthMapObject|MapObject $item */
        $item = $this->itemAt(1);

        return $item;
    }

    /**
     * @return array<int, mixed> the structure as the array it is
     */
    public function normalize(): array
    {
        /** @var IndefiniteLengthListObject|ListObject $object */
        $object = $this->object;

        return $object->normalize();
    }

    /**
     * Checks the array and the two items every COSE structure opens with.
     *
     * @return IndefiniteLengthListObject|ListObject the same object, once it is known to be a list
     */
    protected static function assertStructure(
        CBORObject $object,
        int $count,
        string $name
    ): ListObject|IndefiniteLengthListObject {
        if (! $object instanceof ListObject && ! $object instanceof IndefiniteLengthListObject) {
            throw new InvalidArgumentException(sprintf('Not a valid %s object. Expected a List object.', $name));
        }
        if (count($object) !== $count) {
            throw new InvalidArgumentException(
                sprintf('Not a valid %s object. The list shall have %d items.', $name, $count)
            );
        }

        $protectedHeader = $object->get(0);
        if (! $protectedHeader instanceof ByteStringObject && ! $protectedHeader instanceof IndefiniteLengthByteStringObject) {
            throw new InvalidArgumentException(
                sprintf('Not a valid %s object. The item 1 shall be a Byte String object.', $name)
            );
        }
        $unprotectedHeader = $object->get(1);
        if (! $unprotectedHeader instanceof MapObject && ! $unprotectedHeader instanceof IndefiniteLengthMapObject) {
            throw new InvalidArgumentException(
                sprintf('Not a valid %s object. The item 2 shall be a Map object.', $name)
            );
        }

        return $object;
    }

    protected static function assertByteStringAt(
        ListObject|IndefiniteLengthListObject $object,
        int $index,
        string $name
    ): void {
        $item = $object->get($index);
        if (! $item instanceof ByteStringObject && ! $item instanceof IndefiniteLengthByteStringObject) {
            throw new InvalidArgumentException(
                sprintf('Not a valid %s object. The item %d shall be a Byte String object.', $name, $index + 1)
            );
        }
    }

    /**
     * A payload or a ciphertext may be detached, which COSE spells as null: the content is carried out of band
     * and only the signature or the tag travels with the structure.
     */
    protected static function assertPayloadAt(
        ListObject|IndefiniteLengthListObject $object,
        int $index,
        string $name
    ): void {
        $item = $object->get($index);
        if (! $item instanceof ByteStringObject && ! $item instanceof IndefiniteLengthByteStringObject && ! $item instanceof NullObject) {
            throw new InvalidArgumentException(
                sprintf('Not a valid %s object. The item %d shall be a Byte String object or null.', $name, $index + 1)
            );
        }
    }

    protected static function assertListAt(
        ListObject|IndefiniteLengthListObject $object,
        int $index,
        string $name
    ): void {
        $item = $object->get($index);
        if (! $item instanceof ListObject && ! $item instanceof IndefiniteLengthListObject) {
            throw new InvalidArgumentException(
                sprintf('Not a valid %s object. The item %d shall be a List object.', $name, $index + 1)
            );
        }
    }

    protected function itemAt(int $index): CBORObject
    {
        /** @var IndefiniteLengthListObject|ListObject $object */
        $object = $this->object;

        return $object->get($index);
    }
}
