<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassClasses\LdapExtra\LdapExtra;

require_once __DIR__ . '/../../app/includes/libraries/teampassclasses/ldapextra/src/LdapExtra.php';

/**
 * Regression tests for the "User Distinguished Name" LDAP setting (ldap_user_dn_attribute).
 *
 * The installer seeds the setting with an empty string. identify.php read it with
 * `?? 'distinguishedname'`, which never replaces an empty string: the AD group lookup found no
 * user DN, gave up silently, and no AD group was ever mapped to a Teampass role at login.
 */
class LdapUserDnAttributeTest extends TestCase
{
    /**
     * @return array<string, array{0: array, 1: string}>
     */
    public static function settingsProvider(): array
    {
        return [
            'setting absent' => [[], 'distinguishedname'],
            'installer default (empty)' => [['ldap_user_dn_attribute' => ''], 'distinguishedname'],
            'whitespace only' => [['ldap_user_dn_attribute' => '  '], 'distinguishedname'],
            'null' => [['ldap_user_dn_attribute' => null], 'distinguishedname'],
            'documented AD value' => [['ldap_user_dn_attribute' => 'distinguishedname'], 'distinguishedname'],
            'camelCase AD value' => [['ldap_user_dn_attribute' => 'distinguishedName'], 'distinguishedname'],
            'OpenLDAP value' => [['ldap_user_dn_attribute' => 'dn'], 'dn'],
            'surrounding spaces' => [['ldap_user_dn_attribute' => ' DN '], 'dn'],
        ];
    }

    /**
     * @dataProvider settingsProvider
     */
    public function testResolvesAnEntryKey(array $settings, string $expected): void
    {
        $this->assertSame($expected, LdapExtra::getUserDnAttribute($settings));
    }

    public function testIdentifyNeverReadsTheRawSetting(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/identify.php');

        $this->assertStringNotContainsString(
            "\$SETTINGS['ldap_user_dn_attribute']",
            $source,
            'identify.php must resolve the DN attribute through LdapExtra::getUserDnAttribute()'
        );
    }

    public function testAdGroupLookupFallsBackToTheEntryDn(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/identify.php');

        $this->assertStringContainsString(
            "\$userDN = (string) (\$userADInfos[\$dnAttribute][0] ?? \$userADInfos['dn'] ?? '');",
            $source,
            'getUserADGroups() must fall back to the entry DN when the configured attribute is missing'
        );
    }
}
