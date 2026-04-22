<?php

namespace Hackzilla\PasswordGenerator\Tests\RandomGenerator;

use Hackzilla\PasswordGenerator\RandomGenerator\NoRandomGenerator;

class NoRandomGeneratorTest extends \PHPUnit\Framework\TestCase
{
    private $_object;

    public function setup(): void
    {
        $this->_object = new NoRandomGenerator();
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
    public function testRandomGenerator($min, $max)
    {
        $randomInteger = $this->_object->randomInteger($min, $max);

        $this->assertSame($min, $randomInteger);
    }
}
