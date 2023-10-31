<?php

use PasswordLibTest\Mocks\Hash\Hash as MockHash;
use PasswordLib\Key\Derivation\PBKDF\PBKDF2;

class Vectors_Key_Derivation_PBKDF_PBKDF2Test extends PHPUnit_Framework_TestCase {

    public static function provideTestDerive() {
        $file = \PasswordLibTest\getTestDataFile('Vectors/pbkdf2-draft-josefsson-sha1.test-vectors');
        $nessie = new PasswordLibTest\lib\VectorParser\NESSIE($file);
        $data = array('sha1' => $nessie->getVectors());
        $file = \PasswordLibTest\getTestDataFile('Vectors/pbkdf2-draft-josefsson-sha256.test-vectors');
        $nessie = new PasswordLibTest\lib\VectorParser\NESSIE($file);
        $data['sha256'] = $nessie->getVectors();
        $results = array();
        foreach ($data as $algo => $vectors) {
            foreach ($vectors as $row) {
                $results[] = array(
                    str_replace('\0', "\0", $row['P']),
                    str_replace('\0', "\0", $row['S']),
                    $row['c'],
                    $row['dkLen'],
                    $algo,
                    strtoupper($row['DK'])
                );
            }
        }
        return $results;
    }

    /**
     * @covers PasswordLib\Key\Derivation\PBKDF\PBKDF2::derive
     * @dataProvider provideTestDerive
     * @group Vectors
     */
    public function testDerive($p, $s, $c, $len, $hash, $expect) {
        $pb = new PBKDF2(array('hash'=>$hash));
        $actual = $pb->derive($p, $s, $c, $len);
        $this->assertEquals($expect, strtoupper(bin2hex($actual)));
    }

}
