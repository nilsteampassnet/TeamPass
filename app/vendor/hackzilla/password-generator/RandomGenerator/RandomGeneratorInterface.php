<?php

namespace Hackzilla\PasswordGenerator\RandomGenerator;

interface RandomGeneratorInterface
{
    /**
     * Generates cryptographic random integers that are suitable for use where unbiased results are critical
     * (i.e. shuffling a Poker deck).
     *
     * @param int $min
     * @param int $max
     *
     * @return int
     */
    public function randomInteger($min, $max);
}
