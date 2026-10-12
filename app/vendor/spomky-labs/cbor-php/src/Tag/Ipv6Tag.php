<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\ByteStringObject;
use CBOR\CBORObject;
use CBOR\Tag;
use function inet_pton;
use InvalidArgumentException;
use function strlen;

/**
 * Tag 54: an IPv6 address, prefix or zoned address (RFC 9164).
 *
 * @see \CBOR\Test\Tag\IpAddressTagTest
 * @see https://datatracker.ietf.org/doc/html/rfc9164
 */
final class Ipv6Tag extends AbstractIpAddressTag
{
    private const ADDRESS_LENGTH = 16;

    public static function getTagId(): int
    {
        return self::TAG_IPV6;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_IPV6);

        return new self($ai, $data, $object);
    }

    /**
     * Builds the tag from the colon separated form.
     */
    public static function createFromAddress(string $address): self
    {
        $bytes = inet_pton($address);
        if ($bytes === false || strlen($bytes) !== self::ADDRESS_LENGTH) {
            throw new InvalidArgumentException('Invalid data. The value is not a valid IPv6 address.');
        }

        return self::create(ByteStringObject::create($bytes));
    }

    protected static function getAddressLength(): int
    {
        return self::ADDRESS_LENGTH;
    }
}
