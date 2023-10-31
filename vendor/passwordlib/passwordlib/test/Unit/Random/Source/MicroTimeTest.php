<?php

use PasswordLib\Random\Source\MicroTime;
use PasswordLib\Core\Strength;



class Unit_Random_Source_MicroTimeTest extends PHPUnit_Framework_TestCase {

    public static function provideGenerate() {
        $data = array();
        for ($i = 0; $i < 100; $i += 5) {
            $not = $i > 0 ? str_repeat(chr(0), $i) : chr(0);
            $data[] = array($i, $not);
        }
        return $data;
    }

    /**
     * @covers PasswordLib\Random\Source\MicroTime::getStrength
     */
    public function testGetStrength() {
        $strength = new Strength(Strength::VERYLOW);
        $actual = MicroTime::getStrength();
        $this->assertEquals($actual, $strength);
    }

    /**
     * @covers PasswordLib\Random\Source\MicroTime::generate
     * @dataProvider provideGenerate
     */
    public function testGenerate($length, $not) {
        $rand = new MicroTime;
        $stub = $rand->generate($length);
        $this->assertEquals($length, strlen($stub));
        $this->assertNotEquals($not, $stub);
    }

}
