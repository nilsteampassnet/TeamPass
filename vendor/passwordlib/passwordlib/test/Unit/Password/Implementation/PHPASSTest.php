<?php

use PasswordLib\Core\Strength\Medium as MediumStrength;
use PasswordLibTest\Mocks\Random\Generator as MockGenerator;
use PasswordLib\Password\Implementation\PHPASS;

class Unit_Hash_Implementation_PHPAssTest extends PHPUnit_Framework_TestCase {

    public static function provideTestDetect() {
        return array(
            array('$P$', false),
            array('$A$', false),
            array('$P$ABCDEFGHIJKLMNOPQRSTUVWXYZ01234', true),
            array('$H$ABCDEFGHIJKLMNOPQRSTUVWXYZ01234', false),
            array('$P$ABCDEFGHIJKLMNOPQ STUVWXYZ01234', false),
        );
    }

    public static function provideTestCreate() {
        return array(
            array(10, 'foo', '$P$8........QBguHJ1fRNunMPB19R40y1'),
            array(12, 'bar', '$P$A........4ZlHWmCyIurNtYcc0UUjk.'),
            array(14, 'baz', '$P$C........36uzgma3lYa4NbdLe/RgB.'),
        );
    }

    public static function provideTestVerifyFail() {
        return array(
            array(10, 'foo', '$P$8...a....QBguHJ1fRNunMPB19R40y1'),
            array(12, 'bar', '$P$A....f...4ZlHWmCyIurNtYcc0UUjk.'),
            array(14, 'baz', '$P$C.....D..36uzgma3lYa4NbdLe/RgB.'),
        );
    }

    public static function provideTestVerifyFailException() {
        return array(
            array(10, 'foo', '$P$A...a....QBguHJ1fRNunMPB19R40y'),
            array(12, 'bar', '$F$C....f...4ZlHWmCyIurNtYcc0UUjk.'),
            array(14, 'baz', '$P$8.....D..36uzgma3lYa4NbdLe/RgB.'),
        );
    }

    /**
     * @covers PasswordLib\Password\Implementation\PHPASS
     * @dataProvider provideTestDetect
     */
    public function testDetect($from, $expect) {
        $this->assertEquals($expect, PHPASS::detect($from));
    }

    /**
     * @covers PasswordLib\Password\Implementation\PHPASS
     */
    public function testLoadFromHash() {
        $test = PHPASS::loadFromHash('$P$MBCDEFGHIJKLMNOPQRSTUVWXYZ01234');
        $this->assertTrue($test instanceof PHPASS);
    }

    /**
     * @covers PasswordLib\Password\Implementation\PHPASS
     * @expectedException InvalidArgumentException
     */
    public function testLoadFromHashFail() {
        PHPASS::loadFromHash('foo');
    }

    /**
     * @covers PasswordLib\Password\Implementation\PHPASS
     */
    public function testConstruct() {
        $hash = new PHPASS();
        $this->assertTrue($hash instanceof PHPASS);
    }

    /**
     * @covers PasswordLib\Password\Implementation\PHPASS
     */
    public function testConstructArgs() {
        $iterations = 10;
        $gen = $this->getRandomGenerator(function($size) {});
        $apr = new PHPASS($iterations, $gen);
        $this->assertTrue($apr instanceof PHPASS);
    }

    /**
     * @covers PasswordLib\Password\Implementation\PHPASS
     * @expectedException InvalidArgumentException
     */
    public function testConstructFailFail() {
        $hash = new PHPASS(40);
    }

    public function testGetPrefix() {
        $this->assertEquals('$P$', PHPASS::getPrefix());
    }

    /**
     * @covers PasswordLib\Password\Implementation\PHPASS
     * @dataProvider provideTestCreate
     */
    public function testCreate($iterations, $pass, $expect) {
        $apr = $this->getPHPASSMockInstance($iterations);
        $this->assertEquals($expect, $apr->create($pass));
    }



    /**
     * @covers PasswordLib\Password\Implementation\PHPASS
     */
    public function testCreateAndVerify() {
        $hash = new PHPASS(10);
        $test = $hash->create('Foobar');
        $this->assertTrue($hash->verify('Foobar', $test));
    }

    /**
     * @covers PasswordLib\Password\Implementation\PHPASS
     * @dataProvider provideTestCreate
     */
    public function testVerify($iterations, $pass, $expect) {
        $apr = $this->getPHPASSMockInstance($iterations);
        $this->assertTrue($apr->verify($pass, $expect));
    }

    /**
     * @covers PasswordLib\Password\Implementation\PHPASS
     * @dataProvider provideTestVerifyFail
     */
    public function testVerifyFail($iterations, $pass, $expect) {
        $apr = $this->getPHPASSMockInstance($iterations);
        $this->assertFalse($apr->verify($pass, $expect));
    }

    /**
     * @covers PasswordLib\Password\Implementation\PHPASS
     * @dataProvider provideTestVerifyFailException
     * @expectedException InvalidArgumentException
     */
    public function testVerifyFailException($iterations, $pass, $expect) {
        $apr = $this->getPHPASSMockInstance($iterations);
        $apr->verify($pass, $expect);
    }

    protected function getPHPASSMockInstance($iterations) {
        $gen = $this->getRandomGenerator(function($size) {
            return str_repeat(chr(0), $size);
        });
        return new PHPASS($iterations, $gen);
    }

    protected function getPHPASSInstance($evaluate, $hmac, $generate) {
        $generator = $this->getRandomGenerator($generate);
        return new PHPASS($generator);
    }

    protected function getRandomGenerator($generate) {
        return new MockGenerator(array(
            'generate' => $generate
        ));
    }

}
