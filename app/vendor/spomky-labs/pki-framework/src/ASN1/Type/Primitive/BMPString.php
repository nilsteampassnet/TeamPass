<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\ASN1\Type\Primitive;

use function mb_strlen;
use SpomkyLabs\Pki\ASN1\Type\PrimitiveString;
use SpomkyLabs\Pki\ASN1\Type\UniversalClass;
use function unpack;

/**
 * Implements *BMPString* type.
 *
 * BMP stands for Basic Multilingual Plane. This is generally an Unicode string with UCS-2 encoding.
 */
final class BMPString extends PrimitiveString
{
    use UniversalClass;

    private function __construct(string $string)
    {
        parent::__construct(self::TYPE_BMP_STRING, $string);
    }

    public static function create(string $string): self
    {
        return new self($string);
    }

    protected function validateString(string $string): bool
    {
        // UCS-2 has fixed with of 2 octets (16 bits)
        if (mb_strlen($string, '8bit') % 2 !== 0) {
            return false;
        }
        // UCS-2 has no surrogate pairs, so a surrogate code unit denotes no character. Transcoding one to UTF-8
        // substitutes a replacement character, which makes values that are not the same compare as though they
        // were, so the octets must not reach the comparison at all.
        $units = unpack('n*', $string);
        if ($units === false) {
            return false;
        }
        foreach ($units as $unit) {
            if ($unit >= 0xD800 && $unit <= 0xDFFF) {
                return false;
            }
        }

        return true;
    }
}
