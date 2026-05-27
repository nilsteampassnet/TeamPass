<?php

use PasswordLibTest\Mocks\Random\Mixer;
use PasswordLibTest\Mocks\Random\Source;

use PasswordLib\Core\Strength;
use PasswordLibTest\Mocks\Core\Strength as MockStrength;
use PasswordLib\Random\Factory;

class Unit_Random_FactoryTest extends PHPUnit_Framework_TestCase {

    /**
     * @covers PasswordLib\Random\Factory::__construct
     * @covers PasswordLib\Random\Factory::loadMixers
     * @covers PasswordLib\Random\Factory::loadSources
     * @covers PasswordLib\Random\Factory::registerMixer
     * @covers PasswordLib\Random\Factory::registerSource
     */
    public function testConstruct() {
        $factory = new Factory;
        $this->assertTrue($factory instanceof PasswordLib\Random\Factory);
    }

    /**
     * @covers PasswordLib\Random\Factory
     */
    public function testGetGeneratorFallback() {
        $factory = new Factory;
        $generator = $factory->getGenerator(new MockStrength(MockStrength::MEDIUMLOW));
        $mixer = call_user_func(array(
            get_class($generator->getMixer()),
            'getStrength'
        ));
        $this->assertTrue($mixer->compare(new Strength(Strength::LOW)) <= 0);
    }

    /**
     * @covers PasswordLib\Random\Factory
     * @expectedException RuntimeException
     */
    public function testGetGeneratorFallbackFail() {
        $factory = new Factory;
        $generator = $factory->getGenerator(new MockStrength(MockStrength::SUPERHIGH));
    }

    /**
     * @covers PasswordLib\Random\Factory::registerSource
     * @covers PasswordLib\Random\Factory::getSources
     */
    public function testRegisterSource() {
        $factory = new Factory;
        $factory->registerSource('mock', 'PasswordLibTest\Mocks\Random\Source');
        $test = $factory->getSources();
        $this->assertTrue(in_array('PasswordLibTest\Mocks\Random\Source', $test));
    }

    /**
     * @covers PasswordLib\Random\Factory::registerSource
     * @covers PasswordLib\Random\Factory::getSources
     * @expectedException InvalidArgumentException
     */
    public function testRegisterSourceFail() {
        $factory = new Factory;
        $factory->registerSource('mock', 'stdclass');
    }


    /**
     * @covers PasswordLib\Random\Factory::registerMixer
     * @covers PasswordLib\Random\Factory::getMixers
     */
    public function testRegisterMixer() {
        $factory = new Factory;
        $factory->registerMixer('mock', 'PasswordLibTest\Mocks\Random\Mixer');
        $test = $factory->getMixers();
        $this->assertTrue(in_array('PasswordLibTest\Mocks\Random\Mixer', $test));
    }

    /**
     * @covers PasswordLib\Random\Factory::registerMixer
     * @covers PasswordLib\Random\Factory::getMixers
     * @expectedException InvalidArgumentException
     */
    public function testRegisterMixerFail() {
        $factory = new Factory;
        $factory->registerMixer('mock', 'stdclass');
    }

    /**
     * @covers PasswordLib\Random\Factory::getMediumStrengthGenerator
     * @covers PasswordLib\Random\Factory::getGenerator
     * @covers PasswordLib\Random\Factory::findMixer
     */
    public function testGetMediumStrengthGenerator() {
        $factory = new Factory;
        $generator = $factory->getMediumStrengthGenerator();
        $this->assertTrue($generator instanceof PasswordLib\Random\Generator);
        $mixer = call_user_func(array(
            get_class($generator->getMixer()),
            'getStrength'
        ));
        $this->assertTrue($mixer->compare(new Strength(Strength::MEDIUM)) <= 0);
        foreach ($generator->getSources() as $source) {
            $strength = call_user_func(array(get_class($source), 'getStrength'));
            $this->assertTrue($strength->compare(new Strength(Strength::MEDIUM)) >= 0);
        }
    }


}
