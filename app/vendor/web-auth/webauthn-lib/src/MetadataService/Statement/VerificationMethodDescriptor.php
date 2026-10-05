<?php

declare(strict_types=1);

namespace Webauthn\MetadataService\Statement;

use Webauthn\Exception\MetadataStatementLoadingException;

class VerificationMethodDescriptor
{
    final public const USER_VERIFY_PRESENCE_INTERNAL = 'presence_internal';

    final public const USER_VERIFY_PRESENCE_INTERNAL_INT = 0x00000001;

    final public const USER_VERIFY_FINGERPRINT_INTERNAL = 'fingerprint_internal';

    final public const USER_VERIFY_FINGERPRINT_INTERNAL_INT = 0x00000002;

    final public const USER_VERIFY_PASSCODE_INTERNAL = 'passcode_internal';

    final public const USER_VERIFY_PASSCODE_INTERNAL_INT = 0x00000004;

    final public const USER_VERIFY_VOICEPRINT_INTERNAL = 'voiceprint_internal';

    final public const USER_VERIFY_VOICEPRINT_INTERNAL_INT = 0x00000008;

    final public const USER_VERIFY_FACEPRINT_INTERNAL = 'faceprint_internal';

    final public const USER_VERIFY_FACEPRINT_INTERNAL_INT = 0x00000010;

    final public const USER_VERIFY_LOCATION_INTERNAL = 'location_internal';

    final public const USER_VERIFY_LOCATION_INTERNAL_INT = 0x00000020;

    final public const USER_VERIFY_EYEPRINT_INTERNAL = 'eyeprint_internal';

    final public const USER_VERIFY_EYEPRINT_INTERNAL_INT = 0x00000040;

    final public const USER_VERIFY_PATTERN_INTERNAL = 'pattern_internal';

    final public const USER_VERIFY_PATTERN_INTERNAL_INT = 0x00000080;

    final public const USER_VERIFY_HANDPRINT_INTERNAL = 'handprint_internal';

    final public const USER_VERIFY_HANDPRINT_INTERNAL_INT = 0x00000100;

    final public const USER_VERIFY_PASSCODE_EXTERNAL = 'passcode_external';

    final public const USER_VERIFY_PASSCODE_EXTERNAL_INT = 0x00000800;

    final public const USER_VERIFY_PATTERN_EXTERNAL = 'pattern_external';

    final public const USER_VERIFY_PATTERN_EXTERNAL_INT = 0x00001000;

    final public const USER_VERIFY_NONE = 'none';

    final public const USER_VERIFY_NONE_INT = 0x00000200;

    final public const USER_VERIFY_ALL = 'all';

    final public const USER_VERIFY_ALL_INT = 0x00000400;

    final public const USER_VERIFICATION_METHODS = [
        self::USER_VERIFY_PRESENCE_INTERNAL,
        self::USER_VERIFY_FINGERPRINT_INTERNAL,
        self::USER_VERIFY_PASSCODE_INTERNAL,
        self::USER_VERIFY_VOICEPRINT_INTERNAL,
        self::USER_VERIFY_FACEPRINT_INTERNAL,
        self::USER_VERIFY_LOCATION_INTERNAL,
        self::USER_VERIFY_EYEPRINT_INTERNAL,
        self::USER_VERIFY_PATTERN_INTERNAL,
        self::USER_VERIFY_HANDPRINT_INTERNAL,
        self::USER_VERIFY_PASSCODE_EXTERNAL,
        self::USER_VERIFY_PATTERN_EXTERNAL,
        self::USER_VERIFY_NONE,
        self::USER_VERIFY_ALL,
    ];

    final public const USER_VERIFICATION_METHODS_INT = [
        self::USER_VERIFY_PRESENCE_INTERNAL_INT,
        self::USER_VERIFY_FINGERPRINT_INTERNAL_INT,
        self::USER_VERIFY_PASSCODE_INTERNAL_INT,
        self::USER_VERIFY_VOICEPRINT_INTERNAL_INT,
        self::USER_VERIFY_FACEPRINT_INTERNAL_INT,
        self::USER_VERIFY_LOCATION_INTERNAL_INT,
        self::USER_VERIFY_EYEPRINT_INTERNAL_INT,
        self::USER_VERIFY_PATTERN_INTERNAL_INT,
        self::USER_VERIFY_HANDPRINT_INTERNAL_INT,
        self::USER_VERIFY_PASSCODE_EXTERNAL_INT,
        self::USER_VERIFY_PATTERN_EXTERNAL_INT,
        self::USER_VERIFY_NONE_INT,
        self::USER_VERIFY_ALL_INT,
    ];

    public function __construct(
        public readonly string $userVerificationMethod,
        public readonly ?CodeAccuracyDescriptor $caDesc = null,
        public readonly ?BiometricAccuracyDescriptor $baDesc = null,
        public readonly ?PatternAccuracyDescriptor $paDesc = null
    ) {
        $userVerificationMethod >= 0 || throw MetadataStatementLoadingException::create(
            'The parameter "userVerificationMethod" is invalid'
        );
    }

    public static function create(
        string $userVerificationMethod,
        ?CodeAccuracyDescriptor $caDesc = null,
        ?BiometricAccuracyDescriptor $baDesc = null,
        ?PatternAccuracyDescriptor $paDesc = null
    ): self {
        return new self($userVerificationMethod, $caDesc, $baDesc, $paDesc);
    }

    public function userPresence(): bool
    {
        return $this->userVerificationMethod === self::USER_VERIFY_PRESENCE_INTERNAL;
    }

    public function fingerprint(): bool
    {
        return $this->userVerificationMethod === self::USER_VERIFY_FINGERPRINT_INTERNAL;
    }

    public function passcodeInternal(): bool
    {
        return $this->userVerificationMethod === self::USER_VERIFY_PASSCODE_INTERNAL;
    }

    public function voicePrint(): bool
    {
        return $this->userVerificationMethod === self::USER_VERIFY_VOICEPRINT_INTERNAL;
    }

    public function facePrint(): bool
    {
        return $this->userVerificationMethod === self::USER_VERIFY_FACEPRINT_INTERNAL;
    }

    public function location(): bool
    {
        return $this->userVerificationMethod === self::USER_VERIFY_LOCATION_INTERNAL;
    }

    public function eyePrint(): bool
    {
        return $this->userVerificationMethod === self::USER_VERIFY_EYEPRINT_INTERNAL;
    }

    public function patternInternal(): bool
    {
        return $this->userVerificationMethod === self::USER_VERIFY_PATTERN_INTERNAL;
    }

    public function handprint(): bool
    {
        return $this->userVerificationMethod === self::USER_VERIFY_HANDPRINT_INTERNAL;
    }

    public function passcodeExternal(): bool
    {
        return $this->userVerificationMethod === self::USER_VERIFY_PASSCODE_EXTERNAL;
    }

    public function patternExternal(): bool
    {
        return $this->userVerificationMethod === self::USER_VERIFY_PATTERN_EXTERNAL;
    }

    public function none(): bool
    {
        return $this->userVerificationMethod === self::USER_VERIFY_NONE;
    }

    public function all(): bool
    {
        return $this->userVerificationMethod === self::USER_VERIFY_ALL;
    }
}
