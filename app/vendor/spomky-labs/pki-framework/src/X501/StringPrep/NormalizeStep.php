<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X501\StringPrep;

use Normalizer;
use SpomkyLabs\Pki\X501\StringPrep\Exception\StringPreparationException;

/**
 * Implements 'Normalize' step of the Internationalized String Preparation as specified by RFC 4518.
 *
 * @see https://tools.ietf.org/html/rfc4518#section-2.3
 */
final class NormalizeStep implements PrepareStep
{
    /**
     * @param string $string UTF-8 encoded string
     */
    public function apply(string $string): string
    {
        $normalized = normalizer_normalize($string, Normalizer::NFKC);
        if ($normalized === false) {
            // declared as returning a string, so a failure here would surface as a TypeError, which is an Error
            // and escapes every catch (Exception) a caller wrote around the comparison
            throw new StringPreparationException('Failed to normalize a string for comparison.');
        }

        return $normalized;
    }
}
