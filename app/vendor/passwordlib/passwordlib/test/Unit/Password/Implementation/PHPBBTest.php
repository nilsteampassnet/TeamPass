<?php

use PasswordLib\Core\Strength\Medium as MediumStrength;
use PasswordLibTest\Mocks\Random\Generator as MockGenerator;
use PasswordLib\Password\Implementation\PHPBB;

class Unit_Hash_Implementation_PHPBBTest extends PHPUnit_Framework_TestCase {

    public static function provideTestDetect() {
        return array(
            array('$P$', false),
            array('$A$', false),
            array('$P$ABCDEFGHIJKLMNOPQRSTUVWXYZ01234', false),
            array('$H$ABCDEFGHIJKLMNOPQRSTUVWXYZ01234', true),
            array('$P$ABCDEFGHIJKLMNOPQ STUVWXYZ01234', false),
        );
    }

    public static function provideTestCreate() {
        return array(
            array(10, 'foo', '$H$8........QBguHJ1fRNunMPB19R40y1'),
            array(12, 'bar', '$H$A........4ZlHWmCyIurNtYcc0UUjk.'),
            array(14, 'baz', '$H$C........36uzgma3lYa4NbdLe/RgB.'),
        );
    }

    public static function provideTestVerifyFail() {
        return array(
            array(10, 'foo', '$H$8...a....QBguHJ1fRNunMPB19R40y1'),
            array(12, 'bar', '$H$A....f...4ZlHWmCyIurNtYcc0UUjk.'),
            array(14, 'baz', '$H$C.....D..36uzgma3lYa4NbdLe/RgB.'),
        );
    }

    public static function provideTestVerifyFailException() {
        return array(
            array(10, 'foo', '$H$A...a....QBguHJ1fRNunMPB19R40y'),
            array(12, 'bar', '$F$C....f...4ZlHWmCyIurNtYcc0UUjk.'),
            array(14, 'baz', '$H$8.....D..36uzgma3lYa4NbdLe/RgB.'),
        );
    }

    /**
     * @covers PasswordLib\Password\Implementation\PHPBB
     * @dataProvider provideTestDetect
     */
    public function testDetect($from, $expect) {
        $this->assertEquals($expect, PHPBB::detect($from));
    }

    /**
     * @covers PasswordLib\Password\Implementation\PHPBB
     */
    public function testLoadFromHash() {
        $test = PHPBB::loadFromHash('$H$MBCDEFGHIJKLMNOPQRSTUVWXYZ01234');
        $this->assertTrue($test instanceof PHPBB);
    }

    /**
     * @covers PasswordLib\Password\Implementation\PHPBB
     * @expectedException InvalidArgumentException
     */
    public function testLoadFromHashFail() {
        PHPBB::loadFromHash('foo');
    }

    /**
     * @covers PasswordLib\Password\Implementation\PHPBB
     */
    public function testConstruct() {
        $hash = new PHPBB();
        $this->assertTrue($hash instanceof PHPBB);
    }

    /**
     * @covers PasswordLib\Password\Implementation\PHPBB
     */
    public function testConstructArgs() {
        $iterations = 10;
        $gen = $this->getRandomGenerator(function($size) {});
        $apr = new PHPBB($iterations, $gen);
        $this->assertTrue($apr instanceof PHPBB);
    }

    /**
     * @covers PasswordLib\Password\Implementation\PHPBB
     * @expectedException InvalidArgumentException
     */
    public function testConstructFailFail() {
        $hash = new PHPBB(40);
    }

    public function testGetPrefix() {
        $this->assertEquals('$H$', PHPBB::getPrefix());
    }

    /**
     * @covers PasswordLib\Password\Implementation\PHPBB
     * @dataProvider provideTestCreate
     */
    public function testCreate($iterations, $pass, $expect) {
        $apr = $this->getPHPBBMockInstance($iterations);
        $this->assertEquals($expect, $apr->create($pass));
    }



    /**
     * @covers PasswordLib\Password\Implementation\PHPBB
     */
    public function testCreateAndVerify() {
        $hash = new PHPBB(10);
        $test = $hash->create('Foobar');
        $this->assertTrue($hash->verify('Foobar', $test));
    }

    /**
     * @covers PasswordLib\Password\Implementation\PHPBB
     * @dataProvider provideTestCreate
     */
    public function testVerify($iterations, $pass, $expect) {
        $apr = $this->getPHPBBMockInstance($iterations);
        $this->assertTrue($apr->verify($pass, $expect));
    }

    /**
     * @covers PasswordLib\Password\Implementation\PHPBB
     * @dataProvider provideTestVerifyFail
     */
    public function testVerifyFail($iterations, $pass, $expect) {
        $apr = $this->getPHPBBMockInstance($iterations);
        $this->assertFalse($apr->verify($pass, $expect));
    }

    /**
     * @covers PasswordLib\Password\Implementation\PHPBB
     * @dataProvider provideTestVerifyFailException
     * @expectedException InvalidArgumentException
     */
    public function testVerifyFailException($iterations, $pass, $expect) {
        $apr = $this->getPHPBBMockInstance($iterations);
        $apr->verify($pass, $expect);
    }

    protected function getPHPBBMockInstance($iterations) {
        $gen = $this->getRandomGenerator(function($size) {
            return str_repeat(chr(0), $size);
        });
        return new PHPBB($iterations, $gen);
    }

    protected function getPHPBBInstance($evaluate, $hmac, $generate) {
        $generator = $this->getRandomGenerator($generate);
        return new PHPBB($generator);
    }

    protected function getRandomGenerator($generate) {
        return new MockGenerator(array(
            'generate' => $generate
        ));
    }

}
