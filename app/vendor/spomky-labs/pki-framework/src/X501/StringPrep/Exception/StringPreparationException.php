<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X501\StringPrep\Exception;

use UnexpectedValueException;

/**
 * Exception thrown when a string cannot be prepared for comparison.
 *
 * A value carrying a code point that cannot be normalised, case folded or scanned has no prepared form, so no
 * comparison involving it can answer. The steps fail rather than return a value that would make two different names
 * compare as one, and the callers that compare names for a security decision turn this into their own failure: the
 * comparison is refused, never silently answered "not equal".
 *
 * It derives from `UnexpectedValueException`, which is what the steps raised before this type existed.
 */
final class StringPreparationException extends UnexpectedValueException
{
}
