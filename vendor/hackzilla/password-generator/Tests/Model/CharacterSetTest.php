<?php

namespace Hackzilla\PasswordGenerator\Tests\Model;

use Hackzilla\PasswordGenerator\Model\CharacterSet;

class CharacterSetTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider characterProvider
     *
     * @param $characters
     * @param $result
     */
    public function testConstruct($characters, $result): void
    {
        $characterSet = new CharacterSet($characters);

        $this->assertSame($result, $characterSet->getCharacters());
    }

    public function characterProvider()
    {
        return array(
            array('ABC', 'ABC'),
            array('', ''),
            array(null, null),
        );
    }

    /**
     * @dataProvider castCharacterProvider
     *
     * @param $characters
     * @param $result
     */
    public function testConstructCast($characters, $result): void
    {
        $characterSet = new CharacterSet($characters);

        $this->assertSame($result, $characterSet->__toString());
        $this->assertSame($result, (string) $characterSet);
    }

    public function castCharacterProvider()
    {
        return array(
            array('ABC', 'ABC'),
            array('', ''),
            array(null, ''),
        );
    }
}
