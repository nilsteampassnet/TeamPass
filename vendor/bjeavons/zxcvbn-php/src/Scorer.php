<?php

declare(strict_types=1);

namespace ZxcvbnPhp;

use ZxcvbnPhp\Matchers\Bruteforce;
use ZxcvbnPhp\Matchers\BaseMatch;
use ZxcvbnPhp\Matchers\MatchInterface;

/**
 * scorer - takes a list of potential matches, ranks and evaluates them,
 * and figures out how many guesses it would take to crack the password
 *
 * @see zxcvbn/src/scoring.coffee
 */
class Scorer
{
    public const MIN_GUESSES_BEFORE_GROWING_SEQUENCE = 10000;
    public const MIN_SUBMATCH_GUESSES_SINGLE_CHAR = 10;
    public const MIN_SUBMATCH_GUESSES_MULTI_CHAR = 50;

    protected $password;
    protected $excludeAdditive;
    protected $optimal = [];

    /**
     * ------------------------------------------------------------------------------
     * search --- most guessable match sequence -------------------------------------
     * ------------------------------------------------------------------------------
     *
     * takes a sequence of overlapping matches, returns the non-overlapping sequence with
     * minimum guesses. the following is a O(l_max * (n + m)) dynamic programming algorithm
     * for a length-n password with m candidate matches. l_max is the maximum optimal
     * sequence length spanning each prefix of the password. In practice it rarely exceeds 5 and the
     * search terminates rapidly.
     *
     * the optimal "minimum guesses" sequence is here defined to be the sequence that
     * minimizes the following function:
     *
     *    g = l! * Product(m.guesses for m in sequence) + D^(l - 1)
     *
     * where l is the length of the sequence.
     *
     * the factorial term is the number of ways to order l patterns.
     *
     * the D^(l-1) term is another length penalty, roughly capturing the idea that an
     * attacker will try lower-length sequences first before trying length-l sequences.
     *
     * for example, consider a sequence that is date-repeat-dictionary.
     *  - an attacker would need to try other date-repeat-dictionary combinations,
     *    hence the product term.
     *  - an attacker would need to try repeat-date-dictionary, dictionary-repeat-date,
     *    ..., hence the factorial term.
     *  - an attacker would also likely try length-1 (dictionary) and length-2 (dictionary-date)
     *    sequences before length-3. assuming at minimum D guesses per pattern type,
     *    D^(l-1) approximates Sum(D^i for i in [1..l-1]
     *
     * @param string $password
     * @param MatchInterface[] $matches
     * @param bool $excludeAdditive
     * @return array Returns an array with these keys: [password, guesses, guesses_log10, sequence]
     */
    public function getMostGuessableMatchSequence(string $password, array $matches, bool $excludeAdditive = false): array
    {
        $this->password = $password;
        $this->excludeAdditive = $excludeAdditive;

        $length = mb_strlen($password);
        $emptyArray = $length > 0 ? array_fill(0, $length, []) : [];

        // partition matches into sublists according to ending index j
        $matchesByEndIndex = $emptyArray;
        foreach ($matches as $match) {
            $matchesByEndIndex[$match->end][] = $match;
        }

        // small detail: for deterministic output, sort each sublist by i.
        foreach ($matchesByEndIndex as &$matches) {
            usort($matches, function ($a, $b) {
                /** @var $a BaseMatch */
                /** @var $b BaseMatch */
                return $a->begin - $b->begin;
            });
        }

        $this->optimal = [
            // optimal.m[k][l] holds final match in the best length-l match sequence covering the
            // password prefix up to k, inclusive.
            // if there is no length-l sequence that scores better (fewer guesses) than
            // a shorter match sequence spanning the same prefix, optimal.m[k][l] is undefined.
            'm' => $emptyArray,

            // same structure as optimal.m -- holds the product term Prod(m.guesses for m in sequence).
            // optimal.pi allows for fast (non-looping) updates to the minimization function.
            'pi' => $emptyArray,

            // same structure as optimal.m -- holds the overall metric.
            'g' => $emptyArray,
        ];

        for ($k = 0; $k < $length; $k++) {
            /** @var BaseMatch $match */
            foreach ($matchesByEndIndex[$k] as $match) {
                if ($match->begin > 0) {
                    foreach ($this->optimal['m'][$match->begin - 1] as $l => $null) {
                        $l = (int)$l;
                        $this->update($match, $l + 1);
                    }
                } else {
                    $this->update($match, 1);
                }
            }
            $this->bruteforceUpdate($k);
        }


        if ($length === 0) {
            $guesses = 1.0;
            $optimalSequence = [];
        } else {
            $optimalSequence = $this->unwind($length);
            $optimalSequenceLength = count($optimalSequence);
            $guesses = $this->optimal['g'][$length - 1][$optimalSequenceLength];
        }

        return [
            'password' => $password,
            'guesses' => $guesses,
            'guesses_log10' => log10($guesses),
            'sequence' => $optimalSequence,
        ];
    }

    /**
     * helper: considers whether a length-l sequence ending at match m is better (fewer guesses)
     * than previously encountered sequences, updating state if so.
     * @param BaseMatch $match
     * @param int $length
     */
    protected function update(BaseMatch $match, int $length): void
    {
        $k = $match->end;

        // Upstream has a call to estimateGuesses for this line (which contains some extra logic), but due to our
        // object-oriented approach we can just call getGuesses on the match directly.
        $pi = $match->getGuesses();

        if ($length > 1) {
            // we're considering a length-l sequence ending with match m:
            // obtain the product term in the minimization function by multiplying m's guesses
            // by the product of the length-(l-1) sequence ending just before m, at m.i - 1.
            $pi *= $this->optimal['pi'][$match->begin - 1][$length - 1];
        }

        // calculate the minimization func
        $g = $this->factorial($length) * $pi;
        if (!$this->excludeAdditive) {
            $g += pow(self::MIN_GUESSES_BEFORE_GROWING_SEQUENCE, $length - 1);
        }

        // update state if new best.
        // first see if any competing sequences covering this prefix, with l or fewer matches,
        // fare better than this sequence. if so, skip it and return.
        foreach ($this->optimal['g'][$k] as $competingL => $competingG) {
            if ($competingL > $length) {
                continue;
            }
            if ($competingG <= $g) {
                return;
            }
        }

        $this->optimal['g'][$k][$length] = $g;
        $this->optimal['m'][$k][$length] = $match;
        $this->optimal['pi'][$k][$length] = $pi;

        // Sort the arrays by key after each insert to match how JavaScript objects work
        // Failing to do this results in slightly different matches in some scenarios
        ksort($this->optimal['g'][$k]);
        ksort($this->optimal['m'][$k]);
        ksort($this->optimal['pi'][$k]);
    }

    /**
     * helper: evaluate bruteforce matches ending at k
     * @param int $end
     */
    protected function bruteforceUpdate(int $end): void
    {
        // see if a single bruteforce match spanning the k-prefix is optimal.
        $match = $this->makeBruteforceMatch(0, $end);
        $this->update($match, 1);

        // generate k bruteforce matches, spanning from (i=1, j=k) up to (i=k, j=k).
        // see if adding these new matches to any of the sequences in optimal[i-1]
        // leads to new bests.
        for ($i = 1; $i <= $end; $i++) {
            $match = $this->makeBruteforceMatch($i, $end);
            foreach ($this->optimal['m'][$i - 1] as $l => $lastM) {
                $l = (int)$l;

                // corner: an optimal sequence will never have two adjacent bruteforce matches.
                // it is strictly better to have a single bruteforce match spanning the same region:
                // same contribution to the guess product with a lower length.
                // --> safe to skip those cases.
                if ($lastM->pattern === 'bruteforce') {
                    continue;
                }

                $this->update($match, $l + 1);
            }
        }
    }

    /**
     * helper: make bruteforce match objects spanning i to j, inclusive.
     * @param int $begin
     * @param int $end
     * @return Bruteforce
     */
    protected function makeBruteforceMatch(int $begin, int $end): Bruteforce
    {
        return new Bruteforce($this->password, $begin, $end, mb_substr($this->password, $begin, $end - $begin + 1));
    }

    /**
     * helper: step backwards through optimal.m starting at the end, constructing the final optimal match sequence.
     * @param int $n
     * @return MatchInterface[]
     */
    protected function unwind(int $n): array
    {
        $optimalSequence = [];
        $k = $n - 1;

        // find the final best sequence length and score
        $l = null;
        $g = INF;

        foreach ($this->optimal['g'][$k] as $candidateL => $candidateG) {
            if ($candidateG < $g) {
                $l = $candidateL;
                $g = $candidateG;
            }
        }

        while ($k >= 0) {
            $m = $this->optimal['m'][$k][$l];
            array_unshift($optimalSequence, $m);
            $k = $m->begin - 1;
            $l--;
        }

        return $optimalSequence;
    }

    /**
     * unoptimized, called only on small n
     * @param int $n
     * @return int
     */
    protected function factorial(int $n): int
    {
        if ($n < 2) {
            return 1;
        }
        $f = 1;
        for ($i = 2; $i <= $n; $i++) {
            $f *= $i;
        }
        return $f;
    }
}
