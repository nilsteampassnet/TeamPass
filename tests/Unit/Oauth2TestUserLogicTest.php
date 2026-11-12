<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/oauth2_test_user_logic.php';

/**
 * Decisions of the "Test a user" check of the OAuth page: what the next SSO sign-in of a
 * Microsoft Entra ID user would do. The wiring is covered by Oauth2TestUserWiringTest, the
 * Graph queries by OAuth2EntraDirectoryTest.
 */
final class Oauth2TestUserLogicTest extends TestCase
{
    private const IDENTITY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const OTHER_IDENTITY = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    /**
     * A sign-in that is accepted unless the test changes one fact.
     *
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private static function context(array $changes = []): array
    {
        return array_merge([
            'oauth2_enabled' => true,
            'directory_enabled' => true,
            'login' => 'jdoe',
            'account' => null,
            'reserved_account' => false,
            'identity' => self::IDENTITY,
            'bound_elsewhere' => '',
            'link_existing_accounts' => false,
            'ldap_mode' => false,
            'deleted_homonym' => false,
            'may_register' => true,
        ], $changes);
    }

    /**
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private static function account(array $changes = []): array
    {
        return array_merge([
            'id' => 12,
            'login' => 'jdoe',
            'auth_type' => 'oauth2',
            'oauth2_identity' => self::IDENTITY,
            'disabled' => 0,
        ], $changes);
    }

    /**
     * @param array<string, mixed> $changes
     */
    private static function key(array $changes): string
    {
        return oauth2TestSignInOutcome(self::context($changes))['key'];
    }

    public function testTheLoginIsThePartOfTheUpnBeforeTheAt(): void
    {
        $this->assertSame('jdoe', oauth2LoginFromPrincipalName('jdoe@contoso.com'));
        $this->assertSame('jdoe_fabrikam.com#EXT#', oauth2LoginFromPrincipalName('jdoe_fabrikam.com#EXT#@contoso.onmicrosoft.com'));
        $this->assertSame('jdoe', oauth2LoginFromPrincipalName('jdoe'));
    }

    public function testANewUserInAnAllowedGroupIsCreated(): void
    {
        $outcome = oauth2TestSignInOutcome(self::context());

        $this->assertTrue($outcome['accepted']);
        $this->assertSame('oauth2_test_create', $outcome['key']);
        $this->assertSame('jdoe', $outcome['tokens']['#login#']);
    }

    public function testANewUserFollowsTheOrderOfTheSignIn(): void
    {
        // initialChecks::getUserInfo() refuses first, then checkOauth2User(), then createOauth2User()
        $this->assertSame('oauth2_test_deleted_homonym', self::key(['deleted_homonym' => true, 'may_register' => false, 'bound_elsewhere' => 'john']));
        $this->assertSame('oauth2_test_self_register_refused', self::key(['may_register' => false, 'bound_elsewhere' => 'john']));
        $this->assertSame('oauth2_test_already_bound', self::key(['bound_elsewhere' => 'john']));
    }

    public function testTheRenamedUpnNamesTheAccountAlreadyLinked(): void
    {
        $outcome = oauth2TestSignInOutcome(self::context(['login' => 'john.doe', 'bound_elsewhere' => 'jdoe']));

        $this->assertFalse($outcome['accepted']);
        $this->assertSame(['#login#' => 'john.doe', '#auth_type#' => '', '#bound#' => 'jdoe'], $outcome['tokens']);
    }

    public function testTheProviderAndTheDirectoryRefuseBeforeTeampass(): void
    {
        $this->assertSame('oauth2_test_oauth2_disabled', self::key(['oauth2_enabled' => false, 'directory_enabled' => false]));
        $this->assertSame('oauth2_test_directory_disabled', self::key(['directory_enabled' => false, 'account' => self::account()]));
        // An unknown state is not a refusal
        $this->assertSame('oauth2_test_create', self::key(['directory_enabled' => null]));
    }

    public function testALinkedAccountIsAccepted(): void
    {
        $outcome = oauth2TestSignInOutcome(self::context(['account' => self::account()]));

        $this->assertTrue($outcome['accepted']);
        $this->assertSame('oauth2_test_accept', $outcome['key']);
    }

    public function testAnAccountLinkedToAnotherIdentityIsRefused(): void
    {
        $this->assertSame('oauth2_test_mismatch', self::key(['account' => self::account(['oauth2_identity' => self::OTHER_IDENTITY])]));
    }

    public function testAnOauth2AccountNotLinkedYetIsLinked(): void
    {
        $this->assertSame('oauth2_test_bind', self::key(['account' => self::account(['oauth2_identity' => null])]));
        // ... unless the identity already owns another account
        $this->assertSame('oauth2_test_already_bound', self::key([
            'account' => self::account(['oauth2_identity' => null]),
            'bound_elsewhere' => 'john',
        ]));
    }

    public function testALocalOrLdapAccountIsConvertedOnlyWhenLinkingIsOn(): void
    {
        $local = self::account(['auth_type' => 'local', 'oauth2_identity' => null]);

        $outcome = oauth2TestSignInOutcome(self::context(['account' => $local]));
        $this->assertSame('oauth2_test_link_disabled', $outcome['key']);
        $this->assertSame('local', $outcome['tokens']['#auth_type#']);

        $this->assertSame('oauth2_test_convert', self::key(['account' => $local, 'link_existing_accounts' => true]));
        $this->assertSame('oauth2_test_convert', self::key([
            'account' => self::account(['auth_type' => 'ldap', 'oauth2_identity' => null]),
            'link_existing_accounts' => true,
        ]));
    }

    public function testAnLdapAccountFailsAtTheLdapStepWhenLdapIsOn(): void
    {
        $this->assertSame('oauth2_test_ldap_first', self::key([
            'account' => self::account(['auth_type' => 'ldap', 'oauth2_identity' => null]),
            'link_existing_accounts' => true,
            'ldap_mode' => true,
        ]));
        // A local account never goes through LDAP
        $this->assertSame('oauth2_test_convert', self::key([
            'account' => self::account(['auth_type' => 'local', 'oauth2_identity' => null]),
            'link_existing_accounts' => true,
            'ldap_mode' => true,
        ]));
    }

    public function testTheAdministratorAndTheInternalAccountsNeverSignInThroughSso(): void
    {
        $this->assertSame('oauth2_test_reserved_account', self::key([
            'login' => 'Admin',
            'account' => self::account(['login' => 'admin', 'auth_type' => 'local', 'oauth2_identity' => null]),
            'reserved_account' => true,
            'link_existing_accounts' => true,
        ]));
    }

    public function testALockedAccountIsRefusedOnceItsIdentityIsChecked(): void
    {
        $this->assertSame('account_is_locked', self::key(['account' => self::account(['disabled' => 1])]));
        $this->assertSame('oauth2_test_mismatch', self::key([
            'account' => self::account(['disabled' => 1, 'oauth2_identity' => self::OTHER_IDENTITY]),
        ]));
    }

    public function testAMissingIdentityIsRefused(): void
    {
        $this->assertSame('oauth2_identity_missing', self::key(['account' => self::account(), 'identity' => '']));
    }

    public function testTheGroupEntryKeyTellsATypoFromAnAmbiguousName(): void
    {
        $this->assertSame('oauth2_test_group_not_found', oauth2TestGroupEntryKey([]));
        $this->assertSame('', oauth2TestGroupEntryKey([['id' => 'g1', 'displayName' => 'Sales']]));
        $this->assertSame('oauth2_test_group_several', oauth2TestGroupEntryKey([
            ['id' => 'g1', 'displayName' => 'Sales'],
            ['id' => 'g2', 'displayName' => 'Sales'],
        ]));
    }

    public function testTokensAreReplaced(): void
    {
        $this->assertSame(
            'jdoe is linked to john',
            oauth2TestText('#login# is linked to #bound#', ['#login#' => 'jdoe', '#bound#' => 'john'])
        );
    }

    public function testEveryOutcomeIsTranslated(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/oauth2_test_user_logic.php');
        preg_match_all("/'((?:oauth2_test|oauth2_identity|account_is)_[a-z_]+)'/", $source, $matches);
        $keys = array_unique($matches[1]);
        $this->assertGreaterThan(15, count($keys));

        foreach (['english', 'french'] as $language) {
            $strings = require __DIR__ . '/../../app/includes/language/' . $language . '.php';
            foreach ($keys as $key) {
                $this->assertNotEmpty($strings[$key] ?? '', $language . ' lacks ' . $key);
            }
        }
    }
}
