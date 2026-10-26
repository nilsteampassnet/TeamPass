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

    /**
     * The TP key status names the first broken link of the chain, so each cause gets its own repair.
     */
    public function testTpUserKeyStatusNamesTheFirstBrokenLink(): void
    {
        self::assertSame('ok', tpUserKeyStatus(true, true, true, true));
        self::assertSame('account_missing', tpUserKeyStatus(false, false, false, false));
        self::assertSame('account_missing', tpUserKeyStatus(false, true, true, true));
        self::assertSame('password_unreadable', tpUserKeyStatus(true, false, true, false));
        self::assertSame('password_unreadable', tpUserKeyStatus(true, false, false, false));
        self::assertSame('private_key_missing', tpUserKeyStatus(true, true, false, false));
        self::assertSame('private_key_unreadable', tpUserKeyStatus(true, true, true, false));
    }

    /**
     * Every broken status is explained by a string that exists, and "ok" by none.
     */
    public function testEveryTpUserKeyStatusHasAMessage(): void
    {
        $english = (string) file_get_contents(__DIR__ . '/../../app/includes/language/english.php');

        self::assertSame('', tpUserKeyStatusMessageKey('ok'));
        foreach (['account_missing', 'password_unreadable', 'private_key_missing', 'private_key_unreadable', 'unexpected'] as $status) {
            $key = tpUserKeyStatusMessageKey($status);
            self::assertNotSame('', $key, $status);
            self::assertStringContainsString("'" . $key . "' =>", $english, $status);
        }
    }

    /**
     * A broken TP key blocks only the key generations that would cost the user their access.
     */
    public function testUserKeysGenerationIsBlockedOnlyWhenAccessWouldBeLost(): void
    {
        // Something to lose: refused
        self::assertTrue(userKeysGenerationBlockedByTpKey('password_unreadable', false, true));
        self::assertTrue(userKeysGenerationBlockedByTpKey('private_key_unreadable', false, true));
        // Usable key: never refused
        self::assertFalse(userKeysGenerationBlockedByTpKey('ok', false, true));
        // New account or keys already gone: nothing to lose
        self::assertFalse(userKeysGenerationBlockedByTpKey('password_unreadable', false, false));
        // Key pair kept: the task writes nothing over the existing sharekeys
        self::assertFalse(userKeysGenerationBlockedByTpKey('password_unreadable', true, true));
    }

    /**
     * The key generation must check the TP key before replacing the user's key pair, and every
     * batch that re-keys through the owner must stop instead of writing keys that open nothing.
     */
    public function testKeyGenerationNeverRunsOnABrokenTpKey(): void
    {
        $functions = (string) file_get_contents(__DIR__ . '/../../app/sources/main.functions.php');
        $start = strpos($functions, 'function handleUserKeys(');
        self::assertNotFalse($start);
        $body = substr($functions, $start, (int) strpos($functions, "\nfunction ", $start + 1) - $start);
        $guard = strpos($body, 'userKeysGenerationBlockedByTpKey(');
        self::assertNotFalse($guard);
        self::assertLessThan(strpos($body, 'generateUserKeys('), $guard);

        $trait = (string) file_get_contents(__DIR__ . '/../../app/scripts/traits/UserHandlerTrait.php');
        foreach (['20', '30', '40', '50', '60'] as $step) {
            $stepStart = strpos($trait, 'private function generateNewUserStep' . $step . '(');
            self::assertNotFalse($stepStart, $step);
            $stepBody = substr($trait, $stepStart, (int) strpos($trait, 'private function ', $stepStart + 1) - $stepStart);
            self::assertStringContainsString('$this->assertOwnerPrivateKeyOpens($ownerInfo);', $stepBody, 'step' . $step);
        }
    }

    /**
     * The rebuild discards the TP key pair: it must refuse while that pair still opens, and drop
     * the account's sharekeys in every table, or the old ones would pass for valid reference keys.
     */
    public function testTpKeyRebuildIsGuardedAndComplete(): void
    {
        $tools = (string) file_get_contents(__DIR__ . '/../../app/sources/tools.queries.php');
        $start = strpos($tools, "case 'restore_missing_sharekeys-rebuild_tp_keys':");
        self::assertNotFalse($start);
        $case = substr($tools, $start, (int) strpos($tools, "\n    case '", $start + 1) - $start);
        self::assertStringContainsString("if (\$tpKeyStatus === 'ok') {", $case);
        self::assertLessThan(strpos($case, 'rebuildTpUserKeyPair('), strpos($case, "if (\$tpKeyStatus === 'ok') {"));

        $functions = (string) file_get_contents(__DIR__ . '/../../app/sources/main.functions.php');
        $start = strpos($functions, 'function rebuildTpUserKeyPair(');
        self::assertNotFalse($start);
        $body = substr($functions, $start, (int) strpos($functions, "\nfunction ", $start + 1) - $start);
        foreach (['sharekeys_items', 'sharekeys_fields', 'sharekeys_files', 'sharekeys_logs', 'sharekeys_suggestions', 'sharekeys_webauthn'] as $table) {
            self::assertStringContainsString("'" . $table . "'", $body, $table);
        }
    }
}
