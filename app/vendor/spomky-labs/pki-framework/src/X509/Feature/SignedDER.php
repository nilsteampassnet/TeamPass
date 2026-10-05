<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X509\Feature;

use function count;
use function mb_strlen;
use SpomkyLabs\Pki\ASN1\Element;
use SpomkyLabs\Pki\ASN1\Exception\DecodeException;
use SpomkyLabs\Pki\ASN1\Type\Constructed\Sequence;
use SpomkyLabs\Pki\ASN1\Type\Structure;
use function sprintf;

/**
 * Helper trait for the signed structures of X.509 — a sequence whose first element is the part the signature
 * covers, followed by the signature algorithm and the signature value.
 *
 * Verifying a signature over a re-encoding of the parsed first element checks what the parser produced rather
 * than what the signer signed. Every encoding the parser normalises away then yields another byte string that
 * verifies just as well, so a single signed object can be presented under any number of digests. Decoding
 * therefore keeps the first element exactly as it arrived, and refuses input the signer cannot have produced.
 */
trait SignedDER
{
    /**
     * Decode a signed structure, returning the parsed sequence along with the untouched encoding of the element
     * the signature covers.
     *
     * @param string $data DER encoding of the whole structure
     * @param string $name Name of the type, used in error messages
     *
     * @return array{Sequence, string}
     */
    private static function decodeSignedDER(string $data, string $name): array
    {
        $offset = 0;
        $seq = Element::fromDER($data, $offset)->asUnspecified()
            ->asSequence();
        // Trailing bytes never reach the caller yet travel with the encoding, and they let one signed object be
        // presented under any number of distinct byte strings.
        $consumed = $offset ?? 0;
        $length = mb_strlen($data, '8bit');
        if ($consumed !== $length) {
            throw new DecodeException(
                sprintf('%s encoding is %d bytes but %d bytes were given.', $name, $consumed, $length)
            );
        }
        try {
            $parts = Structure::explodeDER($data);
        } catch (DecodeException $e) {
            // An indefinite length encoding is BER, not DER; the signed bytes cannot be isolated and re-encoding
            // them would bring the malleability back.
            throw new DecodeException(sprintf('%s must be DER encoded.', $name), 0, $e);
        }
        if (count($parts) === 0) {
            throw new DecodeException(sprintf('%s must not be empty.', $name));
        }
        return [$seq, $parts[0]];
    }
}
