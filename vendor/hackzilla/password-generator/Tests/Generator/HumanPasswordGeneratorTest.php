<?php

namespace Hackzilla\PasswordGenerator\Tests\Generator;

use Hackzilla\PasswordGenerator\Generator\HumanPasswordGenerator;

class HumanPasswordGeneratorTest extends \PHPUnit\Framework\TestCase
{
    private $_object;

    /**
     *
     */
    public function setup(): void
    {
        $this->_object = new HumanPasswordGenerator();
        $this->_object->setWordSeparator('');
    }

    /**
     *
     */
    public function testWordCount(): void
    {
        $this->_object->setWordCount(1);
        $this->assertSame($this->_object->getWordCount(), 1);

        $this->_object->setWordCount(6);
        $this->assertSame($this->_object->getWordCount(), 6);

        $this->expectException('InvalidArgumentException');
        $this->_object->setWordCount(-6);

        $this->expectException('InvalidArgumentException');
        $this->_object->setWordCount('fail');
    }

    /**
     *
     */
    public function testMinWordLength(): void
    {
        $this->_object->setMinWordLength(1);
        $this->assertSame($this->_object->getMinWordLength(), 1);

        $this->_object->setMinWordLength(6);
        $this->assertSame($this->_object->getMinWordLength(), 6);

        $this->expectException('InvalidArgumentException');
        $this->_object->setMinWordLength(-6);

        $this->expectException('InvalidArgumentException');
        $this->_object->setMinWordLength('fail');
    }

    /**
     *
     */
    public function testMaxWordLength(): void
    {
        $this->_object->setMaxWordLength(1);
        $this->assertSame($this->_object->getMaxWordLength(), 1);

        $this->_object->setMaxWordLength(6);
        $this->assertSame($this->_object->getMaxWordLength(), 6);

        $this->expectException('InvalidArgumentException');
        $this->_object->setMaxWordLength(-6);

        $this->expectException('InvalidArgumentException');
        $this->_object->setMaxWordLength('fail');
    }

    /**
     *
     */
    public function testWordSeparator(): void
    {
        $this->_object->setWordSeparator('');
        $this->assertSame($this->_object->getWordSeparator(), '');

        $this->_object->setWordSeparator('-');
        $this->assertSame($this->_object->getWordSeparator(), '-');

        $this->_object->setWordSeparator('-?*');
        $this->assertSame($this->_object->getWordSeparator(), '-?*');

        $this->expectException('InvalidArgumentException');
        $this->_object->setWordSeparator(-6);

        $this->expectException('InvalidArgumentException');
        $this->_object->setMaxWordLength(null);
    }

    /**
     * @return string
     */
    public function getSimpleWordList()
    {
        $reflClass = new \ReflectionClass(get_class($this));
        $filename = dirname($reflClass->getFileName()).DIRECTORY_SEPARATOR.'Data'.DIRECTORY_SEPARATOR.'WordList'.DIRECTORY_SEPARATOR.'simple.txt';

        return $filename;
    }

    /**
     * @return string
     */
    public function getEmptyWordList()
    {
        $reflClass = new \ReflectionClass(get_class($this));
        $filename = dirname($reflClass->getFileName()).DIRECTORY_SEPARATOR.'Data'.DIRECTORY_SEPARATOR.'WordList'.DIRECTORY_SEPARATOR.'empty.txt';

        return $filename;
    }

    /**
     *
     */
    public function testWordList(): void
    {
        $filename = $this->getSimpleWordList();

        $this->_object->setWordList($filename);
        $this->assertSame($this->_object->getWordList(), $filename);

        $this->expectException('InvalidArgumentException');
        $this->_object->setWordList(7);
    }

    public function testWordListException()
    {
        $this->expectException(\Hackzilla\PasswordGenerator\Exception\WordsNotFoundException::class);
        $filename = $this->getEmptyWordList();

        $this->_object->setWordList($filename);
        $this->_object->generatePassword();
    }

    public function testUnknownWordList(): void
    {
        $this->expectException(\Hackzilla\PasswordGenerator\Exception\FileNotFoundException::class);
        $this->_object->setWordList('fail');
    }

    public function testGenerateWordList(): void
    {
        $filename = $this->getSimpleWordList();

        $this->_object->setWordList($filename);
        $words = $this->_object->generateWordList();

        $this->assertSame(count($words), 7);
        $this->assertSame($words[0], 'blancmange');
    }

    /**
     * @dataProvider lengthProvider
     *
     * @param $length
     */
    public function testGeneratePasswords($length): void
    {
        $filename = $this->getSimpleWordList();

        $this->_object->setWordList($filename);
        $this->_object->setWordCount($length);
        $passwords = $this->_object->generatePasswords($length);

        $this->assertSame(count($passwords), $length);

        foreach ($passwords as $password) {
            $this->assertSame($password, \str_repeat('blancmange', $length));
        }
    }

    /**
     * @dataProvider lengthProvider
     *
     * @param $length
     */
    public function testGeneratePassword($length): void
    {
        $filename = $this->getSimpleWordList();

        $this->_object->setWordList($filename);
        $this->_object->setWordCount($length);
        $this->assertSame($this->_object->generatePassword(), \str_repeat('blancmange', $length));
    }

    public function testGeneratePasswordException(): void
    {
        $this->expectException('\Hackzilla\PasswordGenerator\Exception\FileNotFoundException');
        $this->_object->generatePassword();
    }

    public function testGeneratePasswordImpossiblePasswordLengthException(): void
    {
        $this->expectException(\Hackzilla\PasswordGenerator\Exception\ImpossiblePasswordLengthException::class);

        $this->_object->setLength(20);
        $this->_object->setWordCount(3);
        $this->_object->setMinWordLength(10);
        $this->_object->setMaxWordLength(10);

        $filename = $this->getSimpleWordList();
        $this->_object->setWordList($filename);

        $this->_object->generatePassword();
    }

    public function testRandomWord(): void
    {
        $filename = $this->getSimpleWordList();
        $this->_object->setWordList($filename);

        $this->assertEquals('blancmange', $this->_object->randomWord(5, 15));
    }

    public function testRandomWordException(): void
    {
        $this->expectException(\Hackzilla\PasswordGenerator\Exception\NotEnoughWordsException::class);

        $filename = $this->getSimpleWordList();
        $this->_object->setWordList($filename);

        $this->_object->randomWord(12, 15);
    }

    public function testEmptyException(): void
    {
        $this->expectException(\Hackzilla\PasswordGenerator\Exception\WordsNotFoundException::class);
        $generator = new HumanPasswordGeneratorClass();

        $generator->generatePassword();
    }

    public function testGetPasswordLength(): void
    {
        $this->_object->setLength(1);
        $this->_object->setWordCount(3);
        $this->_object->setMinWordLength(3);
        $this->_object->setMaxWordLength(6);
        $this->_object->setWordSeparator('-');

        $this->assertEquals(11, $this->_object->getMinPasswordLength());
        $this->assertEquals(20, $this->_object->getMaxPasswordLength());
    }

    /**
     * @dataProvider lengthProvider
     *
     * @param $length
     */
    public function testGeneratePasswordWithSeparator($length): void
    {
        $filename = $this->getSimpleWordList();

        $this->_object->setWordList($filename);
        $this->_object->setWordCount($length);
        $this->_object->setWordSeparator('-');
        $this->assertSame($this->_object->generatePassword(), $this->makePassword('blancmange', $length, '-'));
    }

    /**
     * @param $word
     * @param $length
     * @param $separator
     *
     * @return string
     */
    private function makePassword($word, $length, $separator)
    {
        $password = '';

        for ($i = 0; $i < $length; ++$i) {
            if ($i) {
                $password .= $separator;
            }

            $password .= $word;
        }

        return $password;
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
     * @dataProvider lengthExceptionProvider
     */
    public function testLengthException($length): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->_object->setLength($length);
    }

    /**
     * @return array
     */
    public function lengthExceptionProvider()
    {
        return array(
            array(null),
            array('A'),
            array(-1),
        );
    }
    /**
     * @dataProvider fixedPasswordsProvider
     * @param $length
     */
    public function testGeneratePasswordFixedLength($length): void
    {
        $this->_object->setMinWordLength(1);
        $this->_object->setMaxWordLength(10);

        $this->_object->setParameter(HumanPasswordGenerator::PARAMETER_WORD_CACHE, array(
            'a',
            'ab',
            'abc',
            'abcd',
            'abcde',
            'abcdef',
            'abcdefg',
            'abcdefgh',
            'abcdefghi',
            'abcdefghij',
            'abcdefghijk',
            'abcdefghijkl',
            'abcdefghijklm',
            'abcdefghijklmn',
            'abcdefghijklmno',
            'abcdefghijklmnop',
        ));

        $this->_object->setLength($length);
        $this->_object->setWordCount(3);
        $this->_object->setWordSeparator('-');

        $this->assertEquals($length, \strlen($this->_object->generatePassword()));
    }

    public function fixedPasswordsProvider()
    {
        return array(
            array(5),
            array(10),
            array(20),
            array(30),
            array(32),
        );
    }
}
