<?php

use PasswordLib\Key\Factory;

class Unit_Key_FactoryTest extends PHPUnit_Framework_TestCase {

    public function testConstruct() {
        $factory = new Factory;
    }

    public function testGetPBKDF() {
        $factory = new Factory;
        $this->assertTrue($factory->getPBKDF() instanceof \PasswordLib\Key\Derivation\PBKDF\PBKDF2);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testGetPBKDFFail() {
        $factory = new Factory;
        $factory->getPBKDF('someGibberish');
    }

}
