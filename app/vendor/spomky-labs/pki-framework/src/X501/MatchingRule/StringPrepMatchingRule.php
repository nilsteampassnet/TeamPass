<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X501\MatchingRule;

use SpomkyLabs\Pki\X501\StringPrep\StringPreparer;

/**
 * Base class for matching rules employing string preparement semantics.
 */
abstract class StringPrepMatchingRule extends MatchingRule
{
    protected function __construct(
        private readonly StringPreparer $preparer
    ) {
    }

    public function compare(string $assertion, string $value): ?bool
    {
        return strcmp($this->prepare($assertion), $this->prepare($value)) === 0;
    }

    /**
     * Prepare a string under this rule's own syntax.
     *
     * Transcoding is a property of the value, not of the assertion, so two values of different ASN.1 string types
     * have to be prepared by their own rules and compared afterwards. Preparing both through one rule pushes one
     * value's octets through the other's transcoder, and the same name written as a BMPString and as a
     * PrintableString then never compares equal.
     *
     * @see https://tools.ietf.org/html/rfc4518#section-2.1
     */
    public function prepare(string $value): string
    {
        return $this->preparer->prepare($value);
    }

    public function comparisonKey(string $value): string
    {
        // compare() is an equality test on the prepared strings, so the prepared string is the key
        return $this->prepare($value);
    }
}
