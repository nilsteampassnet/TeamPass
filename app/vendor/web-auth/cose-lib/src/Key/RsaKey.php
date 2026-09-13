<?php

declare(strict_types=1);

namespace Cose\Key;

use function array_key_exists;
use function base64_encode;
use function chunk_split;
use function implode;
use function in_array;
use InvalidArgumentException;
use function is_string;
use function ltrim;
use function ord;
use function pack;
use function sprintf;
use function strlen;
use function trim;

/**
 * @final
 * @see \Cose\Tests\Key\RsaKeyTest
 */
class RsaKey extends Key
{
    final public const DATA_N = -1;

    final public const DATA_E = -2;

    final public const DATA_D = -3;

    final public const DATA_P = -4;

    final public const DATA_Q = -5;

    final public const DATA_DP = -6;

    final public const DATA_DQ = -7;

    final public const DATA_QI = -8;

    final public const DATA_OTHER = -9;

    final public const DATA_RI = -10;

    final public const DATA_DI = -11;

    final public const DATA_TI = -12;

    /**
     * The DER of the rsaEncryption object identifier, 1.2.840.113549.1.1.1 (RFC 8017, appendix A.1).
     */
    private const OID_RSA_ENCRYPTION = "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01";

    private const DER_NULL = "\x05\x00";

    /**
     * @var array<int>
     */
    private const PRIVATE_PARAMETERS = [
        self::DATA_D,
        self::DATA_P,
        self::DATA_Q,
        self::DATA_DP,
        self::DATA_DQ,
        self::DATA_QI,
    ];

    /**
     * @param array<int|string, mixed> $data
     */
    public function __construct(array $data)
    {
        $data = self::normalizeIntegerEntries($data, self::TYPE);
        parent::__construct($data);
        if ($data[self::TYPE] !== self::TYPE_RSA && $data[self::TYPE] !== self::TYPE_NAME_RSA) {
            throw new InvalidArgumentException('Invalid RSA key. The key type does not correspond to a RSA key');
        }
        if (! isset($data[self::DATA_N], $data[self::DATA_E])) {
            throw new InvalidArgumentException('Invalid RSA key. The modulus or the exponent is missing');
        }
        $modulus = $data[self::DATA_N];
        $exponent = $data[self::DATA_E];
        if (! is_string($modulus) || $modulus === '' || ! is_string($exponent) || $exponent === '') {
            throw new InvalidArgumentException(
                'Invalid RSA key. The modulus and the exponent shall be non-empty byte strings'
            );
        }
        if (ltrim($modulus, "\0") === '') {
            throw new InvalidArgumentException('Invalid RSA key. The modulus shall not be zero');
        }
        foreach (self::PRIVATE_PARAMETERS as $parameter) {
            if (array_key_exists($parameter, $data) && ! is_string($data[$parameter])) {
                throw new InvalidArgumentException('Invalid RSA key. The private parameters shall be byte strings');
            }
        }
    }

    /**
     * @param array<int|string, mixed> $data
     */
    public static function create(array $data): self
    {
        return new self($data);
    }

    public function n(): string
    {
        return $this->get(self::DATA_N);
    }

    public function e(): string
    {
        return $this->get(self::DATA_E);
    }

    public function d(): string
    {
        $this->checkKeyIsPrivate();

        return $this->get(self::DATA_D);
    }

    public function p(): string
    {
        $this->checkKeyIsPrivate();

        return $this->get(self::DATA_P);
    }

    public function q(): string
    {
        $this->checkKeyIsPrivate();

        return $this->get(self::DATA_Q);
    }

    public function dP(): string
    {
        $this->checkKeyIsPrivate();

        return $this->get(self::DATA_DP);
    }

    public function dQ(): string
    {
        $this->checkKeyIsPrivate();

        return $this->get(self::DATA_DQ);
    }

    public function QInv(): string
    {
        $this->checkKeyIsPrivate();

        return $this->get(self::DATA_QI);
    }

    /**
     * The "other prime infos" of a multi-prime key (RFC 8230, section 4): one map per prime from the third one on,
     * each holding the DATA_RI, DATA_DI and DATA_TI entries.
     *
     * @return array<int, array<int, string>>
     */
    public function other(): array
    {
        $this->checkKeyIsPrivate();

        return $this->get(self::DATA_OTHER);
    }

    public function rI(): string
    {
        $this->checkKeyIsPrivate();

        return $this->get(self::DATA_RI);
    }

    public function dI(): string
    {
        $this->checkKeyIsPrivate();

        return $this->get(self::DATA_DI);
    }

    public function tI(): string
    {
        $this->checkKeyIsPrivate();

        return $this->get(self::DATA_TI);
    }

    public function hasPrimes(): bool
    {
        return $this->has(self::DATA_P) && $this->has(self::DATA_Q);
    }

    /**
     * @return string[]
     */
    public function primes(): array
    {
        return [$this->p(), $this->q()];
    }

    public function hasExponents(): bool
    {
        return $this->has(self::DATA_DP) && $this->has(self::DATA_DQ);
    }

    /**
     * @return string[]
     */
    public function exponents(): array
    {
        return [$this->dP(), $this->dQ()];
    }

    public function hasCoefficient(): bool
    {
        return $this->has(self::DATA_QI);
    }

    public function isPublic(): bool
    {
        return ! $this->isPrivate();
    }

    public function isPrivate(): bool
    {
        return array_key_exists(self::DATA_D, $this->getData());
    }

    /**
     * A key carrying "other prime infos" (RFC 8230, section 4) is exported as a version 0, two-prime RSAPrivateKey in
     * which p*q is not the modulus: the PKCS #1 OtherPrimeInfos sequence is not emitted. RS1, RS256, RS384 and RS512
     * keep working with such a key only because OpenSSL checks its CRT result against s^e mod n and falls back to
     * m^d mod n when the two disagree. RSASSA-PSS does not go through this method and handles the additional primes
     * itself.
     *
     * The DER is built from the octet strings of the key as they are, without ever turning them into numbers. X.690,
     * section 8.3 defines the content octets of an ASN.1 INTEGER as the two's complement big-endian representation of
     * its value, which for a non-negative integer is its raw big-endian magnitude with a leading 0x00 whenever the
     * first bit is set - and RFC 8230, section 4 already stores every parameter of a COSE RSA key in exactly that
     * form. Routing them through a big integer library instead would convert each of them to a decimal string and
     * back: brick/math falls back to a pure PHP calculator whenever neither ext-gmp nor ext-bcmath is loaded, and its
     * generic base conversion is superlinear, so that round trip alone costs seconds of CPU on a large modulus - paid
     * on every verification, before OpenSSL sees anything.
     */
    public function asPem(): string
    {
        if ($this->isPrivate()) {
            return self::toPem('RSA PRIVATE KEY', self::derSequence(
                // RFC 8017, appendix A.1.2: version is 0 for a two-prime key.
                self::derInteger("\0"),
                self::derInteger($this->n()),
                self::derInteger($this->e()),
                self::derInteger($this->d()),
                self::derInteger($this->p()),
                self::derInteger($this->q()),
                self::derInteger($this->dP()),
                self::derInteger($this->dQ()),
                self::derInteger($this->QInv())
            ));
        }

        // RFC 5280, section 4.1: SubjectPublicKeyInfo wraps the DER of an RFC 8017 RSAPublicKey in a BIT STRING,
        // behind the rsaEncryption algorithm identifier and its mandatory NULL parameters (RFC 3279, section 2.3.1).
        $rsaPublicKey = self::derSequence(self::derInteger($this->n()), self::derInteger($this->e()));

        return self::toPem('PUBLIC KEY', self::derSequence(
            self::derSequence(self::OID_RSA_ENCRYPTION, self::DER_NULL),
            self::derBitString($rsaPublicKey)
        ));
    }

    public function toPublic(): static
    {
        $toBeRemoved = [
            self::DATA_D,
            self::DATA_P,
            self::DATA_Q,
            self::DATA_DP,
            self::DATA_DQ,
            self::DATA_QI,
            self::DATA_OTHER,
            self::DATA_RI,
            self::DATA_DI,
            self::DATA_TI,
        ];
        $data = $this->getData();
        foreach ($data as $k => $v) {
            if (in_array($k, $toBeRemoved, true)) {
                unset($data[$k]);
            }
        }

        return new static($data);
    }

    private function checkKeyIsPrivate(): void
    {
        if (! $this->isPrivate()) {
            throw new InvalidArgumentException('The key is not private.');
        }
    }

    /**
     * An ASN.1 INTEGER holding the non-negative integer whose big-endian magnitude is $value (X.690, section 8.3).
     */
    private static function derInteger(string $value): string
    {
        $value = ltrim($value, "\0");
        if ($value === '') {
            // Section 8.3.1: the content octets are never empty, so zero is a single 0x00 octet.
            $value = "\0";
        } elseif ((ord($value[0]) & 0x80) !== 0) {
            // Section 8.3.2: without this octet the value would be read as a negative number.
            $value = "\0" . $value;
        }

        return self::der(0x02, $value);
    }

    private static function derSequence(string ...$elements): string
    {
        return self::der(0x30, implode('', $elements));
    }

    private static function derBitString(string $value): string
    {
        // X.690, section 8.6.2.2: the leading octet is the number of unused bits of the final octet, none here since
        // the value being wrapped is a whole number of octets.
        return self::der(0x03, "\0" . $value);
    }

    private static function der(int $tag, string $content): string
    {
        $length = strlen($content);
        if ($length < 0x80) {
            // X.690, section 8.1.3.4: short form.
            $encodedLength = pack('C', $length);
        } else {
            // Section 8.1.3.5: long form, the length itself in the fewest octets that can hold it.
            $octets = ltrim(pack('J', $length), "\0");
            $encodedLength = pack('C', 0x80 | strlen($octets)) . $octets;
        }

        return pack('C', $tag) . $encodedLength . $content;
    }

    private static function toPem(string $type, string $der): string
    {
        return sprintf(
            "-----BEGIN %s-----\n%s\n-----END %s-----",
            $type,
            trim(chunk_split(base64_encode($der), 64, "\n")),
            $type
        );
    }
}
