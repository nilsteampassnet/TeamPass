<?php

declare(strict_types=1);

namespace Webauthn\MetadataService\Service;

use function count;
use function is_string;
use Webauthn\Exception\MetadataStatementLoadingException;
use Webauthn\MetadataService\Statement\BiometricStatusReport;
use Webauthn\MetadataService\Statement\MetadataStatement;
use Webauthn\MetadataService\Statement\StatusReport;

class MetadataBLOBPayloadEntry
{
    /**
     * @param StatusReport[] $statusReports
     * @param BiometricStatusReport[] $biometricStatusReports
     * @param string[] $attestationCertificateKeyIdentifiers
     */
    public function __construct(
        public readonly string $timeOfLastStatusChange,
        public array $statusReports,
        public readonly ?string $aaid = null,
        public readonly ?string $aaguid = null,
        public array $attestationCertificateKeyIdentifiers = [],
        public readonly ?MetadataStatement $metadataStatement = null,
        public readonly ?string $rogueListURL = null,
        public readonly ?string $rogueListHash = null,
        public array $biometricStatusReports = []
    ) {
        if ($aaid !== null && $aaguid !== null) {
            throw MetadataStatementLoadingException::create('Authenticators cannot support both AAID and AAGUID');
        }
        if ($aaid === null && $aaguid === null && count($attestationCertificateKeyIdentifiers) === 0) {
            throw MetadataStatementLoadingException::create(
                'If neither AAID nor AAGUID are set, the attestation certificate identifier list shall not be empty'
            );
        }
        foreach ($attestationCertificateKeyIdentifiers as $attestationCertificateKeyIdentifier) {
            is_string($attestationCertificateKeyIdentifier) || throw MetadataStatementLoadingException::create(
                'Invalid attestation certificate identifier. Shall be a list of strings'
            );
            preg_match(
                '/^[0-9a-f]+$/',
                $attestationCertificateKeyIdentifier
            ) === 1 || throw MetadataStatementLoadingException::create(
                'Invalid attestation certificate identifier. Shall be a list of strings'
            );
        }
    }
}
