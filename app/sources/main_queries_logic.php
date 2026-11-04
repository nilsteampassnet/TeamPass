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
 * DB-free validation shared by the main AJAX handler and its unit tests.
 *
 * @file      main_queries_logic.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Normalize the payload decoded by mainQuery() to the array every handler expects.
 *
 * prepareExchangedData(..., 'decode') returns an empty string when no data was posted
 * and when the decryption failed, and json_decode() returns null or a scalar for a
 * payload that is not a JSON object. Reading an offset on any of those values is a
 * fatal TypeError in PHP 8 ("Cannot access offset of type string on string"), which
 * terminates the request instead of rejecting it cleanly. Normalizing once, before the
 * dispatch, keeps every handler on the array contract they all already assume.
 *
 * @param mixed $dataReceived Raw result of prepareExchangedData(..., 'decode')
 *
 * @return array<array-key, mixed> The decoded payload, or an empty array when unusable
 */
function mainQueryNormalizeReceivedData(mixed $dataReceived): array
{
    return is_array($dataReceived) === true ? $dataReceived : [];
}

/**
 * Validate a request that records the current user's IP address.
 *
 * The payload is expected to be already normalized by mainQueryNormalizeReceivedData(),
 * so an unusable payload reaches this function as an empty array and is rejected here.
 *
 * @param array<array-key, mixed> $dataReceived Decoded request payload
 * @param string                  $postKey      Key sent by the client
 * @param string                  $sessionKey   Current server-side session key
 *
 * @return bool True only for a complete request from the current session
 */
function isSaveUserLocationRequestValid(
    array $dataReceived,
    string $postKey,
    string $sessionKey
): bool {
    if ($sessionKey === '' || hash_equals($sessionKey, $postKey) === false) {
        return false;
    }

    return ($dataReceived['action'] ?? null) === 'perform';
}

/**
 * List the forms under which a TeamPass 2.x personal saltkey may have protected the user key.
 *
 * TeamPass 2.x never used the saltkey as typed. Its first definition (store_personal_saltkey)
 * went through the client sanitizeString() then FILTER_SANITIZE_STRING; a later change
 * (change_personal_saltkey) went through sanitizeString() then htmlspecialchars_decode(). A
 * saltkey holding a backslash, a quote or a "<" therefore only unlocks under one of those
 * forms. The saltkey as typed comes first, and duplicates are removed: a saltkey without
 * those characters costs a single key derivation.
 *
 * @param string $psk Saltkey as typed by the user
 *
 * @return list<string> Distinct non-empty candidates, the saltkey as typed first
 */
function legacyPersonalSaltkeyCandidates(string $psk): array
{
    // sanitizeString() from the 2.x includes/js/functions.js
    $clientEncoded = (string) preg_replace(
        '#\s*<script[^>]*>[\s\S]*?</script>\s*#i',
        '',
        str_replace(['\\', '"'], ['&#92;', '&quot;'], $psk)
    );

    // FILTER_SANITIZE_STRING, removed from PHP: quotes encoded, then tags stripped. Unlike
    // strip_tags(), the filter also takes a "<" followed by a whitespace as a tag.
    $firstDefinition = strip_tags((string) preg_replace(
        '/<(?=\s)/',
        '<x',
        str_replace(["'", '"'], ['&#39;', '&#34;'], $clientEncoded)
    ));

    // PHP 7 default flags
    $afterChange = htmlspecialchars_decode($clientEncoded, ENT_COMPAT | ENT_HTML401);

    return array_values(array_unique(array_filter(
        [$psk, $firstDefinition, $afterChange],
        static fn (string $candidate): bool => $candidate !== ''
    )));
}

/**
 * List the forms under which a previous password may have protected a user private key.
 *
 * TeamPass 3.0.x encrypted the private key with the password after
 * FILTER_SANITIZE_FULL_SPECIAL_CHARS, which turns & " ' < > and accented letters into HTML
 * entities (#5389). A previous password holding one of them therefore only unlocks a 3.0.x key
 * under that form. The password as typed comes first, and duplicates are removed: a password
 * without those characters costs a single key derivation.
 *
 * @param string $password Previous password as typed by the user
 *
 * @return list<string> Distinct non-empty candidates, the password as typed first
 */
function legacyPreviousPasswordCandidates(string $password): array
{
    return array_values(array_unique(array_filter(
        [$password, (string) filter_var($password, FILTER_SANITIZE_FULL_SPECIAL_CHARS)],
        static fn (string $candidate): bool => $candidate !== ''
    )));
}

/**
 * Decide how a batch of the 2.x personal items re-encryption ends.
 *
 * The saltkey protects the only copy of the 2.x user key. It is erased only once no personal
 * item is left to re-encrypt: an item that could not be decrypted keeps its 2.x ciphertext,
 * and erasing the saltkey would make it unrecoverable.
 *
 * @param int $batchSize      Items read by this batch
 * @param int $batchLength    Maximum number of items per batch
 * @param int $remainingItems Personal items still not re-encrypted after this batch
 *
 * @return array{finished: bool, clear_saltkey: bool}
 */
function personalItemsReencryptionOutcome(int $batchSize, int $batchLength, int $remainingItems): array
{
    // A batch shorter than requested means every item has been read
    $finished = $remainingItems === 0 || $batchSize < $batchLength;

    return [
        'finished' => $finished,
        'clear_saltkey' => $remainingItems === 0,
    ];
}

/**
 * Tell whether a new password holds a word it may not contain: the login, the name, the
 * lastname or a part of the e-mail address of its owner.
 *
 * A word of one or two characters is ignored, the threshold Active Directory applies to the
 * same rule. Without it, a user called "Li", or whose lastname is a single letter, is refused
 * every password holding those letters.
 *
 * @param string                  $password       Candidate password
 * @param array<array-key, mixed> $forbiddenWords Words taken from the account, empty ones included
 *
 * @return bool True when the password holds one of the words
 */
function passwordHoldsForbiddenWord(string $password, array $forbiddenWords): bool
{
    foreach ($forbiddenWords as $word) {
        $word = is_scalar($word) === true ? (string) $word : '';
        if (mb_strlen($word) < 3) {
            continue;
        }

        if (stripos($password, $word) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Tell whether a key generation may replace the account password with a generated one.
 *
 * A generated password is handed back to the caller in clear text, so it never replaces the
 * password of the caller's own account: there it would let whoever holds the session - a
 * stolen cookie, an unattended browser - take the account over without knowing its password
 * (GHSA-p84q-3h9h-qvh6). Only a user who manages another account, already authorized by
 * keyHandler() for the explicit target, gets one.
 *
 * @param bool $targetProvided Whether the request named a target account (user_id)
 * @param int  $targetUserId   Account whose keys are generated
 * @param int  $sessionUserId  Account of the caller
 * @param bool $requested      Whether the request asked for a generated password
 *
 * @return bool True when a password may be generated for the target
 */
function userKeysGenerationMayGeneratePassword(
    bool $targetProvided,
    int $targetUserId,
    int $sessionUserId,
    bool $requested
): bool {
    return $requested === true
        && $targetProvided === true
        && $targetUserId > 0
        && $targetUserId !== $sessionUserId;
}

/**
 * Compute the end of a session extended by the user.
 *
 * The extension is capped at the administrator's maximum session duration counted from now,
 * the limit the extension dialog already offers. A duration that is not positive leaves the
 * session unchanged, and the result never shortens it.
 *
 * @param int $currentEnd     Current end of the session (timestamp)
 * @param int $duration       Requested extension in seconds
 * @param int $now            Current timestamp
 * @param int $maximumMinutes Setting maximum_session_expiration_time, in minutes
 *
 * @return int New end of the session (timestamp)
 */
function sessionExtensionEnd(int $currentEnd, int $duration, int $now, int $maximumMinutes): int
{
    if ($duration <= 0) {
        return $currentEnd;
    }

    $ceiling = $now + max(0, $maximumMinutes) * 60;

    return max($currentEnd, min($currentEnd + $duration, $ceiling));
}

/**
 * Password protecting the encryption keys of the signed-in OAuth2 account.
 *
 * An OAuth2 user never types a TeamPass password: the login page derives one from the
 * provider subject (hashUserId() in core/load.js.php, applied to `id`, or to `sub` when the
 * provider sends no `id`: the first 16 hex characters of its SHA-256). Once signed in, the
 * browser no longer holds that subject, so the dialogs that need the password ask the
 * server, which still has the provider's answer in the session.
 *
 * The subject is used only when it belongs to the signed-in account: its login (the part
 * before '@' of userPrincipalName, or the whole value) must match the session login, the
 * rule oauth2SessionSubjectLogin() applies at sign-in.
 *
 * @param mixed  $oauth2Info   Session value 'userOauth2Info', written by core/login.php.
 * @param string $sessionLogin Session value 'user-login'.
 *
 * @return string The derived password, or '' when it cannot be derived.
 */
function oauth2KeyPasswordFromSession(mixed $oauth2Info, string $sessionLogin): string
{
    if (is_array($oauth2Info) === false) {
        return '';
    }

    // Same precedence as the login page: id ?? sub
    $subject = $oauth2Info['id'] ?? $oauth2Info['sub'] ?? null;
    if (is_string($subject) === false || $subject === '') {
        return '';
    }

    $principal = $oauth2Info['userPrincipalName'] ?? null;
    if (is_string($principal) === false || trim($principal) === '') {
        return '';
    }
    $localPart = strstr($principal, '@', true);
    $subjectLogin = trim($localPart === false ? $principal : $localPart);

    // The session login went through FILTER_SANITIZE_FULL_SPECIAL_CHARS at sign-in
    $sessionLogin = trim(html_entity_decode($sessionLogin, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($sessionLogin === '' || strcasecmp($subjectLogin, $sessionLogin) !== 0) {
        return '';
    }

    return substr(hash('sha256', $subject), 0, 16);
}
