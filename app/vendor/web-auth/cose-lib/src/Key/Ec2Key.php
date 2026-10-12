<?php

declare(strict_types=1);

namespace Cose\Key;

use function array_key_exists;
use function in_array;
use InvalidArgumentException;
use function is_int;
use function is_string;
use SpomkyLabs\Pki\ASN1\Type\Constructed\Sequence;
use SpomkyLabs\Pki\ASN1\Type\Primitive\BitString;
use SpomkyLabs\Pki\ASN1\Type\Primitive\Integer;
use SpomkyLabs\Pki\ASN1\Type\Primitive\ObjectIdentifier;
use SpomkyLabs\Pki\ASN1\Type\Primitive\OctetString;
use SpomkyLabs\Pki\ASN1\Type\Tagged\ExplicitlyTaggedType;
use function sprintf;
use function strlen;

/**
 * @final
 * @see \Cose\Tests\Key\Ec2KeyTest
 */
class Ec2Key extends Key
{
    final public const CURVE_P256 = 1;

    final public const CURVE_P256K = 8;

    final public const CURVE_P384 = 2;

    final public const CURVE_P521 = 3;

    final public const CURVE_NAME_P256 = 'P-256';

    /**
     * The registered name of COSE curve 8 (RFC 8812, sections 3.1 and 4.2) and the name OpenSSL gives it.
     */
    final public const CURVE_NAME_SECP256K1 = 'secp256k1';

    /**
     * @deprecated The draft-era spelling of curve 8, from draft-ietf-cose-webauthn-algorithms-00, renamed to
     * "secp256k1" before -01 and never registered. Still accepted; use CURVE_NAME_SECP256K1 instead.
     */
    final public const CURVE_NAME_P256K = 'P-256K';

    final public const CURVE_NAME_P384 = 'P-384';

    final public const CURVE_NAME_P521 = 'P-521';

    final public const CURVE_BP256 = 256;

    final public const CURVE_BP320 = 257;

    final public const CURVE_BP384 = 258;

    final public const CURVE_BP512 = 259;

    final public const CURVE_NAME_BP256 = 'brainpoolP256r1';

    final public const CURVE_NAME_BP320 = 'brainpoolP320r1';

    final public const CURVE_NAME_BP384 = 'brainpoolP384r1';

    final public const CURVE_NAME_BP512 = 'brainpoolP512r1';

    final public const DATA_CURVE = -1;

    final public const DATA_X = -2;

    final public const DATA_Y = -3;

    final public const DATA_D = -4;

    private const SUPPORTED_CURVES_INT = [
        self::CURVE_P256,
        self::CURVE_P256K,
        self::CURVE_P384,
        self::CURVE_P521,
        self::CURVE_BP256,
        self::CURVE_BP320,
        self::CURVE_BP384,
        self::CURVE_BP512,
    ];

    /**
     * RFC 9053, section 7.1, table 19 types "crv" as "int / tstr": a curve may be named instead of numbered. Each of
     * these names maps to the identifier of the "COSE Elliptic Curves" registry that curveId() exposes.
     *
     * @var array<string, int>
     */
    private const CURVE_NAME_TO_ID = [
        self::CURVE_NAME_P256 => self::CURVE_P256,
        self::CURVE_NAME_SECP256K1 => self::CURVE_P256K,
        // The deprecated alias is listed on purpose: it is what keeps the draft-era name working.
        // @phpstan-ignore classConstant.deprecated
        self::CURVE_NAME_P256K => self::CURVE_P256K,
        self::CURVE_NAME_P384 => self::CURVE_P384,
        self::CURVE_NAME_P521 => self::CURVE_P521,
        self::CURVE_NAME_BP256 => self::CURVE_BP256,
        self::CURVE_NAME_BP320 => self::CURVE_BP320,
        self::CURVE_NAME_BP384 => self::CURVE_BP384,
        self::CURVE_NAME_BP512 => self::CURVE_BP512,
    ];

    private const NAMED_CURVE_OID = [
        self::CURVE_P256 => '1.2.840.10045.3.1.7',
        // NIST P-256 / secp256r1
        self::CURVE_P256K => '1.3.132.0.10',
        // SECG secp256k1 (RFC 8812)
        self::CURVE_P384 => '1.3.132.0.34',
        // NIST P-384 / secp384r1
        self::CURVE_P521 => '1.3.132.0.35',
        // NIST P-521 / secp521r1
        self::CURVE_BP256 => '1.3.36.3.3.2.8.1.1.7',
        // brainpoolP256r1
        self::CURVE_BP320 => '1.3.36.3.3.2.8.1.1.9',
        // brainpoolP320r1
        self::CURVE_BP384 => '1.3.36.3.3.2.8.1.1.11',
        // brainpoolP384r1
        self::CURVE_BP512 => '1.3.36.3.3.2.8.1.1.13',
        // brainpoolP512r1
    ];

    private const CURVE_KEY_LENGTH = [
        self::CURVE_P256 => 32,
        self::CURVE_P256K => 32,
        self::CURVE_P384 => 48,
        self::CURVE_P521 => 66,
        self::CURVE_BP256 => 32,
        self::CURVE_BP320 => 40,
        self::CURVE_BP384 => 48,
        self::CURVE_BP512 => 64,
    ];

    /**
     * @param array<int|string, mixed> $data
     */
    public function __construct(array $data)
    {
        // Everything below is read from attacker-supplied CBOR: each entry is checked to be present and of the
        // expected PHP type before it is used, so that a malformed key always leaves through the
        // InvalidArgumentException this library documents rather than through a warning, a TypeError or an Error.
        $data = self::normalizeIntegerEntries($data, self::DATA_CURVE, self::TYPE);
        parent::__construct($data);
        if ($data[self::TYPE] !== self::TYPE_EC2 && $data[self::TYPE] !== self::TYPE_NAME_EC2) {
            throw new InvalidArgumentException('Invalid EC2 key. The key type does not correspond to an EC2 key');
        }
        // RFC 9053 section 7.1.1: "For public keys, it is REQUIRED that 'crv', 'x', and 'y' be present".
        if (! isset($data[self::DATA_CURVE], $data[self::DATA_X], $data[self::DATA_Y])) {
            throw new InvalidArgumentException('Invalid EC2 key. The curve or the "x/y" coordinates are missing');
        }
        // The curve is checked first: the coordinate lengths below are read from a table indexed by the curve.
        $curveId = self::toCurveId($data[self::DATA_CURVE]);
        if ($curveId === null) {
            throw new InvalidArgumentException('The curve is not supported');
        }
        $length = self::CURVE_KEY_LENGTH[$curveId];
        // RFC 9053 section 7.1, table 19 types "x", "y" and "d" as byte strings.
        foreach ([
            self::DATA_X => 'x',
            self::DATA_Y => 'y',
        ] as $index => $name) {
            if (! is_string($data[$index])) {
                throw new InvalidArgumentException(sprintf('Invalid type for %s coordinate', $name));
            }
            if (strlen($data[$index]) !== $length) {
                throw new InvalidArgumentException(sprintf('Invalid length for %s coordinate', $name));
            }
        }
        // RFC 5915 section 3: the private key is "an octet string of length ceiling (log2(n)/8)".
        if (array_key_exists(self::DATA_D, $data)
            && (! is_string($data[self::DATA_D]) || strlen($data[self::DATA_D]) !== $length)) {
            throw new InvalidArgumentException('Invalid length for d');
        }
    }

    /**
     * @param array<int|string, mixed> $data
     */
    public static function create(array $data): self
    {
        return new self($data);
    }

    public function toPublic(): self
    {
        $data = $this->getData();
        unset($data[self::DATA_D]);

        return new self($data);
    }

    public function x(): string
    {
        return $this->get(self::DATA_X);
    }

    public function y(): string
    {
        return $this->get(self::DATA_Y);
    }

    public function isPrivate(): bool
    {
        return array_key_exists(self::DATA_D, $this->getData());
    }

    public function d(): string
    {
        if (! $this->isPrivate()) {
            throw new InvalidArgumentException('The key is not private.');
        }
        return $this->get(self::DATA_D);
    }

    /**
     * The curve as the key carries it, which RFC 9053 section 7.1 allows to be either the identifier of the "COSE
     * Elliptic Curves" registry or a name. Use curveId() to get the identifier whatever form was supplied.
     */
    public function curve(): int|string
    {
        return $this->get(self::DATA_CURVE);
    }

    /**
     * The value of the curve in the IANA "COSE Elliptic Curves" registry, whichever of the two forms the key uses.
     */
    public function curveId(): int
    {
        $curve = $this->curve();

        return is_int($curve) ? $curve : self::CURVE_NAME_TO_ID[$curve];
    }

    public function asPEM(): string
    {
        if ($this->isPrivate()) {
            $der = Sequence::create(
                Integer::create(1),
                OctetString::create($this->d()),
                ExplicitlyTaggedType::create(0, ObjectIdentifier::create($this->getCurveOid())),
                ExplicitlyTaggedType::create(1, BitString::create($this->getUncompressedCoordinates())),
            );

            return $this->pem('EC PRIVATE KEY', $der->toDER());
        }

        $der = Sequence::create(
            Sequence::create(
                ObjectIdentifier::create('1.2.840.10045.2.1'),
                ObjectIdentifier::create($this->getCurveOid())
            ),
            BitString::create($this->getUncompressedCoordinates())
        );

        return $this->pem('PUBLIC KEY', $der->toDER());
    }

    public function getUncompressedCoordinates(): string
    {
        return "\x04" . $this->x() . $this->y();
    }

    private function getCurveOid(): string
    {
        return self::NAMED_CURVE_OID[$this->curveId()];
    }

    /**
     * The registry value of a supported curve, or null when the value denotes no curve this class supports.
     */
    private static function toCurveId(mixed $curve): ?int
    {
        if (is_int($curve)) {
            return in_array($curve, self::SUPPORTED_CURVES_INT, true) ? $curve : null;
        }

        return is_string($curve) ? (self::CURVE_NAME_TO_ID[$curve] ?? null) : null;
    }
}
