<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/sharekeys_repair_logic.php';

/**
 * Behavioural tests of the "Restore missing sharekeys" decision logic, and sentinels on the code
 * paths around it that must not regress.
 */
class SharekeysRepairLogicTest extends TestCase
{
    /**
     * Nothing to check against: any key is accepted.
     */
    public function testEmptyCiphertextAcceptsAnyKey(): void
    {
        self::assertTrue(sharekeyRepairDecryptionProvesKey(false, '', '', ''));
    }

    /**
     * A failed decryption never proves a key, whatever the format.
     */
    public function testFailedDecryptionNeverProves(): void
    {
        self::assertFalse(sharekeyRepairDecryptionProvesKey(false, '', 'cipher', ''));
        self::assertFalse(sharekeyRepairDecryptionProvesKey(false, '', 'cipher', 'meta'));
    }

    /**
     * AES v2 is authenticated: a successful decryption is a proof, even of binary content.
     */
    public function testAuthenticatedFormatProvesOnSuccess(): void
    {
        self::assertTrue(sharekeyRepairDecryptionProvesKey(true, base64_encode("\x8d\xe6\xa7"), 'cipher', 'meta'));
    }

    /**
     * Legacy AES-CBC: readable text proves the key, binary bytes do not (a wrong key passes the
     * padding check about once in 256 tries and yields random bytes).
     */
    public function testLegacyFormatNeedsReadablePlaintext(): void
    {
        self::assertTrue(sharekeyRepairDecryptionProvesKey(true, base64_encode('S3cret-é!'), 'cipher', ''));
        self::assertTrue(sharekeyRepairDecryptionProvesKey(true, base64_encode(''), 'cipher', ''));
        self::assertFalse(sharekeyRepairDecryptionProvesKey(true, base64_encode("\x8d\xe6\xa7\x76\xea"), 'cipher', ''));
        self::assertFalse(sharekeyRepairDecryptionProvesKey(true, '***not base64***', 'cipher', ''));
    }

    /**
     * A current TP key that opens the object is kept, whatever the source key says.
     */
    public function testWorkingReferenceKeyIsKept(): void
    {
        self::assertSame('keep', sharekeyRepairReferenceAction(true, true, true, false, false, false));
        self::assertSame('keep', sharekeyRepairReferenceAction(true, true, true, true, true, false));
    }

    /**
     * A missing, legacy or undecryptable TP key is written from a source key that opens the object.
     */
    public function testMissingOrUndecryptableReferenceKeyIsCreated(): void
    {
        // missing, empty or legacy v1
        self::assertSame('create', sharekeyRepairReferenceAction(false, false, false, true, true, false));
        // the TP key pair changed: the key does not decrypt at all
        self::assertSame('create', sharekeyRepairReferenceAction(true, false, false, true, false, false));
        // opening is enough to create, as the tool always did
        self::assertSame('create', sharekeyRepairReferenceAction(false, false, false, true, false, false));
        self::assertSame('source_unusable', sharekeyRepairReferenceAction(false, false, false, false, false, false));
    }

    /**
     * A stale TP key is replaced only with a proven, different source key.
     */
    public function testStaleReferenceKeyNeedsAProvenDifferentSourceKey(): void
    {
        self::assertSame('replace', sharekeyRepairReferenceAction(true, true, false, true, true, false));
        // the source holds the same key: it comes from the same distribution, nothing proves which side is right
        self::assertSame('source_unusable', sharekeyRepairReferenceAction(true, true, false, true, true, true));
        // corrupted content decrypts to garbage with every key: never a reason to drop the other users' keys
        self::assertSame('source_unusable', sharekeyRepairReferenceAction(true, true, false, true, false, false));
        self::assertSame('source_unusable', sharekeyRepairReferenceAction(true, true, false, false, false, false));
    }

    /**
     * The tool that rebuilt the TP keys from a reference user sent the TP account password and the
     * reference user's private key to the browser. Its replacement must keep both on the server.
     */
    public function testNoSecretIsReturnedToTheBrowser(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/tools.queries.php');

        self::assertStringNotContainsString('tp_user_pwd', $source);
        self::assertStringNotContainsString('selected_user_privateKey', $source);
        self::assertStringNotContainsString('perform_fix_items_master_keys', $source);
    }

    /**
     * The background task must check the TP object key before distributing it.
     */
    public function testBackgroundRepairChecksTheReferenceKey(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/scripts/traits/SharekeysRepairTrait.php');

        self::assertStringContainsString('restoreSharekeysCheckObjectKey(', $source);
        self::assertStringContainsString("'invalid_reference'", $source);
    }

    /**
     * The Health page counts orphans in every sharekeys table: the maintenance scan must clean them all.
     */
    public function testOrphanCleanupCoversEverySharekeysTable(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/scripts/task_maintenance_clean_orphan_objects.php');

        foreach (['sharekeys_items', 'sharekeys_files', 'sharekeys_fields', 'sharekeys_logs', 'sharekeys_suggestions'] as $table) {
            self::assertStringContainsString("'" . $table . "'", $source, $table);
        }
        self::assertStringContainsString('k.object_id = l.increment_id', $source);
        self::assertStringContainsString('k.object_id = s.id', $source);
    }

    /**
     * A blanked sharekey holds no key to migrate: the Health counts must ignore it, like the migration.
     */
    public function testHealthVersionCountsIgnoreBlankedKeys(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/utilities.queries.php');

        self::assertMatchesRegularExpression(
            '/WHERE encryption_version = \' \. \(int\) \$targetVersion \. \' AND share_key != ""/',
            $source
        );
    }
}
