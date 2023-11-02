<?php

namespace Hackzilla\PasswordGenerator\RandomGenerator;

/**
 * Class Php5RandomGenerator.
 */
class Php5RandomGenerator implements RandomGeneratorInterface
{
    /**
     * This function does not generate cryptographically secure values, and should not be used for cryptographic purposes.
     * However this is the best PHP5.3 offers.
     *
     * @param int $min
     * @param int $max
     *
     * @return int
     */
    public function randomInteger($min, $max)
    {
        return \mt_rand($min, $max);
    }
}
