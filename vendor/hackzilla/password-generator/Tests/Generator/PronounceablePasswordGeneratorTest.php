<?php

namespace Hackzilla\PasswordGenerator\Tests\Generator;

use Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator;
use Hackzilla\PasswordGenerator\Generator\PasswordGeneratorInterface;
use Hackzilla\PasswordGenerator\Generator\PronounceablePasswordGenerator;

class PronounceablePasswordGeneratorTest extends \PHPUnit\Framework\TestCase
{
    /** @var PasswordGeneratorInterface */
    private $_object;

    /**
     *
     */
    public function setup(): void
    {
        $this->_object = new PronounceablePasswordGenerator();
    }

    /**
     * @dataProvider passwordsProvider
     *
     * @param string $vowels
     * @param bool   $upperCase
     * @param bool   $symbols
     * @param bool   $numbers
     */
    public function testGeneratePassword($vowels, $upperCase, $symbols, $numbers): void
    {
        $this->_object->setParameter(PronounceablePasswordGenerator::PARAMETER_VOWELS, $vowels);
        $this->_object->setOptionValue(ComputerPasswordGenerator::OPTION_LENGTH, 16);
        $this->_object->setOptionValue(ComputerPasswordGenerator::OPTION_LOWER_CASE, true);
        $this->_object->setOptionValue(ComputerPasswordGenerator::OPTION_UPPER_CASE, $upperCase);
        $this->_object->setOptionValue(ComputerPasswordGenerator::OPTION_SYMBOLS, $symbols);
        $this->_object->setOptionValue(ComputerPasswordGenerator::OPTION_NUMBERS, $numbers);

        $passwords = $this->_object->generatePasswords(10);
        foreach ($passwords as $password) {
            $passwordLen = strlen($password);
            $isLastCharVowel = null;

            for ($i = 0; $i < $passwordLen; $i++) {
                $char = $password[$i];
                $isVowel = false !== stripos($vowels, $char);
                if (null !== $isLastCharVowel) {
                    $this->assertNotEquals($isVowel, $isLastCharVowel);
                }
                $isLastCharVowel = $isVowel;
            }
        }
    }

    public function passwordsProvider()
    {
        return array(
            array('aeiou', true, true, true),
            array('aeiou', true, true, false),
            array('aeiou', true, false, true),
            array('aeiou', false, true, true),
            array('aeiou', false, true, false),
            array('aeiou', false, false, true),
            array('aeiou', false, false, false),
        );
    }
}
