<?php

declare(strict_types=1);

/**
 * Pure functions extracted verbatim from production code for unit testing.
 *
 * These functions have no DB, session, or filesystem dependencies and can
 * therefore be tested in full isolation. They are copied from:
 *   - sources/main.functions.php  (isKeyExistingAndEqual, isOneVarOfArrayEqualToValue)
 *   - sources/identify.php        (all others)
 *
 * MAINTENANCE: keep in sync with their originals. If the production function
 * changes, update this file and its corresponding tests accordingly.
 */

// ---------------------------------------------------------------------------
// From sources/main.functions.php
// ---------------------------------------------------------------------------

/**
 * Check if a key exists in an array and equals the expected value.
 *
 * @param string     $key
 * @param int|string $value
 * @param array      $array
 */
function isKeyExistingAndEqual(string $key, int|string $value, array $array): bool
{
    if (isset($array[$key]) === true
        && (is_int($value) === true ?
            (int) $array[$key] === $value :
            (string) $array[$key] === $value)
    ) {
        return true;
    }
    return false;
}

/**
 * Return true if at least one element of the array strictly equals $value.
 *
 * @param array      $arrayOfVars
 * @param int|string $value
 */
function isOneVarOfArrayEqualToValue(array $arrayOfVars, int|string $value): bool
{
    foreach ($arrayOfVars as $variable) {
        if ($variable === $value) {
            return true;
        }
    }
    return false;
}

// ---------------------------------------------------------------------------
// From sources/identify.php
// ---------------------------------------------------------------------------

/**
 * Check if an AD/LDAP account is expired.
 */
function isAccountExpired(array $userADInfos): bool
{
    return (isset($userADInfos['shadowexpire'][0]) && (int) $userADInfos['shadowexpire'][0] === 1)
        || (isset($userADInfos['accountexpires'][0])
            && (int) $userADInfos['accountexpires'][0] < time()
            && (int) $userADInfos['accountexpires'][0] !== 0);
}

/**
 * Resolve the effective username and clear-text password from the request.
 * When HTTP Basic auth is active AND maintenance mode is on, the server-side
 * PHP_AUTH_USER / PHP_AUTH_PW values take priority over the form fields.
 */
function identifyGetUserCredentials(
    array $SETTINGS,
    string $serverPHPAuthUser,
    string $serverPHPAuthPw,
    string $userPassword,
    string $userLogin
): array {
    if ((int) $SETTINGS['enable_http_request_login'] === 1
        && $serverPHPAuthUser !== null
        && (int) $SETTINGS['maintenance_mode'] === 1
    ) {
        if (strpos($serverPHPAuthUser, '@') !== false) {
            return [
                'username'      => explode('@', $serverPHPAuthUser)[0],
                'passwordClear' => $serverPHPAuthPw,
            ];
        }

        if (strpos($serverPHPAuthUser, '\\') !== false) {
            return [
                'username'      => explode('\\', $serverPHPAuthUser)[1],
                'passwordClear' => $serverPHPAuthPw,
            ];
        }

        return [
            'username'      => $serverPHPAuthPw,
            'passwordClear' => $serverPHPAuthPw,
        ];
    }

    return [
        'username'      => $userLogin,
        'passwordClear' => $userPassword,
    ];
}

/**
 * Return true when needle-based permission adjustments should be considered
 * for the given user.
 */
function shouldAdjustPermissionsFromRoleNames(int $userId, string $userLogin, array $SETTINGS): bool
{
    if ($userId < 1000000) {
        return false;
    }

    $excludeUser = isset($SETTINGS['exclude_user']) ? str_contains($userLogin, $SETTINGS['exclude_user']) : false;
    if ($excludeUser) {
        return false;
    }

    return isset($SETTINGS['admin_needle'])
        || isset($SETTINGS['manager_needle'])
        || isset($SETTINGS['tp_manager_needle'])
        || isset($SETTINGS['read_only_needle']);
}

/**
 * Return the permission set that matches the first needle found in the role
 * title. Falls back to $currentPermissions when no needle matches.
 */
function applyRoleNeedlePermissions(array $role, array $currentPermissions, array $SETTINGS): array
{
    $needleConfig = [
        'admin_needle' => [
            'admin' => 1, 'gestionnaire' => 0, 'can_manage_all_users' => 0, 'read_only' => 0,
        ],
        'manager_needle' => [
            'admin' => 0, 'gestionnaire' => 1, 'can_manage_all_users' => 0, 'read_only' => 0,
        ],
        'tp_manager_needle' => [
            'admin' => 0, 'gestionnaire' => 0, 'can_manage_all_users' => 1, 'read_only' => 0,
        ],
        'read_only_needle' => [
            'admin' => 0, 'gestionnaire' => 0, 'can_manage_all_users' => 0, 'read_only' => 1,
        ],
    ];

    foreach ($needleConfig as $needleSetting => $permissions) {
        if (isset($SETTINGS[$needleSetting]) && str_contains($role['title'], $SETTINGS[$needleSetting])) {
            return $permissions;
        }
    }

    return $currentPermissions;
}

/**
 * Validate whether the user's password is still within its allowed lifetime.
 *
 * Returns an array with:
 *   - validite_pw             bool    password is still valid
 *   - last_pw_change          int|string
 *   - user_force_relog        string  '' | 'infinite'
 *   - numDaysBeforePwExpiration int|string
 */
function checkUserPasswordValidity(array $userInfo, int $numDaysBeforePwExpiration, int $lastPwChange, array $SETTINGS): array
{
    if (isKeyExistingAndEqual('ldap_mode', 1, $SETTINGS) === true && $userInfo['auth_type'] !== 'local') {
        return [
            'validite_pw'             => true,
            'last_pw_change'          => $userInfo['last_pw_change'],
            'user_force_relog'        => '',
            'numDaysBeforePwExpiration' => '',
        ];
    }

    if (isset($userInfo['last_pw_change']) === true) {
        if ((int) $SETTINGS['pw_life_duration'] === 0) {
            return [
                'validite_pw'             => true,
                'last_pw_change'          => '',
                'user_force_relog'        => 'infinite',
                'numDaysBeforePwExpiration' => '',
            ];
        } elseif ((int) $SETTINGS['pw_life_duration'] > 0) {
            $numDaysBeforePwExpiration = (int) $SETTINGS['pw_life_duration'] - round(
                (mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('y')) - $userInfo['last_pw_change']) / (24 * 60 * 60)
            );
            return [
                'validite_pw'             => $numDaysBeforePwExpiration <= 0 ? false : true,
                'last_pw_change'          => $userInfo['last_pw_change'],
                'user_force_relog'        => 'infinite',
                'numDaysBeforePwExpiration' => (int) $numDaysBeforePwExpiration,
            ];
        } else {
            return [
                'validite_pw'             => false,
                'last_pw_change'          => '',
                'user_force_relog'        => '',
                'numDaysBeforePwExpiration' => '',
            ];
        }
    } else {
        return [
            'validite_pw'             => false,
            'last_pw_change'          => '',
            'user_force_relog'        => '',
            'numDaysBeforePwExpiration' => '',
        ];
    }
}

/**
 * Subset of the initialChecks class — only the methods that carry no DB or
 * session dependency and can therefore be unit-tested in isolation.
 *
 * DB-bound methods (isTooManyPasswordAttempts, getUserInfo) are intentionally
 * omitted; they belong in integration tests that run against a real database.
 */
class initialChecks
{
    /** @phpstan-ignore-next-line (property kept for interface parity with production class) */
    public mixed $login = null;

    /**
     * Throw when the application is in maintenance mode and the user is not
     * an administrator.
     *
     * @throws \Exception with message 'error'
     */
    public function isMaintenanceModeEnabled(mixed $maintenance_mode, mixed $user_admin): void
    {
        if ((int) $maintenance_mode === 1 && (int) $user_admin === 0) {
            throw new \Exception('error');
        }
    }

    /**
     * Throw when at least one 2FA method is globally enabled, the user has
     * 2FA active, and no method has been selected yet.
     *
     * @throws \Exception with message 'error'
     */
    public function is2faCodeRequired(
        mixed $yubico,
        mixed $ga,
        mixed $duo,
        mixed $admin,
        mixed $adminMfaRequired,
        mixed $mfa,
        mixed $userMfaSelection,
        mixed $userMfaEnabled
    ): void {
        if (
            (empty($userMfaSelection) === true &&
            isOneVarOfArrayEqualToValue(
                [(int) $yubico, (int) $ga, (int) $duo],
                1
            ) === true)
            && (((int) $admin !== 1 && $userMfaEnabled === true) || ((int) $adminMfaRequired === 1 && (int) $admin === 1))
            && $mfa === true
        ) {
            throw new \Exception('error');
        }
    }

    /**
     * Throw when the install folder is still present and the user is an admin
     * (security reminder to remove the folder after installation).
     *
     * @throws \Exception with message 'error'
     */
    public function isInstallFolderPresent(mixed $admin, string $install_folder): void
    {
        if ((int) $admin === 1 && is_dir($install_folder) === true) {
            throw new \Exception('error');
        }
    }
}
