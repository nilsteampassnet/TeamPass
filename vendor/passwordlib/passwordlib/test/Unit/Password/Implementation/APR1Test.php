<?php

use PasswordLib\Core\Strength\Medium as MediumStrength;
use PasswordLibTest\Mocks\Hash\Hash as MockHash;
use PasswordLibTest\Mocks\Hash\Factory as MockFactory;
use PasswordLibTest\Mocks\Random\Generator as MockGenerator;
use PasswordLib\Password\Implementation\APR1;

class Unit_Hash_Implementation_APR1Test extends PHPUnit_Framework_TestCase {

    public static function provideTestCreate() {
        return array(
            array('foobar', '$apr1$6mq6m/..$n7e/M1BvwwixR9jcPrB9I.'),
        );
    }

    public static function provideTestVerify() {
        return array(
            array('foobar', '$apr1$6mq6m/..$n7e/M1BvwwixR9jcPrB9I.'),
        );
    }

    public static function provideTestVerifyFail() {
        return array(
            array('foo', 'bar'),
            //Salt Change
            array('foobar', '$apr1$6mi6m/..$n7e/M1BvwwixR9jcPrB9I.'),
        );
    }

    public static function provideTestDetect() {
        return array(
            array('$apr1$foo', true),
            array('$apr2$bar', false),
            array(md5('test'), false),
        );
    }

    public function testGetPrefix() {
        $this->assertEquals('$apr1$', APR1::getPrefix());
    }

    /**
     * @covers PasswordLib\Password\Implementation\APR1::detect
     * @dataProvider provideTestDetect
     */
    public function testDetect($from, $expect) {
        $this->assertEquals($expect, APR1::detect($from));
    }

    /**
     * @covers PasswordLib\Password\Implementation\APR1::loadFromHash
     */
    public function testLoadFromHash() {
        $test = APR1::loadFromHash('$apr1$foo');
        $this->assertTrue($test instanceof APR1);
    }

    /**
     * @covers PasswordLib\Password\Implementation\APR1::loadFromHash
     * @expectedException InvalidArgumentException
     */
    public function testLoadFromHashFail() {
        APR1::loadFromHash('foo');
    }

    /**
     * @covers PasswordLib\Password\Implementation\APR1::__construct
     */
    public function testConstruct() {
        $apr = new APR1();
        $this->assertTrue($apr instanceof APR1);
    }

    /**
     * @covers PasswordLib\Password\Implementation\APR1::__construct
     */
    public function testConstructArgs() {
        $gen = $this->getRandomGenerator(function($size) {});
        $apr = new APR1($gen);
        $this->assertTrue($apr instanceof APR1);
    }

    /**
     * @covers PasswordLib\Password\Implementation\APR1::create
     * @covers PasswordLib\Password\Implementation\APR1::to64
     * @covers PasswordLib\Password\Implementation\APR1::hash
     * @covers PasswordLib\Password\Implementation\APR1::iterate
     * @covers PasswordLib\Password\Implementation\APR1::convertToHash
     * @dataProvider provideTestCreate
     */
    public function testCreate($pass, $expect) {
        $apr = $this->getAPR1MockInstance();
        $this->assertEquals($expect, $apr->create($pass));
    }

    /**
     * @covers PasswordLib\Password\Implementation\APR1::verify
     * @covers PasswordLib\Password\Implementation\APR1::to64
     * @covers PasswordLib\Password\Implementation\APR1::hash
     * @covers PasswordLib\Password\Implementation\APR1::iterate
     * @covers PasswordLib\Password\Implementation\APR1::convertToHash
     * @dataProvider provideTestVerify
     */
    public function testVerify($pass, $expect) {
        $apr = $this->getAPR1MockInstance();
        $this->assertTrue($apr->verify($pass, $expect));
    }

    /**
     * @covers PasswordLib\Password\Implementation\APR1::verify
     * @dataProvider provideTestVerifyFail
     */
    public function testVerifyFail($pass, $expect) {
        $apr = $this->getAPR1MockInstance();
        $this->assertFalse($apr->verify($pass, $expect));
    }

    protected function getAPR1MockInstance() {
        $gen = $this->getRandomGenerator(function($min, $max) {
            return 1914924168;
        });
        return new APR1($gen);
    }

    protected function getAPR1Instance($evaluate, $hmac, $generate) {
        $generator = $this->getRandomGenerator($generate);
        return new APR1($generator);
    }

    protected function getRandomGenerator($generate) {
        return new MockGenerator(array(
            'generateInt' => $generate
        ));
    }

}
