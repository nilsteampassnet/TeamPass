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
