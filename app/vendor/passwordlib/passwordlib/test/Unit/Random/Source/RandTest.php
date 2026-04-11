<?php

use PasswordLib\Random\Source\Rand;
use PasswordLib\Core\Strength;

class Unit_Random_Source_RandTest extends PHPUnit_Framework_TestCase {

    public static function provideGenerate() {
        $data = array();
        for ($i = 0; $i < 100; $i += 5) {
            $not = $i > 0 ? str_repeat(chr(0), $i) : chr(0);
            $data[] = array($i, $not);
        }
        return $data;
    }

    /**
     * @covers PasswordLib\Random\Source\Rand::getStrength
     */
    public function testGetStrength() {
        if (defined('S_ALL')) {
            $strength = new Strength(Strength::LOW);
        } else {
            $strength = new Strength(Strength::VERYLOW);
        }
        $actual = Rand::getStrength();
        $this->assertEquals($actual, $strength);
    }

    /**
     * @covers PasswordLib\Random\Source\Rand::generate
     * @dataProvider provideGenerate
     */
    public function testGenerate($length, $not) {
        $rand = new Rand;
        $stub = $rand->generate($length);
        $this->assertEquals($length, strlen($stub));
        $this->assertNotEquals($not, $stub);
    }

}
