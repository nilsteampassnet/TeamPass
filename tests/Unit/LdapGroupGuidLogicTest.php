<?php

declare(strict_types=1);

use LdapRecord\Models\Attributes\Guid;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/scripts/ldap_group_guid_logic.php';

/**
 * Regression tests for the AD group objectGUID byte-order migration (upgrade_run_3.2.2.php).
 *
 * Until 3.2.2, ActiveDirectoryExtra::getADGroups() serialised the 16 objectGUID bytes in wire
 * order while getUserADGroups() used LdapRecord's Guid class (Windows mixed-endian). The admin
 * mapping stored one string, the login path resolved another, and no AD group could ever be
 * mapped to a role. Both sides now emit the canonical GUID; the rows already stored have to be
 * converted exactly once.
 */
class LdapGroupGuidLogicTest extends TestCase
{
    /**
     * Rebuild the legacy identifier exactly as the pre-3.2.2 getADGroups() did.
     *
     * @param string $binaryGuid Raw 16-byte objectGUID
     * @return string The byte-sequential GUID string stored in ldap_groups_roles
     */
    private function legacyGuidString(string $binaryGuid): string
    {
        return strtolower(vsprintf(
            '%02x%02x%02x%02x-%02x%02x-%02x%02x-%02x%02x-%02x%02x%02x%02x%02x%02x',
            array_values((array) unpack('C16', $binaryGuid))
        ));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function binaryGuidProvider(): array
    {
        return [
            'ordered bytes' => ['78563412ab90efcd1122334455667788'],
            'all zeroes but last' => ['00000000000000000000000000000001'],
            'high bytes' => ['ffeeddccbbaa99887766554433221100'],
            'realistic azure guid' => ['b2c3d4e5f60718293a4b5c6d7e8f9012'],
            'palindromic fields' => ['aabbccddeeffgghh'],
        ];
    }

    /**
     * The migration must land on exactly the value the new code reads at login, otherwise the
     * mapping stays broken after the upgrade.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('binaryGuidProvider')]
    public function testMigratedValueMatchesWhatLdapRecordResolves(string $hexOrRaw): void
    {
        $binaryGuid = strlen($hexOrRaw) === 32 && ctype_xdigit($hexOrRaw)
            ? (string) hex2bin($hexOrRaw)
            : $hexOrRaw;

        $legacyStored = $this->legacyGuidString($binaryGuid);
        $canonical = strtolower((new Guid($binaryGuid))->getValue());

        $this->assertTrue(
            ldapIsCanonicalGuidFormat($legacyStored),
            'The legacy value must be recognised as convertible'
        );
        $this->assertSame(
            $canonical,
            ldapLegacyGuidToCanonical($legacyStored),
            'Migrated identifier must equal the one getUserADGroups() resolves'
        );
    }

    /**
     * The two encodings genuinely differ: this is the bug the migration exists for.
     */
    public function testLegacyAndCanonicalEncodingsDiffer(): void
    {
        $binaryGuid = (string) hex2bin('78563412ab90efcd1122334455667788');

        $this->assertSame('78563412-ab90-efcd-1122-334455667788', $this->legacyGuidString($binaryGuid));
        $this->assertSame('12345678-90ab-cdef-1122-334455667788', strtolower((new Guid($binaryGuid))->getValue()));
    }

    /**
     * Only the first three fields are byte-reversed; the last two are already big-endian.
     */
    public function testOnlyTheFirstThreeFieldsAreReversed(): void
    {
        $this->assertSame(
            '12345678-90ab-cdef-1122-334455667788',
            ldapLegacyGuidToCanonical('78563412-ab90-efcd-1122-334455667788')
        );
    }

    /**
     * The conversion is its own inverse: running the migration twice restores the broken value.
     * This is precisely why upgrade_run_3.2.2.php is guarded by a one-shot marker and why
     * run.step5.php seeds that marker on fresh installs.
     */
    public function testConversionIsItsOwnInverse(): void
    {
        $legacyStored = '78563412-ab90-efcd-1122-334455667788';

        $this->assertSame(
            $legacyStored,
            ldapLegacyGuidToCanonical(ldapLegacyGuidToCanonical($legacyStored)),
            'Double conversion must return the original value — the marker guard is mandatory'
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonGuidValueProvider(): array
    {
        return [
            'invalid guid placeholder' => ['invalid_guid_5f4dcc3b5aa765d61d8327deb882cf99'],
            'missing placeholder' => ['missing_65b1a2c3d4e5f'],
            'posix gidNumber' => ['10042'],
            'group common name' => ['IT-Department'],
            'uppercase guid' => ['78563412-AB90-EFCD-1122-334455667788'],
            'truncated guid' => ['78563412-ab90-efcd-1122'],
            'empty value' => [''],
        ];
    }

    /**
     * Anything that is not a lowercase canonical GUID must be skipped by the migration.
     * OpenLDAP identifiers (gidNumber, entryUUID kept as a string) were never byte-swapped.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('nonGuidValueProvider')]
    public function testNonGuidValuesAreNotEligible(string $value): void
    {
        $this->assertFalse(
            ldapIsCanonicalGuidFormat($value),
            'Value "' . $value . '" must not be converted by the migration'
        );
    }

    /**
     * Defensive: a malformed value reaching the converter is returned untouched rather than
     * corrupted, so a stray row can never be turned into a different valid identifier.
     */
    public function testMalformedValueIsReturnedUnchanged(): void
    {
        $this->assertSame('not-a-guid', ldapLegacyGuidToCanonical('not-a-guid'));
        $this->assertSame('', ldapLegacyGuidToCanonical(''));
    }
}
