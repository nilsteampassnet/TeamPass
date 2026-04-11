<?php

namespace Hackzilla\PasswordGenerator\Tests;

use Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator;
use Hackzilla\PasswordGenerator\Generator\HumanPasswordGenerator;
use Hackzilla\PasswordGenerator\Generator\HybridPasswordGenerator;
use Hackzilla\PasswordGenerator\RandomGenerator\Php5RandomGenerator;
use Hackzilla\PasswordGenerator\RandomGenerator\Php7RandomGenerator;

class ReadMeTest extends \PHPUnit\Framework\TestCase
{
    public function testSimpleUsage()
    {
        $generator = new ComputerPasswordGenerator();

        $generator
            ->setOptionValue(ComputerPasswordGenerator::OPTION_UPPER_CASE, true)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_LOWER_CASE, true)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_NUMBERS, true)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_SYMBOLS, false)
        ;

        $this->assertIsString($generator->generatePassword());
    }

    public function testMorePasswordsUsage()
    {
        $generator = new ComputerPasswordGenerator();

        $generator
            ->setUppercase()
            ->setLowercase()
            ->setNumbers()
            ->setSymbols(false)
            ->setLength(12);

        $passwords = $generator->generatePasswords(10);

        $this->assertCount(10, $passwords);
        $this->assertIsString($passwords[0]);
    }

    public function testHybridPasswordGeneratorUsage()
    {
        $generator = new HybridPasswordGenerator();

        $generator
            ->setUppercase()
            ->setLowercase()
            ->setNumbers()
            ->setSymbols(false)
            ->setSegmentLength(3)
            ->setSegmentCount(4)
            ->setSegmentSeparator('-');

        $passwords = $generator->generatePasswords(10);

        $this->assertCount(10, $passwords);
        $this->assertIsString($passwords[0]);
    }

    public function testHumanPasswordGeneratorUsage()
    {
        $generator = new HumanPasswordGenerator();

        $generator
            ->setWordList('/usr/share/dict/words')
            ->setMinWordLength(5)
            ->setMaxWordLength(8)
            ->setWordCount(3)
            ->setWordSeparator('-');

        $passwords = $generator->generatePasswords(10);

        $this->assertCount(10, $passwords);
        $this->assertIsString($passwords[0]);
    }

    /**
     * @throws \Hackzilla\PasswordGenerator\Exception\FileNotFoundException
     */
    public function testPhp5RandomGeneratorUsage()
    {
        $generator = new HumanPasswordGenerator();

        $generator
            ->setRandomGenerator(new Php5RandomGenerator())
            ->setWordList('/usr/share/dict/words')
            ->setMinWordLength(5)
            ->setMaxWordLength(8)
            ->setWordCount(3)
            ->setWordSeparator('-');

        $passwords = $generator->generatePasswords(10);

        $this->assertCount(10, $passwords);
        $this->assertIsString($passwords[0]);
    }

    /**
     * @requires PHP 7
     *
     * @throws \Hackzilla\PasswordGenerator\Exception\FileNotFoundException
     */
    public function testPhp7RandomGeneratorUsage()
    {
        $generator = new HumanPasswordGenerator();

        $generator
            ->setRandomGenerator(new Php7RandomGenerator())
            ->setWordList('/usr/share/dict/words')
            ->setMinWordLength(5)
            ->setMaxWordLength(8)
            ->setWordCount(3)
            ->setWordSeparator('-');

        $passwords = $generator->generatePasswords(10);

        $this->assertCount(10, $passwords);
        $this->assertIsString($passwords[0]);
    }
}
