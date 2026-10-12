<?php

declare(strict_types=1);

namespace CBOR\Tag;

use function bin2hex;
use CBOR\ByteStringObject;
use CBOR\CBORObject;
use CBOR\IndefiniteLengthByteStringObject;
use CBOR\Normalizable;
use CBOR\Tag;
use function hex2bin;
use InvalidArgumentException;
use function preg_match;
use function str_replace;
use function str_split;
use function strlen;
use function strtolower;
use function vsprintf;

/**
 * Tag 37: a binary UUID as defined by RFC 4122, in the 16 byte big endian form.
 *
 * The length is enforced: a UUID that is not 16 bytes is not a UUID, and normalize() would otherwise return a
 * string that merely looks like one.
 *
 * @see \CBOR\Test\Tag\RegistryTagsTest
 */
final class UuidTag extends Tag implements Normalizable
{
    private const LENGTH = 16;

    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        if (! $object instanceof ByteStringObject && ! $object instanceof IndefiniteLengthByteStringObject) {
            throw new InvalidArgumentException('This tag only accepts a Byte String object.');
        }
        if ($object->getLength() !== self::LENGTH) {
            throw new InvalidArgumentException('This tag only accepts a 16 byte Byte String object.');
        }

        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_UUID;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_UUID);

        return new self($ai, $data, $object);
    }

    /**
     * Builds the tag from the 8-4-4-4-12 textual form, with or without the dashes.
     */
    public static function createFromUuidString(string $uuid): self
    {
        $hexadecimal = str_replace('-', '', strtolower($uuid));
        if (preg_match('/^[0-9a-f]{32}$/', $hexadecimal) !== 1) {
            throw new InvalidArgumentException('Invalid data. The value is not a valid UUID.');
        }

        return self::create(ByteStringObject::create((string) hex2bin($hexadecimal)));
    }

    /**
     * @return non-empty-string the canonical 8-4-4-4-12 lowercase form
     */
    public function normalize(): string
    {
        /** @var ByteStringObject|IndefiniteLengthByteStringObject $object */
        $object = $this->object;
        $value = $object->normalize();
        if (strlen($value) !== self::LENGTH) {
            throw new InvalidArgumentException('Invalid data. The value is not a valid UUID.');
        }

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($value), 4));
    }
}
