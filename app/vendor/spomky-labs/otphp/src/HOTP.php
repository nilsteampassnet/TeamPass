<?php

declare(strict_types=1);

namespace OTPHP;

use function is_int;
use OTPHP\Exception\InvalidParameterException;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * Note: This class is not marked as readonly because the verify() method
 * updates the counter state on successful verification, which is an intentional
 * side-effect to prevent OTP reuse.
 *
 * @see \OTPHP\Test\HOTPTest
 */
final class HOTP extends OTP implements HOTPInterface
{
    private const DEFAULT_WINDOW = 0;

    /**
     * A successful verification moves the counter to the accepted value plus one,
     * so the last counter that can be accepted is PHP_INT_MAX - 1.
     */
    private const MAX_ACCEPTED_COUNTER = PHP_INT_MAX - 1;

    public static function create(
        null|string $secret = null,
        int $counter = self::DEFAULT_COUNTER,
        string $digest = self::DEFAULT_DIGEST,
        int $digits = self::DEFAULT_DIGITS,
        ?int $secretSize = null
    ): self {
        $htop = $secret !== null
            ? self::createFromSecret($secret)
            : self::generate($secretSize)
        ;
        $htop->setCounter($counter);
        $htop->setDigest($digest);
        $htop->setDigits($digits);

        return $htop;
    }

    public static function createFromSecret(string $secret): self
    {
        $htop = new self($secret);
        $htop->setCounter(self::DEFAULT_COUNTER);
        $htop->setDigest(self::DEFAULT_DIGEST);
        $htop->setDigits(self::DEFAULT_DIGITS);

        return $htop;
    }

    /**
     * @param positive-int|null $secretSize
     */
    public static function generate(?int $secretSize = null): self
    {
        return self::createFromSecret(self::generateSecret($secretSize));
    }

    /**
     * @return 0|positive-int
     */
    public function getCounter(): int
    {
        $value = $this->getParameter('counter');
        (is_int($value) && $value >= 0) || throw new InvalidParameterException(
            'Invalid "counter" parameter.',
            'counter',
            $value
        );

        return $value;
    }

    public function getProvisioningUri(): string
    {
        return $this->generateURI('hotp', [
            'counter' => $this->getCounter(),
        ]);
    }

    /**
     * If the counter is not provided, the OTP is verified at the actual counter.
     *
     * @param null|0|positive-int $counter
     */
    public function verify(string $otp, null|int $counter = null, null|int $window = null): bool
    {
        $counter >= 0 || throw new InvalidParameterException(
            'The counter must be at least 0.',
            'counter',
            $counter
        );

        if ($counter === null) {
            $counter = $this->getCounter();
        } elseif ($counter < $this->getCounter()) {
            return false;
        }

        return $this->verifyOtpWithWindow($otp, $counter, $window);
    }

    public function setCounter(int $counter): void
    {
        $this->setParameter('counter', $counter);
    }

    public function withCounter(int $counter): self
    {
        $otp = clone $this;
        $otp->setParameter('counter', $counter);

        return $otp;
    }

    /**
     * @return array<non-empty-string, callable>
     */
    protected function getParameterMap(): array
    {
        return [...parent::getParameterMap(), ...[
            'counter' => static function (mixed $value): int {
                $value = self::toInt('counter', $value);
                $value >= 0 || throw new InvalidParameterException(
                    'Counter must be at least 0.',
                    'counter',
                    $value
                );

                return $value;
            },
        ]];
    }

    private function updateCounter(int $counter): void
    {
        $this->setCounter($counter);
    }

    /**
     * @param null|0|positive-int $window
     */
    private function getWindow(null|int $window): int
    {
        $window ??= self::DEFAULT_WINDOW;
        // abs(PHP_INT_MIN) does not fit in an int and would return a float.
        $window !== PHP_INT_MIN || throw new InvalidParameterException(
            'The window must be greater than PHP_INT_MIN.',
            'window',
            $window
        );

        return abs($window);
    }

    /**
     * @param non-empty-string $otp
     * @param 0|positive-int $counter
     * @param null|0|positive-int $window
     */
    private function verifyOtpWithWindow(string $otp, int $counter, null|int $window): bool
    {
        $window = $this->getWindow($window);
        // Bounded without overflow: past PHP_INT_MAX, $i would become a float. An exhausted counter accepts nothing.
        $lastCounter = $counter > self::MAX_ACCEPTED_COUNTER - $window
            ? self::MAX_ACCEPTED_COUNTER
            : $counter + $window;

        for ($i = $counter; $i <= $lastCounter; ++$i) {
            if ($this->compareOTP($this->at($i), $otp)) {
                $this->updateCounter($i + 1);

                return true;
            }
        }

        return false;
    }
}
