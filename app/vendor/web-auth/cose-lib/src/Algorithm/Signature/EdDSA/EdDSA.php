<?php

declare(strict_types=1);

namespace Cose\Algorithm\Signature\EdDSA;

use Cose\Algorithm\KeyRestrictionAware;
use Cose\Algorithm\KeyRestrictionEnforcement;
use Cose\Algorithm\Signature\Signature;
use Cose\Algorithms;
use Cose\Key\Key;
use Cose\Key\OkpKey;
use function extension_loaded;
use function hash_equals;
use InvalidArgumentException;
use RuntimeException;
use function sodium_crypto_sign_detached;
use function sodium_crypto_sign_publickey;
use function sodium_crypto_sign_secretkey;
use function sodium_crypto_sign_seed_keypair;
use function sodium_crypto_sign_verify_detached;
use function sodium_memzero;
use SodiumException;
use Throwable;

/**
 * @see \Cose\Tests\Algorithm\Signature\EdDSA\EdDSATest
 */
class EdDSA implements Signature, KeyRestrictionAware
{
    use KeyRestrictionEnforcement;

    public function __construct()
    {
        if (! self::isSupported()) {
            throw new RuntimeException(
                'The EdDSA algorithms require the Sodium extension, which is not loaded.'
            );
        }
    }

    /**
     * Ed25519 is computed with the sodium extension. It ships with PHP and is enabled by default, but a build can
     * leave it out; the algorithm is then unusable, and verify() would report every signature as invalid instead of
     * saying why, because it turns any error into a verification outcome. Availability is therefore settled once,
     * when the algorithm is instantiated.
     */
    public static function isSupported(): bool
    {
        return extension_loaded('sodium');
    }

    public function sign(string $data, Key $key): string
    {
        $key = $this->handleKey($key, Key::OP_SIGN);
        if (! $key->isPrivate()) {
            throw new InvalidArgumentException('The key is not private.');
        }
        if ($key->curveId() !== OkpKey::CURVE_ED25519) {
            throw new InvalidArgumentException('Unsupported curve');
        }

        // RFC 8032 section 5.1.5 defines the public key A as a function of the private seed, and section 5.1.6 puts
        // that very A into the challenge k = SHA-512(R || A || M). Sodium uses the public half of the secret key
        // verbatim, so handing it a caller-supplied "x" would let two signatures of the same message under two
        // different halves share their nonce R and disclose the private scalar. A is therefore always re-derived
        // from "d" here, and a stored "x" is only ever compared against it.
        $keyPair = sodium_crypto_sign_seed_keypair($key->d());
        $secret = sodium_crypto_sign_secretkey($keyPair);
        $public = sodium_crypto_sign_publickey($keyPair);
        sodium_memzero($keyPair);

        $x = $key->has(OkpKey::DATA_X) ? $key->x() : null;
        if ($x !== null && ! hash_equals($public, $x)) {
            sodium_memzero($secret);

            throw new InvalidArgumentException(
                'Invalid key: the public key "x" does not correspond to the private key "d".'
            );
        }

        try {
            return sodium_crypto_sign_detached($data, $secret);
        } finally {
            sodium_memzero($secret);
        }
    }

    public function verify(string $data, Key $key, string $signature): bool
    {
        $key = $this->handleKey($key, Key::OP_VERIFY);
        if ($key->curveId() !== OkpKey::CURVE_ED25519) {
            throw new InvalidArgumentException('Unsupported curve');
        }
        // Sodium reports a signature or a public key whose size is not the one Ed25519 defines with a
        // SodiumException; that is an invalid signature, not an error. Anything else — a missing extension above
        // all — is a platform fault and must not be reported as a verification outcome.
        try {
            return sodium_crypto_sign_verify_detached($signature, $data, $key->x());
        } catch (SodiumException) {
            return false;
        }
    }

    public static function identifier(): int
    {
        return Algorithms::COSE_ALGORITHM_EDDSA;
    }

    private function handleKey(Key $key, int $operation): OkpKey
    {
        $this->checkKeyRestrictions($key, $operation);

        try {
            return OkpKey::create($key->getData());
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            // A last resort: key material comes from the wire, and every rejection of it has to reach the caller as
            // the exception type this library documents, never as a TypeError or an Error.
            throw new InvalidArgumentException('Invalid OKP key', 0, $e);
        }
    }
}
