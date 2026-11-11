<?php

declare(strict_types=1);

namespace Webauthn\CeremonyStep;

use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\CredentialRecord;
use Webauthn\Exception\AuthenticatorResponseVerificationException;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * The BE flag is set when the credential is created and MUST NOT change afterwards. An assertion whose BE flag differs
 * from the value recorded for the credential comes from a malfunctioning or cloned authenticator and is rejected.
 *
 * A credential record without a recorded value, as stored before the flag was tracked, has nothing to compare with
 * and is not checked. The registration ceremony is not concerned: it is the one that records the value.
 *
 * The step belongs after CheckSignature, unlike the order of the specification: the comparison is then made on
 * authenticator data signed by the credential, so a forged response fails as an invalid signature and only the holder
 * of the credential can trigger this rejection, which a Relying Party may treat as a sign of cloning.
 *
 * @see https://www.w3.org/TR/webauthn-3/#sctn-credential-backup
 * @see https://www.w3.org/TR/webauthn-3/#sctn-verifying-assertion
 */
final class CheckBackupEligibilityIsUnchanged implements CeremonyStep
{
    public function process(
        CredentialRecord $credentialRecord,
        AuthenticatorAssertionResponse|AuthenticatorAttestationResponse $authenticatorResponse,
        PublicKeyCredentialRequestOptions|PublicKeyCredentialCreationOptions $publicKeyCredentialOptions,
        ?string $userHandle,
        string $host
    ): void {
        if (! $authenticatorResponse instanceof AuthenticatorAssertionResponse || $credentialRecord->backupEligible === null) {
            return;
        }
        $authenticatorResponse->authenticatorData->isBackupEligible() === $credentialRecord->backupEligible || throw AuthenticatorResponseVerificationException::create(
            'The backup eligibility flag does not match the value recorded for the credential.'
        );
    }
}
