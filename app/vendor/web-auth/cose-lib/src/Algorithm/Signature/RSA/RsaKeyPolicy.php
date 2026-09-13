<?php

declare(strict_types=1);

namespace Cose\Algorithm\Signature\RSA;

use Cose\Key\RsaKey;
use Cose\Key\RsaKeyValidator;
use const E_USER_WARNING;
use InvalidArgumentException;
use function sprintf;
use function trigger_error;

/**
 * The minimum modulus length every RSA algorithm of this library applies to the keys it is given.
 *
 * RFC 8230, section 6.1 - to which RFC 8812 defers for the WebAuthn registrations - states that "a key size of 2048
 * bits or larger MUST be used with these algorithms" and that "it is highly recommended that checks on the key length
 * be done before starting a cryptographic operation". That recommendation is addressed to the component performing
 * the operation, which is this library, so RsaKeyValidator::create() is applied by default rather than left to the
 * caller.
 *
 * Legacy authenticators holding 1024 bit keys exist, so the default bound cannot be enforced with an exception
 * without breaking them: it emits an E_USER_WARNING in 4.x and will throw as of v5.0.0, following the schedule RS1
 * already uses. A caller who has to accept a weaker key says so by handing the algorithm a validator of its own -
 * RsaKeyValidator::create(minimumModulusLength: 1024) - which both records the bound actually accepted and applies it
 * immediately, with an exception, instead of silencing the check altogether.
 *
 * Only the *minimum* modulus length goes through here. The public parameter constraints of RFC 8017, section 3.1 and
 * the upper bounds on the size of the key are not a policy and are applied unconditionally by the algorithms
 * themselves, before this check runs; nothing a validator rejects here has been left unchecked by them.
 *
 * @see https://www.rfc-editor.org/rfc/rfc8230#section-6.1
 *
 * @internal
 */
trait RsaKeyPolicy
{
    private RsaKeyValidator $keyValidator;

    /**
     * Whether the validator was chosen by the caller, in which case the bound it carries is enforced with an
     * exception, as opposed to the implicit default, which only warns until v5.0.0.
     */
    private bool $keyValidatorIsExplicit;

    private function initializeKeyValidator(?RsaKeyValidator $keyValidator): void
    {
        $this->keyValidatorIsExplicit = $keyValidator !== null;
        $this->keyValidator = $keyValidator ?? RsaKeyValidator::create();
    }

    /**
     * @throws InvalidArgumentException when the key is rejected by a validator the caller provided
     */
    private function checkKeyPolicy(RsaKey $key): void
    {
        if (! isset($this->keyValidator)) {
            // Both abstract classes gained their constructor in 4.8.0: an algorithm extending one of them from
            // outside this library and overriding it without calling the parent gets the default rather than an
            // error on an uninitialised property.
            $this->initializeKeyValidator(null);
        }

        try {
            $this->keyValidator->check($key);
        } catch (InvalidArgumentException $exception) {
            if ($this->keyValidatorIsExplicit) {
                throw $exception;
            }
            trigger_error(
                sprintf(RsaKeyValidator::WEAK_KEY_MESSAGE, $exception->getMessage()),
                E_USER_WARNING
            );
        }
    }
}
