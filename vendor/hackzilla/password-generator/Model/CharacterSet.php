<?php

namespace Hackzilla\PasswordGenerator\Model;

class CharacterSet
{
    private $characters;

    /**
     * CharacterSet constructor.
     *
     * @param string $characters
     */
    public function __construct($characters)
    {
        $this->characters = $characters;
    }

    /**
     * Get Character set
     *
     * @return string
     */
    public function getCharacters()
    {
        return $this->characters;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if (!is_string($this->characters)) {
            return '';
        }

        return $this->characters;
    }
}
