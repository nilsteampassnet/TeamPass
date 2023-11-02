<?php

use PasswordLib\Random\Source\MTRand;
use PasswordLib\Core\Strength;



class Unit_Random_Source_MTRandTest extends PHPUnit_Framework_TestCase {

    public static function provideGenerate() {
        $data = array();
        for ($i = 0; $i < 100; $i += 5) {
            $not = $i > 0 ? str_repeat(chr(0), $i) : chr(0);
            $data[] = array($i, $not);
        }
        return $data;
    }

    /**
     * @covers PasswordLib\Random\Source\MTRand::getStrength
     */
    public function testGetStrength() {
        if (defined('S_ALL')) {
            $strength = new Strength(Strength::MEDIUM);
        } else {
            $strength = new Strength(Strength::LOW);
        }
        $actual = MTRand::getStrength();
        $this->assertEquals($actual, $strength);
    }

    /**
     * @covers PasswordLib\Random\Source\MTRand::generate
     * @dataProvider provideGenerate
     */
    public function testGenerate($length, $not) {
        $rand = new MTRand;
        $stub = $rand->generate($length);
        $this->assertEquals($length, strlen($stub));
        $this->assertNotEquals($not, $stub);
    }

}
