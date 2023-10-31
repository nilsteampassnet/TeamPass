<?php

namespace Hackzilla\PasswordGenerator\Tests\RandomGenerator;

use Hackzilla\PasswordGenerator\RandomGenerator\Php7RandomGenerator;

/**
 * Class Php7RandomGeneratorTest.
 *
 * @requires PHP 7
 */
class Php7RandomGeneratorTest extends \PHPUnit\Framework\TestCase
{
    private $_object;

    public function setup(): void
    {
        $this->_object = new Php7RandomGenerator();
    }

    public function rangeProvider()
    {
        return array(
            array(1, 100),
            array(10, 20),
            array(0, 5),
            array(1, 2),
        );
    }

    /**
     * @dataProvider rangeProvider
     *
     * @param int $min
     * @param int $max
     */
    public function testRandomGenerator($min, $max): void
    {
        $randomInteger = $this->_object->randomInteger($min, $max);

        $this->assertGreaterThanOrEqual($min, $randomInteger);
        $this->assertLessThanOrEqual($max, $randomInteger);
    }
}
