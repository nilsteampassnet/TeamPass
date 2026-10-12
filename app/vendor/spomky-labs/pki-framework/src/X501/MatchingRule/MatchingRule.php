<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X501\MatchingRule;

/**
 * Base class for attribute matching rules.
 *
 * @see https://tools.ietf.org/html/rfc4517#section-4
 */
abstract class MatchingRule
{
    /**
     * Compare attribute value to assertion.
     *
     * @param string $assertion Value to assert
     * @param string $value Attribute value
     *
     * @return null|bool True if value matches. Null shall be returned if match
     * evaluates to Undefined.
     */
    abstract public function compare(string $assertion, string $value): ?bool;

    /**
     * Get a key that stands for the value under this rule, or null when the rule cannot produce one.
     *
     * Two values compare equal under a rule exactly when their keys are identical, so a caller matching many values
     * against many others can group them instead of comparing every pair. A rule that cannot express itself that
     * way returns null, and the caller falls back to comparing pairs.
     *
     * @param string $value Attribute value
     */
    public function comparisonKey(string $value): ?string
    {
        return null;
    }
}
