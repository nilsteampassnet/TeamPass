<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Wiring guards for encrypted password history on API updates.
 */
class ItemPasswordHistoryTest extends TestCase
{
    private function itemModelSource(): string
    {
        $source = file_get_contents(__DIR__ . '/../../../app/api/Model/ItemModel.php');
        self::assertIsString($source);

        return str_replace("\r\n", "\n", $source);
    }

    public function testRevisionPreconditionRunsBeforePasswordRecovery(): void
    {
        $source = $this->itemModelSource();
        $precondition = strpos($source, '// Optimistic concurrency.');
        $passwordUpdate = strpos($source, '// Handle password update');

        self::assertNotFalse($precondition);
        self::assertNotFalse($passwordUpdate);
        self::assertLessThan($passwordUpdate, $precondition);
    }

    public function testPasswordRecoveryIsMigrationAwareAndFailsClosed(): void
    {
        $source = $this->itemModelSource();
        $start = strpos($source, 'private function getItemPasswordForUpdate(');
        $end = strpos($source, 'private function getFolderSettings(', (int) $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $helper = substr($source, (int) $start, (int) $end - (int) $start);

        self::assertStringContainsString('SELECT share_key, increment_id', $helper);
        self::assertStringContainsString('decryptUserObjectKeyWithMigration(', $helper);
        self::assertStringContainsString("'sharekeys_items'", $helper);
        self::assertStringContainsString("throw new UnexpectedValueException('The current item password cannot be decrypted.')", $helper);
    }

    public function testOldPasswordIsPreparedBeforeMutationAndLoggedEncryptedAfterSharekeys(): void
    {
        $source = $this->itemModelSource();
        $passwordUpdate = strpos($source, '// Handle password update');
        self::assertNotFalse($passwordUpdate);
        $updatePath = substr($source, (int) $passwordUpdate);

        $prepareHistory = strpos($updatePath, "cryption(\$currentPassword, '', 'encrypt')");
        $itemWrite = strpos($updatePath, "DB::update(\n                    prefixTable('items')");
        $sharekeys = strpos($updatePath, "storeUsersShareKey(\n                    'sharekeys_items'");
        $passwordAudit = strpos($updatePath, "'at_pw'");

        self::assertNotFalse($prepareHistory);
        self::assertNotFalse($itemWrite);
        self::assertNotFalse($sharekeys);
        self::assertNotFalse($passwordAudit);
        self::assertLessThan($itemWrite, $prepareHistory);
        self::assertLessThan($passwordAudit, $sharekeys);
        self::assertStringContainsString('$encryptedPreviousPassword', $updatePath);
    }

    public function testSecretsAreNotIncludedInApiErrorLogs(): void
    {
        $source = $this->itemModelSource();

        self::assertDoesNotMatchRegularExpression('/error_log\([^;]*\$(?:currentPassword|newPassword|encryptedPreviousPassword)/s', $source);
    }
}
