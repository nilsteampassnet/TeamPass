<?php

declare(strict_types=1);

namespace Webauthn\CeremonyStep;

use function count;
use function trigger_deprecation;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\CredentialRecord;
use Webauthn\Exception\AuthenticatorResponseVerificationException;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialSource;

final class CheckAllowedCredentialList implements CeremonyStep
{
    public function process(
        CredentialRecord $credentialRecord,
        AuthenticatorAssertionResponse|AuthenticatorAttestationResponse $authenticatorResponse,
        PublicKeyCredentialRequestOptions|PublicKeyCredentialCreationOptions $publicKeyCredentialOptions,
        ?string $userHandle,
        string $host
    ): void {
        if ($credentialRecord instanceof PublicKeyCredentialSource) {
            trigger_deprecation(
                'web-auth/webauthn-lib',
                '5.3',
                'Passing a PublicKeyCredentialSource to "%s::process()" is deprecated, pass a CredentialRecord instead.',
                self::class
            );
        }
        if (! $publicKeyCredentialOptions instanceof PublicKeyCredentialRequestOptions) {
            return;
        }
        if (count($publicKeyCredentialOptions->allowCredentials) === 0) {
            return;
        }

        foreach ($publicKeyCredentialOptions->allowCredentials as $allowedCredential) {
            if (hash_equals($allowedCredential->id, $credentialRecord->publicKeyCredentialId)) {
                return;
            }
        }
        throw AuthenticatorResponseVerificationException::create('The credential ID is not allowed.');
    }
}
