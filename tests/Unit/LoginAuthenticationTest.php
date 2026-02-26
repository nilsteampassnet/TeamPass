<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassClasses\PasswordManager\PasswordManager;

// Load pure-function stubs extracted from sources/identify.php and
// sources/main.functions.php. These carry no DB or session dependency.
require_once __DIR__ . '/../Stubs/auth_pure_functions.php';

/**
 * Unit tests for the TeamPass login-page authentication flow.
 *
 * The login page sends credentials to sources/identify.php which:
 *   1. Resolves effective username + password  (identifyGetUserCredentials)
 *   2. Runs pre-auth guards                   (initialChecks methods)
 *   3. Verifies the password hash             (PasswordManager / checkCredentials)
 *   4. Validates password lifetime            (checkUserPasswordValidity)
 *   5. Checks LDAP-account expiry             (isAccountExpired)
 *   6. Applies role-based permission needles  (applyRoleNeedlePermissions)
 *
 * DB-dependent steps (brute-force lookups, session creation, key loading)
 * are not covered here; they belong in integration tests that run against a
 * dedicated test database.
 */
class LoginAuthenticationTest extends TestCase
{
    private PasswordManager $manager;

    protected function setUp(): void
    {
        $this->manager = new PasswordManager();
    }

    // =========================================================================
    // 1. Password verification — core of the login credential check
    // =========================================================================

    public function testCorrectPasswordAuthenticates(): void
    {
        $hash = $this->manager->hashPassword('correct-password');

        $this->assertTrue($this->manager->verifyPassword($hash, 'correct-password'));
    }

    public function testWrongPasswordDoesNotAuthenticate(): void
    {
        $hash = $this->manager->hashPassword('correct-password');

        $this->assertFalse($this->manager->verifyPassword($hash, 'wrong-password'));
    }

    public function testEmptyPasswordDoesNotAuthenticate(): void
    {
        $hash = $this->manager->hashPassword('non-empty-password');

        $this->assertFalse($this->manager->verifyPassword($hash, ''));
    }

    public function testPasswordWithUnicodeCharactersAuthenticates(): void
    {
        $plain = 'pässwörd_中文_日本語';
        $hash  = $this->manager->hashPassword($plain);

        $this->assertTrue($this->manager->verifyPassword($hash, $plain));
    }

    public function testVeryLongPasswordAuthenticates(): void
    {
        // bcrypt truncates at 72 bytes internally; Symfony wraps it to avoid the
        // truncation issue. Verify that long passwords still round-trip correctly.
        $plain = str_repeat('abcXYZ123!', 30); // 300 chars
        $hash  = $this->manager->hashPassword($plain);

        $this->assertTrue($this->manager->verifyPassword($hash, $plain));
    }

    /**
     * IMPORTANT: passwords containing HTML-special characters must NOT be
     * sanitized before hashing / verification (fix introduced in 3.1.5.10).
     * A raw password with '<', '>', '&', '"' must match its own hash, NOT the
     * FILTER_SANITIZE_FULL_SPECIAL_CHARS-altered version.
     */
    public function testRawPasswordWithHtmlCharsAuthenticatesWithRawHash(): void
    {
        $raw       = '<script>alert("xss")</script>&foo';
        $sanitized = filter_var($raw, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $hashOfRaw = $this->manager->hashPassword($raw);

        // Raw password verifies against its own hash
        $this->assertTrue($this->manager->verifyPassword($hashOfRaw, $raw));

        // Sanitized version does NOT verify against the raw hash
        // (the two strings differ, confirming they are treated independently)
        if ($sanitized !== $raw) {
            $this->assertFalse($this->manager->verifyPassword($hashOfRaw, $sanitized));
        }
    }

    public function testSanitizedPasswordDiffersFromRawWhenHtmlPresent(): void
    {
        $raw       = 'pass<word>&"quoted"';
        $sanitized = filter_var($raw, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        // Confirm the sanitizer actually changes the string
        $this->assertNotEquals($raw, $sanitized);

        // Each version only validates against its own hash
        $hashRaw       = $this->manager->hashPassword($raw);
        $hashSanitized = $this->manager->hashPassword($sanitized);

        $this->assertTrue($this->manager->verifyPassword($hashRaw, $raw));
        $this->assertTrue($this->manager->verifyPassword($hashSanitized, $sanitized));
        $this->assertFalse($this->manager->verifyPassword($hashRaw, $sanitized));
        $this->assertFalse($this->manager->verifyPassword($hashSanitized, $raw));
    }

    public function testPasswordWithSpecialSymbolsAuthenticates(): void
    {
        $plain = 'P@$$w0rd!#%^&*()_+-=[]{}|;:\',.<>?`~\\"\'éàü';
        $hash  = $this->manager->hashPassword($plain);

        $this->assertTrue($this->manager->verifyPassword($hash, $plain));
    }

    public function testPasswordHashIsNotDeterministicAcrossLogins(): void
    {
        // Two login attempts with the same password must produce different hashes
        // (random salt embedded by bcrypt), so stolen hashes can't be replayed.
        $plain = 'same-password';
        $hash1 = $this->manager->hashPassword($plain);
        $hash2 = $this->manager->hashPassword($plain);

        $this->assertNotEquals($hash1, $hash2);
        // Yet both still verify correctly
        $this->assertTrue($this->manager->verifyPassword($hash1, $plain));
        $this->assertTrue($this->manager->verifyPassword($hash2, $plain));
    }

    // =========================================================================
    // 2. identifyGetUserCredentials — credential resolution
    // =========================================================================

    public function testUsesFormCredentialsWhenHttpLoginDisabled(): void
    {
        $settings = ['enable_http_request_login' => 0, 'maintenance_mode' => 0];

        $result = identifyGetUserCredentials($settings, 'httpUser', 'httpPw', 'formPw', 'formUser');

        $this->assertSame('formUser', $result['username']);
        $this->assertSame('formPw', $result['passwordClear']);
    }

    public function testUsesFormCredentialsWhenMaintenanceModeOff(): void
    {
        // HTTP login enabled but maintenance mode OFF → form credentials win
        $settings = ['enable_http_request_login' => 1, 'maintenance_mode' => 0];

        $result = identifyGetUserCredentials($settings, 'user@domain.com', 'httpPw', 'formPw', 'formUser');

        $this->assertSame('formUser', $result['username']);
        $this->assertSame('formPw', $result['passwordClear']);
    }

    public function testExtractsUsernameFromEmailFormatHttpAuth(): void
    {
        $settings = ['enable_http_request_login' => 1, 'maintenance_mode' => 1];

        $result = identifyGetUserCredentials($settings, 'john.doe@example.com', 'secret', '', '');

        $this->assertSame('john.doe', $result['username']);
        $this->assertSame('secret', $result['passwordClear']);
    }

    public function testExtractsUsernameFromDomainBackslashFormat(): void
    {
        $settings = ['enable_http_request_login' => 1, 'maintenance_mode' => 1];

        $result = identifyGetUserCredentials($settings, 'DOMAIN\\john.doe', 'secret', '', '');

        $this->assertSame('john.doe', $result['username']);
        $this->assertSame('secret', $result['passwordClear']);
    }

    public function testFallsBackToPasswordAsUsernameWhenNoSeparator(): void
    {
        // HTTP auth active + maintenance, but user string has no '@' or '\\'
        $settings = ['enable_http_request_login' => 1, 'maintenance_mode' => 1];

        $result = identifyGetUserCredentials($settings, 'plainnamenoatsep', 'fallback', '', '');

        // The production code returns $serverPHPAuthPw as both username and password
        $this->assertSame('fallback', $result['username']);
        $this->assertSame('fallback', $result['passwordClear']);
    }

    // =========================================================================
    // 3. isAccountExpired — AD/LDAP account expiry detection
    // =========================================================================

    public function testAccountNotExpiredWhenNoExpiryData(): void
    {
        $this->assertFalse(isAccountExpired([]));
    }

    public function testAccountExpiredWhenShadowexpireIsOne(): void
    {
        $this->assertTrue(isAccountExpired(['shadowexpire' => [0 => '1']]));
    }

    public function testAccountNotExpiredWhenShadowexpireIsZero(): void
    {
        $this->assertFalse(isAccountExpired(['shadowexpire' => [0 => '0']]));
    }

    public function testAccountExpiredWhenAccountexpiresIsInPast(): void
    {
        $past = (string) (time() - 3600); // one hour ago
        $this->assertTrue(isAccountExpired(['accountexpires' => [0 => $past]]));
    }

    public function testAccountNotExpiredWhenAccountexpiresIsInFuture(): void
    {
        $future = (string) (time() + 86400); // tomorrow
        $this->assertFalse(isAccountExpired(['accountexpires' => [0 => $future]]));
    }

    public function testAccountNotExpiredWhenAccountexpiresIsZero(): void
    {
        // 0 means "never expires" in AD conventions
        $this->assertFalse(isAccountExpired(['accountexpires' => [0 => '0']]));
    }

    public function testAccountExpiredTakesShadowexpirePrecedence(): void
    {
        // shadowexpire=1 overrides a future accountexpires
        $future = (string) (time() + 86400);
        $data   = [
            'shadowexpire'  => [0 => '1'],
            'accountexpires' => [0 => $future],
        ];
        $this->assertTrue(isAccountExpired($data));
    }

    // =========================================================================
    // 4. shouldAdjustPermissionsFromRoleNames — needle guard
    // =========================================================================

    public function testReturnsFalseForLowUserId(): void
    {
        $settings = ['admin_needle' => 'admin'];
        $this->assertFalse(shouldAdjustPermissionsFromRoleNames(999999, 'alice', $settings));
    }

    public function testReturnsFalseForBoundaryUserId(): void
    {
        // Exactly 1 000 000 — threshold is strictly < 1 000 000
        $settings = ['admin_needle' => 'admin'];
        $this->assertTrue(shouldAdjustPermissionsFromRoleNames(1000000, 'alice', $settings));
    }

    public function testReturnsFalseWhenUserIsExcluded(): void
    {
        $settings = ['admin_needle' => 'admin', 'exclude_user' => 'service'];
        $this->assertFalse(shouldAdjustPermissionsFromRoleNames(1000001, 'service-account', $settings));
    }

    public function testReturnsFalseWhenNoNeedlesConfigured(): void
    {
        $this->assertFalse(shouldAdjustPermissionsFromRoleNames(1000001, 'alice', []));
    }

    public function testReturnsTrueWhenAdminNeedlePresent(): void
    {
        $settings = ['admin_needle' => 'admin'];
        $this->assertTrue(shouldAdjustPermissionsFromRoleNames(1000001, 'alice', $settings));
    }

    public function testReturnsTrueWhenManagerNeedlePresent(): void
    {
        $settings = ['manager_needle' => 'mgr'];
        $this->assertTrue(shouldAdjustPermissionsFromRoleNames(1000001, 'alice', $settings));
    }

    public function testReturnsTrueWhenReadOnlyNeedlePresent(): void
    {
        $settings = ['read_only_needle' => 'readonly'];
        $this->assertTrue(shouldAdjustPermissionsFromRoleNames(1000001, 'alice', $settings));
    }

    // =========================================================================
    // 5. applyRoleNeedlePermissions — permission mapping
    // =========================================================================

    public function testReturnsCurrentPermissionsWhenNoNeedleMatches(): void
    {
        $current  = ['admin' => 0, 'gestionnaire' => 0, 'can_manage_all_users' => 0, 'read_only' => 0];
        $settings = ['admin_needle' => 'admin'];
        $role     = ['title' => 'Standard User'];

        $result = applyRoleNeedlePermissions($role, $current, $settings);

        $this->assertSame($current, $result);
    }

    public function testSetsAdminPermissionsOnAdminNeedle(): void
    {
        $settings = ['admin_needle' => 'admin'];
        $role     = ['title' => 'TeamPass admin'];

        $result = applyRoleNeedlePermissions($role, [], $settings);

        $this->assertSame(1, $result['admin']);
        $this->assertSame(0, $result['gestionnaire']);
        $this->assertSame(0, $result['read_only']);
    }

    public function testSetsManagerPermissionsOnManagerNeedle(): void
    {
        $settings = ['manager_needle' => 'mgr'];
        $role     = ['title' => 'IT mgr'];

        $result = applyRoleNeedlePermissions($role, [], $settings);

        $this->assertSame(0, $result['admin']);
        $this->assertSame(1, $result['gestionnaire']);
    }

    public function testSetsTpManagerPermissionsOnTpManagerNeedle(): void
    {
        $settings = ['tp_manager_needle' => 'tpmgr'];
        $role     = ['title' => 'tpmgr team'];

        $result = applyRoleNeedlePermissions($role, [], $settings);

        $this->assertSame(1, $result['can_manage_all_users']);
        $this->assertSame(0, $result['admin']);
    }

    public function testSetsReadOnlyPermissionsOnReadOnlyNeedle(): void
    {
        $settings = ['read_only_needle' => 'readonly'];
        $role     = ['title' => 'readonly viewer'];

        $result = applyRoleNeedlePermissions($role, [], $settings);

        $this->assertSame(1, $result['read_only']);
        $this->assertSame(0, $result['admin']);
    }

    public function testNeedleIsSubstringMatchNotExact(): void
    {
        // The needle only needs to appear somewhere inside the title
        $settings = ['admin_needle' => 'admin'];
        $role     = ['title' => 'super-admin-role'];

        $result = applyRoleNeedlePermissions($role, [], $settings);

        $this->assertSame(1, $result['admin']);
    }

    public function testFirstMatchingNeedleWins(): void
    {
        // Both admin and manager needles are configured; admin_needle is first
        $settings = ['admin_needle' => 'admin', 'manager_needle' => 'admin'];
        $role     = ['title' => 'admin'];

        $result = applyRoleNeedlePermissions($role, [], $settings);

        // admin_needle is iterated first → admin = 1
        $this->assertSame(1, $result['admin']);
        $this->assertSame(0, $result['gestionnaire']);
    }

    // =========================================================================
    // 6. initialChecks — pre-authentication guards
    // =========================================================================

    public function testMaintenanceModeAllowsAdmins(): void
    {
        $checks = new initialChecks();
        // Must not throw for admin users even when maintenance is on
        $checks->isMaintenanceModeEnabled(1, 1);
        $this->addToAssertionCount(1);
    }

    public function testMaintenanceModeDoesNothingWhenDisabled(): void
    {
        $checks = new initialChecks();
        $checks->isMaintenanceModeEnabled(0, 0);
        $this->addToAssertionCount(1);
    }

    public function testMaintenanceModeBlocksRegularUsers(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('error');

        $checks = new initialChecks();
        $checks->isMaintenanceModeEnabled(1, 0);
    }

    public function testInstallFolderDoesNotBlockNonAdmins(): void
    {
        $checks = new initialChecks();
        // Regular user: no exception regardless of folder existence
        $checks->isInstallFolderPresent(0, '/this/path/does/not/matter');
        $this->addToAssertionCount(1);
    }

    public function testInstallFolderDoesNotBlockAdminWhenAbsent(): void
    {
        $checks = new initialChecks();
        $checks->isInstallFolderPresent(1, '/path/that/definitely/does/not/exist/' . uniqid());
        $this->addToAssertionCount(1);
    }

    public function testInstallFolderBlocksAdminWhenPresent(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('error');

        $checks = new initialChecks();
        // sys_get_temp_dir() is guaranteed to exist on every platform
        $checks->isInstallFolderPresent(1, sys_get_temp_dir());
    }

    public function test2faNotRequiredWhenNoMethodEnabled(): void
    {
        $checks = new initialChecks();
        // All 2FA methods disabled → no exception
        $checks->is2faCodeRequired(0, 0, 0, 0, 0, true, '', true);
        $this->addToAssertionCount(1);
    }

    public function test2faNotRequiredWhenSelectionAlreadyMade(): void
    {
        $checks = new initialChecks();
        // User already selected a method → no exception
        $checks->is2faCodeRequired(1, 0, 0, 0, 0, true, 'google', true);
        $this->addToAssertionCount(1);
    }

    public function test2faRequiredWhenMethodEnabledAndNoSelection(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('error');

        $checks = new initialChecks();
        // Google 2FA is on, user has MFA enabled, no selection yet
        $checks->is2faCodeRequired(0, 1, 0, 0, 0, true, '', true);
    }

    public function test2faNotRequiredForAdminWhenAdminMfaNotMandatory(): void
    {
        $checks = new initialChecks();
        // admin=1, adminMfaRequired=0 → no exception even with GA active
        $checks->is2faCodeRequired(0, 1, 0, 1, 0, true, '', true);
        $this->addToAssertionCount(1);
    }

    public function test2faRequiredForAdminWhenMandatory(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('error');

        $checks = new initialChecks();
        // admin=1, adminMfaRequired=1, GA enabled, no selection → must throw
        $checks->is2faCodeRequired(0, 1, 0, 1, 1, true, '', true);
    }

    // =========================================================================
    // 7. checkUserPasswordValidity — password expiry logic
    // =========================================================================

    public function testLdapNonLocalUserAlwaysHasValidPassword(): void
    {
        $userInfo = ['auth_type' => 'ldap', 'last_pw_change' => 0];
        $settings = ['ldap_mode' => 1, 'pw_life_duration' => 30];

        $result = checkUserPasswordValidity($userInfo, 0, 0, $settings);

        $this->assertTrue($result['validite_pw']);
    }

    public function testLocalUserWithLdapModeIsStillValidated(): void
    {
        // auth_type=local must go through the normal lifetime check even in LDAP mode
        $userInfo = ['auth_type' => 'local']; // no last_pw_change key
        $settings = ['ldap_mode' => 1, 'pw_life_duration' => 30];

        $result = checkUserPasswordValidity($userInfo, 0, 0, $settings);

        // Without last_pw_change, the function returns invalid
        $this->assertFalse($result['validite_pw']);
    }

    public function testInfiniteLifetimeIsAlwaysValid(): void
    {
        $userInfo = ['auth_type' => 'local', 'last_pw_change' => time() - 1000];
        $settings = ['ldap_mode' => 0, 'pw_life_duration' => 0];

        $result = checkUserPasswordValidity($userInfo, 0, 0, $settings);

        $this->assertTrue($result['validite_pw']);
        $this->assertSame('infinite', $result['user_force_relog']);
    }

    public function testPasswordIsValidWhenWithinLifetime(): void
    {
        $settings = ['ldap_mode' => 0, 'pw_life_duration' => 90];
        // Password was changed 10 days ago → 80 days remaining
        $userInfo = [
            'auth_type'     => 'local',
            'last_pw_change' => mktime(0, 0, 0, (int) date('m'), (int) date('d') - 10, (int) date('y')),
        ];

        $result = checkUserPasswordValidity($userInfo, 0, 0, $settings);

        $this->assertTrue($result['validite_pw']);
        $this->assertGreaterThan(0, $result['numDaysBeforePwExpiration']);
    }

    public function testPasswordIsInvalidWhenLifetimeExceeded(): void
    {
        $settings = ['ldap_mode' => 0, 'pw_life_duration' => 30];
        // Password was changed 40 days ago → expired
        $userInfo = [
            'auth_type'     => 'local',
            'last_pw_change' => mktime(0, 0, 0, (int) date('m'), (int) date('d') - 40, (int) date('y')),
        ];

        $result = checkUserPasswordValidity($userInfo, 0, 0, $settings);

        $this->assertFalse($result['validite_pw']);
        $this->assertLessThanOrEqual(0, $result['numDaysBeforePwExpiration']);
    }

    public function testNoLastPwChangeKeyMeansInvalidPassword(): void
    {
        $userInfo = ['auth_type' => 'local']; // intentionally no last_pw_change
        $settings = ['ldap_mode' => 0, 'pw_life_duration' => 90];

        $result = checkUserPasswordValidity($userInfo, 0, 0, $settings);

        $this->assertFalse($result['validite_pw']);
    }
}
