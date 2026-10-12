<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\ByteStringObject;
use CBOR\CBORObject;
use CBOR\Decoder;
use CBOR\DecoderInterface;
use CBOR\IndefiniteLengthByteStringObject;
use CBOR\StringStream;
use CBOR\Tag;
use InvalidArgumentException;
use function strlen;
use function substr;

/**
 * Tag 63: a byte string holding an encoded CBOR sequence (RFC 8742), i.e. zero or more data items one after the
 * other, with no enclosing array.
 *
 * It is to a sequence what tag 24 is to a single data item. The bytes are not decoded on the way in -- the tag
 * only says what they are -- so getSequence() is where a caller opts into parsing them.
 *
 * @see \CBOR\Test\Tag\RegistryTagsTest
 */
final class CBORSequenceTag extends Tag
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
        return self::TAG_ENCODED_CBOR_SEQUENCE;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_ENCODED_CBOR_SEQUENCE);

        return new self($ai, $data, $object);
    }

    /**
     * Decodes the sequence the byte string holds.
     *
     * A CBOR sequence carries no framing, so the only way to find where an item ends is to decode it. The stream
     * abstraction of this library cannot report how far it was consumed, so each item is re-encoded and the bytes
     * it accounts for are dropped from the front. Every object of this library reproduces the head it was decoded
     * from, which makes the round trip exact; a mismatch would mean the item was rewritten on the way out, so it
     * is reported rather than silently used to advance past the wrong number of bytes.
     *
     * @return CBORObject[] the items, in the order they appear; an empty byte string is an empty sequence
     */
    public function getSequence(?DecoderInterface $decoder = null): array
    {
        /** @var ByteStringObject|IndefiniteLengthByteStringObject $object */
        $object = $this->object;
        $remaining = $object->getValue();
        $decoder ??= Decoder::create();

        $items = [];
        while ($remaining !== '') {
            $item = $decoder->decode(StringStream::create($remaining));
            $encoded = (string) $item;
            $length = strlen($encoded);
            if ($length === 0 || substr($remaining, 0, $length) !== $encoded) {
                throw new InvalidArgumentException('Invalid data. The sequence cannot be decoded.');
            }
            $items[] = $item;
            $remaining = substr($remaining, $length);
        }

        return $items;
    }
}
