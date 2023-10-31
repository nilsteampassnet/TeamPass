<?php

use PasswordLib\Core\Strength\Medium as MediumStrength;
use PasswordLibTest\Mocks\Random\Generator as MockGenerator;
use PasswordLib\Password\Implementation\Drupal;

class Unit_Hash_Implementation_DrupalTest extends PHPUnit_Framework_TestCase {

    public static function provideTestDetect() {
        return array(
            array('$P$', false),
            array('$S$', false),
            array('$S$ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz./ABCDEFGHIJKLMNOPQRSTUVWXYZ01234', true),
            array('$S$ABCDEFGHIJKLMNOPQRSTUVWXYZ012  56789abcdefghijklmnopqrstuvwxyz./ABCDEFGHIJKLMNOPQRSTUVWXYZ01234', false),
            array('$P$ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz./ABCDEFGHIJKLMNOPQRSTUVWXYZ01234', false),
            array('$H$ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz./ABCDEFGHIJKLMNOPQRSTUVWXYZ01234', false),

        );
    }

    public static function provideTestCreate() {
        return array(
            array(10, 'foo', '$S$8........u9PH9ZMowV1f3sR2VX.YMyU5IvKjn8XsQOo6AIIJDbKnT3bdYztQdz2R1/P7YLxxsaAoK2aM.DlN8BoZV3.Fa0'),
            array(12, 'bar', '$S$A........3QBQPGxacHssvSgTWZ4zteafujOLj8VTg52YYt7HkGgeRePmuCAe7PqrPF.WRP6mdvdH9FpkucPD1L4xMwVFw.'),
            array(8, 'baz', '$S$6........7VlqDNkTAvIjwCWtn6nTr6q.4QNOMXcaRBtoNAjqd7xlKhZdWGB27q1IzTaueuMGt7ibiksiZGjE1JTKlK5kb/'),
        );
    }

    public static function provideTestVerifyFail() {
        return array(
            array(10, 'foo', '$S$8...3....u9PH9ZMowV1f3sR2VX.YMyU5IvKjn8XsQOo6AIIJDbKnT3bdYztQdz2R1/P7YLxxsaAoK2aM.DlN8BoZV3.Fa0'),
            array(12, 'bar', '$S$A.F......3QBQPGxacHssvSgTWZ4zteafujOLj8VTg52YYt7HkGgeRePmuCAe7PqrPF.WRP6mdvdH9FpkucPD1L4xMwVFw.'),
            array(8, 'baz', '$S$6........7VlqDNkTAvIjwCWtn6nTr6qR4QNOMXcaRBtoNAjqd7xlKhZdWGB27q1IzTaueuMGt7ibiksiZGjE1JTKlK5kb/'),
        );
    }

    public static function provideTestVerifyFailException() {
        return array(
            array(10, 'foo', '$S$A........u9PH9ZMowV1f3sR2VX.YMyU5IvKjn8XsQOo6AIIJDbKnT3bdYztQdz2R1/P7YLxxsaAoK2aM.DlN8BoZV3.Fa0'),
            array(12, 'bar', '$S$C........3QBQPGxacHssvSgTWZ4zteafujOLj8VTg52YYt7HkGgeRePmuCAe7PqrPF.WRP6mdvdH9FpkucPD1L4xMwVFw.'),
            array(8, 'baz', '$S$8........yDYVEB5.jG8aOZh/41LQ8Ntz5ABb6zfm.I/jevKDWvMhzatnR8e6SH93nxagKcEGo.y7nHYMD.IdMMbeUR6eX.'),
        );
    }

    /**
     * @covers PasswordLib\Password\Implementation\Drupal
     * @dataProvider provideTestDetect
     */
    public function testDetect($from, $expect) {
        $this->assertEquals($expect, Drupal::detect($from));
    }

    /**
     * @covers PasswordLib\Password\Implementation\Drupal
     */
    public function testLoadFromHash() {
        $test = Drupal::loadFromHash('$S$ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz./ABCDEFGHIJKLMNOPQRSTUVWXYZ01234');
        $this->assertTrue($test instanceof Drupal);
    }

    /**
     * @covers PasswordLib\Password\Implementation\Drupal
     * @expectedException InvalidArgumentException
     */
    public function testLoadFromHashFail() {
        Drupal::loadFromHash('foo');
    }

    /**
     * @covers PasswordLib\Password\Implementation\Drupal
     */
    public function testGetPrefix() {
        $this->assertEquals('$S$', Drupal::getPrefix());
    }

    /**
     * @covers PasswordLib\Password\Implementation\Drupal
     */
    public function testConstruct() {
        $hash = new Drupal();
        $this->assertTrue($hash instanceof Drupal);
    }

    /**
     * @covers PasswordLib\Password\Implementation\Drupal
     */
    public function testConstructArgs() {
        $iterations = 10;
        $gen = $this->getRandomGenerator(function($size) {});
        $apr = new Drupal($iterations, $gen);
        $this->assertTrue($apr instanceof Drupal);
    }

    /**
     * @covers PasswordLib\Password\Implementation\Drupal
     * @expectedException InvalidArgumentException
     */
    public function testConstructFailFail() {
        $hash = new Drupal(40);
    }

    /**
     * @covers PasswordLib\Password\Implementation\Drupal
     */
    public function testCreateAndVerify() {
        $hash = new Drupal(10);
        $test = $hash->create('Foobar');
        $this->assertTrue($hash->verify('Foobar', $test));
    }

    /**
     * @covers PasswordLib\Password\Implementation\Drupal
     * @dataProvider provideTestCreate
     */
    public function testCreate($iterations, $pass, $expect) {
        $apr = $this->getDrupalMockInstance($iterations);
        $this->assertEquals($expect, $apr->create($pass));
    }

    /**
     * @covers PasswordLib\Password\Implementation\Drupal
     * @dataProvider provideTestCreate
     */
    public function testVerify($iterations, $pass, $expect) {
        $apr = $this->getDrupalMockInstance($iterations);
        $this->assertTrue($apr->verify($pass, $expect));
    }

    /**
     * @covers PasswordLib\Password\Implementation\Drupal
     * @dataProvider provideTestVerifyFail
     */
    public function testVerifyFail($iterations, $pass, $expect) {
        $apr = $this->getDrupalMockInstance($iterations);
        $this->assertFalse($apr->verify($pass, $expect));
    }

    /**
     * @covers PasswordLib\Password\Implementation\Drupal
     * @dataProvider provideTestVerifyFailException
     * @expectedException InvalidArgumentException
     */
    public function testVerifyFailException($iterations, $pass, $expect) {
        $apr = $this->getDrupalMockInstance($iterations);
        $apr->verify($pass, $expect);
    }

    protected function getDrupalMockInstance($iterations) {
        $gen = $this->getRandomGenerator(function($size) {
            return str_repeat(chr(0), $size);
        });
        return new Drupal($iterations, $gen);
    }

    protected function getDrupalInstance($evaluate, $hmac, $generate) {
        $generator = $this->getRandomGenerator($generate);
        return new Drupal($generator);
    }

    protected function getRandomGenerator($generate) {
        return new MockGenerator(array(
            'generate' => $generate
        ));
    }

}
