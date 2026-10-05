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
 * @file      secure_send_snapshot.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Serialize a coherent item copy within the existing OTV TEXT column.
 *
 * Defuse v2 adds 84 bytes and returns hex, so JSON must fit in the remaining
 * half-column budget. Only description may be shortened; credentials never are.
 *
 * @param array $item Authorized item as stored in TeamPass
 * @param string $password Decrypted password
 * @param array{secret:string, algorithm:string, digits:int, period:int}|null $totp Enabled TOTP profile
 * @return array{plaintext:string, description_truncated:bool}
 * @throws InvalidArgumentException When required fields exceed the storage budget or contain invalid UTF-8
 */
function secureSendEncodeSnapshot(array $item, string $password, ?array $totp = null): array
{
    $payload = [
        'label' => (string) $item['label'], 'login' => (string) $item['login'],
        'url' => (string) $item['url'], 'password' => $password,
        'description' => (string) $item['description'], 'description_truncated' => false,
    ];
    if ($totp !== null) {
        $payload['totp'] = $totp;
    }
    $budget = intdiv(65535, 2) - \Defuse\Crypto\Core::MINIMUM_CIPHERTEXT_SIZE;
    $encode = static fn (array $value): string => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    try {
        $plaintext = $encode($payload);
        if (strlen($plaintext) <= $budget) {
            return ['plaintext' => $plaintext, 'description_truncated' => false];
        }
        $description = $payload['description'];
        $payload['description'] = '';
        $payload['description_truncated'] = true;
        if (strlen($encode($payload)) > $budget) {
            throw new InvalidArgumentException('invalid_payload');
        }
        // Search byte lengths, cutting only at UTF-8 boundaries and measuring escaped JSON.
        $low = 0;
        $high = min(strlen($description), $budget);
        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);
            $payload['description'] = mb_strcut($description, 0, $middle, 'UTF-8');
            if (strlen($encode($payload)) <= $budget) {
                $low = $middle;
            } else {
                $high = $middle - 1;
            }
        }
        $cut = mb_strcut($description, 0, $low, 'UTF-8');
        // Stored descriptions are entity-encoded; do not leave a partial trailing entity.
        $amp = strrpos($cut, '&');
        if ($amp !== false && strlen($cut) - $amp <= 10 && strpos($cut, ';', $amp) === false) {
            $cut = substr($cut, 0, $amp);
        }
        $payload['description'] = $cut;
        return ['plaintext' => $encode($payload), 'description_truncated' => true];
    } catch (JsonException $e) {
        throw new InvalidArgumentException('invalid_payload');
    }
}
