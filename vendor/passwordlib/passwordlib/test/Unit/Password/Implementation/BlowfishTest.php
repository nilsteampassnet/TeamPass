<?php

use PasswordLib\Core\Strength\Medium as MediumStrength;
use PasswordLibTest\Mocks\Hash\Hash as MockHash;
use PasswordLibTest\Mocks\Hash\Factory as MockFactory;
use PasswordLibTest\Mocks\Random\Generator as MockGenerator;
use PasswordLib\Password\Implementation\Blowfish;

class Unit_Password_Implementation_BlowfishTest extends PHPUnit_Framework_TestCase {

    public static function provideTestDetect() {
        return array(
            array('$2a$', false),
            array('$2$', false),
            array('$2a$07$usesomesillystringfore2uDLvp1Ii2e./U9C8sBjqp8I90dH6hi', true),
            array('$2$07$usesomesillystringfore2uDLvp1Ii2e./U9C8sBjqp8I90dH6hi', false),
            array('$2a$07$usesome illystringfore2uDLvp1Ii2e./U9C8sBjqp8I90dH6hi', false),
            array('$2a$01$usesomesillystringfore2uDLvp1Ii2e./U9C8sBjqp8I90dH6hi', false),

        );
    }

    public static function provideTestCreate() {
        return array(
            array(4, 'foo', '$2a$04$......................wy8Ny4IYV94XATD85vz/zPNKyDLSamC'),
            array(6, 'bar', '$2a$06$......................D6QbjsjSOywPPik8vlc2TG0FG4vX9De'),
            array(8, 'baz', '$2a$08$......................2r5UcI6EeUqSfXjbJ3a9ILCO4tKmi5C'),
        );
    }

    public static function provideTestVerifyFail() {
        return array(
            array(10, 'foo', '$2a$04$......................wy2Ny4IYV94XATD85vz/zPNKyDLSamC'),
            array(12, 'bar', '$2a$06$.............f........D6QbjsjSOywPPik8vlc2TG0FG4vX9De'),
            array(14, 'baz', '$2a$09$......................2r5UcI6EeUqSfXjbJ3a9ILCO4tKmi5C'),
        );
    }

    public static function provideTestVerifyFailException() {
        return array(
            array(10, 'foo', '$2a$04$......................wy8 y4IYV94XATD85vz/zPNKyDLSamC'),
            array(12, 'bar', '$2b$04$......................wy8Ny4IYV94XATD85vz/zPNKyDLSamC'),
            array(14, 'baz', '$2a$02$......................wy8Ny4IYV94XATD85vz/zPNKyDLSamC'),
        );
    }

    public function testGetPrefix() {
        $this->assertEquals('$2a$', Blowfish::getPrefix());
    }

    /**
     * @covers PasswordLib\Password\Implementation\Blowfish
     * @dataProvider provideTestDetect
     */
    public function testDetect($from, $expect) {
        $this->assertEquals($expect, Blowfish::detect($from));
    }

    /**
     * @covers PasswordLib\Password\Implementation\Blowfish
     */
    public function testLoadFromHash() {
        $test = Blowfish::loadFromHash('$2a$04$......................wy8Ny4IYV94XATD85vz/zPNKyDLSamC');
        $this->assertTrue($test instanceof Blowfish);
    }

    /**
     * @covers PasswordLib\Password\Implementation\Blowfish
     * @expectedException InvalidArgumentException
     */
    public function testLoadFromHashFail() {
        Blowfish::loadFromHash('foo');
    }

    /**
     * @covers PasswordLib\Password\Implementation\Blowfish
     */
    public function testConstruct() {
        $hash = new Blowfish();
        $this->assertTrue($hash instanceof Blowfish);
    }

    /**
     * @covers PasswordLib\Password\Implementation\Blowfish
     */
    public function testConstructArgs() {
        $iterations = 10;
        $gen = $this->getRandomGenerator(function($size) {});
        $apr = new Blowfish($iterations, $gen);
        $this->assertTrue($apr instanceof Blowfish);
    }

    /**
     * @covers PasswordLib\Password\Implementation\Blowfish
     * @expectedException InvalidArgumentException
     */
    public function testConstructFailFail() {
        $hash = new Blowfish(40);
    }

    /**
     * @covers PasswordLib\Password\Implementation\Blowfish
     */
    public function testCreateAndVerify() {
        $hash = new Blowfish(10);
        $test = $hash->create('Foobar');
        $this->assertTrue($hash->verify('Foobar', $test));
    }

    /**
     * @covers PasswordLib\Password\Implementation\Blowfish
     * @dataProvider provideTestCreate
     */
    public function testCreate($iterations, $pass, $expect) {
        $apr = $this->getBlowfishMockInstance($iterations);
        $this->assertEquals($expect, $apr->create($pass));
    }

    /**
     * @covers PasswordLib\Password\Implementation\Blowfish
     * @dataProvider provideTestCreate
     */
    public function testVerify($iterations, $pass, $expect) {
        $apr = $this->getBlowfishMockInstance($iterations);
        $this->assertTrue($apr->verify($pass, $expect));
    }

    /**
     * @covers PasswordLib\Password\Implementation\Blowfish
     * @dataProvider provideTestVerifyFail
     */
    public function testVerifyFail($iterations, $pass, $expect) {
        $apr = $this->getBlowfishMockInstance($iterations);
        $this->assertFalse($apr->verify($pass, $expect));
    }

    /**
     * @covers PasswordLib\Password\Implementation\Blowfish
     * @dataProvider provideTestVerifyFailException
     * @expectedException InvalidArgumentException
     */
    public function testVerifyFailException($iterations, $pass, $expect) {
        $apr = $this->getBlowfishMockInstance($iterations);
        $apr->verify($pass, $expect);
    }

    protected function getBlowfishMockInstance($iterations) {
        $gen = $this->getRandomGenerator(function($size) {
            return str_repeat(chr(0), $size);
        });
        return new Blowfish($iterations, $gen);
    }

    protected function getBlowfishInstance($evaluate, $hmac, $generate) {
        $generator = $this->getRandomGenerator($generate);
        return new Blowfish(10, $generator);
    }

    protected function getRandomGenerator($generate) {
        return new MockGenerator(array(
            'generate' => $generate
        ));
    }

}
