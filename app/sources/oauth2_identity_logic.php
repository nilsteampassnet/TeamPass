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
 * DB-free decisions binding a TeamPass account to the identity an OAuth2 provider
 * asserts (issuer key + subject), instead of to a name claim.
 *
 * @file      oauth2_identity_logic.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Fingerprint of a provider identity, stored in users.oauth2_identity.
 *
 * The NUL separator keeps ('a', 'bc') and ('ab', 'c') apart. Nothing secret goes in:
 * the subject is an identifier, not a credential.
 *
 * @param string $issuerKey Issuer key ('entra', or the issuer of a generic provider)
 * @param string $subject   Immutable subject (Entra object id, OpenID Connect sub)
 *
 * @return string 64 lowercase hex characters, or '' when either part is empty
 */
function oauth2IdentityHash(string $issuerKey, string $subject): string
{
    if ($issuerKey === '' || $subject === '') {
        return '';
    }

    return hash('sha256', $issuerKey . "\0" . $subject);
}

/**
 * Fingerprint of the identity held by the provider answer kept in the session.
 *
 * OAuth2Controller::callback() writes identityIssuer and identitySubject from what the
 * provider returned to the server; the browser never supplies them.
 *
 * @param mixed $oauth2Info Session value 'userOauth2Info'
 *
 * @return string The fingerprint, or '' when the answer carries no usable identity
 */
function oauth2IdentityFromSessionInfo(mixed $oauth2Info): string
{
    if (is_array($oauth2Info) === false) {
        return '';
    }

    $issuerKey = $oauth2Info['identityIssuer'] ?? null;
    $subject = $oauth2Info['identitySubject'] ?? null;
    if (is_string($issuerKey) === false || is_string($subject) === false) {
        return '';
    }

    return oauth2IdentityHash($issuerKey, $subject);
}

/**
 * Decide what an OAuth2 sign-in may do with the TeamPass account whose login it matched.
 *
 * - 'accept': the account is already bound to this identity;
 * - 'bind': the account is not bound yet (OAuth2 account created before the binding existed,
 *   imported by the OAuth2 synchronization or reset by an administrator), or it is a local/LDAP
 *   account the administrator lets OAuth2 take over: the identity is recorded once the sign-in
 *   has completed;
 * - 'refuse_mismatch': the account belongs to another identity;
 * - 'refuse_link_disabled': a local/LDAP account while linking existing accounts is off;
 * - 'refuse_no_identity': the provider answer carries no identity.
 *
 * @param array<string, mixed> $account              Users row: auth_type, oauth2_identity
 * @param string               $sessionIdentity      oauth2IdentityFromSessionInfo() result
 * @param bool                 $linkExistingAccounts Setting oauth2_link_existing_accounts
 *
 * @return string One of the decisions listed above
 */
function oauth2IdentityDecision(array $account, string $sessionIdentity, bool $linkExistingAccounts): string
{
    if ($sessionIdentity === '') {
        return 'refuse_no_identity';
    }

    $boundIdentity = is_string($account['oauth2_identity'] ?? null) ? (string) $account['oauth2_identity'] : '';
    if ($boundIdentity !== '' && hash_equals($boundIdentity, $sessionIdentity) === false) {
        return 'refuse_mismatch';
    }

    if ((string) ($account['auth_type'] ?? '') === 'oauth2') {
        return $boundIdentity === '' ? 'bind' : 'accept';
    }

    // Any other authentication type is taken over by OAuth2 only when the administrator allows it
    return $linkExistingAccounts === true ? 'bind' : 'refuse_link_disabled';
}

/**
 * Language key (also the log label) explaining why a sign-in was refused.
 *
 * @param string $decision oauth2IdentityDecision() result
 *
 * @return string The key, or '' when the decision does not refuse
 */
function oauth2IdentityRefusalKey(string $decision): string
{
    return match ($decision) {
        'refuse_mismatch' => 'oauth2_identity_mismatch',
        'refuse_link_disabled' => 'oauth2_link_disabled',
        'refuse_no_identity' => 'oauth2_identity_missing',
        default => '',
    };
}
