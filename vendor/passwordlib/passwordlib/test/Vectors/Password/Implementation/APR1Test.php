<?php

use PasswordLib\Password\Implementation\APR1;

class Vectors_Password_Implementation_APR1Test extends PHPUnit_Framework_TestCase {

    public static function provideTestVerify() {
        $file = \PasswordLibTest\getTestDataFile('Vectors/apr1.test-vectors');
        $nessie = new PasswordLibTest\lib\VectorParser\NESSIE($file);
        $results = array();
        foreach ($nessie->getVectors() as $vector) {
            $results[] = array(
                $vector['P'],
                $vector['H'],
                (boolean) $vector['Value'],
            );
        }
        $file = \PasswordLibTest\getTestDataFile('Vectors/apr1.custom.test-vectors');
        $nessie = new PasswordLibTest\lib\VectorParser\SSV($file);
        foreach ($nessie->getVectors() as $vector) {
            $results[] = array(
                $vector[0],
                $vector[1],
                true
            );
        }
        return $results;
    }

    /**
     * @covers PasswordLib\Password\Implementation\APR1::verify
     * @covers PasswordLib\Password\Implementation\APR1::to64
     * @covers PasswordLib\Password\Implementation\APR1::hash
     * @covers PasswordLib\Password\Implementation\APR1::iterate
     * @covers PasswordLib\Password\Implementation\APR1::convertToHash
     * @dataProvider provideTestVerify
     * @group Vectors
     */
    public function testVerify($pass, $expect, $value) {
        $apr = new APR1();
        $this->assertEquals($value, $apr->verify($pass, $expect));
    }

}
