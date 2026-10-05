<?php

declare(strict_types=1);

namespace Cose\Algorithm\Signature\ECDSA;

use function bin2hex;
use function hex2bin;
use function hexdec;
use InvalidArgumentException;
use function is_string;
use function sprintf;
use function str_pad;
use const STR_PAD_LEFT;
use function strlen;
use function substr;

/**
 * Converts between the COSE signature format (`I2OSP(R, n) | I2OSP(S, n)`, RFC 9053 §2.1) and the DER structure
 * `Ecdsa-Sig-Value ::= SEQUENCE { r INTEGER, s INTEGER }` (RFC 3279 §2.2.3) that OpenSSL consumes.
 *
 * The encoder emits DER as required by X.690 §10.1 and the decoder only accepts DER: every encoding OpenSSL
 * refuses (indefinite or non-minimal lengths, negative or non-minimal integers, trailing data) is rejected here.
 *
 * @internal
 *
 * @see \Cose\Tests\Algorithm\Signature\ECDSA\ECSignatureTest
 */
final class ECSignature
{
    private const ASN1_SEQUENCE = '30';

    private const ASN1_INTEGER = '02';

    private const ASN1_LENGTH_2BYTES = '81';

    private const ASN1_BIG_INTEGER_LIMIT = '7f';

    private const ASN1_NEGATIVE_INTEGER = '00';

    private const BYTE_SIZE = 2;

    public static function toAsn1(string $signature, int $length): string
    {
        $signature = bin2hex($signature);

        if (self::octetLength($signature) !== $length) {
            throw new InvalidArgumentException('Invalid signature length.');
        }

        $pointR = self::preparePositiveInteger(substr($signature, 0, $length));
        $pointS = self::preparePositiveInteger(substr($signature, $length));
        if ($pointR === '' || $pointS === '') {
            // R = 0 or S = 0 is never a valid ECDSA signature (SEC 1 v2 §4.1.4 step 1) and would be encoded
            // as an INTEGER without content octets, which X.690 §8.3.1 forbids.
            throw new InvalidArgumentException('Invalid signature. R and S must be positive integers.');
        }

        $lengthR = self::octetLength($pointR);
        $lengthS = self::octetLength($pointS);

        $totalLength = $lengthR + $lengthS + self::BYTE_SIZE + self::BYTE_SIZE;

        $der = hex2bin(
            self::ASN1_SEQUENCE . self::encodeAsn1Length($totalLength)
            . self::ASN1_INTEGER . self::encodeAsn1Length($lengthR) . $pointR
            . self::ASN1_INTEGER . self::encodeAsn1Length($lengthS) . $pointS
        );
        if (! is_string($der)) {
            throw new InvalidArgumentException('Unable to encode the signature.');
        }

        return $der;
    }

    public static function fromAsn1(string $signature, int $length): string
    {
        $message = bin2hex($signature);
        $position = 0;

        if (self::readAsn1Content($message, $position, self::BYTE_SIZE) !== self::ASN1_SEQUENCE) {
            throw new InvalidArgumentException('Invalid data. Should start with a sequence.');
        }

        $sequenceLength = self::readAsn1Length($message, $position);
        if ($sequenceLength * self::BYTE_SIZE !== strlen($message) - $position) {
            // X.690 §8.1.3: the length octets cover exactly the contents octets; no trailing data is allowed.
            throw new InvalidArgumentException('Invalid data. Sequence length mismatch.');
        }

        $pointR = self::readAsn1Integer($message, $position, $length);
        $pointS = self::readAsn1Integer($message, $position, $length);

        $raw = hex2bin(str_pad($pointR, $length, '0', STR_PAD_LEFT) . str_pad($pointS, $length, '0', STR_PAD_LEFT));
        if (! is_string($raw)) {
            throw new InvalidArgumentException('Unable to decode the signature.');
        }

        return $raw;
    }

    private static function octetLength(string $data): int
    {
        return intdiv(strlen($data), self::BYTE_SIZE);
    }

    /**
     * X.690 §8.1.3.4, §8.1.3.5 and §10.1: short form below 128 octets, minimal long form above. The largest
     * `Ecdsa-Sig-Value` contents on the supported curves is 138 octets (P-521), so the `82` form is never needed.
     */
    private static function encodeAsn1Length(int $length): string
    {
        if ($length < 0x80) {
            return sprintf('%02x', $length);
        }
        if ($length <= 0xFF) {
            return self::ASN1_LENGTH_2BYTES . sprintf('%02x', $length);
        }

        throw new InvalidArgumentException('Invalid signature. The length is too large.');
    }

    private static function preparePositiveInteger(string $data): string
    {
        if (substr($data, 0, self::BYTE_SIZE) > self::ASN1_BIG_INTEGER_LIMIT) {
            return self::ASN1_NEGATIVE_INTEGER . $data;
        }

        while (
            str_starts_with($data, self::ASN1_NEGATIVE_INTEGER)
            && substr($data, 2, self::BYTE_SIZE) <= self::ASN1_BIG_INTEGER_LIMIT
        ) {
            $data = substr($data, 2);
        }

        return $data;
    }

    private static function readAsn1Content(string $message, int &$position, int $length): string
    {
        $content = substr($message, $position, $length);
        $position += $length;

        return $content;
    }

    private static function readAsn1Length(string $message, int &$position): int
    {
        $firstOctet = self::readAsn1Content($message, $position, self::BYTE_SIZE);
        if (strlen($firstOctet) !== self::BYTE_SIZE) {
            throw new InvalidArgumentException('Invalid data. Truncated length.');
        }

        $first = (int) hexdec($firstOctet);
        if ($first < 0x80) {
            // Short form (X.690 §8.1.3.4).
            return $first;
        }
        if ($first === 0x81) {
            $secondOctet = self::readAsn1Content($message, $position, self::BYTE_SIZE);
            if (strlen($secondOctet) !== self::BYTE_SIZE) {
                throw new InvalidArgumentException('Invalid data. Truncated length.');
            }
            $value = (int) hexdec($secondOctet);
            if ($value < 0x80) {
                // X.690 §10.1: the length is encoded in the minimum number of octets.
                throw new InvalidArgumentException('Invalid data. Non-minimal length encoding.');
            }

            return $value;
        }

        // 0x80 is the indefinite form, forbidden in DER (X.690 §8.1.3.6 and §10.1); 0x82 and above are never
        // needed for an Ecdsa-Sig-Value on the supported curves.
        throw new InvalidArgumentException('Invalid data. Unsupported length encoding.');
    }

    private static function readAsn1Integer(string $message, int &$position, int $length): string
    {
        if (self::readAsn1Content($message, $position, self::BYTE_SIZE) !== self::ASN1_INTEGER) {
            throw new InvalidArgumentException('Invalid data. Should contain an integer.');
        }

        $contentLength = self::readAsn1Length($message, $position);
        if ($contentLength === 0) {
            // X.690 §8.3.1: the contents octets consist of one or more octets.
            throw new InvalidArgumentException('Invalid data. Empty integer.');
        }

        $content = self::readAsn1Content($message, $position, $contentLength * self::BYTE_SIZE);
        if (strlen($content) !== $contentLength * self::BYTE_SIZE) {
            throw new InvalidArgumentException('Invalid data. Truncated integer.');
        }

        $first = (int) hexdec(substr($content, 0, self::BYTE_SIZE));
        if ($first >= 0x80) {
            // X.690 §8.3.3: the contents octets are a two's complement number, so this integer is negative.
            throw new InvalidArgumentException('Invalid data. Negative integer.');
        }
        if ($first === 0) {
            if ($contentLength === 1) {
                // SEC 1 v2 §4.1.4 step 1: R and S must lie in [1, n-1].
                throw new InvalidArgumentException('Invalid signature. R and S must be positive integers.');
            }
            if ((int) hexdec(substr($content, self::BYTE_SIZE, self::BYTE_SIZE)) < 0x80) {
                // X.690 §8.3.2 b): the first nine bits must not all be zero.
                throw new InvalidArgumentException('Invalid data. Non-minimal integer.');
            }
            // Drop the single sign octet the encoding is allowed to carry.
            $content = substr($content, self::BYTE_SIZE);
        }
        if (strlen($content) > $length) {
            throw new InvalidArgumentException('Invalid data. The integer is too large for the curve.');
        }

        return $content;
    }
}
