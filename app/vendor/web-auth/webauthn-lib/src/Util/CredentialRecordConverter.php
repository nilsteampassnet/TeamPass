<?php

declare(strict_types=1);

namespace Webauthn\Util;

use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialSource;

/**
 * Utility class to convert between PublicKeyCredentialSource and CredentialRecord.
 *
 * This class provides helper methods to facilitate migration from the deprecated
 * PublicKeyCredentialSource class to the new CredentialRecord class.
 */
final class CredentialRecordConverter
{
    /**
     * Converts a CredentialRecord (or subclass) to a pure CredentialRecord.
     *
     * Since PublicKeyCredentialSource extends CredentialRecord, this method
     * can accept either type. If the input is already a pure CredentialRecord,
     * it is returned as-is. Otherwise, a new CredentialRecord is created.
     *
     * @param CredentialRecord $source The source credential to convert
     * @return CredentialRecord The converted credential record
     */
    public static function toCredentialRecord(CredentialRecord $source): CredentialRecord
    {
        // Since PublicKeyCredentialSource now extends CredentialRecord,
        // any PublicKeyCredentialSource is already a CredentialRecord.
        // If it's already a pure CredentialRecord (not a subclass), return as-is.
        // Otherwise, create a new CredentialRecord to "strip" the subclass.
        if ($source::class === CredentialRecord::class) {
            return $source;
        }

        return CredentialRecord::create(
            $source->publicKeyCredentialId,
            $source->type,
            $source->transports,
            $source->attestationType,
            $source->trustPath,
            $source->aaguid,
            $source->credentialPublicKey,
            $source->userHandle,
            $source->counter,
            $source->otherUI,
            $source->backupEligible,
            $source->backupStatus,
            $source->uvInitialized,
        );
    }

    /**
     * Converts a CredentialRecord to a PublicKeyCredentialSource.
     *
     * This method creates a PublicKeyCredentialSource from a CredentialRecord.
     * Note that PublicKeyCredentialSource is deprecated and should only be used
     * for backward compatibility.
     *
     * @param CredentialRecord $record The credential record to convert
     * @return PublicKeyCredentialSource The converted credential source
     * @deprecated since 5.3, PublicKeyCredentialSource is deprecated. Use CredentialRecord directly.
     */
    public static function toPublicKeyCredentialSource(CredentialRecord $record): PublicKeyCredentialSource
    {
        // If already a PublicKeyCredentialSource, return as-is
        if ($record instanceof PublicKeyCredentialSource) {
            return $record;
        }

        return new PublicKeyCredentialSource(
            $record->publicKeyCredentialId,
            $record->type,
            $record->transports,
            $record->attestationType,
            $record->trustPath,
            $record->aaguid,
            $record->credentialPublicKey,
            $record->userHandle,
            $record->counter,
            $record->otherUI,
            $record->backupEligible,
            $record->backupStatus,
            $record->uvInitialized,
        );
    }

    /**
     * Converts an array of CredentialRecord (or subclass) instances to pure CredentialRecord instances.
     *
     * @param CredentialRecord[] $sources Array of credential records
     * @return CredentialRecord[] Array of credential records
     */
    public static function toCredentialRecords(array $sources): array
    {
        return array_map(self::toCredentialRecord(...), $sources);
    }

    /**
     * Converts an array of CredentialRecord instances to PublicKeyCredentialSource instances.
     *
     * @param CredentialRecord[] $records Array of credential records
     * @return PublicKeyCredentialSource[] Array of credential sources
     * @deprecated since 5.3, PublicKeyCredentialSource is deprecated. Use CredentialRecord directly.
     */
    public static function toPublicKeyCredentialSources(array $records): array
    {
        return array_map(self::toPublicKeyCredentialSource(...), $records);
    }
}
