<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License v3.0.
 * See <https://www.gnu.org/licenses/>.
 */

require_once __DIR__ . '/../../app/sources/setting_value_logic.php';
require_once __DIR__ . '/../../app/sources/secret_settings_logic.php';

/**
 * Repair the admin settings whose accented letters were stored as named entities.
 *
 * The generic option save used FILTER_SANITIZE_FULL_SPECIAL_CHARS, which acts as
 * htmlentities(): "Société" was stored "Soci&eacute;t&eacute;" and reached e-mail sender names and
 * authenticator apps that way. The save is fixed (tpEncodeSettingValue()); this pass applies its
 * exact inverse to the values already stored. The HTML special characters stay encoded, so a
 * value printed into a page is as safe as before, and a clean table receives no write.
 *
 * Encrypted values and credentials are left alone: they are not stored by that save.
 *
 * @param mysqli $connection Database connection
 * @param string $miscTable  Name of the misc table, prefix included
 *
 * @return int Number of settings repaired
 *
 * @throws RuntimeException When the table cannot be read or written
 */
function tpRepairLegacySettingEntities(mysqli $connection, string $miscTable): int
{
    $miscTable = '`' . str_replace('`', '``', $miscTable) . '`';
    $rows = $connection->query(
        'SELECT increment_id, intitule, valeur FROM ' . $miscTable
        . " WHERE type = 'admin' AND is_encrypted = 0 AND valeur LIKE '%&%;%'"
    );
    if ($rows === false) {
        throw new RuntimeException('Unable to read the admin settings');
    }

    $updates = [];
    while ($row = $rows->fetch_assoc()) {
        if (in_array((string) $row['intitule'], tpSecretSettingNames(), true) === true) {
            continue;
        }

        // Decoding only shortens a value, so it always fits its column.
        $value = (string) $row['valeur'];
        $decoded = tpDecodeLegacySettingEntities($value);
        if ($decoded !== $value) {
            $updates[(int) $row['increment_id']] = $decoded;
        }
    }
    $rows->free();

    $statement = $connection->prepare('UPDATE ' . $miscTable . ' SET valeur = ? WHERE increment_id = ?');
    if ($statement === false) {
        throw new RuntimeException('Unable to update the admin settings');
    }
    foreach ($updates as $id => $value) {
        $statement->bind_param('si', $value, $id);
        if ($statement->execute() === false) {
            throw new RuntimeException('Unable to update the admin settings');
        }
    }
    $statement->close();

    return count($updates);
}
