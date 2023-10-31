<?php

namespace Hackzilla\PasswordGenerator\Generator;

/**
 * Pronounceable passwords generator
 */
class PronounceablePasswordGenerator extends ComputerPasswordGenerator
{
    const PARAMETER_VOWELS = 'VOWELS';

    public function __construct()
    {
        parent::__construct();
        $this->setParameter(self::PARAMETER_VOWELS, 'aeiou');
    }

    public function generatePassword()
    {
        $vowels = $this->getParameter(self::PARAMETER_VOWELS, '');

        if (!strlen($vowels)) {
            return parent::generatePassword();
        }

        $characterList = $this->getCharacterList()->getCharacters();
        $charactersCount = strlen($characterList);
        $password = '';

        $length = $this->getLength();
        $isLastCharVowel = null;

        for ($i = 0; $i < $length; ++$i) {
            do {
                $char = $characterList[$this->randomInteger(0, $charactersCount - 1)];
                $isVowel = false !== stripos($vowels, $char);

            } while (null !== $isLastCharVowel && $isVowel === $isLastCharVowel);

            $password .= $char;
            $isLastCharVowel = $isVowel;
        }

        return $password;
    }
}
