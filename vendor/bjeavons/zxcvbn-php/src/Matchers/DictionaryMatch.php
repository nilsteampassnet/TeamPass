<?php

declare(strict_types=1);

namespace ZxcvbnPhp\Matchers;

use JetBrains\PhpStorm\ArrayShape;
use ZxcvbnPhp\Matcher;
use ZxcvbnPhp\Math\Binomial;

class DictionaryMatch extends BaseMatch
{
    public $pattern = 'dictionary';

    /** @var string The name of the dictionary that the token was found in. */
    public $dictionaryName;

    /** @var int The rank of the token in the dictionary. */
    public $rank;

    /** @var string The word that was matched from the dictionary. */
    public $matchedWord;

    /** @var bool Whether or not the matched word was reversed in the token. */
    public $reversed = false;

    /** @var bool Whether or not the token contained l33t substitutions. */
    public $l33t = false;

    /** @var array A cache of the frequency_lists json file */
    protected static $rankedDictionaries = [];

    protected const START_UPPER = "/^[A-Z][^A-Z]+$/u";
    protected const END_UPPER = "/^[^A-Z]+[A-Z]$/u";
    protected const ALL_UPPER = "/^[^a-z]+$/u";
    protected const ALL_LOWER = "/^[^A-Z]+$/u";

    /**
     * Match occurrences of dictionary words in password.
     *
     * @param string $password
     * @param array $userInputs
     * @param array $rankedDictionaries
     * @return DictionaryMatch[]
     */
    public static function match(string $password, array $userInputs = [], array $rankedDictionaries = []): array
    {
        $matches = [];
        if ($rankedDictionaries) {
            $dicts = $rankedDictionaries;
        } else {
            $dicts = static::getRankedDictionaries();
        }

        if (!empty($userInputs)) {
            $dicts['user_inputs'] = [];
            foreach ($userInputs as $rank => $input) {
                $input_lower = mb_strtolower($input);
                $dicts['user_inputs'][$input_lower] = $rank + 1; // rank starts at 1, not 0
            }
        }
        foreach ($dicts as $name => $dict) {
            $results = static::dictionaryMatch($password, $dict);
            foreach ($results as $result) {
                $result['dictionary_name'] = $name;
                $matches[] = new static($password, $result['begin'], $result['end'], $result['token'], $result);
            }
        }
        Matcher::usortStable($matches, [Matcher::class, 'compareMatches']);
        return $matches;
    }

    /**
     * @param string $password
     * @param int $begin
     * @param int $end
     * @param string $token
     * @param array $params An array with keys: [dictionary_name, matched_word, rank].
     */
    public function __construct(string $password, int $begin, int $end, string $token, array $params = [])
    {
        parent::__construct($password, $begin, $end, $token);
        if (!empty($params)) {
            $this->dictionaryName = $params['dictionary_name'] ?? '';
            $this->matchedWord = $params['matched_word'] ?? '';
            $this->rank = $params['rank'] ?? 0;
        }
    }

    /**
     * @param bool $isSoleMatch
     * @return array
     */
    #[ArrayShape(['warning' => 'string', 'suggestions' => 'string[]'])]
    public function getFeedback(bool $isSoleMatch): array
    {
        $startUpper = '/^[A-Z][^A-Z]+$/u';
        $allUpper = '/^[^a-z]+$/u';

        $feedback = [
            'warning' => $this->getFeedbackWarning($isSoleMatch),
            'suggestions' => []
        ];

        if (preg_match($startUpper, $this->token)) {
            $feedback['suggestions'][] = "Capitalization doesn't help very much";
        } elseif (preg_match($allUpper, $this->token) && mb_strtolower($this->token) != $this->token) {
            $feedback['suggestions'][] = "All-uppercase is almost as easy to guess as all-lowercase";
        }

        return $feedback;
    }

    public function getFeedbackWarning(bool $isSoleMatch): string
    {
        switch ($this->dictionaryName) {
            case 'passwords':
                if ($isSoleMatch && !$this->l33t && !$this->reversed) {
                    if ($this->rank <= 10) {
                        return 'This is a top-10 common password';
                    } elseif ($this->rank <= 100) {
                        return 'This is a top-100 common password';
                    } else {
                        return 'This is a very common password';
                    }
                } elseif ($this->getGuessesLog10() <= 4) {
                    return 'This is similar to a commonly used password';
                }
                break;
            case 'english_wikipedia':
                if ($isSoleMatch) {
                    return 'A word by itself is easy to guess';
                }
                break;
            case 'surnames':
            case 'male_names':
            case 'female_names':
                if ($isSoleMatch) {
                    return 'Names and surnames by themselves are easy to guess';
                } else {
                    return 'Common names and surnames are easy to guess';
                }
        }

        return '';
    }

    /**
     * Attempts to find the provided password (as well as all possible substrings) in a dictionary.
     *
     * @param string $password
     * @param array $dict
     * @return array
     */
    protected static function dictionaryMatch(string $password, array $dict): array
    {
        $result = [];
        $length = mb_strlen($password);

        $pw_lower = mb_strtolower($password);

        foreach (range(0, $length - 1) as $i) {
            foreach (range($i, $length - 1) as $j) {
                $word = mb_substr($pw_lower, $i, $j - $i + 1);

                if (isset($dict[$word])) {
                    $result[] = [
                        'begin' => $i,
                        'end' => $j,
                        'token' => mb_substr($password, $i, $j - $i + 1),
                        'matched_word' => $word,
                        'rank' => $dict[$word],
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Load ranked frequency dictionaries.
     *
     * @return array
     */
    protected static function getRankedDictionaries(): array
    {
        if (empty(self::$rankedDictionaries)) {
            $json = file_get_contents(dirname(__FILE__) . '/frequency_lists.json');
            $data = json_decode($json, true);

            $rankedLists = [];
            foreach ($data as $name => $words) {
                $rankedLists[$name] = array_combine($words, range(1, count($words)));
            }
            self::$rankedDictionaries = $rankedLists;
        }

        return self::$rankedDictionaries;
    }

    protected function getRawGuesses(): float
    {
        $guesses = $this->rank;
        $guesses *= $this->getUppercaseVariations();

        return $guesses;
    }

    protected function getUppercaseVariations(): float
    {
        $word = $this->token;
        if (preg_match(self::ALL_LOWER, $word) || mb_strtolower($word) === $word) {
            return 1;
        }

        // a capitalized word is the most common capitalization scheme,
        // so it only doubles the search space (uncapitalized + capitalized).
        // allcaps and end-capitalized are common enough too, underestimate as 2x factor to be safe.
        foreach (array(self::START_UPPER, self::END_UPPER, self::ALL_UPPER) as $regex) {
            if (preg_match($regex, $word)) {
                return 2;
            }
        }

        // otherwise calculate the number of ways to capitalize U+L uppercase+lowercase letters
        // with U uppercase letters or less. or, if there's more uppercase than lower (for eg. PASSwORD),
        // the number of ways to lowercase U+L letters with L lowercase letters or less.
        $uppercase = count(array_filter(preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY), 'ctype_upper'));
        $lowercase = count(array_filter(preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY), 'ctype_lower'));

        $variations = 0;
        for ($i = 1; $i <= min($uppercase, $lowercase); $i++) {
            $variations += Binomial::binom($uppercase + $lowercase, $i);
        }
        return $variations;
    }
}
