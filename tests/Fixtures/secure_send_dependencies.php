<?php

declare(strict_types=1);

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
    try {
        return KeyProtectedByPassword::loadFromAsciiSafeString($protected)->unlockKey($password)->saveToAsciiSafeString();
    } catch (WrongKeyOrModifiedCiphertextException $e) {
        return 'Error - Wrong passphrase';
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

/** WebSocket transport is outside the recipient transaction tests. */
function emitItemEvent(string $action, int $itemId, int $folderId, string $label, string $login, ?int $excludeUserId = null): bool
{
    return true;
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
