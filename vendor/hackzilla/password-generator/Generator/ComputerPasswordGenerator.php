<?php

namespace Hackzilla\PasswordGenerator\Generator;

use Hackzilla\PasswordGenerator\Exception\CharactersNotFoundException;
use Hackzilla\PasswordGenerator\Model\CharacterSet;
use Hackzilla\PasswordGenerator\Model\Option\Option;

class ComputerPasswordGenerator extends AbstractPasswordGenerator
{
    const OPTION_UPPER_CASE = 'UPPERCASE';
    const OPTION_LOWER_CASE = 'LOWERCASE';
    const OPTION_NUMBERS = 'NUMBERS';
    const OPTION_SYMBOLS = 'SYMBOLS';
    const OPTION_AVOID_SIMILAR = 'AVOID_SIMILAR';
    const OPTION_LENGTH = 'LENGTH';

    const PARAMETER_UPPER_CASE = 'UPPERCASE';
    const PARAMETER_LOWER_CASE = 'LOWERCASE';
    const PARAMETER_NUMBERS = 'NUMBERS';
    const PARAMETER_SYMBOLS = 'SYMBOLS';
    const PARAMETER_SIMILAR = 'AVOID_SIMILAR';

    /**
     */
    public function __construct()
    {
        $this
            ->setOption(self::OPTION_UPPER_CASE, array('type' => Option::TYPE_BOOLEAN, 'default' => true))
            ->setOption(self::OPTION_LOWER_CASE, array('type' => Option::TYPE_BOOLEAN, 'default' => true))
            ->setOption(self::OPTION_NUMBERS, array('type' => Option::TYPE_BOOLEAN, 'default' => true))
            ->setOption(self::OPTION_SYMBOLS, array('type' => Option::TYPE_BOOLEAN, 'default' => false))
            ->setOption(self::OPTION_AVOID_SIMILAR, array('type' => Option::TYPE_BOOLEAN, 'default' => true))
            ->setOption(self::OPTION_LENGTH, array('type' => Option::TYPE_INTEGER, 'default' => 10))
            ->setParameter(self::PARAMETER_UPPER_CASE, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')
            ->setParameter(self::PARAMETER_LOWER_CASE, 'abcdefghijklmnopqrstuvwxyz')
            ->setParameter(self::PARAMETER_NUMBERS, '0123456789')
            ->setParameter(self::PARAMETER_SYMBOLS, '!@$%^&*()<>,.?/[]{}-=_+')
            ->setParameter(self::PARAMETER_SIMILAR, 'iIl1Oo0')
        ;
    }

    /**
     * Generate character list for us in generating passwords.
     *
     * @return CharacterSet Character list
     *
     * @throws CharactersNotFoundException
     */
    public function getCharacterList()
    {
        $characters = '';

        if ($this->getOptionValue(self::OPTION_UPPER_CASE)) {
            $characters .= $this->getParameter(self::PARAMETER_UPPER_CASE, '');
        }

        if ($this->getOptionValue(self::OPTION_LOWER_CASE)) {
            $characters .= $this->getParameter(self::PARAMETER_LOWER_CASE, '');
        }

        if ($this->getOptionValue(self::OPTION_NUMBERS)) {
            $characters .= $this->getParameter(self::PARAMETER_NUMBERS, '');
        }

        if ($this->getOptionValue(self::OPTION_SYMBOLS)) {
            $characters .= $this->getParameter(self::PARAMETER_SYMBOLS, '');
        }

        if ($this->getOptionValue(self::OPTION_AVOID_SIMILAR)) {
            $removeCharacters = \str_split($this->getParameter(self::PARAMETER_SIMILAR, ''));
            $characters = \str_replace($removeCharacters, '', $characters);
        }

        if (!$characters) {
            throw new CharactersNotFoundException('No character sets selected.');
        }

        return new CharacterSet($characters);
    }

    /**
     * Generate one password based on options.
     *
     * @return string password
     */
    public function generatePassword()
    {
        $characterList = $this->getCharacterList()->getCharacters();
        $characters = \strlen($characterList);
        $password = '';

        $length = $this->getLength();

        for ($i = 0; $i < $length; ++$i) {
            $password .= $characterList[$this->randomInteger(0, $characters - 1)];
        }

        return $password;
    }

    /**
     * Password length.
     *
     * @return int
     */
    public function getLength()
    {
        return $this->getOptionValue(self::OPTION_LENGTH);
    }

    /**
     * Set length of desired password(s).
     *
     * @param int $characterCount
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setLength($characterCount)
    {
        if (!is_int($characterCount) || $characterCount < 1) {
            throw new \InvalidArgumentException('Expected positive integer');
        }

        $this->setOptionValue(self::OPTION_LENGTH, $characterCount);

        return $this;
    }

    /**
     * Are Uppercase characters enabled?
     *
     * @return bool
     */
    public function getUppercase()
    {
        return $this->getOptionValue(self::OPTION_UPPER_CASE);
    }

    /**
     * Enable uppercase characters.
     *
     * @param bool $enable
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setUppercase($enable = true)
    {
        if (!is_bool($enable)) {
            throw new \InvalidArgumentException('Expected boolean');
        }

        $this->setOptionValue(self::OPTION_UPPER_CASE, $enable);

        return $this;
    }

    /**
     * Are Lowercase characters enabled?
     *
     * @return string
     */
    public function getLowercase()
    {
        return $this->getOptionValue(self::OPTION_LOWER_CASE);
    }

    /**
     * Enable lowercase characters.
     *
     * @param bool $enable
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setLowercase($enable = true)
    {
        if (!is_bool($enable)) {
            throw new \InvalidArgumentException('Expected boolean');
        }

        $this->setOptionValue(self::OPTION_LOWER_CASE, $enable);

        return $this;
    }

    /**
     * Are Numbers enabled?
     *
     * @return string
     */
    public function getNumbers()
    {
        return $this->getOptionValue(self::OPTION_NUMBERS);
    }

    /**
     * Enable numbers.
     *
     * @param bool $enable
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setNumbers($enable = true)
    {
        if (!is_bool($enable)) {
            throw new \InvalidArgumentException('Expected boolean');
        }

        $this->setOptionValue(self::OPTION_NUMBERS, $enable);

        return $this;
    }

    /**
     * Are Symbols enabled?
     *
     * @return string
     */
    public function getSymbols()
    {
        return $this->getOptionValue(self::OPTION_SYMBOLS);
    }

    /**
     * Enable symbol characters.
     *
     * @param bool $enable
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setSymbols($enable = true)
    {
        if (!is_bool($enable)) {
            throw new \InvalidArgumentException('Expected boolean');
        }

        $this->setOptionValue(self::OPTION_SYMBOLS, $enable);

        return $this;
    }

    /**
     * Avoid similar characters enabled?
     *
     * @return string
     */
    public function getAvoidSimilar()
    {
        return $this->getOptionValue(self::OPTION_AVOID_SIMILAR);
    }

    /**
     * Enable characters to be removed when avoiding similar characters.
     *
     * @param bool $enable
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setAvoidSimilar($enable = true)
    {
        if (!is_bool($enable)) {
            throw new \InvalidArgumentException('Expected boolean');
        }

        $this->setOptionValue(self::OPTION_AVOID_SIMILAR, $enable);

        return $this;
    }
}
