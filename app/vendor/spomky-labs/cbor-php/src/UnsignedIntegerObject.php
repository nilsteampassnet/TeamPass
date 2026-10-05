<?php

declare(strict_types=1);

namespace CBOR;

use Brick\Math\BigInteger;
use InvalidArgumentException;

final class UnsignedIntegerObject extends AbstractCBORObject implements Normalizable
{
    use IntegerPayloadTrait;

    private const MAJOR_TYPE = self::MAJOR_TYPE_UNSIGNED_INTEGER;

    public function __construct(
        int $additionalInformation,
        private ?string $data
    ) {
        parent::__construct(self::MAJOR_TYPE, $additionalInformation);
    }

    public function __toString(): string
    {
        $result = parent::__toString();
        if ($this->data !== null) {
            $result .= $this->data;
        }

        return $result;
    }

    public static function createObjectForValue(int $additionalInformation, ?string $data): self
    {
        return new self($additionalInformation, $data);
    }

    public static function create(int $value): self
    {
        if ($value < 0) {
            throw new InvalidArgumentException('The value must be a positive integer.');
        }

        [$additionalInformation, $data] = self::payloadForInt($value);

        return new self($additionalInformation, $data);
    }

    public static function createFromHex(string $value): self
    {
        $integer = Utils::hexToBigInteger($value);

        return self::createBigInteger($integer);
    }

    public static function createFromString(string $value): self
    {
        $integer = BigInteger::of($value);

        return self::createBigInteger($integer);
    }

    public function getMajorType(): int
    {
        return self::MAJOR_TYPE;
    }

    /**
     * @return numeric-string
     */
    public function getValue(): string
    {
        if ($this->data === null) {
            return (string) $this->additionalInformation;
        }

        $argument = self::argumentFromPayload($this->data);
        if ($argument >= 0) {
            return (string) $argument;
        }

        return Utils::binToBigInteger($this->data)->toBase(10);
    }

    /**
     * @return numeric-string
     */
    public function normalize(): string
    {
        return $this->getValue();
    }

    private static function createBigInteger(BigInteger $integer): self
    {
        if ($integer->isLessThan(BigInteger::zero())) {
            throw new InvalidArgumentException('The value must be a positive integer.');
        }
        if ($integer->isGreaterThan(self::maximumArgument())) {
            throw new InvalidArgumentException(
                'Out of range. Please use UnsignedBigIntegerTag tag with ByteStringObject object instead.'
            );
        }

        [$additionalInformation, $data] = self::payloadForBigInteger($integer);

        return new self($additionalInformation, $data);
    }
}
