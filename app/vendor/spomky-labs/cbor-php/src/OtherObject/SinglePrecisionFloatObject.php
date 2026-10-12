<?php

declare(strict_types=1);

namespace CBOR\OtherObject;

use CBOR\Normalizable;
use CBOR\OtherObject as Base;
use InvalidArgumentException;
use function strlen;

final class SinglePrecisionFloatObject extends Base implements Normalizable
{
    use FloatBitsTrait;

    public static function supportedAdditionalInformation(): array
    {
        return [self::OBJECT_SINGLE_PRECISION_FLOAT];
    }

    public static function createFromFloat(float $number): self
    {
        $value = match (true) {
            is_nan($number) => self::hex2binSafe('7FC00000'),
            is_infinite($number) && $number > 0 => self::hex2binSafe('7F800000'),
            is_infinite($number) && $number < 0 => self::hex2binSafe('FF800000'),
            default => (static fn (): string => unpack('S', "\x01\x00")[1] === 1 ? strrev(pack('f', $number)) : pack(
                'f',
                $number
            ))(),
        };

        return new self(self::OBJECT_SINGLE_PRECISION_FLOAT, $value);
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data): Base
    {
        return new self($additionalInformation, $data);
    }

    public static function create(string $value): self
    {
        if (strlen($value) !== 4) {
            throw new InvalidArgumentException('The value is not a valid single precision floating point');
        }

        return new self(self::OBJECT_SINGLE_PRECISION_FLOAT, $value);
    }

    public function normalize(): float
    {
        return $this->value('G');
    }

    public function getExponent(): int
    {
        return $this->bits('N') >> 23 & 0b11111111;
    }

    public function getMantissa(): int
    {
        return $this->bits('N') & 0x7FFFFF;
    }

    public function getSign(): int
    {
        return ($this->bits('N') >> 31 & 1) === 1 ? -1 : 1;
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
