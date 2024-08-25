<?php

namespace Hackzilla\PasswordGenerator\Generator;

use Hackzilla\PasswordGenerator\Exception\ImpossibleMinMaxLimitsException;
use Hackzilla\PasswordGenerator\Exception\InvalidOptionException;

/**
 * Class RequirementPasswordGenerator
 *
 * Works just like ComputerPasswordGenerator with the addition of minimum and maximum counts.
 *
 * @package Hackzilla\PasswordGenerator\Generator
 */
class RequirementPasswordGenerator extends ComputerPasswordGenerator
{
    private $minimumCounts = array();
    private $maximumCounts = array();
    private $validOptions = array();
    private $dirtyCheck = true;

    /**
     */
    public function __construct()
    {
        parent::__construct();

        $this->validOptions = array(
            self::OPTION_UPPER_CASE,
            self::OPTION_LOWER_CASE,
            self::OPTION_NUMBERS,
            self::OPTION_SYMBOLS,
        );
    }

    /**
     * Generate one password based on options.
     *
     * @return string password
     * @throws ImpossibleMinMaxLimitsException
     * @throws \Hackzilla\PasswordGenerator\Exception\CharactersNotFoundException
     */
    public function generatePassword()
    {
        if ($this->dirtyCheck) {
            if (!$this->validLimits()) {
                throw new ImpossibleMinMaxLimitsException();
            }

            $this->dirtyCheck = false;
        }

        do {
            $password = parent::generatePassword();
        } while (!$this->validatePassword($password));

        return $password;
    }

    /**
     * Password minimum count for option.
     *
     * @param string $option Use class constants
     *
     * @return int|null
     */
    public function getMinimumCount($option)
    {
        return isset($this->minimumCounts[$option]) ? $this->minimumCounts[$option] : null;
    }

    /**
     * Password maximum count for option.
     *
     * @param string $option Use class constants
     *
     * @return int|null
     */
    public function getMaximumCount($option)
    {
        return isset($this->maximumCounts[$option]) ? $this->maximumCounts[$option] : null;
    }

    /**
     * Set minimum count of option for desired password(s).
     *
     * @param string   $option Use class constants
     * @param int|null $characterCount
     *
     * @return $this
     *
     * @throws InvalidOptionException
     */
    public function setMinimumCount($option, $characterCount)
    {
        $this->dirtyCheck = true;

        if (!$this->validOption($option)) {
            throw new InvalidOptionException('Invalid Option');
        }

        if (is_null($characterCount)) {
            unset($this->minimumCounts[$option]);

            return $this;
        }

        if (!is_int($characterCount) || $characterCount < 0) {
            throw new \InvalidArgumentException('Expected non-negative integer');
        }

        $this->minimumCounts[$option] = $characterCount;

        return $this;
    }

    /**
     * Set maximum count of option for desired password(s).
     *
     * @param string   $option Use class constants
     * @param int|null $characterCount
     *
     * @return $this
     *
     * @throws InvalidOptionException
     */
    public function setMaximumCount($option, $characterCount)
    {
        $this->dirtyCheck = true;

        if (!$this->validOption($option)) {
            throw new InvalidOptionException('Invalid Option');
        }

        if (is_null($characterCount)) {
            unset($this->maximumCounts[$option]);

            return $this;
        }

        if (!is_int($characterCount) || $characterCount < 0) {
            throw new \InvalidArgumentException('Expected non-negative integer');
        }

        $this->maximumCounts[$option] = $characterCount;

        return $this;
    }

    public function validLimits()
    {
        $elements = 0;

        if ($this->getOptionValue(self::OPTION_UPPER_CASE)) {
            $elements++;
        }

        if ($this->getOptionValue(self::OPTION_LOWER_CASE)) {
            $elements++;
        }

        if ($this->getOptionValue(self::OPTION_NUMBERS)) {
            $elements++;
        }

        if ($this->getOptionValue(self::OPTION_SYMBOLS)) {
            $elements++;
        }

        // check if there is wiggle room in minimums
        $total = 0;

        foreach ($this->minimumCounts as $minOption => $minCount) {
            $total += $minCount;
        }

        if ($total > $this->getLength()) {
            return false;
        }

        // check if there is wiggle room in maximums
        if ($elements <= count($this->maximumCounts)) {
            $total = 0;

            foreach ($this->maximumCounts as $maxOption => $maxCount) {
                $total += $maxCount;
            }

            if ($total < $this->getLength()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $option
     *
     * @return bool
     */
    public function validOption($option)
    {
        return in_array($option, $this->validOptions, true);
    }

    /**
     * Generate $count number of passwords.
     *
     * @param int $count Number of passwords to return
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    public function generatePasswords($count = 1)
    {
        if (!is_int($count)) {
            throw new \InvalidArgumentException('Expected integer');
        } elseif ($count < 0) {
            throw new \InvalidArgumentException('Expected positive integer');
        }

        $passwords = array();

        for ($i = 0; $i < $count; $i++) {
            $passwords[] = $this->generatePassword();
        }

        return $passwords;
    }

    /**
     * Check password is valid when comparing to minimum and maximum counts of options.
     *
     * @param string $password
     *
     * @return bool
     */
    public function validatePassword($password)
    {
        foreach ($this->validOptions as $option) {
            $minCount = $this->getMinimumCount($option);
            $maxCount = $this->getMaximumCount($option);
            $count = strlen(preg_replace('|[^'.preg_quote($this->getParameter($option)).']|', '', $password));

            if (!is_null($minCount) && $count < $minCount) {
                return false;
            }

            if (!is_null($maxCount) && $count > $maxCount) {
                return false;
            }
        }

        return true;
    }
}
