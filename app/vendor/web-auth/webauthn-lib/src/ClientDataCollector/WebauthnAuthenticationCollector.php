<?php

declare(strict_types=1);

namespace Webauthn\ClientDataCollector;

use function sprintf;
use Webauthn\AuthenticatorResponse;
use Webauthn\CollectedClientData;
use Webauthn\Exception\AuthenticatorResponseVerificationException;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialOptions;

final class WebauthnAuthenticationCollector implements ClientDataCollector
{
    public function supportedTypes(): array
    {
        return ['webauthn.get', 'webauthn.create'];
    }

    /**
     * The type is the one of the ceremony the options were created for: "webauthn.create" for a registration and
     * "webauthn.get" for an authentication (Webauthn Level 3, sections 7.1 and 7.2).
     */
    public function verifyCollectedClientData(
        CollectedClientData $collectedClientData,
        PublicKeyCredentialOptions $publicKeyCredentialOptions,
        AuthenticatorResponse $authenticatorResponse,
        string $host
    ): void {
        $expectedType = $publicKeyCredentialOptions instanceof PublicKeyCredentialCreationOptions
            ? 'webauthn.create'
            : 'webauthn.get';
        $collectedClientData->type === $expectedType || throw AuthenticatorResponseVerificationException::create(
            sprintf('The client data type is not "%s".', $expectedType)
        );
    }
}
