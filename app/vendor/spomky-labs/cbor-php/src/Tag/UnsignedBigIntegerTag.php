<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\ByteStringObject;
use CBOR\CBORObject;
use CBOR\IndefiniteLengthByteStringObject;
use CBOR\Normalizable;
use CBOR\Tag;
use CBOR\Utils;
use InvalidArgumentException;
use function sprintf;
use function strlen;

final class UnsignedBigIntegerTag extends Tag implements Normalizable
{
    /**
     * The maximum length, in bytes, accepted for the wrapped byte string.
     *
     * Turning the big-endian byte string into the decimal string this tag normalizes to is a base conversion, and
     * brick/math performs it in time quadratic in the length on every calculator but GMP. ext-gmp and ext-bcmath are
     * only suggested by this package, so the slowest of the three is what a default installation runs: a 500 byte
     * big number already cost 1.8 s there, and 2 kB close to a hundred. Since a big number used as a map key is
     * normalized by decode() itself, that was reachable without the application ever asking for the value. 256 bytes
     * is a 2048 bit integer, past any number this tag is used to carry, and bounds the conversion to well under a
     * second on the slowest calculator.
     */
    public const MAX_BYTE_LENGTH = 256;

    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        if (! $object instanceof ByteStringObject && ! $object instanceof IndefiniteLengthByteStringObject) {
            throw new InvalidArgumentException('This tag only accepts a Byte String object.');
        }

        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_UNSIGNED_BIG_NUM;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): Tag
    {
        [$ai, $data] = self::determineComponents(self::TAG_UNSIGNED_BIG_NUM);

        return new self($ai, $data, $object);
    }

    public function normalize(): string
    {
        /** @var ByteStringObject|IndefiniteLengthByteStringObject $object */
        $object = $this->object;
        $value = $object->normalize();
        if ($value === '') {
            // RFC 8949 section 3.4.3: the empty byte string is the preferred serialization of zero.
            return '0';
        }
        self::assertLengthIsWithinBounds($value);

        return Utils::hexToString($value);
    }

    /**
     * @param string $value the big-endian byte string this tag wraps
     */
    private static function assertLengthIsWithinBounds(string $value): void
    {
        $length = strlen($value);
        if ($length > self::MAX_BYTE_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'The big number is out of range. Its byte string shall not exceed %d bytes, got %d.',
                self::MAX_BYTE_LENGTH,
                $length
            ));
        }
    }
}
