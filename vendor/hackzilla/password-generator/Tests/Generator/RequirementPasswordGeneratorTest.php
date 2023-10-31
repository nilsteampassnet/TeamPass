<?php

namespace Hackzilla\PasswordGenerator\Tests\Generator;

use Hackzilla\PasswordGenerator\Exception\InvalidOptionException;
use Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator;
use Hackzilla\PasswordGenerator\Generator\RequirementPasswordGenerator;

class RequirementPasswordGeneratorTest extends \PHPUnit\Framework\TestCase
{
    private $_object;

    /**
     *
     */
    public function setup(): void
    {
        $this->_object = new RequirementPasswordGenerator();

        $this->_object
            ->setOptionValue(RequirementPasswordGenerator::OPTION_UPPER_CASE, false)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_LOWER_CASE, false)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_NUMBERS, false)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_SYMBOLS, false)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_AVOID_SIMILAR, false);
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
            ->setOptionValue(RequirementPasswordGenerator::OPTION_UPPER_CASE, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_LOWER_CASE, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_NUMBERS, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_SYMBOLS, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_AVOID_SIMILAR, true);

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

    public function testGeneratePasswordNonIntException(): void
    {
        $this->expectException('\InvalidArgumentException');
        $this->_object->generatePasswords('A');
    }

    public function testGeneratePasswordNegativeIntException(): void
    {
        $this->expectException('\InvalidArgumentException');
        $this->_object->generatePasswords(-1);
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
            array(RequirementPasswordGenerator::OPTION_UPPER_CASE, array('A', 'B', 'C'), array('a', 'b', 'c')),
            array(RequirementPasswordGenerator::OPTION_LOWER_CASE, array('a', 'b', 'c'), array('A', 'B', 'C')),
            array(RequirementPasswordGenerator::OPTION_NUMBERS, array('1', '2', '3'), array('a', 'b', 'c', 'A', 'B', 'C')),
            array(RequirementPasswordGenerator::OPTION_SYMBOLS, array('+', '=', '?'), array('a', 'b', 'c', 'A', 'B', 'C')),
        );
    }

    public function testSetOptionSimilar(): void
    {
        $exists = array('a', 'b', 'c', 'A', 'B', 'C');
        $dontExist = array('o', 'l', 'O');

        $this->_object
            ->setOptionValue(RequirementPasswordGenerator::OPTION_UPPER_CASE, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_LOWER_CASE, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_AVOID_SIMILAR, true);

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
            array(RequirementPasswordGenerator::OPTION_UPPER_CASE, RequirementPasswordGenerator::PARAMETER_UPPER_CASE),
            array(RequirementPasswordGenerator::OPTION_LOWER_CASE, RequirementPasswordGenerator::PARAMETER_LOWER_CASE),
            array(RequirementPasswordGenerator::OPTION_NUMBERS, RequirementPasswordGenerator::PARAMETER_NUMBERS),
            array(RequirementPasswordGenerator::OPTION_SYMBOLS, RequirementPasswordGenerator::PARAMETER_SYMBOLS),
        );
    }

    /**
     *
     */
    public function testAvoidSimilar(): void
    {
        $this->_object
            ->setParameter(RequirementPasswordGenerator::PARAMETER_UPPER_CASE, 'AB')
            ->setParameter(RequirementPasswordGenerator::PARAMETER_SIMILAR, 'B')
            ->setOptionValue(RequirementPasswordGenerator::OPTION_UPPER_CASE, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_AVOID_SIMILAR, true);

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

    /**
     * @dataProvider validOptionProvider
     */
    public function testValidOption($option, $valid): void
    {
        $this->assertSame($valid, $this->_object->validOption($option));
    }

    public function validOptionProvider()
    {
        return array(
            array(RequirementPasswordGenerator::OPTION_UPPER_CASE, true),
            array(RequirementPasswordGenerator::OPTION_LOWER_CASE, true),
            array(RequirementPasswordGenerator::OPTION_NUMBERS, true),
            array(RequirementPasswordGenerator::OPTION_SYMBOLS, true),
            array(null, false),
            array('', false),
            array(RequirementPasswordGenerator::OPTION_AVOID_SIMILAR, false),
            array(RequirementPasswordGenerator::OPTION_LENGTH, false),
        );
    }

    /**
     * @dataProvider minMaxProvider
     *
     * @param $option
     * @param $count
     */
    public function testMinMax($option, $count): void
    {
        $this->_object->setMinimumCount($option, $count);
        $this->assertSame($count, $this->_object->getMinimumCount($option));

        $this->_object->setMaximumCount($option, $count);
        $this->assertSame($count, $this->_object->getMaximumCount($option));
    }

    public function minMaxProvider()
    {
        return array(
            array(RequirementPasswordGenerator::OPTION_UPPER_CASE, null),
            array(RequirementPasswordGenerator::OPTION_UPPER_CASE, 1),
            array(RequirementPasswordGenerator::OPTION_UPPER_CASE, 2),
            array(RequirementPasswordGenerator::OPTION_LOWER_CASE, null),
            array(RequirementPasswordGenerator::OPTION_LOWER_CASE, 1),
            array(RequirementPasswordGenerator::OPTION_LOWER_CASE, 2),
            array(RequirementPasswordGenerator::OPTION_NUMBERS, null),
            array(RequirementPasswordGenerator::OPTION_NUMBERS, 1),
            array(RequirementPasswordGenerator::OPTION_NUMBERS, 2),
            array(RequirementPasswordGenerator::OPTION_SYMBOLS, null),
            array(RequirementPasswordGenerator::OPTION_SYMBOLS, 1),
            array(RequirementPasswordGenerator::OPTION_SYMBOLS, 2),
        );
    }

    public function testSetMinimumCountException(): void
    {
        $this->expectException('\InvalidArgumentException');
        $this->_object->setMinimumCount(RequirementPasswordGenerator::OPTION_UPPER_CASE, 'A');
    }

    public function testSetMaximumCountException(): void
    {
        $this->expectException('\InvalidArgumentException');
        $this->_object->setMaximumCount(RequirementPasswordGenerator::OPTION_UPPER_CASE, 'A');
    }

    public function testValidLimits(): void
    {
        $this->_object
            ->setOptionValue(RequirementPasswordGenerator::OPTION_UPPER_CASE, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_LOWER_CASE, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_NUMBERS, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_SYMBOLS, true)
            ->setLength(4)
            ->setMinimumCount(RequirementPasswordGenerator::OPTION_UPPER_CASE, 1)
            ->setMinimumCount(RequirementPasswordGenerator::OPTION_LOWER_CASE, 1)
            ->setMinimumCount(RequirementPasswordGenerator::OPTION_NUMBERS, 1)
            ->setMinimumCount(RequirementPasswordGenerator::OPTION_SYMBOLS, 1)
        ;

        $this->assertTrue($this->_object->validLimits());
    }

    public function testValidLimitsFalse(): void
    {
        $this->_object
            ->setOptionValue(RequirementPasswordGenerator::OPTION_UPPER_CASE, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_LOWER_CASE, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_NUMBERS, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_SYMBOLS, true)
            ->setLength(3)
            ->setMinimumCount(RequirementPasswordGenerator::OPTION_UPPER_CASE, 1)
            ->setMinimumCount(RequirementPasswordGenerator::OPTION_LOWER_CASE, 1)
            ->setMinimumCount(RequirementPasswordGenerator::OPTION_NUMBERS, 1)
            ->setMinimumCount(RequirementPasswordGenerator::OPTION_SYMBOLS, 1)
        ;

        $this->assertFalse($this->_object->validLimits());
    }

    public function testvalidLimitsFalse2(): void
    {
        $this->_object
            ->setOptionValue(RequirementPasswordGenerator::OPTION_UPPER_CASE, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_LOWER_CASE, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_NUMBERS, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_SYMBOLS, true)
            ->setLength(3)
            ->setMinimumCount(RequirementPasswordGenerator::OPTION_UPPER_CASE, 4)
        ;

        $this->assertFalse($this->_object->validLimits());
    }

    public function testValidLimitsMaxFalse(): void
    {
        $this->_object
            ->setOptionValue(RequirementPasswordGenerator::OPTION_UPPER_CASE, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_LOWER_CASE, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_NUMBERS, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_SYMBOLS, true)
            ->setLength(5)
            ->setMaximumCount(RequirementPasswordGenerator::OPTION_UPPER_CASE, 1)
            ->setMaximumCount(RequirementPasswordGenerator::OPTION_LOWER_CASE, 1)
            ->setMaximumCount(RequirementPasswordGenerator::OPTION_NUMBERS, 1)
            ->setMaximumCount(RequirementPasswordGenerator::OPTION_SYMBOLS, 1)
        ;

        $this->assertFalse($this->_object->validLimits());
    }

    public function testGeneratePasswordException(): void
    {
        $this->_object
            ->setOptionValue(RequirementPasswordGenerator::OPTION_UPPER_CASE, true)
            ->setLength(4)
            ->setMinimumCount(RequirementPasswordGenerator::OPTION_UPPER_CASE, 5)
        ;

        $this->expectException('\Hackzilla\PasswordGenerator\Exception\ImpossibleMinMaxLimitsException');
        $this->_object->generatePassword();
    }

    public function testMaxPartialValidLimits(): void
    {
        $this->_object
            ->setLength(4)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_UPPER_CASE, true)
            ->setMaximumCount(RequirementPasswordGenerator::OPTION_UPPER_CASE, 1)
        ;

        $this->assertFalse($this->_object->validLimits());
    }

    public function testMinPartialValidLimits(): void
    {
        $this->_object
            ->setLength(4)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_UPPER_CASE, true)
            ->setMinimumCount(RequirementPasswordGenerator::OPTION_UPPER_CASE, 5)
        ;

        $this->assertFalse($this->_object->validLimits());
    }

    /**
     * @dataProvider validatePasswordProvider
     *
     * @param $password
     * @param $option
     * @param $min
     * @param $max
     * @param $valid
     */
    public function testValidatePassword($password, $option, $min, $max, $valid): void
    {
        $this->_object->setMinimumCount($option, $min);
        $this->_object->setMaximumCount($option, $max);

        $this->assertSame($valid, $this->_object->validatePassword($password));
    }

    public function validatePasswordProvider()
    {
        return array(
            array('ABCDef', RequirementPasswordGenerator::OPTION_UPPER_CASE, 2, 3, false),
            array('ABCdef', RequirementPasswordGenerator::OPTION_UPPER_CASE, 2, 3, true),
            array('ABcdef', RequirementPasswordGenerator::OPTION_UPPER_CASE, 2, 3, true),
            array('Abcdef', RequirementPasswordGenerator::OPTION_UPPER_CASE, 2, 3, false),
            array('ABCdef^\'%', RequirementPasswordGenerator::OPTION_SYMBOLS, 2, 3, true),
            array('ABcdef!@', RequirementPasswordGenerator::OPTION_SYMBOLS, 2, 3, true),
            array('Abcdef!', RequirementPasswordGenerator::OPTION_SYMBOLS, 2, 3, false),
        );
    }

    /**
     * @dataProvider minLengthProvider
     *
     * @param $length
     */
    public function testMinGeneratePassword($length): void
    {
        $this->_object
            ->setOptionValue(ComputerPasswordGenerator::OPTION_UPPER_CASE, true)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_LOWER_CASE, true)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_NUMBERS, true)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_SYMBOLS, true)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_AVOID_SIMILAR, true)
            ->setMinimumCount(RequirementPasswordGenerator::OPTION_UPPER_CASE, 2)
            ->setMinimumCount(RequirementPasswordGenerator::OPTION_LOWER_CASE, 2)
            ->setMinimumCount(RequirementPasswordGenerator::OPTION_NUMBERS, 2)
            ->setMinimumCount(RequirementPasswordGenerator::OPTION_SYMBOLS, 2)
        ;

        $this->_object->setLength($length);

        $this->assertTrue($this->_object->validLimits());

        $passwords = $this->_object->generatePasswords(5);
        $this->assertCount(5, $passwords);

        foreach ($passwords as $password) {
            $this->assertSame($length, \strlen($password));
        }
    }

    /**
     * @return array
     */
    public function minLengthProvider()
    {
        return array(
            array(8),
            array(16),
        );
    }

    /**
     * @dataProvider      countOptionExceptionProvider
     * @expectException \Hackzilla\PasswordGenerator\Exception\InvalidOptionException
     *
     * @param $method
     * @param $option
     */
    public function testCountOptionException($method, $option): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->_object->{$method}($option, 1);
    }

    public function countOptionExceptionProvider()
    {
        return array(
            array('setMinimumCount', 'INVALID_OPTION'),
            array('setMaximumCount', 'INVALID_OPTION'),
        );
    }

    /**
     * @dataProvider      countValueExceptionProvider
     *
     * @param $method
     * @param $option
     */
    public function testCountValueException($method, $option): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->_object->{$method}($option, 0);
    }

    public function countValueExceptionProvider()
    {
        return array(
            array('setMinimumCount', 'INVALID_OPTION'),
            array('setMaximumCount', 'INVALID_OPTION'),
        );
    }
}
