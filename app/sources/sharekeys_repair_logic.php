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
 * Decision logic of the "Restore missing sharekeys" tool, kept free of any database or session
 * access so it can be unit-tested on its own — same pattern as item_revisions_logic.php.
 *
 * It is included by both:
 *   - app/sources/main.functions.php              (Tools handlers and the background repair task)
 *   - tests/Unit/SharekeysRepairLogicTest.php     (unit tests)
 *
 * A sharekey only proves that an object key was encrypted for a user, never that it is the key
 * the object is currently encrypted with: an object re-encrypted while its key distribution
 * failed leaves the other users, the internal TP account included, with the previous key. The
 * repair therefore checks every object key against the ciphertext before trusting it.
 *
 * @file      sharekeys_repair_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Tell whether the decryption of an object's ciphertext with a candidate object key proves that
 * key is the right one.
 *
 * - An empty ciphertext has nothing to check against: any key is accepted.
 * - The AES v2 format (non-empty meta) is authenticated: a successful decryption is a proof.
 * - The legacy AES-CBC format is not authenticated: a wrong key still produces a valid padding
 *   about once in 256 tries, with random bytes as plaintext. A plaintext that is not valid UTF-8
 *   is rejected, which makes such a false positive negligible.
 *
 * @param bool   $success      Decryption status, as returned by doDataDecryptionWithStatus()
 * @param string $plaintextB64 Decrypted value, base64-encoded (doDataDecryptionWithStatus()['string'])
 * @param string $ciphertext   Stored ciphertext (items.pw, categories_items.data)
 * @param string $meta         Stored AES v2 metadata (items.pw_iv, categories_items.data_iv)
 *
 * @return bool
 */
function sharekeyRepairDecryptionProvesKey(bool $success, string $plaintextB64, string $ciphertext, string $meta): bool
{
    if ($ciphertext === '') {
        return true;
    }
    if ($success === false) {
        return false;
    }
    if ($meta !== '') {
        return true;
    }

    $plaintext = base64_decode($plaintextB64, true);
    if ($plaintext === false) {
        return false;
    }

    return $plaintext === '' || mb_check_encoding($plaintext, 'UTF-8') === true;
}

/**
 * Decide what the reference-key pass does with the internal TP account key of one object.
 *
 * "Opens" means the decryption succeeds (valid padding, or authenticated AES v2); "proves" adds
 * that the plaintext is plausible (sharekeyRepairDecryptionProvesKey()). Opening is enough to keep
 * or create a key, as the tool did before checking anything. Dropping the other users' keys needs
 * the stronger proof, and a source key different from the TP one: data stored corrupted decrypts
 * to the same garbage with every key, and must never be mistaken for a stale distribution.
 *
 * - 'keep'            TP holds a current (v3) key that opens the object: nothing to do.
 * - 'create'          TP holds no current key (missing, empty or legacy v1), or one it cannot
 *                     decrypt at all - the TP key pair changed since, which says nothing about the
 *                     other users' keys. Only the TP key is written from the source key; the
 *                     background task then fills the missing user keys.
 * - 'replace'         TP holds a current key that decrypts to another object key, which does not
 *                     open the object: the object was re-encrypted and that distribution never
 *                     completed. The users who received their key from it hold the same stale key,
 *                     so the TP key is overwritten and the other user keys are dropped, to be
 *                     recreated from it.
 * - 'source_unusable' The source user's key is not good enough for the case above: the object
 *                     needs another source user, or is lost.
 *
 * @param bool $tpHasCurrentKey TP holds a non-empty v3 sharekey on the object
 * @param bool $tpKeyDecrypts   That sharekey decrypts to a non-empty object key
 * @param bool $tpKeyOpens      That object key opens the object
 * @param bool $sourceKeyOpens  The source user's object key opens the object
 * @param bool $sourceKeyProves The source user's object key proves itself on the object
 * @param bool $sameObjectKey   The source and TP object keys are identical
 *
 * @return string One of 'keep', 'create', 'replace', 'source_unusable'
 */
function sharekeyRepairReferenceAction(
    bool $tpHasCurrentKey,
    bool $tpKeyDecrypts,
    bool $tpKeyOpens,
    bool $sourceKeyOpens,
    bool $sourceKeyProves,
    bool $sameObjectKey
): string {
    if ($tpHasCurrentKey === true && $tpKeyDecrypts === true) {
        if ($tpKeyOpens === true) {
            return 'keep';
        }

        return $sourceKeyProves === true && $sameObjectKey === false ? 'replace' : 'source_unusable';
    }

    return $sourceKeyOpens === true ? 'create' : 'source_unusable';
}

/**
 * Name why the private key of the internal TP account cannot be opened, from the first link of
 * the chain that breaks: users row -> users.pw (Defuse, instance key) -> current row of
 * user_private_keys -> private key opened with that password.
 *
 * Each cause calls for a different repair, so they are never reported as one.
 *
 * @param bool $accountFound      The TP account row exists
 * @param bool $passwordDecrypted Its password decrypts with the instance key of this server
 * @param bool $privateKeyStored  It has a non-empty current private key
 * @param bool $privateKeyOpens   That private key opens with the password
 *
 * @return string One of 'ok', 'account_missing', 'password_unreadable', 'private_key_missing',
 *                'private_key_unreadable'
 */
function tpUserKeyStatus(bool $accountFound, bool $passwordDecrypted, bool $privateKeyStored, bool $privateKeyOpens): string
{
    if ($accountFound === false) {
        return 'account_missing';
    }
    if ($passwordDecrypted === false) {
        return 'password_unreadable';
    }
    if ($privateKeyStored === false) {
        return 'private_key_missing';
    }

    return $privateKeyOpens === true ? 'ok' : 'private_key_unreadable';
}

/**
 * Language key explaining a tpUserKeyStatus() result to an administrator.
 *
 * @param string $status tpUserKeyStatus() result
 *
 * @return string Language key, '' for 'ok'
 */
function tpUserKeyStatusMessageKey(string $status): string
{
    $keys = [
        'account_missing' => 'tp_key_status_account_missing',
        'password_unreadable' => 'tp_key_status_password_unreadable',
        'private_key_missing' => 'tp_key_status_private_key_missing',
        'private_key_unreadable' => 'tp_key_status_private_key_unreadable',
    ];

    return $status === 'ok' ? '' : ($keys[$status] ?? 'tp_key_status_private_key_unreadable');
}

/**
 * Tell whether a user's keys must not be generated again while the TP account key is broken.
 *
 * The generation re-keys every object the user can read through the TP account. Without its key
 * the user gets a key pair that opens nothing, and the old pair, which still opened everything,
 * is gone. That loss is refused. A user with nothing to lose (a new account, or one whose keys
 * are already gone) is let through, and so is a change that keeps the key pair: the background
 * task then writes nothing, so the existing sharekeys stay valid.
 *
 * @param string $tpKeyStatus    tpUserKeyStatus() result
 * @param bool   $keepsKeyPair   The generation re-encrypts the current key pair instead of replacing it
 * @param bool   $holdsSharekeys The user holds at least one non-empty sharekey
 *
 * @return bool
 */
function userKeysGenerationBlockedByTpKey(string $tpKeyStatus, bool $keepsKeyPair, bool $holdsSharekeys): bool
{
    return $tpKeyStatus !== 'ok' && $keepsKeyPair === false && $holdsSharekeys === true;
}
