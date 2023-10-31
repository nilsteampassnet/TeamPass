<?php

use PasswordLib\Core\Strength\Medium as MediumStrength;
use PasswordLibTest\Mocks\Random\Generator as MockGenerator;
use PasswordLib\Password\Implementation\Hash;

class Unit_Password_Implementation_HashTest extends PHPUnit_Framework_TestCase {

    public static function provideTestLoadFromHash() {
        return array(
            array('md5'),
            array('sha1'),
            array('sha256'),
            array('sha512'),
        );
    }

    public static function provideTestDetect() {
        return array(
            array('$P$', false),
            array('$S$', false),
            array(str_repeat(chr(0), 32), false),
            array(md5('a'), true),
            array(sha1('a'), true),
            array(hash('sha256', 'a'), true),
            array(hash('sha512', 'a'), true),
        );
    }

    public static function provideTestVerify() {
        return array(
            array('md5', 'foo', md5('foo')),
            array('sha1', 'foo', sha1('foo')),
            array('sha256', 'foo', hash('sha256', 'foo')),
            array('sha512', 'foo', hash('sha512', 'foo'))
        );
    }

    public static function provideTestVerifyFail() {
        return array(
            array('sha1', 'foo', md5('foo')),
            array('sha1', 'bar', sha1('foo')),
        );
    }

    /**
     * @covers PasswordLib\Password\Implementation\Hash
     * @dataProvider provideTestDetect
     */
    public function testDetect($from, $expect) {
        $this->assertEquals($expect, Hash::detect($from));
    }

    /**
     * @covers PasswordLib\Password\Implementation\Hash
     * @dataProvider provideTestLoadFromHash
     */
    public function testLoadFromHash($algo) {
        $test = Hash::loadFromHash(hash($algo, ''));
        $this->assertTrue($test instanceof Hash);
    }

    /**
     * @covers PasswordLib\Password\Implementation\Hash
     * @expectedException InvalidArgumentException
     */
    public function testLoadFromHashFail() {
        Hash::loadFromHash('foo');
    }

    /**
     * @covers PasswordLib\Password\Implementation\Hash
     */
    public function testConstruct() {
        $hash = new Hash('sha256');
        $this->assertTrue($hash instanceof Hash);
    }

    /**
     * @covers PasswordLib\Password\Implementation\Hash
     */
    public function testGetPrefix() {
        $this->assertFalse(Hash::getPrefix());
    }


    /**
     * @covers PasswordLib\Password\Implementation\Hash
     */
    public function testConstructArgs() {
        $gen = $this->getRandomGenerator(function($size) {});
        $apr = new Hash('md5', $gen);
        $this->assertTrue($apr instanceof Hash);
    }

    /**
     * @covers PasswordLib\Password\Implementation\Hash
     * @expectedException BadMethodCallException
     */
    public function testCreate() {
        $hash = new Hash('md5');
        $hash->create('foo');
    }

    /**
     * @covers PasswordLib\Password\Implementation\Hash
     * @dataProvider provideTestVerify
     */
    public function testVerify($func, $pass, $hash) {
        $apr = new Hash($func);
        $this->assertTrue($apr->verify($pass, $hash));
    }

    /**
     * @covers PasswordLib\Password\Implementation\Hash
     * @dataProvider provideTestVerifyFail
     */
    public function testVerifyFail($func, $pass, $expect) {
        $apr = new Hash($func);
        $this->assertFalse($apr->verify($pass, $expect));
    }

    protected function getRandomGenerator($generate) {
        return new MockGenerator(array(
            'generateInt' => $generate
        ));
    }
}
