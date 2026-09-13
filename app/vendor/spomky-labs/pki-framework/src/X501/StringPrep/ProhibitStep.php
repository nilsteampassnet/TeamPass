<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X501\StringPrep;

use function preg_match;
use SpomkyLabs\Pki\X501\StringPrep\Exception\StringPreparationException;

/**
 * Implements 'Prohibit' step of the Internationalized String Preparation as specified by RFC 4518.
 *
 * @see https://tools.ietf.org/html/rfc4518#section-2.4
 */
final class ProhibitStep implements PrepareStep
{
    /**
     * Code points a prepared string may not contain.
     *
     * RFC 4518 section 2.4 forbids the code points of RFC 3454 tables C.3 to C.9. The ones that matter to a
     * comparison are those with no character behind them: a non-character or a private use code point cannot be
     * normalised or case folded, and what a converter does with them instead is what turns two different values
     * into one prepared string. The replacement character is refused for the same reason, since it is what a lossy
     * conversion leaves behind. Surrogates cannot appear here at all, because the string types that could carry
     * them refuse them when they are decoded.
     *
     * @var string
     */
    private const PROHIBITED = '/[\x{E000}-\x{F8FF}\x{FDD0}-\x{FDEF}\x{FFFD}-\x{FFFF}'
        . '\x{F0000}-\x{FFFFD}\x{100000}-\x{10FFFD}]/u';

    /**
     * @param string $string UTF-8 encoded string
     *
     * @throws StringPreparationException If the string carries a code point that cannot be compared.
     */
    public function apply(string $string): string
    {
        $found = preg_match(self::PROHIBITED, $string);
        if ($found === false) {
            throw new StringPreparationException('Failed to scan a string that is not valid UTF-8.');
        }
        if ($found === 1) {
            throw new StringPreparationException('String contains a prohibited character.');
        }

        return $string;
    }
}
