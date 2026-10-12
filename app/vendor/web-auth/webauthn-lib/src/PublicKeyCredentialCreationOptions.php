<?php

declare(strict_types=1);

namespace Webauthn;

use function in_array;
use InvalidArgumentException;
use Webauthn\AuthenticationExtensions\AuthenticationExtensions;
use Webauthn\Exception\InvalidDataException;

final class PublicKeyCredentialCreationOptions extends PublicKeyCredentialOptions
{
    public const ATTESTATION_CONVEYANCE_PREFERENCE_DEFAULT = null;

    public const ATTESTATION_CONVEYANCE_PREFERENCE_NONE = 'none';

    public const ATTESTATION_CONVEYANCE_PREFERENCE_INDIRECT = 'indirect';

    public const ATTESTATION_CONVEYANCE_PREFERENCE_DIRECT = 'direct';

    public const ATTESTATION_CONVEYANCE_PREFERENCE_ENTERPRISE = 'enterprise';

    public const ATTESTATION_CONVEYANCE_PREFERENCES = [
        self::ATTESTATION_CONVEYANCE_PREFERENCE_DEFAULT,
        self::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
        self::ATTESTATION_CONVEYANCE_PREFERENCE_INDIRECT,
        self::ATTESTATION_CONVEYANCE_PREFERENCE_DIRECT,
        self::ATTESTATION_CONVEYANCE_PREFERENCE_ENTERPRISE,
    ];

    public const MEDIATION_DEFAULT = 'default';

    public const MEDIATION_CONDITIONAL = 'conditional';

    public const MEDIATIONS = [self::MEDIATION_DEFAULT, self::MEDIATION_CONDITIONAL];

    /**
     * Server-side mediation hint. Mirrors the JS `mediation` option of `navigator.credentials.create()`
     * but is *not* sent to the browser — it controls how the framework validates the response.
     *
     * - `MEDIATION_DEFAULT` (default) — full ceremony validation, including User Presence (UP) bit.
     * - `MEDIATION_CONDITIONAL` — auto-register flow (e.g. SimpleWebAuthn `useAutoRegister: true`):
     *   the UP bit is allowed to be false because the user already authenticated through another
     *   factor (typically password). All other checks remain.
     *
     * @see https://github.com/w3c/webauthn/wiki/Explainer:-Conditional-Create
     */
    public null|string $mediation;

    /**
     * @param PublicKeyCredentialParameters[] $pubKeyCredParams
     * @param PublicKeyCredentialDescriptor[] $excludeCredentials
     * @param null|positive-int $timeout
     * @param string[] $hints
     */
    public function __construct(
        public readonly PublicKeyCredentialRpEntity $rp,
        public readonly PublicKeyCredentialUserEntity $user,
        string $challenge,
        public array $pubKeyCredParams = [],
        public null|AuthenticatorSelectionCriteria $authenticatorSelection = null,
        public null|string $attestation = null,
        public array $excludeCredentials = [],
        null|int $timeout = null,
        null|AuthenticationExtensions $extensions = null,
        array $hints = [],
        null|string $mediation = null,
    ) {
        foreach ($pubKeyCredParams as $pubKeyCredParam) {
            $pubKeyCredParam instanceof PublicKeyCredentialParameters || throw new InvalidArgumentException(
                'Invalid type for $pubKeyCredParams'
            );
        }
        foreach ($excludeCredentials as $excludeCredential) {
            $excludeCredential instanceof PublicKeyCredentialDescriptor || throw new InvalidArgumentException(
                'Invalid type for $excludeCredentials'
            );
        }
        in_array($attestation, self::ATTESTATION_CONVEYANCE_PREFERENCES, true) || throw InvalidDataException::create(
            $attestation,
            'Invalid attestation conveyance mode'
        );
        $mediation === null || in_array($mediation, self::MEDIATIONS, true) || throw InvalidDataException::create(
            $mediation,
            'Invalid mediation requirement'
        );

        $this->mediation = $mediation;

        parent::__construct($challenge, $timeout, $extensions, $hints);
    }

    /**
     * @param PublicKeyCredentialParameters[] $pubKeyCredParams
     * @param PublicKeyCredentialDescriptor[] $excludeCredentials
     * @param null|positive-int $timeout
     * @param string[] $hints
     */
    public static function create(
        PublicKeyCredentialRpEntity $rp,
        PublicKeyCredentialUserEntity $user,
        string $challenge,
        array $pubKeyCredParams = [],
        null|AuthenticatorSelectionCriteria $authenticatorSelection = null,
        null|string $attestation = null,
        array $excludeCredentials = [],
        null|int $timeout = null,
        null|AuthenticationExtensions $extensions = null,
        array $hints = [],
        null|string $mediation = null,
    ): self {
        return new self(
            $rp,
            $user,
            $challenge,
            $pubKeyCredParams,
            $authenticatorSelection,
            $attestation,
            $excludeCredentials,
            $timeout,
            $extensions,
            $hints,
            $mediation,
        );
    }
}
