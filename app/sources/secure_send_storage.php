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
 * @file      secure_send_storage.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

require_once __DIR__ . '/secure_send_audit.php';

/**
 * Store an already encrypted, authorized link and its creation evidence atomically.
 *
 * The authenticated handler owns session/CSRF, input, policy and item-access checks.
 * This helper never decrypts or returns the stored ciphertext/key.
 *
 * @param array $link Validated link with encrypted payload
 * @param array $settings Application settings
 * @return int New link identifier
 * @throws Throwable When the link or audit cannot be persisted
 */
function secureSendStoreLink(array $link, array $settings): int
{
    DB::startTransaction();
    try {
        DB::insert(prefixTable('otv'), $link);
        $id = (int) DB::insertId();
        $link['id'] = $id;
        $event = secureSendAudit($link, 'created', '', (int) $link['originator']);
        DB::commit();
    } catch (Throwable $e) {
        DB::rollback();
        throw $e;
    }
    secureSendEmitAudit($event, $settings);
    return $id;
}

/**
 * Lock, check ownership and revoke a link with durable evidence in one transaction.
 *
 * @param int $sendId Link identifier from the authenticated handler
 * @param int $userId Authenticated user (never supplied by the request body)
 * @param array $settings Application settings
 * @return bool Whether the owned link was revoked
 * @throws Throwable When revocation or audit fails
 */
function secureSendRevokeLink(int $sendId, int $userId, array $settings): bool
{
    if ($sendId <= 0 || $userId <= 0) {
        return false;
    }
    DB::startTransaction();
    try {
        $link = DB::queryFirstRow(
            'SELECT id, originator, item_id, send_type, timestamp, has_passphrase,
                shared_globaly, max_views, time_limit, views, failed_attempts
            FROM ' . prefixTable('otv') . ' WHERE id = %i FOR UPDATE',
            $sendId
        ) ?: [];
        if ($link === [] || (int) $link['originator'] !== $userId) {
            DB::rollback();
            return false;
        }
        $event = secureSendAudit($link, 'revoked', 'sender_revoked', $userId);
        DB::delete(prefixTable('otv'), 'id = %i', $sendId);
        DB::commit();
    } catch (Throwable $e) {
        DB::rollback();
        throw $e;
    }
    secureSendEmitAudit($event, $settings);
    return true;
}

/**
 * Audit and remove a bounded batch of expired links, including historical links.
 *
 * Expiration time is separate from the observation/purge time. No synthetic creation
 * events are generated for pre-upgrade links. Locks serialize cleanup with revocation
 * and redemption; repeated cleanup cannot duplicate an expiration event.
 *
 * @param array $settings Application settings
 * @param int|null $now Cutoff/observation time
 * @return int Number of expired links removed (at most 100 per call)
 * @throws Throwable When any deletion or audit fails (the entire batch is rolled back)
 */
function secureSendPurgeExpiredLinks(array $settings, ?int $now = null): int
{
    $now ??= time();
    DB::startTransaction();
    try {
        $links = DB::query(
            'SELECT id, originator, item_id, send_type, timestamp, has_passphrase,
                shared_globaly, max_views, time_limit, views, failed_attempts
            FROM ' . prefixTable('otv') . ' WHERE time_limit < %i
            ORDER BY id ASC LIMIT 100 FOR UPDATE',
            $now
        );
        $events = [];
        foreach ($links as $link) {
            $events[] = secureSendAudit($link, 'expired', 'deadline_elapsed', null, $now);
            DB::delete(prefixTable('otv'), 'id = %i', (int) $link['id']);
        }
        DB::commit();
    } catch (Throwable $e) {
        DB::rollback();
        throw $e;
    }
    foreach ($events as $event) {
        secureSendEmitAudit($event, $settings);
    }
    return count($links);
}
