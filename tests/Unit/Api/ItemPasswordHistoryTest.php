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

    /**
     * Offset of the first match, so the guards check statement order and not indentation.
     *
     * @param string $source  Source to scan
     * @param string $pattern PCRE pattern, whitespace-tolerant
     * @return int Byte offset of the match
     */
    private function offsetOf(string $source, string $pattern): int
    {
        $found = preg_match($pattern, $source, $matches, PREG_OFFSET_CAPTURE);
        self::assertSame(1, $found, "Pattern not found in ItemModel: $pattern");

        return (int) $matches[0][1];
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
        self::assertStringContainsString(
            'throw new UnexpectedValueException(self::PASSWORD_RECOVERY_ERROR)',
            $helper
        );
    }

    public function testPasswordRecoveryIsMemoizedForTheRequest(): void
    {
        $source = $this->itemModelSource();

        // The LAPR ownership guard and the password block both ask for the current
        // value on the same item; only the first one may pay for the RSA decryption.
        self::assertMatchesRegularExpression('#private \?array \$currentPasswordMemo#', $source);
        self::assertMatchesRegularExpression(
            '#\$this->currentPasswordMemo = null;\s*\n\s*try \{#',
            $source,
            'updateItem() must clear the memo before it runs'
        );
    }

    public function testOldPasswordIsPreparedBeforeMutationAndLoggedEncryptedAfterSharekeys(): void
    {
        $source = $this->itemModelSource();
        $passwordUpdate = strpos($source, '// Handle password update');
        self::assertNotFalse($passwordUpdate);
        $updatePath = substr($source, (int) $passwordUpdate);

        $prepareHistory = $this->offsetOf($updatePath, '#cryption\(\$currentPassword,\s*\'\',\s*\'encrypt\'\)#');
        $itemWrite = $this->offsetOf($updatePath, '#DB::update\(\s*prefixTable\(\'items\'\)#');
        $sharekeys = $this->offsetOf($updatePath, '#storeUsersShareKey\(\s*\'sharekeys_items\'#');
        $passwordAudit = $this->offsetOf($updatePath, '#\'at_pw\'#');

        self::assertLessThan($itemWrite, $prepareHistory);
        self::assertLessThan($passwordAudit, $sharekeys);
        self::assertStringContainsString('$encryptedPreviousPassword', $updatePath);
        self::assertMatchesRegularExpression('#if \(\$passwordChanged === true\)#', $updatePath);
        self::assertStringContainsString('(string) $encryptedPreviousPassword', $updatePath);
    }

    public function testGenericModificationEntryIsIndependentFromThePasswordEntry(): void
    {
        $source = $this->itemModelSource();
        $passwordUpdate = strpos($source, '// Handle password update');
        self::assertNotFalse($passwordUpdate);
        $updatePath = substr($source, (int) $passwordUpdate);

        // Changing the label and the password in one request must leave both traces,
        // so the generic entry may not be chained to the password one with an elseif.
        self::assertDoesNotMatchRegularExpression(
            '#\}\s*elseif \([^)]*\$hasGeneralUpdate#',
            $updatePath
        );
        self::assertMatchesRegularExpression(
            '#if \(is_array\(\$moveContext\) === false && \$hasNonPasswordUpdate === true\)#',
            $updatePath
        );
    }

    public function testSecretsAreNotIncludedInApiErrorLogs(): void
    {
        $source = $this->itemModelSource();

        self::assertDoesNotMatchRegularExpression('/error_log\([^;]*\$(?:currentPassword|newPassword|encryptedPreviousPassword)/s', $source);
    }
}
