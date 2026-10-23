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
 * DB-free bookkeeping of the attachments a session uploaded but has not
 * attached to their item yet.
 *
 * An attachment is written to the files table as soon as it is uploaded,
 * unconfirmed, under the item it targets: the item id when editing, or the
 * staging id the browser generates for an item not created yet. new_item and
 * confirm_attachments then attach it. Both ids come from the client, so the
 * rows sitting under one of them prove nothing about who put them there: only
 * the files recorded here, by the session that uploaded them, may be attached
 * (GHSA-r6r4-5mr3-rppm).
 *
 * The map is kept in session under user-pending_attachments, as
 * file id => target id.
 *
 * @file      attachment_pending_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Record a file the session has just uploaded for a target.
 *
 * Entries are only removed once attached or discarded, so an abandoned form
 * leaves its entries behind: the oldest are dropped beyond $limit.
 *
 * @param array $pending  Current map (file id => target id)
 * @param int   $fileId   files.id of the uploaded file
 * @param int   $targetId Item id, or staging id of the item being created
 * @param int   $limit    Maximum number of entries kept
 *
 * @return array<int, int> The updated map
 */
function attachmentPendingRecord(array $pending, int $fileId, int $targetId, int $limit = 500): array
{
    if ($fileId <= 0 || $targetId <= 0) {
        return $pending;
    }

    unset($pending[$fileId]);
    $pending[$fileId] = $targetId;

    if ($limit > 0 && count($pending) > $limit) {
        $pending = array_slice($pending, -$limit, null, true);
    }

    return $pending;
}

/**
 * List the files the session uploaded for a target.
 *
 * @param array $pending  Current map (file id => target id)
 * @param int   $targetId Item id, or staging id of the item being created
 *
 * @return array<int, int> File ids, possibly empty
 */
function attachmentPendingFileIds(array $pending, int $targetId): array
{
    if ($targetId <= 0) {
        return [];
    }

    $fileIds = [];
    foreach ($pending as $fileId => $target) {
        if ((int) $fileId > 0 && (int) $target === $targetId) {
            $fileIds[] = (int) $fileId;
        }
    }

    return $fileIds;
}

/**
 * Forget every file recorded for a target, once attached or discarded.
 *
 * @param array $pending  Current map (file id => target id)
 * @param int   $targetId Item id, or staging id of the item being created
 *
 * @return array<int, int> The updated map
 */
function attachmentPendingForget(array $pending, int $targetId): array
{
    foreach ($pending as $fileId => $target) {
        if ((int) $target === $targetId) {
            unset($pending[$fileId]);
        }
    }

    return $pending;
}
