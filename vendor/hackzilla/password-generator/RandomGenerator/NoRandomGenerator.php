<?php

namespace Hackzilla\PasswordGenerator\RandomGenerator;

/**
 * Class NoRandomGenerator.
 */
class NoRandomGenerator implements RandomGeneratorInterface
{
    /**
     * This return the $min, and is for testing only.
     *
     * @param int $min
     * @param int $max
     *
     * @return int
     */
    public function randomInteger($min, $max)
    {
        return $min;
    }
}
