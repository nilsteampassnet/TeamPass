<?php

declare(strict_types=1);

namespace CBOR\OtherObject;

use CBOR\Normalizable;
use CBOR\OtherObject as Base;
use InvalidArgumentException;
use function strlen;

final class DoublePrecisionFloatObject extends Base implements Normalizable
{
    use FloatBitsTrait;

    public static function supportedAdditionalInformation(): array
    {
        return [self::OBJECT_DOUBLE_PRECISION_FLOAT];
    }

    public static function createFromFloat(float $number): self
    {
        $value = match (true) {
            is_nan($number) => self::hex2binSafe('7FF8000000000000'),
            is_infinite($number) && $number > 0 => self::hex2binSafe('7FF0000000000000'),
            is_infinite($number) && $number < 0 => self::hex2binSafe('FFF0000000000000'),
            default => (static fn (): string => unpack('S', "\x01\x00")[1] === 1 ? strrev(pack('d', $number)) : pack(
                'd',
                $number
            ))(),
        };

        return new self(self::OBJECT_DOUBLE_PRECISION_FLOAT, $value);
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data): Base
    {
        return new self($additionalInformation, $data);
    }

    public static function create(string $value): self
    {
        if (strlen($value) !== 8) {
            throw new InvalidArgumentException('The value is not a valid double precision floating point');
        }

        return new self(self::OBJECT_DOUBLE_PRECISION_FLOAT, $value);
    }

    public function normalize(): float
    {
        return $this->value('E');
    }

    public function getExponent(): int
    {
        return $this->bits('J') >> 52 & 0b11111111111;
    }

    public function getMantissa(): int
    {
        return $this->bits('J') & 0xFFFFFFFFFFFFF;
    }

    public function getSign(): int
    {
        // "J" is unpacked signed, so the sign bit of the binary64 payload is the sign of the PHP integer.
        return $this->bits('J') < 0 ? -1 : 1;
    }

    private static function hex2binSafe(string $hex): string
    {
        $result = hex2bin($hex);
        if ($result === false) {
            throw new InvalidArgumentException('Invalid hex string');
        }
        return $result;
    }
}
