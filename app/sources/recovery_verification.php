<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file recovery_verification.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

/** Verify one bounded batch, preserving diagnostics without disclosing keys or item identifiers. */
function tpRecoveryVerifyEncryption(array $settings, array $previous = []): array
{
    $system = tpUserKeyDiagnosis($settings);
    $status = $system['status'] === 'ok' ? 'ok' : 'unusable';
    if ($previous !== [] && ($previous['tp_key_status'] !== $status || $previous['diagnostic'] !== $system['status'])) {
        throw new TeamPassRecoveryException('RECOVERY_INPUT_CHANGED');
    }
    $report = $previous ?: [
        'tp_key_status' => $status, 'diagnostic' => $system['status'],
        'total_items' => (int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('items') . ' WHERE pw<>%s', ''),
        'checked_items' => 0, 'missing_tp_sharekeys' => 0, 'skipped_items' => 0,
        'last_id' => 0, 'complete' => false,
    ];
    if ($status === 'unusable') {
        // User sharekeys can still be intact. Publication requires an explicit
        // acknowledgement; never replace their keys or try to repair them here.
        $report['missing_tp_sharekeys'] = (int) DB::queryFirstField(
            'SELECT COUNT(*) FROM ' . prefixTable('items') . ' i
             LEFT JOIN ' . prefixTable('sharekeys_items') . ' k ON k.object_id=i.id AND k.user_id=%i
             WHERE i.pw<>%s AND k.increment_id IS NULL', TP_USER_ID, ''
        );
        $report['skipped_items'] = $report['total_items'] - $report['missing_tp_sharekeys'];
        $report['complete'] = true;
        return $report;
    }
    $items = DB::query(
        'SELECT i.id,i.pw,i.pw_iv,k.increment_id,k.share_key FROM ' . prefixTable('items') . ' i
         LEFT JOIN ' . prefixTable('sharekeys_items') . ' k ON k.object_id=i.id AND k.user_id=%i
         WHERE i.id>%i AND i.pw<>%s ORDER BY i.id LIMIT 100', TP_USER_ID, $report['last_id'], ''
    );
    foreach ($items as $item) {
        $report['last_id'] = (int) $item['id'];
        if ($item['increment_id'] === null) {
            // Historical personal items and a rebuilt TP account legitimately
            // have no TP row. An existing but unreadable row remains fatal.
            $report['missing_tp_sharekeys']++;
            continue;
        }
        $key = decryptUserObjectKeyWithMigration((string) $item['share_key'], $system['private_key'],
            $system['public_key'], (int) $item['increment_id'], 'sharekeys_items');
        if ($key === '' || doDataDecryptionWithStatus($item['pw'], $key, (string) ($item['pw_iv'] ?? ''))['success'] !== true) {
            throw new TeamPassRecoveryException('ITEM_DECRYPTION_FAILED');
        }
        $report['checked_items']++;
    }
    $report['complete'] = count($items) < 100;
    if ($report['complete'] && $report['checked_items'] + $report['missing_tp_sharekeys'] !== $report['total_items']) {
        throw new TeamPassRecoveryException('RECOVERY_INPUT_CHANGED');
    }
    return $report;
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
