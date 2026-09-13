<?php

declare(strict_types=1);

namespace Webauthn\MetadataService\Statement;

class MetadataStatement
{
    final public const KEY_PROTECTION_SOFTWARE = 'software';

    final public const KEY_PROTECTION_SOFTWARE_INT = 0x0001;

    final public const KEY_PROTECTION_HARDWARE = 'hardware';

    final public const KEY_PROTECTION_HARDWARE_INT = 0x0002;

    final public const KEY_PROTECTION_TEE = 'tee';

    final public const KEY_PROTECTION_TEE_INT = 0x0004;

    final public const KEY_PROTECTION_SECURE_ELEMENT = 'secure_element';

    final public const KEY_PROTECTION_SECURE_ELEMENT_INT = 0x0008;

    final public const KEY_PROTECTION_REMOTE_HANDLE = 'remote_handle';

    final public const KEY_PROTECTION_REMOTE_HANDLE_INT = 0x0010;

    final public const KEY_PROTECTION_TYPES = [
        self::KEY_PROTECTION_SOFTWARE,
        self::KEY_PROTECTION_HARDWARE,
        self::KEY_PROTECTION_TEE,
        self::KEY_PROTECTION_SECURE_ELEMENT,
        self::KEY_PROTECTION_REMOTE_HANDLE,
    ];

    final public const KEY_PROTECTION_TYPES_INT = [
        self::KEY_PROTECTION_SOFTWARE_INT,
        self::KEY_PROTECTION_HARDWARE_INT,
        self::KEY_PROTECTION_TEE_INT,
        self::KEY_PROTECTION_SECURE_ELEMENT_INT,
        self::KEY_PROTECTION_REMOTE_HANDLE_INT,
    ];

    final public const MATCHER_PROTECTION_SOFTWARE = 'software';

    final public const MATCHER_PROTECTION_SOFTWARE_INT = 0x0001;

    final public const MATCHER_PROTECTION_TEE = 'tee';

    final public const MATCHER_PROTECTION_TEE_INT = 0x0002;

    final public const MATCHER_PROTECTION_ON_CHIP = 'on_chip';

    final public const MATCHER_PROTECTION_ON_CHIP_INT = 0x0004;

    final public const MATCHER_PROTECTION_TYPES = [
        self::MATCHER_PROTECTION_SOFTWARE,
        self::MATCHER_PROTECTION_TEE,
        self::MATCHER_PROTECTION_ON_CHIP,
    ];

    final public const MATCHER_PROTECTION_TYPES_INT = [
        self::MATCHER_PROTECTION_SOFTWARE_INT,
        self::MATCHER_PROTECTION_TEE_INT,
        self::MATCHER_PROTECTION_ON_CHIP_INT,
    ];

    final public const ATTACHMENT_HINT_INTERNAL = 'internal';

    final public const ATTACHMENT_HINT_EXTERNAL = 'external';

    final public const ATTACHMENT_HINT_WIRED = 'wired';

    final public const ATTACHMENT_HINT_WIRELESS = 'wireless';

    final public const ATTACHMENT_HINT_NFC = 'nfc';

    final public const ATTACHMENT_HINT_BLUETOOTH = 'bluetooth';

    final public const ATTACHMENT_HINT_NETWORK = 'network';

    final public const ATTACHMENT_HINT_READY = 'ready';

    final public const ATTACHMENT_HINT_WIFI_DIRECT = 'wifi_direct';

    final public const TRANSACTION_CONFIRMATION_DISPLAY_ANY = 'any';

    final public const TRANSACTION_CONFIRMATION_DISPLAY_PRIVILEGED_SOFTWARE = 'privileged_software';

    final public const TRANSACTION_CONFIRMATION_DISPLAY_TEE = 'tee';

    final public const TRANSACTION_CONFIRMATION_DISPLAY_HARDWARE = 'hardware';

    final public const TRANSACTION_CONFIRMATION_DISPLAY_REMOTE = 'remote';

    final public const ALG_SIGN_SECP256R1_ECDSA_SHA256_RAW = 'secp256r1_ecdsa_sha256_raw';

    final public const ALG_SIGN_SECP256R1_ECDSA_SHA256_DER = 'secp256r1_ecdsa_sha256_der';

    final public const ALG_SIGN_RSASSA_PSS_SHA256_RAW = 'rsassa_pss_sha256_raw';

    final public const ALG_SIGN_RSASSA_PSS_SHA256_DER = 'rsassa_pss_sha256_der';

    final public const ALG_SIGN_SECP256K1_ECDSA_SHA256_RAW = 'secp256k1_ecdsa_sha256_raw';

    final public const ALG_SIGN_SECP256K1_ECDSA_SHA256_DER = 'secp256k1_ecdsa_sha256_der';

    final public const ALG_SIGN_SM2_SM3_RAW = 'sm2_sm3_raw';

    final public const ALG_SIGN_RSA_EMSA_PKCS1_SHA256_RAW = 'rsa_emsa_pkcs1_sha256_raw';

    final public const ALG_SIGN_RSA_EMSA_PKCS1_SHA256_DER = 'rsa_emsa_pkcs1_sha256_der';

    final public const ALG_SIGN_RSASSA_PSS_SHA384_RAW = 'rsassa_pss_sha384_raw';

    final public const ALG_SIGN_RSASSA_PSS_SHA512_RAW = 'rsassa_pss_sha256_raw';

    final public const ALG_SIGN_RSASSA_PKCSV15_SHA256_RAW = 'rsassa_pkcsv15_sha256_raw';

    final public const ALG_SIGN_RSASSA_PKCSV15_SHA384_RAW = 'rsassa_pkcsv15_sha384_raw';

    final public const ALG_SIGN_RSASSA_PKCSV15_SHA512_RAW = 'rsassa_pkcsv15_sha512_raw';

    final public const ALG_SIGN_RSASSA_PKCSV15_SHA1_RAW = 'rsassa_pkcsv15_sha1_raw';

    final public const ALG_SIGN_SECP384R1_ECDSA_SHA384_RAW = 'secp384r1_ecdsa_sha384_raw';

    final public const ALG_SIGN_SECP521R1_ECDSA_SHA512_RAW = 'secp512r1_ecdsa_sha256_raw';

    final public const ALG_SIGN_ED25519_EDDSA_SHA256_RAW = 'ed25519_eddsa_sha512_raw';

    final public const ALG_KEY_ECC_X962_RAW = 'ecc_x962_raw';

    final public const ALG_KEY_ECC_X962_DER = 'ecc_x962_der';

    final public const ALG_KEY_RSA_2048_RAW = 'rsa_2048_raw';

    final public const ALG_KEY_RSA_2048_DER = 'rsa_2048_der';

    final public const ALG_KEY_COSE = 'cose';

    final public const ATTESTATION_BASIC_FULL = 'basic_full';

    final public const ATTESTATION_BASIC_SURROGATE = 'basic_surrogate';

    final public const ATTESTATION_ATTCA = 'attca';

    final public const ATTESTATION_ANONCA = 'anonca';

    public readonly AuthenticatorGetInfo $authenticatorGetInfo;

    /**
     * @param Version[] $upv
     * @param string[] $authenticationAlgorithms
     * @param string[] $publicKeyAlgAndEncodings
     * @param string[] $attestationTypes
     * @param VerificationMethodANDCombinations[] $userVerificationDetails
     * @param string[] $matcherProtection
     * @param string[] $tcDisplay
     * @param string[] $attestationRootCertificates
     * @param string[] $attestationCertificateKeyIdentifiers
     * @param string[] $keyProtection
     * @param string[] $attachmentHint
     * @param ExtensionDescriptor[] $supportedExtensions
     * @param DisplayPNGCharacteristicsDescriptor[] $tcDisplayPNGCharacteristics
     */
    public function __construct(
        public readonly string $description,
        public readonly int $authenticatorVersion,
        public readonly string $protocolFamily,
        public readonly int $schema,
        public readonly array $upv,
        public readonly array $authenticationAlgorithms,
        public readonly array $publicKeyAlgAndEncodings,
        public readonly array $attestationTypes,
        public readonly array $userVerificationDetails,
        public readonly array $matcherProtection,
        public readonly array $tcDisplay,
        public readonly array $attestationRootCertificates,
        public readonly ?AlternativeDescriptions $alternativeDescriptions = null,
        public ?string $legalHeader = null,
        public ?string $aaid = null,
        public ?string $aaguid = null,
        public array $attestationCertificateKeyIdentifiers = [],
        public array $keyProtection = [],
        public ?bool $isKeyRestricted = null,
        public ?bool $isFreshUserVerificationRequired = null,
        public ?int $cryptoStrength = null,
        public array $attachmentHint = [],
        public ?string $tcDisplayContentType = null,
        public array $tcDisplayPNGCharacteristics = [],
        public ?string $icon = null,
        public array $supportedExtensions = [],
        ?AuthenticatorGetInfo $authenticatorGetInfo = null,
    ) {
        $this->authenticatorGetInfo = $authenticatorGetInfo ?? AuthenticatorGetInfo::create($attestationTypes);
    }

    /**
     * @param Version[] $upv
     * @param string[] $authenticationAlgorithms
     * @param string[] $publicKeyAlgAndEncodings
     * @param string[] $attestationTypes
     * @param VerificationMethodANDCombinations[] $userVerificationDetails
     * @param string[] $matcherProtection
     * @param string[] $tcDisplay
     * @param string[] $attestationRootCertificates
     * @param string[] $attestationCertificateKeyIdentifiers
     * @param string[] $keyProtection
     * @param string[] $attachmentHint
     * @param ExtensionDescriptor[] $supportedExtensions
     * @param DisplayPNGCharacteristicsDescriptor[] $tcDisplayPNGCharacteristics
     * @param array<string, string> $alternativeDescriptions
     */
    public static function create(
        string $description,
        int $authenticatorVersion,
        string $protocolFamily,
        int $schema,
        array $upv,
        array $authenticationAlgorithms,
        array $publicKeyAlgAndEncodings,
        array $attestationTypes,
        array $userVerificationDetails,
        array $matcherProtection,
        array $tcDisplay,
        array $attestationRootCertificates,
        array $alternativeDescriptions = [],
        ?string $legalHeader = null,
        ?string $aaid = null,
        ?string $aaguid = null,
        array $attestationCertificateKeyIdentifiers = [],
        array $keyProtection = [],
        ?bool $isKeyRestricted = null,
        ?bool $isFreshUserVerificationRequired = null,
        ?int $cryptoStrength = null,
        array $attachmentHint = [],
        ?string $tcDisplayContentType = null,
        array $tcDisplayPNGCharacteristics = [],
        ?string $icon = null,
        array $supportedExtensions = [],
        ?AuthenticatorGetInfo $authenticatorGetInfo = null,
    ): self {
        return new self(
            $description,
            $authenticatorVersion,
            $protocolFamily,
            $schema,
            $upv,
            $authenticationAlgorithms,
            $publicKeyAlgAndEncodings,
            $attestationTypes,
            $userVerificationDetails,
            $matcherProtection,
            $tcDisplay,
            $attestationRootCertificates,
            AlternativeDescriptions::create($alternativeDescriptions),
            $legalHeader,
            $aaid,
            $aaguid,
            $attestationCertificateKeyIdentifiers,
            $keyProtection,
            $isKeyRestricted,
            $isFreshUserVerificationRequired,
            $cryptoStrength,
            $attachmentHint,
            $tcDisplayContentType,
            $tcDisplayPNGCharacteristics,
            $icon,
            $supportedExtensions,
            $authenticatorGetInfo,
        );
    }
}
