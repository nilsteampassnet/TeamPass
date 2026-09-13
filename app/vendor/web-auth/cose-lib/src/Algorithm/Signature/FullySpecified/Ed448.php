<?php

declare(strict_types=1);

namespace Cose\Algorithm\Signature\FullySpecified;

use Cose\Algorithm\KeyRestrictionAware;
use Cose\Algorithm\KeyRestrictionEnforcement;
use Cose\Algorithm\Signature\OpenSslError;
use Cose\Algorithm\Signature\Signature;
use Cose\Key\Key;
use Cose\Key\OkpKey;
use InvalidArgumentException;
use function openssl_pkey_get_private;
use function openssl_pkey_get_public;
use function openssl_sign;
use function openssl_verify;
use const PHP_VERSION_ID;
use RuntimeException;
use Throwable;

/**
 * EdDSA using the Ed448 parameter set of RFC 8032, section 5.2.
 *
 * Unlike Ed25519, Ed448 is not covered by the sodium extension; it is computed through OpenSSL, which PHP only wires
 * up for Edwards curves as of PHP 8.4. Call `isSupported()` before use when the platform is not known in advance.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9864.html#section-2.2
 */
final class Ed448 implements Signature, KeyRestrictionAware
{
    use KeyRestrictionEnforcement;

    public const ID = -53;

    /**
     * EdDSA is a one-shot signature scheme: the message is not hashed beforehand, which OpenSSL expects to be
     * expressed by an empty digest algorithm.
     */
    private const NO_DIGEST = 0;

    public static function create(): self
    {
        return new self();
    }

    public static function identifier(): int
    {
        return self::ID;
    }

    /**
     * Ed448 requires PHP 8.4 or later: earlier versions reject the digest-less signature OpenSSL needs for Edwards
     * curves.
     */
    public static function isSupported(): bool
    {
        return PHP_VERSION_ID >= 80400;
    }

    public function sign(string $data, Key $key): string
    {
        $key = $this->handleKey($key, Key::OP_SIGN);
        if (! $key->isPrivate()) {
            throw new InvalidArgumentException('The key is not private.');
        }

        $privateKey = openssl_pkey_get_private($key->asPEM());
        if ($privateKey === false) {
            throw new InvalidArgumentException('Unable to load the Ed448 private key');
        }

        OpenSslError::clear();
        if (! openssl_sign($data, $signature, $privateKey, self::NO_DIGEST)) {
            throw new InvalidArgumentException('Unable to sign the data: ' . OpenSslError::lastMessage());
        }

        return $signature;
    }

    public function verify(string $data, Key $key, string $signature): bool
    {
        $key = $this->handleKey($key, Key::OP_VERIFY);

        // RFC 8032 section 5.2.7: a public key that cannot be decoded as a point makes the signature invalid; it is
        // a verification outcome, not an error.
        $publicKey = openssl_pkey_get_public($key->toPublic()->asPEM());
        if ($publicKey === false) {
            return false;
        }

        return openssl_verify($data, $signature, $publicKey, self::NO_DIGEST) === 1;
    }

    private function handleKey(Key $key, int $operation): OkpKey
    {
        if (! self::isSupported()) {
            throw new RuntimeException(
                'The Ed448 algorithm requires PHP 8.4 or later, as earlier versions cannot sign or verify with Edwards curves through OpenSSL.'
            );
        }

        $this->checkKeyRestrictions($key, $operation);
        try {
            $key = OkpKey::create($key->getData());
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            // A last resort: key material comes from the wire, and every rejection of it has to reach the caller as
            // the exception type this library documents, never as a TypeError or an Error.
            throw new InvalidArgumentException('Invalid OKP key', 0, $e);
        }
        if ($key->curveId() !== OkpKey::CURVE_ED448) {
            throw new InvalidArgumentException('This key cannot be used with this algorithm');
        }

        return $key;
    }
}
