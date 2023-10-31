<?php

namespace Hackzilla\PasswordGenerator\Tests\Model\Option;

use Hackzilla\PasswordGenerator\Model\Option\Option;

class OptionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider typeProvider
     *
     * @param string $type
     * @param string $namespace
     */
    public function testCreateFromType($type, $namespace)
    {
        $option = Option::createFromType($type);

        $this->assertInstanceOf('Hackzilla\PasswordGenerator\Model\Option\OptionInterface', $option);
        $this->assertInstanceOf($namespace, $option);
        $this->assertSame($type, $option->getType());
    }

    public function testCreateFromTypeNull()
    {
        $this->assertNull(Option::createFromType('fail'));
    }

    public function typeProvider()
    {
        return array(
            array(Option::TYPE_BOOLEAN, 'Hackzilla\PasswordGenerator\Model\Option\BooleanOption'),
            array(Option::TYPE_INTEGER, 'Hackzilla\PasswordGenerator\Model\Option\IntegerOption'),
            array(Option::TYPE_STRING, 'Hackzilla\PasswordGenerator\Model\Option\StringOption'),
        );
    }

    public function testDefault()
    {
        $option = new OptionClass();
        $this->assertSame($option->getValue(), null);

        $option = new OptionClass(array('default' => 1));
        $this->assertSame($option->getValue(), 1);

        $option = new OptionClass(array('default' => 'a'));
        $this->assertSame($option->getValue(), 'a');
    }

    /**
     * @dataProvider valueProvider
     *
     * @param mixed $value
     */
    public function testValue($value)
    {
        $option = new OptionClass();
        $option->setValue($value);

        $this->assertSame($option->getValue(), $value);
    }

    public function valueProvider()
    {
        return array(
            array(true),
            array(1),
            array(null),
            array(1.1),
            array('a'),
        );
    }
}
