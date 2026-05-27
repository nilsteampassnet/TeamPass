<?php

namespace Hackzilla\PasswordGenerator\Tests\Model\Option;

use Hackzilla\PasswordGenerator\Model\Option\IntegerOption;

class IntegerOptionTest extends \PHPUnit\Framework\TestCase
{
    public function testInstantiation()
    {
        $option = new IntegerOption();

        $this->assertInstanceOf('Hackzilla\PasswordGenerator\Model\Option\OptionInterface', $option);
        $this->assertInstanceOf('Hackzilla\PasswordGenerator\Model\Option\IntegerOption', $option);
    }

    public function testType()
    {
        $option = new IntegerOption();

        $this->assertSame(IntegerOption::TYPE_INTEGER, $option->getType());
    }

    /**
     * @dataProvider validValueProvider
     *
     * @param mixed $value
     */
    public function testValidValue($value)
    {
        $option = new IntegerOption();
        $option->setValue($value);

        $this->assertSame($option->getValue(), $value);
    }

    public function validValueProvider()
    {
        return array(
            array(1),
        );
    }

    /**
     * @dataProvider invalidValueProvider
     *
     * @param mixed $value
     */
    public function testInvalidValue($value)
    {
        $this->expectException(\InvalidArgumentException::class);

        $option = new IntegerOption();
        $option->setValue($value);
    }

    public function invalidValueProvider()
    {
        return array(
            array(true),
            array(false),
            array(null),
            array(1.1),
            array('a'),
        );
    }

    /**
     * @dataProvider minMaxProvider
     *
     * @param $min
     * @param $max
     * @param $value
     */
    public function testMinMax($min, $max, $value)
    {
        $option = new IntegerOption(array('min' => $min, 'max' => $max));
        $option->setValue($value);

        $this->assertSame($option->getValue(), $value);
    }

    public function minMaxProvider()
    {
        return array(
            array(0, 255, 10),
            array(10, 10, 10),
            array(10, 15, 10),
            array(-100, 0, -10),
            array(-100, -10, -50),
        );
    }

    /**
     * @dataProvider minMaxExceptionProvider
     *
     * @param $min
     * @param $max
     * @param $value
     */
    public function testMinMaxException($min, $max, $value)
    {
        $this->expectException(\InvalidArgumentException::class);

        $option = new IntegerOption(array('min' => $min, 'max' => $max));
        $option->setValue($value);
    }

    public function minMaxExceptionProvider()
    {
        return array(
            array(0, 255, 256),
            array(10, 10, 11),
            array(10, 15, -12),
            array(-100, 0, -101),
            array(-100, -10, -9),
        );
    }
}
