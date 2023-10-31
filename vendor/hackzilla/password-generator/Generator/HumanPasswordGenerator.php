<?php

namespace Hackzilla\PasswordGenerator\Generator;

use Hackzilla\PasswordGenerator\Exception\FileNotFoundException;
use Hackzilla\PasswordGenerator\Exception\ImpossiblePasswordLengthException;
use Hackzilla\PasswordGenerator\Exception\NotEnoughWordsException;
use Hackzilla\PasswordGenerator\Exception\WordsNotFoundException;
use Hackzilla\PasswordGenerator\Model\Option\Option;

class HumanPasswordGenerator extends AbstractPasswordGenerator
{
    const OPTION_WORDS = 'WORDS';
    const OPTION_MIN_WORD_LENGTH = 'MIN';
    const OPTION_MAX_WORD_LENGTH = 'MAX';
    const OPTION_LENGTH = 'LENGTH';

    const PARAMETER_DICTIONARY_FILE = 'DICTIONARY';
    const PARAMETER_WORD_CACHE = 'CACHE';
    const PARAMETER_WORD_SEPARATOR = 'SEPARATOR';

    private $minWordLength;
    private $maxWordLength;

    public function __construct()
    {
        $this
            ->setOption(self::OPTION_LENGTH, array('type' => Option::TYPE_INTEGER, 'default' => null))
            ->setOption(self::OPTION_WORDS, array('type' => Option::TYPE_INTEGER, 'default' => 4))
            ->setOption(self::OPTION_MIN_WORD_LENGTH, array('type' => Option::TYPE_INTEGER, 'default' => 3))
            ->setOption(self::OPTION_MAX_WORD_LENGTH, array('type' => Option::TYPE_INTEGER, 'default' => 20))
            ->setParameter(self::PARAMETER_WORD_SEPARATOR, '')
        ;
    }

    /**
     * Generate word list for us in generating passwords.
     *
     * @return string[] Words
     *
     * @throws WordsNotFoundException
     */
    public function generateWordList()
    {
        if ($this->getParameter(self::PARAMETER_WORD_CACHE) !== null) {
            $this->findWordListLength();

            return $this->getParameter(self::PARAMETER_WORD_CACHE);
        }

        $words = explode("\n", \file_get_contents($this->getWordList()));

        $minWordLength = $this->getOptionValue(self::OPTION_MIN_WORD_LENGTH);
        $maxWordLength = $this->getOptionValue(self::OPTION_MAX_WORD_LENGTH);

        foreach ($words as $i => $word) {
            $words[$i] = trim($word);
            $wordLength = \strlen($word);

            if ($wordLength > $maxWordLength || $wordLength < $minWordLength) {
                unset($words[$i]);
            }
        }

        $words = \array_values($words);

        if (!$words) {
            throw new WordsNotFoundException('No words selected.');
        }

        $this->setParameter(self::PARAMETER_WORD_CACHE, $words);
        $this->findWordListLength();

        return $words;
    }

    private function findWordListLength()
    {
        $words = $this->getParameter(self::PARAMETER_WORD_CACHE);

        $this->minWordLength = INF;
        $this->maxWordLength = 0;

        foreach ($words as $word) {
            $wordLength = \strlen($word);

            $this->minWordLength = min($wordLength, $this->minWordLength);
            $this->maxWordLength = max($wordLength, $this->maxWordLength);
        }
    }

    private function generateWordListSubset($min, $max)
    {
        $wordList = $this->generateWordList();
        $newWordList = array();

        foreach ($wordList as $word) {
            $wordLength = strlen($word);

            if ($wordLength < $min || $wordLength > $max) {
                continue;
            }

            $newWordList[] = $word;
        }

        return $newWordList;
    }

    /**
     * Generate one password based on options.
     *
     * @return string password
     *
     * @throws WordsNotFoundException
     * @throws ImpossiblePasswordLengthException
     */
    public function generatePassword()
    {
        $wordList = $this->generateWordList();

        $words = \count($wordList);

        if (!$words) {
            throw new WordsNotFoundException('No words selected.');
        }

        $password = '';
        $wordCount = $this->getWordCount();

        if (
            $this->getLength() > 0 &&
            (
                $this->getMinPasswordLength() > $this->getLength()
                ||
                $this->getMaxPasswordLength() < $this->getLength()
            )
        ) {
            throw new ImpossiblePasswordLengthException();
        }

        if (!$this->getLength()) {
            for ($i = 0; $i < $wordCount; $i++) {
                if ($i) {
                    $password .= $this->getWordSeparator();
                }

                $password .= $this->randomWord();
            }

            return $password;
        }

        while(--$wordCount) {
            $thisMin = $this->getLength() - strlen($password) - ($wordCount * $this->getMaxWordLength()) - (strlen($this->getWordSeparator()) * $wordCount);
            $thisMax = $this->getLength() - strlen($password) - ($wordCount * $this->getMinWordLength()) - (strlen($this->getWordSeparator()) * $wordCount);

            if ($thisMin < 1) {
                $thisMin = $this->getMinWordLength();
            }

            if ($thisMax > $this->getMaxWordLength()) {
                $thisMax = $this->getMaxWordLength();
            }

            $length = $this->randomInteger($thisMin, $thisMax);

            $password .= $this->randomWord($length, $length);

            if ($wordCount) {
                $password .= $this->getWordSeparator();
            }
        }

        $desiredLength = $this->getLength() - strlen($password);
        $password .= $this->randomWord($desiredLength, $desiredLength);

        return $password;
    }

    /**
     * @param null|int $minLength
     * @param null|int $maxLength
     *
     * @return string
     *
     * @throws NotEnoughWordsException
     */
    public function randomWord($minLength = null, $maxLength = null)
    {
        if (is_null($minLength)) {
            $minLength = $this->getMinWordLength();
        }

        if (is_null($maxLength)) {
            $maxLength = $this->getMaxWordLength();
        }

        $wordList = $this->generateWordListSubset($minLength, $maxLength);
        $words = \count($wordList);

        if (!$words) {
            throw new NotEnoughWordsException(sprintf('No words with a length between %d and %d', $minLength, $maxLength));
        }

        return $wordList[$this->randomInteger(0, $words - 1)];
    }

    /**
     * Get number of words in desired password.
     *
     * @return int
     */
    public function getWordCount()
    {
        return $this->getOptionValue(self::OPTION_WORDS);
    }

    /**
     * Set number of words in desired password(s).
     *
     * @param int $characterCount
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setWordCount($characterCount)
    {
        if (!is_int($characterCount) || $characterCount < 1) {
            throw new \InvalidArgumentException('Expected positive integer');
        }

        $this->setOptionValue(self::OPTION_WORDS, $characterCount);

        return $this;
    }

    /**
     * get max word length.
     *
     * @return int
     */
    public function getMaxWordLength()
    {
        if (is_null($this->maxWordLength)) {
            return $this->getOptionValue(self::OPTION_MAX_WORD_LENGTH);
        }

        return min(
            $this->maxWordLength,
            $this->getOptionValue(self::OPTION_MAX_WORD_LENGTH)
        );
    }

    /**
     * set max word length.
     *
     * @param int $length
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setMaxWordLength($length)
    {
        if (!is_int($length) || $length < 1) {
            throw new \InvalidArgumentException('Expected positive integer');
        }

        $this->setOptionValue(self::OPTION_MAX_WORD_LENGTH, $length);
        $this->setParameter(self::PARAMETER_WORD_CACHE, null);
        $this->minWordLength = null;
        $this->maxWordLength = null;

        return $this;
    }

    /**
     * get min word length.
     *
     * @return int
     */
    public function getMinWordLength()
    {
        return max(
            $this->minWordLength,
            $this->getOptionValue(self::OPTION_MIN_WORD_LENGTH)
        );
    }

    /**
     * set min word length.
     *
     * @param int $length
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setMinWordLength($length)
    {
        if (!is_int($length) || $length < 1) {
            throw new \InvalidArgumentException('Expected positive integer');
        }

        $this->setOptionValue(self::OPTION_MIN_WORD_LENGTH, $length);
        $this->setParameter(self::PARAMETER_WORD_CACHE, null);
        $this->minWordLength = null;
        $this->maxWordLength = null;

        return $this;
    }

    /**
     * Set word list.
     *
     * @param string $filename
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     * @throws FileNotFoundException
     */
    public function setWordList($filename)
    {
        if (!is_string($filename)) {
            throw new \InvalidArgumentException('Expected string');
        } elseif (!file_exists($filename)) {
            throw new FileNotFoundException('File not found');
        }

        $this->setParameter(self::PARAMETER_DICTIONARY_FILE, $filename);
        $this->setParameter(self::PARAMETER_WORD_CACHE, null);

        return $this;
    }

    /**
     * Get word list filename.
     *
     * @throws FileNotFoundException
     *
     * @return string
     */
    public function getWordList()
    {
        if (!file_exists($this->getParameter(self::PARAMETER_DICTIONARY_FILE))) {
            throw new FileNotFoundException();
        }

        return $this->getParameter(self::PARAMETER_DICTIONARY_FILE);
    }

    /**
     * Get word separator.
     *
     * @return string
     */
    public function getWordSeparator()
    {
        return $this->getParameter(self::PARAMETER_WORD_SEPARATOR);
    }

    /**
     * Set word separator.
     *
     * @param string $separator
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setWordSeparator($separator)
    {
        if (!is_string($separator)) {
            throw new \InvalidArgumentException('Expected string');
        }

        $this->setParameter(self::PARAMETER_WORD_SEPARATOR, $separator);

        return $this;
    }

    /**
     * Password length
     *
     * @return integer
     */
    public function getLength()
    {
        return $this->getOptionValue(self::OPTION_LENGTH);
    }

    /**
     * Set length of desired password(s)
     *
     * @param integer $characterCount
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
     * Calculate how long the password would be using minimum word length
     *
     * @return int
     */
    public function getMinPasswordLength()
    {
        $wordCount = $this->getWordCount();

        return ($this->getMinWordLength() * $wordCount) + (strlen($this->getWordSeparator()) * ($wordCount - 1));
    }

    /**
     * Calculate how long the password would be using maximum word length
     *
     * @return int
     */
    public function getMaxPasswordLength()
    {
        $wordCount = $this->getWordCount();

        return ($this->getMaxWordLength() * $wordCount) + (strlen($this->getWordSeparator()) * ($wordCount - 1));
    }
}
