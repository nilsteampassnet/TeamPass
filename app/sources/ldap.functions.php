<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      ldap.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * Directory access shared by the login (sources/identify.php) and by the configuration test of
 * the LDAP settings page (sources/ldap.queries.php).
 *
 * The test used to carry its own copy of the search and of the bind. It could therefore succeed
 * on a configuration the login rejects, which is the situation these functions remove: the test
 * calls the very same code, in the very same order, and only adds the read-only checks the login
 * performs afterwards (TeamPass account, group mapping).
 */

use TeampassClasses\Language\Language;
use TeampassClasses\LdapExtra\LdapExtra;
use TeampassClasses\LdapExtra\OpenLdapExtra;
use TeampassClasses\LdapExtra\ActiveDirectoryExtra;

/**
 * Name of the attribute holding the user login.
 *
 * The installer seeds 'ldap_user_attribute' with the string '0', which neither `?? ` nor an
 * `isset()` test replaces. The login read it raw and searched the directory on the attribute
 * named "0", so no user was ever found, while the page test used its own `empty()` fallback and
 * reported a success. Both now resolve the value here.
 *
 * @param array $SETTINGS Teampass settings
 *
 * @return string The configured attribute, 'samaccountname' when none is configured
 */
function ldapResolveUserAttribute(array $SETTINGS): string
{
    $attribute = trim((string) ($SETTINGS['ldap_user_attribute'] ?? ''));

    return ($attribute === '' || $attribute === '0') ? 'samaccountname' : $attribute;
}

/**
 * Tell whether the user name attribute is actually configured.
 *
 * @param array $SETTINGS Teampass settings
 *
 * @return bool
 */
function ldapUserAttributeIsConfigured(array $SETTINGS): bool
{
    $attribute = trim((string) ($SETTINGS['ldap_user_attribute'] ?? ''));

    return $attribute !== '' && $attribute !== '0';
}

/**
 * Initialize LDAP connection based on type
 * 
 * @param array $SETTINGS Teampass settings
 * @return array Contains connection and type-specific handler
 * @throws Exception
 */
function initializeLdapConnection(array $SETTINGS): array
{
    $ldapExtra = new LdapExtra($SETTINGS);
    $ldapConnection = $ldapExtra->establishLdapConnection();
    
    switch ($SETTINGS['ldap_type']) {
        case 'ActiveDirectory':
            return [
                'connection' => $ldapConnection,
                'handler' => new ActiveDirectoryExtra(),
                'type' => 'ActiveDirectory'
            ];
        case 'OpenLDAP':
            return [
                'connection' => $ldapConnection,
                'handler' => new OpenLdapExtra(),
                'type' => 'OpenLDAP'
            ];
        default:
            throw new Exception("Unsupported LDAP type: " . $SETTINGS['ldap_type']);
    }
}

/**
 * Attributes the login reads from the directory entry.
 *
 * @param array $SETTINGS Teampass settings
 *
 * @return array<int, string>
 */
function ldapUserSearchAttributes(array $SETTINGS): array
{
    // These are needed for user creation and authentication.
    // 'memberof' is included so the group-mode=user check can inspect it without a second query.
    return [
        'dn', 'mail', 'givenname', 'sn', 'cn', 'displayname',
        'samaccountname', 'userprincipalname', 'uid',
        'shadowexpire', 'accountexpires', 'useraccountcontrol',
        'memberof',
        ldapResolveUserAttribute($SETTINGS),
        LdapExtra::getUserDnAttribute($SETTINGS),
    ];
}

/**
 * Authenticate user against LDAP
 *
 * @param string $username Username
 * @param string $passwordClear Password
 * @param array $ldapHandler LDAP connection and handler
 * @param array $SETTINGS Teampass settings
 * @param Language $lang Language instance
 * @return array Authentication result. The 'stage' key names the step that produced the answer
 *               ('search', 'disabled', 'bind', 'authenticated') so a caller can report it; the
 *               login itself only reads 'error', 'message' and 'user_info'.
 */
function authenticateUser(string $username, string $passwordClear, array $ldapHandler, array $SETTINGS, Language $lang): array
{
    try {
        $userAttribute = ldapResolveUserAttribute($SETTINGS);

        $userADInfos = $ldapHandler['connection']->query()
            ->select(ldapUserSearchAttributes($SETTINGS))
            ->where($userAttribute, '=', $username)
            ->firstOrFail();

        // Verify user status for ActiveDirectory
        if ($ldapHandler['type'] === 'ActiveDirectory' && !$ldapHandler['handler']->userIsEnabled((string) $userADInfos['dn'], $ldapHandler['connection'])) {
            return [
                'error' => true,
                'stage' => 'disabled',
                'message' => "Error: User is not enabled",
                'user_info' => $userADInfos,
            ];
        }
        // Attempt authentication.
        // Active Directory binds on the user principal name. An account that has none - it is not
        // mandatory in AD - used to reach auth()->attempt() with null and fail as a wrong
        // password; the cause is named instead.
        if ($ldapHandler['type'] === 'ActiveDirectory' && empty($userADInfos['userprincipalname'][0]) === true) {
            return [
                'error' => true,
                'stage' => 'bind',
                'message' => $lang->get('ldap_step_bind_no_upn'),
                'user_info' => $userADInfos,
                'bind_identifier' => '',
            ];
        }

        $authIdentifier = $ldapHandler['type'] === 'ActiveDirectory' 
            ? $userADInfos['userprincipalname'][0] 
            : $userADInfos['dn'];
            
        if (!$ldapHandler['connection']->auth()->attempt($authIdentifier, $passwordClear)) {
            return [
                'error' => true,
                'stage' => 'bind',
                'message' => "Error: User is not authenticated",
                'user_info' => $userADInfos,
                'bind_identifier' => (string) $authIdentifier,
            ];
        }
        
        return [
            'error' => false,
            'stage' => 'authenticated',
            'message' => '',
            'user_info' => $userADInfos,
            'bind_identifier' => (string) $authIdentifier,
        ];
        
    } catch (\LdapRecord\Query\ObjectNotFoundException $e) {
        return [
            'error' => true,
            'stage' => 'search',
            'message' => $lang->get('error_bad_credentials')
        ];
    }
}

/**
 * Check if user account is expired
 * 
 * @param array $userADInfos User AD information
 * @return bool
 */
function isAccountExpired(array $userADInfos): bool
{
    return (isset($userADInfos['shadowexpire'][0]) && (int) $userADInfos['shadowexpire'][0] === 1)
        || (isset($userADInfos['accountexpires'][0]) 
            && (int) $userADInfos['accountexpires'][0] < time() 
            && (int) $userADInfos['accountexpires'][0] !== 0);
}

/**
 * Distinguished name of the authenticating user, as the group checks resolve it.
 *
 * @param array $userADInfos User entry returned by the directory
 * @param array $ldapHandler LDAP connection and handler
 * @param array $SETTINGS    Teampass settings
 *
 * @return string '' when the entry carries no usable DN
 */
function ldapResolveUserDn(array $userADInfos, array $ldapHandler, array $SETTINGS): string
{
    if ($ldapHandler['type'] === 'ActiveDirectory') {
        $dnAttribute = LdapExtra::getUserDnAttribute($SETTINGS);

        // The entry DN is the same value: it covers a DN attribute name that does not exist
        return (string) ($userADInfos[$dnAttribute][0] ?? $userADInfos['dn'] ?? '');
    }

    return (string) ($userADInfos['dn'] ?? '');
}

/**
 * Is the authenticating user allowed by the "restrict login to a group" setting?
 *
 * Shared by the login and by the page test: an unmet restriction is the most frequent reason for
 * a directory that accepts the password while TeamPass refuses the login.
 *
 * @param array  $userADInfos User entry returned by the directory
 * @param array  $ldapHandler LDAP connection and handler
 * @param array  $SETTINGS    Teampass settings
 * @param string $username    Login being authenticated
 *
 * @return bool True when no restriction is configured
 */
function ldapUserIsInAllowedLoginGroup(array $userADInfos, array $ldapHandler, array $SETTINGS, string $username): bool
{
    $allowedGroupDn = trim($SETTINGS['ldap_allowed_login_group_dn'] ?? '');
    if ($allowedGroupDn === '') {
        return true;
    }

    $groupMode = $SETTINGS['ldap_allowed_login_group_mode'] ?? 'group';
    $userDnForCheck = ldapResolveUserDn($userADInfos, $ldapHandler, $SETTINGS);

    if ($groupMode === 'user') {
        // User-centric: check the user's own memberOf attribute (AD-native, no extra
        // query). The DN and the connection let the handler resolve nested membership
        // when the attribute alone would deny access.
        return $ldapHandler['handler']->isUserInAllowedGroupByMemberOf(
            $allowedGroupDn,
            $userADInfos,
            $userDnForCheck,
            $ldapHandler['connection']
        );
    }

    // Group-centric (default): scope=base read on the group entry — works outside base DN
    return $ldapHandler['handler']->isUserInAllowedGroup(
        $allowedGroupDn,
        $userDnForCheck,
        $username,
        $ldapHandler['connection']
    );
}

/**
 * Get user groups based on LDAP type
 *
 * @param array $userADInfos User AD information
 * @param array $ldapHandler LDAP connection and handler
 * @param array $SETTINGS Teampass settings
 * @param string $username User login name (used for posixGroup memberuid matching in OpenLDAP)
 * @return array{error: bool, message: string, userGroups: array} User groups, with 'error'
 *         set to true when membership could not be resolved at all
 */
function getUserADGroups(array $userADInfos, array $ldapHandler, array $SETTINGS, string $username = ''): array
{
    if (in_array($ldapHandler['type'], ['ActiveDirectory', 'OpenLDAP'], true) === false) {
        throw new Exception("Unsupported LDAP type: " . $ldapHandler['type']);
    }

    $userDN = ldapResolveUserDn($userADInfos, $ldapHandler, $SETTINGS);

    // Without a DN nothing can be resolved. Reported as an error so the caller keeps the
    // roles already granted instead of removing them all.
    if ($userDN === '') {
        return [
            'error' => true,
            'message' => 'No user DN available to resolve LDAP group membership.',
            'userGroups' => [],
        ];
    }

    if ($ldapHandler['type'] === 'ActiveDirectory') {
        return $ldapHandler['handler']->getUserADGroups(
            $userDN,
            $ldapHandler['connection'],
            $SETTINGS
        );
    }

    return $ldapHandler['handler']->getUserADGroups(
        $userDN,
        $ldapHandler['connection'],
        $SETTINGS,
        $username
    );
}

/**
 * One step of the configuration test.
 *
 * @param string $code   Language key naming the step
 * @param string $status 'ok' | 'ko' | 'warning' | 'skipped'
 * @param string $detail Already translated explanation, '' when the label is enough
 *
 * @return array{code: string, status: string, detail: string}
 */
function ldapTestStep(string $code, string $status, string $detail = ''): array
{
    return [
        'code' => $code,
        'status' => $status,
        'detail' => $detail,
    ];
}

/**
 * Does the login hold a TeamPass account that can complete the authentication?
 *
 * The directory can accept the password while TeamPass refuses the login: no account and no
 * automatic creation, a disabled account, or a deleted homonym. The login answers a generic
 * "bad credentials" in all three cases, which is what makes them so hard to diagnose.
 *
 * @param string $login    Login being tested
 * @param array  $SETTINGS Teampass settings
 * @param Language $lang   Language instance
 *
 * @return array{code: string, status: string, detail: string}
 */
function ldapTestTeampassAccount(string $login, array $SETTINGS, Language $lang): array
{
    $step = 'ldap_step_teampass_account';

    $user = DB::queryFirstRow(
        'SELECT id, login, disabled, auth_type FROM ' . prefixTable('users') . '
         WHERE login = %s AND deleted_at IS NULL',
        $login
    );

    if ($user === null) {
        DB::queryFirstRow(
            'SELECT id FROM ' . prefixTable('users') . ' WHERE login LIKE %s AND deleted_at IS NOT NULL',
            $login . '_deleted_%'
        );
        if (DB::count() > 0) {
            return ldapTestStep($step, 'ko', $lang->get('ldap_step_account_deleted_homonym'));
        }

        if ((int) ($SETTINGS['enable_ad_user_auto_creation'] ?? 0) !== 1) {
            return ldapTestStep($step, 'ko', $lang->get('ldap_step_account_missing_no_creation'));
        }

        return ldapTestStep($step, 'ok', $lang->get('ldap_step_account_will_be_created'));
    }

    if ((int) $user['disabled'] === 1) {
        return ldapTestStep($step, 'ko', $lang->get('ldap_step_account_disabled'));
    }

    if ($user['auth_type'] === 'local' && (int) ($SETTINGS['ldap_and_local_authentication'] ?? 0) !== 1) {
        return ldapTestStep($step, 'warning', $lang->get('ldap_step_account_is_local'));
    }

    return ldapTestStep($step, 'ok', $lang->get('ldap_step_account_exists'));
}

/**
 * Directory groups of the user, and how many of them are mapped to a TeamPass role.
 *
 * A login that succeeds but grants no folder is reported as a broken login; the cause is almost
 * always an unmapped directory group.
 *
 * @param array  $userADInfos User entry returned by the directory
 * @param array  $ldapHandler LDAP connection and handler
 * @param array  $SETTINGS    Teampass settings
 * @param string $username    Login being tested
 * @param Language $lang      Language instance
 *
 * @return array{code: string, status: string, detail: string}
 */
function ldapTestGroupMapping(array $userADInfos, array $ldapHandler, array $SETTINGS, string $username, Language $lang): array
{
    $step = 'ldap_step_groups';

    if ((int) ($SETTINGS['enable_ad_users_with_ad_groups'] ?? 0) !== 1) {
        return ldapTestStep($step, 'skipped', $lang->get('ldap_step_groups_disabled'));
    }

    try {
        $groupsData = getUserADGroups($userADInfos, $ldapHandler, $SETTINGS, $username);
    } catch (Exception $e) {
        return ldapTestStep($step, 'ko', $lang->get('ldap_step_groups_lookup_failed'));
    }

    if ($groupsData['error'] === true) {
        return ldapTestStep($step, 'ko', $lang->get('ldap_step_groups_lookup_failed'));
    }

    $groups = $groupsData['userGroups'];
    if (count($groups) === 0) {
        return ldapTestStep($step, 'warning', $lang->get('ldap_step_groups_none'));
    }

    // Same lookup as handleUserADGroups(), read-only: no role is granted nor removed here.
    $mapped = 0;
    foreach ($groups as $group) {
        if (is_string($group) === false || trim($group) === '') {
            continue;
        }
        DB::queryFirstRow(
            'SELECT role_id FROM ' . prefixTable('ldap_groups_roles') . ' WHERE ldap_group_id = %s',
            trim($group)
        );
        if (DB::count() > 0) {
            $mapped++;
        }
    }

    $detail = sprintf(
        '%s: %d — %s: %d',
        $lang->get('ldap_step_groups_found'),
        count($groups),
        $lang->get('ldap_step_groups_mapped'),
        $mapped
    );

    return ldapTestStep($step, $mapped === 0 ? 'warning' : 'ok', $detail);
}

/**
 * Run the LDAP settings page test along the real login path.
 *
 * Order and code are those of identify.php::authenticateThroughAD(): configuration audit,
 * connection, user search, account state, bind, expiration, login group restriction, then the
 * TeamPass-side gates the login applies once the directory said yes. Nothing is written: no user
 * is created, no role is granted, no session is opened.
 *
 * @param string   $login    Login to test
 * @param string   $password Password to test
 * @param array    $SETTINGS Teampass settings
 * @param Language $lang     Language instance
 *
 * @return array{error: bool, message: string, steps: array<int, array{code: string, status: string, detail: string}>, findings: array}
 */
function ldapRunConfigurationTest(string $login, string $password, array $SETTINGS, Language $lang): array
{
    $steps = [];
    $findings = ldapConfigAudit($SETTINGS);

    // A blocking configuration error is answered before any network call: the directory would
    // only return an opaque failure and hide the real cause.
    if (ldapConfigWorstSeverity($findings) === LDAP_CHECK_ERROR) {
        $steps[] = ldapTestStep('ldap_step_settings', 'ko', $lang->get('ldap_step_settings_blocking'));

        return [
            'error' => true,
            'message' => $lang->get('ldap_step_settings_blocking'),
            'steps' => $steps,
            'findings' => $findings,
        ];
    }

    $steps[] = ldapConfigWorstSeverity($findings) === LDAP_CHECK_WARNING
        ? ldapTestStep('ldap_step_settings', 'warning', $lang->get('ldap_step_settings_warnings'))
        : ldapTestStep('ldap_step_settings', 'ok');

    // 1 - Connection and service account bind
    try {
        $ldapHandler = initializeLdapConnection($SETTINGS);
    } catch (Exception $e) {
        if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
            error_log('TEAMPASS Error - ldap - ' . $e->getMessage());
        }
        $steps[] = ldapTestStep('ldap_step_connection', 'ko', $lang->get('ldap_step_connection_failed'));

        return [
            'error' => true,
            'message' => $lang->get('ldap_step_connection_failed'),
            'steps' => $steps,
            'findings' => $findings,
        ];
    }
    $steps[] = ldapTestStep('ldap_step_connection', 'ok');

    // 2 - Search, account state and bind: the login's own function, unchanged.
    // authenticateUser() only catches a missing entry; any other directory error would surface as
    // an HTTP 500 and tell the administrator nothing, so it is reported as a failed search here.
    $isActiveDirectory = $ldapHandler['type'] === 'ActiveDirectory';
    try {
        $authResult = authenticateUser($login, $password, $ldapHandler, $SETTINGS, $lang);
    } catch (\Throwable $e) {
        if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
            error_log('TEAMPASS Error - ldap - ' . $e->getMessage());
        }
        $authResult = [
            'error' => true,
            'stage' => 'search',
            'message' => $lang->get('error_bad_credentials'),
        ];
    }
    $stage = $authResult['stage'] ?? 'search';

    $searchDetail = sprintf(
        '%s: %s',
        $lang->get('settings_ldap_user_attribute'),
        ldapResolveUserAttribute($SETTINGS)
    );

    if ($stage === 'search') {
        $steps[] = ldapTestStep('ldap_step_user_search', 'ko', $searchDetail);
        $steps[] = ldapTestStep('ldap_step_user_enabled', 'skipped');
        $steps[] = ldapTestStep('ldap_step_bind', 'skipped');

        return [
            'error' => true,
            'message' => $authResult['message'],
            'steps' => $steps,
            'findings' => $findings,
        ];
    }

    $userADInfos = $authResult['user_info'];
    $steps[] = ldapTestStep(
        'ldap_step_user_search',
        'ok',
        $searchDetail . ' — ' . (string) ($userADInfos['dn'] ?? '')
    );

    $steps[] = $isActiveDirectory === false
        ? ldapTestStep('ldap_step_user_enabled', 'skipped', $lang->get('ldap_step_enabled_ad_only'))
        : ldapTestStep('ldap_step_user_enabled', $stage === 'disabled' ? 'ko' : 'ok');

    if ($stage === 'disabled') {
        $steps[] = ldapTestStep('ldap_step_bind', 'skipped');

        return [
            'error' => true,
            'message' => $authResult['message'],
            'steps' => $steps,
            'findings' => $findings,
        ];
    }

    $bindDetail = sprintf(
        '%s: %s',
        $lang->get('ldap_step_bind_identifier'),
        (string) ($authResult['bind_identifier'] ?? '')
    );

    if ($stage === 'bind') {
        $steps[] = ldapTestStep('ldap_step_bind', 'ko', $bindDetail);

        return [
            'error' => true,
            'message' => $authResult['message'],
            'steps' => $steps,
            'findings' => $findings,
        ];
    }
    $steps[] = ldapTestStep('ldap_step_bind', 'ok', $bindDetail);

    // 3 - Account expiration
    if (isAccountExpired($userADInfos) === true) {
        $steps[] = ldapTestStep('ldap_step_expiration', 'ko', $lang->get('error_ad_user_expired'));

        return [
            'error' => true,
            'message' => $lang->get('error_ad_user_expired'),
            'steps' => $steps,
            'findings' => $findings,
        ];
    }
    $steps[] = ldapTestStep('ldap_step_expiration', 'ok');

    // 4 - Login restricted to a group
    $allowedGroupDn = trim($SETTINGS['ldap_allowed_login_group_dn'] ?? '');
    if ($allowedGroupDn === '') {
        $steps[] = ldapTestStep('ldap_step_allowed_group', 'skipped', $lang->get('ldap_step_allowed_group_none'));
    } elseif (ldapUserIsInAllowedLoginGroup($userADInfos, $ldapHandler, $SETTINGS, $login) === false) {
        $steps[] = ldapTestStep('ldap_step_allowed_group', 'ko', $allowedGroupDn);

        return [
            'error' => true,
            'message' => $lang->get('ldap_not_in_allowed_group'),
            'steps' => $steps,
            'findings' => $findings,
        ];
    } else {
        $steps[] = ldapTestStep('ldap_step_allowed_group', 'ok', $allowedGroupDn);
    }

    // 5 - TeamPass-side gates, after the directory said yes
    $accountStep = ldapTestTeampassAccount($login, $SETTINGS, $lang);
    $steps[] = $accountStep;

    // 6 - Directory groups and role mapping
    $steps[] = ldapTestGroupMapping($userADInfos, $ldapHandler, $SETTINGS, $login, $lang);

    if ($accountStep['status'] === 'ko') {
        return [
            'error' => true,
            'message' => $accountStep['detail'],
            'steps' => $steps,
            'findings' => $findings,
        ];
    }

    return [
        'error' => false,
        'message' => $lang->get('ldap_step_login_would_succeed'),
        'steps' => $steps,
        'findings' => $findings,
    ];
}
