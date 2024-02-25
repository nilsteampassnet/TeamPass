<?php

declare(strict_types=1);

namespace ZxcvbnPhp\Math\Impl;

use TypeError;

class BinomialProviderInt64 extends AbstractBinomialProviderWithFallback
{
    protected function initFallbackProvider(): AbstractBinomialProvider
    {
        return new BinomialProviderFloat64();
    }

    protected function tryCalculate(int $n, int $k): ?float
    {
        try {
            $c = 1;

            for ($i = 1; $i <= $k; $i++, $n--) {
                // We're aiming for $c * $n / $i, but the $c * $n part could overflow, so use $c / $i * $n instead. The caveat here is that in
                // order to get a precise answer, we need to avoid floats, which means we need to deal with whole part and the remainder
                // separately.
                $c = intdiv($c, $i) * $n + intdiv($c % $i * $n, $i);
            }

            return (float)$c;
        } catch (TypeError $ex) {
            return null;
        }
    }
}