<?php

declare(strict_types=1);

namespace CBOR;

use Brick\Math\BigInteger;
use InvalidArgumentException;

final class NegativeIntegerObject extends AbstractCBORObject implements Normalizable
{
    use IntegerPayloadTrait;

    private const MAJOR_TYPE = self::MAJOR_TYPE_NEGATIVE_INTEGER;

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
        if ($value >= 0) {
            throw new InvalidArgumentException('The value must be a negative integer.');
        }

        // Exact for every negative PHP integer: -1 - PHP_INT_MIN is PHP_INT_MAX, so the argument never overflows.
        [$additionalInformation, $data] = self::payloadForInt(-1 - $value);

        return new self($additionalInformation, $data);
    }

    public static function createFromString(string $value): self
    {
        $integer = BigInteger::of($value);

        return self::createBigInteger($integer);
    }

    /**
     * @return numeric-string
     */
    public function getValue(): string
    {
        if ($this->data === null) {
            return (string) (-1 - $this->additionalInformation);
        }

        $argument = self::argumentFromPayload($this->data);
        if ($argument >= 0) {
            return (string) (-1 - $argument);
        }

        return BigInteger::of(-1)->minus(Utils::binToBigInteger($this->data))
            ->toBase(10)
        ;
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
        if ($integer->isGreaterThanOrEqualTo(BigInteger::zero())) {
            throw new InvalidArgumentException('The value must be a negative integer.');
        }

        $argument = BigInteger::of(-1)->minus($integer);
        if ($argument->isGreaterThan(self::maximumArgument())) {
            throw new InvalidArgumentException(
                'Out of range. Please use NegativeBigIntegerTag tag with ByteStringObject object instead.'
            );
        }

        [$additionalInformation, $data] = self::payloadForBigInteger($argument);

        return new self($additionalInformation, $data);
    }
}
