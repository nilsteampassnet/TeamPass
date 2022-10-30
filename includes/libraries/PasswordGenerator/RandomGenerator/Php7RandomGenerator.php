<?php

namespace PasswordGenerator\RandomGenerator;

require_once(dirname(__FILE__).'/RandomGeneratorInterface.php');

/**
 * Class Php7RandomGenerator.
 */
class Php7RandomGenerator implements RandomGeneratorInterface
{
    /**
     * Generates cryptographic random integers that are suitable for use where unbiased results are critical (i.e. shuffling a Poker deck).
     * However requires PHP7.
     *
     * @param int $min
     * @param int $max
     *
     * @return int
     */
    public function randomInteger($min, $max)
    {
        return \random_int($min, $max);
    }
}
