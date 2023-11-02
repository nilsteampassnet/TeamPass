<?php

use PasswordLib\PasswordLib;

class Unit_PasswordLibTest extends PHPUnit_Framework_TestCase {

    public function testConstruct() {
        $crypt = new PasswordLib;
    }

    public function testCreatePasswordHash() {
        $password = 'foobar';
        $prefix = '$2a$';
        $crypt = new PasswordLib;
        $test = $crypt->createPasswordHash($password, $prefix);
        $this->assertTrue($test == crypt($password, $test));
    }

    public function testVerifyPasswordHash() {
        $password = 'foobar';
        $prefix = '$2a$';
        $crypt = new PasswordLib;
        $test = $crypt->createPasswordHash($password, $prefix);
        $this->assertTrue($crypt->verifyPasswordHash($password, $test));
    }

    public function testGetRandomArrayElement() {
        $array = range(1, 100);
        $crypt = new PasswordLib;
        $el = $crypt->getRandomArrayElement($array);
        $this->assertTrue(in_array($el, $array));
    }

    public function testGetRandomNumber() {
        $crypt = new PasswordLib;
        $max = 100;
        $min = 0;
        $number = $crypt->getRandomNumber($min, $max);
        $this->assertGreaterThanOrEqual($min, $number);
        $this->assertLessThanOrEqual($max, $number);
    }

    public function testGetRandomBytes() {
        $crypt = new PasswordLib;
        $string = $crypt->getRandomBytes(10);
        $this->assertEquals(10, strlen($string));
    }

    public function testGetRandomToken() {
        $crypt = new PasswordLib;
        $string = $crypt->getRandomToken(10);
        $this->assertEquals(10, strlen($string));
    }

    public function testShuffleArray() {
        $crypt = new PasswordLib;
        $array = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10);
        $newArray = $crypt->shuffleArray($array);
        $this->assertNotEquals($array, array_values($newArray));
        $this->assertEquals($array, $newArray);
    }

    public function testShuffleString() {
        $crypt = new PasswordLib;
        $string = 'abcdefghijklmnopqrstuvwxyz';
        $newString = $crypt->shuffleString($string);
        $this->assertNotEquals($string, $newString);
        $cnt = count_chars($string, 1);
        $cnt2 = count_chars($newString, 1);
        $this->assertEquals($cnt, $cnt2);
    }
}