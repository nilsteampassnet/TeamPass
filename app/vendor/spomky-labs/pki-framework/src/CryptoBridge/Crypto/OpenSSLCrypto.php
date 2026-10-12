<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\CryptoBridge\Crypto;

use function array_key_exists;
use function function_exists;
use function mb_strlen;
use const OPENSSL_ALGO_MD4;
use const OPENSSL_ALGO_MD5;
use const OPENSSL_ALGO_SHA1;
use const OPENSSL_ALGO_SHA224;
use const OPENSSL_ALGO_SHA256;
use const OPENSSL_ALGO_SHA384;
use const OPENSSL_ALGO_SHA512;
use const OPENSSL_RAW_DATA;
use const OPENSSL_ZERO_PADDING;
use RuntimeException;
use SpomkyLabs\Pki\CryptoBridge\Crypto;
use SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\AlgorithmIdentifier;
use SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\Cipher\BlockCipherAlgorithmIdentifier;
use SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\Cipher\CipherAlgorithmIdentifier;
use SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\Cipher\RC2CBCAlgorithmIdentifier;
use SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\Feature\SignatureAlgorithmIdentifier;
use SpomkyLabs\Pki\CryptoTypes\Asymmetric\PrivateKeyInfo;
use SpomkyLabs\Pki\CryptoTypes\Asymmetric\PublicKeyInfo;
use SpomkyLabs\Pki\CryptoTypes\Signature\Signature;
use function sprintf;
use UnexpectedValueException;

/**
 * Crypto engine using OpenSSL extension.
 */
final class OpenSSLCrypto extends Crypto
{
    /**
     * Whether this runtime's OpenSSL extension can verify each EdDSA algorithm, once it has been asked.
     *
     * @var array<string, bool>
     */
    private static array $opensslEdDSASupport = [];

    /**
     * Mapping from algorithm OID to OpenSSL signature method identifier.
     *
     * @internal
     *
     * @var array<string, int>
     */
    private const MAP_DIGEST_OID = [
        AlgorithmIdentifier::OID_MD4_WITH_RSA_ENCRYPTION => OPENSSL_ALGO_MD4,
        AlgorithmIdentifier::OID_MD5_WITH_RSA_ENCRYPTION => OPENSSL_ALGO_MD5,
        AlgorithmIdentifier::OID_SHA1_WITH_RSA_ENCRYPTION => OPENSSL_ALGO_SHA1,
        AlgorithmIdentifier::OID_SHA224_WITH_RSA_ENCRYPTION => OPENSSL_ALGO_SHA224,
        AlgorithmIdentifier::OID_SHA256_WITH_RSA_ENCRYPTION => OPENSSL_ALGO_SHA256,
        AlgorithmIdentifier::OID_SHA384_WITH_RSA_ENCRYPTION => OPENSSL_ALGO_SHA384,
        AlgorithmIdentifier::OID_SHA512_WITH_RSA_ENCRYPTION => OPENSSL_ALGO_SHA512,
        AlgorithmIdentifier::OID_ECDSA_WITH_SHA1 => OPENSSL_ALGO_SHA1,
        AlgorithmIdentifier::OID_ECDSA_WITH_SHA224 => OPENSSL_ALGO_SHA224,
        AlgorithmIdentifier::OID_ECDSA_WITH_SHA256 => OPENSSL_ALGO_SHA256,
        AlgorithmIdentifier::OID_ECDSA_WITH_SHA384 => OPENSSL_ALGO_SHA384,
        AlgorithmIdentifier::OID_ECDSA_WITH_SHA512 => OPENSSL_ALGO_SHA512,
        // EdDSA is a one shot signature scheme: the message is not pre-hashed, and OpenSSL takes 0 as the digest
        // method. Without these two entries a chain that `openssl verify` accepts could not be verified at all,
        // while PathValidationConfig advertises both algorithms in its default allow list.
        AlgorithmIdentifier::OID_ED25519 => 0,
        AlgorithmIdentifier::OID_ED448 => 0,
    ];

    /**
     * The EdDSA signature algorithms, with what is needed to use and to probe for each of them.
     *
     * EdDSA is a one shot scheme: the message is not pre-hashed, so it does not fit the digest based API the
     * other algorithms go through, and an OpenSSL extension that does not implement it reports every signature as
     * invalid rather than saying so. `spki` and `signature` are RFC 8032's test vector for the empty message
     * (sect. 7.1 test 1 and sect. 7.4 "Blank"), a known good pair this engine verifies once to find out whether
     * the runtime can do the algorithm at all. Asking the runtime is more honest than comparing PHP_VERSION_ID,
     * since the extension also has to be built against an OpenSSL that has the curve.
     *
     * @internal
     *
     * @var array<string, array{keyLength: int, signatureLength: int, spki: string, signature: string}>
     */
    private const MAP_EDDSA = [
        AlgorithmIdentifier::OID_ED25519 => [
            'keyLength' => 32,
            'signatureLength' => 64,
            'spki' => '302a300506032b6570032100' .
                'd75a980182b10ab7d54bfed3c964073a0ee172f3daa62325af021a68f707511a',
            'signature' => 'e5564300c360ac729086e2cc806e828a84877f1eb8e5d974d873e06522490155' .
                '5fb8821590a33bacc61e39701cf9b46bd25bf5f0595bbe24655141438e7a100b',
        ],
        AlgorithmIdentifier::OID_ED448 => [
            'keyLength' => 57,
            'signatureLength' => 114,
            'spki' => '3043300506032b6571033a00' .
                '5fd7449b59b461fd2ce787ec616ad46a1da1342485a70e1f8a0ea75d80e96778' .
                'edf124769b46c7061bd6783df1e50f6cd1fa1abeafe8256180',
            'signature' => '533a37f6bbe457251f023c0d88f976ae2dfb504a843e34d2074fd823d41a591f' .
                '2b233f034f628281f2fd7a22ddd47d7828c59bd0a21bfd3980ff0d2028d4b18a' .
                '9df63e006c5d1c2d345b925d8dc00b4104852db99ac5c7cdda8530a113a0f4db' .
                'b61149f05a7363268c71d95808ff2e652600',
        ],
    ];

    /**
     * Mapping from algorithm OID to OpenSSL cipher method name.
     *
     * @internal
     *
     * @var array<string, string>
     */
    private const MAP_CIPHER_OID = [
        AlgorithmIdentifier::OID_DES_CBC => 'des-cbc',
        AlgorithmIdentifier::OID_DES_EDE3_CBC => 'des-ede3-cbc',
        AlgorithmIdentifier::OID_AES_128_CBC => 'aes-128-cbc',
        AlgorithmIdentifier::OID_AES_192_CBC => 'aes-192-cbc',
        AlgorithmIdentifier::OID_AES_256_CBC => 'aes-256-cbc',
    ];

    public function sign(
        string $data,
        PrivateKeyInfo $privkey_info,
        SignatureAlgorithmIdentifier $algo
    ): Signature {
        $this->_checkSignatureAlgoAndKey($algo, $privkey_info->algorithmIdentifier());
        if (array_key_exists($algo->oid(), self::MAP_EDDSA) && ! self::opensslSupportsEdDSA($algo->oid())) {
            // ext-sodium could sign Ed25519, but it cannot produce an Ed448 signature and this is not the place
            // to grow a second signing engine: say plainly that the runtime cannot do it.
            throw new UnexpectedValueException(sprintf(
                '%s signing is not supported by this PHP runtime.',
                $algo->name()
            ));
        }
        $result = openssl_sign($data, $signature, (string) $privkey_info->toPEM(), $this->_algoToDigest($algo));
        if ($result === false) {
            throw new RuntimeException('openssl_sign() failed: ' . $this->_getLastError());
        }
        return Signature::fromSignatureData($signature, $algo);
    }

    public function verify(
        string $data,
        Signature $signature,
        PublicKeyInfo $pubkey_info,
        SignatureAlgorithmIdentifier $algo
    ): bool {
        $this->_checkSignatureAlgoAndKey($algo, $pubkey_info->algorithmIdentifier());
        if (array_key_exists($algo->oid(), self::MAP_EDDSA)) {
            return $this->verifyEdDSA($data, $signature, $pubkey_info, $algo);
        }
        $result = openssl_verify(
            $data,
            $signature->bitString()
                ->string(),
            (string) $pubkey_info->toPEM(),
            $this->_algoToDigest($algo)
        );
        if ($result === -1) {
            throw new RuntimeException('openssl_verify() failed: ' . $this->_getLastError());
        }
        return $result === 1;
    }

    public function encrypt(string $data, string $key, CipherAlgorithmIdentifier $algo): string
    {
        $this->_checkCipherKeySize($algo, $key);
        $iv = $algo->initializationVector();
        $result = openssl_encrypt(
            $data,
            $this->_algoToCipher($algo),
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $iv
        );
        if ($result === false) {
            throw new RuntimeException('openssl_encrypt() failed: ' . $this->_getLastError());
        }
        return $result;
    }

    public function decrypt(string $data, string $key, CipherAlgorithmIdentifier $algo): string
    {
        $this->_checkCipherKeySize($algo, $key);
        $iv = $algo->initializationVector();
        $result = openssl_decrypt(
            $data,
            $this->_algoToCipher($algo),
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $iv
        );
        if ($result === false) {
            throw new RuntimeException('openssl_decrypt() failed: ' . $this->_getLastError());
        }
        return $result;
    }

    /**
     * Validate cipher algorithm key size.
     */
    protected function _checkCipherKeySize(CipherAlgorithmIdentifier $algo, string $key): void
    {
        if ($algo instanceof BlockCipherAlgorithmIdentifier) {
            if (mb_strlen($key, '8bit') !== $algo->keySize()) {
                throw new UnexpectedValueException(
                    sprintf(
                        'Key length for %s must be %d, %d given.',
                        $algo->name(),
                        $algo->keySize(),
                        mb_strlen($key, '8bit')
                    )
                );
            }
        }
    }

    /**
     * Get last OpenSSL error message.
     */
    protected function _getLastError(): ?string
    {
        // pump error message queue
        $msg = null;
        while (false !== ($err = openssl_error_string())) {
            $msg = $err;
        }
        return $msg;
    }

    /**
     * Whether this engine can check a signature made with the given algorithm.
     *
     * PathValidationConfig advertises Ed25519 and Ed448 in its default allowed set, but whether they can actually
     * be checked depends on the runtime. An application that would rather refuse a chain up front than have it
     * fail closed during validation can narrow the configured set with this.
     */
    public function supportsSignatureAlgorithm(SignatureAlgorithmIdentifier $algo): bool
    {
        $oid = $algo->oid();
        if (array_key_exists($oid, self::MAP_EDDSA)) {
            return self::opensslSupportsEdDSA($oid)
                || ($oid === AlgorithmIdentifier::OID_ED25519
                    && function_exists('sodium_crypto_sign_verify_detached'));
        }
        return array_key_exists($oid, self::MAP_DIGEST_OID);
    }

    /**
     * Verify an EdDSA signature.
     *
     * PHP 8.5 verifies it through openssl_verify() with 0 as the digest method. Older runtimes have no EdDSA in
     * the OpenSSL extension at all, so Ed25519 goes through ext-sodium, which has been bundled since PHP 7.2.
     */
    private function verifyEdDSA(
        string $data,
        Signature $signature,
        PublicKeyInfo $pubkey_info,
        SignatureAlgorithmIdentifier $algo
    ): bool {
        $oid = $algo->oid();
        $sig = $signature->bitString()
            ->string();
        if (self::opensslSupportsEdDSA($oid)) {
            $result = openssl_verify($data, $sig, (string) $pubkey_info->toPEM(), 0);
            if ($result === -1) {
                throw new RuntimeException('openssl_verify() failed: ' . $this->_getLastError());
            }
            return $result === 1;
        }
        // ext-sodium has been bundled since PHP 7.2 and covers Ed25519. There is no equivalent for Ed448.
        if ($oid === AlgorithmIdentifier::OID_ED25519
            && function_exists('sodium_crypto_sign_verify_detached')) {
            $key = $pubkey_info->publicKeyData()
                ->string();
            // sodium refuses a key or a signature of the wrong size with a TypeError rather than a false, and
            // both come straight out of the certificate
            if ($key === '' || $sig === '') {
                return false;
            }
            if (mb_strlen($key, '8bit') !== self::MAP_EDDSA[$oid]['keyLength']
                || mb_strlen($sig, '8bit') !== self::MAP_EDDSA[$oid]['signatureLength']) {
                return false;
            }
            return sodium_crypto_sign_verify_detached($sig, $data, $key);
        }
        throw new UnexpectedValueException(sprintf(
            '%s verification is not supported by this PHP runtime.',
            $algo->name()
        ));
    }

    /**
     * Whether this runtime's OpenSSL extension can verify a signature made with the given EdDSA algorithm.
     *
     * Answered once per algorithm, by verifying RFC 8032's known good test vector for it. An extension without
     * EdDSA reports the vector as invalid rather than raising, which is exactly the failure mode this guards
     * against: the two curves are probed separately, since a runtime may well have one and not the other.
     */
    private static function opensslSupportsEdDSA(string $oid): bool
    {
        if (! array_key_exists($oid, self::$opensslEdDSASupport)) {
            $pem = "-----BEGIN PUBLIC KEY-----\n" .
                trim(chunk_split(base64_encode((string) hex2bin(self::MAP_EDDSA[$oid]['spki'])), 64, "\n")) .
                "\n-----END PUBLIC KEY-----";
            $signature = (string) hex2bin(self::MAP_EDDSA[$oid]['signature']);
            self::$opensslEdDSASupport[$oid] = @openssl_verify('', $signature, $pem, 0) === 1;
        }
        return self::$opensslEdDSASupport[$oid];
    }

    /**
     * Check that given signature algorithm supports key of given type.
     *
     * @param SignatureAlgorithmIdentifier $sig_algo Signature algorithm
     * @param AlgorithmIdentifier $key_algo Key algorithm
     */
    protected function _checkSignatureAlgoAndKey(
        SignatureAlgorithmIdentifier $sig_algo,
        AlgorithmIdentifier $key_algo
    ): void {
        if (! $sig_algo->supportsKeyAlgorithm($key_algo)) {
            throw new UnexpectedValueException(
                sprintf(
                    'Signature algorithm %s does not support key algorithm %s.',
                    $sig_algo->name(),
                    $key_algo->name()
                )
            );
        }
    }

    /**
     * Get OpenSSL digest method for given signature algorithm identifier.
     */
    protected function _algoToDigest(SignatureAlgorithmIdentifier $algo): int
    {
        $oid = $algo->oid();
        if (! array_key_exists($oid, self::MAP_DIGEST_OID)) {
            throw new UnexpectedValueException(sprintf('Digest method %s not supported.', $algo->name()));
        }
        return self::MAP_DIGEST_OID[$oid];
    }

    /**
     * Get OpenSSL cipher method for given cipher algorithm identifier.
     */
    protected function _algoToCipher(CipherAlgorithmIdentifier $algo): string
    {
        $oid = $algo->oid();
        if (array_key_exists($oid, self::MAP_CIPHER_OID)) {
            return self::MAP_CIPHER_OID[$oid];
        }
        if ($oid === AlgorithmIdentifier::OID_RC2_CBC) {
            if (! $algo instanceof RC2CBCAlgorithmIdentifier) {
                throw new UnexpectedValueException('Not an RC2-CBC algorithm.');
            }
            return $this->_rc2AlgoToCipher($algo);
        }
        throw new UnexpectedValueException(sprintf('Cipher method %s not supported.', $algo->name()));
    }

    /**
     * Get OpenSSL cipher method for given RC2 algorithm identifier.
     */
    protected function _rc2AlgoToCipher(RC2CBCAlgorithmIdentifier $algo): string
    {
        return match ($algo->effectiveKeyBits()) {
            128 => 'rc2-cbc',
            64 => 'rc2-64-cbc',
            40 => 'rc2-40-cbc',
            default => throw new UnexpectedValueException($algo->effectiveKeyBits() . ' bit RC2 not supported.'),
        };
    }
}
