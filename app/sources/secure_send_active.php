<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of the TeamPass project, distributed under the GNU GPL-3.0.
 * Copyright 2009-2026 Teampass.net. See <https://www.gnu.org/licenses/>.
 *
 * Read-only inventory of currently usable Secure Send links.
 */

/**
 * Read active link metadata under each sender's current access rights.
 *
 * The caller enforces session/page permissions and derives the personal scope from
 * the session. A null originator is reserved for the administrator's global count.
 * No payload, URL credential, encryption key or item secret is selected. Item access
 * is resolved once per sender, rather than once for every outstanding link.
 *
 * @param array $settings Current application settings
 * @param int|null $originatorId Authenticated sender, or null for the global inventory
 * @param int|null $itemId Optional item scope (never includes standalone notes)
 * @param int|null $now Observation timestamp
 * @param bool $withLabels Include authorized item labels for the sender's list
 * @return array<int,array<string,mixed>> Active metadata, newest first
 */
function secureSendActiveLinks(
    array $settings,
    ?int $originatorId = null,
    ?int $itemId = null,
    ?int $now = null,
    bool $withLabels = false
): array {
    if ($withLabels && $originatorId === null) {
        throw new InvalidArgumentException('Item labels require a personal Secure Send scope');
    }
    if ((int) ($settings['otv_is_enabled'] ?? 0) !== 1
        || ($originatorId !== null && $originatorId <= 0)
        || ($itemId !== null && $itemId <= 0)
    ) {
        return [];
    }
    $now ??= time();
    $where = "o.id > 0 AND o.time_limit > %i AND o.views < o.max_views AND o.failed_attempts < 5
        AND o.send_type IN ('item', 'item_v2', 'note')
        AND (o.has_passphrase <> 1 OR (o.protected_key IS NOT NULL AND o.protected_key <> ''))
        AND u.disabled = 0 AND u.deleted_at IS NULL";
    $parameters = [$now];
    if ((int) ($settings['secure_send_allow_notes'] ?? 0) !== 1) {
        $where .= " AND o.send_type <> 'note'";
    }
    if ((int) ($settings['secure_send_require_passphrase'] ?? 0) === 1) {
        $where .= ' AND o.has_passphrase = 1';
    }
    if ($originatorId !== null) {
        $where .= ' AND o.originator = %i';
        $parameters[] = $originatorId;
    }
    if ($itemId !== null) {
        $where .= " AND o.item_id = %i AND o.send_type <> 'note'";
        $parameters[] = $itemId;
    }
    $rows = DB::query(
        'SELECT o.id, o.originator, o.item_id, o.send_type, o.has_passphrase,
            o.views, o.max_views, o.time_limit, u.admin AS sender_admin
        FROM ' . prefixTable('otv') . ' o
        INNER JOIN ' . prefixTable('users') . ' u ON u.id = o.originator
        WHERE ' . $where . ' ORDER BY o.id DESC',
        ...$parameters
    );
    $itemsBySender = [];
    foreach ($rows as $row) {
        if ($row['send_type'] !== 'note' && (int) $row['sender_admin'] !== 1) {
            $itemsBySender[(int) $row['originator']][] = (int) $row['item_id'];
        }
    }
    $accessible = [];
    foreach ($itemsBySender as $senderId => $itemIds) {
        $itemWhere = 'i.id IN %li AND i.inactif = 0 AND (i.deleted_at IS NULL OR i.deleted_at = 0)
            AND ' . securityPostureItemAccessSql($senderId, 'i');
        $itemParameters = [array_values(array_unique($itemIds))];
        if ((int) ($settings['enable_delete_after_consultation'] ?? 0) === 1) {
            $itemWhere .= ' AND NOT EXISTS (SELECT 1 FROM ' . prefixTable('automatic_del') . ' ad
                WHERE ad.item_id = i.id AND ad.del_enabled = 1
                AND ((ad.del_type = 1 AND ad.del_value <= 0) OR (ad.del_type = 2 AND ad.del_value <= %i)))';
            $itemParameters[] = $now;
        }
        foreach (DB::query(
            'SELECT i.id' . ($withLabels ? ', i.label' : '') . ' FROM ' . prefixTable('items') . ' i WHERE ' . $itemWhere,
            ...$itemParameters
        ) as $item) {
            $accessible[$senderId][(int) $item['id']] = $withLabels ? (string) $item['label'] : true;
        }
    }
    $active = [];
    foreach ($rows as $row) {
        if ($row['send_type'] !== 'note') {
            $label = $accessible[(int) $row['originator']][(int) $row['item_id']] ?? null;
            if ($label === null) {
                continue;
            }
            if ($withLabels) {
                $row['item_label'] = html_entity_decode((string) $label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        unset($row['sender_admin']);
        $active[] = $row;
    }
    return $active;
}

/**
 * Count currently usable links without loading labels or decrypted content.
 *
 * @param array $settings Current application settings
 * @param int|null $originatorId Authenticated sender, or null for the admin-only total
 * @param int|null $itemId Optional item scope
 * @param int|null $now Observation timestamp
 * @return int Active links at the observation time
 */
function secureSendCountActiveLinks(array $settings, ?int $originatorId = null, ?int $itemId = null, ?int $now = null): int
{
    return count(secureSendActiveLinks($settings, $originatorId, $itemId, $now));
}
