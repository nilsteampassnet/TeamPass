<?php

declare(strict_types=1);

namespace TeampassTests\MfaRoles;

use PHPUnit\Framework\TestCase;

// isKeyExistingAndEqual() / isOneVarOfArrayEqualToValue(), resolved from the global namespace
require_once __DIR__ . '/../Stubs/auth_pure_functions.php';

/**
 * "MFA is requested for users in Roles" must apply to the roles inherited from AD groups.
 *
 * The login flow precomputed mfa_auth_requested_roles from the manual roles (fonction_id)
 * only. A user holding the MFA role through the AD group mapping alone (roles_from_ad_groups)
 * was never asked for MFA, until an administrator saved the user form, which rewrites every
 * displayed role, AD ones included, as a manual role.
 *
 * The functions below are evaluated from the repository sources, so the test exercises the
 * shipped code rather than a copy.
 */
class MfaRolesFromAdGroupsTest extends TestCase
{
    private const MFA_ROLES = '["12","15"]';

    private static function source(string $relativePath): string
    {
        $content = file_get_contents(__DIR__ . '/../../' . $relativePath);
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

    public static function setUpBeforeClass(): void
    {
        if (function_exists(__NAMESPACE__ . '\\userNeedsMfa') === true) {
            return;
        }

        $identify = self::source('app/sources/identify.php');
        // Evaluates the repository's own code, never external input.
        eval(
            'namespace ' . __NAMESPACE__ . ';'
            . self::functionSource(self::source('app/sources/main.functions.php'), 'mfa_auth_requested_roles')
            . self::functionSource($identify, 'userMfaRequestedByRoles')
            . self::functionSource($identify, 'userNeedsMfa')
        );
    }

    /**
     * @param array<string, mixed> $userInfo
     */
    private static function requestedByRoles(array $userInfo, string $mfaRoles = self::MFA_ROLES): bool
    {
        return userMfaRequestedByRoles(['mfa_for_roles' => $mfaRoles], $userInfo);
    }

    public function testRoleInheritedFromAdGroupRequestsMfa(): void
    {
        self::assertTrue(self::requestedByRoles(['fonction_id' => '', 'roles_from_ad_groups' => '15']));
    }

    public function testAdRoleIsAddedToManualRoles(): void
    {
        self::assertTrue(self::requestedByRoles(['fonction_id' => '3;4', 'roles_from_ad_groups' => '7;12']));
    }

    public function testManualRoleStillRequestsMfa(): void
    {
        self::assertTrue(self::requestedByRoles(['fonction_id' => '12', 'roles_from_ad_groups' => '']));
    }

    public function testUserOutsideTheMfaRolesIsNotRequested(): void
    {
        self::assertFalse(self::requestedByRoles(['fonction_id' => '3', 'roles_from_ad_groups' => '7']));
        self::assertFalse(self::requestedByRoles([]));
    }

    public function testNoRoleRestrictionRequestsMfaForEveryone(): void
    {
        self::assertTrue(self::requestedByRoles(['fonction_id' => '3'], ''));
        self::assertTrue(userMfaRequestedByRoles(['mfa_for_roles' => null], ['fonction_id' => '3']));
    }

    public function testLoginRequiresMfaForUserWithAdRoleOnly(): void
    {
        $settings = ['google_authentication' => 1, 'mfa_for_roles' => self::MFA_ROLES];
        $userInfo = ['admin' => 0, 'mfa_enabled' => 1, 'fonction_id' => '', 'roles_from_ad_groups' => '15'];

        self::assertTrue(userNeedsMfa($settings, $userInfo));

        // The value the login flow precomputes must give the same answer
        $userInfo['mfa_auth_requested_roles'] = userMfaRequestedByRoles($settings, $userInfo);
        self::assertTrue(userNeedsMfa($settings, $userInfo));
    }

    public function testEveryLoginPrecomputationUsesBothRoleSources(): void
    {
        $identify = self::source('app/sources/identify.php');

        self::assertSame(
            1,
            substr_count($identify, 'mfa_auth_requested_roles('),
            'identify.php must evaluate the MFA roles through userMfaRequestedByRoles() only, '
            . 'which merges the manual and the AD roles.'
        );
        self::assertSame(
            3,
            substr_count($identify, "\$userInfo['mfa_auth_requested_roles'] = userMfaRequestedByRoles(\$SETTINGS, \$userInfo);"),
            'The initial checks, the post-LDAP reload and the passwordless passkey sign-in must all '
            . 'precompute the flag from both role sources.'
        );
    }
}
