<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\CryptoTypes\Signature;

use function count;
use function mb_strlen;
use SpomkyLabs\Pki\ASN1\Element;
use SpomkyLabs\Pki\ASN1\Exception\DecodeException;
use SpomkyLabs\Pki\ASN1\Type\Constructed\Sequence;
use SpomkyLabs\Pki\ASN1\Type\Primitive\BitString;
use SpomkyLabs\Pki\ASN1\Type\Primitive\Integer;

/**
 * Implements ECDSA signature value.
 *
 * ECDSA signature is represented as a `ECDSA-Sig-Value` ASN.1 type.
 *
 * @see https://tools.ietf.org/html/rfc3278#section-8.2
 */
final class ECSignature extends Signature
{
    private function __construct(
        private readonly string $r,
        private readonly string $s
    ) {
    }

    public static function create(string $r, string $s): self
    {
        return new self($r, $s);
    }

    /**
     * Initialize from ASN.1.
     */
    public static function fromASN1(Sequence $seq): self
    {
        $r = $seq->at(0)
            ->asInteger()
            ->number();
        $s = $seq->at(1)
            ->asInteger()
            ->number();
        return self::create($r, $s);
    }

    /**
     * Initialize from DER.
     *
     * The signature value is not covered by the signature it carries, so whatever this decoder accepts and then
     * normalises away yields another byte string that verifies just as well. bitString() re-encodes r and s
     * canonically, and that re-encoding is what reaches the crypto engine, so trailing bytes, a BER length, a
     * non-minimal INTEGER and any element past s were all erased before OpenSSL ever saw them. One certificate then
     * had an unlimited supply of accepted encodings, which defeats pinning, deny-listing and deduplicating by the
     * digest of what was received.
     *
     * The encoding must therefore be the one canonical DER of the value it denotes.
     *
     * @throws DecodeException If the encoding is not DER.
     */
    public static function fromDER(string $data): self
    {
        $offset = 0;
        $seq = Element::fromDER($data, $offset)->asUnspecified()
            ->asSequence();
        if ($offset !== mb_strlen($data, '8bit')) {
            throw new DecodeException('ECDSA-Sig-Value must not carry trailing data.');
        }
        if (count($seq) !== 2) {
            throw new DecodeException('ECDSA-Sig-Value must consist of exactly two integers.');
        }
        $signature = self::fromASN1($seq);
        // catches a BER length, a long form length and a non-minimal INTEGER in one comparison
        if ($signature->toDER() !== $data) {
            throw new DecodeException('ECDSA-Sig-Value must be DER encoded.');
        }

        return $signature;
    }

    /**
     * Get the r-value.
     *
     * @return string Base 10 integer string
     */
    public function r(): string
    {
        return $this->r;
    }

    /**
     * Get the s-value.
     *
     * @return string Base 10 integer string
     */
    public function s(): string
    {
        return $this->s;
    }

    /**
     * Generate ASN.1 structure.
     */
    public function toASN1(): Sequence
    {
        return Sequence::create(Integer::create($this->r), Integer::create($this->s));
    }

    /**
     * Get DER encoding of the signature.
     */
    public function toDER(): string
    {
        return $this->toASN1()
            ->toDER();
    }

    public function bitString(): BitString
    {
        return BitString::create($this->toDER());
    }
}
