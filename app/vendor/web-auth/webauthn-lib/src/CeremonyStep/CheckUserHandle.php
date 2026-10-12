<?php

declare(strict_types=1);

namespace Webauthn\CeremonyStep;

use function trigger_deprecation;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\CredentialRecord;
use Webauthn\Exception\InvalidUserHandleException;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialSource;

final class CheckUserHandle implements CeremonyStep
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
        if (! $authenticatorResponse instanceof AuthenticatorAssertionResponse) {
            return;
        }
        $credentialUserHandle = $credentialRecord->userHandle;
        $responseUserHandle = $authenticatorResponse->userHandle;
        if ($userHandle !== null) { // If the user was identified before the authentication ceremony was initiated,
            hash_equals($credentialUserHandle, $userHandle) || throw InvalidUserHandleException::create();
            if ($responseUserHandle !== null && $responseUserHandle !== '') {
                hash_equals($credentialUserHandle, $responseUserHandle) || throw InvalidUserHandleException::create();
            }
        } else {
            ($responseUserHandle !== null && $responseUserHandle !== '' && hash_equals(
                $credentialUserHandle,
                $responseUserHandle
            ))
                || throw InvalidUserHandleException::create();
        }
    }
}
