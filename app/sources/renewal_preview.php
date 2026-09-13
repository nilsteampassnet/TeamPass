<?php

declare(strict_types=1);

/**
 * Preview a folder's renewal policy using current read permissions and password history.
 * Reads metadata only; moving an item never resets its password age.
 */
function renewalPreview(int $userId, int $folderId, array $itemIds, bool $creation, array $settings): array
{
    $denied = ['error' => true];
    $user = DB::queryFirstRow('SELECT admin FROM ' . prefixTable('users') . ' WHERE id = %i AND deleted_at IS NULL', $userId);
    if ($user === null || (int) $user['admin'] === 1
        || !in_array($folderId, securityPostureAuthorizedFolderIds($userId), true)
    ) {
        return $denied;
    }

    $folder = DB::queryFirstRow('SELECT renewal_period FROM ' . prefixTable('nested_tree') . ' WHERE id = %i', $folderId);
    if ($folder === null) {
        return $denied;
    }
    $enabled = (int) ($settings['activate_expiration'] ?? 0) === 1;
    $days = $enabled ? max(0, (int) $folder['renewal_period']) : 0;
    $now = time();
    $result = ['error' => false, 'enabled' => $enabled, 'days' => $days, 'creation' => $creation, 'items' => []];

    if ($creation) {
        if ($itemIds !== []) {
            return $denied;
        }
        $rows = [['id' => 0, 'label' => '', 'last_relevant_date' => $now]];
    } elseif ($itemIds !== []) {
        // Reject malformed or inaccessible selections as a whole, without leaking item metadata.
        $ids = [];
        foreach ($itemIds as $id) {
            $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                return $denied;
            }
            $ids[] = $id;
        }
        $ids = array_values(array_unique($ids));
        $rows = DB::query(
            'SELECT i.id, i.label,
                COALESCE(NULLIF(l.last_relevant_date, 0), NULLIF(CAST(i.created_at AS UNSIGNED), 0), 0) AS last_relevant_date
            FROM ' . prefixTable('items') . ' AS i
            LEFT JOIN (
                SELECT id_item, MAX(CAST(date AS UNSIGNED)) AS last_relevant_date
                FROM ' . prefixTable('log_items') . '
                WHERE id_item IN %li AND (action = %s OR (action = %s AND raison LIKE %s))
                GROUP BY id_item
            ) AS l ON l.id_item = i.id
            WHERE i.id IN %li AND i.inactif = %i AND i.deleted_at IS NULL
                AND ' . securityPostureItemAccessSql($userId),
            $ids, 'at_creation', 'at_modification', 'at_pw%', $ids, 0
        );
        if (count($rows) !== count($ids)) {
            return $denied;
        }
    } else {
        $rows = [];
    }

    foreach ($rows as $row) {
        $baseDate = (int) $row['last_relevant_date'];
        $due = $days > 0 && $baseDate > 0 ? $baseDate + $days * TP_ONE_DAY_SECONDS : null;
        $result['items'][] = [
            'id' => (int) $row['id'],
            'label' => (string) $row['label'],
            'due_at' => $due,
            'due_date' => $due === null ? '' : date($settings['date_format'] ?? 'Y-m-d', $due),
            'expired' => $due !== null && $due < $now,
        ];
    }
    return $result;
}
