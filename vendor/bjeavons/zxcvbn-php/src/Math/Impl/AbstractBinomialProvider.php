<?php

declare(strict_types=1);

namespace ZxcvbnPhp\Math\Impl;

use ZxcvbnPhp\Math\BinomialProvider;

abstract class AbstractBinomialProvider implements BinomialProvider
{
    public function binom(int $n, int $k): float
    {
        if ($k < 0 || $n < 0) {
            throw new \DomainException("n and k must be non-negative");
        }

        if ($k > $n) {
            return 0;
        }

        // $k and $n - $k will always produce the same value, so use smaller of the two
        $k = min($k, $n - $k);

        return $this->calculate($n, $k);
    }

    abstract protected function calculate(int $n, int $k): float;
}