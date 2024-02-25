<?php

declare(strict_types=1);

namespace ZxcvbnPhp\Math;

use ZxcvbnPhp\Math\Impl\BinomialProviderPhp73Gmp;
use ZxcvbnPhp\Math\Impl\BinomialProviderFloat64;
use ZxcvbnPhp\Math\Impl\BinomialProviderInt64;

class Binomial
{
    private static $provider = null;

    private function __construct()
    {
        throw new \LogicException(__CLASS__ . " is static");
    }

    /**
     * Calculate binomial coefficient (n choose k).
     *
     * @param int $n
     * @param int $k
     * @return float
     */
    public static function binom(int $n, int $k): float
    {
        return self::getProvider()->binom($n, $k);
    }

    public static function getProvider(): BinomialProvider
    {
        if (self::$provider === null) {
            self::$provider = self::initProvider();
        }

        return self::$provider;
    }

    /**
     * @return string[]
     */
    public static function getUsableProviderClasses(): array
    {
        // In order of priority.  The first provider with a value of true will be used.
        $possibleProviderClasses = [
            BinomialProviderPhp73Gmp::class => function_exists('gmp_binomial'),
            BinomialProviderInt64::class    => PHP_INT_SIZE >= 8,
            BinomialProviderFloat64::class  => PHP_FLOAT_DIG >= 15,
        ];

        $possibleProviderClasses = array_filter($possibleProviderClasses);

        return array_keys($possibleProviderClasses);
    }

    private static function initProvider(): BinomialProvider
    {
        $providerClasses = self::getUsableProviderClasses();

        if (!$providerClasses) {
            throw new \LogicException("No valid providers");
        }

        $bestProviderClass = reset($providerClasses);

        return new $bestProviderClass();
    }
}