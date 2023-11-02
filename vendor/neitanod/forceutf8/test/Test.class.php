<?php
  class Test {
    protected static $passed = 0;
    protected static $failed = 0;
    protected static $last_echoed;

    public static function true($test_name, $result){
      return static::is($test_name, $result, true);
    }

    public static function is($test_name, $result, $expected){
      if($result == $expected) {
        static::passed($test_name);
      } else {
        static::failed($test_name);
      }
    }

    public static function not($test_name, $result, $expected){
      if($result == $expected) {
        static::failed($test_name);
      } else {
        static::passed($test_name);
      }
    }

    public static function identical($test_name, $result, $expected){
      if($result === $expected) {
        static::passed($test_name);
      } else {
        static::failed($test_name);
      }
    }

    public static function totals(){
      echo "\n";
      echo static::$passed." tests passed.\n";
      echo static::$failed." tests failed.\n";
    }

    private static function failed($test_name){
      echo "\n".$test_name." -> FAILED\n";
      static::$failed++;
    }

    private static function passed($test_name){
      static::character(".");
      static::$passed++;
    }

    private static function character($char){
      echo $char;
      static::$last_echoed = 'char';
    }

    private static function line($msg){
      if(static::$last_echoed == 'char') echo "\n";
      echo $msg."\n";
      static::$last_echoed = 'line';
    }
  }

