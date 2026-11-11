<?php

declare(strict_types=1);

namespace OTPHP;

use function is_int;
use OTPHP\Exception\InvalidParameterException;
use const PHP_INT_MAX;
use Psr\Clock\ClockInterface;

/**
 * @readonly
 *
 * @see \OTPHP\Test\TOTPTest
 */
final class TOTP extends OTP implements TOTPInterface
{
    private readonly ClockInterface $clock;

    public function __construct(string $secret, ?ClockInterface $clock = null)
    {
        parent::__construct($secret);
        if ($clock === null) {
            trigger_deprecation(
                'spomky-labs/otphp',
                '11.3.0',
                'The parameter "$clock" will become mandatory in 12.0.0. Please set a valid PSR Clock implementation instead of "null".'
            );
            $clock = new InternalClock();
        }

        $this->clock = $clock;
    }

    public static function create(
        null|string $secret = null,
        int $period = self::DEFAULT_PERIOD,
        string $digest = self::DEFAULT_DIGEST,
        int $digits = self::DEFAULT_DIGITS,
        int $epoch = self::DEFAULT_EPOCH,
        ?ClockInterface $clock = null,
        ?int $secretSize = null
    ): self {
        $totp = $secret !== null
            ? self::createFromSecret($secret, $clock)
            : self::generate($clock, $secretSize)
        ;
        $totp->setPeriod($period);
        $totp->setDigest($digest);
        $totp->setDigits($digits);
        $totp->setEpoch($epoch);

        return $totp;
    }

    public static function createFromSecret(string $secret, ?ClockInterface $clock = null): self
    {
        $totp = new self($secret, $clock);
        $totp->setPeriod(self::DEFAULT_PERIOD);
        $totp->setDigest(self::DEFAULT_DIGEST);
        $totp->setDigits(self::DEFAULT_DIGITS);
        $totp->setEpoch(self::DEFAULT_EPOCH);

        return $totp;
    }

    /**
     * @param positive-int|null $secretSize
     */
    public static function generate(?ClockInterface $clock = null, ?int $secretSize = null): self
    {
        return self::createFromSecret(self::generateSecret($secretSize), $clock);
    }

    public function getPeriod(): int
    {
        $value = $this->getParameter('period');
        (is_int($value) && $value > 0) || throw new InvalidParameterException(
            'Invalid "period" parameter.',
            'period',
            $value
        );

        return $value;
    }

    public function getEpoch(): int
    {
        $value = $this->getParameter('epoch');
        (is_int($value) && $value >= 0) || throw new InvalidParameterException(
            'Invalid "epoch" parameter.',
            'epoch',
            $value
        );

        return $value;
    }

    public function expiresIn(): int
    {
        $period = $this->getPeriod();
        $epoch = $this->getEpoch();
        $timestamp = $this->clock->now()
            ->getTimestamp();
        // Before the epoch there is no current OTP (now() refuses it too), and the negative remainder would push the
        // result past the period, up to an integer overflow for the largest periods.
        $timestamp >= $epoch || throw new InvalidParameterException(
            'The timestamp must be greater than or equal to the epoch.',
            'timestamp',
            $timestamp
        );

        return $period - (($timestamp - $epoch) % $period);
    }

    /**
     * The OTP at the specified input.
     *
     * @param 0|positive-int $input
     */
    public function at(int $input): string
    {
        return $this->generateOTP($this->timecode($input));
    }

    public function now(): string
    {
        $timestamp = $this->clock->now()
            ->getTimestamp();
        $timestamp >= 0 || throw new InvalidParameterException(
            'The timestamp must return a positive integer.',
            'timestamp',
            $timestamp
        );

        return $this->at($timestamp);
    }

    /**
     * If no timestamp is provided, the OTP is verified at the actual timestamp. When used, the leeway parameter will
     * allow time drift. The passed value is in seconds.
     *
     * @param 0|positive-int $timestamp
     * @param null|0|positive-int $leeway
     */
    public function verify(string $otp, null|int $timestamp = null, null|int $leeway = null): bool
    {
        $timestamp ??= $this->clock->now()
            ->getTimestamp();
        $timestamp >= 0 || throw new InvalidParameterException(
            'Timestamp must be at least 0.',
            'timestamp',
            $timestamp
        );

        if ($leeway === null) {
            return $this->compareOTP($this->at($timestamp), $otp);
        }

        $leeway = abs($leeway);
        $leeway < $this->getPeriod() || throw new InvalidParameterException(
            'The leeway must be lower than the TOTP period',
            'leeway',
            $leeway
        );
        $timestampMinusLeeway = $timestamp - $leeway;
        $timestampMinusLeeway >= 0 || throw new InvalidParameterException(
            'The timestamp must be greater than or equal to the leeway.',
            'timestamp',
            $timestamp
        );
        $timestamp <= PHP_INT_MAX - $leeway || throw new InvalidParameterException(
            'The timestamp must be lower than or equal to PHP_INT_MAX minus the leeway.',
            'timestamp',
            $timestamp
        );

        // There is no time step before the epoch: the "timestamp - leeway" candidate only exists
        // when it is at or after the epoch. Otherwise at() would throw instead of verify() answering.
        return ($timestampMinusLeeway >= $this->getEpoch() && $this->compareOTP($this->at($timestampMinusLeeway), $otp))
            || $this->compareOTP($this->at($timestamp), $otp)
            || $this->compareOTP($this->at($timestamp + $leeway), $otp);
    }

    public function getProvisioningUri(): string
    {
        $params = [];
        if ($this->getPeriod() !== 30) {
            $params['period'] = $this->getPeriod();
        }

        if ($this->getEpoch() !== 0) {
            $params['epoch'] = $this->getEpoch();
        }

        return $this->generateURI('totp', $params);
    }

    public function setPeriod(int $period): void
    {
        $this->setParameter('period', $period);
    }

    public function withPeriod(int $period): self
    {
        $otp = clone $this;
        $otp->setParameter('period', $period);

        return $otp;
    }

    public function setEpoch(int $epoch): void
    {
        $this->setParameter('epoch', $epoch);
    }

    public function withEpoch(int $epoch): self
    {
        $otp = clone $this;
        $otp->setParameter('epoch', $epoch);

        return $otp;
    }

    /**
     * @return array<non-empty-string, callable>
     */
    protected function getParameterMap(): array
    {
        return [
            ...parent::getParameterMap(),
            'period' => static function (mixed $value): int {
                $value = self::toInt('period', $value);
                $value > 0 || throw new InvalidParameterException('Period must be at least 1.', 'period', $value);

                return $value;
            },
            'epoch' => static function (mixed $value): int {
                $value = self::toInt('epoch', $value);
                $value >= 0 || throw new InvalidParameterException(
                    'Epoch must be greater than or equal to 0.',
                    'epoch',
                    $value
                );

                return $value;
            },
        ];
    }

    /**
     * @param array<non-empty-string, mixed> $options
     */
    protected function filterOptions(array &$options): void
    {
        parent::filterOptions($options);

        if (isset($options['epoch']) && $options['epoch'] === 0) {
            unset($options['epoch']);
        }

        ksort($options);
    }

    /**
     * @param 0|positive-int $timestamp
     *
     * @return 0|positive-int
     */
    private function timecode(int $timestamp): int
    {
        $elapsed = $timestamp - $this->getEpoch();
        $elapsed >= 0 || throw new InvalidParameterException(
            'Timecode must be at least 0. The timestamp must be greater than or equal to the epoch.',
            'timecode',
            $elapsed
        );

        // intdiv() keeps the time step exact: "/" and floor() go through a float, which loses precision above 2^53.
        // Both operands are non-negative, so max() only states the 0|positive-int result for static analysis.
        return max(0, intdiv($elapsed, $this->getPeriod()));
    }
}
