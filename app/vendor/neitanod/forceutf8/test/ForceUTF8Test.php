<?php
require_once(dirname(__FILE__)."/Test.class.php");
require_once(dirname(dirname(__FILE__))."/src/ForceUTF8/Encoding.php");

use \ForceUTF8\Encoding;

// Test the testing class itself.
Test::is("'yes' is true", 'yes', true);
Test::not("1 is not false", 1, false);
Test::identical("true is identical to true", true, true);
Test::true("1 is true", 1);

// ForceUTF8 tests.
Test::not("Source files must not use the same encoding before conversion.",
  file_get_contents(dirname(__FILE__)."/data/test1.txt"),
  file_get_contents(dirname(__FILE__)."/data/test1Latin.txt"));

Test::identical("Simple Encoding works.",
  file_get_contents(dirname(__FILE__)."/data/test1.txt"),
  Encoding::toUTF8(file_get_contents(dirname(__FILE__)."/data/test1Latin.txt")));

function test_arrays_are_different(){
  $arr1 = array(
    file_get_contents(dirname(__FILE__)."/data/test1Latin.txt"),
    file_get_contents(dirname(__FILE__)."/data/test1.txt"),
    file_get_contents(dirname(__FILE__)."/data/test1Latin.txt"));
  $arr2 = array(
    file_get_contents(dirname(__FILE__)."/data/test1.txt"),
    file_get_contents(dirname(__FILE__)."/data/test1.txt"),
    file_get_contents(dirname(__FILE__)."/data/test1.txt"));
  return $arr1 != $arr2;
}

function test_encoding_of_arrays(){
  $arr1 = array(
    file_get_contents(dirname(__FILE__)."/data/test1Latin.txt"),
    file_get_contents(dirname(__FILE__)."/data/test1.txt"),
    file_get_contents(dirname(__FILE__)."/data/test1Latin.txt"));
  $arr2 = array(
    file_get_contents(dirname(__FILE__)."/data/test1.txt"),
    file_get_contents(dirname(__FILE__)."/data/test1.txt"),
    file_get_contents(dirname(__FILE__)."/data/test1.txt"));
  return Encoding::toUTF8($arr1) == $arr2;
}

Test::true("Source arrays are different.", test_arrays_are_different());
Test::true("Encoding of array works.", test_encoding_of_arrays());

Test::identical("fixUTF8() maintains UTF-8 string.",
  file_get_contents(dirname(__FILE__)."/data/test1.txt"),
  Encoding::fixUTF8(file_get_contents(dirname(__FILE__)."/data/test1.txt")));

Test::not("An UTF-8 double encoded string differs from a correct UTF-8 string.",
  file_get_contents(dirname(__FILE__)."/data/test1.txt"),
  utf8_encode(file_get_contents(dirname(__FILE__)."/data/test1.txt")));

Test::identical("fixUTF8() reverts to UTF-8 a double encoded string.",
  file_get_contents(dirname(__FILE__)."/data/test1.txt"),
  Encoding::fixUTF8(utf8_encode(file_get_contents(dirname(__FILE__)."/data/test1.txt"))));

function test_double_encoded_arrays_are_different(){
  $arr1 = array(
    utf8_encode(file_get_contents(dirname(__FILE__)."/data/test1Latin.txt")),
    utf8_encode(file_get_contents(dirname(__FILE__)."/data/test1.txt")),
    utf8_encode(file_get_contents(dirname(__FILE__)."/data/test1Latin.txt")));
  $arr2 = array(
    file_get_contents(dirname(__FILE__)."/data/test1.txt"),
    file_get_contents(dirname(__FILE__)."/data/test1.txt"),
    file_get_contents(dirname(__FILE__)."/data/test1.txt"));
  return $arr1 != $arr2;
}

function test_double_encoded_arrays_fix(){
  $arr1 = array(
    utf8_encode(file_get_contents(dirname(__FILE__)."/data/test1Latin.txt")),
    utf8_encode(file_get_contents(dirname(__FILE__)."/data/test1.txt")),
    utf8_encode(file_get_contents(dirname(__FILE__)."/data/test1Latin.txt")));
  $arr2 = array(
    file_get_contents(dirname(__FILE__)."/data/test1.txt"),
    file_get_contents(dirname(__FILE__)."/data/test1.txt"),
    file_get_contents(dirname(__FILE__)."/data/test1.txt"));
  return Encoding::fixUTF8($arr1) == $arr2;
}

Test::true("Source arrays are different (fixUTF8).", test_double_encoded_arrays_are_different());
Test::true("Fixing of double encoded array works.", test_double_encoded_arrays_fix());

Test::identical("fixUTF8() Example 1 still working.",
  Encoding::fixUTF8("FÃÂ©dération Camerounaise de Football\n"),
  "Fédération Camerounaise de Football\n");
Test::identical("fixUTF8() Example 2 still working.",
  Encoding::fixUTF8("FÃ©dÃ©ration Camerounaise de Football\n"),
  "Fédération Camerounaise de Football\n");
Test::identical("fixUTF8() Example 3 still working.",
  Encoding::fixUTF8("FÃÂ©dÃÂ©ration Camerounaise de Football\n"),
  "Fédération Camerounaise de Football\n");
Test::identical("fixUTF8() Example 4 still working.",
  Encoding::fixUTF8("FÃÂÂÂÂ©dÃÂÂÂÂ©ration Camerounaise de Football\n"),
  "Fédération Camerounaise de Football\n");

Test::totals();
