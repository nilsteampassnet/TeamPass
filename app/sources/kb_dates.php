<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License v3.0.
 * See <https://www.gnu.org/licenses/>.
 */

/** Accept only positive Unix timestamps; missing legacy dates stay unknown. */
function kbDateTimestamp(mixed $value): ?int
{
    if (!is_int($value) && !is_string($value)) {
        return null;
    }
    $timestamp = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $timestamp === false ? null : $timestamp;
}

/** Keep a same-second edit, but never display a modification before creation. */
function kbArticleDates(array $article): array
{
    $created = kbDateTimestamp($article['created_at'] ?? null);
    $updated = kbDateTimestamp($article['updated_at'] ?? null);
    if ($created !== null && $updated !== null && $updated < $created) {
        $updated = null;
    }

    return ['created_at' => $created, 'updated_at' => $updated];
}

/** Compare persisted article values and associations independently of their order. */
function kbArticleChangedFields(array $before, array $after, array $oldItems, array $newItems): array
{
    $changes = [];
    foreach (['label', 'category', 'description'] as $field) {
        if ((string) ($before[$field] ?? '') !== (string) ($after[$field] ?? '')) {
            $changes[] = $field;
        }
    }
    foreach (['anyone_can_modify', 'allow_comments'] as $field) {
        if ((int) ($before[$field] ?? 0) !== (int) ($after[$field] ?? 0)) {
            $changes[] = $field;
        }
    }
    sort($oldItems);
    sort($newItems);
    if ($oldItems !== $newItems) {
        $changes[] = 'associated_items';
    }

    return $changes;
}

/** Format article and comment timestamps using the instance's date and time settings. */
function kbFormatTimestamp(int $timestamp, array $settings): string
{
    $dateFormat = (string) ($settings['date_format'] ?? 'Y-m-d');
    $timeFormat = (string) ($settings['time_format'] ?? 'H:i');

    return date(trim($dateFormat . ' ' . $timeFormat), $timestamp);
}

/** Build full and compact date labels with the instance's configured formats. */
function kbArticleDatePayload(array $article, array $settings): array
{
    $dates = kbArticleDates($article);
    $dateFormat = (string) ($settings['date_format'] ?? 'Y-m-d');
    foreach (['created', 'updated'] as $kind) {
        $timestamp = $dates[$kind . '_at'];
        $dates[$kind . '_at_label'] = $timestamp === null ? '' : kbFormatTimestamp($timestamp, $settings);
        $dates[$kind . '_date_label'] = $timestamp === null ? '' : date($dateFormat, $timestamp);
        $dates[$kind . '_at_iso'] = $timestamp === null ? '' : gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }

    return $dates;
}

/**
 * Recover only identifiable creation events and explicit article changes from retained logs.
 * Empty modification reasons can be no-op saves; comments, views and restores are excluded.
 * A later creation for the same id starts a new lifetime rather than inheriting older dates.
 */
function kbArticleDatesFromLogs(iterable $rows): array
{
    $dates = [];
    $articleChanges = ['label', 'category', 'description', 'anyone_can_modify', 'allow_comments',
        'associated_items', 'attachments_upload', 'attachments_delete'];
    foreach ($rows as $row) {
        $event = json_decode((string) ($row['valeur'] ?? ''), true);
        if (!is_array($event)) {
            continue;
        }
        $id = kbDateTimestamp($event['kb_id'] ?? null);
        $timestamp = kbDateTimestamp($event['date'] ?? $row['created_at'] ?? null);
        if ($id === null || $timestamp === null) {
            continue;
        }
        $field = null;
        if (($event['action'] ?? '') === 'at_creation') {
            $field = 'created_at';
        } elseif (($event['action'] ?? '') === 'at_modification' && is_string($event['reason'] ?? null)) {
            $reasons = array_map('trim', explode(',', $event['reason']));
            if (array_diff($reasons, $articleChanges) === []) {
                $field = 'updated_at';
            }
        }
        if ($field !== null) {
            $dates[$id][$field] = max($dates[$id][$field] ?? 0, $timestamp);
        }
    }
    foreach ($dates as $id => $articleDates) {
        $dates[$id] = kbArticleDates($articleDates);
    }

    return $dates;
}
