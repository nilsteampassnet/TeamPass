<?php

declare(strict_types=1);

use LdapRecord\Connection;
use PHPUnit\Framework\TestCase;
use TeampassClasses\LdapExtra\ActiveDirectoryExtra;

/**
 * Regression tests for the AD group lookup contract.
 *
 * Two defects are guarded here:
 *
 *  1. getADGroups() and getUserADGroups() must convert objectGUID identically. From 3.1.7.2 to
 *     3.2.1.x only the second one used LdapRecord's Guid class, so the identifiers stored by
 *     the admin mapping never matched the ones resolved at login and no AD group could be
 *     mapped to a role.
 *  2. A failed membership lookup must be reported as an error. Returning an empty group list
 *     with error=false made identify.php call removeUserRolesBySource() and silently strip
 *     every AD-derived role of the user who was logging in.
 */
class ActiveDirectoryExtraGroupsTest extends TestCase
{
    protected function setUp(): void
    {
        if (function_exists('ldap_escape') === false) {
            $this->markTestSkipped('The ldap extension is not loaded.');
        }
    }

    /**
     * Build a connection that points at no server: every query fails immediately, which is
     * exactly the "lookup could not be performed" situation under test.
     */
    private function unreachableConnection(): Connection
    {
        return new Connection([]);
    }

    /**
     * Read the autoloaded copy of the class — the one PSR-4 actually resolves.
     */
    private function classSource(): string
    {
        $reflection = new ReflectionClass(ActiveDirectoryExtra::class);

        return (string) file_get_contents((string) $reflection->getFileName());
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function emptyUserDnProvider(): array
    {
        return [
            'null dn' => [null],
            'empty dn' => [''],
            'blank dn' => ['   '],
        ];
    }

    /**
     * No DN means membership cannot be resolved. It must not be reported as "no group",
     * otherwise the caller removes every AD role.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('emptyUserDnProvider')]
    public function testEmptyUserDnIsReportedAsAnError(?string $userDn): void
    {
        $result = (new ActiveDirectoryExtra())->getUserADGroups(
            $userDn,
            $this->unreachableConnection(),
            []
        );

        $this->assertTrue($result['error'], 'An unresolvable DN must be reported as an error');
        $this->assertSame([], $result['userGroups']);
        $this->assertNotSame('', $result['message']);
    }

    /**
     * A directory that cannot be queried must not look like a user belonging to no group.
     */
    public function testUnreachableDirectoryIsReportedAsAnError(): void
    {
        $result = (new ActiveDirectoryExtra())->getUserADGroups(
            'cn=jdoe,ou=users,dc=example,dc=com',
            $this->unreachableConnection(),
            []
        );

        $this->assertTrue($result['error'], 'A failed lookup must be reported as an error');
        $this->assertSame([], $result['userGroups']);
    }

    /**
     * The three keys of the contract are always present, whatever the outcome.
     */
    public function testReturnShapeIsStable(): void
    {
        $result = (new ActiveDirectoryExtra())->getUserADGroups(
            null,
            $this->unreachableConnection(),
            []
        );

        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('userGroups', $result);
        $this->assertIsBool($result['error']);
        $this->assertIsString($result['message']);
        $this->assertIsArray($result['userGroups']);
    }

    /**
     * An empty group DN means "no restriction" and must never query the directory.
     */
    public function testEmptyAllowedGroupDnGrantsAccessWithoutQuerying(): void
    {
        $this->assertTrue(
            (new ActiveDirectoryExtra())->isUserInAllowedGroup(
                '',
                'cn=jdoe,ou=users,dc=example,dc=com',
                'jdoe',
                $this->unreachableConnection()
            )
        );
    }

    /**
     * A group that cannot be read denies access — it must never fail open.
     */
    public function testUnreadableAllowedGroupDeniesAccess(): void
    {
        $this->assertFalse(
            (new ActiveDirectoryExtra())->isUserInAllowedGroup(
                'cn=teampass-users,ou=groups,dc=example,dc=com',
                'cn=jdoe,ou=users,dc=example,dc=com',
                'jdoe',
                $this->unreachableConnection()
            )
        );
    }

    /**
     * The byte-sequential conversion must be gone from every GUID path of the class. It is the
     * exact shape of the 3.1.7.2 regression: one of the two call sites left behind.
     */
    public function testLegacyByteSequentialGuidConversionIsGone(): void
    {
        $this->assertStringNotContainsString(
            '%02x%02x%02x%02x-',
            $this->classSource(),
            'getADGroups()/getUserADGroups() must not rebuild objectGUID by hand — '
            . 'the wire byte order does not match what Active Directory displays'
        );
    }

    /**
     * Both GUID paths must go through LdapRecord's Guid class, so the identifier stored by the
     * admin mapping is the one resolved at login.
     */
    public function testBothGuidPathsUseTheSameConverter(): void
    {
        $this->assertSame(
            2,
            substr_count($this->classSource(), 'Models\\Attributes\\Guid('),
            'getADGroups() and getUserADGroups() must both convert objectGUID with LdapRecord'
        );
    }

    /**
     * Both methods must default to the same attribute, or an install that left the setting
     * empty maps identifiers read from two different attributes.
     */
    public function testNoGidNumberDefaultRemainsInTheActiveDirectoryClass(): void
    {
        $this->assertStringNotContainsString(
            "'gidnumber'",
            $this->classSource(),
            'Active Directory has no gidNumber attribute: the default must be objectguid '
            . 'in getADGroups() as well as in getUserADGroups()'
        );
    }
}
