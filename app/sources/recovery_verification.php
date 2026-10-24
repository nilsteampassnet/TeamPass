<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file recovery_verification.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

/** Exercise the actual master key -> system private key -> item sharekey chain. */
function tpRecoveryVerifyEncryption(array $settings): void
{
    $system = DB::queryFirstRow(
        'SELECT u.pw,u.public_key,k.private_key FROM ' . prefixTable('users') . ' u
         LEFT JOIN ' . prefixTable('user_private_keys') . ' k ON k.user_id=u.id AND k.is_current=1 WHERE u.id=%i', TP_USER_ID
    );
    $password = cryption((string) ($system['pw'] ?? ''), '', 'decrypt', $settings);
    $private = decryptPrivateKey((string) ($password['string'] ?? ''), (string) ($system['private_key'] ?? ''));
    if ($private === '') {
        throw new TeamPassRecoveryException('SYSTEM_PRIVATE_KEY_INVALID');
    }
    $lastId = 0;
    do {
        $items = DB::query(
            'SELECT i.id,i.pw,i.pw_iv,k.increment_id,k.share_key FROM ' . prefixTable('items') . ' i
             LEFT JOIN ' . prefixTable('sharekeys_items') . ' k ON k.object_id=i.id AND k.user_id=%i
             WHERE i.id>%i ORDER BY i.id LIMIT 100', TP_USER_ID, $lastId
        );
        foreach ($items as $item) {
            $lastId = (int) $item['id'];
            if ((string) $item['pw'] === '') {
                continue;
            }
            $key = decryptUserObjectKeyWithMigration((string) ($item['share_key'] ?? ''), $private,
                (string) $system['public_key'], (int) ($item['increment_id'] ?? 0), 'sharekeys_items');
            if ($key === '' || doDataDecryptionWithStatus($item['pw'], $key, (string) ($item['pw_iv'] ?? ''))['success'] !== true) {
                throw new TeamPassRecoveryException('ITEM_DECRYPTION_FAILED');
            }
        }
    } while (count($items) === 100);
}

/** Invalidate restored links with their lifecycle audit in the same transaction. */
function tpRecoveryInvalidateSharedLinks(): void
{
    require_once __DIR__ . '/secure_send_audit.php';
    do {
        DB::startTransaction();
        try {
            $links = DB::query('SELECT * FROM ' . prefixTable('otv') . ' ORDER BY id LIMIT 100 FOR UPDATE');
            foreach ($links as $link) {
                secureSendAudit($link, 'invalidated', 'disaster_recovery');
                DB::delete(prefixTable('otv'), 'id=%i', (int) $link['id']);
            }
            DB::commit();
        } catch (Throwable $exception) {
            DB::rollback();
            throw new TeamPassRecoveryException('RECOVERY_LINK_INVALIDATION_FAILED');
        }
    } while (count($links) === 100);
}
