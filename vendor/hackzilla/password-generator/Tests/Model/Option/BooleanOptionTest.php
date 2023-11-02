<?php

namespace Hackzilla\PasswordGenerator\Tests\Model\Option;

use Hackzilla\PasswordGenerator\Model\Option\BooleanOption;

class BooleanOptionTest extends \PHPUnit\Framework\TestCase
{
    public function testInstantiation()
    {
        $option = new BooleanOption();

        $this->assertInstanceOf('Hackzilla\PasswordGenerator\Model\Option\OptionInterface', $option);
        $this->assertInstanceOf('Hackzilla\PasswordGenerator\Model\Option\BooleanOption', $option);
    }

    public function testType()
    {
        $option = new BooleanOption();

        $this->assertSame(BooleanOption::TYPE_BOOLEAN, $option->getType());
    }

    /**
     * @dataProvider validValueProvider
     *
     * @param mixed $value
     */
    public function testValidValue($value)
    {
        $option = new BooleanOption();
        $option->setValue($value);

        $this->assertSame($option->getValue(), $value);
    }

    public function validValueProvider()
    {
        return array(
            array(true),
            array(false),
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

        $option = new BooleanOption();
        $option->setValue($value);
    }

    public function invalidValueProvider()
    {
        return array(
            array(1),
            array(null),
            array(1.1),
            array('a'),
        );
    }
}
