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
 * At-rest sealing of the transparent key recovery backup (users.private_key_backup), kept
 * free of any database or session access so it can be unit-tested on its own.
 *
 * The backup is AES-encrypted with a key derived from user_derivation_seed and public_key,
 * two columns of the same users row: a database dump alone was enough to recover every
 * private key holding one (GHSA-fv78-jwjv-pj25). The backup is therefore sealed with the
 * instance key (SECUREFILE), the Defuse key that already protects users.pw and
 * log_items.old_value, so the database no longer opens it on its own.
 *
 * Sealing wraps the stored ciphertext without opening it: the upgrade converts every
 * existing backup without ever handling a private key.
 *
 * It is included by both:
 *   - app/sources/main.functions.php           (encryptPrivateKeyBackup(), decryptPrivateKeyBackup())
 *   - tests/Unit/PrivateKeyBackupSealTest.php  (unit tests)
 *
 * @file      private_key_backup_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;

/**
 * Prefix of a sealed backup. A legacy backup is plain base64, which never contains ':'.
 */
const PRIVATE_KEY_BACKUP_SEAL_PREFIX = 'sealed:v1:';

/**
 * Tell whether a stored private_key_backup is sealed with the instance key.
 *
 * @param string $storedBackup Value of users.private_key_backup.
 *
 * @return bool
 */
function privateKeyBackupIsSealed(string $storedBackup): bool
{
    return strncmp($storedBackup, PRIVATE_KEY_BACKUP_SEAL_PREFIX, strlen(PRIVATE_KEY_BACKUP_SEAL_PREFIX)) === 0;
}

/**
 * Seal a backup with the instance key.
 *
 * An already sealed value is returned unchanged, so the upgrade step can be replayed.
 *
 * @param string $backup   Base64 backup ciphertext, or an already sealed value.
 * @param string $asciiKey Instance Defuse key, ASCII-safe encoded (SECUREFILE content).
 *
 * @return string Value to store in users.private_key_backup.
 *
 * @throws \Defuse\Crypto\Exception\CryptoException When the key cannot be loaded.
 */
function privateKeyBackupSeal(string $backup, string $asciiKey): string
{
    if (privateKeyBackupIsSealed($backup) === true) {
        return $backup;
    }

    return PRIVATE_KEY_BACKUP_SEAL_PREFIX . Crypto::encrypt($backup, Key::loadFromAsciiSafeString($asciiKey));
}

/**
 * Open a stored backup down to its base64 ciphertext.
 *
 * A legacy value is returned unchanged, so recovery keeps working on a row the upgrade has
 * not converted yet; the next write of the backup seals it.
 *
 * @param string $storedBackup Value of users.private_key_backup.
 * @param string $asciiKey     Instance Defuse key, ASCII-safe encoded (SECUREFILE content).
 *
 * @return string Base64 backup ciphertext.
 *
 * @throws \Defuse\Crypto\Exception\CryptoException When the value was sealed with another key
 *                                                  or has been altered.
 */
function privateKeyBackupUnseal(string $storedBackup, string $asciiKey): string
{
    if (privateKeyBackupIsSealed($storedBackup) === false) {
        return $storedBackup;
    }

    return Crypto::decrypt(
        substr($storedBackup, strlen(PRIVATE_KEY_BACKUP_SEAL_PREFIX)),
        Key::loadFromAsciiSafeString($asciiKey)
    );
}
