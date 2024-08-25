<?php

namespace Hackzilla\PasswordGenerator\Generator;

use Hackzilla\PasswordGenerator\Exception\InvalidOptionException;
use Hackzilla\PasswordGenerator\Exception\InvalidOptionTypeException;
use Hackzilla\PasswordGenerator\Model\Option\Option;
use Hackzilla\PasswordGenerator\RandomGenerator\RandomGeneratorInterface;

interface PasswordGeneratorInterface
{
    /**
     * Possible options.
     *
     * @return array
     */
    public function getOptions();

    /**
     * Set password generator option.
     *
     * @param string $option
     * @param array  $optionSettings
     *
     * @return $this
     * @throws InvalidOptionTypeException
     */
    public function setOption($option, $optionSettings);

    /**
     * Get option.
     *
     * @param $option
     *
     * @return mixed
     */
    public function getOption($option);

//    /**
//     * Remove Option.
//     *
//     * @param string $option
//     *
//     * @return $this
//     */
//    public function removeOption($option);

    /**
     * Set password generator option value.
     *
     * @param string $option
     * @param $value
     *
     * @return $this
     */
    public function setOptionValue($option, $value);

    /**
     * Get option value.
     *
     * @param $option
     *
     * @return mixed
     */
    public function getOptionValue($option);

    /**
     * @param string $parameter
     * @param mixed  $value
     *
     * @return $this
     */
    public function setParameter($parameter, $value);

    /**
     * @param string $parameter
     * @param mixed  $default
     *
     * @return null|mixed
     */
    public function getParameter($parameter);

    /**
     * Generate $count number of passwords.
     *
     * @param int $count Number of passwords to return
     *
     * @return string[]
     *
     * @throws \InvalidArgumentException
     */
    public function generatePasswords($count = 1);

    /**
     * Generate one password based on options.
     *
     * @return string password
     */
    public function generatePassword();


//    /**
//     * Set source of randomness.
//     *
//     * @param RandomGeneratorInterface $randomGenerator
//     *
//     * @return $this
//     */
//    public function setRandomGenerator(RandomGeneratorInterface $randomGenerator);

//    /**
//     * Generate a random value
//     *
//     * @param int $min
//     * @param int $max
//     *
//     * @return int
//     */
//    public function randomInteger($min, $max);
}
