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
