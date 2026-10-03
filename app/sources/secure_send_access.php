<?php

declare(strict_types=1);

require_once __DIR__ . '/otp.functions.php';

/**
 * Access checks shared by the existing Secure Send handlers.
 * This file is part of TeamPass, distributed under the GPL-3.0 license.
 */

/**
 * Read an active item under the originator's current permissions.
 *
 * A sharekey alone does not grant access. The detached resolver covers folder
 * grants, denials, personal trees and item restrictions without changing sessions.
 *
 * @param int $itemId Shared item
 * @param int $userId Originator, never the unauthenticated recipient
 * @param bool $lock Lock the item when called inside a redemption transaction
 * @return array Accessible item, or an empty array
 */
function secureSendReadItem(int $itemId, int $userId, bool $lock = false): array
{
    if ($itemId <= 0 || $userId <= 0) {
        return [];
    }
    $user = DB::queryFirstRow(
        'SELECT id, admin FROM ' . prefixTable('users') . ' WHERE id = %i AND disabled = 0 AND deleted_at IS NULL',
        $userId
    );
    if (empty($user) || (int) $user['admin'] === 1) {
        return [];
    }
    return DB::queryFirstRow(
        'SELECT i.* FROM ' . prefixTable('items') . ' AS i
        WHERE i.id = %i AND i.inactif = 0 AND (i.deleted_at IS NULL OR i.deleted_at = 0)
        AND ' . securityPostureItemAccessSql($userId, 'i') . ($lock ? ' FOR UPDATE' : ''),
        $itemId
    ) ?: [];
}

/**
 * Resolve the public identity of a link originator after checking current eligibility.
 *
 * The public recipient page must never disclose a login or an email address. A missing
 * display name is valid: the instance entity may still identify the sender's organisation.
 * Item links additionally require the originator to retain access to the active item.
 *
 * @param array $link Stored Secure Send link
 * @return array{display_name:string}|null Public identity, or null when the link must be denied
 */
function secureSendPublicSender(array $link): ?array
{
    $originator = (int) ($link['originator'] ?? 0);
    if ($originator <= 0) {
        return null;
    }

    if (($link['send_type'] ?? 'item') !== 'note'
        && secureSendReadItem((int) ($link['item_id'] ?? 0), $originator) === []
    ) {
        return null;
    }

    $user = DB::queryFirstRow(
        'SELECT name, lastname FROM ' . prefixTable('users') . '
        WHERE id = %i AND disabled = 0 AND deleted_at IS NULL',
        $originator
    );
    if (empty($user)) {
        return null;
    }

    return [
        'display_name' => trim(trim((string) ($user['name'] ?? '')) . ' ' . trim((string) ($user['lastname'] ?? ''))),
    ];
}

/**
 * Decrypt an authorized item's password or fail before any link can be inserted.
 *
 * @param array $item Item returned by secureSendReadItem()
 * @param int $userId Authenticated sender
 * @param string $privateKey Sender's private key
 * @param string $publicKey Sender's public key
 * @return string Password, including a legitimately empty password
 * @throws InvalidArgumentException When the sharekey or password cannot be decrypted
 */
function secureSendItemPassword(array $item, int $userId, string $privateKey, string $publicKey): string
{
    $key = DB::queryFirstRow(
        'SELECT share_key, increment_id FROM ' . prefixTable('sharekeys_items') . '
        WHERE user_id = %i AND object_id = %i',
        $userId,
        (int) $item['id']
    );
    if (empty($key['share_key'])) {
        throw new InvalidArgumentException('cannot_decrypt');
    }
    try {
        $objectKey = decryptUserObjectKeyWithMigration(
            $key['share_key'], $privateKey, $publicKey, (int) $key['increment_id'], 'sharekeys_items'
        );
        if ($objectKey === '') {
            throw new InvalidArgumentException('cannot_decrypt');
        }
        $password = $item['pw'] === '' ? '' : teampassDecryptPasswordValue(
            $item['pw'], $objectKey, (int) ($item['pw_len'] ?? 0), (string) ($item['pw_iv'] ?? '')
        );
        if ($password === '' && (int) ($item['pw_len'] ?? 0) > 0) {
            throw new InvalidArgumentException('cannot_decrypt');
        }
        return $password;
    } catch (Throwable $e) {
        throw new InvalidArgumentException('cannot_decrypt');
    }
}

/**
 * Read and decrypt the enabled TOTP profile copied into a Secure Send snapshot.
 *
 * This must only be called after secureSendReadItem() has authorized the sender.
 * The returned secret is encrypted again as part of the link payload and must
 * never be exposed directly to the recipient.
 *
 * @param int $itemId Authorized item identifier
 * @return array{secret:string, algorithm:string, digits:int, period:int}|null
 * @throws InvalidArgumentException With totp_unusable when an enabled profile cannot be decrypted or used
 */
function secureSendItemTotp(int $itemId): ?array
{
    if ($itemId <= 0) {
        return null;
    }

    $stored = DB::queryFirstRow(
        'SELECT secret, algorithm, digits, period FROM ' . prefixTable('items_otp') . '
        WHERE item_id = %i AND enabled = 1',
        $itemId
    );
    if (empty($stored) || empty($stored['secret'])) {
        return null;
    }

    try {
        $decrypted = cryption((string) $stored['secret'], '', 'decrypt');
        if (!empty($decrypted['error']) || !isset($decrypted['string']) || $decrypted['string'] === '') {
            throw new RuntimeException('TOTP decryption failed');
        }
        $totp = createItemTotp(
            (string) $decrypted['string'],
            (string) ($stored['algorithm'] ?? ITEM_TOTP_DEFAULT_ALGORITHM),
            (int) ($stored['digits'] ?? ITEM_TOTP_DEFAULT_DIGITS),
            (int) ($stored['period'] ?? ITEM_TOTP_DEFAULT_PERIOD)
        );
        // Force decoding now so an unusable secret cannot create a partial link.
        $totp->now();

        return [
            'secret' => $totp->getSecret(),
            'algorithm' => $totp->getDigest(),
            'digits' => $totp->getDigits(),
            'period' => $totp->getPeriod(),
        ];
    } catch (Throwable $e) {
        throw new InvalidArgumentException('totp_unusable');
    }
}

/**
 * Hide item links and labels after the sender loses access; preserve standalone notes.
 *
 * @param array $rows Links already restricted to the authenticated originator
 * @param int $userId Authenticated sender
 * @return array Links whose item is still accessible, and standalone notes
 */
function secureSendFilterLinks(array $rows, int $userId): array
{
    $visible = [];
    foreach ($rows as $row) {
        if (($row['send_type'] ?? 'item') !== 'note') {
            $item = secureSendReadItem((int) ($row['item_id'] ?? 0), $userId);
            if ($item === []) {
                continue;
            }
            $row['item_label'] = $item['label'];
        }
        $visible[] = $row;
    }
    return $visible;
}
