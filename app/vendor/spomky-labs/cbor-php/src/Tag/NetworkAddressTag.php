<?php

declare(strict_types=1);

namespace CBOR\Tag;

use function bin2hex;
use CBOR\ByteStringObject;
use CBOR\CBORObject;
use CBOR\IndefiniteLengthByteStringObject;
use CBOR\Normalizable;
use CBOR\Tag;
use function implode;
use function in_array;
use function inet_ntop;
use InvalidArgumentException;
use function str_split;
use function strlen;

/**
 * Tag 260: a network address, given as its bytes -- 4 for IPv4, 16 for IPv6, 6 for a MAC address.
 *
 * This is the earlier, length-discriminated form. RFC 9164 replaced it with tags 52 and 54, which name the
 * family in the tag number instead, and this one is kept because documents that predate the RFC still use it.
 * Prefer Ipv4Tag and Ipv6Tag for anything new.
 *
 * @see \CBOR\Test\Tag\IpAddressTagTest
 * @see https://datatracker.ietf.org/doc/html/rfc9164#appendix-A
 */
final class NetworkAddressTag extends Tag implements Normalizable
{
    private const MAC_LENGTH = 6;

    private const IPV4_LENGTH = 4;

    private const IPV6_LENGTH = 16;

    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        if (! $object instanceof ByteStringObject && ! $object instanceof IndefiniteLengthByteStringObject) {
            throw new InvalidArgumentException('This tag only accepts a Byte String object.');
        }
        if (! in_array($object->getLength(), [self::IPV4_LENGTH, self::MAC_LENGTH, self::IPV6_LENGTH], true)) {
            throw new InvalidArgumentException('This tag only accepts a 4, 6 or 16 byte Byte String object.');
        }

        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_NETWORK_ADDRESS;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_NETWORK_ADDRESS);

        return new self($ai, $data, $object);
    }

    /**
     * @return string a dotted quad, a colon separated IPv6 address, or a colon separated MAC address
     */
    public function normalize(): string
    {
        /** @var ByteStringObject|IndefiniteLengthByteStringObject $object */
        $object = $this->object;
        $value = $object->normalize();

        if (strlen($value) === self::MAC_LENGTH) {
            return implode(':', str_split(bin2hex($value), 2));
        }

        $address = inet_ntop($value);
        if ($address === false) {
            throw new InvalidArgumentException('Invalid address. Cannot be converted into a textual address.');
        }

        return $address;
    }
}
