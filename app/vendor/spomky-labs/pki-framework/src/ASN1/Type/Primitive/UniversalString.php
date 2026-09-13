<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\ASN1\Type\Primitive;

use function mb_strlen;
use SpomkyLabs\Pki\ASN1\Type\PrimitiveString;
use SpomkyLabs\Pki\ASN1\Type\UniversalClass;
use function unpack;

/**
 * Implements *UniversalString* type.
 *
 * Universal string is an Unicode string with UCS-4 encoding.
 */
final class UniversalString extends PrimitiveString
{
    use UniversalClass;

    private function __construct(string $string)
    {
        parent::__construct(self::TYPE_UNIVERSAL_STRING, $string);
    }

    public static function create(string $string): self
    {
        return new self($string);
    }

    protected function validateString(string $string): bool
    {
        // UCS-4 has fixed with of 4 octets (32 bits)
        if (mb_strlen($string, '8bit') % 4 !== 0) {
            return false;
        }
        // A unit outside the Unicode range, or a surrogate, denotes no character. Transcoding one to UTF-8
        // substitutes a replacement character, which makes values that are not the same compare as though they
        // were, so the octets must not reach the comparison at all.
        $units = unpack('N*', $string);
        if ($units === false) {
            return false;
        }
        foreach ($units as $unit) {
            if ($unit > 0x10FFFF || ($unit >= 0xD800 && $unit <= 0xDFFF)) {
                return false;
            }
        }

        return true;
    }
}
