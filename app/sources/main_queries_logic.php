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
 * Validate a request that records the current user's IP address.
 *
 * @param array<string, mixed>|null|string $dataReceived Decoded request payload
 * @param string                          $postKey      Key sent by the client
 * @param string                          $sessionKey   Current server-side session key
 *
 * @return bool True only for a complete request from the current session
 */
function isSaveUserLocationRequestValid(
    array|null|string $dataReceived,
    string $postKey,
    string $sessionKey
): bool {
    if (
        $sessionKey === ''
        || hash_equals($sessionKey, $postKey) === false
        || is_array($dataReceived) === false
    ) {
        return false;
    }

    return ($dataReceived['action'] ?? null) === 'perform';
}
