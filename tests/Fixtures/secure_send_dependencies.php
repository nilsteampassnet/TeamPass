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
 * @file      secure_send_dependencies.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Defuse\Crypto\KeyProtectedByPassword;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;

// Explicit dependency adapters: real Defuse encryption, without booting a vault
// or extracting/evaluating functions from main.functions.php.
if (!defined('OTV_USER_ID')) {
    define('OTV_USER_ID', 9999991);
}

/** Match the production wrapper's ciphertext/result contract with the real cipher. */
function cryption(string $message, string $asciiKey, string $operation, ?array $settings = []): array
{
    if (property_exists('DB', 'cipherError') && DB::$cipherError !== false) {
        return ['string' => '', 'error' => DB::$cipherError];
    }
    $key = Key::loadFromAsciiSafeString($asciiKey);
    try {
        return ['string' => $operation === 'encrypt' ? Crypto::encrypt($message, $key) : Crypto::decrypt($message, $key), 'error' => false];
    } catch (WrongKeyOrModifiedCiphertextException $e) {
        return ['string' => '', 'error' => 'wrong_key_or_modified_ciphertext'];
    }
}

/** Generate a real wrapped key for recipient tests. */
function defuse_generate_personal_key(string $password): string
{
    return KeyProtectedByPassword::createRandomPasswordProtectedKey($password)->saveToAsciiSafeString();
}

/** Unlock a real wrapped key, retaining the production failure contract. */
function defuse_validate_personal_key(string $password, string $protected): string
{
    if (property_exists('DB', 'unwrapError') && DB::$unwrapError !== '') {
        return DB::$unwrapError;
    }
    try {
        return KeyProtectedByPassword::loadFromAsciiSafeString($protected)->unlockKey($password)->saveToAsciiSafeString();
    } catch (WrongKeyOrModifiedCiphertextException $e) {
        return 'Error - The saltkey is not the correct one.';
    }
}

/** Isolate concurrent jobs with their own non-default table prefix. */
function prefixTable(string $name): string
{
    return ($GLOBALS['secure_send_test_prefix'] ?? 'fixture_') . $name;
}

/** Authorization semantics are covered by the access and Security Posture suites. */
function securityPostureItemAccessSql(int $userId, string $alias): string
{
    return property_exists('DB', 'access') && !DB::$access ? '(1 = 0)' : '(1 = 1)';
}

/** Independently model the authenticated sender's item key. */
function decryptUserObjectKeyWithMigration(...$arguments): string
{
    return DB::$objectKey;
}

/** Item RSA/AES formats have their own cryptography tests. */
function teampassDecryptPasswordValue(...$arguments): string
{
    return DB::$password;
}

/** Exercise transactional audit writes, including failures, through the database adapter. */
function logItems(array $settings, int $itemId, string $label, int $userId, string $action, ...$rest): void
{
    DB::insert(prefixTable('send_audit'), ['item_id' => $itemId, 'action' => $action]);
}

/** Mirror the production cache deletion through the transactional database adapter. */
function updateCacheTable(string $action, ?int $itemId = null, ?int $authorId = null): void
{
    if ($action !== 'delete_value' || $itemId === null) {
        throw new LogicException('Unexpected cache operation');
    }
    DB::delete(prefixTable('cache'), 'id = %i', $itemId);
}

/** Model the folder counter write; ancestor propagation has its own helper tests. */
function adjustFolderItemsCounter(int $folderId, int $delta): void
{
    DB::query('UPDATE ' . prefixTable('nested_tree') . ' SET nb_items_in_folder = GREATEST(0, nb_items_in_folder + %i) WHERE id = %i', $delta, $folderId);
}

/** WebSocket transport is outside the recipient transaction tests. */
function emitItemEvent(string $action, int $itemId, int $folderId, string $label, string $login, ?int $excludeUserId = null): bool
{
    return true;
}

/** Legacy item syslog transport is independent of the new structured journal. */
function emitItemSyslog(array $settings, int $itemId, string $label, string $action, ?string $login = null, ?string $reason = null): void
{
    if (method_exists('DB', 'inTransaction') && DB::inTransaction()) {
        throw new LogicException('Item audit forwarded before commit');
    }
}

/** Capture metadata forwarding independently from the transaction; transport may fail. */
function send_syslog(string $message, string $host, int|string $port, string $tag): void
{
    if (DB::inTransaction()) {
        throw new LogicException('Audit forwarded before commit');
    }
    if (DB::$failForward) {
        throw new RuntimeException('Synthetic transport failure containing secret-canary');
    }
    DB::$forwarded[] = $message;
}

/** Seed links in the existing storage format; creation-handler authorization has its own suite. */
function secureSendFixtureCreate(array $input = []): array
{
    $secret = bin2hex(random_bytes(32));
    $phrase = $input['passphrase'] ?? '';
    $protected = defuse_generate_personal_key($phrase === '' ? $secret : hash('sha256', $secret . '|' . $phrase));
    $key = defuse_validate_personal_key($phrase === '' ? $secret : hash('sha256', $secret . '|' . $phrase), $protected);
    $type = $input['send_type'] ?? 'item';
    $payload = $type === 'note'
        ? json_encode(array_replace(['title' => '', 'secret' => '', 'note' => '', 'login' => '', 'url' => ''], $input['payload'] ?? []), JSON_THROW_ON_ERROR)
        : ($input['password'] ?? 'secret-at-creation');
    $parameters = ['code' => bin2hex(random_bytes(16)), 'key' => $secret, 'stamp' => (string) time()];
    DB::insert(prefixTable('otv'), [
        'item_id' => $type === 'note' ? null : 123, 'send_type' => $type,
        'originator' => 42, 'code' => $parameters['code'], 'timestamp' => $parameters['stamp'],
        'encrypted' => cryption($payload, $key, 'encrypt')['string'], 'protected_key' => $protected,
        'has_passphrase' => $phrase === '' ? 0 : 1, 'failed_attempts' => 0, 'views' => 0,
        'max_views' => $input['views'] ?? 1, 'time_limit' => time() + 86400, 'shared_globaly' => $input['shared_globaly'] ?? 0,
    ]);
    return ['url' => 'https://vault.example.com/index.php?' . http_build_query(['otv' => 1] + $parameters), 'otv_id' => (int) DB::insertId()];
}
