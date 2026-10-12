<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X501\MatchingRule;

/**
 * Implements binary matching rule.
 *
 * Generally used only by UnknownAttribute and custom attributes.
 */
final class BinaryMatch extends MatchingRule
{
    public function compare(string $assertion, string $value): bool
    {
        return strcmp($assertion, $value) === 0;
    }

    public function comparisonKey(string $value): string
    {
        // the rule is byte equality, so the value is its own key
        return $value;
    }
}
