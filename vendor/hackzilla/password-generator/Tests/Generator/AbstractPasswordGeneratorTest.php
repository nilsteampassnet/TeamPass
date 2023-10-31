<?php

namespace Hackzilla\PasswordGenerator\Tests\Generator;

class AbstractPasswordGeneratorTest extends \PHPUnit\Framework\TestCase
{
    private $_object;

    public function setup(): void
    {
        $this->_object = new AbstractPasswordGeneratorClass();
    }

    /**
     * @dataProvider generatePasswordsProvider
     *
     * @param $passwordCount
     */
    public function testGeneratePasswordsException($passwordCount): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->_object->generatePasswords($passwordCount);
    }

    public function generatePasswordsProvider()
    {
        return array(
            array(''),
            array(null),
            array(-1),
            array(0.1),
            array(true),
        );
    }

    /**
     * @dataProvider lengthProvider
     *
     * @param $count
     */
    public function testGeneratePasswords($count): void
    {
        $passwords = $this->_object->generatePasswords($count);

        $this->assertSame(\count($passwords), $count);
    }

    /**
     * @return array
     */
    public function lengthProvider()
    {
        return array(
            array(1),
            array(4),
            array(8),
            array(16),
        );
    }

    /**
     * @dataProvider optionProvider
     *
     * @param string $option
     */
    public function testGetOption($option): void
    {
        $this->assertInstanceOf('Hackzilla\PasswordGenerator\Model\Option\OptionInterface', $this->_object->getOption($option));
    }

    public function optionProvider()
    {
        return array(
            array(AbstractPasswordGeneratorClass::OPTION_TEST_BOOLEAN),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_INTEGER),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_INTEGER_DEFAULT),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_STRING),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_STRING_DEFAULT),
        );
    }

    public function testGetPossibleOptions(): void
    {
        $this->assertTrue(is_array($this->_object->getOptions()));
        $this->assertSame(count($this->_object->getOptions()), 5);
    }

    public function testGetOptionValueDefault(): void
    {
        $this->assertSame(99, $this->_object->getOptionValue(AbstractPasswordGeneratorClass::OPTION_TEST_INTEGER_DEFAULT));
        $this->assertSame('test', $this->_object->getOptionValue(AbstractPasswordGeneratorClass::OPTION_TEST_STRING_DEFAULT));
    }

    /**
     * @dataProvider setOptionValueProvider
     *
     * @param $option
     * @param $value
     */
    public function testSetOptionValue($option, $value): void
    {
        $this->_object->setOptionValue($option, $value);
        $this->assertSame($this->_object->getOptionValue($option), $value);
    }

    /**
     * @return array
     */
    public function setOptionValueProvider()
    {
        return array(
            array(AbstractPasswordGeneratorClass::OPTION_TEST_BOOLEAN, true),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_INTEGER, 0),
        );
    }

    /**
     * @dataProvider setOptionExceptionProvider
     *
     * @param $option
     * @param $value
     */
    public function testSetExceptionOption($option, $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->_object->setOptionValue($option, $value);
    }

    /**
     * @return array
     */
    public function setOptionExceptionProvider()
    {
        return array(
            array(AbstractPasswordGeneratorClass::OPTION_TEST_BOOLEAN, 99),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_BOOLEAN, null),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_BOOLEAN, 'test'),

            array(AbstractPasswordGeneratorClass::OPTION_TEST_INTEGER, true),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_INTEGER, null),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_INTEGER, 'test'),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_INTEGER, -101),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_INTEGER, 101),
        );
    }

    public function testUnknownSetOption(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->_object->setOption('unknown', array());
    }

    public function testUnknownOption(): void
    {
        $this->assertNull($this->_object->getOption('unknown', array()));
    }

    public function testUnknownSetOptionValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->_object->setOptionValue('unknown', true);
    }

    public function testUnknownOptionValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->_object->getOptionValue('unknown', true);
    }

    public function testUnknownParameter(): void
    {
        $this->assertNull($this->_object->getParameter('unknown'));
    }

    /**
     * @dataProvider parameterProvider
     *
     * @param $parameter
     * @param $value
     */
    public function testParameter($parameter, $value): void
    {
        $this->_object->setParameter($parameter, $value);

        $this->assertSame($value, $this->_object->getParameter($parameter));
    }

    public function parameterProvider()
    {
        return array(
            array('a', 1),
            array('ab', null),
            array('test', true),
            array('test2', 'value'),
        );
    }

    /**
     * @dataProvider validateValueProvider
     *
     * @param $option
     * @param $value
     * @param $return
     */
    public function testValidateValue($option, $value, $return): void
    {
        if (!$return) {
            $this->expectException('\InvalidArgumentException');
        }

        $this->_object->setOptionValue($option, $value);

        if ($return) {
            $this->assertSame($value, $this->_object->getOptionValue($option));
        }
    }

    /**
     * @return array
     */
    public function validateValueProvider()
    {
        return array(
            array(AbstractPasswordGeneratorClass::OPTION_TEST_BOOLEAN, true, true),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_BOOLEAN, 1, false),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_BOOLEAN, null, false),

            array(AbstractPasswordGeneratorClass::OPTION_TEST_INTEGER, 0, true),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_INTEGER, 100, true),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_INTEGER, -100, true),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_INTEGER, true, false),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_INTEGER, null, false),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_INTEGER, '', false),

            array(AbstractPasswordGeneratorClass::OPTION_TEST_INTEGER_DEFAULT, 2147483647, true),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_INTEGER_DEFAULT, -2147483648, true),

            array(AbstractPasswordGeneratorClass::OPTION_TEST_STRING, 'abc', true),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_STRING, 'a', true),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_STRING, '', false),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_STRING, 0, false),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_STRING, null, false),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_STRING, true, false),

            array(AbstractPasswordGeneratorClass::OPTION_TEST_STRING, 'ac', true),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_STRING, 'abcd', false),
            array(AbstractPasswordGeneratorClass::OPTION_TEST_STRING, '', false),

            array('fail', '', false),
        );
    }
}
