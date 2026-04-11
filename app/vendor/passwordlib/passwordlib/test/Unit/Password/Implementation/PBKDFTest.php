<?php

use PasswordLibTest\Mocks\Hash\Hash as MockHash;
use PasswordLibTest\Mocks\Hash\Factory as MockFactory;
use PasswordLibTest\Mocks\Key\Derivation\PBKDF as MockPBKDF;
use PasswordLibTest\Mocks\Random\Generator as MockGenerator;
use PasswordLib\Password\Implementation\PBKDF;

class Unit_Hash_Implementation_PBKDFTest extends PHPUnit_Framework_TestCase {

    public static function provideTestDetect() {
        return array(
            array('$pbkdf1$abc', false),
            array('$pbkdf$abc', true),
            array('$apr1$abc', false),
        );
    }

    public static function provideTestCreate() {
        return array(
            array('foo', '$pbkdf$testing$50$26$AAECAwQF$AAECAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='),
            array('foobarbizbalbuz', '$pbkdf$testing$50$26$AAECAwQF$AAECAwQFAAAAAAAAAAAAAAAAAAAAAAAAAAA='),
        );
    }

    public static function provideTestVerifyFail() {
        return array(
            //Empty Hash
            array('foo', ''),
            //Invalid Hash
            array('foo', str_repeat('c', 32)),
            //Still Invalid
            array('foo', '$$$$$$$$$$$$$'),
            //Still Invalid
            array('foo', '$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$'),
            //Wrong Derivation
            array('foo', '$pbkdf$testing$50$26$AAECAwQF$AAECAAAAAAAAAAAAAAAAAAAAAAAAAAABAAA='),
            //Salt Change
            array('foo', '$pbkdf$testing$50$26$AADCAwQF$AAECAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='),
            //Password Change
            array('foobar', '$pbkdf$testing$50$26$AAECAwQF$AAECAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='),
            //Signature Change
            array('foo', '$pbkdf$something$50$26$AAECAwQF$AAECAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='),
        );
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testLoadFromHashFail() {
        PBKDF::loadFromHash('foo');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testLoadFromHashFail2() {
        PBKDF::loadFromHash('$pbkdf$foobar$baz');
    }

    public function testGetPrefix() {
        $this->assertEquals('$pbkdf$', PBKDF::getPrefix());
    }

    /**
     * @covers PasswordLib\Password\Implementation\PBKDF::detect
     * @dataProvider provideTestDetect
     */
    public function testDetect($from, $expected) {
        $this->assertEquals($expected, PBKDF::detect($from));
    }

    /**
     * @covers PasswordLib\Password\Implementation\PBKDF::__construct
     */
    public function testConstructWithoutArgs() {
        $pbkdf = new PBKDF;
        $this->assertTrue($pbkdf instanceof PBKDF);
    }

    /**
     * @covers PasswordLib\Password\Implementation\PBKDF::__construct
     */
    public function testConstructWithArgs() {
        $mock1 = new MockPBKDF;
        $mock2 = new MockGenerator;
        $pbkdf = new PBKDF($mock1, 30, 50, $mock2);
        $this->assertTrue($pbkdf instanceof PBKDF);
    }

    /**
     * @covers PasswordLib\Password\Implementation\PBKDF::create
     * @covers PasswordLib\Password\Implementation\PBKDF::hash
     * @dataProvider provideTestCreate
     */
    public function testCreate($password, $expect) {
        $mock1 = new MockPBKDF(array(
            'derive' => function($p, $s, $its, $len) {
                $res = $p ^ $s;
                for ($i = 1; $i < $its; $i++) {
                    $res ^= $p . $s;
                }
                return substr(str_pad($res, $len, $s[0]), 0, $len);
            },
            'getSignature' => function() { return 'testing'; }
        ));
        $mock2 = new MockGenerator(array(
            'generate' => function($size) {
                $res = '';
                for ($i = 0; $i < $size; $i++) {
                    $res .= chr($i);
                }
                return $res;
            }
        ));
        $pbkdf = new PBKDF($mock1, 30, 50, $mock2);
        $act = $pbkdf->create($password);
        $this->assertEquals($expect, $act);
    }

    /**
     * @covers PasswordLib\Password\Implementation\PBKDF::verify
     * @covers PasswordLib\Password\Implementation\PBKDF::hash
     * @dataProvider provideTestCreate
     */
    public function testVerify($password, $expect) {
        $mock1 = new MockPBKDF(array(
            'derive' => function($p, $s, $its, $len) {
                $res = $p ^ $s;
                for ($i = 1; $i < $its; $i++) {
                    $res ^= $p . $s;
                }
                return substr(str_pad($res, $len, $s[0]), 0, $len);
            },
            'getSignature' => function() { return 'testing'; }
        ));
        $mock2 = new MockGenerator(array(
            'generate' => function($size) {
                $res = '';
                for ($i = 0; $i < $size; $i++) {
                    $res .= chr($i);
                }
                return $res;
            }
        ));
        $pbkdf = new PBKDF($mock1, 30, 50, $mock2);
        $this->assertTrue($pbkdf->verify($password, $expect));
    }

    /**
     * @covers PasswordLib\Password\Implementation\PBKDF::verify
     * @covers PasswordLib\Password\Implementation\PBKDF::hash
     * @dataProvider provideTestVerifyFail
     */
    public function testVerifyFail($password, $expect) {
        $mock1 = new MockPBKDF(array(
            'derive' => function($p, $s, $its, $len) {
                $res = $p ^ $s;
                for ($i = 1; $i < $its; $i++) {
                    $res ^= $p . $s;
                }
                return substr(str_pad($res, $len, $s[0]), 0, $len);
            },
            'getSignature' => function() { return 'testing'; }
        ));
        $mock2 = new MockGenerator(array(
            'generate' => function($size) {
                $res = '';
                for ($i = 0; $i < $size; $i++) {
                    $res .= chr($i);
                }
                return $res;
            }
        ));
        $pbkdf = new PBKDF($mock1, 30, 50, $mock2);
        $this->assertFalse($pbkdf->verify($password, $expect));
    }

}
