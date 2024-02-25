<?php

declare(strict_types=1);

namespace ZxcvbnPhp\Math\Impl;

class BinomialProviderFloat64 extends AbstractBinomialProvider
{
    protected function calculate(int $n, int $k): float
    {
        $c = 1.0;

        for ($i = 1; $i <= $k; $i++, $n--) {
            // We're aiming for $c * $n / $i, but the $c * $n part could cause us to lose precision, so use $c / $i * $n instead. The caveat
            // here is that in order to get a precise answer, we need to minimize the chances of going above ~2^52.  This is mitigated
            // somewhat by dealing with whole part and the remainder separately, but it's not perfect and could overflow in practice, which
            // would result in a loss of precision.
            $c = floor($c / $i) * $n + floor(fmod($c, $i) * $n / $i);
        }

        return $c;
    }
}