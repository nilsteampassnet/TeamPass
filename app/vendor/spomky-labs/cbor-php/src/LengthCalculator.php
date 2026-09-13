<?php

declare(strict_types=1);

namespace CBOR;

use function chr;
use function count;
use function strlen;

final class LengthCalculator
{
    /**
     * @return array{int, null|string}
     */
    public static function getLengthOfString(string $data): array
    {
        $length = strlen($data);

        return self::computeLength($length);
    }

    /**
     * @param array<int|string, mixed> $data
     *
     * @return array{int, null|string}
     */
    public static function getLengthOfArray(array $data): array
    {
        $length = count($data);

        return self::computeLength($length);
    }

    /**
     * @return array{int, null|string}
     */
    private static function computeLength(int $length): array
    {
        return match (true) {
            $length <= 23 => [$length, null],
            $length <= 0xFF => [24, chr($length)],
            $length <= 0xFFFF => [25, pack('n', $length)],
            $length <= 0xFFFFFFFF => [26, pack('N', $length)],
            default => [27, pack('J', $length)],
        };
    }
}
