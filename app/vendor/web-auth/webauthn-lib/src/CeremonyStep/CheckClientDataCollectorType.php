<?php

declare(strict_types=1);

namespace Webauthn\CeremonyStep;

use function trigger_deprecation;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\ClientDataCollector\ClientDataCollectorManager;
use Webauthn\ClientDataCollector\WebauthnAuthenticationCollector;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialSource;

final readonly class CheckClientDataCollectorType implements CeremonyStep
{
    private ClientDataCollectorManager $clientDataCollectorManager;

    public function __construct(null|ClientDataCollectorManager $clientDataCollectorManager = null)
    {
        $this->clientDataCollectorManager = $clientDataCollectorManager ?? new ClientDataCollectorManager([
            new WebauthnAuthenticationCollector(),
        ]);
    }

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
        $this->clientDataCollectorManager->collect(
            $authenticatorResponse->clientDataJSON,
            $publicKeyCredentialOptions,
            $authenticatorResponse,
            $host
        );
    }
}
