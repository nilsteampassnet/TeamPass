<?php

declare(strict_types=1);

namespace Cose\Structure;

use CBOR\ByteStringObject;
use CBOR\CBORObject;
use CBOR\ListObject;
use CBOR\TextStringObject;
use Stringable;

/**
 * The shape every RFC 9052 cryptographic structure has: a context string that names what is being computed,
 * followed by the fields that structure commits to.
 *
 * These are the byte strings that are signed, MACed or fed as additional authenticated data -- never the payload on
 * its own. Casting an instance to string yields the CBOR encoding to hand to the algorithm.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9052#section-4.4
 * @see https://www.rfc-editor.org/rfc/rfc9052#section-5.3
 * @see https://www.rfc-editor.org/rfc/rfc9052#section-6.3
 */
abstract class CoseStructure implements Stringable
{
    /**
     * RFC 9052 section 4.4 on external_aad: "If this field is not supplied, it defaults to a zero-length byte
     * string."
     */
    public static function emptyExternalAad(): ByteStringObject
    {
        return ByteStringObject::create('');
    }

    public function __toString(): string
    {
        return (string) ListObject::create([TextStringObject::create($this->context()), ...$this->items()]);
    }

    /**
     * The context string of this structure, as RFC 9052 spells it.
     */
    abstract protected function context(): string;

    /**
     * The fields following the context, in the order the CDDL gives them.
     *
     * @return list<CBORObject>
     */
    abstract protected function items(): array;
}
