<?php

declare(strict_types=1);

namespace Cose\Algorithm\Signature\ECDSA;

use Cose\Algorithm\KeyRestrictionAware;
use Cose\Algorithm\KeyRestrictionEnforcement;
use Cose\Algorithm\Signature\OpenSslError;
use Cose\Algorithm\Signature\Signature;
use Cose\Key\Ec2Key;
use Cose\Key\Key;
use InvalidArgumentException;
use function openssl_pkey_get_private;
use function openssl_pkey_get_public;
use function openssl_sign;
use function openssl_verify;
use function strlen;
use Throwable;

/**
 * @see \Cose\Tests\Algorithm\Signature\ECDSA\ECDSATest
 */
abstract class ECDSA implements Signature, KeyRestrictionAware
{
    use KeyRestrictionEnforcement;

    public function sign(string $data, Key $key): string
    {
        $key = $this->handleKey($key, Key::OP_SIGN);
        if (! $key->isPrivate()) {
            throw new InvalidArgumentException('The key is not private.');
        }
        $privateKey = openssl_pkey_get_private($key->asPEM());
        if ($privateKey === false) {
            throw new InvalidArgumentException('Unable to load the EC private key');
        }
        OpenSslError::clear();
        // openssl_sign() reports failure with a boolean and never throws: an unusable key would otherwise leave
        // $signature null and raise a TypeError inside ECSignature::fromAsn1().
        if (! openssl_sign($data, $signature, $privateKey, $this->getHashAlgorithm())) {
            throw new InvalidArgumentException('Unable to sign the data: ' . OpenSslError::lastMessage());
        }

        return ECSignature::fromAsn1($signature, $this->getSignaturePartLength());
    }

    public function verify(string $data, Key $key, string $signature): bool
    {
        $key = $this->handleKey($key, Key::OP_VERIFY);
        $length = $this->getSignaturePartLength();
        // A signature that does not hold exactly the two coordinates of this curve is an invalid signature, not a
        // caller error: webauthn-lib hands the bytes of an assertion straight to verify().
        if (strlen($signature) !== $length) {
            return false;
        }

        try {
            $signature = ECSignature::toAsn1($signature, $length);
        } catch (InvalidArgumentException) {
            // A well-formed but invalid signature (e.g. R = 0) is a verification failure, not an error.
            return false;
        }
        // The key is loaded before use: openssl_verify() would raise an E_WARNING for key material OpenSSL cannot
        // coerce into a public key, e.g. a point that is not on the named curve.
        $publicKey = openssl_pkey_get_public($key->toPublic()->asPEM());
        if ($publicKey === false) {
            return false;
        }

        return openssl_verify($data, $signature, $publicKey, $this->getHashAlgorithm()) === 1;
    }

    abstract protected function getCurve(): int;

    abstract protected function getHashAlgorithm(): int;

    abstract protected function getSignaturePartLength(): int;

    private function handleKey(Key $key, int $operation): Ec2Key
    {
        $this->checkKeyRestrictions($key, $operation);
        try {
            $key = Ec2Key::create($key->getData());
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            // A last resort: key material comes from the wire, and every rejection of it has to reach the caller as
            // the exception type this library documents, never as a TypeError or an Error.
            throw new InvalidArgumentException('Invalid EC2 key', 0, $e);
        }
        // RFC 9053 section 7.1 lets a key name its curve instead of numbering it, so the two are compared through
        // the registry value rather than through whichever of the two forms the key happens to carry.
        if ($key->curveId() !== $this->getCurve()) {
            throw new InvalidArgumentException('This key cannot be used with this algorithm');
        }

        return $key;
    }
}
