<?php

namespace Hackzilla\PasswordGenerator\Tests\Generator;

use Hackzilla\PasswordGenerator\Generator\HybridPasswordGenerator;

class HybridPasswordGeneratorTest extends \PHPUnit\Framework\TestCase
{
    private $_object;

    public function setup(): void
    {
        $this->_object = new HybridPasswordGenerator();

        $this->_object
            ->setOptionValue(HybridPasswordGenerator::OPTION_UPPER_CASE, false)
            ->setOptionValue(HybridPasswordGenerator::OPTION_LOWER_CASE, false)
            ->setOptionValue(HybridPasswordGenerator::OPTION_NUMBERS, false)
            ->setOptionValue(HybridPasswordGenerator::OPTION_SYMBOLS, false)
            ->setOptionValue(HybridPasswordGenerator::OPTION_AVOID_SIMILAR, false);
    }

    public function passwordProvider()
    {
        return array(
            array(3, 4, '-', '/[A-Za-z0-9]{3}\-[A-Za-z0-9]{3}\-[A-Za-z0-9]{3}\-[A-Za-z0-9]{3}/'),
            array(1, 1, '-', '/[A-Za-z0-9]{1}/'),
            array(1, 2, '', '/[A-Za-z0-9]{1}[A-Za-z0-9]{1}/'),
            array(3, 2, '*', '/[A-Za-z0-9]{3}\*[A-Za-z0-9]{3}/'),
        );
    }

    /**
     * @dataProvider passwordProvider
     *
     * @param $segmentLength
     * @param $segmentCount
     * @param $segmentSeparator
     * @param $regExp
     */
    public function testGeneratePassword($segmentLength, $segmentCount, $segmentSeparator, $regExp): void
    {
        $this->_object
            ->setOptionValue(HybridPasswordGenerator::OPTION_UPPER_CASE, true)
            ->setOptionValue(HybridPasswordGenerator::OPTION_LOWER_CASE, true)
            ->setOptionValue(HybridPasswordGenerator::OPTION_NUMBERS, true)
            ->setOptionValue(HybridPasswordGenerator::OPTION_AVOID_SIMILAR, true);

        $this->_object->setSegmentLength($segmentLength);
        $this->_object->setSegmentCount($segmentCount);
        $this->_object->setSegmentSeparator($segmentSeparator);

        $this->assertMatchesRegularExpression($regExp, $this->_object->generatePassword());
    }

    /**
     * @dataProvider lengthProvider
     *
     * @param $count
     */
    public function testSetSegmentCount($count): void
    {
        $this->_object->setSegmentCount($count);
        $this->assertSame($this->_object->getSegmentCount(), $count);
        $this->assertSame($this->_object->getLength(), $count);
    }

    public function testSetSegmentCountException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->_object->setSegmentCount(-1);
    }

    /**
     * @dataProvider lengthProvider
     *
     * @param $length
     */
    public function testSetSegmentLength($length): void
    {
        $this->_object->setSegmentLength($length);
        $this->assertSame($this->_object->getSegmentLength(), $length);
    }

    public function testSetSegmentLengthException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->_object->setSegmentLength(-1);
    }

    /**
     * @dataProvider lengthProvider
     *
     * @param $length
     */
    public function testSetLength($length): void
    {
        $this->_object->setLength($length);
        $this->assertSame($this->_object->getLength(), $length);
    }

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
     * @dataProvider segmentProvider
     *
     * @param $separator
     */
    public function testSetSegmentSeparator($separator): void
    {
        $this->_object->setSegmentSeparator($separator);
        $this->assertSame($this->_object->getSegmentSeparator(), $separator);
    }

    public function segmentProvider()
    {
        return array(
            array(''),
            array('-'),
        );
    }

    public function testSetSegmentSeparatorException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->_object->setSegmentSeparator(-1);
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
            ->setParameter(HybridPasswordGenerator::PARAMETER_SEPARATOR, '-')
            ->setSegmentLength(1)
            ->setSegmentCount(4)
        ;

        $this->assertSame('A-A-A-A', $this->_object->generatePassword());
    }

    /**
     * @return array
     */
    public function optionsProvider()
    {
        return array(
            array(HybridPasswordGenerator::OPTION_UPPER_CASE, HybridPasswordGenerator::PARAMETER_UPPER_CASE),
            array(HybridPasswordGenerator::OPTION_LOWER_CASE, HybridPasswordGenerator::PARAMETER_LOWER_CASE),
            array(HybridPasswordGenerator::OPTION_NUMBERS, HybridPasswordGenerator::PARAMETER_NUMBERS),
            array(HybridPasswordGenerator::OPTION_SYMBOLS, HybridPasswordGenerator::PARAMETER_SYMBOLS),
        );
    }

    public function testCharacterListException(): void
    {
        $this->expectException('\Hackzilla\PasswordGenerator\Exception\CharactersNotFoundException');
        $this->_object->getCharacterList();
    }
}
