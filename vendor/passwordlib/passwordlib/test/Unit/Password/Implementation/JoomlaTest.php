<?php

use PasswordLib\Core\Strength\Medium as MediumStrength;
use PasswordLibTest\Mocks\Random\Generator as MockGenerator;
use PasswordLib\Password\Implementation\Joomla;

class Unit_Hash_Implementation_JoomlaTest extends PHPUnit_Framework_TestCase {

    public static function provideTestDetect() {
        return array(
            array('$P$', false),
            array('$S$', false),
            array('$S$ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz./ABCDEFGHIJKLMNOPQRSTUVWXYZ01234', false),
            array('abcabcabcabcabcfabcabcabcabcabcf:abcdefghijklmnopabcdefghijklmnop', true),
            array('abcabcabcabcabcfabcabcabcabcabcf:abcdefghijk mnopabcdefghijklmnop', false),

        );
    }

    public static function provideTestCreate() {
        return array(
            array('foo', 'f29e73e26c48d3963a8574f176b1be84:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'),
            array('bar', 'f04ba934aba654a2e1a3221744bc76ad:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'),
            array('baz', '0367f792547d80b71027b9e07994aa0f:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'),
        );
    }

    public static function provideTestVerifyFail() {
        return array(
            array('foo', 'f29e73e26c48d3963a8574f176b1be84:aAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'),
            array('bar', 'f04bA934aba654a2e1a3221744bc76ad:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'),
            array('biz', '0367f792547d80b71027b9e07994aa0f:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'),
        );
    }

    public static function provideTestVerifyFailException() {
        return array(
            array('foo', '$S$A........u9PH9ZMowV1f3sR2VX.YMyU5IvKjn8XsQOo6AIIJDbKnT3bdYztQdz2R1/P7YLxxsaAoK2aM.DlN8BoZV3.Fa0'),
            array('bar', 'abcabcabcabcabcfabcabcabcabcabcf:abcdefghijk mnopabcdefghijklmnop'),
        );
    }

    /**
     * @covers PasswordLib\Password\Implementation\Joomla
     * @dataProvider provideTestDetect
     */
    public function testDetect($from, $expect) {
        $this->assertEquals($expect, Joomla::detect($from));
    }

    /**
     * @covers PasswordLib\Password\Implementation\Joomla
     */
    public function testLoadFromHash() {
        $test = Joomla::loadFromHash('f29e73e26c48d3963a8574f176b1be84:aAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA');
        $this->assertTrue($test instanceof Joomla);
    }

    /**
     * @covers PasswordLib\Password\Implementation\Joomla
     * @expectedException InvalidArgumentException
     */
    public function testLoadFromHashFail() {
        Joomla::loadFromHash('foo');
    }

    /**
     * @covers PasswordLib\Password\Implementation\Joomla
     */
    public function testGetPrefix() {
        $this->assertFalse(Joomla::getPrefix());
    }

    /**
     * @covers PasswordLib\Password\Implementation\Joomla
     */
    public function testConstruct() {
        $hash = new Joomla();
        $this->assertTrue($hash instanceof Joomla);
    }

    /**
     * @covers PasswordLib\Password\Implementation\Joomla
     */
    public function testConstructArgs() {
        $iterations = 10;
        $gen = $this->getRandomGenerator(function($size) {});
        $apr = new Joomla($gen);
        $this->assertTrue($apr instanceof Joomla);
    }

    /**
     * @covers PasswordLib\Password\Implementation\Joomla
     */
    public function testCreateAndVerify() {
        $hash = new Joomla();
        $test = $hash->create('Foobar');
        $this->assertTrue($hash->verify('Foobar', $test));
    }

    /**
     * @covers PasswordLib\Password\Implementation\Joomla
     * @dataProvider provideTestCreate
     */
    public function testCreate($pass, $expect) {
        $apr = $this->getJoomlaMockInstance();
        $this->assertEquals($expect, $apr->create($pass));
    }

    /**
     * @covers PasswordLib\Password\Implementation\Joomla
     * @dataProvider provideTestCreate
     */
    public function testVerify($pass, $expect) {
        $apr = $this->getJoomlaMockInstance();
        $this->assertTrue($apr->verify($pass, $expect));
    }

    /**
     * @covers PasswordLib\Password\Implementation\Joomla
     * @dataProvider provideTestVerifyFail
     */
    public function testVerifyFail($pass, $expect) {
        $apr = $this->getJoomlaMockInstance();
        $this->assertFalse($apr->verify($pass, $expect));
    }

    /**
     * @covers PasswordLib\Password\Implementation\Joomla
     * @dataProvider provideTestVerifyFailException
     * @expectedException InvalidArgumentException
     */
    public function testVerifyFailException($pass, $expect) {
        $apr = $this->getJoomlaMockInstance();
        $apr->verify($pass, $expect);
    }

    protected function getJoomlaMockInstance() {
        $gen = $this->getRandomGenerator(function($size) {
            return str_repeat('A', $size);
        });
        return new Joomla($gen);
    }

    protected function getJoomlaInstance($evaluate, $hmac, $generate) {
        $generator = $this->getRandomGenerator($generate);
        return new Joomla($generator);
    }

    protected function getRandomGenerator($generate) {
        return new MockGenerator(array(
            'generateString' => $generate
        ));
    }

}
