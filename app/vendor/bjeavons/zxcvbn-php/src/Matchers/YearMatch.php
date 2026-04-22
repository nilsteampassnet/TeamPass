<?php

declare(strict_types=1);

namespace ZxcvbnPhp\Matchers;

use ZxcvbnPhp\Matcher;

final class YearMatch extends BaseMatch
{
    public const NUM_YEARS = 119;

    public $pattern = 'regex';
    public $regexName = 'recent_year';

    /**
     * Match occurrences of years in a password
     *
     * @param string $password
     * @param array $userInputs
     * @return YearMatch[]
     */
    public static function match(string $password, array $userInputs = []): array
    {
        $matches = [];
        $groups = static::findAll($password, "/(19\d\d|20\d\d)/u");
        foreach ($groups as $captures) {
            $matches[] = new static($password, $captures[1]['begin'], $captures[1]['end'], $captures[1]['token']);
        }
        Matcher::usortStable($matches, [Matcher::class, 'compareMatches']);
        return $matches;
    }


    /**
     * @return array{'warning': string, "suggestions": string[]}
     */
    public function getFeedback(bool $isSoleMatch): array
    {
        return [
            'warning' => "Recent years are easy to guess",
            'suggestions' => [
                'Avoid recent years',
                'Avoid years that are associated with you',
            ]
        ];
    }

    protected function getRawGuesses(): float
    {
        $yearSpace = abs($this->token - DateMatch::getReferenceYear());
        return max($yearSpace, DateMatch::MIN_YEAR_SPACE);
    }
}
