<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\ByteStringObject;
use CBOR\CBORObject;
use CBOR\IndefiniteLengthByteStringObject;
use CBOR\IndefiniteLengthListObject;
use CBOR\IndefiniteLengthTextStringObject;
use CBOR\ListObject;
use CBOR\Normalizable;
use CBOR\Tag;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;
use function count;
use function inet_ntop;
use InvalidArgumentException;
use function sprintf;
use function str_pad;
use const STR_PAD_RIGHT;
use function strlen;

/**
 * Common behaviour of the two IP address tags of RFC 9164: 52 (IPv4) and 54 (IPv6).
 *
 * Each tag carries one of three shapes: a byte string, which is a plain address; the array [prefix length,
 * bytes], which is a prefix whose trailing zero bytes may be left out; or the array [bytes, zone], which is an
 * address together with the zone identifier that scopes it. The first item tells the last two apart -- an
 * integer opens a prefix, a byte string an address -- which is why the shapes can share one tag number.
 */
abstract class AbstractIpAddressTag extends Tag implements Normalizable
{
    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        $length = static::getAddressLength();
        if ($object instanceof ByteStringObject || $object instanceof IndefiniteLengthByteStringObject) {
            if ($object->getLength() !== $length) {
                throw new InvalidArgumentException(sprintf(
                    'This tag only accepts a %d byte Byte String object as an address.',
                    $length
                ));
            }

            parent::__construct($additionalInformation, $data, $object);

            return;
        }

        if (! $object instanceof ListObject && ! $object instanceof IndefiniteLengthListObject) {
            throw new InvalidArgumentException('This tag only accepts a Byte String object or a List object.');
        }
        if (count($object) !== 2) {
            throw new InvalidArgumentException('This tag only accepts a List object that contains 2 items.');
        }

        $first = $object->get(0);
        $second = $object->get(1);
        if ($first instanceof UnsignedIntegerObject) {
            if (! $second instanceof ByteStringObject && ! $second instanceof IndefiniteLengthByteStringObject) {
                throw new InvalidArgumentException('Invalid prefix. Expected a Byte String object.');
            }
            if ($second->getLength() > $length) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid prefix. Expected at most %d bytes.',
                    $length
                ));
            }
            if ((int) $first->normalize() > $length * 8) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid prefix length. Expected at most %d.',
                    $length * 8
                ));
            }

            parent::__construct($additionalInformation, $data, $object);

            return;
        }

        if (! $first instanceof ByteStringObject && ! $first instanceof IndefiniteLengthByteStringObject) {
            throw new InvalidArgumentException('Invalid address. Expected a Byte String object.');
        }
        if ($first->getLength() !== $length) {
            throw new InvalidArgumentException(sprintf(
                'Invalid address. Expected exactly %d bytes.',
                $length
            ));
        }
        if (! $second instanceof UnsignedIntegerObject && ! $second instanceof TextStringObject && ! $second instanceof IndefiniteLengthTextStringObject) {
            throw new InvalidArgumentException('Invalid zone identifier. Expected a Text String or an Unsigned Integer object.');
        }

        parent::__construct($additionalInformation, $data, $object);
    }

    /**
     * @return string the address in its textual form, followed by "/length" for a prefix or by "%zone" for a
     *                zoned address
     */
    public function normalize(): string
    {
        $object = $this->object;
        if ($object instanceof ByteStringObject || $object instanceof IndefiniteLengthByteStringObject) {
            return self::toTextualAddress($object->normalize());
        }

        /** @var IndefiniteLengthListObject|ListObject $object */
        $first = $object->get(0);
        /** @var ByteStringObject|IndefiniteLengthByteStringObject|TextStringObject|UnsignedIntegerObject $second */
        $second = $object->get(1);
        if ($first instanceof UnsignedIntegerObject) {
            // The bytes of a prefix are truncated after the last one that carries a bit of it, so they are
            // padded back to a full address before being printed.
            $bytes = str_pad($second->normalize(), static::getAddressLength(), "\0", STR_PAD_RIGHT);

            return self::toTextualAddress($bytes) . '/' . $first->normalize();
        }

        /** @var ByteStringObject|IndefiniteLengthByteStringObject $first */
        return self::toTextualAddress($first->normalize()) . '%' . $second->normalize();
    }

    /**
     * @return int the size of an address of this family, in bytes
     */
    abstract protected static function getAddressLength(): int;

    private static function toTextualAddress(string $bytes): string
    {
        if (strlen($bytes) !== static::getAddressLength()) {
            throw new InvalidArgumentException('Invalid address. Cannot be converted into a textual address.');
        }
        $address = inet_ntop($bytes);
        if ($address === false) {
            throw new InvalidArgumentException('Invalid address. Cannot be converted into a textual address.');
        }

        return $address;
    }
}
