<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\CBORObject;
use CBOR\IndefiniteLengthMapObject;
use CBOR\MapObject;
use CBOR\Normalizable;
use CBOR\Tag;
use function count;
use InvalidArgumentException;

/**
 * Tag 261: a network address prefix, given as the single entry map {address bytes: prefix length}.
 *
 * As with tag 260, RFC 9164 supersedes it with the prefix form of tags 52 and 54. It is kept for the documents
 * that predate the RFC.
 *
 * @see \CBOR\Test\Tag\IpAddressTagTest
 * @see https://datatracker.ietf.org/doc/html/rfc9164#appendix-A
 */
final class NetworkAddressPrefixTag extends Tag implements Normalizable
{
    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        if (! $object instanceof MapObject && ! $object instanceof IndefiniteLengthMapObject) {
            throw new InvalidArgumentException('This tag only accepts a Map object.');
        }
        if (count($object) !== 1) {
            throw new InvalidArgumentException('This tag only accepts a Map object that contains 1 item.');
        }

        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_NETWORK_ADDRESS_PREFIX;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_NETWORK_ADDRESS_PREFIX);

        return new self($ai, $data, $object);
    }

    /**
     * The key of the single entry is the address, in the same raw byte form NetworkAddressTag carries, so it is
     * returned as it stands rather than printed: a binary map key has no textual form to fall back on.
     *
     * @return array<int|string, mixed>
     */
    public function normalize(): array
    {
        /** @var IndefiniteLengthMapObject|MapObject $object */
        $object = $this->object;

        return $object->normalize();
    }
}
