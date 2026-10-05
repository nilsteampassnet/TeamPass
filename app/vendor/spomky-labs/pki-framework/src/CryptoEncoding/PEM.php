<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\CryptoEncoding;

use function is_string;
use function mb_strlen;
use function preg_last_error_msg;
use RuntimeException;
use Stringable;
use UnexpectedValueException;

/**
 * Implements PEM file encoding and decoding.
 *
 * @see https://tools.ietf.org/html/rfc7468
 */
final class PEM implements Stringable
{
    // well-known PEM types
    public const TYPE_CERTIFICATE = 'CERTIFICATE';

    public const TYPE_CRL = 'X509 CRL';

    public const TYPE_CERTIFICATE_REQUEST = 'CERTIFICATE REQUEST';

    public const TYPE_ATTRIBUTE_CERTIFICATE = 'ATTRIBUTE CERTIFICATE';

    public const TYPE_PRIVATE_KEY = 'PRIVATE KEY';

    public const TYPE_PUBLIC_KEY = 'PUBLIC KEY';

    public const TYPE_ENCRYPTED_PRIVATE_KEY = 'ENCRYPTED PRIVATE KEY';

    public const TYPE_RSA_PRIVATE_KEY = 'RSA PRIVATE KEY';

    public const TYPE_RSA_PUBLIC_KEY = 'RSA PUBLIC KEY';

    public const TYPE_EC_PRIVATE_KEY = 'EC PRIVATE KEY';

    public const TYPE_PKCS7 = 'PKCS7';

    public const TYPE_CMS = 'CMS';

    /**
     * Regular expression to match PEM block.
     *
     * The label and the payload are both constrained to the characters RFC 7468 allows them. Written with two lazy
     * `.+?` under the `s` modifier and no anchoring, the engine tried every `-----` for the label and, for each one,
     * expanded the payload across the whole remainder: repeated BEGIN lines with no matching END drove cubic
     * backtracking, and a few kilobytes of them cost more than parsing thousands of real certificates.
     *
     * @var string
     */
    public const PEM_REGEX = '/' .
        /* line start */
        '(?:^|[\r\n])' .
        /* header, a label on a single line */
        '-----BEGIN ([A-Za-z0-9][A-Za-z0-9 ]*)-----[\r\n]+' .
        /* payload, base64 and the whitespace that breaks it into lines */
        '([A-Za-z0-9+\/=\r\n\t ]+?)' .
        /* trailer */
        '[\r\n]+-----END \\1-----' .
        '/m';

    /**
     * @param string $type Content type
     * @param string $data Payload
     */
    private function __construct(
        private readonly string $type,
        private readonly string $data
    ) {
    }

    public function __toString(): string
    {
        return $this->string();
    }

    public static function create(string $_type, string $_data): self
    {
        return new self($_type, $_data);
    }

    /**
     * Initialize from a PEM-formatted string.
     */
    public static function fromString(string $str): self
    {
        $result = preg_match(self::PEM_REGEX, $str, $match);
        if ($result === false) {
            // a matcher that gave up says nothing about the input, so it must not be reported as malformed
            throw new RuntimeException('Failed to match a PEM block: ' . preg_last_error_msg() . '.');
        }
        if ($result !== 1) {
            throw new UnexpectedValueException('Not a PEM formatted string.');
        }
        $payload = preg_replace('/\s+/', '', $match[2]);
        if (! is_string($payload)) {
            throw new UnexpectedValueException('Failed to decode PEM data.');
        }
        // base64_decode() is strict about the alphabet but not about padding, so a blob that OpenSSL refuses
        // would otherwise be accepted here (RFC 7468 sect. 3 requires the padded alphabet of RFC 4648)
        if (mb_strlen($payload, '8bit') % 4 !== 0) {
            throw new UnexpectedValueException('Failed to decode PEM data.');
        }
        $data = base64_decode($payload, true);
        if ($data === false) {
            throw new UnexpectedValueException('Failed to decode PEM data.');
        }
        return self::create($match[1], $data);
    }

    /**
     * Initialize from a file.
     *
     * @param string $filename Path to file
     */
    public static function fromFile(string $filename): self
    {
        if (! is_readable($filename)) {
            throw new RuntimeException("Failed to read {$filename}.");
        }
        $str = file_get_contents($filename);
        if ($str === false) {
            throw new RuntimeException("Failed to read {$filename}.");
        }
        return self::fromString($str);
    }

    /**
     * Get content type.
     */
    public function type(): string
    {
        return $this->type;
    }

    public function data(): string
    {
        return $this->data;
    }

    /**
     * Encode to PEM string.
     */
    public function string(): string
    {
        return "-----BEGIN {$this->type}-----\n" .
            trim(chunk_split(base64_encode($this->data), 64, "\n")) . "\n" .
            "-----END {$this->type}-----";
    }
}
