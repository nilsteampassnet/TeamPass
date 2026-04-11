<?php

declare(strict_types=1);

namespace ZxcvbnPhp;

use ZxcvbnPhp\Matchers\MatchInterface;

/**
 * Feedback - gives some user guidance based on the strength
 * of a password
 *
 * @see zxcvbn/src/feedback.coffee
 */
class Feedback
{
    /**
     * @param int $score
     * @param MatchInterface[] $sequence
     * @return array
     */
    public function getFeedback(int $score, array $sequence): array
    {
        // starting feedback
        if (count($sequence) === 0) {
            return [
                'warning'     => '',
                'suggestions' => [
                    "Use a few words, avoid common phrases",
                    "No need for symbols, digits, or uppercase letters",
                ],
            ];
        }

        // no feedback if score is good or great.
        if ($score > 2) {
            return [
                'warning'     => '',
                'suggestions' => [],
            ];
        }

        // tie feedback to the longest match for longer sequences
        $longestMatch = $sequence[0];
        foreach (array_slice($sequence, 1) as $match) {
            if (mb_strlen($match->token) > mb_strlen($longestMatch->token)) {
                $longestMatch = $match;
            }
        }

        $feedback = $longestMatch->getFeedback(count($sequence) === 1);
        $extraFeedback = 'Add another word or two. Uncommon words are better.';

        array_unshift($feedback['suggestions'], $extraFeedback);
        return $feedback;
    }
}
