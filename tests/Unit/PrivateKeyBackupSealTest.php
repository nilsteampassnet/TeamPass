<?php

declare(strict_types=1);

use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\Key;
use PHPUnit\Framework\TestCase;

// Real production logic (DB-free) behind encryptPrivateKeyBackup() / decryptPrivateKeyBackup().
require_once __DIR__ . '/../../app/sources/private_key_backup_logic.php';

/**
 * The transparent key recovery backup must not open from the database alone (GHSA-fv78-jwjv-pj25).
 *
 * Its AES key is derived from user_derivation_seed and public_key, both stored in the users row
 * next to the backup. The backup is therefore sealed with the instance key (SECUREFILE), which a
 * database dump does not contain.
 */
class PrivateKeyBackupSealTest extends TestCase
{
    /** A legacy backup: base64 of the AES ciphertext, as stored before the fix. */
    private const LEGACY_BACKUP = 'q3J+L0m9vYw2Xh8bT1sR/4uKcZ0eN5pW7aD6gF2iH9jE1kQ3oU8yV4xC5tB0nM=';

    private static function newInstanceKey(): string
    {
        return Key::createNewRandomKey()->saveToAsciiSafeString();
    }

    public function testSealedBackupOpensWithTheInstanceKey(): void
    {
        $instanceKey = self::newInstanceKey();
        $sealed = privateKeyBackupSeal(self::LEGACY_BACKUP, $instanceKey);

        self::assertTrue(privateKeyBackupIsSealed($sealed));
        self::assertStringStartsWith(PRIVATE_KEY_BACKUP_SEAL_PREFIX, $sealed);
        self::assertSame(self::LEGACY_BACKUP, privateKeyBackupUnseal($sealed, $instanceKey));
    }

    public function testSealedBackupDoesNotContainTheDerivableCiphertext(): void
    {
        // The inner ciphertext is what the database alone could open: it must not be readable.
        $sealed = privateKeyBackupSeal(self::LEGACY_BACKUP, self::newInstanceKey());

        self::assertStringNotContainsString(self::LEGACY_BACKUP, $sealed);
        self::assertStringNotContainsString(base64_decode(self::LEGACY_BACKUP), $sealed);
    }

    public function testSealedBackupDoesNotOpenWithAnotherInstanceKey(): void
    {
        $sealed = privateKeyBackupSeal(self::LEGACY_BACKUP, self::newInstanceKey());

        $this->expectException(WrongKeyOrModifiedCiphertextException::class);
        privateKeyBackupUnseal($sealed, self::newInstanceKey());
    }

    public function testAlteredSealedBackupIsRejected(): void
    {
        $instanceKey = self::newInstanceKey();
        $sealed = privateKeyBackupSeal(self::LEGACY_BACKUP, $instanceKey);
        $last = substr($sealed, -1);
        $altered = substr($sealed, 0, -1) . ($last === 'a' ? 'b' : 'a');

        $this->expectException(WrongKeyOrModifiedCiphertextException::class);
        privateKeyBackupUnseal($altered, $instanceKey);
    }

    public function testSealingIsIdempotentSoTheUpgradeCanBeReplayed(): void
    {
        $instanceKey = self::newInstanceKey();
        $sealed = privateKeyBackupSeal(self::LEGACY_BACKUP, $instanceKey);

        self::assertSame($sealed, privateKeyBackupSeal($sealed, $instanceKey));
    }

    public function testLegacyBackupIsReadAsIsUntilItIsSealed(): void
    {
        self::assertFalse(privateKeyBackupIsSealed(self::LEGACY_BACKUP));
        self::assertFalse(privateKeyBackupIsSealed(''));
        self::assertSame(self::LEGACY_BACKUP, privateKeyBackupUnseal(self::LEGACY_BACKUP, self::newInstanceKey()));
    }

    // -------------------------------------------------------------------
    // Sentinels on the production call sites
    // -------------------------------------------------------------------

    private static function source(string $relativePath): string
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        self::assertIsString($content, $relativePath . ' must be readable');

        return str_replace("\r\n", "\n", $content);
    }

    private static function functionSource(string $source, string $name): string
    {
        $start = strpos($source, "\nfunction " . $name . '(');
        self::assertNotFalse($start, $name . '() must exist.');
        $end = strpos($source, "\n}\n", $start);
        self::assertNotFalse($end);

        return substr($source, $start, $end - $start + 3);
    }

    public function testBackupHelpersSealAndUnseal(): void
    {
        $mainFunctions = self::source('app/sources/main.functions.php');

        self::assertStringContainsString(
            'privateKeyBackupSeal(',
            self::functionSource($mainFunctions, 'encryptPrivateKeyBackup')
        );
        self::assertStringContainsString(
            'privateKeyBackupUnseal(',
            self::functionSource($mainFunctions, 'decryptPrivateKeyBackup')
        );
        self::assertStringContainsString(
            '= encryptPrivateKeyBackup(',
            self::functionSource($mainFunctions, 'generateUserKeys')
        );
    }

    /**
     * A backup built from deriveBackupKey() directly would be stored unsealed again: only the two
     * helpers may derive the key, every writer and reader goes through them.
     */
    public function testBackupKeyIsOnlyDerivedInsideTheHelpers(): void
    {
        $root = dirname(__DIR__, 2);
        $mainFunctionsPath = $root . '/app/sources/main.functions.php';
        $offenders = [];

        foreach (['app', 'public'] as $directory) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS),
                    static function (SplFileInfo $current): bool {
                        return $current->getFilename() !== 'vendor';
                    }
                )
            );

            foreach ($iterator as $file) {
                if ($file->isFile() === false || $file->getExtension() !== 'php') {
                    continue;
                }

                $path = (string) $file->getRealPath();
                $content = str_replace("\r\n", "\n", (string) file_get_contents($path));
                if ($path === $mainFunctionsPath) {
                    foreach (['encryptPrivateKeyBackup', 'decryptPrivateKeyBackup'] as $helper) {
                        $content = str_replace(self::functionSource($content, $helper), '', $content);
                    }
                    $content = str_replace('function deriveBackupKey(', '', $content);
                }

                if (str_contains($content, 'deriveBackupKey(') === true) {
                    $offenders[] = str_replace($root . '/', '', $path);
                }
            }
        }

        self::assertSame(
            [],
            $offenders,
            'These files must use encryptPrivateKeyBackup() / decryptPrivateKeyBackup() instead of deriveBackupKey().'
        );
    }

    public function testUpgradeSealsTheExistingBackups(): void
    {
        $upgrade = self::source('public/install/upgrade_run_3.2.2.php');

        self::assertStringContainsString('privateKeyBackupSeal(', $upgrade);
        self::assertStringContainsString("PRIVATE_KEY_BACKUP_SEAL_PREFIX . '%'", $upgrade);
    }
}
