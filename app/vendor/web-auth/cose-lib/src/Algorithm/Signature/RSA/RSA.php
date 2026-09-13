<?php

declare(strict_types=1);

namespace Cose\Algorithm\Signature\RSA;

use Cose\Algorithm\KeyRestrictionAware;
use Cose\Algorithm\KeyRestrictionEnforcement;
use Cose\Algorithm\Signature\OpenSslError;
use Cose\Algorithm\Signature\Signature;
use Cose\Key\Key;
use Cose\Key\RsaKey;
use Cose\Key\RsaKeyValidator;
use InvalidArgumentException;
use function openssl_pkey_get_private;
use function openssl_pkey_get_public;
use function openssl_sign;
use function openssl_verify;

/**
 * RSASSA-PKCS1-v1_5 as defined by RFC 8017, section 8.2.
 *
 * Section 8.2.2 applies RSAVP1 under the assumption that the public key is valid, i.e. that its exponent is an odd
 * integer between 3 and n - 1 (section 3.1). openssl_verify() does not re-check it: with e = 1 the public operation
 * is the identity map, so the EMSA-PKCS1-v1_5 encoding of a message - public data anyone can compute - would be
 * accepted as a signature of that message under an attacker chosen key. Every key is therefore checked here.
 *
 * Its length is bounded too, before it is used. RFC 8230, section 6.1 asks for it - "It is highly recommended that
 * checks on the key length be done before starting a cryptographic operation" - because the work an RSA operation
 * costs grows with the size of the key it is given, and a verifier takes that key from whoever produced the message.
 * The same section requires "a key size of 2048 bits or larger": that bound is applied by RsaKeyPolicy, with the
 * validator this algorithm was created with or, by default, with RsaKeyValidator::create().
 *
 * @see https://www.rfc-editor.org/rfc/rfc8017#section-8.2
 * @see \Cose\Tests\Algorithm\Signature\RSA\RSATest
 */
abstract class RSA implements Signature, KeyRestrictionAware
{
    use KeyRestrictionEnforcement;
    use RsaKeyPolicy;

    public function __construct(?RsaKeyValidator $keyValidator = null)
    {
        $this->initializeKeyValidator($keyValidator);
    }

    public function sign(string $data, Key $key): string
    {
        $key = $this->handleKey($key, Key::OP_SIGN);
        RsaKeyValidator::checkPublicParameters($key);
        RsaKeyValidator::checkLengthBounds($key);
        $this->checkKeyPolicy($key);
        if (! $key->isPrivate()) {
            throw new InvalidArgumentException('The key is not private.');
        }
        $privateKey = openssl_pkey_get_private($key->asPem());
        if ($privateKey === false) {
            throw new InvalidArgumentException('Unable to load the RSA private key');
        }
        OpenSslError::clear();
        // openssl_sign() reports failure with a boolean and never throws: a modulus too short for the digest would
        // otherwise leave $signature null and raise a TypeError on the way out.
        if (! openssl_sign($data, $signature, $privateKey, $this->getHashAlgorithm())) {
            throw new InvalidArgumentException('Unable to sign the data: ' . OpenSslError::lastMessage());
        }

        return $signature;
    }

    public function verify(string $data, Key $key, string $signature): bool
    {
        $key = $this->handleKey($key, Key::OP_VERIFY);
        try {
            RsaKeyValidator::checkPublicParameters($key);
            RsaKeyValidator::checkLengthBounds($key);
            // A key below the bound the caller asked for is a key it declared it does not verify with, which is an
            // invalid signature rather than an error here too. The implicit default only warns.
            $this->checkKeyPolicy($key);
        } catch (InvalidArgumentException) {
            // A key too large to compute with, or one that does not satisfy RFC 8017, section 3.1, is key material no
            // verification can be performed with: the contract of Signature::verify() reports it as an invalid
            // signature, not as an error.
            return false;
        }
        // The key is loaded before use so that key material OpenSSL cannot decode yields false instead of an
        // E_WARNING raised from inside openssl_verify().
        $publicKey = openssl_pkey_get_public($key->toPublic()->asPem());
        if ($publicKey === false) {
            return false;
        }

        return openssl_verify($data, $signature, $publicKey, $this->getHashAlgorithm()) === 1;
    }

    abstract protected function getHashAlgorithm(): int;

    private function handleKey(Key $key, int $operation): RsaKey
    {
        $this->checkKeyRestrictions($key, $operation);

        return RsaKey::create($key->getData());
    }
}
