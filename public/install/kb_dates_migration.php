<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License v3.0.
 * See <https://www.gnu.org/licenses/>.
 */

require_once __DIR__ . '/../../app/sources/kb_dates.php';

/** Add durable article dates and fill unknown values from retained, attributable logs. */
function kbUpgradeArticleDates(mysqli $connection, string $articleTable, string $miscTable): void
{
    $articleTable = '`' . str_replace('`', '``', $articleTable) . '`';
    $miscTable = '`' . str_replace('`', '``', $miscTable) . '`';
    foreach (['created_at', 'updated_at'] as $column) {
        $columns = $connection->query('SHOW COLUMNS FROM ' . $articleTable . " WHERE Field = '" . $column . "'");
        if ($columns === false) {
            throw new RuntimeException('Unable to inspect article dates');
        }
        $exists = $columns->num_rows > 0;
        $columns->free();
        if (!$exists && $connection->query('ALTER TABLE ' . $articleTable
            . ' ADD COLUMN `' . $column . '` BIGINT UNSIGNED NULL DEFAULT NULL') === false) {
            throw new RuntimeException('Unable to add article dates');
        }
    }

    // The type/intitule index excludes view events before their JSON reaches PHP.
    // Stream eligible events, retaining only two timestamps per article in memory.
    $logs = $connection->query('SELECT valeur, created_at FROM ' . $miscTable
        . " WHERE type = 'kb_log' AND (intitule LIKE 'kb_log_at_creation%'
            OR intitule LIKE 'kb_log_at_modification%')", MYSQLI_USE_RESULT);
    if ($logs === false) {
        throw new RuntimeException('Unable to read article date history');
    }
    $rows = (static function () use ($logs): Generator {
        while ($row = $logs->fetch_assoc()) {
            yield $row;
        }
    })();
    $dates = kbArticleDatesFromLogs($rows);
    $logs->free();

    $statement = $connection->prepare('UPDATE ' . $articleTable . '
        SET created_at = COALESCE(created_at, ?), updated_at = COALESCE(updated_at, ?)
        WHERE id = ?');
    if ($statement === false) {
        throw new RuntimeException('Unable to prepare article date migration');
    }
    try {
        foreach ($dates as $id => $articleDates) {
            $created = $articleDates['created_at'];
            $updated = $articleDates['updated_at'];
            $statement->bind_param('iii', $created, $updated, $id);
            if ($statement->execute() === false) {
                throw new RuntimeException('Unable to restore article dates');
            }
        }
    } finally {
        $statement->close();
    }
}
