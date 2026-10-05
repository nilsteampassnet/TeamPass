<?php

declare(strict_types=1);

namespace CBOR;

use Brick\Math\BigInteger;
use Brick\Math\Exception\IntegerOverflowException;
use Brick\Math\Exception\MathException;
use InvalidArgumentException;
use function is_string;
use function ord;
use function sprintf;
use function strlen;

/**
 * @internal
 */
abstract class Utils
{
    public static function binToInt(string $value): int
    {
        // A CBOR head argument is at most 8 bytes and nearly always fits a PHP integer, so build it from the bytes
        // and keep brick/math for the one case that does not: an argument above PHP_INT_MAX, which wraps negative
        // here and is handed over below for the range error.
        $length = strlen($value);
        if ($length <= 8) {
            $result = 0;
            for ($i = 0; $i < $length; ++$i) {
                $result = ($result << 8) | ord($value[$i]);
            }

            if ($result >= 0) {
                return $result;
            }
        }

        return self::bigIntegerToInt(self::binToBigInteger($value));
    }

    public static function binToBigInteger(string $value): BigInteger
    {
        return self::hexToBigInteger(bin2hex($value));
    }

    public static function hexToInt(string $value): int
    {
        return self::bigIntegerToInt(self::hexToBigInteger($value));
    }

    public static function hexToBigInteger(string $value): BigInteger
    {
        if ($value === '') {
            throw new InvalidArgumentException('Invalid data. The value shall not be empty.');
        }

        try {
            return BigInteger::fromBase($value, 16);
        } catch (MathException $throwable) {
            throw new InvalidArgumentException(
                sprintf('Invalid data. "%s" is not a valid hexadecimal value.', $value),
                0,
                $throwable
            );
        }
    }

    public static function hexToString(string $value): string
    {
        return self::hexToBigInteger(bin2hex($value))->toBase(10);
    }

    public static function decode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid data provided');
        }

        return $decoded;
    }

    /**
     * @param mixed|null $data
     */
    public static function assertString($data, ?string $message = null): void
    {
        if (! is_string($data)) {
            throw new InvalidArgumentException($message ?? '');
        }
    }

    /**
     * CBOR allows 8 byte lengths, counts and tag numbers, which may exceed PHP_INT_MAX. Brick\Math signals that with
     * an IntegerOverflowException, which sits outside the error contract of this library: callers guard the parse
     * with InvalidArgumentException, so the overflow is translated here rather than surfacing to them raw.
     */
    private static function bigIntegerToInt(BigInteger $value): int
    {
        try {
            return $value->toInt();
        } catch (IntegerOverflowException $throwable) {
            throw new InvalidArgumentException(
                sprintf('Out of range. "%s" cannot be represented as a PHP integer.', $value->toBase(10)),
                0,
                $throwable
            );
        }
    }
}
