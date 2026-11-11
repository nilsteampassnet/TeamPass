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
 * DB-free decisions of the "Test a user" check of the OAuth page: what the next SSO sign-in of
 * a Microsoft Entra ID user would do with TeamPass, and what the directory answers for each
 * entry of the self-registration list. The checks follow the order of the sign-in
 * (identify.php), so that the first refusal reported is the one the user would meet.
 *
 * @file      oauth2_test_user_logic.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

require_once __DIR__ . '/oauth2_identity_logic.php';

/**
 * TeamPass login a user of the identity provider signs in with (app/core/login.php): the part
 * of the principal name before '@', or the whole name when it holds none.
 *
 * @param string $principalName userPrincipalName (Entra) or preferred_username
 *
 * @return string
 */
function oauth2LoginFromPrincipalName(string $principalName): string
{
    $localPart = strstr($principalName, '@', true);

    return $localPart === false ? $principalName : $localPart;
}

/**
 * What the next SSO sign-in of a directory user would do.
 *
 * Context keys:
 * - oauth2_enabled (bool): setting oauth2_enabled;
 * - directory_enabled (bool|null): accountEnabled of the directory user, null when unknown;
 * - login (string): TeamPass login the user signs in with;
 * - account (array|null): users row the login locates (id, login, auth_type, oauth2_identity,
 *   disabled), null when there is none;
 * - reserved_account (bool): the account is the built-in administrator or an internal account;
 * - identity (string): oauth2IdentityHash() of the directory user;
 * - bound_elsewhere (string): login of another account already linked to that identity, or '';
 * - link_existing_accounts (bool): setting oauth2_link_existing_accounts;
 * - ldap_mode (bool): setting ldap_mode;
 * - deleted_homonym (bool): a deleted account kept under "<login>_deleted_..." exists;
 * - may_register (bool): oauth2UserMayRegister() for the groups of the user.
 *
 * @param array<string, mixed> $context See above
 *
 * @return array{accepted: bool, key: string, tokens: array<string, string>} The language key
 *         explaining the outcome, and the values of its #login#, #auth_type# and #bound# tokens
 */
function oauth2TestSignInOutcome(array $context): array
{
    $account = is_array($context['account'] ?? null) ? $context['account'] : null;
    $tokens = [
        '#login#' => $account !== null ? (string) ($account['login'] ?? '') : (string) ($context['login'] ?? ''),
        '#auth_type#' => $account !== null ? (string) ($account['auth_type'] ?? '') : '',
        '#bound#' => (string) ($context['bound_elsewhere'] ?? ''),
    ];
    $outcome = static fn (bool $accepted, string $key): array => [
        'accepted' => $accepted,
        'key' => $key,
        'tokens' => $tokens,
    ];

    if (($context['oauth2_enabled'] ?? false) !== true) {
        return $outcome(false, 'oauth2_test_oauth2_disabled');
    }
    // Entra refuses the sign-in itself: TeamPass is never reached
    if (($context['directory_enabled'] ?? null) === false) {
        return $outcome(false, 'oauth2_test_directory_disabled');
    }

    $boundElsewhere = $tokens['#bound#'] !== '';

    if ($account === null) {
        // initialChecks::getUserInfo(), then checkOauth2User() and createOauth2User()
        if (($context['deleted_homonym'] ?? false) === true) {
            return $outcome(false, 'oauth2_test_deleted_homonym');
        }
        if (($context['may_register'] ?? false) !== true) {
            return $outcome(false, 'oauth2_test_self_register_refused');
        }
        if ($boundElsewhere === true) {
            return $outcome(false, 'oauth2_test_already_bound');
        }

        return $outcome(true, 'oauth2_test_create');
    }

    // shouldUserAuthWithOauth2(): these accounts never sign in through SSO
    if (($context['reserved_account'] ?? false) === true) {
        return $outcome(false, 'oauth2_test_reserved_account');
    }
    // identifyDoLDAPChecks() runs first and binds to LDAP with the derived password
    if ($tokens['#auth_type#'] === 'ldap' && ($context['ldap_mode'] ?? false) === true) {
        return $outcome(false, 'oauth2_test_ldap_first');
    }

    $decision = oauth2IdentityDecision(
        $account,
        (string) ($context['identity'] ?? ''),
        ($context['link_existing_accounts'] ?? false) === true
    );
    if ($decision === 'refuse_mismatch') {
        return $outcome(false, 'oauth2_test_mismatch');
    }
    if ($decision === 'refuse_link_disabled') {
        return $outcome(false, 'oauth2_test_link_disabled');
    }
    if ($decision === 'refuse_no_identity') {
        return $outcome(false, 'oauth2_identity_missing');
    }
    if ($decision === 'bind' && $boundElsewhere === true) {
        return $outcome(false, 'oauth2_test_already_bound');
    }
    // identifyUser(): the credentials are right, the account is locked
    if ((int) ($account['disabled'] ?? 0) === 1) {
        return $outcome(false, 'account_is_locked');
    }

    if ($decision === 'accept') {
        return $outcome(true, 'oauth2_test_accept');
    }

    return $outcome(true, $tokens['#auth_type#'] === 'oauth2' ? 'oauth2_test_bind' : 'oauth2_test_convert');
}

/**
 * Language key describing what the directory answered for an entry of the self-registration list.
 *
 * @param array<int, mixed> $matches Groups the directory returned for the entry
 *
 * @return string '' when exactly one group answers
 */
function oauth2TestGroupEntryKey(array $matches): string
{
    $count = count($matches);
    if ($count === 0) {
        return 'oauth2_test_group_not_found';
    }

    return $count > 1 ? 'oauth2_test_group_several' : '';
}

/**
 * Replace the #token# placeholders of a translated text.
 *
 * @param string                $text   Translated text
 * @param array<string, string> $tokens Placeholder => value
 *
 * @return string
 */
function oauth2TestText(string $text, array $tokens): string
{
    return str_replace(array_keys($tokens), array_values($tokens), $text);
}
