<?php

declare(strict_types=1);

namespace ZxcvbnPhp\Matchers;

use JetBrains\PhpStorm\ArrayShape;
use ZxcvbnPhp\Matcher;

class DateMatch extends BaseMatch
{
    public const NUM_YEARS = 119; // Years match against 1900 - 2019
    public const NUM_MONTHS = 12;
    public const NUM_DAYS = 31;

    public const MIN_YEAR = 1000;
    public const MAX_YEAR = 2050;

    public const MIN_YEAR_SPACE = 20;

    public $pattern = 'date';

    private static $DATE_SPLITS = [
        4 => [         # For length-4 strings, eg 1191 or 9111, two ways to split:
            [1, 2],    # 1 1 91 (2nd split starts at index 1, 3rd at index 2)
            [2, 3],    # 91 1 1
        ],
        5 => [
            [1, 3],    # 1 11 91
            [2, 3]     # 11 1 91
        ],
        6 => [
            [1, 2],    # 1 1 1991
            [2, 4],    # 11 11 91
            [4, 5],    # 1991 1 1
        ],
        7 => [
            [1, 3],    # 1 11 1991
            [2, 3],    # 11 1 1991
            [4, 5],    # 1991 1 11
            [4, 6],    # 1991 11 1
        ],
        8 => [
            [2, 4],    # 11 11 1991
            [4, 6],    # 1991 11 11
        ],
    ];

    protected const DATE_NO_SEPARATOR = '/^\d{4,8}$/u';

    /**
     * (\d{1,4})        # day, month, year
     * ([\s\/\\\\_.-])  # separator
     * (\d{1,2})        # day, month
     * \2               # same separator
     * (\d{1,4})        # day, month, year
     */
    protected const DATE_WITH_SEPARATOR = '/^(\d{1,4})([\s\/\\\\_.-])(\d{1,2})\2(\d{1,4})$/u';

    /** @var int The day portion of the date in the token. */
    public $day;

    /** @var int The month portion of the date in the token. */
    public $month;

    /** @var int The year portion of the date in the token. */
    public $year;

    /** @var string The separator used for the date in the token. */
    public $separator;

    /**
     * Match occurences of dates in a password
     *
     * @param string $password
     * @param array $userInputs
     * @return DateMatch[]
     */
    public static function match(string $password, array $userInputs = []): array
    {
        # a "date" is recognized as:
        #   any 3-tuple that starts or ends with a 2- or 4-digit year,
        #   with 2 or 0 separator chars (1.1.91 or 1191),
        #   maybe zero-padded (01-01-91 vs 1-1-91),
        #   a month between 1 and 12,
        #   a day between 1 and 31.
        #
        # note: this isn't true date parsing in that "feb 31st" is allowed,
        # this doesn't check for leap years, etc.
        #
        # recipe:
        # start with regex to find maybe-dates, then attempt to map the integers
        # onto month-day-year to filter the maybe-dates into dates.
        # finally, remove matches that are substrings of other matches to reduce noise.
        #
        # note: instead of using a lazy or greedy regex to find many dates over the full string,
        # this uses a ^...$ regex against every substring of the password -- less performant but leads
        # to every possible date match.
        $matches = [];
        $dates = static::removeRedundantMatches(array_merge(
            static::datesWithoutSeparators($password),
            static::datesWithSeparators($password)
        ));
        foreach ($dates as $date) {
            $matches[] = new static($password, $date['begin'], $date['end'], $date['token'], $date);
        }
        Matcher::usortStable($matches, [Matcher::class, 'compareMatches']);
        return $matches;
    }

    #[ArrayShape(['warning' => 'string', 'suggestions' => 'string[]'])]
    public function getFeedback(bool $isSoleMatch): array
    {
        return [
            'warning' => "Dates are often easy to guess",
            'suggestions' => [
                'Avoid dates and years that are associated with you'
            ]
        ];
    }

    /**
     * @param string $password
     * @param int $begin
     * @param int $end
     * @param string $token
     * @param array $params An array with keys: [day, month, year, separator].
     */
    public function __construct(string $password, int $begin, int $end, string $token, array $params)
    {
        parent::__construct($password, $begin, $end, $token);
        $this->day = $params['day'];
        $this->month = $params['month'];
        $this->year = $params['year'];
        $this->separator = $params['separator'];
    }

    /**
     * Find dates with separators in a password.
     *
     * @param string $password
     *
     * @return array
     */
    protected static function datesWithSeparators(string $password): array
    {
        $matches = [];
        $length = mb_strlen($password);

        // dates with separators are between length 6 '1/1/91' and 10 '11/11/1991'
        for ($begin = 0; $begin < $length - 5; $begin++) {
            for ($end = $begin + 5; $end - $begin < 10 && $end < $length; $end++) {
                $token = mb_substr($password, $begin, $end - $begin + 1);

                if (!preg_match(static::DATE_WITH_SEPARATOR, $token, $captures)) {
                    continue;
                }

                $date = static::checkDate([
                    (int) $captures[1],
                    (int) $captures[3],
                    (int) $captures[4]
                ]);

                if ($date === false) {
                    continue;
                }

                $matches[] = [
                    'begin' => $begin,
                    'end' => $end,
                    'token' => $token,
                    'separator' => $captures[2],
                    'day' => $date['day'],
                    'month' => $date['month'],
                    'year' => $date['year'],
                ];
            }
        }

        return $matches;
    }

    /**
     * Find dates without separators in a password.
     *
     * @param string $password
     *
     * @return array
     */
    protected static function datesWithoutSeparators(string $password): array
    {
        $matches = [];
        $length = mb_strlen($password);

        // dates without separators are between length 4 '1191' and 8 '11111991'
        for ($begin = 0; $begin < $length - 3; $begin++) {
            for ($end = $begin + 3; $end - $begin < 8 && $end < $length; $end++) {
                $token = mb_substr($password, $begin, $end - $begin + 1);

                if (!preg_match(static::DATE_NO_SEPARATOR, $token)) {
                    continue;
                }

                $candidates = [];

                $possibleSplits = static::$DATE_SPLITS[mb_strlen($token)];
                foreach ($possibleSplits as $splitPositions) {
                    $day = (int)mb_substr($token, 0, $splitPositions[0]);
                    $month = (int)mb_substr($token, $splitPositions[0], $splitPositions[1] - $splitPositions[0]);
                    $year = (int)mb_substr($token, $splitPositions[1]);

                    $date = static::checkDate([$day, $month, $year]);
                    if ($date !== false) {
                        $candidates[] = $date;
                    }
                }

                if (empty($candidates)) {
                    continue;
                }

                // at this point: different possible dmy mappings for the same i,j substring.
                // match the candidate date that likely takes the fewest guesses: a year closest to
                // the current year.
                //
                // ie, considering '111504', prefer 11-15-04 to 1-1-1504
                // (interpreting '04' as 2004)
                $bestCandidate = $candidates[0];
                $minDistance = self::getDistanceForMatchCandidate($bestCandidate);

                foreach ($candidates as $candidate) {
                    $distance = self::getDistanceForMatchCandidate($candidate);
                    if ($distance < $minDistance) {
                        $bestCandidate = $candidate;
                        $minDistance = $distance;
                    }
                }

                $day = $bestCandidate['day'];
                $month = $bestCandidate['month'];
                $year = $bestCandidate['year'];

                $matches[] = [
                    'begin' => $begin,
                    'end' => $end,
                    'token' => $token,
                    'separator' => '',
                    'day' => $day,
                    'month' => $month,
                    'year' => $year
                ];
            }
        }

        return $matches;
    }

    /**
     * @param array $candidate
     * @return int Returns the number of years between the detected year and the current year for a candidate.
     */
    protected static function getDistanceForMatchCandidate(array $candidate): int
    {
        return abs((int)$candidate['year'] - static::getReferenceYear());
    }

    public static function getReferenceYear(): int
    {
        return (int)date('Y');
    }

    /**
     * @param int[] $ints Three numbers in an array representing day, month and year (not necessarily in that order).
     * @return array|bool Returns an associative array containing 'day', 'month' and 'year' keys, or false if the
     *                    provided date array is invalid.
     */
    protected static function checkDate(array $ints)
    {
        # given a 3-tuple, discard if:
        #   middle int is over 31 (for all dmy formats, years are never allowed in the middle)
        #   middle int is zero
        #   any int is over the max allowable year
        #   any int is over two digits but under the min allowable year
        #   2 ints are over 31, the max allowable day
        #   2 ints are zero
        #   all ints are over 12, the max allowable month
        if ($ints[1] > 31 || $ints[1] <= 0) {
            return false;
        }

        $invalidYear = count(array_filter($ints, function (int $int): bool {
            return ($int >= 100 && $int < static::MIN_YEAR)
                || ($int > static::MAX_YEAR);
        }));
        if ($invalidYear > 0) {
            return false;
        }

        $over12 = count(array_filter($ints, function (int $int): bool {
            return $int > 12;
        }));
        $over31 = count(array_filter($ints, function (int $int): bool {
            return $int > 31;
        }));
        $under1 = count(array_filter($ints, function (int $int): bool {
            return $int <= 0;
        }));

        if ($over31 >= 2 || $over12 == 3 || $under1 >= 2) {
            return false;
        }

        # first look for a four digit year: yyyy + daymonth or daymonth + yyyy
        $possibleYearSplits = [
            [$ints[2], [$ints[0], $ints[1]]], // year last
            [$ints[0], [$ints[1], $ints[2]]], // year first
        ];

        foreach ($possibleYearSplits as [$year, $rest]) {
            if ($year >= static::MIN_YEAR && $year <= static::MAX_YEAR) {
                if ($dm = static::mapIntsToDayMonth($rest)) {
                    return [
                        'year'  => $year,
                        'month' => $dm['month'],
                        'day'   => $dm['day'],
                    ];
                }
                # for a candidate that includes a four-digit year,
                # when the remaining ints don't match to a day and month,
                # it is not a date.
                return false;
            }
        }

        foreach ($possibleYearSplits as [$year, $rest]) {
            if ($dm = static::mapIntsToDayMonth($rest)) {
                return [
                    'year'  => static::twoToFourDigitYear($year),
                    'month' => $dm['month'],
                    'day'   => $dm['day'],
                ];
            }
        }

        return false;
    }

    /**
     * @param int[] $ints Two numbers in an array representing day and month (not necessarily in that order).
     * @return array|bool Returns an associative array containing 'day' and 'month' keys, or false if any combination
     *                    of the two numbers does not match a day and month.
     */
    protected static function mapIntsToDayMonth(array $ints)
    {
        foreach ([$ints, array_reverse($ints)] as [$d, $m]) {
            if ($d >= 1 && $d <= 31 && $m >= 1 && $m <= 12) {
                return [
                    'day'   => $d,
                    'month' => $m
                ];
            }
        }

        return false;
    }

    /**
     * @param int $year A two digit number representing a year.
     * @return int Returns the most likely four digit year for the provided number.
     */
    protected static function twoToFourDigitYear(int $year): int
    {
        if ($year > 99) {
            return $year;
        }

        if ($year > 50) {
            // 87 -> 1987
            return $year + 1900;
        }

        // 15 -> 2015
        return $year + 2000;
    }

    /**
     * Removes date matches that are strict substrings of others.
     *
     * This is helpful because the match function will contain matches for all valid date strings in a way that is
     * tricky to capture with regexes only. While thorough, it will contain some unintuitive noise:
     *
     *   '2015_06_04', in addition to matching 2015_06_04, will also contain
     *   5(!) other date matches: 15_06_04, 5_06_04, ..., even 2015 (matched as 5/1/2020)
     *
     * @param array $matches An array of matches (not Match objects)
     * @return array The provided array of matches, but with matches that are strict substrings of others removed.
     */
    protected static function removeRedundantMatches(array $matches): array
    {
        return array_filter($matches, function (array $match) use ($matches): bool {
            foreach ($matches as $otherMatch) {
                if ($match === $otherMatch) {
                    continue;
                }
                if ($otherMatch['begin'] <= $match['begin'] && $otherMatch['end'] >= $match['end']) {
                    return false;
                }
            }

            return true;
        });
    }

    protected function getRawGuesses(): float
    {
        // base guesses: (year distance from REFERENCE_YEAR) * num_days * num_years
        $yearSpace = max(abs($this->year - static::getReferenceYear()), static::MIN_YEAR_SPACE);
        $guesses = $yearSpace * 365;

        // add factor of 4 for separator selection (one of ~4 choices)
        if ($this->separator) {
            $guesses *= 4;
        }

        return $guesses;
    }
}
