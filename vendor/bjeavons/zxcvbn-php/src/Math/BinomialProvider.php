<?php

declare(strict_types=1);

namespace ZxcvbnPhp\Math;

interface BinomialProvider
{
    /**
     * Calculate binomial coefficient (n choose k).
     *
     * @param int $n
     * @param int $k
     * @return float
     */
    public function binom(int $n, int $k): float;
}