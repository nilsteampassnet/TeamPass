<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\ASN1\Type\Primitive;

use SpomkyLabs\Pki\ASN1\Type\PrimitiveString;
use SpomkyLabs\Pki\ASN1\Type\UniversalClass;

/**
 * Implements *PrintableString* type.
 */
final class PrintableString extends PrimitiveString
{
    use UniversalClass;

    private function __construct(string $string)
    {
        parent::__construct(self::TYPE_PRINTABLE_STRING, $string);
    }

    public static function create(string $string): self
    {
        return new self($string);
    }

    protected function validateString(string $string): bool
    {
        // X.680 sect. 41, table 10: the PrintableString character set has no ']'
        $chars = preg_quote(" '()+,-./:=?", '/');
        return preg_match('/[^A-Za-z0-9' . $chars . ']/', $string) !== 1;
    }
}
