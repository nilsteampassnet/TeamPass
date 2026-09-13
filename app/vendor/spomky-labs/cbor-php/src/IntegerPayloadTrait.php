<?php

declare(strict_types=1);

namespace CBOR;

use Brick\Math\BigInteger;
use function chr;
use InvalidArgumentException;
use function ord;
use const PHP_INT_MAX;
use const STR_PAD_LEFT;
use function strlen;

/**
 * Shared head/payload conversion for the two integer objects.
 *
 * Both major type 0 and major type 1 encode a single unsigned argument: the value itself for the former, -1 - value
 * for the latter. RFC 8949 section 3 gives that argument five encodings and selects the shortest one that holds it,
 * which section 4.2 then requires. The bounds below are therefore inclusive: 255 is the largest one-byte argument,
 * not the first two-byte one.
 *
 * Arguments up to PHP_INT_MAX are packed with native integer arithmetic. brick/math is reached for only in the
 * 8-byte range above it, which no PHP integer can hold and which callers supply through createFromString() or
 * createFromHex().
 *
 * @internal
 */
trait IntegerPayloadTrait
{
    /**
     * Largest argument a CBOR head can carry, 2^64 - 1. Beyond it a bignum tag is the only representation.
     */
    private static function maximumArgument(): BigInteger
    {
        return BigInteger::fromBase('FFFFFFFFFFFFFFFF', 16);
    }

    /**
     * @return array{int, null|string} the additional information and its payload
     */
    private static function payloadForInt(int $argument): array
    {
        return match (true) {
            $argument <= 23 => [$argument, null],
            $argument <= 0xFF => [CBORObject::LENGTH_1_BYTE, chr($argument)],
            $argument <= 0xFFFF => [CBORObject::LENGTH_2_BYTES, pack('n', $argument)],
            $argument <= 0xFFFFFFFF => [CBORObject::LENGTH_4_BYTES, pack('N', $argument)],
            default => [CBORObject::LENGTH_8_BYTES, pack('J', $argument)],
        };
    }

    /**
     * @return array{int, null|string} the additional information and its payload
     */
    private static function payloadForBigInteger(BigInteger $argument): array
    {
        if ($argument->isLessThanOrEqualTo(BigInteger::of(PHP_INT_MAX))) {
            return self::payloadForInt($argument->toInt());
        }

        return [CBORObject::LENGTH_8_BYTES, self::hex2bin(str_pad($argument->toBase(16), 16, '0', STR_PAD_LEFT))];
    }

    /**
     * The argument carried by a decoded head.
     *
     * A CBOR argument is unsigned, so a negative result cannot be one: it reports that the argument sits above
     * PHP_INT_MAX and has no PHP integer representation, and the caller shall fall back to brick/math.
     */
    private static function argumentFromPayload(string $data): int
    {
        $length = strlen($data);
        if ($length > 8) {
            return -1;
        }

        $argument = 0;
        for ($i = 0; $i < $length; ++$i) {
            $argument = ($argument << 8) | ord($data[$i]);
        }

        // Only the eighth byte can carry the argument past PHP_INT_MAX, where the shift wraps it into the sign bit.
        return $argument;
    }

    private static function hex2bin(string $data): string
    {
        $result = hex2bin($data);
        if ($result === false) {
            throw new InvalidArgumentException('Unable to convert the data');
        }

        return $result;
    }
}
