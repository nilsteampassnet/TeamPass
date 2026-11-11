<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/oauth2_self_register_logic.php';

/**
 * Decisions of the OAuth2 self-registration (setting oauth_self_register_groups). The wiring
 * into the sign-in is covered by Oauth2SelfRegisterWiringTest, the Entra group retrieval by
 * OAuth2EntraGroupsTest.
 */
final class Oauth2SelfRegisterLogicTest extends TestCase
{
    /**
     * @return array<int, array{id: string, displayName: string|null}>
     */
    private function userGroups(string ...$names): array
    {
        return array_map(
            static fn (string $name): array => ['id' => 'id-' . $name, 'displayName' => $name],
            $names
        );
    }

    public function testTheSettingIsSplitTrimmedAndDeduplicated(): void
    {
        $this->assertSame(
            ['TeamPass-Users', 'Helpdesk'],
            oauth2ParseAllowedGroups(' TeamPass-Users , ,Helpdesk,teampass-users,')
        );
    }

    public function testAnEmptySettingAllowsNoGroup(): void
    {
        $this->assertSame([], oauth2ParseAllowedGroups(''));
        $this->assertSame([], oauth2ParseAllowedGroups(' , ,'));
    }

    public function testTheSettingIsReadAsItWasTypedNotAsItWasStored(): void
    {
        // save_option_change stores the value through FILTER_SANITIZE_FULL_SPECIAL_CHARS
        $stored = (string) filter_var("Équipe R&D, O'Neil team", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->assertStringContainsString('&Eacute;', $stored);

        $this->assertSame(['Équipe R&D', "O'Neil team"], oauth2ParseAllowedGroups($stored));
    }

    public function testNobodyMayRegisterWhenNoGroupIsAllowed(): void
    {
        $this->assertFalse(oauth2UserMayRegister([], $this->userGroups('TeamPass-Users')));
        $this->assertFalse(oauth2UserMayRegister([], []));
    }

    public function testTheWildcardLetsEverybodyRegister(): void
    {
        $this->assertTrue(oauth2UserMayRegister(['*'], []));
        $this->assertTrue(oauth2UserMayRegister(['Other', '*'], $this->userGroups('Sales')));
    }

    public function testAMemberOfAnAllowedGroupMayRegister(): void
    {
        $allowed = oauth2ParseAllowedGroups('TeamPass-Users,Helpdesk');

        $this->assertTrue(oauth2UserMayRegister($allowed, $this->userGroups('Sales', 'Helpdesk')));
        $this->assertTrue(oauth2UserMayRegister($allowed, $this->userGroups('teampass-users')));
    }

    public function testAUserOutsideTheAllowedGroupsMayNotRegister(): void
    {
        $allowed = oauth2ParseAllowedGroups('TeamPass-Users,Helpdesk');

        $this->assertFalse(oauth2UserMayRegister($allowed, $this->userGroups('Sales')));
        $this->assertFalse(oauth2UserMayRegister($allowed, []));
        // Before the fix the whole setting was compared with one group name
        $this->assertFalse(oauth2UserMayRegister($allowed, $this->userGroups('TeamPass-Users,Helpdesk')));
        // No partial match
        $this->assertFalse(oauth2UserMayRegister($allowed, $this->userGroups('TeamPass-Users-Old')));
    }

    public function testAGroupMayBeNamedByItsObjectId(): void
    {
        $allowed = ['6D9E2B1C-0F4A-4C3B-9A8E-1B2C3D4E5F60'];
        // Graph without a permission to read groups: id and type only, no name
        $unnamed = [['id' => '6d9e2b1c-0f4a-4c3b-9a8e-1b2c3d4e5f60', 'displayName' => null]];

        $this->assertTrue(oauth2UserMayRegister($allowed, $unnamed));
        $this->assertFalse(oauth2UserMayRegister(['TeamPass-Users'], $unnamed));
    }

    public function testTheWildcardIsNotAGroupName(): void
    {
        $this->assertFalse(oauth2GroupIsAllowed(['*'], ['id' => '*', 'displayName' => '*']));
    }

    public function testMalformedGroupsNeverMatch(): void
    {
        $allowed = ['TeamPass-Users'];

        $this->assertFalse(oauth2UserMayRegister($allowed, ['TeamPass-Users', null, 42, []]));
        $this->assertFalse(oauth2GroupIsAllowed($allowed, ['id' => null, 'displayName' => ['TeamPass-Users']]));
        $this->assertFalse(oauth2GroupIsAllowed($allowed, ['id' => '', 'displayName' => '  ']));
    }

    public function testTheAllowedGroupsAreLeftOutOfTheRoleMatching(): void
    {
        $allowed = oauth2ParseAllowedGroups('teampass-users, id-Helpdesk');
        $userGroups = $this->userGroups('Sales', 'TeamPass-Users', 'Helpdesk', 'Finance');

        $this->assertSame($this->userGroups('Sales', 'Finance'), oauth2GroupsForRoleMatching($allowed, $userGroups));
    }

    public function testTheWildcardLeavesEveryGroupForTheRoleMatching(): void
    {
        $userGroups = $this->userGroups('Sales', 'Finance');

        $this->assertSame($userGroups, oauth2GroupsForRoleMatching(['*'], $userGroups));
        $this->assertSame($userGroups, oauth2GroupsForRoleMatching([], $userGroups));
    }

    public function testTheRoleMatchingKeepsOnlyGroupEntries(): void
    {
        $this->assertSame(
            [['id' => 'x', 'displayName' => null]],
            oauth2GroupsForRoleMatching(['Sales'], ['Sales', null, ['id' => 'x', 'displayName' => null]])
        );
    }

    public function testARoleIsLookedUpAsItIsStored(): void
    {
        // Every role title is saved through FILTER_SANITIZE_FULL_SPECIAL_CHARS
        $this->assertSame(
            ["Équipe R&D, O'Neil", '&Eacute;quipe R&amp;D, O&#039;Neil'],
            oauth2RoleTitleCandidates("Équipe R&D, O'Neil")
        );
        // Nothing to encode: one lookup, as before
        $this->assertSame(['TeamPass-Users'], oauth2RoleTitleCandidates('TeamPass-Users'));
    }

    public function testAGroupWithoutANameMatchesNoRole(): void
    {
        foreach ([null, '', '  ', 42, ['Sales']] as $name) {
            $this->assertSame([], oauth2RoleTitleCandidates($name));
        }
    }

    public function testAStoredRoleTitleReadsAsTheGroupNameItWasCreatedFrom(): void
    {
        foreach (["Équipe R&D", "O'Neil team", 'Say "hi"', 'TeamPass-Users'] as $name) {
            $stored = (string) filter_var($name, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $this->assertSame($name, oauth2RoleTitleAsGroupName($stored));
        }
    }

    public function testTheLogDetailNamesTheGroupsReceived(): void
    {
        $this->assertSame('jdoe | groups: Sales, Finance', oauth2SelfRegisterLogDetail('jdoe', $this->userGroups('Sales', 'Finance')));
        $this->assertSame('jdoe | groups: none', oauth2SelfRegisterLogDetail('jdoe', []));
    }

    public function testTheLogDetailCountsTheGroupsGraphReturnedWithoutAName(): void
    {
        $groups = array_merge($this->userGroups('Sales'), [['id' => 'a', 'displayName' => null], ['id' => 'b']]);

        $this->assertSame('jdoe | groups: Sales (+2 without name)', oauth2SelfRegisterLogDetail('jdoe', $groups));
        $this->assertSame('jdoe | groups: none (+1 without name)', oauth2SelfRegisterLogDetail('jdoe', [['id' => 'a']]));
    }

    public function testTheLogDetailFitsTheLogColumn(): void
    {
        $detail = oauth2SelfRegisterLogDetail('jdoe', $this->userGroups(...array_fill(0, 60, 'Équipe')));

        $this->assertSame(250, mb_strlen($detail));
        $this->assertTrue(mb_check_encoding($detail, 'UTF-8'));
    }
}
