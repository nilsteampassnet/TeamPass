<?php

namespace Hackzilla\PasswordGenerator\Tests\Generator;

use Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator;

class ComputerPasswordGeneratorTest extends \PHPUnit\Framework\TestCase
{
    private $_object;

    /**
     *
     */
    public function setup(): void
    {
        $this->_object = new ComputerPasswordGenerator();

        $this->_object
            ->setOptionValue(ComputerPasswordGenerator::OPTION_UPPER_CASE, false)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_LOWER_CASE, false)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_NUMBERS, false)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_SYMBOLS, false)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_AVOID_SIMILAR, false);
    }

    /**
     * @dataProvider lengthProvider
     *
     * @param $length
     */
    public function testLength($length): void
    {
        $this->_object->setLength($length);
        $this->assertSame($this->_object->getLength(), $length);
    }

    /**
     * @dataProvider lengthProvider
     *
     * @param $length
     */
    public function testGeneratePassword($length): void
    {
        $this->_object
            ->setOptionValue(ComputerPasswordGenerator::OPTION_UPPER_CASE, true)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_LOWER_CASE, true)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_NUMBERS, true)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_SYMBOLS, true)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_AVOID_SIMILAR, true);

        $this->_object->setLength($length);
        $this->assertSame(\strlen($this->_object->generatePassword()), $length);
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
     * @dataProvider getterSetterProvider
     *
     * @param $method
     */
    public function testGetterSetters($method): void
    {
        $this->_object->{'set'.$method}(true);
        $this->assertTrue($this->_object->{'get'.$method}());

        $this->_object->{'set'.$method}(false);
        $this->assertTrue(!$this->_object->{'get'.$method}());
    }

    /**
     * @dataProvider      lengthExceptionProvider
     *
     * @param $param
     */
    public function testLengthException($param): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->_object->setLength($param);
    }

    public function lengthExceptionProvider()
    {
        return array(
            array('a'),
            array(false),
            array(null),
            array(-1),
        );
    }

    /**
     * @dataProvider      getterSetterProvider
     *
     * @param $method
     */
    public function testGetterSettersException($method): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->_object->{'set'.$method}(1);
    }

    public function getterSetterProvider()
    {
        return array(
            array('Uppercase'),
            array('Lowercase'),
            array('Numbers'),
            array('Symbols'),
            array('AvoidSimilar'),
        );
    }

    /**
     * @dataProvider optionProvider
     *
     * @param $option
     * @param $exists
     * @param $dontExist
     */
    public function testSetOption($option, $exists, $dontExist): void
    {
        $this->_object->setOptionValue($option, true);
        $availableCharacters = $this->_object->getCharacterList()->getCharacters();

        foreach ($exists as $check) {
            $this->assertStringContainsString($check, $availableCharacters);
        }
        foreach ($dontExist as $check) {
            $this->assertStringNotContainsString($check, $availableCharacters);
        }
    }

    /**
     * @return array
     */
    public function optionProvider()
    {
        return array(
            array(ComputerPasswordGenerator::OPTION_UPPER_CASE, array('A', 'B', 'C'), array('a', 'b', 'c')),
            array(ComputerPasswordGenerator::OPTION_LOWER_CASE, array('a', 'b', 'c'), array('A', 'B', 'C')),
            array(ComputerPasswordGenerator::OPTION_NUMBERS, array('1', '2', '3'), array('a', 'b', 'c', 'A', 'B', 'C')),
            array(ComputerPasswordGenerator::OPTION_SYMBOLS, array('+', '=', '?'), array('a', 'b', 'c', 'A', 'B', 'C')),
        );
    }

    public function testSetOptionSimilar(): void
    {
        $exists = array('a', 'b', 'c', 'A', 'B', 'C');
        $dontExist = array('o', 'l', 'O');

        $this->_object
            ->setOptionValue(ComputerPasswordGenerator::OPTION_UPPER_CASE, true)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_LOWER_CASE, true)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_AVOID_SIMILAR, true);

        $availableCharacters = $this->_object->getCharacterList()->getCharacters();

        foreach ($exists as $check) {
            $this->assertStringContainsString($check, $availableCharacters);
        }
        foreach ($dontExist as $check) {
            $this->assertStringNotContainsString($check, $availableCharacters);
        }
    }

    /**
     * @dataProvider optionsProvider
     *
     * @param $option
     * @param $parameter
     */
    public function testSetGet($option, $parameter): void
    {
        $this->_object
            ->setOptionValue($option, true)
            ->setParameter($parameter, 'A')
            ->setLength(4);

        $this->assertSame('AAAA', $this->_object->generatePassword());
    }

    /**
     * @return array
     */
    public function optionsProvider()
    {
        return array(
            array(ComputerPasswordGenerator::OPTION_UPPER_CASE, ComputerPasswordGenerator::PARAMETER_UPPER_CASE),
            array(ComputerPasswordGenerator::OPTION_LOWER_CASE, ComputerPasswordGenerator::PARAMETER_LOWER_CASE),
            array(ComputerPasswordGenerator::OPTION_NUMBERS, ComputerPasswordGenerator::PARAMETER_NUMBERS),
            array(ComputerPasswordGenerator::OPTION_SYMBOLS, ComputerPasswordGenerator::PARAMETER_SYMBOLS),
        );
    }

    /**
     *
     */
    public function testAvoidSimilar(): void
    {
        $this->_object
            ->setParameter(ComputerPasswordGenerator::PARAMETER_UPPER_CASE, 'AB')
            ->setParameter(ComputerPasswordGenerator::PARAMETER_SIMILAR, 'B')
            ->setOptionValue(ComputerPasswordGenerator::OPTION_UPPER_CASE, true)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_AVOID_SIMILAR, true);

        $this->_object->setLength(4);

        $this->assertSame('AAAA', $this->_object->generatePassword());
    }

    /**
     *
     */
    public function testCharacterListException(): void
    {
        $this->expectException('\Hackzilla\PasswordGenerator\Exception\CharactersNotFoundException');
        $this->_object->getCharacterList();
    }
}
