<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static guards for the "empty value" vs "decryption failed" boundary (#5342).
 *
 * doDataDecryption() returns an empty string in both cases. Every caller that
 * turns that emptiness into a verdict - the item card, which flags the field as
 * undecryptable, and update_item, which refuses to clear it - must read the
 * status instead, or a perfectly readable empty field is reported as lost and
 * can never be cleared again.
 */
class CustomFieldDecryptionStatusTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $path = __DIR__ . '/../../' . ltrim($relativePath, '/');
        self::assertFileExists($path);
        $content = file_get_contents($path);
        self::assertIsString($content);

        return $content;
    }

    public function testDoDataDecryptionDelegatesToTheStatusAwareVariant(): void
    {
        $mainFunctions = $this->source('app/sources/main.functions.php');

        self::assertMatchesRegularExpression(
            '/function doDataDecryption\(string \$data, string \$key, string \$meta = \'\'\): string\s*\{\s*return doDataDecryptionWithStatus\(\$data, \$key, \$meta\)\[\'string\'\];/s',
            $mainFunctions,
            'The two entry points must share one implementation so they can never diverge.'
        );

        self::assertMatchesRegularExpression(
            '/function doDataDecryptionWithStatus\(string \$data, string \$key, string \$meta = \'\'\): array/',
            $mainFunctions
        );
    }

    public function testStatusAwareVariantReportsFailureOnlyOnAnException(): void
    {
        $mainFunctions = $this->source('app/sources/main.functions.php');

        self::assertMatchesRegularExpression(
            '/function doDataDecryptionWithStatus\(.*?return \[\'string\' => base64_encode\(\(string\) \$decrypted\), \'success\' => true\];.*?catch \(Exception \$e\).*?return \[\'string\' => \'\', \'success\' => false\];/s',
            $mainFunctions,
            'A decryption that returned is a success, whatever the length of the plaintext.'
        );
    }

    public function testItemCardDoesNotFlagAnEmptyCustomFieldAsUndecryptable(): void
    {
        $itemsQueries = $this->source('app/sources/items.queries.php');

        self::assertMatchesRegularExpression(
            '/\$decryptedField = doDataDecryptionWithStatus\(.*?\);\s*if \(\$decryptedField\[\'success\'\] === false\) \{/s',
            $itemsQueries,
            'The card must branch on the decryption status, not on the plaintext being empty.'
        );
    }

    public function testUpdateItemPreservesOnlyFieldsThatTrulyFailedToDecrypt(): void
    {
        $itemsQueries = $this->source('app/sources/items.queries.php');

        self::assertMatchesRegularExpression(
            '/\$fieldIsUnreadable = empty\(\$fieldObjectKey\) === true\s*\|\| doDataDecryptionWithStatus\(.*?\)\[\'success\'\] === false;/s',
            $itemsQueries,
            'Clearing a field whose stored value is a readable empty string stays allowed.'
        );
    }

    public function testFieldRepairScriptUnlocksTheInternalAccountKey(): void
    {
        $script = $this->source('app/scripts/repair_unencrypted_fields.php');

        self::assertMatchesRegularExpression(
            '/\$tpPasswordClear = cryption\(.*?\$tpPrivateKey = decryptPrivateKey\(/s',
            $script,
            'The stored private key is password-wrapped: handing the raw column to '
            . 'decryptUserObjectKey() reports every row as unrecoverable.'
        );
    }

    public function testFieldRepairScriptNeverAdvisesDeletingRows(): void
    {
        $script = $this->source('app/scripts/repair_unencrypted_fields.php');

        self::assertStringNotContainsString(
            'DELETE ci FROM',
            $script,
            'A row the script cannot open is not a lost value - it is a missing sharekey.'
        );
        self::assertStringContainsString(
            'Tools > Restore missing sharekeys',
            $script,
            'The script must name the tool that actually rebuilds the missing keys.'
        );
    }

    public function testApiCustomFieldReadTrustsTheStoredRowNotTheCategoryFlag(): void
    {
        $itemModel = $this->source('app/api/Model/ItemModel.php');

        self::assertMatchesRegularExpression(
            '/\$isEncrypted = \$row\[\'encryption_type\'\] !== \'not_set\';/',
            $itemModel,
            'Trusting encrypted_data returns the raw ciphertext as the field value once '
            . 'the flag is turned off on a field that already holds encrypted values.'
        );
    }
}
