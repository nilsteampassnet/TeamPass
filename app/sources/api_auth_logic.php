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
 * DB-free decisions naming why an API authentication is refused.
 *
 * The API always answers a refusal with the same "Invalid credentials" message, so the client
 * cannot enumerate accounts. The exact cause only goes to log_system, for administrators: these
 * functions choose that log label.
 *
 * @file      api_auth_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Every label the API writes on a failed authentication, current and historical.
 *
 * The logs page relies on this list to tell API rows from web rows (logsApiFailureLabels()).
 * Keep a label here after it stops being written: older log rows still carry it.
 *
 * @return array<int, string>
 */
function apiAuthFailureLabels(): array
{
    return [
        // Historical: one label for several causes, kept for older rows
        'api_invalid_credentials',
        // Account state
        'api_user_unknown',
        'api_user_disabled',
        'api_access_not_enabled',
        'api_token_auth_type_not_allowed',
        // Password path
        'api_invalid_password',
        'api_invalid_password_ldap',
        'api_invalid_password_oauth2',
        'api_private_key_needs_recrypt',
        'api_private_key_unavailable',
        'api_invalid_apikey',
        // Token path
        'api_invalid_token',
        'api_token_decrypt_failed',
        'api_token_key_outdated',
    ];
}

/**
 * Name the account-state reason an API authentication is refused, before any secret is checked.
 *
 * The checks run in a fixed order and the first match wins: an unknown account, then a disabled
 * one (enabling its API access would fix nothing), then API access not enabled, then, on the
 * token path only, an account type that tokens are not allowed for. None of them depends on the
 * submitted password, which is not verified on a refused account.
 *
 * @param array<string, mixed>|null $userInfo          Row from getUserCompleteData(), null when unknown
 * @param bool                      $tokenPath         True for /authorizeToken
 * @param bool                      $tokenAllAuthTypes Setting extension_token_all_auth_types is on
 *
 * @return string Log label, or '' when the account may go on to credential verification
 */
function apiAuthAccountRefusalReason(?array $userInfo, bool $tokenPath, bool $tokenAllAuthTypes): string
{
    if ($userInfo === null) {
        return 'api_user_unknown';
    }

    if ((int) ($userInfo['disabled'] ?? 0) === 1) {
        return 'api_user_disabled';
    }

    // A user without a row in the api table has a NULL flag: no access either
    if ((int) ($userInfo['api_enabled'] ?? 0) === 0) {
        return 'api_access_not_enabled';
    }

    if ($tokenPath === true
        && $tokenAllAuthTypes === false
        && (string) ($userInfo['auth_type'] ?? '') !== 'oauth2'
    ) {
        return 'api_token_auth_type_not_allowed';
    }

    return '';
}

/**
 * Name the reason a submitted password does not match the stored hash.
 *
 * The API compares with the hash TeamPass keeps locally and never contacts the directory. For an
 * LDAP account that hash is only refreshed by a web sign-in, so a directory password changed since
 * then is refused. An OAuth2 account has no password its owner knows: it must use a token.
 *
 * @param string $authType users.auth_type
 *
 * @return string Log label
 */
function apiAuthPasswordMismatchReason(string $authType): string
{
    if ($authType === 'ldap') {
        return 'api_invalid_password_ldap';
    }

    if ($authType === 'oauth2') {
        return 'api_invalid_password_oauth2';
    }

    return 'api_invalid_password';
}

/**
 * Name the reason a correct password could not decrypt the user's private key.
 *
 * 'recrypt-private-key' is set when a directory password changed and TeamPass could not re-encrypt
 * the key on its own: the key is still encrypted with the previous password until the user
 * completes the prompt shown after a web sign-in.
 *
 * @param string $special users.special
 *
 * @return string Log label
 */
function apiAuthPrivateKeyUnavailableReason(string $special): string
{
    return $special === 'recrypt-private-key'
        ? 'api_private_key_needs_recrypt'
        : 'api_private_key_unavailable';
}
