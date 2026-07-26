<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel test: the two LdapExtra copies must stay byte-identical.
 *
 * The classes exist in two locations (project rule — edit both):
 *   - app/vendor/teampassclasses/ldapextra/src/                (Composer / autoloaded)
 *   - app/includes/libraries/teampassclasses/ldapextra/src/
 *
 * Only the vendor copy is resolved by PSR-4, so editing the includes/libraries one alone
 * produces a change with no runtime effect at all — exactly the trap this test catches.
 */
class LdapExtraCopiesInSyncTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function ldapExtraFilesProvider(): array
    {
        return [
            'ActiveDirectoryExtra' => ['ActiveDirectoryExtra.php'],
            'OpenLdapExtra' => ['OpenLdapExtra.php'],
            'LdapExtra' => ['LdapExtra.php'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ldapExtraFilesProvider')]
    public function testLdapExtraCopiesAreByteIdentical(string $fileName): void
    {
        $root = dirname(__DIR__, 2);
        $vendor = $root . '/app/vendor/teampassclasses/ldapextra/src/' . $fileName;
        $includes = $root . '/app/includes/libraries/teampassclasses/ldapextra/src/' . $fileName;

        $this->assertFileExists($vendor, 'Composer copy of ' . $fileName . ' is missing');
        $this->assertFileExists($includes, 'includes/libraries copy of ' . $fileName . ' is missing');

        $this->assertSame(
            hash_file('sha256', $vendor),
            hash_file('sha256', $includes),
            'The two ' . $fileName . ' copies have diverged — edit both identically (see CLAUDE.md). '
            . 'Only the vendor copy is autoloaded.'
        );
    }
}
