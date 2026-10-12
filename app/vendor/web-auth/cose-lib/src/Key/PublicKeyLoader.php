<?php

declare(strict_types=1);

namespace Cose\Key;

use function array_key_exists;
use function in_array;
use function intdiv;
use InvalidArgumentException;
use function ltrim;
use function ord;
use SpomkyLabs\Pki\CryptoEncoding\PEM;
use SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\Asymmetric\ECPublicKeyAlgorithmIdentifier;
use SpomkyLabs\Pki\CryptoTypes\Asymmetric\PublicKeyInfo;
use SpomkyLabs\Pki\X509\Certificate\Certificate;
use function sprintf;
use function str_contains;
use function strlen;
use function substr;
use Throwable;

/**
 * Turns the public key of an X.509 certificate, or a bare SubjectPublicKeyInfo, into the Cose\Key\Key the signature
 * algorithms of this library take.
 *
 * WebAuthn Level 3 sections 8.2 to 8.4 ask a relying party to verify a certificate-based attestation statement "with
 * the algorithm specified in alg". Doing so through Algorithms::getOpensslAlgorithmFor() and openssl_verify() only
 * covers ECDSA and RSASSA-PKCS1-v1_5, because an OPENSSL_ALGO_* digest implies PKCS #1 v1.5 padding; RSASSA-PSS and
 * the EdDSA family cannot be expressed that way. This loader is the missing half of the other route: it produces the
 * key, and the Signature class registered for the identifier does the verification. See
 * Cose\Algorithm\Signature\CertificateSignatureVerifier for the two put together.
 *
 * Both methods accept PEM or raw DER.
 *
 * @see \Cose\Tests\Key\PublicKeyLoaderTest
 */
final class PublicKeyLoader
{
    /**
     * The named curves of RFC 5480, section 2.1.1.1 and RFC 5639, section 4.1 this library has a COSE identifier for
     * (RFC 9053, section 7.1 and the IANA COSE Elliptic Curves registry).
     */
    private const CURVE_OID_TO_COSE_CURVE = [
        '1.2.840.10045.3.1.7' => Ec2Key::CURVE_P256,
        '1.3.132.0.10' => Ec2Key::CURVE_P256K,
        '1.3.132.0.34' => Ec2Key::CURVE_P384,
        '1.3.132.0.35' => Ec2Key::CURVE_P521,
        '1.3.36.3.3.2.8.1.1.7' => Ec2Key::CURVE_BP256,
        '1.3.36.3.3.2.8.1.1.9' => Ec2Key::CURVE_BP320,
        '1.3.36.3.3.2.8.1.1.11' => Ec2Key::CURVE_BP384,
        '1.3.36.3.3.2.8.1.1.13' => Ec2Key::CURVE_BP512,
    ];

    /**
     * The RFC 8410, section 3 algorithm identifiers, whose subjectPublicKey is the public key itself.
     */
    private const RFC8410_OID_TO_COSE_CURVE = [
        '1.3.101.110' => OkpKey::CURVE_X25519,
        '1.3.101.111' => OkpKey::CURVE_X448,
        '1.3.101.112' => OkpKey::CURVE_ED25519,
        '1.3.101.113' => OkpKey::CURVE_ED448,
    ];

    /**
     * rsaEncryption (RFC 8017, appendix A.1) and RSASSA-PSS (RFC 4055, section 1.2). The latter carries the same
     * RSAPublicKey structure behind its own object identifier; the COSE key knows nothing of the padding, which the
     * algorithm identifier of the signature decides, so both are read the same way.
     */
    private const RSA_OIDS = ['1.2.840.113549.1.1.1', '1.2.840.113549.1.1.10'];

    /**
     * id-ecPublicKey, RFC 5480, section 2.1.1.
     */
    private const OID_EC_PUBLIC_KEY = '1.2.840.10045.2.1';

    private const DER_TAG_SEQUENCE = 0x30;

    private const DER_TAG_INTEGER = 0x02;

    /**
     * The public key of an X.509 certificate, PEM or DER encoded.
     *
     * @throws InvalidArgumentException when the certificate cannot be read, or carries a key this library has no
     * COSE representation for
     */
    public static function fromCertificate(string $certificate): Key
    {
        try {
            $parsed = Certificate::fromDER(self::der($certificate, PEM::TYPE_CERTIFICATE));
        } catch (Throwable $throwable) {
            throw new InvalidArgumentException('Unable to read the certificate', 0, $throwable);
        }

        return self::fromPublicKeyInfo($parsed->tbsCertificate()->subjectPublicKeyInfo());
    }

    /**
     * A bare SubjectPublicKeyInfo (RFC 5280, section 4.1.2.7), PEM or DER encoded.
     *
     * @throws InvalidArgumentException when the structure cannot be read, or carries a key this library has no COSE
     * representation for
     */
    public static function fromSubjectPublicKeyInfo(string $subjectPublicKeyInfo): Key
    {
        try {
            $parsed = PublicKeyInfo::fromDER(self::der($subjectPublicKeyInfo, PEM::TYPE_PUBLIC_KEY));
        } catch (Throwable $throwable) {
            throw new InvalidArgumentException('Unable to read the public key', 0, $throwable);
        }

        return self::fromPublicKeyInfo($parsed);
    }

    private static function fromPublicKeyInfo(PublicKeyInfo $publicKeyInfo): Key
    {
        $algorithm = $publicKeyInfo->algorithmIdentifier();
        $oid = $algorithm->oid();
        $publicKey = $publicKeyInfo->publicKeyData()
            ->string();

        if (in_array($oid, self::RSA_OIDS, true)) {
            return self::rsaKey($publicKey);
        }
        if (array_key_exists($oid, self::RFC8410_OID_TO_COSE_CURVE)) {
            return OkpKey::create([
                Key::TYPE => Key::TYPE_OKP,
                OkpKey::DATA_CURVE => self::RFC8410_OID_TO_COSE_CURVE[$oid],
                OkpKey::DATA_X => $publicKey,
            ]);
        }
        if ($oid === self::OID_EC_PUBLIC_KEY && $algorithm instanceof ECPublicKeyAlgorithmIdentifier) {
            return self::ec2Key($algorithm->namedCurve(), $publicKey);
        }

        throw new InvalidArgumentException(sprintf('Unsupported public key algorithm "%s"', $oid));
    }

    private static function ec2Key(string $namedCurve, string $ecPoint): Ec2Key
    {
        if (! array_key_exists($namedCurve, self::CURVE_OID_TO_COSE_CURVE)) {
            throw new InvalidArgumentException(sprintf('Unsupported elliptic curve "%s"', $namedCurve));
        }
        // RFC 5480, section 2.2: the subjectPublicKey is the ECPoint of SEC 1, section 2.3.3. Only its uncompressed
        // form is read here, as it is the only one Cose\Key\Ec2Key stores; decompressing a point would mean
        // computing a square root modulo the field prime for every curve this library names.
        if ($ecPoint === '' || ord($ecPoint[0]) !== 0x04) {
            throw new InvalidArgumentException(
                'Unsupported elliptic curve public key: only the uncompressed point format is supported'
            );
        }
        $coordinates = substr($ecPoint, 1);
        if (strlen($coordinates) === 0 || strlen($coordinates) % 2 !== 0) {
            throw new InvalidArgumentException('Invalid elliptic curve public key');
        }
        $length = intdiv(strlen($coordinates), 2);

        // Ec2Key rejects coordinates whose length does not match the curve.
        return Ec2Key::create([
            Key::TYPE => Key::TYPE_EC2,
            Ec2Key::DATA_CURVE => self::CURVE_OID_TO_COSE_CURVE[$namedCurve],
            Ec2Key::DATA_X => substr($coordinates, 0, $length),
            Ec2Key::DATA_Y => substr($coordinates, $length),
        ]);
    }

    /**
     * Reads the RSAPublicKey of RFC 8017, appendix A.1.1 - SEQUENCE { modulus INTEGER, publicExponent INTEGER } - and
     * stores both integers as the unsigned big-endian octet strings RFC 8230, section 4 defines a COSE RSA key with.
     *
     * The two INTEGERs are taken as the octet strings they already are, and never turned into numbers: brick/math
     * falls back to a pure PHP calculator whenever neither ext-gmp nor ext-bcmath is loaded, and its generic base
     * conversion is superlinear, so decoding an attacker supplied modulus into a decimal string and back would cost
     * more than the verification it precedes. Cose\Key\RsaKey builds its DER the same way, and for the same reason.
     */
    private static function rsaKey(string $rsaPublicKey): RsaKey
    {
        $offset = 0;
        $content = self::readValue($rsaPublicKey, self::DER_TAG_SEQUENCE, $offset);
        if ($offset !== strlen($rsaPublicKey)) {
            throw new InvalidArgumentException('Invalid RSA public key: trailing data');
        }
        $inner = 0;
        $modulus = self::readValue($content, self::DER_TAG_INTEGER, $inner);
        $exponent = self::readValue($content, self::DER_TAG_INTEGER, $inner);
        if ($inner !== strlen($content)) {
            throw new InvalidArgumentException('Invalid RSA public key: trailing data');
        }

        return RsaKey::create([
            Key::TYPE => Key::TYPE_RSA,
            RsaKey::DATA_N => self::magnitude($modulus, 'modulus'),
            RsaKey::DATA_E => self::magnitude($exponent, 'public exponent'),
        ]);
    }

    /**
     * The content octets of the TLV of the expected tag starting at $offset, which is advanced past the whole TLV.
     *
     * Only the definite form of X.690, section 8.1.3 is accepted, which is all DER allows (section 10.1).
     */
    private static function readValue(string $der, int $expectedTag, int &$offset): string
    {
        if (strlen($der) < $offset + 2) {
            throw new InvalidArgumentException('Invalid RSA public key: truncated structure');
        }
        if (ord($der[$offset]) !== $expectedTag) {
            throw new InvalidArgumentException('Invalid RSA public key: unexpected structure');
        }
        ++$offset;
        $length = ord($der[$offset]);
        ++$offset;
        if ($length > 0x80) {
            $lengthOfLength = $length & 0x7F;
            // A length that needs more than four octets describes a value no PHP string can hold anyway.
            if ($lengthOfLength > 4 || strlen($der) < $offset + $lengthOfLength) {
                throw new InvalidArgumentException('Invalid RSA public key: unsupported length');
            }
            $length = 0;
            for ($i = 0; $i < $lengthOfLength; ++$i) {
                $length = ($length << 8) | ord($der[$offset + $i]);
            }
            $offset += $lengthOfLength;
        } elseif ($length === 0x80) {
            // X.690, section 10.1: the indefinite form is not DER.
            throw new InvalidArgumentException('Invalid RSA public key: indefinite length');
        }
        if (strlen($der) < $offset + $length) {
            throw new InvalidArgumentException('Invalid RSA public key: truncated structure');
        }
        $content = substr($der, $offset, $length);
        $offset += $length;

        return $content;
    }

    /**
     * X.690, section 8.3.2 encodes the content octets of an INTEGER in two's complement, so a leading 0x00 is only
     * there to keep a non-negative value from reading as a negative one; RFC 8230, section 4 stores the magnitude
     * alone. A value whose first bit is set is negative, which no RSA parameter ever is.
     */
    private static function magnitude(string $integer, string $name): string
    {
        if ($integer === '') {
            throw new InvalidArgumentException(sprintf('Invalid RSA public key: the %s is empty', $name));
        }
        if ((ord($integer[0]) & 0x80) !== 0) {
            throw new InvalidArgumentException(sprintf('Invalid RSA public key: the %s is negative', $name));
        }
        $magnitude = ltrim($integer, "\x00");
        if ($magnitude === '') {
            throw new InvalidArgumentException(sprintf('Invalid RSA public key: the %s is zero', $name));
        }

        return $magnitude;
    }

    /**
     * The DER of a structure given either as PEM or as DER already.
     */
    private static function der(string $data, string $pemType): string
    {
        if (! str_contains($data, '-----BEGIN')) {
            return $data;
        }
        $pem = PEM::fromString($data);
        if ($pem->type() !== $pemType) {
            throw new InvalidArgumentException(sprintf('Invalid PEM type "%s", expected "%s"', $pem->type(), $pemType));
        }

        return $pem->data();
    }
}
