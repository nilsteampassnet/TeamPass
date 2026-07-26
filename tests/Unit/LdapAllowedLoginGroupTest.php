<?php

declare(strict_types=1);

use LdapRecord\Connection;
use PHPUnit\Framework\TestCase;
use TeampassClasses\LdapExtra\ActiveDirectoryExtra;
use TeampassClasses\LdapExtra\OpenLdapExtra;

/**
 * Regression tests for "Restrict login to LDAP group (DN)" (ldap_allowed_login_group_dn).
 *
 * Pins the customer scenario the feature was built for: the required group lives OUTSIDE the
 * users base DN and is a groupOfUniqueNames, so membership is carried by uniqueMember.
 *
 *   group : cn=xa_passman,ou=group,ou=rgy_res,o=desy,c=de   (objectClass groupOfUniqueNames)
 *   users : ou=people,ou=rgy,o=desy,c=de
 *
 * Two properties must hold whatever happens to the AD group code:
 *   - the group is read with scope=base on its own DN, so being outside the users base DN is
 *     irrelevant;
 *   - uniqueMember is honoured, alongside member (groupOfNames) and memberUid (posixGroup).
 */
class LdapAllowedLoginGroupTest extends TestCase
{
    private const GROUP_DN = 'cn=xa_passman,ou=group,ou=rgy_res,o=desy,c=de';
    private const USER_DN = 'uid=jdoe,ou=people,ou=rgy,o=desy,c=de';

    protected function setUp(): void
    {
        if (function_exists('ldap_escape') === false) {
            $this->markTestSkipped('The ldap extension is not loaded.');
        }
    }

    /**
     * Connection pointing at no server: any query fails immediately.
     */
    private function unreachableConnection(): Connection
    {
        return new Connection([]);
    }

    /**
     * Invoke the private membership matcher on a simulated LDAP group entry.
     *
     * @param array $groupEntry Group entry as ext-ldap returns it, 'count' keys included
     * @param string $userDn Distinguished name of the authenticating user
     * @param string $userUid Login name of the user
     * @return bool
     */
    private function isMember(array $groupEntry, string $userDn, string $userUid = ''): bool
    {
        $method = new ReflectionMethod(OpenLdapExtra::class, 'isUserMemberOfGroup');
        $method->setAccessible(true);

        return (bool) $method->invoke(new OpenLdapExtra(), $groupEntry, $userDn, $userUid);
    }

    /**
     * The customer's group entry, shaped exactly as ext-ldap returns it.
     *
     * @param array<int, string> $uniqueMembers
     * @return array
     */
    private function groupOfUniqueNamesEntry(array $uniqueMembers): array
    {
        return [
            'objectclass' => ['groupOfUniqueNames', 'top', 'count' => 2],
            'cn' => ['xa_passman', 'count' => 1],
            'uniquemember' => array_merge($uniqueMembers, ['count' => count($uniqueMembers)]),
            'count' => 3,
            'dn' => self::GROUP_DN,
        ];
    }

    public function testUniqueMemberGrantsAccess(): void
    {
        $this->assertTrue(
            $this->isMember(
                $this->groupOfUniqueNamesEntry([
                    'uid=asmith,ou=people,ou=rgy,o=desy,c=de',
                    self::USER_DN,
                ]),
                self::USER_DN,
                'jdoe'
            ),
            'A uniqueMember of the allowed group must be granted access'
        );
    }

    public function testNonMemberIsDenied(): void
    {
        $this->assertFalse(
            $this->isMember(
                $this->groupOfUniqueNamesEntry(['uid=asmith,ou=people,ou=rgy,o=desy,c=de']),
                self::USER_DN,
                'jdoe'
            ),
            'A user absent from uniqueMember must be denied'
        );
    }

    /**
     * DN comparison is case-insensitive: directories do not normalise attribute-type case.
     */
    public function testUniqueMemberMatchIsCaseInsensitive(): void
    {
        $this->assertTrue(
            $this->isMember(
                $this->groupOfUniqueNamesEntry(['UID=JDoe,OU=People,OU=Rgy,O=Desy,C=DE']),
                self::USER_DN,
                'jdoe'
            )
        );
    }

    /**
     * The 'count' key ext-ldap adds must never be compared as a member value.
     */
    public function testCountKeyIsNeverTreatedAsAMember(): void
    {
        $this->assertFalse(
            $this->isMember($this->groupOfUniqueNamesEntry([]), self::USER_DN, 'jdoe'),
            'An empty group must not match through its count key'
        );
    }

    /**
     * The other two schemas stay supported alongside groupOfUniqueNames.
     */
    public function testMemberAndMemberUidSchemasStaySupported(): void
    {
        $groupOfNames = [
            'objectclass' => ['groupOfNames', 'top', 'count' => 2],
            'member' => [self::USER_DN, 'count' => 1],
            'count' => 2,
        ];
        $posixGroup = [
            'objectclass' => ['posixGroup', 'top', 'count' => 2],
            'memberuid' => ['jdoe', 'count' => 1],
            'count' => 2,
        ];

        $this->assertTrue($this->isMember($groupOfNames, self::USER_DN, 'jdoe'), 'groupOfNames');
        $this->assertTrue($this->isMember($posixGroup, self::USER_DN, 'jdoe'), 'posixGroup');
    }

    /**
     * memberUid must not match when no uid is available, otherwise an empty login would let
     * anybody through a posixGroup.
     */
    public function testEmptyUidDoesNotMatchPosixGroup(): void
    {
        $posixGroup = [
            'objectclass' => ['posixGroup', 'top', 'count' => 2],
            'memberuid' => ['', 'count' => 1],
            'count' => 2,
        ];

        $this->assertFalse($this->isMember($posixGroup, self::USER_DN, ''));
    }

    /**
     * An empty setting means no restriction, on both handlers, without querying anything.
     */
    public function testEmptyGroupDnMeansNoRestrictionOnBothHandlers(): void
    {
        $this->assertTrue(
            (new OpenLdapExtra())->isUserInAllowedGroup('', self::USER_DN, 'jdoe', $this->unreachableConnection())
        );
        $this->assertTrue(
            (new ActiveDirectoryExtra())->isUserInAllowedGroup('', self::USER_DN, 'jdoe', $this->unreachableConnection())
        );
    }

    /**
     * A group that cannot be read denies access on both handlers — never fail open.
     */
    public function testUnreadableGroupDeniesAccessOnBothHandlers(): void
    {
        $this->assertFalse(
            (new OpenLdapExtra())->isUserInAllowedGroup(self::GROUP_DN, self::USER_DN, 'jdoe', $this->unreachableConnection())
        );
        $this->assertFalse(
            (new ActiveDirectoryExtra())->isUserInAllowedGroup(self::GROUP_DN, self::USER_DN, 'jdoe', $this->unreachableConnection())
        );
    }

    /**
     * Both handlers must read the group with scope=base on its own DN, which is what makes a
     * group outside the users base DN reachable at all.
     */
    public function testBothHandlersReadTheGroupWithBaseScope(): void
    {
        foreach ([OpenLdapExtra::class, ActiveDirectoryExtra::class] as $className) {
            $source = (string) file_get_contents((string) (new ReflectionClass($className))->getFileName());

            $this->assertStringContainsString(
                '->setDn($groupDn)',
                $source,
                $className . ' must read the allowed group on its own DN'
            );
            $this->assertStringContainsString(
                '->read()',
                $source,
                $className . ' must use a scope=base read so the group can sit outside the users base DN'
            );
        }
    }

    /**
     * On the Active Directory handler the nested-group probe must stay AFTER the direct
     * member / uniquemember / memberuid checks. Running it first would make the whole method
     * return false on any directory that rejects LDAP_MATCHING_RULE_IN_CHAIN, silently
     * breaking the login restriction for groupOfUniqueNames directories.
     */
    public function testNestedProbeRunsAfterDirectMembershipChecks(): void
    {
        $source = (string) file_get_contents(
            (string) (new ReflectionClass(ActiveDirectoryExtra::class))->getFileName()
        );

        $uniqueMemberCheckPosition = strpos($source, "isset(\$groupEntry['uniquemember'])");
        $nestedProbePosition = strpos($source, '$this->isNestedMemberOfGroup(');

        $this->assertIsInt($uniqueMemberCheckPosition, 'The uniquemember check must still exist');
        $this->assertIsInt($nestedProbePosition, 'The nested membership probe must still exist');
        $this->assertGreaterThan(
            $uniqueMemberCheckPosition,
            $nestedProbePosition,
            'The nested probe must run after the direct membership checks'
        );
    }

    /**
     * The nested probe must own its try/catch, so a directory rejecting the extended matching
     * rule falls back to the direct checks instead of failing the whole membership test.
     */
    public function testNestedProbeIsIsolatedFromTheDirectChecks(): void
    {
        $method = new ReflectionMethod(ActiveDirectoryExtra::class, 'isNestedMemberOfGroup');
        $source = (string) file_get_contents((string) $method->getDeclaringClass()->getFileName());
        $lines = explode("\n", $source);
        $body = implode("\n", array_slice(
            $lines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));

        $this->assertStringContainsString('try {', $body);
        $this->assertStringContainsString('catch (\Throwable', $body);
        $this->assertStringContainsString('return false;', $body);
    }
}
