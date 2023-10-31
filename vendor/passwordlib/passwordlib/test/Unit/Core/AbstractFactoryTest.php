<?php

require_once 'vfsStream/vfsStream.php';

use PasswordLibTest\Mocks\Core\Factory;

class Unit_Core_AbstractFactoryTest extends PHPUnit_Framework_TestCase {

    protected function setUp() {
        $root = vfsStream::setup('PasswordLibTest');
        //Setup Folders
        $core = vfsStream::newDirectory('Core')->at($root);
        $af = vfsStream::newDirectory('AbstractFactory')->at($core);

        // Create Files
        vfsStream::newFile('test.php')->at($af);
        vfsStream::newFile('Some234Foo234Bar98Name.php')->at($af);
        vfsStream::newFile('Invalid.csv')->at($af);
        vfsStream::newFile('badlocation.php')->at($core);
    }

    /**
     * @covers PasswordLib\Core\AbstractFactory::registerType
     */
    public function testRegisterType() {
        $factory = new Factory;
        $factory->registerType('test', 'iteratoraggregate', 'foo', 'ArrayObject', false);
    }

    /**
     * @covers PasswordLib\Core\AbstractFactory::registerType
     * @expectedException InvalidArgumentException
     */
    public function testRegisterTypeFail() {
        $factory = new Factory;
        $factory->registerType('test', 'iterator', 'foo', 'ArrayObject', false);
    }

    /**
     * @covers PasswordLib\Core\AbstractFactory::registerType
     */
    public function testRegisterTypeInstantiate() {
        $factory = new Factory;
        $factory->registerType('test', 'iteratoraggregate', 'foo', 'ArrayObject', true);
    }

    public function testLoadFiles() {
        $dir = vfsStream::url('PasswordLibTest/Core/AbstractFactory');

        $result = array();
        $callback = function($name, $class) use (&$result) {
            $result[$name] = $class;
        };

        $factory = new Factory();
        $factory->loadFiles($dir, 'foo\\', $callback);

        $expect = array(
            'test' => 'foo\\test',
            'Some234Foo234Bar98Name' => 'foo\\Some234Foo234Bar98Name'
        );

        $this->assertEquals($expect, $result);
    }


}
